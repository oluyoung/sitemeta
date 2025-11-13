<?php

/**
 * Plugin Name: Site Meta
 * Description: Manage site-wide metadata (logo, contacts, socials, lists, JSON, galleries).
 * Author: Oluyoung
 * Version: 1.0.0
 * Requires at least: 6.3
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) exit;

define('SITEMETA_VER', '1.0.0');
define('SITEMETA_URL', plugin_dir_url(__FILE__));
define('SITEMETA_PATH', plugin_dir_path(__FILE__));

add_action('admin_menu', function () {
	wp_register_script('site-meta-admin', SITEMETA_URL . 'build/index.js', [], SITEMETA_VER, true);
	wp_register_style('site-meta-admin', SITEMETA_URL . 'build/index.css', [], SITEMETA_VER);

	add_menu_page(
		'Site Meta',
		'Site Meta',
		'manage_options',
		'site-meta',
		'site_meta_admin_page',
		'dashicons-admin-generic',
		58
	);
});

add_action('admin_enqueue_scripts', 'site_meta_enqueue_style_script');

function site_meta_enqueue_style_script($hook_suffix)
{
	if ($hook_suffix !== 'toplevel_page_site-meta') {
		return;
	}

	$asset_file = plugin_dir_path(__FILE__) . 'build/index.asset.php';

	if (! file_exists($asset_file)) {
		return;
	}

	$asset = include $asset_file;

	wp_enqueue_media();
	wp_enqueue_script(
		'site-meta-admin-js',
		plugins_url('build/index.js', __FILE__),
		$asset['dependencies'],
		$asset['version'],
		array(
			'in_footer' => true,
		)
	);
	wp_enqueue_style('site-meta-admin-css', SITEMETA_URL . 'build/index.css', [], SITEMETA_VER);

	wp_localize_script('site-meta-admin-js', 'SiteMetaSettings', [
		'root'   => esc_url_raw(rest_url('site-meta/v1/')),
		'nonce'  => wp_create_nonce('wp_rest'),
		'version' => SITEMETA_VER,
	]);
}

function site_meta_can_manage()
{
	return current_user_can('manage_options');
}

add_action('rest_api_init', function () {
	register_rest_route('site-meta/v1', '/fields', array(
		array(
			'methods'             => WP_REST_Server::READABLE, // GET
			'callback'            => 'site_meta_rest_get_items',
			'permission_callback' => 'site_meta_can_manage',
		),
		array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => 'site_meta_rest_create_field',
			'permission_callback' => 'site_meta_can_manage',
			'args'                => site_meta_rest_args_schema(),
		),
	));

	register_rest_route('site-meta/v1', '/fields/(?P<id>[a-zA-Z0-9_-]+)', array(
		array(
			'methods'             => WP_REST_Server::READABLE, // GET
			'callback'            => 'site_meta_rest_get_field',
			'permission_callback' => 'site_meta_can_manage',
		),
		array(
			'methods'             => WP_REST_Server::EDITABLE, // PUT/PATCH
			'callback'            => 'site_meta_rest_update_field',
			'permission_callback' => 'site_meta_can_manage',
			'args'                => site_meta_rest_args_schema(),
		),
		array(
			'methods'             => WP_REST_Server::DELETABLE, // DELETE
			'callback'            => 'site_meta_rest_delete_field',
			'permission_callback' => 'site_meta_can_manage',
		),
	));
});

// Schema validation (used in rest args)
function site_meta_rest_args_schema()
{
	return [
		'field_id' => [
			'required'          => true,
			'type'              => 'string',
			'validate_callback' => function ($param) {
				return is_string($param) && !empty($param);
			},
		],
		'type' => [
			'required'          => true,
			'type'              => 'string',
			'enum'              => ['text', 'json', 'list', 'image', 'gallery'],
		],
		'content' => [
			'required'          => false,
			'validate_callback' => function ($param, $request, $key) {
				$type = $request->get_param('type');
				switch ($type) {
					case 'image':
						return is_numeric($param) || is_string($param);
					case 'gallery':
						return is_array($param);
					case 'text':
					default:
						return is_string($param);
				}
			},
		],
		'json_content' => [
			'required'          => false,
			'validate_callback' => function ($param, $request, $key) {
				return is_array($param) || is_object($param);
			},
		],
	];
}

// Activate plugin: create the table
register_activation_hook(__FILE__, 'site_meta_activate_plugin');

// Function to handle plugin activation
function site_meta_activate_plugin()
{
	try {
		global $wpdb;
		$table_name = $wpdb->prefix . 'site_meta';

		// Create table if it doesn't exist
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table_name (
      id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      field_id VARCHAR(255) NOT NULL,
			type VARCHAR(50) NOT NULL DEFAULT 'text',
      content TEXT,
      json_content JSON,
      UNIQUE (field_id)
    ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	} catch (Exception $e) {
		error_log('Error during plugin activation: ' . $e->getMessage());
		wp_die('Plugin activation failed. Please check the debug log for details.');
	}
}

register_deactivation_hook(__FILE__, 'site_meta_cleanup_database');

// Function to clean up the database
function site_meta_cleanup_database()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'site_meta';
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

function site_meta_admin_page()
{
	include plugin_dir_path(__FILE__) . './page.php';
}

/** Helpers */
function site_meta_table()
{
	global $wpdb;
	return $wpdb->prefix . 'site_meta';
}

