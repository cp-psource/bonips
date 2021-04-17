<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Shortcode: Sell This
 * This shortcode is intended to be used when selling parts of a content.
 * Can only be used once per content.
 * @since 1.7
 * @version 1.0.3
 */
if ( ! function_exists( 'bonipress_render_sell_this' ) ) :
	function bonipress_render_sell_this( $atts, $content = '' ) {

		global $bonipress_partial_content_sale, $bonipress_modules;

		$post_id  = bonipress_sell_content_post_id();
		$post     = bonipress_get_post( $post_id );
		$user_id  = get_current_user_id();
		$is_admin = bonipress_is_admin( $user_id );
		$is_owner = ( (int) $post->post_author === $user_id ) ? true : false;

		$bonipress_partial_content_sale = true;

		// Logged in users
		if ( is_user_logged_in() ) {

			// Authors and admins do not pay
			if ( ! $is_admin && ! $is_owner ) {

				// In case we have not paid
				if ( ! bonipress_user_paid_for_content( $user_id, $post_id ) ) {

					// Get Payment Options
					$payment_options = bonipress_sell_content_payment_buttons( $user_id, $post_id );

					// User can buy
					if ( $payment_options !== false ) {

						$content = $bonipress_modules['solo']['content']->sell_content['templates']['members'];
						$content = str_replace( '%buy_button%', $payment_options, $content );
						$content = bonipress_sell_content_template( $content, $post, 'bonipress-sell-partial-content', 'bonipress-sell-unpaid' );

					}

					// Can not afford to buy
					else {

						$content = $bonipress_modules['solo']['content']->sell_content['templates']['cantafford'];
						$content = bonipress_sell_content_template( $content, $post, 'bonipress-sell-partial-content', 'bonipress-sell-insufficient' );

					}

				}

			}

			/**
			 * Incase the shortcode is used incorrectly
			 * Since the shortcode is only used to indicate which part of the content that is for sale, we need to make sure it can only be used
			 * on content that has been set to be purchasable. In manual mode, this means we must have clicked to enable sale in the metabox.
			 * In auto modes, the particular post types setup must be enabled and the post must fit any filter criteria we might have set.
			 * Since the content might have monetary value, we do not want to just show it, but to warn admin/post author and appologize to the user.
			 * @since 1.7.8
			 */
			elseif ( ! bonipress_post_is_for_sale( $post ) ) {

				if ( $is_admin || $is_owner )
					return '<p>' . sprintf( '%s %s', __( 'This shortcode can not be used in content that has not been set for sale!', 'bonipress' ), '<a href="' . get_edit_post_link( $post_id ) ) . '">' . __( 'Edit', 'bonipress' ) . '</a></p>';

				return '<p>' . __( 'This content is currently unattainable. Apologies for the inconvenience.', 'bonipress' ) . '</p>';

			}

		}

		// Visitors
		else {

			$content = $bonipress_modules['solo']['content']->sell_content['templates']['visitors'];
			$content = bonipress_sell_content_template( $content, $post, 'bonipress-sell-partial-content', 'bonipress-sell-visitor' );

		}

		return do_shortcode( $content );

	}
endif;

