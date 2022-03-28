<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Get Coupon
 * Returns a coupon object based on the post ID.
 * @since 1.7
 * @version 1.0.1
 */
if ( ! function_exists( 'bonips_get_coupon' ) ) :
	function bonips_get_coupon( $coupon_id = NULL ) {

		if ( $coupon_id === NULL ) return false;

		global $bonips_coupon;

		if ( isset( $bonips_coupon )
			&& ( $bonips_coupon instanceof boniPS_Coupon )
			&& ( $coupon_id === $bonips_coupon->post_id || $coupon_id === $bonips_coupon->code )
		) {
			return $bonips_coupon;
		}

		$bonips_coupon = new boniPS_Coupon( $coupon_id );

		do_action( 'bonips_get_coupon' );

		return $bonips_coupon;

	}
endif;

/**
 * Get Coupon Value
 * @filter bonips_coupon_value
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_coupon_value' ) ) :
	function bonips_get_coupon_value( $post_id = 0 ) {

		return apply_filters( 'bonips_coupon_value', bonips_get_post_meta( $post_id, 'value', true ), $post_id );

	}
endif;

/**
 * Get Coupon Expire Date
 * @filter bonips_coupon_max_balance
 * @since 1.4
 * @version 1.0.1
 */
if ( ! function_exists( 'bonips_get_coupon_expire_date' ) ) :
	function bonips_get_coupon_expire_date( $post_id = 0, $unix = false ) {

		$expires = bonips_get_post_meta( $post_id, 'expires', true );

		if ( ! empty( $expires ) && $unix )
			$expires = ( strtotime( $expires . ' midnight' ) + ( DAY_IN_SECONDS - 1 ) );

		if ( empty( $expires ) ) $expires = false;

		return apply_filters( 'bonips_coupon_expires', $expires, $post_id, $unix );

	}
endif;

/**
 * Get Coupon User Max
 * The maximum number a user can use this coupon.
 * @filter bonips_coupon_user_max
 * @since 1.4
 * @version 1.0.1
 */
if ( ! function_exists( 'bonips_get_coupon_user_max' ) ) :
	function bonips_get_coupon_user_max( $post_id = 0 ) {

		return (int) apply_filters( 'bonips_coupon_user_max', bonips_get_post_meta( $post_id, 'user', true ), $post_id );

	}
endif;

/**
 * Get Coupons Global Max
 * @filter bonips_coupon_global_max
 * @since 1.4
 * @version 1.0.1
 */
if ( ! function_exists( 'bonips_get_coupon_global_max' ) ) :
	function bonips_get_coupon_global_max( $post_id = 0 ) {

		return (int) apply_filters( 'bonips_coupon_global_max', bonips_get_post_meta( $post_id, 'global', true ), $post_id );

	}
endif;

/**
 * Create New Coupon
 * Creates a new boniPS coupon post.
 * @filter bonips_create_new_coupon_post
 * @filter bonips_create_new_coupon
 * @returns false if data is missing, post ID on success or wp_error / 0 if 
 * post creation failed.
 * @since 1.4
 * @version 1.1.1
 */
