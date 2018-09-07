<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_QD_Credit_Manager {

	public function setup() {
		add_action( 'woocommerce_refund_created', array( $this, 'create_credit' ), 10, 2 );
	}

	/**
	 * Create credit
	 *
	 * @param $refund_id
	 */
	public function create_credit( $refund_id, $args ) {
		// Get the refund
		$refund = wc_get_order( $refund_id );
		$order = wc_get_order( $args['order_id'] );

		// Return if an credit has already been issued for this refund
		$credit_id = get_post_meta( $refund_id, '_quaderno_credit', true );
		if ( !empty( $credit_id ) || $order->get_total() == 0 ) {
			return;
		}

		$credit_params = array(
			'issue_date' => date('Y-m-d'),
			'currency' => $refund->get_currency(),
			'po_number' => get_post_meta( $order->get_id(), '_order_number_formatted', true ) ?: $order->get_id(),
			'processor' => 'woocommerce',
			'processor_id' => $order->get_id(),
			'payment_method' => self::get_payment_method($order->get_id())
		);

		// Add the contact
		$contact_id = get_user_meta( $order->get_user_id(), '_quaderno_contact', true );
		if ( !empty( $contact_id ) ) {
			$credit_params['contact_id'] = $contact_id;
		}
		else {
			if ( !empty( $order->get_billing_company() ) ) {
				$kind = 'company';
				$first_name = $order->get_billing_company();
				$last_name = '';
				$contact_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			} else {
				$kind = 'person';
				$first_name = $order->get_billing_first_name();
				$last_name = $order->get_billing_last_name();
				$contact_name = '';
			}

			$credit_params['contact'] = array(
				'kind' => $kind,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'contact_name' => $contact_name,
				'street_line_1' => $order->get_billing_address_1(),
				'street_line_2' => $order->get_billing_address_2(),
				'city' => $order->get_billing_city(),
				'postal_code' => $order->get_billing_postcode(),
				'region' => $order->get_billing_state(),
				'country' => $order->get_billing_country(),
				'email' => $order->get_billing_email(),
				'phone_1' => $order->get_billing_phone(),
				'vat_number' => get_post_meta( $order->get_id(), WC_QD_Vat_Number_Field::META_KEY, true ),
  			'tax_id' => get_post_meta( $order->get_id(), WC_QD_Tax_Id_Field::META_KEY, true )
			);
		}
		
		//Let's create the credit note
		$credit = new QuadernoCredit($credit_params);

		// Calculate exchange rate
		$exchange_rate = get_post_meta( $order->get_id(), '_woocs_order_rate', true ) ?: 1;

		// Calculate tax name & rate
		$taxes = $order->get_taxes();
		$tax = array_shift($taxes);
		if ( !isset( $tax ) ) {
			list($tax_name, $tax_rate) = array( NULL, 0 );
		} else if ( empty( WC_Tax::get_rate_code( $tax['rate_id'] ))) {
			list($tax_name, $tax_rate) = explode( '|', $tax['name'] );
		} else {
			list($tax_name, $tax_rate) = array( WC_Tax::get_rate_label( $tax['rate_id'] ), floatval( WC_Tax::get_rate_percent( $tax['rate_id'] )) );
		}

		// Calculate tax country
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		if ( 'base' === $tax_based_on ) {
			$tax_country  = WC()->countries->get_base_country();
		} else if ( 'billing' === $tax_based_on ) {
			$tax_country  = $order->get_billing_country();
		} else {
			$tax_country  = $order->get_shipping_country();
		}

		if ( empty( $tax_country )) {
			$tax_country = $order->get_billing_country();
		}

		// Add item
		$refunded_amount = -round($refund->get_total() * $exchange_rate, 2);
		$new_item = new QuadernoDocumentItem(array(
			'description' => 'Refund invoice #' . get_post_meta( $order->get_id(), '_quaderno_invoice_number', true ),
			'quantity' => 1,
			'total_amount' => $refunded_amount,
			'tax_1_name' => $tax['label'],
			'tax_1_rate' => $tax_rate,
			'tax_1_country' => $tax_country
		));
		$credit->addItem( $new_item );

		if ( $credit->save() ) {
			add_post_meta( $refund_id, '_quaderno_credit', $credit->id );
			add_user_meta( $order->get_user_id(), '_quaderno_contact', $credit->contact_id, true );

			if ( 'yes' === WC_QD_Integration::$autosend_invoices ) $credit->deliver();
		}
	}

	/**
	 * Get payment method for Quaderno
	 *
	 * @param $order_id
	 */
	public function get_payment_method( $order_id ) {
		$payment_id = get_post_meta( $order_id, '_payment_method', true );
		$method = '';
		switch( $payment_id ) {
			case 'bacs':
				$method = 'wire_transfer';
				break;
			case 'cheque':
				$method = 'check';
				break;
			case 'cod':
				$method = 'cash';
				break;
			case 'paypal':
				$method = 'paypal';
				break;
			default:
				$method = 'credit_card';
		}
		return $method;
	}

}
