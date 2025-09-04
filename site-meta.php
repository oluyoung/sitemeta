<?php

/**
 * Plugin Name: Site Meta
 * Description: Manage site-wide meta (logo, contacts, socials, lists, JSON, galleries) with a React admin UI.
 * Author: You
 * Version: 0.1.0
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

// add_action('admin_enqueue_scripts', function ($hook) {
// 	if ($hook !== 'toplevel_page_site-meta') return;

// 	$asset = [
// 		'dependencies' => ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'],
// 		'version' => SITEMETA_VER
// 	];

// 	wp_enqueue_script('wp-api-fetch');
// 	wp_enqueue_script('site-meta-admin', SITEMETA_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true);
// 	wp_enqueue_style('site-meta-admin', SITEMETA_URL . 'build/index.css', [], SITEMETA_VER);

// 	wp_localize_script('site-meta-admin', 'SiteMetaSettings', [
// 		'root'   => esc_url_raw(rest_url('site-meta/v1/')),
// 		'nonce'  => wp_create_nonce('wp_rest'),
// 		'version' => SITEMETA_VER,
// 	]);
// });


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

	// wp_enqueue_script('wp-api-fetch');
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

/**
 * Minimal in-options storage to make UI usable for Step 1.
 * Later we’ll replace with a custom table + transients.
 */
function sitemeta_get_store()
{
	$data = get_option('site_meta_fields', []);
	return is_array($data) ? $data : [];
}

function sitemeta_set_store($data)
{
	update_option('site_meta_fields', $data, false);
}

function wk_register_custom_routes()
{
	register_rest_route(
		'site-meta/v1',
		'/fields',
		array(
			array(
				'methods'  => 'GET',
				'callback' => 'wk_get_post_callback',
			),
			array(
				'methods'  => 'PUT',
				'callback' => 'wk_put_post_callback',
			),
			array(
				'methods'  => 'DELETE',
				'callback' => 'wk_delete_post_callback'
			)
		)
	);
}

function wk_put_post_callback($request)
{
	// Your code 
}

function wk_delete_post_callback($request)
{
	// Your code 
}

function site_meta_can_manage()
{
	return current_user_can('manage_options');
}

function site_meta_get_fields_callback($request)
{
	return new WP_REST_Response(array_values(sitemeta_get_store()), 200);
}

function site_meta_post_fields_callback($request)
{
	$params = $request->get_json_params();
	$id = sanitize_key($params['field_id'] ?? '');
	if (! $id) {
		return new WP_REST_Response(['message' => 'Missing id'], 400);
	}
	$store = sitemeta_get_store();
	$store[$id] = [
		'field_id' => $id,
		'type'    => sanitize_text_field($params['type'] ?? 'text'),
		'content' => $params['content'] ?? null,
	];
	sitemeta_set_store($store);
	return new WP_REST_Response($store[$id], 200);
}

