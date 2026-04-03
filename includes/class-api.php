<?php
/**
 * Template data API service.
 *
 * @package GRTAddonForElementor
 */

namespace GRTAddonForElementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GRT_ADDON_FOR_ELEMENTOR_VERSION' ) ) {
	define( 'GRT_ADDON_FOR_ELEMENTOR_VERSION', '1.0.0' );
}

if ( ! defined( 'GRT_ADDON_FOR_ELEMENTOR_PATH' ) ) {
	define( 'GRT_ADDON_FOR_ELEMENTOR_PATH', trailingslashit( dirname( __DIR__ ) ) );
}

if ( ! defined( 'GRT_ADDON_FOR_ELEMENTOR_URL' ) && function_exists( 'plugin_dir_url' ) ) {
	define( 'GRT_ADDON_FOR_ELEMENTOR_URL', plugin_dir_url( dirname( __DIR__ ) . '/grt-addon-for-elementor.php' ) );
}

class API {

	/**
	 * Settings option key.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'grt_templates_settings';

	/**
	 * Favorites user meta key.
	 *
	 * @var string
	 */
	public const FAVORITES_META_KEY = 'grt_template_favorites';

	/**
	 * Transient prefix.
	 *
	 * @var string
	 */
	private const REMOTE_CACHE_PREFIX = 'grt_remote_templates_';

	/**
	 * Cached plugin registry.
	 *
	 * @var array|null
	 */
	private $plugins_cache = null;

