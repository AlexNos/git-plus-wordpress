<?php
/**
 * class-groups-ws-user.php
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
 * @since groups-woocommerce 1.3.0
 */

/**
 * User info - currently only used for debugging when GROUPS_WS_LOG.
 */
class Groups_WS_User {

	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'show_user_profile' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'edit_user_profile' ) );
	}

	/**
	 * Own profile.
	 * @param WP_User $user
	 */
	public static function show_user_profile( $user ) {
		self::show_buckets( $user );
	}

	/**
	 * A user's profile.
	 * @param WP_User $user
	 */
	public static function edit_user_profile( $user ) {
		self::show_buckets( $user );
	}
	
	private static function show_buckets( $user ) {
		$user_buckets = get_user_meta( $user->ID, '_groups_buckets', true );
		echo '<h2>Groups</h2>';
		echo '<ul>';
		foreach( $user_buckets as $group_id => $timestamps ) {
			echo '<li>';
			$group = new Groups_Group( $group_id );
			echo '<h3>' . wp_filter_nohtml_kses( $group->name ) . '</h3>';
			echo '<ul>';
			foreach( $timestamps as $timestamp ) {
				echo '<li>';
				if ( intval( $timestamp ) === Groups_WS_Terminator::ETERNITY ) {
					echo __( 'Unlimited', GROUPS_WS_PLUGIN_DOMAIN );
				} else {
					echo date( 'Y-m-d H:i:s', $timestamp );
				}
				
				echo '</li>';
			}
			echo '<ul>';
			echo '</li>';
		}
		echo '</ul>';
	}
}
Groups_WS_User::init();
