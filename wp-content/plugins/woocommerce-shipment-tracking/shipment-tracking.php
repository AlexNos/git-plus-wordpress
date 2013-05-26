<?php
/*
	Plugin Name: WooCommerce Shipment Tracking
	Plugin URI: http://woothemes.com/woocommerce
	Description: Add tracking numbers to orders allowing customers to track their orders via a link. Supports many shipping providers, as well as custom ones if neccessary via a regular link.
	Version: 1.0.5
	Author: Mike Jolley
	Author URI: http://mikejolley.com

	Copyright: © 2009-2012 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '1968e199038a8a001c9f9966fd06bf88', '18693' );

if ( is_woocommerce_active() ) {

	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'wc_shipment_tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * WC_Shipment_Tracking class
	 */
	if ( ! class_exists( 'WC_Shipment_Tracking' ) ) {

		class WC_Shipment_Tracking {

			var $providers;

			/**
			 * Constructor
			 */
			function __construct() {

				$this->providers = array(
					'Australia' => array(
						'Australia Post'
							=> 'http://auspost.com.au/track/track.html?id=%1$s',
					),
					'Brazil' => array(
						'Correios'
							=> 'http://websro.correios.com.br/sro_bin/txect01$.QueryList?P_LINGUA=001&P_TIPO=001&P_COD_UNI=%1$s'
					),
					'Canada' => array(
						'Canada Post'
							=> 'http://www.canadapost.ca/cpotools/apps/track/personal/findByTrackNumber?trackingNumber=%1$s',
					),
					'India' => array(
						'DTDC'
							=> 'http://www.dtdc.in/dtdcTrack/Tracking/consignInfo.asp?strCnno=%1$s',
					),
					'Netherlands' => array(
						'PostNL'
							=> 'https://mijnpakket.postnl.nl/Claim?Barcode=%1$s&Postalcode=%2$s&Foreign=False&ShowAnonymousLayover=False&CustomerServiceClaim=False',
					),
					'South African' => array(
						'SAPO'
							=> 'http://tracking.postoffice.co.za/parcel.aspx?id=%1$s',
					),
					'Sweden' => array(
						'Posten AB'
							=> 'http://server.logistik.posten.se/servlet/PacTrack?xslURL=/xsl/pactrack/standard.xsl&/css/kolli.css&lang2=SE&kolliid=%1$s',
					),
					'United Kingdom' => array(
						'City Link'
							=> 'http://www.city-link.co.uk/dynamic/track.php?parcel_ref_num=%1$s',
						'DHL'
							=> 'http://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=%1$s',
						'DPD'
							=> 'http://track.dpdnl.nl/?parcelnumber=%1$s',
						'ParcelForce'
							=> 'http://www.parcelforce.com/portal/pw/track?trackNumber=%1$s',
						'Royal Mail'
							=> 'http://track2.royalmail.com/portal/rm/track?trackNumber=%1$s',
						'TNT Express (consignment)'
							=> 'http://www.tnt.com/webtracker/tracking.do?requestType=GEN&searchType=CON&respLang=en&
respCountry=GENERIC&sourceID=1&sourceCountry=ww&cons=%1$s&navigation=1&g
enericSiteIdent=',
						'TNT Express (reference)'
							=> 'http://www.tnt.com/webtracker/tracking.do?requestType=GEN&searchType=REF&respLang=en&r
espCountry=GENERIC&sourceID=1&sourceCountry=ww&cons=%1$s&navigation=1&gen
ericSiteIdent=',
					),
					'United States' => array(
						'Fedex'
							=> 'http://www.fedex.com/Tracking?action=track&tracknumbers=%1$s',
						'OnTrac'
							=> 'http://www.ontrac.com/trackingdetail.asp?tracking=%1$s',
						'UPS'
							=> 'http://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=%1$s',
						'USPS'
							=> 'https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1=%1$s',
					),
				);

				add_action( 'admin_print_styles', array( &$this, 'admin_styles' ) );
				add_action( 'add_meta_boxes', array( &$this, 'add_meta_box' ) );
				add_action( 'woocommerce_process_shop_order_meta', array( &$this, 'save_meta_box' ), 0, 2 );

				// View Order Page
				add_action( 'woocommerce_view_order', array( &$this, 'display_tracking_info' ) );
				add_action( 'woocommerce_email_before_order_table', array( &$this, 'email_display' ) );
			}


			function admin_styles() {
				wp_enqueue_style( 'shipment_tracking_styles', plugins_url( basename( dirname( __FILE__ ) ) ) . '/assets/css/admin.css' );
			}

			/**
			 * Add the meta box for shipment info on the order page
			 *
			 * @access public
			 */
			function add_meta_box() {
				add_meta_box( 'woocommerce-shipment-tracking', __('Shipment Tracking', 'wc_shipment_tracking'), array( &$this, 'meta_box' ), 'shop_order', 'side', 'high');
			}

			/**
			 * Show the meta box for shipment info on the order page
			 *
			 * @access public
			 */
			function meta_box() {
				global $woocommerce, $post;

				// Providers
				echo '<p class="form-field tracking_provider_field"><label for="tracking_provider">' . __('Provider:', 'wc_shipment_tracking') . '</label><br/><select id="tracking_provider" name="tracking_provider" class="chosen_select" style="width:100%;">';

				echo '<option value="">' . __('Custom (enter link)', 'wc_shipment_tracking') . '</option>';

				$selected_provider = get_post_meta( $post->ID, '_tracking_provider', true );

				foreach ( $this->providers as $provider_group => $providers ) {

					echo '<optgroup label="' . $provider_group . '">';

					foreach ( $providers as $provider => $url ) {

						echo '<option value="' . sanitize_title( $provider ) . '" ' . selected( sanitize_title( $provider ), $selected_provider, true ) . '>' . $provider . '</option>';

					}

					echo '</optgroup>';

				}

				echo '</select> ';

				woocommerce_wp_text_input( array(
					'id' 			=> 'tracking_number',
					'label' 		=> __('Tracking number/link:', 'wc_shipment_tracking'),
					'placeholder' 	=> '',
					'description' 	=> '',
					'value'			=> get_post_meta( $post->ID, '_tracking_number', true )
				) );

				woocommerce_wp_text_input( array(
					'id' 			=> 'date_shipped',
					'label' 		=> __('Date shipped:', 'wc_shipment_tracking'),
					'placeholder' 	=> '',
					'description' 	=> '',
					'class'			=> 'date-picker-field',
					'value'			=> ( $date = get_post_meta( $post->ID, '_date_shipped', true ) ) ? date( 'Y-m-d', $date ) : ''
				) );

				// Live preview
				echo '<p class="preview_tracking_link">' . __('Preview:', 'wc_shipment_tracking') . ' <a href="" target="_blank">' . __('Click here to track your shipment', 'wc_shipment_tracking') . '</a></p>';

				$provider_array = array();

				foreach ( $this->providers as $providers ) {
					foreach ( $providers as $provider => $format ) {
						$provider_array[sanitize_title( $provider )] = urlencode( $format );
					}
				}

				$woocommerce->add_inline_js("
					jQuery('input#tracking_number, #tracking_provider').change(function(){

						var tracking = jQuery('input#tracking_number').val();
						var provider = jQuery('#tracking_provider').val();
						var providers = jQuery.parseJSON( '" . json_encode( $provider_array ) . "' );

						var postcode = jQuery('#_shipping_postcode').val();

						if ( ! postcode )
							postcode = jQuery('#_billing_postcode').val();

						postcode = encodeURIComponent( postcode );

						var link = '';

						if ( providers[provider] ) {
							link = providers[provider];
							link = link.replace( '%251%24s', tracking );
							link = link.replace( '%252%24s', postcode );
							link = decodeURIComponent( link );
						}

						if ( link ) {
							jQuery('p.preview_tracking_link a').attr('href', link);
							jQuery('p.preview_tracking_link').show();
						} else {
							jQuery('p.preview_tracking_link').hide();
						}

					}).change();
				");
			}

			/**
			 * Order Downloads Save
			 *
			 * Function for processing and storing all order downloads.
			 */
			function save_meta_box( $post_id, $post ) {
				if ( isset( $_POST['tracking_number'] ) ) {

					// Download data
					$tracking_provider		= esc_attr( $_POST['tracking_provider'] );
					$tracking_number 		= esc_attr( $_POST['tracking_number'] );
					$date_shipped			= esc_attr( strtotime( $_POST['date_shipped'] ) );

					// Update order data
					update_post_meta( $post_id, '_tracking_provider', $tracking_provider );
					update_post_meta( $post_id, '_tracking_number', $tracking_number );
					update_post_meta( $post_id, '_date_shipped', $date_shipped );
				}
			}

			/**
			 * Display Shipment info in the frontend (order view/tracking page).
			 *
			 * @access public
			 */
			function display_tracking_info( $order_id ) {

				$tracking_provider 	= get_post_meta( $order_id, '_tracking_provider', true );
				$tracking_number 	= get_post_meta( $order_id, '_tracking_number', true );
				$date_shipped		= get_post_meta( $order_id, '_date_shipped', true );
				$postcode			= get_post_meta( $order_id, '_shipping_postcode', true );
				if ( ! $postcode )
					$postcode		= get_post_meta( $order_id, '_billing_postcode', true );

				if ( ! $tracking_number ) return;

				if ( $date_shipped ) {
					$date_shipped = ' ' . date_i18n( __('\o\n l jS \of F Y', 'wc_shipment_tracking'), $date_shipped );
				}

				$tracking_link = '';

				if ( $tracking_provider ) {

					$link_format = '';

					foreach ( $this->providers as $providers ) {
						foreach ( $providers as $provider => $format ) {
							if ( sanitize_title( $provider ) == $tracking_provider ) {
								$link_format = $format;
								$tracking_provider = $provider;
								break;
							}
						}
						if ( $link_format ) break;
					}

					if ( $link_format )
						$tracking_link = sprintf( sprintf( __('<a href="%s">Click here to track your shipment.</a>', 'wc_shipment_tracking'), $link_format ), $tracking_number, urlencode( $postcode ) );

					$tracking_provider = ' ' . __('via', 'wc_shipment_tracking') . ' <strong>' . $tracking_provider . '</strong>';

					echo wpautop( sprintf( __('Your order was shipped%s%s. Tracking number %s. %s', 'wc_shipment_tracking'), $date_shipped, $tracking_provider, $tracking_number, $tracking_link ) );

				} else {

					$tracking_link = sprintf( __('<a href="%s">%s.</a>', 'wc_shipment_tracking'), $tracking_number, $tracking_number );

					echo wpautop( sprintf( __('Your order was shipped%s. Tracking via %s', 'wc_shipment_tracking'), $date_shipped, $tracking_link ) );

				}

			}

			/**
			 * Display shipment info in customer emails.
			 *
			 * @access public
			 * @return void
			 */
			function email_display( $order ) {
				$this->display_tracking_info( $order->id );
			}

		}

	}

	/**
	 * Register this class globally
	 */
	$GLOBALS['WC_Shipment_Tracking'] = new WC_Shipment_Tracking();

}