function site_meta_transient_all_key()
{
	return 'sitemeta_cache_all';
}

function site_meta_transient_one_key($id)
{
	return 'sitemeta_cache_' . sanitize_key($id);
}

function site_meta_normalize_payload($id, $type, $content, $json_content)
{
	// Normalize content by type
	switch ($type) {
		case 'image':
			// store attachment ID as int
			$content = is_numeric($content) ? intval($content) : 0;
			break;
		case 'gallery':
			// expect array
			if (!is_array($json_content)) $json_content = array();
			// normalize gallery IDs to ints;
			$json_content = array_values(array_filter(array_map('intval', $json_content)));
			$json_content = wp_json_encode($json_content); // force assoc
			break;
		case 'json':
			// must be object/assoc array
			if (!is_array($json_content) && !is_object($json_content)) $json_content = array();
			$json_content = wp_json_encode($json_content); // force assoc
			break;
		case 'text':
		default:
			$content = is_scalar($content) ? (string) $content : '';
			break;
	}

	$record = array(
		'field_id' => $id,
		'type'     => $type,
		'content'  => $content,
		'json_content' => $json_content
	);

	return $record;
}

function site_meta_row_from_record($record)
{
	// Mirror scalar content to content; full canonical object to json_content
	$content = null;
	$json_content = null;

	if (in_array($record['type'], array('text', 'image'), true)) {
		$content = ($record['type'] === 'image') ? (string) intval($record['content']) : (string) $record['content'];
	}

	$value = $record['json_content'];
	if (is_array($value) || is_object($value)) {
		$json_content = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	} elseif (is_string($value)) {
		// Keep if it's already valid JSON; otherwise encode it
		json_decode($value);
		$json_content = (json_last_error() === JSON_ERROR_NONE) ? $value : wp_json_encode($value);
	} elseif ($value == null) {
		$json_content = null;
	} else {
		// Fallback: encode scalars
		$json_content = wp_json_encode($value);
	}

	return array($content, $json_content);
}

function site_meta_record_from_row($row)
{
	// Prefer JSON
	$decoded = array();
	if (!empty($row->json_content)) {
		$decoded = json_decode($row->json_content, true);
	}
	$id    = $row->field_id;
	$type  = isset($decoded['type']) ? $decoded['type'] : 'text';
	$cont  = array_key_exists('content', $decoded) ? $decoded['content'] : $row->content;
	$json  = array_key_exists('json_content', $decoded) ? $decoded['json_content'] : $row->json_content;

	// Coerce some types in case of legacy rows
	if ($type === 'image') $cont = is_numeric($cont) ? intval($cont) : 0;
	if ($type === 'gallery') {
		if (!is_array($json)) $json = array();
	}

	return array(
		'field_id'      => $id,
		'type'    => $type,
		'content' => $cont,
		'json_content' => $json,
	);
}