/**
 * Shortcode: Sell This AJAX
 * Depreciated as of version 1.7 and will be removed in version 1.8
 * @since 1.3
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_sell_this_ajax' ) ) :
	function bonipress_render_sell_this_ajax( $atts, $content = '' ) {

		_doing_it_wrong( 'bonipress_render_sell_this_ajax', 'The bonipress_sell_this_ajax shortcode has been depreciated and will be removed in version 1.8.', '1.7' );

		return bonipress_render_sell_this( $atts, $content );

	}
endif;

/**
 * Shortcode: Sales Counter
 * Renders the total number of times this post has been purchased or the total number of
 * active sales right now, if sales expire.
 * @attribute wrapper (string) - optional html element to wrap around the value.
 * @attribute post_id (int) - option to get the count for the provided post ID.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_sell_count' ) ) :
	function bonipress_render_sell_count( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'wrapper' => '',
			'post_id' => NULL
		), $atts, BONIPRESS_SLUG . '_content_sale_count' ) );

		if ( $post_id === NULL )
			$post_id = bonipress_sell_content_post_id();

		$content = '';

		if ( $wrapper != '' )
			$content .= '<' . $wrapper . ' class="bonipress-sell-this-sales-count">';

		$content .= bonipress_get_content_sales_count( $post_id );

		if ( $wrapper != '' )
			$content .= '</' . $wrapper . '>';

		return $content;

	}
endif;

/**
 * Shortcode: Sales Buyer Counter
 * Renders the total number of unique users that has purchased this content.
 * @attribute wrapper (string) - optional html element to wrap around the value.
 * @attribute post_id (int) - option to get the count for the provided post ID.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_sell_buyer_count' ) ) :
	function bonipress_render_sell_buyer_count( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'wrapper' => '',
			'post_id' => NULL
		), $atts, BONIPRESS_SLUG . '_content_buyer_count' ) );

		if ( $post_id === NULL )
			$post_id = bonipress_sell_content_post_id();

		$content = '';

		if ( $wrapper != '' )
			$content .= '<' . $wrapper . ' class="bonipress-sell-this-author-count">';

		$content .= bonipress_get_content_buyers_count( $post_id );

		if ( $wrapper != '' )
			$content .= '</' . $wrapper . '>';

		return $content;

	}
endif;

/**
 * Shortcode: Sales Verlauf
 * Will show a given users payment history with links to the posts
 * they have purchased.
 * @since 1.7
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_render_sell_history' ) ) :
	function bonipress_render_sell_history( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'user_id' => 'current',
			'number'  => 25,
			'nothing' => 'No purchases found',
			'ctype'   => NULL,
			'order'   => 'DESC'
		), $atts, BONIPRESS_SLUG . '_sales_history' ) );

		// Not logged in
		if ( ! is_user_logged_in() && $user_id == 'current' )
			return $content;

		$user_id     = bonipress_get_user_id( $user_id );
		$date_format = get_option( 'date_format' );
		$expiration  = apply_filters( 'bonipress_sell_exp_title', __( 'Hour(s)', 'bonipress' ) );
		$purchases   = bonipress_get_users_purchased_content( $user_id, $number, $order, $ctype );

		$columns     = apply_filters( 'bonipress_sales_history_columns', array(
			'col-date'    => __( 'Date', 'bonipress' ),
			'col-title'   => __( 'Title', 'bonipress' ),
			'col-amount'  => __( 'Cost', 'bonipress' ),
			'col-expires' => __( 'Expires', 'bonipress' )
		), $atts );

		if ( empty( $purchases ) && $nothing == '' ) return;

		ob_start();

?>
<div class="table-responsive bonipress-sell-this-history">
	<table class="table">
		<thead>
			<tr>
<?php

		foreach ( $columns as $column_id => $column_label )
			echo '<th class="bonipress-sell-' . $column_id . ' ' . $column_id . '">' . $column_label . '</th>';

?>
		</thead>
		<tbody>
<?php

		if ( ! empty( $purchases ) ) {
			foreach ( $purchases as $entry ) {

				$bonipress       = bonipress( $entry->ctype );
				$expirares_in = bonipress_sell_content_get_expiration_length( $entry->ref_id, $entry->ctype );

				echo '<td class="bonipress-sell-' . $column_id . ' ' . $column_id . '">';

				foreach ( $columns as $column_id => $column_label ) {

					if ( $column_id == 'col-date' )
						echo date( $date_format, $entry->time );

					elseif ( $column_id == 'col-title' )
						echo '<a href="' . bonipress_get_permalink( $entry->ref_id ) . '">' . bonipress_get_the_title( $entry->ref_id ) . '</a>';

					elseif ( $column_id == 'col-amount' )
						echo '<td class="">' . $bonipress->format_creds( abs( $entry->creds ) ) . '</td>';

					elseif ( $column_id == 'col-expires' ) {

						$expires = __( 'Never', 'bonipress' );
						if ( $prefs['expire'] > 0 )
							$expires = sprintf( _x( 'Purchase expires in %s', 'e.g. 10 hours', 'bonipress' ), $expirares_in . ' ' . $expiration );

						echo '<td class="">' . $expires . '</td>';

					}
					else {

						do_action( 'bonipress_sales_history_column', $column_id, $entry );
						do_action( 'bonipress_sales_history_column_' . $column_id, $entry );

					}

				}

				echo '</td>';

			}
		}
		else {

			echo '<tr><td class="no-results" colspan="' . count( $columns ) . '">' . $nothing . '</td></tr>';

		}

?>
		</tbody>
	</table>
</div>
<?php

		$content = ob_get_contents();
		ob_end_clean();

		return $content;

	}
endif;

/**
 * Shortcode: Buyer Avatars
 * Renders a given number of avatars of past buyers for this post.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_sell_buyer_avatars' ) ) :
	function bonipress_render_sell_buyer_avatars( $atts, $content = '' ) {

		extract( shortcode_atts( array(
			'post_id'   => NULL,
			'number'    => 10,
			'size'      => 42,
			'ctype'     => NULL,
			'use_email' => 0,
			'default'   => '',
			'alt'       => ''
		), $atts, BONIPRESS_SLUG . '_content_buyer_avatars' ) );

		if ( $post_id === NULL )
			$post_id = bonipress_sell_content_post_id();

		$buyers = bonipress_get_posts_buyers( $post_id, $number, $ctype );

		$content = '';
		if ( ! empty( $buyers ) ) {
			foreach ( $buyers as $buyer_id ) {

				$identification = $buyer_id;
				if ( absint( $use_email ) === 1 ) {
					$buyer_object   = get_userdata( $buyer_id );
					if ( ! isset( $buyer_object->ID ) ) continue;
					$identification = $buyer_object->user_email;
				}

				$avatar = get_avatar( $identification, $size, $default, $alt );
				$avatar = apply_filters( 'bonipress_sell_content_buyer_avatar', $avatar, $buyer_id, $post_id );
				if ( $avatar !== false )
					$content .= $avatar;

			}
		}

		return '<div class="bonipress-sell-this-buyers">' . $content . '</div>';

	}
endif;
