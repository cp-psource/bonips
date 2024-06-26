<?php
if ( ! defined( 'BONIPS_PURCHASE' ) ) exit;

/**
 * boniPS_buyCRED_Module class
 * @since 0.1
 * @version 1.4.1
 */
if ( ! class_exists( 'boniPS_buyCRED_Module' ) ) :
	class boniPS_buyCRED_Module extends boniPS_Module {

		public $purchase_log = '';

		/**
		 * Construct
		 */
		function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPS_BuyCRED_Module', array(
				'module_name' => 'gateways',
				'option_id'   => 'bonips_pref_buycreds',
				'defaults'    => array(
					'installed'     => array(),
					'active'        => array(),
					'gateway_prefs' => array()
				),
				'labels'      => array(
					'menu'        => __( 'Zahlungs-Gateways', 'bonips' ),
					'page_title'  => __( 'Zahlungs-Gateways', 'bonips' ),
					'page_header' => __( 'Zahlungs-Gateways', 'bonips' )
				),
				'screen_id'   => BONIPS_SLUG . '-gateways',
				'accordion'   => true,
				'add_to_core' => true,
				'menu_pos'    => 70
			), $type );

			$this->bonips_type = BONIPS_DEFAULT_TYPE_KEY;

		}

		/**
		 * Load
		 * @version 1.0.2
		 */
		public function load() {

			add_filter( 'bonips_parse_log_entry',      array( $this, 'render_gift_tags' ), 10, 2 );

			add_action( 'bonips_init',                 array( $this, 'module_init' ), $this->menu_pos );
			add_action( 'wp_loaded',                   array( $this, 'module_run' ) );

			add_action( 'bonips_register_assets',      array( $this, 'register_assets' ) );
			add_action( 'bonips_front_enqueue_footer', array( $this, 'enqueue_footer' ) );

			add_action( 'bonips_admin_init',           array( $this, 'module_admin_init' ), $this->menu_pos );
			add_action( 'bonips_admin_init',           array( $this, 'register_settings' ), $this->menu_pos+1 );
			add_action( 'bonips_add_menu',             array( $this, 'add_menu' ), $this->menu_pos );
			add_action( 'bonips_add_menu',             array( $this, 'add_to_menu' ), $this->menu_pos+1 );

			add_action( 'bonips_after_core_prefs',     array( $this, 'after_general_settings' ) );
			add_filter( 'bonips_save_core_prefs',      array( $this, 'sanitize_extra_settings' ), 90, 3 );

			add_action('pre_get_comments',             array( $this, 'hide_buycred_transactions' ) );

		}

		/**
		 * Init
		 * Register shortcodes.
		 * @since 0.1
		 * @version 1.4
		 */
		public function module_init() {

			// Add shortcodes first
			add_shortcode( BONIPS_SLUG . '_buy',      'bonips_render_buy_points' );
			add_shortcode( BONIPS_SLUG . '_buy_form', 'bonips_render_buy_form_points' );

			$this->setup_instance();

			$this->current_user_id = get_current_user_id();

		}

		/**
		 * Register Assets
		 * @since 1.8
		 * @version 1.0
		 */
		public function register_assets() {

			wp_register_style( 'buycred-checkout', plugins_url( 'assets/css/checkout.css', BONIPS_PURCHASE ), array(), BONIPS_PURCHASE_VERSION, 'all' );
			wp_register_script( 'buycred-checkout', plugins_url( 'assets/js/checkout.js', BONIPS_PURCHASE ), array( 'jquery' ), BONIPS_PURCHASE_VERSION, 'all' );

		}

		/**
		 * Setup Purchase Instance
		 * @since 1.8
		 * @version 1.0
		 */
		public function setup_instance() {

			global $buycred_instance;

			$buycred_instance             = new StdClass();
			$buycred_instance->settings   = bonips_get_buycred_settings();
			$buycred_instance->active     = array();
			$buycred_instance->gateway_id = false;
			$buycred_instance->checkout   = false;
			$buycred_instance->cancelled  = false;
			$buycred_instance->error      = false;
			$buycred_instance->gateway    = false;

		}

		/**
		 * Get Payment Gateways
		 * Retreivs all available payment gateways that can be used to buyCRED
		 * @since 0.1
		 * @version 1.1.1
		 */
		public function get() {

			$installed = bonips_get_buycred_gateways();

			// Untill all custom gateways have been updated, make sure all gateways have an external setting
			if ( ! empty( $installed ) ) {
				foreach ( $installed as $id => $settings ) {

					if ( ! array_key_exists( 'external', $settings ) )
						$installed[ $id ]['external'] = true;

					if ( ! array_key_exists( 'custom_rate', $settings ) )
						$installed[ $id ]['custom_rate'] = false;

				}
			}

			return $installed;

		}

		/**
		 * Run
		 * Runs a gateway if requested.
		 * @since 1.7
		 * @version 1.0
		 */
		public function module_run() {

			global $buycred_instance;

			// Prep
			$installed = $this->get();

			// Make sure we have installed gateways.
			if ( empty( $installed ) ) return;

			// We only want to deal with active gateways
			foreach ( $installed as $id => $data ) {
				if ( $this->is_active( $id ) )
					$buycred_instance->active[ $id ] = $data;
			}

			if ( empty( $buycred_instance->active ) ) return;

			/**
			 * Step 1 - Look for returns
			 * Runs though all active payment gateways and lets them decide if this is the
			 * user returning after a remote purchase. Each gateway should know what to look
			 * for to determen if they are responsible for handling the return.
			 */
			foreach ( $buycred_instance->active as $id => $data ) {

				if ( $data['external'] === true )
					$this->call( 'returning', $buycred_instance->active[ $id ]['callback'] );

			}

			/**
			 * Step 2 - Check for gateway calls
			 * Checks to see if a gateway should be loaded.
			 */
			$buycred_instance->gateway_id = bonips_get_requested_gateway_id();
			$buycred_instance->checkout   = false;
			$buycred_instance->is_ajax    = ( isset( $_REQUEST['ajax'] ) && $_REQUEST['ajax'] == 1 ) ? true : false;

			do_action( 'bonips_pre_process_buycred' );

			// If we have a valid gateway ID and the gateway is active, lets run that gateway.
			if ( $buycred_instance->gateway_id !== false && array_key_exists( $buycred_instance->gateway_id, $buycred_instance->active ) ) {

				// Construct Gateway
				$buycred_instance->gateway = buycred_gateway( $buycred_instance->gateway_id );

				// Check payment processing
				if ( isset( $_REQUEST['bonips_call'] ) ) {

					$buycred_instance->gateway->process();

					do_action( 'bonips_buycred_process',               $buycred_instance->gateway_id, $this->gateway_prefs );
					do_action( "bonips_buycred_process_{$gateway_id}", $this->gateway_prefs );

				}

				add_action( 'template_redirect',    array( $this, 'process_new_request' ) );
				add_filter( 'template_include',     array( $this, 'checkout_page' ) );

			}

		}

		/**
		 * Process New Request
		 * @since 1.8
		 * @version 1.0
		 */
		public function process_new_request() {

			global $buycred_instance, $buycred_sale;

			if ( $buycred_instance->checkout === false && isset( $_REQUEST['bonips_buy'] ) )
				$buycred_instance->checkout = true;

			if ( $buycred_instance->checkout ) {

				$buycred_sale = true;

				if ( $buycred_instance->gateway->valid_request() ) {

					if ( $buycred_instance->is_ajax )
						$buycred_instance->gateway->ajax_buy();

					do_action( 'bonips_buycred_buy', $buycred_instance->gateway_id, $this->gateway_prefs );
					do_action( "bonips_buycred_buy_{$buycred_instance->gateway_id}", $this->gateway_prefs );

				}
				else {

					if ( ! empty( $buycred_instance->gateway->errors ) ){
						$buycred_instance->checkout = false;

						if ( $buycred_instance->is_ajax )
							die( json_encode( array( 'validationFail' => true , 'errors' => $buycred_instance->gateway->errors ) ) 
						);
					}

				}

			}

		}

		/**
		 * Checkout Page
		 * @since 1.8
		 * @version 1.0
		 */
		public function checkout_page( $template ) {

			global $buycred_instance;

			if ( $buycred_instance->checkout ) {

				return BONIPS_BUYCRED_TEMPLATES_DIR . 'buycred-checkout.php';
				$override = bonips_locate_template( 'buycred-checkout.php', BONIPS_SLUG, BONIPS_BUYCRED_TEMPLATES_DIR );
				if ( ! $override )
					$template = $override;

			}

			return $template;

		}

		/**
		 * Enqueue Footer
		 * @since 1.8
		 * @version 1.0
		 */
		public function enqueue_footer() {

			global $buycred_instance, $buycred_sale;

			$settings = bonips_get_buycred_settings();

			if ( $buycred_sale ) {

				wp_enqueue_style( 'buycred-checkout' );

				wp_localize_script(
					'buycred-checkout',
					'buyCRED',
					apply_filters( 'bonips_buycred_checkout_js', array(
						'ajaxurl'     => home_url( '/' ),
						'token'       => wp_create_nonce( 'bonips-buy-creds' ),
						'checkout'    => $settings['checkout'],
						'redirecting' => esc_js( esc_attr__( 'Redirecting', 'bonips' ) ),
						'error'       => 'communications error'
					), $this )
				);
				wp_enqueue_script( 'buycred-checkout' );

				if ( $settings['checkout'] != 'page' ) {
					echo '
<div id="cancel-checkout-wrapper"><a href="javascript:void(0);">X</a></div>
<div id="buycred-checkout-wrapper">
	<div class="checkout-inside">
		<div class="checkout-wrapper">

			<div id="checkout-box">

				<form method="post" action="" id="buycred-checkout-form">
					<div class="loading-indicator"></div>
				</form>

			</div>

		</div>
	</div>
</div>';
				}

			}

		}

		/**
		 * Admin Init
		 * @since 1.5
		 * @version 1.1
		 */
		public function module_admin_init() {

			add_action( 'bonips_user_edit_after_balances', array( $this, 'exchange_rates_user_screen' ), 30 );

			add_action( 'personal_options_update',         array( $this, 'save_manual_exchange_rates' ), 30 );
			add_action( 'edit_user_profile_update',        array( $this, 'save_manual_exchange_rates' ), 30 );

			// Prep
			$installed = bonips_get_buycred_gateways();

			// Make sure we have installed gateways.
			if ( empty( $installed ) ) return;

			/**
			 * Admin Init
			 * Runs though all installed gateways to allow admin inits.
			 */
			foreach ( $installed as $id => $data )
				$this->call( 'admin_init', $installed[ $id ]['callback'] );

		}

		/**
		 * Add to General Settings
		 * @since 0.1
		 * @version 1.2
		 */
		public function after_general_settings( $bonips = NULL ) {

			// Reset while on this screen so we can use $this->field_id() and $this->field_name()
			$this->module_name = 'buy_creds';
			$this->option_id   = '';

			$uses_buddypress   = class_exists( 'BuddyPress' );

			$settings          = bonips_get_buycred_settings();

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><strong>buy</strong>CRED</h4>
<div class="body" style="display:none;">

	<div class="row">
		<div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
			<h3><?php _e( 'Verkaufseinrichtung', 'bonips' ); ?></h3>
<?php

			foreach ( $this->point_types as $type_id => $label ) {

				$bonips     = bonips( $type_id );
				$sale_setup = bonips_get_buycred_sale_setup( $type_id );

?>
			<div class="row">
				<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">

					<div class="form-group">
						<label for="buycred-type-<?php echo esc_attr( $type_id ); ?>-enabled"><?php echo $bonips->plural(); ?></label>
						<div class="checkbox" style="padding-top: 4px;">
							<label for="buycred-type-<?php echo esc_attr( $type_id ); ?>-enabled"><input type="checkbox" name="bonips_pref_core[buy_creds][types][<?php echo esc_attr( $type_id ); ?>][enabled]" id="buycred-type-<?php echo esc_attr( $type_id ); ?>-enabled"<?php if ( in_array( $type_id, $settings['types'] ) ) echo ' checked="checked"'; ?> value="<?php echo esc_attr( $type_id ); ?>" /> <?php _e( 'Aktivieren', 'bonips' ); ?></label>
						</div>
					</div>

				</div>
				<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">

					<div class="form-group">
						<label for="buycred-type-<?php echo esc_attr( $type_id ); ?>-min"><?php _e( 'Mindestbetrag', 'bonips' ); ?></label>
						<input type="text" name="bonips_pref_core[buy_creds][types][<?php echo esc_attr( $type_id ); ?>][min]" id="buycred-type-<?php echo esc_attr( $type_id ); ?>-min" class="form-control" placeholder="<?php echo $bonips->get_lowest_value(); ?>" value="<?php echo esc_attr( $sale_setup['min'] ); ?>" />
					</div>

				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

					<div class="form-group">
						<label for="buycred-type-<?php echo esc_attr( $type_id ); ?>-max"><?php _e( 'Maximal', 'bonips' ); ?></label>
						<div class="row">
							<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
								<input type="text" name="bonips_pref_core[buy_creds][types][<?php echo esc_attr( $type_id ); ?>][max]" id="buycred-type-<?php echo esc_attr( $type_id ); ?>-max" class="form-control" placeholder="<?php _e( 'Kein Limit', 'bonips' ); ?>" value="<?php echo esc_attr( $sale_setup['max'] ); ?>" />
							</div>
							<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
								<?php bonips_purchase_limit_dropdown( 'bonips_pref_core[buy_creds][types][' . $type_id . '][time]', 'buycred-type-' . $type_id . '-time', $sale_setup['time'] ); ?>
							</div>
						</div>
					</div>

				</div>
			</div>
<?php

			}

?>
			<hr />
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'custom_log' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'custom_log' ); ?>" id="<?php echo $this->field_id( 'custom_log' ); ?>"<?php checked( $settings['custom_log'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Erstelle ein dediziertes Protokoll für Einkäufe.', 'bonips' ) ); ?></label>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<h3><?php _e( 'Kasse', 'bonips' ); ?></h3>

			<div class="form-group">

				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6 text-center">
						<label for="<?php echo $this->field_id( 'checkout-full' ); ?>">
							<img src="<?php echo plugins_url( 'assets/images/checkout-full.png', BONIPS_PURCHASE ); ?>" alt="" style="max-width: 100%; height: auto;" />
							<input type="radio" name="<?php echo $this->field_name( 'checkout' ); ?>"<?php checked( $settings['checkout'], 'page' ); ?> id="<?php echo $this->field_id( 'checkout-full' ); ?>" value="page" /> Full Page
						</label>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6 text-center">
						<label for="<?php echo $this->field_id( 'checkout-popup' ); ?>">
							<img src="<?php echo plugins_url( 'assets/images/checkout-popup.png', BONIPS_PURCHASE ); ?>" alt="" style="max-width: 100%; height: auto;" />
							<input type="radio" name="<?php echo $this->field_name( 'checkout' ); ?>"<?php checked( $settings['checkout'], 'popup' ); ?> id="<?php echo $this->field_id( 'checkout-popup' ); ?>" value="popup" /> Popup
						</label>
					</div>
				</div>

			</div>

		</div>
	</div>

	<h3><?php _e( 'Weiterleitungen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

			<div class="form-group">
				<p style="margin-top: 0;"><span class="description"><?php _e( 'Wohin sollen Benutzer nach erfolgreichem Abschluss eines Kaufs weitergeleitet werden? Du kannst eine bestimmte URL oder eine Seite benennen.', 'bonips' ); ?></span></p>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'thankyou' => 'page' ) ); ?>"><?php _e( 'Weiterleitung auf Seite', 'bonips' ); ?></label>
