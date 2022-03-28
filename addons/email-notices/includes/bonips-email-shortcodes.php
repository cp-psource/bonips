<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * BoniPress Shortcode: bonips_email_subscriptions
 * Returns a given users rank
 * @see https://n3rds.work/docs/bonips-bonips_email_subscriptions/
 * @since 1.4.6
 * @version 1.1
 */
if ( ! function_exists( 'bonips_render_email_subscriptions' ) ) :
	function bonips_render_email_subscriptions( $atts = array(), $content = '' ) {

		extract( shortcode_atts( array(
			'success' => __( 'Einstellungen aktualisiert', 'bonips' )
		), $atts, BONIPS_SLUG . '_email_subscriptions' ) );

		if ( ! is_user_logged_in() ) return $content;

		$user_id         = get_current_user_id();
		$unsubscriptions = bonips_get_user_meta( $user_id, 'bonips_email_unsubscriptions', '', true );

		if ( $unsubscriptions == '' ) $unsubscriptions = array();

		// Save
		$saved           = false;
		if ( isset( $_REQUEST['do'] ) && $_REQUEST['do'] == 'bonips-unsubscribe' && wp_verify_nonce( $_REQUEST['token'], 'update-bonips-email-subscriptions' ) ) {

			if ( isset( $_POST['bonips_email_unsubscribe'] ) && ! empty( $_POST['bonips_email_unsubscribe'] ) )
				$new_selection = $_POST['bonips_email_unsubscribe'];
			else
				$new_selection = array();

			bonips_update_user_meta( $user_id, 'bonips_email_unsubscriptions', '', $new_selection );
			$unsubscriptions = $new_selection;
			$saved           = true;

		}

		global $wpdb;

		$email_notices   = $wpdb->get_results( $wpdb->prepare( "
			SELECT * 
			FROM {$wpdb->posts} notices

			LEFT JOIN {$wpdb->postmeta} prefs 
				ON ( notices.ID = prefs.post_id AND prefs.meta_key = 'bonips_email_settings' )

			WHERE notices.post_type = 'bonips_email_notice' 
				AND notices.post_status = 'publish'
				AND ( prefs.meta_value LIKE %s OR prefs.meta_value LIKE %s );", '%s:9:"recipient";s:4:"user";%', '%s:9:"recipient";s:4:"both";%' ) );

		ob_start();

		if ( $saved )
			echo '<p class="updated-email-subscriptions">' . $success . '</p>';

			$url             = add_query_arg( array( 'do' => 'bonips-unsubscribe', 'user' => get_current_user_id(), 'token' => wp_create_nonce( 'update-bonips-email-subscriptions' ) ) );

?>
<form action="<?php echo esc_url( $url ); ?>" id="bonips-email-subscriptions" method="post">
	<table class="table">
		<thead>
			<tr>
				<th class="check"><?php _e( 'Abbestellen', 'bonips' ); ?></th>
				<th class="notice-title"><?php _e( 'E-Mail-Benachrichtigung', 'bonips' ); ?></th>
			</tr>
		</thead>
		<tbody>

		<?php if ( ! empty( $email_notices ) ) : ?>
		
			<?php foreach ( $email_notices as $notice ) : $settings = bonips_get_email_settings( $notice->ID ); ?>

			<?php if ( $settings['recipient'] == 'admin' ) continue; ?>

			<tr>
				<td class="check"><input type="checkbox" name="bonips_email_unsubscribe[]"<?php if ( in_array( $notice->ID, $unsubscriptions ) ) echo ' checked="checked"'; ?> value="<?php echo $notice->ID; ?>" /></td>
				<td class="notice-title"><?php echo $settings['label']; ?></td>
			</tr>

			<?php endforeach; ?>
		
		<?php else : ?>

			<tr>
				<td colspan="2"><?php _e( 'Es gibt noch keine E-Mail-Benachrichtigungen.', 'bonips' ); ?></td>
			</tr>

		<?php endif; ?>

		</tbody>
	</table>
	<input type="submit" class="btn btn-primary button button-primary pull-right" value="<?php _e( 'Änderungen speichern', 'bonips' ); ?>" />
</form>
<?php

		$content = ob_get_contents();
		ob_end_clean();

		return apply_filters( 'bonips_render_email_subscriptions', $content, $atts );

	}
endif;
