<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: my_balance
 * Gibt das aktuelle Benutzerguthaben zurück.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_my_balance/
 * @contributor DerN3rd
 * @since 1.0.9
 * @version 1.2.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_my_balance' ) ) :
	function bonipress_render_shortcode_my_balance( $atts )
	{
		extract( shortcode_atts( array(
			'login'      => NULL,
			'title'      => '',
			'title_el'   => 'h1',
			'balance_el' => 'div',
			'wrapper'    => 1,
			'type'       => 'bonipress_default'
		), $atts ) );

		$output = '';

		// Not logged in
		if ( ! is_user_logged_in() ) {
			if ( $login !== NULL ) {
				if ( $wrapper )
					$output .= '<div class="bonipress-not-logged-in">';

				$output .= $login;

				if ( $wrapper )
					$output .= '</div>';

				return $output;
			}
			return;
		}

		$user_id = get_current_user_id();
		$bonipress = bonipress( $type );
		// Check for exclusion
		if ( $bonipress->exclude_user( $user_id ) ) return;

		if ( ! empty( $type ) )
			$bonipress->cred_id = $type;

		if ( $wrapper )
			$output .= '<div class="bonipress-my-balance-wrapper">';

		// Title
		if ( ! empty( $title ) ) {
			if ( ! empty( $title_el ) )
				$output .= '<' . $title_el . '>';

			$output .= $title;

			if ( ! empty( $title_el ) )
				$output .= '</' . $title_el . '>';
		}

		// Balance
		if ( ! empty( $balance_el ) )
			$output .= '<' . $balance_el . '>';

		$balance = $bonipress->get_users_cred( $user_id, $type );
		$output .= $bonipress->format_creds( $balance );

		if ( ! empty( $balance_el ) )
			$output .= '</' . $balance_el . '>';

		if ( $wrapper )
			$output .= '</div>';

		return $output;
	}
endif;

/**
 * BoniPress Shortcode: bonipress_history
 * Returns the points history.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_history/
 * @since 1.0.9
 * @version 1.1.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_history' ) ) :
	function bonipress_render_shortcode_history( $atts )
	{
		extract( shortcode_atts( array(
			'user_id'   => NULL,
			'number'    => NULL,
			'time'      => NULL,
			'ref'       => NULL,
			'order'     => NULL,
			'show_user' => false,
			'login'     => '',
			'type'      => 'bonipress_default'
		), $atts ) );

		// If we are not logged in
		if ( ! is_user_logged_in() && ! empty( $login ) ) return '<p class="bonipress-history login">' . $login . '</p>';

		if ( $user_id === NULL )
			$user_id = get_current_user_id();

		$args = array(
			'user_id' => $user_id,
			'ctype'   => $type
		);

		if ( $number !== NULL )
			$args['number'] = $number;

		if ( $time !== NULL )
			$args['time'] = $time;

		if ( $ref !== NULL )
			$args['ref'] = $ref;

		if ( $order !== NULL )
			$args['order'] = $order;

		$log = new boniPRESS_Query_Log( $args );

		if ( $show_user !== true )
			unset( $log->headers['column-username'] ); 

		$result = $log->get_display();
		$log->reset_query();
		return $result;
	}
endif;

/**
 * BoniPress Shortcode: bonipress_leaderboard
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_leaderboard//
 * @since 0.1
 * @version 1.4
 */
