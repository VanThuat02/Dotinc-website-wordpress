<?php

/**
 * This file contains the class to interact with database.
 *
 * @since 1.0.0
 *
 * @package MonsterInsights
 * @package MonsterInsights_Site_Notes
 */

/**
 * Class containing CRUD operations for site notes in the custom post type.
 *
 * @since 1.0.0
 */
class MonsterInsights_Site_Notes_DB_Base
{

	private function create_post_type()
	{
		$args = array(
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'supports'           => array('title', 'author', 'custom-fields')
		);

		register_post_type('monsterinsights_note', $args);
	}

	private function create_taxonomy()
	{
		$args = array(
			'hierarchical' => false,
			'show_ui'      => false,
			'query_var'    => true,
			'public'       => false,
		);

		register_taxonomy('monsterinsights_note_category', array('monsterinsights_note'), $args);
	}

	public function insert_default_categories()
	{
		if (get_option('monsterinsights_sitenotes_installed')) {
			return;
		}

		$categories = array(
			array(
				'name' => __('Website Updates', 'google-analytics-for-wordpress'),
				'background_color' => '#F072B2'
			),
			array(
				'name' => __('Blog Post', 'google-analytics-for-wordpress'),
				'background_color' => '#8D87E5'
			),
			array(
				'name' => __('Promotion', 'google-analytics-for-wordpress'),
				'background_color' => '#1EC185'
			),
		);

		foreach ($categories as $category) {
			$this->create_category( $category );
		}

		update_option('monsterinsights_sitenotes_installed', time());
	}

	public function install()
	{
		$this->create_post_type();
		$this->create_taxonomy();
	}

	private function get_category_name($id)
	{
		$term = get_term($id, 'monsterinsights_note_category');
		if (is_wp_error($term)) {
			return false;
		}

		return $term->name;
	}

	/**
	 * Add new Site Note.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data  Data to store.
	 *
	 * @return int|WP_Error
	 */
	public function create($data)
	{
		if (empty($data['note'])) {
			return new WP_Error(400, __('Your Site Note Cannot be Empty', 'google-analytics-for-wordpress'));
		}
		$note_post = array(
			'ID' => isset($data['id']) ? $data['id'] : null,
			'post_title'    => sanitize_text_field($data['note']),
			'post_status'   => 'publish',
			'post_author'   => isset( $data['author_id'] ) ? intval( $data['author_id'] ) : get_current_user_id(),
			'post_date'     => !empty($data['date']) ? $data['date'] : current_datetime()->format('Y-m-d'),
			'post_type'     => 'monsterinsights_note',
		);
		$post_id = wp_insert_post($note_post, true, false);

		if (is_wp_error($post_id)) {
			return $post_id;
		}

		// Create the note in GA4.
		$this->create_note_in_ga4($post_id, $data);

		// Attach the note to the category.
		if (!empty($data['category'])) {
			if ($category_name = $this->get_category_name($data['category'])) {
				wp_set_object_terms($post_id, $data['category'], 'monsterinsights_note_category');
				update_post_meta($post_id, '_category', $category_name);
			}
		} else {
			wp_set_object_terms($post_id, array(), 'monsterinsights_note_category');
			update_post_meta($post_id, '_category', '');
		}

		if (!empty($data['medias'])) {
			update_post_meta($post_id, 'medias', $data['medias']);
		}

		if (isset($data['important'])) {
			update_post_meta($post_id, 'important', $data['important']);
		}

		return $post_id;
	}

	/**
	 * Add new Site Note's category.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data  Data to store.
	 *
	 * @return int|WP_Error
	 */
	public function create_category($data)
	{
		if (isset($data['id'])) {
			$created_term = wp_update_term($data['id'], 'monsterinsights_note_category', array('name' => $data['name']));
		} else {
			$created_term = wp_insert_term($data['name'], 'monsterinsights_note_category');
		}

		if (is_wp_error($created_term)) {
			return $created_term;
		}

		if (!empty($data['background_color'])) {
			update_term_meta($created_term['term_id'], 'background_color', $data['background_color']);
		}

		return $created_term['term_id'];
	}

