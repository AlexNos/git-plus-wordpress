<?php
/**
 * Subscriptions Order Class
 * 
 * Mirrors and overloads a few functions in the WC_Order class to work for subscriptions. 
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Order
 * @category	Class
 * @author		Brent Shepherd
 */
class WC_Subscriptions_Order {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 * 
	 * @since 1.0
	 */
	public static function init() {
		add_filter( 'woocommerce_get_order_item_totals', __CLASS__ . '::get_order_item_totals', 10, 2 );
		add_filter( 'woocommerce_get_formatted_order_total', __CLASS__ . '::get_formatted_order_total', 10, 2 );
		add_filter( 'woocommerce_order_formatted_line_subtotal', __CLASS__ . '::get_formatted_line_total', 10, 3 );
		add_filter( 'woocommerce_order_subtotal_to_display', __CLASS__ . '::get_subtotal_to_display', 10, 3 );
		add_filter( 'woocommerce_order_cart_discount_to_display', __CLASS__ . '::get_cart_discount_to_display', 10, 3 );
		add_filter( 'woocommerce_order_discount_to_display', __CLASS__ . '::get_order_discount_to_display', 10, 3 );
		add_filter( 'woocommerce_order_shipping_to_display', __CLASS__ . '::get_shipping_to_display', 10, 2 );

		add_action( 'woocommerce_thankyou', __CLASS__ . '::subscription_thank_you' );

		add_action( 'manage_shop_order_posts_custom_column', __CLASS__ . '::add_contains_subscription_hidden_field', 10, 1 );
		add_action( 'woocommerce_admin_order_data_after_order_details', __CLASS__ . '::contains_subscription_hidden_field', 10, 1 );

		add_action( 'woocommerce_process_shop_order_meta', __CLASS__ . '::maybe_manually_change_subscriptions', 0, 2 ); // Need to fire before WooCommerce

		// Record initial payment against the subscription
		add_action( 'woocommerce_payment_complete', __CLASS__ . '::maybe_record_order_payment', 10, 1 );
		add_action( 'woocommerce_order_status_processing', __CLASS__ . '::maybe_record_order_payment', 10, 1 );
		add_action( 'woocommerce_order_status_completed', __CLASS__ . '::maybe_record_order_payment', 10, 1 );
	}

	/*
	 * Helper functions for extracting the details of subscriptions in an order
	 */

	/**
	 * Checks an order to see if it contains a subscription.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @return bool True if the order contains a subscription, otherwise false.
	 * @version 1.2
	 * @since 1.0
	 */
	public static function order_contains_subscription( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$contains_subscription = false;

		foreach ( $order->get_items() as $order_item ) {
			if ( self::is_item_a_subscription( $order, $order_item['id'] ) ) {
				$contains_subscription = true;
				break;
			}
		}

		return $contains_subscription;
	}

	/**
	 * Checks if a subscription requires manual payment because the payment gateway used to purchase the subscription
	 * did not support automatic payments at the time of the subscription sign up.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @return bool True if the subscription exists and requires manual payments, false if the subscription uses automatic payments (defaults to false for backward compatibility).
	 * @since 1.2
	 */
	public static function requires_manual_renewal( $order ) {

		if ( 'true' == self::get_meta( $order, '_wcs_requires_manual_renewal', 'false' ) )
			$requires_manual_renewal = true;
		else
			$requires_manual_renewal = false;

		return $requires_manual_renewal;
	}

	/**
	 * Checks an order item to see if it is a subscription. The item needs to exist and have been a subscription
	 * product at the time of purchase for the function to return true.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int The ID of a WC_Product object purchased in the order.
	 * @return bool True if the order contains a subscription, otherwise false.
	 * @since 1.2
	 */
	public static function is_item_a_subscription( $order, $product_id ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$is_subscription = false;

		foreach ( $order->get_items() as $order_item ) {
			if ( $order_item['id'] == $product_id ) {
				foreach ( $order_item['item_meta'] as $item_meta ) {
					if ( false !== strpos( $item_meta['meta_name'], '_subscription' ) ) {
						$is_subscription = true;
						break;
					}
				}
			}
		}

		return $is_subscription;
	}

	/**
	 * Returns the total amount to be charged at the outset of the Subscription.
	 * 
	 * This may return 0 if there is a free trial period and no sign up fee, otherwise it will be the sum of the sign up 
	 * fee and price per period. This function should be used by payment gateways for the initial payment.
	 * 
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @return float The total initial amount charged when the subscription product in the order was first purchased, if any.
	 * @since 1.1
	 */
	public static function get_total_initial_payment( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$order_total     = $order->get_total();
		$recurring_total = self::get_recurring_total( $order );
		$trial_length    = self::get_subscription_trial_length( $order );

		// If there is a free trial period and no sign up fee, the initial payment is 0
		if ( $trial_length > 0 && $order_total == $recurring_total )
			$initial_payment = 0;
		else
			$initial_payment = $order_total; // Order total already accounts for sign up fees when there is no trial period

		return $initial_payment;
	}

	/**
	 * Returns the total sign-up fee for a subscription product in an order.
	 * 
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @return float The initial sign-up fee charged when the subscription product in the order was first purchased, if any.
	 * @since 1.0
	 */
	public static function get_sign_up_fee( $order, $product_id = '' ) {

		$recurring_total = self::get_recurring_total( $order );
		$initial_payment = self::get_total_initial_payment( $order );

		if ( self::get_subscription_trial_length( $order ) > 0 )
			$sign_up_fee = $initial_payment;
		elseif ( $recurring_total != $initial_payment )
			$sign_up_fee = $initial_payment - $recurring_total;
		else
			$sign_up_fee = 0;

		return $sign_up_fee;
	}

	/**
	 * Returns the period (e.g. month) for a each subscription product in an order.
	 * 
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @return string A string representation of the period for the subscription, i.e. day, week, month or year.
	 * @since 1.0
	 */
	public static function get_subscription_period( $order, $product_id = '' ) {
		return self::get_item_meta( $order, '_subscription_period', $product_id );
	}

