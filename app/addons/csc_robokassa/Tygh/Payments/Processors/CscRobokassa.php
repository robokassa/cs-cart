<?php
namespace Tygh\Payments\Processors;
use Tygh;
use Tygh\Registry;
use Tygh\Tools\Math;

class CscRobokassa {
    private $_country;
    private $_merchantid;
    private $_details;
    private $_mode;
    private $_split;
    private $_password1;
    private $_password2;
    private $_payment_method;
    private $_order_info;
    private $_root_total;

    public function __construct($processor_data, $order_info) {
        $this->_country = $processor_data['processor_params']['country'];
        $this->_merchantid = $processor_data['processor_params']['merchantid'];
        $this->_details = $processor_data['processor_params']['details'];
        $this->_mode = $processor_data['processor_params']['mode'];
        $this->_split = $processor_data['processor_params']['split'];
        $this->_password1 = $processor_data['processor_params']['password1'];
        $this->_password2 = $processor_data['processor_params']['password2'];
        $this->_payment_method = $processor_data['processor_params']['payment_method'];
        $this->_order_info = $order_info;
        $this->calculateTaxesAndDiscounts();
    }

    public function getReceipt() {
        $receipt_result = [];

        foreach ($this->_order_info['products'] as $product) {
            if ($this->_country == "RU") {
                $receipt_result['items'][] = [
                    'name'     => $this->truncateItemReceiptName($product['product']),
                    'quantity' => $product['amount'],
                    'sum'      => $product['subtotal'],
                    'payment_method' => $this->_payment_method,
                    'payment_object' => 'commodity',
                    'tax'      => !empty($product['tax_type']) ? $product['tax_type'] : 'none',
                ];
            }
            elseif ($this->_country == "KZ") {
                $receipt_result['items'][] = [
                    'name'     => $this->truncateItemReceiptName($product['product']),
                    'quantity' => $product['amount'],
                    'sum'      => $product['subtotal'],
                    'tax'      => !empty($product['tax_type']) ? $product['tax_type'] : 'none',
                ];
            }
        }
        //shipping
        foreach (@$this->_order_info['shipping'] as $shipping) {
            $tax_id = current(array_keys($shipping['taxes']));
            $tax_sum = $shipping['taxes'][$tax_id]['tax_subtotal'];
            $sum = number_format($shipping['rate'] + $tax_sum, 2, '.', '');
            $receipt_result['items'][] = [
                'name' => __("shipping"),
                'quantity' => 1,
                'sum' => $sum,
                'tax' => !empty($tax['tax_type']) ? $tax['tax_type'] : 'none',
            ];
        }

        return $receipt_result;
    }

    public function processPayment() {
        if ($this->_split == "Y") {
            $this->split();
        }
        else {
            $this->payment();
        }
    }

    private function payment() {
        $total = number_format($this->_order_info['total'], 2, '.', '');
        $receipt = json_encode($this->getReceipt());

        $url = 'https://auth.robokassa.ru/Merchant/Index.aspx';

        if ($this->_country == "RU") {
            if (!in_array(CART_PRIMARY_CURRENCY, ['USD', 'EUR', 'KZT']) && CART_PRIMARY_CURRENCY != 'RUB') {
                $total = fn_format_price_by_currency($total, CART_PRIMARY_CURRENCY, 'RUB');
            }
            elseif (CART_PRIMARY_CURRENCY != 'RUB') {
                $outsum_currency = CART_PRIMARY_CURRENCY;
            }
        }
        elseif ($this->_country == "KZ") {
            $url = 'https://auth.robokassa.kz/Merchant/Index.aspx';
            if (!in_array(CART_PRIMARY_CURRENCY, ['USD', 'EUR', 'RUB']) && CART_PRIMARY_CURRENCY != 'KZT') {
                $total = fn_format_price_by_currency($total, CART_PRIMARY_CURRENCY, 'KZT');
            }
            elseif (CART_PRIMARY_CURRENCY != 'KZT') {
                if (CART_PRIMARY_CURRENCY == 'RUB') {
                    $outsum_currency = 'RUR';
                }
                else {
                    $outsum_currency = CART_PRIMARY_CURRENCY;
                }
            }

        }

        $crc = $this->_merchantid . ':' . $total . ':' . $this->_order_info['order_id'] . ':';
        if (!empty($outsum_currency)) {
            $crc .= $outsum_currency . ':';
        }
        $crc .= $receipt . ':' . $this->_password1 . ':' . 'shp_label=official_cscart';
        $crc = md5($crc);

        $post_data = [
            'MrchLogin' => $this->_merchantid,
            'OutSum' => $total,
            'InvId' => $this->_order_info['order_id'],
            'email' => $this->_order_info['email'],
            'Receipt' => $receipt,
            'Desc' => $this->_details,
            'shp_label' => 'official_cscart',
            'SignatureValue' => $crc,
            'Culture' => CART_LANGUAGE,
        ];

        if (!empty($outsum_currency)) {
            $post_data['OutSumCurrency'] = $outsum_currency;
        }
        if ($this->_mode != 'live') {
            $post_data['isTest'] = 1;
        }

        fn_create_payment_form($url, $post_data, 'Connecting to Robokassa...');
    }

