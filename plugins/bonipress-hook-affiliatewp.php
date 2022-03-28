<?php
if ( ! defined( 'boniPRESS_VERSION' ) ) exit;

/**
 * Register Hook
 * @since 1.6
 * @version 1.1
 */
add_filter( 'bonipress_setup_hooks', 'bonipress_register_affiliatewp_hook', 10 );
function bonipress_register_affiliatewp_hook( $installed ) {

	if ( ! class_exists( 'Affiliate_WP' ) ) return $installed;

	$installed['affiliatewp'] = array(
		'title'         => __( 'AffiliateWP', 'bonipress' ),
		'description'   => __( 'Vergibt %_plural% für Affiliate-Anmeldungen, werbende Besucher und Ladenverkaufsempfehlungen.', 'bonipress' ),
		'documentation' => 'https://n3rds.work/docs/affiliatewp-aktionen/',
		'callback'      => array( 'boniPRESS_AffiliateWP' )
	);

	return $installed;

}

/**
 * Affiliate WP Hook
 * @since 1.6
 * @version 1.1
 */
add_action( 'bonipress_load_hooks', 'bonipress_load_affiliatewp_hook', 10 );
function bonipress_load_affiliatewp_hook() {

	// If the hook has been replaced or if plugin is not installed, exit now
	if ( class_exists( 'boniPRESS_AffiliateWP' ) || ! class_exists( 'Affiliate_WP' ) ) return;

	class boniPRESS_AffiliateWP extends boniPRESS_Hook {

		public $currency;

		/**
		 * Construct
		 */
		public function __construct( $hook_prefs, $type = BONIPS_DEFAULT_TYPE_KEY ) {

			parent::__construct( array(
				'id'       => 'affiliatewp',
				'defaults' => array(
					'signup' => array(
						'creds'  => 0,
						'log'    => '%plural% dafür, dass Du Partner wirst'
					),
					'visits' => array(
						'creds'  => 0,
						'log'    => '%plural% für die Empfehlung eines Besuchers',
						'limit'  => '0/x'
					),
					'referrals' => array(
						'creds'      => 0,
						'exchange'   => 1,
						'currency'   => 'MYC',
						'log'        => '%plural% für Ladenempfehlung',
						'remove_log' => '%plural% Rückerstattung für abgelehnten Verkauf',
						'pay'        => 'amount'
					)
				)
			), $hook_prefs, $type );

			$this->currency = affiliate_wp()->settings->get( 'currency', 'USD' );

			// Möglicherweise möchten wir einen benutzerdefinierten Währungscode hinzufügen
			add_filter( 'affwp_currencies', array( $this, 'add_currency' ) );

			// Ein benutzerdefinierter Währungscode wurde festgelegt und wird in AffiliateWP verwendet!
			// Wir müssen die Art und Weise übernehmen, wie Währungen in AffiliateWP angezeigt werden
			if ( ! empty( $this->prefs['referrals']['currency'] ) && $this->currency == $this->prefs['referrals']['currency'] ) {
				add_filter( 'affwp_format_amount',                                  array( $this, 'amount' ) );
				add_filter( 'affwp_sanitize_amount_decimals',                       array( $this, 'decimals' ) );
				add_filter( 'affwp_' . $this->currency . '_currency_filter_before', array( $this, 'before' ), 10, 3 );
				add_filter( 'affwp_' . $this->currency . '_currency_filter_after',  array( $this, 'after' ), 10, 3 );
			}

		}

		public function add_currency( $currencies ) {

			if ( $this->prefs['referrals']['pay'] == 'currency' && ! empty( $this->prefs['referrals']['currency'] ) && ! array_key_exists( $this->prefs['referrals']['currency'], $currencies ) )
				$currencies[ $this->prefs['referrals']['currency'] ] = $this->core->plural();

			return $currencies;

		}

		public function amount( $amount ) {

			// BoniPRESS-Weise formatieren
			return $this->core->format_number( $amount );

		}

		public function before( $formatted, $currency, $amount ) {

			// Keine Notwendigkeit hinzuzufügen, wenn leer
			if ( $this->core->before != '' )
				$formatted = $this->core->before . ' ' . $amount;

			// Einige haben möglicherweise Anpassungen vorgenommen, wie Punkte angezeigt werden, wende sie auch hier an
			return apply_filters( 'bonipress_format_creds', $formatted, $amount, $this->core );

		}

		public function after( $formatted, $currency, $amount ) {

			// No need to add if empty
			if ( $this->core->after != '' )
				$formatted = $amount . ' ' . $this->core->after;

			// Einige haben möglicherweise Anpassungen vorgenommen, wie Punkte angezeigt werden, wende sie auch hier an
			return apply_filters( 'bonipress_format_creds', $formatted, $amount, $this->core );

		}

		public function decimals( $decimals ) {

			// Holt sich die Dezimaleinstellung
			return absint( $this->core->format['decimals'] );

		}

		/**
		 * Run
		 * @since 1.6
		 * @version 1.0.1
		 */
		public function run() {

			// Wenn wir Affiliate-Anmeldungen belohnen
			if ( $this->prefs['signup']['creds'] != 0 )
				add_action( 'affwp_register_user', array( $this, 'affiliate_signup' ), 10, 3 );

			// Wenn wir Besuchsempfehlungen belohnen
			if ( $this->prefs['visits']['creds'] != 0 )
				add_action( 'affwp_post_insert_visit', array( $this, 'new_visit' ), 10, 2 );

			// Wenn wir Empfehlungen belohnen
			add_action( 'affwp_set_referral_status', array( $this, 'referral_payouts' ), 10, 3 );

		}

		/**
		 * Affiliate-Anmeldung
		 * @since 1.6
		 * @version 1.0
		 */
		public function affiliate_signup( $affiliate_id, $status, $args ) {

			if ( $status == 'pending' ) return;

			// Holt sich Benutzer-ID von der Affiliate-ID
			$user_id = affwp_get_affiliate_user_id( $affiliate_id );

			// Ausschluss prüfen
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Ausführen (falls noch nicht geschehen)
			if ( ! $this->has_entry( 'affiliate_signup', $affiliate_id, $user_id ) )
				$this->core->add_creds(
					'affiliate_signup',
					$user_id,
					$this->prefs['signup']['creds'],
					$this->prefs['signup']['log'],
					$affiliate_id,
					'',
					$this->bonipress_type
				);

		}

		/**
		 * Neuer Besuch
		 * @since 1.6
		 * @version 1.0.1
		 */
		public function new_visit( $insert_id, $data ) {

			$affiliate_id = absint( $data['affiliate_id'] );
			$user_id      = affwp_get_affiliate_user_id( $affiliate_id );

			// Ausschluss prüfen
			if ( $this->core->exclude_user( $user_id ) ) return;

			// Limit
			if ( $this->over_hook_limit( 'visits', 'affiliate_visit_referral', $user_id ) ) return;

			// Ausführen
			$this->core->add_creds(
				'affiliate_visit_referral',
				$user_id,
				$this->prefs['visits']['creds'],
				$this->prefs['visits']['log'],
				$insert_id,
				$data,
				$this->bonipress_type
			);

		}

		/**
		 * Empfehlungsauszahlung
		 * @since 1.6
		 * @version 1.0
		 */
		public function referral_payouts( $referral_id, $new_status, $old_status ) {

			// Wenn die Empfehlungs-ID nicht gültig ist
			if ( ! is_numeric( $referral_id ) ) {
				return;
			}

			// Rufe das Referenzobjekt ab
			$referral = affwp_get_referral( $referral_id );

			// Holt sich die Benutzer-ID
			$user_id  = affwp_get_affiliate_user_id( $referral->affiliate_id );

			// Ausschluss prüfen
			if ( $this->core->exclude_user( $user_id ) ) return;

			$amount   = false;

			// Wir zahlen einen festgelegten Betrag für alle Empfehlungen
			if ( $this->prefs['referrals']['pay'] == 'creds' )
				$amount = $this->prefs['referrals']['creds'];

			// Wir zahlen den Empfehlungsbetrag (vorausgesetzt, Punkte werden als Store-Währung verwendet).
			elseif ( $this->prefs['referrals']['pay'] == 'currency' )
				$amount = $referral->amount;

			// Wir wenden einen Wechselkurs an
			elseif ( $this->prefs['referrals']['pay'] == 'exchange' )
				$amount = $this->core->number( ( $referral->amount * $this->prefs['referrals']['exchange'] ) );

			$amount = apply_filters( 'bonipress_affiliatewp_payout', $amount, $referral, $new_status, $old_status, $this );
			if ( $amount === false ) return;

			if ( 'paid' === $new_status ) {

				$this->core->add_creds(
					'affiliate_referral',
					$user_id,
					$amount,
					$this->prefs['referrals']['log'],
					$referral_id,
					array( 'ref_type' => 'post' ),
					$this->bonipress_type
				);

			}

			elseif ( 'paid' === $old_status ) {

				if ( $this->core->has_entry( 'affiliate_referral', $referral_id, $user_id, array( 'ref_type' => 'post' ), $this->bonipress_type ) )
					$this->core->add_creds(
						'affiliate_referral_refund',
						$user_id,
						0 - $amount,
						$this->prefs['referrals']['remove_log'],
						$referral_id,
						array( 'ref_type' => 'post' ),
						$this->bonipress_type
					);

			}

		}

		/**
		 * Einstellungen
		 * @since 1.6
		 * @version 1.1
		 */
		public function preferences() {

			$prefs = $this->prefs;

?>
<div class="hook-instance">
	<h3><?php _e( 'Affiliate-Anmeldung', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'signup', 'creds' ) ); ?>"><?php _e( 'Betrag', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'signup', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'signup', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['signup']['creds'] ); ?>" class="form-control" />
				<span class="description"><?php _e( 'Verwende Null zum Deaktivieren.', 'bonipress' ); ?></span>
			</div>
		</div>
		<div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'signup', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'signup', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'signup', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['signup']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Referring Visitors', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'visits', 'creds' ) ); ?>"><?php _e( 'Betrag', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'visits', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'visits', 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['visits']['creds'] ); ?>" class="form-control" />
				<span class="description"><?php _e( 'Verwende Null zum Deaktivieren.', 'bonipress' ); ?></span>
			</div>
		</div>
		<div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'visits', 'limit' ) ); ?>"><?php _e( 'Limit', 'bonipress' ); ?></label>
				<?php echo $this->hook_limit_setting( $this->field_name( array( 'visits', 'limit' ) ), $this->field_id( array( 'visits', 'limit' ) ), $prefs['visits']['limit'] ); ?>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'visits', 'log' ) ); ?>"><?php _e( 'Protokollvorlage', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'visits', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'visits', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['visits']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Referring Sales', 'bonipress' ); ?></h3>
	<div class="row">
		<div class="col-lg-4 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'referrals', 'pay-amount' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'referrals', 'pay' ) ); ?>" id="<?php echo $this->field_id( array( 'referrals', 'pay-amount' ) ); ?>"<?php checked( $this->prefs['referrals']['pay'], 'creds' ); ?> value="creds" /> <?php _e( 'Zahle einen festgelegten Betrag', 'bonipress' ); ?></label>
				</div>
				<label for="<?php echo $this->field_id( array( 'referrals', 'creds' ) ); ?>"><?php _e( 'Betrag', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'referrals', 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'referrals', 'creds' ) ); ?>" class="form-control" value="<?php echo $this->core->number( $prefs['referrals']['creds'] ); ?>" />
				<span class="description"><?php _e( 'Alle Empfehlungen zahlen den gleichen Betrag.', 'bonipress' ); ?></span>
			</div>
		</div>
		<div class="col-lg-4 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'referrals', 'pay-store' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'referrals', 'pay' ) ); ?>" id="<?php echo $this->field_id( array( 'referrals', 'pay-store' ) ); ?>"<?php checked( $this->prefs['referrals']['pay'], 'currency' ); ?> value="currency" /> <?php _e( 'Zahle den Empfehlungsbetrag', 'bonipress' ); ?></label>
				</div>
				<label for="<?php echo $this->field_id( array( 'referrals', 'currency' ) ); ?>"><?php _e( 'Punkte-Währungscode', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'referrals', 'currency' ) ); ?>" id="<?php echo $this->field_id( array( 'referrals', 'currency' ) ); ?>" class="form-control" value="<?php echo esc_attr( $prefs['referrals']['currency'] ); ?>" />
				<span class="description"><?php _e( 'Erfordert, dass AffiliateWP und Dein Geschäft Punkte als Währung verwenden.', 'bonipress' ); ?></span>
			</div>
		</div>
		<div class="col-lg-4 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="radio">
					<label for="<?php echo $this->field_id( array( 'referrals', 'pay-ex' ) ); ?>"><input type="radio" name="<?php echo $this->field_name( array( 'referrals', 'pay' ) ); ?>"<?php if ( array_key_exists( $this->currency, $this->point_types ) ) echo ' readonly="readonly"'; ?> id="<?php echo $this->field_id( array( 'referrals', 'pay-ex' ) ); ?>"<?php checked( $this->prefs['referrals']['pay'], 'exchange' ); ?> value="exchange" /> <?php _e( 'Wende einen Wechselkurs an', 'bonipress' ); ?></label>
				</div>
				<label for="<?php echo $this->field_id( array( 'referrals', 'exchange' ) ); ?>"><?php _e( 'Wechselkurs', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'referrals', 'exchange' ) ); ?>" id="<?php echo $this->field_id( array( 'referrals', 'exchange' ) ); ?>" class="form-control"<?php if ( array_key_exists( $this->currency, $this->point_types ) ) echo ' readonly="readonly"'; ?> value="<?php echo esc_attr( $prefs['referrals']['exchange'] ); ?>" />
				<span class="description"><?php if ( ! array_key_exists( $this->currency, $this->point_types ) ) printf( __( 'Wie viel ist 1 %s in %s wert', 'bonipress' ), $this->core->plural(), $this->currency ); else _e( 'Deaktiviert', 'bonipress' ); ?></span>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'referrals', 'log' ) ); ?>"><?php _e( 'Protokollvorlage – Auszahlung', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'referrals', 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'referrals', 'log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['referrals']['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
			</div>
		</div>
		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( array( 'referrals', 'remove_log' ) ); ?>"><?php _e( 'Protokollvorlage – Rückerstattung', 'bonipress' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( array( 'referrals', 'remove_log' ) ); ?>" id="<?php echo $this->field_id( array( 'referrals', 'remove_log' ) ); ?>" placeholder="<?php _e( 'erforderlich', 'bonipress' ); ?>" value="<?php echo esc_attr( $prefs['referrals']['remove_log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

		}
		
		/**
		 * Sanitise Preferences
		 * @since 1.6
		 * @version 1.0
		 */
		function sanitise_preferences( $data ) {

			if ( isset( $data['visits']['limit'] ) && isset( $data['visits']['limit_by'] ) ) {
				$limit = sanitize_text_field( $data['visits']['limit'] );
				if ( $limit == '' ) $limit = 0;
				$data['visits']['limit'] = $limit . '/' . $data['visits']['limit_by'];
				unset( $data['visits']['limit_by'] );
			}

			return $data;

		}

	}

}
