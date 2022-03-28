<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Total Since
 * Shows the total number of points a user has gained / lost in a given timeframe.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonips_render_shortcode_total_since' ) ) :
	function bonips_render_shortcode_total_since( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'from'      => 'today',
			'until'     => 'now',
			'type'      => BONIPS_DEFAULT_TYPE_KEY,
			'ref'       => '',
			'user_id'   => 'current',
			'formatted' => 1
		), $atts, BONIPS_SLUG . '_total_since' ) );

		if ( ! bonips_point_type_exists( $type ) )
			$type = BONIPS_DEFAULT_TYPE_KEY;

		if ( $ref == '' ) $ref = NULL;

		$user_id = bonips_get_user_id( $user_id );
		$bonips  = bonips( $type );
		$total   = bonips_get_total_by_time( $from, $until, $ref, $user_id, $type );

		if ( substr( $total, 0, 7 ) != 'Invalid' && $formatted == 1 )
			$total = $bonips->format_creds( $total );

		return $total;

	}
endif;
add_shortcode( BONIPS_SLUG . '_total_since', 'bonips_render_shortcode_total_since' );
