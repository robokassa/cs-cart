<?php
defined('BOOTSTRAP') or die('Access denied');

/**
 * @var array<string, array> $schema
 */
$schema['/payment_notification/result/csc_robokassa'] = [
    'dispatch' => 'payment_notification.result',
    'payment'  => 'csc_robokassa',
];

$schema['/payment_notification/success/csc_robokassa'] = [
    'dispatch' => 'payment_notification.return',
    'payment'  => 'csc_robokassa',
];

$schema['/payment_notification/fail/csc_robokassa'] = [
    'dispatch' => 'payment_notification.cancel',
    'payment'  => 'csc_robokassa',
];

return $schema;
