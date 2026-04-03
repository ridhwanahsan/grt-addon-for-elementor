<?php
/**
 * Elementor import service.
 *
 * @package GRTAddonForElementor
 */

namespace GRTAddonForElementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Importer {

	/**
	 * Template data API.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param API $api Template API service.
	 */
	public function __construct( API $api ) {
		$this->api = $api;

		add_action( 'wp_ajax_grt_import_template', [ $this, 'ajax_import_template' ] );
	}

	/**
	 * AJAX import handler.
	 *
	 * @return void
	 */
	public function ajax_import_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to import templates.', 'grt-addon-for-elementor' ),
				],
				403
			);
		}

		check_ajax_referer( 'grt_template_library', 'nonce' );

		$template_key = sanitize_text_field( wp_unslash( $_POST['template_key'] ?? '' ) );
		$force        = ! empty( $_POST['force'] );
		$result       = $this->import_template( $template_key, $force );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message'         => $result->get_error_message(),
					'missing_plugins' => $result->get_error_data()['missing_plugins'] ?? [],
				],
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Import a template into Elementor library.
	 *
	 * @param string $template_key Template key.
	 * @param bool   $force        Skip recommended plugin warning.
	 * @return array|WP_Error
	 */
	public function import_template( $template_key, $force = false ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error(
				'grt_missing_elementor',
				__( 'Elementor must be active before importing templates.', 'grt-addon-for-elementor' )
			);
		}

		$template = $this->api->get_template( $template_key );

		if ( ! $template ) {
			return new WP_Error(
				'grt_missing_template',
				__( 'The requested template could not be found.', 'grt-addon-for-elementor' )
			);
		}

		if ( ! $force && ! empty( $template['missing_plugins'] ) ) {
			return new WP_Error(
				'grt_missing_required_plugins',
				__( 'This template recommends plugins that are not active yet.', 'grt-addon-for-elementor' ),
				[
					'missing_plugins' => $template['missing_plugins'],
				]
			);
		}

		$json = $this->get_template_json( $template );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$upload = [
			'fileName'    => sanitize_file_name( $template['id'] . '.json' ),
			'fileData'    => base64_encode( $json ),
			'source'      => 'local',
			'import_mode' => 'match_site',
		];

		$result = \Elementor\Plugin::$instance->templates_manager->import_template( $upload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$item        = is_array( $result ) && ! empty( $result[0] ) ? $result[0] : [];
		$template_id = absint( $item['template_id'] ?? 0 );
		$edit_url    = '';

		if ( $template_id ) {
			$document = \Elementor\Plugin::$instance->documents->get( $template_id );
			$edit_url = $document ? $document->get_edit_url() : get_edit_post_link( $template_id, 'raw' );
		}

		return [
			'message'      => __( 'Template imported successfully.', 'grt-addon-for-elementor' ),
			'imported'     => $item,
			'template_id'  => $template_id,
			'edit_url'     => $edit_url ? esc_url_raw( $edit_url ) : '',
			'source_title' => $template['title'],
		];
	}

	/**
	 * Get raw JSON for a local or remote template.
	 *
	 * @param array $template Normalized template.
	 * @return string|WP_Error
	 */
	private function get_template_json( $template ) {
		if ( 'local' === $template['source'] ) {
			if ( empty( $template['file_path'] ) || ! file_exists( $template['file_path'] ) ) {
				return new WP_Error(
					'grt_missing_local_file',
					__( 'Local template file is missing.', 'grt-addon-for-elementor' )
				);
			}

			$json = (string) file_get_contents( $template['file_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading bundled local template files.
		} else {
			$response = wp_remote_get(
				$template['json_url'],
				[
					'timeout'   => 20,
					'sslverify' => true,
					'headers'   => [
						'Accept' => 'application/json',
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );

			if ( 200 !== $status_code ) {
				return new WP_Error(
					'grt_remote_template_http_error',
					/* translators: %d: HTTP status code. */
					sprintf( __( 'Remote template returned HTTP %d.', 'grt-addon-for-elementor' ), $status_code )
				);
			}

			$json = wp_remote_retrieve_body( $response );
		}

		if ( ! $this->is_valid_template_json( $json ) ) {
			return new WP_Error(
				'grt_invalid_template_json',
				__( 'The template JSON is invalid or unsupported.', 'grt-addon-for-elementor' )
			);
		}

		return $json;
	}

	/**
	 * Basic Elementor JSON validation.
	 *
	 * @param string $json Template JSON.
	 * @return bool
	 */
	private function is_valid_template_json( $json ) {
		if ( empty( $json ) || ! is_string( $json ) ) {
			return false;
		}

		$data = json_decode( $json, true );

		return is_array( $data ) && ! empty( $data['content'] ) && is_array( $data['content'] );
	}
}
