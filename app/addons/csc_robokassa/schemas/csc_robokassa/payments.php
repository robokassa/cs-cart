<?php

return [
    'Robokassa' => [
        'processor' => 'Robokassa RU, KZ, Split',
        'processor_script' => 'csc_robokassa.php',
        'processor_template' => 'views/orders/components/payments/cc_outside.tpl',
        'admin_template' => 'csc_robokassa.tpl',
        'callback' => 'N',
        'type' => 'P',
        'position' => 40,
        'addon' => 'csc_robokassa',
    ],
];