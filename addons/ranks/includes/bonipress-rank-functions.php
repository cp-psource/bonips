<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Have Ranks
 * Checks if there are any rank posts.
 * @returns (bool) true or false
 * @since 1.1
 * @version 1.6
 */
if ( ! function_exists( 'bonipress_have_ranks' ) ) :
	function bonipress_have_ranks( $point_type = '' ) {

		$have_ranks = false;
		$total      = 0;
		foreach ( wp_count_posts( BONIPS_RANK_KEY ) as $status => $count ) {
			$total += $count;
		}

		if ( $total > 0 )
			$have_ranks = true;

		return apply_filters( 'bonipress_have_ranks', $have_ranks, $point_type, $count );

	}
endif;

/**
 * Have Published Ranks
 * Checks if there are any published rank posts.
 * @returns (int) the number of published ranks found.
 * @since 1.3.2
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_get_published_ranks_count' ) ) :
	function bonipress_get_published_ranks_count( $point_type = NULL ) {

		$count = wp_count_posts( BONIPS_RANK_KEY )->publish;

		if ( $point_type === NULL ) {

			$cache_key  = 'ranks-published-count-' . $point_type;
			$count      = wp_cache_get( $cache_key, BONIPS_SLUG );

			if ( $count === false ) {

				global $wpdb;

				$posts       = bonipress_get_db_column( 'posts' );
				$type_filter = '';

				if ( $point_type !== NULL && bonipress_point_type_exists( sanitize_key( $point_type ) ) ) {

					$postmeta    = bonipress_get_db_column( 'postmeta' );
					$type_filter = $wpdb->prepare( "INNER JOIN {$postmeta} ctype ON ( ranks.ID = ctype.post_id AND ctype.meta_key = 'ctype' AND ctype.meta_value = %s )", $point_type );

				}

				$count      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$posts} ranks {$type_filter} WHERE ranks.post_type = %s AND ranks.post_status = 'publish';", BONIPS_RANK_KEY ) );
				if ( $count === NULL ) $count = 0;

				wp_cache_set( $cache_key, $count, BONIPS_SLUG );

			}

		}

		return apply_filters( 'bonipress_get_published_ranks_count', $count, $point_type );

	}
endif;

/**
 * Get Rank Object ID
 * Makes sure a given post ID is a rank post ID or converts a rank title into a rank ID.
 * @since 1.7
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_get_rank_object_id' ) ) :
	function bonipress_get_rank_object_id( $identifier = NULL ) {

		if ( $identifier === NULL ) return false;

		$rank_id = false;

		if ( absint( $identifier ) !== 0 && bonipress_get_post_type( absint( $identifier ) ) == BONIPS_RANK_KEY )
			$rank_id = absint( $identifier );

		else {

			$rank = bonipress_get_page_by_title( $identifier, OBJECT, BONIPS_RANK_KEY );
			if ( isset( $rank->post_type ) && $rank->post_type === BONIPS_RANK_KEY )
				$rank_id = $rank->ID;

		}

		return $rank_id;

	}
endif;

/**
 * Get Rank
 * Returns the rank object.
 * @since 1.1
 * @version 1.3
 */
if ( ! function_exists( 'bonipress_get_rank' ) ) :
	function bonipress_get_rank( $rank_identifier = NULL ) {

		global $bonipress_rank;

		$rank_id     = bonipress_get_rank_object_id( $rank_identifier );
		if ( $rank_id === false ) return false;

		if ( isset( $bonipress_rank )
			&& ( $bonipress_rank instanceof boniPRESS_Rank )
			&& ( $rank_id === $bonipress_rank->post_id )
		) {
			return $bonipress_rank;
		}

		$bonipress_rank = new boniPRESS_Rank( $rank_id );

		do_action( 'bonipress_get_rank' );

		return $bonipress_rank;

	}
endif;

/**
 * Rank Has Logo
 * Checks if a given rank has a logo.
 * @since 1.1
 * @version 1.5
 */
if ( ! function_exists( 'bonipress_rank_has_logo' ) ) :
	function bonipress_rank_has_logo( $rank_identifier = NULL ) {

		$return  = false;
		$rank_id = bonipress_get_rank_object_id( $rank_identifier );

		if ( $rank_id === false ) return $return;

		if ( bonipress_override_settings() && ! bonipress_is_main_site() ) {

			switch_to_blog( get_network()->site_id );

			if ( has_post_thumbnail( $rank_id ) )
				$return = true;

			restore_current_blog();

		}

		else {

			if ( has_post_thumbnail( $rank_id ) )
				$return = true;

		}

		return apply_filters( 'bonipress_rank_has_logo', $return, $rank_id );

	}
