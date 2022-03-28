<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Get The Transfer Object
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonips_transfer' ) ) :
	function bonips_transfer( $transfer_id = false ) {

		global $bonips_transfer;

		$transfer_id     = sanitize_text_field( $transfer_id );

		if ( isset( $bonips_transfer )
			&& ( $bonips_transfer instanceof boniPS_Transfer )
			&& ( $transfer_id === $bonips_transfer->transfer_id )
		) {
			return $bonips_transfer;
		}

		$bonips_transfer = new boniPS_Transfer( $transfer_id );

		do_action( 'bonips_transfer' );

		return $bonips_transfer;

	}
endif;

/**
 * Get Transfer
 * @param $transfer_id (string) required transfer id to retreave.
 * @returns boniPS_Transfer object on success else false.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_transfer' ) ) :
	function bonips_get_transfer( $transfer_id = false ) {

		if ( $transfer === false ) return false;

		$transfer    = bonips_transfer( $transfer_id );
		$transaction = $transfer->get_transfer();

		// Transaction not found
		if ( $transaction === false ) return false;

		// Populate object
		foreach ( $transaction as $key => $value )
			$transfer->$key = $value;

		return $transfer;

	}
endif;

/**
 * New Transfer
 * @param $request (array) the required transfer request array.
 * @param $post (array) optional posted data from the transfer form.
 * @returns error code if transfer failed else an array or transfer details.
 * @since 1.7.6
 * @version 1.1
 */
if ( ! function_exists( 'bonips_new_transfer' ) ) :
	function bonips_new_transfer( $request = array(), $post = array() ) {

		$transfer       = bonips_transfer();

		// Validate the request first
		$valid_transfer = $transfer->is_valid_transfer_request( $request, $post );
		if ( $valid_transfer !== true )
			return $valid_transfer;

		// Attempt to make the transfer
		return $transfer->new_transfer();

	}
endif;

/**
 * Refund Transfer
 * @param $transfer_id (string) required transfer id to refund.
 * @returns error message (string) or true on success.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonips_refund_transfer' ) ) :
	function bonips_refund_transfer( $transfer_id = false ) {

		if ( $transfer === false ) return false;

		$transfer    = bonips_transfer( $transfer_id );
		$transaction = $transfer->get_transfer();

		// Transaction could not be found
		if ( $transaction === false ) return false;

		// Populate object
		foreach ( $transaction as $key => $value )
			$transfer->$key = $value;

		return $transfer->refund();

	}
endif;

/**
 * Get Transfer Limits
 * @param $settings (array) optional transfer settings.
 * @returns array of limits.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_transfer_limits' ) ) :
	function bonips_get_transfer_limits( $settings = false ) {

		if ( $settings === false )
			$settings = bonips_get_addon_settings( 'transfers' );

		$limits = array(
			'none'    => __( 'Keine Limits.', 'bonips' ),
			'daily'   => __( 'Tägliches Limit auferlegen.', 'bonips' ),
			'weekly'  => __( 'Wöchentliches Limit auferlegen.', 'bonips' ),
			'monthly' => __( 'Monatliches Limit auferlegen.', 'bonips' )
		);

		return apply_filters( 'bonips_transfer_limits', $limits, $settings );

	}
endif;

/**
 * Get Transfer Limits
 * @param $settings (array) optional transfer settings.
 * @returns array of autofill options.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_transfer_autofill_by' ) ) :
	function bonips_get_transfer_autofill_by( $settings = false ) {

		if ( $settings === false )
			$settings = bonips_get_addon_settings( 'transfers' );

		$autofills = array(
			'user_login' => __( 'Benutzerlogin (user_login)', 'bonips' ),
			'user_email' => __( 'Benutzer Email (user_email)', 'bonips' )
		);

		return apply_filters( 'bonips_transfer_autofill_by', $autofills, $settings );

	}
endif;

/**
 * Get Transfer Recipient
 * @param $value (int|string) a value that identifies a particular user in WordPress.
 * @returns false if no recipient was found else the users id (int).
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_transfer_recipient' ) ) :
	function bonips_get_transfer_recipient( $value = '' ) {

		$settings     = bonips_get_addon_settings( 'transfers' );
		$recipient_id = false;

		if ( ! empty( $value ) ) {

			// A numeric ID has been provided that we need to validate
			if ( is_numeric( $value ) ) {

				$user = get_userdata( $value );
				if ( isset( $user->ID ) )
					$recipient_id = $user->ID;

			}

			// A username has been provided
			elseif ( $settings['autofill'] == 'user_login' ) {

				$user = get_user_by( 'login', $value );
				if ( isset( $user->ID ) )
					$recipient_id = $user->ID;

			}

			// An email address has been provided
			elseif ( $settings['autofill'] == 'user_email' ) {

				$user = get_user_by( 'email', $value );
				if ( isset( $user->ID ) )
					$recipient_id = $user->ID;

			}

		}

		return apply_filters( 'bonips_transfer_get_recipient', $recipient_id, $value, $settings );

	}
endif;

/**
 * User Can Transfer
 * @param $user_id (int) requred user id
 * @param $amount (int) optional amount to check against balance
 * @returns true if no limit is set, 'limit' (string) if user is over limit else the amount of creds left
 * @since 0.1
 * @version 1.4.1
 */
