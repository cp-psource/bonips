<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_exchange
 * This shortcode will return an exchange form allowing users to
 * exchange one point type for another.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_exchange/
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'bonips_render_shortcode_exchange' ) ) :
	function bonips_render_shortcode_exchange( $atts, $content = '' ) {

		if ( ! is_user_logged_in() ) return $content;

		extract( shortcode_atts( array(
			'from'   => '',
			'to'     => '',
			'rate'   => 1,
			'min'    => 1,
			'button' => 'Exchange'
		), $atts, BONIPS_SLUG . '_exchange' ) );

		if ( $from == '' || $to == '' ) return '';

		if ( ! bonips_point_type_exists( $from ) || ! bonips_point_type_exists( $to ) ) return __( 'Punkttyp nicht gefunden.', 'bonips' );

		$user_id     = get_current_user_id();

		$bonips_from = bonips( $from );
		if ( $bonips_from->exclude_user( $user_id ) )
			return sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_from->plural() );

		$balance     = $bonips_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonips_from->number( $min ) )
			return __( 'Dein Guthaben ist zu niedrig, um diese Funktion zu verwenden.', 'bonips' );

		$bonips_to   = bonips( $to );
		if ( $bonips_to->exclude_user( $user_id ) )
			return sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_to->plural() );

		global $bonips_exchange;

		$rate        = apply_filters( 'bonips_exchange_rate', $rate, $bonips_from, $bonips_to );
		$token       = bonips_create_token( array( $from, $to, $user_id, $rate, $min ) );

		ob_start();

?>
<div class="bonips-exchange">

	<?php echo $content; ?>

	<?php if ( isset( $bonips_exchange['message'] ) ) : ?>
	<div class="alert alert-<?php if ( $bonips_exchange['success'] ) echo 'success'; else echo 'warning'; ?>"><?php echo $bonips_exchange['message']; ?></div>
	<?php endif; ?>

	<form action="" method="post" class="form">
		<div class="row">
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 bonips-exchange-current-balance">
				<div class="form-group">
					<label><?php printf( __( 'Dein aktuelles %s-Guthaben', 'bonips' ), $bonips_from->singular() ); ?></label>
					<p class="form-control-static"><?php echo $bonips_from->format_creds( $balance ); ?></p>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 bonips-exchange-current-amount">
				<div class="form-group">
					<label for="bonips-exchange-amount"><?php _e( 'Betrag', 'bonips' ); ?></label>
					<input type="text" size="20" placeholder="<?php printf( __( 'Minimum %s', 'bonips' ), $bonips_from->format_creds( $min ) ); ?>" value="" class="form-control" id="bonips-exchange-amount" name="bonips_exchange[amount]" />
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 bonips-exchange-current-rate">
				<div class="form-group">
					<label><?php _e( 'Tauschrate', 'bonips' ); ?></label>
					<p class="form-control-static"><?php printf( __( '1 %s = <span class="rate">%s</span> %s', 'bonips' ), $bonips_from->singular(), $rate, ( ( $rate == 1 ) ? $bonips_to->singular() : $bonips_to->plural() ) ); ?></p>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12bonips-exchange-current-submit">
				<div class="form-group">
					<input type="submit" class="btn btn-primary btn-lg btn-block" value="<?php echo esc_attr( $button ); ?>" />
				</div>
			</div>
		</div>
		<input type="hidden" name="bonips_exchange[token]" value="<?php echo $token; ?>" />
		<input type="hidden" name="bonips_exchange[nonce]" value="<?php echo wp_create_nonce( 'bonips-exchange' ); ?>" />
	</form>

</div>
<?php

		$output = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'bonips_exchange_output', $output, $atts );

	}
endif;
add_shortcode( BONIPS_SLUG . '_exchange', 'bonips_render_shortcode_exchange' );

/**
 * Catch Exchange
 * Intercepts and executes exchange requests.
 * @since 1.5
 * @version 1.1
 */
if ( ! function_exists( 'bonips_catch_exchange_requests' ) ) :
	function bonips_catch_exchange_requests() {

		if ( ! isset( $_POST['bonips_exchange']['nonce'] ) || ! wp_verify_nonce( $_POST['bonips_exchange']['nonce'], 'bonips-exchange' ) ) return;

		// Decode token
		$token       = bonips_verify_token( $_POST['bonips_exchange']['token'], 5 );
		if ( $token === false ) return;

		global $bonips_exchange;
		list ( $from, $to, $user_id, $rate, $min ) = $token;

		// Check point types
		$types       = bonips_get_types();
		if ( ! array_key_exists( $from, $types ) || ! array_key_exists( $to, $types ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => __( 'Punkttypen nicht gefunden.', 'bonips' )
			);
			return;
		}

		$user_id     = get_current_user_id();

		// Check for exclusion
		$bonips_from = bonips( $from );
		if ( $bonips_from->exclude_user( $user_id ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_from->plural() )
			);
			return;
		}

		// Check balance
		$balance     = $bonips_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonips_from->number( $min ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => __( 'Dein Guthaben ist zu niedrig, um diese Funktion zu verwenden.', 'bonips' )
			);
			return;
		}

		// Check for exclusion
		$bonips_to   = bonips( $to );
		if ( $bonips_to->exclude_user( $user_id ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_to->plural() )
			);
			return;
		}

		// Prep Amount
		$amount      = abs( $_POST['bonips_exchange']['amount'] );
		$amount      = $bonips_from->number( $amount );

		// Make sure we are sending more then minimum
		if ( $amount < $min ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du musst mindestens %s tauschen!', 'bonips' ), $bonips_from->format_creds( $min ) )
			);
			return;
		}

		// Make sure we have enough points
		if ( $amount > $balance ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => __( 'Unzureichende Mittel. Bitte versuche es mit einer geringeren Menge.', 'bonips' )
			);
			return;
		}

		// Let others decline
		$reply       = apply_filters( 'bonips_decline_exchange', false, compact( 'from', 'to', 'user_id', 'rate', 'min', 'amount' ) );
		if ( $reply === false ) {

			$bonips_from->add_creds(
				'exchange',
				$user_id,
				0-$amount,
				sprintf( __( 'Austausch von %s', 'bonips' ), $bonips_from->plural() ),
				0,
				array( 'from' => $from, 'rate' => $rate, 'min' => $min ),
				$from
			);

			$exchanged = $bonips_to->number( ( $amount * $rate ) );

			$bonips_to->add_creds(
				'exchange',
				$user_id,
				$exchanged,
				sprintf( __( 'Austausch zu %s', 'bonips' ), $bonips_to->plural() ),
				0,
				array( 'to' => $to, 'rate' => $rate, 'min' => $min ),
				$to
			);

			$bonips_exchange = array(
				'success' => true,
				'message' => sprintf( __( 'Du hast %s erfolgreich in %s umgetauscht.', 'bonips' ), $bonips_from->format_creds( $amount ), $bonips_to->format_creds( $exchanged ) )
			);

		}
		else {

			$bonips_exchange = array(
				'success' => false,
				'message' => $reply
			);
			return;

		}

	}
endif;
add_action( 'bonips_init', 'bonips_catch_exchange_requests', 100 );