<?php

			// Thank you page dropdown
			$thankyou_args = array(
				'name'             => $this->field_name( array( 'thankyou' => 'page' ) ),
				'id'               => $this->field_id( array( 'thankyou' => 'page' ) ) . '-id',
				'selected'         => $settings['thankyou']['page'],
				'show_option_none' => __( 'Auswählen', 'bonips' ),
				'class'            => 'form-control'
			);
			wp_dropdown_pages( $thankyou_args );

?>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'thankyou' => 'custom' ) ); ?>"><?php _e( 'Umleitung auf URL', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'thankyou' => 'custom' ) ); ?>" id="<?php echo $this->field_id( array( 'thankyou' => 'custom' ) ); ?>" placeholder="https://" class="form-control" value="<?php echo esc_attr( $settings['thankyou']['custom'] ); ?>" />
			</div>
			<?php if ( $uses_buddypress ) : ?>
			<p style="margin-top: 0;"><span class="description"><?php _e( 'Du kannst %profile% für die Basis-URL des Benutzerprofils verwenden.', 'bonips' ); ?></span></p>
			<?php endif; ?>

		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

			<div class="form-group">
				<p style="margin-top: 0;"><span class="description"><?php _e( 'Wohin sollen Benutzer umgeleitet werden, wenn sie eine Transaktion stornieren? Du kannst eine bestimmte URL oder eine Seite benennen.', 'bonips' ); ?></span></p>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cancelled' => 'page' ) ); ?>"><?php _e( 'Weiterleitung auf Seite', 'bonips' ); ?></label>