	/**
	 * Returns the billing interval for a each subscription product in an order.
	 *
	 * For example, this would return 3 for a subscription charged every 3 months or 1 for a subscription charged every month.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @return int The billing interval for a each subscription product in an order.
	 * @since 1.0
	 */
	public static function get_subscription_interval( $order, $product_id = '' ) {
		return self::get_item_meta( $order, '_subscription_interval', $product_id, 1 );
	}

	/**
	 * Returns the length for a subscription in an order.
	 * 
	 * There must be only one subscription in an order for this to be accurate. 
	 * 
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @return int The number of periods for which the subscription will recur. For example, a $5/month subscription for one year would return 12. A $10 every 3 month subscription for one year would also return 12.
	 * @since 1.0
	 */
	public static function get_subscription_length( $order, $product_id = '' ) {
		return self::get_item_meta( $order, '_subscription_length', $product_id, 0 );
	}

	/**
	 * Returns the length for a subscription product's trial period as set when added to an order.
	 *
	 * The trial period is the same as the subscription period, as derived from @see self::get_subscription_period().
	 *
	 * For now, there must be only one subscription in an order for this to be accurate. 
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @return int The number of periods the trial period lasts for. For no trial, this will return 0, for a 3 period trial, it will return 3.
	 * @since 1.1
	 */
	public static function get_subscription_trial_length( $order, $product_id = '' ) {
		return self::get_item_meta( $order, '_subscription_trial_length', $product_id, 0 );
	}

	/**
	 * Returns the period (e.g. month)  for a subscription product's trial as set when added to an order.
	 *
	 * As of 1.2.x, a subscriptions trial period may be different than the recurring period
	 *
	 * For now, there must be only one subscription in an order for this to be accurate.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @return string A string representation of the period for the subscription, i.e. day, week, month or year.
	 * @since 1.2
	 */
	public static function get_subscription_trial_period( $order, $product_id = '' ) {

		$period = self::get_item_meta( $order, '_subscription_trial_period', $product_id, '' );

		// Backward compatibility
		if ( empty( $period ) )
			$period = self::get_subscription_period( $order, $product_id );

		return $period;
	}


	/**
	 * Returns the recurring amount for an item
	 *
	 * @param $order WC_Order A WC_Order object  
	 * @param $product_id int The product/post ID of a subscription
	 * @return float The total amount to be charged for each billing period, if any, not including failed payments.
	 * @since 1.2
	 */
	public static function get_item_recurring_amount( $order, $product_id ) {
		return self::get_item_meta( $order, '_subscription_recurring_amount', $product_id, 0 );
	}

	/**
	 * Returns the sign up fee for an item
	 *
	 * @param $order WC_Order A WC_Order object  
	 * @param $product_id int The product/post ID of a subscription
	 * @since 1.2
	 */
	public static function get_item_sign_up_fee( $order, $product_id = '' ) {

		$item = self::get_item( $order, $product_id );

		$line_subtotal           = $order->get_line_subtotal( $item );
		$recurring_line_subtotal = self::get_item_recurring_amount( $order, $product_id );

		if ( self::get_subscription_trial_length( $order, $product_id ) > 0 )
			$sign_up_fee = $line_subtotal;
		else if ( $line_subtotal != $recurring_line_subtotal )
			$sign_up_fee = $line_subtotal - self::get_item_recurring_amount( $order, $product_id );
		else
			$sign_up_fee = 0;

		return $sign_up_fee;
	}

	/**
	 * Takes a subscription product's ID and returns the timestamp on which the next payment is due.
	 *
	 * A convenience wrapper for @see WC_Subscriptions_Manager::get_next_payment_date() to get the
	 * next payment date for a subscription when all you have is the order and product.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int The product/post ID of the subscription
	 * @param mixed $deprecated Never used.
	 * @return int If no more payments are due, returns 0, otherwise returns a timestamp of the date the next payment is due.
	 * @version 1.2
	 * @since 1.0
	 */
	public static function get_next_payment_timestamp( $order, $product_id, $deprecated = null ) {

		if ( null != $deprecated ) { // We want to calculate a date
			_deprecated_argument( __CLASS__ . '::' . __FUNCTION__, '1.2' );
			$next_payment_timestamp = self::calculate_next_payment_date( $order, $product_id, 'timestamp', $deprecated );
		} else {

			if ( ! is_object( $order ) )
				$order = new WC_Order( $order );

			$subscription_key       = WC_Subscriptions_Manager::get_subscription_key( $order->id, $product_id );
			$next_payment_timestamp = WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $order->user_id, 'timestamp' );
		}

