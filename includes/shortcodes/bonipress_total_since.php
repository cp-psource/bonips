<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Total Since
 * Shows the total number of points a user has gained / lost in a given timeframe.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_total_since' ) ) :
	function bonipress_render_shortcode_total_since( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'from'      => 'today',
			'until'     => 'now',
			'type'      => BONIPRESS_DEFAULT_TYPE_KEY,
			'ref'       => '',
			'user_id'   => 'current',
			'formatted' => 1
		), $atts, BONIPRESS_SLUG . '_total_since' ) );

		if ( ! bonipress_point_type_exists( $type ) )
			$type = BONIPRESS_DEFAULT_TYPE_KEY;

		if ( $ref == '' ) $ref = NULL;

		$user_id = bonipress_get_user_id( $user_id );
		$bonipress  = bonipress( $type );
		$total   = bonipress_get_total_by_time( $from, $until, $ref, $user_id, $type );

		if ( substr( $total, 0, 7 ) != 'Invalid' && $formatted == 1 )
			$total = $bonipress->format_creds( $total );

		return $total;

	}
endif;
add_shortcode( BONIPRESS_SLUG . '_total_since', 'bonipress_render_shortcode_total_since' );
