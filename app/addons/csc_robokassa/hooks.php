<?php
use Tygh\Registry;
use Tygh\VendorPayouts;
use Tygh\Enum\VendorPayoutTypes;
use Tygh\Enum\VendorPayoutApprovalStatuses;

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

function fn_csc_robokassa_change_order_status($status_to, $status_from, $order_info, $force_notification, $order_statuses, $place_order) {
	if ($order_info['payment_method']['processor'] == 'Robokassa RU, KZ, Split' && $order_info['payment_method']['processor_params']['split'] == 'Y') {
		if ($status_to == $order_info['payment_method']['processor_params']['status_paid']) {
			$company_ids = array_unique(array_column($order_info['products'], 'company_id'));
			foreach($company_ids as $company_id) {
				$payouts_manager = VendorPayouts::instance(array('vendor' => $company_id));

				$order_payout = $payouts_manager->getSimple(array(
					'order_id'    => $order_info['order_id'],
					'payout_type' => VendorPayoutTypes::ORDER_PLACED,
				));
				if (!$order_payout) {
					continue;
				}

				$order_payout = reset($order_payout);
                $payout_params = array(
                    'payout_type'     => VendorPayoutTypes::WITHDRAWAL,
                    'payout_amount'   => $order_payout['order_amount'] - $order_payout['commission_amount'],
                    'comments'        => '',
                    'company_id'      => $company_id,
                    'order_id'        => $order_info['order_id'],
                    'approval_status' => VendorPayoutApprovalStatuses::COMPLETED,
                );

                $payouts_manager->update($payout_params);

				// mark payout as requested
				db_replace_into('order_data', array(
					'order_id' => $order_info['order_id'],
					'type'     => 'I',
					'data'     => serialize(true),
				));
			}
		}
	}
}
