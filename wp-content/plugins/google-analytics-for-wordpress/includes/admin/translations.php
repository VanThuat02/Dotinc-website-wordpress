<?php
/**
 * Translation download process.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MonsterInsights_Translation {

	private $remote_cache_key = 'monsterinsights_t15s_remote_data';

	private $api_url = 'https://cdn.get.mi-translations.com/%s/packages.json';

	/**
	 * @var array
	 */
	private $available_languages;

	/**
	 * @var array
	 */
	private $installed_translations;

	/**
	 * @var Automatic_Upgrader_Skin
	 */
	private $skin;

	/**
	 * @var Language_Pack_Upgrader
	 */
	private $upgrader;

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! $this->has_access() ) {
			return;
		}

		global $pagenow;

		if ( $pagenow === 'update-core.php' ) {
			// Clear cache for translations.
			add_action( 'set_site_transient_update_plugins', array( $this, 'clear_translations_cache' ) );

			// Add translations to the list of available for download.
			add_filter( 'site_transient_update_plugins', array( $this, 'register_t15s_translations' ), 20, 1 );
		}

		add_action( 'monsterinsights_plugin_activated', array( $this, 'plugin_activated' ) );

		// Remove translation cache for a plugin on deactivation.
		// Translation removal is handled on plugin removal by WordPress.
		add_action( 'monsterinsights_plugin_deactivated', [ $this, 'clear_translations_cache' ] );
	}

	/**
	 * Check for user or WP has enough access.
	 *
	 * @return bool
	 */
	private function has_access() {

		if ( ! is_admin() ) {
			return false;
		}

		if ( ! current_user_can( 'install_languages' ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		return wp_can_install_language_pack();
	}

	/**
	 * Register all translations from our Translations endpoint.
	 *
	 * @param object|mixed $value Value of the `update_plugins` transient option.
	 *
	 * @return stdClass
	 */
	public function register_t15s_translations( $value ) {

		if ( ! is_object( $value ) ) {
			$value = new stdClass();
		}

		if ( ! isset( $value->translations ) ) {
			$value->translations = [];
		}

		$slug = monsterinsights_is_pro_version() ? 'google-analytics-premium' : 'google-analytics-for-wordpress';

		$translations = $this->get_translations( $slug );

		if ( empty( $translations['translations'] ) ) {
			return $value;
		}

		// Remove WP predefined translation URL for our plugin, So that we can use our own.
		foreach ( $value->translations as $key => $wp_translation ) {
			if ( $wp_translation['slug'] === $slug ) {
				// Remove item and re-index array.
				array_splice( $value->translations, $key, 1 );
			}
		}

		foreach ( $translations['translations'] as $translation ) {
			$value->translations[] = $translation;
		}

		return $value;
	}

	/**
	 * Get a list of needed translations for the plugin.
	 *
	 * @since 1.6.5
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array
	 */
	private function get_translations( $slug ) {

		$translations           = $this->get_available_translations( $slug );
		$available_languages    = $this->get_available_languages();
		$installed_translations = $this->get_installed_translations();

		foreach ( $translations['translations'] as $key => $translation ) {
			if ( empty( $translation['language'] ) || ! in_array( $translation['language'], $available_languages, true ) ) {
				unset( $translations['translations'][ $key ] );
			}

			// Skip languages which were updated locally.
			if ( isset( $installed_translations[ $slug ][ $translation['language'] ]['PO-Revision-Date'], $translation['updated'] ) ) {
				$local  = strtotime( $installed_translations[ $slug ][ $translation['language'] ]['PO-Revision-Date'] );
				$remote = strtotime( $translation['updated'] );

				if ( $local >= $remote ) {
					unset( $translations['translations'][ $key ] );
				}
			}
		}

		return $translations;
	}

	/**
	 * Get all available translations for the plugin.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array Translation data.
	 */
	private function get_available_translations( $slug ) {
		$translations = get_site_transient( $this->remote_cache_key );

		if ( $translations !== false ) {
			return $translations;
		}

		$translations = json_decode(
			wp_remote_retrieve_body(
				wp_remote_get(
					sprintf( $this->api_url, $slug ),
					[
						'timeout' => 3,
					]
				)
			),
			true
		);

		if ( ! is_array( $translations ) || empty( $translations['translations'] ) ) {
			$translations = [ 'translations' => [] ];
		}

		// Convert translations from API to a WordPress standard.
		foreach ( $translations['translations'] as $key => $translation ) {
			$translations['translations'][ $key ]['type'] = 'plugin';
			$translations['translations'][ $key ]['slug'] = $slug;
			$translations['translations'][ $key ]['autoupdate'] = true;

			if ( isset( $translations['translations'][ $key ]['iso'] ) ) {
				unset( $translations['translations'][ $key ]['iso'] );
			}
		}

		set_site_transient( $this->remote_cache_key, $translations );

		return $translations;
	}

	/**
	 * Get available languages.
	 *
	 * @return array
	 */
	private function get_available_languages() {

		if ( $this->available_languages ) {
			return $this->available_languages;
		}

		$this->available_languages = get_available_languages();

		return $this->available_languages;
	}

	/**
	 * Get installed translations.
	 *
	 * @return array
	 */
	private function get_installed_translations() {

		if ( $this->installed_translations ) {
			return $this->installed_translations;
		}

		$this->installed_translations = wp_get_installed_translations( 'plugins' );

		return $this->installed_translations;
	}

	/**
	 * Clear existing translation cache.
	 */
	public function clear_translations_cache() {
		delete_site_transient( $this->remote_cache_key );
	}

	/**
	 * Trigger on plugin activate.
	 */
	public function plugin_activated() {
		$slug = monsterinsights_is_pro_version() ? 'google-analytics-premium' : 'google-analytics-for-wordpress';

		$translations = $this->get_translations( $slug );

		if ( empty( $translations['translations'] ) ) {
			return;
		}

		$this->download_plugin_translations( $slug, $translations['translations'] );
	}

	/**
	 * Download translations for the plugin.
	 *
	 * @since 1.6.5
	 *
	 * @param string $slug         Slug of plugin.
	 * @param array  $translations List of available translations.
	 */
	private function download_plugin_translations( $slug, $translations ) {

		$this->load_download_requirements();

		$available_translations = $this->get_available_plugin_translations( $translations, $slug );

		foreach ( $available_translations as $language ) {
			if ( ! is_object( $language ) ) {
				$language = (object) $language;
			}

			$this->skin->language_update = $language;

			$this->upgrader->run(
				[
					'package'                     => $language->package,
					'destination'                 => WP_LANG_DIR . '/plugins',
					'abort_if_destination_exists' => false,
					'is_multi'                    => true,
					'hook_extra'                  => [
						'language_update_type' => $language->type,
						'language_update'      => $language,
					],
				]
			);
		}
	}

	/**
	 * Load required libraries.
	 */
	private function load_download_requirements() {
		$this->skin     = new Automatic_Upgrader_Skin();
		$this->upgrader = new Language_Pack_Upgrader( $this->skin );
	}

	/**
	 * Get available translations for the plugin.
	 *
	 * @param array  $translations List of translations.
	 * @param string $slug         Plugin slug.
	 *
	 * @return array
	 */
	private function get_available_plugin_translations( $translations, $slug ) {
		$available_languages = $this->get_available_languages();

		if ( empty( $available_languages ) ) {
			return [];
		}

		foreach ( $translations as $key => $language ) {
			if ( ! is_object( $language ) ) {
				$language = (object) $language;
			}

			if (
				( ! property_exists( $language, 'slug' ) || ! property_exists( $language, 'language' ) ) ||
				$slug !== $language->slug ||
				! in_array( $language->language, $available_languages, true )
			) {
				unset( $translations[ $key ] );
			}
		}

		return $translations;
	}
}

$monsterinsights_translation = new MonsterInsights_Translation();
