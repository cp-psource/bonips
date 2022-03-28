<?php
if ( ! defined( 'BONIPS_PURCHASE' ) ) exit;

/**
 * Shortcode: bonips_buy
 * @see http://codex.bonips.me/shortcodes/bonips_buy/
 * @since 1.0
 * @version 1.3
 */
if ( ! function_exists( 'bonips_render_buy_points' ) ) :
	function bonips_render_buy_points( $atts = array(), $button_label = '' ) {

		$settings           = bonips_get_buycred_settings();

		extract( shortcode_atts( array(
			'gateway' => '',
			'ctype'   => BONIPS_DEFAULT_TYPE_KEY,
			'amount'  => '',
			'gift_to' => '',
			'class'   => 'bonips-buy-link btn btn-primary btn-lg',
			'login'   => $settings['login']
		), $atts, BONIPS_SLUG . '_buy' ) );

		$bonips = bonips( $ctype );

		// If we are not logged in
		if ( ! is_user_logged_in() ) return $bonips->template_tags_general( $login );

		global $bonips_modules, $buycred_sale, $post;

		$buycred            = $bonips_modules['solo']['buycred'];
		$installed          = bonips_get_buycred_gateways();

		// Catch errors
		if ( empty( $installed ) )                                                   return 'No gateways installed.';
		elseif ( ! empty( $gateway ) && ! array_key_exists( $gateway, $installed ) ) return 'Gateway does not exist.';
		elseif ( empty( $buycred->active ) )                                         return 'No active gateways found.';
		elseif ( ! empty( $gateway ) && ! $buycred->is_active( $gateway ) )          return 'The selected gateway is not active.';

		$buycred_sale       = true;

		// Make sure we are trying to sell a point type that is allowed to be purchased
		if ( ! in_array( $ctype, $settings['types'] ) )
			$ctype = $settings['types'][0];

		$args               = array();
		$args['bonips_buy'] = $gateway;
		$classes[]          = $gateway;

		// Prep
		$buyer_id           = get_current_user_id();
		$recipient_id       = $buyer_id;

		if ( $settings['gifting']['authors'] && $gift_to == 'author' )
			$recipient_id = $post->post_author;

		if ( $settings['gifting']['members'] && absint( $gift_to ) !== 0 )
			$recipient_id = absint( $gift_to );

		if ( $recipient_id !== $buyer_id )
			$args['gift_to'] = $recipient_id;

		// Allow user related template tags to be used in the button label
		$button_label       = $bonips->template_tags_general( $button_label );
		$button_label       = $bonips->template_tags_user( $button_label, $recipient_id );

		$args['ctype']      = $ctype;
		$args['amount']     = $amount;
		$args['token']      = wp_create_nonce( 'bonips-buy-creds' );

		// Let others add items to the arguments
		$args               = apply_filters( 'bonips_buy_args', $args, $atts, $bonips );

		// Classes
		$classes            = explode( ' ', $class );

		if ( empty( $classes ) || ! in_array( 'bonips-buy-link', $classes ) )
			$classes[] = 'bonips-buy-link';

		$current_url        = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		if ( is_ssl() )
			$current_url = str_replace( 'http://', 'https://', $current_url );

		// Construct anchor element to take us to the checkout page
		return '<a href="' . esc_url( add_query_arg( $args, $current_url ) ) . '" data-gateway="' . $gateway . '" class="' . implode( ' ', $classes ) . '" title="' . esc_attr( strip_tags( $button_label ) ) . '">' . do_shortcode( $button_label ) . '</a>';

	}
endif;

/**
 * Shortcode: bonips_buy_form
 * @see http://codex.bonips.me/shortcodes/bonips_buy_form/
 * @since 1.0
 * @version 1.3
 */
