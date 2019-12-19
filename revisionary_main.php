<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );
	
/**
 * @package     PublishPress\Revisions
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */
class Revisionary
{		
	var $admin;					// object ref - RevisionaryAdmin
	var $filters_admin_item_ui; // object ref - RevisionaryAdminFiltersItemUI
	var $skip_revision_allowance = false;
	var $content_roles;			// object ref - instance of RevisionaryContentRoles subclass, set by external plugin
	var $doing_rest = false;
	var $rest = '';				// object ref - Revisionary_REST
	var $impose_pending_rev = [];
	var $save_future_rev = [];
	var $last_autosave_id = [];

	// minimal config retrieval to support pre-init usage by WP_Scoped_User before text domain is loaded
	function __construct() {
		global $script_name;

		// Ensure editing access to past revisions is not accidentally filtered. 
		// @todo: Correct selective application of filtering downstream so Revisors can use a read-only Compare [Past] Revisions screen
		//
		// Note: some filtering is needed to allow users with full editing permissions on the published post to access a Compare Revisions screen with Preview and Manage buttons
		if (is_admin() && (false !== strpos($_SERVER['REQUEST_URI'], 'revision.php')) && (!empty($_REQUEST['revision'])) && !is_content_administrator_rvy()) {
			$revision_id = (!empty($_REQUEST['revision'])) ? $_REQUEST['revision'] : $_REQUEST['to'];
			
			if ($revision_id) {
				if ($_post = get_post($_REQUEST['revision'])) {
					if (!rvy_is_revision_status($_post->post_status)) {
						if ($parent_post = get_post($_post->post_parent)) {
							global $current_user;

							$type_obj = get_post_type_object($parent_post->post_type);

							if ($type_obj && (
								empty($current_user->allcaps[$type_obj->cap->edit_published_posts]) 
								|| (($current_user->ID != $parent_post->ID) && empty($current_user->allcaps[$type_obj->cap->edit_published_posts]))
							)) {
								return;
							}
						}
					}
				}
			}
		}

		rvy_refresh_options_sitewide();

		// NOTE: $_GET['preview'] and $_GET['post_type'] arguments are set by rvy_init() at response to ?p= request when the requested post is a revision.
		if (!is_admin() && (!defined('REST_REQUEST') || ! REST_REQUEST) && ((!empty($_GET['preview']) && empty($_REQUEST['preview_id'])) || !empty($_GET['mark_current_revision']))) { // preview_id indicates a regular preview via WP core, based on autosave revision
			require_once( dirname(__FILE__).'/front_rvy.php' );
			$this->front = new RevisionaryFront();
		}
		
		if (!is_admin() && (!defined('REST_REQUEST') || ! REST_REQUEST) && (!empty($_GET['preview']) && !empty($_REQUEST['preview_id']))) {
			if ($_post = get_post($_REQUEST['preview_id'])) {
				if (in_array($_post->post_status, ['pending-revision', 'future-revision']) && !$this->isBlockEditorActive()) {
					if (empty($_REQUEST['_thumbnail_id']) || !get_post($_REQUEST['_thumbnail_id'])) {
						$preview_url = rvy_preview_url($_post);
						wp_redirect($preview_url);
						exit;
					}
				}
			}

			if (!defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
				require_once(dirname(__FILE__).'/classes/PublishPress/Revisions/PostPreview.php');
				new PublishPress\Revisions\PostPreview();
			}
		}
		
		if ( ! is_content_administrator_rvy() ) {
			add_filter( 'map_meta_cap', array(&$this, 'flt_post_map_meta_cap'), 5, 4);
			add_filter( 'user_has_cap', array( &$this, 'flt_user_has_cap' ), 98, 3 );
			add_filter( 'pp_has_cap_bypass', array( &$this, 'flt_has_cap_bypass' ), 10, 4 );

			add_filter( 'map_meta_cap', array( &$this, 'flt_limit_others_drafts' ), 10, 4 );
		}

		if ( is_admin() ) {
			require_once( dirname(__FILE__).'/admin/admin_rvy.php');
			$this->admin = new RevisionaryAdmin();
		}	
		
		add_action( 'wpmu_new_blog', array( &$this, 'act_new_blog'), 10, 2 );
		
		add_filter( 'posts_results', array( &$this, 'inherit_status_workaround' ) );
		add_filter( 'the_posts', array( &$this, 'undo_inherit_status_workaround' ) );
	
		//add_action( 'wp_loaded', array( &$this, 'set_revision_capdefs' ) );
		
		if ( rvy_get_option( 'pending_revisions' ) ) {
			// special filtering to support Contrib editing of published posts/pages to revision
			add_filter('pre_post_status', array(&$this, 'flt_pendingrev_post_status') );

			//$priority = (defined('ICL_SITEPRESS_VERSION') && !$this->isBlockEditorActive()) ? 12 : 2;

			add_filter('wp_insert_post_data', array($this, 'flt_maybe_insert_revision'), 2, 2);
		}
		
		if ( rvy_get_option('scheduled_revisions') ) {
			add_filter('wp_insert_post_data', array(&$this, 'flt_create_scheduled_rev'), 3, 2 );  // other filters will have a chance to apply at actual publish time

			// users who have edit_published capability for post/page can create a scheduled revision by modifying post date to a future date (without setting "future" status explicitly)
			add_filter( 'wp_insert_post_data', array(&$this, 'flt_insert_post_data'), 99, 2 );
		}

		// REST logging
		add_filter( 'rest_pre_dispatch', array( &$this, 'rest_pre_dispatch' ), 10, 3 );

		// This is needed, implemented for pending revisions only
		if (!empty($_REQUEST['get_new_revision'])) {
			add_action('template_redirect', array($this, 'act_new_revision_redirect'));
		}

		add_filter('get_comments_number', array($this, 'flt_get_comments_number'), 10, 2);

		add_action('save_post', array($this, 'actSavePost'), 20, 2);
		add_action('delete_post', [$this, 'actDeletePost'], 10, 3);

		if (!defined('REVISIONARY_PRO_VERSION')) {
			add_action('revisionary_saved_revision', [$this, 'act_save_revision_followup'], 5);
		}

		add_filter('presspermit_exception_clause', [$this, 'fltPressPermitExceptionClause'], 10, 4);

		add_action('wp_insert_post', [$this, 'actLogPreviewAutosave'], 10, 2);

		do_action( 'rvy_init', $this );
	}
	
	function actLogPreviewAutosave($post_id, $post) {
		if ('inherit' == $post->post_status && strpos($post->post_name, 'autosave')) {
			$this->last_autosave_id[$post->post_parent] = $post_id;
		}
	}
	
	function fltPressPermitExceptionClause($clause, $required_operation, $post_type, $args) {
		//"$src_table.ID $logic ('" . implode("','", $ids) . "')",

		if (('edit' == $required_operation) && in_array($post_type, rvy_get_manageable_types()) 
		) {
			foreach(['mod', 'src_table', 'logic', 'ids'] as $var) {
				if (!empty($args[$var])) {
					$$var =  $args[$var];
				} else {
					return $clause;
				}
			}

			if ('include' == $mod) {
				$clause = "(($clause) OR ($src_table.post_status IN ('pending-revision', 'future-revision') AND $src_table.comment_count IN ('" . implode("','", $ids) . "')))";
			} elseif ('exclude' == $mod) {
				$clause = "(($clause) AND ($src_table.post_status NOT IN ('pending-revision', 'future-revision') OR $src_table.comment_count NOT IN ('" . implode("','", $ids) . "')))";
			}
		}

		return $clause;
	}

