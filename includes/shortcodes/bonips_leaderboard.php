<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_leaderboard
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_leaderboard/
 * @since 0.1
 * @version 1.6
 */
if ( ! function_exists( 'bonips_render_shortcode_leaderboard' ) ) :
	function bonips_render_shortcode_leaderboard( $atts, $content = '' ) {

		$args = shortcode_atts( array(
			'number'       => 25,
			'order'        => 'DESC',
			'offset'       => 0,
			'type'         => BONIPS_DEFAULT_TYPE_KEY,
			'based_on'     => 'balance',
			'total'        => 0,
			'wrap'         => 'li',
			'template'     => '#%position% %user_profile_link% %cred_f%',
			'nothing'      => 'Bestenliste ist leer',
			'current'      => 0,
			'exclude_zero' => 1,
			'timeframe'    => '',
			'to'           => ''
		), $atts, BONIPS_SLUG . '_leaderboard' );

		// Construct the leaderboard class
		$leaderboard = bonips_get_leaderboard( $args );

		// Just constructing the class will not yeld any results
		// We need to run the query to populate the leaderboard
		$leaderboard->get_leaderboard_results( (bool) $args['current'] );

		// Render and return
		return do_shortcode( $leaderboard->render( $args, $content ) );

	}
endif;
add_shortcode( BONIPS_SLUG . '_leaderboard', 'bonips_render_shortcode_leaderboard' );
