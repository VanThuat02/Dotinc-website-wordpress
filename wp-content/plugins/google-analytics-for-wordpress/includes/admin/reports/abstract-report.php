<?php
/**
 * Report Abstract
 *
 * Ensures all of the reports have a uniform class with helper functions.
 *
 * @since 6.0.0
 *
 * @package MonsterInsights
 * @subpackage Reports
 * @author  Chris Christoff
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MonsterInsights_Report {

	public $title;
	public $class;
	public $name;
	public $version = '1.0.0';
	public $source = 'reports';
	public $start_date;
	public $end_date;

	/**
	 * We will use this value if we are not using the same value for report store and relay path.
	 *
	 * @var string
	 */
	protected $api_path;

	/**
	 * Primary class constructor.
	 *
	 * @access public
	 * @since 6.0.0
	 */
	public function __construct() {
		add_filter( 'monsterinsights_reports_abstract_get_data_pre_cache', array( $this, 'requirements' ), 10, 3 );
	}

	// Let's get the HTML to output for a particular report. This is not the AJAX endpoint. Args can hold things (generally start/end date range)
	protected function get_report_html( $args = array() ) {
		/* Defined in the report class */
		// For ajax, args start, end, and data will be set with the data to use. Else call $this->get_data( array( 'default' => true ) )
		return '';
	}

	public function additional_data() {
		return array();
	}

	public function requirements( $error = false, $args = array(), $name = '' ) {
		return $error;
	}

	public function show_report( $args = array() ) {

		if ( ! current_user_can( 'monsterinsights_view_dashboard' ) ) {
			return monsterinsights_get_message( 'error', esc_html__( 'Oops! Access is denied. If you believe you should be able to view this report, please contact your website administrator to ensure you have the correct user role to view MonsterInsights reports.', 'google-analytics-for-wordpress' ) );
		}

		if ( monsterinsights_get_option( 'dashboard_disabled', false ) ) {
			if ( current_user_can( 'monsterinsights_save_settings' ) ) {
				$url = is_network_admin() ? network_admin_url( 'admin.php?page=monsterinsights_settings' ) : admin_url( 'admin.php?page=monsterinsights_settings' );

				// Translators: Placeholders add a link to the settings panel.
				return monsterinsights_get_message( 'error', sprintf( esc_html__( 'Please %1$senable the dashboard%2$s to see report data.', 'google-analytics-for-wordpress' ), '<a href="' . $url . '">', '</a>' ) );
			} else {
				$message = sprintf(
				// Translators: Link tag starts with url and link tag ends.
					esc_html__( 'Oops! The MonsterInsights dashboard has been disabled. Please check with your site administrator that your role is included in the MonsterInsights permissions settings. %1$sClick here for more information%2$s.', 'google-analytics-for-wordpress' ),
					'<a target="_blank" href="' . monsterinsights_get_url( 'notice', 'cannot-view-reports', 'https://www.monsterinsights.com/docs/how-to-allow-user-roles-to-access-the-monsterinsights-reports-and-settings/' ) . '">',
					'</a>'
				);

				return monsterinsights_get_message( 'error', $message );
			}
		}

		if ( monsterinsights_is_pro_version() ) {
			if ( ! MonsterInsights()->license->has_license() ) {
				$url = is_network_admin() ? network_admin_url( 'admin.php?page=monsterinsights_settings' ) : admin_url( 'admin.php?page=monsterinsights_settings' );
				// Translators: Placeholders add a link to the settings panel, Support link tag starts with url and support link tag ends.
				$message = sprintf(
					esc_html__( 'Oops! We did not find an active MonsterInsights license. Please %1$scheck your license settings%2$s or %3$scontact our support team%4$s for help.', 'google-analytics-for-wordpress' ),
					'<a href="' . $url . '">',
					'</a>',
					'<a target="_blank" href="' . monsterinsights_get_url( 'notice', 'no-active-license', 'https://www.monsterinsights.com/my-account/support/' ) . '">',
					'</a>'
				);

				return monsterinsights_get_message( 'error', $message );
			} else if ( MonsterInsights()->license->license_has_error() ) {
				return monsterinsights_get_message( 'error', $this->get_license_error() );
			}
		}

		if ( ! ( MonsterInsights()->auth->is_authed() || MonsterInsights()->auth->is_network_authed() ) ) {
			if ( current_user_can( 'monsterinsights_save_settings' ) ) {
				$url = is_network_admin() ? network_admin_url( 'admin.php?page=monsterinsights_settings' ) : admin_url( 'admin.php?page=monsterinsights_settings' );

				// Translators: Placeholders add a link to the settings panel.
				return monsterinsights_get_message( 'error', sprintf( esc_html__( 'Oops! We did not find a properly authenticated analytics account. Please %1$sauthenticate with Google%2$s to allow MonsterInsights to show you reports.', 'google-analytics-for-wordpress' ), '<a href="' . $url . '">', '</a>' ) );
			} else {
				return monsterinsights_get_message( 'error', esc_html__( 'Oops! It appears as though you do not have the right user permissions to authenticate. Please contact your website administrator to check your user roles.', 'google-analytics-for-wordpress' ) );
			}
		}

		if ( monsterinsights_is_pro_version() ) {
			if ( ! MonsterInsights()->license->license_can( $this->level ) ) {
				return $this->get_upsell_notice();
			}
		}

		$error = $this->requirements( false, array(), $this->name );

		if ( ! empty( $error ) ) {
			return monsterinsights_get_message( 'error', $error );
		}

		if ( ! empty( $args['error'] ) ) {
			return monsterinsights_get_message( 'error', $args['error'] );
		}

		if ( empty( $args['data'] ) || ! is_array( $args['data'] ) ) {
			if ( monsterinsights_is_pro_version() ) {
				return '';
			} else {
				// Try to get default data.
				$args = $this->get_data( array( 'default' => true ) );
				if ( empty( $args['data'] ) || is_array( $args['data'] ) ) {
					return monsterinsights_get_message( 'error', __( 'No data found', 'google-analytics-for-wordpress' ) );
				}

				if ( ! empty( $args['error'] ) ) {
					return monsterinsights_get_message( 'error', $args['error'] );
				}
			}
		}

		return $this->get_report_html( $args['data'] );
	}

	// Deletes the report data from the cache
	public function delete_cache( $where = 'site' ) {
		$start = $this->default_start_date();
		$end   = $this->default_end_date();

		// Build cache key for default date range
		$cache_key = 'report_' . $this->name . '_' . $start . '_' . $end;

		if ( $where === 'site' || $where === 'both' ) {
			monsterinsights_cache_delete( $cache_key );
		}

		if ( $where === 'network' || $where === 'both' ) {
			// For network, append network identifier to key
			$network_cache_key = 'network_report_' . $this->name . '_' . $start . '_' . $end;
			monsterinsights_cache_delete( $network_cache_key );
		}
	}

	/**
	 * Get cache key suffix for this report.
	 *
	 * Override this method in child classes to add custom cache key suffixes.
	 * Useful for reports that need different cache entries based on parameters.
	 *
	 * @param array $extra_params Extra parameters passed to get_data().
	 * @return string Cache key suffix (should start with underscore if non-empty).
	 * @since 9.11.0
	 */
	protected function get_cache_key_suffix( $extra_params ) {
		return '';
	}

	// Get report data
	public function get_data( $args = array() ) {
		if ( ! empty( $args['default'] ) ) {
			$args['start'] = $this->default_start_date();
			$args['end']   = $this->default_end_date();
		}

		$start = ! empty( $args['start'] ) && $this->is_valid_date( $args['start'] ) ? $args['start'] : '';
		$end   = ! empty( $args['end'] ) && $this->is_valid_date( $args['end'] ) ? $args['end'] : '';

		$extra_params = [];
		if ( ! empty( $args['included_metrics'] ) ) {
			$extra_params['included_metrics'] = $args['included_metrics'];
		}
		$compare_start    = null;
		$compare_end      = null;

		if ( isset( $args['compare_start'] ) ) {
			$compare_start = ! empty( $args['compare_start'] ) && $this->is_valid_date( $args['compare_start'] ) ? $args['compare_start'] : '';
			$compare_end   = ! empty( $args['compare_end'] ) && $this->is_valid_date( $args['compare_end'] ) ? $args['compare_end'] : '';
		}

		if ( monsterinsights_is_pro_version() && ! MonsterInsights()->license->license_can( $this->level ) ) {
			return array(
				'success' => true,
				'upgrade' => true,
				'data'    => array(),
			);
		}

		if ( ! $this->is_valid_date_range( $start, $end ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Whoops! No data found for this date range', 'google-analytics-for-wordpress' ),
				'data'    => array(
					'type' => 'INVALID_DATE_RANGE',
				),
			);
		}

		if ( ( $start !== $this->default_start_date() || $end !== $this->default_end_date() ) && ! monsterinsights_is_pro_version() ) {
			// On lite version, the date range is blocked with upgrade to pro message and this conflicts with getting YIR report.
			// $start = $this->default_start_date();
			// $end   = $this->default_end_date();

			// return array(
			// 	'success' => false,
			// 	'error'   => __( 'Please upgrade to MonsterInsights Pro to use custom date ranges.', 'google-analytics-for-wordpress' ),
			// 	'data'    => array(),
			// );
		}

		$error = apply_filters( 'monsterinsights_reports_abstract_get_data_pre_cache', false, $args, $this->name );
		if ( $error ) {
			return apply_filters( 'monsterinsights_reports_handle_error_message', array(
				'success' => false,
				'error'   => $error,
				'data'    => array(),
			) );
		}

		// These values are going to use on child classes.
		$this->start_date = $start;
		$this->end_date   = $end;

		$check_cache       = ( $start === $this->default_start_date() && $end === $this->default_end_date() ) || apply_filters( 'monsterinsights_report_use_cache', false, $this->name );
		$site_auth         = MonsterInsights()->auth->get_viewname();
		$ms_auth           = is_multisite() && MonsterInsights()->auth->get_network_viewname();

		// Build unified cache key
		$cache_key_prefix = ( ! $site_auth && $ms_auth ) ? 'network_report_' : 'report_';
		$cache_key_suffix = $this->get_cache_key_suffix( $extra_params );

		if ( $compare_start && $compare_end ) {
			$cache_key = $cache_key_prefix . $this->name . '_' . $start . '_' . $end . '_to_' . $compare_start . '_' . $compare_end . $cache_key_suffix;
		} else {
			$cache_key = $cache_key_prefix . $this->name . '_' . $start . '_' . $end . $cache_key_suffix;
		}

		// Cache duration
		// Same-day data: 60 minutes (more stable, reduces API calls)
		// Historical data: 24 hours (data won't change, safe to cache longer)
		//
		// Filter: monsterinsights_report_cache_expiration
		// @param int $expiration Cache duration in seconds
		// @param string $name Report name
		//
		// Note: Filter name changed from 'monsterinsights_report_transient_expiration' in v9.11.0
		// to reflect new cache system. Old filter is deprecated but still works via compatibility layer.
		$expiration = apply_filters( 'monsterinsights_report_cache_expiration',
			date( 'Y-m-d' ) === $end ? ( HOUR_IN_SECONDS ) : ( DAY_IN_SECONDS ), $this->name ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We need this to depend on the runtime timezone.

		// Backward compatibility: Support old filter name
		$expiration = apply_filters( 'monsterinsights_report_transient_expiration', $expiration, $this->name );

		// Default date range, check.
		if ( $site_auth || $ms_auth ) {
			$p = $site_auth ? MonsterInsights()->auth->get_viewid() : MonsterInsights()->auth->get_network_viewid();

			// Retrieve from unified cache
			$data = monsterinsights_cache_get( $cache_key );

			// Check if cache is valid (not expired and correct viewid)
			// Cache system handles expiration automatically, false = expired/not found
			// Since 9.11.0: Metrics are included in cache key, so no need for per-user validation
			if ( $data !== false &&
					! empty( $data['data'] ) &&
					! empty( $data['p'] ) &&
					$data['p'] === $p
			) {
				return $this->prepare_report_data( array(
					'success' => true,
					'data'    => $data['data'],
				) );
			}

			// Nothing in cache, either not saved before, expired or mismatch. Let's grab from API.
			$api_options = array( 'start' => $start, 'end' => $end );

			if ( $compare_start && $compare_end ) {
				$api_options['compare_start'] = $compare_start;
				$api_options['compare_end']   = $compare_end;
			}

			if ( ! $site_auth && $ms_auth ) {
				$api_options['network'] = true;
			}

			// Get the path of the relay.
			$api_path = empty( $this->api_path ) ? $this->name : $this->api_path;

			$api = new MonsterInsights_API_Request( 'analytics/reports/' . $api_path . '/', $api_options, 'GET' );

			// Use a report source indicator for requests.
			if ( ! empty( $this->source ) ) {
				$api->set_additional_data( array(
					'source' => $this->source,
				) );
			}

			$additional_data = $this->additional_data();

			if ( ! empty( $additional_data ) ) {
				$api->set_additional_data( $additional_data );
			}
			$ret = $api->request( $extra_params );

			if ( is_wp_error( $ret ) ) {
				return array(
					'success' => false,
					'error'   => $ret->get_error_message(),
					'data'    => array(),
				);
			} else {
				// Success

				// Strip any HTML tags from API response
				$ret['data'] = json_encode($ret['data']);
				$ret['data'] = strip_tags($ret['data']);
				$ret['data'] = json_decode($ret['data'], true);

				$data = array(
					'p'    => $p,
					'data' => $ret['data'],
				);

				// Store in unified cache with TTL
				monsterinsights_cache_set( $cache_key, $data, 'reports', $expiration );

				return $this->prepare_report_data( array(
					'success' => true,
					'data'    => $ret['data'],
				) );
			}

		} else {
			$url = admin_url( 'admin.php?page=monsterinsights-onboarding' );

			// Check for MS dashboard
			if ( is_network_admin() ) {
				$url = network_admin_url( 'admin.php?page=monsterinsights-onboarding' );
			}

			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: Placeholders add a link to the Setup Wizard page. */
					__( 'You must be properly authenticated with MonsterInsights to use our reports. Please use our %1$ssetup wizard%2$s to get started.', 'google-analytics-for-wordpress' ),
					'<a href=" ' . $url . ' ">',
					'</a>'
				),
				'data'    => array(),
			);
		}
	}

	public function default_start_date() {
		return date( 'Y-m-d', strtotime( '-30 days' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We need this to depend on the runtime timezone.
	}

	public function default_end_date() {
		return date( 'Y-m-d', strtotime( '-1 day' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We need this to depend on the runtime timezone.
	}

	/**
	 * Default date for compare start.
	 *
	 * @return string
	 */
	public function default_compare_start_date() {
		return date( 'Y-m-d', strtotime( '-60 days' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We need this to depend on the runtime timezone.
	}

	/**
	 * Default date for compare end.
	 *
	 * @return string
	 */
	public function default_compare_end_date() {
		return date( 'Y-m-d', strtotime( '-31 day' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We need this to depend on the runtime timezone.
	}

	// Checks to see if date range is valid. Should be 30-yesterday always for lite & any valid date range to today for Pro.
	public function is_valid_date_range( $start, $end ) {
		$now         = current_datetime();
		$wp_timezone = wp_timezone_string();
		$start_date  = DateTime::createFromFormat( 'Y-m-d', $start, new DateTimeZone( $wp_timezone ) );
		$end_date    = DateTime::createFromFormat( 'Y-m-d', $end, new DateTimeZone( $wp_timezone ) );

		$ancient_date = DateTime::createFromFormat( 'Y-m-d', '2005-01-01', new DateTimeZone( $wp_timezone ) );

		if ( $start_date > $now || $end_date > $now || $start_date < $ancient_date || $end_date < $ancient_date ) {
			return false;
		}

		// return false if the start date is after the end date
		return ( $start > $end ) ? false : true;
	}

	// Is a valid date value
	public function is_valid_date( $date = '' ) {
		$d = MonsterInsightsDateTime::create_from_format( 'Y-m-d', $date );

		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Do not use the functions below this. They are unused and are just here so people
	 * with out of date MonsterInsights addons won't get fatal errors.
	 */
	protected function get_api_max_limit() {
		return 300;
	}

	protected function get_date_range() {
		return array();
	}

	protected function get_ga_report_url( $v4_name, $data, $v4_extra_params = '', $v4_endpoint = 'explorer', $is_real_time = false ) {
		$auth = MonsterInsights()->auth;

		$params = $this->get_ga_report_range( $data );

		$format = 'https://analytics.google.com/analytics/web/#/%1$s/' . ( $is_real_time ? 'realtime' : 'reports' ) . '/%5$s?params=%3$s%4$s&r=%2$s';

		if ( empty( $v4_name ) ) {
			$report_name = '';
		} else {
			$report_name = $v4_name;
		}
		$extra_params = '&' . $v4_extra_params;
		$endpoint     = $v4_endpoint;

		return sprintf(
			$format,
			$auth->get_referral_url(),
			$report_name,
			$params,
			urlencode( $extra_params ),
			$endpoint
		);
	}

	public function get_upsell_notice() {
		$has_level = monsterinsights_is_pro_version() ? MonsterInsights()->license->get_license_type() : false;
		$has_level = $has_level ? $has_level : 'lite';
		// Translators: Placeholders add the license level and the report title.
		$message = sprintf( __( 'You currently have a %1$s level license, but this report requires at least a %2$s level license to view the %3$s. Please upgrade to view this report.', 'google-analytics-for-wordpress' ), $has_level, $this->level, $this->title );
		ob_start(); ?>
		<div
			class="monsterinsights-upsell-report-container monsterinsights-upsell-report-<?php echo esc_attr($this->name); ?>-bg">
			<div class="monsterinsights-upsell-container">
				<div class="row justify-content-center">
					<div class="col-lg-10 col-lg-offset-1 align-self-center">
						<div class="monsterinsights-upsell-card">
							<img class="monsterinsights-upgrade-mascot"
								 src="<?php echo esc_url(trailingslashit( MONSTERINSIGHTS_PLUGIN_URL )); ?>assets/css/images/mascot.png"
								 srcset="<?php echo esc_url(trailingslashit( MONSTERINSIGHTS_PLUGIN_URL )); ?>assets/css/images/mascot@2x.png 2x"
								 alt="">
							<div class="monsterinsights-upsell-card-card-content">
								<span
									class="monsterinsights-upsell-card-title"><?php esc_html_e( 'Ready to Get Analytics Super-Powers?', 'google-analytics-for-wordpress' ); ?></span>
								<p class="monsterinsights-upsell-card-subtitle">
									<strong><?php esc_html_e( '(And Crush Your Competition?)', 'google-analytics-for-wordpress' ); ?></strong>
								</p> &nbsp;
								<?php if ( monsterinsights_is_pro_version() ) { ?>
									<p>
										<?php
										// Translators: License level and smiley.
										echo sprintf( esc_html__( 'Hey there! It looks like you\'ve got the %1$s license installed on your site. That\'s awesome! %s', 'google-analytics-for-wordpress' ), $has_level, '<span class="dashicons dashicons-smiley"></span>' ); // phpcs:ignore
										?>
									</p>
									&nbsp;
									<p>
										<?php
										// Translators: Placeholders add the report title and license level.
										echo sprintf( esc_html__( 'Do you want to access to %1$s reporting right now%2$s in your WordPress Dashboard? That comes with the %3$s level%4$s of our paid packages. You\'ll need to upgrade your license to get instant access.', 'google-analytics-for-wordpress' ), '<strong>' . $this->title, '</strong>', '<strong><a href="' . monsterinsights_get_url( 'reports-page', $this->name . '-report-upsell-license-link', 'https://monsterinsights.com/my-account/' ) . '">' . $this->level, '</a></strong>' ); // phpcs:ignore
										?>
									</p>
									&nbsp;
									<p>
										<?php
										// Translators: Placeholdes add links to the account area and a guide.
										echo sprintf( esc_html__( 'It\'s easy! To upgrade, navigate to %1$sMy Account%2$s on MonsterInsights.com, go to the licenses tab, and click upgrade. We also have a %3$sstep by step guide%4$s with pictures of this process.', 'google-analytics-for-wordpress' ), '<a href="' . monsterinsights_get_url( 'reports-page', $this->name . '-report-upsell-license-link', 'https://monsterinsights.com/my-account/' ) . '"><strong>', '</strong></a>', '<a href="' . monsterinsights_get_url( 'reports-page', $this->name . '-report-upsell-license-link', 'https://www.monsterinsights.com/docs/upgrade-monsterinsights-license/' ) . '" style="text-decoration:underline !important">', '</a>' ); // phpcs:ignore
										?>
									</p>
									&nbsp;
									<p><?php esc_html_e( 'If you have any questions, don\'t hesitate to reach out. We\'re here to help.', 'google-analytics-for-wordpress' ); ?></p>
								<?php } else { ?>
									<p>
										<?php
										// Translators: Placeholder adds a smiley face.
										echo sprintf( esc_html__( 'Hey there! %s It looks like you\'ve got the free version of MonsterInsights installed on your site. That\'s awesome!', 'google-analytics-for-wordpress' ), '<span class="dashicons dashicons-smiley"></span>' );
										?>
									</p>
									&nbsp;
									<p>
										<?php
										// Translators: Placeholders make the text bold, add the license level and add a link to upgrade.
										echo sprintf( esc_html__( 'Do you you want to access to %1$s reporting right now%2$s in your WordPress Dashboard? That comes with %3$s level%4$s of our paid packages. To get instant access, you\'ll want to buy a MonsterInsights license, which also gives you access to powerful addons, expanded reporting (including the ability to use custom date ranges), comprehensive tracking features (like UserID tracking) and access to our world-class support team.', 'google-analytics-for-wordpress' ), '<strong>' . $this->title, '</strong>', '<a href="' . monsterinsights_get_upgrade_link( 'reports-page', $this->name . '-report-upsell-license-link' ) . '">' . $this->level, '</a>' ); // phpcs:ignore
										?>
									</p>
									&nbsp;
									<p>
										<?php
										// Translators: Placeholders make the text bold, add the license level and add a link to upgrade.
										echo sprintf( esc_html__( 'Upgrading is easy! To upgrade, navigate to %1$ssour pricing page%2$s, purchase the required license, and then follow the %3$sinstructions in the email receipt%4$s to upgrade. It only takes a few minutes to unlock the most powerful, yet easy to use analytics tracking system for WordPress.', 'google-analytics-for-wordpress' ), '<a href="' . monsterinsights_get_upgrade_link( 'reports-page', $this->name . '-report-upsell-license-link' ) . '"><strong>', '</strong></a>', '<a style="text-decoration:underline !important" href="' . monsterinsights_get_url( 'reports-page', $this->name . '-report-go-lite-pro-link', 'https://www.monsterinsights.com/docs/go-lite-pro/' ) . '">', '</a>' ); // phpcs:ignore
										?>
									</p>
									&nbsp;
									<p><?php esc_html_e( 'If you have any questions, don\'t hesitate to reach out. We\'re here to help.', 'google-analytics-for-wordpress' ); ?></p>
								<?php } ?>
							</div>
							<div class="monsterinsights-upsell-card-action">
								<?php if ( monsterinsights_is_pro_version() ) { ?>
									<a href="<?php echo monsterinsights_get_upgrade_link( 'reports-page', $this->name . '-report-upsell-license-link' ); // phpcs:ignore -- Escaped in the function ?>"
									   class="monsterinsights-upsell-card-button"><?php esc_html_e( 'Upgrade Now', 'google-analytics-for-wordpress' ); ?></a>
								<?php } else { ?>
									<a href="<?php echo monsterinsights_get_url( 'reports-page', $this->name . '-report-upsell-license-link', 'https://www.monsterinsights.com/docs/upgrade-monsterinsights-license/' ); // phpcs:ignore -- Escaped in the function ?>"
									   class="monsterinsights-upsell-card-button"><?php esc_html_e( 'Get MonsterInsights Pro', 'google-analytics-for-wordpress' ); ?></a>
								<?php } ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_ga_report_range( $data = array() ) {
		if ( empty( $data['reportcurrentrange'] ) || empty( $data['reportcurrentrange']['startDate'] ) || empty( $data['reportcurrentrange']['endDate'] ) ) {
			return '';
		} else {
			if ( ! empty( $data['reportprevrange'] ) && ! empty( $data['reportprevrange']['startDate'] ) && ! empty( $data['reportprevrange']['endDate'] ) ) {
				return urlencode( '_u.date00=' . str_replace( '-', '', $data['reportcurrentrange']['startDate'] ) . '&_u.date01=' . str_replace( '-', '', $data['reportcurrentrange']['endDate'] ) . '&_u.date10=' . str_replace( '-', '', $data['reportprevrange']['startDate'] ) . '&_u.date11=' . str_replace( '-', '', $data['reportprevrange']['endDate'] ) );
			}

			return urlencode( '_u.date00=' . str_replace( '-', '', $data['reportcurrentrange']['startDate'] ) . '&_u.date01=' . str_replace( '-', '', $data['reportcurrentrange']['endDate'] ) );
		}
	}

	/**
	 * Grab the link to the addons page used in each report's error message.
	 *
	 * @return string
	 */
	public function get_addons_page_link() {
		if ( monsterinsights_can_install_plugins() ) {
			$addons_link = 'install_addon';
		} else {
			$addons_link = esc_html__( 'Please ask your webmaster to enable this addon.', 'google-analytics-for-wordpress' );
		}

		return $addons_link;
	}

	/**
	 * When called will add the footer link to be displayed in the error popup.
	 *
	 * @param array $data The data sent as error response to the ajax call.
	 *
	 * @return array
	 */
	public function add_error_addon_link( $data ) {
		$data['data']['footer'] = $this->get_addons_page_link();

		return $data;
	}

	/**
	 * Added to allow individual reports to alter data when outputting for Vue reports.
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function prepare_report_data( $data ) {
		return $data;
	}

	/**
	 * Set a report source to be sent with the request.
	 *
	 * @param string $source The source where the report is called from, defaults to reports.
	 */
	public function set_report_source( $source ) {
		if ( ! empty( $source ) && is_string( $source ) ) {
			$this->source = $source;
		}
	}
}

if ( ! class_exists( 'MonsterInsightsDateTime' ) ) {
	class MonsterInsightsDateTime extends DateTime {
		public static function create_from_format( $format, $time, $timezone = null ) {
			if ( ! $timezone ) {
				$timezone = new DateTimeZone( date_default_timezone_get() );
			}
			if ( version_compare( PHP_VERSION, '5.3', '>=' ) ) {
				return parent::createFromFormat( $format, $time, $timezone );
			}

			return new DateTime( date( $format, strtotime( $time ) ), $timezone ); // phpcs:ignore
		}
	}
}
