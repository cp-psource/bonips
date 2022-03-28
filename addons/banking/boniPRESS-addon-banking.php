<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Addon: Banking
 * Version: 2.0
 */
define( 'boniPRESS_BANK',              __FILE__ );
define( 'boniPRESS_BANK_DIR',          boniPRESS_ADDONS_DIR . 'banking/' );
define( 'boniPRESS_BANK_ABSTRACT_DIR', boniPRESS_BANK_DIR . 'abstracts/' );
define( 'boniPRESS_BANK_INCLUDES_DIR', boniPRESS_BANK_DIR . 'includes/' );
define( 'boniPRESS_BANK_SERVICES_DIR', boniPRESS_BANK_DIR . 'services/' );

require_once boniPRESS_BANK_ABSTRACT_DIR . 'bonipress-abstract-service.php';

require_once boniPRESS_BANK_INCLUDES_DIR . 'bonipress-banking-functions.php';

require_once boniPRESS_BANK_SERVICES_DIR . 'bonipress-service-central.php';
require_once boniPRESS_BANK_SERVICES_DIR . 'bonipress-service-interest.php';
require_once boniPRESS_BANK_SERVICES_DIR . 'bonipress-service-payouts.php';

/**
 * boniPRESS_Banking_Module class
 * @since 0.1
 * @version 2.0
 */