add_action('rest_api_init', function () {
	// register_rest_route('site-meta/v1', '/fields', [
	// 	[
	// 		'methods'             => 'GET',
	// 		'permission_callback' => 'site_meta_permission_callback',
	// 		'callback'            => 'site_meta_get_fields_callback'
	// 	],
	// 	[
	// 		'methods'             => 'POST',
	// 		'permission_callback' => 'site_meta_permission_callback',
	// 		'callback'            => 'site_meta_post_fields_callback'
	// 	],
	// ]);

	register_rest_route('site-meta/v1', '/fields', array(
		array(
			'methods'             => WP_REST_Server::READABLE, // GET
			'callback'            => 'site_meta_rest_list_fields',
			'permission_callback' => 'site_meta_can_manage',
		),
		array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => 'site_meta_rest_create_field',
			'permission_callback' => 'site_meta_can_manage',
			'args'                => site_meta_rest_args_schema(),
		),
	));

	// register_rest_route('site-meta/v1', '/fields/(?P<id>[a-zA-Z0-9_\-]+)', [
	// 	[
	// 		'methods'             => 'PUT',
	// 		'permission_callback' => function () {
	// 			return current_user_can('manage_options');
	// 		},
	// 		'callback'            => function (WP_REST_Request $req) {
	// 			$id    = sanitize_key($req['id']);
	// 			$data  = $req->get_json_params();
	// 			$store = sitemeta_get_store();
	// 			if (! isset($store[$id])) {
	// 				return new WP_REST_Response(['message' => 'Not found'], 404);
	// 			}
	// 			$store[$id]['type']    = sanitize_text_field($data['type'] ?? $store[$id]['type']);
	// 			$store[$id]['content'] = $data['content'] ?? $store[$id]['content'];
	// 			sitemeta_set_store($store);
	// 			return new WP_REST_Response($store[$id], 200);
	// 		}
	// 	],
	// 	[
	// 		'methods'             => 'DELETE',
	// 		'permission_callback' => function () {
	// 			return current_user_can('manage_options');
	// 		},
	// 		'callback'            => function (WP_REST_Request $req) {
	// 			$id    = sanitize_key($req['id']);
	// 			$store = sitemeta_get_store();
	// 			if (isset($store[$id])) {
	// 				unset($store[$id]);
	// 				sitemeta_set_store($store);
	// 			}
	// 			return new WP_REST_Response(['ok' => true], 200);
	// 		}
	// 	],
	// ]);

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

/** Args schema (basic validation) */
// function site_meta_rest_args_schema() {
// 	return array(
// 		'id' => array(
// 			'type'              => 'string',
// 			'required'          => true,
// 			'validate_callback' => function($value) {
// 				return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $value);
// 			},
// 			'sanitize_callback' => function($value){ return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $value)); },
// 			'description'       => 'Unique field ID (a-z, 0-9, underscore, dash).',
// 		),
// 		'type' => array(
// 			'type'              => 'string',
// 			'required'          => true,
// 			'validate_callback' => function($value) {
// 				return in_array($value, array('text','json','list','image','gallery','keyvalue'), true);
// 			},
// 			'description'       => 'Field type.',
// 		),
// 		'content' => array(
// 			'required'          => true,
// 			// Accept any JSON value; sanitize later per type.
// 		)
// 	);
// }

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
						return is_string($param);
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
function site_meta_table() {
	global $wpdb;
	return $wpdb->prefix . 'site_meta';
}

function site_meta_transient_all_key() {
	return 'sitemeta_cache_all';
}

function site_meta_transient_one_key( $id ) {
	return 'sitemeta_cache_' . sanitize_key($id);
}

function site_meta_normalize_payload( $id, $type, $content, $json_content ) {
	// Normalize content by type
	switch ($type) {
		case 'text':
			$content = is_scalar($content) ? (string) $content : '';
			break;
		case 'image':
			// store attachment ID as int
			$content = is_numeric($content) ? intval($content) : 0;
			break;
		case 'gallery':
		case 'list':
			// expect array
			if (!is_array($content)) $content = array();
			// normalize gallery IDs to ints; list items to strings
			if ($type === 'gallery') {
				$content = array_values( array_filter( array_map('intval', $content) ) );
			} else {
				$content = array_values( array_filter( array_map( function($v){ return is_scalar($v) ? (string)$v : ''; }, $content ) ) );
			}
			break;
		case 'json':
		case 'keyvalue':
			// must be object/assoc array
			if (!is_array($json_content) && !is_object($json_content)) $json_content = array();
			$json_content = json_decode( wp_json_encode($json_content), true ); // force assoc
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

function site_meta_row_from_record( $record ) {
	// Mirror scalar content to content; full canonical object to json_content
	$content = null;

	if (in_array($record['type'], array('text','image'), true)) {
		$content = ( $record['type'] === 'image' ) ? (string) intval($record['content']) : (string) $record['content'];
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

	return array( $content, $json_content );
}

function site_meta_record_from_row( $row ) {
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
	if ($type === 'gallery' || $type === 'list') {
		if (!is_array($cont)) $cont = array();
	}

	return array(
		'field_id'      => $id,
		'type'    => $type,
		'content' => $cont,
		'json_content' => $json,
	);
}

function site_meta_cache_set_all( $data ) {
	set_transient( site_meta_transient_all_key(), $data, MONTH_IN_SECONDS );
}

function site_meta_cache_get_all() {
	return get_transient( site_meta_transient_all_key() );
}

function site_meta_cache_set_one( $id, $data ) {
	set_transient( site_meta_transient_one_key($id), $data, MONTH_IN_SECONDS );
}

function site_meta_cache_get_one( $id ) {
	return get_transient( site_meta_transient_one_key($id) );
}

function site_meta_cache_invalidate( $id = null ) {
	if ($id) {
		delete_transient( site_meta_transient_one_key($id) );
	}
	delete_transient( site_meta_transient_all_key() );
}

/** REST: List */
function site_meta_rest_list_fields( WP_REST_Request $req ) {
	$cached = site_meta_cache_get_all();
	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

	global $wpdb;
	$table = site_meta_table();
	$rows  = $wpdb->get_results( "SELECT field_id, type, content, json_content FROM {$table} ORDER BY field_id ASC" );

	$list = array();
	if ( $rows ) {
		foreach ( $rows as $row ) {
			$list[] = site_meta_record_from_row( $row );
		}
	}

	site_meta_cache_set_all( $list );
	return new WP_REST_Response( $list, 200 );
}

/** REST: Get One */
function site_meta_rest_get_field( WP_REST_Request $req ) {
	$id = strtolower( preg_replace('/[^a-zA-Z0-9_-]/', '', $req['id']) );
	if ( empty($id) ) {
		return new WP_Error('sitemeta_invalid_id', 'Invalid ID', array('status' => 400));
	}

	$cached = site_meta_cache_get_one( $id );
	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

	global $wpdb;
	$table = site_meta_table();
	$row   = $wpdb->get_row( $wpdb->prepare(
		"SELECT field_id, content, type, json_content FROM {$table} WHERE field_id = %s LIMIT 1",
		$id
	) );

	if ( !$row ) {
		return new WP_Error('sitemeta_not_found', 'Not found', array('status' => 404));
	}

	$record = site_meta_record_from_row( $row );
	site_meta_cache_set_one( $id, $record );
	return new WP_REST_Response( $record, 200 );
}

/**
 * GET handler: fetch all site meta records
 * - Returns cached data if available
 * - Decodes JSON fields into arrays/objects
 * - Unserializes lists into arrays
 */
function site_meta_rest_get_items( $request ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'site_meta';

    // Try cached response
    $cached = get_transient( 'site_meta_all' );
    if ( $cached ) {
        return rest_ensure_response( $cached );
    }

    // Fetch all records from DB
    $rows = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
    $items = [];

    foreach ( $rows as $row ) {
        $content = null;
        $json_content = null;

        // If JSON field is not null
        if ( ! empty( $row['json_content'] ) ) {
            $decoded = json_decode( $row['json_content'], true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $json_content = $decoded;
            }
        }
        // Else fall back to value field
        else {
            $maybe_unserialized = maybe_unserialize( $row['content'] );
            $content = $maybe_unserialized;
        }

        $items[] = [
            'field_id'    => $row['field_id'],
            'type'        => $row['type'],
            'content'     => $content,
            'json_content' => $json_content
        ];
    }

    set_transient( 'site_meta_all', $items, MONTH_IN_SECONDS );
    return rest_ensure_response( $items );
}

/** REST: Create */
function site_meta_rest_create_field( WP_REST_Request $req ) {
	$params  = $req->get_json_params();
	$id      = isset($params['field_id']) ? strtolower(preg_replace('/[^a-zA-Z0-9_-]/','', $params['field_id'])) : '';
	$type    = isset($params['type']) ? $params['type'] : '';
	$content = isset($params['content']) ? $params['content'] : null;
	$json_content = isset($params['json_content']) ? $params['json_content'] : null;

	if ( empty($id) || empty($type) ) {
		return new WP_Error('sitemeta_missing_params', 'ID and type are required', array('status' => 400));
	}
	if ( !in_array($type, array('text','json','list','image','gallery','keyvalue'), true) ) {
		return new WP_Error('sitemeta_invalid_type', 'Invalid type', array('status' => 400));
	}

	$record = site_meta_normalize_payload( $id, $type, $content, $json_content );
	list($content, $json_content) = site_meta_row_from_record( $record );

	global $wpdb;
	$table = site_meta_table();

	// If exists -> error; else insert
	$existing = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE field_id = %s", $id) );
	if ( $existing ) {
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
		array('%s','%s','%s', '%s')
	);

	if ( false === $inserted ) {
		return new WP_Error('sitemeta_db_error', 'Insert failed', array('status' => 500));
	}

	site_meta_cache_invalidate(); // all list cache
	site_meta_cache_set_one( $id, $record );

	return new WP_REST_Response( $record, 201 );
}

/** REST: Update */
function site_meta_rest_update_field( WP_REST_Request $req ) {
	$id_route = strtolower( preg_replace('/[^a-zA-Z0-9_-]/','', $req['id']) );

	$params  = $req->get_json_params();
	$id      = isset($params['id']) ? strtolower(preg_replace('/[^a-zA-Z0-9_-]/','', $params['id'])) : '';
	$type    = isset($params['type']) ? $params['type'] : '';
	$content = isset($params['content']) ? $params['content'] : null;
	$json_content = isset($params['json_content']) ? $params['json_content'] : null;

	if ( empty($id) || $id !== $id_route ) {
		return new WP_Error('sitemeta_id_mismatch', 'Body ID must match URL ID', array('status' => 400));
	}
	if ( !in_array($type, array('text','json','list','image','gallery','keyvalue'), true) ) {
		return new WP_Error('sitemeta_invalid_type', 'Invalid type', array('status' => 400));
	}

	$record = site_meta_normalize_payload( $id, $type, $content, $json_content );
	list($content, $json_content) = site_meta_row_from_record( $record );

	global $wpdb;
	$table = site_meta_table();

	$exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE field_id = %s", $id) );

	if ( $exists ) {
		$updated = $wpdb->update(
			$table,
			array(
				'content' => $content,
				'type'    => $type,
				'json_content'  => $json_content,
			),
			array( 'field_id' => $id ),
			array('%s','%s', '%s'),
			array('%s')
		);
		if ( false === $updated ) {
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
			array('%s','%s','%s', '%s')
		);
		if ( false === $inserted ) {
			return new WP_Error('sitemeta_db_error', 'Insert failed', array('status' => 500));
		}
	}

	site_meta_cache_invalidate( $id );
	site_meta_cache_invalidate(); // list cache too
	site_meta_cache_set_one( $id, $record );

	return new WP_REST_Response( $record, 200 );
}

// function site_meta_rest_update_item($request)
// {
// 	global $wpdb;
// 	$table_name = $wpdb->prefix . 'site_meta';

// 	$params = $request->get_json_params();
// 	$field_id = sanitize_text_field($params['field_id']);
// 	$type     = sanitize_text_field($params['type']);
// 	$content  = $params['content'];

// 	// Separate storage depending on type
// 	$content = null;
// 	$json_content  = null;

// 	switch ($type) {
// 		case 'json':
// 			if (!is_array($content) && !is_object($content)) {
// 				return new WP_Error('invalid_json', 'Content must be valid JSON.', ['status' => 400]);
// 			}
// 			$json_content = wp_json_encode($content);
// 			break;

// 		case 'list':
// 			if (!is_array($content)) {
// 				return new WP_Error('invalid_list', 'Content must be an array for type "list".', ['status' => 400]);
// 			}
// 			$content = maybe_serialize($content);
// 			break;

// 		case 'image':
// 		case 'gallery':
// 			if (!is_string($content) && !is_array($content)) {
// 				return new WP_Error('invalid_image', 'Content must be a string (url) or array (gallery).', ['status' => 400]);
// 			}
// 			$content = maybe_serialize($content);
// 			break;

// 		case 'text':
// 		default:
// 			if (!is_string($content)) {
// 				return new WP_Error('invalid_text', 'Content must be a string.', ['status' => 400]);
// 			}
// 			$content = sanitize_textarea_field($content);
// 			break;
// 	}

// 	$wpdb->replace(
// 		$table_name,
// 		[
// 			'field_id'    => $field_id,
// 			'content' => $content,
// 			'json_content'  => $json_content,
// 		],
// 		['%s', '%s', '%s']
// 	);

// 	delete_transient('site_meta_all');
// 	set_transient('site_meta_all', site_meta_get_all(), MONTH_IN_SECONDS);

// 	return ['success' => true, 'field_id' => $field_id];
// }

/** REST: Delete */

function site_meta_rest_delete_field( WP_REST_Request $req ) {
	$id = strtolower( preg_replace('/[^a-zA-Z0-9_-]/','', $req['id']) );
	if ( empty($id) ) {
		return new WP_Error('sitemeta_invalid_id', 'Invalid ID', array('status' => 400));
	}

	global $wpdb;
	$table = site_meta_table();

	$deleted = $wpdb->delete( $table, array('field_id' => $id), array('%s') );
	if ( false === $deleted ) {
		return new WP_Error('sitemeta_db_error', 'Delete failed', array('status' => 500));
	}
	if ( 0 === intval($deleted) ) {
		return new WP_Error('sitemeta_not_found', 'Not found', array('status' => 404));
	}

	site_meta_cache_invalidate( $id );
	site_meta_cache_invalidate(); // list

	return new WP_REST_Response( array('deleted' => true, 'id' => $id), 200 );
}
