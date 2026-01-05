<?php

class MonsterInsights_SiteNotes_Controller {

	public static $instance;

	/**
	 * @var MonsterInsights_Site_Notes_DB_Base
	 */
	private $db;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if (!isset(self::$instance) && !(self::$instance instanceof MonsterInsights_SiteNotes_Controller)) {
			self::$instance = new MonsterInsights_SiteNotes_Controller();
		}
		return self::$instance;
	}

	public function run() {
		$this->load_dependencies();
		$this->add_hooks();
	}

	public function load_dependencies() {
		require_once MONSTERINSIGHTS_PLUGIN_DIR . 'includes/admin/site-notes/Database.php';
		$this->db = new MonsterInsights_Site_Notes_DB_Base();
	}

	public function add_hooks() {
		add_action('init', array($this->db, 'install'));
		add_action('wp_ajax_monsterinsights_vue_get_notes', array($this, 'get_notes'));
		add_action('wp_ajax_monsterinsights_vue_get_note', array($this, 'get_note'));
		add_action('wp_ajax_monsterinsights_vue_get_categories', array($this, 'get_categories'));
		add_action('wp_ajax_monsterinsights_vue_save_note', array($this, 'save_note'));
		add_action('wp_ajax_monsterinsights_vue_save_category', array($this, 'save_category'));
		add_action('wp_ajax_monsterinsights_vue_trash_notes', array($this, 'trash_notes'));
		add_action('wp_ajax_monsterinsights_vue_restore_notes', array($this, 'restore_notes'));
		add_action('wp_ajax_monsterinsights_vue_delete_notes', array($this, 'delete_notes'));
		add_action('wp_ajax_monsterinsights_vue_delete_categories', array($this, 'delete_categories'));
		add_action( 'wp_ajax_monsterinsights_vue_export_notes', array( $this, 'export_notes_to_ga4' ) );
		add_action( 'wp_ajax_monsterinsights_vue_import_notes', array( $this, 'import_notes_from_ga4' ) );

		add_action('init', array($this, 'register_meta'));

		add_action('wp_after_insert_post', array($this, 'create_note_with_post'));

		if (!is_admin()) {
			return;
		}

		add_filter('monsterinsights_report_overview_data', array($this, 'prepare_data_overview_chart'));
		add_filter('monsterinsights_report_traffic_sessions_chart_data', array($this, 'prepare_traffic_sessions_chart_data'), 10, 3);
		add_action('save_post', array($this, 'save_custom_fields'));
		add_filter('monsterinsights_gutenberg_tool_vars', array($this, 'add_categories_to_editor'));
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
		add_action('admin_init', array($this->db, 'insert_default_categories'));
		add_action('admin_init', array($this, 'export_notes'));
		add_filter('wp_untrash_post_status', array($this, 'change_restore_note_status'), 10, 3);
		add_action('monsterinsights_after_exclude_metabox', array($this, 'add_metabox_contents'), 11, 2);
		add_action('admin_enqueue_scripts', array($this, 'load_metabox_assets'));
	}

	private function prepare_notes($params) {
		$args = wp_parse_args($params, array(
			'per_page' => 10,
			'page' => 1,
			'orderby' => 'id',
			'order' => 'desc',
			'filter' => [
				'status' => 'all',
				'important' => null,
				'date_range' => null,
				'category' => null,
			],
		));

		switch ($args['orderby']) {
			case 'note_date':
				$args['orderby'] = 'date';
				break;
			case 'note_title':
				$args['orderby'] = 'title';
				break;
		}

		if ('note_date' === $args['orderby']) {
			$args['orderby'] = 'date';
		}

		if ('note_date' === $args['orderby']) {
			$args['orderby'] = 'date';
		}

		if (!empty($params['search'])) {
			$args['search'] = $args['search'];
		}

		return $this->db->get_items($args);
	}

	/**
	 * AJAX callback function to get notes.
	 */
	public function get_notes() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_view_dashboard' ) && ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to view notes.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$params = !empty($_POST['params']) ? json_decode(html_entity_decode(stripslashes($_POST['params'])), true) : [];

		$output = $this->prepare_notes($params);

		$num_posts = wp_count_posts('monsterinsights_note', 'readable');

		if ($num_posts) {
			$output['status_filters'] = array(
				array(
					'status' => 'all',
					'count'  => array_sum((array) $num_posts) - $num_posts->trash,
				),
			);

			foreach ($num_posts as $status => $count) {
				if (0 >= $count) {
					continue;
				}

				$output['status_filters'][] = array(
					'status' => $status,
					'count'  => $count,
				);
			}
		}

		wp_send_json($output);
	}

	/**
	 * AJAX callback function to get a note.
	 */
	public function get_note() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_view_dashboard' ) && ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to view notes.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$id = !empty($_POST['id']) ? intval($_POST['id']) : null;
		$item = $this->db->get($id);

		if (is_wp_error($item)) {
			wp_send_json(
				array(
					'success' => false,
					'message' => $item->get_error_message(),
				)
			);
		}

		wp_send_json($item);
	}

	/**
	 * AJAX callback function to get categories.
	 */
	public function get_categories() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_view_dashboard' ) && ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to view notes categories.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$params = !empty($_POST['params']) ? json_decode(html_entity_decode(stripslashes($_POST['params'])), true) : [];

		$args = wp_parse_args($params, array(
			'per_page' => -1,
			'page' => 1,
			'orderby' => 'name',
			'order' => 'asc',
		));

		$total = intval($this->db->get_categories($args, true));

		if ($total) {
			$items = $this->db->get_categories($args);
		} else {
			$items = array();
		}

		wp_send_json(
			array(
				'items' => $items,
				'pagination' => array(
					'total' => $total,
					'pages' => ceil($total / $args['per_page']),
					'page'  => $args['page'],
					'per_page' => $args['per_page'],
				),
			)
		);
	}

	/**
	 * AJAX Callback function to save a note.
	 */
	public function save_note() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to update notes.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$note = !empty($_POST['note']) ? json_decode(html_entity_decode(stripslashes($_POST['note']))) : [];

		$note_details = array(
			'note' => sanitize_text_field($note->note_title),
			'category' => intval(is_object($note->category) && isset($note->category->id) && intval($note->category->id) ? $note->category->id : 0),
			'date' => $note->note_date_ymd,
			'medias' => !empty($note->medias) ? array_values(array_keys((array) $note->medias)) : [],
			'important' => isset($note->important) ? $note->important : false,
		);

		if ($note->id) {
			// Update Site Note.
			$note_details['id'] = $note->id;
		}

		$note_id = $this->db->create($note_details);

		if (is_wp_error($note_id)) {
			wp_send_json(
				array(
					'published' => false,
					'message' => $note_id->get_error_message(),
				)
			);
		}

		wp_send_json(
			array(
				'published' => true,
				'message' => '',
				'id' => $note_id,
			)
		);
	}

	/**
	 * AJAX Callback function to save a category.
	 */
	public function save_category() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to update categories.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$category = !empty($_POST['category']) ? json_decode(html_entity_decode(stripslashes($_POST['category']))) : [];

		if (empty($category->name)) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __('Please add a category name', 'google-analytics-for-wordpress'),
				)
			);
		}

		if (200 < mb_strlen($category->name)) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __('You can\'t exceed the 200 characters length for each site note.', 'google-analytics-for-wordpress'),
				)
			);
		}

		$category_id = $this->db->create_category(array(
			'id' => $category->id,
			'name' => $category->name,
			'background_color' => sanitize_hex_color($category->background_color),
		));

		if (is_wp_error($category_id)) {
			wp_send_json(
				array(
					'published' => false,
					'message' => $category_id->get_error_message(),
				)
			);
		}

		wp_send_json(
			array(
				'published' => true,
				'message' => '',
				'id' => $category_id,
			)
		);
	}

	public function change_restore_note_status($new_status, $post_id, $previous_status) {
		if ('monsterinsights_note' !== get_post_type($post_id)) {
			return $new_status;
		}

		return $previous_status;
	}

	/**
	 * AJAX Callback function to trash notes.
	 */
	public function trash_notes() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to update notes.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$ids = !empty($_POST['ids']) ? json_decode(html_entity_decode(stripslashes($_POST['ids']))) : [];

		if (empty($ids)) {
			wp_send_json(
				array(
					'success' => false,
					'message' => __('Please choose a site note to trash!', 'google-analytics-for-wordpress'),
				)
			);
		}

		foreach ($ids as $id) {
			$this->db->trash_note($id);
		}

		wp_send_json(
			array(
				'success' => true,
				'message' => '',
			)
		);
	}

	/**
	 * AJAX Callback function to restore notes.
	 */
	public function restore_notes() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to update notes.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$ids = !empty($_POST['ids']) ? json_decode(html_entity_decode(stripslashes($_POST['ids']))) : [];

		if (empty($ids)) {
			wp_send_json(
				array(
					'success' => false,
					'message' => __('Please choose a site note(s) to restore!', 'google-analytics-for-wordpress'),
				)
			);
		}

		foreach ($ids as $id) {
			$this->db->restore_note($id);
		}

		wp_send_json(
			array(
				'success' => true,
				'message' => '',
			)
		);
	}

	/**
	 * AJAX callback function to delete notes.
	 */
	public function delete_notes() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to update notes.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$ids = !empty($_POST['ids']) ? json_decode(html_entity_decode(stripslashes($_POST['ids']))) : [];

		if (empty($ids)) {
			wp_send_json(
				array(
					'success' => false,
					'message' => __('Please choose a site note(s) to delete!', 'google-analytics-for-wordpress'),
				)
			);
		}

		foreach ($ids as $id) {
			$this->db->delete_note($id);
		}

		wp_send_json(
			array(
				'success' => true,
				'message' => '',
			)
		);
	}

	/**
	 * AJAX callback function to delete categories.
	 */
	public function delete_categories() {
		check_ajax_referer('mi-admin-nonce', 'nonce');

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_send_json(
				array(
					'published' => false,
					'message' => __( "You don't have permission to update notes.", 'google-analytics-for-wordpress' ),
				)
			);
		}

		$ids = !empty($_POST['ids']) ? json_decode(html_entity_decode(stripslashes($_POST['ids']))) : [];

		if (empty($ids)) {
			wp_send_json(
				array(
					'success' => false,
					'message' => __('Please choose a category to delete!', 'google-analytics-for-wordpress'),
				)
			);
		}

		foreach ($ids as $id) {
			$this->db->delete_category($id);
		}

		wp_send_json(
			array(
				'success' => true,
				'message' => '',
			)
		);
	}

	public function export_notes() {
		if (!isset($_POST['monsterinsights_action']) || empty($_POST['monsterinsights_action'])) {
			return;
		}

		if (!current_user_can('monsterinsights_save_settings')) {
			return;
		}

		if ('monsterinsights_export_notes' !== $_POST['monsterinsights_action']) {
			return;
		}

		check_admin_referer('mi-admin-nonce', 'nonce');

		//Generate the CSV.
		ignore_user_abort(true);

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=monsterinsights-notes-export-' . wp_date('m-d-Y') . '.csv');
		header("Expires: 0");

		$args = array(
			'per_page' => -1,
			'status' => ['publish', 'trash'],
		);

		$items = $this->db->get_items($args);
		$headers = array(
			__('Date', 'google-analytics-for-wordpress'),
			__('Site Note', 'google-analytics-for-wordpress'),
			__('Category', 'google-analytics-for-wordpress'),
			__('Important', 'google-analytics-for-wordpress'),
			__('Media', 'google-analytics-for-wordpress'),
		);

		$outstream = fopen("php://output", "wb");

		// phpcs:ignore
		fputcsv($outstream, $headers);

		foreach ($items['items'] as $item) {
			$item_media = __('NA', 'google-analytics-for-wordpress');
			foreach ($item['medias'] as $media) {
				if (isset($media['url'])) {
					$item_media = $media['url'];
				}
			}
			$row = array(
				$item['note_date'],
				$item['note_title'],
				!empty($item['category']) ? $item['category']['name'] : 'N/A',
				intval($item['important']),
				$item_media
			);

			// phpcs:ignore
			fputcsv($outstream, $row);
		}

		fclose($outstream);
		exit;
	}
	/**
	 * AJAX callback function to export notes to GA4.
	 */
	public function export_notes_to_ga4() {
		if (
			! isset( $_POST['action'] ) ||
			'monsterinsights_vue_export_notes' !== $_POST['action']
		) {
			return;
		}

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_die(
				esc_html__(
					'You do not have sufficient permissions to access this page.',
					'google-analytics-for-wordpress'
				)
			);
		}

		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		$annotations = isset( $_POST['annotations'] ) ? json_decode( stripslashes( $_POST['annotations'] ), true ) : array();
		if ( empty( $annotations ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No annotations data provided.', 'google-analytics-for-wordpress' ),
				)
			);
		}

		// Check if user is authenticated.
		if (
			! ( MonsterInsights()->auth->is_authed() || MonsterInsights()->auth->is_network_authed() )
		) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be properly authenticated with MonsterInsights to export annotations.', 'google-analytics-for-wordpress' ),
				)
			);
		}

		// Prepare API request options.
		$api_options = array();

		// Add network flag if needed.
		if (
			! MonsterInsights()->auth->is_authed() &&
			MonsterInsights()->auth->is_network_authed()
		) {
			$api_options['network'] = true;
		}

		// Create API request.
		$api = new MonsterInsights_API_Request( 'analytics/reports/annotations/', $api_options, 'POST' );

		// Set additional data with annotations.
		$api->set_additional_data(
			array(
				'annotations' => $annotations,
				'source'      => 'site-notes-export',
			)
		);

		// Make the API request.
		$response = $api->request();
		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => $response->get_error_message(),
				)
			);
		}

		// Update post meta with GA4 annotation IDs if response is successful
		if ( isset( $response['success'] ) && $response['success'] && isset( $response['created'] ) && is_array( $response['created'] ) ) {
			foreach ( $response['created'] as $created_annotation ) {
				if ( ! isset( $created_annotation['annotation'] ) || ! isset( $created_annotation['annotation']['id'] ) ) {
					continue;
				}

				$ga4_annotation_id = $created_annotation['annotation']['id'];
				$ga4_title = isset( $created_annotation['annotation']['title'] ) ? $created_annotation['annotation']['title'] : '';
				$ga4_date = isset( $created_annotation['annotation']['annotationDate'] ) ? $created_annotation['annotation']['annotationDate'] : array();
				// Find matching annotation in the original annotations array
				foreach ( $annotations as $annotation ) {
					$annotation_title = isset( $annotation['title'] ) ? $annotation['title'] : '';
					$annotation_date = isset( $annotation['annotation_date'] ) ? $annotation['annotation_date'] : '';
					$annotation_id = isset( $annotation['id'] ) ? $annotation['id'] : 0;

					// Format GA4 date to match annotation date format
					$ga4_formatted_date = '';
					if ( is_array( $ga4_date ) && isset( $ga4_date['year'] ) && isset( $ga4_date['month'] ) && isset( $ga4_date['day'] ) ) {
						$ga4_formatted_date = sprintf( '%04d-%02d-%02d', $ga4_date['year'], $ga4_date['month'], $ga4_date['day'] );
					}

					// Match by title and date
					if ( $annotation_title === $ga4_title && $annotation_date === $ga4_formatted_date && $annotation_id > 0 ) {
						update_post_meta( $annotation_id, '_ga4_annotation_id', $ga4_annotation_id );
						break;
					}
				}
			}
		}

		monsterinsights_update_option( 'site_notes_export_synced', 1 );

		// Return success response.
		wp_send_json_success(
			array(
				'message' => __( 'Annotations exported successfully.', 'google-analytics-for-wordpress' ),
				'data'    => $response,
			)
		);
	}

	/**
	 * Delete a single note from GA4.
	 *
	 * @param int $note_id The note ID.
	 * @param string $ga4_annotation_id The GA4 annotation ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function deleted_note_from_ga4_single($ga4_annotation_id) {
		// Check if user is authenticated
		if (!(MonsterInsights()->auth->is_authed() || MonsterInsights()->auth->is_network_authed())) {
			return new WP_Error('not_authenticated', __('You must be properly authenticated with MonsterInsights to delete annotations.', 'google-analytics-for-wordpress'));
		}

		// Prepare API request options
		$api_options = array();
		
		// Add network flag if needed
		if (!MonsterInsights()->auth->is_authed() && MonsterInsights()->auth->is_network_authed()) {
			$api_options['network'] = true;
		}

		// Create API request with DELETE method
		$api = new MonsterInsights_API_Request('analytics/reports/annotations/delete', $api_options, 'POST');
		
		// Set additional data with GA4 annotation ID
		$api->set_additional_data(array(
			'ga_note_ids' => array($ga4_annotation_id),
		));

		// Make the API request
		$response = $api->request();
		if (is_wp_error($response)) {
			return $response;
		}

		return true;
	}

	/**
	 * AJAX callback function to import notes from GA4.
	 */
	public function import_notes_from_ga4() {
		if (
			! isset( $_POST['action'] ) ||
			'monsterinsights_vue_import_notes' !== $_POST['action']
		) {
			return;
		}

		if ( ! current_user_can( 'monsterinsights_save_settings' ) ) {
			wp_die(
				esc_html__(
					'You do not have sufficient permissions to access this page.',
					'google-analytics-for-wordpress'
				)
			);
		}

		check_ajax_referer( 'mi-admin-nonce', 'nonce' );

		// Check if user is authenticated.
		if (
			! ( MonsterInsights()->auth->is_authed() || MonsterInsights()->auth->is_network_authed() )
		) {
			wp_send_json_error(
				array(
					'message' => esc_html__(
						'You must be properly authenticated with MonsterInsights to import annotations.',
						'google-analytics-for-wordpress'
					),
				)
			);
		}

		// Prepare API request options.
		$api_options = array();

		// Add network flag if needed.
		if (
			! MonsterInsights()->auth->is_authed() &&
			MonsterInsights()->auth->is_network_authed()
		) {
			$api_options['network'] = true;
		}

		// Create API request for GET method.
		$api = new MonsterInsights_API_Request(
			'analytics/reports/annotations/',
			$api_options,
			'GET'
		);
		
		// Make the API request.
		$response = $api->request();

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => $response->get_error_message(),
				)
			);
		}
		// Check if response contains annotations data.
		if (
			empty( $response ) ||
			! isset( $response['data']['annotations'] ) ||
			empty( $response['data']['annotations'] )
		) {
			wp_send_json_error(
				array(
					'message' => __(
						'No annotations found to import.',
						'google-analytics-for-wordpress'
					),
				)
			);
		}

		$imported_count = 0;
		$errors         = array();
		$skipped_count  = 0;

		// Process each annotation and create site notes.
		foreach ( $response['data']['annotations'] as $annotation ) {
			// Check if annotation already exists by GA4 ID.
			$ga4_annotation_id = isset( $annotation['id'] ) ? sanitize_text_field( $annotation['id'] ) : '';
			
			if ( ! empty( $ga4_annotation_id ) && $this->annotation_exists( $ga4_annotation_id ) ) {
				$skipped_count++;
				continue;
			}

			// Prepare note details based on annotation data.
			$note_details = array(
				'note'      => isset( $annotation['title'] ) ? sanitize_text_field( $annotation['title'] ) : '',
				'category'  => 0, // Default category, can be mapped later if needed.
				'date'      => $this->format_annotation_date( $annotation['annotationDate'] ),
				'medias'    => array(),
				'important' => false, // GA4 doesn't have important flag, default to false
			);

			// Skip if note is empty.
			if ( empty( $note_details['note'] ) ) {
				$errors[] = sprintf(
					__(
						'Skipped annotation with empty title (ID: %s)',
						'google-analytics-for-wordpress'
					),
					$ga4_annotation_id ?: 'unknown'
				);
				continue;
			}

			// Create the note using the existing create_note method.
			$note_id = $this->create_note( $note_details );

			if ( is_wp_error( $note_id ) ) {
				$errors[] = sprintf(
					__(
						'Failed to import annotation "%s": %s',
						'google-analytics-for-wordpress'
					),
					$note_details['note'],
					$note_id->get_error_message()
				);
			} else {
				// Store the GA4 annotation ID as post meta for future duplicate checking.
				if ( ! empty( $ga4_annotation_id ) ) {
					update_post_meta( $note_id, '_ga4_annotation_id', $ga4_annotation_id );
				}
				$imported_count++;
			}
		}

		// Prepare response message.
		$message = sprintf(
			__(
				'Successfully imported %d annotations.',
				'google-analytics-for-wordpress'
			),
			$imported_count
		);

		if ( $skipped_count > 0 ) {
			$message .= ' ' . sprintf(
				__( '%d annotations were skipped (already exist).', 'google-analytics-for-wordpress' ),
				$skipped_count
			);
		}

		if ( ! empty( $errors ) ) {
			$message .= ' ' . sprintf(
				__(
					'%d annotations could not be imported.',
					'google-analytics-for-wordpress'
				),
				count( $errors )
			);
		}

		monsterinsights_update_option( 'site_notes_import_synced', 1 );

		// Return success response.
		wp_send_json_success(
			array(
				'message'        => $message,
				'imported_count' => $imported_count,
				'skipped_count'  => $skipped_count,
				'error_count'    => count( $errors ),
				'errors'         => $errors,
				'data'           => $response,
			)
		);
	}

	/**
	 * Format GA4 annotation date to YYYY-MM-DD format.
	 *
	 * @param array $annotation_date The annotation date array from GA4.
	 * @return string Formatted date string.
	 */
	private function format_annotation_date( $annotation_date ) {
		if ( ! is_array( $annotation_date ) ) {
			return wp_date( 'Y-m-d' );
		}

		$year  = isset( $annotation_date['year'] ) ? intval( $annotation_date['year'] ) : 0;
		$month = isset( $annotation_date['month'] ) ? intval( $annotation_date['month'] ) : 0;
		$day   = isset( $annotation_date['day'] ) ? intval( $annotation_date['day'] ) : 0;

		// Validate date components
		if ( $year < 1900 || $year > 2100 || $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
			return wp_date( 'Y-m-d' );
		}

		// Format as YYYY-MM-DD
		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Check if an annotation with the given GA4 ID already exists.
	 *
	 * @param string $ga4_annotation_id The GA4 annotation ID to check.
	 * @return bool True if annotation exists, false otherwise.
	 */
	private function annotation_exists( $ga4_annotation_id ) {
		$args = array(
			'post_type'  => 'monsterinsights_note',
			'meta_key'   => '_ga4_annotation_id',
			'meta_value' => $ga4_annotation_id,
			'post_status' => array( 'publish', 'trash' ), // Check both published and trashed notes
			'posts_per_page' => 1,
		);
		$notes = get_posts( $args );
		return ! empty( $notes );
	}

	public function add_categories_to_editor($vars) {
		$args = array(
			'per_page' => 0,
			'page' => 1,
			'orderby' => 'name',
			'order' => 'asc',
		);

		$categories = $this->db->get_categories($args);
		$output_categories = array();

		if ($categories) {
			foreach ($categories as $category) {
				$output_categories[] = (object) array(
					'label' => $category['name'],
					'value' => $category['id'],
				);
			}
		}

		$vars['site_notes_categories'] = $output_categories;
		return $vars;
	}

	public function register_meta() {
		if (!function_exists('register_post_meta')) {
			return;
		}

		register_post_meta(
			'',
			'_monsterinsights_sitenote_active',
			[
				'auth_callback' => '__return_true',
				'default'       => false,
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'boolean',
			]
		);

		register_post_meta(
			'',
			'_monsterinsights_sitenote_note',
			[
				'auth_callback' => '__return_true',
				'default'       => '',
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
			]
		);

		register_post_meta(
			'',
			'_monsterinsights_sitenote_category',
			[
				'auth_callback' => '__return_true',
				'default'       => 0,
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
			]
		);
	}

	public function save_custom_fields($current_post_id) {
		if (!isset($_POST['monsterinsights_metabox_nonce']) || !wp_verify_nonce($_POST['monsterinsights_metabox_nonce'], 'monsterinsights_metabox')) {
			return;
		}

		if ('monsterinsights_note' === get_post_type($current_post_id) || 'publish' !== get_post_status($current_post_id)) {
			return;
		}

		$active = intval(isset($_POST['_monsterinsights_sitenote_active']) ? $_POST['_monsterinsights_sitenote_active'] : 0);
		update_post_meta($current_post_id, '_monsterinsights_sitenote_active', $active);

		if (!$active) {
			delete_post_meta($current_post_id, '_monsterinsights_sitenote_note');
			delete_post_meta($current_post_id, '_monsterinsights_sitenote_category');
			delete_post_meta($current_post_id, '_monsterinsights_sitenote_id');
			return;
		}

		$note = isset($_POST['_monsterinsights_sitenote_note']) ? esc_html($_POST['_monsterinsights_sitenote_note']) : '';
		update_post_meta($current_post_id, '_monsterinsights_sitenote_note', $note);

		$category = isset($_POST['_monsterinsights_sitenote_category']) ? intval($_POST['_monsterinsights_sitenote_category']) : 0;
		if ($category) {
			update_post_meta($current_post_id, '_monsterinsights_sitenote_category', $category);
		}
	}

	public function create_note_with_post($post_ID) {
		if ('monsterinsights_note' === get_post_type($post_ID) || 'publish' !== get_post_status($post_ID)) {
			return;
		}

		$active = get_post_meta($post_ID, '_monsterinsights_sitenote_active', true);

		if (!$active) {
			delete_post_meta($post_ID, '_monsterinsights_sitenote_note');
			delete_post_meta($post_ID, '_monsterinsights_sitenote_category');
			delete_post_meta($post_ID, '_monsterinsights_sitenote_id');
			return;
		}

		$note = get_post_meta($post_ID, '_monsterinsights_sitenote_note', true);

		$category = get_post_meta($post_ID, '_monsterinsights_sitenote_category', true);

		$note_id = get_post_meta($post_ID, '_monsterinsights_sitenote_id', true);

		//create the new note
		$note_details = array(
			'note' => $note,
			'category' => intval($category),
			'date' => wp_date('Y-m-d'),
			'medias' => [],
			'important' => false,
		);

		if (!empty($note_id)) {
			// Update Site Note.
			$note_details['id'] = $note_id;
		}

		$created_note_id = $this->db->create($note_details);
		if (is_wp_error($created_note_id) || $note_id === $created_note_id) {
			return;
		}

		update_post_meta($post_ID, '_monsterinsights_sitenote_id', $created_note_id);
	}

	public function admin_scripts() {
		if (!function_exists('get_current_screen')) {
			return;
		}

		// Get current screen.
		$screen = get_current_screen();

		// Bail if we're not on a MonsterInsights screen.
		if (empty($screen->id) || strpos($screen->id, 'monsterinsights') === false) {
			return;
		}

		wp_enqueue_media();
	}

	public function prepare_data_overview_chart($data) {
		if (!isset($data['data']['overviewgraph'])) {
			return $data;
		}

		$params = array(
			'per_page' => -1,
			'filter' => array(
				'date_range' => array(
					'start' => $data['data']['infobox']['current']['startDate'],
					'end' => $data['data']['infobox']['current']['endDate'],
				),
			),
		);
		$notes = $this->prepare_notes($params);

		$data['data']['overviewgraph']['notes'] = array();

		foreach ($notes['items'] as $note) {
			$date_index = date('j M', strtotime($note['note_date'], current_time('U'))); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We need this to depend on the runtime timezone.
			if (!isset($data['data']['overviewgraph']['notes'][$date_index])) {
				$data['data']['overviewgraph']['notes'][$date_index] = array();
			}
			$data['data']['overviewgraph']['notes'][$date_index][] = array(
				'title' => $note['note_title'],
				'color' => (isset($note['category']) && isset($note['category']['background_color'])) ? str_replace('#', '', $note['category']['background_color']) : null,
				'important' => $note['important'],
			);
		}

		return $data;
	}

	public function add_metabox_contents($skipped, $post) {
		$sitenote_active = get_post_meta($post->ID, '_monsterinsights_sitenote_active', true);
		$sitenote_note = get_post_meta($post->ID, '_monsterinsights_sitenote_note', true);
		$sitenote_category = get_post_meta($post->ID, '_monsterinsights_sitenote_category', true);

		$args = array(
			'per_page' => 0,
			'page' => 1,
			'orderby' => 'name',
			'order' => 'asc',
		);

		$categories = $this->db->get_categories($args); ?>
		<div class="monsterinsights-metabox" id="monsterinsights-metabox-site-notes">
			<div class="monsterinsights-metabox-input monsterinsights-metabox-input-checkbox">
				<label class="">
					<input type="checkbox" name="_monsterinsights_sitenote_active" value="1" <?php checked($sitenote_active); ?>>
					<span class="monsterinsights-metabox-input-checkbox-label"><?php _e('Add a Site Note', 'google-analytics-for-wordpress'); ?></span>
				</label>
			</div>

			<div id="site-notes-active-container" class="<?php echo (!$sitenote_active ? 'hidden' : ''); ?>">
				<div class="monsterinsights-metabox-input monsterinsights-metabox-textarea">
					<textarea name="_monsterinsights_sitenote_note" rows="3"><?php echo esc_textarea($sitenote_note); ?></textarea>
				</div>

				<div class="monsterinsights-metabox-input monsterinsights-metabox-select">
					<label>
						<?php _e('Category', 'google-analytics-for-wordpress'); ?>
						<select name="_monsterinsights_sitenote_category">
							<?php if (!empty($categories)) {
								foreach ($categories as $category) {
							?>
									<option <?php selected($sitenote_category, $category['id']); ?> value="<?php echo esc_attr($category['id']); ?>"><?php echo esc_html($category['name']); ?></option>
							<?php
								}
							} ?>
						</select>
					</label>
				</div>
			</div>

		</div>
<?php
	}

	public function load_metabox_assets() {
		wp_register_style('monsterinsights-admin-metabox-sitenotes-style', plugins_url('assets/css/admin-metabox-sitenotes.css', MONSTERINSIGHTS_PLUGIN_FILE), array(), monsterinsights_get_asset_version());
		wp_enqueue_style('monsterinsights-admin-metabox-sitenotes-style');

		wp_register_script('monsterinsights-admin-metabox-sitenotes-script', plugins_url('assets/js/admin-metabox-sitenotes.js', MONSTERINSIGHTS_PLUGIN_FILE), array('jquery'), monsterinsights_get_asset_version());
		wp_enqueue_script('monsterinsights-admin-metabox-sitenotes-script');
	}

	/**
	 * Add site-note to traffic sessions chart.
	 *
	 * @param array $data
	 * @param string $start_date
	 * @param string $end_date
	 *
	 * @return array
	 */
	public function prepare_traffic_sessions_chart_data( $data, $start_date, $end_date ) {

		if ( ! isset( $data['data']['sessions_chart'] ) ) {
			return $data;
		}

		$params = array(
			'per_page' => - 1,
			'filter'   => array(
				'date_range' => array(
					'start' => $start_date,
					'end'   => $end_date,
				),
			),
		);

		$notes = $this->prepare_notes( $params );

		$prepared_notes = array();

		foreach ( $notes['items'] as $note ) {
			$date_index = date( 'j M', strtotime( $note['note_date'], current_time( 'U' ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- We need this to depend on the runtime timezone.

			if ( ! isset( $prepared_notes[ $date_index ] ) ) {
				$prepared_notes[ $date_index ] = array();
			}

			$prepared_notes[ $date_index ][] = array(
				'title'     => $note['note_title'],
				'color'     => ( isset( $note['category'] ) && isset( $note['category']['background_color'] ) ) ? str_replace( '#', '', $note['category']['background_color'] ) : null,
				'important' => $note['important'],
			);
		}

		$data['data']['sessions_chart']['notes'] = $prepared_notes;

		return $data;
	}

	/**
	 * Create a site note.
	 */
	public function create_note( $note_details ) {
		return $this->db->create( $note_details );
	}

}

MonsterInsights_SiteNotes_Controller::get_instance()->run();
