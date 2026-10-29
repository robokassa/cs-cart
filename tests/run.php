<?php
require (getenv('MODULE_ROOT') ?: dirname(__DIR__)) . '/app/addons/csc_robokassa/Tygh/Payments/Processors/CscRobokassa.php';
function __($key) { return $key; }
function fn_get_tax($id) { return ['tax_type' => 'vat20']; }
$results = [];
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function run_case($name, $callback) {
    global $results;
    $warnings = [];
    set_error_handler(function ($level, $message) use (&$warnings) {
        $warnings[] = $message;
        return true;
    });
    try {
        $detail = $callback();
        check(!$warnings, implode('; ', $warnings));
        $results[] = ['name' => $name, 'status' => 'PASS', 'detail' => $detail];
    } catch (Throwable $error) {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'error' => $error->getMessage()];
    }
    restore_error_handler();
}
function receipt($shipping) {
    $class = new ReflectionClass('Tygh\\Payments\\Processors\\CscRobokassa');
    $processor = $class->newInstanceWithoutConstructor();
    foreach (['_country' => 'RU', '_order_info' => array_merge(['products' => []], $shipping)] as $key => $value) {
        $property = $class->getProperty($key);
        $property->setAccessible(true);
        $property->setValue($processor, $value);
    }
    return $processor->getReceipt();
}
run_case('shipping_missing_taxes', function () {
    $result = receipt(['shipping' => [['rate' => 28]]]);
    check($result['items'][0]['sum'] === '28.00' && $result['items'][0]['tax'] === 'none', 'неверный чек доставки без налога');
});
run_case('shipping_empty_taxes', function () {
    $result = receipt(['shipping' => [['rate' => 0, 'taxes' => []]]]);
    check($result['items'][0]['sum'] === '0.00' && $result['items'][0]['tax'] === 'none', 'неверный чек бесплатной доставки');
});
run_case('shipping_tax_then_no_tax', function () {
    $result = receipt(['shipping' => [
        ['rate' => 100, 'taxes' => [7 => ['tax_subtotal' => 20]]],
        ['rate' => 50],
    ]]);
    check($result['items'][0]['sum'] === '120.00' && $result['items'][0]['tax'] === 'vat20', 'не сохранен налог доставки');
    check($result['items'][1]['sum'] === '50.00' && $result['items'][1]['tax'] === 'none', 'налог предыдущей доставки перенесен в следующую');
});
run_case('no_shipping', function () { check(receipt([]) === [], 'неожиданная доставка'); });
function signed_request($split = false) {
    $request = ['OutSum' => '100.00', 'InvId' => $split ? '90000001' : '42', 'shp_label' => 'official_cscart'];
    $string = $request['OutSum'] . ':' . $request['InvId'] . ':test-password-2:shp_label=official_cscart';
    if ($split) {
        $request['OrderID'] = '42';
        $string .= ':OrderID=42';
    }
    $request['SignatureValue'] = strtoupper(md5($string));
    return $request;
}
function callback($request, $options = []) {
    $state = tempnam(sys_get_temp_dir(), 'rk-callback-');
    $fixture = array_merge(['request' => $request, 'mode' => 'result'], $options);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/callback-fixture.php') . ' '
        . escapeshellarg(base64_encode(json_encode($fixture))) . ' ' . escapeshellarg($state);
    $pipes = [];
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $result = json_decode(file_get_contents($state), true);
    unlink($state);
    check($exit === 0 && $errors === '', 'ошибка обработчика: ' . $errors);
    $result['body'] = $body;
    return $result;
}
foreach ([false, true] as $split) {
    $prefix = $split ? 'split_' : 'ordinary_';
    run_case($prefix . 'valid_signature', function () use ($split) {
        $request = signed_request($split);
        $request['SignatureValue'] = strtolower($request['SignatureValue']);
        $result = callback($request);
        check($result['calls'] === [['finish', 42, 'P']] && $result['body'] === 'OK42', 'правильное уведомление не принято');
    });
    $mutations = [
        'invalid_signature' => ['SignatureValue' => str_repeat('0', 32)],
        'signature_array' => ['SignatureValue' => ['invalid']],
        'amount_array' => ['OutSum' => ['100.00']],
        'invoice_array' => ['InvId' => ['42']],
        'order_array' => ['OrderID' => ['42']],
        'order_suffix' => ['OrderID' => '42junk'],
        'order_leading_zero' => ['OrderID' => '042'],
        'order_overflow' => ['OrderID' => str_repeat('9', 30)],
        'empty_order' => ['OrderID' => ''],
        'zero_order' => ['OrderID' => '0'],
        'wrong_amount' => ['OutSum' => '1.00'],
    ];
    foreach ($mutations as $name => $changes) {
        run_case($prefix . $name, function () use ($split, $changes) {
            $result = callback(array_merge(signed_request($split), $changes));
            check($result['calls'] === [] && $result['code'] === 400 && strpos($result['body'], 'OK') !== 0, 'неверное уведомление изменило заказ или принято');
        });
    }
    foreach (['return', 'cancel'] as $mode) {
        run_case($prefix . $mode . '_does_not_finish', function () use ($split, $mode) {
            $request = signed_request($split);
            unset($request['SignatureValue']);
            $result = callback($request, ['mode' => $mode]);
            check($result['calls'] === [['route', 42]], 'браузерный возврат изменил состояние оплаты');
        });
    }
}
run_case('other_processor_rejected', function () {
    $result = callback(signed_request(), ['processor' => 'another.php']);
    check($result['calls'] === [] && $result['code'] === 400, 'уведомление принято для другого процессора');
});
run_case('missing_password_rejected', function () {
    $result = callback(signed_request(), ['password' => '']);
    check($result['calls'] === [] && $result['code'] === 400, 'уведомление принято без пароля');
});
echo json_encode(['php' => PHP_VERSION, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit(count(array_filter($results, function ($result) { return $result['status'] !== 'PASS'; })) ? 1 : 0);
