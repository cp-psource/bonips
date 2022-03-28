<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Affiliate ID
 * @since 1.5.3
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_render_affiliate_id' ) ) :
	function bonipress_render_affiliate_id( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'type' => BONIPS_DEFAULT_TYPE_KEY
		), $atts, BONIPS_SLUG . '_affiliate_id' ) );

		return apply_filters( 'bonipress_affiliate_id_' . $type, '', $atts, $content );

	}
endif;
add_shortcode( BONIPS_SLUG . '_affiliate_id', 'bonipress_render_affiliate_id' );