if ( ! function_exists( 'bonips_create_new_coupon' ) ) :
	function bonips_create_new_coupon( $data = array() ) {

		// Required data is missing
		if ( empty( $data ) ) return false;

		// Apply defaults
		extract( shortcode_atts( array(
			'code'             => bonips_get_unique_coupon_code(),
			'value'            => 0,
			'global_max'       => 1,
			'user_max'         => 1,
			'min_balance'      => 0,
			'min_balance_type' => BONIPS_DEFAULT_TYPE_KEY,
			'max_balance'      => 0,
			'max_balance_type' => BONIPS_DEFAULT_TYPE_KEY,
			'expires'          => '',
			'type'             => BONIPS_DEFAULT_TYPE_KEY
		), $data ) );

		// Create Coupon Post
		$post_id = wp_insert_post( apply_filters( 'bonips_create_new_coupon_post', array(
			'post_type'      => BONIPS_COUPON_KEY,
			'post_title'     => $code,
			'post_status'    => 'publish',
			'comment_status' => 'closed',
			'ping_status'    => 'closed'
		), $data ) );

		// Error
		if ( $post_id !== 0 && ! is_wp_error( $post_id ) ) {

			// Save Coupon Details
			bonips_add_post_meta( $post_id, 'type',             $type, true );
			bonips_add_post_meta( $post_id, 'value',            $value, true );
			bonips_add_post_meta( $post_id, 'global',           $global_max, true );
			bonips_add_post_meta( $post_id, 'user',             $user_max, true );
			bonips_add_post_meta( $post_id, 'min_balance',      $min_balance, true );
			bonips_add_post_meta( $post_id, 'min_balance_type', $min_balance_type, true );
			bonips_add_post_meta( $post_id, 'max_balance',      $max_balance, true );
			bonips_add_post_meta( $post_id, 'max_balance_type', $max_balance_type, true );

			if ( ! empty( $expires ) )
				bonips_add_post_meta( $post_id, 'expires', $expires );

		}

		return apply_filters( 'bonips_create_new_coupon', $post_id, $data );

	}
endif;

/**
 * Get Unique Coupon Code
 * Generates a unique 12 character alphanumeric coupon code.
 * @filter bonips_get_unique_coupon_code
 * @since 1.4
 * @version 1.0.2
 */
if ( ! function_exists( 'bonips_get_unique_coupon_code' ) ) :
	function bonips_get_unique_coupon_code() {

		global $wpdb;

		$table = bonips_get_db_column( 'posts' );

		do {

			$id    = strtoupper( wp_generate_password( 12, false, false ) );
			$query = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_title = %s AND post_type = %s;", $id, BONIPS_COUPON_KEY ) );

		} while ( ! empty( $query ) );

		return apply_filters( 'bonips_get_unique_coupon_code', $id );

	}
endif;

/**
 * Get Coupon Post
 * @filter bonips_get_coupon_by_code
 * @since 1.4
 * @version 1.0.1
 */
if ( ! function_exists( 'bonips_get_coupon_post' ) ) :
	function bonips_get_coupon_post( $code = '' ) {

		if ( $code == '' ) return false;

		return apply_filters( 'bonips_get_coupon_by_code', bonips_get_page_by_title( strtoupper( $code ), 'OBJECT', BONIPS_COUPON_KEY ), $code );

	}
endif;

/**
 * Use Coupon
 * Will attempt to use a given coupon and award it's value
 * to a given user. Requires you to provide a log entry template.
 * @action bonips_use_coupon
 * @since 1.4
 * @version 1.2
 */
if ( ! function_exists( 'bonips_use_coupon' ) ) :
	function bonips_use_coupon( $code = '', $user_id = 0 ) {

		// Missing required information
		if ( empty( $code ) || $user_id === 0 ) return 'invalid';

		$coupon  = bonips_get_coupon( $code );

		// Coupon does not exist
		if ( $coupon === false ) return 'invalid';

		return $coupon->use_coupon( $user_id );

	}
endif;

/**
 * Was Coupon Successfully Used?
 * Checks to see if bonips_use_coupon() successfully paid out or if
 * we ran into issues.
 * @since 1.7.5
 * @version 1.0
 */
if ( ! function_exists( 'bonips_coupon_was_successfully_used' ) ) :
	function bonips_coupon_was_successfully_used( $code = '' ) {

		$results     = true;
		$error_codes = apply_filters( 'bonips_coupon_error_codes', array( 'invalid', 'expired', 'user_limit', 'min', 'max', 'excluded' ) );

		if ( $code === false || in_array( $code, $error_codes ) )
			$results = false;

		return $results;

	}
endif;

