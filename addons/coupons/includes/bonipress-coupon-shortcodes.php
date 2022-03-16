<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Load Coupon Shortcode
 * Renders the form that allows users to redeem coupons from.
 * @since 1.4
 * @version 1.4
 */
if ( ! function_exists( 'bonipress_render_shortcode_load_coupon' ) ) :
	function bonipress_render_shortcode_load_coupon( $atts, $content = NULL ) {

		if ( ! is_user_logged_in() )
			return $content;

		extract( shortcode_atts( array(
			'label'       => 'Gutschein',
			'button'      => 'Gutschein anwenden',
			'placeholder' => ''
		), $atts, BONIPRESS_SLUG . '_load_coupon' ) );

		$bonipress = bonipress();
		if ( ! isset( $bonipress->coupons ) )
			return '<p><strong>Coupon-Add-on-Einstellungen fehlen! Bitte besuche die Seite BoniPress > Einstellungen, um Deine Einstellungen zu speichern, bevor Du diesen Shortcode verwendest.</strong></p>';

		// Prep
		$user_id = get_current_user_id();

		$output  = '<div class="bonipress-coupon-form">';

		// On submits
		if ( isset( $_POST['bonipress_coupon_load']['token'] ) && wp_verify_nonce( $_POST['bonipress_coupon_load']['token'], 'bonipress-load-coupon' . $user_id ) ) {

			$coupon_code = sanitize_text_field( $_POST['bonipress_coupon_load']['couponkey'] );
			$coupon_post = bonipress_get_coupon_post( $coupon_code );
			if ( isset( $coupon_post->ID ) ) {

				$coupon      = bonipress_get_coupon( $coupon_post->ID );

				// Attempt to use this coupon
				$load        = bonipress_use_coupon( $coupon_code, $user_id );

				// Load boniPRESS in the type we are paying out for messages
				if ( isset( $coupon->point_type ) && $coupon->point_type != $bonipress->cred_id )
					$bonipress = bonipress( $coupon->point_type );

				// That did not work out well, need to show an error message
				if ( ! bonipress_coupon_was_successfully_used( $load ) ) {

					$message = bonipress_get_coupon_error_message( $load, $coupon );
					$message = $bonipress->template_tags_general( $message );
					$output .= '<div class="alert alert-danger">' . $message . '</div>';

				}

				// Success!
				else {

					//$message = $bonipress->template_tags_amount( $bonipress->coupons['success'], $coupon->value );
					$updated_coupon_value=$coupon->value;
					$updated_coupon_value=apply_filters('bonipress_show_custom_coupon_value',$updated_coupon_value);
					$coupon_settings = bonipress_get_addon_settings( 'coupons' ,  $coupon->point_type  );
					$message = $bonipress->template_tags_amount( $coupon_settings['success'], $updated_coupon_value );   // without filter
					$message = str_replace( '%amount%', $bonipress->format_creds( $updated_coupon_value ), $message );
					$output .= '<div class="alert alert-success">' . $message . '</div>';

				}

			}

			// Invalid coupon
			else {

				$message = bonipress_get_coupon_error_message( 'invalid' );
				$message = $bonipress->template_tags_general( $message );
				$output .= '<div class="alert alert-danger">' . $message . '</div>';

			}

		}

		if ( $label != '' )
			$label = '<label for="bonipress-coupon-code">' . $label . '</label>';

		$output .= '
	<form action="" method="post" class="form-inline">
		<div class="form-group">
			' . $label . '
			<input type="text" name="bonipress_coupon_load[couponkey]" placeholder="' . esc_attr( $placeholder ) . '" id="bonipress-coupon-couponkey" class="form-control" value="" />
		</div>
		<div class="form-group">
			<input type="hidden" name="bonipress_coupon_load[token]" value="' . wp_create_nonce( 'bonipress-load-coupon' . $user_id ) . '" />
			<input type="submit" class="btn btn-primary" value="' . $button . '" />
		</div>
	</form>
</div>';

		return apply_filters( 'bonipress_load_coupon', $output, $atts, $content );

	}
endif;
