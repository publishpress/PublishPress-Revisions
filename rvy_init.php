<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename(esc_url_raw($_SERVER['SCRIPT_FILENAME'])) )
	die();

require_once( dirname(__FILE__).'/rvy_init-functions.php');

add_action('init', 'rvy_status_registrations', 40);

if (did_action('wp_loaded')) {
	rvy_ajax_handler();
} else {
	add_action( 'wp_loaded', 'rvy_ajax_handler', 20);
}

if (!defined('RVY_PREVIEW_ARG')) {
	$preview_arg = (rvy_get_option('preview_link_alternate_preview_arg')) ? 'rv_preview' : 'preview';
	define('RVY_PREVIEW_ARG', $preview_arg);
} else {
	if (!defined('RVY_PREVIEW_ARG_LOCKED')) {
		define('RVY_PREVIEW_ARG_LOCKED', true);
	}
}

if (('preview' != RVY_PREVIEW_ARG) && !empty($_REQUEST['preview']) && !empty($_REQUEST['nc'])) {
	$url = sanitize_url($_SERVER['REQUEST_URI']);
	$arr = parse_url(site_url());
	$url = $arr['scheme'] . '://' . $arr['host'] . $url;

	$url = str_replace('preview=', RVY_PREVIEW_ARG . '=', $url);
	wp_redirect($url);
	exit;
}

$preview_arg = (defined('RVY_PREVIEW_ARG')) ? sanitize_key(constant('RVY_PREVIEW_ARG')) : 'rv_preview';

if (!empty($_REQUEST[$preview_arg]) && !empty($_REQUEST['post_type']) && empty($_REQUEST['preview_id'])) {
	add_filter('redirect_canonical', '_rvy_no_redirect_filter', 10, 2);
}

/*======== WP-Cron implementation for Email Notification Buffer ========*/
add_action('init', 'rvy_set_notification_buffer_cron');
add_action('rvy_mail_buffer_hook', 'rvy_send_buffered_mail' );
add_filter('cron_schedules', 'rvy_mail_buffer_cron_interval');

// wp-cron hook
add_action('publish_revision_rvy', '_revisionary_publish_scheduled_cron');

add_action("update_option_rvy_scheduled_publish_cron", '_rvy_existing_schedules_to_cron', 10, 2);

add_action('before_delete_post', 
	function($delete_post_id) {
		if (rvy_in_revision_workflow($delete_post_id)) {
			if ($published_post_id = rvy_post_id($delete_post_id)) {
				_rvy_delete_revision($delete_post_id, $published_post_id);
			}
		}
	}
);

add_action('rvy_delete_revision', '_rvy_delete_revision', 999, 2);
add_action('untrash_post', 
	function($post_id) {
		revisionary_refresh_revision_flags($post_id);
	}
);

add_action('init', 
	function() {
		global $kinsta_cache;

		if (!empty($kinsta_cache)) {
			remove_action('init', [$kinsta_cache, 'init_cache'], 20);
		}

		if (defined('PP_REVISIONS_NO_POST_KSES')) {			
			remove_filter('content_save_pre', 'wp_filter_post_kses');
		}
	}
);

// Advanced Custom Fields plugin: Prevent invalid filtering of revision ID
if (class_exists('ACF')) {
	add_filter(
		'acf/pre_load_post_id', 
		function($return_val, $post_id) {
			if (rvy_in_revision_workflow($post_id)) {
				if (is_object($post_id)) {
					if (!empty($post_id->ID)) {
						$return_val = $post_id->ID;
					}	
				} else {
					$return_val = $post_id;
				}
			}

			return $return_val;
		}, 10, 2
	);
}

if (defined('JREVIEWS_ROOT') && !empty($_REQUEST['preview']) 
&& ((empty($_REQUEST['preview_id']) && empty($_REQUEST['thumbnail_id']))
|| (!empty($_REQUEST['preview_id']) && rvy_in_revision_workflow((int) $_REQUEST['preview_id']))
)
) {
	require_once('compat_rvy.php');
	_rvy_jreviews_preview_compat();
}

// Default early beta testers to same revision status labeling they are already using. They will be directly notified of the new setting.
$last_ver = get_option('revisionary_last_version');

if (version_compare($last_ver, '3.0-alpha', '>=') && version_compare($last_ver, '3.0-beta7', '<')) {
	if (!get_option('pp_revisions_beta3_option_sync_done')) {
		update_option('rvy_revision_statuses_noun_labels', 1);
		update_option('pp_revisions_beta3_option_sync_done', 1);
	}
}

// Revision Edit in Gutenberg: Enable non-Editors to set requested publish date
add_action('init', function() {
	global $revisionary;
	
	foreach(array_keys($revisionary->enabled_post_types) as $post_type) {
		add_filter("rest_prepare_{$post_type}", '_rvy_rest_prepare', 10, 3);
	}
}, 100);


// Yoast SEO: Prevent invalid "indexable" maintenance operation on revision creation / submission
if (defined('WPSEO_VERSION')) {
	add_filter(
		'wpseo_should_save_indexable',
		function($intend_to_save, $indexable) {
			if (function_exists('rvy_detect_post_id')) {
				$post_id = rvy_detect_post_id();

				if ($post_id && rvy_in_revision_workflow($post_id)) {
					return false;
				}
			}

			if (is_object($indexable) && isset($indexable->object_id) && empty($indexable->object_id)) {
				// WordPress database error Duplicate entry '0' for key 'PRIMARY' for query INSERT INTO `wp_yoast_indexable`
				return false;
			}

			return $intend_to_save;
		},
	10, 2);
}

// Prevent any default filters from screwing with our paging settings
foreach(['revisions_per_page', 'revision_archive_per_page'] as $option_val) {
	add_filter("set_screen_option_{$option_val}", function($screen_option, $option, $value ) {return $value;}, 99, 3);
}
