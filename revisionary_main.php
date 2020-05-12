<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );
	
/**
 * @package     PublishPress\Revisions
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2020 PublishPress. All rights reserved.
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

	var $config_loaded = false;		// configuration related to post types and statuses must be loaded late on the init action
	var $enabled_post_types = [];	// enabled_post_types property is set (keyed by post type slug) late on the init action. 

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
								!isset($type_obj->cap->edit_published_posts)
								|| empty($current_user->allcaps[$type_obj->cap->edit_published_posts]) 
								|| (($current_user->ID != $parent_post->ID) && empty($current_user->allcaps[$type_obj->cap->edit_published_posts]))
							)) {
								return;
							}
						}
					}
				}
			}
		}

		$this->setPostTypes();

		rvy_refresh_options_sitewide();

		// NOTE: $_GET['preview'] and $_GET['post_type'] arguments are set by rvy_init() at response to ?p= request when the requested post is a revision.
		if (!is_admin() && (!defined('REST_REQUEST') || ! REST_REQUEST) && ((!empty($_GET['preview']) && empty($_REQUEST['preview_id'])) || !empty($_GET['mark_current_revision']))) { // preview_id indicates a regular preview via WP core, based on autosave revision
			require_once( dirname(__FILE__).'/front_rvy.php' );
			$this->front = new RevisionaryFront();
		}
		
		if (!is_admin() && (!defined('REST_REQUEST') || ! REST_REQUEST) && (!empty($_GET['preview']) && !empty($_REQUEST['preview_id']))) {
			if (defined('REVISIONARY_PREVIEW_WORKAROUND')) { // @todo: confirm this is no longer needed
				if ($_post = get_post($_REQUEST['preview_id'])) {
					if (in_array($_post->post_status, ['pending-revision', 'future-revision']) && !$this->isBlockEditorActive()) {
						if (empty($_REQUEST['_thumbnail_id']) || !get_post($_REQUEST['_thumbnail_id'])) {
							$preview_url = rvy_preview_url($_post);
							wp_redirect($preview_url);
							exit;
						}
					}
				}
			}

			if (!defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
				require_once(dirname(__FILE__).'/classes/PublishPress/Revisions/PostPreview.php');
				new PublishPress\Revisions\PostPreview();
			}
		}
		
		if ( ! is_content_administrator_rvy() ) {
			add_filter( 'map_meta_cap', array($this, 'flt_post_map_meta_cap'), 5, 4);
			add_filter( 'user_has_cap', array( $this, 'flt_user_has_cap' ), 98, 3 );
			add_filter( 'pp_has_cap_bypass', array( $this, 'flt_has_cap_bypass' ), 10, 4 );

			add_filter( 'map_meta_cap', array( $this, 'flt_limit_others_drafts' ), 10, 4 );
		}

		if ( is_admin() ) {
			require_once( dirname(__FILE__).'/admin/admin_rvy.php');
			$this->admin = new RevisionaryAdmin();
		}	
		
		add_action( 'wpmu_new_blog', array( $this, 'act_new_blog'), 10, 2 );
		
		add_filter( 'posts_results', array( $this, 'inherit_status_workaround' ) );
		add_filter( 'the_posts', array( $this, 'undo_inherit_status_workaround' ) );
	
		//add_action( 'wp_loaded', array( &$this, 'set_revision_capdefs' ) );
		
		add_action( 'deleted_post', [$this, 'actDeletedPost']);

		if ( rvy_get_option( 'pending_revisions' ) ) {
			// special filtering to support Contrib editing of published posts/pages to revision
			add_filter('pre_post_status', array($this, 'flt_pendingrev_post_status') );
			add_filter('wp_insert_post_data', array($this, 'flt_maybe_insert_revision'), 2, 2);
		}
		
		if ( rvy_get_option('scheduled_revisions') ) {
			add_filter('wp_insert_post_data', array($this, 'flt_create_scheduled_rev'), 3, 2 );  // other filters will have a chance to apply at actual publish time

			// users who have edit_published capability for post/page can create a scheduled revision by modifying post date to a future date (without setting "future" status explicitly)
			add_filter( 'wp_insert_post_data', array($this, 'flt_insert_post_data'), 99, 2 );
		}

		add_filter( 'wp_insert_post_data', array($this, 'flt_regulate_revision_status'), 100, 2 );

		// REST logging
		add_filter( 'rest_pre_dispatch', array( $this, 'rest_pre_dispatch' ), 10, 3 );

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

		add_filter('post_link', [$this, 'fltEditRevisionUpdatedLink'], 99, 3);

		do_action( 'rvy_init', $this );
	}
	
	function configurationLateInit() {
		$this->setPostTypes();
		$this->config_loaded = true;
	}

	// This is intentionally called twice: once for code that fires on 'init' and then very late on 'init' for types which were registered late on 'init'
	private function setPostTypes() {
		$this->enabled_post_types = apply_filters('revisionary_enabled_post_types', array_fill_keys(get_post_types(['public' => true]), true));
		unset($this->enabled_post_types['attachment']);
		$this->enabled_post_types = array_filter($this->enabled_post_types);
	}

	// If deleted revision was the last remaining pending / scheduled, clear _rvy_has_revisions postmeta flag 
	function actDeletedPost($post_id) {
		revisionary_refresh_postmeta($post_id);
	}

	function fltEditRevisionUpdatedLink($permalink, $post, $leavename) {
		static $busy = false;

		if ($busy) {
			return $permalink;
		}

		$params = (!$this->doing_rest || empty($this->rest->request)) ? $_REQUEST : $this->rest->request->get_params();

		if ((empty($params['action']) || ('edit' != $params['action']))
			&& (empty($params['context']) || ('edit' != $params['context']))
		) {
			return $permalink;
		}

		if ($post_id = rvy_detect_post_id()) {
			if (rvy_is_revision_status(get_post_field('post_status', $post_id))) {
				if ($published_post_id = rvy_post_id($post_id)) {
					$match_ids = [$post_id];
					
					if (!$this->isBlockEditorActive()) {
						$match_ids []= $published_post_id;
					}

					if (in_array($post->ID, $match_ids)) {
						$busy = true;
						$permalink = get_permalink($published_post_id);
						$busy = false;
					}
				}
			}
		}

		return $permalink;
	}

	function actLogPreviewAutosave($post_id, $post) {
		if ('inherit' == $post->post_status && strpos($post->post_name, 'autosave')) {
			$this->last_autosave_id[$post->post_parent] = $post_id;
		}
	}
	
	function fltPressPermitExceptionClause($clause, $required_operation, $post_type, $args) {
		//"$src_table.ID $logic ('" . implode("','", $ids) . "')",

		if (empty($this->enabled_post_types[$post_type]) && $this->config_loaded) {
			return $clause;
		}

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
		require_once(dirname(__FILE__).'/admin/admin-init_rvy.php');

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
		require_once( dirname(__FILE__).'/rest-insert_rvy.php' );
		return _rvy_act_rest_insert($post, $request, $unused);
	}

	private function handle_template( $template, $post_id, $validate = false ) {
		update_post_meta( $post_id, '_wp_page_template', $template );
	}

	public function handle_featured_media( $featured_media, $post_id ) {
		$featured_media = (int) $featured_media;
		if ( $featured_media ) {
			$result = set_post_thumbnail( $post_id, $featured_media );
			if ( $result ) {
				return true;
			} else {
				return new WP_Error( 'rest_invalid_featured_media', __( 'Invalid featured media ID.', 'revisionary' ), array( 'status' => 400 ) );
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
			if ( ('revision' != $post->post_type) && ! rvy_is_revision_status($post->post_status) ) {
				$status_obj = get_post_status_object( $post->post_status );

				if (!apply_filters('revisionary_require_edit_others_drafts', true, $post->post_type, $post->post_status, $args)) {
					return $caps;
				}

				if (!rvy_is_post_author($post) && $status_obj && ! $status_obj->public && ! $status_obj->private) {
					$post_type_obj = get_post_type_object( $post->post_type );
					if (isset($post_type_obj->cap->edit_published_posts) && agp_user_can( $post_type_obj->cap->edit_published_posts, 0, '', array('skip_revision_allowance' => true) ) ) {	// don't require any additional caps for sitewide Editors
						return $caps;
					}
			
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
		global $current_user;
		
		if (!in_array($cap, array('read_post', 'read_page', 'edit_post', 'edit_page', 'delete_post', 'delete_page'))) {
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

			if (empty($this->enabled_post_types[$post->post_type]) && $this->config_loaded) {
				return $caps;
			}
		}

		if ($post && ('future-revision' == $post->post_status)) {
			if (in_array($cap, ['read_post', 'read_page'])) {
				return $caps;
			}
 
			// allow Revisor to view a preview of their scheduled revision
			if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || empty($_REQUEST['preview']) || !empty($_POST) || did_action('template_redirect')) {
				if ($type_obj = get_post_type_object( $post->post_type )) {
					if (isset($type_obj->cap->edit_published_posts)) {
					$check_cap = in_array($cap, ['delete_post', 'delete_page']) ? $type_obj->cap->delete_published_posts : $type_obj->cap->edit_published_posts;
					return array_merge($caps, [$check_cap]);
					} else {
						return $caps;
					}
				}
			}
		}

		if (in_array($cap, ['read_post', 'read_page'])	// WP Query imposes edit_post capability requirement for front end viewing of protected statuses 
			|| (!empty($_REQUEST['preview']) && in_array($cap, array('edit_post', 'edit_page')) && did_action('posts_selection') && !did_action('template_redirect'))
		) {
			if ($post && rvy_is_revision_status($post->post_status)) {
				$type_obj = get_post_type_object($post->post_type);

				if ($type_obj && !empty($type_obj->cap->edit_others_posts)) {
					$caps = array_diff($caps, [$type_obj->cap->edit_others_posts, 'do_not_allow']);

					$check_post = $post;

					if ($post->ID <= 0) {
						if ($check_id = rvy_detect_post_id()) {
							$check_post = get_post($check_id);
						}
					}

					if (rvy_is_post_author($check_post) || rvy_is_post_author(rvy_post_id($check_post->ID)) || rvy_is_full_editor($post)) {
						$caps []= 'read';

					} elseif (rvy_get_option('revisor_hide_others_revisions')) {
						$caps []= 'list_others_revisions';
					
					} else {
						$caps []= $type_obj->cap->edit_posts;
					}
				}
			}

			return $caps;

		} elseif (($post_id > 0) && $post && rvy_is_revision_status($post->post_status) 
			&& rvy_get_option('revisor_lock_others_revisions') && !rvy_is_post_author($post) && !rvy_is_full_editor($post)
		) {
			if ($type_obj = get_post_type_object( $post->post_type )) {
				if (in_array($type_obj->cap->edit_others_posts, $caps)) {					
					if ((!empty($type_obj->cap->edit_others_posts) && empty($current_user->allcaps[$type_obj->cap->edit_others_posts])) 
					|| (!empty($type_obj->cap->edit_published_posts) && empty($current_user->allcaps[$type_obj->cap->edit_published_posts]))
					) {
						if (!empty($current_user->allcaps['edit_others_revisions'])) {
							$caps[] = 'edit_others_revisions';
						} else {
							$caps []= 'do_not_allow';	// @todo: implement this within user_has_cap filters?
						}
					}
				}
			}
		}

		if (in_array($cap, array('edit_post', 'edit_page'))) {
			if ($post && !empty($post->post_status)) {
				if ($status_obj = get_post_status_object($post->post_status)) {
					if (empty($status_obj->public) && empty($status_obj->private)) {
						return $caps;
					}
				}
			}
			
			// Run reqd_caps array through the filter which is normally used to implicitly grant edit_published cap to Revisors
			// Applying this adjustment to reqd_caps instead of user caps on 'edit_post' checks allows for better compat with PressPermit and other plugins
			if ($grant_caps = $this->filter_caps(array(), $caps, array(0 => $cap, 1 => $user_id, 2 => $post_id), array('filter_context' => 'map_meta_cap'))) {
				$caps = array_diff($caps, array_keys(array_filter($grant_caps)));
			}
		}

		return $caps;
	}

	function flt_user_has_cap($wp_blogcaps, $reqd_caps, $args) {
		return $this->filter_caps($wp_blogcaps, $reqd_caps, $args);
	}

	private function filter_caps($wp_blogcaps, $reqd_caps, $args, $internal_args = array()) {
		global $current_user;
		
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

		if (empty($this->enabled_post_types[$object_type]) && $this->config_loaded) {
			return $wp_blogcaps;
		}

		// For 'edit_post' check, filter required capabilities via 'map_meta_cap' filter, then pass 'user_has_cap' unfiltered
		if (in_array($args[0], array('edit_post', 'edit_page')) && ! $is_meta_cap_call) {
			if (empty($post) || !rvy_is_revision_status($post->post_status)) {
				return $wp_blogcaps;
			}
		}

		if ( ! in_array( $args[0], array( 'edit_post', 'edit_page', 'delete_post', 'delete_page' ) ) && empty($support_publish_cap) ) {			
			if ( ( 
				( ! strpos( $script_name, 'p-admin/post.php' ) || empty( $_POST ) ) 
				&& ! $this->doing_rest && ! rvy_wp_api_request() 
				&& ( ! defined('DOING_AJAX') || ! DOING_AJAX )
				) || empty( $_REQUEST['action'] ) || ( 'editpost' != $_REQUEST['action'] ) 
			) {
				if (!apply_filters('revisionary_flag_as_post_update', false, $post_id, $reqd_caps, $args, $internal_args)) {
					if ( ! in_array( $args[0], array( 'edit_published_pages', 'edit_others_pages', 'edit_private_pages', 'edit_pages', 'publish_pages', 'publish_posts' ) ) ) {
						return $wp_blogcaps;
					}
				}
			}
		}

		// integer value indicates internally triggered on previous execution of this filter
		if ( 1 === $this->skip_revision_allowance ) {
			$this->skip_revision_allowance = false;
		}

		if (!empty($_REQUEST['action']) && ('inline-save' == $_REQUEST['action']) && !rvy_is_revision_status($post->post_status)) {
			$this->skip_revision_allowance = true;
		}

		if (rvy_get_option('revisor_lock_others_revisions')) {
			if ($post && !rvy_is_full_editor($post)) {
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
		
		//if (!empty($args[2]) && $post && rvy_is_revision_status($post->post_status)) {
		if ($post && rvy_is_revision_status($post->post_status)) {
			if (in_array($cap->edit_others_posts, $reqd_caps) ) {
				if (!empty($current_user->allcaps['edit_others_revisions']) || !rvy_get_option('revisor_lock_others_revisions')) {
					$wp_blogcaps[$cap->edit_others_posts] = true;
				}
			}
		}

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

		if (!empty($args[0]) && ('edit_post' == $args[0]) && !defined('REVISIONARY_DISABLE_REVISION_CAP_WORKAROUND') && array_diff($reqd_caps, array_keys(array_filter($wp_blogcaps)))) {
			// If checking capability for a revision, also grant permission if user has capability for published post
			$published_id = rvy_post_id($args[2]);
			if ($published_id && ($published_id != $args[2])) {
				remove_filter('map_meta_cap', array($this, 'flt_post_map_meta_cap'), 5, 4);
				remove_filter('user_has_cap', array($this, 'flt_user_has_cap' ), 98, 3);
				remove_filter('map_meta_cap', array($this, 'flt_limit_others_drafts' ), 10, 4);

				if (current_user_can('edit_post', $args[2])) {
					$wp_blogcaps = array_merge($wp_blogcaps, array_fill_keys($reqd_caps, true));
				}

				add_filter('map_meta_cap', array($this, 'flt_post_map_meta_cap'), 5, 4);
				add_filter('user_has_cap', array($this, 'flt_user_has_cap' ), 98, 3);
				add_filter('map_meta_cap', array($this, 'flt_limit_others_drafts' ), 10, 4);
			}
		}

		// TODO: possible need to redirect revision cap check to published parent post/page ( RS cap-interceptor "maybe_revision" )
		return $wp_blogcaps;			
	}

	function flt_pendingrev_post_status($status) {
		require_once( dirname(__FILE__).'/revision-creation_rvy.php' );
		$rvy_creation = new PublishPress\Revisions\RevisionCreation(['revisionary' => $this]);
		return $rvy_creation->flt_pendingrev_post_status($status);
	}

	function flt_maybe_insert_revision($data, $postarr) {
		require_once( dirname(__FILE__).'/revision-creation_rvy.php' );
		$rvy_creation = new PublishPress\Revisions\RevisionCreation(['revisionary' => $this]);
		return $rvy_creation->flt_maybe_insert_revision($data, $postarr);
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
	
	function flt_regulate_revision_status($data, $postarr) {
		// Revisions are not published by wp_update_post() execution; Prevent setting to a non-revision status
		if (get_post_meta($postarr['ID'], '_rvy_base_post_id', true) && ('trash' != $data['post_status'])) {
			$revision = get_post($postarr['ID']);
			
			if (!rvy_is_revision_status($data['post_status'])) {
				$revert_status = true;
			} elseif ($revision) {
				if (($data['post_status'] != $revision->post_status) 
				&& (('future-revision' == $revision->post_status) || ('future-revision' == $postarr['post_status']))
				) {
					$revert_status = true;
				}
			}

			if (!empty($revert_status) && rvy_is_revision_status($revision->post_status)) {
				$data['post_status'] = $revision->post_status;
			}
		}

		return $data;
	}

	function flt_create_scheduled_rev( $data, $post_arr ) {
		global $current_user;

		// If Administrator opted to save as a pending revision, don't apply revision scheduling scripts
		if (get_post_meta($post_arr['ID'], "_save_as_revision_{$current_user->ID}", true)) {
			return $data;
		}

		require_once( dirname(__FILE__).'/revision-creation_rvy.php' );
		$rvy_creation = new PublishPress\Revisions\RevisionCreation(['revisionary' => $this]);
		return $rvy_creation->flt_create_scheduled_rev( $data, $post_arr );
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
			} elseif (get_option('classic-editor-allow-users') === 'allow') {
				if ($post_id = rvy_detect_post_id()) {
					$which = get_post_meta( $post_id, 'classic-editor-remember', true );

					if ('block-editor' == $which) {
						return true;
					} elseif ('classic-editor' == $which) {
						return false;
					}
				}
			}
		}

		$pluginsState = array(
			'classic-editor' => class_exists( 'Classic_Editor' ), // is_plugin_active('classic-editor/classic-editor.php'),
			'gutenberg'      => function_exists( 'the_gutenberg_project' ), //is_plugin_active('gutenberg/gutenberg.php'),
			'gutenberg-ramp' => class_exists('Gutenberg_Ramp'),
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
						&& ! $pluginsState['gutenberg-ramp']
						&& apply_filters('use_block_editor_for_post_type', true, $postType, PHP_INT_MAX);

		$conditions[] = $this->isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'block'
                            && ! isset($_GET['classic-editor__forget']));

        $conditions[] = $this->isWp5()
                        && $pluginsState['classic-editor']
                        && (get_option('classic-editor-replace') === 'classic'
                            && isset($_GET['classic-editor__forget']));

		/**
		 * < 5.0 but Gutenberg plugin is active.
		 */
		$conditions[] = ! $this->isWp5() && ($pluginsState['gutenberg'] || $pluginsState['gutenberg-ramp']);

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

		do_action('revisionary_get_rev_msg', $revision_id, $args);

		return $rvy_workflow_ui->get_revision_msg( $revision_id, $args );
	}
} // end Revisionary class
