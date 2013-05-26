<?php
/**
 * class-groups-ws-handler.php
 *
 * Copyright (c) "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is provided subject to the license granted.
 * Unauthorized use and distribution is prohibited.
 * See COPYRIGHT.txt and LICENSE.txt
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This header and all notices must be kept intact.
 *
 * @author Karim Rahimpur
 * @package groups-woocommerce
 * @since groups-woocommerce 1.0.0
 */

/**
 * Product & subscription handler.
 */
class Groups_WS_Handler {

	/**
	 * Register action hooks.
	 */
	public static function init() {

		$options = get_option( 'groups-woocommerce', array() );
		$order_status = isset( $options[GROUPS_WS_MEMBERSHIP_ORDER_STATUS] ) ? $options[GROUPS_WS_MEMBERSHIP_ORDER_STATUS] : GROUPS_WS_DEFAULT_MEMBERSHIP_ORDER_STATUS;

		// normal products

		// the essentials for normal order processing flow
		add_action ( 'woocommerce_order_status_cancelled',  array( __CLASS__, 'order_status_cancelled' ) );
		add_action ( 'woocommerce_order_status_completed',  array( __CLASS__, 'order_status_completed' ) );
		if ( $order_status == 'processing' ) {
			add_action ( 'woocommerce_order_status_processing', array( __CLASS__, 'order_status_completed' ) );
		} else {
			add_action ( 'woocommerce_order_status_processing', array( __CLASS__, 'order_status_processing' ) );
		}
		add_action ( 'woocommerce_order_status_refunded',   array( __CLASS__, 'order_status_refunded' ) );

		// these are of concern when manual adjustments are made (backwards in order flow) 
		add_action ( 'woocommerce_order_status_failed',     array( __CLASS__, 'order_status_failed' ) );
		add_action ( 'woocommerce_order_status_on_hold',    array( __CLASS__, 'order_status_on_hold' ) );
		add_action ( 'woocommerce_order_status_pending',    array( __CLASS__, 'order_status_pending' ) );
		

		// subscriptions

		// do_action( 'activated_subscription', $user_id, $subscription_key );
		add_action( 'activated_subscription', array( __CLASS__, 'activated_subscription' ), 10, 2 );
		// do_action( 'cancelled_subscription', $user_id, $subscription_key );
		add_action( 'cancelled_subscription', array( __CLASS__, 'cancelled_subscription' ), 10, 2 );
		// do_action( 'subscription_trashed', $user_id, $subscription_key );
		add_action( 'subscription_trashed', array( __CLASS__, 'subscription_trashed' ), 10, 2 );
		// do_action( 'subscription_expired', $user_id, $subscription_key );
		add_action( 'subscription_expired', array( __CLASS__, 'subscription_expired' ), 10, 2 );

		// scheduled expirations

		add_action( 'groups_ws_subscription_expired', array( __CLASS__, 'subscription_expired' ), 10, 2 );
		
		// time-limited memberships
		add_action( 'groups_created_user_group', array( __CLASS__, 'groups_created_user_group' ), 10, 2 );
		add_action( 'groups_deleted_user_group', array( __CLASS__, 'groups_deleted_user_group' ), 10, 2 );
	}

	/**
	 * Cancel group memberships for the order.
	 * @param int $order_id
	 */
	public static function order_status_cancelled( $order_id ) {
		$order = new WC_Order();
		if ( $order->get_order( $order_id ) ) {
			if ( $items = $order->get_items() ) {
				if ( $user_id = $order->user_id ) { // not much we can do if there isn't
					foreach ( $items as $item ) {
						if ( $product = $order->get_product_from_item( $item ) ) {
							// don't act on subscriptions here
							if ( !class_exists( 'WC_Subscriptions_Product' ) || !WC_Subscriptions_Product::is_subscription( $product->id ) ) {
								$groups_product_groups = get_user_meta( $user_id, '_groups_product_groups', true );
								if ( isset( $groups_product_groups[$order_id] ) &&
									 isset( $groups_product_groups[$order_id][$product->id] ) &&
									 isset( $groups_product_groups[$order_id][$product->id]['groups'] )
								) {
									foreach( $groups_product_groups[$order_id][$product->id]['groups'] as $group_id ) {
										Groups_User_Group::delete( $user_id, $group_id );
									}
								}
							}
						}
					}
				}
			}
		}

		self::unregister_order( $order_id );
	}