endif;

/**
 * Get Rank Logo
 * Returns the given ranks logo.
 * @since 1.1
 * @version 1.4
 */
if ( ! function_exists( 'bonipress_get_rank_logo' ) ) :
	function bonipress_get_rank_logo( $rank_identifier = NULL, $size = 'post-thumbnail', $attr = NULL ) {

		$rank_id = bonipress_get_rank_object_id( $rank_identifier );
		if ( $rank_id === false ) return false;

		if ( is_numeric( $size ) )
			$size = array( $size, $size );

		if ( bonipress_override_settings() && ! bonipress_is_main_site() ) {

			switch_to_blog( get_network()->site_id );

			$logo = get_the_post_thumbnail( $rank_id, $size, $attr );

			restore_current_blog();

		}

		else {

			$logo = get_the_post_thumbnail( $rank_id, $size, $attr );

		}

		return apply_filters( 'bonipress_get_rank_logo', $logo, $rank_id, $size, $attr );

	}
endif;

/**
 * Count Users with Rank
 * @since 1.6
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_count_users_with_rank' ) ) :
	function bonipress_count_users_with_rank( $rank_identifier = NULL ) {

		$rank_id    = bonipress_get_rank_object_id( $rank_identifier );
		if ( $rank_id === false ) return 0;

		$user_count = bonipress_get_post_meta( $rank_id, 'bonipress_rank_users', true );

		if ( $user_count == '' ) {

			$point_type = bonipress_get_post_meta( $rank_id, 'ctype', true );
			if ( $point_type == '' ) return 0;

			global $wpdb;

			$user_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( user_id ) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %d;", bonipress_get_meta_key( BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ) ), $rank_id ) );

			if ( $user_count === NULL ) $user_count = 0;

			bonipress_update_post_meta( $rank_id, 'bonipress_rank_users', $user_count );

		}

		return $user_count;

	}
endif;

/**
 * Get Users Rank ID
 * Returns the rank post ID for the given point type.
 * @since 1.6
 * @version 1.0.1
 */
if ( ! function_exists( 'bonipress_get_users_rank_id' ) ) :
	function bonipress_get_users_rank_id( $user_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		$user_id        = absint( $user_id );
		if ( $user_id === 0 ) return false;

		$account_object = bonipress_get_account( $user_id );
		if ( isset( $account_object->ranks ) && $account_object->balance[ $point_type ] !== false && $account_object->balance[ $point_type ]->rank !== false )
			return $account_object->balance[ $point_type ]->rank->post_id;

		$rank_id        = bonipress_get_user_meta( $user_id, BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ), true );

		if ( $rank_id == '' ) {

			$rank = bonipress_find_users_rank( $user_id, $point_type );

			// Found a rank, save it
			if ( $rank !== false ) {
				bonipress_save_users_rank( $user_id, $rank->rank_id, $point_type );
				$rank_id = $rank->rank_id;
			}

		}

		return $rank_id;

	}
endif;

/**
 * Save Users Rank
 * Saves a given rank for a user.
 * @since 1.7.4
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_save_users_rank' ) ) :
	function bonipress_save_users_rank( $user_id = NULL, $rank_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		if ( $user_id === NULL || $rank_id === NULL ) return false;

		$user_id        = absint( $user_id );
		$rank_id        = absint( $rank_id );
		$point_type     = sanitize_key( $point_type );

		global $bonipress_current_account;

		bonipress_update_user_meta( $user_id, BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ), $rank_id );

		if ( isset( $bonipress_current_account->ranks ) && $bonipress_current_account->balance[ $point_type ] !== false )
			$bonipress_current_account->balance[ $point_type ]->rank = new boniPRESS_Rank( $rank_id );

		return true;

	}
endif;

/**
 * Get My Rank
 * Returns the current users rank
 * @since 1.1
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_get_my_rank' ) ) :
	function bonipress_get_my_rank( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		if ( ! is_user_logged_in() ) return;

		$account = bonipress_get_current_account();
		if ( $account !== false )
			return $account->balance[ $point_type ]->rank;

		return bonipress_get_users_rank( get_current_user_id(), $point_type );

	}
endif;

/**
 * Get Users Rank
 * Retreaves the users current saved rank or if rank is missing
 * finds the appropriate rank and saves it.
 * @since 1.1
 * @version 1.7
 */