if ( ! class_exists( 'boniPRESS_Banking_Module' ) ) :
	class boniPRESS_Banking_Module extends boniPRESS_Module {

		/**
		 * Constructor
		 */
		public function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPRESS_Banking_Module', array(
				'module_name' => 'banking',
				'option_id'   => 'bonipress_pref_bank',
				'defaults'    => array(
					'active'        => array(),
					'services'      => array(),
					'service_prefs' => array()
				),
				'labels'      => array(
					'menu'        => __( 'Banking', 'bonipress' ),
					'page_title'  => __( 'Banking', 'bonipress' ),
					'page_header' => __( 'Banking', 'bonipress' )
				),
				'screen_id'   => BONIPS_SLUG . '-banking',
				'accordion'   => true,
				'menu_pos'    => 60
			), $type );

		}

		/**
		 * Load Services
		 * @since 1.2
		 * @version 1.0
		 */
		public function module_init() {

			if ( ! empty( $this->services ) ) {

				foreach ( $this->services as $key => $gdata ) {

					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'run', $gdata['callback'] );
					}

				}

			}

			add_action( 'wp_ajax_run-bonipress-bank-service', array( $this, 'ajax_handler' ) );

		}

		/**
		 * Module Admin Init
		 * @since 1.7
		 * @version 1.0
		 */
		public function module_admin_init() {

			// User Override
			add_action( 'bonipress_user_edit_after_' . $this->bonipress_type, array( $this, 'banking_user_screen' ), 20 );

		}

		/**
		 * Call
		 * Either runs a given class method or function.
		 * @since 1.2
		 * @version 1.2
		 */
		public function call( $call, $callback, $return = NULL ) {

			// Class
			if ( is_array( $callback ) && class_exists( $callback[0] ) ) {

				$class = $callback[0];
				$methods = get_class_methods( $class );
				if ( in_array( $call, $methods ) ) {

					$new = new $class( ( isset( $this->service_prefs ) ) ? $this->service_prefs : array(), $this->bonipress_type );
					return $new->$call( $return );

				}

			}

			// Function
			elseif ( ! is_array( $callback ) ) {

				if ( function_exists( $callback ) ) {

					if ( $return !== NULL )
						return call_user_func( $callback, $return, $this );
					else
						return call_user_func( $callback, $this );

				}

			}

			if ( $return !== NULL )
				return array();

		}

		/**
		 * Get Bank Services
		 * @since 1.2
		 * @version 1.0
		 */
		public function get( $save = false ) {

			// Savings
			$services['central'] = array(
				'title'        => __( 'Zentralbank', 'bonipress' ),
				'description'  => __( 'Anstatt %_plural% aus dem Nichts zu erstellen, werden alle Auszahlungen von einem nominierten "Zentralbank"-Konto vorgenommen. Alle %_plural%, die ein Benutzer ausgibt oder verliert, werden wieder auf dieses Konto eingezahlt. Wenn der Zentralbank %_plural% ausgeht, wird kein %_plural% ausgezahlt.', 'bonipress' ),
				'cron'         => false,
				'icon'         => 'dashicons-admin-site',
				'callback'     => array( 'boniPRESS_Banking_Service_Central' )
			);

			// Interest
			$services['interest'] = array(
				'title'        => __( 'Zinseszins', 'bonipress' ),
				'description'  => __( 'Biete Deinen Benutzern Interesse an dem %_plural%, den sie auf Deiner Webseite verdienen. Die Zinsen werden täglich berechnet.', 'bonipress' ),
				'cron'         => true,
				'icon'         => 'dashicons-vault',
				'callback'     => array( 'boniPRESS_Banking_Service_Interest' )
			);

			// Inflation
			$services['payouts'] = array(
				'title'       => __( 'Wiederkehrende Auszahlungen', 'bonipress' ),
				'description' => __( 'Richte Massen %_singular% Auszahlungen für Deine Benutzer ein.', 'bonipress' ),
				'cron'        => true,
				'icon'         => 'dashicons-update',
				'callback'    => array( 'boniPRESS_Banking_Service_Payouts' )
			);

			$services = apply_filters( 'bonipress_setup_banking', $services );

			if ( $save === true && $this->core->user_is_point_admin() ) {
				$new_data = array(
					'active'        => $this->active,
					'services'      => $services,
					'service_prefs' => $this->service_prefs
				);
				bonipress_update_option( $this->option_id, $new_data );
			}

			$this->services = $services;
			return $services;

		}

		/**
		 * Page Header
		 * @since 1.3
		 * @version 1.0
		 */
		public function settings_header() {

			$banking_icons = plugins_url( 'assets/images/gateway-icons.png', boniPRESS_THIS );

			wp_enqueue_style( 'bonipress-bootstrap-grid' );
			wp_enqueue_style( 'bonipress-forms' );

			wp_register_script( 'bonipress-bank-manage-schedules', plugins_url( 'assets/js/manage-schedules.js', boniPRESS_BANK ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'jquery-effects-core', 'jquery-effects-slide', 'jquery-numerator' ), boniPRESS_VERSION );

			wp_localize_script(
				'bonipress-bank-manage-schedules',
				'Banking',
				array(
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'token'          => wp_create_nonce( 'run-bonipress-bank-task' . $this->bonipress_type ),
					'new'            => esc_attr__( 'Neue wiederkehrende Auszahlung', 'bonipress' ),
					'edit'           => esc_attr__( 'Wiederkehrende Auszahlung bearbeiten', 'bonipress' ),
					'close'          => esc_attr__( 'Schließen', 'bonipress' ),
					'emptyfields'    => esc_attr__( 'Bitte fülle alle erforderlichen Felder aus, die rot hervorgehoben sind.', 'bonipress' ),
					'confirmremoval' => esc_attr__( 'Möchtest Du diesen Zeitplan wirklich entfernen? Das kann nicht rückgängig gemacht werden!', 'bonipress' )
				)
			);
			wp_enqueue_script( 'bonipress-bank-manage-schedules' );

?>
<style type="text/css">
.bonipress-update-balance { font-family: "Open Sans",sans-serif; background-color: white !important; z-index: 9999 !important; border: none !important; border-radius: 0 !important; background-image: none !important; padding: 0 !important; overflow: visible !important; }
.bonipress-update-balance.ui-dialog .ui-dialog-content { padding: 0 0 0 0; }
.bonipress-update-balance .ui-widget-header { border: none !important; background: transparent !important; font-weight: normal !important; }
.bonipress-update-balance .ui-dialog-titlebar { line-height: 24px !important; border-bottom: 1px solid #ddd !important; border-left: none; border-top: none; border-right: none; padding: 12px !important; border-radius: 0 !important; }
.bonipress-update-balance .ui-dialog-titlebar:hover { cursor: move; }
.bonipress-update-balance .ui-dialog-titlebar-close { float: right; margin: 0 12px 0 0; background: 0 0; border: none; -webkit-box-shadow: none; box-shadow: none; color: #666; cursor: pointer; display: block; padding: 0; position: absolute; top: 0; right: 0; width: 36px; height: 36px; text-align: center; font-size: 13px; line-height: 26px; vertical-align: top; white-space: nowrap; }
.bonipress-update-balance .ui-icon { display: none !important; }
.bonipress-update-balance .ui-button:focus, .bonipress-update-balance .ui-button:active { outline: none !important; }
.bonipress-update-balance .ui-button .ui-button-text { display: block; text-indent: 0; }
.bonipress-update-balance .ui-dialog-title { font-size: 22px; font-weight: 600; margin: 0 0 0 0; width: auto !important; float: none !important; }
.ui-widget-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: repeat-x scroll 70% 70% #000; opacity: 0.7; overflow: hidden; background: repeat-x scroll 70% 70% #000; z-index: 99; }
#manage-recurring-schedule { min-height: 4px !important; background-color: #f3f3f3; }
#manage-recurring-schedule-form h3 { margin: 0 0 12px 0; }
.bonipress-metabox .form .has-error .form-control { border-color: #dc3232; }
.alert { padding: 24px; }
.alert-warning { background-color: #dc3232; color: white; }
.alert-success { background-color: #46b450; color: white; }
</style>
<?php

		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.1
		 */
		public function admin_page() {

			// Security
			if ( ! $this->core->user_is_point_admin() ) wp_die( 'Access Denied' );

			// Get installed
			$installed = $this->get();

?>
<div class="wrap bonipress-metabox" id="boniPRESS-wrap">

	<?php $this->update_notice(); ?>

	<h1><?php _e( 'Bankdienstleistungen', 'bonipress' ); ?></h1>
	<form method="post" class="form" action="options.php">

		<?php settings_fields( $this->settings_name ); ?>

		<!-- Loop though Services -->
		<div class="list-items expandable-li" id="accordion">
<?php

			// Installed Services
			if ( ! empty( $installed ) ) {
				foreach ( $installed as $key => $data ) {

?>
			<h4><span class="dashicons <?php echo $data['icon']; ?><?php if ( $this->is_active( $key ) ) echo ' active'; else echo ' static'; ?>"></span><?php echo $this->core->template_tags_general( $data['title'] ); ?></h4>
			<div class="body" style="display: none;">
				<p><?php echo nl2br( $this->core->template_tags_general( $data['description'] ) ); ?></p>
				<?php if ( $data['cron'] && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
				<div class="alert alert-warning"><strong><?php _e( 'Warning', 'bonipress' ); ?></strong> - <?php _e( 'Dieser Bankdienst verwendet WordPress CRON, um Ereignisse zu planen. Wenn WordPress CRON deaktiviert ist, funktioniert dieser Dienst nicht richtig.', 'bonipress' ); ?></div>
				<?php endif; ?>
				<label class="subheader"><?php _e( 'Enable', 'bonipress' ); ?></label>
				<ol>
					<li>
						<input type="checkbox" name="<?php echo $this->option_id; ?>[active][]" id="bonipress-bank-service-<?php echo $key; ?>" value="<?php echo $key; ?>"<?php if ( $this->is_active( $key ) ) echo ' checked="checked"'; ?> />
					</li>
				</ol>

				<?php $this->call( 'preferences', $data['callback'] ); ?>

			</div>
<?php

				}
			}

?>

		</div>

		<?php submit_button( __( 'Änderungen aktualisieren', 'bonipress' ), 'primary large', 'submit', false ); ?>

	</form>
</div>
<style type="text/css">
body .ui-dialog.ui.widget { height: auto !important; }
</style>
<div id="manage-recurring-schedule" style="display: none;">
	<div class="bonipress-container">
		<form class="form" method="post" action="" id="manage-recurring-schedule-form"></form>
		<div id="bonipress-processing"><div class="loading-indicator"></div></div>
	</div>
</div>
<?php

		}

		/**
		 * Sanititze Settings
		 * @since 1.2
		 * @version 1.1
		 */
		public function sanitize_settings( $post ) {

			$installed            = $this->get();

			// Construct new settings
			$new_post             = array();
			$new_post['services'] = $installed;

			if ( empty( $post['active'] ) || ! isset( $post['active'] ) )
				$post['active'] = array();

			$new_post['active']   = $post['active'];

			// Loop though all installed hooks
			if ( ! empty( $installed ) ) {
				foreach ( $installed as $key => $data ) {

					if ( isset( $data['callback'] ) && isset( $post['service_prefs'][ $key ] ) ) {

						// Old settings
						$old_settings = $post['service_prefs'][ $key ];

						// New settings
						$new_settings = $this->call( 'sanitise_preferences', $data['callback'], $old_settings );

						// If something went wrong use the old settings
						if ( empty( $new_settings ) || $new_settings === NULL || ! is_array( $new_settings ) )
							$new_post['service_prefs'][ $key ] = $old_settings;
						// Else we got ourselves new settings
						else
							$new_post['service_prefs'][ $key ] = $new_settings;

						// Handle de-activation
						if ( in_array( $key, (array) $this->active ) && ! in_array( $key, $new_post['active'] ) )
							$this->call( 'deactivate', $data['callback'], $new_post['service_prefs'][ $key ] );

						// Handle activation
						if ( ! in_array( $key, (array) $this->active ) && in_array( $key, $new_post['active'] ) )
							$this->call( 'activate', $data['callback'], $new_post['service_prefs'][ $key ] );

						// Next item

					}

				}
			}

			return $new_post;

		}

		/**
		 * User Screen
		 * @since 1.7
		 * @version 1.0
		 */
		public function banking_user_screen( $user ) {

			if ( ! empty( $this->services ) ) {

				foreach ( $this->services as $key => $gdata ) {

					if ( $this->is_active( $key ) && isset( $gdata['callback'] ) ) {
						$this->call( 'user_screen', $gdata['callback'], $user );
					}

				}

			}

		}

		/**
		 * Ajax Handler
		 * @since 1.7
		 * @version 1.0
		 */
		public function ajax_handler() {

			// Make sure this is an ajax call for this point type
			if ( isset( $_REQUEST['_token'] ) && wp_verify_nonce( $_REQUEST['_token'], 'run-bonipress-bank-task' . $this->bonipress_type ) ) {

				// Make sure ajax call is made by an admin
				if ( $this->core->user_is_point_admin() ) {

					// Get the service requesting to use this
					$service   = sanitize_key( $_POST['service'] );
					$installed = $this->get();

					// If there is such a service, load it's ajax handler
					if ( array_key_exists( $service, $installed ) )
						$this->call( 'ajax_handler', $installed[ $service ]['callback'] );

				}

			}

		}

	}
endif;

/**
 * Load Banking Module
 * @since 1.2
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_load_banking_addon' ) ) :
	function bonipress_load_banking_addon( $modules, $point_types ) {

		foreach ( $point_types as $type => $title ) {
			$modules['type'][ $type ]['banking'] = new boniPRESS_Banking_Module( $type );
			$modules['type'][ $type ]['banking']->load();
		}

		return $modules;

	}
endif;
add_filter( 'bonipress_load_modules', 'bonipress_load_banking_addon', 20, 2 );
