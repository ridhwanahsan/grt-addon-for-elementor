<?php
/**
 * Template library view.
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
			<span class="grt-eyebrow"><?php esc_html_e( 'Template Library', 'grt-addon-for-elementor' ); ?></span>
			<h1><?php esc_html_e( 'Browse, preview, favorite, and import Elementor templates.', 'grt-addon-for-elementor' ); ?></h1>
		</div>
		<a class="button button-secondary grt-button is-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=grt-template-settings' ) ); ?>">
			<?php esc_html_e( 'Library Settings', 'grt-addon-for-elementor' ); ?>
		</a>
	</section>

	<?php if ( $remote_error instanceof \WP_Error ) : ?>
		<div class="notice notice-warning inline">
			<p><?php echo esc_html( $remote_error->get_error_message() ); ?></p>
		</div>
	<?php endif; ?>

	<div class="grt-library-toolbar">
		<label class="grt-toolbar-field grt-toolbar-search" for="grt-template-search">
			<span class="screen-reader-text"><?php esc_html_e( 'Search templates', 'grt-addon-for-elementor' ); ?></span>
			<input type="search" id="grt-template-search" class="grt-input" placeholder="<?php esc_attr_e( 'Search by name or category...', 'grt-addon-for-elementor' ); ?>" />
		</label>

		<label class="grt-toolbar-field" for="grt-category-filter">
			<span class="screen-reader-text"><?php esc_html_e( 'Filter by category', 'grt-addon-for-elementor' ); ?></span>
			<select id="grt-category-filter" class="grt-select">
				<option value=""><?php esc_html_e( 'All Categories', 'grt-addon-for-elementor' ); ?></option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="grt-toolbar-toggle" for="grt-favorites-only">
			<input type="checkbox" id="grt-favorites-only" />
			<span><?php esc_html_e( 'Favorites only', 'grt-addon-for-elementor' ); ?></span>
		</label>
	</div>

	<div id="grt-template-feedback" class="grt-feedback" hidden></div>

	<div id="grt-template-grid" class="grt-template-grid" aria-live="polite"></div>

	<div id="grt-template-empty" class="grt-empty-state" hidden>
		<h2><?php esc_html_e( 'No templates found', 'grt-addon-for-elementor' ); ?></h2>
		<p><?php esc_html_e( 'Try a different keyword, clear the category filter, or disable the favorites-only toggle.', 'grt-addon-for-elementor' ); ?></p>
	</div>

	<div class="grt-library-footer">
		<div id="grt-library-spinner" class="grt-inline-spinner" hidden></div>
		<button type="button" id="grt-load-more" class="button button-secondary grt-button is-secondary" hidden>
			<?php esc_html_e( 'Load More', 'grt-addon-for-elementor' ); ?>
		</button>
	</div>
</div>

<div id="grt-preview-modal" class="grt-modal" hidden>
	<div class="grt-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="grt-preview-title">
		<div class="grt-modal-header">
			<h2 id="grt-preview-title"><?php esc_html_e( 'Template Preview', 'grt-addon-for-elementor' ); ?></h2>
			<button type="button" class="grt-modal-close" id="grt-preview-close" aria-label="<?php esc_attr_e( 'Close preview', 'grt-addon-for-elementor' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<div class="grt-modal-body">
			<div class="grt-modal-viewport">
				<iframe id="grt-preview-frame" title="<?php esc_attr_e( 'Template preview frame', 'grt-addon-for-elementor' ); ?>" hidden></iframe>
				<img id="grt-preview-image" alt="" hidden />
			</div>
		</div>
		<div class="grt-modal-footer">
			<a id="grt-preview-link" class="button button-secondary grt-button is-secondary" href="#" target="_blank" rel="noopener noreferrer" hidden>
				<?php esc_html_e( 'Open Preview in New Tab', 'grt-addon-for-elementor' ); ?>
			</a>
		</div>
	</div>
</div>
