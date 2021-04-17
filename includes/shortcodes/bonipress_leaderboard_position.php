<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonipress_leaderboard_position
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_leaderboard/_position/
 * Replaces the bonipress_my_ranking shortcode.
 * @since 1.7
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_render_shortcode_leaderbaord_position' ) ) :
	function bonipress_render_shortcode_leaderbaord_position( $atts, $content = '' ) {

		$args = shortcode_atts( array(
			'user_id'   => 'current',
			'ctype'     => BONIPRESS_DEFAULT_TYPE_KEY,
			'type'      => '',
			'based_on'  => 'balance',
			'total'     => 0,
			'missing'   => '-',
			'suffix'    => 0,
			'timeframe' => ''
		), $atts, BONIPRESS_SLUG . '_leaderboard_position' );

		// Get the user ID we need a position for
		$user_id     = bonipress_get_user_id( $args['user_id'] );

		// Backwards comp.
		if ( $args['type'] == '' )
			$args['type'] = $args['ctype'];

		// Construct the leaderboard class
		$leaderboard = bonipress_get_leaderboard( $args );

		// Query the users position
		$position    = $leaderboard->get_users_current_position( $user_id, $missing );

		if ( $position != $missing && $suffix == 1 )
			$position = bonipress_ordinal_suffix( $position, true );

		return $position;

	}
endif;
add_shortcode( BONIPRESS_SLUG . '_leaderboard_position', 'bonipress_render_shortcode_leaderbaord_position' );
