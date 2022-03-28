<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Network class
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'boniPS_Network_Module' ) ) {
	class boniPS_Network_Module {

		public $core;
		public $plug;

		/**
		 * Construct
		 */
		function __construct() {
			global $bonips_network;
			$this->core = bonips();
		}

		/**
		 * Load
		 * @since 0.1
		 * @version 1.0
		 */
		public function load() {
			add_action( 'admin_init',         array( $this, 'module_admin_init' ) );
			add_action( 'admin_head',         array( $this, 'admin_menu_styling' ) );
			add_action( 'network_admin_menu', array( $this, 'add_menu' ) );

			add_filter( 'site_option_active_sitewide_plugins', array( $this, 'network_check' ) );
		}

		/**
		 * Init
		 * @since 0.1
		 * @version 1.0
		 */
		public function module_admin_init() {
			register_setting( 'bonips_network', 'bonips_network', array( $this, 'save_network_prefs' ) );
		}

		/**
		 * Add Network Menu Items
		 * @since 0.1
		 * @version 1.2
		 */
		public function add_menu() {
			$pages[] = add_menu_page(
				__( 'BoniPress', 'bonips' ),
				__( 'BoniPress', 'bonips' ),
				'manage_network_options',
				'boniPS_Network',
				'',
				'dashicons-star-filled'
			);
			$pages[] = add_submenu_page(
				'boniPS_Network',
				__( 'Netzwerkeinstellungen', 'bonips' ),
				__( 'Netzwerkeinstellungen', 'bonips' ),
				'manage_network_options',
				'boniPS_Network',
				array( $this, 'admin_page_settings' )
			);

			foreach ( $pages as $page )
				add_action( 'admin_print_styles-' . $page, array( $this, 'admin_menu_styling' ) );
		}

		/**
		 * Network Check
		 * Blocks bonips from being used if the plugin is network wide
		 * enabled.
		 * @since 1.3
		 * @version 1.0
		 */
		public function network_check( $value ) {
			global $current_blog;
			
			$network = bonips_get_settings_network();
			if ( empty( $network['block'] ) ) return $value;
			
			$list = explode( ',', $network['block'] );
			if ( in_array( $current_blog->blog_id, $list ) ) {
				unset( $value['bonips/bonips.php'] );
			}
			
			return $value;
		}

		/**
		 * Add Admin Menu Styling
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_menu_styling() {
			global $wp_version;

			wp_enqueue_style( 'bonips-admin' );
			$image = plugins_url( 'assets/images/logo-menu.png', boniPS_THIS ); ?>

<style type="text/css">
h4:before { float:right; padding-right: 12px; font-size: 14px; font-weight: normal; color: silver; }
h4.ui-accordion-header.ui-state-active:before { content: "<?php _e( 'Klicke zum Schließen', 'bonips' ); ?>"; }
h4.ui-accordion-header:before { content: "<?php _e( 'Klicke zum Öffnen', 'bonips' ); ?>"; }
</style>
<?php
		}

		/**
		 * Load Admin Page Styling
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_print_styles() {
			if ( ! wp_style_is( 'bonips-admin', 'registered' ) ) {
				wp_register_style(
					'bonips-admin',
					plugins_url( 'assets/css/admin.css', boniPS_THIS ),
					false,
					boniPS_VERSION . '.1',
					'all'
				);
			}
			wp_enqueue_style( 'bonips-admin' );

			if ( ! wp_script_is( 'bonips-admin', 'registered' ) ) {
				wp_register_script(
					'bonips-admin',
					plugins_url( 'assets/js/accordion.js', boniPS_THIS ),
					array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ),
					boniPS_VERSION . '.1'
				);
				wp_localize_script( 'bonips-admin', 'boniPS', apply_filters( 'bonips_localize_admin', array( 'active' => '-1' ) ) );
			}
			wp_enqueue_script( 'bonips-admin' );
		}

		/**
		 * Network Settings Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page_settings() {
			// Security
			if ( ! current_user_can( 'manage_network_options' ) ) wp_die( __( 'Zugriff abgelehnt', 'bonips' ) );

			global $bonips_network;

			$prefs = bonips_get_settings_network();
			$name = bonips_label(); ?>

	<div class="wrap" id="boniPS-wrap">
		<div id="icon-boniPS" class="icon32"><br /></div>
		<h2> <?php echo sprintf( __( '%s Netzwerk', 'bonips' ), $name ); ?></h2>
		<?php
			
			// Inform user that boniPS has not yet been setup
			$setup = get_blog_option( 1, 'bonips_setup_completed', false );
			if ( $setup === false )
				echo '<div class="error"><p>' . sprintf( __( 'Hinweis! %s wurde noch nicht eingerichtet.', 'bonips' ), $name ) . '</p></div>';

			// Settings Updated
			if ( isset( $_GET['settings-updated'] ) )
				echo '<div class="updated"><p>' . __( 'Netzwerkeinstellungen aktualisiert', 'bonips' ) . '</p></div>'; ?>

<p><?php echo sprintf( __( 'Konfiguriere die Netzwerkeinstellungen für %s.', 'bonips' ), $name ); ?></p>
<form method="post" action="<?php echo admin_url( 'options.php' ); ?>" class="">
	<?php settings_fields( 'bonips_network' ); ?>

	<div class="list-items expandable-li" id="accordion">
		<h4><div class="icon icon-inactive core"></div><?php _e( 'Einstellungen', 'bonips' ); ?></h4>
		<div class="body" style="display:block;">
			<label class="subheader"><?php _e( 'Master-Vorlage', 'bonips' ); ?></label>
			<ol id="boniPS-network-settings-enabling">
				<li>
					<input type="radio" name="bonips_network[master]" id="boniPS-network-overwrite-enabled" <?php checked( $prefs['master'], 1 ); ?> value="1" /> 
					<label for="boniPS-network-"><?php _e( 'Ja', 'bonips' ); ?></label>
				</li>
				<li>
					<input type="radio" name="bonips_network[master]" id="boniPS-network-overwrite-disabled" <?php checked( $prefs['master'], 0 ); ?> value="0" /> 
					<label for="boniPS-network-"><?php _e( 'Nein', 'bonips' ); ?></label>
				</li>
				<li>
					<p class="description"><?php echo sprintf( __( "Wenn diese Option aktiviert ist, verwendet %s die Einstellungen Deiner Hauptwebseite für alle anderen Webseiten in Deinem Netzwerk.", 'bonips' ), $name ); ?></p>
				</li>
			</ol>
			<label class="subheader"><?php _e( 'Zentrale Protokollierung', 'bonips' ); ?></label>
			<ol id="boniPS-network-log-enabling">
				<li>
					<input type="radio" name="bonips_network[central]" id="boniPS-network-overwrite-log-enabled" <?php checked( $prefs['central'], 1 ); ?> value="1" /> 
					<label for="boniPS-network-"><?php _e( 'Ja', 'bonips' ); ?></label>
				</li>
				<li>
					<input type="radio" name="bonips_network[central]" id="boniPS-network-overwrite-log-disabled" <?php checked( $prefs['central'], 0 ); ?> value="0" /> 
					<label for="boniPS-network-"><?php _e( 'Nein', 'bonips' ); ?></label>
				</li>
				<li>
					<p class="description"><?php echo sprintf( __( "Wenn diese Option aktiviert ist, protokolliert %s alle Webseiten-Aktionen im Protokoll Deiner Haupt-Webseite.", 'bonips' ), $name ); ?></p>
				</li>
			</ol>
			<label class="subheader"><?php _e( 'Webseite(n) blockieren', 'bonips' ); ?></label>
			<ol id="boniPS-network-site-blocks">
				<li>
					<div class="h2"><input type="text" name="bonips_network[block]" id="boniPS-network-block" value="<?php echo $prefs['block']; ?>" class="long" /></div>
					<span class="description"><?php echo sprintf( __( 'Durch Kommas getrennte Liste von Blog-IDs, bei denen %s deaktiviert werden soll.', 'bonips' ), $name ); ?></span>
				</li>
			</ol>
			<?php do_action( 'bonips_network_prefs', $this ); ?>

		</div>
		<?php do_action( 'bonips_after_network_prefs', $this ); ?>

	</div>
	<p><?php submit_button( __( 'Netzwerkeinstellungen speichern', 'bonips' ), 'primary large', 'submit', false ); ?></p>
</form>	
<?php do_action( 'bonips_bottom_network_page', $this ); ?>

</div>
<?php
		}

		/**
		 * Save Network Settings
		 * @since 0.1
		 * @version 1.1
		 */
		public function save_network_prefs( $settings ) {

			$new_settings = array();
			$new_settings['master'] = ( isset( $settings['master'] ) ) ? $settings['master'] : 0;
			$new_settings['central'] = ( isset( $settings['central'] ) ) ? $settings['central'] : 0;
			$new_settings['block'] = sanitize_text_field( $settings['block'] );

			$new_settings = apply_filters( 'bonips_save_network_prefs', $new_settings, $settings, $this->core );

			return $new_settings;
		}
	}
}
?>