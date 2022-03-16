<?php
/**
 * Addon: Coupons
 * Addon URI: http://codex.bonipress.me/chapter-iii/coupons/
 * Version: 1.4
 */
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

define( 'boniPRESS_COUPONS',         __FILE__ );
define( 'boniPRESS_COUPONS_DIR',     boniPRESS_ADDONS_DIR . 'coupons/' );
define( 'boniPRESS_COUPONS_VERSION', '1.3' );

// Coupon Key
if ( ! defined( 'BONIPRESS_COUPON_KEY' ) )
	define( 'BONIPRESS_COUPON_KEY', 'bonipress_coupon' );

require_once boniPRESS_COUPONS_DIR . 'includes/bonipress-coupon-functions.php';
require_once boniPRESS_COUPONS_DIR . 'includes/bonipress-coupon-object.php';
require_once boniPRESS_COUPONS_DIR . 'includes/bonipress-coupon-shortcodes.php';

/**
 * boniPRESS_Coupons_Module class
 * @since 1.4
 * @version 1.4
 */
if ( ! class_exists( 'boniPRESS_Coupons_Module' ) ) :
	class boniPRESS_Coupons_Module extends boniPRESS_Module {

		/**
		 * Construct
		 */
		function __construct() {

			parent::__construct( 'boniPRESS_Coupons_Module', array(
				'module_name' => 'coupons',
				'defaults'    => array(
					'log'         => 'Gutschein-Einlösung',
					'invalid'     => 'Dies ist kein gültiger Gutschein',
					'expired'     => 'Dieser Gutschein ist abgelaufen',
					'user_limit'  => 'Du hast diesen Gutschein bereits verwendet',
					'min'         => 'Um diesen Gutschein zu verwenden, ist ein Mindestbetrag von %amount% erforderlich',
					'max'         => 'Um diesen Gutschein zu verwenden, ist ein Maximum von %amount% erforderlich',
					'excluded'    => 'Du kannst keine Gutscheine verwenden.',
					'success'     => '%amount% erfolgreich auf Dein Konto eingezahlt'
				),
				'register'    => false,
				'add_to_core' => true,
				'menu_pos'    => 80
			) );

			add_filter( 'bonipress_parse_log_entry_coupon', array( $this, 'parse_log_entry' ), 10, 2 );

		}

		/**
		 * Hook into Init
		 * @since 1.4
		 * @version 1.0
		 */
		public function module_init() {

			$this->register_coupons();

			add_shortcode( BONIPRESS_SLUG . '_load_coupon', 'bonipress_render_shortcode_load_coupon' );

			add_action( 'bonipress_add_menu',       array( $this, 'add_to_menu' ), $this->menu_pos );
			add_action( 'admin_notices',         array( $this, 'warn_bad_expiration' ), $this->menu_pos );

		}

		/**
		 * Hook into Admin Init
		 * @since 1.4
		 * @version 1.1
		 */
		public function module_admin_init() {

			add_filter( 'post_updated_messages',   array( $this, 'post_updated_messages' ) );

			add_filter( 'parent_file',             array( $this, 'parent_file' ) );
			add_filter( 'submenu_file',            array( $this, 'subparent_file' ), 10, 2 );

			add_filter( 'enter_title_here',        array( $this, 'enter_title_here' ) );
			add_filter( 'post_row_actions',        array( $this, 'adjust_row_actions' ), 10, 2 );

			add_action( 'admin_head-post.php',     array( $this, 'edit_coupons_style' ) );
			add_action( 'admin_head-post-new.php', array( $this, 'edit_coupons_style' ) );
			add_action( 'admin_head-edit.php',     array( $this, 'coupon_style' ) );

			add_filter( 'manage_' . BONIPRESS_COUPON_KEY . '_posts_columns',       array( $this, 'adjust_column_headers' ) );
			add_action( 'manage_' . BONIPRESS_COUPON_KEY . '_posts_custom_column', array( $this, 'adjust_column_content' ), 10, 2 );
			add_filter( 'bulk_actions-edit-' . BONIPRESS_COUPON_KEY,               array( $this, 'bulk_actions' ) );
			add_action( 'save_post_' . BONIPRESS_COUPON_KEY,                       array( $this, 'save_coupon' ), 10, 2 );

		}

		/**
		 * Register Coupons Post Type
		 * @since 1.4
		 * @version 1.0.2
		 */
		protected function register_coupons() {

			$labels = array(
				'name'                 => __( 'Gutscheine', 'bonipress' ),
				'singular_name'        => __( 'Gutschein', 'bonipress' ),
				'add_new'              => __( 'Neuer Gutschein', 'bonipress' ),
				'add_new_item'         => __( 'Neuer Gutschein', 'bonipress' ),
				'edit_item'            => __( 'Gutschein bearbeiten', 'bonipress' ),
				'new_item'             => __( 'Neuer Gutschein', 'bonipress' ),
				'all_items'            => __( 'Gutscheine', 'bonipress' ),
				'view_item'            => '',
				'search_items'         => __( 'Gutscheine suchen', 'bonipress' ),
				'not_found'            => __( 'Keine Gutscheine gefunden', 'bonipress' ),
				'not_found_in_trash'   => __( 'Keine Gutscheine im Papierkorb gefunden', 'bonipress' ), 
				'parent_item_colon'    => '',
				'menu_name'            => __( 'E-Mail-Benachrichtigungen', 'bonipress' )
			);
			$args = array(
				'labels'               => $labels,
				'supports'             => array( 'title' ),
				'hierarchical'         => false,
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

			register_post_type( BONIPRESS_COUPON_KEY, apply_filters( 'bonipress_register_coupons', $args ) );

		}

		/**
		 * Adjust Update Messages
		 * @since 1.4
		 * @version 1.0.2
		 */
		public function post_updated_messages( $messages ) {

			$messages[ BONIPRESS_COUPON_KEY ] = array(
				0  => '',
				1  => __( 'Gutschein aktualisiert.', 'bonipress' ),
				2  => __( 'Gutschein aktualisiert.', 'bonipress' ),
				3  => __( 'Gutschein aktualisiert.', 'bonipress' ),
				4  => __( 'Gutschein aktualisiert.', 'bonipress' ),
				5  => false,
				6  => __( 'Gutschein veröffentlicht.', 'bonipress' ),
				7  => __( 'Gutschein aktualisiert.', 'bonipress' ),
				8  => __( 'Gutschein aktualisiert.', 'bonipress' ),
				9  => __( 'Gutschein aktualisiert.', 'bonipress' ),
				10 => __( 'Gutschein aktualisiert.', 'bonipress' ),
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
			if ( bonipress_override_settings() && ! bonipress_is_main_site() ) return;

			add_submenu_page(
				BONIPRESS_SLUG,
				__( 'Gutscheine', 'bonipress' ),
				__( 'Gutscheine', 'bonipress' ),
				$this->core->get_point_editor_capability(),
				'edit.php?post_type=' . BONIPRESS_COUPON_KEY
			);

		}

		/**
		 * Parent File
		 * @since 1.7
		 * @version 1.0.2
		 */
		public function parent_file( $parent = '' ) {

			global $pagenow;

			if ( isset( $_GET['post'] ) && bonipress_get_post_type( $_GET['post'] ) == BONIPRESS_COUPON_KEY && isset( $_GET['action'] ) && $_GET['action'] == 'edit' )
				return BONIPRESS_SLUG;

			if ( $pagenow == 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPRESS_COUPON_KEY )
				return BONIPRESS_SLUG;

			return $parent;

		}

		/**
		 * Sub Parent File
		 * @since 1.7
		 * @version 1.0.1
		 */
		public function subparent_file( $subparent = '', $parent = '' ) {

			global $pagenow;

			if ( ( $pagenow == 'edit.php' || $pagenow == 'post-new.php' ) && isset( $_GET['post_type'] ) && $_GET['post_type'] == BONIPRESS_COUPON_KEY ) {

				return 'edit.php?post_type=' . BONIPRESS_COUPON_KEY;
			
			}

			elseif ( $pagenow == 'post.php' && isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) == BONIPRESS_COUPON_KEY ) {

				return 'edit.php?post_type=' . BONIPRESS_COUPON_KEY;

			}

			return $subparent;

		}

		/**
		 * Adjust Enter Title Here
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function enter_title_here( $title ) {

			global $post_type;

			if ( $post_type == BONIPRESS_COUPON_KEY )
				return __( 'Gutscheincode', 'bonipress' );

			return $title;

		}

		/**
		 * Adjust Column Header
		 * @since 1.4
		 * @version 1.1
		 */
		public function adjust_column_headers( $defaults ) {

			$columns            = array();
			$columns['cb']      = $defaults['cb'];

			// Add / Adjust
			$columns['title']   = __( 'Gutscheincode', 'bonipress' );
			$columns['value']   = __( 'Wert', 'bonipress' );
			$columns['usage']   = __( 'Benutzt', 'bonipress' );
			$columns['limits']  = __( 'Limits', 'bonipress' );
			$columns['expires'] = __( 'Abgelaufen', 'bonipress' );

			if ( count( $this->point_types ) > 1 )
				$columns['ctype'] = __( 'Punkttyp', 'bonipress' );

			return $columns;

		}

		/**
		 * Adjust Column Body
		 * @since 1.4
		 * @version 1.1.3
		 */
		public function adjust_column_content( $column_name, $post_id ) {

			$coupon = bonipress_get_coupon( $post_id );

			switch ( $column_name ) {

				case 'value' :

					$bonipress = bonipress( $coupon->point_type );

					echo $bonipress->format_creds( $coupon->value );

				break;

				case 'usage' :

					if ( $coupon->used == 0 )
						echo '-';

					else {

						$set_type = $coupon->point_type;
						$page     = BONIPRESS_SLUG;

						if ( $set_type != BONIPRESS_DEFAULT_TYPE_KEY && array_key_exists( $set_type, $this->point_types ) )
							$page .= '_' . $set_type;

						$url      = add_query_arg( array( 'page' => $page, 'ref' => 'coupon', 'ref_id' => $post_id ), admin_url( 'admin.php' ) );
						echo '<a href="' . esc_url( $url ) . '">' . sprintf( _n( '1 mal', '%d mal', $coupon->used, 'bonipress' ), $coupon->used ) . '</a>';

					}

				break;

				case 'limits' :

					printf( '%1$s: %2$d<br />%3$s: %4$d', __( 'Gesamt', 'bonipress' ), $coupon->max_global, __( 'Pro Benutzer', 'bonipress' ), $coupon->max_user );

				break;

				case 'expires' :

					if ( $coupon->expires === false )
						echo '-';

					else {

						if ( $coupon->expires_unix < current_time( 'timestamp' ) ) {

							bonipress_trash_post( $post_id );

							echo '<span style="color:red;">' . __( 'Abgelaufen', 'bonipress' ) . '</span>';

						}

						else {

							echo sprintf( __( 'In %s Zeit', 'bonipress' ), human_time_diff( $coupon->expires_unix ) ) . '<br /><small class="description">' . date( get_option( 'date_format' ), $coupon->expires_unix ) . '</small>';

						}

					}

				break;

				case 'ctype' :

					if ( isset( $this->point_types[ $coupon->point_type ] ) )
						echo $this->point_types[ $coupon->point_type ];

					else
						echo '-';

				break;

			}
		}

		/**
		 * Adjust Bulk Actions
		 * @since 1.7
		 * @version 1.0
		 */
		public function bulk_actions( $actions ) {

			unset( $actions['edit'] );
			return $actions;

		}

		/**
		 * Adjust Row Actions
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function adjust_row_actions( $actions, $post ) {

			if ( $post->post_type == BONIPRESS_COUPON_KEY ) {
				unset( $actions['inline hide-if-no-js'] );
				unset( $actions['view'] );
			}

			return $actions;

		}

		/**
		 * Edit Coupon Style
		 * @since 1.7
		 * @version 1.0.2
		 */
		public function edit_coupons_style() {

			global $post_type;

			if ( $post_type !== BONIPRESS_COUPON_KEY ) return;

			wp_enqueue_style( 'bonipress-bootstrap-grid' );
			wp_enqueue_style( 'bonipress-forms' );

			add_filter( 'postbox_classes_' . BONIPRESS_COUPON_KEY . '_bonipress-coupon-setup',        array( $this, 'metabox_classes' ) );
			add_filter( 'postbox_classes_' . BONIPRESS_COUPON_KEY . '_bonipress-coupon-limits',       array( $this, 'metabox_classes' ) );
			add_filter( 'postbox_classes_' . BONIPRESS_COUPON_KEY . '_bonipress-coupon-requirements', array( $this, 'metabox_classes' ) );
			add_filter( 'postbox_classes_' . BONIPRESS_COUPON_KEY . '_bonipress-coupon-usage',        array( $this, 'metabox_classes' ) );

			echo '<style type="text/css">#misc-publishing-actions #visibility { display: none; }</style>';

		}

		/**
		 * Coupon Style
		 * @since 1.7
		 * @version 1.0
		 */
		public function coupon_style() { }

		/**
		 * Add Meta Boxes
		 * @since 1.4
		 * @version 1.1.1
		 */
		public function add_metaboxes( $post ) {

			add_meta_box(
				'bonipress-coupon-setup',
				__( 'Gutschein-Einrichtung', 'bonipress' ),
				array( $this, 'metabox_coupon_setup' ),
				BONIPRESS_COUPON_KEY,
				'normal',
				'core'
			);

			add_meta_box(
				'bonipress-coupon-limits',
				__( 'Gutscheinlimits', 'bonipress' ),
				array( $this, 'metabox_coupon_limits' ),
				BONIPRESS_COUPON_KEY,
				'normal',
				'core'
			);

			add_meta_box(
				'bonipress-coupon-requirements',
				__( 'Gutscheinanforderungen', 'bonipress' ),
				array( $this, 'bonipress_coupon_requirements' ),
				BONIPRESS_COUPON_KEY,
				'side',
				'core'
			);

			if ( $post->post_status == 'publish' )
				add_meta_box(
					'bonipress-coupon-usage',
					__( 'Coupon-Nutzung', 'bonipress' ),
					array( $this, 'bonipress_coupon_usage' ),
					BONIPRESS_COUPON_KEY,
					'side',
					'core'
				);

		}

		/**
		 * Admin Notice
		 * If we are have an issue with the expiration date set for this coupon we need to warn the user.
		 * @since 1.7.5
		 * @version 1.0.1
		 */
		public function warn_bad_expiration() {

			if ( isset( $_GET['post'] ) && isset( $_GET['action'] ) && $_GET['action'] == 'edit' && get_post_type( absint( $_GET['post'] ) ) == BONIPRESS_COUPON_KEY ) {

				$post_id            = absint( $_GET['post'] );
				$expiration_warning = bonipress_get_post_meta( $post_id, '_warning_bad_expiration', true );

				if ( $expiration_warning != '' ) {

					bonipress_delete_post_meta( $post_id, '_warning_bad_expiration' );

					echo '<div id="message" class="error notice is-dismissible"><p>' . __( 'Warnung. Das vorherige für diesen Gutschein festgelegte Ablaufdatum war falsch formatiert und wurde gelöscht. Wenn Du dennoch möchtest, dass der Gutschein abläuft, gib bitte ein neues Datum ein oder lasse es leer, um es zu deaktivieren.', 'bonipress' ) . '</p><button type="button" class="notice-dismiss"></button></div>';

				}

			}

		}

		/**
		 * Metabox: Coupon Setup
		 * @since 1.4
		 * @version 1.2.1
		 */
		public function metabox_coupon_setup( $post ) {

			$coupon = bonipress_get_coupon( $post->ID );

			if ( $coupon->point_type != $this->core->cred_id )
				$bonipress = bonipress( $coupon->point_type );
			else
				$bonipress = $this->core;

?>
<div class="form">
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for=""><?php _e( 'Wert', 'bonipress' ); ?></label>
				<input type="text" name="bonipress_coupon[value]" class="form-control" id="bonipress-coupon-value" value="<?php echo $bonipress->number( $coupon->value ); ?>" />
				<span class="description"><?php echo $bonipress->template_tags_general( __( 'Der Betrag von %plural%, den ein Benutzer beim Einlösen dieses Gutscheins erhält.', 'bonipress' ) ); ?></span>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for=""><?php _e( 'Punkttyp', 'bonipress' ); ?></label>
				<?php if ( count( $this->point_types ) > 1 ) : ?>

					<?php bonipress_types_select_from_dropdown( 'bonipress_coupon[type]', 'bonipress-coupon-type', $coupon->point_type, false, ' class="form-control"' ); ?><br />
					<span class="description"><?php _e( 'Wähle den Punktetyp aus, auf den dieser Gutschein angewendet wird.', 'bonipress' ); ?></span>

				<?php else : ?>

					<p class="form-control-static"><?php echo $bonipress->plural(); ?></p>
					<input type="hidden" name="bonipress_coupon[type]" value="<?php echo BONIPRESS_DEFAULT_TYPE_KEY; ?>" />

				<?php endif; ?>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for=""><?php _e( 'Ablauf', 'bonipress' ); ?></label>
				<input type="text" name="bonipress_coupon[expires]" class="form-control" id="bonipress-coupon-expire" maxlength="10" value="<?php echo esc_attr( $coupon->expires ); ?>" placeholder="YYYY-MM-DD" />
				<span class="description"><?php _e( 'Optionales Datum, an dem dieser Gutschein abläuft. Abgelaufene Coupons werden gelöscht.', 'bonipress' ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Metabox: Coupon Limits
		 * @since 1.4
		 * @version 1.1
		 */
		public function metabox_coupon_limits( $post ) {

			$coupon = bonipress_get_coupon( $post->ID );

			if ( $coupon->point_type != $this->core->cred_id )
				$bonipress = bonipress( $coupon->point_type );
			else
				$bonipress = $this->core;

?>
<div class="form">
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonipress-coupon-global"><?php _e( 'Globales Maximum', 'bonipress' ); ?></label>
				<input type="text" name="bonipress_coupon[global]" class="form-control" id="bonipress-coupon-global" value="<?php echo absint( $coupon->max_global ); ?>" />
				<span class="description"><?php _e( 'Die maximale Anzahl von Malen, die dieser Gutschein insgesamt verwendet werden kann. Sobald dies erreicht ist, wird der Coupon automatisch gelöscht.', 'bonipress' ); ?></span>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonipress-coupon-user"><?php _e( 'Benutzermaximum', 'bonipress' ); ?></label>
				<input type="text" name="bonipress_coupon[user]" class="form-control" id="bonipress-coupon-user" value="<?php echo absint( $coupon->max_user ); ?>" />
				<span class="description"><?php _e( 'Die maximale Anzahl von Malen, die dieser Gutschein von einem Benutzer verwendet werden kann.', 'bonipress' ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Metabox: Coupon Requirements
		 * @since 1.4
		 * @version 1.1.1
		 */
		public function bonipress_coupon_requirements( $post ) {

			$coupon = bonipress_get_coupon( $post->ID );

			if ( $coupon->point_type != $this->core->cred_id )
				$bonipress = bonipress( $coupon->point_type );
			else
				$bonipress = $this->core;

?>
<div class="form">
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonipress-coupon-min_balance"><?php _e( 'Mindestguthaben', 'bonipress' ); ?></label>
				<div>
					<input type="text" name="bonipress_coupon[min_balance]" <?php if ( count( $this->point_types ) > 1 ) echo 'size="8"'; else echo ' style="width: 99%;"'; ?> id="bonipress-coupon-min_balance" value="<?php echo $bonipress->number( $coupon->requires_min['value'] ); ?>" />
					<?php echo bonipress_types_select_from_dropdown( 'bonipress_coupon[min_balance_type]', 'bonipress-coupon-min_balance_type', $coupon->requires_min_type, true, ' style="vertical-align: top;"' ); ?>
				</div>
				<span class="description"><?php _e( 'Optionales Mindestguthaben, das ein Benutzer haben muss, um diesen Gutschein zu verwenden. Verwende Null zum Deaktivieren.', 'bonipress' ); ?></span>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="bonipress-coupon-max_balance"><?php _e( 'Maximales Guthaben', 'bonipress' ); ?></label>
				<div>
					<input type="text" name="bonipress_coupon[max_balance]" <?php if ( count( $this->point_types ) > 1 ) echo 'size="8"'; else echo ' style="width: 99%;"'; ?> id="bonipress-coupon-max_balance" value="<?php echo $bonipress->number( $coupon->requires_max['value'] ); ?>" />
					<?php echo bonipress_types_select_from_dropdown( 'bonipress_coupon[max_balance_type]', 'bonipress-coupon-max_balance_type', $coupon->requires_max_type, true, ' style="vertical-align: top;"' ); ?>
				</div>
				<span class="description"><?php _e( 'Optionales maximales Guthaben, das ein Benutzer haben kann, um diesen Gutschein zu verwenden. Verwende Null zum Deaktivieren.', 'bonipress' ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Metabox: Coupon Usage
		 * @since 1.6
		 * @version 1.0.1
		 */
		public function bonipress_coupon_usage( $post ) {

			$count = bonipress_get_global_coupon_count( $post->ID );
			if ( empty( $count ) )
				echo '-';

			else {

				$set_type = bonipress_get_post_meta( $post->ID, 'type', true );
				$page     = BONIPRESS_SLUG;

				if ( $set_type != BONIPRESS_DEFAULT_TYPE_KEY && array_key_exists( $set_type, $this->point_types ) )
					$page .= '_' . $set_type;

				$url = add_query_arg( array( 'page' => $page, 'ref' => 'coupon', 'data' => $post->post_title ), admin_url( 'admin.php' ) );
				echo '<a href="' . esc_url( $url ) . '">' . sprintf( _n( '1 mal', '%d mal', $count, 'bonipress' ), $count ) . '</a>';

			}

		}

		/**
		 * Save Coupon
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function save_coupon( $post_id, $post = NULL ) {

			if ( $post === NULL || ! $this->core->user_is_point_editor() || ! isset( $_POST['bonipress_coupon'] ) ) return $post_id;

			foreach ( $_POST['bonipress_coupon'] as $meta_key => $meta_value ) {

				$new_value = sanitize_text_field( $meta_value );

				// Make sure we provide a valid date that strtotime() can understand
				if ( $meta_key == 'expires' && $new_value != '' ) {

					// Always expires at midnight
					$check = ( strtotime( $new_value . ' midnight' ) + ( DAY_IN_SECONDS - 1 ) );

					// Thats not good. Date is in the past?
					if ( $check === false || $check < current_time( 'timestamp' ) )
						$new_value = '';

				}

				// No need to update if it's still the same value
				$old_value = bonipress_get_post_meta( $post_id, $meta_key, true );
				if ( $new_value != $old_value )
					bonipress_update_post_meta( $post_id, $meta_key, $new_value );

			}

		}

		/**
		 * Add to General Settings
		 * @since 1.4
		 * @version 1.1
		 */
		public function after_general_settings( $bonipress = NULL ) {

			if ( ! isset( $this->coupons ) )
				$prefs = $this->default_prefs;
			else
				$prefs = bonipress_apply_defaults( $this->default_prefs, $this->coupons );

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Gutscheine', 'bonipress' ); ?></h4>
<div class="body" style="display:none;">

	<h3><?php _e( 'Nachrichtenvorlagen', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'invalid' ); ?>"><?php _e( 'Ungültiger Gutschein Nachricht', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'invalid' ); ?>" id="<?php echo $this->field_id( 'invalid' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['invalid'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Meldung, die angezeigt wird, wenn Benutzer versuchen, einen nicht vorhandenen Gutschein zu verwenden.', 'bonipress' ), $this->available_template_tags( array( 'general' ) ) ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'expired' ); ?>"><?php _e( 'Nachricht abgelaufener Gutschein', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'expired' ); ?>" id="<?php echo $this->field_id( 'expired' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['expired'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Nachricht, die angezeigt werden soll, wenn Benutzer versuchen, abgelaufene Gutscheine zu verwenden.', 'bonipress' ), $this->available_template_tags( array( 'general' ) ) ); ?></span></p>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'min' ); ?>"><?php _e( 'Meldung Mindestguthaben', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'min' ); ?>" id="<?php echo $this->field_id( 'min' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['min'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Nachricht, die angezeigt wird, wenn ein Benutzer die Mindestguthabenanforderung nicht erfüllt. (Falls gebraucht)', 'bonipress' ), $this->available_template_tags( array( 'general' ) ) ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'max' ); ?>"><?php _e( 'Meldung maximaler Kontostand', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'max' ); ?>" id="<?php echo $this->field_id( 'max' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['max'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Meldung, die angezeigt wird, wenn ein Benutzer die Anforderungen an das maximale Guthaben nicht erfüllt. (Falls gebraucht)', 'bonipress' ), $this->available_template_tags( array( 'general' ) ) ); ?></span></p>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'user_limit' ); ?>"><?php _e( 'Benutzerlimit-Meldung', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'user_limit' ); ?>" id="<?php echo $this->field_id( 'user_limit' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['user_limit'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Nachricht, die angezeigt wird, wenn das Benutzerlimit für den Gutschein erreicht wurde.', 'bonipress' ), $this->available_template_tags( array( 'general' ) ) ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'excluded' ); ?>"><?php _e( 'Ausgeschlossen Nachricht', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'excluded' ); ?>" id="<?php echo $this->field_id( 'excluded' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['excluded'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Meldung, die angezeigt wird, wenn ein Benutzer von der Punkteart des Gutscheins ausgeschlossen wird.', 'bonipress' ), $this->available_template_tags( array( 'general' ) ) ); ?></span></p>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'success' ); ?>"><?php _e( 'Erfolgsmeldung', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'success' ); ?>" id="<?php echo $this->field_id( 'success' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['success'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Meldung, die angezeigt wird, wenn ein Gutschein erfolgreich auf ein Benutzerkonto eingezahlt wurde.', 'bonipress' ), $this->available_template_tags( array( 'general', 'amount' ) ) ); ?></span></p>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Protokollvorlage', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['log'] ); ?>" />
				<p><span class="description"><?php printf( '%s %s', __( 'Protokolleintrag für erfolgreiche Coupon-Einlösung. Verwende %coupon%, um den Gutscheincode anzuzeigen.', 'bonipress' ), $this->available_template_tags( array( 'general', 'amount' ) ) ); ?></span></p>
			</div>
		</div>
	</div>

</div>
<?php

		}

		/**
		 * Save Settings
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['coupons']['log']        = sanitize_text_field( $data['coupons']['log'] );
			$new_data['coupons']['invalid']    = sanitize_text_field( $data['coupons']['invalid'] );
			$new_data['coupons']['expired']    = sanitize_text_field( $data['coupons']['expired'] );
			$new_data['coupons']['user_limit'] = sanitize_text_field( $data['coupons']['user_limit'] );
			$new_data['coupons']['min']        = sanitize_text_field( $data['coupons']['min'] );
			$new_data['coupons']['max']        = sanitize_text_field( $data['coupons']['max'] );
			$new_data['coupons']['excluded']   = sanitize_text_field( $data['coupons']['excluded'] );
			$new_data['coupons']['success']    = sanitize_text_field( $data['coupons']['success'] );

			return $new_data;

		}

		/**
		 * Parse Log Entries
		 * @since 1.4
		 * @version 1.0
		 */
		public function parse_log_entry( $content, $log_entry ) {

			return str_replace( '%coupon%', $log_entry->data, $content );

		}

	}
endif;

/**
 * Load Coupons Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_load_coupons_addon' ) ) :
	function bonipress_load_coupons_addon( $modules, $point_types ) {

		$modules['solo']['coupons'] = new boniPRESS_Coupons_Module();
		$modules['solo']['coupons']->load();

		return $modules;

	}
endif;
add_filter( 'bonipress_load_modules', 'bonipress_load_coupons_addon', 50, 2 );
