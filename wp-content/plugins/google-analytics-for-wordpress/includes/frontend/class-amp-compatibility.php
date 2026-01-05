<?php
/**
 * AMP Compatibility Handler for MonsterInsights
 * 
 * @package MonsterInsights
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MonsterInsights_AMP_Compatibility
 */
class MonsterInsights_AMP_Compatibility {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Only run if we're in AMP context
		if ( $this->is_amp() ) {
			$this->init();
		}
	}

	/**
	 * Initialize AMP compatibility
	 */
	private function init() {
		// Remove all MonsterInsights scripts and styles with highest priority
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_monsterinsights_assets' ), 999999 );
		
		// Remove MonsterInsights tracking scripts with highest priority
		add_action( 'wp_head', array( $this, 'remove_monsterinsights_tracking' ), 1 );
		add_action( 'wp_footer', array( $this, 'remove_monsterinsights_tracking' ), 1 );
		
		// Intercept specific hooks that MonsterInsights uses
		$this->intercept_monsterinsights_hooks();
		
		// Remove any remaining scripts from output
		add_action( 'wp_print_scripts', array( $this, 'remove_remaining_scripts' ), 999999 );
		add_action( 'wp_print_footer_scripts', array( $this, 'remove_remaining_scripts' ), 999999 );
	}

	/**
	 * Intercept specific MonsterInsights hooks
	 */
	private function intercept_monsterinsights_hooks() {
		// Remove the specific hook that's causing the problem
		remove_action( 'cmplz_before_statistics_script', 'monsterinsights_tracking_script', 10 );
		
		// Remove other potential MonsterInsights hooks
		remove_action( 'wp_head', 'monsterinsights_tracking_script' );
		remove_action( 'wp_footer', 'monsterinsights_tracking_script' );
		
		// Remove any gtag scripts
		remove_action( 'wp_head', array( $this, 'remove_gtag_scripts' ) );
		add_action( 'wp_head', array( $this, 'remove_gtag_scripts' ), 1 );
		
		// Remove any remaining MonsterInsights output
		add_filter( 'monsterinsights_tracking_script', '__return_false', 999999 );
		add_filter( 'monsterinsights_frontend_tracking_options', '__return_false', 999999 );
	}

	/**
	 * Remove gtag scripts from head
	 */
	public function remove_gtag_scripts() {
		// Remove any gtag scripts that might be added
		remove_action( 'wp_head', array( $this, 'output_gtag_script' ) );
		remove_action( 'wp_footer', array( $this, 'output_gtag_script' ) );
	}

	/**
	 * Remove all MonsterInsights assets
	 */
	public function dequeue_monsterinsights_assets() {
		// Remove all MonsterInsights scripts
		wp_dequeue_script( 'monsterinsights-frontend-script' );
		wp_dequeue_script( 'monsterinsights-frontend-script-js' );
		wp_dequeue_script( 'monsterinsights-gtag' );
		wp_dequeue_script( 'monsterinsights-dual-tracking' );
		
		// Remove all MonsterInsights styles
		wp_dequeue_style( 'monsterinsights-frontend' );
		wp_dequeue_style( 'monsterinsights-admin' );
		
		// Remove any other MonsterInsights assets
		global $wp_scripts, $wp_styles;
		
		if ( isset( $wp_scripts->registered ) ) {
			foreach ( $wp_scripts->registered as $handle => $script ) {
				if ( strpos( $handle, 'monsterinsights' ) !== false ) {
					wp_dequeue_script( $handle );
				}
			}
		}
		
		if ( isset( $wp_styles->registered ) ) {
			foreach ( $wp_styles->registered as $handle => $style ) {
				if ( strpos( $handle, 'monsterinsights' ) !== false ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}

	/**
	 * Remove MonsterInsights tracking from head and footer
	 */
	public function remove_monsterinsights_tracking() {
		// Remove any inline scripts that MonsterInsights might add
		ob_start();
		// This will capture any output and prevent it from being displayed
	}

	/**
	 * Remove any remaining scripts
	 */
	public function remove_remaining_scripts() {
		// Remove any remaining MonsterInsights scripts
		global $wp_scripts;
		
		if ( isset( $wp_scripts->queue ) ) {
			foreach ( $wp_scripts->queue as $handle ) {
				if ( strpos( $handle, 'monsterinsights' ) !== false ) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	/**
	 * Check if we are in an AMP context
	 *
	 * @return bool
	 */
	private function is_amp() {
		// Check for AMP plugin
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return true;
		}
		
		// Check for AMP theme
		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			return true;
		}
		
		// Check for AMP query parameter
		if ( isset( $_GET['amp'] ) && $_GET['amp'] === '1' ) {
			return true;
		}
		
		// Check for AMP in URL path
		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], '/amp/' ) ) {
			return true;
		}
		
		// Check for AMP in theme
		if ( function_exists( 'amp_is_canonical' ) && amp_is_canonical() ) {
			return true;
		}
		
		return false;
	}
}

// Initialize the class
new MonsterInsights_AMP_Compatibility();
