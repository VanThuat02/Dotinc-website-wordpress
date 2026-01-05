<?php
/**
 * Plain Text Email Body Template for Summaries
 *
 * A plain text version of the email body, converted from body-summaries.php.
 *
 * @since 8.19.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

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