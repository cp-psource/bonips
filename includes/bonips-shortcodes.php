<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: my_balance
 * Gibt das aktuelle Benutzerguthaben zurück.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_my_balance/
 * @contributor DerN3rd
 * @since 1.0.9
 * @version 1.2.1
 */
if ( ! function_exists( 'bonips_render_shortcode_my_balance' ) ) :
	function bonips_render_shortcode_my_balance( $atts )
	{
		extract( shortcode_atts( array(
			'login'      => NULL,
			'title'      => '',
			'title_el'   => 'h1',
			'balance_el' => 'div',
			'wrapper'    => 1,
			'type'       => 'bonips_default'
		), $atts ) );

		$output = '';

		// Not logged in
		if ( ! is_user_logged_in() ) {
			if ( $login !== NULL ) {
				if ( $wrapper )
					$output .= '<div class="bonips-not-logged-in">';

				$output .= $login;

				if ( $wrapper )
					$output .= '</div>';

				return $output;
			}
			return;
		}

		$user_id = get_current_user_id();
		$bonips = bonips( $type );
		// Check for exclusion
		if ( $bonips->exclude_user( $user_id ) ) return;

		if ( ! empty( $type ) )
			$bonips->cred_id = $type;

		if ( $wrapper )
			$output .= '<div class="bonips-my-balance-wrapper">';

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

		$balance = $bonips->get_users_cred( $user_id, $type );
		$output .= $bonips->format_creds( $balance );

		if ( ! empty( $balance_el ) )
			$output .= '</' . $balance_el . '>';

		if ( $wrapper )
			$output .= '</div>';

		return $output;
	}
endif;

/**
 * BoniPress Shortcode: bonips_history
 * Returns the points history.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_history/
 * @since 1.0.9
 * @version 1.1.1
 */
if ( ! function_exists( 'bonips_render_shortcode_history' ) ) :
	function bonips_render_shortcode_history( $atts )
	{
		extract( shortcode_atts( array(
			'user_id'   => NULL,
			'number'    => NULL,
			'time'      => NULL,
			'ref'       => NULL,
			'order'     => NULL,
			'show_user' => false,
			'login'     => '',
			'type'      => 'bonips_default'
		), $atts ) );

		// If we are not logged in
		if ( ! is_user_logged_in() && ! empty( $login ) ) return '<p class="bonips-history login">' . $login . '</p>';

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

		$log = new boniPS_Query_Log( $args );

		if ( $show_user !== true )
			unset( $log->headers['column-username'] ); 

		$result = $log->get_display();
		$log->reset_query();
		return $result;
	}
endif;

/**
 * BoniPress Shortcode: bonips_leaderboard
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_leaderboard//
 * @since 0.1
 * @version 1.4
 */
