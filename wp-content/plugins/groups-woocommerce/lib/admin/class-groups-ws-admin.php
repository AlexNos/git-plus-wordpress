<?php
/**
 * class-groups-ws-admin.php
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
 * @since groups-woocommerce 1.2.0
 */

/**
 * Admin section for Groups integration.
 */
class Groups_WS_Admin {
	
	const NONCE = 'groups-woocommerce-admin-nonce';
	const MEMBERSHIP_ORDER_STATUS = GROUPS_WS_MEMBERSHIP_ORDER_STATUS;
	const SHOW_DURATION           = GROUPS_WS_SHOW_DURATION;
	
	/**
	 * Admin setup.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 40 );
	}
	
	/**
	 * Adds the admin section.
	 */
	public static function admin_menu() {
		$admin_page = add_submenu_page(
			'woocommerce',
			__( 'Groups' ),
			__( 'Groups' ),
			GROUPS_ADMINISTER_OPTIONS,
			'groups_woocommerce',
			array( __CLASS__, 'groups_woocommerce' )
		);
// 		add_action( 'admin_print_scripts-' . $admin_page, array( __CLASS__, 'admin_print_scripts' ) );
// 		add_action( 'admin_print_styles-' . $admin_page, array( __CLASS__, 'admin_print_styles' ) );
	}
	
	/**
	 * Renders the admin section.
	 */
	public static function groups_woocommerce() {

		if ( !current_user_can( GROUPS_ADMINISTER_OPTIONS ) ) {
			wp_die( __( 'Access denied.', GROUPS_WS_PLUGIN_DOMAIN ) );
		}

		$options = get_option( 'groups-woocommerce', null );
		if ( $options === null ) {
			if ( add_option( 'groups-woocommerce', array(), null, 'no' ) ) {
				$options = get_option( 'groups-woocommerce' );
			}
		}

		if ( isset( $_POST['submit'] ) ) {
			if ( wp_verify_nonce( $_POST[self::NONCE], 'set' ) ) {
				$order_status = isset( $_POST[self::MEMBERSHIP_ORDER_STATUS] ) ? $_POST[self::MEMBERSHIP_ORDER_STATUS] : 'completed';
				switch ( $order_status ) {
					case 'completed' :
					case 'processing' :
						break;
					default :
						$order_status = GROUPS_WS_DEFAULT_MEMBERSHIP_ORDER_STATUS;
				}
				$options[self::MEMBERSHIP_ORDER_STATUS] = $order_status;
				$options[self::SHOW_DURATION] = isset( $_POST[self::SHOW_DURATION] );

				update_option( 'groups-woocommerce', $options );
			}
		}

		$order_status  = isset( $options[self::MEMBERSHIP_ORDER_STATUS] ) ? $options[self::MEMBERSHIP_ORDER_STATUS] : GROUPS_WS_DEFAULT_MEMBERSHIP_ORDER_STATUS;
		$show_duration = isset( $options[self::SHOW_DURATION] ) ? $options[self::SHOW_DURATION] : GROUPS_WS_DEFAULT_SHOW_DURATION;

		echo '<div class="groups-woocommerce">';

		echo '<h2>' . __( 'Groups', GROUPS_PLUGIN_DOMAIN ) . '</h2>';

		echo
			'<form action="" name="options" method="post">' .
			'<div>' .

			'<h3>' . __( 'Group membership', GROUPS_WS_PLUGIN_DOMAIN ) . '</h3>' .
			'<p>' .
			'<label>' . __( 'Add users to or remove from groups as early as the order is ...', GROUPS_WS_PLUGIN_DOMAIN ) .
			'<select name="' . self::MEMBERSHIP_ORDER_STATUS . '">' .
			'<option value="completed" ' . ( $order_status == 'completed' ? ' selected="selected" ' : '' ) . '>' . __( 'Completed', GROUPS_WS_PLUGIN_DOMAIN ) . '</option>' .
			'<option value="processing" ' . ( $order_status == 'processing' ? ' selected="selected" ' : '' ) . '>' . __( 'Processing', GROUPS_WS_PLUGIN_DOMAIN ) . '</option>' .
			'</select>' .
			'</label>' .
			'</p>' .
			'<p class="description">' . __( 'Note that users will always be added to or removed from groups when an order is completed.', GROUPS_WS_PLUGIN_DOMAIN ) . '</p>' .
			'<p>' .
			
			'<h3>' . __( 'Durations', GROUPS_WS_PLUGIN_DOMAIN ) . '</h3>' .
			'<p>' .
			'<label>' .
			sprintf( '<input name="show_duration" type="checkbox" %s />', $show_duration ? ' checked="checked" ' : '' ) .
			' ' .
			__( 'Show durations', GROUPS_WS_PLUGIN_DOMAIN ) .
			'</label>' .
			'</p>' .
			'<p class="description">' . __( 'Modifies the way product prices are displayed to show durations.', GROUPS_WS_PLUGIN_DOMAIN ) . '</p>' .
			'<p>' .

			'<p>' .
			wp_nonce_field( 'set', self::NONCE, true, false ) .
			'<input class="button" type="submit" name="submit" value="' . __( 'Save', GROUPS_WS_PLUGIN_DOMAIN ) . '"/>' .
			'</p>' .
			'</div>' .
			'</form>';

		echo '</div>'; // .groups-woocommerce

		if ( GROUPS_WS_LOG ) {
			$crons = _get_cron_array();
			echo '<h2>Cron</h2>';
			echo '<pre>';
			echo var_export( $crons, true );
			echo '</pre>';
		}
	}
}
Groups_WS_Admin::init();
