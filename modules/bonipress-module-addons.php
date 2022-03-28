<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Addons_Module class
 * @since 0.1
 * @version 1.1.1
 */
if ( ! class_exists( 'boniPRESS_Addons_Module' ) ) :
	class boniPRESS_Addons_Module extends boniPRESS_Module {

		/**
		 * Construct
		 */
		public function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPRESS_Addons_Module', array(
				'module_name' => 'addons',
				'option_id'   => 'bonipress_pref_addons',
				'defaults'    => array(
					'installed'     => array(),
					'active'        => array()
				),
				'labels'      => array(
					'menu'        => __( 'Erweiterungen', 'bonipress' ),
					'page_title'  => __( 'Erweiterungen', 'bonipress' )
				),
				'screen_id'   => BONIPS_SLUG . '-addons',
				'accordion'   => true,
				'menu_pos'    => 30
			), $type );

		}

		/**
		 * Admin Init
		 * Catch activation and deactivations
		 * @since 0.1
		 * @version 1.2.2
		 */
		public function module_admin_init() {

			// Handle actions
			if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) && isset( $_GET['_token'] ) && wp_verify_nonce( $_GET['_token'], 'bonipress-activate-deactivate-addon' ) && $this->core->user_is_point_admin() ) {

				$addon_id = sanitize_text_field( $_GET['addon_id'] );
				$action   = sanitize_text_field( $_GET['addon_action'] );

				$this->get();
				if ( array_key_exists( $addon_id, $this->installed ) ) {

					// Activation
					if ( $action == 'activate' ) {
						// Add addon id to the active array
						$this->active[] = $addon_id;
						$result         = 1;
					}

					// Deactivation
					elseif ( $action == 'deactivate' ) {
						// Remove addon id from the active array
						$index = array_search( $addon_id, $this->active );
						if ( $index !== false ) {
							unset( $this->active[ $index ] );
							$result = 0;
						}

						// Run deactivation now before the file is no longer included
						do_action( 'bonipress_addon_deactivation_' . $addon_id );
					}

					$new_settings = array(
						'installed' => $this->installed,
						'active'    => $this->active
					);

					bonipress_update_option( 'bonipress_pref_addons', $new_settings );

					$url = add_query_arg( array( 'page' => BONIPS_SLUG . '-addons', 'activated' => $result ), admin_url( 'admin.php' ) );

					wp_safe_redirect( $url );
					exit;

				}

			}

		}

		/**
		 * Run Addons
		 * Catches all add-on activations and deactivations and loads addons
		 * @since 0.1
		 * @version 1.2
		 */
		public function run_addons() {

			// Make sure each active add-on still exists. If not delete.
			if ( ! empty( $this->active ) ) {
				$active = array_unique( $this->active );
				$_active = array();
				foreach ( $active as $pos => $active_id ) {
					if ( array_key_exists( $active_id, $this->installed ) ) {
						$_active[] = $active_id;
					}
				}
				$this->active = $_active;
			}

			// Load addons
			foreach ( $this->installed as $key => $data ) {
				if ( $this->is_active( $key ) ) {

					if ( apply_filters( 'bonipress_run_addon', true, $key, $data, $this ) === false || apply_filters( 'bonipress_run_addon_' . $key, true, $data, $this ) === false ) continue;

					// Core add-ons we know where they are
					if ( file_exists( boniPRESS_ADDONS_DIR . $key . '/boniPRESS-addon-' . $key . '.php' ) )
						include_once boniPRESS_ADDONS_DIR . $key . '/boniPRESS-addon-' . $key . '.php';

					// If path is set, load the file
					elseif ( isset( $data['path'] ) && file_exists( $data['path'] ) )
						include_once $data['path'];

					else {
						continue;
					}

					// Check for activation
					if ( $this->is_activation( $key ) )
						do_action( 'bonipress_addon_activation_' . $key );

				}
			}

		}

		/**
		 * Is Activation
		 * @since 0.1
		 * @version 1.0
		 */
		public function is_activation( $key ) {

			if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) && $_GET['addon_action'] == 'activate' && $_GET['addon_id'] == $key )
				return true;

			return false;

		}

		/**
		 * Is Deactivation
		 * @since 0.1
		 * @version 1.0
		 */
		public function is_deactivation( $key ) {

			if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) && $_GET['addon_action'] == 'deactivate' && $_GET['addon_id'] == $key )
				return true;

			return false;

		}

		/**
		 * Get Addons
		 * @since 0.1
		 * @version 1.7.2
		 */
		public function get( $save = false ) {

			$installed = array();

			// Badges Add-on
			$installed['badges'] = array(
				'name'        => 'Abzeichen',
				'description' => __( 'Verleihe Deinen Benutzern Abzeichen basierend auf ihrer Interaktion mit Deiner Webseite.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-abzeichen/',
				'version'     => '1.4',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/badges-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// Banking Add-on
			$installed['banking'] = array(
				'name'        => 'Banking',
				'description' => __( 'Richte wiederkehrende Auszahlungen oder Angebots-/Gebührenzinsen für Benutzerkontoguthaben ein.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-banking/',
				'version'     => '2.0',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/banking-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// buyCRED Add-on
			$installed['buy-creds'] = array(
				'name'        => 'Kreditkauf',
				'description' => __( 'Mit der <strong>Kreditkauf</strong> Erweiterung können Deine Benutzer Punkte mit PayPal, Skrill (Moneybookers) oder NETbilling kaufen. Mit <strong>Kreditkauf</strong> können Deine Benutzer auch Punkte für andere Mitglieder kaufen.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterung-kreditkauf/',
				'version'     => '1.5',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/buy-creds-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// Coupons Add-on
			$installed['coupons'] = array(
				'name'        => 'Gutscheine',
				'description' => __( 'Mit der Gutschein-Erweiterung kannst Du Gutscheine erstellen, mit denen Benutzer ihren Konten Punkte hinzufügen können.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-gutscheine/',
				'version'     => '1.4',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/coupons-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// Email Notices Add-on
			$installed['email-notices'] = array(
				'name'        => 'E-Mail Benachrichtigungen',
				'description' => __( 'Erstelle E-Mail-Benachrichtigungen für jede Art von boniPRESS-Instanz.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-e-mail-benachrichtigungen/',
				'version'     => '1.4',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/email-notifications-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// Gateway Add-on
			$installed['gateway'] = array(
				'name'        => 'Gateway',
				'description' => __( 'Lasse Deine Benutzer mit ihrem <strong>BoniPress</strong> Punkteguthaben bezahlen. Unterstützte Einkaufswagen: WooCommerce, PSeCommerce und WP E-Commerce. Unterstützte Eventbuchungen: Event Espresso und Events Manager (kostenlos&pro).', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-gateway/',
				'version'     => '1.4',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/gateway-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// Notifications Add-on
			$installed['notifications'] = array(
				'name'        => 'Benachrichtigungen',
				'description' => __( 'Erstelle Popup-Benachrichtigungen, wenn Benutzer Punkte gewinnen oder verlieren.', 'bonipress' ),
				'addon_url'   => 'http://codex.bonipress.me/chapter-iii/notifications/',
				'version'     => '1.1.2',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				//'pro_url'     => 'https://n3rds.work/docs/bonipress-erweiterungen-benachrichtigungen/',
				'screenshot'  =>  plugins_url( 'assets/images/notifications-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// Ranks Add-on
			$installed['ranks'] = array(
				'name'        => 'Ränge',
				'description' => __( 'Erstelle Ränge für Benutzer, die eine bestimmte Anzahl von %_plural% erreichen, mit der Option, Logos für jeden Rang hinzuzufügen.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-raenge/',
				'version'     => '1.6',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/ranks-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			// Sell Content Add-on
			$installed['sell-content'] = array(
				'name'        => 'Inhalt verkaufen',
				'description' => __( 'Mit dieser Erweiterung kannst Du Beiträge, Seiten oder andere öffentliche Beitragstypen auf Deiner Webseite verkaufen. Du kannst entweder den gesamten Inhalt verkaufen oder mit unserem Shortcode Teile Deines Inhalts verkaufen, um "Teaser" anzubieten.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-inhalt-verkaufen/',
				'version'     => '2.0.1',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/sell-content-addon.png', boniPRESS_THIS ),
				'requires'    => array( 'log' )
			);

			// Statistics Add-on
			$installed['stats'] = array(
				'name'        => 'Statistiken',
				'description' => __( 'Ermöglicht Dir den Zugriff auf Deine BoniPress-Statistiken basierend auf den Gewinnen und Verlusten Deiner Benutzer.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-stastiken/',
				'version'     => '2.0',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				'screenshot'  => plugins_url( 'assets/images/statistics-addon.png', boniPRESS_THIS )
			);

			// Transfer Add-on
			$installed['transfer'] = array(
				'name'        => 'Transaktionen',
				'description' => __( 'Ermögliche Deinen Benutzern, Punkte an andere Mitglieder zu senden oder zu "spenden", indem Du entweder den Shortcode bonipress_transfer oder das Widget BoniPress Transfer verwendest.', 'bonipress' ),
				'addon_url'   => 'https://n3rds.work/docs/bonipress-erweiterungen-transaktionen/',
				'version'     => '1.6',
				'author'      => 'DerN3rd',
				'author_url'  => 'https://n3rds.work',
				//'pro_url'     => 'https://bonipress.me/store/transfer-plus/',
				'screenshot'  => plugins_url( 'assets/images/transfer-addon.png', boniPRESS_THIS ),
				'requires'    => array()
			);

			$installed = apply_filters( 'bonipress_setup_addons', $installed );

			if ( $save === true && $this->core->user_is_point_admin() ) {
				$new_data = array(
					'active'    => $this->active,
					'installed' => $installed
				);
				bonipress_update_option( 'bonipress_pref_addons', $new_data );
			}

			$this->installed = $installed;
			return $installed;

		}

		/**
		 * Admin Page
		 * @since 0.1
		 * @version 1.2.2
		 */
		public function admin_page() {

			// Security
			if ( ! $this->core->user_is_point_admin() ) wp_die( 'Access Denied' );

			$installed = $this->get( true );

?>
<style type="text/css">
#boniPRESS-wrap > h1 { margin-bottom: 15px; }
.theme-browser .theme:focus, .theme-browser .theme:hover { cursor: default !important; }
.theme-browser .theme:hover .more-details { opacity: 1; }
.theme-browser .theme:hover a.more-details, .theme-browser .theme:hover a.more-details:hover { text-decoration: none; }
</style>
<div class="wrap" id="boniPRESS-wrap">
	<h1><?php _e( 'Erweiterungen', 'bonipress' ); if ( BONIPS_DEFAULT_LABEL === 'boniPRESS' ) : ?> <a href="https://n3rds.work/docs/bonipress-erweiterungen-uebersicht/" class="page-title-action" target="_blank"><?php _e( 'Dokumentation', 'bonipress' ); ?></a><?php endif; ?></h1>
<?php

			// Messages
			if ( isset( $_GET['activated'] ) ) {

				if ( $_GET['activated'] == 1 )
					echo '<div id="message" class="updated"><p>' . __( 'Erweiterung Aktiviert', 'bonipress' ) . '</p></div>';

				elseif ( $_GET['activated'] == 0 )
					echo '<div id="message" class="error"><p>' . __( 'Erweiterung Deaktiviert', 'bonipress' ) . '</p></div>';

			}

?>
	<div class="theme-browser">
		<div class="themes">
<?php

			// Loop though installed
			if ( ! empty( $installed ) ) {

				foreach ( $installed as $key => $data ) {

					$aria_action = esc_attr( $key . '-action' );
					$aria_name   = esc_attr( $key . '-name' );

?>
			<div class="theme<?php if ( $this->is_active( $key ) ) echo ' active'; else echo ' inactive'; ?>" tabindex="0" aria-describedby="<?php echo $aria_action . ' ' . $aria_name; ?>">

				<?php if ( $data['screenshot'] != '' ) : ?>

				<div class="theme-screenshot">
					<img src="<?php echo $data['screenshot']; ?>" alt="" />
				</div>

				<?php else : ?>

				<div class="theme-screenshot blank"></div>

				<?php endif; ?>

				<a class="more-details" id="<?php echo $aria_action; ?>" href="<?php echo $data['addon_url']; ?>" target="_blank"><?php _e( 'Dokumentation', 'bonipress' ); ?></a>

				<div class="theme-id-container">

					<?php if ( $this->is_active( $key ) ) : ?>

					<h2 class="theme-name" id="<?php echo $aria_name; ?>"><?php echo $this->core->template_tags_general( $data['name'] ); ?></h2>

					<?php else : ?>

					<h2 class="theme-name" id="<?php echo $aria_name; ?>"><?php echo $this->core->template_tags_general( $data['name'] ); ?></h2>

					<?php endif; ?>

					<div class="theme-actions">

					<?php echo $this->activate_deactivate( $key ); ?>

					</div>

				</div>

			</div>
<?php

				}

				if ( BONIPS_SHOW_PREMIUM_ADDONS ) echo '<div class="theme add-new-theme"><a href="https://n3rds.work/shop/artikel/category/bonipress-erweiterungen/" target="_blank"><div class="theme-screenshot"><span></span></div><h2 class="theme-name">Weitere Erweiterungen hinzufügen</h2></a></div><br class="clear" />';

			}

?>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Activate / Deactivate Button
		 * @since 0.1
		 * @version 1.2
		 */
		public function activate_deactivate( $addon_id = NULL ) {

			$link_url  = get_bonipress_addon_activation_url( $addon_id );
			$link_text = __( 'Aktivieren', 'bonipress' );

			// Deactivate
			if ( $this->is_active( $addon_id ) ) {

				$link_url  = get_bonipress_addon_deactivation_url( $addon_id );
				$link_text = __( 'Deaktivieren', 'bonipress' );

			}

			return '<a href="' . esc_url_raw( $link_url ) . '" title="' . esc_attr( $link_text ) . '" class="button button-primary bonipress-action ' . esc_attr( $addon_id ) . '">' . esc_html( $link_text ) . '</a>';

		}

	}
endif;

/**
 * Get Activate Add-on Link
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'get_bonipress_addon_activation_url' ) ) :
	function get_bonipress_addon_activation_url( $addon_id = NULL, $deactivate = false ) {

		if ( $addon_id === NULL ) return '#';

		$args = array(
			'page'         => BONIPS_SLUG . '-addons',
			'addon_id'     => $addon_id,
			'addon_action' => ( ( $deactivate === false ) ? 'activate' : 'deactivate' ),
			'_token'       => wp_create_nonce( 'bonipress-activate-deactivate-addon' )
		);

		return esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) );

	}
endif;

/**
 * Get Deactivate Add-on Link
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'get_bonipress_addon_deactivation_url' ) ) :
	function get_bonipress_addon_deactivation_url( $addon_id = NULL ) {

		if ( $addon_id === NULL ) return '#';

		return get_bonipress_addon_activation_url( $addon_id, true );

	}
endif;
