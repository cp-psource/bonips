<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonipress_exchange
 * This shortcode will return an exchange form allowing users to
 * exchange one point type for another.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_exchange/
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_exchange' ) ) :
	function bonipress_render_shortcode_exchange( $atts, $content = '' ) {

		if ( ! is_user_logged_in() ) return $content;

		extract( shortcode_atts( array(
			'from'   => '',
			'to'     => '',
			'rate'   => 1,
			'min'    => 1,
			'button' => 'Exchange'
		), $atts, BONIPRESS_SLUG . '_exchange' ) );

		if ( $from == '' || $to == '' ) return '';

		if ( ! bonipress_point_type_exists( $from ) || ! bonipress_point_type_exists( $to ) ) return __( 'Point type not found.', 'bonipress' );

		$user_id     = get_current_user_id();

		$bonipress_from = bonipress( $from );
		if ( $bonipress_from->exclude_user( $user_id ) )
			return sprintf( __( 'You are excluded from using %s.', 'bonipress' ), $bonipress_from->plural() );

		$balance     = $bonipress_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonipress_from->number( $min ) )
			return __( 'Your balance is too low to use this feature.', 'bonipress' );

		$bonipress_to   = bonipress( $to );
		if ( $bonipress_to->exclude_user( $user_id ) )
			return sprintf( __( 'You are excluded from using %s.', 'bonipress' ), $bonipress_to->plural() );

		global $bonipress_exchange;

		$rate        = apply_filters( 'bonipress_exchange_rate', $rate, $bonipress_from, $bonipress_to );
		$token       = bonipress_create_token( array( $from, $to, $user_id, $rate, $min ) );

		ob_start();

?>
<div class="bonipress-exchange">

	<?php echo $content; ?>

	<?php if ( isset( $bonipress_exchange['message'] ) ) : ?>
	<div class="alert alert-<?php if ( $bonipress_exchange['success'] ) echo 'success'; else echo 'warning'; ?>"><?php echo $bonipress_exchange['message']; ?></div>
	<?php endif; ?>

	<form action="" method="post" class="form">
		<div class="row">
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 bonipress-exchange-current-balance">
				<div class="form-group">
					<label><?php printf( __( 'Your current %s balance', 'bonipress' ), $bonipress_from->singular() ); ?></label>
					<p class="form-control-static"><?php echo $bonipress_from->format_creds( $balance ); ?></p>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 bonipress-exchange-current-amount">
				<div class="form-group">
					<label for="bonipress-exchange-amount"><?php _e( 'Amount', 'bonipress' ); ?></label>
					<input type="text" size="20" placeholder="<?php printf( __( 'Minimum %s', 'bonipress' ), $bonipress_from->format_creds( $min ) ); ?>" value="" class="form-control" id="bonipress-exchange-amount" name="bonipress_exchange[amount]" />
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 bonipress-exchange-current-rate">
				<div class="form-group">
					<label><?php _e( 'Exchange Rate', 'bonipress' ); ?></label>
					<p class="form-control-static"><?php printf( __( '1 %s = <span class="rate">%s</span> %s', 'bonipress' ), $bonipress_from->singular(), $rate, ( ( $rate == 1 ) ? $bonipress_to->singular() : $bonipress_to->plural() ) ); ?></p>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12bonipress-exchange-current-submit">
				<div class="form-group">
					<input type="submit" class="btn btn-primary btn-lg btn-block" value="<?php echo esc_attr( $button ); ?>" />
				</div>
			</div>
		</div>
		<input type="hidden" name="bonipress_exchange[token]" value="<?php echo $token; ?>" />
		<input type="hidden" name="bonipress_exchange[nonce]" value="<?php echo wp_create_nonce( 'bonipress-exchange' ); ?>" />
	</form>

</div>
<?php

		$output = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'bonipress_exchange_output', $output, $atts );

	}
endif;
add_shortcode( BONIPRESS_SLUG . '_exchange', 'bonipress_render_shortcode_exchange' );

/**
 * Catch Exchange
 * Intercepts and executes exchange requests.
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_catch_exchange_requests' ) ) :
	function bonipress_catch_exchange_requests() {

		if ( ! isset( $_POST['bonipress_exchange']['nonce'] ) || ! wp_verify_nonce( $_POST['bonipress_exchange']['nonce'], 'bonipress-exchange' ) ) return;

		// Decode token
		$token       = bonipress_verify_token( $_POST['bonipress_exchange']['token'], 5 );
		if ( $token === false ) return;

		global $bonipress_exchange;
		list ( $from, $to, $user_id, $rate, $min ) = $token;

		// Check point types
		$types       = bonipress_get_types();
		if ( ! array_key_exists( $from, $types ) || ! array_key_exists( $to, $types ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => __( 'Point types not found.', 'bonipress' )
			);
			return;
		}

		$user_id     = get_current_user_id();

		// Check for exclusion
		$bonipress_from = bonipress( $from );
		if ( $bonipress_from->exclude_user( $user_id ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'You are excluded from using %s.', 'bonipress' ), $bonipress_from->plural() )
			);
			return;
		}

		// Check balance
		$balance     = $bonipress_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonipress_from->number( $min ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => __( 'Your balance is too low to use this feature.', 'bonipress' )
			);
			return;
		}

		// Check for exclusion
		$bonipress_to   = bonipress( $to );
		if ( $bonipress_to->exclude_user( $user_id ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'You are excluded from using %s.', 'bonipress' ), $bonipress_to->plural() )
			);
			return;
		}

		// Prep Amount
		$amount      = abs( $_POST['bonipress_exchange']['amount'] );
		$amount      = $bonipress_from->number( $amount );

		// Make sure we are sending more then minimum
		if ( $amount < $min ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'You must exchange at least %s!', 'bonipress' ), $bonipress_from->format_creds( $min ) )
			);
			return;
		}

		// Make sure we have enough points
		if ( $amount > $balance ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => __( 'Insufficient Funds. Please try a lower amount.', 'bonipress' )
			);
			return;
		}

		// Let others decline
		$reply       = apply_filters( 'bonipress_decline_exchange', false, compact( 'from', 'to', 'user_id', 'rate', 'min', 'amount' ) );
		if ( $reply === false ) {

			$bonipress_from->add_creds(
				'exchange',
				$user_id,
				0-$amount,
				sprintf( __( 'Exchange from %s', 'bonipress' ), $bonipress_from->plural() ),
				0,
				array( 'from' => $from, 'rate' => $rate, 'min' => $min ),
				$from
			);

			$exchanged = $bonipress_to->number( ( $amount * $rate ) );

			$bonipress_to->add_creds(
				'exchange',
				$user_id,
				$exchanged,
				sprintf( __( 'Exchange to %s', 'bonipress' ), $bonipress_to->plural() ),
				0,
				array( 'to' => $to, 'rate' => $rate, 'min' => $min ),
				$to
			);

			$bonipress_exchange = array(
				'success' => true,
				'message' => sprintf( __( 'You have successfully exchanged %s into %s.', 'bonipress' ), $bonipress_from->format_creds( $amount ), $bonipress_to->format_creds( $exchanged ) )
			);

		}
		else {

			$bonipress_exchange = array(
				'success' => false,
				'message' => $reply
			);
			return;

		}

	}
endif;
add_action( 'bonipress_init', 'bonipress_catch_exchange_requests', 100 );
