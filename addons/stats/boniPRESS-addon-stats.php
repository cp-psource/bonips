<?php
/**
 * Addon: Stats
 * Addon URI: http://bonipress.me/add-ons/stats/
 * Version: 2.0
 */
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

define( 'boniPRESS_STATS_VERSION',     '2.0' );
define( 'boniPRESS_STATS',             __FILE__ );
define( 'boniPRESS_STATS_DIR',         boniPRESS_ADDONS_DIR . 'stats/' );

// Acceptable values: hex, rgb or rgba
if ( ! defined( 'BONIPS_STATS_COLOR_TYPE' ) )
	define( 'BONIPS_STATS_COLOR_TYPE', 'hex' );

require_once boniPRESS_STATS_DIR . 'includes/bonipress-stats-functions.php';
require_once boniPRESS_STATS_DIR . 'includes/bonipress-stats-object.php';
require_once boniPRESS_STATS_DIR . 'includes/bonipress-stats-shortcodes.php';

do_action( 'bonipress_stats_load_widgets' );

/**
 * boniPRESS_Stats_Module class
 * @since 1.6
 * @version 2.0
 */
if ( ! class_exists( 'boniPRESS_Stats_Module' ) ) :
	class boniPRESS_Stats_Module extends boniPRESS_Module {

		/**
		 * Construct
		 */
		public function __construct( $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( 'boniPRESS_Stats_Module', array(
				'module_name' => 'stats',
				'defaults'    => array(
					'color_positive' => '',
					'color_negative' => '',
					'animate'        => 1,
					'bezier'         => 1,
					'caching'        => 'off'
				),
				'accordion'   => false,
				'register'    => false,
				'add_to_core' => false
			), $type );

		}

		/**
		 * Load
		 * @since 2.0
		 * @version 1.0
		 */
		public function load() {

			global $bonipress_stats_cleared, $bonipress_user_stats_cleared;

			$bonipress_stats_cleared      = false;
			$bonipress_user_stats_cleared = false;

			add_action( 'bonipress_register_assets',      array( $this, 'register_assets' ) );
			add_action( 'bonipress_init',                 array( $this, 'module_init' ) );
			add_action( 'bonipress_admin_init',           array( $this, 'module_admin_init' ) );
			add_action( 'bonipress_front_enqueue_footer', array( $this, 'maybe_enqueue_scripts' ) );

			add_action( 'bonipress_update_user_balance',  array( $this, 'clear_user_data' ) );
			add_action( 'bonipress_set_user_balance',     array( $this, 'clear_user_data' ) );

			add_filter( 'bonipress_add_finished',         array( $this, 'clear_data' ) );

			add_action( 'bonipress_delete_log_entry',     array( $this, 'force_clear_data' ) );
			add_action( 'bonipress_update_log_entry',     array( $this, 'force_clear_data' ) );

			add_action( 'bonipress_deleted_log_entry',    array( $this, 'force_clear_user_data' ) );
			add_action( 'bonipress_updated_log_entry',    array( $this, 'force_clear_user_data' ) );

		}

		/**
		 * Init
		 * @since 1.6
		 * @version 1.0
		 */
		public function module_init() {

			$this->register_shortcodes();

			add_action( 'bonipress_admin_enqueue', array( $this, 'admin_enqueue' ) );

		}

		/**
		 * Register Shortcodes
		 * @since 1.8
		 * @version 1.0
		 */
		public function register_shortcodes() {

			add_shortcode( BONIPS_SLUG . '_chart_circulation',      'bonipress_render_chart_circulation' );
			add_shortcode( BONIPS_SLUG . '_chart_gain_loss',        'bonipress_render_chart_gain_vs_loss' );

			add_shortcode( BONIPS_SLUG . '_chart_history',          'bonipress_render_chart_history' );
			add_shortcode( BONIPS_SLUG . '_chart_balance_history',  'bonipress_render_chart_balance_history' );
			add_shortcode( BONIPS_SLUG . '_chart_instance_history', 'bonipress_render_chart_instance_history' );

			add_shortcode( BONIPS_SLUG . '_chart_top_balances',     'bonipress_render_chart_top_balances' );
			add_shortcode( BONIPS_SLUG . '_chart_top_instances',    'bonipress_render_chart_top_instances' );

		}

		/**
		 * Init
		 * @since 1.6
		 * @version 1.1
		 */
		public function module_admin_init() {

			add_action( 'bonipress_overview_after',  array( $this, 'overview_after' ) );

			foreach ( $this->point_types as $type_id => $label ) {

				add_action( 'bonipress_overview_total_' . $type_id, array( $this, 'overview_total' ), 10, 3 );

			}

			add_action( 'bonipress_after_core_prefs', array( $this, 'after_general_settings' ) );
			add_filter( 'bonipress_save_core_prefs',  array( $this, 'sanitize_extra_settings' ), 90, 3 );

			if ( count( $this->point_types ) > 1 ) {

				$priority = 10;
				foreach ( $this->point_types as $type_id => $label ) {

					add_action( 'bonipress_after_core_prefs' . $type_id, array( $this, 'after_general_settings' ), $priority );
					add_filter( 'bonipress_save_core_prefs' . $type_id,  array( $this, 'sanitize_extra_settings' ), $priority, 3 );

					$priority += 10;

				}
			}

		}

		/**
		 * Register Assets
		 * @since 2.0
		 * @version 1.0
		 */
		public function register_assets() {

			global $bonipress_charts;

			$bonipress_charts = array();

			// Built-in
			wp_register_style( 'bonipress-stats', plugins_url( 'assets/css/bonipress-statistics.css', boniPRESS_STATS ), array(), boniPRESS_STATS_VERSION, 'all' );

			// Chart Libraries
			wp_register_script( 'peity',     plugins_url( 'assets/libs/peity.min.js', boniPRESS_STATS ), array( 'jquery' ), '3.2.1', true );
			wp_register_script( 'charts-js', plugins_url( 'assets/libs/Chart.bundle.min.js', boniPRESS_STATS ), array( 'jquery' ), '2.7', true );

			wp_register_script( 'bonipress-stats', plugins_url( 'assets/js/bonipress-statistics.js', boniPRESS_STATS ), array( 'jquery', 'charts-js' ), boniPRESS_STATS_VERSION, true );

		}

		/**
		 * Maybe Enqueue Scripts
		 * @since 2.0
		 * @version 1.0
		 */
		public function maybe_enqueue_scripts() {

			global $bonipress_charts;

			if ( ! empty( $bonipress_charts ) && is_array( $bonipress_charts ) ) {

				wp_localize_script(
					'bonipress-stats',
					'boniPRESSStats',
					array(
						'globals' => array(),
						'charts'  => $bonipress_charts
					)
				);

				wp_enqueue_script( 'bonipress-stats' );

			}

		}

		/**
		 * Clear Data
		 * Will attempt to clear the stats data, assuming we can based on our setup.
		 * @since 2.0
		 * @version 1.0
		 */
		public function clear_data( $value ) {

			global $bonipress_stats_cleared;

			if ( $bonipress_stats_cleared === true ) return $value;

			bonipress_delete_stats_data();

			$bonipress_stats_cleared = true;

			return $value;

		}

		/**
		 * Clear User Data
		 * Will attempt to clear the stats data for a user, assuming we can based on our setup.
		 * @since 2.0
		 * @version 1.0
		 */
		public function clear_user_data( $user_id ) {

			global $bonipress_user_stats_cleared;

			if ( $bonipress_user_stats_cleared === true ) return $user_id;

			bonipress_delete_user_stats_data( $user_id );

			$bonipress_user_stats_cleared = true;

			return $user_id;

		}

		/**
		 * Force Clear Data
		 * Situations where stats data must be cleared no matter what we set for our setup.
		 * Mainly used when admin edits / deletes log entries in the wp-admin area.
		 * @since 2.0
		 * @version 1.0
		 */
		public function force_clear_data() {

			bonipress_delete_stats_data( true );

		}

		/**
		 * Force Clear User Data
		 * Situations where stats data must be cleared no matter what we set for our setup.
		 * Mainly used when admin edits / deletes log entries in the wp-admin area.
		 * @since 2.0
		 * @version 1.0
		 */
		public function force_clear_user_data( $user_id ) {

			bonipress_delete_user_stats_data( $user_id, true );

		}

		/**
		 * Overview Total
		 * @since 2.0
		 * @version 1.0
		 */
		public function overview_total( $point_type, $total, $data ) {

			$color = bonipress_get_type_color( $point_type );
			$data  = bonipress_get_history_data( $point_type );

			if ( ! empty( $data ) ) {

				$values = array();
				foreach ( $data as $dataset ) {
					foreach ( $dataset as $set )
						$values[] = $set->value;
				}

				echo '<span class="' . BONIPS_SLUG . '-stats-bar" data-type="' . $point_type . '" data-positive="' . $color['positive'] . '" data-negative="' . $color['negative'] . '" style="display: none;">' . implode( ',', $values ) . '</span>';

			}

		}

		/**
		 * Overview After
		 * @since 2.0
		 * @version 1.0
		 */
		public function overview_after() {

?>
<script type="text/javascript">
jQuery(function($){

	$( 'span.<?php echo BONIPS_SLUG; ?>-stats-bar' ).each(function(index,item){

		var barchart      = $(this);
		var positivecolor = barchart.data( 'positive' );
		var negativecolor = barchart.data( 'negative' );

		barchart.peity( "bar", {
			width : '56',
			fill  : function(value) {
				if ( value == 0 ) return '#ededed';
				return value > 0 ? positivecolor : negativecolor
			}
		});

	});

});
</script>
<?php

		}

		/**
		 * Admin Enqueue
		 * @since 2.0
		 * @version 1.0
		 */
		public function admin_enqueue( $hook ) {

			$screen = get_current_screen();

			if ( 'dashboard' == $screen->id ) {

				wp_enqueue_script( 'peity' );

			}

		}

		/**
		 * Add-on Settings
		 * @since 2.0
		 * @version 1.0
		 */
		public function after_general_settings( $bonipress = NULL ) {

			$prefs             = $this->stats;
			$this->add_to_core = true;
			if ( $bonipress->bonipress_type != BONIPS_DEFAULT_TYPE_KEY ) {

				if ( ! isset( $bonipress->stats ) )
					$prefs = $this->default_prefs;
				else
					$prefs = $bonipress->stats;

				$this->option_id = $bonipress->option_id;

			}

			$colors = bonipress_get_type_color( $bonipress->bonipress_type );
			if ( empty( $prefs['color_positive'] ) ) $prefs['color_positive'] = $colors['positive'];
			if ( empty( $prefs['color_negative'] ) ) $prefs['color_negative'] = $colors['negative'];

?>
<h4><span class="dashicons dashicons-admin-plugins static"></span><?php _e( 'Statistics', 'bonipress' ); ?></h4>
<div class="body" style="display:none;">

	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

			<h3><?php _e( 'Statistics Color', 'bonipress' ); ?></h3>
			<div class="row">
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

					<div class="form-group">
						<label for="<?php echo $this->field_id( 'color_positive' ); ?>"><?php _e( 'Positive Values', 'bonipress' ); ?></label>
						<input type="text" name="<?php echo $this->field_name( 'color_positive' ); ?>" id="<?php echo $this->field_id( 'color_positive' ); ?>" value="<?php echo esc_attr( $prefs['color_positive'] ); ?>" class="form-control <?php if ( BONIPS_STATS_COLOR_TYPE == 'hex' ) echo ' wp-color-picker-field" data-default-color="#dedede'; ?>" />
					</div>

				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

					<div class="form-group">
						<label for="<?php echo $this->field_id( 'color_negative' ); ?>"><?php _e( 'Negative Values', 'bonipress' ); ?></label>
						<input type="text" name="<?php echo $this->field_name( 'color_negative' ); ?>" id="<?php echo $this->field_id( 'color_negative' ); ?>" value="<?php echo esc_attr( $prefs['color_negative'] ); ?>" class="form-control <?php if ( BONIPS_STATS_COLOR_TYPE == 'hex' ) echo ' wp-color-picker-field" data-default-color="#dedede'; ?>" />
					</div>

				</div>
			</div>

		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

<?php

			if ( $bonipress->bonipress_type == BONIPS_DEFAULT_TYPE_KEY ) :

				$cache_options = bonipress_get_stats_cache_times();

?>

			<h3><?php _e( 'Optimization', 'bonipress' ); ?></h3>
			<p><span class="description"><?php _e( 'Disabling these features can improve render time of your charts, especially if you are showing a large number of charts on a single page. You can also select to disable these features when using the chart shortcodes.', 'bonipress' ); ?></span></p>
			<div class="row">
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

					<div class="form-group">
						<div class="checkbox">
							<label for="<?php echo $this->field_id( 'animate' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'animate' ); ?>" id="<?php echo $this->field_id( 'animate' ); ?>"<?php checked( $prefs['animate'], 1 ); ?> value="1" /> <?php _e( 'Animate Charts', 'bonipress' ); ?></label>
						</div>
					</div>

				</div>
				<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">

					<div class="form-group">
						<div class="checkbox">
							<label for="<?php echo $this->field_id( 'bezier' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'bezier' ); ?>" id="<?php echo $this->field_id( 'bezier' ); ?>"<?php checked( $prefs['bezier'], 1 ); ?> value="1" /> <?php _e( 'Use Bezier Curves', 'bonipress' ); ?></label>
						</div>
					</div>

				</div>
			</div>

			<div class="form-group">
				<label for="<?php echo $this->field_id( 'caching' ); ?>"><?php _e( 'Caching', 'bonipress' ); ?></label>
				<select name="<?php echo $this->field_name( 'caching' ); ?>" id="<?php echo $this->field_id( 'caching' ); ?>" class="form-control">
<?php

				foreach ( $cache_options as $value => $label ) {
					echo '<option value="' . $value . '"';
					if ( $prefs['caching'] == $value ) echo ' selected="selected"';
					echo '>' . $label . '</option>';
				}

?>
				</select>
			</div>

			<?php endif; ?>

		</div>
	</div>

<?php if ( BONIPS_STATS_COLOR_TYPE == 'hex' ) : ?>
<script type="text/javascript">
jQuery(document).ready(function($){

	// Load wp color picker
	$( '.wp-color-picker-field' ).wpColorPicker();
	
});
</script>
<?php endif; ?>

</div>
<?php

		}

		/**
		 * Sanitize Settings
		 * @since 2.0
		 * @version 1.0
		 */
		public function sanitize_extra_settings( $new_data, $data, $core ) {

			$new_data['stats']['color_positive'] = sanitize_text_field( $data['stats']['color_positive'] );
			$new_data['stats']['color_negative'] = sanitize_text_field( $data['stats']['color_negative'] );

			$colors                       = bonipress_get_type_color();
			$colors[ $core->bonipress_type ] = array(
				'positive' => $new_data['stats']['color_positive'],
				'negative' => $new_data['stats']['color_negative']
			);

			bonipress_update_option( 'bonipress-point-colors', $colors );

			if ( $core->bonipress_type == BONIPS_DEFAULT_TYPE_KEY ) {

				$new_data['stats']['animate'] = ( array_key_exists( 'animate', $data['stats'] ) ? 1 : 0 );
				$new_data['stats']['bezier']  = ( array_key_exists( 'bezier', $data['stats'] ) ? 1 : 0 );
				$new_data['stats']['caching'] = sanitize_text_field( $data['stats']['caching'] );

			}

			return $new_data;

		}

	}
endif;

/**
 * Load Statistics Module
 * @since 1.7
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_load_statistics_addon' ) ) :
	function bonipress_load_statistics_addon( $modules, $point_types ) {

		$modules['solo']['stats'] = new boniPRESS_Stats_Module();
		$modules['solo']['stats']->load();

		return $modules;

	}
endif;
add_filter( 'bonipress_load_modules', 'bonipress_load_statistics_addon', 100, 2 );
