<?php
/**
 * eCommerce Product Feed Report
 *
 * Ensures all the reports have a uniform class with helper functions.
 *
 * @since 8.17
 *
 * @package MonsterInsights
 * @subpackage Reports
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Ecommerce_Product_Feed extends MonsterInsights_Report {

	public $class = 'MonsterInsights_Report_Ecommerce_Product_Feed';
	public $name  = 'ecommerce_product_feed';
	public $level = 'plus';

	protected $api_path = 'traffic-landing-pages-adtribes';

	/**
	 * Primary class constructor.
	 */
	public function __construct() {
		$this->title = __( 'Product Feed', 'google-analytics-premium' );

		parent::__construct();
	}

	/**
	 * Add necessary information to data for Vue reports.
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function prepare_report_data( $data ) {
		return apply_filters( 'monsterinsights_report_traffic_sessions_chart_data', $data, $this->start_date, $this->end_date );
	}

}