	function act_save_revision_followup($revision) {
		global $wpdb;

		// To ensure no postmeta is dropped from revision, copy any missing keys from published post
		$fields = ['_thumbnail_id', '_wp_page_template'];

		if ($can_remove_empty_fields = apply_filters('revisionary_removable_meta_fields', [], $revision->ID)) {
			if (!$fields = array_diff(['_thumbnail_id', '_wp_page_template'], $can_remove_meta_fields)) {
				return;
			}
		}

		if (!$published_post_id = rvy_post_id($revision->ID)) {
			return;
		}

		$field_csv = implode("','", array_map('sanitize_key', $fields));

		$target_meta = $wpdb->get_results( 
			$wpdb->prepare(
				"SELECT meta_key, meta_value, meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key IN ('$field_csv') GROUP BY meta_key",
				$revision->ID
			)
			, ARRAY_A 
		);

		foreach($fields as $meta_key) {
			foreach($target_meta as $row) {
				if ($row['meta_key'] == $meta_key) {
					continue 2;
				}
			}

			// revision was stored without a post_meta entry for this meta key, so copy it from published post
			if ($published_val = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s AND post_id = %d", $meta_key, $published_post_id))) {
				update_post_meta($revision->ID, $meta_key, $published_val);
			}
		}
	}

	function actSavePost($post_id, $post) {
		if (strtotime($post->post_date_gmt) > agp_time_gmt()) {
			require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
			rvy_update_next_publish_date();
		}
	}

