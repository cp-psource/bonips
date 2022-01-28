<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonipress_link
 * This shortcode allows you to award or deduct points from the current user
 * when their click on a link. The shortcode will generate an anchor element
 * and call the bonipress-click-link jQuery script which will award the points.
 *
 * Note! Only HTML5 anchor attributes are supported and this shortcode is only
 * available if the hook is enabled!
 *
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_link/
 * @since 1.1
 * @version 1.4
 */
if ( ! function_exists( 'bonipress_render_shortcode_link' ) ) :
	function bonipress_render_shortcode_link( $atts, $link_title = '' ) {

		global $bonipress_link_points;

		$atts = shortcode_atts( array(
			'id'       => '',
			'rel'      => '',
			'class'    => '',
			'href'     => '',
			'title'    => '',
			'target'   => '',
			'style'    => '',
			'amount'   => 0,
			'ctype'    => BONIPRESS_DEFAULT_TYPE_KEY,
			'hreflang' => '',
			'media'    => '',
			'type'     => '',
			'onclick'  => ''
		), $atts, BONIPRESS_SLUG . '_link' );

		// Make sure point type exists
		if ( ! bonipress_point_type_exists( $atts['ctype'] ) )
			$atts['ctype'] = BONIPRESS_DEFAULT_TYPE_KEY;

		// HREF is required
		if ( empty( $atts['href'] ) )
			$atts['href'] = '#';

		// All links must contain the 'bonipress-points-link' class
		if ( empty( $atts['class'] ) )
			$atts['class'] = 'bonipress-points-link';
		else
			$atts['class'] = 'bonipress-points-link ' . $atts['class'];

		// If no id exists, make one
		if ( empty( $atts['id'] ) ) {
			$id         = str_replace( array( 'http://', 'https://', 'http%3A%2F%2F', 'https%3A%2F%2F' ), 'hs', $atts['href'] );
			$id         = str_replace( array( '/', '-', '_', ':', '.', '?', '=', '+', '\\', '%2F' ), '', $id );
			$atts['id'] = $id;
		}

		// Construct anchor attributes
		$attr = array();
		foreach ( $atts as $attribute => $value ) {
			if ( ! empty( $value ) && ! in_array( $attribute, array( 'amount', 'ctype' ) ) ) {
				$attr[] = $attribute . '="' . $value . '"';
			}
		}

		// Add point type as a data attribute
		$attr[] = 'data-type="' . esc_attr( $atts['ctype'] ) . '"';

		// Only usable for members
		if ( is_user_logged_in() ) {

			// If amount is zero, use the amount we set in the hooks settings
			if ( $atts['amount'] == 0 ) {

				// Get hook settings
				$prf_hook = apply_filters( 'bonipress_option_id', 'bonipress_pref_hooks' );
				$hooks = bonipress_get_option( $prf_hook, false );
				if ( $atts['ctype'] != BONIPRESS_DEFAULT_TYPE_KEY )
					$hooks = bonipress_get_option( 'bonipress_pref_hooks_' . sanitize_key( $atts['ctype'] ), false );

				// Apply points value
				if ( $hooks !== false && is_array( $hooks ) && array_key_exists( 'link_click', $hooks['hook_prefs'] ) ) {
					$atts['amount'] = $hooks['hook_prefs']['link_click']['creds'];
				}

			}

			// Add key
			$token  = bonipress_create_token( array( $atts['amount'], $atts['ctype'], $atts['id'], urlencode( $atts['href'] ) ) );
			$attr[] = 'data-token="' . $token . '"';

			// Make sure jQuery script is called
			$bonipress_link_points = true;

		}

		// Return result
		return apply_filters( 'bonipress_link', '<a ' . implode( ' ', $attr ) . '>' . do_shortcode( $link_title ) . '</a>', $atts, $link_title );

	}
endif;
add_shortcode( BONIPRESS_SLUG . '_link', 'bonipress_render_shortcode_link' );
