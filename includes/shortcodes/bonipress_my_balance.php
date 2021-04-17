<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: my_balance
 * Returns the current users balance.
 * @see http://codex.bonipress.me/shortcodes/bonipress_my_balance/
 * @since 1.0.9
 * @version 1.3
 */
if ( ! function_exists( 'bonipress_render_shortcode_my_balance' ) ) :
	function bonipress_render_shortcode_my_balance( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id'    => 'current',
			'title'      => '',
			'title_el'   => 'h1',
			'balance_el' => 'div',
			'wrapper'    => 1,
			'formatted'  => 1,
			'type'       => BONIPRESS_DEFAULT_TYPE_KEY
		), $atts, BONIPRESS_SLUG . '_my_balance' ) );

		$output = '';

		// Not logged in
		if ( ! is_user_logged_in() && $user_id == 'current' )
			return $content;

		// Get user ID
		$user_id = bonipress_get_user_id( $user_id );

		// Make sure we have a valid point type
		if ( ! bonipress_point_type_exists( $type ) )
			$type = BONIPRESS_DEFAULT_TYPE_KEY;

		// Get the users boniPRESS account object
		$account = bonipress_get_account( $user_id );
		if ( $account === false ) return;

		// Check for exclusion
		if ( empty( $account->balance ) || ! array_key_exists( $type, $account->balance ) || $account->balance[ $type ] === false ) return;

		$balance = $account->balance[ $type ];

		if ( $wrapper )
			$output .= '<div class="bonipress-my-balance-wrapper">';

		// Title
		if ( ! empty( $title ) ) {
			if ( ! empty( $title_el ) )
				$output .= '<' . $title_el . '>';

			$output .= $title;

			if ( ! empty( $title_el ) )
				$output .= '</' . $title_el . '>';
		}

		// Balance
		if ( ! empty( $balance_el ) )
			$output .= '<' . $balance_el . '>';

		if ( $formatted )
			$output .= $balance->point_type->format( $balance->current );
		else
			$output .= $balance->point_type->number( $balance->current );

		if ( ! empty( $balance_el ) )
			$output .= '</' . $balance_el . '>';

		if ( $wrapper )
			$output .= '</div>';

		return $output;

	}
endif;
add_shortcode( BONIPRESS_SLUG . '_my_balance', 'bonipress_render_shortcode_my_balance' );
