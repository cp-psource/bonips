<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonipress_send
 * This shortcode allows the current user to send a pre-set amount of points
 * to a pre-set user. A simpler version of the bonipress_transfer shortcode.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_send/ 
 * @since 1.1
 * @version 1.3.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_send' ) ) :
	function bonipress_render_shortcode_send( $atts, $content = '' ) {

		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => 0,
			'to'     => '',
			'log'    => '',
			'ref'    => 'gift',
			'type'   => BONIPRESS_DEFAULT_TYPE_KEY,
			'class'  => 'button button-primary btn btn-primary',
			'reload' => 0
		), $atts, BONIPRESS_SLUG . '_send' ) );

		if ( ! bonipress_point_type_exists( $type ) ) return 'Point type not found.';

		global $post;

		// Send points to the post author (assuming this shortcode is used inside the loop)
		$to            = bonipress_get_user_id( $to );

		// We will not render for ourselves.
		$user_id       = get_current_user_id();
		$recipient     = absint( $to );
		if ( $recipient === $user_id || $recipient === 0 ) return;

		global $bonipress_sending_points;

		$bonipress_sending_points = false;

		$bonipress        = bonipress( $type );

		// Make sure current user or recipient is not excluded!
		if ( $bonipress->exclude_user( $recipient ) || $bonipress->exclude_user( $user_id ) ) return;

		$account_limit = $bonipress->number( apply_filters( 'bonipress_transfer_acc_limit', 0 ) );
		$balance       = $bonipress->get_users_balance( $user_id, $type );
		$amount        = $bonipress->number( $amount );

		// Insufficient Funds
		if ( $balance-$amount < $account_limit ) return;

		// We are ready!
		$bonipress_sending_points = true;

		if ( $class != '' )
			$class = ' ' . sanitize_text_field( $class );

		$reload = absint( $reload );

		$render = '<button type="button" class="bonipress-send-points-button btn btn-primary' . $class . '" data-reload="' . $reload . '" data-to="' . $recipient . '" data-ref="' . esc_attr( $ref ) . '" data-log="' . esc_attr( $log ) . '" data-amount="' . $amount . '" data-type="' . esc_attr( $type ) . '">' . $bonipress->template_tags_general( $content ) . '</button>';

		return apply_filters( 'bonipress_send', $render, $atts, $content );

	}
endif;
add_shortcode( BONIPRESS_SLUG . '_send', 'bonipress_render_shortcode_send' );

/**
 * boniPRESS Send Points Ajax
 * @since 0.1
 * @version 1.4.1
 */
if ( ! function_exists( 'bonipress_shortcode_send_points_ajax' ) ) :
	function bonipress_shortcode_send_points_ajax() {

		// Security
		check_ajax_referer( 'bonipress-send-points', 'token' );

		$user_id       = get_current_user_id();

		if ( bonipress_force_singular_session( $user_id, 'bonipress-last-send' ) )
			wp_send_json( 'error' );

		$point_type    = BONIPRESS_DEFAULT_TYPE_KEY;
		if ( isset( $_POST['type'] ) )
			$point_type = sanitize_text_field( $_POST['type'] );

		// Make sure the type exists
		if ( ! bonipress_point_type_exists( $point_type ) ) die();

		// Prep
		$recipient     = (int) sanitize_text_field( $_POST['recipient'] );
		$reference     = sanitize_text_field( $_POST['reference'] );
		$log_entry     = strip_tags( trim( $_POST['log'] ), '<a>' );

		// No sending to ourselves
		if ( $user_id == $recipient )
			wp_send_json( 'error' );

		$bonipress        = bonipress( $point_type );

		// Prep amount
		$amount        = sanitize_text_field( $_POST['amount'] );
		$amount        = $bonipress->number( abs( $amount ) );

		// Check solvency
		$account_limit = $bonipress->number( apply_filters( 'bonipress_transfer_acc_limit', $bonipress->zero() ) );
		$balance       = $bonipress->get_users_balance( $user_id, $point_type );
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
		if ( $bonipress->add_creds(
			$reference,
			$user_id,
			0 - $amount,
			$log_entry,
			$recipient,
			$data,
			$point_type
		) ) {

			// Then add to recipient
			$bonipress->add_creds(
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
add_action( 'wp_ajax_bonipress-send-points', 'bonipress_shortcode_send_points_ajax' );