if ( ! function_exists( 'bonipress_render_shortcode_leaderboard' ) ) :
	function bonipress_render_shortcode_leaderboard( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'number'   => '-1',
			'order'    => 'DESC',
			'offset'   => 0,
			'type'     => 'bonipress_default',
			'based_on' => 'balance',
			'wrap'     => 'li',
			'template' => '#%position% %user_profile_link% %cred_f%',
			'nothing'  => __( 'Die Rangliste ist leer.', 'bonipress' ),
			'current'  => 0
		), $atts ) );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		if ( $number != '-1' )
			$limit = 'LIMIT ' . absint( $offset ) . ',' . absint( $number );
		else
			$limit = '';

		$bonipress = bonipress( $type );

		global $wpdb;

		// Leaderboard based on balance
		$based_on = sanitize_text_field( $based_on );

		if ( $based_on == 'balance' )
			$SQL = $wpdb->prepare( "
				SELECT DISTINCT u.ID, um.meta_value AS cred 
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um
					ON ( u.ID = um.user_id )
				WHERE um.meta_key = %s  
				ORDER BY um.meta_value+0 {$order} {$limit};", $type );
		else
			$SQL = $wpdb->prepare( "
				SELECT DISTINCT user_id AS ID, SUM( creds ) AS cred 
				FROM {$bonipress->log_table} 
				WHERE ref = %s 
				GROUP BY user_id 
				ORDER BY SUM( creds ) {$order} {$limit};", $based_on );

		$leaderboard = $wpdb->get_results( apply_filters( 'bonipress_ranking_sql', $SQL ), 'ARRAY_A' );

		$output = '';
		$in_list = false;

		// Get current users object
		$current_user = wp_get_current_user();

		if ( ! empty( $leaderboard ) ) {

			// Check if current user is in the leaderboard
			if ( $current == 1 && is_user_logged_in() ) {

				// Find the current user in the leaderboard
				foreach ( $leaderboard as $position => $user ) {
					if ( $user['ID'] == $current_user->ID ) {
						$in_list = true;
						break;
					}
				}

			}

			// Load boniPRESS
			$bonipress = bonipress( $type );

			// Wrapper
			if ( $wrap == 'li' )
				$output .= '<ol class="boniPRESS-leaderboard">';

			// Loop
			foreach ( $leaderboard as $position => $user ) {

				// Prep
				$class = array();

				// Classes
				$class[] = 'item-' . $position;
				if ( $position == 0 )
					$class[] = 'first-item';

				if ( $position % 2 != 0 )
					$class[] = 'alt';

				if ( ! empty( $content ) )
					$template = $content;

				// Template Tags
				if ( ! function_exists( 'bonipress_get_users_rank' ) )
					$layout = str_replace( array( '%rank%', '%ranking%', '%position%' ), $position+1, $template );
				else
					$layout = str_replace( array( '%ranking%', '%position%' ), $position+1, $template );

				$layout = $bonipress->template_tags_amount( $layout, $user['cred'] );
				$layout = $bonipress->template_tags_user( $layout, $user['ID'] );

				// Wrapper
				if ( ! empty( $wrap ) )
					$layout = '<' . $wrap . ' class="%classes%">' . $layout . '</' . $wrap . '>';

				$layout = str_replace( '%classes%', apply_filters( 'bonipress_ranking_classes', implode( ' ', $class ) ), $layout );
				$layout = apply_filters( 'bonipress_ranking_row', $layout, $template, $user, $position+1 );

				$output .= $layout . "\n";

			}

			$leaderboard = NULL;

			// Current user is not in list but we want to show his position
			if ( ! $in_list && $current == 1 && is_user_logged_in() ) {

				// Flush previous query
				$wpdb->flush();

				// Get a complete leaderboard with just user IDs
				if ( $based_on == 'balance' )
					$full_SQL = $wpdb->prepare( "
						SELECT DISTINCT u.ID 
						FROM {$wpdb->users} u
						INNER JOIN {$wpdb->usermeta} um
							ON ( u.ID = um.user_id )
						WHERE um.meta_key = %s  
						ORDER BY um.meta_value+0 {$order};", $type );
				else
					$full_SQL = $wpdb->prepare( "
						SELECT DISTINCT user_id AS ID, SUM( creds ) AS cred 
						FROM {$bonipress->log_table} 
						WHERE ref = %s 
						GROUP BY user_id 
						ORDER BY SUM( creds ) {$order} {$limit};", $based_on );

				$full_leaderboard = $wpdb->get_results( $full_SQL, 'ARRAY_A' );

				if ( ! empty( $full_leaderboard ) ) {

					// Get current users position
					$current_position = array_search( array( 'ID' => $current_user->ID ), $full_leaderboard );
					$full_leaderboard = NULL;

					// If position is found
					if ( $current_position !== false ) {

						// Template Tags
						if ( ! function_exists( 'bonipress_get_users_rank' ) )
							$layout = str_replace( array( '%rank%', '%ranking%', '%position%' ), $current_position+1, $template );
						else
							$layout = str_replace( array( '%ranking%', '%position%' ), $current_position+1, $template );

						$layout = $bonipress->template_tags_amount( $layout, $bonipress->get_users_cred( $current_user->ID, $type ) );
						$layout = $bonipress->template_tags_user( $layout, false, $current_user );

						// Wrapper
						if ( ! empty( $wrap ) )
							$layout = '<' . $wrap . ' class="%classes%">' . $layout . '</' . $wrap . '>';

						$layout = str_replace( '%classes%', apply_filters( 'bonipress_ranking_classes', implode( ' ', $class ) ), $layout );
						$layout = apply_filters( 'bonipress_ranking_row', $layout, $template, $current_user, $current_position+1 );

						$output .= $layout . "\n";
						
					}
				}

			}

			if ( $wrap == 'li' )
				$output .= '</ol>';

		}

		// No result template is set
		else {

			$output .= '<p class="bonipress-leaderboard-none">' . $nothing . '</p>';

		}

		return do_shortcode( apply_filters( 'bonipress_leaderboard', $output, $atts ) );
	}
endif;

/**
 * BoniPress Shortcode: bonipress_my_ranking
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_my_ranking/
 * @since 0.1
 * @version 1.4
 */
if ( ! function_exists( 'bonipress_render_shortcode_my_ranking' ) ) :
	function bonipress_render_shortcode_my_ranking( $atts )
	{
		extract( shortcode_atts( array(
			'user_id'  => NULL,
			'ctype'    => 'bonipress_default',
			'based_on' => 'balance',
			'missing'  => 0
		), $atts ) );

		// If no id is given
		if ( $user_id === NULL ) {
			// Current user must be logged in for this shortcode to work
			if ( ! is_user_logged_in() ) return;
			// Get current user id
			$user_id = get_current_user_id();
		}

		// If no type is given
		if ( $ctype == '' )
			$ctype = 'bonipress_default';

		$bonipress = bonipress( $ctype );

		global $wpdb;

		$based_on = sanitize_text_field( $based_on );

		// Get a complete leaderboard with just user IDs
		if ( $based_on == 'balance' )
			$full_SQL = $wpdb->prepare( "
				SELECT DISTINCT u.ID 
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} um
					ON ( u.ID = um.user_id )
				WHERE um.meta_key = %s  
				ORDER BY um.meta_value+0 {$order};", $ctype );
		else
			$full_SQL = $wpdb->prepare( "
				SELECT DISTINCT user_id AS ID, SUM( creds ) AS cred 
				FROM {$bonipress->log_table} 
				WHERE ref = %s 
				GROUP BY user_id 
				ORDER BY SUM( creds ) {$order} {$limit};", $based_on );

		$full_leaderboard = $wpdb->get_results( $full_SQL, 'ARRAY_A' );

		$position = 0;
		if ( ! empty( $full_leaderboard ) ) {

			// Get current users position
			$current_position = array_search( array( 'ID' => $user_id ), $full_leaderboard );
			$position = $current_position+1;

		}
		else $position = $missing;

		$full_leaderboard = NULL;

		return apply_filters( 'bonipress_get_leaderboard_position', $position, $user_id, $ctype );
	}
endif;

/**
 * BoniPress Shortcode: bonipress_give
 * This shortcode allows you to award or deduct points from a given user or the current user
 * when this shortcode is executed. You can insert this in page/post content
 * or in a template file. Note that users are awarded/deducted points each time
 * this shortcode exectutes!
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_give/
 * @since 1.1
 * @version 1.1.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_give' ) ) :
	function bonipress_render_shortcode_give( $atts )
	{
		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount'  => NULL,
			'user_id' => '',
			'log'     => '',
			'ref'     => 'gift',
			'limit'   => 0,
			'type'    => 'bonipress_default'
		), $atts ) );

		if ( $amount === NULL )
			return '<strong>' . __( 'Fehler', 'bonipress' ) . '</strong> ' . __( 'Betrag fehlt!', 'bonipress' );

		if ( empty( $log ) )
			return '<strong>' . __( 'Fehler', 'bonipress' ) . '</strong> ' . __( 'Protokollvorlage fehlt!', 'bonipress' );

		$bonipress = bonipress();

		if ( empty( $user_id ) )
			$user_id = get_current_user_id();

		// Check for exclusion
		if ( $bonipress->exclude_user( $user_id ) ) return;

		// Limit
		$limit = abs( $limit );
		if ( $limit != 0 && bonipress_count_ref_instances( $ref, $user_id ) >= $limit ) return;

		$amount = $bonipress->number( $amount );
		$bonipress->add_creds(
			$ref,
			$user_id,
			$amount,
			$log,
			'',
			'',
			$type
		);
	}
endif;

/**
 * BoniPress Shortcode: bonipress_link
 * This shortcode allows you to award or deduct points from the current user
 * when their click on a link. The shortcode will generate an anchor element
 * and call the bonipress-click-link jQuery script which will award the points.
 *
 * Note! Only HTML5 anchor attributes are supported and this shortcode is only
 * available if the hook is enabled!
 *
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_link/
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_link' ) ) :
	function bonipress_render_shortcode_link( $atts, $content = ''	 )
	{
		global $bonipress_link_points;

		$atts = shortcode_atts( array(
			'id'       => '',
			'rel'      => '',
			'class'    => '',
			'href'     => '',
			'title'    => '',
			'target'   => '',
			'style'    => '',
			'amount'   => 0,
			'ctype'    => 'bonipress_default',
			'hreflang' => '',   // for advanced users
			'media'    => '',   // for advanced users
			'type'     => ''    // for advanced users
		), $atts );

		// HREF is required
		if ( empty( $atts['href'] ) )
			return '<strong>' . __( 'Fehler', 'bonipress' ) . '</strong> ' . __( 'Anker fehlende URL!', 'bonipress' );

		// All links must contain the 'bonipress-points-link' class
		if ( empty( $atts['class'] ) )
			$atts['class'] = 'bonipress-points-link';
		else
			$atts['class'] = 'bonipress-points-link ' . $atts['class'];

		// If no id exists, make one
		if ( empty( $atts['id'] ) ) {
			$id = str_replace( array( 'http://', 'https://', 'http%3A%2F%2F', 'https%3A%2F%2F' ), 'hs', $atts['href'] );
			$id = str_replace( array( '/', '-', '_', ':', '.', '?', '=', '+', '\\', '%2F' ), '', $id );
			$atts['id'] = $id;
		}

		// Construct anchor attributes
		$attr = array();
		foreach ( $atts as $attribute => $value ) {
			if ( !empty( $value ) && ! in_array( $attribute, array( 'amount', 'ctype' ) ) ) {
				$attr[] = $attribute . '="' . $value . '"';
			}
		}

		// Add key
		$token = bonipress_create_token( array( $atts['amount'], $atts['ctype'], $atts['id'] ) );
		$attr[] = 'data-token="' . $token . '"';

		// Make sure jQuery script is called
		$bonipress_link_points = true;

		// Return result
		return '<a ' . implode( ' ', $attr ) . '>' . $content . '</a>';
	}
endif;

/**
 * BoniPress Shortcode: bonipress_send
 * This shortcode allows the current user to send a pre-set amount of points
 * to a pre-set user. A simpler version of the bonipress_transfer shortcode.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_send/ 
 * @since 1.1
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_shortcode_send' ) ) :
	function bonipress_render_shortcode_send( $atts, $content = NULL )
	{
		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => NULL,
			'to'     => NULL,
			'log'    => '',
			'ref'    => 'gift',
			'type'   => 'bonipress_default'
		), $atts ) );

		// Amount is required
		if ( $amount === NULL )
			return '<strong>' . __( 'Fehler', 'bonipress' ) . '</strong> ' . __( 'Betrag fehlt!', 'bonipress' );

		// Recipient is required
		if ( empty( $to ) )
			return '<strong>' . __( 'Fehler', 'bonipress' ) . '</strong> ' . __( 'Benutzer-ID für Empfänger fehlt.', 'bonipress' );

		// Log template is required
		if ( empty( $log ) )
			return '<strong>' . __( 'Fehler', 'bonipress' ) . '</strong> ' . __( 'Protokollvorlage fehlt!', 'bonipress' );

		if ( $to == 'author' ) {
			// You can not use this outside the loop
			$author = get_the_author_meta( 'ID' );
			if ( empty( $author ) ) $author = $GLOBALS['post']->post_author;
			$to = $author;
		}

		global $bonipress_sending_points;

		$bonipress = bonipress( $type );
		$user_id = get_current_user_id();

		// Make sure current user or recipient is not excluded!
		if ( $bonipress->exclude_user( $to ) || $bonipress->exclude_user( $user_id ) ) return;

		$account_limit = (int) apply_filters( 'bonipress_transfer_acc_limit', 0 );
		$balance = $bonipress->get_users_cred( $user_id, $type );
		$amount = $bonipress->number( $amount );

		// Insufficient Funds
		if ( $balance-$amount < $account_limit ) return;

		// We are ready!
		$bonipress_sending_points = true;

		return '<input type="button" class="bonipress-send-points-button" data-to="' . $to . '" data-ref="' . $ref . '" data-log="' . $log . '" data-amount="' . $amount . '" data-type="' . $type . '" value="' . $bonipress->template_tags_general( $content ) . '" />';
	}
endif;

/**
 * Load boniPRESS Send Points Footer
 * @since 0.1
 * @version 1.2
 */
if ( ! function_exists( 'bonipress_send_shortcode_footer' ) ) :
	add_action( 'wp_footer', 'bonipress_send_shortcode_footer' );
	function bonipress_send_shortcode_footer()
	{
		global $bonipress_sending_points;

		if ( $bonipress_sending_points === true ) {
			$bonipress = bonipress();
			$base = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'token'   => wp_create_nonce( 'bonipress-send-points' )
			);

			$language = apply_filters( 'bonipress_send_language', array(
				'working' => __( 'Verarbeitung...', 'bonipress' ),
				'done'    => __( 'Gesendet', 'bonipress' ),
				'error'   => __( 'Fehler, versuche es erneut', 'bonipress' )
			) );
			wp_localize_script(
				'bonipress-send-points',
				'boniPRESSsend',
				array_merge_recursive( $base, $language )
			);
			wp_enqueue_script( 'bonipress-send-points' );
		}
	}
endif;

/**
 * boniPRESS Send Points Ajax
 * @since 0.1
 * @version 1.3
 */
if ( ! function_exists( 'bonipress_shortcode_send_points_ajax' ) ) :
	add_action( 'wp_ajax_bonipress-send-points', 'bonipress_shortcode_send_points_ajax' );
	function bonipress_shortcode_send_points_ajax()
	{
		// We must be logged in
		if ( ! is_user_logged_in() ) die();

		// Security
		check_ajax_referer( 'bonipress-send-points', 'token' );

		$bonipress_types = bonipress_get_types();
		$type = 'bonipress_default';
		if ( isset( $_POST['type'] ) )
			$type = sanitize_text_field( $type );

		if ( ! array_key_exists( $type, $bonipress_types ) ) die();

		$bonipress = bonipress( $type );
		$user_id = get_current_user_id();

		$account_limit = (int) apply_filters( 'bonipress_transfer_acc_limit', 0 );
		$balance = $bonipress->get_users_cred( $user_id, $type );
		$amount = $bonipress->number( $_POST['amount'] );
		$new_balance = $balance-$amount;

		// Insufficient Funds
		if ( $new_balance < $account_limit )
			die();
		// After this transfer our account will reach zero
		elseif ( $new_balance == $account_limit )
			$reply = 'zero';
		// Check if this is the last time we can do these kinds of amounts
		elseif ( $new_balance-$amount < $account_limit )
			$reply = 'minus';
		// Else everything is fine
		else
			$reply = 'done';

		// First deduct points
		$bonipress->add_creds(
			trim( $_POST['reference'] ),
			$user_id,
			0-$amount,
			trim( $_POST['log'] ),
			$_POST['recipient'],
			array( 'ref_type' => 'user' ),
			$type
		);

		// Then add to recipient
		$bonipress->add_creds(
			trim( $_POST['reference'] ),
			$_POST['recipient'],
			$amount,
			trim( $_POST['log'] ),
			$user_id,
			array( 'ref_type' => 'user' ),
			$type
		);

		// Share the good news
		wp_send_json( $reply );
	}
endif;

/**
 * BoniPress Shortcode: bonipress_video
 * This shortcode allows points to be given to the current user
 * for watchinga YouTube video.
 * @see https://n3rds.work/docs/bonipress-shortcode-bonipress_video/
 * @since 1.2
 * @version 1.1.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_video' ) ) :
	function bonipress_render_shortcode_video( $atts )
	{
		global $bonipress_video_points;

		$hooks = bonipress_get_option( 'bonipress_pref_hooks', false );
		if ( $hooks === false ) return;
		$prefs = $hooks['hook_prefs']['video_view'];

		extract( shortcode_atts( array(
			'id'       => NULL,
			'width'    => 560,
			'height'   => 315,
			'amount'   => $prefs['creds'],
			'logic'    => $prefs['logic'],
			'interval' => $prefs['interval']
		), $atts ) );

		// ID is required
		if ( $id === NULL || empty( $id ) ) return __( 'Für diesen Shortcode ist eine Video-ID erforderlich', 'bonipress' );

		// Interval
		if ( strlen( $interval ) < 3 )
			$interval = abs( $interval * 1000 );

		// Video ID
		$video_id = str_replace( '-', '__', $id );

		// Create key
		$key = bonipress_create_token( array( 'youtube', $video_id, $amount, $logic, $interval ) );

		if ( ! isset( $bonipress_video_points ) || ! is_array( $bonipress_video_points ) )
			$bonipress_video_points = array();

		// Construct YouTube Query
		$query = apply_filters( 'bonipress_video_query_youtube', array(
			'enablejsapi' => 1,
			'version'     => 3,
			'playerapiid' => 'bonipress_vvideo_v' . $video_id,
			'rel'         => 0,
			'controls'    => 1,
			'showinfo'    => 0
		), $atts, $video_id );

		// Construct Youtube Query Address
		$url = 'https://www.youtube.com/embed/' . $id;
		$url = add_query_arg( $query, $url );

		$bonipress_video_points[] = 'youtube';

		// Make sure video source ids are unique
		$bonipress_video_points = array_unique( $bonipress_video_points );

		ob_start(); ?>

<div class="bonipress-video-wrapper youtube-video">
	<iframe id="bonipress_vvideo_v<?php echo $video_id; ?>" class="bonipress-video bonipress-youtube-video" data-vid="<?php echo $video_id; ?>" src="<?php echo $url; ?>" width="<?php echo $width; ?>" height="<?php echo $height; ?>" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	<script type="text/javascript">
function bonipress_vvideo_v<?php echo $video_id; ?>( state ) {
	duration[ "<?php echo $video_id; ?>" ] = state.target.getDuration();
	bonipress_view_video( "<?php echo $video_id; ?>", state.data, "<?php echo $logic; ?>", "<?php echo $interval; ?>", "<?php echo $key; ?>" );
}
	</script>
</div>
<?php
		$output = ob_get_contents();
		ob_end_clean();
		
		// Return the shortcode output
		return apply_filters( 'bonipress_video_output', $output, $atts );
	}
endif;

/**
 * BoniPress Shortcode: bonipress_total_balance
 * This shortcode will return either the current user or a given users
 * total balance based on either all point types or a comma seperated list
 * of types.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_total_balance/
 * @since 1.4.3
 * @version 1.1
 */
if ( ! function_exists( 'bonipress_render_shortcode_total' ) ) :
	function bonipress_render_shortcode_total( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'user_id' => NULL,
			'types'   => 'bonipress_default',
			'raw'     => 0
		), $atts ) );

		// If user ID is not set, get the current users ID
		if ( $user_id === NULL ) {
			// If user is not logged in bail now
			if ( ! is_user_logged_in() ) return $content;
			$user_id = get_current_user_id();
		}

		// Get types
		$types_to_addup = array();
		$all = false;
		$existing_types = bonipress_get_types();

		if ( $types == 'all' ) {
			$types_to_addup = array_keys( $existing_types );
		}
		else {
			$types = explode( ',', $types );
			if ( ! empty( $types ) ) {
				foreach ( $types as $type_key ) {
					$type_key = sanitize_text_field( $type_key );
					if ( ! array_key_exists( $type_key, $existing_types ) ) continue;

					if ( ! in_array( $type_key, $types_to_addup ) )
						$types_to_addup[] = $type_key;
				}
			}
		}

		// In case we still have no types, we add the default one
		if ( empty( $types_to_addup ) )
			$types_to_addup = array( 'bonipress_default' );

		// Add up all point type balances
		$total = 0;
		foreach ( $types_to_addup as $type ) {
			// Get the balance for this type
			$balance = bonipress_query_users_total( $user_id, $type );

			$total = $total+$balance;
		}

		// If we want the total unformatted return this now
		if ( $raw )
			return $total;

		// Return formatted
		return apply_filters( 'bonipress_total_balances_output', $total, $atts );
	}