    private function split() {
        $total = number_format($this->_order_info['total'], 2, '.', '');
        $this->_root_total = $this->_order_info['total'];
        $url = 'https://auth.robokassa.ru/Merchant/Payment/CreateV2';
        $merchants = [];
        $company_ids = array_column($this->_order_info['products'], 'company_id');
        foreach ($company_ids as $company_id) {
            $merchants[$company_id]['id'] = $this->getVendorRobokassaId($company_id);
            $merchants[$company_id]['amount'] = 0;
        }

        foreach ($this->_order_info['products'] as $product) {
            $company_id = $product['company_id'];
            $item = [
                'name' => $this->truncateItemReceiptName($product['product']),
                'quantity' => $product['amount'],
                'sum' => $product['subtotal'],
                'tax' => !empty($product['tax_type']) ? $product['tax_type'] : 'none',
                'product_code' => $product['product_code'], //tmp value for category commission
                'tax_sum' => $product['tax_value'], //tmp value for category commission
            ];
            if ($this->_country == "RU") {
                $item['payment_method'] = $this->_payment_method;
                $item['payment_object'] = 'commodity';
            }
            $merchants[$company_id]['receipt']['items'] []= $item;
        }
        $merchants[$company_id]['amount'] = array_sum(array_column($merchants[$company_id]['receipt']['items'], 'sum'));
        $merchants[$company_id]['amount'] = number_format($merchants[$company_id]['amount'], 2, '.', '');
        $include_shipping = Registry::get('addons.vendor_plans.include_shipping');
        if ($include_shipping != "Y") {
            $this->calculateMerchantsCommission($merchants);
        }
        //shipping
        foreach (@$this->_order_info['shipping'] as $shipping) {
            if (!empty($shipping['taxes'])) {
                $tax_id = current(array_keys($shipping['taxes']));
                if (!empty($tax_id)) {
                    $tax = fn_get_tax($tax_id);
                    $tax_sum = $shipping['taxes'][$tax_id]['tax_subtotal'];
                }
            }
            else {
                $tax_sum = 0;
            }
            $company_id = $this->_order_info['product_groups'][$shipping['group_key']]['company_id'];
            $sum = number_format($shipping['rate'] + $tax_sum, 2, '.', '');
            $merchants[$company_id]['receipt']['items']['shipping'] = [
                'name' => __("shipping"),
                'quantity' => 1,
                'sum' => $sum,
                'tax' => !empty($tax['tax_type']) ? $tax['tax_type'] : 'none',
            ];
            if ($this->_country == "RU") {
                $merchants[$company_id]['receipt']['items']['shipping']['payment_method'] = $this->_payment_method;
                $merchants[$company_id]['receipt']['items']['shipping']['payment_object'] = 'commodity';
            }
            if ($include_shipping == "Y") {
                $merchants[$company_id]['receipt']['items']['shipping']['tax_sum'] = !empty($tax_sum) ? $tax_sum : 0;
            }
            else {
                $this->_root_total -= $sum;
            }
            $merchants[$company_id]['receipt']['items'] = array_values($merchants[$company_id]['receipt']['items']);
            $merchants[$company_id]['amount'] = number_format($merchants[$company_id]['amount'] + $sum, 2, '.', '');
        }
        if ($include_shipping == "Y") {
            $this->calculateMerchantsCommission($merchants);
        }

        if (!empty($this->_order_info['payment_surcharge'])) {
            $this->_root_total -= $this->_order_info['payment_surcharge'];
        }
        $item = [
            'name' => __("csc_robokassa_commission"),
            'quantity' => 1,
            'sum' => $this->_root_total > 0 ? number_format($this->_root_total, 2, '.', '') : 0,
            'tax' => 'none',
        ];
        if ($this->_country == "RU") {
            $item['payment_method'] = 'full_prepayment';
            $item['payment_object'] = 'commodity';
        }
        $root_items = [$item];
        $item = [
            'name' => __("payment_surcharge"),
            'quantity' => 1,
            'sum' => number_format($this->_order_info['payment_surcharge'], 2, '.', ''),
            'tax' => 'none',
        ];
        if ($this->_country == "RU") {
            $item['payment_method'] = 'full_prepayment';
            $item['payment_object'] = 'commodity';
        }
        if (!empty($this->_order_info['payment_surcharge'])) {
            $root_items []= $item;
        }

        $merchants []= [
            'id' => $this->_merchantid,
            'amount' => number_format($this->_root_total + @$this->_order_info['payment_surcharge'], 2, '.', ''),
            'receipt' => [
                'items' => $root_items
            ]
        ];

        $invoice = [
            'outAmount' => $total,
            'shop_params' => [
                [
                    'name' => 'OrderID',
                    'value' => $this->_order_info['order_id']
                ]
            ],
            'email' => $this->_order_info['email'],
            'language' => CART_LANGUAGE,
            'merchant' => [
                'id' => $this->_merchantid,
            ],
            'splitMerchants' => array_values($merchants)
        ];

        $invoice = json_encode($invoice);

        $crc = md5($invoice . $this->_password1);
        $data = [
            'Invoice' => urlencode($invoice),
            'Signature' => $crc
        ];
        fn_create_payment_form($url, $data, 'Robokassa server');
    }

