<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Load Referral Program
 * @since 1.5.3
 * @version 1.0
 */
if ( ! function_exists( 'bonips_load_referral_program' ) ) :
	function bonips_load_referral_program() {

		// BuddyPress: Hook into user activation
		if ( function_exists( 'buddypress' ) )
		add_action( 'bp_core_activated_user', 'bonips_detect_bp_user_activation' );

		// Logged in users do not get points
		if ( is_user_logged_in() && apply_filters( 'bonips_affiliate_allow_members', false ) === false ) return;

		// Points for visits
		add_action( 'template_redirect', 'bonips_detect_referred_visits' );

		// Points for signups
		add_action( 'user_register', 'bonips_detect_referred_signups' );

	}
endif;
add_action( 'bonips_init', 'bonips_load_referral_program' );

/**
 * Detect Referred Visits
 * @since 1.5.3
 * @version 1.0.1
 */
if ( ! function_exists( 'bonips_detect_referred_visits' ) ) :
	function bonips_detect_referred_visits() {

		do_action( 'bonips_referred_visit' );

		$keys = apply_filters( 'bonips_referral_keys', array() );
		if ( ! empty( $keys ) ) {
			wp_redirect( remove_query_arg( $keys ), 301 );
			exit;
		}

	}
endif;

/**
 * Detect Referred Signups
 * @since 1.5.3
 * @version 1.0
 */
if ( ! function_exists( 'bonips_detect_referred_signups' ) ) :
	function bonips_detect_referred_signups( $new_user_id ) {

		do_action( 'bonips_referred_signup', $new_user_id );

	}
endif;

/**
 * Detect Referred BP User Activation
 * @since 1.5.3
 * @version 1.0
 */
if ( ! function_exists( 'bonips_detect_bp_user_activation' ) ) :
	function bonips_detect_bp_user_activation( $user_id ) {

		do_action( 'bonips_bp_user_activated', $user_id );

	}
endif;
