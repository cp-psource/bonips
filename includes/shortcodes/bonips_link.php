<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_link
 * This shortcode allows you to award or deduct points from the current user
 * when their click on a link. The shortcode will generate an anchor element
 * and call the bonips-click-link jQuery script which will award the points.
 *
 * Note! Only HTML5 anchor attributes are supported and this shortcode is only
 * available if the hook is enabled!
 *
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_link/
 * @since 1.1
 * @version 1.4
 */
if ( ! function_exists( 'bonips_render_shortcode_link' ) ) :
	function bonips_render_shortcode_link( $atts, $link_title = '' ) {

		global $bonips_link_points;

		$atts = shortcode_atts( array(
			'id'       => '',
			'rel'      => '',
			'class'    => '',
			'href'     => '',
			'title'    => '',
			'target'   => '',
			'style'    => '',
			'amount'   => 0,
			'ctype'    => BONIPS_DEFAULT_TYPE_KEY,
			'hreflang' => '',
			'media'    => '',
			'type'     => '',
			'onclick'  => ''
		), $atts, BONIPS_SLUG . '_link' );

		// Make sure point type exists
		if ( ! bonips_point_type_exists( $atts['ctype'] ) )
			$atts['ctype'] = BONIPS_DEFAULT_TYPE_KEY;

		// HREF is required
		if ( empty( $atts['href'] ) )
			$atts['href'] = '#';

		// All links must contain the 'bonips-points-link' class
		if ( empty( $atts['class'] ) )
			$atts['class'] = 'bonips-points-link';
		else
			$atts['class'] = 'bonips-points-link ' . $atts['class'];

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
				$prf_hook = apply_filters( 'bonips_option_id', 'bonips_pref_hooks' );
				$hooks = bonips_get_option( $prf_hook, false );
				if ( $atts['ctype'] != BONIPS_DEFAULT_TYPE_KEY )
					$hooks = bonips_get_option( 'bonips_pref_hooks_' . sanitize_key( $atts['ctype'] ), false );

				// Apply points value
				if ( $hooks !== false && is_array( $hooks ) && array_key_exists( 'link_click', $hooks['hook_prefs'] ) ) {
					$atts['amount'] = $hooks['hook_prefs']['link_click']['creds'];
				}

			}

			// Add key
			$token  = bonips_create_token( array( $atts['amount'], $atts['ctype'], $atts['id'], urlencode( $atts['href'] ) ) );
			$attr[] = 'data-token="' . $token . '"';

			// Make sure jQuery script is called
			$bonips_link_points = true;

		}

		// Return result
		return apply_filters( 'bonips_link', '<a ' . implode( ' ', $attr ) . '>' . do_shortcode( $link_title ) . '</a>', $atts, $link_title );

	}
endif;
add_shortcode( BONIPS_SLUG . '_link', 'bonips_render_shortcode_link' );
