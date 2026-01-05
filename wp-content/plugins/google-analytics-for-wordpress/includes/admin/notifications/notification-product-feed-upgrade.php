<?php

/**
 * Add upgradenotification when Product Feed report has data.
 * Recurrence: 30 Days
 *
 * @since 9.9.0
 */
final class MonsterInsights_Notification_Product_Feed_Upgrade extends MonsterInsights_Notification_Event {

	public $notification_id       = 'monsterinsights_notification_product_feed_upgrade';
	public $notification_interval = 30; // in days
	public $notification_type     = array( 'plus', 'pro' );
	public $notification_category = 'success';
	public $notification_priority = 2;
	public $notification_icon    = 'success';

	/**
	 * Build Notification
	 *
	 * @return array $notification notification is ready to add
	 *
	 * @since 9.9.0
	 */
	public function prepare_notification_data( $notification ) {
		$data = array();
		
		// Check if WooCommerce Product Feed Pro plugin is active
		$woo_feed_pro_active = class_exists( 'AdTribes\PFP\App' ) || defined( 'ADT_PFP_PLUGIN_FILE' );
		
		// Check if MonsterInsights Pro is active
		$monsterinsights_pro_active = monsterinsights_is_pro_version();
		
		// Show notification if:
		// 1. WooCommerce Product Feed Pro is active AND MonsterInsights Pro is NOT active
		// 2. OR if Pro is active but Product Feed report has no data
		if ( $woo_feed_pro_active && ! $monsterinsights_pro_active ) {
			
			// Case 1: WooCommerce Product Feed Pro is active but MonsterInsights Pro is not
			// Check if there's data in the Product Feed report to show upgrade notification
			$report = $this->get_report( 'ecommerce_product_feed' );
			
			// Check if the report was successful and has data
			if ( ! empty( $report['success'] ) && $report['success'] === true ) {
				$product_feeds_table = isset( $report['data']['product_feeds_table'] ) ? $report['data']['product_feeds_table'] : array();
				
				// If there's data, show upgrade notification
				if ( ! empty( $product_feeds_table ) && is_array( $product_feeds_table ) && count( $product_feeds_table ) > 0 ) {
					$is_em = function_exists( 'ExactMetrics' );

					$upgrade_url = $is_em
						? 'https://www.exactmetrics.com/pricing/'
						: 'https://www.monsterinsights.com/pricing/';

					$notification['title'] = __( 'Upgrade to Pro for Product Feed Analytics', 'google-analytics-premium' );
					// Translators: Product Feed Pro upgrade notification content
					$notification['content'] = sprintf( 
						__( 'Great news! We detected that you have WooCommerce Product Feed Pro installed and there\'s product feed data available. Upgrade to MonsterInsights Pro to unlock detailed analytics for your product feed campaigns, track conversions, and optimize your shopping ads performance. %1$sUpgrade now%2$s to see your product feed insights!', 'google-analytics-premium' ), 
						'<a href="' . $this->build_external_link( $upgrade_url ) . '" target="_blank">', 
						'</a>' 
					);
					$notification['btns'] = array(
						'upgrade_now' => array(
							'url'         => $this->build_external_link( $upgrade_url ),
							'text'        => __( 'Upgrade to Pro', 'google-analytics-premium' ),
							'is_external' => true,
						),
						'learn_more'  => array(
							'url'         => $this->build_external_link( 'https://www.monsterinsights.com/product-feed-analytics/' ),
							'text'        => __( 'Learn More', 'google-analytics-premium' ),
							'is_external' => true,
						),
					);
					return $notification;
				}
			} 
		} else if ( $monsterinsights_pro_active ) {
			// Case 2: MonsterInsights Pro is active, check if Product Feed report has data
			$report = $this->get_report( 'ecommerce_product_feed' );
			
			// Check if the report was successful and has data
			if ( ! empty( $report['success'] ) && $report['success'] === true ) {
				$product_feeds_table = isset( $report['data']['product_feeds_table'] ) ? $report['data']['product_feeds_table'] : array();
				
				// Check if product_feeds_table is empty or has no meaningful data
				if ( empty( $product_feeds_table ) || ! is_array( $product_feeds_table ) || count( $product_feeds_table ) === 0 ) {
					
					$is_em = function_exists( 'ExactMetrics' );

					$learn_more_url = $is_em
						? 'https://www.exactmetrics.com/how-to-set-up-google-shopping-campaigns/'
						: 'https://www.monsterinsights.com/how-to-set-up-google-shopping-campaigns/';

					$notification['title'] = __( 'Product Feed Report Has No Data', 'google-analytics-premium' );
					// Translators: Product Feed empty notification content
					$notification['content'] = sprintf( 
						__( 'Your Product Feed report is currently empty, which means you may not be tracking product feed performance properly. This could indicate that your product feeds are not set up correctly or there are no product feed campaigns running. <br><br>Learn how to set up product feeds with %1$sthis guide%2$s to start tracking your product feed performance.', 'google-analytics-premium' ), 
						'<a href="' . $this->build_external_link( $learn_more_url ) . '" target="_blank">', 
						'</a>' 
					);
					$notification['btns'] = array(
						'learn_more'  => array(
							'url'         => $this->build_external_link( $learn_more_url ),
							'text'        => __( 'Learn More', 'google-analytics-premium' ),
							'is_external' => true,
						),
						'view_report' => array(
							'url'  => $this->get_view_url( 'monsterinsights-report-ecommerce-product-feed', 'monsterinsights_reports', 'ecommerce-product-feed' ),
							'text' => __( 'View Product Feed Report', 'google-analytics-premium' ),
						),
					);

					return $notification;
				}
			}
		}
		return false;
	}

}

// initialize the class
new MonsterInsights_Notification_Product_Feed_Upgrade();
