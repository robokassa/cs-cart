<?php
use Tygh\Registry;

function fn_csc_robokassa_get_payments_post($params, &$payments) {
    if (AREA != 'C') return;
    $allow_split = fn_csc_allow_robokassa_split(Tygh::$app['session']['cart']);
    $_payments = [];
    foreach ($payments as $payment_id => $payment) {
        $params = unserialize($payment['processor_params']);
        if (@$params['split'] == "Y" && $payment['processor_script'] == 'csc_robokassa.php') {
            $_payments['split'] []= $payment_id;
            if (!$allow_split) unset($payments[$payment_id]);
        }
        if (@$params['split'] == "N" && $payment['processor_script'] == 'csc_robokassa.php') {
            $_payments['payment'] []= $payment_id;
        }
    }

    if ($allow_split && !empty($_payments['split']) && !empty($_payments['payment'])) {
        foreach ($_payments['payment'] as $payment_id) {
            unset($payments[$payment_id]);
        }
    }
}