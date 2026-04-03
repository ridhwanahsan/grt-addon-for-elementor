<?php
/**
 * Admin controller.
 *
 * @package GRTAddonForElementor
 */

namespace GRTAddonForElementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/**
	 * Settings group.
	 *
	 * @var string
	 */
	private const SETTINGS_GROUP = 'grt_templates_settings_group';

	/**
	 * Admin capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Templates per batch.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 10;

	/**
	 * Template data API.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * Import service.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @param API      $api Template API service.
	 * @param Importer $importer Import service.
	 */
	public function __construct( API $api, Importer $importer ) {
		$this->api      = $api;
		$this->importer = $importer;

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_grt_clear_template_cache', [ $this, 'clear_template_cache' ] );
		add_action( 'wp_ajax_grt_get_templates', [ $this, 'ajax_get_templates' ] );
		add_action( 'wp_ajax_grt_toggle_favorite', [ $this, 'ajax_toggle_favorite' ] );
	}

	/**
	 * Register plugin admin menu.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'GRT Templates', 'grt-addon-for-elementor' ),
			__( 'GRT Templates', 'grt-addon-for-elementor' ),
			self::CAPABILITY,
			'grt-templates',
			[ $this, 'render_dashboard_page' ],
			'dashicons-layout',
			58
		);

		add_submenu_page(
			'grt-templates',
			__( 'Dashboard', 'grt-addon-for-elementor' ),
			__( 'Dashboard', 'grt-addon-for-elementor' ),
			self::CAPABILITY,
			'grt-templates',
			[ $this, 'render_dashboard_page' ]
		);

		add_submenu_page(
			'grt-templates',
			__( 'Template Library', 'grt-addon-for-elementor' ),
			__( 'Template Library', 'grt-addon-for-elementor' ),
			self::CAPABILITY,
			'grt-template-library',
			[ $this, 'render_template_library_page' ]
		);

		add_submenu_page(
			'grt-templates',
			__( 'Settings', 'grt-addon-for-elementor' ),
			__( 'Settings', 'grt-addon-for-elementor' ),
			self::CAPABILITY,
			'grt-template-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			API::OPTION_KEY,
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->api->get_default_settings(),
			]
		);

		add_settings_section(
			'grt_templates_remote_section',
			__( 'Remote Library', 'grt-addon-for-elementor' ),
			[ $this, 'render_remote_section_intro' ],
			'grt-template-settings'
		);

		add_settings_field(
			'remote_api_url',
			__( 'Remote API URL', 'grt-addon-for-elementor' ),
			[ $this, 'render_remote_api_field' ],
			'grt-template-settings',
			'grt_templates_remote_section'
		);

		add_settings_field(
			'enable_remote_templates',
			__( 'Enable Remote Templates', 'grt-addon-for-elementor' ),
			[ $this, 'render_remote_toggle_field' ],
			'grt-template-settings',
			'grt_templates_remote_section'
		);

		add_settings_field(
			'cache_duration',
			__( 'Cache Duration', 'grt-addon-for-elementor' ),
			[ $this, 'render_cache_duration_field' ],
			'grt-template-settings',
			'grt_templates_remote_section'
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$current  = $this->api->get_settings();
		$settings = $this->api->get_default_settings();

		$settings['remote_api_url']          = esc_url_raw( trim( (string) ( $input['remote_api_url'] ?? '' ) ) );
		$settings['enable_remote_templates'] = ! empty( $input['enable_remote_templates'] ) ? '1' : '0';
		$settings['cache_duration']          = max( 0, absint( $input['cache_duration'] ?? $settings['cache_duration'] ) );

		$this->api->clear_remote_cache_for_url( $current['remote_api_url'] ?? '' );
		$this->api->clear_remote_cache_for_url( $settings['remote_api_url'] );

		return $settings;
	}

	/**
	 * Section intro.
	 *
	 * @return void
	 */
	public function render_remote_section_intro() {
		echo '<p>' . esc_html__( 'Control the remote API endpoint, caching, and whether remote templates should appear beside your bundled templates.', 'grt-addon-for-elementor' ) . '</p>';
	}

	/**
	 * Render API URL field.
	 *
	 * @return void
	 */
	public function render_remote_api_field() {
		$settings = $this->api->get_settings();
		?>
		<input
			type="url"
			class="regular-text code"
			name="<?php echo esc_attr( API::OPTION_KEY ); ?>[remote_api_url]"
			value="<?php echo esc_attr( $settings['remote_api_url'] ); ?>"
			placeholder="https://yourdomain.com/templates/templates.json"
		/>
		<p class="description">
			<?php esc_html_e( 'Expected format: a JSON array where each item contains id, title, category, thumbnail, and json_url.', 'grt-addon-for-elementor' ); ?>
		</p>
		<?php
	}

	/**
	 * Render remote toggle field.
	 *
	 * @return void
	 */
	public function render_remote_toggle_field() {
		$settings = $this->api->get_settings();
		?>
		<label for="grt-enable-remote-templates">
			<input
				type="checkbox"
				id="grt-enable-remote-templates"
				name="<?php echo esc_attr( API::OPTION_KEY ); ?>[enable_remote_templates]"
				value="1"
				<?php checked( ! empty( $settings['enable_remote_templates'] ) ); ?>
			/>
			<?php esc_html_e( 'Load templates from the configured remote API endpoint.', 'grt-addon-for-elementor' ); ?>
		</label>
		<?php
	}

	/**
	 * Render cache duration field.
	 *
	 * @return void
	 */
	public function render_cache_duration_field() {
		$settings = $this->api->get_settings();
		?>
		<input
			type="number"
			min="0"
			step="1"
			class="small-text"
			name="<?php echo esc_attr( API::OPTION_KEY ); ?>[cache_duration]"
			value="<?php echo esc_attr( (string) $settings['cache_duration'] ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Duration in minutes. Use 0 to disable remote caching.', 'grt-addon-for-elementor' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'grt-addon-for-elementor-admin',
			GRT_ADDON_FOR_ELEMENTOR_URL . 'assets/css/admin.css',
			[],
			GRT_ADDON_FOR_ELEMENTOR_VERSION
		);

		wp_enqueue_script(
			'grt-addon-for-elementor-admin',
			GRT_ADDON_FOR_ELEMENTOR_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			GRT_ADDON_FOR_ELEMENTOR_VERSION,
			true
		);

		wp_localize_script(
			'grt-addon-for-elementor-admin',
			'GRTTemplatesAdmin',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'grt_template_library' ),
				'batchSize' => self::BATCH_SIZE,
				'strings'   => [
					'loadError'             => __( 'We could not load templates right now. Please try again.', 'grt-addon-for-elementor' ),
					'importError'           => __( 'The template could not be imported.', 'grt-addon-for-elementor' ),
					'previewUnavailable'    => __( 'Preview is not available for this template yet.', 'grt-addon-for-elementor' ),
					'loadMore'              => __( 'Load More', 'grt-addon-for-elementor' ),
					'loading'               => __( 'Loading templates...', 'grt-addon-for-elementor' ),
					'noTemplates'           => __( 'No templates matched your current filters.', 'grt-addon-for-elementor' ),
					'openPreview'           => __( 'Open preview in a new tab', 'grt-addon-for-elementor' ),
					'preview'               => __( 'Preview', 'grt-addon-for-elementor' ),
					'import'                => __( 'Import', 'grt-addon-for-elementor' ),
					'importing'             => __( 'Importing...', 'grt-addon-for-elementor' ),
					'favorite'              => __( 'Favorite template', 'grt-addon-for-elementor' ),
					'unfavorite'            => __( 'Remove from favorites', 'grt-addon-for-elementor' ),
					'pluginsRequiredPrefix' => __( 'Recommended plugins:', 'grt-addon-for-elementor' ),
					'missingPluginsConfirm' => __( 'This template recommends plugins that are not active yet. Continue with the import anyway?', 'grt-addon-for-elementor' ),
					'openInElementor'       => __( 'Open in Elementor', 'grt-addon-for-elementor' ),
				],
			]
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		$summary       = $this->api->get_summary_counts();
		$settings      = $this->api->get_settings();
		$remote_status = $this->api->get_remote_status();

		include GRT_ADDON_FOR_ELEMENTOR_PATH . 'views/admin-dashboard.php';
	}

	/**
	 * Render template library page.
	 *
	 * @return void
	 */
	public function render_template_library_page() {
		$categories   = $this->api->get_categories();
		$remote_error = $this->api->get_last_remote_error();

		include GRT_ADDON_FOR_ELEMENTOR_PATH . 'views/admin-template-library.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! empty( $_GET['grt-cache-cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag.
			add_settings_error(
				API::OPTION_KEY,
				'grt-cache-cleared',
				__( 'Remote template cache cleared.', 'grt-addon-for-elementor' ),
				'updated'
			);
		}

		include GRT_ADDON_FOR_ELEMENTOR_PATH . 'views/admin-settings.php';
	}

	/**
	 * Return template list for AJAX lazy loading.
	 *
	 * @return void
	 */
	public function ajax_get_templates() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to load templates.', 'grt-addon-for-elementor' ),
				],
				403
			);
		}

		check_ajax_referer( 'grt_template_library', 'nonce' );

		$batch = $this->api->get_template_batch(
			[
				'offset'         => absint( $_POST['offset'] ?? 0 ),
				'limit'          => absint( $_POST['limit'] ?? self::BATCH_SIZE ),
				'search'         => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
				'category'       => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
				'favorites_only' => ! empty( $_POST['favorites_only'] ),
			]
		);

		wp_send_json_success( $batch );
	}

	/**
	 * Toggle favorites over AJAX.
	 *
	 * @return void
	 */
	public function ajax_toggle_favorite() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to update favorites.', 'grt-addon-for-elementor' ),
				],
				403
			);
		}

		check_ajax_referer( 'grt_template_library', 'nonce' );

		$template_key = sanitize_text_field( wp_unslash( $_POST['template_key'] ?? '' ) );
		$template     = $this->api->get_template( $template_key );

		if ( ! $template ) {
			wp_send_json_error(
				[
					'message' => __( 'That template no longer exists.', 'grt-addon-for-elementor' ),
				],
				404
			);
		}

		$is_favorite = $this->api->toggle_favorite( $template_key );

		wp_send_json_success(
			[
				'is_favorite' => $is_favorite,
			]
		);
	}

	/**
	 * Clear cached remote templates.
	 *
	 * @return void
	 */
	public function clear_template_cache() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to clear the template cache.', 'grt-addon-for-elementor' ) );
		}

		check_admin_referer( 'grt_clear_template_cache' );

		$this->api->clear_remote_cache();

		wp_safe_redirect(
			add_query_arg(
				[
					'page'              => 'grt-template-settings',
					'grt-cache-cleared' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Check whether current admin page belongs to the plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_screen() {
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.

		return in_array( $page, [ 'grt-templates', 'grt-template-library', 'grt-template-settings' ], true );
	}
}
