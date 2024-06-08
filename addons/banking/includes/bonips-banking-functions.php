<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Get Next Payout
 * Adds seconds to a given time based on the payout period set.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_banking_addon_settings' ) ) :
	function bonips_get_banking_addon_settings( $service = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$default = array(
			'active'        => array(),
			'services'      => array(),
			'service_prefs' => array()
		);

		$option_id = 'bonips_pref_bank';
		if ( $point_type != BONIPS_DEFAULT_TYPE_KEY )
			$option_id .= '_' . $point_type;

		$settings = bonips_get_option( $option_id, $default );
		$settings = wp_parse_args( $settings, $default );

		if ( $service !== NULL && array_key_exists( $service, $settings['service_prefs'] ) )
			$settings = $settings['service_prefs'][ $service ];

		return $settings;

	}
endif;

/**
 * Get Next Payout
 * Adds seconds to a given time based on the payout period set.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_banking_get_next_payout' ) ) :
	function bonips_banking_get_next_payout( $prefs = '', $time = 0 ) {

		if ( $prefs == 'hourly' )
			return $time + HOUR_IN_SECONDS;

		elseif ( $prefs == 'daily' )
			return $time + DAY_IN_SECONDS;

		elseif ( $prefs == 'weekly' )
			return $time + WEEK_IN_SECONDS;

		elseif ( $prefs == 'monthly' )
			return $time + MONTH_IN_SECONDS;

		elseif ( $prefs == 'quarterly' )
			return $time + ( MONTH_IN_SECONDS * 4 );

		elseif ( $prefs == 'semiannually' )
			return $time + ( MONTH_IN_SECONDS * 6 );

		elseif ( $prefs == 'annually' )
			return $time + YEAR_IN_SECONDS;

		return apply_filters( 'bonips_banking_get_next_payout', $time, $prefs );

	}
endif;

/**
 * Get Timeframes
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_banking_get_timeframes' ) ) :
	function bonips_banking_get_timeframes() {

		$timeframes = array(
			'hourly'    => array(
				'label'       => __( 'Stündlich', 'bonips' ),
				'single'      => __( 'Stunde', 'bonips' ),
				'date_format' => 'G'
			),
			'daily'     => array(
				'label'       => __( 'Täglich', 'bonips' ),
				'single'      => __( 'Tag', 'bonips' ),
				'date_format' => 'z'
			),
			'weekly'    => array(
				'label'       => __( 'Wöchentlich', 'bonips' ),
				'single'      => __( 'Woche', 'bonips' ),
				'date_format' => 'W'
			),
			'monthly'   => array(
				'label'       => __( 'Monatlich', 'bonips' ),
				'single'      => __( 'Monat', 'bonips' ),
				'date_format' => 'M'
			),
			'quarterly'  => array(
				'label'       => __( 'Vierteljährlich', 'bonips' ),
				'single'      => __( 'Quartal', 'bonips' ),
				'date_format' => 'Y'
			),
			'semiannually'  => array(
				'label'       => __( 'Halbjährlich', 'bonips' ),
				'single'      => __( 'Halbjährlich', 'bonips' ),
				'date_format' => 'Y'
			),
			'annually'  => array(
				'label'       => __( 'Jährlich', 'bonips' ),
				'single'      => __( 'Jährlich', 'bonips' ),
				'date_format' => 'Y'
			)
		);

		return apply_filters( 'bonips_banking_timeframes', $timeframes );

	}
endif;

/**
 * Get Time Options
 * Returns an array of 24 hours with support for either 24 or 12 hour formats.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_banking_get_time_options' ) ) :
	function bonips_banking_get_time_options( $twelve_hour = false ) {

		$options = array(
			'00:00' => '00:00',
			'01:00' => '01:00',
			'02:00' => '02:00',
			'03:00' => '03:00',
			'04:00' => '04:00',
			'05:00' => '05:00',
			'06:00' => '06:00',
			'07:00' => '07:00',
			'08:00' => '08:00',
			'09:00' => '09:00',
			'10:00' => '10:00',
			'11:00' => '11:00',
			'12:00' => '12:00',
			'13:00' => '13:00',
			'14:00' => '14:00',
			'15:00' => '15:00',
			'16:00' => '16:00',
			'17:00' => '17:00',
			'18:00' => '18:00',
			'19:00' => '19:00',
			'20:00' => '20:00',
			'21:00' => '21:00',
			'22:00' => '22:00',
			'23:00' => '23:00'
		);

		$time_format = get_option( 'time_format' );
		if ( str_replace( array( 'a', 'A' ), '', $time_format ) != $time_format || $twelve_hour )
			$options = array(
				'00:00' => '12:00 AM',
				'01:00' => '1:00 AM',
				'02:00' => '2:00 AM',
				'03:00' => '3:00 AM',
				'04:00' => '4:00 AM',
				'05:00' => '5:00 AM',
				'06:00' => '6:00 AM',
				'07:00' => '7:00 AM',
				'08:00' => '8:00 AM',
				'09:00' => '9:00 AM',
				'10:00' => '10:00 AM',
				'11:00' => '11:00 AM',
				'12:00' => '12:00 PM',
				'13:00' => '1:00 PM',
				'14:00' => '2:00 PM',
				'15:00' => '3:00 PM',
				'16:00' => '4:00 PM',
				'17:00' => '5:00 PM',
				'18:00' => '6:00 PM',
				'19:00' => '7:00 PM',
				'20:00' => '8:00 PM',
				'21:00' => '9:00 PM',
				'22:00' => '10:00 PM',
				'23:00' => '11:00 PM'
			);

		return apply_filters( 'bonips_banking_time_options', $options );

	}
endif;

/**
 * Get Recurring Payout Schedules
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_recurring_payout_schedules' ) ) :
	function bonips_get_recurring_payout_schedules( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$schedules = bonips_get_option( 'bonips-recurring-schedule-' . $point_type, array() );
		$defaults  = bonips_get_recurring_payout_defaults();

		$results = array();
		if ( ! empty( $schedules ) ) {

			foreach ( $schedules as $schedule_id => $setup )
				$results[ $schedule_id ] = shortcode_atts( $defaults, $setup );

		}

		return $results;

	}
endif;

/**
 * Get Recurring Payout Defaults
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_recurring_payout_defaults' ) ) :
	function bonips_get_recurring_payout_defaults() {

		$defaults = array(
			'job_title'       => '',
			'status'          => 0,
			'payout'          => '',
			'frequency'       => 'daily',
			'last_run'        => '',
			'total_runs'      => 1,
			'runs_remaining'  => 0,
			'min_balance'     => 0,
			'max_balance'     => 0,
			'id_exclude'      => 'exclude',
			'id_list'         => '',
			'role_exclude'    => 'exclude',
			'role_list'       => array(),
			'log_template'    => '%plural% payout',
			'total_completed' => 0,
			'total_misses'    => 0,
			'ignore_central'  => 0
		);

		return $defaults;

	}
endif;

/**
 * Get Recurring Payout
 * Returns the settings of a specific recurring payout.
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_get_recurring_payout' ) ) :
	function bonips_get_recurring_payout( $schedule_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$result    = false;
		$schedules = bonips_get_recurring_payout_schedules( $point_type );

		if ( array_key_exists( $schedule_id, $schedules ) )
			$result = $schedules[ $schedule_id ];

		return $result;

	}
endif;

/**
 * Add New Recurring Payout
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_add_new_recurring_payout' ) ) :
	function bonips_add_new_recurring_payout( $new_id = '', $schedule_setup = array(), $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$default        = bonips_get_recurring_payout_defaults();
		$new_id         = sanitize_key( $new_id );
		$schedule_setup = shortcode_atts( $default, $schedule_setup );
		$bonips         = bonips( $point_type );

		// Validate first
		$title = sanitize_text_field( $schedule_setup['job_title'] );
		if ( $title == '' )
			return new WP_Error( 'invalid', __( 'Es muss ein Titel festgelegt werden.', 'bonips' ) );

		$schedule_setup['job_title'] = $title;

		$payout = sanitize_text_field( $schedule_setup['payout'] );
		$payout = $bonips->number( $payout );
		if ( $payout == $bonips->zero() )
			return new WP_Error( 'invalid', __( 'Der Auszahlungsbetrag darf nicht Null sein.', 'bonips' ) );

		$schedule_setup['payout'] = $payout;

		$last_run = absint( $schedule_setup['last_run'] );
		if ( $last_run < time() )
			return new WP_Error( 'invalid', __( 'Das Startdatum darf nicht in der Vergangenheit liegen.', 'bonips' ) );

		$schedule_setup['last_run'] = $last_run;

		$total_runs = sanitize_text_field( $schedule_setup['total_runs'] );
		if ( $total_runs < -1 ) $total_runs = -1;
		if ( $total_runs == 0 )
			return new WP_Error( 'invalid', __( 'Wiederholen kann nicht Null sein.', 'bonips' ) );

		$schedule_setup['total_runs'] = $total_runs;

		$schedules = bonips_get_recurring_payout_schedules( $point_type );
		if ( array_key_exists( $new_id, $schedules ) )
			return new WP_Error( 'invalid', __( 'Zeitplan duplizieren.', 'bonips' ) );

		$schedule_setup['status']         = 0;
		$schedule_setup['runs_remaining'] = $schedule_setup['total_runs'];

		$schedules[ $new_id ]     = $schedule_setup;

		// Save
		bonips_update_option( 'bonips-recurring-schedule-' . $point_type, $schedules );

		// Add Schedule to CRON
		wp_schedule_single_event( $last_run, 'bonips-recurring-' . $new_id, array( 'id' => $new_id ) );

		return $schedule_setup;

	}
endif;

/**
 * Update Recurring Payout
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_update_recurring_payout' ) ) :
	function bonips_update_recurring_payout( $schedule_id = '', $schedule_setup = array(), $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$result = false;
		$setup  = bonips_get_recurring_payout( $schedule_id, $point_type );

		if ( is_array( $setup ) ) {

			$schedules = bonips_get_recurring_payout_schedules( $point_type );

			$schedules[ $schedule_id ] = shortcode_atts( $setup, $schedule_setup );

			bonips_update_option( 'bonips-recurring-schedule-' . $point_type, $schedules );

			$result = true;

		}

		return $result;

	}
endif;

/**
 * Delete Recurring Payout
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_delete_recurring_payout' ) ) :
	function bonips_delete_recurring_payout( $id_to_remove = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$result    = false;
		$schedules = bonips_get_recurring_payout_schedules( $point_type );

		if ( ! empty( $schedules ) ) {

			$new_schedule = array();
			foreach ( $schedules as $schedule_id => $schedule_setup ) {

				if ( $schedule_id == $id_to_remove ) continue;
				$new_schedule[ $schedule_id ] = $schedule_setup;

			}

			// Save
			bonips_update_option( 'bonips-recurring-schedule-' . $point_type, $new_schedule );

			// Clear schedule in CRON
			wp_clear_scheduled_hook( 'bonips-recurring-' . $schedule_id, array( 'id' => $schedule_id ) );

			$result = true;

		}

		return $result;

	}
endif;