	/**
	 * Get site note with ID
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Single entry.
	 *
	 * @return array|WP_Error
	 */
	public function get($post_id)
	{
		$post = get_post($post_id);

		if (!$post) {
			return new WP_Error(400, __('Note not found', 'google-analytics-for-wordpress'));
		}

		$categories = wp_get_object_terms($post->ID, 'monsterinsights_note_category');
		$post_title = get_the_title($post);
		$note = array(
			'id'  => $post->ID,
			'note_title' => wp_strip_all_tags(html_entity_decode(htmlspecialchars_decode($post_title), ENT_COMPAT, 'UTF-8')),
			'note_date' => get_the_date( 'Y-m-d', $post),
			'note_date_ymd' => get_the_date( 'Y-m-d', $post ),
			'status' => get_post_status($post),
			'important' => (int) get_post_meta($post->ID, 'important', true),
			'medias' => array(),
			'category' => array(
				'id' => 0,
			),
		);

		if (
			monsterinsights_is_pro_version()
			&& $medias = get_post_meta( $post->ID, 'medias', true )
		) {
			if ( ! empty( $medias ) ) {
				foreach ($medias as $media_id) {
					$attachment_url = wp_get_attachment_url($media_id);
					if (!$attachment_url) {
						continue;
					}

					$attachment_filename = basename( get_attached_file( $media_id ) );

					$note['medias'][ $media_id ] = [
						'url'  => $attachment_url,
						'name' => $attachment_filename
					];
				}
			}
		}

		$note['medias'] = (object) $note['medias'];

		if ($post->post_author) {
			$user = get_userdata($post->post_author);

			if ($user) {
				$note['author'] = array(
					'id'   => $user->ID,
					'name' => $user->display_name,
				);
			}
		}

		if ($categories) {
			$note['category'] = array(
				'id'   => $categories[0]->term_id,
				'name' => html_entity_decode( $categories[0]->name ),
			);

			if (monsterinsights_is_pro_version()) {
				$background_color = get_term_meta($categories[0]->term_id, 'background_color', true);
				$note['category']['background_color'] = !empty($background_color) ? $background_color : '#E9AF00';
			}
		}

		return $note;
	}

