<?php

/**
 * MonsterInsights Onboarding Class
 *
 * @package MonsterInsights
 * @author David Paternina
 */
class MonsterInsights_Onboarding {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public function register_routes() {
		$namespace = 'monsterinsights/v1';

		register_rest_route( $namespace, '/onboarding/settings', array(
			'args' => array(
				'onboarding_key' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function( $param ) {
						return ! empty( $param );
					},
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_info' ),
				'permission_callback' => array( $this, 'validate_onboarding_request' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'store_settings' ),
				'permission_callback' => array( $this, 'validate_onboarding_request' ),
			),
		) );

		register_rest_route(
			$namespace,
			'/onboarding/connect-url',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_connect_url' ),
				'permission_callback' => array( $this, 'validate_onboarding_request' ),
				'args'               => array(
					'license_key'     => array(
						'required'          => true,
						'type'             => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'onboarding_key' => array(
						'required'          => true,
						'type'             => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route( $namespace, '/onboarding/set-license-key', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'set_license_key' ),
			'permission_callback' => array( $this, 'validate_onboarding_request' ),
			'args' => array(
				'license_key' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'onboarding_key' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
		register_rest_route( $namespace, '/onboarding/delete-onboarding-key', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'delete_onboarding_key' ),
			'permission_callback' => array( $this, 'validate_onboarding_request' ),
			'args' => array(
				'onboarding_key' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
	}

	/**
	 * Validate onboarding request
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if validation succeeds, WP_Error otherwise.
	 */
	public function validate_onboarding_request( $request ) {
		// Validate the onboarding key for all requests.
		$provided_key = $request->get_param( 'onboarding_key' );
		$stored_key = get_transient( 'monsterinsights_onboarding_key' );
		if ( empty( $provided_key ) || false === $stored_key || ! hash_equals( $stored_key, $provided_key ) ) {
			return new WP_Error(
				'monsterinsights_invalid_key',
				'Invalid onboarding key',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get onboarding settings
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with settings data.
	 */
	public function get_info( $request ) {
		$auth = MonsterInsights()->auth;
		$is_network = is_network_admin();

		if ( ! function_exists( 'monsterinsights_get_addons' ) ) {
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/admin/pages/addons.php';
		}

		if ( ! class_exists( 'MonsterInsights_Rest_Routes' ) ) {
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/admin/routes.php';
		}

		if ( monsterinsights_is_pro_version() ) {
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'pro/includes/license.php';
			$licensing = new MonsterInsights_License();
		} else {
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'lite/includes/license-compat.php';
			$licensing = new MonsterInsights_License_Compat();
		}

		$rest_routes = new MonsterInsights_Rest_Routes();
		$is_file_edit_allowed = 1;
		// Determine whether file modifications are allowed.
		if ( function_exists( 'wp_is_file_mod_allowed' ) && ! wp_is_file_mod_allowed( 'monsterinsights_can_install' ) ) {
			$is_file_edit_allowed = 0;
		}
		$mi_info = array(
			'existing_settings'     => array(
				'site_type'             => monsterinsights_get_option( 'site_type', 'business' ),
				'extensions_of_files'   => monsterinsights_get_option( 'extensions_of_files' ),
				'affiliate_links'       => monsterinsights_get_option( 'affiliate_links' ),
				'view_reports'          => monsterinsights_get_option( 'view_reports' ),
				'automatic_updates'     => monsterinsights_get_option( 'automatic_updates' ),
				'anonymous_data'        => monsterinsights_get_option( 'anonymous_data' ),
				'verified_automatic'    => monsterinsights_get_option( 'verified_automatic' ),
			),
			'plugin_info'          => array(
				'plugin_version'       => MONSTERINSIGHTS_VERSION,
				'is_pro'               => monsterinsights_is_pro_version() ? 1 : 0,
				'installed_version'    => monsterinsights_get_option( 'installed_version' ),
				'installed_date'        => monsterinsights_get_option( 'installed_date' ),
				'site_license'  => array(
					'key'  => $licensing->get_site_license_key(),
					'type' => $licensing->get_site_license_type(),
				),
				'network_license' => array(
					'key'  => $licensing->get_network_license_key(),
					'type' => $licensing->get_network_license_type(),
				),
				'shareasale_id'         => monsterinsights_get_shareasale_id(),
				'shareasale_url'        => monsterinsights_get_shareasale_url( monsterinsights_get_shareasale_id(), '' ),
			),	
			'site_info'             => array(
				'admin_url'            => admin_url( 'admin-ajax.php' ),
				'site_url'             => get_site_url(),
				'roles'                => monsterinsights_get_roles(),
				'roles_manage_options' => monsterinsights_get_manage_options_roles(),
				'rest_url'             => rest_url( 'monsterinsights/v1' ),
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'locale'               => get_locale(),
				'allow_file_edit'      => $is_file_edit_allowed,
				'exit_url'             => add_query_arg( 'page', 'monsterinsights_reports', admin_url( 'admin.php' ) ),
			),	
			'plugins'                 => array(
				'addons' => $rest_routes->onboarding_get_addons(),
			),
			'auth'              => array(
				'is_authed'                           => $auth->is_authed() || $auth->is_network_authed(),
				'v4'                                  => $auth->get_v4_id(),
				'viewname'                            => $auth->get_viewname(),
				'manual_v4'                           => $auth->get_manual_v4_id(),
				'measurement_protocol_secret'         => $auth->get_measurement_protocol_secret(),
				'network_v4'                          => $auth->get_network_v4_id(),
				'network_viewname'                    => $auth->get_network_viewname(),
				'network_manual_v4'                   => $auth->get_network_manual_v4_id(),
				'network_measurement_protocol_secret' => $auth->get_network_measurement_protocol_secret(),
				'gtag_selector_tracking_tag'          => monsterinsights_get_option( 'gtag_selector_tracking_tag', '' ),
				'gtag_selector_tracking_mp'           => monsterinsights_get_option( 'gtag_selector_tracking_mp', '' ),
				'site_key'                            => $auth->get_key(),
				'network_key'                         => $auth->get_network_key(),
				'site_token'                          => $auth->get_token(),
				'network_token'                       => $auth->get_network_token(),
			),
		);

		return new WP_REST_Response( $mi_info, 200 );
	}

	/**
	 * Store onboarding settings
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response indicating success.
	 */
	public function store_settings( $request ) {
		$is_network = boolval( $request->get_param( 'is_network' ) );
		// Process settings
		$settings = $request->get_param( 'settings' );

		if ( ! empty( $settings ) ) {
			foreach ( $settings as $key => $value ) {
				monsterinsights_update_option( $key, $value );
			}
		}
		if ( $request->get_param('license') && class_exists( 'MonsterInsights' ) ) {
			$license = $request->get_param('license');
			$option                = $is_network ? MonsterInsights()->license->get_network_license() : MonsterInsights()->license->get_site_license();
			$option['key']         = $license['key'];
			$option['type']        = $license['type'];
			$option['is_expired']  = false;
			$option['is_disabled'] = false;
			$option['is_invalid']  = false;
			$option['is_agency']   = isset( $license['is_agency'] ) ? boolval( $license['is_agency'] ) : false;

			$is_network ? MonsterInsights()->license->set_network_license( $option ) : MonsterInsights()->license->set_site_license( $option );
			delete_transient( '_monsterinsights_addons' );
			monsterinsights_get_addons_data( $option['key'] );
		}

		// Process auth data from request, if present
		$auth = $request->get_param( 'auth' );
		if ( ! empty( $auth ) ) {
			if( isset( $auth['is_authed'] ) &&  true === $auth['is_authed'] ) {
				// If the user is authed, we probably have an existing key and token.
				$existing = $is_network ? MonsterInsights()->auth->get_network_analytics_profile() : MonsterInsights()->auth->get_analytics_profile();
				$auth['key']   = $existing['key'];
				$auth['token'] = $existing['token'];
			}
			$profile = array(
				'key'                         => $auth['key'],
				'token'                       => $auth['token'],
				'v4'                          => $auth['v4'],
				'viewname'                    => $auth['v4'],
				'a'                           => $auth['account_id'], // AccountID
				'w'                           => $auth['property_id'], // PropertyID
				'p'                           => $auth['view_id'],
				'siteurl'                     => home_url(),
				'neturl'                      => network_admin_url(),
				'measurement_protocol_secret' => $auth['measurement_protocol_secret'],
			);
			$is_network ? MonsterInsights()->auth->set_network_analytics_profile( $profile ) : MonsterInsights()->auth->set_analytics_profile( $profile );
		}
		$triggered_by_user = $request->get_param( 'triggered_by' );
		$can_install       = monsterinsights_can_install_plugins( $triggered_by_user );
		if ( $can_install && ! empty( $settings['addons_to_install'] ) ) {
			$plugins = $settings['addons_to_install'];
			
			foreach ( $plugins as $plugin ) {
				if ( ! isset( $plugin['slug'] ) || ! isset( $plugin['type'] ) || ! isset( $plugin['title'] ) ) {
					continue;
				}
				$installed = false;
				$plugin_type = $plugin['type'];
				switch ( $plugin_type ) {
					case 'wp_plugin':
						$installed = $this->install_plugin( $plugin['slug'] );
						break;
					case 'mkt_addon':
						$installed = $this->install_addon( $plugin['installation_path'] );
						break;
					case 'activation':
						activate_plugin( $plugin['installation_path'], '', $is_network, true );
						$activated_plugins[] = $plugin['title'];
						break;
				}
				if ( $installed ) {
					activate_plugin( $installed, '', $is_network, true );
					$activated_plugins[] = $plugin['title'];
				}
			}
			//Delete addons transient to update them to the latest status.
			delete_transient( '_monsterinsights_addons' );
		}
		return new WP_REST_Response( array(
			'success' => true
		), 200 );
	}

	/**
	 * Get connect URL for MonsterInsights authentication
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response with connect URL or error.
	 */
	public function get_connect_url( $request ) {
		if ( ! class_exists( 'MonsterInsights_Connect' ) ) {
			require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/connect.php';
		}

		$key = $request->get_param( 'license_key' );
		$network = is_network_admin();
		if ( empty( $key ) ) {
			return new WP_Error(
				'missing_key',
				esc_html__( 'Please enter your license key to connect.', 'google-analytics-for-wordpress' ),
				array( 'status' => 400 )
			);
		}
		$url_data = MonsterInsights_Connect::generate_connect_url_data( $key, $network );
		if ( empty( $url_data ) ) {
			return new WP_Error(
				'url_generation_failed',
				esc_html__( 'Failed to generate connect URL.', 'google-analytics-for-wordpress' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'url' => $url_data['url'],
			),
			200
		);
	}

	/**
	 * Install plugins which are not addons.
	 * 
	 * @since 9.5.0
	 * @param string $slug The slug of the plugin to install.
	 * @return string|false The basename of the installed plugin or false if installation fails.
	 */
	private function install_plugin( $slug ) {
		if ( ! $slug ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$api = plugins_api( 'plugin_information', array(
			'slug'   => $slug,
			'fields' => array(
				'short_description' => false,
				'sections'          => false,
				'requires'          => false,
				'rating'            => false,
				'ratings'           => false,
				'downloaded'        => false,
				'last_updated'      => false,
				'added'             => false,
				'tags'              => false,
				'compatibility'     => false,
				'homepage'          => false,
				'donate_link'       => false,
			),
		) );

		if ( is_wp_error( $api ) ) {
			return $api->get_error_message();
		}

		$download_url = $api->download_link;

		$method = '';
		$url    = add_query_arg(
			array(
				'page' => 'monsterinsights-settings',
			),
			admin_url( 'admin.php' )
		);
		$url    = esc_url( $url );

		$creds = request_filesystem_credentials( $url, $method, false, false, null );
		if ( false === $creds ) {
			return false;
		}

		if ( ! WP_Filesystem( $creds ) ) {
			return false;
		}

		// We do not need any extra credentials if we have gotten this far, so let's install the plugin.
		monsterinsights_require_upgrader();

		// Prevent language upgrade in ajax calls.
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );
		// Create the plugin upgrader with our custom skin.
		$installer = new MonsterInsights_Plugin_Upgrader( new MonsterInsights_Skin() );
		$installer->install( $download_url );

		// Flush the cache and return the newly installed plugin basename.
		if ( $installer->plugin_info() ) {
			wp_cache_flush();
			$plugin_basename = $installer->plugin_info();
			return $plugin_basename;
		}

		return false;
	}

	/**
	 * Installs a MonsterInsights addon.
	 *
	 * @param string $download_url The plugin download URL.
	 * @since 9.5.0
	 */
	private function install_addon( $download_url ) {
		// Install the addon.
		if ( isset( $download_url ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$method = '';
			$url    = add_query_arg(
				array(
					'page' => 'monsterinsights-settings',
				),
				admin_url( 'admin.php' )
			);
			$url    = esc_url( $url );

			$creds = request_filesystem_credentials( $url, $method, false, false, null );
			if ( false === $creds ) {
				return false;
			}

			if ( ! WP_Filesystem( $creds ) ) {
				return false;
			}
			monsterinsights_require_upgrader( false );

			// Create the plugin upgrader with our custom skin.
			$installer = new Plugin_Upgrader( $skin = new MonsterInsights_Skin() );
			$installer->install( $download_url );

			// Flush the cache and return the newly installed plugin basename.
			wp_cache_flush();
			if ( $installer->plugin_info() ) {
				$plugin_basename = $installer->plugin_info();
				return $plugin_basename;
			}
			return false;
		}
	}
	/**
	 * Set the license key if valid, if not return an error.
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response indicating success.
	 */
	public function set_license_key( $request ) {
		$license_key = $request->get_param( 'license_key' );
		$verify = monsterinsights_perform_remote_request( 'verify-key', array( 'tgm-updater-key' => $license_key ) );
		if ( ! $verify ) {
			return new WP_REST_Response(
				array(
					'error' => esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'google-analytics-for-wordpress' ),
				)
			);
		}

		if ( ! empty( $verify->error ) ) {
			return new WP_REST_Response(
				array(
					'error' => $verify->error,
				)
			);
		}
		return new WP_REST_Response(
			array(
				'success'   => true,
				'type'      => $verify->type,
				'is_agency' => $verify->is_agency,
			)
		);
	}

	/**
	 * Delete the onboarding key.
	 * 
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response indicating success.
	 */
	public function delete_onboarding_key( $request ) {
		delete_transient( 'monsterinsights_onboarding_key' );
		return new WP_REST_Response(
			array(
				'success' => true,
			), 200 );
	}
}

new MonsterInsights_Onboarding();