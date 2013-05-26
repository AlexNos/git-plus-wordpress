<?php
/*
Plugin Name: WooTransmitter
Plugin URI: http://woothemes.com/
Description: A WooThemes notification centre, in your WordPress Toolbar.
Author: WooThemes
Author URI: http://woothemes.com/
Version: 1.0.1
Stable tag: 1.0.1
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

 if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) && basename( __FILE__ ) == basename( $_SERVER['SCRIPT_FILENAME'] ) ) {
    die ( 'Please do not load this screen directly. Thanks!' );
 }

 if ( ! class_exists( 'WooThemes_Transmitter' ) ) {
 	require_once( 'classes/class-wootransmitter.php' );
 	global $wootransmitter;
 	$wootransmitter = new WooThemes_Transmitter( __FILE__ );
 }
?>