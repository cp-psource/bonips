<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Affiliate Link
 * @since 1.5.3
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_render_affiliate_link' ) ) :
	function bonipress_render_affiliate_link( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'type' => BONIPRESS_DEFAULT_TYPE_KEY
		), $atts, BONIPRESS_SLUG . '_affiliate_link' ) );

		return apply_filters( 'bonipress_affiliate_link_' . $type, '', $atts, $content );

	}
endif;
add_shortcode( BONIPRESS_SLUG . '_affiliate_link', 'bonipress_render_affiliate_link' );
