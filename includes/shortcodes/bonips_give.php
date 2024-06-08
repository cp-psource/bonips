<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_give
 * This shortcode allows you to award or deduct points from a given user or the current user
 * when this shortcode is executed. You can insert this in page/post content
 * or in a template file. Note that users are awarded/deducted points each time
 * this shortcode exectutes!
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_give/
 * @since 1.1
 * @version 1.3
 */
if ( ! function_exists( 'bonips_render_shortcode_give' ) ) :
	function bonips_render_shortcode_give( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'amount'  => '',
			'user_id' => 'current',
			'log'     => '',
			'ref'     => 'gift',
			'limit'   => 0,
			'type'    => BONIPS_DEFAULT_TYPE_KEY
		), $atts, BONIPS_SLUG . '_give' ) );

		if ( ! is_user_logged_in() && $user_id == 'current' )
			return $content;

		if ( ! bonips_point_type_exists( $type ) || apply_filters( 'bonips_give_run', true, $atts ) === false ) return $content;

		$bonips  = bonips( $type );
		$user_id = bonips_get_user_id( $user_id );
		$ref     = sanitize_key( $ref );
		$limit   = absint( $limit );

		// Check for exclusion
		if ( $bonips->exclude_user( $user_id ) ) return $content;

		// Limit
		if ( $limit > 0 && bonips_count_ref_instances( $ref, $user_id, $type ) >= $limit ) return $content;

		$bonips->add_creds(
			$ref,
			$user_id,
			$amount,
			$log,
			0,
			'',
			$type
		);

	}
endif;
add_shortcode( BONIPS_SLUG . '_give', 'bonips_render_shortcode_give' );