	/**
	 * Last remote fetch error.
	 *
	 * @var WP_Error|null
	 */
	private $last_remote_error = null;

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return [
			'remote_api_url'          => 'https://yourdomain.com/templates/templates.json',
			'enable_remote_templates' => '0',
			'cache_duration'          => 60,
		];
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		return wp_parse_args( $settings, $this->get_default_settings() );
	}

	/**
	 * Check whether remote templates are enabled.
	 *
	 * @return bool
	 */
	public function is_remote_enabled() {
		$settings = $this->get_settings();

		return ! empty( $settings['enable_remote_templates'] );
	}

	/**
	 * Get remote API URL.
	 *
	 * @return string
	 */
	public function get_remote_api_url() {
		$settings = $this->get_settings();

		return esc_url_raw( trim( (string) $settings['remote_api_url'] ) );
	}

	/**
	 * Get remote cache duration in minutes.
	 *
	 * @return int
	 */
	public function get_cache_duration() {
		$settings = $this->get_settings();

		return max( 0, absint( $settings['cache_duration'] ) );
	}

	/**
	 * Build a filtered template list.
	 *
	 * @param array $args Filter arguments.
	 * @return array
	 */
	public function get_templates( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'search'         => '',
				'category'       => '',
				'favorites_only' => false,
				'source'         => '',
			]
		);

		$templates       = array_merge( $this->get_local_templates(), $this->get_remote_templates() );
		$favorites       = $this->get_favorites();
		$search          = sanitize_text_field( $args['search'] );
		$category        = sanitize_text_field( $args['category'] );
		$source          = sanitize_key( $args['source'] );
		$favorites_only  = ! empty( $args['favorites_only'] );

		usort(
			$templates,
			static function ( $left, $right ) {
				if ( $left['source'] === $right['source'] ) {
					return strcasecmp( $left['title'], $right['title'] );
				}

				return 'local' === $left['source'] ? -1 : 1;
			}
		);

		$templates = array_filter(
			$templates,
			static function ( $template ) use ( $favorites, $favorites_only, $search, $category, $source ) {
				if ( $favorites_only && ! in_array( $template['key'], $favorites, true ) ) {
					return false;
				}

				if ( $category && strtolower( $template['category'] ) !== strtolower( $category ) ) {
					return false;
				}

				if ( $source && $template['source'] !== $source ) {
					return false;
				}

				if ( $search ) {
					$haystack = implode(
						' ',
						[
							$template['title'],
							$template['category'],
							$template['source_label'],
							$template['description'],
						]
					);

					if ( false === stripos( $haystack, $search ) ) {
						return false;
					}
				}

				return true;
			}
		);

		$templates = array_map(
			function ( $template ) use ( $favorites ) {
				$template['is_favorite'] = in_array( $template['key'], $favorites, true );
				return $template;
			},
			$templates
		);

		return array_values( $templates );
	}

	/**
	 * Get a lazy-loaded template batch.
	 *
	 * @param array $args Batch arguments.
	 * @return array
	 */
	public function get_template_batch( $args = [] ) {
		$offset = max( 0, absint( $args['offset'] ?? 0 ) );
		$limit  = max( 1, min( 50, absint( $args['limit'] ?? 10 ) ) );

		unset( $args['offset'], $args['limit'] );

		$templates = $this->get_templates( $args );
		$total     = count( $templates );
		$items     = array_slice( $templates, $offset, $limit );

		return [
			'items'       => array_values( $items ),
			'total'       => $total,
			'count'       => count( $items ),
			'offset'      => $offset,
			'next_offset' => $offset + count( $items ),
			'has_more'    => ( $offset + count( $items ) ) < $total,
		];
	}

	/**
	 * Get a single template by its key.
	 *
	 * @param string $key Template key.
	 * @return array|null
	 */
	public function get_template( $key ) {
		$key = sanitize_text_field( $key );

		foreach ( array_merge( $this->get_local_templates(), $this->get_remote_templates() ) as $template ) {
			if ( $template['key'] === $key ) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Get available template categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		$categories = [];

		foreach ( $this->get_templates() as $template ) {
			if ( ! empty( $template['category'] ) ) {
				$categories[] = $template['category'];
			}
		}

		$categories = array_unique( $categories );
		natcasesort( $categories );

		return array_values( $categories );
	}

	/**
	 * Get a summary count of template sources.
	 *
	 * @return array
	 */
	public function get_summary_counts() {
		$local  = count( $this->get_local_templates() );
		$remote = count( $this->get_remote_templates() );

		return [
			'local'  => $local,
			'remote' => $remote,
			'all'    => $local + $remote,
		];
	}

	/**
	 * Get remote status for the dashboard.
	 *
	 * @return array
	 */
	public function get_remote_status() {
		if ( ! $this->is_remote_enabled() ) {
			return [
				'state'   => 'neutral',
				'label'   => __( 'Remote library is currently disabled.', 'grt-addon-for-elementor' ),
				'details' => __( 'Enable it from the settings page when your API endpoint is ready.', 'grt-addon-for-elementor' ),
			];
		}

		$url = $this->get_remote_api_url();

		if ( empty( $url ) || false !== strpos( $url, 'yourdomain.com' ) ) {
			return [
				'state'   => 'warning',
				'label'   => __( 'Remote API URL still needs to be configured.', 'grt-addon-for-elementor' ),
				'details' => __( 'Add your production templates.json endpoint in GRT Templates > Settings.', 'grt-addon-for-elementor' ),
			];
		}

		$templates = $this->get_remote_templates();

		if ( $this->last_remote_error instanceof WP_Error ) {
			return [
				'state'   => 'error',
				'label'   => __( 'Remote library could not be loaded.', 'grt-addon-for-elementor' ),
				'details' => $this->last_remote_error->get_error_message(),
			];
		}

		return [
			'state'   => 'success',
			/* translators: %d: Number of templates. */
			'label'   => sprintf( _n( '%d remote template ready.', '%d remote templates ready.', count( $templates ), 'grt-addon-for-elementor' ), count( $templates ) ),
			'details' => $url,
		];
	}

	/**
	 * Get bundled local templates.
	 *
	 * @return array
	 */
	public function get_local_templates() {
		$templates_dir = trailingslashit( GRT_ADDON_FOR_ELEMENTOR_PATH . 'templates' );
		$files         = glob( $templates_dir . '*.json' );
		$templates     = [];

		if ( ! is_array( $files ) ) {
			return $templates;
		}

		foreach ( $files as $file ) {
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled local template files.

			if ( ! is_array( $data ) || empty( $data['title'] ) || empty( $data['content'] ) || ! is_array( $data['content'] ) ) {
				continue;
			}

			$id          = sanitize_title( $data['id'] ?? pathinfo( $file, PATHINFO_FILENAME ) );
			$thumbnail   = $this->normalize_local_asset_url( $data['grt_thumbnail'] ?? '' );
			$preview_url = $this->normalize_local_asset_url( $data['grt_preview_url'] ?? $thumbnail );

			$templates[] = $this->build_template_record(
				[
					'id'               => $id,
					'source'           => 'local',
					'title'            => sanitize_text_field( $data['title'] ),
					'category'         => sanitize_text_field( $data['grt_category'] ?? __( 'General', 'grt-addon-for-elementor' ) ),
					'description'      => sanitize_text_field( $data['grt_description'] ?? '' ),
					'thumbnail'        => $thumbnail,
					'preview_url'      => $preview_url,
					'demo_url'         => $preview_url,
					'json_url'         => '',
					'file_path'        => $file,
					'type'             => sanitize_key( $data['type'] ?? 'page' ),
					'required_plugins' => $data['grt_required_plugins'] ?? [],
				]
			);
		}

		return $templates;
	}

	/**
	 * Get cached remote templates.
	 *
	 * @return array
	 */
	public function get_remote_templates() {
		$this->last_remote_error = null;

		if ( ! $this->is_remote_enabled() ) {
			return [];
		}

		$url = $this->get_remote_api_url();

		if ( empty( $url ) ) {
			return [];
		}

		$cache_key = $this->get_remote_cache_key( $url );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 15,
				'redirection' => 3,
				'sslverify'   => true,
				'headers'     => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->last_remote_error = $response;
			return [];
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$this->last_remote_error = new WP_Error(
				'grt_remote_http_error',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'Remote endpoint returned HTTP %d.', 'grt-addon-for-elementor' ), $status_code )
			);

			return [];
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $payload['templates'] ) && is_array( $payload['templates'] ) ) {
			$payload = $payload['templates'];
		}

		if ( ! is_array( $payload ) ) {
			$this->last_remote_error = new WP_Error(
				'grt_remote_invalid_payload',
				__( 'Remote library response is not a valid JSON array.', 'grt-addon-for-elementor' )
			);

			return [];
		}

		$templates = [];

		foreach ( $payload as $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}

			$normalized = $this->normalize_remote_template( $template, $url );

			if ( $normalized ) {
				$templates[] = $normalized;
			}
		}

		if ( $this->get_cache_duration() > 0 ) {
			set_transient( $cache_key, $templates, MINUTE_IN_SECONDS * $this->get_cache_duration() );
		}

		return $templates;
	}

	/**
	 * Get the last remote error object.
	 *
	 * @return WP_Error|null
	 */
	public function get_last_remote_error() {
		return $this->last_remote_error;
	}

	/**
	 * Get favorite template keys.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_favorites( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return [];
		}

		$favorites = get_user_meta( $user_id, self::FAVORITES_META_KEY, true );

		if ( ! is_array( $favorites ) ) {
			return [];
		}

		$favorites = array_map( 'sanitize_text_field', $favorites );

		return array_values( array_unique( array_filter( $favorites ) ) );
	}

	/**
	 * Toggle a template favorite state.
	 *
	 * @param string $key Template key.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	public function toggle_favorite( $key, $user_id = 0 ) {
		$key     = sanitize_text_field( $key );
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $key || ! $user_id ) {
			return false;
		}

		$favorites = $this->get_favorites( $user_id );

		if ( in_array( $key, $favorites, true ) ) {
			$favorites = array_values( array_diff( $favorites, [ $key ] ) );
		} else {
			$favorites[] = $key;
		}

		update_user_meta( $user_id, self::FAVORITES_META_KEY, array_values( array_unique( $favorites ) ) );

		return in_array( $key, $favorites, true );
	}

	/**
	 * Clear remote cache using current settings URL.
	 *
	 * @return void
	 */
	public function clear_remote_cache() {
		$this->clear_remote_cache_for_url( $this->get_remote_api_url() );
	}

	/**
	 * Clear remote cache for a specific URL.
	 *
	 * @param string $url Remote URL.
	 * @return void
	 */
	public function clear_remote_cache_for_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );

		if ( $url ) {
			delete_transient( $this->get_remote_cache_key( $url ) );
		}
	}

	/**
	 * Normalize a remote template item.
	 *
	 * @param array  $template Template payload.
	 * @param string $base_url Source endpoint.
	 * @return array|null
	 */
	private function normalize_remote_template( $template, $base_url ) {
		$id = sanitize_title( $template['id'] ?? $template['slug'] ?? $template['title'] ?? '' );

		if ( ! $id ) {
			return null;
		}

		$json_url = $this->resolve_remote_url( $template['json_url'] ?? $template['template_url'] ?? '', $base_url );

		if ( ! $json_url ) {
			return null;
		}

		$thumbnail = $this->resolve_remote_url( $template['thumbnail'] ?? $template['thumbnail_url'] ?? $template['image'] ?? '', $base_url );
		$preview   = $this->resolve_remote_url( $template['preview_url'] ?? $template['demo_url'] ?? $thumbnail, $base_url );

		return $this->build_template_record(
			[
				'id'               => $id,
				'source'           => 'remote',
				'title'            => sanitize_text_field( $template['title'] ?? $id ),
				'category'         => sanitize_text_field( $template['category'] ?? __( 'Remote', 'grt-addon-for-elementor' ) ),
				'description'      => sanitize_text_field( $template['description'] ?? '' ),
				'thumbnail'        => $thumbnail,
				'preview_url'      => $preview,
				'demo_url'         => $this->resolve_remote_url( $template['demo_url'] ?? $preview, $base_url ),
				'json_url'         => $json_url,
				'file_path'        => '',
				'type'             => sanitize_key( $template['type'] ?? 'page' ),
				'required_plugins' => $template['required_plugins'] ?? [],
			]
		);
	}

	/**
	 * Build a normalized template record.
	 *
	 * @param array $template Raw template data.
	 * @return array
	 */
	private function build_template_record( $template ) {
		$source           = 'remote' === $template['source'] ? 'remote' : 'local';
		$required_plugins = $this->normalize_required_plugins( $template['required_plugins'] ?? [] );
		$missing_plugins  = array_values(
			array_filter(
				$required_plugins,
				static function ( $plugin ) {
					return empty( $plugin['active'] );
				}
			)
		);

		return [
			'key'              => $source . ':' . sanitize_key( $template['id'] ),
			'id'               => sanitize_key( $template['id'] ),
			'source'           => $source,
			'source_label'     => 'remote' === $source ? __( 'Remote', 'grt-addon-for-elementor' ) : __( 'Local', 'grt-addon-for-elementor' ),
			'title'            => sanitize_text_field( $template['title'] ),
			'category'         => sanitize_text_field( $template['category'] ),
			'description'      => sanitize_text_field( $template['description'] ?? '' ),
			'thumbnail'        => esc_url_raw( $template['thumbnail'] ?? '' ),
			'preview_url'      => esc_url_raw( $template['preview_url'] ?? '' ),
			'demo_url'         => esc_url_raw( $template['demo_url'] ?? '' ),
			'json_url'         => esc_url_raw( $template['json_url'] ?? '' ),
			'file_path'        => $template['file_path'] ?? '',
			'type'             => sanitize_key( $template['type'] ?? 'page' ),
			'required_plugins' => $required_plugins,
			'missing_plugins'  => $missing_plugins,
			'is_favorite'      => in_array( $source . ':' . sanitize_key( $template['id'] ), $this->get_favorites(), true ),
		];
	}

	/**
	 * Normalize a local asset URL.
	 *
	 * @param string $path Asset path.
	 * @return string
	 */
	private function normalize_local_asset_url( $path ) {
		$path = trim( (string) $path );

		if ( empty( $path ) ) {
			return '';
		}

		if ( 0 === strpos( $path, 'http://' ) || 0 === strpos( $path, 'https://' ) ) {
			return esc_url_raw( $path );
		}

		return esc_url_raw( GRT_ADDON_FOR_ELEMENTOR_URL . ltrim( $path, '/' ) );
	}

	/**
	 * Resolve a remote URL from an endpoint-relative path.
	 *
	 * @param string $url Relative or absolute URL.
	 * @param string $base_url Base endpoint URL.
	 * @return string
	 */
	private function resolve_remote_url( $url, $base_url ) {
		$url = trim( (string) $url );

		if ( empty( $url ) ) {
			return '';
		}

		if ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) {
			return esc_url_raw( $url );
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			return esc_url_raw( ( $scheme ? $scheme : 'https' ) . ':' . $url );
		}

		$parts = wp_parse_url( $base_url );

		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return '';
		}

		$base_path = '/';

		if ( ! empty( $parts['path'] ) ) {
			$path      = str_replace( '\\', '/', dirname( $parts['path'] ) );
			$base_path = trailingslashit( '.' === $path ? '/' : $path );
		}

		$resolved_path = 0 === strpos( $url, '/' ) ? $url : $base_path . ltrim( $url, '/' );

		return esc_url_raw( $parts['scheme'] . '://' . $parts['host'] . $resolved_path );
	}

	/**
	 * Normalize required plugin declarations.
	 *
	 * @param array $plugins Plugin declarations.
	 * @return array
	 */
	private function normalize_required_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return [];
		}

		$registry   = $this->get_plugins_registry();
		$normalized = [];

		foreach ( $plugins as $plugin ) {
			$slug        = '';
			$plugin_file = '';
			$name        = '';

			if ( is_string( $plugin ) ) {
				$raw         = sanitize_text_field( $plugin );
				$plugin_file = false !== strpos( $raw, '/' ) ? $raw : sanitize_key( $raw ) . '/' . sanitize_key( $raw ) . '.php';
				$slug        = sanitize_key( dirname( $plugin_file ) );
				$name        = $this->humanize_slug( $slug );
			} elseif ( is_array( $plugin ) ) {
				$slug        = sanitize_key( $plugin['slug'] ?? dirname( (string) ( $plugin['plugin_file'] ?? '' ) ) );
				$plugin_file = sanitize_text_field( $plugin['plugin_file'] ?? ( $slug ? $slug . '/' . $slug . '.php' : '' ) );
				$name        = sanitize_text_field( $plugin['name'] ?? $this->humanize_slug( $slug ) );
			}

			if ( ! $slug && $plugin_file ) {
				$slug = sanitize_key( dirname( $plugin_file ) );
			}

			if ( empty( $slug ) || empty( $plugin_file ) ) {
				continue;
			}

			$installed = isset( $registry[ $plugin_file ] );
			$active    = $installed && is_plugin_active( $plugin_file );

			$normalized[] = [
				'slug'        => $slug,
				'plugin_file' => $plugin_file,
				'name'        => $name ? $name : $this->humanize_slug( $slug ),
				'installed'   => $installed,
				'active'      => $active,
			];
		}

		return $normalized;
	}

	/**
	 * Get plugin registry.
	 *
	 * @return array
	 */
	private function get_plugins_registry() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( null === $this->plugins_cache ) {
			$this->plugins_cache = get_plugins();
		}

		return $this->plugins_cache;
	}

	/**
	 * Get remote cache key.
	 *
	 * @param string $url Remote endpoint URL.
	 * @return string
	 */
	private function get_remote_cache_key( $url ) {
		return self::REMOTE_CACHE_PREFIX . md5( esc_url_raw( $url ) );
	}

	/**
	 * Convert a slug into a label.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	private function humanize_slug( $slug ) {
		return ucwords( str_replace( [ '-', '_' ], ' ', sanitize_key( $slug ) ) );
	}
}