if ( ! function_exists( 'bonips_render_shortcode_leaderboard' ) ) :
	function bonips_render_shortcode_leaderboard( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'number'   => '-1',
			'order'    => 'DESC',
			'offset'   => 0,
			'type'     => 'bonips_default',
			'based_on' => 'balance',
			'wrap'     => 'li',
			'template' => '#%position% %user_profile_link% %cred_f%',
			'nothing'  => __( 'Die Rangliste ist leer.', 'bonips' ),
			'current'  => 0
		), $atts ) );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) )
			$order = 'DESC';

		if ( $number != '-1' )
			$limit = 'LIMIT ' . absint( $offset ) . ',' . absint( $number );
		else
			$limit = '';

		$bonips = bonips( $type );

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
				FROM {$bonips->log_table} 
				WHERE ref = %s 
				GROUP BY user_id 
				ORDER BY SUM( creds ) {$order} {$limit};", $based_on );

		$leaderboard = $wpdb->get_results( apply_filters( 'bonips_ranking_sql', $SQL ), 'ARRAY_A' );

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

			// Load boniPS
			$bonips = bonips( $type );

			// Wrapper
			if ( $wrap == 'li' )
				$output .= '<ol class="boniPS-leaderboard">';

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
				if ( ! function_exists( 'bonips_get_users_rank' ) )
					$layout = str_replace( array( '%rank%', '%ranking%', '%position%' ), $position+1, $template );
				else
					$layout = str_replace( array( '%ranking%', '%position%' ), $position+1, $template );

				$layout = $bonips->template_tags_amount( $layout, $user['cred'] );
				$layout = $bonips->template_tags_user( $layout, $user['ID'] );

				// Wrapper
				if ( ! empty( $wrap ) )
					$layout = '<' . $wrap . ' class="%classes%">' . $layout . '</' . $wrap . '>';

				$layout = str_replace( '%classes%', apply_filters( 'bonips_ranking_classes', implode( ' ', $class ) ), $layout );
				$layout = apply_filters( 'bonips_ranking_row', $layout, $template, $user, $position+1 );

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
						FROM {$bonips->log_table} 
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
						if ( ! function_exists( 'bonips_get_users_rank' ) )
							$layout = str_replace( array( '%rank%', '%ranking%', '%position%' ), $current_position+1, $template );
						else
							$layout = str_replace( array( '%ranking%', '%position%' ), $current_position+1, $template );

						$layout = $bonips->template_tags_amount( $layout, $bonips->get_users_cred( $current_user->ID, $type ) );
						$layout = $bonips->template_tags_user( $layout, false, $current_user );

						// Wrapper
						if ( ! empty( $wrap ) )
							$layout = '<' . $wrap . ' class="%classes%">' . $layout . '</' . $wrap . '>';

						$layout = str_replace( '%classes%', apply_filters( 'bonips_ranking_classes', implode( ' ', $class ) ), $layout );
						$layout = apply_filters( 'bonips_ranking_row', $layout, $template, $current_user, $current_position+1 );

						$output .= $layout . "\n";
						
					}
				}

			}

			if ( $wrap == 'li' )
				$output .= '</ol>';

		}

		// No result template is set
		else {

			$output .= '<p class="bonips-leaderboard-none">' . $nothing . '</p>';

		}

		return do_shortcode( apply_filters( 'bonips_leaderboard', $output, $atts ) );
	}
endif;

/**
 * BoniPress Shortcode: bonips_my_ranking
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_my_ranking/
 * @since 0.1
 * @version 1.4
 */