    private function calculateTaxesAndDiscounts() {
        foreach ($this->_order_info['products'] as &$product) {
            $product['tax_ids'] = explode(',', db_get_field('select tax_ids from ?:products where product_id = ?i', $product['product_id']));
            if (!empty($product['tax_ids'])) {
                $product['tax_type'] = $this->getTaxType(current($product['tax_ids']));
                if (empty($product['tax_type'])) {
                    $product['tax_type'] = 'none';
                }
            }
            $_shipping_mod = $this->_order_info['shipping_cost'] / sizeof($this->_order_info['products']);
            if (!empty($this->_order_info['subtotal_discount'])) {
                $discount = round(($product['subtotal'] + $_shipping_mod) / ($this->_order_info['total'] + $this->_order_info['subtotal_discount']) * $this->_order_info['subtotal_discount'], 2);
                $product['subtotal'] = $product['subtotal'] - $discount;
            }
        }
    }

    private function getTaxType($tax_id) {
        return db_get_field('select tax_type from ?:taxes where tax_id = ?i', $tax_id);
    }

    private function calculateMerchantsCommission(&$merchants) {
        if (function_exists('fn_vendor_plans_get_vendor_plan_by_company_id')) {
            $include_taxes = Registry::get('addons.vendor_plans.include_taxes_in_commission');
            foreach ($merchants as $company_id => &$merchant) {
                $plan = fn_vendor_plans_get_vendor_plan_by_company_id($company_id);
                $fixed_commission = $plan->fixed_commission;
                if ($fixed_commission > 0) {
                    $fixed_commission_per_item = number_format($fixed_commission / sizeof($merchant['receipt']['items']), 2, '.', '');
                }
                foreach ($merchant['receipt']['items'] as &$item) {
                    if ($include_taxes != "Y") {
                        $item['sum'] -= $item['tax_sum'];
                    }
                    if (function_exists('fn_vendor_categories_fee_get_category_fee') && !empty($item['product_code'])) {
                        $product_id = db_get_field('select product_id from ?:products where product_code = ?s', $item['product_code']);
                        $category_id = db_get_field('select category_id from ?:products_categories where product_id = ?i and link_type = "M"', $product_id);
                        $category_fee = fn_vendor_categories_fee_get_category_fee($category_id);
                        if (empty($category_fee[$plan->plan_id]['percent_fee'])) {
                            $category_fee = fn_vendor_categories_fee_get_parent_category_fee($category_id);
                        }
                        $commission = $category_fee[$plan->plan_id]['percent_fee'];
                    }
                    else {
                        $commission = $plan->commission;
                    }
                    $item['sum'] -= $item['sum'] * $commission / 100;
                    if (!empty($fixed_commission_per_item)) {
                        if ($item['sum'] >= $fixed_commission_per_item) {
                            $item['sum'] -= $fixed_commission_per_item;
                            $fixed_commission -= $fixed_commission_per_item;
                        }
                        else {
                            $fixed_commission -= $item['sum'];
                            $item['sum'] = 0;
                        }
                        if ($item === end($merchant['receipt']['items']) && $fixed_commission > 0) {
                            $item['sum'] -= $fixed_commission;
                        }
                    }
                    if ($item['sum'] < 0) {
                        $item['sum'] = 0;
                    }
                    if ($include_taxes != "Y") {
                        $item['sum'] += $item['tax_sum'];
                    }
                    $item['sum'] = number_format($item['sum'], 2, '.', '');
                    $this->_root_total -= $item['sum'];
                    unset($item['product_code']); //tmp value for commission
                    unset($item['tax_sum']); //tmp value for commission
                }
                $merchant['amount'] = array_sum(array_column($merchant['receipt']['items'], 'sum'));
                $merchant['amount'] = number_format($merchant['amount'], 2, '.', '');
            }
        }
    }

    private function getVendorRobokassaId($company_id) {
        return db_get_field('select csc_robokassa_merchant_id from ?:companies where company_id = ?i', $company_id);
    }

    private function truncateItemReceiptName($name, $length = 64, $suffix = '...') {
        $name = preg_replace('/[^0-9a-zA-Zа-яА-Я-,. ]/ui', '', $name);

        if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > $length) {
            $length -= mb_strlen($suffix);
            return rtrim(mb_substr($name, 0, $length, 'UTF-8')) . $suffix;
        }

        return $name;
    }
}
