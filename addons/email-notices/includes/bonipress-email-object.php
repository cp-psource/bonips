<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Email class
 * @see http://codex.bonipress.me/classes/bonipress_email/
 * @since 1.8
 * @version 1.0
 */
if ( ! class_exists( 'boniPRESS_Email' ) ) :
	class boniPRESS_Email extends boniPRESS_Object {

		/**
		 * The Email Notice Post ID
		 */
		public $post_id       = false;

		/**
		 * The Email Notice post object
		 */
		public $post          = false;

		/**
		 * Point Types that trigger this email
		 */
		public $point_types   = array();

		/**
		 * The Add-on settings
		 */
		public $emailnotices  = array();

		/**
		 * The Email notice settings
		 */
		public $settings      = array();

		/**
		 * Email Trigger
		 */
		protected $trigger    = '';

		/**
		 * Last time the email was sent
		 */
		public $last_run      = '';

		/**
		 * Construct
		 */
		function __construct( $notice_id = NULL ) {

			parent::__construct();

			$notice_id = absint( $notice_id );
			if ( $notice_id === 0 ) return;

			if ( bonipress_get_post_type( $notice_id ) != BONIPRESS_EMAIL_KEY ) return;

			$this->populate( $notice_id );

		}

		/**
		 * Populate
		 * @since 1.0
		 * @version 1.0
		 */
		protected function populate( $notice_id = NULL ) {

			$this->post_id       = absint( $notice_id );
			$this->post          = bonipress_get_post( $this->post_id );

			$this->point_types   = (array) bonipress_get_post_meta( $this->post_id, 'bonipress_email_ctype', true );
			if ( empty( $this->point_types ) ) $this->point_types = array( BONIPRESS_DEFAULT_TYPE_KEY );

			$this->emailnotices  = bonipress_get_addon_settings( 'emailnotices' );
			$settings            = shortcode_atts( array(
				'recipient'     => 'user',
				'senders_name'  => $this->emailnotices['from']['name'],
				'senders_email' => $this->emailnotices['from']['email'],
				'reply_to'      => $this->emailnotices['from']['reply_to']
			), (array) bonipress_get_post_meta( $this->post_id, 'bonipress_email_settings', true ) );

			// Default to the main settings
			if ( $settings['senders_name'] == '' ) $settings['senders_name'] = $this->emailnotices['from']['name'];
			if ( $settings['senders_email'] == '' ) $settings['senders_email'] = $this->emailnotices['from']['email'];
			if ( $settings['reply_to'] == '' ) $settings['reply_to'] = $this->emailnotices['from']['reply_to'];

			$this->settings = apply_filters( 'bonipress_email_notice_settings', $settings, $this->post_id, $this );

			$this->trigger  = bonipress_get_post_meta( $this->post_id, 'bonipress_email_instance', true );
			$this->last_run = bonipress_get_post_meta( $this->post_id, 'bonipress_email_last_run', true );

		}

		/**
		 * Save Settings
		 * @since 1.0
		 * @version 1.0
		 */
		public function save_settings( $setup = array() ) {

			$setup = shortcode_atts( array(
				'recipient'     => $this->settings['recipient'],
				'senders_name'  => $this->settings['senders_name'],
				'senders_email' => $this->settings['senders_email'],
				'reply_to'      => $this->settings['reply_to']
			), $setup );

			$saved = bonipress_update_post_meta( $this->post_id, 'bonipress_email_settings', $setup );

			$saved = apply_filters( 'bonipress_email_save_settings', $saved, $this->post_id, $setup, $this );

			if ( $saved )
				$this->settings = $setup;

			return $saved;

		}

		/**
		 * Get Trigger
		 * @since 1.0
		 * @version 1.0
		 */
		public function get_trigger() {

			return apply_filters( 'bonipress_get_emails_trigger', $this->trigger, $this );

		}

		/**
		 * Set Trigger
		 * @since 1.0
		 * @version 1.0
		 */
		public function set_trigger( $instance = '' ) {

			$instance = sanitize_key( $instance );
			$current  = $this->get_trigger();

			$new      = $current;
			if ( $current != $instance )
				$new = $instance;

			$trigger  = apply_filters( 'bonipress_set_email_trigger', $new, $instance, $this );

			if ( $trigger !== false ) {

				bonipress_update_post_meta( $this->post_id, 'bonipress_email_instance', $trigger );

				$this->trigger = $trigger;

				return true;

			}

			return false;

		}

		/**
		 * Schedule Email
		 * @since 1.0
		 * @version 1.0
		 */
		public function schedule( $event = array(), $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			if ( empty( $event ) || ! array_key_exists( 'user_id', $event ) ) return false;

			$user_id  = absint( $event['user_id'] );

			$schedule = bonipress_add_user_meta( $user_id, 'bonipress_scheduled_email_notices', '', array(
				'notice_id'  => $this->post_id,
				'event'      => $event,
				'point_type' => $point_type
			), false );
            //added these line to get user balance to fix warning error undefine variable $balance.
            $bonipress  =  bonipress($point_type);
            $balance        = $bonipress->get_users_balance( $user_id );
            //ends here

			return apply_filters( 'bonipress_schedule_email', $schedule, $event, $balance, $point_type, $this );

		}

		/**
		 * Send Email
		 * @since 1.0
		 * @version 1.0
		 */
		public function send( $event = array(), $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			if ( empty( $event ) || ! array_key_exists( 'user_id', $event ) ) return false;

			$user_id    = absint( $event['user_id'] );

			$user       = get_userdata( $user_id );
			$admin      = get_option( 'admin_email' );
			$recipients = $this->get_recipients( $user->user_email, $admin );

			$headers    = $this->get_headers();
			$subject    = $this->get_subject( $event, $point_type );
			$message    = $this->get_message( $event, $point_type );

			add_filter( 'wp_mail_content_type', 'bonipress_get_email_content_type' );

			$result     = wp_mail( $recipients, $subject, $message, $headers );

			remove_filter( 'wp_mail_content_type', 'bonipress_get_email_content_type' );

			$this->update_last_run();
            //added  bonipress object here for error undefine variable $bonipress
            $bonipress  = bonipress( $point_type );
			return apply_filters( 'bonipress_email_send', $result, $event, $bonipress, $this );

		}

		/**
		 * Update Last Run
		 * @since 1.0
		 * @version 1.0
		 */
		public function update_last_run( $timestamp = NULL ) {

			if ( $timestamp === NULL )
				$timestamp = current_time( 'timestamp' );

			$timestamp = apply_filters( 'bonipress_email_update_last_run', absint( $timestamp ), $this );

			if ( $timestamp > 0 ) {

				$result         = bonipress_update_post_meta( $this->post_id, 'bonipress_email_last_run', $timestamp );
				$this->last_run = $timestamp;

			}

			return $result;

		}

		/**
		 * Get Email Styling
		 * @since 1.0
		 * @version 1.0
		 */
		public function get_email_styling() {

			if ( $this->emailnotices['use_html'] === false ) return '';

			$style = bonipress_get_post_meta( $this->post_id, 'bonipress_email_styling', true );

			// Defaults
			if ( empty( $style ) )
				$style = $this->emailnotices['styling'];

			return apply_filters( 'bonipress_email_notice_get_styling', $style, $this );

		}

		/**
		 * Get Recipients
		 * Returns an array of email addresses that this email should be sent to, based on our setup.
		 * Returns false if used incorrectly.
		 * @since 1.0
		 * @version 1.0
		 */
		public function get_recipients( $user = '', $admin = '' ) {

			if ( empty( $user ) || ! is_email( $user ) || empty( $admin ) || ! is_email( $admin ) ) return false;

			$recipient = 'user';
			if ( isset( $this->settings['recipient'] ) )
				$recipient = $this->settings['recipient'];

			$emails = array( $user );
			if ( $recipient == 'both' )
				$emails = array( $user, $admin );

			elseif ( $recipient == 'admin' )
				$emails = array( $admin );

			return apply_filters( 'bonipress_email_notice_get_recipients', $emails, $user, $admin, $this );

		}

		/**
		 * Get Headers
		 * Returns a header array based on our setup.
		 * @since 1.0
		 * @version 1.0
		 */
		public function get_headers() {

			$headers = array();

			// Construct headers
			if ( $this->emailnotices['use_html'] === true ) {
				$headers[] = 'MIME-Version: 1.0';
				$headers[] = 'Content-Type: text/HTML; charset="' . get_option( 'blog_charset' ) . '"';
			}

			if ( $this->settings['senders_name'] != '' && $this->settings['senders_email'] != '' )
				$headers[] = 'From: ' . $this->settings['senders_name'] . ' <' . $this->settings['senders_email'] . '>';

			elseif ( $this->settings['senders_name'] == '' && $this->settings['senders_email'] != '' )
				$headers[] = 'From: <' . $this->settings['senders_email'] . '>';

			// Reply-To
			if ( $this->settings['senders_name'] != '' && $this->settings['reply_to'] != '' )
				$headers[] = 'Reply-To: ' . $this->settings['senders_name'] . ' <' . $this->settings['reply_to'] . '>';

			elseif ( $this->settings['senders_name'] == '' && $this->settings['reply_to'] != '' )
				$headers[] = 'Reply-To: <' . $this->settings['reply_to'] . '>';

			return apply_filters( 'bonipress_email_notice_get_headers', $headers, $this );

		}

		/**
		 * Get Subject
		 * Returns the email notices subject.
		 * @since 1.0
		 * @version 1.0
		 */
		public function get_subject( $event = array(), $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			$subject = $this->post->post_title;

			if ( ! empty( $subject ) ) {

				$bonipress  = bonipress( $point_type );

				if ( $this->emailnotices['filter']['subject'] === true )
					$subject = bonipress_get_the_title( $this->post );

				$subject = $bonipress->template_tags_amount( $subject, $event['amount'] );
				$subject = $bonipress->template_tags_user( $subject, $event['user_id'] );

				if ( array_key_exists( 'data', $event ) && ! empty( $event['data'] ) && array_key_exists( 'ref_type', $event['data'] ) && $event['data']['ref_type'] == 'post' )
					$subject = $bonipress->template_tags_post( $subject, $event['ref_id'] );

				$subject = str_replace( '%amount%', $event['amount'], $subject );

			}

			return apply_filters( 'bonipress_email_notice_get_subject', $subject, $this );

		}

		/**
		 * Get Body
		 * Returns the email notices body.
		 * @since 1.0
		 * @version 1.0
		 */
		public function get_body( $event = array(), $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			$content = $this->post->post_content;

			if ( ! empty( $content ) ) {

				$bonipress  = bonipress( $point_type );

				if ( $this->emailnotices['use_html'] === true )
					$content = wpautop( $content );

				$content = wptexturize( $content );

				if ( $this->emailnotices['filter']['content'] === true ) {
					$content = apply_filters( 'the_content', $content );
					$content = do_shortcode( $content );
				}

				// Template tags can only be used if the email triggers for one point type only.
				$content = $bonipress->template_tags_amount( $content, $event['amount'] );
				$content = $bonipress->template_tags_user( $content, $event['user_id'] );

				if ( array_key_exists( 'data', $event ) && ! empty( $event['data'] ) && array_key_exists( 'ref_type', $event['data'] ) && $event['data']['ref_type'] == 'post' )
					$content = $bonipress->template_tags_post( $content, $event['ref_id'] );

				$content = str_replace( '%amount%',        $bonipress->format_creds( $event['amount'] ), $content );
				$content = str_replace( '%new_balance%',   $bonipress->format_creds( $event['new'] ), $content );
				$content = str_replace( '%old_balance%',   $bonipress->format_creds( $event['old'] ), $content );

				$content = str_replace( '%entry%',         $event['entry'], $content );

				$content = str_replace( '%blog_name%',     get_option( 'blogname' ), $content );
				$content = str_replace( '%blog_url%',      get_option( 'home' ), $content );
				$content = str_replace( '%blog_info%',     get_option( 'blogdescription' ), $content );
				$content = str_replace( '%admin_email%',   get_option( 'admin_email' ), $content );

			}

			return apply_filters( 'bonipress_email_notice_get_body', $content, $this );

		}

		/**
		 * Get Message
		 * Returns the email message with HTML formatting (if used).
		 * @since 1.0
		 * @version 1.0
		 */
		public function get_message( $event = array(), $point_type = BONIPRESS_DEFAULT_TYPE_KEY ) {

			$message = $this->get_body( $event, $point_type );

			if ( $this->emailnotices['use_html'] ) {

				$subject = $this->get_subject( $event, $point_type );
				$styling = $this->get_email_styling();

				$message = '<html><head><title>' . $subject . '</title><style type="text/css" media="all"> ' . trim( $styling ) . '</style></head><body>' . $message . '</body></html>';

			}

			// Backwards comp.
			$message = apply_filters( 'bonipress_email_content_body', $message, $event, $this );

			return apply_filters( 'bonipress_email_notice_get_message', $message, $this );

		}

	}
endif;