	// On post deletion, also delete its pending revisions and future revisions (and their meta data)
	function actDeletePost($post_id) {
		global $wpdb;

		if (!$post_id) {
			return;
		}

		$any_trashed_posts = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_status = 'trash' AND comment_count > 0 LIMIT 1");

		$trashed_clause = ($any_trashed_posts) 
		? $wpdb->prepare( 
			" OR (ID IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rvy_base_post_id' AND meta_value = %d) AND post_status = 'trash')",
			$post_id
		) : '';

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE (post_status IN ('pending-revision', 'future-revision') AND comment_count = %d) $trashed_clause", 
				$post_id
			)
		);

		foreach($post_ids as $revision_id) {
			wp_delete_post($revision_id, true);
		}

		$post = get_post($post_id);

		if ($post && rvy_is_revision_status($post->post_status)) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->postmeta WHERE post_id = %d", 
					$post_id
				)
			);
		}
	}

	// Return zero value for revision comments because:
	// * comments are not supported for revisions
	// * published post ID is stored to comment_count column is used for query efficiency 
	function flt_get_comments_number($count, $post_id) {
		if ($post = get_post($post_id)) {
			if (rvy_is_revision_status($post->post_status)) {
				$count = 0;
			}
		}

		return $count;
	}

	function get_last_revision($post_id, $user_id) {
		if ( $revisions = rvy_get_post_revisions( $post_id, 'pending-revision', array( 'order' => 'DESC', 'orderby' => 'ID' ) ) ) {  // @todo: retrieve revision_id in block editor js, pass as redirect arg
			foreach( $revisions as $revision ) {
				if (rvy_is_post_author($revision, $user_id)) {
					return $revision;
				}
			}
		}

		return false;
	}

	function act_new_revision_redirect() {
		global $current_user, $post;

		if (is_admin() || empty($post) || empty($_REQUEST['get_new_revision'])) {
			return;
		}

		$last_user_revision_id = $_REQUEST['get_new_revision'];

		$published_post_id = rvy_post_id($post->ID);
		$published_url = get_permalink($published_post_id);

		$revision = $this->get_last_revision($published_post_id, $current_user->ID);

		$usec = 0;
		$delay = 50 * 1000;
		$limit = 15 * 1000 * 1000;
		while ((!$revision || ($revision->ID <= $last_user_revision_id)) && ($usec < $limit)) {
			usleep($delay);
			$revision = $this->get_last_revision($published_post_id, $current_user->ID);
			$usec = $usec + $delay;
		}

		if ($revision && ($usec < $limit)) {
			$preview_link = rvy_preview_url($revision);
			wp_redirect($preview_link);
			exit;
		}

		// If logged user does not have a pending revision of this post, redirect to published permalink
		wp_redirect($published_url);
	}

	function act_rest_insert( $post, $request, $unused ) {
		global $current_user, $wpdb;

		$this->flt_pendingrev_post_status($post->post_status);

		if (!empty($this->impose_pending_rev[$post->ID]) || !empty($this->save_future_rev[$post->ID])) {
			// todo: better revision id logging

			//$revision_id = $this->impose_pending_rev[$post->ID];

			$revision_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_status IN ('pending-revision', 'future-revision') AND "
					. "post_author = %s AND comment_count = %d "
					. "ORDER BY ID DESC LIMIT 1",
					$current_user->ID,
					$post->ID
				)
			);

			// store selected meta array, featured image, template, format and stickiness to revision

			// todo: validate schema support for sticky, page template
			//$schema = $this->get_item_schema();

			//if (! empty( $schema['properties']['format'] ) && isset($request['format'])) {
			if (!empty($request['format']) && post_type_supports($post->post_type, 'post-formats')) {
				set_post_format( $revision_id, $request['format'] );
			}
	
			//if (! empty( $schema['properties']['featured_media'] ) && isset($request['featured_media'])) {
			if (isset($request['featured_media']) && post_type_supports($post->post_type, 'thumbnail')) {
				$this->handle_featured_media( $request['featured_media'], $revision_id );
			}
	
			//if ( ! empty( $schema['properties']['sticky'] ) && isset( $request['sticky'] ) ) {
			if ( isset( $request['sticky'] ) ) {
				if ( ! empty( $request['sticky'] ) ) {
					stick_post( $revision_id );
				} else {
					unstick_post( $revision_id );
				}
			}
	
			//if ( ! empty( $schema['properties']['template'] ) && isset( $request['template'] ) ) {
			if (isset($request['template']) && post_type_supports($post->post_type, 'page-attributes')) {
				$this->handle_template( $request['template'], $revision_id );
			}
	
			//if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
			//if (isset($request['meta']) && post_type_supports($post->post_type, 'custom-fields')) {
			if (isset($request['meta'])) {
				$meta = new WP_REST_Post_Meta_Fields( $this->rest->post_type );

				$meta_update = $meta->update_value( $request['meta'], $revision_id );
	
				if ( is_wp_error( $meta_update ) ) {
					return $meta_update;
				}
			}

			// prevent these selections from updating published post
			foreach(array('meta', 'featured_media', 'template', 'format', 'sticky') as $key) {
				$request[$key] = '';
			}

			// update revision with terms selections, prevent update of published post
			$taxonomies = wp_list_filter( get_object_taxonomies( $this->rest->post_type, 'objects' ), array( 'show_in_rest' => true ) );

			foreach ( $taxonomies as $taxonomy ) {
				$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

				if ( ! isset( $request[ $base ] ) ) {
					continue;
				}

				$result = wp_set_object_terms( $revision_id, $request[ $base ], $taxonomy->name );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				unset($request[$base]);
			}
		}
	}

	private function handle_template( $template, $post_id, $validate = false ) {
		update_post_meta( $post_id, '_wp_page_template', $template );
	}

	private function handle_featured_media( $featured_media, $post_id ) {

		$featured_media = (int) $featured_media;
		if ( $featured_media ) {
			$result = set_post_thumbnail( $post_id, $featured_media );
			if ( $result ) {
				return true;
			} else {
				return new WP_Error( 'rest_invalid_featured_media', __( 'Invalid featured media ID.' ), array( 'status' => 400 ) );
			}
		} else {
			return delete_post_thumbnail( $post_id );
		}
	}

	// log post type and ID from REST handler for reference by subsequent PP filters 
	function rest_pre_dispatch( $rest_response, $rest_server, $request ) {
		$this->doing_rest = true;

		require_once( dirname(__FILE__).'/rest_rvy.php' );
		$this->rest = new Revisionary_REST();
		
		$rest_response = $this->rest->pre_dispatch( $rest_response, $rest_server, $request );

		if ($this->rest->is_posts_request) {
			add_action( 
				"rest_insert_{$this->rest->post_type}", 
				array($this, 'act_rest_insert'), 
				10, 
				3 
			);
		}

		return $rest_response;
	}

	// prevent revisors from editing other users' regular drafts and pending posts
	function flt_limit_others_drafts( $caps, $meta_cap, $user_id, $args ) {
		if ( ! in_array( $meta_cap, array( 'edit_post', 'edit_page' ) ) )
			return $caps;
		
		$object_id = ( is_array($args) && ! empty($args[0]) ) ? $args[0] : $args;
		
		if ( ! $object_id || ! is_scalar($object_id) || ( $object_id < 0 ) )
			return $caps;
		
		if ( ! rvy_get_option( 'require_edit_others_drafts' ) )
			return $caps;

		if ( $post = get_post( $object_id ) ) {
			//if ( ('revision' != $post->post_type) && ! rvy_is_revision_status($post->post_status) ) {
				$status_obj = get_post_status_object( $post->post_status );

				if (!rvy_is_post_author($post) && $status_obj && ! $status_obj->public && ! $status_obj->private) {
					$post_type_obj = get_post_type_object( $post->post_type );
					if ( agp_user_can( $post_type_obj->cap->edit_published_posts, 0, '', array('skip_revision_allowance' => true) ) ) {	// don't require any additional caps for sitewide Editors
						return $caps;
					}
				
					if(rvy_is_revision_status($post->post_status)) {
						$caps[]= "edit_others_drafts";
					} else {
						static $stati;

						if ( ! isset($stati) ) {
							$stati = get_post_stati( array( 'internal' => false, 'protected' => true ) );
							$stati = array_diff( $stati, array( 'future' ) );
						}

						if ( in_array( $post->post_status, $stati ) ) {
							$caps[]= "edit_others_drafts";
						}
					}
				}
			//}
		}
		
		return $caps;
	}
	
	function set_content_roles( $content_roles_obj ) {
		$this->content_roles = $content_roles_obj;

		if ( ! defined( 'RVY_CONTENT_ROLES' ) ) {
			define( 'RVY_CONTENT_ROLES', true );
		}
	}
	
	// we generally want Revisors to edit other users' posts, but not other users' revisions
	// @todo: impose edit_others_revisions requirement via filter 
	/*
	function set_revision_capdefs() {
		global $wp_post_types;
		if ( 'edit_others_posts' == $wp_post_types['revision']->cap->edit_others_posts ) {
			$wp_post_types['revision']->cap->edit_others_posts = 'edit_others_revisions';
			//$wp_post_types['revision']->cap->delete_others_posts = 'delete_others_revisions';
		}
	}
	*/
	
	// @todo: still needed?
	// work around WP query_posts behavior (won't allow preview on posts unless status is public, private or protected)
	function inherit_status_workaround( $results ) {
		if ( isset( $this->orig_inherit_protected_value ) )
			return $results;
		
		$this->orig_inherit_protected_value = $GLOBALS['wp_post_statuses']['inherit']->protected;
		
		$GLOBALS['wp_post_statuses']['inherit']->protected = true;
		return $results;
	}
	
	function undo_inherit_status_workaround( $results ) {
		if ( ! empty( $this->orig_inherit_protected_value ) )
			$GLOBALS['wp_post_statuses']['inherit']->protected = $this->orig_inherit_protected_value;
		
		return $results;
	}
	
	function act_new_blog( $blog_id, $user_id ) {
		rvy_add_revisor_role( $blog_id );
	}
	
	function flt_has_cap_bypass( $bypass, $wp_sitecaps, $pp_reqd_caps, $args ) {
		if ( ! $GLOBALS['pp_attributes']->is_metacap( $args[0] ) && ( ! array_intersect( $pp_reqd_caps, array_keys($GLOBALS['pp_attributes']->condition_cap_map) )
		|| ( is_admin() && strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/post.php' ) && ! is_array($args[0]) && ( false !== strpos( $args[0], 'publish_' ) && empty( $_REQUEST['publish'] ) ) ) )
		) {						// @todo: simplify (Press Permit filter for publish_posts cap check which determines date selector visibility)
			return $wp_sitecaps;
		}

		return $bypass;
	}
	
	function flt_post_map_meta_cap($caps, $cap, $user_id, $args) {
		if (!in_array($cap, array('edit_post', 'edit_page', 'delete_post', 'delete_page'))) {
			return $caps;
		}

		if (!empty($args[0])) {
				$post_id = (is_object($args[0])) ? $args[0]->ID : $args[0];
		} else {
				$post_id = 0;
		}

		if ($post = get_post($post_id)) {
			if ('inherit' == $post->post_status) {
				return $caps;
			}
		}
		
		if (in_array($cap, array('edit_post', 'edit_page'))) {
			// Run reqd_caps array through the filter which is normally used to implicitly grant edit_published cap to Revisors
			// Applying this adjustment to reqd_caps instead of user caps on 'edit_post' checks allows for better compat with PressPermit and other plugins
			if ($grant_caps = $this->filter_caps(array(), $caps, array(0 => $cap, 1 => $user_id, 2 => $post_id), array('filter_context' => 'map_meta_cap'))) {
				$caps = array_diff($caps, array_keys(array_filter($grant_caps)));
			}
		}

		if ($post && ('future-revision' == $post->post_status)) {
					// allow Revisor to view a preview of their scheduled revision
					if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || empty($_REQUEST['preview']) || !empty($_POST) || did_action('template_redirect')) {
						if ($type_obj = get_post_type_object( $post->post_type )) {
							$check_cap = in_array($cap, ['delete_post', 'delete_page']) ? $type_obj->cap->delete_published_posts : $type_obj->cap->edit_published_posts;
							return array_merge($caps, [$check_cap => true]);
						}
					}
				}

		return $caps;
	}

	function flt_user_has_cap($wp_blogcaps, $reqd_caps, $args) {
		return $this->filter_caps($wp_blogcaps, $reqd_caps, $args);
	}

	private function filter_caps($wp_blogcaps, $reqd_caps, $args, $internal_args = array()) {
		//if ( ! rvy_get_option('pending_revisions') )
		//	return $wp_blogcaps;

		if (!rvy_get_option('pending_revisions')) {
			return $wp_blogcaps;
		}

		$script_name = $_SERVER['SCRIPT_NAME'];
		
		if ( ( defined( 'PRESSPERMIT_VERSION' ) || defined( 'PP_VERSION' ) || defined( 'PPC_VERSION' ) ) && ( strpos( $script_name, 'p-admin/post.php' ) || rvy_wp_api_request() ) ) {
			$support_publish_cap = empty( $_REQUEST['publish'] ) && ! is_array($args[0]) && ( false !== strpos( $args[0], 'publish_' ) );  // TODO: support custom publish cap prefix without perf hit?
		}

		$is_meta_cap_call = is_array($internal_args) && !empty($internal_args['filter_context']) && ('map_meta_cap' == $internal_args['filter_context']);

		// Featured image selection by Revisor
		if (($this->doing_rest || (defined('DOING_AJAX') && DOING_AJAX)) && ('edit_media' == reset($reqd_caps) || 'edit_others_media' == reset($reqd_caps)) && (count($reqd_caps) == 1)) {
			if (!empty($wp_blogcaps['upload_files'])) {
				return array_merge($wp_blogcaps, array('edit_media' => true, 'edit_others_media' => true));
			}
		}

		// For 'edit_post' check, filter required capabilities via 'map_meta_cap' filter, then pass 'user_has_cap' unfiltered
		if (in_array($args[0], array('edit_post', 'edit_page')) && ! $is_meta_cap_call) {
			return $wp_blogcaps;
		}

		if ( ! in_array( $args[0], array( 'edit_post', 'edit_page', 'delete_post', 'delete_page' ) ) && empty($support_publish_cap) ) {			
			if ( ( 
				( ! strpos( $script_name, 'p-admin/post.php' ) || empty( $_POST ) ) 
				&& ! $this->doing_rest && ! rvy_wp_api_request() 
				&& ( ! defined('DOING_AJAX') || ! DOING_AJAX ) 
				) || empty( $_REQUEST['action'] ) || ( 'editpost' != $_REQUEST['action'] ) 
			) {
				if ( ! in_array( $args[0], array( 'edit_published_pages', 'edit_others_pages', 'edit_private_pages', 'edit_pages', 'publish_pages', 'publish_posts' ) ) ) {
					return $wp_blogcaps;
				}
			}
		}

		// integer value indicates internally triggered on previous execution of this filter
		if ( 1 === $this->skip_revision_allowance ) {
			$this->skip_revision_allowance = false;
		}

		if ( ! empty($args[2]) )
			$post_id = $args[2];
		else
			$post_id = rvy_detect_post_id();

		if ( $post = get_post( $post_id ) ) {
			$object_type = $post->post_type;								// todo: better API?
		} elseif (($post_id == -1) && defined('PRESSPERMIT_PRO_VERSION') && !empty(presspermit()->meta_cap_post)) {  // wp_cache_add(-1) does not work for map_meta_cap call on get-revision-diffs ajax call 
			$post = presspermit()->meta_cap_post;
			$object_type = $post->post_type;
		} else {
			$object_type = rvy_detect_post_type();
		}

		if (!empty($_REQUEST['action']) && ('inline-save' == $_REQUEST['action']) && !rvy_is_revision_status($post->post_status)) {
			$this->skip_revision_allowance = true;
		}

		if ( rvy_get_option( 'revisor_lock_others_revisions' ) ) {
			if ( $post ) {
				// Revisors are enabled to edit other users' posts for revision, but cannot edit other users' revisions unless cap is explicitly set sitewide
				if ( rvy_is_revision_status($post->post_type) && ! $this->skip_revision_allowance ) {
					if (!rvy_is_post_author($post)) {
						if ( empty( $GLOBALS['current_user']->allcaps['edit_others_revisions'] ) ) {
							$this->skip_revision_allowance = 1;
						}
					}
				}
			}
		}
		
		$object_type_obj = get_post_type_object( $object_type );
		
		if ( empty( $object_type_obj->cap ) )
			return $wp_blogcaps;
		
		$cap = $object_type_obj->cap;
		
		$edit_published_cap = ( isset($cap->edit_published_posts) ) ? $cap->edit_published_posts : "edit_published_{$object_type}s";
		$edit_private_cap = ( isset($cap->edit_private_posts) ) ? $cap->edit_private_posts : "edit_private_{$object_type}s";

		$this->skip_revision_allowance = !apply_filters('revisionary_apply_revision_allowance', !$this->skip_revision_allowance, $post_id);

		if ( ! $this->skip_revision_allowance ) {
			// Allow Contributors / Revisors to edit published post/page, with change stored as a revision pending review
			$replace_caps = array( 'edit_published_posts', $edit_published_cap, 'edit_private_posts', $edit_private_cap );
			
			if ( ! strpos( $script_name, 'p-admin/edit.php' ) ) {
				$replace_caps = array_merge( $replace_caps, array( $cap->publish_posts, 'publish_posts' ) );
			}
			
			if ( array_intersect( $reqd_caps, $replace_caps) ) {	// don't need to fudge the capreq for post.php unless existing post has public/private status
				if ( is_preview() || rvy_wp_api_request() || strpos($script_name, 'p-admin/edit.php') || strpos($script_name, 'p-admin/widgets.php') 
				|| ( !empty($post) && in_array( $post->post_status, array('publish', 'private') ) ) 
				) {
					if ( $type_obj = get_post_type_object( $object_type ) ) {
						if ( ! empty( $wp_blogcaps[ $type_obj->cap->edit_posts ] ) || $is_meta_cap_call) {
							foreach ( $replace_caps as $replace_cap_name ) {
								$wp_blogcaps[$replace_cap_name] = true;
							}
						}
					}
				}
			}
		}
		
		// Special provision for Pages - @todo: still needed?
		if ( is_admin() && in_array( 'edit_others_posts', $reqd_caps ) && ( 'post' != $object_type ) ) {
			// Allow contributors to edit published post/page, with change stored as a revision pending review
			if ( ! rvy_metaboxes_started() && ! strpos($script_name, 'p-admin/revision.php') && false === strpos(urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions' )  ) // don't enable contributors to view/restore revisions
				$use_cap_req = $cap->edit_posts;
			else
				$use_cap_req = $edit_published_cap;
				
			if ( ! empty( $wp_blogcaps[$use_cap_req] ) )
				$wp_blogcaps['edit_others_posts'] = true;
		}

		// TODO: possible need to redirect revision cap check to published parent post/page ( RS cap-interceptor "maybe_revision" )
		return $wp_blogcaps;			
	}

	function flt_pendingrev_post_status($status) {
		if (rvy_is_revision_status($status) || ('inherit' == $status)) {
			return $status;
		}

		if ( $this->doing_rest && $this->rest->is_posts_request ) {
			$post_id = $this->rest->post_id;
		} elseif ( ! empty( $_POST['post_ID'] ) ) {
			$post_id = $_POST['post_ID'];
		} else {
			$post_id = rvy_detect_post_id();
		} 
		
		if ( empty( $post_id ) || !is_scalar($post_id) ) {
			return $status;
		}

		global $current_user;

		if (get_post_meta( $post_id, "_save_as_revision_{$current_user->ID}", true )) {
			$this->impose_pending_rev[$post_id] = true;
			return $status;
		}
		
		// Make sure the stored post is published / scheduled		
		// With Events Manager plugin active, Role Scoper 1.3 to 1.3.12 caused this filter to fire prematurely as part of object_id detection, flagging for pending_rev needlessly on update of an unpublished post
		if ( $stored_post = get_post( $post_id ) )
			$status_obj = get_post_status_object( $stored_post->post_status );

		if ( empty($status_obj) || ( ! $status_obj->public && ! $status_obj->private && ( 'future' != $stored_post->post_status ) ) ) {
			return $status;
		}
		
		if ( ! empty( $_POST['rvy_save_as_pending_rev'] ) && ! empty($post_id) ) {
			$this->impose_pending_rev[$post_id] = true;
		}
		
		if ( is_content_administrator_rvy() )
			return $status;
		
		if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) ) {
			return $status;
		}	

		if ( $this->doing_rest && $this->rest->is_posts_request ) {
			$post_type = $this->rest->post_type;
		} elseif ( ! empty( $_POST['post_type'] ) ) {
			$post_type = $_POST['post_type'];
		} else {
			$post_type = rvy_detect_post_type();
		} 
			
		if ( ! empty( $post_type ) ) {
			if ( $type_obj = get_post_type_object( $post_type ) ) {
				if ( ! agp_user_can( $type_obj->cap->edit_post, $post_id, '', array( 'skip_revision_allowance' => true ) ) ) {
					$this->impose_pending_rev[$post_id] = true;
			}
		}
		}
		
		return $status;
	}

	function flt_maybe_insert_revision($data, $postarr) {
		if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) ) {
			return $data;
		}
	
		if ( empty( $postarr['ID'] ) || empty($this->impose_pending_rev[ $postarr['ID'] ]) ) {
			return $data;
		}

		// todo: consolidate functions
		$this->flt_pendingrev_post_status($data['post_status']);

		if ( $this->doing_rest && ! $this->rest->is_posts_request ) {
			return $data;
		}

		if ( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions' ) ) {
			return $data;
		}
		
		if ( isset($_POST['action']) && ( 'autosave' == $_POST['action'] ) ) {
			if ( $this->doing_rest ) {
				exit;
			} else {
				rvy_halt( __('Autosave disabled when editing a published post/page to create a pending revision.', 'revisionary' ) );
			}
		}
		
		return $this->flt_pending_revision_data($data, $postarr);
	}
	
	// impose pending revision
	function flt_pending_revision_data( $data, $postarr ) {
		global $wpdb, $current_user;
		
		if ( $this->doing_rest && $this->rest->is_posts_request && ! empty( $this->rest->request ) ) {
			$postarr = array_merge( $this->rest->request->get_params(), $postarr );
			
			if (isset($postarr['featured_media'])) {
				$postarr['_thumbnail_id'] = $postarr['featured_media'];
			}
		}
		
		$published_post = get_post( $postarr['ID'] );

		if ((('revision' == $published_post->post_type) || ('auto-save' == $published_post->post_status)) && $published_post->post_parent) {
			$published_post = get_post($published_post->post_parent);
		}

		if ($return_data = apply_filters('revisionary_pending_revision_intercept', [], $data, $postarr, $published_post)) {
			return $return_data;
		}

		if ( $this->isBlockEditorActive() && !$this->doing_rest ) {
			if (!empty($_REQUEST['meta-box-loader']) && !empty($_REQUEST['action']) && ('editpost' == $_REQUEST['action'])) {
				// Use logged revision ID from preceding REST query
				if (!$revision_id = get_transient("_rvy_pending_revision_{$current_user->ID}_{$postarr['ID']}")) {
					return $data;
				}
			} else {
				//delete_transient("_rvy_pending_revision_{$current_user->ID}_{$postarr['ID']}");
			}
		}

		if (!empty($_POST)) {
			$_POST['skip_sitepress_actions'] = true;
		}

		if (!empty($revision_id) && $post = get_post($revision_id)) {
			$post_ID = $revision_id;
			$post_arr['post_ID'] = $revision_id;
			$data = wp_unslash((array) $post);
		} else {
			$post_ID = 0;
			$previous_status = 'new';
		
			foreach ( array( 
				'post_author', 
				'post_date', 
				'post_date_gmt', 
				'post_content', 
				'post_content_filtered', 
				'post_title', 
				'post_excerpt', 
				'post_status', 
				'post_type', 
				'comment_status', 
				'ping_status', 
				'post_password', 
				'post_name', 
				'to_ping', 
				'pinged', 
				'post_modified', 
				'post_modified_gmt', 
				'post_parent', 
				'menu_order', 
				'post_mime_type', 
				'guid' 
			) as $col ) {
				$$col = (isset($data[$col])) ? $data[$col] : '';
			}

			$data['post_status'] = 'pending-revision';
			//$data['parent_id'] = $data['post_parent'];
			$data['comment_count'] = $published_post->ID; 	// buffer this value in posts table for query efficiency (actual comment count stored for published post will not be overwritten)
			$postarr['post_ID'] = 0;
			$data['ID'] = 0;
			$data['guid'] = '';
			$data['post_name'] = '';

			/*	
			if ( defined('RVY_CONTENT_ROLES') ) {
				if ( isset($data['post_category']) ) {	// todo: also filter other post taxonomies
					$data['post_category'] = $this->content_roles->filter_object_terms( $data['post_category'], 'category' );
				}
			}	
			*/
			
			if ( $future_date = ! empty($data['post_date']) && ( strtotime($data['post_date_gmt'] ) > agp_time_gmt() ) ) {  // $future_date is also passed to get_revision_msg()
				// round down to zero seconds
				$data['post_date_gmt'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date_gmt'] ) );
				$data['post_date'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date'] ) );
			}

			$data = wp_unslash( $data );

			$revision_id = $this->create_revision($data, $postarr);
			if (!is_scalar($revision_id)) { // update_post_data() returns array or object on update abandon / failure
				return $revision_id;
			}

			$post = get_post($revision_id);
		}

		// Pro: better compatibility in third party action handlers
		$revision_id = (int) $revision_id;

		unset($this->impose_pending_rev[ $published_post->ID ]);
		
		if ( $revision_id ) {
			set_transient("_rvy_pending_revision_{$current_user->ID}_{$postarr['ID']}", $revision_id, 30);

			update_post_meta($revision_id, '_rvy_base_post_id', $published_post->ID);
			update_post_meta($published_post->ID, '_rvy_has_revisions', true);

			$post_id = $published_post->ID;						  // passing args ensures back compat by using variables directly rather than retrieving revision, post data
			$object_type = isset($postarr['post_type']) ? $postarr['post_type'] : '';
			$msg = $this->get_revision_msg( $revision_id, compact( 'data', 'post_id', 'object_type', 'future_date' ) );
		} else {
			$msg = __('Sorry, an error occurred while attempting to submit your revision!', 'revisionary') . ' ';
			rvy_halt( $msg, __('Revision Submission Error', 'revisionary') );
		}
	
		if (!$this->doing_rest) {
			$_POST['ID'] = $revision_id;
			$_REQUEST['ID'] = $revision_id;

			do_action( 'revisionary_save_revision', $post );
			do_action( "save_post_{$post->post_type}", $revision_id, $post, false );
			do_action( 'save_post', $revision_id, $post, false );
			do_action( 'wp_insert_post', $revision_id, $post, false );
			do_action( 'revisionary_saved_revision', $post );
		}

		if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
			// Make sure Multiple Authors plugin does not change post_author value for revisor. Authors taxonomy terms can be revisioned for published post.
			$wpdb->update($wpdb->posts, ['post_author' => $current_user->ID], ['ID' => $revision_id]);
			
			// Make sure Multiple Authors plugin does not change post_author value for published post on revision submission.
			$wpdb->update($wpdb->posts, ['post_author' => $published_post->post_author], ['ID' => $published_post->ID]);
			
			// On some sites, MA autosets Authors to current user. Temporary workaround: if Authors are set to current user, revert to published post terms.
			$_authors = get_multiple_authors($revision_id);
			
			if (count($_authors) == 1) {
				$_author = reset($_authors);

				if ($_author && empty($_author->ID)) { // @todo: is this still necessary?
				$_author = MultipleAuthors\Classes\Objects\Author::get_by_term_id($_author->term_id);
			}
			}

			$published_authors = get_multiple_authors($published_post->ID);

			// If multiple authors could not be stored, restore original authors from published post
			if (empty($_authors) || (!empty($_author) && $_author->ID == $current_user->ID)) {
				if (!$published_authors) {
					if ($author = MultipleAuthors\Classes\Objects\Author::get_by_user_id((int) $published_post->post_author)) {
						$published_authors = [$author];
					}
				}

				if ($published_authors) {
					// This sets author taxonomy terms and meta field ppma_author_name
					_rvy_set_ma_post_authors($revision_id, $published_authors);

					// Also ensure meta field is set for published post
					_rvy_set_ma_post_authors($published_post->ID, $published_authors);
				}
			}
			
			if (!defined('REVISIONARY_DISABLE_MA_AUTHOR_RESTORATION')) {
				// Fix past overwrites of published post_author field by copying correct author ID back from multiple authors array
				if ($published_authors && $published_post->post_author) {
					$author_user_ids = [];
					foreach($published_authors as $author) {
						$author_user_ids []= $author->user_id;
					}

					if (!in_array($published_post->post_author, $author_user_ids)) {
						$author = reset($published_authors);
						if (is_object($author) && !empty($author->user_id)) {
							$wpdb->update($wpdb->posts, ['post_author' => $author->user_id], ['ID' => $published_post->ID]);
						}
					}
				}
			}
		}

		if ( $this->doing_rest || apply_filters('revisionary_limit_revision_fields', false, $post, $published_post) ) {
			// prevent alteration of published post, while allowing save operation to complete
			$data = array_intersect_key( (array) $published_post, array_fill_keys( array( 'ID', 'post_type', 'post_name', 'post_status', 'post_parent', 'post_author' ), true ) );
		
		}

		do_action('revisionary_created_revision', $post);

		if (apply_filters('revisionary_do_revision_notice', !$this->doing_rest, $post, $published_post)) {
			$object_type = isset($postarr['post_type']) ? $postarr['post_type'] : '';
			$args = compact( 'revision_id', 'published_post', 'object_type' );
			if ( ! empty( $_REQUEST['prev_cc_user'] ) ) {
				$args['selected_recipients'] = $_REQUEST['prev_cc_user'];
			}
			$this->do_notifications( 'pending-revision', 'pending-revision', $postarr, $args );
			rvy_halt( $msg, __('Pending Revision Created', 'revisionary') );
		}

		return $data;
	}

	// If Scheduled Revisions are enabled, don't allow WP to force current post status to future based on publish date
	function flt_insert_post_data( $data, $postarr ) {
		if ( ( 'future' == $data['post_status'] ) && ( rvy_is_status_published( $postarr['post_status'] ) ) ) {
			// don't interfere with scheduling of unpublished drafts
			if ( $stored_status = get_post_field ( 'post_status', rvy_detect_post_id() ) ) {
				if ( rvy_is_status_published( $stored_status ) ) {
					$data['post_status'] = $postarr['post_status'];
				}
			}
		}
		
		return $data;
	}
	
	function flt_create_scheduled_rev( $data, $post_arr ) {
		global $current_user, $wpdb;

		if ( empty( $post_arr['ID'] ) ) {
			return $data;
		}
		
		if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) ) {
			return $data;
		}
		
		if ( isset($_POST['action']) && ( 'autosave' == $_POST['action'] ) ) {
			return $data;
		}

		if ( $this->doing_rest && ! $this->rest->is_posts_request ) {
			return $data;
		}

		if ( $this->doing_rest && $this->rest->is_posts_request && ! empty( $this->rest->request ) ) {
			$post_arr = array_merge( $this->rest->request->get_params(), $post_arr );
			
			if (isset($post_arr['featured_media'])) {
				$post_arr['_thumbnail_id'] = $post_arr['featured_media'];
			}
		}

		if ( $this->isBlockEditorActive() && !$this->doing_rest ) {
			if (!empty($_REQUEST['meta-box-loader']) && !empty($_REQUEST['action']) && ('editpost' == $_REQUEST['action'])) {
				// Use logged revision ID from preceding REST query
				if (!$revision_id = get_transient("_rvy_scheduled_revision_{$current_user->ID}_{$post_arr['ID']}")) {
					return $data;
				}
			} else {
				//delete_transient("_rvy_scheduled_revision_{$current_user->ID}_{$post_arr['ID']}");
			}
		}

		if ( $this->doing_rest ) {
			$original_post_status = get_post_field( 'post_status', $post_arr['ID']);
		} else { 
			// @todo: eliminate this?
			$original_post_status = ( isset( $_POST['original_post_status'] ) ) ? $_POST['original_post_status'] : '';
			
			if ( ! $original_post_status ) {
				$original_post_status = ( isset( $_POST['hidden_post_status'] ) ) ? $_POST['hidden_post_status'] : '';
			}
		}

		// don't interfere with scheduling of unpublished drafts
		if ( ! $stored_status_obj = get_post_status_object( $original_post_status ) ) {
			return $data;
		}

		if ( empty( $stored_status_obj->public ) && empty( $stored_status_obj->private ) ) {
			return $data;
		}

		if ( ! $published_post = get_post( $post_arr['ID'] ) ) {
			return $data;
		}
		
		if ( empty($post_arr['post_date_gmt']) || ( strtotime($post_arr['post_date_gmt'] ) <= agp_time_gmt() ) ) {
			// Allow continued processing for non-REST followup query after REST operation
			if (empty($_REQUEST['meta-box-loader']) || empty($_REQUEST['action']) || ('editpost' != $_REQUEST['action'])) {
				return $data;
			}
		}

		if ( $type_obj = get_post_type_object( $published_post->post_type ) ) {
			if ( ! agp_user_can( $type_obj->cap->edit_post, $published_post->ID, $current_user->ID, array( 'skip_revision_allowance' => true ) ) )
				return $data;
		}
		
		// @todo: need to filter post parent?

		$data['post_status'] = 'future-revision';
		//$post_arr['parent_id'] = $post_arr['post_parent'];
		$data['comment_count'] = $published_post->ID; 	// buffer this value in posts table for query efficiency (actual comment count stored for published post will not be overwritten)
		$post_arr['post_ID'] = 0;
		//$post_arr['guid'] = '';
		$data['guid'] = '';
		
		/*
		if ( defined('RVY_CONTENT_ROLES') ) {
			if ( isset($post_arr['post_category']) ) {	// todo: also filter other post taxonomies
				$post_arr['post_category'] = $this->content_roles->filter_object_terms( $post_arr['post_category'], 'category' );
			}
		}
		*/

		$this->save_future_rev[$published_post->ID] = true;

		if (!empty($revision_id) && $post = get_post($revision_id)) {
			$post_ID = $revision_id;
			$post_arr['post_ID'] = $revision_id;
			$data = wp_unslash((array) $post);
		} else {
			unset($data['post_ID']);

			// round down to zero seconds
			$data['post_date_gmt'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date_gmt'] ) );
			$data['post_date'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date'] ) );

			$revision_id = $this->create_revision($data, $post_arr);
			if (!is_scalar($revision_id)) { // update_post_data() returns array or object on update abandon / failure
				return $revision_id;
			}

			if ($revision_id) {
				set_transient("_rvy_scheduled_revision_{$current_user->ID}_{$post_arr['ID']}", $revision_id, 30);

				update_post_meta($revision_id, '_rvy_base_post_id', $published_post->ID);
				update_post_meta($published_post->ID, '_rvy_has_revisions', true);
			} else {
				$msg = __('Sorry, an error occurred while attempting to schedule your revision!', 'revisionary') . ' ';
				rvy_halt( $msg, __('Revision Scheduling Error', 'revisionary') );
			}

			$post = get_post($revision_id);
		}
	
		// Pro: better compatibility in third party action handlers
		$revision_id = (int) $revision_id;

		if (!$this->doing_rest) {
			$_POST['ID'] = $revision_id;
			$_REQUEST['ID'] = $revision_id;

			do_action( 'revisionary_save_revision', $post );
			do_action( "save_post_{$post->post_type}", $revision_id, $post, false );
			do_action( 'save_post', $revision_id, $post, false );
			do_action( 'wp_insert_post', $revision_id, $post, false );
			do_action( 'revisionary_saved_revision', $post );
		}

		if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
			// Make sure Multiple Authors plugin does not change post_author value for revisor. Authors taxonomy terms can be revisioned for published post. 
			$wpdb->update($wpdb->posts, ['post_author' => $current_user->ID], ['ID' => $revision_id]);

			// Make sure Multiple Authors plugin does not change post_author value for published post on revision submission.
			$wpdb->update($wpdb->posts, ['post_author' => $published_post->post_author], ['ID' => $published_post->ID]);
		}

		require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
		rvy_update_next_publish_date();

		if ( $this->doing_rest ) {
			// prevent alteration of published post, while allowing save operation to complete
			$data = array_intersect_key( (array) $published_post, array_fill_keys( array( 'ID', 'post_name', 'post_status', 'post_parent', 'post_author' ), true ) );
			update_post_meta( $published_post->ID, "_new_scheduled_revision_{$current_user->ID}", $revision_id );
		} else {
			$msg = $this->get_revision_msg( $revision_id, array( 'post_id' => $published_post->ID ) );
			rvy_halt( $msg, __('Scheduled Revision Created', 'revisionary') );
		}

		return $data;
	}

	private function create_revision($data, $postarr) {
		global $wpdb, $current_user;

		$data['post_author'] = $current_user->ID;		// store current user as revision author (but will retain current post_author on restoration)

		$data['post_modified'] = current_time( 'mysql' );
		$data['post_modified_gmt'] = current_time( 'mysql', 1 );

		$data = wp_unslash($data);

		$post_type = $data['post_type'];
		unset($data['ID']);

		if ( false === $wpdb->insert( $wpdb->posts, $data ) ) {
			if (!empty($wpdb->last_error)) {
				return new WP_Error( 'db_insert_error', __( 'Could not insert post into the database' ), $wpdb->last_error );
			} else {
				return 0;
			}
		}

		$post_ID = (int) $wpdb->insert_id; // revision_id
		$revision_id = $post_ID;

		$published_post_id = rvy_post_id($data['comment_count']);

		// Workaround for Gutenberg stripping post thumbnail, page template on revision creation
		$archived_meta = [];
		foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
			$archived_meta[$meta_key] = get_post_meta($published_post_id, $meta_key, true);
		}

		// Use the newly generated $post_ID.
		$where = array( 'ID' => $post_ID );
		
		$data['post_name'] = wp_unique_post_slug( sanitize_title( $data['post_title'], $post_ID ), $post_ID, $data['post_status'], $data['post_type'], $data['post_parent'] );
		$wpdb->update( $wpdb->posts, array( 'post_name' => $data['post_name'] ), $where );

		if ( ! empty( $postarr['post_category'] ) ) {
		if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
			$post_category = $postarr['post_category'];
			wp_set_post_categories( $post_ID, $post_category );
			}
		}
	
		if ( isset( $postarr['tags_input'] ) && is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
			wp_set_post_tags( $post_ID, $postarr['tags_input'] );
		}
	
		// New-style support for all custom taxonomies.
		if ( ! empty( $postarr['tax_input'] ) ) {
			foreach ( $postarr['tax_input'] as $taxonomy => $tags ) {
				$taxonomy_obj = get_taxonomy( $taxonomy );
				if ( ! $taxonomy_obj ) {
					/* translators: %s: taxonomy name */
					_doing_it_wrong( __FUNCTION__, sprintf( __( 'Invalid taxonomy: %s.' ), $taxonomy ), '4.4.0' );
					continue;
				}
	
				// array = hierarchical, string = non-hierarchical.
				if ( is_array( $tags ) ) {
					$tags = array_filter( $tags );
				}
				if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
					wp_set_post_terms( $post_ID, $tags, $taxonomy );
				}
			}
		}
	
		if ( ! empty( $postarr['meta_input'] ) ) {
			foreach ( $postarr['meta_input'] as $field => $value ) {
				update_post_meta( $post_ID, $field, $value );
			}
		}
	
		$current_guid = get_post_field( 'guid', $post_ID );
	
		// Set GUID.
		if ( '' == $current_guid ) {
			// need to give revision a guid for 3rd party editor compat (post_ID is ID of revision)
			$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post_ID ) ), $where );
		}
	
		if ( 'attachment' === $postarr['post_type'] ) {
			if ( ! empty( $postarr['file'] ) ) {
				update_attached_file( $post_ID, $postarr['file'] );
			}
	
			if ( ! empty( $postarr['context'] ) ) {
				update_post_meta( $post_ID, '_wp_attachment_context', $postarr['context'], true );
			}
		}
	
		// Set or remove featured image.
		if ( isset( $postarr['_thumbnail_id'] ) ) {
			$thumbnail_support = current_theme_supports( 'post-thumbnails', $post_type ) && post_type_supports( $post_type, 'thumbnail' ) || 'revision' === $post_type;
			if ( ! $thumbnail_support && 'attachment' === $post_type && $post_mime_type ) {
				if ( wp_attachment_is( 'audio', $post_ID ) ) {
					$thumbnail_support = post_type_supports( 'attachment:audio', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:audio' );
				} elseif ( wp_attachment_is( 'video', $post_ID ) ) {
					$thumbnail_support = post_type_supports( 'attachment:video', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:video' );
				}
			}
	
			if ( $thumbnail_support ) {
				$thumbnail_id = intval( $postarr['_thumbnail_id'] );
				if ( -1 === $thumbnail_id ) {
					delete_post_thumbnail( $post_ID );
				} else {
					set_post_thumbnail( $post_ID, $thumbnail_id );
				}
			}
		}

		clean_post_cache( $post_ID );

		$post = get_post( $post_ID );
	
		if ( ! empty( $postarr['page_template'] ) ) {
			$post->page_template = $postarr['page_template'];
			$page_templates      = wp_get_theme()->get_page_templates( $post );
			if ( 'default' != $postarr['page_template'] && ! isset( $page_templates[ $postarr['page_template'] ] ) ) {
				if ( $wp_error ) {
					return new WP_Error( 'invalid_page_template', __( 'Invalid page template.' ) );
				}
				update_post_meta( $post_ID, '_wp_page_template', 'default' );
			} else {
				update_post_meta( $post_ID, '_wp_page_template', $postarr['page_template'] );
			}
		}
	
		// Workaround for Gutenberg stripping post thumbnail, page template on revision creation
		foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
			if (!empty($archived_meta[$meta_key])) {
				update_post_meta($published_post_id, $meta_key, $archived_meta[$meta_key]);
				update_post_meta($published_post_id, "_archive_{$meta_key}", $archived_meta[$meta_key]);
			}
		}

		if ( 'attachment' !== $postarr['post_type'] ) {
			$previous_status = '';
			wp_transition_post_status( $data['post_status'], $previous_status, $post );
		} else {
			/**
			 * Fires once an attachment has been added.
			 *
			 * @param int $post_ID Attachment ID.
			 */
			do_action( 'add_attachment', $post_ID );
	
			return $data;
		}

		return (int) $revision_id; // only return array in calling function should return
	}

	/**
     * Returns true if is a beta or stable version of WP 5.
     *
     * @return bool
     */
    public function isWp5()
    {
        global $wp_version;

        return version_compare($wp_version, '5.0', '>=') || substr($wp_version, 0, 2) === '5.';
    }
	
	/**
	 * Based on Edit Flow's \Block_Editor_Compatible::should_apply_compat method.
	 *
	 * @return bool
	 */
	function isBlockEditorActive() {
		// Check if PP Custom Post Statuses lower than v2.4 is installed. It disables Gutenberg.
		if ( defined('PPS_VERSION') && version_compare(PPS_VERSION, '2.4-beta', '<') ) {
			return false;
		}

		if (class_exists('Classic_Editor')) {
			if (isset($_REQUEST['classic-editor__forget']) && isset($_REQUEST['classic'])) {
				return false;
			} elseif (isset($_REQUEST['classic-editor__forget']) && !isset($_REQUEST['classic'])) {
				return true;
			} elseif ($post_id = rvy_detect_post_id()) {
				$which = get_post_meta( $post_id, 'classic-editor-remember', true );
				
				if ('block-editor' == $which) {
					return true;
				} elseif ('classic-editor' == $which) {
					return false;
				}
			}
		}

		$pluginsState = array(
			'classic-editor' => class_exists( 'Classic_Editor' ), // is_plugin_active('classic-editor/classic-editor.php'),
			'gutenberg'      => function_exists( 'the_gutenberg_project' ), //is_plugin_active('gutenberg/gutenberg.php'),
		);

		if ( ! $postType = rvy_detect_post_type() ) {
			$postType = 'page';
		}
		
		if ( $post_type_obj = get_post_type_object( $postType ) ) {
			if ( empty( $post_type_obj->show_in_rest ) ) {
				return false;
			}
		}

		$conditions = array();

		/**
		 * 5.0:
		 *
		 * Classic editor either disabled or enabled (either via an option or with GET argument).
		 * It's a hairy conditional :(
		 */
		// phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.Security.NonceVerification.NoNonceVerification
		$conditions[] = $this->isWp5()
						&& ! $pluginsState['classic-editor']
						&& apply_filters('use_block_editor_for_post_type', true, $postType );

		/*
		$conditions[] = $this->isWp5()
						&& $pluginsState['classic-editor']
						&& (get_option('classic-editor-replace') === 'block'
							&& ! isset($_GET['classic-editor__forget']));

		$conditions[] = $this->isWp5()
						&& $pluginsState['classic-editor']
						&& (get_option('classic-editor-replace') === 'classic'
							&& isset($_GET['classic-editor__forget']));
		*/

		/**
		 * < 5.0 but Gutenberg plugin is active.
		 */
		$conditions[] = ! $this->isWp5() && $pluginsState['gutenberg'];

		// Returns true if at least one condition is true.
		return count(
				   array_filter($conditions,
					   function ($c) {
						   return (bool)$c;
					   }
				   )
			   ) > 0;
	}

	function do_notifications( $notification_type, $status, $post_arr, $args ) {
		global $rvy_workflow_ui;
		if ( ! isset( $rvy_workflow_ui ) ) {
			require_once( dirname(__FILE__).'/revision-workflow_rvy.php' );
			$rvy_workflow_ui = new Rvy_Revision_Workflow_UI();
		}
		
		return $rvy_workflow_ui->do_notifications( $notification_type, $status, $post_arr, $args );
	}

	function get_revision_msg( $revision_id, $args ) {
		global $rvy_workflow_ui;
		if ( ! isset( $rvy_workflow_ui ) ) {
			require_once( dirname(__FILE__).'/revision-workflow_rvy.php' );
			$rvy_workflow_ui = new Rvy_Revision_Workflow_UI();
		}

		return $rvy_workflow_ui->get_revision_msg( $revision_id, $args );
	}
} // end Revisionary class


// Disable Gutenberg on deprecated Revisions Edit screen
if ( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions') ) {
	$post_types = get_post_types( array( 'public' => true ), 'object' );
	foreach( $post_types as $post_type => $type_obj ) {
		if ( ! defined( 'RVY_FORCE_BLOCKEDIT_' . strtoupper($post_type) ) ) {
			add_filter( "use_block_editor_for_{$post_type}", '__return_false', 10 );
		}
	}
}
