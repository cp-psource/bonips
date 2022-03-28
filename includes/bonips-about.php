<?php
if ( ! defined( 'boniPS_VERSION' ) ) exit;

/**
 * boniPS About Page Header
 * @since 1.3.2
 * @version 1.3
 */
function bonips_about_header() {

	$name = bonips_label();

?>
<style type="text/css">
#bonips-badge { background: url('<?php echo plugins_url( 'assets/images/badge.png', boniPS_THIS ); ?>') no-repeat center center; background-size: 140px 160px; }.dashboard_page_bonips-about #wpwrap {background-color: white;}#wpwrap #bonips-about a {color: #666;background-color: #EBEBEB;padding: 10px;text-decoration: none;border-radius: 5px;}
</style>
<h1><?php printf( __( 'Willkommen zu %s %s', 'bonips' ), $name, boniPS_VERSION ); ?></h1>
<div class="about-text"><?php printf( 'Ein adaptives Punkteverwaltungssystem für WordPress-basierte Webseiten.', $name ); ?></div>
<p><?php printf( __( 'Vielen Dank das Du %s verwendest.', 'bonips' ), $name ); ?></p>
<div class="wp-badge" id="bonips-badge">Version <?php echo boniPS_VERSION; ?></div>
<h2 class="nav-tab-wrapper wp-clearfix">
	<a class="nav-tab nav-tab-active" href="#">What&#8217;s New</a>
	<a class="nav-tab" href="https://n3rds.work/gruppen/psource-communityhub/docs/?folder=33146" target="_blank">Dokumentation</a>
	<a class="nav-tab" href="https://n3rds.work/shop/artikel/category/bonips-erweiterungen/" target="_blank">Shop</a>
</h2>
<?php

}


/**
 * About boniPS Page
 * @since 1.3.2
 * @version 1.3
 */
function bonips_about_page() {

?>
<div class="wrap about-wrap" id="bonips-about-wrap">

	<?php bonips_about_header(); ?>

	<div id="bonips-about">
		<h2>Improved Management Tools</h2>
		<div class="feature-section two-col">
			<div class="col">
				<img src="<?php echo plugins_url( 'assets/images/bonips16-stats-addon.png', boniPS_THIS ); ?>" alt="" />			
			</div>
			<div class="col">
				<h3>Statistics 2.0</h3>
				<p>The Statistics add-on has received a complete re-write in order to add support for showing charts and statistical data on the front end of your website. The add-on comes with pre-set types of data that you can select to show either as a table or using charts (or both).</p>								
				<a href="https://bonips.me/guides/1-8-guide-statistics-add-on/">Documentation</a>
			</div>
			<div class="col">
				<h3>New BuyCred Checkout</h3>
				<p>One of the most requested features for buyCRED has been to making the checkout process easier to customize, so the checkout process has been completely re-written. You can now override the built-in template via your theme and style or customize the checkout page anyway you like.</p>
				<p><a href="https://bonips.me/guides/1-8-guide-buycred-add-on-updates/">Documentation</a></p>
			</div>
			<div class="col">	
				<img src="<?php echo plugins_url( 'assets/images/buycred-checkout-page.png', boniPS_THIS ); ?>" alt="" />							
			</div>
		</div>
		<hr />
		<h2>Add-on Improvements</h2>
		<div class="feature-section two-col">
			<div class="col">
				<h3>Ranks</h3>
				<p>As of version 1.8, ranks can be set to be assigned to users manually, just like badges. This means that you will need to manually change your users rank as boniPS will take no action. To do this, simply edit the user in question in the admin area and select the rank you want to assign them.</p>
			</div>
			<div class="col">
				<h3>Email Notifications</h3>
				<p>The email notifications add-on now supports setting up emails for specific instances based on reference.</p>
			</div>
		</div>
		<hr />
		<h2>New Shortcodes</h2>
		<div class="feature-section three-col">
			<div class="col">
				<h3><code>[bonips_chart_circulation]</code></h3>
				<p>This shortcode will render charts based on the amount of points that currently exists amongst your users for each point type.</p>
			</div>
			<div class="col">
				<h3><code>[bonips_chart_gain_loss]</code></h3>
				<p>This shortcode will render charts based on the amount of points that has been given to users vs. the total amount taken.</p>
			</div>
			<div class="col">
				<h3><code>[bonips_chart_top_balances]</code></h3>
				<p>This shortcode will render a list of balances ordered by size.</p>
			</div>
		</div>
		<div class="feature-section one-col">
			<p style="text-align: center;"><a href="https://bonips.me/support/changelog/" target="_blank">View All Changes</a></p>
		</div>
		<hr />
	</div>

	<?php bonips_about_footer(); ?>

</div>
<?php

}
