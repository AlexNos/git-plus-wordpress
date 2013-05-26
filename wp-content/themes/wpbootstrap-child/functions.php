<?php 

/*

add_filter('get_frm_stylesheet',  'my_custom_stylesheet', 20, 2);
function my_custom_stylesheet($previous_css, $location='header'){
        global $frmpro_settings, $frm_datepicker_loaded, $frm_css_loaded;

        $css_file = array();
        
        if(!$frm_css_loaded)
            $css_file[] = FRM_SCRIPT_URL . '&amp;controller=settings';

        if($frm_datepicker_loaded)
            $css_file[] = FrmProAppHelper::jquery_css_url($frmpro_settings->theme_css);

        return $css_file;
}
*/

add_shortcode( 'alert-info', 'alert_info' );
function alert_info( $atts, $content )
{
	return do_shortcode( "[alert type='info'][icon icon='info-sign' size='large']&nbsp;&nbsp;" .  $content . "[/alert]" );
}

add_shortcode( 'alert-success', 'alert_success' );
function alert_success( $atts, $content )
{
	return do_shortcode( "[alert type='success'][icon icon='ok-sign' size='large']&nbsp;&nbsp;" .  $content . "[/alert]" );
}

add_shortcode( 'alert-warning', 'alert_warning' );
function alert_warning( $atts, $content )
{
	return do_shortcode( "[alert type='warning'][icon icon='warning-sign' size='large']&nbsp;&nbsp;" .  $content . "[/alert]" );
}

add_shortcode( 'alert-error', 'alert_error' );
function alert_error( $atts, $content )
{
	return do_shortcode( "[alert type='error'][icon icon='remove-sign' size='large']&nbsp;&nbsp;" .  $content . "[/alert]" );
}

add_shortcode( 'page-header', 'hf_page_header' );
function hf_page_header( $atts, $content )
{
	return "<h2 class='page-header'>" . do_shortcode($content) . "</h2>";
}

add_shortcode( 'clear', 'hf_clearfix' );
function hf_clearfix( $atts, $content )
{
	return "<div class='clearfix'></div>";
}

add_shortcode( 'hr', 'hf_hr' );
function hf_hr( $atts, $content )
{
	return "<hr>";
}

function horsefly_register_menus() 
{
	register_nav_menus( array(
								'main-menu' => __( 'Main Menu' )
							 )
					   );
}
add_action( 'init', 'horsefly_register_menus' );


?>