<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Affiliate Link
 * @since 1.5.3
 * @version 1.2
 */
if ( ! function_exists( 'bonips_render_affiliate_link' ) ) :
	function bonips_render_affiliate_link( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'type' => BONIPS_DEFAULT_TYPE_KEY
		), $atts, BONIPS_SLUG . '_affiliate_link' ) );

		return apply_filters( 'bonips_affiliate_link_' . $type, '', $atts, $content );

	}
endif;
add_shortcode( BONIPS_SLUG . '_affiliate_link', 'bonips_render_affiliate_link' );
