<?php
/**
 * Settings view.
 *
 * @package GRTAddonForElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap grt-admin-wrap">
	<section class="grt-page-header">
		<div>
			<span class="grt-eyebrow"><?php esc_html_e( 'Settings', 'grt-addon-for-elementor' ); ?></span>
			<h1><?php esc_html_e( 'Configure the remote template source and cache behavior.', 'grt-addon-for-elementor' ); ?></h1>
		</div>
	</section>

	<?php settings_errors( API::OPTION_KEY ); ?>

	<div class="grt-panel-grid">
		<article class="grt-panel">
			<form action="options.php" method="post">
				<?php
				settings_fields( 'grt_templates_settings_group' );
				do_settings_sections( 'grt-template-settings' );
				submit_button( __( 'Save Settings', 'grt-addon-for-elementor' ) );
				?>
			</form>
		</article>

		<article class="grt-panel">
			<h2><?php esc_html_e( 'Remote Cache Tools', 'grt-addon-for-elementor' ); ?></h2>
			<p><?php esc_html_e( 'Use this button after updating your API data and you want the library to refresh immediately instead of waiting for the next cache window.', 'grt-addon-for-elementor' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<?php wp_nonce_field( 'grt_clear_template_cache' ); ?>
				<input type="hidden" name="action" value="grt_clear_template_cache" />
				<button type="submit" class="button button-secondary grt-button is-secondary">
					<?php esc_html_e( 'Clear Remote Cache', 'grt-addon-for-elementor' ); ?>
				</button>
			</form>

			<h2><?php esc_html_e( 'Quick Start', 'grt-addon-for-elementor' ); ?></h2>
			<ol class="grt-steps">
				<li><?php esc_html_e( 'Install and activate Elementor.', 'grt-addon-for-elementor' ); ?></li>
				<li><?php esc_html_e( 'Add your templates.json API URL in the field above.', 'grt-addon-for-elementor' ); ?></li>
				<li><?php esc_html_e( 'Enable remote templates if you want API items in the library.', 'grt-addon-for-elementor' ); ?></li>
				<li><?php esc_html_e( 'Go to the Template Library and import the layouts you need.', 'grt-addon-for-elementor' ); ?></li>
			</ol>
		</article>
	</div>
</div>