if ( ! function_exists( 'bonips_render_buy_form_points' ) ) :
	function bonips_render_buy_form_points( $atts = array(), $content = '' ) {

		$settings     = bonips_get_buycred_settings();

		extract( shortcode_atts( array(
			'button'   => __( 'Kaufe jetzt', 'bonips' ),
			'gateway'  => '',
			'ctype'    => BONIPS_DEFAULT_TYPE_KEY,
			'amount'   => '',
			'excluded' => '',
			'maxed'    => '',
			'gift_to'  => '',
			'e_rate'   => '',
			'gift_by'  => __( 'Benutzername', 'bonips' ),
			'inline'   => 0
		), $atts, BONIPS_SLUG . '_buy_form' ) );

		// If we are not logged in
		if ( ! is_user_logged_in() ) return $content;

		global $post, $buycred_instance, $buycred_sale;

		// Prepare
		$buyer_id     = get_current_user_id();
		$recipient_id = $buyer_id;
		$classes      = array( 'boniPS-buy-form' );
		$amounts      = array();
		$gifting      = false;

		// Make sure we have a gateway we can use
		if ( ( ! empty( $gateway ) && ! bonips_buycred_gateway_is_usable( $gateway ) ) || ( empty( $gateway ) && empty( $buycred_instance->active ) ) )
			return 'Kein Gateway verfügbar.';

		// Make sure we are trying to sell a point type that is allowed to be purchased
		if ( ! in_array( $ctype, $settings['types'] ) )
			$ctype = $settings['types'][0];

		$bonips       = bonips( $ctype );
		$setup        = bonips_get_buycred_sale_setup( $ctype );

		$remaining    = bonips_user_can_buycred( $buyer_id, $ctype );

		// We are excluded from this point type
		if ( $remaining === false ) return $excluded;

		// We have reached a max purchase limit
		elseif ( $remaining === 0 ) return $maxed;

		// From this moment on, we need to indicate the shortcode usage for scripts and styles.
		$buycred_sale = true;

		// Amount - This can be one single amount or a comma separated list
		$minimum      = $bonips->number( $setup['min'] );

		if ( ! empty( $amount ) ) {
			foreach ( explode( ',', $amount ) as $value ) {
				$value     = $bonips->number( abs( trim( $value ) ) );
				if ( $value < $minimum ) continue;
				$amounts[] = $value;
			}
		}

		// If we want to gift this to the post author (must be used inside the loop)
		if ( $settings['gifting']['authors'] && $gift_to == 'author' ) {
			$recipient_id = absint( $post->post_author );
			$gifting      = true;
		}

		// If we have nominated a user ID to be the recipient, use it
		elseif ( $settings['gifting']['members'] && absint( $gift_to ) !== 0 ) {
			$recipient_id = absint( $gift_to );
			$gifting      = true;
		}

		// Button Label
		$button_label = $bonips->template_tags_general( $button );

		if ( ! empty( $gateway ) ) {
			$gateway_name = explode( ' ', $buycred_instance->active[ $gateway ]['title'] );
			$button_label = str_replace( '%gateway%', $gateway_name[0], $button_label );
			$classes[]    = $gateway_name[0];
		}

		ob_start();

		if ( ! empty( $buycred_instance->gateway->errors ) ) {

			foreach ( $buycred_instance->gateway->errors as $error )
				echo '<div class="alert alert-warnng"><p>' . $error . '</p></div>';

		}

?>
<div class="row">
	<div class="col-xs-12">
		<form method="post" class="form<?php if ( $inline == 1 ) echo '-inline'; ?> <?php echo implode( ' ', $classes ); ?>" action="">
			<input type="hidden" name="token" value="<?php echo wp_create_nonce( 'bonips-buy-creds' ); ?>" />
			<input type="hidden" name="ctype" value="<?php echo esc_attr( $ctype ); ?>" />

			<?php if( isset($e_rate) && !empty($e_rate)){ 
				$e_rate=base64_encode($e_rate);
				?>
			<input type="hidden" name="er_random" value="<?php echo esc_attr($e_rate); ?>" />
			<?php } ?>

			<div class="form-group">
				<label><?php echo $bonips->plural(); ?></label>
<?php

		// No amount given - user must nominate the amount
		if ( count( $amounts ) == 0 ) {

?>
				<input type="text" name="amount" class="form-control" placeholder="<?php echo $bonips->format_creds( $minimum ); ?>" min="<?php echo $minimum; ?>" value="" />
<?php

		}

		// One amount - this is the amount a user must buy
		elseif ( count( $amounts ) == 1 ) {

?>
				<p class="form-control-static"><?php echo $bonips->format_creds( $amounts[0] ); ?></p>
				<input type="hidden" name="amount" value="<?php echo esc_attr( $amounts[0] ); ?>" />
<?php

		}

		// Multiple amounts - user selects the amount from a dropdown menu
		else {

?>
				<select name="amount" class="form-control">
<?php

				foreach ( $amounts as $amount ) {

					echo '<option value="' . esc_attr( $amount ) . '"';

					// If we enforce a maximum and the nominated amount is higher than we can buy,
					// disable the option
					if ( $remaining !== true && $remaining < $amount ) echo ' disabled="disabled"';

					echo '>' . $bonips->format_creds( $amount ) . '</option>';

				}

?>
				</select>
<?php

		}

		// A recipient is set
		if ( $gifting ) {

			$user = get_userdata( $recipient_id );

?>
				<div class="form-group">
					<label for="gift_to"><?php _e( 'Recipient', 'bonips' ); ?></label>
					<p class="form-control-static"><?php echo esc_html( $user->display_name ); ?></p>
					<input type="hidden" name="<?php if ( $gift_to == 'author' ) echo 'post_id'; else echo 'gift_to'; ?>" value="<?php echo absint( $recipient_id ); ?>" />
				</div>
<?php

		}

		// The payment gateway needs to be selected
		if ( empty( $gateway ) && count( $buycred_instance->active ) > 1 ) {

?>
				<div class="form-group">
					<label for="gateway"><?php _e( 'Pay Using', 'bonips' ); ?></label>
					<select name="bonips_buy" class="form-control">
<?php

			$active_gateways = apply_filters( 'bonips_buycred_sort_gateways', $buycred_instance->active, $atts );
			foreach ( $active_gateways as $gateway_id => $info )
				echo '<option value="' . esc_attr( $gateway_id ) . '">' . esc_html( $info['title'] ) . '</option>';

?>
					</select>
				</div>
<?php

		}

		// The gateway is set or we just have one gateway enabled
		else {

			// If no gateway is set, use the first active gateway
			if (  empty( $gateway ) && count( $buycred_instance->active ) > 0 )
				$gateway = array_keys( $buycred_instance->active )[0];

?>
				<input type="hidden" name="bonips_buy" value="<?php echo esc_attr( $gateway ); ?>" />
<?php

		}

?>
				</div>

				<div class="form-group">
					<input type="submit" class="button btn btn-block btn-lg" value="<?php echo $button_label; ?>" />
				</div>
			

		</form>
	</div>
</div>
<?php

		$content = ob_get_contents();
		ob_end_clean();

		return $content;

	}