if ( ! function_exists( 'bonipress_get_users_rank' ) ) :
	function bonipress_get_users_rank( $user_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$rank           = false;

		// User ID is required
		if ( $user_id === NULL || ! is_numeric( $user_id ) ) return $rank;

		$user_id        = absint( $user_id );
		$account_object = bonipress_get_account( $user_id );
		if ( isset( $account_object->ranks ) && $account_object->balance[ $point_type ] !== false )
			return $account_object->balance[ $point_type ]->rank;

		// Get users rank
		$rank_id = bonipress_get_user_meta( $user_id, BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ), true );

		// No rank, try to assign one
		if ( $rank_id == '' ) {

			$rank = bonipress_find_users_rank( $user_id, $point_type );

			// Found a rank, save it
			if ( $rank !== false ) {
				bonipress_save_users_rank( $user_id, $rank->rank_id, $point_type );
				$rank_id = $rank->rank_id;
			}

		}

		// Get Rank object
		if ( $rank_id != '' )
			$rank = bonipress_get_rank( $rank_id );

		return apply_filters( 'bonipress_get_users_rank', $rank, $user_id, $rank_id, $point_type );

	}
endif;

/**
 * Find Users Rank
 * Attenots to find a particular users rank for a particular point type.
 * @uses bonipress_user_got_demoted if user got demoted to a lower rank.
 * @uses bonipress_user_got_promoted if user got promoted to a higher rank.
 * @since 1.1
 * @version 1.7
 */
