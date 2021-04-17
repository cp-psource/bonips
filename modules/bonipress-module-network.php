<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Network class
 * This module handles all Multisite related features along with adding in the Network settings
 * page in the wp-admin area. Only used if boniPRESS is enabled network wide!
 * @since 0.1
 * @version 1.3
 */
if ( ! class_exists( 'boniPRESS_Network_Module' ) ) :
	class boniPRESS_Network_Module {

		public $core;
		public $plug;
		public $blog_id  = 0;
		public $settings = array();

		/**
		 * Construct
		 */
		public function __construct() {

			global $bonipress_network;

			$this->core     = bonipress();
			$this->blog_id  = get_current_blog_id();
			$this->settings = bonipress_get_settings_network();

		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.1
		 */
		public function load() {

			add_action( 'bonipress_init',                array( $this, 'module_init' ) );
			add_action( 'bonipress_admin_init',          array( $this, 'module_admin_init' ) );

			add_action( 'admin_enqueue_scripts',      array( $this, 'enqueue_admin_before' ) );
			add_action( 'network_admin_menu',         array( $this, 'add_menu' ) );

		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function module_init() {

			if ( BONIPRESS_ENABLE_LOGGING && ! bonipress_is_main_site() ) {

				/**
				 * In situations where we are enforcing our main sites settings on all blogs and
				 * we are not centralising the log, we need to check if the local database table
				 * should be installed.
				 */
				if ( $this->settings['master'] && ! $this->settings['central'] ) {

					$local_install = get_blog_option( $this->blog_id, 'bonipress_version_db', false );
					if ( $local_install === false ) {

						bonipress_install_log( NULL, true );

						// Add local marker to prevent this from running again
						add_blog_option( $this->blog_id, 'bonipress_version_db', time() );

					}

				}

			}

			$this->network_enabled = is_plugin_active_for_network( 'bonipress/bonipress.php' );

			if ( $this->network_enabled ) {

				add_filter( 'wpmu_blogs_columns',         array( $this, 'site_column_headers' ) );
				add_action( 'manage_sites_custom_column', array( $this, 'site_column_content' ), 10, 2 );

			}

		}

		/**
		 * Admin Init
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_admin_init() {

			register_setting( 'bonipress_network', 'bonipress_network', array( $this, 'save_network_prefs' ) );

		}

		/**
		 * Enqueue Admin Before
		 * Adjust the boniPRESS column on the sites screen.
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function enqueue_admin_before() {

			$screen = get_current_screen();
			if ( $screen->id == 'sites-network' ) {

				echo '<style type="text/css">th#' . BONIPRESS_SLUG . ' { width: 15%; }</style>';

			}

		}

		/**
		 * Site Column Headers
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function site_column_headers( $columns ) {

			if ( ! array_key_exists( BONIPRESS_SLUG, $columns ) )
				$columns[ BONIPRESS_SLUG ] = bonipress_label();

			return $columns;

		}

		/**
		 * Site Column Content
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function site_column_content( $column_name, $blog_id ) {

			if ( $column_name == BONIPRESS_SLUG ) {

				if ( bonipress_is_site_blocked( $blog_id ) ) {

					echo '<span class="dashicons dashicons-warning"></span><div class="row-actions"><span class="info" style="color: #666">' . __( 'Gesperrt', 'bonipress' ) . '</span></div>';

				}
				else {

					if ( ! $this->settings['master'] ) {

						if ( get_blog_option( $blog_id, 'bonipress_setup_completed', false ) !== false )
							echo '<span class="dashicons dashicons-yes" style="color: green;"></span><div class="row-actions"><span class="info" style="color: #666">' . __( 'Eingerichtet', 'bonipress' ) . '</span></div>';
						else
							echo '<span class="dashicons dashicons-minus"></span><div class="row-actions"><span class="info" style="color: #666">' . __( 'Nicht eingerichtet', 'bonipress' ) . '</span></div>';

					}
					else {

						echo '<span class="dashicons dashicons-yes"' . ( $blog_id == 1 ? ' style="color: green;"' : '' ) . '></span><div class="row-actions"><span class="info" style="color: #666">' . ( $blog_id == 1 ? __( 'Master-Vorlage', 'bonipress' ) : __( 'aktiviert', 'bonipress' ) ) . '</span></div>';

					}

				}

			}

		}

		/**
		 * Add Network Menu Items
		 * @since 0.1
		 * @version 1.2
		 */
		public function add_menu() {

			$pages   = array();
			$name    = bonipress_label( true );

			$pages[] = add_menu_page(
				$name,
				$name,
				'manage_network_options',
				BONIPRESS_SLUG . '-network',
				'',
				'dashicons-star-filled'
			);

			$pages[] = add_submenu_page(
				BONIPRESS_SLUG . '-network',
				__( 'Netzwerkeinstellungen', 'bonipress' ),
				__( 'Netzwerkeinstellungen', 'bonipress' ),
				'manage_network_options',
				BONIPRESS_SLUG . '-network',
				array( $this, 'admin_page_settings' )
			);

			foreach ( $pages as $page )
				add_action( 'admin_print_styles-' . $page, array( $this, 'admin_page_header' ) );

		}

		/**
		 * Add Admin Menu Styling
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page_header() {

			wp_enqueue_style( 'bonipress-admin' );
			wp_enqueue_style( 'bonipress-bootstrap-grid' );
			wp_enqueue_style( 'bonipress-forms' );

			wp_localize_script( 'bonipress-accordion', 'boniPRESS', array( 'active' => 0 ) );

			wp_enqueue_script( 'bonipress-accordion' );

?>
<!-- boniPRESS Accordion Styling -->
<style type="text/css">
h4:before { float:right; padding-right: 12px; font-size: 14px; font-weight: normal; color: silver; }
h4.ui-accordion-header.ui-state-active:before { content: "<?php _e( 'Klicke zum Schließen', 'bonipress' ); ?>"; }
h4.ui-accordion-header:before { content: "<?php _e( 'Klicke zum Öffnen', 'bonipress' ); ?>"; }
</style>
<?php

		}

		/**
		 * Network Settings Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page_settings() {

			// Security
			if ( ! current_user_can( 'manage_network_options' ) ) wp_die( 'Access Denied' );

			global $bonipress_network;

			$name = bonipress_label();

?>
<div class="wrap bonipress-metabox" id="boniPRESS-wrap">
	<h1><?php printf( __( '%s Netzwerk', 'bonipress' ), $name ); ?><?php if ( BONIPRESS_DEFAULT_LABEL === 'boniPRESS' ) : ?> <a href="https://n3rds.work/docs/bonipress-multisite/" class="page-title-action" target="_blank"><?php _e( 'Dokumentation', 'bonipress' ); ?></a><?php endif; ?></h1>
<?php

			if ( wp_is_large_network() ) {

?>
	<p><?php _e( 'Es tut mir leid, aber Dein Netzwerk ist zu groß, um diese Funktionen nutzen zu können.', 'bonipress' ); ?></p>
<?php

			}

			else {

				// Inform user that boniPRESS has not yet been setup
				$setup = get_blog_option( 1, 'bonipress_setup_completed', false );
				if ( $setup === false )
					echo '<div class="error"><p>' . sprintf( __( 'Hinweis! %s wurde noch nicht eingerichtet.', 'bonipress' ), $name ) . '</p></div>';

				// Settings Updated
				if ( isset( $_GET['settings-updated'] ) )
					echo '<div class="updated"><p>' . __( 'Einstellungen aktualisiert', 'bonipress' ) . '</p></div>';

?>
	<form method="post" action="<?php echo admin_url( 'options.php' ); ?>" class="form" name="bonipress-core-settings-form" novalidate>

		<?php settings_fields( 'bonipress_network' ); ?>

		<div class="list-items expandable-li" id="accordion">

			<h4><span class="dashicons dashicons-admin-settings static"></span><label><?php _e( 'Einstellungen', 'bonipress' ); ?></label></h4>
			<div class="body" style="display: none;">

				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
						<h3><?php _e( 'Master-Vorlage', 'bonipress' ); ?></h3>
						<p><a href="https://n3rds.work/docs/bonipress-multisite/" target="_blank"><?php _e( 'Dokumentation', 'bonipress' ); ?></a></p>
						<div class="row">
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonipress-network-overwrite-enabled"><input type="radio" name="bonipress_network[master]" id="bonipress-network-overwrite-enabled" <?php checked( (int) $this->settings['master'], 1 ); ?> value="1" /> <?php _e( 'Aktiviert', 'bonipress' ); ?></label>
								</div>
							</div>
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonipress-network-overwrite-disabled"><input type="radio" name="bonipress_network[master]" id="bonipress-network-overwrite-disabled" <?php checked( (int) $this->settings['master'], 0 ); ?> value="0" /> <?php _e( 'Deaktiviert', 'bonipress' ); ?></label>
								</div>
							</div>
						</div>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
						<h3><?php _e( 'Central Logging', 'bonipress' ); ?></h3>
						<p><a href="https://n3rds.work/docs/bonipress-multisite/" target="_blank"><?php _e( 'Dokumentation', 'bonipress' ); ?></a></p>
						<div class="row">
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonipress-network-overwrite-log-enabled"><input type="radio" name="bonipress_network[central]" id="bonipress-network-overwrite-log-enabled" <?php checked( (int) $this->settings['central'], 1 ); ?> value="1" /> <?php _e( 'Aktiviert', 'bonipress' ); ?></label>
								</div>
							</div>
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonipress-network-overwrite-log-disabled"><input type="radio" name="bonipress_network[central]" id="bonipress-network-overwrite-log-disabled" <?php checked( (int) $this->settings['central'], 0 ); ?> value="0" /> <?php _e( 'Deaktiviert', 'bonipress' ); ?></label>
								</div>
							</div>
						</div>
					</div>
				</div>

				<h3><?php _e( 'Webseiten Sperre', 'bonipress' ); ?></h3>
				<div class="row">
					<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
						<div class="form-group">
							<label for="bonipress-network-block"><?php _e( 'Blog IDs', 'bonipress' ); ?></label>
							<input type="text" name="bonipress_network[block]" id="bonipress-network-block" value="<?php echo esc_attr( $this->settings['block'] ); ?>" class="form-control" />
							<p><span class="description"><?php printf( __( 'Durch Kommas getrennte Liste von Blog-IDs, bei denen %s deaktiviert werden soll.', 'bonipress' ), $name ); ?></span></p>
						</div>
					</div>
				</div>

				<?php do_action( 'bonipress_network_prefs', $this ); ?>

			</div>

			<?php do_action( 'bonipress_after_network_prefs', $this ); ?>

		</div>

		<?php submit_button( __( 'Netzwerkeinstellungen speichern', 'bonipress' ), 'primary large', 'submit' ); ?>

	</form>	
<?php

			}

			do_action( 'bonipress_bottom_network_page', $this );

?>
</div>
<?php

		}

		/**
		 * Save Network Settings
		 * @since 0.1
		 * @version 1.1
		 */
		public function save_network_prefs( $settings ) {

			$new_settings            = array();
			$new_settings['master']  = ( isset( $settings['master'] ) ) ? absint( $settings['master'] ) : 0;
			$new_settings['central'] = ( isset( $settings['central'] ) ) ? absint( $settings['central'] ) : 0;
			$new_settings['block']   = sanitize_text_field( $settings['block'] );

			// Master template feature change
			if ( (bool) $new_settings['master'] !== $this->settings['master'] ) {

				// Enabled
				if ( (bool) $new_settings['master'] === true ) {
					$this->enable_master_template();
				}
				// Disabled
				else {
					$this->disable_master_template();
				}

			}

			// Central logging feature change
			if ( (bool) $new_settings['central'] !== $this->settings['central'] ) {

				// Enabled
				if ( (bool) $new_settings['central'] === true ) {
					$this->enable_central_logging();
				}
				// Disabled
				else {
					$this->disable_central_logging();
				}

			}

			return apply_filters( 'bonipress_save_network_prefs', $new_settings, $settings, $this->core );

		}

		/**
		 * Enable Master Template
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function enable_master_template() {

			do_action( 'bonipress_master_template_enabled' );

		}

		/**
		 * Disable Master Template
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function disable_master_template() {

			do_action( 'bonipress_master_template_disabled' );

		}

		/**
		 * Enable Central Logging
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function enable_central_logging() {

			do_action( 'bonipress_central_logging_enabled' );

		}

		/**
		 * Disable Central Logging
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function disable_central_logging() {

			do_action( 'bonipress_central_logging_disabled' );

		}

	}
endif;
