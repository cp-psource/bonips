<?php
/**
 * Addon: Email Notices
 * Addon URI: http://codex.bonips.me/chapter-iii/email-notice/
 * Version: 1.4
 */
if ( ! defined( 'boniPS_VERSION' ) ) exit;

// Define constants
define( 'boniPS_EMAIL',         __FILE__ );
define( 'boniPS_EMAIL_DIR',     boniPS_ADDONS_DIR . 'email-notices/' );
define( 'boniPS_EMAIL_VERSION', '1.4' );

// Coupon Key
if ( ! defined( 'BONIPS_EMAIL_KEY' ) )
	define( 'BONIPS_EMAIL_KEY', 'bonips_email_notice' );

// Includes
require_once boniPS_EMAIL_DIR . 'includes/bonips-email-functions.php';
require_once boniPS_EMAIL_DIR . 'includes/bonips-email-object.php';
require_once boniPS_EMAIL_DIR . 'includes/bonips-email-shortcodes.php';

/**
 * boniPS_Email_Notice_Module class
 * @since 1.1
 * @version 2.0
 */
if ( ! class_exists( 'boniPS_Email_Notice_Module' ) ) :
	class boniPS_Email_Notice_Module extends boniPS_Module {

		/**
		 * Construct
		 */
		function __construct() {

			parent::__construct( 'boniPS_Email_Notice_Module', array(
				'module_name' => 'emailnotices',
				'defaults'    => array(
					'from'        => array(
						'name'        => get_bloginfo( 'name' ),
						'email'       => get_bloginfo( 'admin_email' ),
						'reply_to'    => get_bloginfo( 'admin_email' )
					),
					'filter'      => array(
						'subject'     => 0,
						'content'     => 0
					),
					'use_html'    => true,
					'content'     => '',
					'styling'     => '',
					'send'        => '',
					'override'    => 0
				),
				'register'    => false,
				'add_to_core' => true,
				'menu_pos'    => 90
			) );

		}

		/**
		 * Hook into Init
		 * @since 1.1
		 * @version 1.3
		 */
		public function module_init() {

			$this->register_email_notices();
			$this->setup_cron_jobs();

			add_action( 'bonips_set_current_account',    array( $this, 'populate_current_account' ) );
			add_action( 'bonips_get_account',            array( $this, 'populate_account' ) );

			add_filter( 'bonips_add_finished',           array( $this, 'email_check' ), 80, 3 );

			add_action( 'bonips_badge_level_reached',    array( $this, 'badge_check' ), 10, 3 );
			add_action( 'bonips_user_got_promoted',      array( $this, 'rank_promotion' ), 10, 4 );
			add_action( 'bonips_user_got_demoted',       array( $this, 'rank_demotion' ), 10, 4 );

			add_action( 'bonips_send_email_notices',     'bonips_email_notice_cron_job' );

			add_shortcode( BONIPS_SLUG . '_email_subscriptions', 'bonips_render_email_subscriptions' );

			add_action( 'bonips_admin_enqueue',          array( $this, 'enqueue_scripts' ), $this->menu_pos );
			add_action( 'bonips_add_menu',               array( $this, 'add_to_menu' ), $this->menu_pos );

		}

		/**
		 * Hook into Admin Init
		 * @since 1.1
		 * @version 1.1
		 */
		public function module_admin_init() {

			add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );

			add_filter( 'parent_file',           array( $this, 'parent_file' ) );
			add_filter( 'submenu_file',          array( $this, 'subparent_file' ), 10, 2 );

			add_action( 'admin_head',            array( $this, 'admin_header' ) );

			add_filter( 'enter_title_here',      array( $this, 'enter_title_here' ) );
			add_filter( 'page_row_actions',      array( $this, 'adjust_row_actions' ), 10, 2 );

			add_filter( 'user_can_richedit',     array( $this, 'disable_richedit' ) );
			add_filter( 'default_content',       array( $this, 'default_content' ) );

			add_filter( 'manage_' . BONIPS_EMAIL_KEY . '_posts_columns',       array( $this, 'adjust_column_headers' ), 50 );
			add_action( 'manage_' . BONIPS_EMAIL_KEY . '_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );
			add_action( 'save_post_' . BONIPS_EMAIL_KEY,                       array( $this, 'save_email_notice' ), 10, 2 );

		}

		/**
		 * Setup Cron Jobs
		 * @since 1.8
		 * @version 1.0
		 */
		public function setup_cron_jobs() {

			// Schedule Cron
			if ( ! isset( $this->emailnotices['send'] ) ) return;

			if ( $this->emailnotices['send'] == 'hourly' && wp_next_scheduled( 'bonips_send_email_notices' ) === false )
				wp_schedule_event( time(), 'hourly', 'bonips_send_email_notices' );

			elseif ( $this->emailnotices['send'] == 'daily' && wp_next_scheduled( 'bonips_send_email_notices' ) === false )
				wp_schedule_event( time(), 'daily', 'bonips_send_email_notices' );

			elseif ( $this->emailnotices['send'] == '' && wp_next_scheduled( 'bonips_send_email_notices' ) !== false )
				wp_clear_scheduled_hook( 'bonips_send_email_notices' );

		}

		/**
		 * Register Email Notice Post Type
		 * @since 1.1
		 * @version 1.1
		 */
		protected function register_email_notices() {

			$labels = array(
				'name'               => __( 'E-Mail Benachrichtigungen', 'bonips' ),
				'singular_name'      => __( 'E-Mail-Benachrichtigung', 'bonips' ),
				'add_new'            => __( 'Neue hinzufügen', 'bonips' ),
				'add_new_item'       => __( 'Neue hinzufügen', 'bonips' ),
				'edit_item'          => __( 'E-Mail-Benachrichtigung bearbeiten', 'bonips' ),
				'new_item'           => __( 'Neue E-Mail-Benachrichtigung', 'bonips' ),
				'all_items'          => __( 'E-Mail Benachrichtigungen', 'bonips' ),
				'view_item'          => '',
				'search_items'       => __( 'Suche nach E-Mail-Benachrichtigungen', 'bonips' ),
				'not_found'          => __( 'Keine E-Mail-Benachrichtigungen gefunden', 'bonips' ),
				'not_found_in_trash' => __( 'Keine E-Mail-Benachrichtigungen im Papierkorb gefunden', 'bonips' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'E-Mail Benachrichtigungen', 'bonips' )
			);
			$args = array(
				'labels'               => $labels,
				'supports'             => array( 'title', 'editor' ),
				'hierarchical'         => true,
				'public'               => false,
				'show_ui'              => true,
				'show_in_menu'         => false,
				'show_in_nav_menus'    => false,
				'show_in_admin_bar'    => false,
				'can_export'           => true,
				'has_archive'          => false,
				'exclude_from_search'  => true,
				'publicly_queryable'   => false,
				'register_meta_box_cb' => array( $this, 'add_metaboxes' )
			);

			register_post_type( BONIPS_EMAIL_KEY, apply_filters( 'bonips_register_emailnotices', $args ) );

		}

		/**
		 * Register Scripts & Styles
		 * @since 1.7
		 * @version 1.0
		 */
		public function scripts_and_styles() {

			// Register Email List Styling
			wp_register_style(
				'bonips-email-notices',
				plugins_url( 'assets/css/email-notice.css', boniPS_EMAIL ),
				false,
				boniPS_EMAIL_VERSION . '.1',
				'all'
			);

			// Register Edit Email Notice Styling
			wp_register_style(
				'bonips-email-edit-notice',
				plugins_url( 'assets/css/edit-email-notice.css', boniPS_EMAIL ),
				false,
				boniPS_EMAIL_VERSION . '.1',
				'all'
			);

		}

		/**
		 * Populate Current Account
		 * @since 1.8
		 * @version 1.0
		 */
		public function populate_current_account() {

			global $bonips_current_account;

			if ( isset( $bonips_current_account )
				&& ( $bonips_current_account instanceof boniPS_Account )
				&& ( isset( $bonips_current_account->email_block ) )
			) return;

			$bonips_current_account->email_block = (array) bonips_get_user_meta( $bonips_current_account->user_id, 'bonips_email_unsubscriptions', '', true );

		}

		/**
		 * Populate Account
		 * @since 1.8
		 * @version 1.0
		 */
		public function populate_account() {

			global $bonips_account;

			if ( isset( $bonips_account )
				&& ( $bonips_account instanceof boniPS_Account )
				&& ( isset( $bonips_account->email_block ) )
			) return;

			$bonips_account->email_block = (array) bonips_get_user_meta( $bonips_account->user_id, 'bonips_email_unsubscriptions', '', true );

		}

		/**
		 * Adjust Post Updated Messages
		 * @since 1.1
		 * @version 1.1
		 */
		public function post_updated_messages( $messages ) {

			$messages[ BONIPS_EMAIL_KEY ] = array(
				0  => '',
				1  => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' ),
				2  => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' ),
				3  => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' ),
				4  => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' ),
				5  => false,
				6  => __( 'E-Mail-Benachrichtigung aktiviert.', 'bonips' ),
				7  => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' ),
				8  => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' ),
				9  => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' ),
				10 => __( 'E-Mail-Benachrichtigung aktualisiert.', 'bonips' )
			);

			return $messages;

		}

		/**
		 * Add Admin Menu Item
		 * @since 1.7
		 * @version 1.1
		 */
		public function add_to_menu() {

			// In case we are using the Master Template feautre on multisites, and this is not the main
			// site in the network, bail.
			if ( bonips_override_settings() && ! bonips_is_main_site() ) return;

			add_submenu_page(
				BONIPS_SLUG,
				__( 'E-Mail Benachrichtigungen', 'bonips' ),
				__( 'E-Mail Benachrichtigungen', 'bonips' ),
				$this->core->get_point_editor_capability(),
				'edit.php?post_type=' . BONIPS_EMAIL_KEY
			);

		}

		/**
		 * Parent File
		 * @since 1.7
		 * @version 1.1
		 */
		public function parent_file( $parent = '' ) {

			global $pagenow;

			if ( isset( $_GET['post'] ) && bonips_get_post_type( $_GET['post'] ) == BONIPS_EMAIL_KEY && isset( $_GET['action'] ) && $_GET['action'] == 'edit' )
				return BONIPS_SLUG;

			if ( $pagenow == 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPS_EMAIL_KEY )
				return BONIPS_SLUG;

			return $parent;

		}

		/**
		 * Sub Parent File
		 * @since 1.7
		 * @version 1.0
		 */
		public function subparent_file( $subparent = '', $parent = '' ) {

			global $pagenow;

			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPS_EMAIL_KEY ) {

				return 'edit.php?post_type=' . BONIPS_EMAIL_KEY;
			
			}

			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && bonips_get_post_type( $_GET['post'] ) == BONIPS_EMAIL_KEY ) {

				return 'edit.php?post_type=' . BONIPS_EMAIL_KEY;

			}

			return $subparent;

		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.1
		 * @version 1.0
		 */
		public function enter_title_here( $title ) {

			global $post_type;

			if ( $post_type == BONIPS_EMAIL_KEY )
				return __( 'E-Mail Betreff', 'bonips' );

			return $title;

		}

		/**
		 * Adjust Column Header
		 * @since 1.1
		 * @version 1.1
		 */
		public function adjust_column_headers( $defaults ) {

			$columns       = array();
			$columns['cb'] = $defaults['cb'];

			// Add / Adjust
			$columns['title']                  = __( 'E-Mail Betreff', 'bonips' );
			$columns['bonips-email-status']    = __( 'Status', 'bonips' );
			$columns['bonips-email-reference'] = __( 'Setup', 'bonips' );

			if ( count( $this->point_types ) > 1 )
				$columns['bonips-email-ctype'] = __( 'Punkttyp', 'bonips' );

			// Return
			return $columns;

		}

		/**
		 * Adjust Column Content
		 * @since 1.1
		 * @version 1.1
		 */
		public function adjust_column_content( $column_name, $post_id ) {

			// Get the post
			if ( in_array( $column_name, array( 'bonips-email-status', 'bonips-email-reference', 'bonips-email-ctype' ) ) )
				$email = bonips_get_email_notice( $post_id );

			// Email Status Column
			if ( $column_name == 'bonips-email-status' ) {

				if ( $email->post->post_status != 'publish' && $email->post->post_status != 'future' )
					echo '<p>' . __( 'Nicht aktiv', 'bonips' ) . '</p>';

				elseif ( $email->post->post_status == 'future' )
					echo '<p>' . sprintf( '<strong>%s</strong> %s', __( 'Geplant', 'bonips' ), date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $email->post->post_date ) ) ) . '</p>';

				else {

					if ( empty( $email->last_run ) )
						echo '<p><strong>' . __( 'Aktiv', 'bonips' ) . '</strong></p>';
					else
						echo '<p>' . sprintf( '<strong>%s</strong> %s', __( 'Aktiv - Letzte Ausführung', 'bonips' ), date( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $email->last_run ) ) . '</p>';

				}

			}

			// Email Setup Column
			elseif ( $column_name == 'bonips-email-reference' ) {

				$instances  = bonips_get_email_instances();
				$references = bonips_get_all_references();

				$trigger     = $email->get_trigger();
				$description = array();

				if ( $trigger == '' )
					$description[] = sprintf( '<strong>%s:</strong> %s', __( 'Gesendet wenn', 'bonips' ), __( 'Nicht eingestellt', 'bonips' ) );

				elseif ( array_key_exists( $trigger, $instances ) )
					$description[] = sprintf( '<strong>%s:</strong> %s', __( 'Gesendet wenn', 'bonips' ), $instances[ $trigger ] );

				elseif( array_key_exists( $trigger, $references ) )
					$description[] = sprintf( '<strong>%s:</strong> %s', __( 'Gesendet wenn', 'bonips' ), $references[ $trigger ] );

				else
					$description[] = sprintf( '<strong>%s:</strong> %s', __( 'Wird bei benutzerdefinierten Veranstaltungen gesendet', 'bonips' ), str_replace( ',', ', ', $trigger ) );

				if ( $email->settings['recipient'] == 'user' )
					$description[] = sprintf( '<strong>%s:</strong> %s', __( 'Empfänger', 'bonips' ), __( 'Benutzer', 'bonips' ) );

				elseif ( $email->settings['recipient'] == 'admin' )
					$description[] = sprintf( '<strong>%s:</strong> %s', __( 'Empfänger', 'bonips' ), __( 'Administrator', 'bonips' ) );

				else
					$description[] = sprintf( '<strong>%s:</strong> %s', __( 'Empfänger', 'bonips' ), __( 'Beide', 'bonips' ) );

				echo '<p>' . implode( '<br />', $description ) . '</p>';

			}

			// Email Setup Column
			elseif ( $column_name == 'bonips-email-ctype' ) {

				echo '<p>';
				if ( empty( $email->point_types ) )
					_e( 'Keine Punkttypen ausgewählt', 'bonips' );

				else {
					$types = array();
					foreach ( $email->point_types as $type_key ) {
						$types[] = $this->point_types[ $type_key ];
					}
					echo implode( ', ', $types );
				}
				echo '</p>';

			}

		}

		/**
		 * Adjust Row Actions
		 * @since 1.1
		 * @version 1.0.1
		 */
		public function adjust_row_actions( $actions, $post ) {

			if ( $post->post_type == BONIPS_EMAIL_KEY ) {
				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['view'] );
			}

			return $actions;

		}

		/**
		 * Add Meta Boxes
		 * @since 1.1
		 * @version 1.1
		 */
		public function add_metaboxes() {

			add_meta_box(
				'bonips-email-setup',
				__( 'Email Trigger', 'bonips' ),
				array( $this, 'metabox_email_setup' ),
				BONIPS_EMAIL_KEY,
				'side',
				'high'
			);

			add_meta_box(
				'bonips-email-tags',
				__( 'Verfügbare Vorlagen-Tags', 'bonips' ),
				array( $this, 'metabox_template_tags' ),
				BONIPS_EMAIL_KEY,
				'normal',
				'core'
			);

			add_meta_box(
				'bonips-email-details',
				__( 'Email Details', 'bonips' ),
				array( $this, 'metabox_email_details' ),
				BONIPS_EMAIL_KEY,
				'normal',
				'high'
			);

		}

		/**
		 * Enqueue Scripts & Styles
		 * @since 1.1
		 * @version 1.1
		 */
		public function enqueue_scripts() {

			$screen = get_current_screen();
			// Commonly used
			if ( $screen->id == 'edit-' . BONIPS_EMAIL_KEY || $screen->id == BONIPS_EMAIL_KEY )
				wp_enqueue_style( 'bonips-admin' );

			// Edit Email Notice Styling
			if ( $screen->id == BONIPS_EMAIL_KEY ) {

				wp_enqueue_style( 'bonips-email-edit-notice' );
				wp_enqueue_style( 'bonips-bootstrap-grid' );
				wp_enqueue_style( 'bonips-forms' );

				wp_enqueue_script( 'bonips-edit-email', plugins_url( 'assets/js/edit-email.js', boniPS_EMAIL ), array( 'jquery' ), boniPS_EMAIL_VERSION, true );

				add_filter( 'postbox_classes_' . BONIPS_EMAIL_KEY . '_bonips-email-setup',   array( $this, 'metabox_classes' ) );
				add_filter( 'postbox_classes_' . BONIPS_EMAIL_KEY . '_bonips-email-tags',    array( $this, 'metabox_classes' ) );
				add_filter( 'postbox_classes_' . BONIPS_EMAIL_KEY . '_bonips-email-details', array( $this, 'metabox_classes' ) );

			}

			// Email Notice List Styling
			elseif ( $screen->id == 'edit-' . BONIPS_EMAIL_KEY )
				wp_enqueue_style( 'bonips-email-notices' );

		}

		/**
		 * Admin Header
		 * @since 1.1
		 * @version 1.0
		 */
		public function admin_header() {

			$screen = get_current_screen();
			if ( $screen->id == BONIPS_EMAIL_KEY && $this->emailnotices['use_html'] === false ) {
				remove_action( 'media_buttons', 'media_buttons' );
				echo '<style type="text/css">#ed_toolbar { display: none !important; }</style>';
			}

		}

		/**
		 * Disable WYSIWYG Editor
		 * @since 1.1
		 * @version 1.0.1
		 */
		public function disable_richedit( $default ) {

			global $post;

			if ( isset( $post->post_type ) && $post->post_type == BONIPS_EMAIL_KEY && $this->emailnotices['use_html'] === false )
				return false;

			return $default;

		}

		/**
		 * Apply Default Content
		 * @since 1.1
		 * @version 1.0
		 */
		public function default_content( $content ) {

			global $post_type;

			if ( $post_type == BONIPS_EMAIL_KEY && !empty( $this->emailnotices['content'] ) )
				$content = $this->emailnotices['content'];

			return $content;

		}

		/**
		 * Email Settings Metabox
		 * @since 1.1
		 * @version 1.1
		 */
		public function metabox_email_setup( $post ) {

			// Get trigger
			$email         = bonips_get_email_notice( $post->ID );
			$trigger       = $email->get_trigger();

			$instances     = bonips_get_email_instances();
			$references    = bonips_get_all_references();

			$uses_generic  = ( $trigger == '' || array_key_exists( $trigger, $instances ) ) ? true : false;
			$uses_specific = ( ! $uses_generic && array_key_exists( $trigger, $references ) ) ? true : false;
			$uses_custom   = ( ! $uses_generic && ! $uses_specific ) ? true : false;

?>
<div class="form">
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonips-email-instance"<?php if ( $post->post_status == 'publish' && empty( $trigger ) ) echo ' style="color:red;font-weight:bold;"'; ?>><?php _e( 'Diese E-Mail-Benachrichtigung senden, wenn...', 'bonips' ); ?></label>
				<select name="bonips_email[instance]" id="bonips-email-instance" class="form-control">
<?php

			// Loop though instances
			foreach ( $instances as $instance => $event ) {

				echo '<option value="' . $instance . '"';
				if ( $instance == $trigger || ( $instance == 'any' && $trigger == '' ) || ( $instance == 'custom' && ( $uses_specific || $uses_custom ) ) ) echo ' selected="selected"';
				echo '>... ' . esc_html( $event ) . '</option>';

			}

?>
				</select>
			</div>
			<div id="reference-selection" style="display: <?php if ( $uses_specific || $uses_custom ) echo 'block'; else echo 'none'; ?>;">
				<div class="form-group">
					<label for="bonips-email-ctype"><?php _e( 'Referenz', 'bonips' ); ?></label>
					<select name="bonips_email[reference]" id="bonips-email-reference" class="form-control">
<?php

			$references['bonips_custom'] = __( 'Benutzerdefinierte Referenz', 'bonips' );

			foreach ( $references as $ref_id => $ref_description ) {

				echo '<option value="' . esc_attr( $ref_id ) . '"';
				if ( $uses_specific && $trigger == $ref_id ) echo ' selected="selected"';
				elseif ( $ref_id == 'bonips_custom' && $uses_custom ) echo ' selected="selected"';
				echo '>' . esc_html( $ref_description ) . '</option>';

			}

?>
					</select>
				</div>
				<div id="custom-reference-selection" style="display: <?php if ( $uses_custom ) echo 'block'; else echo 'none'; ?>;">
					<div class="form-group">
						<label for="bonips-email-custom-ref"><?php _e( 'Benutzerdefinierte Referenz', 'bonips' ); ?></label>
						<input type="text" name="bonips_email[custom_reference]" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" id="bonips-email-custom-ref" class="form-control" value="<?php echo esc_attr( $trigger ); ?>" />
					</div>
					<p class="description" style="line-height: 16px;"><?php _e( 'Dies kann entweder eine einzelne Referenz oder eine durch Kommas getrennte Liste von Referenzen sein.', 'bonips' ); ?></p>
				</div>
			</div>
			<hr />

			<div class="form-group">
				<label for="bonips-email-ctype"><?php _e( 'Punkttypen', 'bonips' ); ?></label>
<?php

			if ( count( $this->point_types ) > 1 ) {

				bonips_types_select_from_checkboxes( 'bonips_email[ctype][]', 'bonips-email-ctype', $email->point_types );

			}

			else {

?>

				<p class="form-control-static"><?php echo $this->core->plural(); ?></p>
				<input type="hidden" name="bonips_email[ctype][]" id="bonips-email-ctype" value="<?php echo BONIPS_DEFAULT_TYPE_KEY; ?>" />
<?php

			}

?>

			</div>
			<hr />

			<div class="form-group" style="margin-bottom: 0;">
				<label for="bonips-email-recipient-user"><?php _e( 'Empfänger:', 'bonips' ); ?></label>
				<div class="inline-radio">
					<label for="bonips-email-recipient-user"><input type="radio" name="bonips_email[recipient]" id="bonips-email-recipient-user" value="user" <?php checked( $email->settings['recipient'], 'user' ); ?> /> <?php _e( 'Benutzer', 'bonips' ); ?></label>
				</div>
				<div class="inline-radio">
					<label for="bonips-email-recipient-admin"><input type="radio" name="bonips_email[recipient]" id="bonips-email-recipient-admin" value="admin" <?php checked( $email->settings['recipient'], 'admin' ); ?> /> <?php _e( 'Administrator', 'bonips' ); ?></label>
				</div>
				<div class="inline-radio">
					<label for="bonips-email-recipient-both"><input type="radio" name="bonips_email[recipient]" id="bonips-email-recipient-both" value="both" <?php checked( $email->settings['recipient'], 'both' ); ?> /> <?php _e( 'Beide', 'bonips' ); ?></label>
				</div>
			</div>
		</div>
	</div>

	<?php do_action( 'bonips_email_settings_box', $this ); ?>

</div>
<?php

		}

		/**
		 * Email Details Metabox
		 * @since 1.8
		 * @version 1.0
		 */
		public function metabox_email_details( $post ) {

			$email = bonips_get_email_notice( $post->ID );

?>
<div class="form">
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonips-email-senders-name"><?php _e( 'Name des Absenders:', 'bonips' ); ?></label>
				<input type="text" name="bonips_email[senders_name]" id="bonips-email-senders-name" class="form-control" value="<?php echo esc_attr( $email->settings['senders_name'] ); ?>" />
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonips-email-senders-email"><?php _e( 'E-Mail des Absenders:', 'bonips' ); ?></label>
				<input type="text" name="bonips_email[senders_email]" id="bonips-email-senders-email" class="form-control" value="<?php echo esc_attr( $email->settings['senders_email'] ); ?>" />
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonips-email-reply-to"><?php _e( 'Antwort-E-Mail:', 'bonips' ); ?></label>
				<input type="text" name="bonips_email[reply_to]" id="bonips-email-reply-to" class="form-control" value="<?php echo esc_attr( $email->settings['reply_to'] ); ?>" />
			</div>
		</div>
	</div>
</div>
<?php

			if ( $this->emailnotices['use_html'] !== false ) {

?>
<div class="form">
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonips-email-styling"><?php _e( 'CSS-Styling', 'bonips' ); ?></label>
				<textarea name="bonips_email[styling]" class="form-control code" rows="10" cols="30" id="bonips-email-styling"><?php echo esc_html( $email->get_email_styling() ); ?></textarea>
			</div>
		</div>
	</div>
</div>
<?php

			}

			do_action( 'bonips_email_details_box', $this );

		}

		/**
		 * Template Tags Metabox
		 * @since 1.1
		 * @version 1.2
		 */
		public function metabox_template_tags( $post ) {

?>
<div class="row">
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Webseiten-bezogen', 'bonips' ); ?></h3>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%blog_name%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Titel Deiner Webseite', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%blog_url%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Deine Webseiten-Adresse', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%blog_info%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Slogan Deiner Webseite (Beschreibung)', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%admin_email%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Die E-Mail-Adresse des Administrators Deiner Webseite', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%num_members%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Gesamtzahl der Blog-Mitglieder', 'bonips' ); ?></div>
			</div>
		</div>
	</div>
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Instanzbezogen', 'bonips' ); ?></h3>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%new_balance%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Das neue Guthaben des Benutzers', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%old_balance%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Das alte Guthaben des Benutzers', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%amount%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Die Anzahl der in diesem Fall gewonnenen oder verlorenen Punkte', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<strong>%entry%</strong>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div><?php _e( 'Der Logeintrag', 'bonips' ); ?></div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<div><?php printf( __( 'Du kannst auch %s verwenden.', 'bonips' ), '<a href="https://github.com/cp-psource/docs/boniprerss-benutzerbezogene-template-tags/" target="_blank">' . __( 'benutzerbezogene Template-Tags', 'bonips' ) . '</a>' ); ?></div>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Save Email Notice Details
		 * @since 1.1
		 * @version 1.2
		 */
		public function save_email_notice( $post_id, $post = NULL ) {

			if ( $post === NULL || ! $this->core->user_is_point_editor() || ! isset( $_POST['bonips_email'] ) ) return $post_id;

			global $bonips_types;

			$email       = bonips_get_email_notice( $post_id );
			$current     = $email->get_trigger();

			// Update Instance
			$instances   = bonips_get_email_instances();
			$references  = bonips_get_all_references();
			$instance    = '';
			$event       = '';
			$settings    = array();
			$point_types = array();

			// Generic
			if ( $_POST['bonips_email']['instance'] != '' && $_POST['bonips_email']['instance'] != 'custom' ) {

				$instance = sanitize_key( $_POST['bonips_email']['instance'] );
				if ( ! array_key_exists( $instance, $instances ) )
					$instance = '';

				else {
					$event = 'generic';
				}

			}

			// Specific
			elseif ( $_POST['bonips_email']['instance'] != '' ) {

				$event     = 'specific';
				$reference = sanitize_key( $_POST['bonips_email']['reference'] );

				// Based on built-in reference
				if ( array_key_exists( $reference, $references ) )
					$instance = $reference;

				// Based on custom reference
				else {

					$reference_list   = array();
					$custom_reference = explode( ',', $_POST['bonips_email']['custom_reference'] );

					foreach ( $custom_reference as $reference_id ) {

						$reference_id = sanitize_key( $reference_id );
						if ( ! empty( $reference_id ) && ! array_key_exists( $reference_id, $instances ) )
							$reference_list[] = $reference_id;

					}

					if ( ! empty( $reference_list ) )
						$instance = implode( ',', $reference_list );

				}

			}

			$email->set_trigger( $instance );

			// Construct new settings
			if ( ! empty( $_POST['bonips_email']['recipient'] ) )
				$settings['recipient']     = sanitize_text_field( $_POST['bonips_email']['recipient'] );

			if ( ! empty( $_POST['bonips_email']['senders_name'] ) )
				$settings['senders_name']  = sanitize_text_field( $_POST['bonips_email']['senders_name'] );

			if ( ! empty( $_POST['bonips_email']['senders_email'] ) )
				$settings['senders_email'] = sanitize_text_field( $_POST['bonips_email']['senders_email'] );

			if ( ! empty( $_POST['bonips_email']['reply_to'] ) )
				$settings['reply_to']      = sanitize_text_field( $_POST['bonips_email']['reply_to'] );

			$email->save_settings( $settings );

			// Point Types
			if ( array_key_exists( 'ctype', $_POST['bonips_email'] ) && ! empty( $_POST['bonips_email']['ctype'] ) ) {

				$checked_types = ( isset( $_POST['bonips_email']['ctype'] ) ) ? $_POST['bonips_email']['ctype'] : array();
				if ( ! empty( $checked_types ) ) {
					foreach ( $checked_types as $type_key ) {
						$type_key = sanitize_key( $type_key );
						if ( bonips_point_type_exists( $type_key ) && ! in_array( $type_key, $point_types ) )
							$point_types[] = $type_key;
					}
				}
				bonips_update_post_meta( $post_id, 'bonips_email_ctype', $point_types );

			}

			// Trigger changed, so we need to remove all existing instances of this email
			// before we add the new instance in.
			if ( $current != $instance ) {
				foreach ( $bonips_types as $type_id => $label ) {

					bonips_delete_email_trigger( $post_id, $type_id );

				}
			}

			if ( ! empty( $point_types ) ) {
				foreach ( $point_types as $type_id ) {

					bonips_add_email_trigger( $event, $instance, $post_id, $type_id );

				}
			}

			// If rich editing is disabled bail now
			if ( $email->emailnotices['use_html'] === false ) return;

			// Save styling
			if ( ! empty( $_POST['bonips_email']['styling'] ) )
				bonips_update_post_meta( $post_id, 'bonips_email_styling', wp_kses_post( $_POST['bonips_email']['styling'] ) );

		}

		/**
		 * Email Notice Check
		 * @since 1.1
		 * @version 1.6
		 */
		public function email_check( $ran, $request, $bonips ) {

			// Exit now if $ran is false or new settings is not yet saved.
			if ( $ran === false || ! isset( $this->emailnotices['send'] ) ) return $ran;

			$user_id        = absint( $request['user_id'] );
			$balance        = $bonips->get_users_balance( $user_id );
			$point_type     = $bonips->get_point_type_key();

			// Check for triggered emails
			$emails         = bonips_get_triggered_emails( $request, $balance );

			// No emails, bail
			if ( empty( $emails ) ) return $ran;

			$request['new'] = $balance;
			$request['old'] = $balance - $request['amount'];

			// This event might have triggered multiple emails
			foreach ( $emails as $notice_id ) {

				// Respect unsubscriptions
				if ( bonips_user_wants_email( $user_id, $notice_id ) )
					bonips_send_new_email( $notice_id, $request, $point_type );

			}

			return $ran;

		}

		/**
		 * Badge Check
		 * @since 1.7
		 * @version 1.1
		 */
		public function badge_check( $user_id, $badge_id, $level_reached ) {

			if ( $level_reached === false ) return;

			$badge       = bonips_get_badge( $badge_id );

			$instance    = 'badge_level';
			$users_level = $badge->get_users_current_level( $user_id );

			// Earning a badge
			if ( $users_level === false )
				$instance = 'badge_new';

			global $bonips_types;

			foreach ( $bonips_types as $type_id => $label ) {

				$emails     = bonips_get_event_emails( $type_id, 'generic', $instance );
				if ( empty( $emails ) ) continue;

				$bonips     = bonips( $type_id );
				$balance    = $bonips->get_users_balance( $user_id );
				$point_type = $bonips->get_point_type_key();

				$request    = array(
					'ref'      => $instance,
					'user_id'  => $user_id,
					'amount'   => 0,
					'entry'    => 'New Badge',
					'ref_id'   => $badge_id,
					'data'     => array( 'ref_type' => 'post' ),
					'type'     => $type_id,
					'level'    => $level_reached,
					'new'      => $balance,
					'old'      => $balance
				);

				foreach ( $emails as $notice_id ) {

					// Respect unsubscriptions
					if ( bonips_user_wants_email( $user_id, $notice_id ) )
						bonips_send_new_email( $notice_id, $request, $point_type );

				}

			}

		}

		/**
		 * Rank Promotions
		 * @since 1.7.6
		 * @version 1.1
		 */
		public function rank_promotion( $user_id, $rank_id, $query, $point_type ) {

			$emails     = bonips_get_event_emails( $point_type, 'generic', 'rank_up' );
			if ( empty( $emails ) ) return;

			$bonips     = bonips( $point_type );
			$balance    = $bonips->get_users_balance( $user_id );

			$request    = array(
				'ref'      => 'rank_promotion',
				'user_id'  => $user_id,
				'amount'   => 0,
				'entry'    => 'Neuer Rang',
				'ref_id'   => $rank_id,
				'data'     => array( 'ref_type' => 'post' ),
				'type'     => $point_type,
				'new'      => $balance,
				'old'      => $balance
			);

			foreach ( $emails as $notice_id ) {

				// Respect unsubscriptions
				if ( bonips_user_wants_email( $user_id, $notice_id ) )
					bonips_send_new_email( $notice_id, $request, $point_type );

			}

		}

		/**
		 * Rank Demotions
		 * @since 1.7.6
		 * @version 1.1
		 */
		public function rank_demotion( $user_id, $rank_id, $query, $point_type ) {

			$emails     = bonips_get_event_emails( $point_type, 'generic', 'rank_down' );
			if ( empty( $emails ) ) return;

			$bonips     = bonips( $point_type );
			$balance    = $bonips->get_users_balance( $user_id );

			$request    = array(
				'ref'      => 'rank_promotion',
				'user_id'  => $user_id,
				'amount'   => 0,
				'entry'    => 'Neuer Rang',
				'ref_id'   => $rank_id,
				'data'     => array( 'ref_type' => 'post' ),
				'type'     => $point_type,
				'new'      => $balance,
				'old'      => $balance
			);

			foreach ( $emails as $notice_id ) {

				// Respect unsubscriptions
				if ( bonips_user_wants_email( $user_id, $notice_id ) )
					bonips_send_new_email( $notice_id, $request, $point_type );

			}

		}

		/**
		 * Add to General Settings
		 * @since 1.1
		 * @version 1.1
		 */
		public function after_general_settings( $bonips = NULL ) {

			$this->emailnotices = bonips_apply_defaults( $this->default_prefs, $this->emailnotices );

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'E-Mail-Benachrichtigungen', 'bonips' ); ?></h4>
<div class="body" style="display:none;">

	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<h3><?php _e( 'Format', 'bonips' ); ?></h3>
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'use_html' ); ?>" id="<?php echo $this->field_id( array( 'use_html' => 'no' ) ); ?>" <?php checked( $this->emailnotices['use_html'], 0 ); ?> value="0" /> <?php _e( 'Einfacher Text', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'use_html' => 'yes' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( 'use_html' ); ?>" id="<?php echo $this->field_id( array( 'use_html' => 'yes' ) ); ?>" <?php checked( $this->emailnotices['use_html'], 1 ); ?> value="1" /> HTML</label>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<h3><?php _e( 'Zeitplan', 'bonips' ); ?></h3>
			<div class="form-group">
				<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
				<input type="hidden" name="<?php echo $this->field_name( 'send' ); ?>" value="" />
				<p class="form-control-static"><?php _e( 'WordPress-Cron ist deaktiviert. E-Mails werden sofort versendet.', 'bonips' ); ?></p>
				<?php else : ?>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'send' ); ?>"><input type="radio" name="<?php echo $this->field_name( 'send' ); ?>" id="<?php echo $this->field_id( 'send' ); ?>" <?php checked( $this->emailnotices['send'], '' ); ?> value="" /> <?php _e( 'E-Mails sofort versenden', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'send' ); ?>-hourly"><input type="radio" name="<?php echo $this->field_name( 'send' ); ?>" id="<?php echo $this->field_id( 'send' ); ?>-hourly" <?php checked( $this->emailnotices['send'], 'hourly' ); ?> value="hourly" /> <?php _e( 'Sende E-Mails einmal pro Stunde', 'bonips' ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'send' ); ?>-daily"><input type="radio" name="<?php echo $this->field_name( 'send' ); ?>" id="<?php echo $this->field_id( 'send' ); ?>-daily" <?php checked( $this->emailnotices['send'], 'daily' ); ?> value="daily" /> <?php _e( 'Sende einmal täglich E-Mails', 'bonips' ); ?></label>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<h3><?php _e( 'Erweitert', 'bonips' ); ?></h3>
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'filter' => 'subject' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'filter' => 'subject' ) ); ?>" id="<?php echo $this->field_id( array( 'filter' => 'subject' ) ); ?>" <?php checked( $this->emailnotices['filter']['subject'], 1 ); ?> value="1" /> <?php _e( 'E-Mail-Betreff filtern', 'bonips' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'filter' => 'content' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'filter' => 'content' ) ); ?>" id="<?php echo $this->field_id( array( 'filter' => 'content' ) ); ?>" <?php checked( $this->emailnotices['filter']['content'], 1 ); ?> value="1" /> <?php _e( 'E-Mail-Text filtern', 'bonips' ); ?></label>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'override' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'override' ); ?>" id="<?php echo $this->field_id( 'override' ); ?>" <?php checked( $this->emailnotices['override'], 1 ); ?> value="1" /> <?php _e( 'SMTP-Debug. Aktiviere diese Option, wenn Du Probleme mit wp_mail() hast oder wenn Du ein SMTP-Plugin für E-Mails verwendest.', 'bonips' ); ?></label>
				</div>
			</div>
		</div>
	</div>

	<h3 style="margin-bottom: 0;"><?php _e( 'Verfügbare Shortcodes', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<p><a href="https://github.com/cp-psource/docs/bonips-bonips_email_subscriptions/" target="_blank">[bonips_email_subscriptions]</a></p>
		</div>
	</div>

	<h3><?php _e( 'Standardwerte', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'from' => 'name' ) ); ?>"><?php _e( 'Name des Absenders:', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'from' => 'name' ) ); ?>" id="<?php echo $this->field_id( array( 'from' => 'name' ) ); ?>" value="<?php echo esc_attr( $this->emailnotices['from']['name'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'from' => 'email' ) ); ?>"><?php _e( 'E-Mail des Absenders:', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'from' => 'email' ) ); ?>" id="<?php echo $this->field_id( array( 'from' => 'email' ) ); ?>" value="<?php echo esc_attr( $this->emailnotices['from']['email'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'from' => 'reply_to' ) ); ?>"><?php _e( 'Antwort an:', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'from' => 'reply_to' ) ); ?>" id="<?php echo $this->field_id( array( 'from' => 'reply_to' ) ); ?>" value="<?php echo esc_attr( $this->emailnotices['from']['reply_to'] ); ?>" class="form-control" />
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'content' ); ?>"><?php _e( 'Standard-E-Mail-Inhalt', 'bonips' ); ?></label>
				<textarea rows="10" cols="50" name="<?php echo $this->field_name( 'content' ); ?>" id="<?php echo $this->field_id( 'content' ); ?>" class="form-control"><?php echo esc_attr( $this->emailnotices['content'] ); ?></textarea>
				<p><span class="description"><?php _e( 'Standard-E-Mail-Inhalt.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'styling' ); ?>"><?php _e( 'Standard-CSS-Stil', 'bonips' ); ?></label>
				<textarea rows="10" cols="50" name="<?php echo $this->field_name( 'styling' ); ?>" id="<?php echo $this->field_id( 'styling' ); ?>" class="form-control"><?php echo esc_attr( $this->emailnotices['styling'] ); ?></textarea>
				<p><span class="description"><?php _e( 'Standard-E-Mail-CSS-Stil. Beachte, dass Du, wenn Du HTML-E-Mails senden möchtest, für beste Ergebnisse das Inline-CSS-Styling verwenden solltest.', 'bonips' ); ?></span></p>
			</div>
		</div>
	</div>

</div>
<?php

		}

		/**
		 * Save Settings
		 * @since 1.1
		 * @version 1.3
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['emailnotices']['use_html']          = ( isset( $data['emailnotices']['use_html'] ) ) ? absint( $data['emailnotices']['use_html'] ) : 0;

			$new_data['emailnotices']['filter']['subject'] = ( isset( $data['emailnotices']['filter']['subject'] ) ) ? 1 : 0;
			$new_data['emailnotices']['filter']['content'] = ( isset( $data['emailnotices']['filter']['content'] ) ) ? 1 : 0;

			$new_data['emailnotices']['from']['name']      = sanitize_text_field( $data['emailnotices']['from']['name'] );
			$new_data['emailnotices']['from']['email']     = sanitize_text_field( $data['emailnotices']['from']['email'] );
			$new_data['emailnotices']['from']['reply_to']  = sanitize_text_field( $data['emailnotices']['from']['reply_to'] );

			$new_data['emailnotices']['content']           = wp_kses_post( $data['emailnotices']['content'] );
			$new_data['emailnotices']['styling']           = sanitize_textarea_field( $data['emailnotices']['styling'] );

			$new_data['emailnotices']['send']              = sanitize_text_field( $data['emailnotices']['send'] );
			$new_data['emailnotices']['override']          = ( isset( $data['emailnotices']['override'] ) ) ? 1 : 0;

			return $new_data;

		}

	}

endif;

/**
 * Load Email Notice Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_load_email_notice_addon' ) ) :
	function bonips_load_email_notice_addon( $modules, $point_types ) {

		$modules['solo']['emails'] = new boniPS_Email_Notice_Module();
		$modules['solo']['emails']->load();

		return $modules;

	}
endif;
add_filter( 'bonips_load_modules', 'bonips_load_email_notice_addon', 60, 2 );
