<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Load Coupon Shortcode
 * Renders the form that allows users to redeem coupons from.
 * @since 1.4
 * @version 1.4
 */
if ( ! function_exists( 'bonips_render_shortcode_load_coupon' ) ) :
	function bonips_render_shortcode_load_coupon( $atts, $content = NULL ) {

		if ( ! is_user_logged_in() )
			return $content;

		extract( shortcode_atts( array(
			'label'       => 'Gutschein',
			'button'      => 'Gutschein anwenden',
			'placeholder' => ''
		), $atts, BONIPS_SLUG . '_load_coupon' ) );

		$bonips = bonips();
		if ( ! isset( $bonips->coupons ) )
			return '<p><strong>Coupon-Add-on-Einstellungen fehlen! Bitte besuche die Seite BoniPress > Einstellungen, um Deine Einstellungen zu speichern, bevor Du diesen Shortcode verwendest.</strong></p>';

		// Prep
		$user_id = get_current_user_id();

		$output  = '<div class="bonips-coupon-form">';

		// On submits
		if ( isset( $_POST['bonips_coupon_load']['token'] ) && wp_verify_nonce( $_POST['bonips_coupon_load']['token'], 'bonips-load-coupon' . $user_id ) ) {

			$coupon_code = sanitize_text_field( $_POST['bonips_coupon_load']['couponkey'] );
			$coupon_post = bonips_get_coupon_post( $coupon_code );
			if ( isset( $coupon_post->ID ) ) {

				$coupon      = bonips_get_coupon( $coupon_post->ID );

				// Attempt to use this coupon
				$load        = bonips_use_coupon( $coupon_code, $user_id );

				// Load boniPS in the type we are paying out for messages
				if ( isset( $coupon->point_type ) && $coupon->point_type != $bonips->cred_id )
					$bonips = bonips( $coupon->point_type );

				// That did not work out well, need to show an error message
				if ( ! bonips_coupon_was_successfully_used( $load ) ) {

					$message = bonips_get_coupon_error_message( $load, $coupon );
					$message = $bonips->template_tags_general( $message );
					$output .= '<div class="alert alert-danger">' . $message . '</div>';

				}

				// Success!
				else {

					//$message = $bonips->template_tags_amount( $bonips->coupons['success'], $coupon->value );
					$updated_coupon_value=$coupon->value;
					$updated_coupon_value=apply_filters('bonips_show_custom_coupon_value',$updated_coupon_value);
					$coupon_settings = bonips_get_addon_settings( 'coupons' ,  $coupon->point_type  );
					$message = $bonips->template_tags_amount( $coupon_settings['success'], $updated_coupon_value );   // without filter
					$message = str_replace( '%amount%', $bonips->format_creds( $updated_coupon_value ), $message );
					$output .= '<div class="alert alert-success">' . $message . '</div>';

				}

			}

			// Invalid coupon
			else {

				$message = bonips_get_coupon_error_message( 'invalid' );
				$message = $bonips->template_tags_general( $message );
				$output .= '<div class="alert alert-danger">' . $message . '</div>';

			}

		}

		if ( $label != '' )
			$label = '<label for="bonips-coupon-code">' . $label . '</label>';

		$output .= '
	<form action="" method="post" class="form-inline">
		<div class="form-group">
			' . $label . '
			<input type="text" name="bonips_coupon_load[couponkey]" placeholder="' . esc_attr( $placeholder ) . '" id="bonips-coupon-couponkey" class="form-control" value="" />
		</div>
		<div class="form-group">
			<input type="hidden" name="bonips_coupon_load[token]" value="' . wp_create_nonce( 'bonips-load-coupon' . $user_id ) . '" />
			<input type="submit" class="btn btn-primary" value="' . $button . '" />
		</div>
	</form>
</div>';

		return apply_filters( 'bonips_load_coupon', $output, $atts, $content );

	}
endif;
