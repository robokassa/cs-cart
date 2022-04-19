<?php
require 'hooks.php';

function fn_csc_robokassa_install() {
    $processor_data = [
        'processor' => 'Robokassa RU, KZ, Split',
        'processor_script' => 'csc_robokassa.php',
        'processor_template' => 'views/orders/components/payments/cc_outside.tpl',
        'admin_template' => 'csc_robokassa.tpl',
        'callback' => 'N',
        'type' => 'P',
        'position' => 40,
        'addon' => 'csc_robokassa',
    ];

    $processor_id = db_get_field('SELECT processor_id FROM ?:payment_processors WHERE admin_template = ?s', $processor_data['admin_template']);

    if (empty($processor_id)) {
        db_query('INSERT INTO ?:payment_processors ?e', $processor_data);
    } else {
        db_query('UPDATE ?:payment_processors SET ?u WHERE processor_id = ?i', $processor_data, $processor_id);
    }

    if (!(bool) db_get_field('select addon from ?:addons where addon = "rus_taxes"')) {
        db_query("ALTER TABLE ?:taxes ADD tax_type VARCHAR(6) NOT NULL");
    }
}

function fn_csc_robokassa_uninstall() {
    $processors = [];
    $processors []= [
        'processor' => 'Robokassa RU, KZ, Split',
        'processor_script' => 'csc_robokassa.php',
        'processor_template' => 'views/orders/components/payments/cc_outside.tpl',
        'admin_template' => 'csc_robokassa.tpl',
        'callback' => 'N',
        'type' => 'P',
        'position' => 40,
        'addon' => 'csc_robokassa',
    ];

    foreach ($processors as $processor_data) {
        db_query('DELETE FROM ?:payment_processors WHERE admin_template = ?s', $processor_data['admin_template']);
    }

    if (function_exists('fn_rus_payments_disable_payments')) {
        fn_rus_payments_disable_payments($processors, true);
    }

    if (!(bool) db_get_field('select addon from ?:addons where addon = "rus_taxes"')) {
        db_query("ALTER TABLE ?:taxes DROP tax_type");
    }
}

function fn_csc_allow_robokassa_split($cart) {
    foreach($cart['product_groups'] as $group) {
        if (!(bool) db_get_row('select csc_robokassa_split, csc_robokassa_merchant_id from ?:companies where company_id = ?i and csc_robokassa_split = "Y" and not csc_robokassa_merchant_id = ""', $group['company_id'])) {
            return false;
        }
    }

    return true;
}