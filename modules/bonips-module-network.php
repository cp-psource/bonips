<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Network class
 * This module handles all Multisite related features along with adding in the Network settings
 * page in the wp-admin area. Only used if boniPS is enabled network wide!
 * @since 0.1
 * @version 1.3
 */
if ( ! class_exists( 'boniPS_Network_Module' ) ) :
	class boniPS_Network_Module {

		public $core;
		public $plug;
		public $blog_id  = 0;
		public $settings = array();

		/**
		 * Construct
		 */
		public function __construct() {

			global $bonips_network;

			$this->core     = bonips();
			$this->blog_id  = get_current_blog_id();
			$this->settings = bonips_get_settings_network();

		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.1
		 */
		public function load() {

			add_action( 'bonips_init',                array( $this, 'module_init' ) );
			add_action( 'bonips_admin_init',          array( $this, 'module_admin_init' ) );

			add_action( 'admin_enqueue_scripts',      array( $this, 'enqueue_admin_before' ) );
			add_action( 'network_admin_menu',         array( $this, 'add_menu' ) );

		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function module_init() {

			if ( BONIPS_ENABLE_LOGGING && ! bonips_is_main_site() ) {

				/**
				 * In situations where we are enforcing our main sites settings on all blogs and
				 * we are not centralising the log, we need to check if the local database table
				 * should be installed.
				 */
				if ( $this->settings['master'] && ! $this->settings['central'] ) {

					$local_install = get_blog_option( $this->blog_id, 'bonips_version_db', false );
					if ( $local_install === false ) {

						bonips_install_log( NULL, true );

						// Add local marker to prevent this from running again
						add_blog_option( $this->blog_id, 'bonips_version_db', time() );

					}

				}

			}

			$this->network_enabled = is_plugin_active_for_network( 'bonips/bonips.php' );

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

			register_setting( 'bonips_network', 'bonips_network', array( $this, 'save_network_prefs' ) );

		}

		/**
		 * Enqueue Admin Before
		 * Adjust the boniPS column on the sites screen.
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function enqueue_admin_before() {

			$screen = get_current_screen();
			if ( $screen->id == 'sites-network' ) {

				echo '<style type="text/css">th#' . BONIPS_SLUG . ' { width: 15%; }</style>';

			}

		}

		/**
		 * Site Column Headers
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function site_column_headers( $columns ) {

			if ( ! array_key_exists( BONIPS_SLUG, $columns ) )
				$columns[ BONIPS_SLUG ] = bonips_label();

			return $columns;

		}

		/**
		 * Site Column Content
		 * @since 1.7.6
		 * @version 1.0
		 */
		public function site_column_content( $column_name, $blog_id ) {

			if ( $column_name == BONIPS_SLUG ) {

				if ( bonips_is_site_blocked( $blog_id ) ) {

					echo '<span class="dashicons dashicons-warning"></span><div class="row-actions"><span class="info" style="color: #666">' . __( 'Gesperrt', 'bonips' ) . '</span></div>';

				}
				else {

					if ( ! $this->settings['master'] ) {

						if ( get_blog_option( $blog_id, 'bonips_setup_completed', false ) !== false )
							echo '<span class="dashicons dashicons-yes" style="color: green;"></span><div class="row-actions"><span class="info" style="color: #666">' . __( 'Eingerichtet', 'bonips' ) . '</span></div>';
						else
							echo '<span class="dashicons dashicons-minus"></span><div class="row-actions"><span class="info" style="color: #666">' . __( 'Nicht eingerichtet', 'bonips' ) . '</span></div>';

					}
					else {

						echo '<span class="dashicons dashicons-yes"' . ( $blog_id == 1 ? ' style="color: green;"' : '' ) . '></span><div class="row-actions"><span class="info" style="color: #666">' . ( $blog_id == 1 ? __( 'Master-Vorlage', 'bonips' ) : __( 'aktiviert', 'bonips' ) ) . '</span></div>';

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
			$name    = bonips_label( true );

			$pages[] = add_menu_page(
				$name,
				$name,
				'manage_network_options',
				BONIPS_SLUG . '-network',
				'',
				'dashicons-star-filled'
			);

			$pages[] = add_submenu_page(
				BONIPS_SLUG . '-network',
				__( 'Netzwerkeinstellungen', 'bonips' ),
				__( 'Netzwerkeinstellungen', 'bonips' ),
				'manage_network_options',
				BONIPS_SLUG . '-network',
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

			wp_enqueue_style( 'bonips-admin' );
			wp_enqueue_style( 'bonips-bootstrap-grid' );
			wp_enqueue_style( 'bonips-forms' );

			wp_localize_script( 'bonips-accordion', 'boniPS', array( 'active' => 0 ) );

			wp_enqueue_script( 'bonips-accordion' );

?>
<!-- boniPS Accordion Styling -->
<style type="text/css">
h4:before { float:right; padding-right: 12px; font-size: 14px; font-weight: normal; color: silver; }
h4.ui-accordion-header.ui-state-active:before { content: "<?php _e( 'Klicke zum Schließen', 'bonips' ); ?>"; }
h4.ui-accordion-header:before { content: "<?php _e( 'Klicke zum Öffnen', 'bonips' ); ?>"; }
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

			global $bonips_network;

			$name = bonips_label();

?>
<div class="wrap bonips-metabox" id="boniPS-wrap">
	<h1><?php printf( __( '%s Netzwerk', 'bonips' ), $name ); ?><?php if ( BONIPS_DEFAULT_LABEL === 'boniPS' ) : ?> <a href="https://n3rds.work/docs/bonips-multisite/" class="page-title-action" target="_blank"><?php _e( 'Dokumentation', 'bonips' ); ?></a><?php endif; ?></h1>
<?php

			if ( wp_is_large_network() ) {

?>
	<p><?php _e( 'Es tut mir leid, aber Dein Netzwerk ist zu groß, um diese Funktionen nutzen zu können.', 'bonips' ); ?></p>
<?php

			}

			else {

				// Inform user that boniPS has not yet been setup
				$setup = get_blog_option( 1, 'bonips_setup_completed', false );
				if ( $setup === false )
					echo '<div class="error"><p>' . sprintf( __( 'Hinweis! %s wurde noch nicht eingerichtet.', 'bonips' ), $name ) . '</p></div>';

				// Settings Updated
				if ( isset( $_GET['settings-updated'] ) )
					echo '<div class="updated"><p>' . __( 'Einstellungen aktualisiert', 'bonips' ) . '</p></div>';

?>
	<form method="post" action="<?php echo admin_url( 'options.php' ); ?>" class="form" name="bonips-core-settings-form" novalidate>

		<?php settings_fields( 'bonips_network' ); ?>

		<div class="list-items expandable-li" id="accordion">

			<h4><span class="dashicons dashicons-admin-settings static"></span><label><?php _e( 'Einstellungen', 'bonips' ); ?></label></h4>
			<div class="body" style="display: none;">

				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
						<h3><?php _e( 'Master-Vorlage', 'bonips' ); ?></h3>
						<p><a href="https://n3rds.work/docs/bonips-multisite/" target="_blank"><?php _e( 'Dokumentation', 'bonips' ); ?></a></p>
						<div class="row">
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonips-network-overwrite-enabled"><input type="radio" name="bonips_network[master]" id="bonips-network-overwrite-enabled" <?php checked( (int) $this->settings['master'], 1 ); ?> value="1" /> <?php _e( 'Aktiviert', 'bonips' ); ?></label>
								</div>
							</div>
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonips-network-overwrite-disabled"><input type="radio" name="bonips_network[master]" id="bonips-network-overwrite-disabled" <?php checked( (int) $this->settings['master'], 0 ); ?> value="0" /> <?php _e( 'Deaktiviert', 'bonips' ); ?></label>
								</div>
							</div>
						</div>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
						<h3><?php _e( 'Central Logging', 'bonips' ); ?></h3>
						<p><a href="https://n3rds.work/docs/bonips-multisite/" target="_blank"><?php _e( 'Dokumentation', 'bonips' ); ?></a></p>
						<div class="row">
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonips-network-overwrite-log-enabled"><input type="radio" name="bonips_network[central]" id="bonips-network-overwrite-log-enabled" <?php checked( (int) $this->settings['central'], 1 ); ?> value="1" /> <?php _e( 'Aktiviert', 'bonips' ); ?></label>
								</div>
							</div>
							<div class="col-xs-6">
								<div class="form-group">
									<label for="bonips-network-overwrite-log-disabled"><input type="radio" name="bonips_network[central]" id="bonips-network-overwrite-log-disabled" <?php checked( (int) $this->settings['central'], 0 ); ?> value="0" /> <?php _e( 'Deaktiviert', 'bonips' ); ?></label>
								</div>
							</div>
						</div>
					</div>
				</div>

				<h3><?php _e( 'Webseiten Sperre', 'bonips' ); ?></h3>
				<div class="row">
					<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
						<div class="form-group">
							<label for="bonips-network-block"><?php _e( 'Blog IDs', 'bonips' ); ?></label>
							<input type="text" name="bonips_network[block]" id="bonips-network-block" value="<?php echo esc_attr( $this->settings['block'] ); ?>" class="form-control" />
							<p><span class="description"><?php printf( __( 'Durch Kommas getrennte Liste von Blog-IDs, bei denen %s deaktiviert werden soll.', 'bonips' ), $name ); ?></span></p>
						</div>
					</div>
				</div>

				<?php do_action( 'bonips_network_prefs', $this ); ?>

			</div>

			<?php do_action( 'bonips_after_network_prefs', $this ); ?>

		</div>

		<?php submit_button( __( 'Netzwerkeinstellungen speichern', 'bonips' ), 'primary large', 'submit' ); ?>

	</form>	
<?php

			}

			do_action( 'bonips_bottom_network_page', $this );

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

			return apply_filters( 'bonips_save_network_prefs', $new_settings, $settings, $this->core );

		}

		/**
		 * Enable Master Template
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function enable_master_template() {

			do_action( 'bonips_master_template_enabled' );

		}

		/**
		 * Disable Master Template
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function disable_master_template() {

			do_action( 'bonips_master_template_disabled' );

		}

		/**
		 * Enable Central Logging
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function enable_central_logging() {

			do_action( 'bonips_central_logging_enabled' );

		}

		/**
		 * Disable Central Logging
		 * @since 1.7.6
		 * @version 1.0
		 */
		protected function disable_central_logging() {

			do_action( 'bonips_central_logging_disabled' );

		}

	}
endif;