endif;

/**
 * Shortcode: bonips_buy_pending
 * @see http://codex.bonips.me/shortcodes/bonips_buy_pending/
 * @since 1.5
 * @version 1.3
 */
if ( ! function_exists( 'bonips_render_pending_purchases' ) ) :
	function bonips_render_pending_purchases( $atts = array(), $content = '' ) {

		// Must be logged in
		if ( ! is_user_logged_in() ) return $content;

		extract( shortcode_atts( array(
			'ctype'   => BONIPS_DEFAULT_TYPE_KEY,
			'pay_now' => __( 'Pay Now', 'bonips' ),
			'cancel'  => __( 'Cancel', 'bonips' )
		), $atts, BONIPS_SLUG . '_buy_pending' ) );

		$user_id = get_current_user_id();
		$pending = buycred_get_users_pending_payments( $user_id, $ctype );

		global $bonips_modules, $buycred_sale;

		$buycred = $bonips_modules['solo']['buycred-pending'];

		ob_start();

?>
<div id="pending-buycred-payments-<?php echo $ctype; ?>">
	<table class="table">
		<thead>
			<tr>
				<th class="column-transaction-id"><?php _e( 'Transaction ID', 'bonips' ); ?></th>
				<th class="column-gateway"><?php _e( 'Gateway', 'bonips' ); ?></th>
				<th class="column-amount"><?php _e( 'Amount', 'bonips' ); ?></th>
				<th class="column-cost"><?php _e( 'Cost', 'bonips' ); ?></th>
				<?php if ( $ctype == '' ) : ?><th class="column-ctype"><?php _e( 'Point Type', 'bonips' ); ?></th><?php endif; ?>
				<th class="column-actions"><?php _e( 'Actions', 'bonips' ); ?></th>
			</tr>
		</thead>
		<tbody>
<?php

		if ( ! empty( $pending ) ) {

			// Showing all point types
			if ( $ctype == '' ) {

				foreach ( $pending as $point_type => $entries ) {

					if ( empty( $entries ) ) continue;

					foreach ( $entries as $entry ) {

?>
			<tr>
				<td class="column-transaction-id"><?php echo esc_attr( $entry->public_id ); ?></td>
				<td class="column-gateway"><?php echo $buycred->adjust_column_content( 'gateway', $entry->payment_id ); ?></td>
				<td class="column-amount"><?php echo $buycred->adjust_column_content( 'amount', $entry->payment_id ); ?></td>
				<td class="column-cost"><?php echo $buycred->adjust_column_content( 'cost', $entry->payment_id ); ?></td>
				<td class="column-ctype"><?php echo bonips_get_point_type_name( $entry->point_type, false ); ?></td>
				<td class="column-actions">
					<a href="<?php echo esc_url( $entry->pay_now_url ); ?>"><?php echo $pay_now; ?></a> &bull; <a href="<?php echo esc_url( $entry->cancel_url ); ?>"><?php echo $cancel; ?></a>
				</td>
			</tr>
<?php
 
					}

				}

			}

			// Showing a particular point type
			else {

				foreach ( $pending as $entry ) {

?>
			<tr>
				<td class="column-transaction-id"><?php echo esc_attr( $entry->public_id ); ?></td>
				<td class="column-gateway"><?php echo $buycred->adjust_column_content( 'gateway', $entry->payment_id ); ?></td>
				<td class="column-amount"><?php echo $buycred->adjust_column_content( 'amount', $entry->payment_id ); ?></td>
				<td class="column-cost"><?php echo $buycred->adjust_column_content( 'cost', $entry->payment_id ); ?></td>
				<td class="column-actions">
					<a href="<?php echo esc_url( $entry->pay_now_url ); ?>"><?php echo $pay_now; ?></a> &bull; <a href="<?php echo esc_url( $entry->cancel_url ); ?>"><?php echo $cancel; ?></a>
				</td>
			</tr>
<?php

				}

			}

		}
		else {

?>
			<tr>
				<td colspan="<?php if ( $ctype == '' ) echo '6'; else echo '5'; ?>"><?php _e( 'No pending payments found', 'bonips' ); ?></td>
			</tr>
<?php

		}

?>
		</tbody>
	</table>
</div>
<?php

		$output = ob_get_contents();
		ob_end_clean();

		return $output;

	}
endif;
