<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Register Hook
 * @since 0.1
 * @version 1.1
 */
add_filter( 'bonips_setup_hooks', 'bonips_register_buddypress_hook', 40 );
function bonips_register_buddypress_hook( $installed ) {

	if ( ! class_exists( 'BuddyPress' ) ) return $installed;

	if ( bp_is_active( 'xprofile' ) ) {
		$installed['hook_bp_profile'] = array(
			'title'         => __( 'BuddyPress: Mitglieder', 'bonips' ),
			'description'   => __( 'Vergibt %_plural% für profilbezogene Aktionen.', 'bonips' ),
			'documentation' => 'https://github.com/cp-psource/docs/buddypress-profilaktionen/',
			'callback'      => array( 'boniPS_BuddyPress_Profile' )
		);
	}

	if ( bp_is_active( 'groups' ) ) {
		$installed['hook_bp_groups'] = array(
			'title'         => __( 'BuddyPress: Gruppen', 'bonips' ),
			'description'   => __( 'Vergibt %_plural% für gruppenbezogene Aktionen. Verwende Minus, um %_plural% abzuziehen, oder Null, um einen bestimmten Hook zu deaktivieren.', 'bonips' ),
			'documentation' => 'https://github.com/cp-psource/docs/buddypress-gruppenaktionen/',
			'callback'      => array( 'boniPS_BuddyPress_Groups' )
		);
	}

	return $installed;

}

/**
 * boniPS_BuddyPress_Profile class
 * Creds for profile updates
 * @since 0.1
 * @version 1.3
 */
