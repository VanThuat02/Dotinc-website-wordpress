<?php
/**
 * Plain Text Email Body Template for Testing
 *
 * A plain text version of the email body for testing purposes.
 *
 * @since 8.19.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Initialize variables with fake data for testing (similar to body-summaries-test.php)
$update_available = true;
$report_title = __('Your Monthly Website Analytics Summary', 'google-analytics-for-wordpress');
$report_image_src = 'https://placehold.co/600x400'; // Placeholder image URL (not used in plain text but kept for consistency)
$report_description = __('Here\'s a quick overview of your website\'s performance over the last month. Check out your key stats and top pages below.', 'google-analytics-for-wordpress');
$report_features = array(
	__('Track key metrics', 'google-analytics-for-wordpress'),
	__('Identify top content', 'google-analytics-for-wordpress'),
	__('Improve user engagement', 'google-analytics-for-wordpress'),
);
$report_button_text = __('View Full Report', 'google-analytics-for-wordpress');
$report_link = admin_url('admin.php?page=monsterinsights_reports');
$report_stats = array(
	array('icon' => '📊', 'label' => __('Sessions', 'google-analytics-for-wordpress'), 'value' => '1.5K', 'difference' => 15, 'trend_icon' => '↑', 'trend_class' => 'mset-text-increase'),
	array('icon' => 'Users', 'label' => __('Users', 'google-analytics-for-wordpress'), 'value' => '1.2K', 'difference' => -5, 'trend_icon' => '↓', 'trend_class' => 'mset-text-decrease'),
	array('icon' => 'Pageviews', 'label' => __('Page Views', 'google-analytics-for-wordpress'), 'value' => '2.8K', 'difference' => 10, 'trend_icon' => '↑', 'trend_class' => 'mset-text-increase'),
	array('icon' => 'Avg. Duration', 'label' => __('Avg. Session Duration', 'google-analytics-for-wordpress'), 'value' => '00:02:30', 'difference' => 2, 'trend_icon' => '↑', 'trend_class' => 'mset-text-increase'),
	array('icon' => 'Bounce Rate', 'label' => __('Bounce Rate', 'google-analytics-for-wordpress'), 'value' => '45%', 'difference' => -3, 'trend_icon' => '↓', 'trend_class' => 'mset-text-decrease'),
);
$top_pages = array(
	array('hostname' => 'example.com', 'url' => '/page-1', 'title' => 'Example Page 1', 'sessions' => 500),
	array('hostname' => 'example.com', 'url' => '/page-2', 'title' => 'Example Page 2', 'sessions' => 450),
	array('hostname' => 'example.com', 'url' => '/page-3', 'title' => 'Example Page 3', 'sessions' => 400),
	array('hostname' => 'example.com', 'url' => '/page-4', 'title' => 'Example Page 4', 'sessions' => 350),
	array('hostname' => 'example.com', 'url' => '/page-5', 'title' => 'Example Page 5', 'sessions' => 300),
);
$more_pages_url = admin_url('admin.php?page=monsterinsights_reports#/overview/toppages-report/');
$blog_posts = array(
	array('featured_image' => 'https://placehold.co/100x100', 'title' => 'Blog Post Title 1', 'excerpt' => 'Blog post excerpt 1...', 'link' => '#'),
	array('featured_image' => 'https://placehold.co/100x100', 'title' => 'Blog Post Title 2', 'excerpt' => 'Blog post excerpt 2...', 'link' => '#'),
	array('featured_image' => 'https://placehold.co/100x100', 'title' => 'Blog Post Title 3', 'excerpt' => 'Blog post excerpt 3...', 'link' => '#'),
);
$blog_posts_url = 'https://monsterinsights.com/blog/';


if ( $update_available ) :
	echo esc_html__('Update Notice:', 'google-analytics-for-wordpress') . "\n";
	echo esc_html__('An update is available for MonsterInsights.', 'google-analytics-for-wordpress') . "\n";
	echo esc_url(admin_url('plugins.php')) . "\n\n";
endif;

echo esc_html($report_title) . "\n";
echo "------------------------------------\n\n";

if ( ! empty( $report_description ) ) :
	echo esc_html($report_description) . "\n\n";
endif;

if ( ! empty( $report_features ) ) :
	echo esc_html__('Key Features:', 'google-analytics-for-wordpress') . "\n";
	foreach ($report_features as $feature) :
		echo "- " . esc_html($feature) . "\n";
	endforeach;
	echo "\n";
endif;

if ( ! empty( $report_button_text ) && ! empty( $report_link ) ) :
	echo esc_html($report_button_text) . ": " . esc_url($report_link) . "\n\n";
else :
	echo esc_html__('Upgrade and Unlock:', 'google-analytics-for-wordpress') . " " . esc_url( monsterinsights_get_upgrade_link('lite-email-summaries') ) . "\n\n";
endif;

echo esc_html__('Analytics Stats', 'google-analytics-for-wordpress') . "\n";
echo "------------------------------------\n\n";

if ( ! empty( $report_stats ) ) :
	foreach ($report_stats as $stat) :
		echo esc_html($stat['label']) . ": " . esc_html($stat['value']);
		if (isset($stat['difference'])) :
			echo " (" . esc_html($stat['trend_icon']) . esc_html($stat['difference']) . "%)\n";
		else :
			echo "\n";
		endif;
	endforeach;
	echo "\n";
endif;

echo esc_html__('See My Analytics:', 'google-analytics-for-wordpress') . " " . esc_url(admin_url('admin.php?page=monsterinsights_reports')) . "\n\n";


if (!empty($top_pages)) :
	echo esc_html__('Your Top 5 Viewed Pages', 'google-analytics-for-wordpress') . "\n";
	echo "------------------------------------\n\n";
	echo esc_html__('Page Title', 'google-analytics-for-wordpress') . "\t\t" . esc_html__('Page Views', 'google-analytics-for-wordpress') . "\n";
	foreach ($top_pages as $i => $page) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- false positive
		echo esc_html((intval($i) + 1) . '. ' . monsterinsights_trim_text($page['title'], 2)) . "\t\t" . esc_html(number_format_i18n($page['sessions'])) . "\n";
	endforeach;
	echo "\n";
	echo esc_html__('View All Pages:', 'google-analytics-for-wordpress') . " " . esc_url($more_pages_url) . "\n\n";
endif;

if ( ! empty( $blog_posts ) ) :
	echo esc_html__('What\'s New at MonsterInsights', 'google-analytics-for-wordpress') . "\n";
	echo "------------------------------------\n\n";
	foreach ( $blog_posts as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- false positive
		echo esc_html($post['title']) . "\n";
		echo esc_html($post['excerpt']) . "\n";
		echo esc_html__('Continue Reading:', 'google-analytics-for-wordpress') . " " . esc_url($post['link']) . "\n\n";
	endforeach;
	echo esc_html__('See All Resources:', 'google-analytics-for-wordpress') . " " . esc_url($blog_posts_url) . "\n";
endif;
