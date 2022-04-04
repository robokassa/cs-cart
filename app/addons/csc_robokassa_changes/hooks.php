<?php
use Tygh\Registry;

function fn_csc_robokassa_changes_get_payments_post($params, &$payments) {
    if (AREA != 'C') return;
    $chosen_currency = $_SESSION['settings']['secondary_currencyC']['value'];
    foreach($payments as $key => $payment) {
        if ($payment['processor_script'] == 'csc_robokassa.php') {
            $processor_data = unserialize($payment['processor_params']);
            if ($processor_data['country'] == 'RU' && $chosen_currency != 'RUB' || $processor_data['country'] == 'KZ' && $chosen_currency != 'KZT') {
                unset($payments[$key]);
            }
        }
    }
}