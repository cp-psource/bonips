<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_video
 * This shortcode allows points to be given to the current user
 * for watchinga YouTube video.
 * @see https://github.com/cp-psource/docs/bonips-shortcode-bonips_video/
 * @since 1.2
 * @version 1.2.2
 */
if ( ! function_exists( 'bonips_render_shortcode_video' ) ) :
	function bonips_render_shortcode_video( $atts ) {

		global $bonips_video_points;

		extract( shortcode_atts( array(
			'id'       => NULL,
			'width'    => 560,
			'height'   => 315,
			'amount'   => '',
			'logic'    => '',
			'interval' => '',
			'ctype'    => BONIPS_DEFAULT_TYPE_KEY
		), $atts, BONIPS_SLUG . '_video' ) );

		$hooks    = bonips_get_option( 'bonips_pref_hooks', false );
		if ( $ctype != BONIPS_DEFAULT_TYPE_KEY )
			$hooks = bonips_get_option( 'bonips_pref_hooks_' . sanitize_key( $ctype ), false );

		if ( $hooks === false || ! is_array( $hooks ) || ! array_key_exists( 'video_view', $hooks['hook_prefs'] ) ) return;
		$prefs    = $hooks['hook_prefs']['video_view'];

		if ( $amount == '' )
			$amount = $prefs['creds'];

		if ( $logic == '' )
			$logic = $prefs['logic'];

		if ( $interval == '' )
			$interval = $prefs['interval'];

		// ID is required
		if ( $id === NULL || empty( $id ) ) return __( 'Für diesen Shortcode ist eine Video-ID erforderlich', 'bonips' );

		// Interval
		if ( strlen( $interval ) < 3 ) {
		   $interval = (float) $interval;
           $interval = abs( $interval * 1000 );
        }

		// Video ID
		$video_id = str_replace( '-', '__', $id );

		// Create key
		$key      = bonips_create_token( array( 'youtube', $video_id, $amount, $logic, $interval, $ctype ) );

		if ( ! isset( $bonips_video_points ) || ! is_array( $bonips_video_points ) )
			$bonips_video_points = array();

		// Construct YouTube Query
		$query    = apply_filters( 'bonips_video_query_youtube', array(
			'enablejsapi' => 1,
			'version'     => 3,
			'playerapiid' => 'bonips_vvideo_v' . $video_id,
			'rel'         => 0,
			'controls'    => 1,
			'showinfo'    => 0
		), $atts, $video_id );

		if ( ! is_user_logged_in() )
			unset( $query['playerapiid'] );

		// Construct Youtube Query Address
		$url      = 'https://www.youtube.com/embed/' . $id;
		$url      = add_query_arg( $query, $url );

		$bonips_video_points[] = 'youtube';

		// Make sure video source ids are unique
		$bonips_video_points   = array_unique( $bonips_video_points );

		ob_start();

?>
<div class="row bonips-video-wrapper youtube-video">
	<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
		<iframe id="bonips_vvideo_v<?php echo $video_id; ?>" class="bonips-video bonips-youtube-video" data-vid="<?php echo $video_id; ?>" src="<?php echo esc_url( $url ); ?>" width="<?php echo $width; ?>" height="<?php echo $height; ?>" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	</div>
</div>
<?php

		if ( is_user_logged_in() ) :

?>
<script type="text/javascript">function bonips_vvideo_v<?php echo $video_id; ?>( state ) { duration[ "<?php echo $video_id; ?>" ] = state.target.getDuration(); bonips_view_video( "<?php echo $video_id; ?>", state.data, "<?php echo $logic; ?>", "<?php echo $interval; ?>", "<?php echo $key; ?>", "<?php echo $ctype; ?>" ); }</script>
<?php

		endif;

		$output = ob_get_contents();
		ob_end_clean();

		// Return the shortcode output
		return apply_filters( 'bonips_video_output', $output, $atts );

	}
endif;
add_shortcode( BONIPS_SLUG . '_video', 'bonips_render_shortcode_video' );
