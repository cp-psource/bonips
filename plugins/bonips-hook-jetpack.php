<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Register Hook
 * @since 1.0.5
 * @version 1.1
 */
add_filter( 'bonips_setup_hooks', 'bonips_register_jetpack_hook', 75 );
function bonips_register_jetpack_hook( $installed ) {

	if ( ! defined( 'JETPACK__PLUGIN_DIR' ) ) return $installed;

	$installed['jetpack'] = array(
		'title'         => __( 'Jetpack Subscriptions', 'bonips' ),
		'description'   => __( 'Awards %_plural% for users signing up for site or comment updates using Jetpack.', 'bonips' ),
		'documentation' => 'http://codex.bonips.me/hooks/jetpack-subscriptions/',
		'callback'      => array( 'boniPS_Hook_Jetpack' )
	);

	return $installed;

}

/**
 * Jetpack Hook
 * @since 1.0.5
 * @version 1.2
 */
add_action( 'bonips_load_hooks', 'bonips_load_jetpack_hook', 75 );
function bonips_load_jetpack_hook() {

	// If the hook has been replaced or if plugin is not installed, exit now
	if ( class_exists( 'boniPS_Hook_Jetpack' ) || ! defined( 'JETPACK__PLUGIN_DIR' ) ) return;

	class boniPS_Hook_Jetpack extends boniPS_Hook {

		/**
		 * Construct
		 */
		public function __construct( $hook_prefs, $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( array(
				'id'       => 'jetpack',
				'defaults' => array(
					'subscribe_site'    => array(
						'creds'            => 1,
						'log'              => '%plural% for site subscription'
					),
					'subscribe_comment' => array(
						'creds'            => 1,
						'log'              => '%plural% for comment subscription'
					)
				)
			), $hook_prefs, $type );

		}

		/**
		 * Run
		 * @since 1.0.5
		 * @version 1.1
		 */
		public function run() {

			// Site Subscriptions
			if ( $this->prefs['subscribe_site']['creds'] != 0 )
				add_filter( 'jetpack_subscriptions_form_submission', array( $this, 'subscriptions_submit' ) );

			// Comment Subscriptions
			if ( $this->prefs['subscribe_comment']['creds'] != 0 )
				add_action( 'jetpack_subscriptions_comment_form_submission', array( $this, 'comment_submit' ), 99, 2 );

		}

		/**
		 * Site Subscriptions
		 * @since 1.0.5
		 * @version 1.0
		 */
		public function subscriptions_submit( $result = '' ) {

			if ( ! is_user_logged_in() || $result !== 'success' ) return;

			$user_id = get_current_user_id();

			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) === true ) return;

			// Ensure we only get points once
			if ( $this->core->has_entry( 'site_subscription', 0, $user_id ) ) return;

			$this->core->add_creds(
				'site_subscription',
				$user_id,
				$this->prefs['subscribe_site']['creds'],
				$this->prefs['subscribe_site']['log'],
				0,
				'',
				$this->bonips_type
			);

			do_action( 'bonips_jetpack_site', $user_id );

		}

		/**
		 * Comment Subscription
		 * @since 1.0.5
		 * @version 1.1
		 */
		public function comment_submit( $result, $post_ids ) {

			if ( ! is_user_logged_in() || $result !== 'success' || empty( $post_ids ) ) return;

			$user_id = get_current_user_id();

			// Check for exclusion
			if ( $this->core->exclude_user( $user_id ) === true ) return;

			// Award each post
			foreach ( $post_ids as $post_id ) {

				// We can only get points once per post ID
				if ( $this->core->has_entry( 'comment_subscription', $post_id, $user_id ) ) continue;

				$this->core->add_creds(
					'comment_subscription',
					$user_id,
					$this->prefs['subscribe_comment']['creds'],
					$this->prefs['subscribe_comment']['log'],
					$post_id,
					array( 'ref_type' => 'post' ),
					$this->bonips_type
				);

				do_action( 'bonips_jetpack_comment', $user_id, $post_id );

			}

		}

		/**
		 * Preferences
		 * @since 1.0.5
		 * @version 1.1
		 */
		public function preferences() {

			$prefs = $this->prefs;

?>
<div class="hook-instance">
	<h3><?php _e( 'Site Subscriptions', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'subscribe_site' => 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'subscribe_site' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'subscribe_site' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['subscribe_site']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'subscribe_site' => 'log' ) ); ?>"><?php _e( 'Log template', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'subscribe_site' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'subscribe_site' => 'log' ) ); ?>" placeholder="<?php _e( 'required', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['subscribe_site']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Comment Subscriptions', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-6 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'subscribe_comment' => 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'subscribe_comment' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'subscribe_comment' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['subscribe_comment']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'subscribe_comment' => 'log' ) ); ?>"><?php _e( 'Log template', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'subscribe_comment' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'subscribe_comment' => 'log' ) ); ?>" placeholder="<?php _e( 'required', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['subscribe_comment']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}

	}

}
