<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Sell Content Settings
 * Returns the sell content add-ons settings.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_sell_content_settings' ) ) :
	function bonipress_sell_content_settings() {

		$bonipress   = bonipress();
		if ( isset( $bonipress->sell_content ) )
			$settings = $bonipress->sell_content;

		else {

			global $bonipress_modules;

			$settings = $bonipress_modules['solo']['content']->sell_content;

		}

		return $settings;

	}
endif;

/**
 * Get Post Types
 * Returns an array of sellable post types. In order for a post type to be
 * considered "usable", it must be public.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_sell_content_post_types' ) ) :
	function bonipress_sell_content_post_types() {

		$args = array(
			'public'   => true,
			'_builtin' => false
		);

		$post_types        = get_post_types( $args, 'objects', 'OR' );

		$eligeble_types    = array();
		$bonipress_post_types = get_bonipress_post_types();

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type => $setup ) {

				if ( $setup->public != 1 ) continue;

				if ( in_array( $post_type, $bonipress_post_types ) || $post_type == 'attachment' ) continue;

				$eligeble_types[ $post_type ] = $setup->labels->name;

			}
		}

		return $eligeble_types;

	}
endif;

/**
 * Get The Post ID
 * Will attempt to get the current posts ID. Also supports bbPress
 * where it will return either the form ID (if viewing a forum), the topic ID
 * (if viewing a topic) or the reply ID (if viewing a reply).
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_sell_content_post_id' ) ) :
	function bonipress_sell_content_post_id() {

		$post_id = $bbp_topic_id = $bbp_reply_id = false;

		// Check if we are selling access to bbPress forum, topic or reply
		if ( function_exists( 'bbpress' ) ) {

			global $wp_query;

			$bbp = bbpress();

			// Currently inside a topic loop
			if ( ! empty( $bbp->topic_query->in_the_loop ) && isset( $bbp->topic_query->post->ID ) )
				$bbp_topic_id = $bbp->topic_query->post->ID;

			// Currently inside a search loop
			elseif ( ! empty( $bbp->search_query->in_the_loop ) && isset( $bbp->search_query->post->ID ) && bbp_is_topic( $bbp->search_query->post->ID ) )
				$bbp_topic_id = $bbp->search_query->post->ID;

			// Currently viewing/editing a topic, likely alone
			elseif ( ( bbp_is_single_topic() || bbp_is_topic_edit() ) && ! empty( $bbp->current_topic_id ) )
				$bbp_topic_id = $bbp->current_topic_id;

			// Currently viewing/editing a topic, likely in a loop
			elseif ( ( bbp_is_single_topic() || bbp_is_topic_edit() ) && isset( $wp_query->post->ID ) )
				$bbp_topic_id = $wp_query->post->ID;
			
			// So far, no topic found, check if we are in a reply
			if ( $bbp_topic_id === false ) {

				// Currently inside a replies loop
				if ( ! empty( $bbp->reply_query->in_the_loop ) && isset( $bbp->reply_query->post->ID ) )
					$bbp_reply_id = $bbp->reply_query->post->ID;

				// Currently inside a search loop
				elseif ( ! empty( $bbp->search_query->in_the_loop ) && isset( $bbp->search_query->post->ID ) && bbp_is_reply( $bbp->search_query->post->ID ) )
					$bbp_reply_id = $bbp->search_query->post->ID;

				// Currently viewing a forum
				elseif ( ( bbp_is_single_reply() || bbp_is_reply_edit() ) && ! empty( $bbp->current_reply_id ) )
					$bbp_reply_id = $bbp->current_reply_id;

				// Currently viewing a reply
				elseif ( ( bbp_is_single_reply() || bbp_is_reply_edit() ) && isset( $wp_query->post->ID ) )
					$bbp_reply_id = $wp_query->post->ID;
			
				if ( $bbp_reply_id !== false )
					$post_id = $bbp_reply_id;

			}
			
			// Else we are in a topic
			else $post_id = $bbp_topic_id;

		}

		if ( $post_id === false ) {

			global $post;

			if ( isset( $post->ID ) )
				$post_id = $post->ID;

		}

		return apply_filters( 'bonipress_sell_this_get_post_ID', $post_id );

	}
endif;

/**
 * Post Type for Sale
 * Returns either true (post type is for sale) or false (post type not for sale).
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_post_type_for_sale' ) ) :
	function bonipress_post_type_for_sale( $post_type = NULL ) {

		$settings = bonipress_sell_content_settings();
		$for_sale = false;

		if ( array_key_exists( 'post_types', $settings ) && ! empty( $settings['post_types'] ) ) {

			$post_types = explode( ',', $settings['post_types'] );
			if ( in_array( $post_type, $post_types ) )
				$for_sale = true;

		}

		// BuddyPress support to prevent issues when we select to sell access to all pages.
		if ( function_exists( 'bp_current_component' ) && bp_current_component() !== false )
			$for_sale = false;

		return apply_filters( 'bonipress_post_type_for_sale', $for_sale, $post_type );

	}
endif;

/**
 * Post is for Sale
 * Returns true (post is for sale) or false (post is not for sale).
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_post_is_for_sale' ) ) :
	function bonipress_post_is_for_sale( $post = NULL ) {

		if ( ! is_object( $post ) )
			$post = bonipress_get_post( $post );

		// Invalid post - not for sale
		if ( ! isset( $post->ID ) ) return false;

		$settings    = bonipress_sell_content_settings();
		$point_types = $settings['type'];
		$for_sale    = false;

		// We start with checking the post type.
		if ( bonipress_post_type_for_sale( $post->post_type ) && array_key_exists( $post->post_type, $settings['filters'] ) ) {

			$filter = $settings['filters'][ $post->post_type ]['by'];
			$list   = explode( ',', $settings['filters'][ $post->post_type ]['list'] );

			// Manual filter - check saved settings
			if ( $filter === 'manual' ) {

				// Loop through each point type we allow and check the settings to see if anyone is enabled
				foreach ( $point_types as $type_id ) {

					$suffix = '_' . $type_id;
					if ( $type_id == BONIPRESS_DEFAULT_TYPE_KEY )
						$suffix = '';

					$sale_setup = (array) bonipress_get_post_meta( $post->ID, 'boniPRESS_sell_content' . $suffix, true );
					if ( array_key_exists( 'status', $sale_setup ) && $sale_setup['status'] === 'enabled' )
						$for_sale = true;

				}

			}

			// All posts for sale
			elseif ( $filter === 'all' ) {

				$for_sale = true;

			}

			// Posts are set for sale but some are excluded
			elseif ( $filter === 'exclude' ) {

				// If post is not excluded, it is for sale
				if ( ! in_array( $post->ID, $list ) )
					$for_sale = true;

			}

			// Posts are not for sale but some are
			elseif ( $filter === 'include' ) {

				// If post is included, it is for sale
				if ( in_array( $post->ID, $list ) )
					$for_sale = true;

			}

			// Taxonomy check
			else {

				$check    = 'include';
				$taxonomy = $filter;

				if ( substr( $taxonomy, 0, 1 ) === '-' ) {
					$check    = 'exclude';
					$taxonomy = ltrim( $taxonomy );
				}

				// Get post terms
				$terms    = wp_get_post_terms( $post->ID, $taxonomy );

				// Taxonomy exclude check
				if ( $check === 'exclude' ) {

					if ( ! empty( $terms ) ) {
						foreach ( $terms as $term ) {

							if ( in_array( $term->slug, $list ) ) continue;
							$for_sale = true;

						}
					}

					// No terms - not excluded
					else {
						$for_sale = true;
					}

				}

				// Taxonomy include check
				else {

					if ( ! empty( $terms ) ) {
						foreach ( $terms as $term ) {

							if ( ! in_array( $term->slug, $list ) ) continue;
							$for_sale = true;

						}
					}

				}

			}

		}

		return apply_filters( 'bonipress_post_is_for_sale', $for_sale, $post, $settings );

	}
endif;

/**
 * User Has Paid
 * Checks if a user has paid for the given post. Will also take into account
 * if a purchase has expired (if used).
 * @since 1.7
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_user_paid_for_content' ) ) :
	function bonipress_user_paid_for_content( $user_id = NULL, $post_id = NULL, $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		global $wpdb, $bonipress_log_table;

		$has_paid = false;
		$user_id  = absint( $user_id );
		$post_id  = absint( $post_id );
		$account  = bonipress_get_account( $user_id );
		$expires  = bonipress_sell_content_get_expiration_length( $post_id, $point_type );

		// No expirations
		if ( $expires == 0 ) {

			// The history object should have a record of our payment for a quick check without the need to run the below db query
			if ( ! empty( $account->point_types ) && in_array( $point_type, $account->point_types ) && isset( $account->balance[ $point_type ]->history ) ) {

				$data = $account->balance[ $point_type ]->history->get( 'data' );
				if ( array_key_exists( 'buy_content', $data ) && ! empty( $data['buy_content']->reference_ids ) && in_array( $post_id, $data['buy_content']->reference_ids ) )
					$has_paid = true;

			}

		}

		$last_payment = '';

		if ( ! $has_paid ) {

			$last_payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bonipress_log_table} WHERE user_id = %d AND ref = 'buy_content' AND ref_id = %d ORDER BY time DESC LIMIT 1;", $user_id, $post_id ) );

			// Found a payment
			if ( $last_payment !== NULL ) {

				$has_paid = true;

				// Check for expirations
				if ( bonipress_content_purchase_has_expired( $last_payment ) )
					$has_paid = false;

			}

		}

		// All else there are no purchases
		return apply_filters( 'bonipress_user_has_paid_for_content', $has_paid, $user_id, $post_id, $last_payment );

	}
endif;

/**
 * Sale Expired
 * Checks if a given purchase has expired. Left this in place from the old version
 * for backwards comp.
 * @since 1.7
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_content_purchase_has_expired' ) ) :
	function bonipress_content_purchase_has_expired( $payment = NULL ) {

		$has_expired = false;
		if ( ! is_object( $payment ) ) return $has_expired;

		$length      = bonipress_sell_content_get_expiration_length( $payment->ref_id, $payment->ctype );

		// If expiration is set
		if ( $length > 0 ) {

			$expiration = apply_filters( 'bonipress_sell_expire_calc', absint( $length * HOUR_IN_SECONDS ), $length, $payment->user_id, $payment->ref_id );
			$expiration = $expiration + $payment->time;

			if ( $expiration <= current_time( 'timestamp' ) )
				$has_expired = true;

		}

		return apply_filters( 'bonipress_sell_content_sale_expired', $has_expired, $payment->user_id, $payment->ref_id, $payment->time, $length );

	}
endif;

/**
 * Get Expiration Length
 * @since 1.7.9.1
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_sell_content_get_expiration_length' ) ) :
	function bonipress_sell_content_get_expiration_length( $post_id = NULL, $type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		$length      = 0;
		if ( $post_id === NULL ) return $length;

		$settings    = bonipress_sell_content_settings();
		$post        = bonipress_get_post( $post_id );
		$point_types = $settings['type'];
		$has_expired = false;

		// Invalid post
		if ( ! isset( $post->ID ) ) return $length;

		$filter = $settings['filters'][ $post->post_type ]['by'];

		// Manual mode - expiration settings are found in the post setting
		if ( $filter === 'manual' ) {

			$suffix = '_' . $type;
			if ( $type == BONIPRESS_DEFAULT_TYPE_KEY )
				$suffix = '';

			$sale_setup = (array) bonipress_get_post_meta( $post->ID, 'boniPRESS_sell_content' . $suffix, true );
			if ( ! empty( $sale_setup ) && array_key_exists( 'expire', $sale_setup ) && $sale_setup['expire'] > 0 )
				$length = $sale_setup['expire'];

		}

		// Else we need to check the point type setup in our add-on settings.
		else {

			$point_type_setup = (array) bonipress_get_option( 'bonipress_sell_this_' . $type );
			if ( ! empty( $point_type_setup ) && array_key_exists( 'expire', $point_type_setup ) && $point_type_setup['expire'] > 0 )
				$length = $point_type_setup['expire'];

		}

		return apply_filters( 'bonipress_sell_content_expiration', $length, $post );

	}
endif;

/**
 * Get Payment Buttons
 * Returns all payment buttons a user can use to pay for a given post.
 * @since 1.7
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_sell_content_payment_buttons' ) ) :
	function bonipress_sell_content_payment_buttons( $user_id = NULL, $post_id = NULL ) {

		if ( $user_id === NULL || $post_id === NULL ) return false;

		$settings    = bonipress_sell_content_settings();
		$post        = bonipress_get_post( $post_id );
		$result      = false;

		if ( ! empty( $settings['type'] ) ) {

			$buttons = array();

			foreach ( $settings['type'] as $point_type ) {

				// Load point type
				$bonipress       = bonipress( $point_type );
				$setup        = bonipress_get_option( 'bonipress_sell_this_' . $point_type );
				$price        = bonipress_get_content_price( $post_id, $point_type, $user_id );
				$status       = $setup['status'];

				// Manual mode
				if ( $settings['filters'][ $post->post_type ]['by'] == 'manual' ) {

					$suffix       = ( $point_type != BONIPRESS_DEFAULT_TYPE_KEY ) ? '_' . $point_type : '';
					$manual_setup = (array) bonipress_get_post_meta( $post_id, 'boniPRESS_sell_content' . $suffix, true );
					if ( ! empty( $manual_setup ) && array_key_exists( 'status', $manual_setup ) )
						$status = $manual_setup['status'];

				}

				// Point type not enabled
				if ( $status == 'disabled' ) continue;

				// Make sure we are not excluded from this type
				if ( $bonipress->exclude_user( $user_id ) ) continue;

				// Make sure we can afford to pay
				if ( $bonipress->get_users_balance( $user_id, $point_type ) < $price ) continue;

				$button_label = str_replace( '%price%', $bonipress->format_creds( $price ), $setup['button_label'] );

				$button       = '<button type="button" class="bonipress-buy-this-content-button ' . $setup['button_classes'] . '" data-pid="' . $post_id . '" data-type="' . $point_type . '">' . $button_label . '</button>';
				$buttons[]    = apply_filters( 'bonipress_sell_this_button', $button, $post, $setup, $bonipress );

			}

			if ( ! empty( $buttons ) )
				$result = implode( ' ', $buttons );

		}

		// Return a string of buttons or false if user can not afford
		return apply_filters( 'bonipress_sellcontent_buttons', $result, $user_id, $post_id );

	}
endif;

/**
 * Sell Content Template
 * Parses a particular template.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_sell_content_template' ) ) :
	function bonipress_sell_content_template( $template = '', $post = NULL, $type = 'bonipress-sell-partial-content', $status = 'visitor' ) {

		if ( ! is_object( $post ) || strlen( $template ) === 0 ) return $template;

		$post_type         = get_post_type_object( $post->post_type );
		$url               = bonipress_get_permalink( $post->ID );

		// Remove old tags that are no longer supported
		$template          = str_replace( array( '%price%', '%expires%', ), '', $template );

		$template          = str_replace( '%post_title%',      bonipress_get_the_title( $post->ID ), $template );
		$template          = str_replace( '%post_type%',       $post_type->labels->singular_name, $template );
		$template          = str_replace( '%post_url%',        $url, $template );
		$template          = str_replace( '%link_with_title%', '<a href="' . $url . '">' . $post->post_title . '</a>', $template );

		$template          = apply_filters( 'bonipress_sell_content_template', $template, $post, $type );
		$template          = do_shortcode( $template );

		$wrapper_classes   = array();
		$wrapper_classes[] = 'bonipress-sell-this-wrapper';
		$wrapper_classes[] = esc_attr( $type );
		$wrapper_classes[] = esc_attr( $status );

		$wrapper_classes   = apply_filters( 'bonipress_sell_template_class', $wrapper_classes, $post );

		return '<div id="bonipress-buy-content' . $post->ID . '" class="' . implode( ' ', $wrapper_classes ) . '" data-pid="' . $post->ID . '">' . $template . '</div>';

	}
endif;

/**
 * New Purchase
 * Handles the purchase of a particular post by a given user.
 * @returns true (bool) on success or an error message (string)
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_sell_content_new_purchase' ) ) :
	function bonipress_sell_content_new_purchase( $post = NULL, $user_id = NULL, $point_type = NULL ) {

		if ( ! is_object( $post ) )
			$post = bonipress_get_post( $post );

		if ( ! isset( $post->ID ) ) return false;

		$bonipress   = bonipress( $point_type );
		$settings = bonipress_sell_content_settings();
		$setup    = bonipress_get_option( 'bonipress_sell_this_' . $point_type );
		$result   = apply_filters( 'bonipress_before_content_purchase', false, $post->ID, $user_id, $point_type );

		// We handle payment
		if ( $result === false ) {

			// Disabled point type or user is excluded.
			if ( $setup['status'] === 'disabled' || $bonipress->exclude_user( $user_id ) )
				$result = sprintf( _x( 'You can not pay using %s', 'Point type name', 'bonipress' ), $bonipress->plural() );

			else {

				$balance = $bonipress->get_users_balance( $user_id, $point_type );
				$price   = bonipress_get_content_price( $post->ID, $point_type, $user_id );

				// Insufficient funds (not free)
				if ( $price > 0 && $balance < $price )
					$result = __( 'Insufficient funds.', 'bonipress' );

				// Content is not free
				elseif ( $price > 0 ) {

					// Need a unqiue transaction id
					$transaction_id = 'TXID' . $user_id . current_time( 'timestamp' ) . $post->ID;

					// Charge buyer
					$bonipress->add_creds(
						'buy_content',
						$user_id,
						0 - $price,
						$setup['log_payment'],
						$post->ID,
						array(
							'ref_type'    => 'post',
							'purchase_id' => $transaction_id,
							'seller'      => $post->post_author
						),
						$point_type
					);

					// Profit Sharing
					if ( $setup['profit_share'] > 0 ) {

						// Let others play with the users profit share
						$percentage = bonipress_get_authors_profit_share( $post->post_author, $point_type, $setup['profit_share'] );
						if ( $percentage !== false ) {

							// Convert percentage to a share amount
							$share = ( $percentage / 100 ) * $price;

							// Pay the author
							$bonipress->add_creds(
								'sell_content',
								$post->post_author,
								$share,
								$setup['log_sale'],
								$post->ID,
								array(
									'ref_type'    => 'post',
									'purchase_id' => $transaction_id,
									'buyer'       => $user_id
								),
								$point_type
							);

						}

					}

					$result = true;

					// Delete counters to trigger new db query
					bonipress_delete_post_meta( $post->ID, '_bonipress_content_sales' );
					bonipress_delete_post_meta( $post->ID, '_bonipress_content_buyers' );

				}

				// Free
				else {
					$result = true;
				}

			}

		}

		return apply_filters( 'bonipress_after_content_purchase', $result, $post, $user_id, $point_type );

	}
endif;

/**
 * Get Content Price
 * Returns the contents price.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_get_content_price' ) ) :
	function bonipress_get_content_price( $post_id = NULL, $point_type = NULL, $user_id = NULL ) {

		$bonipress    = bonipress( $point_type );
		$settings  = bonipress_sell_content_settings();

		$setup     = bonipress_get_option( 'bonipress_sell_this_' . $point_type );
		$price     = $bonipress->number( $setup['price'] );
		$post_type = bonipress_get_post_type( $post_id );

		if ( array_key_exists( $post_type, $settings['filters'] ) && $settings['filters'][ $post_type ]['by'] === 'manual' ) {

			$suffix = '_' . $point_type;
			if ( $point_type == BONIPRESS_DEFAULT_TYPE_KEY )
				$suffix = '';

			$sale_setup = (array) bonipress_get_post_meta( $post_id, 'boniPRESS_sell_content' . $suffix, true );
			if ( array_key_exists( 'price', $sale_setup ) )
				$price = $bonipress->number( $sale_setup['price'] );

		}

		return apply_filters( 'bonipress_get_content_price', $price, $post_id, $point_type, $user_id );

	}
endif;

/**
 * Get Users Purchased Content
 * Returns an array of log entries for content purchases.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_get_users_purchased_content' ) ) :
	function bonipress_get_users_purchased_content( $user_id = NULL, $number = 25, $order = 'DESC', $point_type = NULL ) {

		global $wpdb, $bonipress_log_table;

		$limit = '';
		if ( absint( $number ) > 0 )
			$limit = $wpdb->prepare( "LIMIT 0,%d", $number );

		$wheres   = array();
		$wheres[] = "ref = 'buy_content'";
		$wheres[] = $wpdb->prepare( "user_id = %d", $user_id );

		if ( $point_type !== NULL && bonipress_point_type_exists( $point_type ) )
			$wheres[] = $wpdb->prepare( "ctype = %s", $point_type );

		$wheres = 'WHERE ' . implode( ' AND ', $wheres );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		$sql = apply_filters( 'bonipress_get_users_purchased_content', "SELECT * FROM {$bonipress_log_table} log INNER JOIN {$wpdb->posts} posts ON ( log.ref_id = posts.ID ) {$wheres} ORDER BY time {$order} {$limit};", $user_id, $number, $order, $point_type );

		return $wpdb->get_results( $sql );

	}
endif;

/**
 * Get Posts Buyers
 * Returns an array of User IDs of the user that has purchased
 * a given post.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_posts_buyers' ) ) :
	function bonipress_get_posts_buyers( $post_id = NULL, $number = 25, $point_type = NULL ) {

		global $wpdb, $bonipress_log_table;

		$limit = '';
		if ( absint( $number ) > 0 )
			$limit = $wpdb->prepare( "LIMIT 0,%d", $number );

		$wheres   = array();
		$wheres[] = "ref = 'buy_content'";
		$wheres[] = $wpdb->prepare( "ref_id = %d", $post_id );

		if ( $point_type !== NULL && bonipress_point_type_exists( $point_type ) )
			$wheres[] = $wpdb->prepare( "ctype = %s", $point_type );

		$wheres = 'WHERE ' . implode( ' AND ', $wheres );

		return $wpdb->get_col( "SELECT user_id FROM {$bonipress_log_table} {$wheres} ORDER BY time DESC {$limit};" );

	}
endif;

/**
 * Get Content Sales Count
 * Returns the number of times a content has been purchased.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_content_sales_count' ) ) :
	function bonipress_get_content_sales_count( $post_id = NULL ) {

		$count = bonipress_get_post_meta( $post_id, '_bonipress_content_sales', true );
		if ( strlen( $count ) == 0 ) {

			$count = bonipress_count_ref_id_instances( 'buy_content', $post_id );
			bonipress_add_post_meta( $post_id, '_bonipress_content_sales', $count, true );

		}

		return apply_filters( 'bonipress_content_sales_count', $count, $post_id );

	}
endif;

/**
 * Get Content Buyer Count
 * Returns the number of buyers a particular content has.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_content_buyers_count' ) ) :
	function bonipress_get_content_buyers_count( $post_id = NULL ) {

		$count = bonipress_get_post_meta( $post_id, '_bonipress_content_buyers', true );
		if ( strlen( $count ) == 0 ) {

			global $wpdb, $bonipress_log_table;

			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT user_id ) FROM {$bonipress_log_table} WHERE ref = 'buy_content' AND ref_id = %d;", $post_id ) );
			if ( $count === NULL ) $count = 0;

			bonipress_add_post_meta( $post_id, '_bonipress_content_buyers', $count, true );

		}

		return apply_filters( '_bonipress_content_buyers', $count, $post_id );

	}
endif;

/**
 * Get Authors Profit Share
 * Get a particular users profit share percentage. If not set, the general setup for the given
 * point type is returned instead.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_authors_profit_share' ) ) :
	function bonipress_get_authors_profit_share( $user_id = NULL, $point_type = NULL, $default = false ) {

		$users_share = bonipress_get_user_meta( $user_id, 'bonipress_sell_content_share_' . $point_type, '', true );
		if ( strlen( $users_share ) == 0 )
			$users_share = $default;

		return apply_filters( 'bonipress_sell_content_profit_share', $users_share, $user_id, $point_type, $default );

	}
endif;

/**
 * Get Post Type Options
 * Returns an array of filter options for post types.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_post_type_options' ) ) :
	function bonipress_get_post_type_options( $post_type ) {

		$type = get_post_type_object( $post_type );
		if ( ! is_object( $type ) ) return;

		$options = array();
		$options['all'] = array(
			'label' => sprintf( _x( 'All %s', 'all post type name', 'bonipress' ), $type->labels->name ),
			'data'  => ''
		);
		$options['manual'] = array(
			'label' => sprintf( _x( '%s I manually select', 'all post type name', 'bonipress' ), $type->labels->name ),
			'data'  => ''
		);
		$options['exclude'] = array(
			'label' => sprintf( _x( 'All %s except', '%s = post type name', 'bonipress' ), $type->labels->name ),
			'data'  => sprintf( _x( 'Comma separated list of %s IDs to exclude', '%s = post type name', 'bonipress' ), $type->labels->singular_name )
		);
		$options['include'] = array(
			'label' => sprintf( _x( 'Only %s', '%s = post type name', 'bonipress' ), $type->labels->name ),
			'data'  => sprintf( _x( 'Comma separated list of %s IDs', '%s = post type name', 'bonipress' ), $type->labels->singular_name )
		);

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		if ( ! empty( $taxonomies ) ) {

			foreach ( $taxonomies as $taxonomy_id => $term ) {

				if ( ! $term->public ) continue;

				if ( $term->hierarchical ) {
					$options[ $taxonomy_id ] = array(
						'label' => sprintf( _x( 'Only %s in %s', 'e.g. Only "Posts" in "Categories"', 'bonipress' ), $type->labels->name, $term->labels->name ),
						'data'  => sprintf( _x( 'Comma separated list of %s slugs', '%s = taxonomy name', 'bonipress' ), $term->labels->singular_name )
					);
					$options[ '_' . $taxonomy_id ] = array(
						'label' => sprintf( _x( 'Only %s not in %s', 'e.g. Only "Posts" not in "Categories"', 'bonipress' ), $type->labels->name, $term->labels->name ),
						'data'  => sprintf( _x( 'Comma separated list of %s slugs', '%s = taxonomy name', 'bonipress' ), $term->labels->singular_name )
					);
				}
				else {
					$options[ $taxonomy_id ] = array(
						'label' => sprintf( _x( 'Only %s with %s', 'e.g. Only "Posts" with "Tags"', 'bonipress' ), $type->labels->name, $term->labels->name ),
						'data'  => sprintf( _x( 'Comma separated list of %s slugs', '%s = taxonomy name', 'bonipress' ), $term->labels->singular_name )
					);
					$options[ '_' . $taxonomy_id ] = array(
						'label' => sprintf( _x( 'Only %s without %s', 'e.g. Only "Posts" without "Tags"', 'bonipress' ), $type->labels->name, $term->labels->name ),
						'data'  => sprintf( _x( 'Comma separated list of %s slugs', '%s = taxonomy name', 'bonipress' ), $term->labels->singular_name )
					);
				}

			}

		}

		return apply_filters( 'bonipress_sell_post_type_options', $options, $post_type );

	}
endif;
