<?php
/**
 * Email Body Template
 *
 * Uses modern HTML/CSS while maintaining email client compatibility.
 * CSS classes are prefixed with 'mset-' (MonsterInsights Summary Email Template)
 * to avoid conflicts with email client styles.
 *
 * @since 8.19.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Initialize variables with fake data for testing
$update_available = true;
$report_title = __('Your Monthly Website Analytics Summary', 'google-analytics-for-wordpress');
$report_image_src = 'https://placehold.co/600x400'; // Placeholder image URL
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

if ( $update_available ) : ?>
	<div class="mset-update-notice">
		<p><?php esc_html_e('An update is available for MonsterInsights.', 'google-analytics-for-wordpress'); ?></p>
		<a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="mset-button-secondary">
			<?php esc_html_e('Upgrade to the latest version', 'google-analytics-for-wordpress'); ?>
			<span class="mset-icon-long-arrow-right mset-icon"></span>
		</a>
	</div>
<?php endif; ?>

<div class="mset-section mset-analytics-report">
	<div class="mset-section-header">
		<h2><?php echo esc_html( $report_title ); ?></h2>
	</div>
	
	<div class="mset-section-content">
		<?php if ( ! empty( $report_image_src ) ) : ?>
			<img src="<?php echo esc_url( $report_image_src ); ?>" 
				alt="<?php esc_attr_e('MonsterInsights Dashboard', 'google-analytics-for-wordpress'); ?>"
				class="mset-report-image">
		<?php endif;

		if ( ! empty( $report_description ) ) : ?>
			<div class="mset-report-description">
				<?php echo wp_kses_post( $report_description ); ?>
			</div>
		<?php endif;

		if ( ! empty( $report_features ) ) : ?>
			<div class="mset-report-features">
				<?php foreach ($report_features as $feature) : ?>
					<div class="mset-feature-item">
						<span class="mset-feature-item-icon"></span>
						<span><?php echo esc_html($feature); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif;

		if ( ! empty( $report_button_text ) && ! empty( $report_link ) ) : ?>
			<div class="mset-report-center-button">
				<a href="<?php echo esc_url( $report_link ); ?>" class="mset-button-primary">
					<?php echo esc_html( $report_button_text ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="mset-report-center-button">
				<a href="<?php echo esc_url( monsterinsights_get_upgrade_link('lite-email-summaries') ); ?>" class="mset-button-primary">
					<?php esc_html('Upgrade and Unlock', 'google-analytics-for-wordpress'); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="mset-section mset-analytics-stats">
	<div class="mset-section-header">
		<h2>📈 <?php esc_html_e('Analytics Stats', 'google-analytics-for-wordpress'); ?></h2>
	</div>

	<div class="mset-section-content">
		<div class="mset-stats-grid">
			<?php foreach ($report_stats as $stat) : ?>
				<div class="mset-stat-item">
					<div class="mset-stat-item-icon"><?php echo esc_html($stat['icon']); ?></div>
					<div class="mset-stat-label"><?php echo esc_html($stat['label']); ?></div>
					<div class="mset-stat-value">
						<?php
						echo esc_html($stat['value']);
						if (isset($stat['difference'])) : ?>
							<span class="mset-stat-trend <?php echo esc_attr($stat['trend_class']); ?>">
								<span class="mset-stat-trend-icon"><?php echo esc_html($stat['trend_icon']); ?></span>
								<?php echo esc_html($stat['difference']); ?>%
							</span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="mset-report-center-button">
			<a href="<?php echo esc_url(admin_url('admin.php?page=monsterinsights_reports')); ?>" class="mset-button-primary">
				<?php esc_html_e('See My Analytics', 'google-analytics-for-wordpress'); ?>
			</a>
		</div>
	</div>
</div>

<?php if (!empty($top_pages)) : ?>
<div class="mset-section mset-top-pages">
	<div class="mset-section-header">
		<h2>🌐 <?php esc_html_e('Your Top 5 Viewed Pages', 'google-analytics-for-wordpress'); ?></h2>
	</div>

	<div class="mset-section-content">
		<div class="mset-pages-table">
			<div class="mset-table-header">
				<div class="mset-table-header-cell"><?php esc_html_e('Page Title', 'google-analytics-for-wordpress'); ?></div>
				<div class="mset-table-header-cell"><?php esc_html_e('Page Views', 'google-analytics-for-wordpress'); ?></div>
			</div>
			<?php foreach ($top_pages as $i => $page) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- false positive ?>
				<div class="mset-table-row">
					<div class="mset-table-cell">
						<a href="<?php echo esc_url($page['hostname'] . $page['url']); ?>">
							<?php echo esc_html((intval($i) + 1) . '. ' . monsterinsights_trim_text($page['title'], 2)); ?>
						</a>
					</div>
					<div class="mset-table-cell">
						<?php echo esc_html(number_format_i18n($page['sessions'])); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="mset-report-center-button">
			<a href="<?php echo esc_url( $more_pages_url ); ?>" class="mset-button-primary">
				<?php esc_html_e('View All Pages', 'google-analytics-for-wordpress'); ?>
			</a>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( ! empty( $blog_posts ) ) : ?>
<div class="mset-section">
	<div class="mset-section-header">
		<h2>⭐ <?php esc_html_e('What\'s New at MonsterInsights', 'google-analytics-for-wordpress'); ?></h2>
	</div>
	<div class="mset-section-content">
		<ul class="mset-blog-posts">
			<?php foreach ( $blog_posts as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- false positive ?>
				<li class="mset-blog-post">
					<?php if ( ! empty( $post['featured_image'] ) ) : ?>
						<div class="mset-blog-post-image">
							<img src="<?php echo esc_url( $post['featured_image'] ); ?>" alt="<?php echo esc_attr( $post['title'] ); ?>" />
						</div>
					<?php endif; ?>
					<div class="mset-blog-post-content">
						<h4 class="mset-blog-post-title"><?php echo esc_html( $post['title'] ); ?></h4>
						<p class="mset-blog-post-excerpt"><?php echo esc_html( $post['excerpt'] ); ?></p>
						<a href="<?php echo esc_url( $post['link'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e('Continue Reading', 'google-analytics-for-wordpress'); ?>
						</a>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="mset-report-center-button">
			<a href="<?php echo esc_url( $blog_posts_url ); ?>" class="mset-button-primary">
				<?php esc_html_e('See All Resources', 'google-analytics-for-wordpress'); ?>
			</a>
		</div>
	</div>
</div>
<?php endif; ?>
