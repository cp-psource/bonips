<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Get Email Notice
 * Returns the email notice object.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_email_notice' ) ) :
	function bonipress_get_email_notice( $notice_id = false ) {

		global $bonipress_email;

		if ( $notice_id === false || absint( $notice_id ) === 0 ) return false;

		if ( isset( $bonipress_email )
			&& ( $bonipress_email instanceof boniPRESS_Email )
			&& ( $notice_id === $bonipress_email->post_id )
		) {
			return $bonipress_email;
		}

		$bonipress_email = new boniPRESS_Email( $notice_id );

		do_action( 'bonipress_get_email_notice' );

		return $bonipress_email;

	}
endif;

/**
 * User Wants Email Notice
 * Returns true if user has not selected to unsubscribe from this email address else false if they did.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_user_wants_email' ) ) :
	function bonipress_user_wants_email( $user_id = false, $notice_id = false ) {

		if ( $user_id === false || absint( $user_id ) === 0 ) return false;

		$wants_email = true;
		$account     = bonipress_get_account( $user_id );

		if ( isset( $account->email_block ) && ! empty( $account->email_block ) && in_array( $notice_id, $account->email_block ) )
			$wants_email = false;

		elseif ( ! isset( $account->email_block ) ) {

			$unsubscriptions = (array) bonipress_get_user_meta( $user_id, 'bonipress_email_unsubscriptions', '', true );
			if ( ! empty( $unsubscriptions ) && in_array( $notice_id, $unsubscriptions ) )
				$wants_email = false;

		}

		return apply_filters( 'bonipress_email_notice_user_wants', $wants_email, $user_id, $notice_id );

	}
endif;

/**
 * Get Email Notice Instances
 * Returns an array of supported instances where an email can be sent by this add-on.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_email_instances' ) ) :
	function bonipress_get_email_instances( $none = true ) {

		$instances = array();

		if ( $none ) $instances[''] = __( 'Select', 'bonipress' );

		$instances['any']      = __( 'users balance changes', 'bonipress' );
		$instances['positive'] = __( 'users balance increases', 'bonipress' );
		$instances['negative'] = __( 'users balance decreases', 'bonipress' );
		$instances['zero']     = __( 'users balance reaches zero', 'bonipress' );
		$instances['minus']    = __( 'users balance goes negative', 'bonipress' );

		if ( class_exists( 'boniPRESS_Badge_Module' ) ) {
			$instances['badge_new'] = __( 'user gains a badge', 'bonipress' );
			$instances['badge_level'] = __( 'user gains a new badge level', 'bonipress' );
		}

		if ( class_exists( 'boniPRESS_Ranks_Module' ) ) {
			$instances['rank_up']   = __( 'user is promoted to a higher rank', 'bonipress' );
			$instances['rank_down'] = __( 'user is demoted to a lower rank', 'bonipress' );
		}

		if ( class_exists( 'boniPRESS_Transfer_Module' ) ) {
			$instances['transfer_out'] = __( 'user sends a transfer', 'bonipress' );
			$instances['transfer_in']  = __( 'user receives a transfer', 'bonipress' );
		}

		$instances['custom']  = __( 'a custom event occurs', 'bonipress' );

		return apply_filters( 'bonipress_email_instances', $instances );

	}
endif;

/**
 * Get Email Triggers
 * Retreaves the saved email triggers for a given point type.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_email_triggers' ) ) :
	function bonipress_get_email_triggers( $point_type = BONIPRESS_DEFAULT_TYPE_KEY, $force = false ) {

		$generic_events = array(
			'any'          => array(),
			'positive'     => array(),
			'negative'     => array(),
			'zero'         => array(),
			'minus'        => array(),
			'badge_new'    => array(),
			'badge_level'  => array(),
			'rank_up'      => array(),
			'rank_down'    => array(),
			'transfer_out' => array(),
			'transfer_in'  => array()
		);

		$defaults = array(
			'generic'  => $generic_events,
			'specific' => array()
		);

		$setup    = (array) bonipress_get_option( 'bonipress-email-triggers-' . $point_type, $defaults );

		if ( empty( $setup ) || $force )
			$setup = $defaults;

		return apply_filters( 'bonipress_get_email_triggers', $setup, $point_type, $force );

	}
endif;

/**
 * Add Email Trigger
 * Adds an email post to the nominated instance for a particular point type.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_add_email_trigger' ) ) :
	function bonipress_add_email_trigger( $event_type = '', $instance = '', $notice_id = false, $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		$event_type = sanitize_key( $event_type );
		$instance   = sanitize_key( $instance );
		$notice_id  = absint( $notice_id );

		if ( empty( $event_type ) || empty( $instance ) || $notice_id === 0 ) return false;

		$triggers   = bonipress_get_email_triggers( $point_type );

		if ( array_key_exists( $event_type, $triggers ) ) {

			if ( ! array_key_exists( $instance, $triggers[ $event_type ] ) ) {

				if ( ! is_array( $triggers[ $event_type ] ) )
					$triggers[ $event_type ] = array();

				$triggers[ $event_type ][ $instance ]   = array();
				$triggers[ $event_type ][ $instance ][] = $notice_id;

			}
			else {

				if ( empty( $triggers[ $event_type ] ) || ! in_array( $notice_id, $triggers[ $event_type ][ $instance ] ) )
					$triggers[ $event_type ][ $instance ][] = $notice_id;

			}

			$triggers = apply_filters( 'bonipress_update_email_triggers', $triggers, $event_type, $instance, $notice_id, $point_type );

			if ( ! empty( $triggers ) )
				bonipress_update_option( 'bonipress-email-triggers-' . $point_type, $triggers );

			return true;

		}

		return false;

	}
endif;

/**
 * Add Email Trigger
 * Adds an email post to the nominated instance for a particular point type.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_delete_email_trigger' ) ) :
	function bonipress_delete_email_trigger( $notice_id = false, $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		$notice_id  = absint( $notice_id );
		$point_type = sanitize_key( $point_type );

		if ( $notice_id === 0 ) return false;

		$triggers   = bonipress_get_email_triggers( $point_type );
		$original   = $triggers;

		if ( ! empty( $triggers ) ) {

			// Generics - here the keys needs to be preserved, even if it's an empty array.
			if ( array_key_exists( 'generic', $triggers ) ) {
				foreach ( $triggers['generic'] as $instance => $notice_ids ) {

					if ( ! empty( $notice_ids ) && in_array( $notice_id, $notice_ids ) ) {

						$new_list = array();
						foreach ( $notice_ids as $id ) {

							$id = absint( $id );
							if ( $id !== 0 && $id !== $notice_id )
								$new_list[] = $id;

						}

						$triggers['generic'][ $instance ] = $new_list;

					}

				}

			}

			// Specific - here we only keep instances that have notice IDs, no empty values.
			if ( array_key_exists( 'specific', $triggers ) ) {
				foreach ( $triggers['specific'] as $instance => $notice_ids ) {

					// If our notice is in this array, remove it by building a new array
					// take this opportuniy to make sure we have integers and no zero values
					if ( ! empty( $notice_ids ) && in_array( $notice_id, $notice_ids ) ) {

						$new_list = array();
						foreach ( $notice_ids as $id ) {

							$id = absint( $id );
							if ( $id !== 0 && $id !== $notice_id )
								$new_list[] = $id;

						}

						if ( ! empty( $new_list ) )
							$triggers['specific'][ $instance ] = $new_list;

						else {

							unset( $triggers['specific'][ $instance ] );

						}

					}

					// No notice ID = should not be in here.
					elseif ( empty( $notice_ids ) ) {

						unset( $triggers['specific'][ $instance ] );

					}

				}
			}

			$triggers = apply_filters( 'bonipress_delete_email_triggers', $triggers, $notice_id, $original, $point_type );

			if ( ! empty( $triggers ) )
				bonipress_update_option( 'bonipress-email-triggers-' . $point_type, $triggers );

			return true;

		}

		return false;

	}
endif;

/**
 * Get Email Triggers
 * Retreaves the saved email triggers for a given point type.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_triggered_emails' ) ) :
	function bonipress_get_triggered_emails( $bonipress_event = array(), $new_balance = 0 ) {

		extract( shortcode_atts( array(
			'ref'     => '',
			'user_id' => 0,
			'amount'  => 0,
			'entry'   => '',
			'ref_id'  => 0,
			'data'    => '',
			'type'    => BONIPRESS_DEFAULT_TYPE_KEY
		), $bonipress_event ) );

		$notices  = array();
		if ( empty( $ref ) || $user_id == 0 ) return $notices;

		$triggers = bonipress_get_email_triggers( $type );

		$gain     = ( $amount > 0 ) ? true : false;
		$zero     = ( $new_balance == 0 ) ? true : false;
		$minus    = ( $new_balance < 0 ) ? true : false;

		if ( ! empty( $triggers ) ) {

			// Generic - any event
			if ( ! empty( $triggers['generic']['any'] ) ) {
				foreach ( $triggers['generic']['any'] as $notice_id ) {

					if ( ! in_array( $notice_id, $notices ) )
						$notices[] = $notice_id;

				}
			}

			// Point gains
			if ( $gain && ! empty( $triggers['generic']['positive'] ) ) {
				foreach ( $triggers['generic']['positive'] as $notice_id ) {

					if ( ! in_array( $notice_id, $notices ) )
						$notices[] = $notice_id;

				}
			}

			// Point loss
			elseif ( ! $gain && ! empty( $triggers['generic']['negative'] ) ) {
				foreach ( $triggers['generic']['negative'] as $notice_id ) {

					if ( ! in_array( $notice_id, $notices ) )
						$notices[] = $notice_id;

				}
			}

			// Balance is zero
			if ( $zero && ! empty( $triggers['generic']['zero'] ) ) {
				foreach ( $triggers['generic']['zero'] as $notice_id ) {

					if ( ! in_array( $notice_id, $notices ) )
						$notices[] = $notice_id;

				}
			}

			// Balance is negative
			if ( $minus && ! empty( $triggers['generic']['minus'] ) ) {
				foreach ( $triggers['generic']['minus'] as $notice_id ) {

					if ( ! in_array( $notice_id, $notices ) )
						$notices[] = $notice_id;

				}
			}

            // check if trasfer trigger has notice id
            if ( ! empty( $ref ) && $ref == 'transfer' && floatval( $amount ) > 0 && ! empty( $triggers['generic']['transfer_in'] ) ) {
                foreach ( $triggers['generic']['transfer_in'] as $notice_id ) {

                    if ( ! in_array( $notice_id, $notices ) )
                        $notices[] = $notice_id;

                }
            }

            // check if trasfer trigger has notice ids
            if ( ! empty( $ref ) && $ref == 'transfer' && floatval( $amount ) < 0 && ! empty( $triggers['generic']['transfer_out'] ) ) {
                foreach ( $triggers['generic']['transfer_out'] as $notice_id ) {
                    if ( ! in_array( $notice_id, $notices ) )
                        $notices[] = $notice_id;

                }
            }

            // Specific instances based on reference
			if ( ! empty( $triggers['specific'] ) && array_key_exists( $ref, $triggers['specific'] ) && ! empty( $triggers['specific'][ $ref ] ) ) {
				foreach ( $triggers['specific'][ $ref ] as $notice_id ) {

					if ( ! in_array( $notice_id, $notices ) )
						$notices[] = $notice_id;

				}
			}

		}

		return apply_filters( 'bonipress_get_triggered_emails', $notices, $triggers, $bonipress_event, $new_balance );

	}
endif;

/**
 * Get Event Emails
 * Returns all the notice IDs that exists for a given event type + instance.
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_event_emails' ) ) :
	function bonipress_get_event_emails( $point_type = BONIPRESS_DEFAULT_TYPE_KEY, $event_type = '', $instance = '' ) {

		$triggers = bonipress_get_email_triggers( $point_type );
		$notices  = array();

		if ( array_key_exists( $event_type, $triggers ) ) {

			if ( array_key_exists( $instance, $triggers[ $event_type ] ) && ! empty( $triggers[ $event_type ][ $instance ] ) )
				$notices = $triggers[ $event_type ][ $instance ];

		}

		return apply_filters( 'bonipress_get_event_emails', $notices, $triggers, $point_type, $event_type, $instance );

	}
endif;

/**
 * Send New Email
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_send_new_email' ) ) :
	function bonipress_send_new_email( $notice_id = false, $event = array(), $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

		if ( $notice_id === false || get_post_status ( $notice_id ) !== 'publish' ) return false;

		$notice_id  = absint( $notice_id );
		$email      = bonipress_get_email_notice( $notice_id );

        //if $email notice object is empty skip this 
        if (!empty($email->settings) ) {

            // Schedule for later
            if ( $email->emailnotices['send'] != '' ){
                $email->schedule( $event, $point_type );

            }

            // Run now
            else {

                $email->send( $event, $point_type );

            }
        }
		return true;

	}
endif;

/**
 * Get Email Content Type
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_get_email_content_type' ) ) :
	function bonipress_get_email_content_type() {

		$format   = 'text/plain';
		$bonipress_version = (float) explode(' ', boniPRESS_VERSION)[0];
			if( $bonipress_version >= 1.8 ){
				$settings = bonipress_get_addon_settings( 'emailnotices' );
			}else{
				$settings = bonipress_get_addon_settings( 'emails' );

			}
		if ( $settings['use_html'] )
			$format = 'text/html';

		return apply_filters( 'bonipress_get_email_content_type', $format, $settings );

	}
endif;

/**
 * Cron Schedule Handler
 * @since 1.3
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_email_notice_cron_job' ) ) :
	function bonipress_email_notice_cron_job() {

		if ( ! class_exists( 'boniPRESS_Email_Notice_Module' ) ) return;

		global $wpdb;

		$pending = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->usermeta} WHERE meta_key = %s;", bonipress_get_meta_key( 'bonipress_scheduled_email_notices' ) ) );

		if ( $pending ) {

			foreach ( $pending as $pending_notice ) {

				$notice     = maybe_unserialize( $pending_notice->meta_value );

				$notice_id  = absint( $notice['notice_id'] );
				$email      = bonipress_get_email_notice( $notice_id );

				// Send email now
				$email->send( $notice['event'], $notice['point_type'] );

				// Delete record
				bonipress_delete_user_meta( $pending_notice->user_id, 'bonipress_scheduled_email_notices', '', $notice );

			}

		}

	}
endif;

/**
 * Get Email Settings
 * @since 1.4
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_email_subscriptions' ) ) :
	function bonipress_get_email_settings( $post_id ) {

		$emailnotices  = bonipress_get_addon_settings( 'emailnotices' );
		$settings      = (array) bonipress_get_post_meta( $post_id, 'bonipress_email_settings', true );

		if ( $settings == '' || empty($settings) )
			$settings = array();

		// Defaults
		$default = array(
			'recipient'     => 'user',
			'senders_name'  => $emailnotices['from']['name'],
			'senders_email' => $emailnotices['from']['email'],
			'reply_to'      => $emailnotices['from']['reply_to'],
			'label'         => get_the_title($post_id)
		);

		$settings = bonipress_apply_defaults( $default, $settings );
		return apply_filters( 'bonipress_email_notice_settings', $settings, $post_id );
	}
endif; 