<?php

/**
 * Email Summaries main class.
 *
 * Handles the generation and sending of monthly email summaries containing
 * analytics data and insights for the website.
 *
 * @package MonsterInsights
 * @subpackage Emails
 * @since 8.19.0
 */
class MonsterInsights_Email_Summaries {
	/**
	 * The source URL for the email summaries.
	 * This will be used to compute the assets URL for the email summaries.
	 *
	 * @since 9.4.0
	 * @access public
	 * @var string
	 */
	const SOURCE_URL = 'https://plugin-cdn.monsterinsights.com';

	/**
	 * The email template slug used for regular summary emails.
	 *
	 * @since 8.19.0
	 * @access private
	 * @var string
	 */
	private $email_template = 'summaries';

	/**
	 * The email template slug used for test summary emails.
	 *
	 * @since 8.19.0
	 * @access private
	 * @var string
	 */
	private $test_email_template = 'summaries-test';

	/**
	 * Stores email configuration options.
	 *
	 * Contains settings like enabled status, HTML template preference,
	 * carbon copy settings, recipient addresses, and header image.
	 *
	 * @since 8.19.0
	 * @access private
	 * @var array
	 */
	private $email_options;

	/**
	 * Start date for summaries.
	 *
	 * @since 8.19.0
	 * @access private
	 * @var string
	 */
	private $summary_start_date;

	/**
	 * End date for summaries.
	 *
	 * @since 8.19.0
	 * @access private
	 * @var string
	 */
	private $summary_end_date;

	/**
	 * Whether email summaries are enabled.
	 *
	 * @since 9.4.0
	 * @access private
	 * @var boolean
	 */
	private $is_enabled;

	/**
	 * Initialize the email summaries functionality.
	 *
	 * Sets up options, hooks, and schedules the monthly cron job
	 * for sending summary emails.
	 *
	 * @since 8.19.0
	 */
	public function __construct() {
		$options                              = array();
		$email_summaries                      = monsterinsights_get_option( 'email_summaries', 'on' );
		$options['email_summaries']           = $email_summaries;
		$options['summaries_html_template']   = monsterinsights_get_option( 'summaries_html_template', 'yes' );
		$options['summaries_carbon_copy']     = 'no';
		$options['summaries_email_addresses'] = array(get_option('admin_email'));
		$options['summaries_header_image']    = false;

		$this->email_options = $options;
		$this->hooks();

		// Remove weekly cron job.
		wp_clear_scheduled_hook( 'monsterinsights_email_summaries_weekly' );

		// Schedule or clear Monthly cron job.
		if ( ! empty( $email_summaries ) && 'on' !== $email_summaries && wp_next_scheduled( 'monsterinsights_email_summaries_cron' ) ) {
			wp_clear_scheduled_hook( 'monsterinsights_email_summaries_cron' );
		}

		if ( ! empty( $email_summaries ) && 'on' === $email_summaries && ! wp_next_scheduled( 'monsterinsights_email_summaries_cron' ) ) {
			wp_schedule_event( $this->get_first_cron_date(), 'monsterinsights_email_summaries_monthly', 'monsterinsights_email_summaries_cron' );
		}

		$this->summary_start_date = $this->get_summaries_start_date();
		$this->summary_end_date   = $this->get_summaries_end_date();
	}

	/**
	 * Register hooks and callbacks for email summaries.
	 *
	 * Sets up admin scripts, preview functionality, template paths,
	 * cron schedules and other required hooks.
	 *
	 * @since 8.19.0
	 * @access public
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		if ( ! empty( $this->email_options['email_summaries'] ) && 'on' === $this->email_options['email_summaries'] ) {
			add_action( 'init', array( $this, 'preview' ) );
			add_filter( 'monsterinsights_email_template_paths', array( $this, 'add_email_template_path' ) );
			add_filter( 'monsterinsights_emails_templates_set_initial_args', array( $this, 'set_template_args' ) );
			add_filter( 'cron_schedules', array( $this, 'add_monthly_cron_schedule' ) );
			add_action( 'monsterinsights_email_summaries_cron', array( $this, 'cron' ) );
			add_action( 'wp_ajax_monsterinsights_send_test_email', array( $this, 'send_test_email' ) );
			add_action( 'monsterinsights_after_update_settings', array(
				$this,
				'reset_email_summaries_options'
			), 10, 2 );
		}

	}

	/**
	 * Enqueue required admin scripts for email summaries.
	 *
	 * Loads the WordPress media uploader scripts when on the settings page.
	 *
	 * @since 8.19.0
	 * @access public
	 */
	public function admin_scripts() {
		if ( monsterinsights_is_settings_page() ) {
			// This will load the required dependencies for the WordPress media uploader
			wp_enqueue_media();
		}
	}