	/**
	 * Get rows from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args  Optional args.
	 * @param bool  $count Flag to return count instead of results.
	 *
	 * @return array|int
	 */
	public function get_items($args = array(), $count = false)
	{
		$query_args = array(
			'post_type'      => 'monsterinsights_note',
			'posts_per_page' => $args['per_page'],
			'paged'          => $args['page'],
			'fields'         => 'ids',
			'orderby'        => $args['orderby'],
			'order'          => $args['order'],
			'post_status'    => (isset($args['filter']) && !empty($args['filter']['status']) && 'all' !== $args['filter']['status']) ? $args['filter']['status'] : ['publish'],
		);

		if (isset($args['search'])) {
			$query_args['s'] = $args['search'];
		}

		$all_notes_query_args = $query_args;
		$all_notes_query_args['post_status'] = ['publish'];

		if (isset($args['filter'])) {
			if (isset($args['filter']['important']) && !is_null($args['filter']['important'])) {
				$query_args['meta_key'] = 'important';
				$query_args['meta_value'] = $args['filter']['important'];
			}
			if (isset($args['filter']['category']) && !is_null($args['filter']['category'])) {
				$args['category'] = $args['filter']['category'];
			}
		}

		if (isset($args['filter']) && isset($args['filter']['date_range']) && !is_null($args['filter']['date_range'])) {
			$query_args['date_query'] = array(
				'after' => $args['filter']['date_range']['start'],
				'before' => $args['filter']['date_range']['end'],
				'inclusive' => true,
			);
			$all_notes_query_args['date_query'] = array(
				'after' => $args['filter']['date_range']['start'],
				'before' => $args['filter']['date_range']['end'],
				'inclusive' => true,
			);
		}

		if (!empty($args['category'])) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'monsterinsights_note_category',
					'field'    => 'term_id',
					'terms'    => $args['category'],
				),
			);
		}

		if ('category' === $query_args['orderby']) {
			$query_args['meta_key'] = '_category';
			$query_args['orderby'] = 'meta_value';
		}
		// handle last30days

		if (isset($query_args['date_query']) && !is_null($query_args['date_query'])) {
			if ($query_args['date_query']['after'] == '' && $query_args['date_query']['before'] == '') {
				$query_args['date_query']['before'] = wp_date('Y-m-d', strtotime('-1 day'));
				$query_args['date_query']['after'] = wp_date('Y-m-d', strtotime('-30 days'));
				$all_notes_query_args['date_query']['before'] = wp_date('Y-m-d', strtotime('-1 day'));
				$all_notes_query_args['date_query']['after'] = wp_date('Y-m-d', strtotime('-30 days'));
			}
		}

		$items = array();
		$query = new WP_Query($query_args);
		$all_notes_query = new WP_Query($all_notes_query_args);
		$important_count = 0;
		if (!$query->have_posts()) {
			return array(
				'items' => $items,
				'pagination' => array(
					'total_published'    => $all_notes_query->post_count,
					'total_important'    => $important_count,
					'all_published'    => 0,
					'total'    => 0,
					'pages'    => 0,
					'page'     => $args['page'],
					'per_page' => $args['per_page'],
				),
			);
		}

		foreach ($query->posts as $post_id) {
			$post = $this->get($post_id);
			$is_important = get_post_meta($post_id, 'important', true);
			if($is_important){
				$important_count++;
			}
			$post['ga_note_id'] = get_post_meta( $post_id, '_ga4_annotation_id', true ) ?? null;
			$items[] = $post;
		}

		return array(
			'items' => $items,
			'pagination' => array(
				'total_published'    => $all_notes_query->post_count,
				'total_important'    => $important_count,
				'total'    => $query->found_posts,
				'pages'    => $query->max_num_pages,
				'page'     => $args['page'],
				'per_page' => $args['per_page'],
			),
		);
	}

	public function get_categories($args = array(), $count = false)
	{
		$query_args = array(
			'taxonomy' => 'monsterinsights_note_category',
			'hide_empty' => false,
		);

		if ($count) {
			return wp_count_terms($query_args);
		}

		$query_args['offset'] = ($args['page'] - 1) * $args['per_page'];
		$query_args['fields'] = 'id=>name';
		$query_args['order'] = $args['order'];
		$query_args['orderby'] = $args['orderby'];

		$items = get_terms($query_args);
		if (!$items) {
			return false;
		}

		$categories = array();

		foreach ($items as $term_id => $term_name) {
			$background_color = get_term_meta($term_id, 'background_color', true);

			$categories[] = array(
				'id' => $term_id,
				'name' => html_entity_decode( $term_name ),
				'background_color' => !empty($background_color) ? $background_color : '#E9AF00',
			);
		}

		return $categories;
	}

	/**
	 * Delete note by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $note_id Note ID.
	 *
	 * @return WP_Post|false|null
	 */
	public function trash_note($note_id = 0)
	{
		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}

		return wp_trash_post($note_id);
	}

	public function restore_note($note_id = 0)
	{
		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}
		return wp_untrash_post($note_id);
	}
	/**
	 * Delete a note and its associated GA4 annotation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $note_id The ID of the note to delete.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_note( $note_id = 0 )
	{
		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}

		// Check if this note has a GA4 annotation ID and delete it from GA4 first
		$ga4_annotation_id = get_post_meta( $note_id, '_ga4_annotation_id', true );
		if ( ! empty( $ga4_annotation_id ) ) {
			// Get the controller instance to call the GA4 deletion function.
			$controller = MonsterInsights_SiteNotes_Controller::get_instance();
			$controller->deleted_note_from_ga4_single( $ga4_annotation_id );
		}

		return wp_delete_post( $note_id, true );
	}

	public function delete_category($id = 0)
	{
		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			return;
		}
		return wp_delete_term($id, 'monsterinsights_note_category');
	}

	/**
	 * Create or update a note in GA4.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The ID of the post. 
	 * @param array $data The data of the note.
	 *
	 * @return void
	 */
	public function create_note_in_ga4( $post_id, $data )
	{
		// Check if user is authenticated for GA4
		if ( !( MonsterInsights()->auth->is_authed() || MonsterInsights()->auth->is_network_authed() ) ) {
			return; // Exit if not authenticated
		}

		// Check if this is an update (post existed previously)
		$is_update = isset( $data['id'] ) && ! empty( $data['id'] );
		
		// Prepare annotation data for GA4
		$annotation = array(
			'title'           => $data['note'],
			'annotation_date' => ! empty( $data['date'] ) ? $data['date'] : current_datetime()->format( 'Y-m-d' ),
			'description'     => $data['note'], // Use note as description
		);

		// Prepare API request options
		$api_options = array();
		
		// Add network flag if needed
		if ( ! MonsterInsights()->auth->is_authed() && MonsterInsights()->auth->is_network_authed() ) {
			$api_options['network'] = true;
		}

		// Determine if we should create or update
		if ( $is_update ) {
			// Get existing GA4 annotation ID for update
			$existing_ga4_id = get_post_meta( $post_id, '_ga4_annotation_id', true );
			
			if ( ! empty( $existing_ga4_id ) ) {
				// Update existing annotation
				$api = new MonsterInsights_API_Request( 'analytics/reports/annotations/update', $api_options, 'POST' );
				
				// Add the GA4 annotation ID to the annotation data for update
				$annotation['id'] = $existing_ga4_id;
				
				// Set additional data with annotation for update
				$api->set_additional_data( array(
					'annotations' => array( $annotation ),
				) );
			} else {
				// No GA4 ID found, create new annotation
				$api = new MonsterInsights_API_Request( 'analytics/reports/annotations/', $api_options, 'POST' );
				// Set additional data with annotation for creation
				$api->set_additional_data( array(
					'annotations' => array( $annotation ),
				) );
			}
		} else {
			// Create new annotation
			$api = new MonsterInsights_API_Request( 'analytics/reports/annotations/', $api_options, 'POST' );
			
			// Set additional data with annotation for creation
			$api->set_additional_data( array(
				'annotations' => array( $annotation ),
			) );
		}

		// Make the API request
		$response = $api->request();
		if ( ! is_wp_error( $response ) ) {
			if ( $is_update && ! empty( $existing_ga4_id ) ) {
				// Update successful - annotation ID remains the same
				// No need to update post meta as it already exists
			} else {
				// Creation successful - store the new GA4 annotation ID
				if ( isset( $response['created'][0]['annotation']['id'] ) ) {
					update_post_meta( $post_id, '_ga4_annotation_id', $response['created'][0]['annotation']['id'] );
				}
			}
		}
	}
}
