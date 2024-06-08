<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_send
 * This shortcode allows the current user to send a pre-set amount of points
 * to a pre-set user. A simpler version of the bonips_transfer shortcode.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_send/ 
 * @since 1.1
 * @version 1.3.1
 */
if ( ! function_exists( 'bonips_render_shortcode_send' ) ) :
	function bonips_render_shortcode_send( $atts, $content = '' ) {

		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => 0,
			'to'     => '',
			'log'    => '',
			'ref'    => 'gift',
			'type'   => BONIPS_DEFAULT_TYPE_KEY,
			'class'  => 'button button-primary btn btn-primary',
			'reload' => 0
		), $atts, BONIPS_SLUG . '_send' ) );

		if ( ! bonips_point_type_exists( $type ) ) return 'Point type not found.';

		global $post;

		// Send points to the post author (assuming this shortcode is used inside the loop)
		$to            = bonips_get_user_id( $to );

		// We will not render for ourselves.
		$user_id       = get_current_user_id();
		$recipient     = absint( $to );
		if ( $recipient === $user_id || $recipient === 0 ) return;

		global $bonips_sending_points;

		$bonips_sending_points = false;

		$bonips        = bonips( $type );

		// Make sure current user or recipient is not excluded!
		if ( $bonips->exclude_user( $recipient ) || $bonips->exclude_user( $user_id ) ) return;

		$account_limit = $bonips->number( apply_filters( 'bonips_transfer_acc_limit', 0 ) );
		$balance       = $bonips->get_users_balance( $user_id, $type );
		$amount        = $bonips->number( $amount );

		// Insufficient Funds
		if ( $balance-$amount < $account_limit ) return;

		// We are ready!
		$bonips_sending_points = true;

		if ( $class != '' )
			$class = ' ' . sanitize_text_field( $class );

		$reload = absint( $reload );

		$render = '<button type="button" class="bonips-send-points-button btn btn-primary' . $class . '" data-reload="' . $reload . '" data-to="' . $recipient . '" data-ref="' . esc_attr( $ref ) . '" data-log="' . esc_attr( $log ) . '" data-amount="' . $amount . '" data-type="' . esc_attr( $type ) . '">' . $bonips->template_tags_general( $content ) . '</button>';

		return apply_filters( 'bonips_send', $render, $atts, $content );

	}
endif;
add_shortcode( BONIPS_SLUG . '_send', 'bonips_render_shortcode_send' );

/**
 * boniPS Send Points Ajax
 * @since 0.1
 * @version 1.4.1
 */
if ( ! function_exists( 'bonips_shortcode_send_points_ajax' ) ) :
	function bonips_shortcode_send_points_ajax() {

		// Security
		check_ajax_referer( 'bonips-send-points', 'token' );

		$user_id       = get_current_user_id();

		if ( bonips_force_singular_session( $user_id, 'bonips-last-send' ) )
			wp_send_json( 'error' );

		$point_type    = BONIPS_DEFAULT_TYPE_KEY;
		if ( isset( $_POST['type'] ) )
			$point_type = sanitize_text_field( $_POST['type'] );

		// Make sure the type exists
		if ( ! bonips_point_type_exists( $point_type ) ) die();

		// Prep
		$recipient     = (int) sanitize_text_field( $_POST['recipient'] );
		$reference     = sanitize_text_field( $_POST['reference'] );
		$log_entry     = strip_tags( trim( $_POST['log'] ), '<a>' );

		// No sending to ourselves
		if ( $user_id == $recipient )
			wp_send_json( 'error' );

		$bonips        = bonips( $point_type );

		// Prep amount
		$amount        = sanitize_text_field( $_POST['amount'] );
		$amount        = $bonips->number( abs( $amount ) );

		// Check solvency
		$account_limit = $bonips->number( apply_filters( 'bonips_transfer_acc_limit', $bonips->zero() ) );
		$balance       = $bonips->get_users_balance( $user_id, $point_type );
		$new_balance   = $balance-$amount;

		$data          = array( 'ref_type' => 'user' );

		// Insufficient Funds
		if ( $new_balance < $account_limit )
			die();

		// After this transfer our account will reach zero
		elseif ( $new_balance == $account_limit )
			$reply = 'zero';

		// Check if this is the last time we can do these kinds of amounts
		elseif ( $new_balance - $amount < $account_limit )
			$reply = 'minus';

		// Else everything is fine
		else
			$reply = 'done';

		// First deduct points
		if ( $bonips->add_creds(
			$reference,
			$user_id,
			0 - $amount,
			$log_entry,
			$recipient,
			$data,
			$point_type
		) ) {

			// Then add to recipient
			$bonips->add_creds(
				$reference,
				$recipient,
				$amount,
				$log_entry,
				$user_id,
				$data,
				$point_type
			);

		}
		else {
			$reply = 'error';
		}

		// Share the good news
		wp_send_json( $reply );

	}
endif;
add_action( 'wp_ajax_bonips-send-points', 'bonips_shortcode_send_points_ajax' );
