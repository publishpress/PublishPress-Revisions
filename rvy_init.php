<?php
if ( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

define( 'RVY_NETWORK', awp_is_mu() && rvy_plugin_active_for_network( RVY_BASENAME ) );

add_action('init', 'rvy_status_registrations', 40);

add_action( 'rest_api_init', array( 'RVY_RestAPI', 'register_scheduled_rev_meta_field' ) );

if (did_action('set_current_user')) {
	rvy_ajax_handler();
} else {
	add_action( 'set_current_user', 'rvy_ajax_handler', 20);
}

add_action('init', 'rvy_maybe_redirect', 1);

/*======== WP-Cron implentation for Email Notification Queue ========*/
add_action('init', 'rvy_set_notification_queue_cron');
add_action('rvy_mail_queue_hook', 'rvy_send_queued_mail' );
add_filter('cron_schedules', 'rvy_mail_queue_cron_interval');

function rvy_set_notification_queue_cron() {
	$cron_timestamp = wp_next_scheduled( 'rvy_mail_queue_hook' );

	//$wait_sec = time() - $cron_timestamp;

	if (rvy_get_option('use_notification_queue')) {
		if (!$cron_timestamp) {
			wp_schedule_event(time(), 'two_minutes', 'rvy_mail_queue_hook');
		}
	} else {
		wp_unschedule_event($cron_timestamp, 'rvy_mail_queue_hook');
	}
}

function rvy_mail_queue_cron_interval( $schedules ) {
    $schedules['two_minutes'] = array(
        'interval' => 30,
        'display'  => esc_html__( 'Every 2 Minutes' ),
    );
 
    return $schedules;
}

function rvy_mail_queue_exec() {
	return rvy_mail_process_queue();
}
/*=================== End WP-Cron implementation ====================*/


function rvy_maybe_redirect() {
	// temporary provision for 2.0 beta testers
	if (strpos($_SERVER['REQUEST_URI'], 'page=rvy-moderation')) {
		wp_redirect(str_replace('page=rvy-moderation', 'page=revisionary-q', $_SERVER['REQUEST_URI']));
		exit;
	}
}

class RVY_RestAPI {
    // register a postmeta field to flag the need for a redirect following scheduled revision creation
    public static function register_scheduled_rev_meta_field() {
			$post_types = get_post_types( array( 'public' => true ) );

			foreach( $post_types as $post_type ) {
				// Thanks to Josh Pollock for demonstrating this:
				// https://torquemag.io/2015/07/working-with-post-meta-data-using-the-wordpress-rest-api/
				register_rest_field( $post_type, 'new_scheduled_revision', array(
					'get_callback' => array( 'RVY_RestAPI', 'get_new_scheduled_revision_flag' ),
					'schema' => null,
					)
				);

				register_rest_field( $post_type, 'save_as_revision', array(
					'get_callback' => array( 'RVY_RestAPI', 'get_save_as_revision_flag' ),
					'schema' => null,
					)
				);
			}
    }
    
    public static function get_new_scheduled_revision_flag( $object ) {
		global $current_user;
        return ( isset( $object['id'] ) ) ? get_post_meta( $object['id'], "_new_scheduled_revision_{$current_user->ID}", true ) : false;
	}
	
	public static function get_save_as_revision_flag( $object ) {
		global $current_user;
        return ( isset( $object['id'] ) ) ? get_post_meta( $object['id'], "_save_as_revision_{$current_user->ID}", true ) : false;
    }
}

function rvy_ajax_handler() {
	global $current_user;

	if (!empty($_REQUEST['rvy_ajax_field']) && !empty($_REQUEST['post_id'])) {
		if ('save_as_revision' == $_REQUEST['rvy_ajax_field']) {
			$save_revision = isset($_REQUEST['rvy_ajax_value']) && in_array($_REQUEST['rvy_ajax_value'], ['true', true, 1, '1'], true);
			update_post_meta($_REQUEST['post_id'], "_save_as_revision_{$current_user->ID}", $save_revision);
			exit;
		}
	}

	if (defined('DOING_AJAX') && DOING_AJAX && ('get-revision-diffs' == $_REQUEST['action'])) {
		require_once( dirname(__FILE__).'/admin/history_rvy.php' );
		new RevisionaryHistory();
	}
}

function rvy_status_registrations() {
	register_post_status('pending-revision', array(
		'label' => _x('Pending Revision', 'post'),
		'labels' => (object)['publish' => __('Publish Revision', 'revisionary'), 'save' => __('Save Revision', 'revisionary'), 'update' => __('Update Revision', 'revisionary'), 'plural' => __('Pending Revisions', 'revisionary'), 'short' => __('Pending', 'revisionary') ],
		'protected' => true,
		'internal' => true,
		'label_count' => _n_noop('Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>'),
		'exclude_from_search' => false,
		'show_in_admin_all_list' => false,
		'show_in_admin_status_list' => false,
	));

	register_post_status('future-revision', array(
		'label' => _x('Scheduled Revision', 'post'),
		'labels' => (object)['publish' => __('Publish Revision', 'revisionary'), 'save' => __('Save Revision', 'revisionary'), 'update' => __('Update Revision', 'revisionary'), 'plural' => __('Scheduled Revisions', 'revisionary'), 'short' => __('Scheduled', 'revisionary')],
		'protected' => true,
		'internal' => true,
		'label_count' => _n_noop('Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>'),
		'exclude_from_search' => false,
		'show_in_admin_all_list' => false,
		'show_in_admin_status_list' => false,
	));

	foreach(rvy_get_manageable_types() as $post_type) {
		add_filter("rest_{$post_type}_collection_params", function($query_params, $post_type) 
			{
				$query_params['status']['items']['enum'] []= 'pending-revision';
				$query_params['status']['items']['enum'] []= 'future-revision';
				return $query_params;
			}, 999, 2 
		);
	}

	// WP > 5.3: Don't allow revision statuses to be blocked at the REST API level. Our own filters are sufficient to regulate their usage.
	add_action( 'rest_api_init', function() {
			global $wp_post_statuses;
			foreach( ['pending-revision', 'future-revision'] as $status) {
				if (isset($wp_post_statuses[$status])) {
					$wp_post_statuses[$status]->internal = false;
				}
			}
		}, 97 
	);

	add_action( 'rest_api_init', function() {
		global $wp_post_statuses;
		foreach( ['pending-revision', 'future-revision'] as $status) {
			if (isset($wp_post_statuses[$status])) {
				$wp_post_statuses[$status]->internal = true;
			}
		}
	}, 99
);
}

// WP function is_plugin_active_for_network() is defined in admin
function rvy_plugin_active_for_network( $plugin ) {
	if ( ! is_multisite() ) {
		return false;
	}

	$plugins = get_site_option( 'active_sitewide_plugins' );
	if ( isset( $plugins[ $plugin ] ) ) {
		return true;
	}

	return false;
}

// auto-define the Revisor role to include custom post type capabilities equivalent to those added for post, page in rvy_add_revisor_role()
function rvy_add_revisor_custom_caps() {
	if ( ! rvy_get_option( 'revisor_role_add_custom_rolecaps' ) )
		return;

	global $wp_roles;

	if ( isset( $wp_roles->roles['revisor'] ) ) {
		if ( $custom_types = get_post_types( array( 'public' => true, '_builtin' => false ), 'object' ) ) {
			foreach( $custom_types as $post_type => $type_obj ) {
				$cap = $type_obj->cap;	
				$custom_caps = array_fill_keys( array( $cap->read_private_posts, $cap->edit_posts, $cap->edit_others_posts, "delete_{$post_type}s" ), true );

				$wp_roles->roles['revisor']['capabilities'] = array_merge( $wp_roles->roles['revisor']['capabilities'], $custom_caps );
				$wp_roles->role_objects['revisor']->capabilities = array_merge( $wp_roles->role_objects['revisor']->capabilities, $custom_caps );
			}
		}
	}
}

function rvy_detect_post_type() {
	global $revisionary;
	
	if ( isset($revisionary) && $revisionary->doing_rest && $revisionary->rest->is_posts_request )
		return $revisionary->rest->post_type;
	else
		return awp_post_type_from_uri();
}

function rvy_detect_post_id() {
	global $revisionary;
	
	if ( isset($revisionary) && $revisionary->doing_rest && $revisionary->rest->is_posts_request )
		$post_id = $revisionary->rest->post_id;
	elseif ( ! empty( $_GET['post'] ) )
		$post_id = $_GET['post'];
	elseif ( ! empty( $_POST['post_ID'] ) )
		$post_id = $_POST['post_ID'];
	elseif ( ! empty( $_GET['post_id'] ) )
		$post_id = $_GET['post_id'];
	elseif ( ! empty( $_GET['p'] ) )
		$post_id = $_GET['p'];
	elseif ( ! empty( $_GET['id'] ) )
		$post_id = $_GET['id'];
	elseif ( ! empty( $_REQUEST['fl_builder_data'] ) && is_array( $_REQUEST['fl_builder_data'] ) && ! empty( $_REQUEST['fl_builder_data']['post_id'] ) )
		$post_id = $_REQUEST['fl_builder_data']['post_id'];
	else
		$post_id = 0;
	
	return $post_id;	
}

function rvy_add_revisor_role( $requested_blog_id = '' ) {
	global $wp_roles;
	
	$wp_role_caps = array(
		'read' => true,
		'read_private_posts' => true,
		'read_private_pages' => true,
		'edit_posts' => true,
		'delete_posts' => true,
		'edit_others_posts' => true,
		'edit_pages' => true,
		'delete_pages' => true,
		'edit_others_pages' => true,
		'level_3' => true,
		'level_2' => true,
		'level_1' => true,
		'level_0' => true
	);

	$wp_roles->add_role( 'revisor', __( 'Revisor', 'revisionary' ), $wp_role_caps );
}

// wrapper function for use with wp_cron hook
function revisionary_publish_scheduled() {
	require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
	rvy_publish_scheduled_revisions();
}

function rvy_refresh_options() {
	rvy_retrieve_options(true);
	rvy_retrieve_options(false);
	
	rvy_refresh_default_options();
	rvy_refresh_options_sitewide();
}

function rvy_refresh_options_sitewide() {
	if ( ! RVY_NETWORK )
		return;
		
	global $rvy_options_sitewide;
	$rvy_options_sitewide = apply_filters( 'options_sitewide_rvy', rvy_default_options_sitewide() );	// establishes which options are set site-wide

	if ( $options_sitewide_reviewed =  rvy_get_option( 'options_sitewide_reviewed', true ) ) {
		$custom_options_sitewide = (array) rvy_get_option( 'options_sitewide', true );
		
		$unreviewed_default_sitewide = array_diff( array_keys($rvy_options_sitewide), $options_sitewide_reviewed );

		$rvy_options_sitewide = array_fill_keys( array_merge( $custom_options_sitewide, $unreviewed_default_sitewide ), true );
	}

	$rvy_options_sitewide = array_filter( $rvy_options_sitewide );
}

function rvy_refresh_default_options() {
	global $rvy_default_options;

	$rvy_default_options = apply_filters( 'default_options_rvy', rvy_default_options() );
	
	if ( RVY_NETWORK )
		rvy_apply_custom_default_options();
}

function rvy_apply_custom_default_options() {
	global $wpdb, $rvy_default_options, $rvy_options_sitewide;
	
	if ( $results = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE site_id = '$wpdb->siteid' AND meta_key LIKE 'rvy_default_%'" ) ) {
		foreach ( $results as $row ) {
			$option_basename = str_replace( 'rvy_default_', '', $row->meta_key );

			if ( ! empty( $rvy_options_sitewide[$option_basename] ) )
				continue;	// custom defaults are only for site-specific options

			if( isset( $rvy_default_options[$option_basename] ) )
				$rvy_default_options[$option_basename] = maybe_unserialize( $row->meta_value );
		}
	}
}

function rvy_delete_option( $option_basename, $sitewide = -1 ) {
	
	// allow explicit selection of sitewide / non-sitewide scope for better performance and update security
	if ( -1 === $sitewide ) {
		global $rvy_options_sitewide;
		$sitewide = isset( $rvy_options_sitewide ) && ! empty( $rvy_options_sitewide[$option_basename] );
	}

	if ( $sitewide ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = '$wpdb->siteid' AND meta_key = 'rvy_$option_basename'" );
	} else 
		delete_option( "rvy_$option_basename" );
}

function rvy_update_option( $option_basename, $option_val, $sitewide = -1 ) {
	
	// allow explicit selection of sitewide / non-sitewide scope for better performance and update security
	if ( -1 === $sitewide ) {
		global $rvy_options_sitewide;
		$sitewide = isset( $rvy_options_sitewide ) && ! empty( $rvy_options_sitewide[$option_basename] );
	}
		
	if ( $sitewide ) {
		update_site_option( "rvy_$option_basename", $option_val );
	} else { 
		update_option( "rvy_$option_basename", $option_val );
	}
}

function rvy_retrieve_options( $sitewide = false ) {
	global $wpdb;
	
	if ( $sitewide ) {
		if ( ! RVY_NETWORK )
			return;

		global $rvy_site_options;
		
		$rvy_site_options = array();

		if ( $results = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE site_id = '$wpdb->siteid' AND meta_key LIKE 'rvy_%'" ) )
			foreach ( $results as $row )
				$rvy_site_options[$row->meta_key] = $row->meta_value;

		$rvy_site_options = apply_filters( 'site_options_rvy', $rvy_site_options );
		return $rvy_site_options;

	} else {
		global $rvy_blog_options;
		
		$rvy_blog_options = array();
		
		if ( $results = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'rvy_%'") )
			foreach ( $results as $row )
				$rvy_blog_options[$row->option_name] = $row->option_value;
				
		$rvy_blog_options = apply_filters( 'options_rvy', $rvy_blog_options );
		return $rvy_blog_options;
	}
}

function rvy_get_option($option_basename, $sitewide = -1, $get_default = false) {
	if ( ! $get_default ) {
		// allow explicit selection of sitewide / non-sitewide scope for better performance and update security
		if ( -1 === $sitewide ) {
			global $rvy_options_sitewide;
			$sitewide = isset( $rvy_options_sitewide ) && ! empty( $rvy_options_sitewide[$option_basename] );
		}
	
		if ( $sitewide ) {
			// this option is set site-wide
			global $rvy_site_options;
			
			if ( ! isset($rvy_site_options) )
				$rvy_site_options = rvy_retrieve_options( true );	
				
			if ( isset($rvy_site_options["rvy_{$option_basename}"]) )
				$optval = $rvy_site_options["rvy_{$option_basename}"];
			
		} else {	
			global $rvy_blog_options;
			
			if ( ! isset($rvy_blog_options) )
				$rvy_blog_options = rvy_retrieve_options( false );	
				
			if ( isset($rvy_blog_options["rvy_$option_basename"]) )
				$optval = $rvy_blog_options["rvy_$option_basename"];
		}
	}

	if ( ! isset( $optval ) ) {
		global $rvy_default_options;
			
		if ( empty( $rvy_default_options ) ) {
			if ( did_action( 'rvy_init' ) )	// Make sure other plugins have had a chance to apply any filters to default options
				rvy_refresh_default_options();
			else {
				$hardcode_defaults = rvy_default_options();
				if ( isset($hardcode_defaults[$option_basename]) )
					$optval = $hardcode_defaults[$option_basename];	
			}
		}
		
		if ( ! empty($rvy_default_options) && ! empty( $rvy_default_options[$option_basename] ) )
			$optval = $rvy_default_options[$option_basename];
			
		if ( ! isset($optval) )
			return '';
	}

	return maybe_unserialize($optval);
}
 
function rvy_get_post_revisions($post_id, $status = 'inherit', $args = '' ) {
	global $wpdb;
	
	$defaults = array( 'order' => 'DESC', 'orderby' => 'post_modified_gmt', 'use_memcache' => true, 'fields' => COLS_ALL_RVY, 'return_flipped' => false );
	$args = wp_parse_args( $args, $defaults );
	
	foreach( array_keys( $defaults ) as $var ) {
		$$var = ( isset( $args[$var] ) ) ? $args[$var] : $defaults[$var];
	}
	
	if (!in_array( 
		$status, 
		array_merge(rvy_revision_statuses(), array('inherit')) 
	) ) {
		return array();
	}

	if ( COL_ID_RVY == $fields ) {
		// performance opt for repeated calls by user_has_cap filter
		if ( $use_memcache ) {
			static $last_results;
			
			if ( ! isset($last_results) )
				$last_results = array();
		
			elseif ( isset($last_results[$post_id][$status]) )
				return $last_results[$post_id][$status];
		}
		
		if ('inherit' == $status) {
			$revisions = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent = '$post_id' AND post_status = '$status'");
		} else {
			$revisions = $wpdb->get_col(
				"SELECT ID FROM $wpdb->posts "
				. " INNER JOIN $wpdb->postmeta pm_published ON $wpdb->posts.ID = pm_published.post_id AND pm_published.meta_key = '_rvy_base_post_id'"
				. " WHERE pm_published.meta_value = '$post_id' AND post_status = '$status'"
			);
		}

		if ( $return_flipped )
			$revisions = array_fill_keys( $revisions, true );

		if ( $use_memcache ) {
			if ( ! isset($last_results[$post_id]) )
				$last_results[$post_id] = array();
				
			$last_results[$post_id][$status] = $revisions;
		}	
			
	} else {
		$order_clause = ( $order && $orderby ) ? "ORDER BY $orderby $order" : '';

		if ('inherit' == $status) {
			$revisions = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent = '$post_id' AND post_status = '$status' $order_clause");
		} else {
			$revisions = $wpdb->get_results(
				"SELECT * FROM $wpdb->posts "
				. " INNER JOIN $wpdb->postmeta pm_published ON $wpdb->posts.ID = pm_published.post_id AND pm_published.meta_key = '_rvy_base_post_id'"
				. " WHERE pm_published.meta_value = '$post_id' AND post_status = '$status' $order_clause"
			);
		}
	}

	return $revisions;
}

function rvy_log_async_request($action) {						
	// the function which performs requested action will clear this entry to confirm that the asynchronous call was effective 
	$requested_actions = get_option( 'requested_remote_actions_rvy' );
	if ( ! is_array($requested_actions) )
		$requested_actions = array();
		
	$requested_actions[$action] = true;
	update_option( 'requested_remote_actions_rvy', $requested_actions );
}

function rvy_confirm_async_execution($action) {
	$requested_actions = get_option( 'requested_remote_actions_rvy' );
	if ( is_array($requested_actions) && isset($requested_actions[$action]) ) {
		unset( $requested_actions[$action] );
		update_option( 'requested_remote_actions_rvy', $requested_actions );
	}
}

function is_content_administrator_rvy() {
	$cap_name = defined( 'SCOPER_CONTENT_ADMIN_CAP' ) ? SCOPER_CONTENT_ADMIN_CAP : 'activate_plugins';
	return current_user_can( $cap_name );
}

function rvy_notice( $message, $class = 'error fade' ) {
	include_once( dirname(__FILE__).'/lib/error_rvy.php');
	$rvy_err = new RvyError();
	return $rvy_err->add_notice( $message, compact( 'class' ) );
}

function rvy_error( $err_slug, $arg2 = '' ) {
	include_once( dirname(__FILE__).'/lib/error_rvy.php');
	$rvy_err = new RvyError();
	$rvy_err->error_notice( $err_slug );
}

function rvy_mail_check_queue($new_msg = []) {
	if (!$use_queue = rvy_get_option('use_notification_queue')) {
		if (defined('REVISIONARY_DISABLE_MAIL_LOG')) {
			return array_fill_keys(['queue', 'sent_mail', 'send_limits', 'sent_counts', 'new_msg_queued'], []);
		}

		$queue = [];

	} elseif (!$queue = get_option('revisionary_mail_queue')) {
		$queue = [];
		$first_queue = true;
	}

	$new_msg_queued = false;

	if (!$sent_mail = get_option('revisionary_sent_mail')) {
		$sent_mail = [];
		$first_mail_log = true;
	}

	$current_time = time();

	// check sending limits
	$durations = ['minute' => 60, 'hour' => 3600, 'day' => 86400];
	$sent_counts = ['minute' => 0, 'hour' => 0, 'day' => 0];
	
	// by default, purge mail log entries older than 30 days
	$purge_time = apply_filters('revisionary_mail_log_duration', 86400 * 30);
	
	if ($purge_time < $durations['day'] * 2) {
		$purge_time = $durations['day'] * 2;
	}

	if ($use_queue) {
		$default_minute_limit = (defined('REVISIONARY_EMAIL_LIMIT_MINUTE')) ? REVISIONARY_EMAIL_LIMIT_MINUTE : 20;
		$default_hour_limit = (defined('REVISIONARY_EMAIL_LIMIT_HOUR')) ? REVISIONARY_EMAIL_LIMIT_HOUR : 100;
		$default_day_limit = (defined('REVISIONARY_EMAIL_LIMIT_DAY')) ? REVISIONARY_EMAIL_LIMIT_DAY : 1000;

	$send_limits = apply_filters(
		'revisionary_email_limits', 
		[
				'minute' => $default_minute_limit,
				'hour' => $default_hour_limit,
				'day' => $default_day_limit,
		]
	);
	}

	foreach($sent_mail as $k => $mail) {
		if (!isset($mail['time_gmt'])) {
			continue;
		}

		$elapsed = $current_time - $mail['time_gmt'];

		if ($use_queue) {
		foreach($durations as $limit_key => $duration) {
			if ($elapsed < $duration) {
				$sent_counts[$limit_key]++;
			}

				if ($new_msg && ($sent_counts[$limit_key] >= $send_limits[$limit_key])) {
				$new_msg_queued = true;
			}
		}
		}

		if ($elapsed > $purge_time) {
			unset($sent_mail[$k]);
			$purged = true;
		}
	}

	if ($new_msg_queued) {
		$queue = array_merge([$new_msg], $queue);
		update_option('revisionary_mail_queue', $queue);
	}

	if (!empty($purged)) {
		update_option('revisionary_sent_mail', $sent_mail);
	}

	if (!empty($first_mail_log) && $sent_mail) {
		global $wpdb;
		$wpdb->query("UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'revisionary_sent_mail'");
	}

	if (!empty($first_queue) && $queue) {
		global $wpdb;
		$wpdb->query("UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'revisionary_mail_queue'");
	}

	return (object) compact('queue', 'sent_mail', 'send_limits', 'sent_counts', 'new_msg_queued');
}

// called by WP-cron hook
function rvy_send_queued_mail() {
	$queue_status = rvy_mail_check_queue();

	if (empty($queue_status->queue)) {
		return false;
	}

	$q = $queue_status->queue;

	while ($q) {
		foreach($queue_status->sent_counts as $limit_key => $count) {
			$queue_status->sent_counts[$limit_key]++;

			if ($count > $queue_status->send_limits[$limit_key]) {
				// A send limit has been reached
				break 2;
			}
		}

		$next_mail = array_pop($q);

		// update truncated queue immediately to prevent duplicate sending by another process
		update_option('revisionary_mail_queue', $q);

		// If queued notification is missing vital data, discard it
		if (empty($next_mail['address']) || empty($next_mail['title']) || empty($next_mail['message']) || empty($next_mail['time_gmt'])) {
			continue;
		}

		// If notification was queued more than a week ago, discard it
		if (time() - $next_mail['time_gmt'] > 3600 * 24 * 7 ) {
			continue;
		}

		if (defined('PRESSPERMIT_DEBUG')) {
			pp_errlog('*** Sending QUEUED mail: ');
			pp_errlog($next_mail['address'] . ', ' . $next_mail['title']);
			pp_errlog($next_mail['message']);
		}
		
		if (defined('RS_DEBUG')) {
			$success = wp_mail($next_mail['address'], $next_mail['title'], $next_mail['message']);
		} else {
			$success = @wp_mail($next_mail['address'], $next_mail['title'], $next_mail['message']);
		}

		if (!$success && !defined('REVISIONARY_NO_MAIL_RETRY')) {
			// message was not sent successfully, so put it back in the queue
			if ($q) {
				$q = array_merge([$next_mail], $q);
			} else {
				$q = [$next_mail];
			}
			update_option('revisionary_mail_queue', $q);
		} else {
		// log the sent mail
			$next_mail['time'] = strtotime(current_time( 'mysql' ));
		$next_mail['time_gmt'] = time();

		if (!defined('RS_DEBUG') && !defined('REVISIONARY_LOG_EMAIL_MESSAGE')) {
			unset($next_mail['message']);
		}

			$queue_status->sent_mail[]= $next_mail;
			update_option('revisionary_sent_mail', $queue_status->sent_mail);
	}
}
}

function rvy_mail( $address, $title, $message, $args ) {
	// args: ['revision_id' => $revision_id, 'post_id' => $published_post->ID, 'notification_type' => $notification_type, 'notification_class' => $notification_class]

	/*
	 * [wp-cron action checks wp_option revisionary_mail_queue. If wait time has elapsed, send queued emails (up to limit per minute)]
	 * 
	 * If mail is already queued to wp_option revisionary_mail_queue, add this email to queue
	 * 
	 * 	- or -
	 * 
	 * Check wp_option array revisionary_sent_mail
	 *   - If exceeding daily, hourly or minute limit, add this email to queue
	 * 	 - If sending, add current timestamp to wp_option array revisionary_sent_mail
	 */

	$new_msg = array_merge(compact('address', 'title', 'message'), ['time' => strtotime(current_time( 'mysql' )), 'time_gmt' => time()], $args);

	$queue_status = rvy_mail_check_queue($new_msg);

	if (!empty($queue_status->new_msg_queued)) {
		return;
	}

	if (defined('PRESSPERMIT_DEBUG')) {
		pp_errlog("$address, $title");
		pp_errlog($message);
	}

	if ( defined( 'RS_DEBUG' ) )
		$success = wp_mail( $address, $title, $message );
	else
		$success = @wp_mail( $address, $title, $message );

	if ($success || defined('REVISIONARY_NO_MAIL_RETRY')) {
		if (!defined('REVISIONARY_DISABLE_MAIL_LOG')) {
			$queue_status->sent_mail[]= $new_msg;
			update_option('revisionary_sent_mail', $queue_status->sent_mail);
		}
	} elseif (rvy_get_option('use_notification_queue')) {
		if ($queue_status->queue) {
			$queue_status->queue = array_merge([$new_msg], $queue_status->queue);
		} else {
			$queue_status->queue = [$new_msg];
		}
		
		update_option('revisionary_mail_queue', $queue_status->queue);
	}
}

function rvy_settings_scripts() {
	if (defined('REVISIONARY_PRO_VERSION')) {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		wp_enqueue_script('revisionary-pro-settings', plugins_url('', REVISIONARY_FILE) . "/includes-pro/settings-pro{$suffix}.js", ['jquery', 'jquery-form'], REVISIONARY_VERSION, true);
		$wp_scripts->in_footer[] = 'revisionary-pro-settings';  // otherwise it will not be printed in footer  @todo: review
	}
}

function rvy_omit_site_options() {
	rvy_settings_scripts();
	add_thickbox();
	include_once( RVY_ABSPATH . '/admin/options.php' );
	rvy_options( false );
}

function rvy_wp_api_request() {
	return ( function_exists('wp_api_request') ) ? wp_api_request() : false;
}

function rvy_is_status_public( $status ) {
	if ( $post_status_obj = get_post_status_object( $status ) ) {
		return ! empty( $post_status_obj->public );
	}

	return false;
}

function rvy_is_status_private( $status ) {
	if ( $post_status_obj = get_post_status_object( $status ) ) {
		return ! empty( $post_status_obj->private );
	}

	return false;
}

function rvy_is_status_published( $status ) {
	if ( $post_status_obj = get_post_status_object( $status ) ) {
		return ! empty( $post_status_obj->public ) || ! empty( $post_status_obj->private );
	}

	return false;
}

function rvy_revision_statuses() {
	return apply_filters('rvy_revision_statuses', array('pending-revision', 'future-revision'));
}

function rvy_is_revision_status($status) {
	return in_array($status, rvy_revision_statuses());
}

function rvy_post_id($revision_id) {
	static $busy;

	if (!empty($busy)) {
		return;
	}

	$busy = true;
	$published_id = get_post_meta( $revision_id, '_rvy_base_post_id', true );
	$busy = false;

	if (empty($published_id)) {
		if ($_post = get_post($revision_id)) {
			// if ID passed in is not a revision, return it as is
			if (('revision' != $_post->post_type) && !rvy_is_revision_status($_post->post_status)) {
				return $revision_id;
			} elseif('revision' == $_post->post_type) {
				return $_post->post_parent;
			} else {
				update_post_meta( $revision_id, '_rvy_base_post_id', $_post->comment_count );
				return $_post->comment_count;
			}
		}
	}

	return ($published_id) ? $published_id : 0;
}

/*
function rvy_get_post_meta($post_id, $key, $single = false) {
	global $wpdb;
	if ( $results = $wpdb->get_results( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '$key' AND post_id = '$post_id' GROUP BY meta_id LIMIT 1" ) ) {
		if ( $single )
			return current( $results[0] );
		else
			return @array_map( 'maybe_unserialize', current($results) );
	} else
		return false;
}
*/

function rvy_halt( $msg, $title = '' ) {
	if ( ! $title ) {
		$title = __( 'Revision Workflow', 'revisionary' );
	}
	wp_die( $msg, $title, array( 'response' => 200 ) );
}

function _revisionary_dashboard_dismiss_msg() {
	$dismissals = get_option( 'revisionary_dismissals' );
	if ( ! is_array( $dismissals ) )
		$dismissals = array();

	$msg_id = ( isset( $_REQUEST['msg_id'] ) ) ? $_REQUEST['msg_id'] : 'intro_revisor_role';
	$dismissals[$msg_id] = true;
	update_option( 'rvy_dismissals', $dismissals );
}

function rvy_is_supported_post_type($post_type) {
	$types = rvy_get_manageable_types();
	return !empty($types[$post_type]);
}

function rvy_get_manageable_types() {
	$types = array();
	
	global $current_user;
	
	foreach( get_post_types( array( 'public' => true ), 'object' ) as $post_type => $type_obj ) {
		//if ( ! empty( $current_user->allcaps[$type_obj->cap->publish_posts] ) 
		//&& ! empty( $current_user->allcaps[$type_obj->cap->edit_published_posts] ) 
		//&& ! empty( $current_user->allcaps[$type_obj->cap->edit_others_posts] ) ) {
			$types[$post_type]= $post_type;
		//}
	}
	
	$types = array_diff_key($types, array('acf-field-group' => true));
	return apply_filters('revisionary_supported_post_types', $types);
}

// thanks to GravityForms for the nifty dismissal script
if ( in_array( basename($_SERVER['PHP_SELF']), array('admin.php', 'admin-ajax.php') ) ) {
	add_action( 'wp_ajax_rvy_dismiss_msg', '_revisionary_dashboard_dismiss_msg' );
}

function revisionary_copy_meta_field( $meta_key, $from_post_id, $to_post_id, $mirror_empty = true ) {
	global $wpdb;

	if ( ! $to_post_id )
		return;
	
	if ( $_post = $wpdb->get_row( "SELECT * FROM $wpdb->posts WHERE ID = '$from_post_id'" ) ) {
		if ( $source_meta = $wpdb->get_row( 
				$wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s AND post_id = %d", $meta_key, $from_post_id )
			)
		) {
			update_post_meta($to_post_id, $meta_key, $source_meta->meta_value);

		} elseif ($mirror_empty && in_array($meta_key, apply_filters('revisionary_removable_meta_fields', [], $to_post_id))) {
			// Disable postmeta deletion until further testing
			delete_post_meta($to_post_id, $meta_key);
		}
	}
}

function revisionary_copy_terms( $from_post_id, $to_post_id, $mirror_empty = false ) {
	global $wpdb;

	if ( ! $to_post_id )
		return;
	
	//if (false===$skip_taxonomies) { @todo: $args
		$skip_taxonomies = array();	
	//}

	if ($skip_taxonomies = apply_filters('revisionary_skip_taxonomies', $skip_taxonomies, $from_post_id, $to_post_id)) {
		$tx_join = "INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id ";
		$tx_where = "tt.taxonomy NOT IN ('" . implode("','", array_filter($skip_taxonomies, 'sanitize_key')) . "')";
	} else {
		$tx_join = '';
		$tx_where = '';
	}

	if ( $_post = $wpdb->get_row( "SELECT * FROM $wpdb->posts WHERE ID = '$from_post_id'" ) ) {
		$source_terms = $wpdb->get_col( "SELECT term_taxonomy_id FROM $wpdb->term_relationships AS tr {$tx_join}WHERE {$tx_where}tr.object_id = '$from_post_id'" );

		$target_terms = $wpdb->get_col( "SELECT term_taxonomy_id FROM $wpdb->term_relationships AS tr {$tx_join}WHERE {$tx_where}tr.object_id = '$to_post_id'" );

		if ( $add_terms = array_diff($source_terms, $target_terms) ) {
			// todo: single query
			foreach($add_terms as $tt_id) {
				$wpdb->query("INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES ('$to_post_id', '$tt_id')");
			}
		}
		
		if ($source_terms || $mirror_empty) {
		if ( $delete_terms = array_diff($target_terms, $source_terms) ) {
			// todo: single query
			foreach($delete_terms as $tt_id) {
				$wpdb->query("DELETE FROM $wpdb->term_relationships WHERE object_id = '$to_post_id' AND term_taxonomy_id = '$tt_id'");
				}
			}
		}
	}	
}

function rvy_is_network_activated($plugin_file = '')
{
	if (!$plugin_file && defined('REVISIONARY_FILE')) {
		$plugin_file = plugin_basename(REVISIONARY_FILE);
	}

	return (array_key_exists($plugin_file, (array)maybe_unserialize(get_site_option('active_sitewide_plugins'))));
}

function rvy_init() {
	global $wp_roles;

	if ( ! isset( $wp_roles->roles['revisor'] ) ) {
		rvy_add_revisor_role();
	} else {
		set_site_transient('revisionary_previous_install', true, 86400);
	}

	/*  // wp_cron hook @todo
	if ( rvy_get_option( 'scheduled_revisions' ) ) {
		add_action( 'publish_revision_rvy', 'revisionary_publish_scheduled' ); //wp-cron hook
	}
	*/

	if ( is_admin() ) {
		require_once( dirname(__FILE__).'/admin/admin-init_rvy.php' );
		rvy_load_textdomain();
		rvy_admin_init();

	} else {		// @todo: fix links instead
		// fill in the missing args for Pending / Scheduled revision preview link from Edit Posts / Pages
		if ( isset($_SERVER['HTTP_REFERER']) 
		&& ( false !== strpos( urldecode($_SERVER['HTTP_REFERER']),'p-admin/edit-pages.php') 
		|| false !== strpos( urldecode($_SERVER['HTTP_REFERER']),'p-admin/edit.php') ) ) {

			if ( ! empty($_GET['p']) ) {
				if ( rvy_get_option( 'scheduled_revisions' ) || rvy_get_option( 'pending_revisions' ) ) {
					if ( $post = get_post( $_GET['p'] ) ) {
						if (rvy_is_revision_status($post->post_status)) {
							$_GET['preview'] = 1;
						}
					}
				}
			}
		// Is this an asynchronous request to publish scheduled revisions?
		} elseif ( ! empty($_GET['action']) && ('publish_scheduled_revisions' == $_GET['action']) && rvy_get_option( 'scheduled_revisions' ) ) {
				require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
				add_action( 'rvy_init', '_rvy_publish_scheduled_revisions' );
		}
	}
	
	if ( empty( $_GET['action'] ) || ( 'publish_scheduled_revisions' != $_GET['action'] ) ) {
		if ( ! strpos( $_SERVER['REQUEST_URI'], 'login.php' ) && rvy_get_option( 'scheduled_revisions' ) ) {
		
			// If a previously requested asynchronous request was ineffective, perform the actions now
			// (this is not executed if the current URI is from a manual publication request with action=publish_scheduled_revisions)
			$requested_actions = get_option( 'requested_remote_actions_rvy' );
			if ( is_array( $requested_actions) && ! empty($requested_actions) ) {
				if ( ! empty($requested_actions['publish_scheduled_revisions']) ) {
					require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
					rvy_publish_scheduled_revisions();
					unset( $requested_actions['publish_scheduled_revisions'] );
				}
	
				update_option( 'requested_remote_actions_rvy', $requested_actions );
			}
			
			$next_publish = get_option( 'rvy_next_rev_publish_gmt' );
			
			// automatically publish any scheduled revisions whose time has come
			if ( ! $next_publish || ( agp_time_gmt() >= strtotime( $next_publish ) ) ) {

				if ( ini_get( 'allow_url_fopen' ) && rvy_get_option('async_scheduled_publish') ) {
					// asynchronous secondary site call to avoid delays // TODO: pass site key here
					rvy_log_async_request('publish_scheduled_revisions');
					$url = site_url( 'index.php?action=publish_scheduled_revisions' );
					wp_remote_post( $url, array('timeout' => 5, 'blocking' => false, 'sslverify' => apply_filters('https_local_ssl_verify', true)) );
				} else {
					// publish scheduled revision now
					if ( ! defined('DOING_CRON') ) {
						define( 'DOING_CRON', true );
					}
					require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
					rvy_publish_scheduled_revisions();
				}
			}	
		}
	}

	require_once( dirname(__FILE__).'/revisionary_main.php');

	global $revisionary;
	$revisionary = new Revisionary();
}

function rvy_is_post_author($post, $user = false) {
	if (!is_object($post)) {
		if (!$post = get_post($post)) {
			return false;
		}
	}

	if (false === $user) {
		global $current_user;
		$user_id = $current_user->ID;
	} else {
		$user_id = (is_object($user)) ? $user->ID : $user;
	}

	if (!empty($post->post_author) && ($post->post_author == $user_id)) {
		return true;

	} elseif (function_exists('is_multiple_author_for_post') && is_multiple_author_for_post($user_id, $post->ID)) {
		return true;
	}

	return false;
}

function rvy_preview_url($revision, $args = []) {
	$defaults = ['post_type' => $revision->post_type];  // support preview url for past revisions, which are stored with post_type = 'revision'
	foreach(array_keys($defaults) as $var) {
		$$var = (!empty($args[$var])) ? $args[$var] : $defaults[$var]; 
	}

	$link_type = rvy_get_option('preview_link_type');

	if ('id_only' == $link_type) {
		// support using ids only if theme or plugins do not tolerate published post url and do not require standard format with revision slug
		$preview_url = add_query_arg('preview', true, get_post_permalink($revision));

		if ('page' == $post_type) {
			$preview_url = str_replace('p=', "page_id=", $preview_url);
			$id_arg = 'page_id';
		} else {
			$id_arg = 'p';
		}
	} elseif ('revision_slug' == $link_type) {
		// support using actual revision slug in case theme or plugins do not tolerate published post url
		$preview_url = add_query_arg('preview', true, get_permalink($revision));

		if ('page' == $post_type) {
			$preview_url = str_replace('p=', "page_id=", $preview_url);
			$id_arg = 'page_id';
		} else {
			$id_arg = 'p';
		}
	} else { // 'published_slug'
		// default to published post url, appended with 'preview' and page_id args
		$preview_url = add_query_arg('preview', true, get_permalink(rvy_post_id($revision->ID)));
		$id_arg = 'page_id';
	}

	if (!strpos($preview_url, "{$id_arg}=")) {
		$preview_url = add_query_arg($id_arg, $revision->ID, $preview_url);
	}

	if (!strpos($preview_url, "post_type=")) {
		$preview_url = add_query_arg('post_type', $post_type, $preview_url);
	}

	return apply_filters('revisionary_preview_url', $preview_url, $revision, $args);
}

function _rvy_set_ma_post_authors($post_id, $authors)
{
	_rvy_set_ma_post_authors_custom_field($post_id, $authors);

	$authors = wp_list_pluck($authors, 'term_id');
	wp_set_object_terms($post_id, $authors, 'author');
}

/**
 * Save a custom field with the post authors' name. Add compatibility to
 * Yoast for using in the custom title, and other 3rd party plugins.
 *
 * @param $post_id
 * @param $authors
 */
function _rvy_set_ma_post_authors_custom_field($post_id, $authors)
{
	global $wpdb, $multiple_authors_addon;

	if ( ! is_array($authors)) {
		$authors = [];
	}

	$metadata = 'ppma_authors_name';

	if (empty($authors)) {
		delete_post_meta($post_id, $metadata);
	} else {
		$names = [];

		foreach ($authors as $author) {
			// since this function may be passed a term object with no name property, do a fresh query
			if (!is_numeric($author)) {
				if (empty($author->term_id)) {
					return;
				}

				$author = $author->term_id;
			}

			$taxonomy = (!empty($multiple_authors_addon) && !empty($multiple_authors_addon->coauthor_taxonomy)) 
			? $multiple_authors_addon->coauthor_taxonomy 
			: 'author';

			//$author = Author::get_by_term_id($author);  // this returns an object with term_id property and no name
			//$author = get_term($author, 'author');	  // 'author' is actually an invalid taxonomy name per WP API
			$author = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id"
					. " WHERE tt.taxonomy = %s AND t.term_id = %d"
					, $taxonomy, $author
				)
			);

			if (!empty($author->name)) {
				$names[] = $author->name;
			}
		}

		if (!empty($names)) {
			$names = implode(', ', $names);
			update_post_meta($post_id, $metadata, $names);
		} else {
			delete_post_meta($post_id, $metadata);
		}
	}
}
