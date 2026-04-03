<?php
/**
 * Plugin Name: GRT Addon For Elementor
 * Plugin URI:  https://example.com/
 * Description: Lightweight Elementor template library addon with local and remote JSON template imports.
 * Version:     1.0.0
 * Author:      GRT
 * Text Domain: grt-addon-for-elementor
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package GRTAddonForElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GRT_ADDON_FOR_ELEMENTOR_VERSION' ) ) {
	define( 'GRT_ADDON_FOR_ELEMENTOR_VERSION', '1.0.0' );
}

if ( ! defined( 'GRT_ADDON_FOR_ELEMENTOR_FILE' ) ) {
	define( 'GRT_ADDON_FOR_ELEMENTOR_FILE', __FILE__ );
}

if ( ! defined( 'GRT_ADDON_FOR_ELEMENTOR_PATH' ) ) {
	define( 'GRT_ADDON_FOR_ELEMENTOR_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'GRT_ADDON_FOR_ELEMENTOR_URL' ) ) {
	define( 'GRT_ADDON_FOR_ELEMENTOR_URL', plugin_dir_url( __FILE__ ) );
}

final class GRT_Addon_For_Elementor {

	/**
	 * Singleton instance.
	 *
	 * @var GRT_Addon_For_Elementor|null
	 */
	private static $instance = null;

	/**
	 * Elementor plugin file.
	 *
	 * @var string
	 */
	private $elementor_plugin = 'elementor/elementor.php';

	/**
	 * Get plugin instance.
	 *
	 * @return GRT_Addon_For_Elementor
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();

		register_activation_hook( GRT_ADDON_FOR_ELEMENTOR_FILE, [ __CLASS__, 'activate' ] );

		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'boot' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( GRT_ADDON_FOR_ELEMENTOR_FILE ), [ $this, 'add_action_links' ] );
	}

	/**
	 * Load plugin files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once GRT_ADDON_FOR_ELEMENTOR_PATH . 'includes/class-api.php';
		require_once GRT_ADDON_FOR_ELEMENTOR_PATH . 'includes/class-importer.php';
		require_once GRT_ADDON_FOR_ELEMENTOR_PATH . 'includes/class-admin.php';
	}

	/**
	 * Set default options on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		$api      = new \GRTAddonForElementor\API();
		$defaults = $api->get_default_settings();
		$current  = get_option( \GRTAddonForElementor\API::OPTION_KEY, [] );

		if ( ! is_array( $current ) ) {
			$current = [];
		}

		update_option( \GRTAddonForElementor\API::OPTION_KEY, wp_parse_args( $current, $defaults ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'grt-addon-for-elementor', false, dirname( plugin_basename( GRT_ADDON_FOR_ELEMENTOR_FILE ) ) . '/languages' );
	}

	/**
	 * Bootstrap plugin services.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! $this->is_elementor_active() ) {
			add_action( 'admin_notices', [ $this, 'render_elementor_notice' ] );
			return;
		}

		$api      = new \GRTAddonForElementor\API();
		$importer = new \GRTAddonForElementor\Importer( $api );

		new \GRTAddonForElementor\Admin( $api, $importer );
	}

	/**
	 * Check whether Elementor is loaded.
	 *
	 * @return bool
	 */
	private function is_elementor_active() {
		return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Check whether Elementor exists on disk.
	 *
	 * @return bool
	 */
	private function is_elementor_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		return isset( $plugins[ $this->elementor_plugin ] );
	}

	/**
	 * Render admin notice when Elementor is missing.
	 *
	 * @return void
	 */
	public function render_elementor_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$action_url   = $this->get_elementor_action_url();
		$button_label = $this->is_elementor_installed() ? __( 'Activate Elementor', 'grt-addon-for-elementor' ) : __( 'Install Elementor', 'grt-addon-for-elementor' );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'GRT Addon For Elementor', 'grt-addon-for-elementor' ); ?></strong>
				<?php esc_html_e( 'requires Elementor to be installed and active before the template library can run.', 'grt-addon-for-elementor' ); ?>
			</p>
			<?php if ( $action_url ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $action_url ); ?>">
						<?php echo esc_html( $button_label ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get Elementor action URL.
	 *
	 * @return string
	 */
	private function get_elementor_action_url() {
		if ( $this->is_elementor_installed() ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return '';
			}

			return wp_nonce_url(
				admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $this->elementor_plugin ) ),
				'activate-plugin_' . $this->elementor_plugin
			);
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			return '';
		}

		return wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ),
			'install-plugin_elementor'
		);
	}

	/**
	 * Add plugin action links.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function add_action_links( $links ) {
		if ( $this->is_elementor_active() ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( admin_url( 'admin.php?page=grt-template-settings' ) ),
					esc_html__( 'Settings', 'grt-addon-for-elementor' )
				)
			);
		}

		return $links;
	}
}

GRT_Addon_For_Elementor::instance();
