<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS_Setup class
 * Used when the plugin has been activated for the first time. Handles the setup
 * wizard along with temporary admin menus.
 * @since 0.1
 * @version 1.2
 */
if ( ! class_exists( 'boniPS_Setup' ) ) :
	class boniPS_Setup {

		public $status = false;
		public $core;

		/**
		 * Construct
		 */
		public function __construct() {

			$this->core = bonips();

		}

		/**
		 * Load Class
		 * @since 1.7
		 * @version 1.0
		 */
		public function load() {

			add_action( 'admin_notices',         array( $this, 'admin_notice' ) );
			add_action( 'admin_menu',            array( $this, 'setup_menu' ) );

			add_action( 'wp_ajax_bonips-setup',  array( $this, 'ajax_setup' ) );

		}

		/**
		 * Setup Setup Nag
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function admin_notice() {

			$screen = get_current_screen();
			if ( $screen->id == 'plugins_page_' . BONIPS_SLUG . '-setup' || ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) || ! bonips_is_admin() ) return;

			echo '<div class="info notice notice-info"><p>' . sprintf( __( '%s braucht Deine Aufmerksamkeit.', 'bonips' ), bonips_label() ) . ' <a href="' . admin_url( 'plugins.php?page=' . BONIPS_SLUG . '-setup' ) . '">' . __( 'Ersteinrichtung', 'bonips' ) . '</a></p></div>';

		}

		/**
		 * Add Setup page under "Plugins"
		 * @since 0.1
		 * @version 1.0
		 */
		public function setup_menu() {

			$page = add_submenu_page(
				'plugins.php',
				__( 'BoniPress Setup', 'bonips' ),
				__( 'BoniPress Setup', 'bonips' ),
				'manage_options',
				BONIPS_SLUG . '-setup',
				array( $this, 'setup_page' )
			);

			add_action( 'admin_print_styles-' . $page, array( $this, 'settings_header' ) );

		}

		/**
		 * Setup Header
		 * @since 0.1
		 * @version 1.1
		 */
		public function settings_header() {

			wp_enqueue_style( 'bonips-admin' );
			wp_enqueue_style( 'bonips-bootstrap-grid' );
			wp_enqueue_style( 'bonips-forms' );

		}

		/**
		 * Setup Screen
		 * Outputs the setup page.
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function setup_page() {

			$whitelabel = bonips_label();

?>
<style type="text/css">
#boniPS-wrap p { font-size: 13px; line-height: 17px; }
#bonips-setup-completed, #bonips-setup-progress { padding-top: 48px; }
#bonips-setup-completed h1, #bonips-setup-progress h1 { font-size: 3em; line-height: 3.2em; }
pre { margin: 0 0 12px 0; padding: 10px; background-color: #dedede; }
</style>
<div class="wrap bonips-metabox" id="boniPS-wrap">
	<h1><?php printf( __( '%s Einrichtung', 'bonips' ), $whitelabel ); ?></h1>
	<p><?php printf( __( 'Bevor Du %s verwenden kannst, musst Du Deinen ersten Punkttyp einrichten. Dazu gehört, wie Du Deine Punkte nennen möchtest, wie diese Punkte dargestellt werden und wer Zugriff darauf hat.', 'bonips' ), $whitelabel ); ?></p>
	<form method="post" action="" class="form" id="bonips-setup-form">

		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<h1><?php _e( 'Dein erster Punkttyp', 'bonips' ); ?></h1>
			</div>
		</div>

		<div id="bonips-form-content">

			<?php $this->new_point_type(); ?>

			<?php do_action( 'bonips_setup_after_form' ); ?>

		</div>

		<div id="bonips-advanced-setup-options" style="display: none;">

			<div class="row">
				<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
					<h1><?php _e( 'Erweiterte Einstellungen', 'bonips' ); ?></h1>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<h3><?php _e( 'Ändere den Standardpunkttypschlüssel', 'bonips' ); ?></h3>
					<pre>define( 'BONIPS_DEFAULT_TYPE_KEY', 'yourkey' );</pre>
					<p><span class="description"><?php _e( 'Du kannst den zum Speichern des Standardpunkttyps verwendeten Metaschlüssel mithilfe der Konstante BONIPS_DEFAULT_TYPE_KEY ändern. Kopiere den obigen Code in Deine zu verwendende Datei wp-config.php.', 'bonips' ); ?></span></p>
					<p><span class="description"><?php _e( 'Wenn Du den Standard-Metaschlüssel ändern möchtest, solltest Du dies tun, bevor Du mit diesem Setup fortfährst!', 'bonips' ); ?></span></p>
				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<h3><?php _e( 'Whitelabel', 'bonips' ); ?></h3>
					<pre>define( 'BONIPS_DEFAULT_LABEL', 'SuperPoints' );</pre>
					<p><span class="description"><?php _e( 'Du kannst boniPS mit der Konstante BONIPS_DEFAULT_LABEL neu beschriften. Kopiere den obigen Code zur Verwendung in Deine Datei wp-config.php.', 'bonips' ); ?></span></p>
				</div>
			</div>

		</div>

		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<p><input type="submit" class="button button-primary button-large" value="<?php _e( 'Punkttyp erstellen', 'bonips' ); ?>" /><button type="button" id="toggle-advanced-options" class="button button-secondary pull-right" data-hide="<?php _e( 'Ausblenden', 'bonips' ); ?>" data-show="<?php _e( 'Fortgeschritten', 'bonips' ); ?>"><?php _e( 'Fortgeschritten', 'bonips' ); ?></button></p>
			</div>
		</div>

	</form>
	<div id="bonips-setup-progress" style="display: none;">
		<h1 class="text-center"><?php _e( 'Verarbeitung...', 'bonips' ); ?></h1>
	</div>
	<div id="bonips-setup-completed" style="display: none;">
		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<h1 class="text-center"><?php _e( 'Einrichtung abgeschlossen!', 'bonips' ); ?></h1>
				<p class="text-center" style="font-weight: bold; color: green;"><?php _e( 'Herzliche Glückwünsche! Du kannst jetzt BoniPress verwenden. Was kommt als nächstes?', 'bonips' ); ?></p>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<h3><?php _e( 'Hooks aktivieren', 'bonips' ); ?></h3>
				<p><span class="description"><?php _e( 'Wenn Du Deinen Benutzern Punkte für die automatische Interaktion mit Deiner Webseite geben möchtest, solltest Du als Nächstes die Hooks aktivieren und einrichten, die Du verwenden möchtest.', 'bonips' ); ?></span></p>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => BONIPS_SLUG . '-hooks' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary"><?php _e( 'Hooks einrichten', 'bonips' ); ?></a></p>
			</div>
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<h3><?php _e( 'Erweiterungen', 'bonips' ); ?></h3>
				<p><span class="description"><?php _e( 'Wenn Du erweiterte Funktionen wie Überweisungen, Punktekäufe usw. verwenden möchtest, solltest Du als Nächstes Deine Add-Ons aktivieren und einrichten.', 'bonips' ); ?></span></p>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => BONIPS_SLUG . '-addons' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary"><?php _e( 'Add-Ons einrichten', 'bonips' ); ?></a></p>
			</div>
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<h3><?php _e( 'Adjust Settings', 'bonips' ); ?></h3>
				<p><span class="description"><?php _e( 'Wenn Du weitere Änderungen an Deinen Einstellungen vornehmen oder neue Punkttypen hinzufügen musst, kannst Du die Einstellungen Deines Standardpunkttyps aufrufen.', 'bonips' ); ?></span></p>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => BONIPS_SLUG . '-settings' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary"><?php _e( 'Einstellungen anzeigen', 'bonips' ); ?></a></p>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
jQuery(function($) {

	$( '#toggle-advanced-options' ).click(function(){

		var hidelabel = $(this).data( 'hide' );
		var showlabel = $(this).data( 'show' );

		if ( ! $(this).hasClass( 'open' ) ) {
			$( '#bonips-advanced-setup-options' ).slideDown();
			$(this).text( hidelabel ).addClass( 'open' );
		}
		else {
			$( '#bonips-advanced-setup-options' ).slideUp();
			$(this).text( showlabel ).removeClass( 'open' );
		}

	});

	$( '#boniPS-wrap' ).on( 'submit', 'form#bonips-setup-form', function(e){

		var progressbox  = $( '#bonips-setup-progress' );
		var completedbox = $( '#bonips-setup-completed' );
		var setupform    = $(this);

		e.preventDefault();

		$.ajax({
			type       : "POST",
			data       : {
				action   : 'bonips-setup',
				setup    : $(this).serialize(),
				token    : '<?php echo wp_create_nonce( 'bonips-run-setup' ); ?>'
			},
			dataType   : "JSON",
			url        : ajaxurl,
			beforeSend : function(){

				setupform.hide();
				progressbox.show();

				if ( $( '#toggle-advanced-options' ).hasClass( 'open' ) )
					$( '#toggle-advanced-options' ).click();
				

			},
			success    : function( response ) {

				console.log( response );

				if ( response.success === undefined )
					location.reload();

				else {

					progressbox.hide();

					if ( response.success ) {
						completedbox.slideDown();
						setupform.remove();
					}
					else {
						$( '#bonips-form-content' ).empty().append( response.data );
						setupform.slideDown();
					}

				}

			}
		});

	});

});
</script>
<?php

		}

		/**
		 * New Point Type Form
		 * @since 1.7
		 * @version 1.1
		 */
		protected function new_point_type( $posted = array() ) {

			$bonips = bonips();
			$posted = wp_parse_args( $posted, $bonips->defaults() );

?>
<div class="row">
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Beschriftungen', 'bonips' ); ?></h3>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-name-singular"><?php _e( 'Einzahl', 'bonips' ); ?></label>
					<input type="text" name="first_type[name][singular]" id="bonips-setup-name-singular" placeholder="<?php _e( 'Erforderlich', 'bonips' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['name']['singular'] ); ?>" />
				</div>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-name-plural"><?php _e( 'Mehrzahl', 'bonips' ); ?></label>
					<input type="text" name="first_type[name][plural]" id="bonips-setup-name-plural" placeholder="<?php _e( 'Erforderlich', 'bonips' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['name']['plural'] ); ?>" />
				</div>
			</div>
		</div>
		<p><span class="description"><?php _e( 'Diese Beschriftungen werden im gesamten Administrationsbereich und bei der Präsentation von Punkten für Deine Benutzer verwendet.', 'bonips' ); ?></span></p>
	</div>
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Format', 'bonips' ); ?></h3>
		<div class="row">
			<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-before"><?php _e( 'Präfix', 'bonips' ); ?></label>
					<input type="text" name="first_type[before]" id="bonips-setup-before" class="form-control" value="<?php echo esc_attr( $posted['before'] ); ?>" />
				</div>
			</div>
			<div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-format-separators-thousand"><?php _e( 'Trennzeichen', 'bonips' ); ?></label>
					<div class="form-inline">
						<label>1</label> <input type="text" name="first_type[format][separators][thousand]" id="bonips-setup-format-separators-thousand" placeholder="," class="form-control" size="2" value="<?php echo esc_attr( $posted['format']['separators']['thousand'] ); ?>" /> <label>000</label> <input type="text" name="first_type[format][separators][decimal]" id="bonips-setup-format-separators-decimal" placeholder="." class="form-control" size="2" value="<?php echo esc_attr( $posted['format']['separators']['decimal'] ); ?>" /> <label>00</label>
					</div>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for=""><?php _e( 'Dezimalstellen', 'bonips' ); ?></label>
					<input type="text" name="first_type[format][decimals]" id="bonips-setup-format-decimals" placeholder="0" class="form-control" value="<?php echo esc_attr( $posted['format']['decimals'] ); ?>" />
				</div>
			</div>
			<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for=""><?php _e( 'Suffix', 'bonips' ); ?></label>
					<input type="text" name="first_type[after]" id="bonips-setup-after" class="form-control" value="<?php echo esc_attr( $posted['after'] ); ?>" />
				</div>
			</div>
		</div>
		<p><span class="description"><?php _e( 'Setze die Dezimalstellen auf Null, wenn Du ganze Zahlen verwenden möchtest.', 'bonips' ); ?></span></p>
	</div>
</div>

<div class="row">
	<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
		<h3><?php _e( 'Sicherheit', 'bonips' ); ?></h3>
		<div class="row">
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-caps-creds"><?php _e( 'Punkteditoren', 'bonips' ); ?></label>
					<input type="text" name="first_type[caps][creds]" id="bonips-setup-caps-creds" placeholder="<?php _e( 'Erforderlich', 'bonips' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['caps']['creds'] ); ?>" />
					<p><span class="description"><?php _e( 'Die Fähigkeit von Benutzern, die Salden bearbeiten können.', 'bonips' ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-caps-plugin"><?php _e( 'Punktadministratoren', 'bonips' ); ?></label>
					<input type="text" name="first_type[caps][plugin]" id="bonips-setup-caps-plugin" placeholder="<?php _e( 'Erforderlich', 'bonips' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['caps']['plugin'] ); ?>" />
					<p><span class="description"><?php _e( 'Die Fähigkeit von Benutzern, die Einstellungen bearbeiten können.', 'bonips' ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-max"><?php _e( 'Max. Betrag', 'bonips' ); ?></label>
					<input type="text" name="first_type[max]" id="bonips-setup-max" class="form-control" value="<?php echo esc_attr( $posted['max'] ); ?>" />
					<p><span class="description"><?php _e( 'Der maximale Betrag, der in einer einzelnen Instanz ausgezahlt werden darf.', 'bonips' ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonips-setup-exclude-list"><?php _e( 'Ausschließen nach Benutzer-ID', 'bonips' ); ?></label>
					<input type="text" name="first_type[exclude][list]" id="bonips-setup-exclude-list" placeholder="<?php _e( 'Optional', 'bonips' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['exclude']['list'] ); ?>" />
					<p><span class="description"><?php _e( 'Durch Kommas getrennte Liste von Benutzer-IDs, die von der Verwendung dieses Punkttyps ausgeschlossen werden sollen.', 'bonips' ); ?></span></p>
				</div>
				<div class="form-group">
					<div class="checkbox">
						<label for="bonips-setup-exclude-cred-editors"><input type="checkbox" name="first_type[exclude][cred_editors]" id="bonips-setup-exclude-cred-editors"<?php checked( $posted['exclude']['cred_editors'], 1 ); ?> value="1" /> <?php _e( 'Punkteditoren ausschließen', 'bonips' ); ?></label>
					</div>
					<div class="checkbox">
						<label for="bonips-setup-exclude-plugin-editors"><input type="checkbox" name="first_type[exclude][plugin_editors]" id="bonips-setup-exclude-plugin-editors"<?php checked( $posted['exclude']['plugin_editors'], 1 ); ?> value="1" /> <?php _e( 'Punktadministratoren ausschließen', 'bonips' ); ?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Process Setup Steps
		 * @since 0.1
		 * @version 1.2
		 */
		public function ajax_setup() {

			// Security
			check_admin_referer( 'bonips-run-setup', 'token' );

			if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

			parse_str( $_POST['setup'], $posted );

			$errors               = array();
			$defaults             = $this->core->defaults();
			$decimals             = 0;

			if ( ! array_key_exists( 'first_type', $posted ) ) {

				ob_start();

				echo '<div class="info notice notice-info"><p>' . __( 'Bitte fülle alle erforderlichen Felder aus!', 'bonips' ) . '</a></p></div>';

				$this->new_point_type( $defaults );

				$output = ob_get_contents();
				ob_end_clean();

				wp_send_json_error( $output );

			}

			$setup                = bonips_apply_defaults( $defaults, $posted['first_type'] );
			$first_type           = $defaults;

			$singular_name        = sanitize_text_field( $setup['name']['singular'] );
			if ( empty( $singular_name ) )
				$errors[] = 'empty';

			elseif ( $singular_name != $first_type['name']['singular'] )
				$first_type['name']['singular'] = $singular_name;

			$plural_name          = sanitize_text_field( $setup['name']['plural'] );
			if ( empty( $plural_name ) )
				$errors[] = 'empty';

			elseif ( $plural_name != $first_type['name']['plural'] )
				$first_type['name']['plural'] = $plural_name;

			$first_type['before'] = sanitize_text_field( $setup['before'] );
			$first_type['after']  = sanitize_text_field( $setup['after'] );

			$point_editor_cap     = sanitize_key( $setup['caps']['creds'] );
			if ( empty( $point_editor_cap ) )
				$errors[] = 'empty';

			if ( $point_editor_cap != $first_type['caps']['creds'] )
				$first_type['caps']['creds'] = $point_editor_cap;

			$point_admin_cap      = sanitize_key( $setup['caps']['plugin'] );
			if ( empty( $point_admin_cap ) )
				$errors[] = 'empty';

			if ( $point_admin_cap != $first_type['caps']['plugin'] )
				$first_type['caps']['plugin'] = $point_admin_cap;

			if ( absint( $setup['format']['decimals'] ) > 0 ) {
				$first_type['format']['type']     = 'decimal';
				$first_type['format']['decimals'] = absint( $setup['format']['decimals'] );
				$decimals                         = $first_type['format']['decimals'];
			}

			$errors = apply_filters( 'bonips_setup_errors', $errors, $posted );

			// Something went wrong
			if ( ! empty( $errors ) ) {

				ob_start();

				echo '<div class="info notice notice-info"><p>' . __( 'Bitte fülle alle erforderlichen Felder aus!', 'bonips' ) . '</a></p></div>';

				$this->new_point_type( $setup );

				$output = ob_get_contents();
				ob_end_clean();

				wp_send_json_error( apply_filters( 'bonips_setup_error_output', $output, $posted ) );

			}

			// Save our first point type
			bonips_update_option( 'bonips_pref_core', $first_type );

			// Install database
			if ( ! function_exists( 'bonips_install_log' ) )
				require_once boniPS_INCLUDES_DIR . 'bonips-functions.php';

			bonips_install_log( $decimals, true );

			bonips_add_option( 'bonips_setup_completed', time() );

			// Return the good news
			wp_send_json_success();

		}

	}
endif;
