<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Network class
 * @since 0.1
 * @version 1.1
 */
if ( ! class_exists( 'boniPRESS_Network_Module' ) ) {
	class boniPRESS_Network_Module {

		public $core;
		public $plug;

		/**
		 * Construct
		 */
		function __construct() {
			global $bonipress_network;
			$this->core = bonipress();
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
			register_setting( 'bonipress_network', 'bonipress_network', array( $this, 'save_network_prefs' ) );
		}

		/**
		 * Add Network Menu Items
		 * @since 0.1
		 * @version 1.2
		 */
		public function add_menu() {
			$pages[] = add_menu_page(
				__( 'BoniPress', 'bonipress' ),
				__( 'BoniPress', 'bonipress' ),
				'manage_network_options',
				'boniPRESS_Network',
				'',
				'dashicons-star-filled'
			);
			$pages[] = add_submenu_page(
				'boniPRESS_Network',
				__( 'Netzwerkeinstellungen', 'bonipress' ),
				__( 'Netzwerkeinstellungen', 'bonipress' ),
				'manage_network_options',
				'boniPRESS_Network',
				array( $this, 'admin_page_settings' )
			);

			foreach ( $pages as $page )
				add_action( 'admin_print_styles-' . $page, array( $this, 'admin_menu_styling' ) );
		}

		/**
		 * Network Check
		 * Blocks bonipress from being used if the plugin is network wide
		 * enabled.
		 * @since 1.3
		 * @version 1.0
		 */
		public function network_check( $value ) {
			global $current_blog;
			
			$network = bonipress_get_settings_network();
			if ( empty( $network['block'] ) ) return $value;
			
			$list = explode( ',', $network['block'] );
			if ( in_array( $current_blog->blog_id, $list ) ) {
				unset( $value['bonipress/bonipress.php'] );
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

			wp_enqueue_style( 'bonipress-admin' );
			$image = plugins_url( 'assets/images/logo-menu.png', boniPRESS_THIS ); ?>

<style type="text/css">
h4:before { float:right; padding-right: 12px; font-size: 14px; font-weight: normal; color: silver; }
h4.ui-accordion-header.ui-state-active:before { content: "<?php _e( 'Klicke zum Schließen', 'bonipress' ); ?>"; }
h4.ui-accordion-header:before { content: "<?php _e( 'Klicke zum Öffnen', 'bonipress' ); ?>"; }
</style>
<?php
		}

		/**
		 * Load Admin Page Styling
		 * @since 0.1
		 * @version 1.0
		 */
		public function admin_print_styles() {
			if ( ! wp_style_is( 'bonipress-admin', 'registered' ) ) {
				wp_register_style(
					'bonipress-admin',
					plugins_url( 'assets/css/admin.css', boniPRESS_THIS ),
					false,
					boniPRESS_VERSION . '.1',
					'all'
				);
			}
			wp_enqueue_style( 'bonipress-admin' );

			if ( ! wp_script_is( 'bonipress-admin', 'registered' ) ) {
				wp_register_script(
					'bonipress-admin',
					plugins_url( 'assets/js/accordion.js', boniPRESS_THIS ),
					array( 'jquery', 'jquery-ui-core', 'jquery-ui-accordion' ),
					boniPRESS_VERSION . '.1'
				);
				wp_localize_script( 'bonipress-admin', 'boniPRESS', apply_filters( 'bonipress_localize_admin', array( 'active' => '-1' ) ) );
			}
			wp_enqueue_script( 'bonipress-admin' );
		}

		/**
		 * Network Settings Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page_settings() {
			// Security
			if ( ! current_user_can( 'manage_network_options' ) ) wp_die( __( 'Zugriff abgelehnt', 'bonipress' ) );

			global $bonipress_network;

			$prefs = bonipress_get_settings_network();
			$name = bonipress_label(); ?>

	<div class="wrap" id="boniPRESS-wrap">
		<div id="icon-boniPRESS" class="icon32"><br /></div>
		<h2> <?php echo sprintf( __( '%s Netzwerk', 'bonipress' ), $name ); ?></h2>
		<?php
			
			// Inform user that boniPRESS has not yet been setup
			$setup = get_blog_option( 1, 'bonipress_setup_completed', false );
			if ( $setup === false )
				echo '<div class="error"><p>' . sprintf( __( 'Hinweis! %s wurde noch nicht eingerichtet.', 'bonipress' ), $name ) . '</p></div>';

			// Settings Updated
			if ( isset( $_GET['settings-updated'] ) )
				echo '<div class="updated"><p>' . __( 'Netzwerkeinstellungen aktualisiert', 'bonipress' ) . '</p></div>'; ?>

<p><?php echo sprintf( __( 'Konfiguriere die Netzwerkeinstellungen für %s.', 'bonipress' ), $name ); ?></p>
<form method="post" action="<?php echo admin_url( 'options.php' ); ?>" class="">
	<?php settings_fields( 'bonipress_network' ); ?>

	<div class="list-items expandable-li" id="accordion">
		<h4><div class="icon icon-inactive core"></div><?php _e( 'Einstellungen', 'bonipress' ); ?></h4>
		<div class="body" style="display:block;">
			<label class="subheader"><?php _e( 'Master-Vorlage', 'bonipress' ); ?></label>
			<ol id="boniPRESS-network-settings-enabling">
				<li>
					<input type="radio" name="bonipress_network[master]" id="boniPRESS-network-overwrite-enabled" <?php checked( $prefs['master'], 1 ); ?> value="1" /> 
					<label for="boniPRESS-network-"><?php _e( 'Ja', 'bonipress' ); ?></label>
				</li>
				<li>
					<input type="radio" name="bonipress_network[master]" id="boniPRESS-network-overwrite-disabled" <?php checked( $prefs['master'], 0 ); ?> value="0" /> 
					<label for="boniPRESS-network-"><?php _e( 'Nein', 'bonipress' ); ?></label>
				</li>
				<li>
					<p class="description"><?php echo sprintf( __( "Wenn diese Option aktiviert ist, verwendet %s die Einstellungen Deiner Hauptwebseite für alle anderen Webseiten in Deinem Netzwerk.", 'bonipress' ), $name ); ?></p>
				</li>
			</ol>
			<label class="subheader"><?php _e( 'Zentrale Protokollierung', 'bonipress' ); ?></label>
			<ol id="boniPRESS-network-log-enabling">
				<li>
					<input type="radio" name="bonipress_network[central]" id="boniPRESS-network-overwrite-log-enabled" <?php checked( $prefs['central'], 1 ); ?> value="1" /> 
					<label for="boniPRESS-network-"><?php _e( 'Ja', 'bonipress' ); ?></label>
				</li>
				<li>
					<input type="radio" name="bonipress_network[central]" id="boniPRESS-network-overwrite-log-disabled" <?php checked( $prefs['central'], 0 ); ?> value="0" /> 
					<label for="boniPRESS-network-"><?php _e( 'Nein', 'bonipress' ); ?></label>
				</li>
				<li>
					<p class="description"><?php echo sprintf( __( "Wenn diese Option aktiviert ist, protokolliert %s alle Webseiten-Aktionen im Protokoll Deiner Haupt-Webseite.", 'bonipress' ), $name ); ?></p>
				</li>
			</ol>
			<label class="subheader"><?php _e( 'Webseite(n) blockieren', 'bonipress' ); ?></label>
			<ol id="boniPRESS-network-site-blocks">
				<li>
					<div class="h2"><input type="text" name="bonipress_network[block]" id="boniPRESS-network-block" value="<?php echo $prefs['block']; ?>" class="long" /></div>
					<span class="description"><?php echo sprintf( __( 'Durch Kommas getrennte Liste von Blog-IDs, bei denen %s deaktiviert werden soll.', 'bonipress' ), $name ); ?></span>
				</li>
			</ol>
			<?php do_action( 'bonipress_network_prefs', $this ); ?>

		</div>
		<?php do_action( 'bonipress_after_network_prefs', $this ); ?>

	</div>
	<p><?php submit_button( __( 'Netzwerkeinstellungen speichern', 'bonipress' ), 'primary large', 'submit', false ); ?></p>
</form>	
<?php do_action( 'bonipress_bottom_network_page', $this ); ?>

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

			$new_settings = apply_filters( 'bonipress_save_network_prefs', $new_settings, $settings, $this->core );

			return $new_settings;
		}
	}
}
?>