	/**
	 * Creates group membership for the order.
	 * @param int $order_id
	 */
	public static function order_status_completed( $order_id ) {

		$unhandled = self::register_order( $order_id );

		$order = new WC_Order();
		if ( $order->get_order( $order_id ) ) {
			if ( $items = $order->get_items() ) {
				if ( $user_id = $order->user_id ) { // not much we can do if there isn't
					foreach ( $items as $item ) {
						if ( $product = $order->get_product_from_item( $item ) ) {
							if ( $product_groups = get_post_meta( $product->id, '_groups_groups', false ) ) {
								// don't act on subscriptions here
								if ( !class_exists( 'WC_Subscriptions_Product' ) || !WC_Subscriptions_Product::is_subscription( $product->id ) ) {
									if ( count( $product_groups ) > 0 ) {
										// add the groups to the user by order and product so that if the product is changed later on,
										// the data is still valid for what has been purchased
										$groups_product_groups = get_user_meta( $user_id, '_groups_product_groups', true );
										if ( empty( $groups_product_groups ) ) {
											$groups_product_groups = array();
										}
										$groups_product_groups[$order_id][$product->id]['groups'] = $product_groups;
										update_user_meta( $user_id, '_groups_product_groups', $groups_product_groups );
										global $groups_ws_product_with_duration;
										$groups_ws_product_with_duration = Groups_WS_Product::has_duration( $product );

										// add the user to the groups
										foreach( $product_groups as $group_id ) {
											$result = Groups_User_Group::create(
												array(
													'user_id' => $user_id,
													'group_id' => $group_id
												)
											);
											if ( $groups_ws_product_with_duration ) {
												if ( $unhandled ) {
													Groups_WS_Terminator::schedule_termination( time() + Groups_WS_Product::get_duration( $product ), $user_id, $group_id );
												}
											} else {
												Groups_WS_Terminator::mark_as_eternal( $user_id, $group_id );
											}
										}

									}
								}
							}
							// remove from groups
							if ( $product_groups_remove = get_post_meta( $product->id, '_groups_groups_remove', false ) ) {
								if ( !class_exists( 'WC_Subscriptions_Product' ) || !WC_Subscriptions_Product::is_subscription( $product->id ) ) {
									if ( count( $product_groups_remove )  > 0 ) {
										$groups_product_groups_remove = get_user_meta( $user_id, '_groups_product_groups_remove', true );
										if ( empty( $groups_product_groups_remove ) ) {
											$groups_product_groups_remove = array();
										}
										$groups_product_groups_remove[$order_id][$product->id]['groups'] = $product_groups_remove;
										update_user_meta( $user_id, '_groups_product_groups_remove', $groups_product_groups_remove );
										// remove the user from the groups
										foreach( $product_groups_remove as $group_id ) {
											$result = Groups_User_Group::delete( $user_id, $group_id );
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Revokes group memberships for the order.
	 * @param int $order_id
	 */
	public static function order_status_refunded( $order_id ) {
		self::order_status_cancelled( $order_id );
	}

	/**
	 * Proxy for cancel.
	 * @param int $order_id
	 */
	public static function order_status_failed( $order_id ) {
		self::order_status_cancelled( $order_id );
	}

	/**
	 * Proxy for cancel.
	 * @param int $order_id
	 */
	public static function order_status_on_hold( $order_id ) {
		self::order_status_cancelled( $order_id );
	}

	/**
	 * Proxy for cancel.
	 * @param int $order_id
	 */
	public static function order_status_pending( $order_id ) {
		self::order_status_cancelled( $order_id );
	}

	/**
	 * Proxy for cancel.
	 * @param int $order_id
	 */
	public static function order_status_processing( $order_id ) {
		self::order_status_cancelled( $order_id );
	}

	/**
	 * Hooked on user added to group.
	 * @param int $user_id
	 * @param int $group_id
	 */
	public static function groups_created_user_group( $user_id, $group_id ) {
		global $groups_ws_product_with_duration;
		if ( !isset( $groups_ws_product_with_duration ) || !$groups_ws_product_with_duration ) {
			Groups_WS_Terminator::mark_as_eternal( $user_id, $group_id );
		}
	}

	/**
	 * Hooked on user removed from group.
	 * @param int $user_id
	 * @param int $group_id
	 */
	public static function groups_deleted_user_group( $user_id, $group_id ) {
		Groups_WS_Terminator::lift_scheduled_terminations( $user_id, $group_id, false );
	}

	/**
	 * Marks order as handled only if not already marked.
	 * @param int $order_id
	 * @return boolean true if order wasn't handled yet and could be marked as handled, otherwise false
	 */
	public static function register_order( $order_id ) {
		$registered = false;
		$order = new WC_Order();
		if ( $order->get_order( $order_id ) ) {
			$r = get_post_meta( $order->id, '_groups_ws_registered', true );
			if ( empty( $r ) ) {
				$registered = update_post_meta( $order->id, '_groups_ws_registered', true );
			}
		}
		return $registered;
	}

	/**
	 * Marks order as not handled.
	 * @param int $order_id
	 * @return boolean true if order could be marked as not handled, false on failure
	 */
	public static function unregister_order( $order_id ) {
		$unregistered = false;
		$order = new WC_Order();
		if ( $order->get_order( $order_id ) ) {
			$r = get_post_meta( $order->id, '_groups_ws_registered', true );
			if ( !empty( $r ) ) {
				$unregistered = delete_post_meta( $order->id, '_groups_ws_registered' );
			}
		}
		return $unregistered;
	}

	/**
	 * Handle group assignment : assign the user to the groups related to the subscription's product.
	 * @param int $user_id
	 * @param string $subscription_key
	 */
	public static function activated_subscription( $user_id, $subscription_key ) {
		$subscription = WC_Subscriptions_Manager::get_users_subscription( $user_id, $subscription_key );
		if ( isset( $subscription['product_id'] ) && isset( $subscription['order_id'] ) ) {
			$product_id = $subscription['product_id'];
			$order_id = $subscription['order_id'];
			// Leasving this here for reference, it can be assumed that normally,
			// if the product's groups are modified, a reactivation should take its
			// data from the current product, not from its previous state.
			// See if the subscription was activated before and try to get subscription's groups.
			// If there are any, use these instead of those from the product.
			// This is necessary when a subscription has been cancelled and re-activated and the
			// original product groups were modified since and we do NOT want to make group
			// assignments based on the current state of the product.
			$done = false;
			//$groups_product_groups = get_user_meta( $user_id, '_groups_product_groups', true );
			//if ( isset( $groups_product_groups[$order_id] ) && isset( $groups_product_groups[$order_id][$product_id] ) &&
			//	 isset( $groups_product_groups[$order_id][$product_id]['groups'] ) &&
			//	 isset( $groups_product_groups[$order_id][$product_id]['subscription_key'] ) &&
			//	( $groups_product_groups[$order_id][$product_id]['subscription_key'] === $subscription_key )
			//) {
			//	foreach( $groups_product_groups[$order_id][$product_id]['groups'] as $group_id ) {
			//		Groups_User_Group::create( $user_id, $group_id );
			//	}
			//	$done = true;
			//}

			// maybe unschedule pending expiration
			wp_clear_scheduled_hook(
				'groups_ws_subscription_expired',
				array(
					'user_id' => $user_id,
					'subscription_key' => $subscription_key
				)
			);

			if ( !$done ) {
				// get the product from the subscription
				$product = new WC_Product( $product_id );
				if ( $product->exists() ) {
					// get the groups related to the product
					if ( $product_groups = get_post_meta( $product_id, '_groups_groups', false ) ) {
						if ( count( $product_groups )  > 0 ) {
							// add the groups to the subscription (in case the product is changed later on, the subscription is still valid)
							$groups_product_groups = get_user_meta( $user_id, '_groups_product_groups', true );
							if ( empty( $groups_product_groups ) ) {
								$groups_product_groups = array();
							}
							$groups_product_groups[$order_id][$product_id]['groups'] = $product_groups;
							$groups_product_groups[$order_id][$product_id]['subscription_key'] = $subscription_key;
							update_user_meta( $user_id, '_groups_product_groups', $groups_product_groups );
							// add the user to the groups
							foreach( $product_groups as $group_id ) {
								$result = Groups_User_Group::create(
									array(
										'user_id' => $user_id,
										'group_id' => $group_id
									)
								);
							}
							Groups_WS_Terminator::mark_as_eternal( $user_id, $group_id );
						}
					}
					// remove from groups
					if ( $product_groups_remove = get_post_meta( $product_id, '_groups_groups_remove', false ) ) {
						if ( count( $product_groups_remove )  > 0 ) {
							$groups_product_groups_remove = get_user_meta( $user_id, '_groups_product_groups_remove', true );
							if ( empty( $groups_product_groups_remove ) ) {
								$groups_product_groups_remove = array();
							}
							$groups_product_groups_remove[$order_id][$product_id]['groups'] = $product_groups_remove;
							update_user_meta( $user_id, '_groups_product_groups_remove', $groups_product_groups_remove );
							// remove the user from the groups
							foreach( $product_groups_remove as $group_id ) {
								$result = Groups_User_Group::delete( $user_id, $group_id );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Remove the user from the subscription product's related groups.
	 * @param int $user_id
	 * @param string $subscription_key
	 */
	public static function cancelled_subscription( $user_id, $subscription_key ) {
		// Instead of just doing:
		//     self::subscription_expired( $user_id, $subscription_key );
		// schedule it so that memberships will last as long as the member
		// has actually paid for.
		$subscription = WC_Subscriptions_Manager::get_users_subscription( $user_id, $subscription_key );
		if ( isset( $subscription['product_id'] ) && isset( $subscription['order_id'] ) ) {
			$expire_timestamp = strtotime (
				WC_Subscriptions_Order::get_next_payment_date(
					$subscription['order_id'],
					$subscription['product_id']
				)
			);
			wp_schedule_single_event(
				$expire_timestamp,
				'groups_ws_subscription_expired', 
				array(
					'user_id' => $user_id,
					'subscription_key' => $subscription_key
				)
			);
		}
	}

	/**
	 * Trashed subscriptions expire immediately.
	 * @param int $user_id
	 * @param string $subscription_key
	 */
	public static function subscription_trashed( $user_id, $subscription_key ) {
		self::subscription_expired( $user_id, $subscription_key );
		// unschedule pending expiration if any
		wp_clear_scheduled_hook(
			'groups_ws_subscription_expired',
			array(
				'user_id' => $user_id,
				'subscription_key' => $subscription_key
			)
		);
	}

	/**
	 * Same as when a subscription is cancelled.
	 * @param int $user_id
	 * @param string $subscription_key
	 */
	public static function subscription_expired( $user_id, $subscription_key ) {
		$subscription = WC_Subscriptions_Manager::get_users_subscription( $user_id, $subscription_key );
		if ( isset( $subscription['product_id'] ) && isset( $subscription['order_id'] ) ) {
			$product_id = $subscription['product_id'];
			$order_id = $subscription['order_id'];
			$groups_product_groups = get_user_meta( $user_id, '_groups_product_groups', true );
			if ( isset( $groups_product_groups[$order_id] ) &&
				 isset( $groups_product_groups[$order_id][$product_id] ) &&
				 isset( $groups_product_groups[$order_id][$product_id]['groups'] )
			) {
				foreach( $groups_product_groups[$order_id][$product_id]['groups'] as $group_id ) {
					Groups_User_Group::delete( $user_id, $group_id );
				}
			}
		}
	}
}
Groups_WS_Handler::init();
