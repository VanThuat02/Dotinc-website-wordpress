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

if ( isset( $update_available ) && $update_available ) : ?>
	<div class="mset-update-notice">
		<p><?php esc_html_e('An update is available for MonsterInsights.', 'google-analytics-for-wordpress'); ?></p>
		<a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="mset-button-secondary">
			<?php esc_html_e('Upgrade to the latest version', 'google-analytics-for-wordpress'); ?>
			<span class="mset-icon-long-arrow-right mset-icon"></span>
		</a>
	</div>
<?php endif; ?>

<div class="mset-section mset-analytics-report">
	<?php if ( isset( $report_title ) && $report_title ) : ?>
		<div class="mset-section-header">
			<h2><?php echo esc_html( $report_title ); ?></h2>
		</div>
	<?php endif; ?>
	<div class="mset-section-content">
		<?php if ( isset( $report_image_src ) && $report_image_src ) : ?>
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
		<?php if ( isset( $report_stats ) && ! empty( $report_stats ) ) : ?>
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
		<?php endif; ?>

		<?php if ( isset( $reports_url ) ) : ?>
			<div class="mset-report-center-button">
				<a href="<?php echo esc_url( $reports_url ); ?>" class="mset-button-primary">
				<?php esc_html_e('See My Analytics', 'google-analytics-for-wordpress'); ?>
			</a>
		</div>
		<?php endif; ?>
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

		<?php if ( isset( $reports_url ) ) : ?>
			<div class="mset-report-center-button">
				<a href="<?php echo esc_url( $reports_url ); ?>" class="mset-button-primary">
					<?php esc_html_e('View All Pages', 'google-analytics-for-wordpress'); ?>
				</a>
			</div>
		<?php endif; ?>
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
		<?php if ( isset( $blog_posts_url ) && $blog_posts_url ) : ?>
			<div class="mset-report-center-button">
				<a href="<?php echo esc_url( $blog_posts_url ); ?>" class="mset-button-primary">
					<?php esc_html_e('See All Resources', 'google-analytics-for-wordpress'); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>
