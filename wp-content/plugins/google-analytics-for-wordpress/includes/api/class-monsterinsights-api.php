<?php
/**
 * Base API Client class for MonsterInsights.
 *
 *
 * @package MonsterInsights
 */

abstract class MonsterInsights_API_Client {
	/**
	 * Base API URL.
	 *
	 * @var string
	 */
	protected $base_url = '';

	/**
	 * API token.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * API key.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * License key.
	 *
	 * @var string
	 */
	protected $license;

	/**
	 * Site URL.
	 *
	 * @var string
	 */
	protected $site_url;

	/**
	 * MonsterInsights version.
	 *
	 * @var string
	 */
	protected $miversion;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token     = $this->get_token();
		$this->key       = $this->get_key();
		$this->license   = $this->get_license();
		$this->site_url  = $this->get_site_url();
		$this->miversion = MONSTERINSIGHTS_VERSION;
	}

	/**
	 * Get the API token.
	 *
	 * @return string
	 */
	protected function get_token() {
		return is_network_admin() ? MonsterInsights()->auth->get_network_token() : MonsterInsights()->auth->get_token();
	}

	/**
	 * Get the API key.
	 *
	 * @return string
	 */
	protected function get_key() {
		return is_network_admin() ? MonsterInsights()->auth->get_network_key() : MonsterInsights()->auth->get_key();
	}

	/**
	 * Get the license key.
	 *
	 * @return string
	 */
	protected function get_license() {
		if ( ! monsterinsights_is_pro_version() ) {
			return '';
		}

		return is_network_admin() ? MonsterInsights()->license->get_network_license_key() : MonsterInsights()->license->get_site_license_key();
	}

	/**
	 * Get the site URL.
	 *
	 * @return string
	 */
	protected function get_site_url() {
		return is_network_admin() ? network_admin_url() : home_url();
	}

	/**
	 * Get common request parameters.
	 *
	 * @return array
	 */
	protected function get_common_params() {
		$params = array(
			'token'     => $this->token,
			'key'       => $this->key,
			'miversion' => $this->miversion,
			'site_url'  => $this->site_url,
		);

		if ( ! empty( $this->license ) ) {
			$params['license'] = $this->license;
		}

		return $params;
	}

	/**
	 * Make an API request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $params   The request parameters.
	 * @param string $method   The request method.
	 *
	 * @return array|WP_Error
	 */
	protected function request( $endpoint, $params = array(), $method = 'POST' ) {
		$url = apply_filters( 'monsterinsights_api_url', trailingslashit( $this->base_url ) . $endpoint );
		$params = array_merge( $this->get_common_params(), $params );

		$args = array(
			'method'      => $method,
			'timeout'     => 3,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'MIAPI-Sender'  => 'WordPress',
				'MIAPI-Referer' => $this->site_url,
			),
			'body'        => $params,
			'cookies'     => array(),
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		// Accept any 2xx code as success
		if ( $response_code >= 200 && $response_code < 300 ) {
			return $decoded_body;
		}

		// If the response is not valid JSON or doesn't have an error structure, create a generic error array
		if ( ! is_array( $decoded_body ) || ! isset( $decoded_body['error'] ) ) {
			$decoded_body = array(
				'error' => array(
					'code'    => 'monsterinsights_api_unexpected_response',
					'message' => 'Unexpected API response.',
					'details' => array(
						'code'     => $response_code,
						'body'     => $response_body,
						'endpoint' => $endpoint,
					),
				),
			);
		}

		return new MonsterInsights_API_Error( $decoded_body );
	}
}