	/**
	 * Check if email summaries functionality is enabled.
	 *
	 * Verifies that summaries are enabled in settings and all required
	 * data and recipients are configured.
	 *
	 * @since 8.19.0
	 * @access protected
	 *
	 * @return boolean True if email summaries are enabled and properly configured.
	 */
	protected function is_enabled() {
		if ( ! isset( $this->is_enabled ) ) {
			$this->is_enabled = false;

			if ( ! $this->is_preview() ) {

				$info_block      = new MonsterInsights_Summaries_InfoBlocks();
				$info_block      = $info_block->fetch_data();
				$email_addresses = $this->get_email_addresses();

				if ( ! empty( $info_block ) ) {
					if ( 'on' === $this->email_options['email_summaries'] && ! empty( $email_addresses ) && true === $info_block['status'] ) {
						$this->is_enabled = true;
					}
				}
			}
		}

		return apply_filters( 'monsterinsights_emails_summaries_is_enabled', $this->is_enabled );
	}

	/**
	 * Preview the email summary template.
	 *
	 * Generates and displays a preview of the email summary template for administrators.
	 * Handles both HTML and plain text template formats.
	 *
	 * @since 8.19.0
	 * @access public
	 */
	public function preview() {

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}

		if ( ! $this->is_preview() ) {
			return;
		}

		// initiate email class.
		$emails = new MonsterInsights_WP_Emails( $this->email_template );

		// check if html template option is enabled
		if ( ! $this->is_enabled_html_template() ) {
			$emails->__set( 'html', false );
		}

		$content = $emails->build_email();

		if ( ! $this->is_enabled_html_template() ) {
			$content = wpautop( $content );
		}

		echo $content; // phpcs:ignore

