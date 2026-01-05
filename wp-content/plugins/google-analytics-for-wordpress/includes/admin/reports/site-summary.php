<?php
/**
 * Site Summary Report
 *
 * Ensures all of the reports have a uniform class with helper functions.
 *
 * @since 6.0.0
 *
 * @package MonsterInsights
 * @subpackage Reports
 * @author  Andrei Lupu
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MonsterInsights_Report_Site_Summary extends MonsterInsights_Report {

	public $title;
	public $class = 'MonsterInsights_Report_Site_Summary';
	public $name = 'site_summary';
	public $version = '1.0.0';
	public $level = 'lite';

	/**
	 * Primary class constructor.
	 *
	 * @access public
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->title = __( 'Site Summary', 'google-analytics-for-wordpress' );
		parent::__construct();

		add_filter( 'monsterinsights_report_site_summary_data', array( $this, 'prepare_data' ) );
	}

	/**
	 * Prepare report-specific data for output.
	 *
	 * @param array $data The data from the report before it gets sent to the frontend.
	 *
	 * @return mixed
	 */
	public function prepare_report_data( $data ) {
		return apply_filters( 'monsterinsights_report_site_summary_data', $data );
	}

	public function prepare_data( $data ) {
		// Fill summary data with total number of posts, pages, and comments.
		if ( isset( $data['data']['summary'] ) ) {
			$posts = wp_count_posts('post');
			$data['data']['summary']['total_posts'] = $posts->publish;
			$data['data']['summary']['total_posts_url'] = admin_url( 'edit.php?post_type=post' );
			$pages = wp_count_posts('page');
			$data['data']['summary']['total_pages'] = $pages->publish;
			$data['data']['summary']['total_pages_url'] = admin_url( 'edit.php?post_type=page' );
			$comments = get_comment_count();
			$data['data']['summary']['total_comments'] = $comments['all'];
			$data['data']['summary']['total_comments_url'] = admin_url( 'edit-comments.php' );
		}

		if ( ! empty( $data['data']['popular_post'] ) ) {
			// Get the thumbnail for the post.
			$post_id = url_to_postid( home_url() . $data['data']['popular_post']['url'] );
			$data['data']['popular_post']['id'] = $post_id;
			$data['data']['popular_post']['title'] = esc_html( get_the_title($post_id) );
			$data['data']['popular_post']['img'] = $this->get_post_image_url( $post_id );
		}

		if ( !empty( $data['data']['popular_page'] ) ) {
			// Compose the page url from homepage and the path we fetch from GA.
			$data['data']['popular_page']['url'] = esc_url( home_url() . $data['data']['popular_page']['url'] );
			// Get the thumbnail for the post.
			$post_id = url_to_postid( $data['data']['popular_page']['url'] );
			$data['data']['popular_page']['id'] = $post_id;
			$data['data']['popular_page']['img'] = $this->get_post_image_url( $post_id );
		}

		if ( !empty( $data['data']['popular_categories'] ) ) { 
			foreach ( $data['data']['popular_categories'] as $key => $category ) {
				$cat = get_category_by_slug( sanitize_title( strtolower( $category['title'] ) ) );
				if ( $cat ) {
					$category_url = esc_url( get_category_link( $cat->term_id ) );
					$data['data']['popular_categories'][$key]['url'] = $category_url;
				} else {
					$data['data']['popular_categories'][$key]['url'] = '';
				}
			}
		}
		return $data;
	}

	/**
	 * Get the image URL for a post.
	 */
	private function get_post_image_url( $post_id ) {
		$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );

		if ( $thumbnail_url ) {
			return $thumbnail_url;
		}

		// Get wordpress favicon.
		$favicon_url = get_site_icon_url();

		if ( $favicon_url ) {
			return $favicon_url;
		}

		return plugins_url( 'assets/images/mascot.png', MONSTERINSIGHTS_PLUGIN_FILE );
	}
}