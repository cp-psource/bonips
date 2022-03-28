<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_leaderboard_position
 * @see https://n3rds.work/docs/bonips-shortcodes-bonips_leaderboard/_position/
 * Replaces the bonips_my_ranking shortcode.
 * @since 1.7
 * @version 1.2
 */
if ( ! function_exists( 'bonips_render_shortcode_leaderbaord_position' ) ) :
	function bonips_render_shortcode_leaderbaord_position( $atts, $content = '' ) {

		$args = shortcode_atts( array(
			'user_id'   => 'current',
			'ctype'     => BONIPS_DEFAULT_TYPE_KEY,
			'type'      => '',
			'based_on'  => 'balance',
			'total'     => 0,
			'missing'   => '-',
			'suffix'    => 0,
			'timeframe' => ''
		), $atts, BONIPS_SLUG . '_leaderboard_position' );

		// Get the user ID we need a position for
		$user_id     = bonips_get_user_id( $args['user_id'] );

		// Backwards comp.
		if ( $args['type'] == '' )
			$args['type'] = $args['ctype'];

		// Construct the leaderboard class
		$leaderboard = bonips_get_leaderboard( $args );

		// Query the users position
		$position    = $leaderboard->get_users_current_position( $user_id, $args['missing'] );

		if ( $position != $args['missing'] && $args['suffix'] == 1 )
			$position = bonips_ordinal_suffix( $position, true );

		return $position;

	}
endif;
add_shortcode( BONIPS_SLUG . '_leaderboard_position', 'bonips_render_shortcode_leaderbaord_position' );
