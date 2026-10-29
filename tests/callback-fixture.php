<?php
namespace Tygh\Enum {
    class OrderDataTypes { const PAYMENT_STARTED = 'S'; }
    class OrderStatuses { const FAILED = 'F'; const INCOMPLETED = 'I'; }
}
namespace {
    define('BOOTSTRAP', true);
    define('PAYMENT_NOTIFICATION', true);
    define('ROBOKASSA_TIMEOUT', 0);
    $fixture = json_decode(base64_decode($argv[1]), true);
    $_REQUEST = $fixture['request'];
    $mode = $fixture['mode'];
    $calls = [];
    function db_get_field($query, $order_id) {
        return $order_id === 42 ? 12 : 0;
    }
    function fn_get_processor_data($payment_id) {
        global $fixture;
        return $payment_id ? [
            'processor_script' => isset($fixture['processor']) ? $fixture['processor'] : 'csc_robokassa.php',
            'processor_params' => ['password2' => isset($fixture['password']) ? $fixture['password'] : 'test-password-2', 'status_paid' => 'P'],
        ] : [];
    }
    function __($key) { return $key; }
    function fn_finish_payment($order_id, $response) {
        $GLOBALS['calls'][] = ['finish', $order_id, $response['order_status']];
    }
    function fn_order_placement_routines($action, $order_id, $notify = null) {
        $GLOBALS['calls'][] = ['route', $order_id];
    }
    register_shutdown_function(function () use ($argv) {
        file_put_contents($argv[2], json_encode(['calls' => $GLOBALS['calls'], 'code' => http_response_code()]));
    });
    require (getenv('MODULE_ROOT') ?: dirname(__DIR__)) . '/app/addons/csc_robokassa/payments/csc_robokassa.php';
}