if ( ! function_exists( 'bonipress_find_users_rank' ) ) :
	function bonipress_find_users_rank( $user_id = NULL, $point_type = BONIPS_DEFAULT_TYPE_KEY, $act = true ) {

		if ( bonipress_manual_ranks() ) return false;

		if ( $user_id === NULL ) $user_id = get_current_user_id();

		// Non logged in users have ID 0.
		if ( absint( $user_id ) === 0 ) return false;

		$user_id        = absint( $user_id );
		$account_object = bonipress_get_account( $user_id );
		$balance_object = $account_object->balance[ $point_type ];

		if ( $balance_object === false ) return false;

		if ( isset( $account_object->ranks ) ) {
			$current_rank_id = ( $balance_object->rank !== false ) ? $balance_object->rank->post_id : false;
			$current_rank    = ( $balance_object->rank !== false ) ? $balance_object->rank : false;
		}

		else {
			$current_rank_id = bonipress_get_user_meta( $user_id, BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ), true );
			$current_rank    = ( $current_rank_id != '' ) ? bonipress_get_rank( $current_rank_id ) : false;
		}

		$balance        = $balance_object->current;
		if ( bonipress_rank_based_on_total( $point_type ) )
			$balance = $balance_object->accumulated;

		// We are still within the set requirements
		if ( $current_rank !== false && $balance >= $current_rank->minimum && $balance <= $current_rank->maximum )
			return false;

		global $wpdb;

		// Prep format for the db query
		$balance_format = ( isset( $balance_object->point_type->sql_format ) ) ? $balance_object->point_type->sql_format : '%d';

		// Get the appropriate post tables
		$posts          = bonipress_get_db_column( 'posts' );
		$postmeta       = bonipress_get_db_column( 'postmeta' );

		// See where the users balance fits in
		$results        = $wpdb->get_row( $wpdb->prepare( "
			SELECT ranks.ID AS rank_id, min.meta_value AS minimum, max.meta_value AS maximum 
			FROM {$posts} ranks 
				INNER JOIN {$postmeta} ctype ON ( ranks.ID = ctype.post_id AND ctype.meta_key = 'ctype' AND ctype.meta_value = %s )
				INNER JOIN {$postmeta} min ON ( ranks.ID = min.post_id AND min.meta_key = 'bonipress_rank_min' )
				INNER JOIN {$postmeta} max ON ( ranks.ID = max.post_id AND max.meta_key = 'bonipress_rank_max' ) 
			WHERE ranks.post_type = %s 
				AND ranks.post_status = 'publish'
				AND {$balance_format} BETWEEN min.meta_value AND max.meta_value
			LIMIT 0,1;", $point_type, BONIPS_RANK_KEY, $balance ) );


		if ( isset( $results->rank_id ) )
			$results->current_id = $current_rank_id;

		// Found a new rank
		if ( $act === true && isset( $results->rank_id ) ) {

			// Demotions
			if ( $results->current_id !== false && $current_rank !== false && $current_rank->maximum > $results->maximum )
				do_action( 'bonipress_user_got_demoted', $user_id, $results, $current_rank, $point_type );

			// Promotions
			else {

				do_action( 'bonipress_user_got_promoted', $user_id, $results->rank_id, $results, $point_type );

			}

			// Reset counters
			if ( $current_rank !== false )
				bonipress_delete_post_meta( $current_rank->post_id, 'bonipress_rank_users' );

			bonipress_delete_post_meta( $results->rank_id, 'bonipress_rank_users' );

		}

		if ( $results === NULL )
			$results = false;

		return apply_filters( 'bonipress_find_users_rank', $results, $user_id, $point_type );

	}
endif;

/**
 * Assign Ranks
 * Runs though all user balances and assigns each users their
 * appropriate ranks.
 * @returns count
 * @since 1.3.2
 * @version 1.7
 */
if ( ! function_exists( 'bonipress_assign_ranks' ) ) :
	function bonipress_assign_ranks( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		if ( bonipress_manual_ranks() ) return 0;

		global $wpdb;

		$type_object    = new boniPRESS_Point_Type( $point_type );
		$rank_key       = bonipress_get_meta_key( BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ) );

		$balance_key    = bonipress_get_meta_key( $point_type );
		$bonipress      = bonipress( $point_type );
		if ( isset( $bonipress->rank['base'] ) && $bonipress->rank['base'] == 'total' )
			$balance_key = bonipress_get_meta_key( $point_type, '_total' );

		$ranks          = bonipress_get_ranks( 'publish', '-1', 'ASC', $point_type );
		$balance_format = ( isset( $type_object->sql_format ) ) ? $type_object->sql_format : '%d';

		do_action( 'bonipress_assign_ranks_start' );

		$count          = 0;
		if ( ! empty( $ranks ) ) {
			foreach ( $ranks as $rank ) {

				$count += $wpdb->query( $wpdb->prepare( "
					UPDATE {$wpdb->usermeta} ranks 
						INNER JOIN {$wpdb->usermeta} balance ON ( ranks.user_id = balance.user_id AND balance.meta_key = %s )
					SET ranks.meta_value = %d 
					WHERE ranks.meta_key = %s 
						AND balance.meta_value BETWEEN {$balance_format} AND {$balance_format};", $balance_key, $rank->post_id, $rank_key, $rank->minimum, $rank->maximum ) );

				bonipress_delete_post_meta( $rank->post_id, 'bonipress_rank_users' );

			}
		}

		do_action( 'bonipress_assign_ranks_end' );

		return $count;

	}
endif;

/**
 * Get Ranks
 * Returns an associative array of ranks with the given status.
 * @param $status (string) post status, defaults to 'publish'
 * @param $number (int|string) number of ranks to return, defaults to all
 * @param $order (string) option to return ranks ordered Ascending or Descending
 * @param $type (string) optional point type
 * @returns (array) empty if no ranks are found or associative array with post ID as key and title as value
 * @since 1.1
 * @version 1.6
 */
if ( ! function_exists( 'bonipress_get_ranks' ) ) :
	function bonipress_get_ranks( $status = 'publish', $number = '-1', $order = 'DESC', $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$cache_key = 'ranks-published-' . $point_type;
		$ranks     = wp_cache_get( $cache_key, BONIPS_SLUG );
		$results   = array();

		if ( $ranks === false ) {

			global $wpdb;

			$order     = ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) ? 'DESC' : $order;
			$limit     = ( $number != '-1' ) ? 'LIMIT 0,' . absint( $number ) : '';

			$posts     = bonipress_get_db_column( 'posts' );
			$postmeta  = bonipress_get_db_column( 'postmeta' );

			$rank_ids  = $wpdb->get_col( $wpdb->prepare( "
				SELECT ranks.ID
				FROM {$posts} ranks
					LEFT JOIN {$postmeta} ctype ON ( ranks.ID = ctype.post_id AND ctype.meta_key = 'ctype' )
					LEFT JOIN {$postmeta} min ON ( ranks.ID = min.post_id AND min.meta_key = 'bonipress_rank_min' ) 
				WHERE ranks.post_type = %s 
					AND ranks.post_status = %s 
					AND ctype.meta_value = %s
				ORDER BY min.meta_value+0 {$order} {$limit};", BONIPS_RANK_KEY, $status, $point_type ) );

			if ( ! empty( $rank_ids ) ) {

				foreach ( $rank_ids as $rank_id )
					$results[] = bonipress_get_rank( $rank_id );

			}

			wp_cache_set( $cache_key, $results, BONIPS_SLUG );

		} else {
			$results = $ranks;

		}

		return apply_filters( 'bonipress_get_ranks', $results, $status, $number, $order );

	}
endif;

/**
 * Get Users of Rank
 * Returns an associative array of user IDs and display names of users for a given
 * rank.
 * @param $rank (int|string) either a rank id or rank name
 * @param $number (int) number of users to return
 * @returns (array) empty if no users were found or associative array with user ID as key and display name as value
 * @since 1.1
 * @version 1.6
 */
if ( ! function_exists( 'bonipress_get_users_of_rank' ) ) :
	function bonipress_get_users_of_rank( $rank_identifier = NULL, $number = 25, $order = 'DESC', $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$rank_id       = bonipress_get_rank_object_id( $rank_identifier );
		if ( $rank_id === false || ! bonipress_point_type_exists( $point_type ) ) return false;

		$number        = ( $number > 25 || $number < 0 ) ? 25 : absint( $number );
		$order         = ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) ? 'DESC' : $order;
		$cache_key     = 'ranks-users-' . $rank_id;
		$users         = wp_cache_get( $cache_key, BONIPS_SLUG );

		if ( $users === false ) {

			global $wpdb;

			$rank_meta_key = bonipress_get_meta_key( BONIPS_RANK_KEY, ( ( $point_type != BONIPS_DEFAULT_TYPE_KEY ) ? $point_type : '' ) );
			$balance_key   = bonipress_get_meta_key( $point_type );

			$posts         = bonipress_get_db_column( 'posts' );
			$postmeta      = bonipress_get_db_column( 'postmeta' );


			$users         = $wpdb->get_results( $wpdb->prepare( "
				SELECT users.*, creds.meta_value AS balance 
				FROM {$wpdb->users} users 
					LEFT JOIN {$wpdb->usermeta} rank ON ( users.ID = rank.user_id AND rank.meta_key = %s ) 
					LEFT JOIN {$wpdb->usermeta} creds ON ( users.ID = creds.user_id AND creds.meta_key = %s ) 
				WHERE rank.meta_value = %d 
				ORDER BY creds.meta_value+0 DESC LIMIT 25;", $rank_meta_key, $balance_key, $rank_id ) );

			wp_cache_set( $cache_key, $users, BONIPS_SLUG );

		}

		$users         = array_slice( $users, 0, $number, true );

		if ( $order == 'ASC' ) $users = array_reverse( $users, true );

		return apply_filters( 'bonipress_get_users_of_rank', $users, $rank_id, $number, $order, $point_type );

	}
endif;

/**
 * Manual Ranks
 * @since 1.8
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_manual_ranks' ) ) :
	function bonipress_manual_ranks( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$prefs  = bonipress_get_addon_settings( 'rank', $point_type );

		$result = false;
		if ( ! empty( $prefs ) && $prefs['base'] == 'manual' )
			$result = true;

		return $result;

	}
endif;

/**
 * Rank Based on Total
 * Checks if ranks for a given point type are based on total or current
 * balance.
 * @since 1.6
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_rank_based_on_total' ) ) :
	function bonipress_rank_based_on_total( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$prefs  = bonipress_get_addon_settings( 'rank', $point_type );

		$result = false;
		if ( ! empty( $prefs ) && $prefs['base'] == 'total' )
			$result = true;

		return $result;

	}
endif;

/**
 * Rank Shown in BuddyPress
 * Returns either false or the location where the rank is to be shown in BuddyPress.
 * @since 1.6
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_show_rank_in_buddypress' ) ) :
	function bonipress_show_rank_in_buddypress( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$prefs  = bonipress_get_addon_settings( 'rank', $point_type );

		$result = false;
		if ( $prefs['rank']['bb_location'] != '' )
			$result = $prefs['rank']['bb_location'];

		return $result;

	}
endif;

/**
 * Rank Shown in PSForum
 * Returns either false or the location where the rank is to be shown in PSForum.
 * @since 1.6
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_show_rank_in_psforum' ) ) :
	function bonipress_show_rank_in_psforum( $point_type = BONIPS_DEFAULT_TYPE_KEY ) {

		$prefs  = bonipress_get_addon_settings( 'rank', $point_type );

		$result = false;
		if ( $prefs['rank']['bp_location'] != '' )
			$result = $prefs['rank']['bp_location'];

		return $result;

	}
endif;
