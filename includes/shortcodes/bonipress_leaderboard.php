<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonipress_leaderboard
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_leaderboard/
 * @since 0.1
 * @version 1.6
 */
if ( ! function_exists( 'bonipress_render_shortcode_leaderboard' ) ) :
	function bonipress_render_shortcode_leaderboard( $atts, $content = '' ) {

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
		$leaderboard = bonipress_get_leaderboard( $args );

		// Just constructing the class will not yeld any results
		// We need to run the query to populate the leaderboard
		$leaderboard->get_leaderboard_results( (bool) $args['current'] );

		// Render and return
		return do_shortcode( $leaderboard->render( $args, $content ) );

	}
endif;
add_shortcode( BONIPS_SLUG . '_leaderboard', 'bonipress_render_shortcode_leaderboard' );
