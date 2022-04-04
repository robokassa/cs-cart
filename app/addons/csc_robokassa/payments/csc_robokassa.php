<?php
use Tygh\Payments\Processors\CscRobokassa;
use Tygh\Enum\OrderDataTypes;
use Tygh\Enum\OrderStatuses;

defined('BOOTSTRAP') or die('Access denied');

/** @var string $mode **/
if (defined('PAYMENT_NOTIFICATION')) {
    if (empty($_REQUEST['SignatureValue']) || empty($_REQUEST['InvId']) || (empty($_REQUEST['OutSum']) && $mode != 'cancel')) {
        die('Access denied');
    }

    if (!empty($_REQUEST['OrderID'])) {
        $order_id = (int) $_REQUEST['OrderID'];
    }
    else {
        $order_id = (int) $_REQUEST['InvId'];
    }

    $payment_id = db_get_field("SELECT payment_id FROM ?:orders WHERE order_id = ?i", $order_id);
    $processor_data = fn_get_processor_data($payment_id);

    if ($mode === 'result') {
        $string = $_REQUEST['OutSum'] . ':' . $_REQUEST['InvId'] . ':' . $processor_data['processor_params']['password2'];
        if (!empty($_REQUEST['OrderID'])) {
            $string .= ':OrderID=' . $order_id;
        }
        $crc = strtoupper(md5($string));
        if (strtoupper($_REQUEST['SignatureValue']) == $crc) {
            $pp_response['order_status'] = $processor_data['processor_params']['status_paid'];
            $pp_response['reason_text'] = __('approved');
        } else {
            $pp_response['order_status'] = OrderStatuses::FAILED;
            $pp_response['reason_text'] = __('crc_wrong');
        }
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

        $status = db_get_field('SELECT status FROM ?:orders WHERE order_id = ?i', $order_id);
        if ($status === OrderStatuses::INCOMPLETED) {
            $pp_response = [
                'order_status' => OrderStatuses::FAILED,
                'reason_text' => __('csc_robokassa_response_not_received'),
            ];
            fn_finish_payment($order_id, $pp_response);
        }
        fn_order_placement_routines('route', $order_id, false);
    }

    if ($mode === 'cancel') {
        $pp_response = [
            'order_status' => OrderStatuses::INCOMPLETED,
            'reason_text' => __('text_transaction_cancelled'),
        ];
        fn_finish_payment($order_id, $pp_response);

        fn_order_placement_routines('route', $order_id);
    }
}
else {
    /** @var array $order_info **/
    /** @var array $processor_data **/
    $robokassa = new CscRobokassa($processor_data, $order_info);
    $robokassa->processPayment();
}