add_action( 'bonips_load_hooks', 'bonips_load_buddypress_profile_hook', 40 );
function bonips_load_buddypress_profile_hook() {

	// If the hook has been replaced or if plugin is not installed, exit now
	if ( class_exists( 'boniPS_BuddyPress_Profile' ) || ! class_exists( 'BuddyPress' ) ) return;

	class boniPS_BuddyPress_Profile extends boniPS_Hook {

		/**
		 * Construct
		 */
		public function __construct( $hook_prefs, $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( array(
				'id'       => 'hook_bp_profile',
				'defaults' => array(
					'update'         => array(
						'creds'         => 1,
						'log'           => '%plural% für das Aktualisieren des Profils',
						'limit'         => '0/x'
					),
					'removed_update' => array(
						'creds'         => 1,
						'log'           => '%plural% für das Entfernen der Profilaktualisierung',
						'limit'         => '0/x'
					),
					'avatar'         => array(
						'creds'         => 1,
						'log'           => '%plural% für neuen Avatar',
						'limit'         => '0/x'
					),
					'cover'         => array(
						'creds'         => 1,
						'log'           => '%plural% für neues Titelbild',
						'limit'         => '0/x'
					),
					'new_friend'     => array(
						'creds'         => 1,
						'log'           => '%plural% für neue Freundschaft',
						'block'         => 0,
						'limit'         => '0/x'
					),
					'leave_friend'   => array(
						'creds'         => '-1',
						'log'           => '%singular% Abzug für den Verlust eines Freundes',
						'limit'         => '0/x'
					),
					'new_comment'    => array(
						'creds'         => 1,
						'log'           => '%plural% für neuen Kommentar',
						'limit'         => '0/x'
					),
					'delete_comment' => array(
						'creds'         => '-1',
						'log'           => 'Abzug von %singular% für das Entfernen von Kommentaren'
					),
					'add_favorite'   => array(
						'creds'         => 1,
						'log'           => '%plural% für das Hinzufügen einer Aktivität zu den Favoriten',
						'limit'         => '0/x'
					),
					'remove_favorite' => array(
						'creds'         => '-1',
						'log'           => 'Abzug von %singular% für das Entfernen von Lieblingsaktivitäten'
					),
					'message'        => array(
						'creds'         => 1,
						'log'           => '%plural% für das Senden einer Nachricht',
						'limit'         => '0/x'
					),
					'send_gift'      => array(
						'creds'         => 1,
						'log'           => '%plural% für das Versenden eines Geschenks',
						'limit'         => '0/x'
					)
				)
			), $hook_prefs, $type );

		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {

			if ( $this->prefs['update']['creds'] != 0 )
				add_action( 'bp_activity_posted_update',          array( $this, 'new_update' ), 10, 3 );

			if ( $this->prefs['removed_update']['creds'] != 0 )
				add_action( 'bp_activity_delete',                 array( $this, 'remove_update' ), 10, 3 );

			if ( $this->prefs['avatar']['creds'] != 0 )
				add_action( 'xprofile_avatar_uploaded',           array( $this, 'avatar_upload' ) );

			if ( $this->prefs['cover']['creds'] != 0 )
				add_action( 'xprofile_cover_image_uploaded',      array( $this, 'cover_change' ) );

			if ( $this->prefs['new_friend']['creds'] < 0 && isset( $this->prefs['new_friend']['block'] ) && $this->prefs['new_friend']['block'] == 1 ) {
				add_action( 'wp_ajax_addremove_friend',           array( $this, 'ajax_addremove_friend' ), 0 );
				add_filter( 'bp_get_add_friend_button',           array( $this, 'disable_friendship' ) );
			}

			if ( $this->prefs['new_friend']['creds'] != 0 )
				add_action( 'friends_friendship_accepted',        array( $this, 'friendship_join' ), 10, 3 );

			if ( $this->prefs['leave_friend']['creds'] != 0 )
				add_action( 'friends_friendship_deleted',         array( $this, 'friendship_leave' ), 10, 3 );

			if ( $this->prefs['new_comment']['creds'] != 0 )
				add_action( 'bp_activity_comment_posted',         array( $this, 'new_comment' ), 10, 2 );

			if ( $this->prefs['delete_comment']['creds'] != 0 )
				add_action( 'bp_activity_action_delete_activity', array( $this, 'delete_comment' ), 10, 2 );

			if ( $this->prefs['add_favorite']['creds'] != 0 )
				add_action( 'bp_activity_add_user_favorite',      array( $this, 'add_to_favorites' ), 10, 2 );

			if ( $this->prefs['remove_favorite']['creds'] != 0 )
				add_action( 'bp_activity_remove_user_favorite',   array( $this, 'removed_from_favorites' ), 10, 2 );

			if ( $this->prefs['message']['creds'] != 0 )
				add_action( 'messages_message_sent',              array( $this, 'messages' ) );

			if ( $this->prefs['send_gift']['creds'] != 0 )
				add_action( 'bp_gifts_send_gifts',                array( $this, 'send_gifts' ), 10, 2 );

		}

		/**
		 * New Profile Update
		 * @since 0.1
		 * @version 1.2
		 */
		public function new_update( $content, $user_id, $activity_id ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'update', 'new_profile_update', $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_profile_update', $activity_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_profile_update',
				$user_id,
				$this->prefs['update']['creds'],
				$this->prefs['update']['log'],
				$activity_id,
				'bp_activity',
				$this->bonips_type
			);

		}

		/**
		 * Removing Profile Update
		 * @since 1.6
		 * @version 1.0
		 */
		public function remove_update( $args ) {

			if ( ! isset( $args['user_id'] ) || $args['user_id'] === false ) return;

			$user_id = absint( $args['user_id'] );

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'removed_update', 'deleted_profile_update', $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'deleted_profile_update',
				$user_id,
				$this->prefs['removed_update']['creds'],
				$this->prefs['removed_update']['log'],
				0,
				$args,
				$this->bonips_type
			);

		}

		/**
		 * Avatar Upload
		 * @since 0.1
		 * @version 1.2
		 */
		public function avatar_upload() {

			$user_id = apply_filters( 'bp_xprofile_new_avatar_user_id', bp_displayed_user_id() );

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'avatar', 'upload_avatar', $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'upload_avatar',
				$user_id,
				$this->prefs['avatar']['creds'],
				$this->prefs['avatar']['log'],
				0,
				'',
				$this->bonips_type
			);

		}

		/**
		 * Cover Upload
		 * @since 1.7
		 * @version 1.0
		 */
		public function cover_change( $user_id = NULL ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'cover', 'upload_cover', $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'upload_cover',
				$user_id,
				$this->prefs['cover']['creds'],
				$this->prefs['cover']['log'],
				0,
				'',
				$this->bonips_type
			);

		}

		/**
		 * AJAX: Add/Remove Friend
		 * Intercept addremovefriend ajax call and block
		 * action if the user can not afford new friendship.
		 * @since 1.5.4
		 * @version 1.0
		 */
		public function ajax_addremove_friend() {

			// Bail if not a POST action
			if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
				return;

			$user_id = bp_loggedin_user_id();
			$balance = $this->core->get_users_balance( $user_id, $this->bonips_type );
			$cost    = abs( $this->prefs['new_friend']['creds'] );

			// Take into account any existing requests which will be charged when the new
			// friend approves it. Prevents users from requesting more then they can afford.
			$pending_requests = $this->count_pending_requests( $user_id );
			if ( $pending_requests > 0 )
				$cost = $cost + ( $cost * $pending_requests );

			// Prevent BP from running this ajax call
			if ( $balance < $cost ) {
				echo apply_filters( 'bonips_bp_declined_addfriend', __( 'Unzureichende Mittel', 'bonips' ), $this );
				exit;
			}

		}

		/**
		 * Disable Friendship
		 * If we deduct points from a user for new friendships
		 * we disable the friendship button if the user ca not afford it.
		 * @since 1.5.4
		 * @version 1.0
		 */
		public function disable_friendship( $button ) {

			// Only applicable for Add Friend button
			if ( $button['id'] == 'not_friends' ) {

				$user_id = bp_loggedin_user_id();
				$balance = $this->core->get_users_balance( $user_id, $this->bonips_type );
				$cost    = abs( $this->prefs['new_friend']['creds'] );

				// Take into account any existing requests which will be charged when the new
				// friend approves it. Prevents users from requesting more then they can afford.
				$pending_requests = $this->count_pending_requests( $user_id );
				if ( $pending_requests > 0 )
					$cost = $cost + ( $cost * $pending_requests );

				if ( $balance < $cost )
					return array();

			}

			return $button;

		}

		/**
		 * Count Pending Friendship Requests
		 * Counts the given users pending friendship requests sent to
		 * other users.
		 * @since 1.5.4
		 * @version 1.0
		 */
		protected function count_pending_requests( $user_id ) {

			global $wpdb, $bp;

			return $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(*) 
				FROM {$bp->friends->table_name} 
				WHERE initiator_user_id = %d 
				AND is_confirmed = 0;", $user_id ) );

		}

		/**
		 * New Friendship
		 * @since 0.1
		 * @version 1.3.1
		 */
		public function friendship_join( $friendship_id, $initiator_user_id, $friend_user_id ) {

			// Make sure this is unique event
			if ( ! $this->core->exclude_user( $initiator_user_id ) && ! $this->core->has_entry( 'new_friendship', $friend_user_id, $initiator_user_id ) && ! $this->over_hook_limit( 'new_friend', 'new_friendship', $initiator_user_id ) )
				$this->core->add_creds(
					'new_friendship',
					$initiator_user_id,
					$this->prefs['new_friend']['creds'],
					$this->prefs['new_friend']['log'],
					$friend_user_id,
					array( 'ref_type' => 'user' ),
					$this->bonips_type
				);

			// Points to friend (ignored if we are deducting points for new friendships)
			if ( $this->prefs['new_friend']['creds'] > 0 && ! $this->core->exclude_user( $friend_user_id ) && ! $this->over_hook_limit( 'new_friend', 'new_friendship', $friend_user_id ) )
				$this->core->add_creds(
					'new_friendship',
					$friend_user_id,
					$this->prefs['new_friend']['creds'],
					$this->prefs['new_friend']['log'],
					$initiator_user_id,
					array( 'ref_type' => 'user' ),
					$this->bonips_type
				);

		}

		/**
		 * Ending Friendship
		 * @since 0.1
		 * @version 1.2
		 */
		public function friendship_leave( $friendship_id, $initiator_user_id, $friend_user_id ) {

			if ( ! $this->core->exclude_user( $initiator_user_id ) && ! $this->core->has_entry( 'ended_friendship', $friend_user_id, $initiator_user_id ) )
				$this->core->add_creds(
					'ended_friendship',
					$initiator_user_id,
					$this->prefs['leave_friend']['creds'],
					$this->prefs['leave_friend']['log'],
					$friend_user_id,
					array( 'ref_type' => 'user' ),
					$this->bonips_type
				);

			if ( ! $this->core->exclude_user( $friend_user_id ) )
				$this->core->add_creds(
					'ended_friendship',
					$friend_user_id,
					$this->prefs['leave_friend']['creds'],
					$this->prefs['leave_friend']['log'],
					$initiator_user_id,
					array( 'ref_type' => 'user' ),
					$this->bonips_type
				);

		}

		/**
		 * New Comment
		 * @since 0.1
		 * @version 1.2
		 */
		public function new_comment( $comment_id, $params ) {

			$user_id = bp_loggedin_user_id();

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'new_comment', 'new_comment' ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_comment', $comment_id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_comment',
				$user_id,
				$this->prefs['new_comment']['creds'],
				$this->prefs['new_comment']['log'],
				$comment_id,
				'bp_comment',
				$this->bonips_type
			);

		}

		/**
		 * Comment Deletion
		 * @since 0.1
		 * @version 1.0
		 */
		public function delete_comment( $activity_id, $user_id ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'comment_deletion', $activity_id ) ) return;

			// Execute
			$this->core->add_creds(
				'comment_deletion',
				$user_id,
				$this->prefs['delete_comment']['creds'],
				$this->prefs['delete_comment']['log'],
				$activity_id,
				'bp_comment',
				$this->bonips_type
			);

		}

		/**
		 * Add to Favorites
		 * @since 1.7
		 * @version 1.0
		 */
		public function add_to_favorites( $activity_id, $user_id ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'fave_activity', $activity_id ) ) return;

			// Execute
			$this->core->add_creds(
				'fave_activity',
				$user_id,
				$this->prefs['add_favorite']['creds'],
				$this->prefs['add_favorite']['log'],
				$activity_id,
				'bp_comment',
				$this->bonips_type
			);

		}

		/**
		 * Remove from Favorites
		 * @since 1.7
		 * @version 1.0
		 */
		public function removed_from_favorites( $activity_id, $user_id ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'unfave_activity', $activity_id ) ) return;

			// Execute
			$this->core->add_creds(
				'unfave_activity',
				$user_id,
				$this->prefs['remove_favorite']['creds'],
				$this->prefs['remove_favorite']['log'],
				$activity_id,
				'bp_comment',
				$this->bonips_type
			);

		}

		/**
		 * New Message
		 * @since 0.1
		 * @version 1.1
		 */
		public function messages( $message ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $message->sender_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'message', 'new_message', $message->sender_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_message', $message->thread_id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_message',
				$message->sender_id,
				$this->prefs['message']['creds'],
				$this->prefs['message']['log'],
				$message->thread_id,
				'bp_message',
				$this->bonips_type
			);

		}

		/**
		 * Send Gift
		 * @since 0.1
		 * @version 1.1
		 */
		public function send_gifts( $to_user_id, $from_user_id ) {

			// Check if sender is excluded
			if ( $this->core->exclude_user( $from_user_id ) ) return;

			// Check if recipient is excluded
			if ( $this->core->exclude_user( $to_user_id ) ) return;

			// Limit
			if ( ! $this->over_hook_limit( 'send_gift', 'sending_gift', $from_user_id ) )
				$this->core->add_creds(
					'sending_gift',
					$from_user_id,
					$this->prefs['send_gift']['creds'],
					$this->prefs['send_gift']['log'],
					$to_user_id,
					'bp_gifts',
					$this->bonips_type
				);

		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.2
		 */
		public function preferences() {

			$prefs = $this->prefs;

			if ( ! isset( $prefs['removed_update'] ) )
				$prefs['removed_update'] = array( 'creds' => 0, 'limit' => '0/x', 'log' => '%plural% Abzug für das Entfernen der Profilaktualisierung' );

			$friend_block = 0;
			if ( isset( $prefs['new_friend']['block'] ) )
				$friend_block = $prefs['new_friend']['block'];

?>
<div class="hook-instance">
	<h3><?php _e( 'Neue Profilaktivität', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'update', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'update', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['update']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'update', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'update', 'limit' ) ), $this->field_id( array( 'update', 'limit' ) ), $prefs['update']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'update', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'update', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'update', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['update']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Gelöschte Profilaktivität', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'removed_update', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'removed_update', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'removed_update', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['removed_update']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'removed_update', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'removed_update', 'limit' ) ), $this->field_id( array( 'removed_update', 'limit' ) ), $prefs['removed_update']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'removed_update', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'removed_update', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'removed_update', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['removed_update']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neuer Profil-Avatar-Upload', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'avatar', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['avatar']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'avatar', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'avatar', 'limit' ) ), $this->field_id( array( 'avatar', 'limit' ) ), $prefs['avatar']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'avatar', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['avatar']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neues Profilcover hochladen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cover', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'cover', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'cover', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['cover']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cover', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'cover', 'limit' ) ), $this->field_id( array( 'cover', 'limit' ) ), $prefs['cover']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cover', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'cover', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'cover', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['cover']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neue Freundschaften', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_friend', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_friend', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_friend', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_friend']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_friend', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_friend', 'limit' ) ), $this->field_id( array( 'new_friend', 'limit' ) ), $prefs['new_friend']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_friend', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_friend', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_friend', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['new_friend']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'new_friend', 'block' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'new_friend', 'block' ) ); ?>"<?php checked( $friend_block, 1 ); ?> id="<?php echo $this->field_id( array( 'new_friend', 'block' ) ); ?>" value="1" /> <?php echo $this->core->template_tags_general( __( 'Benutzer mit Nullguthaben können keine Freunde hinzufügen. Erfordert, dass Du %_plural% für das Hinzufügen eines neuen Freundes abziehst.', 'bonips' ) ); ?></label>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Freundschaften beenden', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'leave_friend', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'leave_friend', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'leave_friend', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['leave_friend']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'leave_friend', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'leave_friend', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'leave_friend', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['leave_friend']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neuer Kommentar', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_comment', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_comment', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_comment', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_comment']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_comment', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_comment', 'limit' ) ), $this->field_id( array( 'new_comment', 'limit' ) ), $prefs['new_comment']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_comment', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_comment', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_comment', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['new_comment']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Gelöschter Kommentar', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_comment', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_comment', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_comment', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['delete_comment']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_comment', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_comment', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_comment', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['delete_comment']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Lieblingsbeschäftigung', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'add_favorite', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'add_favorite', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'add_favorite', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['add_favorite']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'add_favorite', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'add_favorite', 'limit' ) ), $this->field_id( array( 'add_favorite', 'limit' ) ), $prefs['add_favorite']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'add_favorite', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'add_favorite', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'add_favorite', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['add_favorite']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Lieblingsaktivität entfernen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'remove_favorite', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'remove_favorite', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'remove_favorite', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['remove_favorite']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'remove_favorite', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'remove_favorite', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'remove_favorite', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['remove_favorite']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neue private Nachricht', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'message', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'message', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'message', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['message']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'message', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'message', 'limit' ) ), $this->field_id( array( 'message', 'limit' ) ), $prefs['message']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'message', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'message', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'message', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['message']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Geschenk senden', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'send_gift', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'send_gift', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'send_gift', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['send_gift']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'send_gift', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'send_gift', 'limit' ) ), $this->field_id( array( 'send_gift', 'limit' ) ), $prefs['send_gift']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'send_gift', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'send_gift', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'send_gift', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['send_gift']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Sanitise Preferences
		 * @since 1.6
		 * @version 1.1
		 */
		public function sanitise_preferences( $data ) {

			if ( isset( $data['update']['limit'] ) && isset( $data['update']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['update']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['update']['limit'] = $limit . '/' . $data['update']['limit_by'];
				unset( $data['update']['limit_by'] );
			}

			if ( isset( $data['removed_update']['limit'] ) && isset( $data['removed_update']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['removed_update']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['removed_update']['limit'] = $limit . '/' . $data['removed_update']['limit_by'];
				unset( $data['removed_update']['limit_by'] );
			}

			if ( isset( $data['avatar']['limit'] ) && isset( $data['avatar']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['avatar']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['avatar']['limit'] = $limit . '/' . $data['avatar']['limit_by'];
				unset( $data['avatar']['limit_by'] );
			}

			if ( isset( $data['cover']['limit'] ) && isset( $data['cover']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['cover']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['cover']['limit'] = $limit . '/' . $data['cover']['limit_by'];
				unset( $data['cover']['limit_by'] );
			}

			if ( isset( $data['new_friend']['limit'] ) && isset( $data['new_friend']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['new_friend']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['new_friend']['limit'] = $limit . '/' . $data['new_friend']['limit_by'];
				unset( $data['new_friend']['limit_by'] );
			}

			$data['new_friend']['block'] = ( isset( $data['new_friend']['block'] ) ) ? absint( $data['new_friend']['block'] ) : 0;

			if ( isset( $data['new_comment']['limit'] ) && isset( $data['new_comment']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['new_comment']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['new_comment']['limit'] = $limit . '/' . $data['new_comment']['limit_by'];
				unset( $data['new_comment']['limit_by'] );
			}

			if ( isset( $data['add_favorite']['limit'] ) && isset( $data['add_favorite']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['add_favorite']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['add_favorite']['limit'] = $limit . '/' . $data['add_favorite']['limit_by'];
				unset( $data['add_favorite']['limit_by'] );
			}

			if ( isset( $data['message']['limit'] ) && isset( $data['message']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['message']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['message']['limit'] = $limit . '/' . $data['message']['limit_by'];
				unset( $data['message']['limit_by'] );
			}

			if ( isset( $data['send_gift']['limit'] ) && isset( $data['send_gift']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['send_gift']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['send_gift']['limit'] = $limit . '/' . $data['send_gift']['limit_by'];
				unset( $data['send_gift']['limit_by'] );
			}

			return $data;

		}

	}

}

/**
 * boniPS_BuddyPress_Groups class
 * Creds for groups actions such as joining / leaving, creating / deleting, new topics / edit topics or new posts / edit posts
 * @since 0.1
 * @version 1.1
 */
add_action( 'bonips_load_hooks', 'bonips_load_buddypress_groups_hook', 45 );
function bonips_load_buddypress_groups_hook() {

	// If the hook has been replaced or if plugin is not installed, exit now
	if ( class_exists( 'boniPS_BuddyPress_Groups' ) || ! class_exists( 'BuddyPress' ) ) return;

	class boniPS_BuddyPress_Groups extends boniPS_Hook {

		/**
		 * Construct
		 */
		function __construct( $hook_prefs, $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( array(
				'id'       => 'hook_bp_groups',
				'defaults' => array(
					'create'     => array(
						'creds'     => 10,
						'log'       => '%plural% für das Erstellen einer neuen Gruppe',
						'min'       => 0
					),
					'delete'     => array(
						'creds'     => '-10',
						'log'       => '%singular% Abzug für das Löschen einer Gruppe'
					),
					'new_topic'  => array(
						'creds'     => 1,
						'log'       => '%plural% für neues Gruppenthema',
						'limit'     => '0/x'
					),
					'edit_topic' => array(
						'creds'     => 1,
						'log'       => '%plural% für das Aktualisieren des Gruppenthemas',
						'limit'     => '0/x'
					),
					'new_post'   => array(
						'creds'     => 1,
						'log'       => '%plural% für neuen Gruppenbeitrag',
						'limit'     => '0/x'
					),
					'edit_post'  => array(
						'creds'     => 1,
						'log'       => '%plural% für die Aktualisierung des Gruppenbeitrags',
						'limit'     => '0/x'
					),
					'join'       => array(
						'creds'     => 1,
						'log'       => '%plural% für den Beitritt zu einer neuen Gruppe',
						'limit'     => '0/x'
					),
					'leave'      => array(
						'creds'     => '-5',
						'log'       => '%singular% Abzug für Austritt aus Gruppe'
					),
					'avatar'     => array(
						'creds'     => 1,
						'log'       => '%plural% für neuen Gruppenavatar',
						'limit'     => '0/x'
					),
					'cover'      => array(
						'creds'      => 1,
						'log'        => '%plural% für neues Titelbild',
						'limit'      => '0/x'
					),
					'comments'   => array(
						'creds'     => 1,
						'log'       => '%plural% für neuen Gruppenkommentar',
						'limit'     => '0/x'
					)
				)
			), $hook_prefs, $type );

		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.0
		 */
		public function run() {

			if ( $this->prefs['create']['creds'] != 0 && $this->prefs['create']['min'] == 0 )
				add_action( 'groups_group_create_complete',     array( $this, 'create_group' ) );

			if ( $this->prefs['create']['creds'] < 0 )
				add_filter( 'bp_user_can_create_groups',        array( $this, 'restrict_group_creation' ), 99, 2 );

			if ( $this->prefs['delete']['creds'] != 0 )
				add_action( 'groups_group_deleted',             array( $this, 'delete_group' ) );

			if ( $this->prefs['new_topic']['creds'] != 0 )
				add_action( 'bp_forums_new_topic',              array( $this, 'new_topic' ) );

			if ( $this->prefs['edit_topic']['creds'] != 0 )
				add_action( 'groups_edit_forum_topic',          array( $this, 'edit_topic' ) );

			if ( $this->prefs['new_post']['creds'] != 0 )
				add_action( 'bp_forums_new_post',               array( $this, 'new_post' ) );

			if ( $this->prefs['edit_post']['creds'] != 0 )
				add_action( 'groups_edit_forum_post',           array( $this, 'edit_post' ) );

			if ( $this->prefs['join']['creds'] != 0 || ( $this->prefs['create']['creds'] != 0 && $this->prefs['create']['min'] != 0 ) )
				add_action( 'groups_join_group',                array( $this, 'join_group' ), 20, 2 );

			if ( $this->prefs['join']['creds'] < 0 )
				add_filter( 'bp_get_group_join_button',         array( $this, 'restrict_joining_group' ) );

			if ( $this->prefs['leave']['creds'] != 0 )
				add_action( 'groups_leave_group',               array( $this, 'leave_group' ), 20, 2 );

			if ( $this->prefs['avatar']['creds'] != 0 )
				add_action( 'groups_screen_group_admin_avatar', array( $this, 'avatar_upload_group' ) );

			if ( $this->prefs['cover']['creds'] != 0 )
				add_action( 'group_cover_image_uploaded',       array( $this, 'cover_change' ) );

			if ( $this->prefs['comments']['creds'] != 0 )
				add_action( 'bp_groups_posted_update',          array( $this, 'new_group_comment' ), 20, 4 );

		}

		/**
		 * Creating Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function create_group( $group_id ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'creation_of_new_group',
				$bp->loggedin_user->id,
				$this->prefs['create']['creds'],
				$this->prefs['create']['log'],
				$group_id,
				'bp_group',
				$this->bonips_type
			);

		}

		/**
		 * Restrict Group Creation
		 * If creating a group costs and the user does not have enough points, we restrict creations.
		 * @since 0.1
		 * @version 1.0
		 */
		public function restrict_group_creation( $can_create, $restricted ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return $can_create;

			// Check if user has enough to create a group
			$cost = abs( $this->prefs['create']['creds'] );
			$balance = $this->core->get_users_balance( $bp->loggedin_user->id, $this->bonips_type );
			if ( $cost > $balance ) return false;

			return $can_create;

		}

		/**
		 * Restrict Group Join
		 * If joining a group costs and the user does not have enough points, we restrict joining of groups.
		 * @since 0.1
		 * @version 1.0
		 */
		public function restrict_joining_group( $button ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return $button;

			// Check if user has enough to join group
			$cost = abs( $this->prefs['join']['creds'] );
			$balance = $this->core->get_users_balance( $bp->loggedin_user->id, $this->bonips_type );
			if ( $cost > $balance ) return false;

			return $button;

		}

		/**
		 * Deleting Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function delete_group( $group_id ) {

			global $bp;

			// If admin is removing deduct from creator
			if ( $bp->loggedin_user->is_super_admin )
				$user_id = $bp->groups->current_group->creator_id;

			// Else if admin but not the creator is removing
			elseif ( $bp->loggedin_user->id != $bp->groups->current_group->creator_id )
				$user_id = $bp->groups->current_group->creator_id;

			// Else deduct from current user
			else
				$user_id = $bp->loggedin_user->id;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'deletion_of_group',
				$user_id,
				$this->prefs['delete']['creds'],
				$this->prefs['delete']['log'],
				$group_id,
				'bp_group',
				$this->bonips_type
			);

		}

		/**
		 * New Group Forum Topic
		 * @since 0.1
		 * @version 1.1
		 */
		public function new_topic( $topic_id ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'new_topic', 'new_group_forum_topic' ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_group_forum_topic', $topic_id, $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_group_forum_topic',
				$bp->loggedin_user->id,
				$this->prefs['new_topic']['creds'],
				$this->prefs['new_topic']['log'],
				$topic_id,
				'bp_ftopic',
				$this->bonips_type
			);

		}

		/**
		 * Edit Group Forum Topic
		 * @since 0.1
		 * @version 1.0
		 */
		public function edit_topic( $topic_id ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'edit_topic', 'edit_group_forum_topic' ) ) return;

			// Execute
			$this->core->add_creds(
				'edit_group_forum_topic',
				$bp->loggedin_user->id,
				$this->prefs['edit_topic']['creds'],
				$this->prefs['edit_topic']['log'],
				$topic_id,
				'bp_ftopic',
				$this->bonips_type
			);

		}

		/**
		 * New Group Forum Post
		 * @since 0.1
		 * @version 1.1
		 */
		public function new_post( $post_id ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'new_post', 'new_group_forum_post' ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_group_forum_post', $post_id, $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_group_forum_post',
				$bp->loggedin_user->id,
				$this->prefs['new_post']['creds'],
				$this->prefs['new_post']['log'],
				$post_id,
				'bp_fpost',
				$this->bonips_type
			);

		}

		/**
		 * Edit Group Forum Post
		 * @since 0.1
		 * @version 1.0
		 */
		public function edit_post( $post_id ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'edit_post', 'edit_group_forum_post' ) ) return;

			// Execute
			$this->core->add_creds(
				'edit_group_forum_post',
				$bp->loggedin_user->id,
				$this->prefs['edit_post']['creds'],
				$this->prefs['edit_post']['log'],
				$post_id,
				'bp_fpost',
				$this->bonips_type
			);

		}

		/**
		 * Joining Group
		 * @since 0.1
		 * @version 1.1
		 */
		public function join_group( $group_id, $user_id ) {

			// Minimum members limit
			if ( $this->prefs['create']['min'] != 0 ) {
				$group = groups_get_group( array( 'group_id' => $group_id ) );

				// Award creator if we have reached the minimum number of members and we have not yet been awarded
				if ( $group->total_member_count >= (int) $this->prefs['create']['min'] && ! $this->core->has_entry( 'creation_of_new_group', $group_id, $group->creator_id ) )
					$this->core->add_creds(
						'creation_of_new_group',
						$group->creator_id,
						$this->prefs['create']['creds'],
						$this->prefs['create']['log'],
						$group_id,
						'bp_group',
						$this->bonips_type
					);

				// Clean up
				unset( $group );

			}

			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'join', 'joining_group' ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'joining_group', $group_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'joining_group',
				$user_id,
				$this->prefs['join']['creds'],
				$this->prefs['join']['log'],
				$group_id,
				'bp_group',
				$this->bonips_type
				);

		}

		/**
		 * Leaving Group
		 * @since 0.1
		 * @version 1.0
		 */
		public function leave_group( $group_id, $user_id ) {

			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'leaving_group', $group_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'leaving_group',
				$user_id,
				$this->prefs['leave']['creds'],
				$this->prefs['leave']['log'],
				$group_id,
					'bp_group',
				$this->bonips_type
			);

		}

		/**
		 * Avatar Upload for Group
		 * @since 0.1
		 * @version 1.1
		 */
		public function avatar_upload_group( $group_id ) {

			global $bp;

			// Check if user should be excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'avatar', 'upload_group_avatar' ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'upload_group_avatar', $group_id ) ) return;

			// Execute
			$this->core->add_creds(
				'upload_group_avatar',
				$bp->loggedin_user->id,
				$this->prefs['avatar']['creds'],
				$this->prefs['avatar']['log'],
				$group_id,
				'bp_group',
				$this->bonips_type
			);

		}

		/**
		 * Group Cover Upload
		 * @since 1.7
		 * @version 1.0
		 */
		public function cover_change( $group_id ) {

			global $bp;

			// Check if user is excluded
			if ( $this->core->exclude_user( $bp->loggedin_user->id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'cover', 'upload_group_cover', $bp->loggedin_user->id ) ) return;

			// Execute
			$this->core->add_creds(
				'upload_group_cover',
				$bp->loggedin_user->id,
				$this->prefs['cover']['creds'],
				$this->prefs['cover']['log'],
				$group_id,
				'bp_group',
				$this->bonips_type
			);

		}

		/**
		 * New Group Comment
		 * @since 0.1
		 * @version 1.1
		 */
		public function new_group_comment( $content, $user_id, $group_id, $activity_id ) {

			// Check if user should be excluded
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'comments', 'new_group_comment', $user_id ) ) return;

			// Make sure this is unique event
			if ( $this->core->has_entry( 'new_group_comment', $activity_id, $user_id ) ) return;

			// Execute
			$this->core->add_creds(
				'new_group_comment',
				$user_id,
				$this->prefs['comments']['creds'],
				$this->prefs['comments']['log'],
				$activity_id,
				'bp_activity',
				$this->bonips_type
			);

		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.3
		 */
		public function preferences() {

			$prefs = $this->prefs;

?>
<div class="hook-instance">
	<h3><?php _e( 'Gruppenerstellung', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'create', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'create', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['create']['creds'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->core->template_tags_general( __( 'Wenn Du einen negativen Wert verwendest und der Benutzer nicht genug %_plural% hat, wird die Schaltfläche "Gruppe erstellen" deaktiviert.', 'bonips' ) ); ?></span>
			</div>
		</div>
		<div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'create', 'min' ) ); ?>"><?php _e( 'No. of Members', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'create', 'min' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'min' ) ); ?>" value="<?php echo esc_attr( $prefs['create']['min'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->core->template_tags_general( __( 'Die Anzahl der Mitglieder, die eine Gruppe gewinnen muss, bevor %_plural% vergeben werden. Verwende Null, um zu vergeben, sobald die Gruppe erstellt wurde.', 'bonips' ) ); ?></span>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'create', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'create', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'create', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['create']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Gruppenlöschungen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['delete']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['delete']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neuer Gruppen-Avatar-Upload', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'avatar', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['avatar']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'avatar', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'avatar', 'limit' ) ), $this->field_id( array( 'avatar', 'limit' ) ), $prefs['avatar']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'avatar', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'avatar', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['avatar']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neues Gruppencover hochladen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cover', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'cover', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'cover', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['cover']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cover', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'cover', 'limit' ) ), $this->field_id( array( 'cover', 'limit' ) ), $prefs['cover']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'cover', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'cover', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'cover', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['cover']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neue Forenthemen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_topic']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_topic', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_topic', 'limit' ) ), $this->field_id( array( 'new_topic', 'limit' ) ), $prefs['new_topic']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['new_topic']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Forenthemen bearbeiten', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'edit_topic', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'edit_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_topic', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['edit_topic']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'edit_topic', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'edit_topic', 'limit' ) ), $this->field_id( array( 'edit_topic', 'limit' ) ), $prefs['edit_topic']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'edit_topic', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'edit_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_topic', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['edit_topic']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neue Forumsbeiträge', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_post', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_post', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_post', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_post']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_post', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_post', 'limit' ) ), $this->field_id( array( 'new_post', 'limit' ) ), $prefs['new_post']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_post', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_post', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_post', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['new_post']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Bearbeiten von Forenbeiträgen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'edit_post', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'edit_post', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_post', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['edit_post']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'edit_post', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'edit_post', 'limit' ) ), $this->field_id( array( 'edit_post', 'limit' ) ), $prefs['edit_post']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'edit_post', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'edit_post', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'edit_post', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['edit_post']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Gruppen beitreten', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'join', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'join', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'join', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['join']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'join', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'join', 'limit' ) ), $this->field_id( array( 'join', 'limit' ) ), $prefs['join']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'join', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'join', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'join', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['join']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Gruppen verlassen', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'leave', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'leave', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'leave', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['leave']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'leave', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'leave', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'leave', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['leave']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Neue Gruppenkommentare', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'comments', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'comments', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'comments', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['comments']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'comments', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'comments', 'limit' ) ), $this->field_id( array( 'comments', 'limit' ) ), $prefs['comments']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'comments', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'comments', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'comments', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['comments']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Sanitise Preferences
		 * @since 1.6
		 * @version 1.1
		 */
		public function sanitise_preferences( $data ) {

			if ( isset( $data['new_topic']['limit'] ) && isset( $data['new_topic']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['new_topic']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['new_topic']['limit'] = $limit . '/' . $data['new_topic']['limit_by'];
				unset( $data['new_topic']['limit_by'] );
			}

			if ( isset( $data['edit_topic']['limit'] ) && isset( $data['edit_topic']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['edit_topic']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['edit_topic']['limit'] = $limit . '/' . $data['edit_topic']['limit_by'];
				unset( $data['edit_topic']['limit_by'] );
			}

			if ( isset( $data['new_post']['limit'] ) && isset( $data['new_post']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['new_post']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['new_post']['limit'] = $limit . '/' . $data['new_post']['limit_by'];
				unset( $data['new_post']['limit_by'] );
			}

			if ( isset( $data['edit_post']['limit'] ) && isset( $data['edit_post']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['edit_post']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['edit_post']['limit'] = $limit . '/' . $data['edit_post']['limit_by'];
				unset( $data['edit_post']['limit_by'] );
			}

			if ( isset( $data['join']['limit'] ) && isset( $data['join']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['join']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['join']['limit'] = $limit . '/' . $data['join']['limit_by'];
				unset( $data['join']['limit_by'] );
			}

			if ( isset( $data['avatar']['limit'] ) && isset( $data['avatar']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['avatar']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['avatar']['limit'] = $limit . '/' . $data['avatar']['limit_by'];
				unset( $data['avatar']['limit_by'] );
			}

			if ( isset( $data['cover']['limit'] ) && isset( $data['cover']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['cover']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['cover']['limit'] = $limit . '/' . $data['cover']['limit_by'];
				unset( $data['cover']['limit_by'] );
			}

			if ( isset( $data['comments']['limit'] ) && isset( $data['comments']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['comments']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['comments']['limit'] = $limit . '/' . $data['comments']['limit_by'];
				unset( $data['comments']['limit_by'] );
			}

			return $data;

		}

	}

}