function site_meta_cache_set_all($data)
{
	set_transient(site_meta_transient_all_key(), $data, MONTH_IN_SECONDS);
}

function site_meta_cache_get_all()
{
	return get_transient(site_meta_transient_all_key());
}

function site_meta_cache_set_one($id, $data)
{
	set_transient(site_meta_transient_one_key($id), $data, MONTH_IN_SECONDS);
}

function site_meta_cache_get_one($id)
{
	return get_transient(site_meta_transient_one_key($id));
}

function site_meta_cache_invalidate($id = null)
{
	if ($id) {
		delete_transient(site_meta_transient_one_key($id));
	}
	delete_transient(site_meta_transient_all_key());
}

/** REST: List */
function site_meta_rest_list_fields(WP_REST_Request $req)
{
	$cached = site_meta_cache_get_all();
	if (false !== $cached) {
		return new WP_REST_Response($cached, 200);
	}

	global $wpdb;
	$table = site_meta_table();
	$rows  = $wpdb->get_results("SELECT field_id, type, content, json_content FROM {$table} ORDER BY id ASC");

	$list = array();
	if ($rows) {
		foreach ($rows as $row) {
			$list[] = site_meta_record_from_row($row);
		}
	}

	site_meta_cache_set_all($list);
	return new WP_REST_Response($list, 200);
}

/** REST: Get One */
function site_meta_rest_get_field(WP_REST_Request $req)
{
	$id = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $req['id']));
	if (empty($id)) {
		return new WP_Error('sitemeta_invalid_id', 'Invalid ID', array('status' => 400));
	}

	$cached = site_meta_cache_get_one($id);
	if (false !== $cached) {
		return new WP_REST_Response($cached, 200);
	}

	global $wpdb;
	$table = site_meta_table();
	$row   = $wpdb->get_row($wpdb->prepare(
		"SELECT field_id, content, type, json_content FROM {$table} WHERE field_id = %s LIMIT 1",
		$id
	));

	if (!$row) {
		return new WP_Error('sitemeta_not_found', 'Not found', array('status' => 404));
	}

	$record = site_meta_record_from_row($row);
	site_meta_cache_set_one($id, $record);
	return new WP_REST_Response($record, 200);
}

/**
 * GET handler: fetch all site meta records
 * - Returns cached data if available
 * - Decodes JSON fields into arrays/objects
 * - Unserializes lists into arrays
 */
function site_meta_rest_get_items($request)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'site_meta';

	// Try cached response
	$cached = site_meta_cache_get_all();
	if (false !== $cached) {
		return rest_ensure_response($cached);
	}

	// Fetch all records from DB
	$rows = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
	$items = [];

	foreach ($rows as $row) {
		$content = null;
		$json_content = null;

		// If JSON field is not null
		if (! empty($row['json_content'])) {
			$decoded = json_decode($row['json_content'], true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$json_content = $decoded;
			}
		}
		// Else fall back to value field
		else {
			$maybe_unserialized = maybe_unserialize($row['content']);
			$content = $maybe_unserialized;
		}

		$items[] = [
			'field_id'    => $row['field_id'],
			'type'        => $row['type'],
			'content'     => $content,
			'json_content' => $json_content
		];
	}

	set_transient('site_meta_all', $items, MONTH_IN_SECONDS);
	return rest_ensure_response($items);
}