<?php

			// Thank you page dropdown
			$thankyou_args = array(
				'name'             => $this->field_name( array( 'cancelled' => 'page' ) ),
				'id'               => $this->field_id( array( 'cancelled' => 'page' ) ) . '-id',
				'selected'         => $settings['cancelled']['page'],
				'show_option_none' => __( 'Auswählen', 'bonips' ),
				'class'            => 'form-control'
			);
			wp_dropdown_pages( $thankyou_args );

?>
			</div>
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cancelled' => 'custom' ) ); ?>"><?php _e( 'Umleitung auf URL', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'cancelled' => 'custom' ) ); ?>" id="<?php echo $this->field_id( array( 'cancelled' => 'custom' ) ); ?>" placeholder="https://" class="form-control" value="<?php echo esc_attr( $settings['cancelled']['custom'] ); ?>" />
			</div>
			<?php if ( $uses_buddypress ) : ?>
			<p style="margin-top: 0;"><span class="description"><?php _e( 'Du kannst %profile% für die Basis-URL des Benutzerprofils verwenden.', 'bonips' ); ?></span></p>
			<?php endif; ?>

		</div>
	</div>

	<h3><?php _e( 'Vorlagen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

			<div class="form-group">
				<label for="<?php echo $this->field_id( 'login' ); ?>"><?php _e( 'Anmeldenachricht', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'login' ); ?>" id="<?php echo $this->field_id( 'login' ); ?>" class="form-control" value="<?php echo esc_attr( $settings['login'] ); ?>" />
				<p><span class="description"><?php _e( 'Nachricht, die in Shortcodes angezeigt wird, wenn sie von jemandem angezeigt wird, der nicht angemeldet ist.', 'bonips' ); ?></span></p>
			</div>

		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

			<div class="form-group">
				<label for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $settings['log'] ); ?>" />
				<p><span class="description"><?php echo $this->core->available_template_tags( array( 'general' ), '%gateway%' ); ?></span></p>
			</div>

		</div>
	</div>

	<h3><?php _e( 'Schenken', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'gifting' => 'members' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'gifting' => 'members' ) ); ?>" id="<?php echo $this->field_id( array( 'gifting' => 'members' ) ); ?>"<?php checked( $settings['gifting']['members'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Benutzern erlauben, %_plural% für andere Benutzer zu kaufen.', 'bonips' ) ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( array( 'gifting' => 'authors' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'gifting' => 'authors' ) ); ?>" id="<?php echo $this->field_id( array( 'gifting' => 'authors' ) ); ?>"<?php checked( $settings['gifting']['authors'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Benutzern erlauben, %_plural% für Inhaltsautoren zu kaufen.', 'bonips' ) ); ?></label>
				</div>
			</div>

		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'gifting' => 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'gifting' => 'log' ) ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" class="form-control" placeholder="<?php _e( 'Erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $settings['gifting']['log'] ); ?>" />
				<p><span class="description"><?php echo $this->core->available_template_tags( array( 'general', 'user' ) ); ?></span></p>
			</div>

		</div>
	</div>

	<h3 style="margin-bottom: 0;"><?php _e( 'Verfügbare Shortcodes', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<p><a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_buy/" target="_blank">[bonips_buy]</a>, <a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_buy_form/" target="_blank">[bonips_buy_form]</a>, <a href="https://github.com/cp-psource/docs/bonips-shortcodes-bonips_buy_pending/" target="_blank">[bonips_buy_pending]</a></p>
		</div>
	</div>

</div>
<?php

			$this->module_name = 'gateways';
			$this->option_id   = 'bonips_pref_buycreds';

		}

		/**
		 * Save Settings
		 * @since 0.1
		 * @version 1.2
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$point_types = array();

			if ( isset( $data['buy_creds']['types'] ) && ! empty( $data['buy_creds']['types'] ) ) {
				foreach ( $data['buy_creds']['types'] as $type_id => $setup ) {

					$type_id = sanitize_key( $type_id );
					if ( ! bonips_point_type_exists( $type_id ) ) continue;

					$point_types[]    = $type_id;

					$settings         = array();
					$settings['min']  = sanitize_text_field( $setup['min'] );
					$settings['max']  = sanitize_text_field( $setup['max'] );
					$settings['time'] = sanitize_key( $setup['time'] );

					$settings         = shortcode_atts( bonips_get_buycred_sale_setup( $type_id ), $settings );

					bonips_update_option( 'buycred-setup-' . $type_id, $settings );

				}
			}

			if ( empty( $point_types ) )
				$point_types[] = BONIPS_DEFAULT_TYPE_KEY;

			$new_data['buy_creds']['types']               = $point_types;

			$new_data['buy_creds']['checkout']            = sanitize_key( $data['buy_creds']['checkout'] );
			$new_data['buy_creds']['log']                 = sanitize_text_field( $data['buy_creds']['log'] );
			$new_data['buy_creds']['login']               = wp_kses_post( $data['buy_creds']['login'] );

			$new_data['buy_creds']['thankyou']['page']    = absint( $data['buy_creds']['thankyou']['page'] );
			$new_data['buy_creds']['thankyou']['custom']  = sanitize_text_field( $data['buy_creds']['thankyou']['custom'] );
			$new_data['buy_creds']['thankyou']['use']     = ( $new_data['buy_creds']['thankyou']['custom'] != '' ) ? 'custom' : 'page';

			$new_data['buy_creds']['cancelled']['page']   = absint( $data['buy_creds']['cancelled']['page'] );
			$new_data['buy_creds']['cancelled']['custom'] = sanitize_text_field( $data['buy_creds']['cancelled']['custom'] );
			$new_data['buy_creds']['cancelled']['use']    = ( $new_data['buy_creds']['cancelled']['custom'] != '' ) ? 'custom' : 'page';

			$new_data['buy_creds']['custom_log']          = ( ! isset( $data['buy_creds']['custom_log'] ) ) ? 0 : 1;

			$new_data['buy_creds']['gifting']['members']  = ( ! isset( $data['buy_creds']['gifting']['members'] ) ) ? 0 : 1;
			$new_data['buy_creds']['gifting']['authors']  = ( ! isset( $data['buy_creds']['gifting']['authors'] ) ) ? 0 : 1;
			$new_data['buy_creds']['gifting']['log']      = sanitize_text_field( $data['buy_creds']['gifting']['log'] );

			delete_option( 'bonips_buycred_reset' );

			return $new_data;

		}

		/**
		 * Render Gift Tags
		 * @since 1.4.1
		 * @version 1.0
		 */
		public function render_gift_tags( $content, $log ) {

			if ( substr( $log->ref, 0, 15 ) != 'buy_creds_with_' ) return $content;
			return $this->core->template_tags_user( $content, absint( $log->ref_id ) );

		}

		/**
		 * Add Admin Menu Item
		 * @since 0.1
		 * @version 1.2
		 */
		public function add_to_menu() {

			// In case we are using the Master Template feautre on multisites, and this is not the main
			// site in the network, bail.
			if ( bonips_override_settings() && ! bonips_is_main_site() ) return;

			// If we selected to insert a purchase log
			if ( isset( $this->core->buy_creds['custom_log'] ) && $this->core->buy_creds['custom_log'] ) {

				$pages       = array();
				$point_types = ( isset( $this->core->buy_creds['types'] ) && ! empty( $this->core->buy_creds['types'] ) ) ? $this->core->buy_creds['types'] : array( BONIPS_DEFAULT_TYPE_KEY );

				foreach ( $point_types as $type_id ) {

					$bonips    = bonips( $type_id );
					$menu_slug = ( $type_id != BONIPS_DEFAULT_TYPE_KEY ) ? BONIPS_SLUG . '_' . $type_id : BONIPS_SLUG;

					$pages[]   = add_submenu_page(
						$menu_slug,
						__( 'buyBONI Kaufprotokoll', 'bonips' ),
						__( 'Kaufprotokoll', 'bonips' ),
						$bonips->get_point_editor_capability(),
						BONIPS_SLUG . '-purchases-' . $type_id,
						array( $this, 'purchase_log_page' )
					);

				}

				foreach ( $pages as $page ) {

					add_action( 'admin_print_styles-' . $page, array( $this, 'settings_page_enqueue' ) );
					add_action( 'load-' . $page,               array( $this, 'screen_options' ) );

				}

				$this->purchase_log = $pages;

			}

		}

		/**
		 * Page Header
		 * @since 1.3
		 * @version 1.2
		 */
		public function settings_header() {

			wp_enqueue_style( 'bonips-admin' );
			wp_enqueue_style( 'bonips-bootstrap-grid' );
			wp_enqueue_style( 'bonips-forms' );

		}

		/**
		 * Payment Gateways Page
		 * @since 0.1
		 * @version 1.2.2
		 */
		public function admin_page() {

			// Security
			if ( ! $this->core->user_is_point_admin() ) wp_die( 'Access Denied' );

			$installed = $this->get();

?>
<div class="wrap bonips-metabox" id="boniPS-wrap">
	<h1><?php _e( 'Zahlungs-Gateways', 'bonips' ); ?></h1>
<?php

			// Updated settings
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true )
				echo '<div class="updated settings-error"><p>' . __( 'Einstellungen aktualisiert', 'bonips' ) . '</p></div>';

?>
	<form method="post" action="options.php" class="form">

		<?php settings_fields( $this->settings_name ); ?>

		<?php do_action( 'bonips_before_buycreds_page', $this ); ?>

		<div class="list-items expandable-li" id="accordion">
<?php

			if ( ! empty( $installed ) ) {
				foreach ( $installed as $key => $data ) {

					$has_documentation = ( array_key_exists( 'documentation', $data ) && ! empty( $data['documentation'] ) ) ? esc_url_raw( $data['documentation'] ) : false;
					$has_test_mode     = ( array_key_exists( 'sandbox', $data ) ) ? (bool) $data['sandbox'] : false;
					$sandbox_mode      = ( array_key_exists( $key, $this->gateway_prefs ) && array_key_exists( 'sandbox', $this->gateway_prefs[ $key ] ) && $this->gateway_prefs[ $key ]['sandbox'] === 1 ) ? true : false;

					if ( ! array_key_exists( 'icon', $data ) )
						$data['icon'] = 'dashicons-admin-plugins';

					$column_class = 'col-lg-6 col-md-6 col-sm-12 col-xs-12';
					if ( ! $has_documentation && ! $has_test_mode )
						$column_class = 'col-lg-12 col-md-12 col-sm-12 col-xs-12';
					elseif ( $has_documentation && $has_test_mode )
						$column_class = 'col-lg-4 col-md-4 col-sm-12 col-xs-12';

?>
			<h4><span class="dashicons <?php echo $data['icon']; ?><?php if ( $this->is_active( $key ) ) { if ( $sandbox_mode ) echo ' debug'; else echo ' active'; } else echo ' static'; ?>"></span><?php echo $this->core->template_tags_general( $data['title'] ); ?></h4>
			<div class="body" style="display: none;">

				<div class="row">
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<div>&nbsp;</div>
							<label for="buycred-gateway-<?php echo $key; ?>"><input type="checkbox" name="bonips_pref_buycreds[active][]" id="buycred-gateway-<?php echo $key; ?>" value="<?php echo $key; ?>"<?php if ( $this->is_active( $key ) ) echo ' checked="checked"'; ?> /> <?php _e( 'Aktivieren', 'bonips' ); ?></label>
						</div>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
						<?php if ( $has_test_mode ) : ?>
						<div class="form-group">
							<div>&nbsp;</div>
							<label for="buycred-gateway-<?php echo $key; ?>-sandbox"><input type="checkbox" name="bonips_pref_buycreds[gateway_prefs][<?php echo $key; ?>][sandbox]" id="buycred-gateway-<?php echo $key; ?>-sandbox" value="<?php echo $key; ?>"<?php if ( $sandbox_mode ) echo ' checked="checked"'; ?> /> <?php _e( 'Sandbox Modus', 'bonips' ); ?></label>
						</div>
						<?php endif; ?>
					</div>
					<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12" style="text-align: right;">
						<?php if ( BONIPS_DEFAULT_LABEL === 'boniPS' && $has_documentation ) : ?>
						<div class="form-group">
							<div>&nbsp;</div>
							<a href="<?php echo $has_documentation; ?>" target="_blank"><?php _e( 'Dokumentation', 'bonips' ); ?></a>
						</div>
						<?php endif; ?>
					</div>
				</div>
				<hr />

				<?php $this->call( 'preferences', $data['callback'] ); ?>

				<input type="hidden" name="bonips_pref_buycreds[installed]" value="<?php echo $key; ?>" />
			</div>
<?php

				}
			}

?>
		</div>

		<?php do_action( 'bonips_after_buycreds_page', $this ); ?>

		<p><?php submit_button( __( 'Update Einstellungen', 'bonips' ), 'primary large', 'submit', false ); ?> <?php if ( BONIPS_SHOW_PREMIUM_ADDONS ) : ?><a href="https://github.com/cp-psource/shop/artikel/category/bonips-erweiterungen/" class="button button-secondary button-large" target="_blank">Mehr Erweiterungen</a><?php endif; ?></p>

	</form>

	<?php do_action( 'bonips_bottom_buycreds_page', $this ); ?>

<script type="text/javascript">
jQuery(function($) {
	$( 'select.currency' ).on('change', function(){
		var target = $(this).attr( 'data-update' );
		$( '.' + target ).empty();
		$( '.' + target ).text( $(this).val() );
	});
});
</script>
</div>
<?php

		}

		/**
		 * Sanititze Settings
		 * @since 0.1
		 * @version 1.3.1
		 */
		public function sanitize_settings( $data ) {

			$data      = apply_filters( 'bonips_buycred_save_prefs', $data );
			$installed = $this->get();

			if ( empty( $installed ) ) return $data;

			foreach ( $installed as $gateway_id => $gateway ) {

				$gateway_id     = (string) $gateway_id;
				$submitted_data = ( ! empty( $data['gateway_prefs'] ) && array_key_exists( $gateway_id, $data['gateway_prefs'] ) ) ? $data['gateway_prefs'][ $gateway_id ] : false;

				// No need to do anything if we have no data
				if ( $submitted_data !== false )
					$data['gateway_prefs'][ $gateway_id ] = $this->call( 'sanitise_preferences', $installed[ $gateway_id ]['callback'], $submitted_data );

			}

			return $data;

		}

		/**
		 * Purchase Log Screen Options
		 * @since 1.4
		 * @version 1.1
		 */
		public function screen_options() {

			if ( empty( $this->purchase_log ) ) return;

			$meta_key = 'bonips_payments_' . str_replace( BONIPS_SLUG . '-purchases-', '', $_GET['page'] );

			if ( isset( $_REQUEST['wp_screen_options']['option'] ) && isset( $_REQUEST['wp_screen_options']['value'] ) ) {
			
				if ( $_REQUEST['wp_screen_options']['option'] == $meta_key ) {
					$value = absint( $_REQUEST['wp_screen_options']['value'] );
					bonips_update_user_meta( $this->current_user_id, $meta_key, $value );
				}

			}

			$args = array(
				'label'   => __( 'Zahlungen', 'bonips' ),
				'default' => 10,
				'option'  => $meta_key
			);
			add_screen_option( 'per_page', $args );

		}

		/**
		 * Purchase Log
		 * Render the dedicated admin screen where all point purchases are shown from the boniPS Log.
		 * This screen is added in for each point type that is set to be for sale.
		 * @since 1.4
		 * @version 1.5
		 */
		public function purchase_log_page() {

			$point_type           = str_replace( 'bonips-purchases-', '', $_GET['page'] );
			$installed            = $this->get();

			$bonips               = $this->core;
			if ( $point_type != BONIPS_DEFAULT_TYPE_KEY && bonips_point_type_exists( $point_type ) )
				$bonips = bonips( $point_type );

			// Security (incase the user has setup different capabilities to manage this particular point type)
			if ( ! $bonips->user_is_point_editor() ) wp_die( 'Access Denied' );

			// Get references
			$references           = bonips_get_buycred_gateway_refs( $point_type );

			$search_args          = bonips_get_search_args();
			$filter_url           = admin_url( 'admin.php?page=' . BONIPS_SLUG . '-purchases-' . $point_type );

			$per_page             = bonips_get_user_meta( $this->current_user_id, 'bonips_payments_' . $point_type, '', true );
			if ( empty( $per_page ) || $per_page < 1 ) $per_page = 10;

			// Entries per page
			if ( ! array_key_exists( 'number', $search_args ) )
				$search_args['number'] = absint( $per_page );

			$search_args['ctype'] = $point_type;
			$search_args['ref']   = array(
				'ids'     => $references,
				'compare' => 'IN'
			);

			$log                  = new boniPS_Query_Log( $search_args );
			$log->headers         = apply_filters( 'bonips_buycred_log_columns', array(
				'column-gateway'     => __( 'Gateway', 'bonips' ),
				'column-username'    => __( 'Käufer', 'bonips' ),
				'column-date'        => __( 'Datum', 'bonips' ),
				'column-amount'      => $bonips->plural(),
				'column-payed'       => __( 'Bezahlt', 'bonips' ),
				'column-tranid'      => __( 'Transaktions-ID', 'bonips' )
			) );

?>
<div class="wrap list" id="boniPS-wrap">
	<h1><?php _e( 'Kaufprotokoll', 'bonips' ); ?></h1>

	<?php $log->filter_dates( esc_url( $filter_url ) ); ?>

	<form method="get" action="" name="bonips-buycred-form" novalidate>
		<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>" />
<?php

			if ( array_key_exists( 's', $search_args ) )
				echo '<input type="hidden" name="s" value="' . esc_attr( $search_args['s'] ) . '" />';

			if ( isset( $_GET['ref'] ) )
				echo '<input type="hidden" name="show" value="' . esc_attr( $_GET['ref'] ) . '" />';

			if ( isset( $_GET['show'] ) )
				echo '<input type="hidden" name="show" value="' . esc_attr( $_GET['show'] ) . '" />';

			if ( array_key_exists( 'order', $search_args ) )
				echo '<input type="hidden" name="order" value="' . esc_attr( $search_args['order'] ) . '" />';

			if ( array_key_exists( 'paged', $search_args ) )
				echo '<input type="hidden" name="paged" value="' . esc_attr( $search_args['paged'] ) . '" />';

			$log->search();

?>

		<?php do_action( 'bonips_above_payment_log_table', $this ); ?>

		<div class="tablenav top">

			<?php $log->table_nav( 'top' ); ?>

		</div>
		<table class="wp-list-table widefat fixed striped users bonips-table" cellspacing="0">
			<thead>
				<tr>
<?php

			foreach ( $log->headers as $col_id => $col_title )
				echo '<th scope="col" id="' . str_replace( 'column-', '', $col_id ) . '" class="manage-column ' . $col_id . '">' . $col_title . '</th>';

?>
				</tr>
			</thead>
			<tfoot>
				<tr>
<?php

			foreach ( $log->headers as $col_id => $col_title )
				echo '<th scope="col" class="manage-column ' . $col_id . '">' . $col_title . '</th>';

?>
				</tr>
			</tfoot>
			<tbody id="the-list">
<?php

			// If we have results
			if ( $log->have_entries() ) {

				// Prep
				$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				$entry_data  = '';
				$alt         = 0;

				// Loop results
				foreach ( $log->results as $log_entry ) {

					// Highlight alternate rows
					$alt   = $alt + 1;
					$class = '';
					$style = '';
					if ( $alt % 2 == 0 ) $class = ' alt';

					// Prep Sales data for use in columns
					$sales_data = $this->get_sales_data_from_log_data( $log_entry->data );
					list ( $buyer_id, $payer_id, $amount, $cost, $currency, $token, $other ) = $sales_data;

					// Default Currency
					if ( empty( $currency ) )
						$currency = 'USD';

					$gateway_name = str_replace( 'buy_creds_with_', '', $log_entry->ref );

					// Color rows based on if the transaction was made in Sandbox mode or using a gateway that no longer is used.
					if ( ! array_key_exists( str_replace( '_', '-', $gateway_name ), $installed ) )
						$style = ' style="color:silver;"';

					elseif ( ! $this->is_active( str_replace( '_', '-', $gateway_name ) ) )
						$style = ' style="color:gray;"';

					elseif ( substr( $log_entry->entry, 0, 4 ) == 'TEST' )
						$style = ' style="color:orange;"';

					echo '<tr class="boniPS-log-row' . $class . '" id="bonips-log-entry-' . $log_entry->id . '">';

					// Run though columns
					foreach ( $log->headers as $column_id => $column_name ) {

						echo '<td class="' . $column_id . '"' . $style . '>';

						switch ( $column_id ) {

							// Used gateway
							case 'column-gateway' :

								$gateway = str_replace( array( '-', '_' ), ' ', $gateway_name );
								echo ucwords( $gateway );

							break;

							// Username Column
							case 'column-username' :

								$user = get_userdata( $log_entry->user_id );
								if ( $user === false )
									echo 'ID: ' . $log_entry->user_id;
								else
									echo $user->display_name . ' <em><small>(ID: ' . $log_entry->user_id . ')</small></em>';

							break;

							// Date & Time Column
							case 'column-date' :

								echo date( $date_format, $log_entry->time );

							break;

							// Amount Column
							case 'column-amount' :

								echo $bonips->format_creds( $log_entry->creds );

							break;

							// Amount Paid
							case 'column-payed' :

								$cost     = 'n/a';
								$currency = '';
								$data     = maybe_unserialize( $log_entry->data );
								if ( is_array( $data ) && array_key_exists( 'sales_data', $data ) ) {

									$sales_data = explode( '|', $data['sales_data'] );
									if ( count( $sales_data ) >= 5 ) {
										$cost     = $sales_data[3];
										$currency = $sales_data[4];
									}

								}

								if ( $cost === 'n/a' )
									echo 'n/a';

								else {

									$rendered_cost = apply_filters( 'bonips_buycred_display_cost', $cost . ' ' . $currency, $sales_data, $log_entry, $gateway_name );
									$rendered_cost = apply_filters( 'bonips_buycred_display_cost_' . $gateway_name, $rendered_cost, $sales_data, $log_entry );

									echo $rendered_cost;

								}

							break;

							// Transaction ID
							case 'column-tranid' :

								$transaction_id = $log_entry->time . $log_entry->user_id;
								$saved_data     = maybe_unserialize( $log_entry->data );

								if ( isset( $saved_data['txn_id'] ) )
									$transaction_id = $saved_data['txn_id'];

								elseif ( isset( $saved_data['transaction_id'] ) )
									$transaction_id = $saved_data['transaction_id'];

								echo $transaction_id;

							break;

							default :

								do_action( "bonips_payment_log_{$column_id}", $log_entry );
								do_action( "bonips_payment_log_{$column_id}_{$type}", $log_entry );

							break;

						}

						echo '</td>';

					}

					echo '</tr>';

				}

			}

			// No log entry
			else {

				echo '<tr><td colspan="' . count( $log->headers ) . '" class="no-entries">' . __( 'Keine Käufe gefunden', 'bonips' ) . '</td></tr>';

			}

?>
			</tbody>
		</table>
		<div class="tablenav bottom">

			<?php $log->table_nav( 'bottom' ); ?>

		</div>

		<?php do_action( 'bonips_below_payment_log_table', $this ); ?>

	</form>
</div>
<?php

		}

		/**
		 * Get Sales Data from Log Data
		 * @since 1.4
		 * @version 1.0.1
		 */
		public function get_sales_data_from_log_data( $log_data = '' ) {

			$defaults = array( '', '', '', '', '', '', '' );
			$log_data = maybe_unserialize( $log_data );

			$found_data = array();
			if ( is_array( $log_data ) && array_key_exists( 'sales_data', $log_data ) ) {
				if ( is_array( $log_data['sales_data'] ) )
					$found_data = $log_data['sales_data'];
				else
					$found_data = explode( '|', $log_data['sales_data'] );
			}
			elseif ( ! empty( $log_data ) && ! is_array( $log_data ) ) {
				$try = explode( '|', $log_data );
				if ( count( $try == 7 ) )
					$found_data = $log_data;
			}

			return wp_parse_args( $found_data, $defaults );

		}

		/**
		 * User Rates Admin Screen
		 * @since 1.5
		 * @version 1.0
		 */
		public function exchange_rates_user_screen( $user ) {

			// Make sure buyCRED is setup
			if ( ! isset( $this->core->buy_creds['types'] ) || empty( $this->core->buy_creds['types'] ) ) return;

			// Only visible to admins
			if ( ! bonips_is_admin() ) return;

			$bonips_types         = bonips_get_types( true );
			$point_types_for_sale = $this->core->buy_creds['types'];
			$installed            = $this->get();
			$available_options    = array();

			foreach ( $installed as $gateway_id => $prefs ) {

				// Gateway is not active or settings have not yet been saved
				if ( ! $this->is_active( $gateway_id ) || ! array_key_exists( $gateway_id, $this->gateway_prefs ) || ! $prefs['custom_rate'] ) continue;

				$gateway_prefs = $this->gateway_prefs[ $gateway_id ];

				// Need a currency
				if ( array_key_exists( 'currency', $gateway_prefs ) && $gateway_prefs['currency'] == '' ) continue;

				if ( ! array_key_exists( 'currency', $gateway_prefs ) )
					$gateway_prefs['currency'] = 'EUR';

				$setup = array( 'name' => $prefs['title'], 'currency' => $gateway_prefs['currency'], 'types' => array() );

				foreach ( $bonips_types as $point_type_key => $label ) {

					$row = array( 'name' => $label, 'enabled' => false, 'excluded' => true, 'default' => 0, 'override' => false, 'custom' => '', 'before' => '' );

					if ( in_array( $point_type_key, $point_types_for_sale ) && array_key_exists( $point_type_key, $gateway_prefs['exchange'] ) ) {

						$row['enabled'] = true;

						$bonips = bonips( $point_type_key );

						if ( ! $bonips->exclude_user( $user->ID ) ) {

							$row['excluded'] = false;
							$row['default']  = $gateway_prefs['exchange'][ $point_type_key ];

							$row['before']   = $bonips->format_creds( 1 ) . ' = ';

							$saved_overrides = (array) bonips_get_user_meta( $user->ID, 'bonips_buycred_rates_' . $point_type_key, '', true );

							if ( ! empty( $saved_overrides ) && array_key_exists( $gateway_id, $saved_overrides ) ) {

								$row['override'] = true;
								$row['custom']   = $saved_overrides[ $gateway_id ];

							}

						}

					}

					$setup['types'][ $point_type_key ] = $row;

				}

				$available_options[ $gateway_id ] = $setup;

			}

			if ( empty( $available_options ) ) return;

?>
<p class="bonips-p"><?php _e( 'Benutzerwechselkurs beim Kauf von Punkten.', 'bonips' ); ?></p>
<table class="form-table bonips-inline-table">
<?php

			foreach ( $available_options as $gateway_id => $setup ) :

?>
	<tr>
		<th scope="row"><?php echo esc_attr( $setup['name'] ); ?></th>
		<td>
			<fieldset id="bonips-buycred-list" class="buycred-list">
				<legend class="screen-reader-text"><span><?php _e( 'buyBONI-Wechselkurse', 'bonips' ); ?></span></legend>
<?php

				foreach ( $setup['types'] as $type_id => $data ) {

					// This point type is not for sale
					if ( ! $data['enabled'] ) {

?>
					<div class="bonips-wrapper buycred-wrapper disabled-option color-option">
						<div><?php printf( _x( '%s kaufen', 'Punktname', 'bonips' ), $data['name'] ); ?></div>
						<div class="balance-row">
							<div class="balance-view"><?php _e( 'Deaktiviert', 'bonips' ); ?></div>
							<div class="balance-desc"><em><?php _e( 'Dieser Punktetyp steht nicht zum Verkauf.', 'bonips' ); ?></em></div>
						</div>
					</div>
<?php

					}

					// This user is excluded from this point type
					elseif ( $data['excluded'] ) {

?>
					<div class="bonips-wrapper buycred-wrapper excluded-option color-option">
						<div><?php printf( _x( '%s kaufen', 'Kaufe Punkte', 'bonips' ), $data['name'] ); ?></div>
						<div class="balance-row">
							<div class="balance-view"><?php _e( 'Ausgeschlossen', 'bonips' ); ?></div>
							<div class="balance-desc"><em><?php printf( _x( 'Benutzer kann %s nicht kaufen', 'Punktname', 'bonips' ), $data['name'] ); ?></em></div>
						</div>
					</div>
<?php

					}

					// Eligeble user
					else {

?>
					<div class="bonips-wrapper buycred-wrapper color-option selected">
						<div><?php printf( _x( '%s kaufen', 'Kaufe Punkte', 'bonips' ), $data['name'] ); ?></div>
						<div class="balance-row">
							<div class="balance-view"><?php echo $data['before']; ?><input type="text" name="bonips_adjust_users_buyrates[<?php echo $type_id; ?>][<?php echo $gateway_id; ?>]" placeholder="<?php echo $data['default']; ?>" value="<?php if ( $data['override'] ) echo esc_attr( $data['custom'] ); ?>" class="short" size="8" /><?php echo ' ' . $setup['currency']; ?></div>
							<div class="balance-desc"><em><?php _e( 'Lasse dieses Feld leer, um den Standardsatz zu verwenden.', 'bonips' ); ?></em></div>
						</div>
					</div>
<?php

					}

				}

?>
			</fieldset>
		</td>
	</tr>
<?php

			endforeach;

?>
</table>
<hr />
<script type="text/javascript">
jQuery(function($) {

	$( '.buycred-wrapper label input.trigger-buycred' ).on('change', function(){

		if ( $(this).val().length > 0 )
			$(this).parent().parent().parent().addClass( 'selected' );

		else
			$(this).parent().parent().parent().removeClass( 'selected' );

	});

});
</script>
<?php

		}

		/**
		 * Save Override
		 * @since 1.5
		 * @version 1.2
		 */
		public function save_manual_exchange_rates( $user_id ) {

			if ( ! bonips_is_admin() ) return;

			if ( isset( $_POST['bonips_adjust_users_buyrates'] ) && is_array( $_POST['bonips_adjust_users_buyrates'] ) && ! empty( $_POST['bonips_adjust_users_buyrates'] ) ) {

				foreach ( $_POST['bonips_adjust_users_buyrates'] as $ctype => $gateway ) {

					$ctype  = sanitize_key( $ctype );
					$bonips = bonips( $ctype );

					if ( ! $bonips->exclude_user( $user_id ) ) {

						$new_rates = array();
						foreach ( (array) $gateway as $gateway_id => $rate ) {

							if ( $rate == '' ) continue;

							if ( $rate != 1 && in_array( substr( $rate, 0, 1 ), array( '.', ',' ) ) )
								$rate = (float) '0' . $rate;

							$new_rates[ $gateway_id ] = $rate;

						}

						if ( ! empty( $new_rates ) )
							bonips_update_user_meta( $user_id, 'bonips_buycred_rates_' . $ctype, '', $new_rates );
						else
							bonips_delete_user_meta( $user_id, 'bonips_buycred_rates_' . $ctype );

					}

				}

			}

		}

		/**
		 * Hide Comments
		 * @since 1.8.9
		 * @version 1.0
		 */
		public function hide_buycred_transactions( $query ) {

		    $query->query_vars['type__not_in'] = 'buycred';

		}

	}
endif;

/**
 * Load buyCRED Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonips_load_buycred_core_addon' ) ) :
	function bonips_load_buycred_core_addon( $modules, $point_types ) {

		$modules['solo']['buycred'] = new boniPS_buyCRED_Module();
		$modules['solo']['buycred']->load();

		return $modules;

	}
endif;
add_filter( 'bonips_load_modules', 'bonips_load_buycred_core_addon', 30, 2 );