endif;

/**
 * BoniPress Shortcode: bonipress_exchange
 * This shortcode will return an exchange form allowing users to
 * exchange one point type for another.
 * @see https://n3rds.work/docs/bonipress-shortcodes-bonipress_exchange/
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_render_shortcode_exchange' ) ) :
	function bonipress_render_shortcode_exchange( $atts, $content = '' )
	{
		if ( ! is_user_logged_in() ) return $content;

		extract( shortcode_atts( array(
			'from' => '',
			'to'   => '',
			'rate' => 1,
			'min'  => 1
		), $atts ) );

		if ( $from == '' || $to == '' ) return '';

		$types = bonipress_get_types();
		if ( ! array_key_exists( $from, $types ) || ! array_key_exists( $to, $types ) ) return __( 'Punkttypen nicht gefunden.', 'bonipress' );

		$user_id = get_current_user_id();

		$bonipress_from = bonipress( $from );
		if ( $bonipress_from->exclude_user( $user_id ) )
			return sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonipress' ), $bonipress_from->plural() );

		$balance = $bonipress_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonipress_from->number( $min ) )
			return __( 'Dein Guthaben ist zu niedrig, um diese Funktion zu verwenden.', 'bonipress' );

		$bonipress_to = bonipress( $to );
		if ( $bonipress_to->exclude_user( $user_id ) )
			return sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonipress' ), $bonipress_to->plural() );

		global $bonipress_exchange;

		$token = bonipress_create_token( array( $from, $to, $user_id, $rate, $min ) );

		ob_start(); ?>

<style type="text/css">
#bonipress-exchange table tr td { width: 50%; }
#bonipress-exchange table tr td label { display: block; font-weight: bold; font-size: 12px; }
#bonipress-exchange { margin-bottom: 24px; }
.alert-success { color: green; }
.alert-warning { color: red; }
</style>
<div class="bonipress-exchange">
	<form action="" method="post">
		<h3><?php printf( __( 'Konvertiere <span>%s</span> zu <span>%s</span>', 'bonipress' ), $bonipress_from->plural(), $bonipress_to->plural() ); ?></h3>

		<?php if ( isset( $bonipress_exchange['message'] ) ) : ?>
		<div class="alert alert-<?php if ( $bonipress_exchange['success'] ) echo 'success'; else echo 'warning'; ?>"><?php echo $bonipress_exchange['message']; ?></div>
		<?php endif; ?>

		<table class="table">
			<tr>
				<td colspan="2">
					<label><?php printf( __( 'Dein aktuelles %s Guthaben', 'bonipress' ), $bonipress_from->singular() ); ?></label>
					<p><?php echo $bonipress_from->format_creds( $balance ); ?></p>
				</td>
			</tr>
			<tr>
				<td>
					<label for="bonipress-exchange-amount"><?php _e( 'Betrag', 'bonipress' ); ?></label>
					<input type="text" size="12" value="0" id="bonipress-exchange-amount" name="bonipress_exchange[amount]" />
					<?php if ( $min != 0 ) : ?><p><small><?php printf( __( 'Minimum %s', 'bonipress' ), $bonipress_from->format_creds( $min ) ); ?></small></p><?php endif; ?>
				</td>
				<td>
					<label for="exchange-rate"><?php _e( 'Wechselkurs', 'bonipress' ); ?></label>
					<p><?php printf( __( '1 %s = <span class="rate">%s</span> %s', 'bonipress' ), $bonipress_from->singular(), $rate, $bonipress_to->plural() ); ?></p>
				</td>
			</tr>
		</table>
		<input type="hidden" name="bonipress_exchange[token]" value="<?php echo $token; ?>" />
		<input type="hidden" name="bonipress_exchange[nonce]" value="<?php echo wp_create_nonce( 'bonipress-exchange' ); ?>" />
		<input type="submit" class="btn btn-primary button button-primary" value="<?php _e( 'Umwechseln', 'bonipress' ); ?>" />
		<div class="clear clearfix"></div>
	</form>
</div>
<?php
		$output = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'bonipress_exchange_output', $output, $atts );
	}
endif;

/**
 * Run Exchange
 * Intercepts and executes exchange requests.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'bonipress_run_exchange' ) ) :
	add_filter( 'bonipress_init', 'bonipress_run_exchange' );
	function bonipress_run_exchange()
	{
		if ( ! isset( $_POST['bonipress_exchange']['nonce'] ) || ! wp_verify_nonce( $_POST['bonipress_exchange']['nonce'], 'bonipress-exchange' ) ) return;

		// Decode token
		$token = bonipress_verify_token( $_POST['bonipress_exchange']['token'], 5 );
		if ( $token === false ) return;

		global $bonipress_exchange;
		list ( $from, $to, $user_id, $rate, $min ) = $token;

		// Check point types
		$types = bonipress_get_types();
		if ( ! array_key_exists( $from, $types ) || ! array_key_exists( $to, $types ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => __( 'Punkttypen nicht gefunden.', 'bonipress' )
			);
			return;
		}

		$user_id = get_current_user_id();

		// Check for exclusion
		$bonipress_from = bonipress( $from );
		if ( $bonipress_from->exclude_user( $user_id ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonipress' ), $bonipress_from->plural() )
			);
			return;
		}

		// Check balance
		$balance = $bonipress_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonipress_from->number( $min ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => __( 'Dein Guthaben ist zu niedrig, um diese Funktion zu verwenden.', 'bonipress' )
			);
			return;
		}

		// Check for exclusion
		$bonipress_to = bonipress( $to );
		if ( $bonipress_to->exclude_user( $user_id ) ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonipress' ), $bonipress_to->plural() )
			);
			return;
		}

		// Prep Amount
		$amount = abs( $_POST['bonipress_exchange']['amount'] );
		$amount = $bonipress_from->number( $amount );

		// Make sure we are sending more then minimum
		if ( $amount < $min ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du musst mindestens %s umtauschen!', 'bonipress' ), $bonipress_from->format_creds( $min ) )
			);
			return;
		}

		// Make sure we have enough points
		if ( $amount > $balance ) {
			$bonipress_exchange = array(
				'success' => false,
				'message' => __( 'Unzureichende Mittel. Bitte versuche es mit einem niedrigeren Betrag.', 'bonipress' )
			);
			return;
		}

		// Let others decline
		$reply = apply_filters( 'bonipress_decline_exchange', false, compact( 'from', 'to', 'user_id', 'rate', 'min', 'amount' ) );
		if ( $reply === false ) {

			$bonipress_from->add_creds(
				'exchange',
				$user_id,
				0-$amount,
				sprintf( __( 'Umtauschen von %s', 'bonipress' ), $bonipress_from->plural() ),
				0,
				array( 'from' => $from, 'rate' => $rate, 'min' => $min ),
				$from
			);

			$exchanged = $bonipress_to->number( ( $amount * $rate ) );

			$bonipress_to->add_creds(
				'exchange',
				$user_id,
				$exchanged,
				sprintf( __( 'Tausche zu %s', 'bonipress' ), $bonipress_to->plural() ),
				0,
				array( 'to' => $to, 'rate' => $rate, 'min' => $min ),
				$to
			);

			$bonipress_exchange = array(
				'success' => true,
				'message' => sprintf( __( 'Du hast %s erfolgreich in %s umgetauscht.', 'bonipress' ), $bonipress_from->format_creds( $amount ), $bonipress_to->format_creds( $exchanged ) )
			);

		}
		else {
			$bonipress_exchange = array(
				'success' => false,
				'message' => $reply
			);
			return;
		}

	}
endif;
?>