if ( ! function_exists( 'bonips_user_can_transfer' ) ) :
	function bonips_user_can_transfer( $user_id = NULL, $amount = NULL, $type = BONIPS_DEFAULT_TYPE_KEY, $reference = NULL ) {

		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		if ( $reference === NULL )
			$reference = 'transfer';

		if ( ! bonips_point_type_exists( $type ) )
			$type = BONIPS_DEFAULT_TYPE_KEY;

		// Grab Settings
		$settings = bonips_get_addon_settings( 'transfers' );
		$bonips   = bonips( $type );
		$zero     = $bonips->zero();

		// Get users balance
		$balance  = $bonips->get_users_balance( $user_id, $type );

		// Get Transfer Max
		$max      = apply_filters( 'bonips_transfer_limit', $bonips->number( $settings['limit']['amount'] ), $user_id, $amount, $settings, $reference );

		// If an amount is given, deduct this amount to see if the transaction
		// brings us over the account limit
		if ( $amount !== NULL )
			$balance = $bonips->number( $balance - $amount );

		// Zero
		// The lowest amount a user can have on their account. By default, this
		// is zero. But you can override this via the bonips_transfer_acc_limit hook.
		$account_limit = $bonips->number( apply_filters( 'bonips_transfer_acc_limit', $zero, $type, $user_id, $reference ) );

		// Check if we would go minus
		if ( $balance < $account_limit ) return 'low';

		// If there are no limits, return the current balance
		if ( $settings['limit']['limit'] == 'none' ) return $balance;

		// Else we have a limit to impose
		$now = current_time( 'timestamp' );
		$max = $bonips->number( $settings['limit']['amount'] );

		// Daily limit
		if ( $settings['limit']['limit'] == 'daily' )
			$total = bonips_get_total_by_time( 'today', 'now', $reference, $user_id, $type );

		// Weekly limit
		elseif ( $settings['limit']['limit'] == 'weekly' ) {
			$this_week = mktime( 0, 0, 0, date( 'n', $now ), date( 'j', $now ) - date( 'n', $now ) + 1 );
			$total     = bonips_get_total_by_time( $this_week, 'now', $reference, $user_id, $type );
		}

		// Custom limits will need to return the result
		// here and now. Accepted answers are 'limit', 'low' or the amount left on limit.
		else {
			return apply_filters( 'bonips_user_can_transfer', 'limit', $user_id, $amount, $settings, $reference );
		}

		// We are adding up point deducations.
		$total = abs( $total );

		if ( $amount !== NULL ) {

			$total = $bonips->number( $total + $amount );

			// Transfer limit reached
			if ( $total > $max ) return 'limit';

		}

		else {

			// Transfer limit reached
			if ( $total >= $max ) return 'limit';

		}

		// Return whats remaining of limit
		return $bonips->number( $max - $total );

	}
endif;

/**
 * Render Transfer Message
 * @since 1.7.6
 * @version 1.0
 */
if ( ! function_exists( 'bonips_transfer_render_message' ) ) :
	function bonips_transfer_render_message( $original = '', $data = array() ) {

		if ( empty( $original ) || empty( $data ) ) return $original;

		// Default message
		$message = apply_filters( 'bonips_transfer_default_message', $original, $data );

		// Get saved message
		if ( ! empty( $data ) && array_key_exists( 'message', $data ) && ! empty( $data['message'] ) )
			$message = $data['message'];

		$content = str_replace( '%transfer_message%', $message, $original );

		return apply_filters( 'bonips_transfer_message', $content, $original, $message, $data );

	}
endif;

/**
 * Get Users Transfer Verlauf
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_users_transfer_history' ) ) :
	function bonips_get_users_transfer_history( $user_id, $type = BONIPS_DEFAULT_TYPE_KEY, $key = NULL ) {

		if ( $key === NULL )
			$key = 'bonips_transactions';

		if ( $type != BONIPS_DEFAULT_TYPE_KEY && $type != '' )
			$key .= '_' . $type;

		$default = array(
			'frame'  => '',
			'amount' => 0
		);
		return bonips_apply_defaults( $default, bonips_get_user_meta( $user_id, $key, '', true ) );

	}
endif;

/**
 * Update Users Transfer Verlauf
 * @since 1.3.3
 * @version 1.0
 */
if ( ! function_exists( 'bonips_update_users_transfer_history' ) ) :
	function bonips_update_users_transfer_history( $user_id, $history, $type = BONIPS_DEFAULT_TYPE_KEY, $key = NULL ) {

		if ( $key === NULL )
			$key = 'bonips_transactions';

		if ( $type != BONIPS_DEFAULT_TYPE_KEY && $type != '' )
			$key .= '_' . $type;

		// Get current history
		$current = bonips_get_users_transfer_history( $user_id, $type, $key );

		// Reset
		if ( $history === true )
			$new_history = array(
				'frame'  => '',
				'amount' => 0
			);

		// Update
		else $new_history = bonips_apply_defaults( $current, $history );

		bonips_update_user_meta( $user_id, $key, '', $new_history );

	}
endif;
