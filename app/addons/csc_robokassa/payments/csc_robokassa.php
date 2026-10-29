<?php
use Tygh\Payments\Processors\CscRobokassa;
use Tygh\Enum\OrderDataTypes;

defined('BOOTSTRAP') or die('Access denied');

/** @var string $mode **/
if (defined('PAYMENT_NOTIFICATION')) {
    if (!isset($_REQUEST['InvId']) || !is_string($_REQUEST['InvId'])
        || !preg_match('/^[1-9][0-9]*$/D', $_REQUEST['InvId'])
        || (isset($_REQUEST['OrderID']) && (!is_string($_REQUEST['OrderID'])
            || !preg_match('/^[1-9][0-9]*$/D', $_REQUEST['OrderID'])))) {
        http_response_code(400);
        die('Access denied');
    }

    $order_reference = isset($_REQUEST['OrderID']) ? $_REQUEST['OrderID'] : $_REQUEST['InvId'];
    $order_id = filter_var($order_reference, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($order_id === false) {
        http_response_code(400);
        die('Access denied');
    }

    $payment_id = db_get_field("SELECT payment_id FROM ?:orders WHERE order_id = ?i", $order_id);
    $processor_data = fn_get_processor_data($payment_id);
    if (empty($processor_data['processor_script']) || $processor_data['processor_script'] !== 'csc_robokassa.php') {
        http_response_code(400);
        die('Access denied');
    }

    if ($mode === 'result') {
        if (!isset($_REQUEST['SignatureValue'], $_REQUEST['OutSum'])
            || !is_string($_REQUEST['SignatureValue']) || !is_string($_REQUEST['OutSum'])
            || !preg_match('/^[a-f0-9]{32}$/iD', $_REQUEST['SignatureValue'])
            || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $_REQUEST['OutSum'])
            || empty($processor_data['processor_params']['password2'])) {
            http_response_code(400);
            die('Access denied');
        }

        $string = $_REQUEST['OutSum'] . ':' . $_REQUEST['InvId'] . ':' . $processor_data['processor_params']['password2'] . ':' . 'shp_label=official_cscart';
        if (isset($_REQUEST['OrderID'])) {
            $string .= ':OrderID=' . $_REQUEST['OrderID'];
        }
        $crc = strtoupper(md5($string));
        if (!hash_equals($crc, strtoupper($_REQUEST['SignatureValue']))) {
            http_response_code(400);
            die('Access denied');
        }

        // только проверенное уведомление может завершить оплату
        $pp_response['order_status'] = $processor_data['processor_params']['status_paid'];
        $pp_response['reason_text'] = __('approved');
        fn_finish_payment($order_id, $pp_response);

        die('OK' . $order_id);
    }

    if ($mode === 'return') {
        for ($i=1;$i <= ROBOKASSA_TIMEOUT;$i++) {
            $in_progress = (bool) db_get_field('SELECT order_data_id FROM ?:order_data WHERE order_id = ?i AND type = ?s', $order_id, OrderDataTypes::PAYMENT_STARTED);
            if (!$in_progress) {
                break;
            }

            sleep(1);
        }

        fn_order_placement_routines('route', $order_id, false);
    }

    if ($mode === 'cancel') {
        fn_order_placement_routines('route', $order_id);
    }
}
else {
    /** @var array $order_info **/
    /** @var array $processor_data **/
    $robokassa = new CscRobokassa($processor_data, $order_info);
    $robokassa->processPayment();
}