if ( ! function_exists( 'bonips_render_shortcode_my_ranking' ) ) :
	function bonips_render_shortcode_my_ranking( $atts )
	{
		extract( shortcode_atts( array(
			'user_id'  => NULL,
			'ctype'    => 'bonips_default',
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
			$ctype = 'bonips_default';

		$bonips = bonips( $ctype );

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
				FROM {$bonips->log_table} 
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

		return apply_filters( 'bonips_get_leaderboard_position', $position, $user_id, $ctype );
	}
endif;

/**
 * BoniPress Shortcode: bonips_give
 * This shortcode allows you to award or deduct points from a given user or the current user
 * when this shortcode is executed. You can insert this in page/post content
 * or in a template file. Note that users are awarded/deducted points each time
 * this shortcode exectutes!
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_give/
 * @since 1.1
 * @version 1.1.1
 */
if ( ! function_exists( 'bonips_render_shortcode_give' ) ) :
	function bonips_render_shortcode_give( $atts )
	{
		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount'  => NULL,
			'user_id' => '',
			'log'     => '',
			'ref'     => 'gift',
			'limit'   => 0,
			'type'    => 'bonips_default'
		), $atts ) );

		if ( $amount === NULL )
			return '<strong>' . __( 'Fehler', 'bonips' ) . '</strong> ' . __( 'Betrag fehlt!', 'bonips' );

		if ( empty( $log ) )
			return '<strong>' . __( 'Fehler', 'bonips' ) . '</strong> ' . __( 'Protokollvorlage fehlt!', 'bonips' );

		$bonips = bonips();

		if ( empty( $user_id ) )
			$user_id = get_current_user_id();

		// Check for exclusion
		if ( $bonips->exclude_user( $user_id ) ) return;

		// Limit
		$limit = abs( $limit );
		if ( $limit != 0 && bonips_count_ref_instances( $ref, $user_id ) >= $limit ) return;

		$amount = $bonips->number( $amount );
		$bonips->add_creds(
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
 * BoniPress Shortcode: bonips_link
 * This shortcode allows you to award or deduct points from the current user
 * when their click on a link. The shortcode will generate an anchor element
 * and call the bonips-click-link jQuery script which will award the points.
 *
 * Note! Only HTML5 anchor attributes are supported and this shortcode is only
 * available if the hook is enabled!
 *
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_link/
 * @since 1.1
 * @version 1.1
 */
if ( ! function_exists( 'bonips_render_shortcode_link' ) ) :
	function bonips_render_shortcode_link( $atts, $content = ''	 )
	{
		global $bonips_link_points;

		$atts = shortcode_atts( array(
			'id'       => '',
			'rel'      => '',
			'class'    => '',
			'href'     => '',
			'title'    => '',
			'target'   => '',
			'style'    => '',
			'amount'   => 0,
			'ctype'    => 'bonips_default',
			'hreflang' => '',   // for advanced users
			'media'    => '',   // for advanced users
			'type'     => ''    // for advanced users
		), $atts );

		// HREF is required
		if ( empty( $atts['href'] ) )
			return '<strong>' . __( 'Fehler', 'bonips' ) . '</strong> ' . __( 'Anker fehlende URL!', 'bonips' );

		// All links must contain the 'bonips-points-link' class
		if ( empty( $atts['class'] ) )
			$atts['class'] = 'bonips-points-link';
		else
			$atts['class'] = 'bonips-points-link ' . $atts['class'];

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
		$token = bonips_create_token( array( $atts['amount'], $atts['ctype'], $atts['id'] ) );
		$attr[] = 'data-token="' . $token . '"';

		// Make sure jQuery script is called
		$bonips_link_points = true;

		// Return result
		return '<a ' . implode( ' ', $attr ) . '>' . $content . '</a>';
	}
endif;

/**
 * BoniPress Shortcode: bonips_send
 * This shortcode allows the current user to send a pre-set amount of points
 * to a pre-set user. A simpler version of the bonips_transfer shortcode.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_send/ 
 * @since 1.1
 * @version 1.0
 */
if ( ! function_exists( 'bonips_render_shortcode_send' ) ) :
	function bonips_render_shortcode_send( $atts, $content = NULL )
	{
		if ( ! is_user_logged_in() ) return;

		extract( shortcode_atts( array(
			'amount' => NULL,
			'to'     => NULL,
			'log'    => '',
			'ref'    => 'gift',
			'type'   => 'bonips_default'
		), $atts ) );

		// Amount is required
		if ( $amount === NULL )
			return '<strong>' . __( 'Fehler', 'bonips' ) . '</strong> ' . __( 'Betrag fehlt!', 'bonips' );

		// Recipient is required
		if ( empty( $to ) )
			return '<strong>' . __( 'Fehler', 'bonips' ) . '</strong> ' . __( 'Benutzer-ID für Empfänger fehlt.', 'bonips' );

		// Log template is required
		if ( empty( $log ) )
			return '<strong>' . __( 'Fehler', 'bonips' ) . '</strong> ' . __( 'Protokollvorlage fehlt!', 'bonips' );

		if ( $to == 'author' ) {
			// You can not use this outside the loop
			$author = get_the_author_meta( 'ID' );
			if ( empty( $author ) ) $author = $GLOBALS['post']->post_author;
			$to = $author;
		}

		global $bonips_sending_points;

		$bonips = bonips( $type );
		$user_id = get_current_user_id();

		// Make sure current user or recipient is not excluded!
		if ( $bonips->exclude_user( $to ) || $bonips->exclude_user( $user_id ) ) return;

		$account_limit = (int) apply_filters( 'bonips_transfer_acc_limit', 0 );
		$balance = $bonips->get_users_cred( $user_id, $type );
		$amount = $bonips->number( $amount );

		// Insufficient Funds
		if ( $balance-$amount < $account_limit ) return;

		// We are ready!
		$bonips_sending_points = true;

		return '<input type="button" class="bonips-send-points-button" data-to="' . $to . '" data-ref="' . $ref . '" data-log="' . $log . '" data-amount="' . $amount . '" data-type="' . $type . '" value="' . $bonips->template_tags_general( $content ) . '" />';
	}
endif;

/**
 * Load boniPS Send Points Footer
 * @since 0.1
 * @version 1.2
 */
if ( ! function_exists( 'bonips_send_shortcode_footer' ) ) :
	add_action( 'wp_footer', 'bonips_send_shortcode_footer' );
	function bonips_send_shortcode_footer()
	{
		global $bonips_sending_points;

		if ( $bonips_sending_points === true ) {
			$bonips = bonips();
			$base = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'token'   => wp_create_nonce( 'bonips-send-points' )
			);

			$language = apply_filters( 'bonips_send_language', array(
				'working' => __( 'Verarbeitung...', 'bonips' ),
				'done'    => __( 'Gesendet', 'bonips' ),
				'error'   => __( 'Fehler, versuche es erneut', 'bonips' )
			) );
			wp_localize_script(
				'bonips-send-points',
				'boniPSsend',
				array_merge_recursive( $base, $language )
			);
			wp_enqueue_script( 'bonips-send-points' );
		}
	}
endif;

/**
 * boniPS Send Points Ajax
 * @since 0.1
 * @version 1.3
 */
if ( ! function_exists( 'bonips_shortcode_send_points_ajax' ) ) :
	add_action( 'wp_ajax_bonips-send-points', 'bonips_shortcode_send_points_ajax' );
	function bonips_shortcode_send_points_ajax()
	{
		// We must be logged in
		if ( ! is_user_logged_in() ) die();

		// Security
		check_ajax_referer( 'bonips-send-points', 'token' );

		$bonips_types = bonips_get_types();
		$type = 'bonips_default';
		if ( isset( $_POST['type'] ) )
			$type = sanitize_text_field( $type );

		if ( ! array_key_exists( $type, $bonips_types ) ) die();

		$bonips = bonips( $type );
		$user_id = get_current_user_id();

		$account_limit = (int) apply_filters( 'bonips_transfer_acc_limit', 0 );
		$balance = $bonips->get_users_cred( $user_id, $type );
		$amount = $bonips->number( $_POST['amount'] );
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
		$bonips->add_creds(
			trim( $_POST['reference'] ),
			$user_id,
			0-$amount,
			trim( $_POST['log'] ),
			$_POST['recipient'],
			array( 'ref_type' => 'user' ),
			$type
		);

		// Then add to recipient
		$bonips->add_creds(
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
 * BoniPress Shortcode: bonips_video
 * This shortcode allows points to be given to the current user
 * for watchinga YouTube video.
 * @see https://github.com/cp-psource/docs/bonips-shortcode-bonips_video/
 * @since 1.2
 * @version 1.1.1
 */
if ( ! function_exists( 'bonips_render_shortcode_video' ) ) :
	function bonips_render_shortcode_video( $atts )
	{
		global $bonips_video_points;

		$hooks = bonips_get_option( 'bonips_pref_hooks', false );
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
		if ( $id === NULL || empty( $id ) ) return __( 'Für diesen Shortcode ist eine Video-ID erforderlich', 'bonips' );

		// Interval
		if ( strlen( $interval ) < 3 )
			$interval = abs( $interval * 1000 );

		// Video ID
		$video_id = str_replace( '-', '__', $id );

		// Create key
		$key = bonips_create_token( array( 'youtube', $video_id, $amount, $logic, $interval ) );

		if ( ! isset( $bonips_video_points ) || ! is_array( $bonips_video_points ) )
			$bonips_video_points = array();

		// Construct YouTube Query
		$query = apply_filters( 'bonips_video_query_youtube', array(
			'enablejsapi' => 1,
			'version'     => 3,
			'playerapiid' => 'bonips_vvideo_v' . $video_id,
			'rel'         => 0,
			'controls'    => 1,
			'showinfo'    => 0
		), $atts, $video_id );

		// Construct Youtube Query Address
		$url = 'https://www.youtube.com/embed/' . $id;
		$url = add_query_arg( $query, $url );

		$bonips_video_points[] = 'youtube';

		// Make sure video source ids are unique
		$bonips_video_points = array_unique( $bonips_video_points );

		ob_start(); ?>

<div class="bonips-video-wrapper youtube-video">
	<iframe id="bonips_vvideo_v<?php echo $video_id; ?>" class="bonips-video bonips-youtube-video" data-vid="<?php echo $video_id; ?>" src="<?php echo $url; ?>" width="<?php echo $width; ?>" height="<?php echo $height; ?>" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	<script type="text/javascript">
function bonips_vvideo_v<?php echo $video_id; ?>( state ) {
	duration[ "<?php echo $video_id; ?>" ] = state.target.getDuration();
	bonips_view_video( "<?php echo $video_id; ?>", state.data, "<?php echo $logic; ?>", "<?php echo $interval; ?>", "<?php echo $key; ?>" );
}
	</script>
</div>
<?php
		$output = ob_get_contents();
		ob_end_clean();
		
		// Return the shortcode output
		return apply_filters( 'bonips_video_output', $output, $atts );
	}
endif;

/**
 * BoniPress Shortcode: bonips_total_balance
 * This shortcode will return either the current user or a given users
 * total balance based on either all point types or a comma seperated list
 * of types.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_total_balance/
 * @since 1.4.3
 * @version 1.1
 */
if ( ! function_exists( 'bonips_render_shortcode_total' ) ) :
	function bonips_render_shortcode_total( $atts, $content = '' )
	{
		extract( shortcode_atts( array(
			'user_id' => NULL,
			'types'   => 'bonips_default',
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
		$existing_types = bonips_get_types();

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
			$types_to_addup = array( 'bonips_default' );

		// Add up all point type balances
		$total = 0;
		foreach ( $types_to_addup as $type ) {
			// Get the balance for this type
			$balance = bonips_query_users_total( $user_id, $type );

			$total = $total+$balance;
		}

		// If we want the total unformatted return this now
		if ( $raw )
			return $total;

		// Return formatted
		return apply_filters( 'bonips_total_balances_output', $total, $atts );
	}
endif;

/**
 * BoniPress Shortcode: bonips_exchange
 * This shortcode will return an exchange form allowing users to
 * exchange one point type for another.
 * @see https://github.com/cp-psource/docs/bonips-shortcodes-bonips_exchange/
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'bonips_render_shortcode_exchange' ) ) :
	function bonips_render_shortcode_exchange( $atts, $content = '' )
	{
		if ( ! is_user_logged_in() ) return $content;

		extract( shortcode_atts( array(
			'from' => '',
			'to'   => '',
			'rate' => 1,
			'min'  => 1
		), $atts ) );

		if ( $from == '' || $to == '' ) return '';

		$types = bonips_get_types();
		if ( ! array_key_exists( $from, $types ) || ! array_key_exists( $to, $types ) ) return __( 'Punkttypen nicht gefunden.', 'bonips' );

		$user_id = get_current_user_id();

		$bonips_from = bonips( $from );
		if ( $bonips_from->exclude_user( $user_id ) )
			return sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_from->plural() );

		$balance = $bonips_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonips_from->number( $min ) )
			return __( 'Dein Guthaben ist zu niedrig, um diese Funktion zu verwenden.', 'bonips' );

		$bonips_to = bonips( $to );
		if ( $bonips_to->exclude_user( $user_id ) )
			return sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_to->plural() );

		global $bonips_exchange;

		$token = bonips_create_token( array( $from, $to, $user_id, $rate, $min ) );

		ob_start(); ?>

<style type="text/css">
#bonips-exchange table tr td { width: 50%; }
#bonips-exchange table tr td label { display: block; font-weight: bold; font-size: 12px; }
#bonips-exchange { margin-bottom: 24px; }
.alert-success { color: green; }
.alert-warning { color: red; }
</style>
<div class="bonips-exchange">
	<form action="" method="post">
		<h3><?php printf( __( 'Konvertiere <span>%s</span> zu <span>%s</span>', 'bonips' ), $bonips_from->plural(), $bonips_to->plural() ); ?></h3>

		<?php if ( isset( $bonips_exchange['message'] ) ) : ?>
		<div class="alert alert-<?php if ( $bonips_exchange['success'] ) echo 'success'; else echo 'warning'; ?>"><?php echo $bonips_exchange['message']; ?></div>
		<?php endif; ?>

		<table class="table">
			<tr>
				<td colspan="2">
					<label><?php printf( __( 'Dein aktuelles %s Guthaben', 'bonips' ), $bonips_from->singular() ); ?></label>
					<p><?php echo $bonips_from->format_creds( $balance ); ?></p>
				</td>
			</tr>
			<tr>
				<td>
					<label for="bonips-exchange-amount"><?php _e( 'Betrag', 'bonips' ); ?></label>
					<input type="text" size="12" value="0" id="bonips-exchange-amount" name="bonips_exchange[amount]" />
					<?php if ( $min != 0 ) : ?><p><small><?php printf( __( 'Minimum %s', 'bonips' ), $bonips_from->format_creds( $min ) ); ?></small></p><?php endif; ?>
				</td>
				<td>
					<label for="exchange-rate"><?php _e( 'Wechselkurs', 'bonips' ); ?></label>
					<p><?php printf( __( '1 %s = <span class="rate">%s</span> %s', 'bonips' ), $bonips_from->singular(), $rate, $bonips_to->plural() ); ?></p>
				</td>
			</tr>
		</table>
		<input type="hidden" name="bonips_exchange[token]" value="<?php echo $token; ?>" />
		<input type="hidden" name="bonips_exchange[nonce]" value="<?php echo wp_create_nonce( 'bonips-exchange' ); ?>" />
		<input type="submit" class="btn btn-primary button button-primary" value="<?php _e( 'Umwechseln', 'bonips' ); ?>" />
		<div class="clear clearfix"></div>
	</form>
</div>
<?php
		$output = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'bonips_exchange_output', $output, $atts );
	}
endif;

/**
 * Run Exchange
 * Intercepts and executes exchange requests.
 * @since 1.5
 * @version 1.0
 */
if ( ! function_exists( 'bonips_run_exchange' ) ) :
	add_filter( 'bonips_init', 'bonips_run_exchange' );
	function bonips_run_exchange()
	{
		if ( ! isset( $_POST['bonips_exchange']['nonce'] ) || ! wp_verify_nonce( $_POST['bonips_exchange']['nonce'], 'bonips-exchange' ) ) return;

		// Decode token
		$token = bonips_verify_token( $_POST['bonips_exchange']['token'], 5 );
		if ( $token === false ) return;

		global $bonips_exchange;
		list ( $from, $to, $user_id, $rate, $min ) = $token;

		// Check point types
		$types = bonips_get_types();
		if ( ! array_key_exists( $from, $types ) || ! array_key_exists( $to, $types ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => __( 'Punkttypen nicht gefunden.', 'bonips' )
			);
			return;
		}

		$user_id = get_current_user_id();

		// Check for exclusion
		$bonips_from = bonips( $from );
		if ( $bonips_from->exclude_user( $user_id ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_from->plural() )
			);
			return;
		}

		// Check balance
		$balance = $bonips_from->get_users_balance( $user_id, $from );
		if ( $balance < $bonips_from->number( $min ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => __( 'Dein Guthaben ist zu niedrig, um diese Funktion zu verwenden.', 'bonips' )
			);
			return;
		}

		// Check for exclusion
		$bonips_to = bonips( $to );
		if ( $bonips_to->exclude_user( $user_id ) ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du bist von der Verwendung von %s ausgeschlossen.', 'bonips' ), $bonips_to->plural() )
			);
			return;
		}

		// Prep Amount
		$amount = abs( $_POST['bonips_exchange']['amount'] );
		$amount = $bonips_from->number( $amount );

		// Make sure we are sending more then minimum
		if ( $amount < $min ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => sprintf( __( 'Du musst mindestens %s umtauschen!', 'bonips' ), $bonips_from->format_creds( $min ) )
			);
			return;
		}

		// Make sure we have enough points
		if ( $amount > $balance ) {
			$bonips_exchange = array(
				'success' => false,
				'message' => __( 'Unzureichende Mittel. Bitte versuche es mit einem niedrigeren Betrag.', 'bonips' )
			);
			return;
		}

		// Let others decline
		$reply = apply_filters( 'bonips_decline_exchange', false, compact( 'from', 'to', 'user_id', 'rate', 'min', 'amount' ) );
		if ( $reply === false ) {

			$bonips_from->add_creds(
				'exchange',
				$user_id,
				0-$amount,
				sprintf( __( 'Umtauschen von %s', 'bonips' ), $bonips_from->plural() ),
				0,
				array( 'from' => $from, 'rate' => $rate, 'min' => $min ),
				$from
			);

			$exchanged = $bonips_to->number( ( $amount * $rate ) );

			$bonips_to->add_creds(
				'exchange',
				$user_id,
				$exchanged,
				sprintf( __( 'Tausche zu %s', 'bonips' ), $bonips_to->plural() ),
				0,
				array( 'to' => $to, 'rate' => $rate, 'min' => $min ),
				$to
			);

			$bonips_exchange = array(
				'success' => true,
				'message' => sprintf( __( 'Du hast %s erfolgreich in %s umgetauscht.', 'bonips' ), $bonips_from->format_creds( $amount ), $bonips_to->format_creds( $exchanged ) )
			);

		}
		else {
			$bonips_exchange = array(
				'success' => false,
				'message' => $reply
			);
			return;
		}

	}
endif;
?>