/**
 * Coupon Error Message
 * Translates a coupon error code into a readable message.
 * we ran into issues.
 * @since 1.7.5
 * @version 1.1
 */
if ( ! function_exists( 'bonips_get_coupon_error_message' ) ) :
	function bonips_get_coupon_error_message( $code = '', $coupon = NULL ) {

		$message  = __( 'Ein unbekannter Fehler ist aufgetreten. Gutschein nicht verwendet.', 'bonips' );
		$settings = bonips_get_addon_settings( 'coupons' );

		if ( array_key_exists( $code, $settings ) )
			$message = $settings[ $code ];

		if ( $code == 'min' && is_object( $coupon ) ) {

			$bonips  = bonips( $coupon->requires_min_type );
			$message = str_replace( array( '%min%', '%amount%' ), $bonips->format_creds( $coupon->requires_min['value'] ), $message );

		}

		elseif ( $code == 'max' && is_object( $coupon ) ) {

			$bonips  = bonips( $coupon->requires_max_type );
			$message = str_replace( array( '%max%', '%amount%' ), $bonips->format_creds( $coupon->requires_max['value'] ), $message );

		}

		return apply_filters( 'bonips_coupon_error_message', $message, $code, $coupon );

	}
endif;

/**
 * Get Users Coupon Count
 * Counts the number of times a user has used a given coupon.
 * @filter bonips_get_users_coupon_count
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_users_coupon_count' ) ) :
	function bonips_get_users_coupon_count( $code = '', $user_id = '' ) {

		global $wpdb, $bonips_log_table;

		// Count how many times a given user has used a given coupon
		$result = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT( * ) 
			FROM {$bonips_log_table} 
			WHERE ref = %s 
				AND user_id = %d
				AND data = %s;", 'coupon', $user_id, $code ) );

		return apply_filters( 'bonips_get_users_coupon_count', $result, $code, $user_id );

	}
endif;

/**
 * Get Coupons Global Count
 * @filter bonips_get_global_coupon_count
 * @since 1.4
 * @version 1.2
 */
if ( ! function_exists( 'bonips_get_global_coupon_count' ) ) :
	function bonips_get_global_coupon_count( $coupon_id = 0 ) {

		$coupon = bonips_get_coupon( $coupon_id );
		if ( $coupon === false ) return 0;

		return $coupon->get_usage_count();

	}
endif;

/**
 * Get Coupons Minimum Balance Requirement
 * @filter bonips_coupon_min_balance
 * @since 1.4
 * @version 1.1.1
 */
if ( ! function_exists( 'bonips_get_coupon_min_balance' ) ) :
	function bonips_get_coupon_min_balance( $post_id = 0 ) {

		$type = bonips_get_post_meta( $post_id, 'min_balance_type', true );
		if ( ! bonips_point_type_exists( $type ) ) $type = BONIPS_DEFAULT_TYPE_KEY;

		$min  = bonips_get_post_meta( $post_id, 'min_balance', true );
		if ( $min == '' ) $min = 0;

		return apply_filters( 'bonips_coupon_min_balance', array(
			'type'  => $type,
			'value' => $min
		), $post_id );

	}
endif;

/**
 * Get Coupons Maximum Balance Requirement
 * @filter bonips_coupon_max_balance
 * @since 1.4
 * @version 1.1.1
 */
if ( ! function_exists( 'bonips_get_coupon_max_balance' ) ) :
	function bonips_get_coupon_max_balance( $post_id = 0 ) {

		$type = bonips_get_post_meta( $post_id, 'max_balance_type', true );
		if ( ! bonips_point_type_exists( $type ) ) $type = BONIPS_DEFAULT_TYPE_KEY;

		$max  = bonips_get_post_meta( $post_id, 'max_balance', true );
		if ( $max == '' ) $max = 0;

		return apply_filters( 'bonips_coupon_max_balance', array(
			'type'  => $type,
			'value' => $max
		), $post_id );

	}
endif;