		exit;
	}

	/**
	 * Check if the current request is for an email preview.
	 *
	 * Determines if the current page request is for previewing an email summary template.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return boolean True if current request is for email preview.
	 */
	public function is_preview() {
		if ( isset( $_GET['monsterinsights_email_preview'], $_GET['monsterinsights_email_template'] ) && 'summary' === $_GET['monsterinsights_email_template'] ) { // phpcs:ignore
			return true;
		}

		return false;
	}

	/**
	 * Get the email header logo.
	 *
	 * @return string The email header logo.
	 * @since 8.19.0
	 */
	public function get_header_logo() {
		$default = self::SOURCE_URL . '/assets/img/logo-monsterinsights-white.png';

		return apply_filters( 'monsterinsights_email_header_logo', $default );
	}

	/**
	 * Get the email header image.
	 * This is deprecated and will be removed in a future version.
	 *
	 * @return string The email from address.
	 * @since 8.19.0
	 * @deprecated 9.4.0 Use {@see 'monsterinsights_email_header_logo'} instead.
	 *
	 */
	public function get_header_image() {
		$new_logo = $this->get_header_logo();

		// keep this for backwards compatibility.
		$img = array(
			'url' => $new_logo,
			'2x'  => $new_logo,
		);

		if ( ! empty( $this->email_options['summaries_header_image'] ) ) {
			$img['url'] = $this->email_options['summaries_header_image'];
			$img['2x']  = '';
		}

		/**
		 * Filters the logo image for the email header.
		 *
		 * @since 8.19.0
		 * @deprecated 9.4.0 Use {@see 'monsterinsights_email_header_logo'} instead.
		 *
		 * @param array $img The email header logo.
		 */
		$old_value = apply_filters_deprecated(
			'monsterinsights_email_header_image',
			$img,
			'9.4.0',
			'monsterinsights_email_header_logo',
			__( 'This filter is deprecated. Use monsterinsights_email_header_logo instead.' )
		);

		return $old_value;
	}

	/**
	 * Calculate the first cron execution date.
	 *
	 * Generates a random future date for the first email summary cron job.
	 * Randomizes day, hour, minute and second to distribute load.
	 *
	 * @since 8.19.0
	 * @access protected
	 *
	 * @return int Unix timestamp for first cron execution.
	 */
	protected function get_first_cron_date() {
		$schedule           = array();
		$schedule['day']    = wp_rand( 0, 1 );
		$schedule['hour']   = wp_rand( 0, 23 );
		$schedule['minute'] = wp_rand( 0, 59 );
		$schedule['second'] = wp_rand( 0, 59 );
		$schedule['offset'] = ( $schedule['day'] * DAY_IN_SECONDS ) +
							  ( $schedule['hour'] * HOUR_IN_SECONDS ) +
							  ( $schedule['minute'] * MINUTE_IN_SECONDS ) +
							  $schedule['second'];
		$date               = strtotime( 'next saturday' ) + $schedule['offset'];

		return $date;
	}

	/**
	 * Add the email templates directory path.
	 *
	 * Adds the plugin's email templates directory to the template search path.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @param array $file_paths Existing template file paths.
	 * @return array Modified template file paths.
	 */
	public function add_email_template_path( $file_paths ) {
		$file_paths['1000'] = MONSTERINSIGHTS_PLUGIN_DIR . 'lite/includes/emails/templates';

		return $file_paths;
	}

	/**
	 * Register monthly cron schedule.
	 *
	 * Adds a monthly interval to WordPress cron schedules for email summaries.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @param array $schedules WordPress cron schedules.
	 * @return array Modified cron schedules with monthly interval added.
	 */
	public function add_monthly_cron_schedule( $schedules ) {
		$schedules['monsterinsights_email_summaries_monthly'] = array(
			'interval' => MONTH_IN_SECONDS,
			'display'  => esc_html__( 'Monthly MonsterInsights Email Summaries', 'google-analytics-for-wordpress' ),
		);

		return $schedules;
	}

	/**
	 * Get the email subject line.
	 *
	 * Generates the email subject line including the site domain name.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return string Formatted email subject line.
	 */
	public function get_email_subject() {
		$site_url        = get_site_url();
		$site_url_parsed = parse_url( $site_url );// Can't use wp_parse_url as that was added in WP 4.4 and we still support 3.8.
		$site_url        = isset( $site_url_parsed['host'] ) ? $site_url_parsed['host'] : $site_url;

		// Translators: The domain of the site is appended to the subject.
		$subject = sprintf( __( 'MonsterInsights Summary - %s', 'google-analytics-for-wordpress' ), $site_url );

		return apply_filters( 'monsterinsights_emails_summaries_cron_subject', $subject );
	}

	/**
	 * Get configured email recipient addresses.
	 *
	 * Retrieves the list of email addresses that should receive the summary emails.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return array List of recipient email addresses.
	 */
	public function get_email_addresses() {
		$emails = $this->email_options['summaries_email_addresses'];
		return apply_filters( 'monsterinsights_email_addresses_to_send', $emails );
	}

	/**
	 * Check if carbon copy (CC) is enabled for email summaries.
	 *
	 * Determines if recipients should be CC'd on the email summaries
	 * rather than being added as individual recipients.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return boolean True if CC is enabled, false otherwise.
	 */
	public function is_cc_enabled() {
		$value = false;
		if ( 'yes' === $this->email_options['summaries_carbon_copy'] ) {
			$value = true;
		}

		return apply_filters( 'monsterinsights_email_cc_enabled', $value, $this );
	}

	/**
	 * Check if HTML email template is enabled.
	 *
	 * Determines if email summaries should be sent in HTML format
	 * rather than plain text.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return boolean True if HTML template is enabled, false otherwise.
	 */
	public function is_enabled_html_template() {
		$value = true;
		if ( 'no' === $this->email_options['summaries_html_template'] ) {
			$value = false;
		}

		return apply_filters( 'monsterinsights_email_html_template', $value, $this );
	}

	/**
	 * Process and send the scheduled email summary.
	 *
	 * Handles the monthly cron job that generates and sends email summaries.
	 * Checks authentication status and processes data before sending.
	 *
	 * @since 8.19.0
	 * @access public
	 */
	public function cron() {

		if ( ! $this->is_enabled() ) {
			return;
		}

		if( !monsterinsights_is_authed() ){
			return;
		}

		$email            = array();
		$email['subject'] = $this->get_email_subject();
		$email['address'] = $this->get_email_addresses();
		$email['address'] = array_map( 'sanitize_email', $email['address'] );

		// Create new email.
		$emails = new MonsterInsights_WP_Emails( $this->email_template );

		// Maybe include CC.
		if ( $this->is_cc_enabled() ) {
			$emails->__set( 'cc', implode( ',', $this->get_email_addresses() ) );
		}

		// check if html template option is enabled
		if ( ! $this->is_enabled_html_template() ) {
			$emails->__set( 'html', false );
		}

		$info_blocks = new MonsterInsights_Summaries_InfoBlocks();
		$next_block  = $info_blocks->get_next();

		// Go.
		if( !empty( $email['address'] ) ){
			foreach ( $email['address'] as $address ) {
				$sent = $emails->send( trim( $address ), $email['subject'] );

				if ( true === $sent && ! empty( $next_block ) ) {
					$info_blocks->register_sent( $next_block );
				}
			}
		}
	}

	/**
	 * Send a test email summary.
	 *
	 * Handles AJAX request to send a test email summary to verify configuration.
	 * Requires admin capabilities and nonce verification.
	 *
	 * @since 8.19.0
	 * @access public
	 */
	public function send_test_email() {
		// Run a security check first.
		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}

		$email            = array();
		$email['subject'] = '[Test email] MonsterInsights Summary';
		$email['address'] = $this->get_email_addresses();
		$email['address'] = array_map( 'sanitize_email', $email['address'] );

		// Create new email.
		$emails = new MonsterInsights_WP_Emails( $this->test_email_template );

		// Maybe include CC.
		if ( $this->is_cc_enabled() ) {
			$emails->__set( 'cc', implode( ',', $this->get_email_addresses() ) );
		}

		// check if html template option is enabled
		if ( ! $this->is_enabled_html_template() ) {
			$emails->__set( 'html', false );
		}

		// Go.
		if(!empty($email['address'])){
			foreach ( $email['address'] as $address ) {
				if ( ! $emails->send( trim( $address ), $email['subject'] ) ) {
					wp_send_json_error();
				}
			}
		}
		wp_send_json_success();
	}

	/**
	 * Set up email template arguments.
	 *
	 * Prepares all the data and configuration needed for the email template,
	 * including header images, summaries data, and report features.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @param array $args Existing template arguments.
	 * @return array Modified template arguments with summary data.
	 */
	public function set_template_args( $args ) {
		$summaries_data = $this->get_summaries(); // Get full summaries data

		$args['header']['start_date']  = date( "F j, Y", strtotime( $this->summary_start_date ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We want this to depend on the runtime timezone.
		$args['header']['end_date']    = date( "F j, Y", strtotime( $this->summary_end_date ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We want this to depend on the runtime timezone.
		$args['header']['assets_url']  = self::SOURCE_URL;
		$args['header']['reports_url'] = admin_url( 'admin.php?page=monsterinsights_reports' );
		$args['header']['logo_link']   = monsterinsights_get_url( 'email-summary', 'email-header-logo', 'https://www.monsterinsights.com/' );
		$args['body']['startDate']     = $this->get_summaries_start_date();
		$args['body']['endDate']       = $this->get_summaries_end_date();
		$args['body']['reports_url']   = admin_url( 'admin.php?page=monsterinsights_reports' );

		if ( ! isset( $summaries_data['success'] ) || true !== $summaries_data['success'] || ! isset( $summaries_data['data'] ) ) {
			return $args; // Return early if summaries data is not successful
		}

		$summaries_data                   = $summaries_data['data']; // Use only the 'data' part for simplified access
		$args['body']['top_pages']        = $this->get_top_pages_from_summary_data( $summaries_data );
		$args['body']['more_pages_url']   = $this->get_more_pages_report_link( $summaries_data );
		$args['body']['update_available'] = $this->plugin_has_update_notice();

		$args['footer']['settings_tab_url'] = esc_url( admin_url( 'admin.php?page=monsterinsights_settings#/advanced' ) );

		$info_blocks   = new MonsterInsights_Summaries_InfoBlocks();
		$next_block    = $info_blocks->get_next();
		$default_block = $info_blocks->get_default_block_data();
		// A default url for the blog posts source.
		$args['body']['blog_posts_url'] = 'https://www.monsterinsights.com/blog/';

		if ( ! empty( $default_block['blog_posts_source'] ) ) {
			$args['body']['blog_posts_url']     = $default_block['blog_posts_source'];
		}

		$args['body']['blog_posts']         = $this->get_latest_blog_posts_from_feed( $default_block );
		$args['body']['report_title']       = $next_block['title'];
		$args['body']['report_description'] = $next_block['html'];
		$args['body']['report_link']        = $next_block['link_url'];
		$args['body']['report_button_text'] = $next_block['link_text'];
		$args['body']['report_features']    = $this->get_report_features( $next_block );
		$args['body']['report_stats']       = $this->get_report_stats( $next_block, $summaries_data );

		if ( $this->is_enabled_html_template() ) {
			$args['header']['header_image']      = $this->get_header_logo();
			$args['header']['header_background'] = self::SOURCE_URL . '/assets/img/header-background-monsterinsights.png';
			$args['body']['report_image_src']    = $next_block['featured_image'];
			$args['footer']['left_image']        = self::SOURCE_URL . '/assets/img/logo-monsterinsights-long.png';
			$args['footer']['logo_image']        = self::SOURCE_URL . '/assets/img/logo-monsterinsights-small.png';
		}

		$args['footer']['facebook_url'] = 'https://www.facebook.com/monsterinsights';
		$args['footer']['linkedin_url'] = 'https://www.linkedin.com/company/awesome-motive-inc./';
		$args['footer']['logo_link']    = monsterinsights_get_url( 'email-summary', 'email-footer-logo', 'https://www.monsterinsights.com/' );

		return apply_filters( 'monsterinsights_email_summaries_template_args', $args );
	}

	/**
	 * Get the start date for the summary period.
	 *
	 * Returns the first day of the previous month as the start date
	 * for gathering analytics data.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return string Date in Y-m-d format.
	 */
	public function get_summaries_start_date() {
		if ( ! isset( $this->summary_start_date ) ) {
			$this->summary_start_date = date( 'Y-m-d', strtotime( 'first day of last month' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We want this to depend on the runtime timezone.
		}

		return $this->summary_start_date;
	}

	/**
	 * Get the end date for the summary period.
	 *
	 * Returns the last day of the previous month as the end date
	 * for gathering analytics data.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return string Date in Y-m-d format.
	 */
	public function get_summaries_end_date() {
		if ( ! isset( $this->summary_end_date ) ) {
			$this->summary_end_date = date( 'Y-m-d', strtotime( 'last day of last month' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We want this to depend on the runtime timezone.
		}

		return $this->summary_end_date;
	}

	/**
	 * Retrieve analytics data for email summaries.
	 *
	 * Fetches and processes analytics report data for the summary period.
	 * Handles permissions and data formatting.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @return array {
	 *     Analytics data array.
	 *
	 *     @type boolean $success Whether data was retrieved successfully.
	 *     @type array   $data    The analytics data for the summary.
	 * }
	 */
	public function get_summaries() {
		$data = array();

		// get overview report data for email summaries template
		$report_name = 'summaries';
		$report      = MonsterInsights()->reporting->get_report( $report_name );

		if ( is_wp_error( $report ) ) {
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => $report->get_error_message(),
			);
		}

		$isnetwork = ! empty( $_REQUEST['isnetwork'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['isnetwork'] ) ) : '';

		// get the data of last month
		$args = array(
			'start' => $this->summary_start_date,
			'end'   => $this->summary_end_date,
		);

		if ( $isnetwork ) {
			$args['network'] = true;
		}

		if ( monsterinsights_is_pro_version() && ! MonsterInsights()->license->license_can( $report->level ) ) {
			$data = array(
				'success' => false,
				'message' => __( "You don't have permission to view MonsterInsights reports.", 'google-analytics-for-wordpress' ),
			);
		} else {
			$data = apply_filters( 'monsterinsights_vue_reports_data', $report->get_data( $args ), $report_name, $report );
		}

		return $data;
	}

	/**
	 * Get the list of report features.
	 *
	 * Returns either default features or remote data features if available.
	 *
	 * @since 9.4.0
	 * @access private
	 *
	 * @param array $remote_data Remote feature data if available.
	 * @return array List of report features.
	 */
	private function get_report_features( $remote_data ) {
		$default_features = array(
			__( 'Overall Score', 'google-analytics-for-wordpress' ),
			__( 'Total Blocking Time', 'google-analytics-for-wordpress' ),
			__( 'Time to Interactive', 'google-analytics-for-wordpress' ),
			__( 'First Contentful Paint', 'google-analytics-for-wordpress' ),
			__( 'Benchmarks', 'google-analytics-for-wordpress' ),
			__( 'Server Response Time', 'google-analytics-for-wordpress' )
		);

		if ( ! empty( $remote_data ) && ! empty( $remote_data['features'] ) ) {
			return $remote_data['features'];
		}

		return $default_features;
	}

	/**
	 * Check if a plugin update is available.
	 *
	 * Verifies if there's a new version of the plugin available for update.
	 *
	 * @since 8.19.0
	 * @access private
	 *
	 * @param boolean $force_update Whether to force a check with WordPress.org.
	 * @return boolean True if an update is available, false otherwise.
	 */
	private function plugin_has_update_notice( $force_update = false ) {
		$plugin_slug = plugin_basename( MonsterInsights_Lite::get_instance()->file );

		if ( $force_update === true ) {
			wp_update_plugins();
		}

		// Get the update_plugins transient
		$update_plugins = get_site_transient('update_plugins');

		if (isset($update_plugins->response[$plugin_slug])) {
			// There is a new version available
			return true;
		}
	
		// No new version available
		return false;
	}

	/**
	 * Get the report stats.
	 *
	 * Returns an array of report stats.
	 *
	 * @since 9.4.0
	 * @access private
	 * 
	 * @param array $data Analytics data array.
	 * @param array $summaries_data Summaries data array.
	 * @return array Report stats array.
	 */
	private function get_report_stats( $data, $summaries_data ) {
		// Icons are stored as unicode characters in the summaries assets. Check the plugin.monsterinsights.com documentation for more info.
		$stats = array(
			'sessions'     => array(
				'label' => __( 'Number of Sessions', 'google-analytics-for-wordpress' ),
				'icon'  => '',
			),
			'views'        => array(
				'label' => __( 'Number of Page Views', 'google-analytics-for-wordpress' ),
				'icon'  => '',
			),
			'avg_duration' => array(
				'label' => __( 'Avg Session Duration', 'google-analytics-for-wordpress' ),
				'icon'  => '',
			),
			'bounce_rate'  => array(
				'label' => __( 'Bounce Rate', 'google-analytics-for-wordpress' ),
				'icon'  => '',
			),
			'nr_of_posts'  => array(
				'label' => __( 'Number of Blog Posts', 'google-analytics-for-wordpress' ),
				'icon'  => '',
			),
			'nr_of_pages'  => array(
				'label' => __( 'Number of Pages', 'google-analytics-for-wordpress' ),
				'icon'  => '',
			)
		);

		$infobox = array();

		if ( ! empty( $summaries_data ) && isset( $summaries_data['infobox'] ) ) {
			$infobox = $summaries_data['infobox'];
		}

		foreach ( $stats as $key => $stat ) {
			// Add default values for stats
			$new_value = array(
				'value'        => 0,
				'difference'   => 0,
				'trend_icon'   => '↑',
				'trend_class'  => 'mset-text-increase',
			);

			switch ( $key ) {
				case 'sessions':
					if ( ! empty( $infobox ) && isset( $infobox['sessions'] ) ) {
						$value     = $this->roundThousandsNumber( $infobox['sessions']['value'] );
						$new_value = $this->calculate_trend_data( $stat, $value, $infobox['sessions']['prev'] );
					}
					break;
				case 'views':
					if ( ! empty( $infobox ) && isset( $infobox['pageviews'] ) ) {
						$value     = $this->roundThousandsNumber( $infobox['pageviews']['value'] );
						$new_value = $this->calculate_trend_data( $stat, $value, $infobox['pageviews']['prev'] );
					}

					break;
				case 'nr_of_posts':
					$current_count = $this->get_posts_count_by_date_range();
					$prev_count    = $this->get_posts_count_by_date_range( 'post', 'first day of -2 months', 'last day of -2 months' );
					$difference    = $this->calculate_percentage_difference( $current_count, $prev_count );
					$new_value     = $this->calculate_trend_data( $stat, $current_count, $difference );
					break;
				case 'nr_of_pages':
					$current_count = $this->get_posts_count_by_date_range( 'page' );
					$prev_count    = $this->get_posts_count_by_date_range( 'page', 'first day of -2 months', 'last day of -2 months' );
					$difference    = $this->calculate_percentage_difference( $current_count, $prev_count );
					$new_value     = $this->calculate_trend_data( $stat, $current_count, $difference );
					break;
				case 'avg_duration':
					if ( ! empty( $infobox ) && isset( $infobox['duration'] ) ) {
						$value     = $infobox['duration']['value'];
						$new_value = $this->calculate_trend_data( $stat, $value, $infobox['duration']['prev'] );
					}
					break;
				case 'bounce_rate':
					if ( ! empty( $infobox ) && isset( $infobox['bounceRate'] ) ) {
						$value     = number_format( (float)$infobox['bounceRate']['value'], 2 ) . '%';
						$new_value = $this->calculate_trend_data( $stat, $value, $infobox['bounceRate']['prev'], true );
					}
					break;
			}

			$stats[$key] = array_merge($new_value, $stat);
		}

		return $stats;
	}

	/**
	 * Calculate trend data for a metric
	 *
	 * @since 9.4.0
	 * @access private
	 * 
	 * @param array $stat The stat array to modify.
	 * @param int   $current_count Current period value. 
	 * @param int   $difference    Percentage difference.
	 * @param bool  $inverse       Whether to invert the trend.
	 * @return array Trend data including difference, class and icon
	 */
	private function calculate_trend_data( $stat, $current_count, $difference, $inverse = false ) {
		$is_increase = $difference >= 0;

		$stat['difference']  = round( $difference );
		$stat['trend_icon']  = $is_increase ? '↑' : '↓';

		// Invert the trend color only if the inverse parameter is true. For example, a higher bounce rate is not a great thing, so we invert the color.
		if ( $inverse ) {
			$is_increase = ! $is_increase;
		}

		$stat['trend_class'] = $is_increase ? 'mset-text-increase' : 'mset-text-decrease';
		$stat['value']       = $current_count;

		return $stat;
	}

	/**
	 * Extract top 5 pages from summaries data.
	 *
	 * @since 9.4.0
	 * @access private
	 *
	 * @param array $summaries_data Summaries data array.
	 * @return array Top 5 pages data.
	 */
	private function get_top_pages_from_summary_data( $summaries_data ) {
		if ( ! is_array( $summaries_data ) || ! isset( $summaries_data['toppages'] ) ) {
			return array(); // Return empty array if data is invalid or missing
		}

		$top_pages_data = $summaries_data['toppages'];
		$top_pages = array();
		$count     = 0;

		foreach ( $top_pages_data as $page ) {
			if ( $count >= 5 ) {
				break; // Limit to top 5 pages
			}
			$top_pages[] = $page;
			$count++;
		}

		return $top_pages;
	}

	/**
	 * Get the "View All Report" link for top pages.
	 *
	 * @since 9.4.0
	 * @access private
	 *
	 * @return string URL for the full Top Pages report.
	 */
	private function get_more_pages_report_link( $summaries_data ) {
		// Use the direct link to the Top Posts report from the API data if available.
		return isset( $summaries_data['galinks']['topposts'] ) ? esc_url_raw( $summaries_data['galinks']['topposts'] ) : admin_url( 'admin.php?page=monsterinsights_reports' );
	}

	/**
	 * Reset email summaries options to defaults.
	 *
	 * Resets email addresses and header image when summaries are disabled.
	 *
	 * @since 8.19.0
	 * @access public
	 *
	 * @param string $key   The option key being updated.
	 * @param mixed  $value The new value for the option.
	 */
	public function reset_email_summaries_options( $key, $value ) {
		if ( isset( $key ) && $key === 'email_summaries' && isset( $value ) && $value === 'off' ) {
			$default_email = array(
				'email' => get_option( 'admin_email' ),
			);
			monsterinsights_update_option( 'summaries_email_addresses', array( $default_email ) );
			monsterinsights_update_option( 'summaries_header_image', '' );
		}
	}

	/**
	 * Get the count of posts or pages within a specified date range.
	 *
	 * @access private
	 * @since 9.4.0
	 *
	 * @param string $post_type  Post type to count ('post' or 'page'). Default is 'post'.
	 * @param string $start_date Start date for the range. Default is 'first day of this month'.
	 * @param string $end_date   End date for the range. Default is 'last day of this month'.
	 *
	 * @return int Number of posts or pages in the given date range.
	 */
	private function get_posts_count_by_date_range( $post_type = 'post', $start_date = 'first day of this month', $end_date = 'last day of this month' ) {
		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'date_query'     => array(
				array(
					'after'     => date( 'Y-m-d H:i:s', strtotime( $start_date ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We want this to depend on the runtime timezone.
					'before'    => date( 'Y-m-d H:i:s', strtotime( $end_date ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We want this to depend on the runtime timezone.
					'inclusive' => true,
				),
			),
			'posts_per_page' => - 1, // Get all posts in the date range
			'no_found_rows'  => true, // Optimize query performance
		);

		$query = new WP_Query( $query_args );
		return $query->post_count;
	}

	/**
	 * Calculate the percentage difference between two numbers.
	 *
	 * @since 9.4.0
	 * @access private
	 *
	 * @param int $current_count Current count.
	 * @param int $prev_count    Previous count.
	 *
	 * @return float Percentage difference.
	 */
	private function calculate_percentage_difference( $current_count, $prev_count ) {
		if ( ! $prev_count ) {
			return 0; // Avoid division by zero
		}
		return ( ( $current_count - $prev_count ) / $prev_count ) * 100;
	}

	/**
	 * Get the latest blog posts from the MonsterInsights RSS feed.
	 *
	 * Fetches the RSS feed from monsterinsights.com and extracts the titles and links
	 * of the last 3 blog posts.
	 *
	 * @since 9.4.0
	 * @access private
	 *
	 * @return array Array of the latest 3 blog posts with title and link, or empty array on failure.
	 */
	private function get_latest_blog_posts_from_feed( $info_block ) {
		$source = 'https://monsterinsights.com';

		if ( ! empty( $info_block['blog_posts_source'] ) ) {
			$source = $info_block['blog_posts_source'];
		}

		$rest_url = $source . '/wp-json/wp/v2/posts?per_page=3&_embed'; // Fetch last 3 posts and embed media

		$response = wp_remote_get( $rest_url );

		if ( is_wp_error( $response ) ) {
			return array(); // Return empty array on error
		}

		$body       = wp_remote_retrieve_body( $response );
		$posts_data = json_decode( $body, true );
		$posts      = array();

		if ( empty( $posts_data ) || ! is_array( $posts_data ) ) {
			return array();
		}

		foreach ( $posts_data as $post_item ) {
			$featured_image_url = '';

			if ( isset( $post_item['_embedded']['wp:featuredmedia'] ) && ! empty( $post_item['_embedded']['wp:featuredmedia'] ) ) {
				$featured_media = $post_item['_embedded']['wp:featuredmedia'][0];

				if ( isset( $featured_media['media_details']['sizes']['medium']['source_url'] ) ) {
					$featured_image_url = $featured_media['media_details']['sizes']['medium']['source_url'];
				}
			}

			if ( empty( $post_item['title']['rendered'] ) || empty( $post_item['link'] ) ) {
				continue;
			}

			$new = array(
				'title'            => esc_html( $post_item['title']['rendered'] ),
				'link'             => esc_url( $post_item['link'] ),
				'excerpt'          => ''
			);
			
			if ( ! empty( $post_item['excerpt']['rendered'] ) ) {
				$new['excerpt'] = esc_html( wp_trim_words( strip_tags( $post_item['excerpt']['rendered'] ), 15, '...' ) );
			}

			if ( ! empty( $featured_image_url ) ) {
				$new['featured_image'] = esc_url_raw( $featured_image_url );
			}

			$posts[] = $new;
		}

		return $posts;
	}


	/**
	 * Convert 1000 to 1K, 1000000 to 1M.
	 *
	 * @param integer $num
	 * @since 9.4.0
	 *
	 * @return string
	 */
	public function roundThousandsNumber( $num ) {
		$units = ['', 'K', 'M', 'B', 'T'];

		for ($i = 0; $num >= 1000; $i++) {
			$num /= 1000;
		}

		return round($num, 1) . $units[$i];
	}
}
