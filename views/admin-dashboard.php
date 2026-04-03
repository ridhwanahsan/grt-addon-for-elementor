<?php
/**
 * Admin dashboard view.
 *
 * @package GRTAddonForElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap grt-admin-wrap">
	<section class="grt-hero">
		<div class="grt-hero-copy">
			<span class="grt-eyebrow"><?php esc_html_e( 'GRT Template Library', 'grt-addon-for-elementor' ); ?></span>
			<h1><?php esc_html_e( 'A clean Elementor import workflow for local and remote templates.', 'grt-addon-for-elementor' ); ?></h1>
			<p><?php esc_html_e( 'Bundle JSON templates inside the plugin, connect a remote templates API, and import everything into Elementor with secure AJAX actions and transient-based caching.', 'grt-addon-for-elementor' ); ?></p>
			<div class="grt-hero-actions">
				<a class="button button-primary grt-button is-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=grt-template-library' ) ); ?>">
					<?php esc_html_e( 'Open Template Library', 'grt-addon-for-elementor' ); ?>
				</a>
				<a class="button button-secondary grt-button is-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=grt-template-settings' ) ); ?>">
					<?php esc_html_e( 'Configure API', 'grt-addon-for-elementor' ); ?>
				</a>
			</div>
		</div>
		<div class="grt-hero-status">
			<div class="grt-status-chip is-<?php echo esc_attr( $remote_status['state'] ); ?>">
				<?php echo esc_html( $remote_status['label'] ); ?>
			</div>
			<p><?php echo esc_html( $remote_status['details'] ); ?></p>
		</div>
	</section>

	<section class="grt-stat-grid">
		<article class="grt-stat-card">
			<span class="grt-stat-label"><?php esc_html_e( 'Total Templates', 'grt-addon-for-elementor' ); ?></span>
			<strong><?php echo esc_html( (string) $summary['all'] ); ?></strong>
			<p><?php esc_html_e( 'Combined local and remote entries available to the library UI.', 'grt-addon-for-elementor' ); ?></p>
		</article>
		<article class="grt-stat-card">
			<span class="grt-stat-label"><?php esc_html_e( 'Local Templates', 'grt-addon-for-elementor' ); ?></span>
			<strong><?php echo esc_html( (string) $summary['local'] ); ?></strong>
			<p><?php esc_html_e( 'Bundled JSON templates shipped directly with the plugin.', 'grt-addon-for-elementor' ); ?></p>
		</article>
		<article class="grt-stat-card">
			<span class="grt-stat-label"><?php esc_html_e( 'Remote Templates', 'grt-addon-for-elementor' ); ?></span>
			<strong><?php echo esc_html( (string) $summary['remote'] ); ?></strong>
			<p><?php esc_html_e( 'Templates fetched from your configured API and cached locally.', 'grt-addon-for-elementor' ); ?></p>
		</article>
		<article class="grt-stat-card">
			<span class="grt-stat-label"><?php esc_html_e( 'Cache Window', 'grt-addon-for-elementor' ); ?></span>
			<strong><?php echo esc_html( (string) $settings['cache_duration'] ); ?>m</strong>
			<p><?php esc_html_e( 'Remote library refresh interval in minutes.', 'grt-addon-for-elementor' ); ?></p>
		</article>
	</section>

	<section class="grt-panel-grid">
		<article class="grt-panel">
			<h2><?php esc_html_e( 'Installation Guide', 'grt-addon-for-elementor' ); ?></h2>
			<ol class="grt-steps">
				<li><?php esc_html_e( 'Install and activate Elementor.', 'grt-addon-for-elementor' ); ?></li>
				<li><?php esc_html_e( 'Go to GRT Templates > Settings and add your remote API URL.', 'grt-addon-for-elementor' ); ?></li>
				<li><?php esc_html_e( 'Enable remote templates and choose an API cache duration.', 'grt-addon-for-elementor' ); ?></li>
				<li><?php esc_html_e( 'Open GRT Templates > Template Library.', 'grt-addon-for-elementor' ); ?></li>
				<li><?php esc_html_e( 'Preview a design, then click Import to add it to the Elementor library.', 'grt-addon-for-elementor' ); ?></li>
			</ol>
		</article>

		<article class="grt-panel">
			<h2><?php esc_html_e( 'API Response Shape', 'grt-addon-for-elementor' ); ?></h2>
			<pre class="grt-code-block">[
  {
    "id": "home-1",
    "title": "Home 1",
    "category": "Business",
    "thumbnail": "https://yourdomain.com/templates/images/home1.jpg",
    "json_url": "https://yourdomain.com/templates/home1.json"
  }
]</pre>
			<p><?php esc_html_e( 'Optional fields such as preview_url, demo_url, required_plugins, description, and type are also supported.', 'grt-addon-for-elementor' ); ?></p>
		</article>
	</section>
</div>