		return $next_payment_timestamp;
	}

	/**
	 * Takes a subscription product's ID and the order it was purchased in and returns the date on 
	 * which the next payment is due.
	 *
	 * A convenience wrapper for @see WC_Subscriptions_Manager::get_next_payment_date() to get the next
	 * payment date for a subscription when all you have is the order and product.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int The product/post ID of the subscription
	 * @param mixed $deprecated Never used.
	 * @return mixed If no more payments are due, returns 0, otherwise it returns the MySQL formatted date/time string for the next payment date.
	 * @version 1.2
	 * @since 1.0
	 */
	public static function get_next_payment_date( $order, $product_id, $deprecated = null ) {

		if ( null != $deprecated ) { // We want to calculate a date
			_deprecated_argument( __CLASS__ . '::' . __FUNCTION__, '1.2' );
			$next_payment_date = self::calculate_next_payment_date( $order, $product_id, 'mysql', $deprecated );
		} else {
			if ( ! is_object( $order ) )
				$order = new WC_Order( $order );

			$subscription_key  = WC_Subscriptions_Manager::get_subscription_key( $order->id, $product_id );
			$next_payment_date = WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $order->user_id, 'mysql' );
		}

		return $next_payment_date;
	}

	/**
	 * Takes a subscription product's ID and the order it was purchased in and returns the date on 
	 * which the last payment was made.
	 *
	 * A convenience wrapper for @see WC_Subscriptions_Manager::get_next_payment_date() to get the next
	 * payment date for a subscription when all you have is the order and product.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int The product/post ID of the subscription
	 * @param mixed $deprecated Never used.
	 * @return mixed If no more payments are due, returns 0, otherwise it returns the MySQL formatted date/time string for the next payment date.
	 * @version 1.2.1
	 * @since 1.0
	 */
	public static function get_last_payment_date( $order, $product_id ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$subscription_key  = WC_Subscriptions_Manager::get_subscription_key( $order->id, $product_id );
		$next_payment_date = WC_Subscriptions_Manager::get_last_payment_date( $subscription_key, $order->user_id );

		return $next_payment_date;
	}

	/**
	 * Takes a subscription product's ID and calculates the date on which the next payment is due.
	 *
	 * Calculation is based on $from_date if specified, otherwise it will fall back to the last
	 * completed payment, the subscription's start time, or the current date/time, in that order.
	 *
	 * The next payment date will occur after any free trial period and up to any expiration date.
	 *
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int The product/post ID of the subscription
	 * @param $type string (optional) The format for the Either 'mysql' or 'timestamp'.
	 * @param $from_date mixed A MySQL formatted date/time string from which to calculate the next payment date, or empty (default), which will use the last payment on the subscription, or today's date/time if no previous payments have been made.
	 * @return mixed If there is no future payment set, returns 0, otherwise it will return a date of the next payment in the form specified by $type
	 * @since 1.0
	 */
	public static function calculate_next_payment_date( $order, $product_id, $type = 'mysql', $from_date = '' ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$subscription              = WC_Subscriptions_Manager::get_users_subscription( $order->user_id, WC_Subscriptions_Manager::get_subscription_key( $order->id, $product_id ) );
		$subscription_period       = self::get_subscription_period( $order, $product_id );
		$subscription_interval     = self::get_subscription_interval( $order, $product_id );
		$subscription_trial_length = self::get_subscription_trial_length( $order, $product_id );
		$subscription_trial_period = self::get_subscription_trial_period( $order, $product_id );

		$trial_end_time   = ( ! empty( $subscription['trial_expiry_date'] ) ) ? $subscription['trial_expiry_date'] : WC_Subscriptions_Product::get_trial_expiration_date( $product_id, $order->order_date );
		$trial_end_time   = strtotime( $trial_end_time );

		// If the subscription is not active, there is no next payment date
		if ( $subscription['status'] != 'active' ) {

			$next_payment_timestamp = 0;

		// If the subscription has a free trial period, and we're still in the free trial period, the next payment is due at the end of the free trial
		} elseif ( $subscription_trial_length > 0 && $trial_end_time > time() + 120 ) {

			$next_payment_timestamp = $trial_end_time;

		// The next payment date is {interval} billing periods from the from date
		} else {

			// We have a timestamp
			if ( ! empty( $from_date ) && is_numeric( $from_date ) )
				$from_date = date( 'Y-m-d H:i:s', $from_date );

			if ( empty( $from_date ) ) {

				if ( ! empty( $subscription['completed_payments'] ) ) {
					$from_date = array_pop( $subscription['completed_payments'] );
					$add_failed_payments = true;
				} else if ( ! empty ( $subscription['start_date'] ) ) {
					$from_date = $subscription['start_date'];
					$add_failed_payments = true;
				} else {
					$from_date = date( 'Y-m-d H:i:s' );
					$add_failed_payments = false;
				}

				$failed_payment_count = self::get_failed_payment_count( $order, $product_id );

				// Maybe take into account any failed payments
				if ( true === $add_failed_payments && $failed_payment_count > 0 ) {
					$failed_payment_periods = $failed_payment_count * $subscription_interval;
					$from_date = date( 'Y-m-d H:i:s', strtotime( "+ {$failed_payment_periods} {$subscription_period}", strtotime( $from_date ) ) );
				}

			}

			$next_payment_timestamp = strtotime( "+ {$subscription_interval} {$subscription_period}", strtotime( $from_date ) );

			// Make sure the next payment is in the future
			while ( $next_payment_timestamp < time() )
				$next_payment_timestamp = strtotime( "+ {$subscription_interval} {$subscription_period}", $next_payment_timestamp );

		}

		// If the subscription has an expiry date and the next billing period comes after the expiration, return 0
		if ( isset( $subscription['expiry_date'] ) && 0 != $subscription['expiry_date'] && $next_payment_timestamp > strtotime( $subscription['expiry_date'] ) )
			$next_payment_timestamp =  0;

		$next_payment = ( 'mysql' == $type && 0 != $next_payment_timestamp ) ? date( 'Y-m-d H:i:s', $next_payment_timestamp ) : $next_payment_timestamp;

		return apply_filters( 'woocommerce_subscriptions_calculated_next_payment_date', $next_payment, $order, $product_id, $type, $from_date );
	}

	/**
	 * Gets an item by product id from an order.
	 * 
	 * @param $order WC_Order | int The WC_Order object or ID of the order for which the meta should be sought. 
	 * @param $product_id int The product/post ID of a subscription. Option - if no product id is provided, it is expected that only one item exists and the last item's meta will be returned
	 * @since 1.2
	 */
	public static function get_item( $order, $product_id = '' ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		foreach ( $order->get_items() as $item )
			if ( $item['id'] == $product_id || empty( $product_id ) )
				return $item;

		return array();
	}

	/**
	 * A unified API for accessing product specific meta on an order.
	 * 
	 * @param $order WC_Order | int The WC_Order object or ID of the order for which the meta should be sought. 
	 * @param $meta_key string The key as stored in the post meta table for the meta item. 
	 * @param $product_id int The product/post ID of a subscription. Option - if no product id is provided, it is expected that only one item exists and the last item's meta will be returned
	 * @param $default mixed (optional) The default value to return if the meta key does not exist. Default 0.
	 * @since 1.2
	 */
	public static function get_item_meta( $order, $meta_key, $product_id = '', $default = 0 ) {

		$meta_value = $default;

		$item = self::get_item( $order, $product_id );

		if ( ! empty ( $item ) && isset( $item['item_meta'] ) ) {
			foreach ( $item['item_meta'] as $meta )
				if ( $meta['meta_name'] == $meta_key )
					$meta_value = $meta['meta_value'];
		}

		return $meta_value;
	}

	/**
	 * Gets the name of a subscription item by product ID from an order.
	 * 
	 * @param $order WC_Order | int The WC_Order object or ID of the order for which the meta should be sought. 
	 * @param $product_id int The product/post ID of a subscription. Option - if no product id is provided, it is expected that only one item exists and the last item's meta will be returned
	 * @since 1.2
	 */
	public static function get_item_name( $order, $product_id = '' ) {

		$item = self::get_item( $order, $product_id );

		if ( isset( $item['name'] ) )
			return $item['name'];
		else
			return '';
	}

	/**
	 * A unified API for accessing subscription order meta, especially for sign-up fee related order meta.
	 * 
	 * @param $order WC_Order | int The WC_Order object or ID of the order for which the meta should be sought. 
	 * @param $meta_key string The key as stored in the post meta table for the meta item. 
	 * @param $default mixed (optional) The default value to return if the meta key does not exist. Default 0.
	 * @since 1.0
	 */
	public static function get_meta( $order, $meta_key, $default = 0 ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		if ( isset( $order->order_custom_fields[$meta_key] ) ) {
			$meta_value = maybe_unserialize( $order->order_custom_fields[$meta_key][0] );
		} else {
			$meta_value = get_post_meta( $order->id, $meta_key, true );

			if ( empty( $meta_value ) )
				$meta_value = $default;
		}

		return $meta_value;
	}

	/* 
	 * Functions to customise the way WooCommerce displays order prices.
	 */

	/**
	 * Appends the subscription period/duration string to order total
	 *
	 * @since 1.0
	 */
	public static function get_formatted_line_total( $formatted_total, $item, $order ) {

		if ( WC_Subscriptions_Order::is_item_subscription( $order, $item['id'] ) ) {

			$subscription_details = array(
				'subscription_interval' => self::get_subscription_interval( $order, $item['id'] ),
				'subscription_period'   => self::get_subscription_period( $order, $item['id'] ),
				'subscription_length'   => self::get_subscription_length( $order, $item['id'] ),
				'trial_length'          => self::get_subscription_trial_length( $order, $item['id'] ),
				'trial_period'          => self::get_subscription_trial_period( $order, $item['id'] )
			);

			if ( self::get_sign_up_fee( $order ) && self::get_subscription_trial_length( $order ) == 0 && $subscription_details['subscription_interval'] != $subscription_details['subscription_length'] )
				$subscription_details['initial_amount'] = $formatted_total;
			else
				$subscription_details['initial_amount'] = '';

			// Use the core WC_Order::get_formatted_line_subtotal() WC function to get the recurring total
			remove_filter( 'woocommerce_order_formatted_line_subtotal', __CLASS__ . '::' . __FUNCTION__, 10, 3 ); // Avoid getting into an infinite loop

			foreach ( self::get_recurring_items( $order ) as $recurring_item )
				if ( $recurring_item['id'] == $item['id'] )
					$subscription_details['recurring_amount'] = $order->get_formatted_line_subtotal( $recurring_item );

			add_filter( 'woocommerce_order_formatted_line_subtotal', __CLASS__ . '::' . __FUNCTION__, 10, 3 );

			$formatted_total = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );
		}

		return $formatted_total;
	}

	/**
	 * Appends the subscription period/duration string to order subtotal
	 *
	 * @since 1.0
	 */
	public static function get_subtotal_to_display( $subtotal, $compound, $order ) {
		global $woocommerce;

		if( self::order_contains_subscription( $order ) ) {

			$subscription_details = array(
				'subscription_interval' => self::get_subscription_interval( $order ),
				'subscription_period'   => self::get_subscription_period( $order )
			);

			// If there is a free trial period and no sign up fee, just show this amount recurring
			if ( self::get_subscription_trial_length( $order ) > 0 && self::get_sign_up_fee( $order ) == 0 ) {

				$subscription_details['recurring_amount'] = $subtotal;

				$subtotal = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );

			} else {

				if ( self::get_sign_up_fee( $order ) > 0 && $subscription_details['subscription_interval'] != self::get_subscription_length( $order ) )
					$subscription_details['initial_amount'] = $subtotal;
				else
					$subscription_details['initial_amount'] = '';

				$recurring_subtotal = 0;

				if ( ! $compound ) {

					foreach ( self::get_recurring_items( $order ) as $item ) {
						$recurring_subtotal += $order->get_line_subtotal( $item ); // Can use the $order function here as we pass it the recurring item amounts

						if ( ! $order->display_cart_ex_tax )
							$recurring_subtotal += $item['line_subtotal_tax'];
					}

					$subscription_details['recurring_amount'] = $recurring_subtotal;

					$subtotal = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );

					if ( $order->display_cart_ex_tax && $order->prices_include_tax )
						$subtotal .= ' <small>' . $woocommerce->countries->ex_tax_or_vat() . '</small>';

				} else {

					foreach ( self::get_recurring_items( $order ) as $item )
						$recurring_subtotal += $item['line_subtotal'];

					// Add Shipping Costs
					$recurring_subtotal += self::get_recurring_shipping_total( $order );

					// Remove non-compound taxes
					foreach ( self::get_recurring_taxes( $order ) as $tax ) {
						if ( isset( $tax['compound'] ) && $tax['compound'] )
							continue;

						$recurring_subtotal = $recurring_subtotal + $tax['cart_tax'] + $tax['shipping_tax'];
					}

					// Remove discounts
					$recurring_subtotal = $recurring_subtotal - self::get_recurring_cart_discount( $order );

					$subscription_details['recurring_amount'] = $recurring_subtotal;

					$subtotal = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );

				}

			}
		}

		return $subtotal;
	}

	/**
	 * Appends the subscription period/duration string to order total
	 *
	 * @since 1.0
	 */
	public static function get_cart_discount_to_display( $discount, $order ) {

		if( self::order_contains_subscription( $order ) ) {

			$subscription_details = array(
				'recurring_amount'      => self::get_recurring_discount_cart( $order ),
				'subscription_interval' => self::get_subscription_interval( $order ),
				'subscription_period'   => self::get_subscription_period( $order )
			);

			if ( self::get_sign_up_fee( $order ) > 0 && self::get_subscription_trial_length( $order ) == 0 && $subscription_details['subscription_interval'] != self::get_subscription_length( $order ) )
				$subscription_details['initial_amount'] = $discount;
			else
				$subscription_details['initial_amount'] = '';

			$discount = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );
			$discount = sprintf( __( '%s discount', WC_Subscriptions::$text_domain ), $discount );
		}

		return $discount;
	}

	/**
	 * Appends the subscription period/duration string to order total
	 *
	 * @since 1.0
	 */
	public static function get_order_discount_to_display( $discount, $order ) {

		if( self::order_contains_subscription( $order ) ) {

			$subscription_details = array(
				'recurring_amount'      => self::get_recurring_discount_total( $order ),
				'subscription_interval' => self::get_subscription_interval( $order ),
				'subscription_period'   => self::get_subscription_period( $order )
			);

			if ( self::get_sign_up_fee( $order ) > 0 && self::get_subscription_trial_length( $order ) == 0 && $subscription_details['subscription_interval'] != self::get_subscription_length( $order ) )
				$subscription_details['initial_amount'] = $discount;
			else
				$subscription_details['initial_amount'] = '';

			$discount = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );
			$discount = sprintf( __( '%s discount', WC_Subscriptions::$text_domain ), $discount );
		}

		return $discount;
	}

	/**
	 * Appends the subscription period/duration string to order total
	 *
	 * @since 1.0
	 */
	public static function get_formatted_order_total( $formatted_total, $order ) {

		if( self::order_contains_subscription( $order ) ) {

			$subscription_details = array(
				'recurring_amount'      => self::get_recurring_total( $order ),
				'subscription_interval' => self::get_subscription_interval( $order ),
				'subscription_period'   => self::get_subscription_period( $order ),
				'subscription_length'   => self::get_subscription_length( $order ),
				'trial_length'          => self::get_subscription_trial_length( $order ),
				'trial_period'          => self::get_subscription_trial_period( $order )
			);

			if ( self::get_sign_up_fee( $order ) > 0 && self::get_subscription_trial_length( $order ) == 0 && $subscription_details['subscription_interval'] != $subscription_details['subscription_length'] )
				$subscription_details['initial_amount'] = $formatted_total;
			else
				$subscription_details['initial_amount'] = '';

			$formatted_total = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );
		}

		return $formatted_total;
	}

	/**
	 * Appends the subscription period/duration string to shipping fee
	 *
	 * @since 1.0
	 */
	public static function get_shipping_to_display( $shipping_to_display, $order ) {

		if( self::order_contains_subscription( $order ) ) {

			$subscription_details = array(
				'recurring_amount'      => self::get_recurring_shipping_total( $order ),
				'subscription_interval' => self::get_subscription_interval( $order ),
				'subscription_period'   => self::get_subscription_period( $order )
			);

			if ( self::get_sign_up_fee( $order ) > 0 && self::get_subscription_trial_length( $order ) == 0 && $subscription_details['subscription_interval'] != self::get_subscription_length( $order ) )
				$subscription_details['initial_amount'] = self::get_recurring_shipping_total( $order );
			else
				$subscription_details['initial_amount'] = '';

			$shipping_to_display = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );
		}

		return $shipping_to_display;
	}

	/**
	 * Individual totals are taken care of by filters, but taxes are not, so we need to override them here.
	 * 
	 * @since 1.0
	 */
	public static function get_order_item_totals( $total_rows, $order ) {
		global $woocommerce;

		if ( self::order_contains_subscription( $order ) && self::get_recurring_total_tax( $order ) > 0 ) {

			$order_taxes         = $order->get_taxes();
			$recurring_taxes     = self::get_recurring_taxes( $order );
			$subscription_length = self::get_subscription_length( $order );
			$sign_up_fee         = self::get_sign_up_fee( $order );
			$trial_length        = self::get_subscription_trial_length( $order );

			// Only want to display recurring amounts for taxes, no need for trial period, length etc.
			$subscription_details = array(
				'subscription_interval' => self::get_subscription_interval( $order ),
				'subscription_period'   => self::get_subscription_period( $order )
			);

			if ( count( $order_taxes ) > 0 || count( $recurring_taxes ) > 0 ) {

				foreach ( $recurring_taxes as $index => $tax ) {

					$tax_key = sanitize_title( $tax['label'] );

					if ( $tax['compound'] ) {
						$has_compound_tax = true;
						continue;
					}

					if ( $tax['cart_tax'] > 0 ) {

						$order_tax     = ( isset( $order_taxes[$index]['cart_tax'] ) ) ? $order_taxes[$index]['cart_tax'] + $order_taxes[$index]['shipping_tax'] : '';
						$recurring_tax = ( isset( $recurring_taxes[$index]['cart_tax'] ) ) ? $recurring_taxes[$index]['cart_tax'] + $recurring_taxes[$index]['shipping_tax'] : '';

						$subscription_details['recurring_amount'] = $recurring_tax;

						if ( $sign_up_fee > 0 && $trial_length == 0 && $subscription_details['subscription_interval'] != $subscription_length )
							$subscription_details['initial_amount'] = $order_tax;
						else
							$subscription_details['initial_amount'] = '';

						$total_rows[$tax_key]['value'] = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );

					} elseif ( $tax['shipping_tax'] > 0  ) { // Just a recurring shipping tax

						if ( $sign_up_fee > 0 && $trial_length == 0 && $subscription_details['subscription_interval'] != $subscription_length )
							$subscription_details['initial_amount'] = $tax['shipping_tax'];
						else
							$subscription_details['initial_amount'] = '';

						$subscription_details['recurring_amount'] = $tax['shipping_tax'];

						$shipping_tax_row = array(
							$tax_key . '_shipping' => array(
								'label' => $tax['label'],
								'value' => WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details )
							)
						);

						// Insert the tax just before the order total
						$total_rows = array_splice( $total_rows, 0, -1 ) + $shipping_tax_row + array_splice( $total_rows, -1 );
					}
				}

				foreach ( $recurring_taxes as $index => $tax ) {

					$tax_key = sanitize_title( $tax['label'] );

					if ( ! $tax['compound'] )
						continue;

					if ( $tax['cart_tax'] > 0 ) {

						$order_tax     = ( isset( $order_taxes[$index]['cart_tax'] ) ) ? $order_taxes[$index]['cart_tax'] + $order_taxes[$index]['shipping_tax'] : '';
						$recurring_tax = ( isset( $recurring_taxes[$index]['cart_tax'] ) ) ? $recurring_taxes[$index]['cart_tax'] + $recurring_taxes[$index]['shipping_tax'] : '';

						if ( $sign_up_fee > 0 && $trial_length == 0 && $subscription_details['subscription_interval'] != $subscription_length )
							$subscription_details['initial_amount'] = $order_tax;
						else
							$subscription_details['initial_amount'] = '';

						$subscription_details['recurring_amount'] = $recurring_tax;

						$total_rows[$tax_key]['value'] = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );

					} elseif ( $tax['shipping_tax'] > 0  ) { // Just a recurring shipping tax

						if ( $sign_up_fee > 0 && $trial_length == 0 && $subscription_details['subscription_interval'] != $subscription_length )
							$subscription_details['initial_amount'] = $tax['shipping_tax'];
						else
							$subscription_details['initial_amount'] = '';

						$subscription_details['recurring_amount'] = $tax['shipping_tax'];

						$shipping_tax_row = array(
							$tax_key . '_shipping' => array(
								'label' => $tax['label'],
								'value' => WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details )
							)
						);

						// Insert the tax just before the order total
						$total_rows = array_splice( $total_rows, 0, -1 ) + $shipping_tax_row + array_splice( $total_rows, -1, 0 );
					}
				}

			} else {

				if ( isset( $total_rows['tax'] ) ) {

					$subscription_details['initial_amount']   = woocommerce_price( $order->get_total_tax() );
					$subscription_details['recurring_amount'] = self::get_recurring_total_tax( $order );

					$total_rows['tax']['value'] = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );
				}
			}
		}

		return $total_rows;
	}

	/**
	 * Displays a few details about what happens to their subscription. Hooked
	 * to the thank you page. 
	 *
	 * @since 1.0
	 */
	public static function subscription_thank_you( $order_id ){

		if( self::order_contains_subscription( $order_id ) ) {
			echo '<p>' . __( 'Your subscription will be activated when payment clears.', WC_Subscriptions::$text_domain ) . '</p>';
			echo '<p>' . sprintf( __( 'View the status of your subscription in %syour account%s.', WC_Subscriptions::$text_domain ), '<a href="' . get_permalink( woocommerce_get_page_id( 'myaccount' ) ) . '">', '</a>' ) . '</p>';
		}
	}

	/**
	 * Returns the number of failed payments for a given subscription.
	 * 
	 * @param $order WC_Order The WC_Order object of the order for which you want to determine the number of failed payments.
	 * @param product_id int The ID of the subscription product.
	 * @return string The key representing the given subscription.
	 * @since 1.0
	 */
	public static function get_failed_payment_count( $order, $product_id ) {

		$failed_payment_count = WC_Subscriptions_Manager::get_subscriptions_failed_payment_count( WC_Subscriptions_Manager::get_subscription_key( $order->id, $product_id ), $order->customer_user );

		return $failed_payment_count;
	}

	/**
	 * Returns the amount outstanding on a subscription product.
	 * 
	 * @param $order WC_Order The WC_Order object of the order for which you want to determine the number of failed payments.
	 * @param product_id int The ID of the subscription product.
	 * @return string The key representing the given subscription.
	 * @since 1.0
	 */
	public static function get_outstanding_balance( $order, $product_id ) {

		$failed_payment_count = self::get_failed_payment_count( $order, $product_id );

		$oustanding_balance = $failed_payment_count * self::get_recurring_total( $order, $product_id );

		return $oustanding_balance;
	}

	/**
	 * Output a hidden element in the order status of the orders list table to provide information about whether
	 * the order displayed in that row contains a subscription or not.
	 * 
	 * @param $column String The string of the current column.
	 * @since 1.1
	 */
	public static function add_contains_subscription_hidden_field( $column ) {
		global $post;

		if ( $column == 'order_status' )
			self::contains_subscription_hidden_field( $post->ID );
	}

	/**
	 * Output a hidden element in the order status of the orders list table to provide information about whether
	 * the order displayed in that row contains a subscription or not.
	 * 
	 * @param $column String The string of the current column.
	 * @since 1.1
	 */
	public static function contains_subscription_hidden_field( $order_id ) {

		$has_subscription = WC_Subscriptions_Order::order_contains_subscription( $order_id ) ? 'true' : 'false';

		echo '<input type="hidden" name="contains_subscription" value="' . $has_subscription . '">';
	}

	/**
	 * When an order is added or updated from the admin interface, check if a new subscription product
	 * has been manually added to the order, and if one has, create a new subscription. 
	 * 
	 * @param $post_id int The ID of the post which is the WC_Order object.
	 * @param $post Object The post object of the order.
	 * @since 1.1
	 */
	public static function maybe_manually_change_subscriptions( $post_id, $post ) {

		$order = new WC_Order( $post_id );

		// Check if all the subscription products on the order have associated subscriptions on the user's account, and if not, add a new one
		foreach ( $_POST['item_id'] as $item_id ) {

			if ( ! WC_Subscriptions_Order::is_item_subscription( $order, $item_id ) )
				continue;

			$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $post_id, $item_id );

			$subscription = array();

			// If order customer changed, move the subscription from the old customer's account to the new customer
			if ( ! empty( $order->customer_user ) && $order->customer_user != (int)$_POST['customer_user'] ) {

				$subscription = WC_Subscriptions_Manager::remove_users_subscription( $order->customer_user, $subscription_key );

				$subscriptions = WC_Subscriptions_Manager::get_users_subscriptions( (int)$_POST['customer_user'] );

				if ( ! empty( $subscription ) ) {
					$subscriptions[$subscription_key] = $subscription;
					WC_Subscriptions_Manager::update_users_subscriptions( (int)$_POST['customer_user'], $subscriptions );
				}
			}

			// In case it's a new order or the customer has changed
			$order->customer_user = $order->user_id = (int)$_POST['customer_user'];

			$subscription = WC_Subscriptions_Manager::get_users_subscription( $order->customer_user, $subscription_key );

			if ( empty( $subscription ) ) { // Add a new subscription

				// The order doesn't may not exist yet, so we need to set a few things ourselves
				$order->order_key = uniqid( 'order_' );
				add_post_meta( $post_id, '_order_key', $order->order_key, true );

				WC_Subscriptions_Manager::create_pending_subscription_for_order( $order, $item_id );

				// Add the subscription meta for this item to the order
				$functions_and_meta = array( 'get_period' => '_order_subscription_periods', 'get_interval' => '_order_subscription_intervals', 'get_length' => '_order_subscription_lengths' );

				foreach ( $functions_and_meta as $function_name => $meta_key ) {
					$subscription_meta = self::get_meta( $order, $meta_key, array() );
					$subscription_meta[$item_id] = WC_Subscriptions_Product::$function_name( $item_id );
					update_post_meta( $order->id, $meta_key, $subscription_meta );
				}

				// Set the subscription's status if it should be something other than pending
				switch( $order->status ) {
					case 'completed' :
					case 'processing' :
						WC_Subscriptions_Manager::activate_subscription( $order->customer_user, $subscription_key );
						break;
					case 'refunded' :
					case 'cancelled' :
						WC_Subscriptions_Manager::cancel_subscription( $order->customer_user, $subscription_key );
						break;
					case 'failed' :
						WC_Subscriptions_Manager::failed_subscription_signup( $order->customer_user, $subscription_key );
						break;
				}
			}
		}

	}

	/**
	 * Once payment is completed on an order, set a lock on payments until the next subscription payment period.
	 * 
	 * @param $user_id int The id of the user who purchased the subscription
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.1.2
	 */
	public static function safeguard_scheduled_payments( $order_id ) {

		$order = new WC_Order( $order_id );

		if ( self::order_contains_subscription( $order ) ) {

			$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $order_id );

			WC_Subscriptions_Manager::safeguard_scheduled_payments( $order->customer_user, $subscription_key );

		}
	}

	/**
	 * Records the initial payment against a subscription. 
	 *
	 * This function is called when a gateway calls @see WC_Order::payment_complete() and payment
	 * is completed on an order. It is also called when an orders status is changed to completed or
	 * processing for those gateways which never call @see WC_Order::payment_complete(), like the 
	 * core WooCommerce Cheque and Bank Transfer gateways.
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.1.2
	 */
	public static function maybe_record_order_payment( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$subscriptions_in_order = self::get_recurring_items( $order );

		foreach ( $subscriptions_in_order as $subscription_item ) {

			$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $order->id, $subscription_item['id'] );
			$subscription     = WC_Subscriptions_Manager::get_subscription( $subscription_key, $order->customer_user );

			// No payments have been recorded yet
			if ( empty( $subscription['completed_payments'] ) ) {

				// Don't duplicate orders
				remove_action( 'processed_subscription_payment', 'WC_Subscriptions_Renewal_Order::generate_paid_renewal_order', 10, 2 );

				WC_Subscriptions_Manager::process_subscription_payments_on_order( $order->id );

				WC_Subscriptions_Manager::safeguard_scheduled_payments( $order->customer_user, $subscription_key );

				// Make sure orders are still generated for other payments in the same request
				add_action( 'processed_subscription_payment', 'WC_Subscriptions_Renewal_Order::generate_paid_renewal_order', 10, 2 );
			}
		}
	}

	/* Order Price Getters */

	/**
	 * Returns the proportion of cart discount that is recurring for the product specified with $product_id
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_discount_cart( $order, $product_id = '' ) {
		return self::get_meta( $order, '_order_recurring_discount_cart', 0 );
	}

	/**
	 * Returns the proportion of total discount that is recurring for the product specified with $product_id
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_discount_total( $order, $product_id = '' ) {
		return self::get_meta( $order, '_order_recurring_discount_total', 0 );
	}

	/**
	 * Returns the amount of shipping tax that is recurring. As shipping only applies
	 * to recurring payments, and only 1 subscription can be purchased at a time, 
	 * this is equal to @see WC_Order::get_shipping()
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_shipping_tax_total( $order, $product_id = '' ) {
		return $order->get_shipping_tax();
	}

	/**
	 * Returns the recurring shipping price . As shipping only applies to recurring
	 * payments, and only 1 subscription can be purchased at a time, this is
	 * equal to @see WC_Order::get_shipping()
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_shipping_total( $order, $product_id = '' ) {
		return $order->get_shipping();
	}

	/**
	 * Returns an array of items in an order which are recurring along with their recurring totals.
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_items( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$items = array();

		foreach ( $order->get_items() as $item_id => $item_details ) {

			if ( ! self::is_item_subscription( $order,$item_details ) )
				continue;

			$items[$item_id] = $item_details;

			foreach ( $item_details['item_meta'] as $item_meta ) {
				switch ( $item_meta['meta_name'] ) {
					case '_recurring_line_subtotal' :
						$items[$item_id]['line_subtotal'] = $item_meta['meta_value'];
						break;
					case '_recurring_line_subtotal_tax' :
						$items[$item_id]['line_subtotal_tax'] = $item_meta['meta_value'];
						break;
					case '_recurring_line_total' :
						$items[$item_id]['line_total'] = $item_meta['meta_value'];
						break;
					case '_recurring_line_tax' :
						$items[$item_id]['line_tax'] = $item_meta['meta_value'];
						break;
				}
			}
		}

		return $items;
	}

	/**
	 * Checks if a given order item is a subscription. For now, this is determine as a subscription with will have 
	 * a piece of meta with the 'meta_name' of 'recurring_line_subtotal'.
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @param $item Array | int An array representing an order item or a product ID of an item in an order
	 * @since 1.2
	 */
	public static function is_item_subscription( $order, $item ) {

		if ( ! is_array( $item ) )
			$item = self::get_item( $order, $item );

		$item_is_subscription = false;

		foreach ( $item['item_meta'] as $item_meta ) {
			if ( $item_meta['meta_name'] == '_recurring_line_subtotal' ) {
				$item_is_subscription = true;
				continue;
			}
		}

		return $item_is_subscription;
	}

	/**
	 * Returns an array of taxes on an order with their recurring totals.
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_taxes( $order ) {
		return self::get_meta( $order, '_order_recurring_taxes', array() );
	}

	/**
	 * Returns the proportion of total tax on an order that is recurring for the product specified with $product_id
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_total_tax( $order, $product_id = '' ) {
		return self::get_meta( $order, '_order_recurring_tax_total', 0 );
	}

	/**
	 * Returns the proportion of total before tax on an order that is recurring for the product specified with $product_id
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @since 1.2
	 */
	public static function get_recurring_total_ex_tax( $order, $product_id = '' ) {
		return self::get_recurring_total( $order, $product_id ) - self::get_recurring_total_tax( $order, $product_id );
	}

	/**
	 * Returns the price per period for a subscription in an order.
	 * 
	 * @param $order mixed A WC_Order object or the ID of the order which the subscription was purchased in.
	 * @param $product_id int (optional) The post ID of the subscription WC_Product object purchased in the order. Defaults to the ID of the first product purchased in the order.
	 * @since 1.2
	 */
	public static function get_recurring_total( $order ) {
		return self::get_meta( $order, '_order_recurring_total', 0 );
	}

	/**
	 * Determines the proportion of the order total that a recurring amount accounts for and
	 * returns that proportion.
	 *
	 * If there is only one subscription in the order and no sign up fee for the subscription, 
	 * this function will return 1 (i.e. 100%).
	 *
	 * Shipping only applies to recurring amounts so is deducted from both the order total and 
	 * recurring amount so it does not distort the proportion.
	 *
	 * @param $order WC_Order | int A WC_Order object or ID of a WC_Order order.
	 * @return float The proportion of the order total which the recurring amount accounts for
	 * @since 1.2
	 */
	public static function get_recurring_total_proportion( $order, $product_id = '' ) {

		$order_shipping_total          = $order->get_shipping() + $order->get_shipping_tax();
		$order_total_sans_shipping     = $order->get_total() - $order_shipping_total;
		$recurring_total_sans_shipping = self::get_recurring_total( $order, $product_id ) - $order_shipping_total;

		return $recurring_total_sans_shipping / $order_total_sans_shipping;
	}

	/**
	 * Creates a string representation of the subscription period/term for each item in the cart
	 * 
	 * @param $order WC_Order A WC_Order object.
	 * @param mixed $deprecated Never used.
	 * @param mixed $deprecated Never used.
	 * @since 1.0
	 */
	public static function get_order_subscription_string( $order, $deprecated_price = '', $deprecated_sign_up_fee = '' ) {

		if ( ! empty( $deprecated_price ) || ! empty( $deprecated_sign_up_fee ) )
			_deprecated_argument( __CLASS__ . '::' . __FUNCTION__, '1.2' );

		$subscription_details = array(
			'initial_amount'        => self::get_total_initial_payment( $order ),
			'initial_description'   => __( 'now', WC_Subscriptions::$text_domain ),
			'recurring_amount'      => self::get_recurring_total( $order ),
			'subscription_interval' => self::get_subscription_interval( $order ),
			'subscription_period'   => self::get_subscription_period( $order ),
			'subscription_length'   => self::get_subscription_length( $order ),
			'trial_length'          => self::get_subscription_trial_length( $order ),
			'trial_period'          => self::get_subscription_trial_period( $order )
		);

		$subscription_string = WC_Subscriptions_Manager::get_subscription_price_string( $subscription_details );

		return $subscription_string;
	}

	/* Deprecated Functions */

	/**
	 * Returned the recurring amount for a subscription in an order.
	 * 
	 * @deprecated 1.2
	 * @since 1.0
	 */
	public static function get_price_per_period( $order, $product_id = '' ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', __CLASS__ . '::get_recurring_total( $order, $product_id )' );
		return self::get_recurring_total( $order, $product_id );
	}

	/**
	 * Creates a new order for renewing a subscription product based on the details of a previous order.
	 *
	 * @param $order WC_Order | int The WC_Order object or ID of the order for which the a new order should be created.
	 * @param $product_id string The ID of the subscription product in the order which needs to be added to the new order.
	 * @param $new_order_role string A flag to indicate whether the new order should become the master order for the subscription. Accepts either 'parent' or 'child'. Defaults to 'parent' - replace the existing order.
	 * @deprecated 1.2
	 * @since 1.0
	 */
	public static function generate_renewal_order( $original_order, $product_id, $new_order_role = 'parent' ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', 'WC_Subscriptions_Renewal_Order::generate_renewal_order( $original_order, $product_id, $new_order_role )' );
		return WC_Subscriptions_Renewal_Order::generate_renewal_order( $original_order, $product_id, $new_order_role );
	}

	/**
	 * Hooks to the renewal order created action to determine if the order should be emailed to the customer. 
	 *
	 * @param $order WC_Order | int The WC_Order object or ID of a WC_Order order.
	 * @deprecated 1.2
	 * @since 1.0
	 */
	public static function maybe_send_customer_renewal_order_email( $order ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', 'WC_Subscriptions_Renewal_Order::maybe_send_customer_renewal_order_email( $order )' );
		WC_Subscriptions_Renewal_Order::maybe_send_customer_renewal_order_email( $order );
	}

	/**
	 * Processing Order
	 * 
	 * @param $order WC_Order | int The WC_Order object or ID of a WC_Order order.
	 * @deprecated 1.2
	 * @since 1.0
	 */
	public static function send_customer_renewal_order_email( $order ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', 'WC_Subscriptions_Renewal_Order::send_customer_renewal_order_email( $order )' );
		WC_Subscriptions_Renewal_Order::send_customer_renewal_order_email( $order );
	}

	/**
	 * Check if a given order is a subscription renewal order
	 * 
	 * @param $order WC_Order | int The WC_Order object or ID of a WC_Order order.
	 * @deprecated 1.2
	 * @since 1.0
	 */
	public static function is_renewal( $order ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', 'WC_Subscriptions_Renewal_Order::is_renewal( $order )' );
		return WC_Subscriptions_Renewal_Order::is_renewal( $order );
	}

	/**
	 * Once payment is completed on an order, record the payment against the subscription automatically so that
	 * payment gateway extension developers don't have to do this.
	 * 
	 * @param $order_id int The id of the order to record payment against
	 * @deprecated 1.2
	 * @since 1.1.2
	 */
	public static function record_order_payment( $order_id ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', __CLASS__ . '::maybe_record_order_payment( $order_id )' );
		return self::maybe_record_order_payment( $order_id );
	}
}

WC_Subscriptions_Order::init();