/** REST: Create */
function site_meta_rest_create_field(WP_REST_Request $req)
{
	$params  = $req->get_json_params();
	$id      = isset($params['field_id']) ? strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $params['field_id'])) : '';
	$type    = isset($params['type']) ? $params['type'] : '';
	$content = isset($params['content']) ? $params['content'] : null;
	$json_content = isset($params['json_content']) ? $params['json_content'] : null;

	if (empty($id) || empty($type)) {
		return new WP_Error('sitemeta_missing_params', 'ID and type are required', array('status' => 400));
	}
	if (!in_array($type, array('text', 'json', 'list', 'image', 'gallery', 'keyvalue'), true)) {
		return new WP_Error('sitemeta_invalid_type', 'Invalid type', array('status' => 400));
	}

	$record = site_meta_normalize_payload($id, $type, $content, $json_content);
	list($content, $json_content) = site_meta_row_from_record($record);

	global $wpdb;
	$table = site_meta_table();

	// If exists -> error; else insert
	$existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE field_id = %s", $id));
	if ($existing) {
		return new WP_Error('sitemeta_exists', 'Field already exists', array('status' => 409));
	}

	$inserted = $wpdb->insert(
		$table,
		array(
			'field_id'    => $id,
			'content' => $content,
			'type'    => $type,
			'json_content'  => $json_content,
		),
		array('%s', '%s', '%s', '%s')
	);

	if (false === $inserted) {
		return new WP_Error('sitemeta_db_error', 'Insert failed', array('status' => 500));
	}

	site_meta_cache_invalidate(); // all list cache
	site_meta_cache_set_one($id, $record);

	return new WP_REST_Response($record, 201);
}

/** REST: Update */
function site_meta_rest_update_field(WP_REST_Request $req)
{
	$id_route = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $req['field_id']));

	$params  = $req->get_json_params();
	$id      = isset($params['field_id']) ? strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $params['field_id'])) : '';
	$type    = isset($params['type']) ? $params['type'] : '';
	$content = isset($params['content']) ? $params['content'] : null;
	$json_content = isset($params['json_content']) ? $params['json_content'] : null;

	if (empty($id) || $id !== $id_route) {
		return new WP_Error('sitemeta_id_mismatch', 'Body ID must match URL ID', array('status' => 400));
	}
	if (!in_array($type, array('text', 'json', 'list', 'image', 'gallery', 'keyvalue'), true)) {
		return new WP_Error('sitemeta_invalid_type', 'Invalid type', array('status' => 400));
	}

	$record = site_meta_normalize_payload($id, $type, $content, $json_content);
	list($content, $json_content) = site_meta_row_from_record($record);

	global $wpdb;
	$table = site_meta_table();

	$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE field_id = %s", $id));

	if ($exists) {
		$updated = $wpdb->update(
			$table,
			array(
				'content' => $content,
				'type'    => $type,
				'json_content'  => $json_content,
			),
			array('field_id' => $id),
			array('%s', '%s', '%s'),
			array('%s')
		);
		if (false === $updated) {
			return new WP_Error('sitemeta_db_error', 'Update failed', array('status' => 500));
		}
	} else {
		$inserted = $wpdb->insert(
			$table,
			array(
				'field_id'    => $id,
				'content' => $content,
				'type'    => $type,
				'json_content'  => $json_content,
			),
			array('%s', '%s', '%s', '%s')
		);
		if (false === $inserted) {
			return new WP_Error('sitemeta_db_error', 'Insert failed', array('status' => 500));
		}
	}

	site_meta_cache_invalidate($id);
	site_meta_cache_invalidate(); // list cache too
	site_meta_cache_set_one($id, $record);

	return new WP_REST_Response($record, 200);
}

function site_meta_rest_delete_field(WP_REST_Request $req)
{
	$id = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $req['id']));
	if (empty($id)) {
		return new WP_Error('sitemeta_invalid_id', 'Invalid ID', array('status' => 400));
	}

	global $wpdb;
	$table = site_meta_table();

	$deleted = $wpdb->delete($table, array('field_id' => $id), array('%s'));
	if (false === $deleted) {
		return new WP_Error('sitemeta_db_error', 'Delete failed', array('status' => 500));
	}
	if (0 === intval($deleted)) {
		return new WP_Error('sitemeta_not_found', 'Not found', array('status' => 404));
	}

	site_meta_cache_invalidate($id);
	site_meta_cache_invalidate(); // list

	return new WP_REST_Response(array('deleted' => true, 'id' => $id), 200);
}

