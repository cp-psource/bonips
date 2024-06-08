<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * Register Hook
 * @since 0.1
 * @version 1.1
 */
add_filter( 'bonips_setup_hooks', 'bonips_register_psforum_hook', 20 );
function bonips_register_psforum_hook( $installed ) {

	if ( ! class_exists( 'PSForum' ) ) return $installed;

	$installed['hook_psforum'] = array(
		'title'         => 'PSForum',
		'description'   => __( 'Awards %_plural% for PSForum actions.', 'bonips' ),
		'documentation' => 'http://codex.bonips.me/hooks/psforum-actions/',
		'callback'      => array( 'boniPS_PSForum' )
	);

	return $installed;

}

/**
 * PSForum Hook
 * @since 0.1
 * @version 1.4.4
 */
add_action( 'bonips_load_hooks', 'bonips_load_psforum_hook', 20 );
function bonips_load_psforum_hook() {

	// If the hook has been replaced or if plugin is not installed, exit now
	if ( class_exists( 'boniPS_PSForum' ) || ! class_exists( 'PSForum' ) ) return;

	class boniPS_PSForum extends boniPS_Hook {

		/**
		 * Construct
		 */
		public function __construct( $hook_prefs, $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( array(
				'id'       => 'hook_psforum',
				'defaults' => array(
					'new_forum' => array(
						'creds'    => 1,
						'log'      => '%plural% for new forum',
						'limit'    => '0/x'
					),
					'delete_forum' => array(
						'creds'    => 0,
						'log'      => '%singular% deduction for deleted forum'
					),
					'new_topic' => array(
						'creds'    => 1,
						'log'      => '%plural% for new forum topic',
						'author'   => 0,
						'limit'    => '0/x'
					),
					'delete_topic' => array(
						'creds'    => 0,
						'log'      => '%singular% deduction for deleted topic'
					),
					'fav_topic' => array(
						'creds'    => 1,
						'log'      => '%plural% for someone favorited your forum topic',
						'limit'    => '0/x'
					),
					'new_reply' => array(
						'creds'    => 1,
						'log'      => '%plural% for new forum reply',
						'author'   => 0,
						'limit'    => '0/x'
					),
					'delete_reply' => array(
						'creds'    => 0,
						'log'      => '%singular% deduction for deleted reply'
					),
					'show_points_in_reply'   => 0,
					'show_points_in_profile' => 0
				)
			), $hook_prefs, $type );

			add_filter( 'bonips_post_type_excludes', array( $this, 'exclude_post_type' ) );

		}

		/**
		 * Run
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function run() {

			// Insert Points balance in profile
			if ( isset( $this->prefs['show_points_in_reply'] ) && $this->prefs['show_points_in_reply'] == 1 )
				add_action( 'psf_theme_after_reply_author_details', array( $this, 'insert_balance_reply' ) );

			if ( isset( $this->prefs['show_points_in_profile'] ) && $this->prefs['show_points_in_profile'] == 1 )
				add_action( 'psf_template_after_user_profile', array( $this, 'insert_balance_profile' ) );

			// New Forum
			if ( $this->prefs['new_forum']['creds'] != 0 )
				add_action( 'psf_new_forum',    array( $this, 'new_forum' ), 20 );

			// Delete Forum
			if ( $this->prefs['delete_forum']['creds'] != 0 )
				add_action( 'psf_delete_forum', array( $this, 'delete_forum' ) );

			// New Topic
			if ( $this->prefs['new_topic']['creds'] != 0 )
				add_action( 'psf_new_topic',    array( $this, 'new_topic' ), 20, 4 );

			// Delete Topic
			if ( $this->prefs['delete_topic']['creds'] != 0 )
				add_action( 'psf_delete_topic', array( $this, 'delete_topic' ) );

			// Fave Topic
			if ( $this->prefs['fav_topic']['creds'] != 0 )
				add_action( 'psf_add_user_favorite', array( $this, 'fav_topic' ), 10, 2 );

			// New Reply
			if ( $this->prefs['new_reply']['creds'] != 0 )
				add_action( 'psf_new_reply',    array( $this, 'new_reply' ), 20, 5 );

			// Delete Reply
			if ( $this->prefs['delete_reply']['creds'] != 0 )
				add_action( 'psf_delete_reply', array( $this, 'delete_reply' ) );

		}

		/**
		 * Exclude PSForum Post Types
		 * @since 0.1
		 * @version 1.0
		 */
		public function exclude_post_type( $excludes ) {

			$excludes[] = psf_get_forum_post_type();
			$excludes[] = psf_get_topic_post_type();
			$excludes[] = psf_get_reply_post_type();

			return $excludes;

		}

		/**
		 * Insert Balance in PSForum Profiles
		 * @since 1.1.1
		 * @version 1.2
		 */
		public function insert_balance_profile() {

			$user_id = psf_get_displayed_user_id();

			if ( $this->core->exclude_user( $user_id ) || $user_id == 0 ) return;

			$balance = $this->core->get_users_balance( $user_id, $this->bonips_type );
			$layout  = $this->core->plural() . ': ' . $this->core->format_creds( $balance );

			echo apply_filters( 'bonips_psf_profile_balance', '<div class="users-bonips-balance">' . $layout . '</div>', $layout, $user_id, $this );

		}

		/**
		 * Insert Balance
		 * @since 0.1
		 * @version 1.2.1
		 */
		public function insert_balance_reply() {

			$reply_id = psf_get_reply_id();

			// Skip Anonymous replies
			if ( psf_is_reply_anonymous( $reply_id ) ) return;

			// Get reply author
			$user_id = psf_get_reply_author_id( $reply_id );

			// Check for exclusions and guests
			if ( $this->core->exclude_user( $user_id ) || $user_id == 0 ) return;

			$balance = $this->core->get_users_balance( $user_id, $this->bonips_type );
			$layout  = $this->core->plural() . ': ' . $this->core->format_creds( $balance );

			echo apply_filters( 'bonips_psf_reply_balance', '<div class="users-bonips-balance">' . $layout . '</div>', $layout, $user_id, $this );

		}

		/**
		 * New Forum
		 * @since 1.1.1
		 * @version 1.2
		 */
		public function new_forum( $forum ) {

			// Forum id
			$forum_id = $forum['forum_id'];

			// Forum author
			$forum_author = $forum['forum_author'];

			// Check if user is excluded
			if ( $this->core->exclude_user( $forum_author ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'new_forum', 'new_forum', $forum_author ) ) return;

			// Make sure this is unique event
			if ( $this->has_entry( 'new_forum', $forum_id, $forum_author ) ) return;

			// Execute
			$this->core->add_creds(
				'new_forum',
				$forum_author,
				$this->prefs['new_forum']['creds'],
				$this->prefs['new_forum']['log'],
				$forum_id,
				array( 'ref_type' => 'post' ),
				$this->bonips_type
			);

		}

		/**
		 * Delete Forum
		 * @since 1.2
		 * @version 1.1
		 */
		public function delete_forum( $forum_id ) {

			// Get Author
			$forum_author = psf_get_forum_author_id( $forum_id );

			// If gained, points, deduct
			if ( $this->has_entry( 'new_forum', $forum_id, $forum_author ) ) {

				// Execute
				$this->core->add_creds(
					'deleted_forum',
					$forum_author,
					$this->prefs['delete_forum']['creds'],
					$this->prefs['delete_forum']['log'],
					$forum_id,
					'',
					$this->bonips_type
				);

			}

		}

		/**
		 * New Topic
		 * @since 0.1
		 * @version 1.2
		 */
		public function new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $topic_author ) ) return;

			// Check if forum author is allowed to get points for their own topics
			if ( (bool) $this->prefs['new_topic']['author'] == false ) {
				if ( psf_get_forum_author_id( $forum_id ) == $topic_author ) return;
			}

			// Limit
			if ( $this->over_hook_limit( 'new_topic', 'new_forum_topic', $topic_author ) ) return;

			// Make sure this is unique event
			if ( $this->has_entry( 'new_forum_topic', $topic_id, $topic_author ) ) return;

			// Execute
			$this->core->add_creds(
				'new_forum_topic',
				$topic_author,
				$this->prefs['new_topic']['creds'],
				$this->prefs['new_topic']['log'],
				$topic_id,
				array( 'ref_type' => 'post' ),
				$this->bonips_type
			);

		}

		/**
		 * Delete Topic
		 * @since 1.2
		 * @version 1.1
		 */
		public function delete_topic( $topic_id ) {

			// Get Author
			$topic_author = psf_get_topic_author_id( $topic_id );

			// If gained, points, deduct
			if ( $this->has_entry( 'new_forum_topic', $topic_id, $topic_author ) ) {

				// Execute
				$this->core->add_creds(
					'deleted_topic',
					$topic_author,
					$this->prefs['delete_topic']['creds'],
					$this->prefs['delete_topic']['log'],
					$topic_id,
					'',
					$this->bonips_type
				);

			}

		}

		/**
		 * Topic Added to Favorites
		 * @by Fee (http://wordpress.org/support/profile/wdfee)
		 * @since 1.1.1
		 * @version 1.5
		 */
		public function fav_topic( $user_id, $topic_id ) {

			// $user_id is loggedin_user, not author, so get topic author
			$topic_author = get_post_field( 'post_author', $topic_id );

			// Check if user is excluded (required)
			if ( $this->core->exclude_user( $topic_author ) || $topic_author == $user_id ) return;

			// Limit
			if ( $this->over_hook_limit( 'fav_topic', 'topic_favorited', $topic_author ) ) return;

			// Make sure this is a unique event (favorite not from same user)
			$data = array( 'ref_user' => $user_id, 'ref_type' => 'post' );
			if ( $this->has_entry( 'topic_favorited', $topic_id, $topic_author, $data ) ) return;

			// Execute
			$this->core->add_creds(
				'topic_favorited',
				$topic_author,
				$this->prefs['fav_topic']['creds'],
				$this->prefs['fav_topic']['log'],
				$topic_id,
				$data,
				$this->bonips_type
			);

		}

		/**
		 * New Reply
		 * @since 0.1
		 * @version 1.5
		 */
		public function new_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {

			// Check if user is excluded
			if ( $this->core->exclude_user( $reply_author ) ) return;

			// Check if topic author gets points for their own replies
			if ( (bool) $this->prefs['new_reply']['author'] === false && psf_get_topic_author_id( $topic_id ) == $reply_author ) return;

			// Limit
			if ( $this->over_hook_limit( 'new_reply', 'new_forum_reply', $reply_author ) ) return;

			// Make sure this is unique event
			if ( $this->has_entry( 'new_forum_reply', $reply_id, $reply_author ) ) return;

			// Execute
			$this->core->add_creds(
				'new_forum_reply',
				$reply_author,
				$this->prefs['new_reply']['creds'],
				$this->prefs['new_reply']['log'],
				$reply_id,
				array( 'ref_type' => 'post' ),
				$this->bonips_type
			);

		}

		/**
		 * Delete Reply
		 * @since 1.2
		 * @version 1.2.1
		 */
		public function delete_reply( $reply_id ) {

			// Get Author
			$reply_author = psf_get_reply_author_id( $reply_id );

			// If gained, points, deduct
			if ( $this->has_entry( 'new_forum_reply', $reply_id, $reply_author ) ) {

				// Execute
				$this->core->add_creds(
					'deleted_reply',
					$reply_author,
					$this->prefs['delete_reply']['creds'],
					$this->prefs['delete_reply']['log'],
					$reply_id,
					'',
					$this->bonips_type
				);

			}

		}

		/**
		 * Preferences
		 * @since 0.1
		 * @version 1.3
		 */
		public function preferences() {

			$prefs = $this->prefs;

			if ( ! isset( $prefs['new_forum']['limit'] ) )
				$prefs['new_forum']['limit'] = '0/x';

			if ( ! isset( $prefs['new_topic']['limit'] ) )
				$prefs['new_topic']['limit'] = '0/x';

			if ( ! isset( $prefs['fav_topic']['limit'] ) )
				$prefs['fav_topic']['limit'] = '0/x';

			if ( ! isset( $prefs['new_reply']['limit'] ) )
				$prefs['new_reply']['limit'] = '0/x';

?>
<div class="hook-instance">
	<h3><?php _e( 'New Forums', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_forum', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_forum', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_forum', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_forum']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_forum', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_forum', 'limit' ) ), $this->field_id( array( 'new_forum', 'limit' ) ), $prefs['new_forum']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_forum', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_forum', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_forum', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['new_forum']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Deleting Forums', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_forum', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_forum', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_forum', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['delete_forum']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_forum', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_forum', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_forum', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['delete_forum']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'New Topic', 'bonips' ); ?></h3>
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
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'new_topic' => 'author' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'new_topic' => 'author' ) ); ?>" id="<?php echo $this->field_id( array( 'new_topic' => 'author' ) ); ?>" <?php checked( $prefs['new_topic']['author'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Forum authors can receive %_plural% for creating new topics.', 'bonips' ) ); ?></label>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Deleted Topic', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_topic', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_topic', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['delete_topic']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_topic', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_topic', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['delete_topic']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Adding Topic to Favorites', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'fav_topic', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'fav_topic', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'fav_topic', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['fav_topic']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'fav_topic', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'fav_topic', 'limit' ) ), $this->field_id( array( 'fav_topic', 'limit' ) ), $prefs['fav_topic']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'fav_topic', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'fav_topic', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'fav_topic', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['fav_topic']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Forum Reply', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-2 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_reply', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_reply', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'new_reply', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['new_reply']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_reply', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonips' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'new_reply', 'limit' ) ), $this->field_id( array( 'new_reply', 'limit' ) ), $prefs['new_reply']['limit'] ); ?>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'new_reply', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'new_reply', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'new_reply', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['new_reply']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'new_reply' => 'author' ) ); ?>"><input type="checkbox" name="<?php echo $this->field_name( array( 'new_reply' => 'author' ) ); ?>" id="<?php echo $this->field_id( array( 'new_reply' => 'author' ) ); ?>" <?php checked( $prefs['new_reply']['author'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Topic authors can receive %_plural% for replying to their own Topic.', 'bonips' ) ); ?></label>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Deleted Reply', 'bonips' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_reply', 'creds' ) ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_reply', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_reply', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['delete_reply']['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'delete_reply', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonips' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'delete_reply', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'delete_reply', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonips' ); ?>" value="<?php echo esc_attr( $prefs['delete_reply']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( 'show_points_in_reply' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_points_in_reply' ); ?>" id="<?php echo $this->field_id( 'show_points_in_reply' ); ?>" <?php checked( $prefs['show_points_in_reply'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Show users %_plural% balance in replies', 'bonips' ) ); ?></label>
				</div>
				<div class="radio">
					<label for="<?php echo $this->field_id( 'show_points_in_profile' ); ?>"><input type="checkbox" name="<?php echo $this->field_name( 'show_points_in_profile' ); ?>" id="<?php echo $this->field_id( 'show_points_in_profile' ); ?>" <?php checked( $prefs['show_points_in_profile'], 1 ); ?> value="1" /> <?php echo $this->core->template_tags_general( __( 'Show users %_plural% balance in their PSForum profiles', 'bonips' ) ); ?></label>
				</div>
			</div>
		</div>
	</div>
</div>
<?php

		}

		/**
		 * Sanitise Preference
		 * @since 1.1.1
		 * @version 1.1
		 */
		public function sanitise_preferences( $data ) {

			if ( isset( $data['new_forum']['limit'] ) && isset( $data['new_forum']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['new_forum']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['new_forum']['limit'] = $limit . '/' . $data['new_forum']['limit_by'];
				unset( $data['new_forum']['limit_by'] );
			}

			if ( isset( $data['new_topic']['limit'] ) && isset( $data['new_topic']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['new_topic']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['new_topic']['limit'] = $limit . '/' . $data['new_topic']['limit_by'];
				unset( $data['new_topic']['limit_by'] );
			}

			if ( isset( $data['fav_topic']['limit'] ) && isset( $data['fav_topic']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['fav_topic']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['fav_topic']['limit'] = $limit . '/' . $data['fav_topic']['limit_by'];
				unset( $data['fav_topic']['limit_by'] );
			}

			if ( isset( $data['new_reply']['limit'] ) && isset( $data['new_reply']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['new_reply']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['new_reply']['limit'] = $limit . '/' . $data['new_reply']['limit_by'];
				unset( $data['new_reply']['limit_by'] );
			}

			$data['new_topic']['author']    = ( isset( $data['new_topic']['author'] ) ) ? 1 : 0;
			$data['new_reply']['author']    = ( isset( $data['new_reply']['author'] ) ) ? 1 : 0;

			$data['show_points_in_reply']   = ( isset( $data['show_points_in_reply'] ) ) ? 1 : 0;
			$data['show_points_in_profile'] = ( isset( $data['show_points_in_profile'] ) ) ? 1 : 0;

			return $data;
		}

	}

}
