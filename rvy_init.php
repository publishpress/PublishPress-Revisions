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
			update_post_meta($_REQUEST['post_id'], "_save_as_revision_{$current_user->ID}", !empty($_REQUEST['rvy_ajax_value']));
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
		'labels' => (object)['publish' => __('Publish Revision', 'revisionary'), 'save' => __('Save Revision'), 'plural' => __('Pending Revisions', 'revisionary'), 'short' => __('Pending', 'revisionary') ],
		'protected' => true,
		'internal' => true,
		'label_count' => _n_noop('Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>'),
		'exclude_from_search' => false,
		'show_in_admin_all_list' => false,
		'show_in_admin_status_list' => false,
	));

	register_post_status('future-revision', array(
		'label' => _x('Scheduled Revision', 'post'),
		'labels' => (object)['publish' => __('Publish Revision', 'revisionary'), 'save' => __('Save Revision'), 'plural' => __('Scheduled Revisions', 'revisionary'), 'short' => __('Scheduled', 'revisionary')],
		'protected' => true,
		'internal' => true,
		'label_count' => _n_noop('Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>'),
		'exclude_from_search' => false,
		'show_in_admin_all_list' => false,
		'show_in_admin_status_list' => false,
	));
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

function rvy_mail( $address, $title, $message ) {
	if (defined('PRESSPERMIT_DEBUG')) {
		pp_errlog("$address, $title");
		pp_errlog($message);
	}

	if ( defined( 'RS_DEBUG' ) )
		wp_mail( $address, $title, $message );
	else
		@wp_mail( $address, $title, $message );
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

	if (empty($pubished_id)) {
		if ($_post = get_post($revision_id)) {
			// if ID passed in is not a revision, return it as is
			if (('revision' != $_post->post_type) && !rvy_is_revision_status($_post->post_status)) {
				return $revision_id;
			} elseif('revision' == $_post->post_type) {
				return $_post->post_parent;
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
							$_GET['rvy_revision'] = true;
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

	if ($post->post_author == $user_id) {
		return true;

	} elseif (function_exists('is_multiple_author_for_post') && is_multiple_author_for_post($user_id, $post->ID)) {
		return true;
	}

	return false;
}