/**
 * Fetch a site meta field by ID.
 *
 * @param string $id  The field_id to fetch.
 * @param bool   $raw If true, return the raw DB row.
 * @return array|null { 'id' => string, 'type' => string, 'value' => mixed }
 */
function site_meta_get($id, $raw = false)
{
	$cached = site_meta_cache_get_one($id);
	if (false !== $cached) {
		return $cached;
	}

	global $wpdb;
	$table = site_meta_table();

	// Expect schema with type/content/json_content
	$row = $wpdb->get_row(
		$wpdb->prepare("SELECT field_id, type, content, json_content FROM $table WHERE field_id = %s LIMIT 1", $id),
		ARRAY_A
	);
	var_dump($row);

	if (!$row) {
		return null;
	}

	// Decode JSON or unserialize where needed
	$value = null;
	$json_value = null;
	if ($row['json_content'] !== null) {
		$json_value = json_decode($row['json_content'], true);
	} else {
		$maybe_unser = maybe_unserialize($row['content']);
		$value       = $maybe_unser !== false ? $maybe_unser : $row['content'];
	}

	$result = [
		'id'    => $row['field_id'],
		'type'  => $row['type'],
		'content' => $value,
		'json_content' => $json_value,
	];

	return $raw ? $row : $result;
}

function site_transient_find_or_create($id, $anonFunc = null, $value = null) {
	$cached = site_meta_cache_get_one($id);
	if (false !== $cached) {
		return $cached;
	}

	if ($anonFunc) $record = $anonFunc();
	if ($value) $record = $value;

	if ($record) {
		site_meta_cache_set_one($id, $record);
		return $record;
	}
}

/**
 * Template tag: echo or return a site meta value (type-aware).
 *
 * @param string $id   The field_id.
 * @param array  $args Options: before, after, echo, format ('url'|'image'), attrs (for wp_get_attachment_image).
 * @return mixed
 */
function site_meta($id, $args = [])
{
	$field = site_meta_get($id);
	if (! $field) {
		return null;
	}
	$defaults = [
		'before' => '',
		'after'  => '',
		'echo'   => true,
		'format' => 'url', // or 'image' for image/gallery
		'attrs'  => [],    // extra attributes for wp_get_attachment_image
	];
	$args = wp_parse_args($args, $defaults);

	$output = '';
	$value  = $field['content'];
	$json_value  = $field['json_content'];

	switch ($field['type']) {
		case 'image':
			if ($args['format'] === 'image') {
				$output = wp_get_attachment_image(intval($value), 'full', false, $args['attrs']);
			} else {
				$output = wp_get_attachment_url(intval($value));
			}
			break;

		case 'gallery':
			$output = [];
			foreach (json_decode($json_value) as $attachment_id) {
				if ($args['format'] === 'image') {
					$output[] = wp_get_attachment_image(intval($attachment_id), 'full', false, $args['attrs']);
				} else {
					$output[] = wp_get_attachment_url(intval($attachment_id));
				}
			}
			break;

		case 'json':
			// Already decoded into assoc/array
			$output = $json_value;
			break;

		case 'text':
		default:
			$output = $value;
			break;
	}

	// Add before/after if string
	if (is_string($output)) {
		$output = $args['before'] . $output . $args['after'];
	}

	if ($args['echo']) {
		if (is_string($output)) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif (is_array($output)) {
			// For debugging / default rendering of gallery/json
			echo esc_html(wp_json_encode($output, JSON_PRETTY_PRINT));
		}
	} else {
		return $output;
	}
}

/**
 * Shortcode handler: [site_meta id="logo" format="image"]
 */
function site_meta_shortcode($atts)
{
	$atts = shortcode_atts(
		[
			'id'     => '',
			'before' => '',
			'after'  => '',
			'format' => 'url', // or "image"
		],
		$atts,
		'site_meta'
	);

	if (empty($atts['id'])) {
		return '';
	}

	return site_meta($atts['id'], [
		'before' => $atts['before'],
		'after'  => $atts['after'],
		'echo'   => false,
		'format' => $atts['format'],
	]);
}

add_shortcode('site_meta', 'site_meta_shortcode');
