<?php

/**
 * MonsterInsights Charitable Notice Class
 *
 * @package MonsterInsights
 */
class MonsterInsights_Charitable_Notice {

	/**
	 * Cron key
	 *
	 * @var string
	 */
	private $cron_key = 'monsterinsights_charitable_notice_cron';

	/**
	 * Option key
	 *
	 * @var string
	 */
	private $option_key = 'show_charitable_notice';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'schedule_monthly_update' ) );

		add_filter( 'cron_schedules', array( $this, 'add_intervals' ) );

		add_action( $this->cron_key, array( $this, 'check_charitablewp_notice' ) );

		add_action( 'wp_ajax_monsterinsights_dismiss_charitablewp_notice', array( $this, 'dismiss_charitablewp_notice' ) );
	}

	/**
	 * This schedules the monthly event with the first one in 1 day from the current time.
	 */
	public function schedule_monthly_update() {
		if ( ! wp_next_scheduled( $this->cron_key ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'monsterinsights_monthly', $this->cron_key );
		}
	}

	/**
	 * Cron handler that checks if the CharitableWP notice should be shown.
	 */
	public function check_charitablewp_notice() {
		$show_notice = monsterinsights_get_option( $this->option_key, false );

		// If user has dismissed the notice, return.
		if ( 'dismissed' === $show_notice ) {
			return;
		}

		$api_options = array(
			'start' => date( 'Y-m-d', strtotime( '-30 days' ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			'end' => date( 'Y-m-d', strtotime( 'yesterday' ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		);

		// If user has not dismissed the notice, send request to the server to check if the notice should be shown.
		$api = new MonsterInsights_API_Request( 'analytics/reports/engagement-pages/', $api_options, 'GET' );
		$result = $api->request();

		if ( is_wp_error( $result ) ) {
			return;
		}

		if ( isset( $result['data'] ) && $result['data']['pages_report_table'] ) {
			if ( $this->has_charitable_page( $result['data']['pages_report_table'] ) ) {
				monsterinsights_update_option( $this->option_key, 'show' );
				return;
			}
		}
	}

	/**
	 * Add monthly interval to the cron schedules.
	 */
	public function add_intervals($schedules) {
		// Add a 'monthly' interval.
		$schedules['monsterinsights_monthly'] = array(
			'interval' => MONTH_IN_SECONDS,
			'display'  => 'Once a month'
		);
		return $schedules;
	}

	/**
	 * Dismiss the CharitableWP notice.
	 */
	public function dismiss_charitablewp_notice() {
		// Check if user has permission to dismiss the notice.
		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json_error();
		}

		// Verify nonce.
		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		monsterinsights_update_option( $this->option_key, 'dismissed' );

		wp_send_json_success();
	}

	/**
	 * Check if the CharitableWP page is in the pages report table.
	 */
	public function has_charitable_page( $pages_report_table ) {
		$charitable_paths = array(
			// Donate family
			'donate', 'donation', 'donations', 'donate-now', 'make-a-donation', 'make-a-gift',
			// Giving family
			'give', 'giving', 'online-giving', 'ways-to-give',
			// Support family
			'support-us', 'support-our-cause', 'ways-to-support',
			// Fundraising family
			'fundraise', 'fundraiser', 'fundraising', 'campaign', 'campaigns',
			// Post-Donation / Confirmation Pages
			'donation-confirmation', 'donation-success', 'thank-you-for-donating', 'thank-you-for-your-donation', 'donation-receipt',
		);

		$escaped = array_map( function( $slug ) { return preg_quote( $slug, '#' ); }, $charitable_paths );
		$alternation = implode( '|', $escaped );
		$pattern = '#(?:^|/)(' . $alternation . ')(?:/|$)#i';

		foreach ( $pages_report_table as $row ) {
			if ( empty( $row['page_path'] ) ) {
				continue;
			}

			if ( empty( $row['page_path'][0] ) ) {
				continue;
			}

			$raw_path = $row['page_path'][0];
			$path_only = parse_url( $raw_path, PHP_URL_PATH );
			$path = is_string( $path_only ) ? $path_only : $raw_path;

			if ( preg_match( $pattern, $path ) ) {
				return true;
			}
		}

		return false;
	}
}

new MonsterInsights_Charitable_Notice();
