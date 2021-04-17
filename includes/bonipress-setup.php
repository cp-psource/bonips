<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * boniPRESS_Setup class
 * Used when the plugin has been activated for the first time. Handles the setup
 * wizard along with temporary admin menus.
 * @since 0.1
 * @version 1.2
 */
if ( ! class_exists( 'boniPRESS_Setup' ) ) :
	class boniPRESS_Setup {

		public $status = false;
		public $core;

		/**
		 * Construct
		 */
		public function __construct() {

			$this->core = bonipress();

		}

		/**
		 * Load Class
		 * @since 1.7
		 * @version 1.0
		 */
		public function load() {

			add_action( 'admin_notices',         array( $this, 'admin_notice' ) );
			add_action( 'admin_menu',            array( $this, 'setup_menu' ) );

			add_action( 'wp_ajax_bonipress-setup',  array( $this, 'ajax_setup' ) );

		}

		/**
		 * Setup Setup Nag
		 * @since 0.1
		 * @version 1.0.1
		 */
		public function admin_notice() {

			$screen = get_current_screen();
			if ( $screen->id == 'plugins_page_' . BONIPRESS_SLUG . '-setup' || ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) || ! bonipress_is_admin() ) return;

			echo '<div class="info notice notice-info"><p>' . sprintf( __( '%s braucht Deine Aufmerksamkeit.', 'bonipress' ), bonipress_label() ) . ' <a href="' . admin_url( 'plugins.php?page=' . BONIPRESS_SLUG . '-setup' ) . '">' . __( 'Ersteinrichtung', 'bonipress' ) . '</a></p></div>';

		}

		/**
		 * Add Setup page under "Plugins"
		 * @since 0.1
		 * @version 1.0
		 */
		public function setup_menu() {

			$page = add_submenu_page(
				'plugins.php',
				__( 'BoniPress Setup', 'bonipress' ),
				__( 'BoniPress Setup', 'bonipress' ),
				'manage_options',
				BONIPRESS_SLUG . '-setup',
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

			wp_enqueue_style( 'bonipress-admin' );
			wp_enqueue_style( 'bonipress-bootstrap-grid' );
			wp_enqueue_style( 'bonipress-forms' );

		}

		/**
		 * Setup Screen
		 * Outputs the setup page.
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function setup_page() {

			$whitelabel = bonipress_label();

?>
<style type="text/css">
#boniPRESS-wrap p { font-size: 13px; line-height: 17px; }
#bonipress-setup-completed, #bonipress-setup-progress { padding-top: 48px; }
#bonipress-setup-completed h1, #bonipress-setup-progress h1 { font-size: 3em; line-height: 3.2em; }
pre { margin: 0 0 12px 0; padding: 10px; background-color: #dedede; }
</style>
<div class="wrap bonipress-metabox" id="boniPRESS-wrap">
	<h1><?php printf( __( '%s Einrichtung', 'bonipress' ), $whitelabel ); ?></h1>
	<p><?php printf( __( 'Bevor Du %s verwenden kannst, musst Du Deinen ersten Punkttyp einrichten. Dazu gehört, wie Du Deine Punkte nennen möchtest, wie diese Punkte dargestellt werden und wer Zugriff darauf hat.', 'bonipress' ), $whitelabel ); ?></p>
	<form method="post" action="" class="form" id="bonipress-setup-form">

		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<h1><?php _e( 'Dein erster Punkttyp', 'bonipress' ); ?></h1>
			</div>
		</div>

		<div id="bonipress-form-content">

			<?php $this->new_point_type(); ?>

			<?php do_action( 'bonipress_setup_after_form' ); ?>

		</div>

		<div id="bonipress-advanced-setup-options" style="display: none;">

			<div class="row">
				<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
					<h1><?php _e( 'Erweiterte Einstellungen', 'bonipress' ); ?></h1>
				</div>
			</div>

			<div class="row">
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<h3><?php _e( 'Ändere den Standardpunkttypschlüssel', 'bonipress' ); ?></h3>
					<pre>define( 'BONIPRESS_DEFAULT_TYPE_KEY', 'yourkey' );</pre>
					<p><span class="description"><?php _e( 'Du kannst den zum Speichern des Standardpunkttyps verwendeten Metaschlüssel mithilfe der Konstante BONIPRESS_DEFAULT_TYPE_KEY ändern. Kopiere den obigen Code in Deine zu verwendende Datei wp-config.php.', 'bonipress' ); ?></span></p>
					<p><span class="description"><?php _e( 'Wenn Du den Standard-Metaschlüssel ändern möchtest, solltest Du dies tun, bevor Du mit diesem Setup fortfährst!', 'bonipress' ); ?></span></p>
				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<h3><?php _e( 'Whitelabel', 'bonipress' ); ?></h3>
					<pre>define( 'BONIPRESS_DEFAULT_LABEL', 'SuperPoints' );</pre>
					<p><span class="description"><?php _e( 'Du kannst boniPRESS mit der Konstante BONIPRESS_DEFAULT_LABEL neu beschriften. Kopiere den obigen Code zur Verwendung in Deine Datei wp-config.php.', 'bonipress' ); ?></span></p>
				</div>
			</div>

		</div>

		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<p><input type="submit" class="button button-primary button-large" value="<?php _e( 'Punkttyp erstellen', 'bonipress' ); ?>" /><button type="button" id="toggle-advanced-options" class="button button-secondary pull-right" data-hide="<?php _e( 'Ausblenden', 'bonipress' ); ?>" data-show="<?php _e( 'Fortgeschritten', 'bonipress' ); ?>"><?php _e( 'Fortgeschritten', 'bonipress' ); ?></button></p>
			</div>
		</div>

	</form>
	<div id="bonipress-setup-progress" style="display: none;">
		<h1 class="text-center"><?php _e( 'Verarbeitung...', 'bonipress' ); ?></h1>
	</div>
	<div id="bonipress-setup-completed" style="display: none;">
		<div class="row">
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<h1 class="text-center"><?php _e( 'Einrichtung abgeschlossen!', 'bonipress' ); ?></h1>
				<p class="text-center" style="font-weight: bold; color: green;"><?php _e( 'Herzliche Glückwünsche! Du kannst jetzt BoniPress verwenden. Was kommt als nächstes?', 'bonipress' ); ?></p>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<h3><?php _e( 'Hooks aktivieren', 'bonipress' ); ?></h3>
				<p><span class="description"><?php _e( 'Wenn Du Deinen Benutzern Punkte für die automatische Interaktion mit Deiner Webseite geben möchtest, solltest Du als Nächstes die Hooks aktivieren und einrichten, die Du verwenden möchtest.', 'bonipress' ); ?></span></p>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => BONIPRESS_SLUG . '-hooks' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary"><?php _e( 'Hooks einrichten', 'bonipress' ); ?></a></p>
			</div>
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<h3><?php _e( 'Erweiterungen', 'bonipress' ); ?></h3>
				<p><span class="description"><?php _e( 'Wenn Du erweiterte Funktionen wie Überweisungen, Punktekäufe usw. verwenden möchtest, solltest Du als Nächstes Deine Add-Ons aktivieren und einrichten.', 'bonipress' ); ?></span></p>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => BONIPRESS_SLUG . '-addons' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary"><?php _e( 'Add-Ons einrichten', 'bonipress' ); ?></a></p>
			</div>
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<h3><?php _e( 'Adjust Settings', 'bonipress' ); ?></h3>
				<p><span class="description"><?php _e( 'Wenn Du weitere Änderungen an Deinen Einstellungen vornehmen oder neue Punkttypen hinzufügen musst, kannst Du die Einstellungen Deines Standardpunkttyps aufrufen.', 'bonipress' ); ?></span></p>
				<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => BONIPRESS_SLUG . '-settings' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary"><?php _e( 'Einstellungen anzeigen', 'bonipress' ); ?></a></p>
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
			$( '#bonipress-advanced-setup-options' ).slideDown();
			$(this).text( hidelabel ).addClass( 'open' );
		}
		else {
			$( '#bonipress-advanced-setup-options' ).slideUp();
			$(this).text( showlabel ).removeClass( 'open' );
		}

	});

	$( '#boniPRESS-wrap' ).on( 'submit', 'form#bonipress-setup-form', function(e){

		var progressbox  = $( '#bonipress-setup-progress' );
		var completedbox = $( '#bonipress-setup-completed' );
		var setupform    = $(this);

		e.preventDefault();

		$.ajax({
			type       : "POST",
			data       : {
				action   : 'bonipress-setup',
				setup    : $(this).serialize(),
				token    : '<?php echo wp_create_nonce( 'bonipress-run-setup' ); ?>'
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
						$( '#bonipress-form-content' ).empty().append( response.data );
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

			$bonipress = bonipress();
			$posted = wp_parse_args( $posted, $bonipress->defaults() );

?>
<div class="row">
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Beschriftungen', 'bonipress' ); ?></h3>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-name-singular"><?php _e( 'Einzahl', 'bonipress' ); ?></label>
					<input type="text" name="first_type[name][singular]" id="bonipress-setup-name-singular" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['name']['singular'] ); ?>" />
				</div>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-name-plural"><?php _e( 'Mehrzahl', 'bonipress' ); ?></label>
					<input type="text" name="first_type[name][plural]" id="bonipress-setup-name-plural" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['name']['plural'] ); ?>" />
				</div>
			</div>
		</div>
		<p><span class="description"><?php _e( 'Diese Beschriftungen werden im gesamten Administrationsbereich und bei der Präsentation von Punkten für Deine Benutzer verwendet.', 'bonipress' ); ?></span></p>
	</div>
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Format', 'bonipress' ); ?></h3>
		<div class="row">
			<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-before"><?php _e( 'Präfix', 'bonipress' ); ?></label>
					<input type="text" name="first_type[before]" id="bonipress-setup-before" class="form-control" value="<?php echo esc_attr( $posted['before'] ); ?>" />
				</div>
			</div>
			<div class="col-lg-5 col-md-5 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-format-separators-thousand"><?php _e( 'Trennzeichen', 'bonipress' ); ?></label>
					<div class="form-inline">
						<label>1</label> <input type="text" name="first_type[format][separators][thousand]" id="bonipress-setup-format-separators-thousand" placeholder="," class="form-control" size="2" value="<?php echo esc_attr( $posted['format']['separators']['thousand'] ); ?>" /> <label>000</label> <input type="text" name="first_type[format][separators][decimal]" id="bonipress-setup-format-separators-decimal" placeholder="." class="form-control" size="2" value="<?php echo esc_attr( $posted['format']['separators']['decimal'] ); ?>" /> <label>00</label>
					</div>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for=""><?php _e( 'Dezimalstellen', 'bonipress' ); ?></label>
					<input type="text" name="first_type[format][decimals]" id="bonipress-setup-format-decimals" placeholder="0" class="form-control" value="<?php echo esc_attr( $posted['format']['decimals'] ); ?>" />
				</div>
			</div>
			<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for=""><?php _e( 'Suffix', 'bonipress' ); ?></label>
					<input type="text" name="first_type[after]" id="bonipress-setup-after" class="form-control" value="<?php echo esc_attr( $posted['after'] ); ?>" />
				</div>
			</div>
		</div>
		<p><span class="description"><?php _e( 'Setze die Dezimalstellen auf Null, wenn Du ganze Zahlen verwenden möchtest.', 'bonipress' ); ?></span></p>
	</div>
</div>

<div class="row">
	<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
		<h3><?php _e( 'Sicherheit', 'bonipress' ); ?></h3>
		<div class="row">
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-caps-creds"><?php _e( 'Punkteditoren', 'bonipress' ); ?></label>
					<input type="text" name="first_type[caps][creds]" id="bonipress-setup-caps-creds" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['caps']['creds'] ); ?>" />
					<p><span class="description"><?php _e( 'Die Fähigkeit von Benutzern, die Salden bearbeiten können.', 'bonipress' ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-caps-plugin"><?php _e( 'Punktadministratoren', 'bonipress' ); ?></label>
					<input type="text" name="first_type[caps][plugin]" id="bonipress-setup-caps-plugin" placeholder="<?php _e( 'Erforderlich', 'bonipress' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['caps']['plugin'] ); ?>" />
					<p><span class="description"><?php _e( 'Die Fähigkeit von Benutzern, die Einstellungen bearbeiten können.', 'bonipress' ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-max"><?php _e( 'Max. Betrag', 'bonipress' ); ?></label>
					<input type="text" name="first_type[max]" id="bonipress-setup-max" class="form-control" value="<?php echo esc_attr( $posted['max'] ); ?>" />
					<p><span class="description"><?php _e( 'Der maximale Betrag, der in einer einzelnen Instanz ausgezahlt werden darf.', 'bonipress' ); ?></span></p>
				</div>
			</div>
			<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
				<div class="form-group">
					<label for="bonipress-setup-exclude-list"><?php _e( 'Ausschließen nach Benutzer-ID', 'bonipress' ); ?></label>
					<input type="text" name="first_type[exclude][list]" id="bonipress-setup-exclude-list" placeholder="<?php _e( 'Optional', 'bonipress' ); ?>" class="form-control" value="<?php echo esc_attr( $posted['exclude']['list'] ); ?>" />
					<p><span class="description"><?php _e( 'Durch Kommas getrennte Liste von Benutzer-IDs, die von der Verwendung dieses Punkttyps ausgeschlossen werden sollen.', 'bonipress' ); ?></span></p>
				</div>
				<div class="form-group">
					<div class="checkbox">
						<label for="bonipress-setup-exclude-cred-editors"><input type="checkbox" name="first_type[exclude][cred_editors]" id="bonipress-setup-exclude-cred-editors"<?php checked( $posted['exclude']['cred_editors'], 1 ); ?> value="1" /> <?php _e( 'Punkteditoren ausschließen', 'bonipress' ); ?></label>
					</div>
					<div class="checkbox">
						<label for="bonipress-setup-exclude-plugin-editors"><input type="checkbox" name="first_type[exclude][plugin_editors]" id="bonipress-setup-exclude-plugin-editors"<?php checked( $posted['exclude']['plugin_editors'], 1 ); ?> value="1" /> <?php _e( 'Punktadministratoren ausschließen', 'bonipress' ); ?></label>
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
			check_admin_referer( 'bonipress-run-setup', 'token' );

			if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

			parse_str( $_POST['setup'], $posted );

			$errors               = array();
			$defaults             = $this->core->defaults();
			$decimals             = 0;

			if ( ! array_key_exists( 'first_type', $posted ) ) {

				ob_start();

				echo '<div class="info notice notice-info"><p>' . __( 'Bitte fülle alle erforderlichen Felder aus!', 'bonipress' ) . '</a></p></div>';

				$this->new_point_type( $defaults );

				$output = ob_get_contents();
				ob_end_clean();

				wp_send_json_error( $output );

			}

			$setup                = bonipress_apply_defaults( $defaults, $posted['first_type'] );
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

			$errors = apply_filters( 'bonipress_setup_errors', $errors, $posted );

			// Something went wrong
			if ( ! empty( $errors ) ) {

				ob_start();

				echo '<div class="info notice notice-info"><p>' . __( 'Bitte fülle alle erforderlichen Felder aus!', 'bonipress' ) . '</a></p></div>';

				$this->new_point_type( $setup );

				$output = ob_get_contents();
				ob_end_clean();

				wp_send_json_error( apply_filters( 'bonipress_setup_error_output', $output, $posted ) );

			}

			// Save our first point type
			bonipress_update_option( 'bonipress_pref_core', $first_type );

			// Install database
			if ( ! function_exists( 'bonipress_install_log' ) )
				require_once boniPRESS_INCLUDES_DIR . 'bonipress-functions.php';

			bonipress_install_log( $decimals, true );

			bonipress_add_option( 'bonipress_setup_completed', time() );

			// Return the good news
			wp_send_json_success();

		}

	}
endif;
