<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );
	
/**
 * @package     Revisionary\Revisionary
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
	var $impose_pending_rev = array();
	
	// minimal config retrieval to support pre-init usage by WP_Scoped_User before text domain is loaded
	function __construct() {
		rvy_refresh_options_sitewide();
		
		// NOTE: $_GET['preview'] and $_GET['post_type'] arguments are set by rvy_init() at response to ?p= request when the requested post is a revision.
		if ( ! is_admin() && ( ! empty( $_GET['preview'] ) || ! empty( $_GET['mark_current_revision'] ) ) && empty($_GET['preview_id']) ) { // preview_id indicates a regular preview via WP core, based on autosave revision
			require_once( dirname(__FILE__).'/front_rvy.php' );
			$this->front = new RevisionaryFront();
		}
			
		if ( ! is_content_administrator_rvy() ) {
			add_filter( 'user_has_cap', array( &$this, 'flt_user_has_cap' ), 98, 3 );
			add_filter( 'pp_has_cap_bypass', array( &$this, 'flt_has_cap_bypass' ), 10, 4 );
			
			add_filter( 'map_meta_cap', array( &$this, 'flt_limit_others_drafts' ), 10, 4 );
			//add_filter( 'posts_where', array( &$this, 'flt_posts_where' ), 1 );
		}
		
		if ( is_admin() ) {
			require_once( dirname(__FILE__).'/admin/admin_rvy.php');
			$this->admin = new RevisionaryAdmin();
		}	
		
		add_action( 'wpmu_new_blog', array( &$this, 'act_new_blog'), 10, 2 );
		
		add_filter( 'posts_results', array( &$this, 'inherit_status_workaround' ) );
		add_filter( 'the_posts', array( &$this, 'undo_inherit_status_workaround' ) );
	
		add_action( 'wp_loaded', array( &$this, 'set_revision_capdefs' ) );
		
		if ( rvy_get_option( 'pending_revisions' ) ) {
			// special filtering to support Contrib editing of published posts/pages to revision
			add_filter('pre_post_status', array(&$this, 'flt_pendingrev_post_status') );
			add_filter('wp_insert_post_data', array(&$this, 'flt_impose_pending_rev'), 2, 2 );
		}
		
		if ( rvy_get_option('scheduled_revisions') ) {
			add_filter('wp_insert_post_data', array(&$this, 'flt_create_scheduled_rev'), 3, 2 );  // other filters will have a chance to apply at actual publish time

			// users who have edit_published capability for post/page can create a scheduled revision by modifying post date to a future date (without setting "future" status explicitly)
			add_filter( 'wp_insert_post_data', array(&$this, 'flt_insert_post_data'), 99, 2 );
		}

		add_filter( 'pre_trash_post', array( &$this, 'flt_pre_trash_post' ), 10, 2 );
		
		// REST logging
		add_filter( 'rest_pre_dispatch', array( &$this, 'rest_pre_dispatch' ), 10, 3 );
		
		do_action( 'rvy_init' );
	}
	
	// log post type and ID from REST handler for reference by subsequent PP filters 
	function rest_pre_dispatch( $rest_response, $rest_server, $request ) {
		$this->doing_rest = true;
		
		require_once( dirname(__FILE__).'/rest_rvy.php' );
		$this->rest = new Revisionary_REST();
		
		return $this->rest->pre_dispatch( $rest_response, $rest_server, $request );
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
			if ( 'revision' != $post->ID ) {
				global $current_user;
			
				$status_obj = get_post_status_object( $post->post_status );
			
				if ( ( $current_user->ID != $post->post_author ) && $status_obj && ! $status_obj->public && ! $status_obj->private ) {
					$post_type_obj = get_post_type_object( $post->post_type );
					if ( current_user_can( $post_type_obj->cap->edit_published_posts ) ) {	// don't require any additional caps for sitewide Editors
						return $caps;
					}
				
					static $stati;
					static $private_stati;
				
					if ( ! isset($public_stati) ) {
						$stati = get_post_stati( array( 'internal' => false, 'protected' => true ) );
						$stati = array_diff( $stati, array( 'future' ) );
					}
					
					if ( in_array( $post->post_status, $stati ) ) {
						//if ( $post_type_obj = get_post_type_object( $post->post_type ) ) {
							$caps[]= "edit_others_drafts";
						//}
					}
				}
			}
		}
		
		return $caps;
	}
	
	function set_content_roles( $content_roles_obj ) {
		$this->content_roles = $content_roles_obj;

		if ( ! defined( 'RVY_CONTENT_ROLES' ) )
			define( 'RVY_CONTENT_ROLES', true );
	}
	
	// we generally want Revisors to edit other users' posts, but not other users' revisions
	function set_revision_capdefs() {
		global $wp_post_types;
		if ( 'edit_others_posts' == $wp_post_types['revision']->cap->edit_others_posts ) {
			$wp_post_types['revision']->cap->edit_others_posts = 'edit_others_revisions';
			//$wp_post_types['revision']->cap->delete_others_posts = 'delete_others_revisions';
		}
	}
	
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
	
	function flt_user_has_cap($wp_blogcaps, $reqd_caps, $args)	{
		if ( ! rvy_get_option('pending_revisions') )
			return $wp_blogcaps;
		
		$script_name = $_SERVER['SCRIPT_NAME'];
		
		if ( ( defined( 'PRESSPERMIT_VERSION' ) || defined( 'PP_VERSION' ) || defined( 'PPC_VERSION' ) ) && ( strpos( $script_name, 'p-admin/post.php' ) || rvy_wp_api_request() ) ) {
			$support_publish_cap = empty( $_REQUEST['publish'] ) && ! is_array($args[0]) && ( false !== strpos( $args[0], 'publish_' ) );  // TODO: support custom publish cap prefix without perf hit?
		}
		
		//if ( in_array( $args[0], array( 'edit_post', 'edit_page', 'delete_post', 'delete_page', 'edit_published_pages', 'edit_others_pages', 'edit_private_pages', 'edit_pages', 'publish_pages' ) ) )
		//	pp_errlog( "== RVY: flt_user_has_cap {$args[0]}" );
		
		if ( ! in_array( $args[0], array( 'edit_post', 'edit_page', 'delete_post', 'delete_page' ) ) && empty($support_publish_cap) ) {
			if ( ( ( ! strpos( $script_name, 'p-admin/post.php' ) || empty( $_POST ) ) && ! rvy_wp_api_request() && ( ! defined('DOING_AJAX') || ! DOING_AJAX || empty( $_REQUEST['action'] ) ) ) || ( 'editpost' != $_REQUEST['action'] ) ) {
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

		if ( $post = get_post( $post_id ) )
			$object_type = $post->post_type;
		else
			$object_type = rvy_detect_post_type();
			
		if (!empty($_REQUEST['action']) && ('inline-save' == $_REQUEST['action']) && ('revision' != $post->post_type)) {
			$this->skip_revision_allowance = true;
		}

		if ( rvy_get_option( 'revisor_lock_others_revisions' ) ) {
			if ( $post ) {
				// Revisors are enabled to edit other users' posts for revision, but cannot edit other users' revisions unless cap is explicitly set sitewide
				if ( ( 'revision' == $post->post_type ) && ! $this->skip_revision_allowance ) {
					if ( $post->post_author != $GLOBALS['current_user']->ID ) {
						if ( empty( $GLOBALS['current_user']->allcaps['edit_others_revisions'] ) ) {
							$this->skip_revision_allowance = 1;
						}
					}
				}

				if ( 'revision' == $post->post_type ) {  // Role Scoper / Press Permit may have already done this
					$object_type = get_post_field( 'post_type', $post->post_parent );
				}
			}
		} elseif ( 'revision' == $object_type ) {
			if ( $post )
				$object_type = get_post_field( 'post_type', $post->post_parent );
		}
		
		$object_type_obj = get_post_type_object( $object_type );
		
		if ( empty( $object_type_obj->cap ) )
			return $wp_blogcaps;
		
		$cap = $object_type_obj->cap;
		
		$edit_published_cap = ( isset($cap->edit_published_posts) ) ? $cap->edit_published_posts : "edit_published_{$object_type}s";
		$edit_private_cap = ( isset($cap->edit_private_posts) ) ? $cap->edit_private_posts : "edit_private_{$object_type}s";

		if ( ! $this->skip_revision_allowance ) {
			// Allow Contributors / Revisors to edit published post/page, with change stored as a revision pending review
			//$replace_caps = apply_filters( 'rvy_replace_post_edit_caps', array( 'edit_published_posts', 'edit_private_posts', $edit_published_cap, $edit_private_cap ), $object_type, $post_id );
			$replace_caps = array( 'edit_published_posts', $edit_published_cap, 'edit_private_posts', $edit_private_cap );
			
			if ( ! strpos( $script_name, 'p-admin/edit.php' ) ) {
				$replace_caps = array_merge( $replace_caps, array( $cap->publish_posts, 'publish_posts' ) );
			}
			
			//pp_errlog( $reqd_caps );
			//pp_errlog( $replace_caps );
			
			if ( array_intersect( $reqd_caps, $replace_caps) ) {	// don't need to fudge the capreq for post.php unless existing post has public/private status
				/*
				$post_status = get_post_field('post_status', $post_id );
				$post_status_obj = get_post_status_object( $post_status );
				
				if ( is_preview() || strpos($script_name, 'p-admin/edit.php') || strpos($script_name, 'p-admin/widgets.php') || ( $post_status_obj && ( $post_status_obj->public || $post_status_obj->private ) ) ) {
				*/				

				if ( is_preview() || rvy_wp_api_request() || strpos($script_name, 'p-admin/edit.php') || strpos($script_name, 'p-admin/widgets.php') || ( in_array( get_post_field('post_status', $post_id ), array('publish', 'private') ) ) ) {
					if ( $type_obj = get_post_type_object( $object_type ) ) {

						if ( ! empty( $wp_blogcaps[ $type_obj->cap->edit_posts ] ) ) {
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
	
	function flt_pre_trash_post( $check, $post ) {
		if ( ( 'revision' == $post->post_type ) & in_array( $post->post_status, array( 'pending', 'future' ) ) ) {
			wp_delete_post( $post->ID );
			return $post->ID;
		}
	}

	function flt_pendingrev_post_status($status) {
		if ( $this->doing_rest && $this->rest->is_posts_request ) {
			$post_id = $this->rest->post_id;
		} elseif ( ! empty( $_POST['post_ID'] ) ) {
			$post_id = $_POST['post_ID'];
		} else {
			$post_id = rvy_detect_post_id();
		} 
		
		if ( empty( $post_id ) ) {
			return $status;
		}
		
		// Make sure the stored post is published / scheduled		
		// With Events Manager plugin active, Role Scoper 1.3 to 1.3.12 caused this filter to fire prematurely as part of object_id detection, flagging for pending_rev needlessly on update of an unpublished post
		if ( $stored_post = get_post( $post_id ) )
			$status_obj = get_post_status_object( $stored_post->post_status );

		if ( empty($status_obj) || ( ! $status_obj->public && ! $status_obj->private && ( 'future' != $stored_post->post_status ) ) )
			return $status;
		
		if ( ! empty( $_POST['rvy_save_as_pending_rev'] ) && ! empty($post_id) ) {
			$this->impose_pending_rev[$post_id] = true;
		}
		
		if ( is_content_administrator_rvy() )
			return $status;
		
		if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) )
			return $status;
			
		if ( $this->doing_rest && $this->rest->is_posts_request ) {
			$post_type = $this->rest->post_type;
		} elseif ( ! empty( $_POST['post_type'] ) ) {
			$post_type = $_POST['post_type'];
		} else {
			$post_type = rvy_detect_post_type();
		} 
			
		if ( ! empty( $post_type ) ) {
			if ( $type_obj = get_post_type_object( $post_type ) ) {
				if ( ! agp_user_can( $type_obj->cap->edit_post, $post_id, '', array( 'skip_revision_allowance' => true ) ) )
					$this->impose_pending_rev[$post_id] = true;
			}
		}
		
		return $status;
	}
	
	function flt_impose_pending_rev( $data, $post_arr ) {
		if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) )
			return $data;

		if ( empty( $post_arr['ID'] ) || empty($this->impose_pending_rev[ $post_arr['ID'] ]) ) {
			return $data;
		}
		
		if ( $this->isBlockEditorActive() && ! $this->doing_rest ) {
			return $data;
		}

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
		
		$published_post = get_post( $post_arr['ID'] );

		$object_type = isset($post_arr['post_type']) ? $post_arr['post_type'] : '';
	
		$post_arr = array_intersect_key( $post_arr, array_fill_keys( array( 'post_content', 'post_title', 'ID', 'post_date_gmt', 'post_date' ), true ) );

		$post_arr['post_type'] = 'revision';
		$post_arr['post_status'] = 'pending';
		$post_arr['post_parent'] = $post_arr['ID'];  // side effect: don't need to filter page parent selection because parent is set to published revision
		$post_arr['parent_id'] = $post_arr['ID'];
		$post_arr['post_ID'] = 0;
		$post_arr['ID'] = 0;
		$post_arr['guid'] = '';

		/*	// @todo: support term revisioning
		if ( defined('RVY_CONTENT_ROLES') ) {
			if ( isset($post_arr['post_category']) ) {	// todo: also filter other post taxonomies
				$post_arr['post_category'] = $this->content_roles->filter_object_terms( $post_arr['post_category'], 'category' );
			}
		}
		*/

		global $current_user, $wpdb;
		$post_arr['post_author'] = $current_user->ID;		// store current user as revision author (but will retain current post_author on restoration)
			
		$post_arr['post_modified'] = current_time( 'mysql' );
		$post_arr['post_modified_gmt'] = current_time( 'mysql', 1 );

		$date_clause = ", post_modified = '" . current_time( 'mysql' ) . "', post_modified_gmt = '" . current_time( 'mysql', 1 ) . "'";  // make sure actual modification time is stored to revision
		
		$future_date = ( ! empty($post_arr['post_date']) && ( strtotime($post_arr['post_date_gmt'] ) > agp_time_gmt() ) );
		
		if ( $future_date ) {
			// round down to zero seconds
			$post_arr['post_date_gmt'] = date( 'Y-m-d H:i:00', strtotime( $post_arr['post_date_gmt'] ) );
			$post_arr['post_date'] = date( 'Y-m-d H:i:00', strtotime( $post_arr['post_date'] ) );
		}
		
		if ( $revision_id = wp_insert_post($post_arr) ) {
			$wpdb->query("UPDATE $wpdb->posts SET post_status = 'pending', post_parent = '$published_post->ID' $date_clause WHERE ID = '$revision_id'");
			
			$post_id = $published_post->ID;						  // passing args ensures back compat by using variables directly rather than retrieving revision, post data
			$msg = $this->get_revision_msg( $revision_id, compact( 'post_arr', 'post_id', 'object_type', 'future_date' ) );
		} else {
			$msg = __('Sorry, an error occurred while attempting to save your modification for editorial review!', 'revisionary') . ' ';
		}
		
		unset($this->impose_pending_rev[ $post_arr['ID'] ]);
		
		if ( $this->doing_rest ) {
			// prevent alteration of published post, while allowing save operation to complete
			$data = array_intersect_key( (array) $published_post, array_fill_keys( array( 'ID', 'post_type', 'post_name', 'post_status', 'post_parent', 'post_author' ), true ) );
		} else {
			$args = compact( 'revision_id', 'published_post', 'object_type' );
			if ( ! empty( $_REQUEST['prev_cc_user'] ) ) {
				$args['selected_recipients'] = $_REQUEST['prev_cc_user'];
			}
			$this->do_notifications( 'pending', 'pending', $post_arr, $args );
			rvy_halt( $msg, __('Pending Revision Created', 'revisionary') );
		}

		return $data;
	}
	
	// If Scheduled Revisions are enabled, don't allow WP to force current post status to future based on publish date
	function flt_insert_post_data( $data, $postarr ) {
		if ( ( 'future' == $data['post_status'] ) && ( rvy_is_status_published( $postarr['post_status'] ) ) ) {
			// don't interfere with scheduling of unpublished drafts
			if ( $stored_status = get_post_field ( 'post_status', rvy_detect_post_id() ) ) {
			
				//if ( in_array( $_POST['original_post_status'], array( 'publish', 'private' ) )  || in_array( $_POST['hidden_post_status'], array( 'publish', 'private' ) ) )
				if ( rvy_is_status_published( $stored_status ) ) {
					$data['post_status'] = $postarr['post_status'];
				}
			}
		}
		
		return $data;
	}
	
	function flt_create_scheduled_rev( $data, $post_arr ) {
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

		if ( $this->isBlockEditorActive() && ! $this->doing_rest ) {
			return $data;
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
		
		if ( ! empty($post_arr['post_date_gmt']) && ( strtotime($post_arr['post_date_gmt'] ) > agp_time_gmt() ) ) {
			if ( $type_obj = get_post_type_object( $published_post->post_type ) ) {
				global $current_user;
				if ( ! agp_user_can( $type_obj->cap->edit_post, $published_post->ID, $current_user->ID, array( 'skip_revision_allowance' => true ) ) )
					return $data;
			}
			
			$object_type = isset($post_arr['post_type']) ? $post_arr['post_type'] : '';
		
			$post_arr = array_intersect_key( $post_arr, array_fill_keys( array( 'post_content', 'post_title', 'ID', 'post_date_gmt', 'post_date' ), true ) );

			$post_arr['post_type'] = 'revision';
			$post_arr['post_status'] = 'future';
			$post_arr['post_parent'] = $post_arr['ID'];  // side effect: don't need to filter page parent selection because parent is set to published revision
			$post_arr['parent_id'] = $post_arr['ID'];
			$post_arr['post_ID'] = 0;
			$post_arr['ID'] = 0;
			$post_arr['guid'] = '';

			/* // @todo: support term revisioning
			if ( defined('RVY_CONTENT_ROLES') ) {
				if ( isset($post_arr['post_category']) ) {	// todo: also filter other post taxonomies
					$post_arr['post_category'] = $this->content_roles->filter_object_terms( $post_arr['post_category'], 'category' );
				}
			}
			*/

			global $current_user, $wpdb;
			$post_arr['post_author'] = $current_user->ID;		// store current user as revision author (but will retain current post_author on restoration)
				
			// round down to zero seconds
			$post_arr['post_date_gmt'] = date( 'Y-m-d H:i:00', strtotime( $post_arr['post_date_gmt'] ) );
			$post_arr['post_date'] = date( 'Y-m-d H:i:00', strtotime( $post_arr['post_date'] ) );

			$post_arr['post_modified'] = current_time( 'mysql' );
			$post_arr['post_modified_gmt'] = current_time( 'mysql', 1 );

			$date_clause = ", post_date = '{$post_arr['post_date']}', post_date_gmt = '{$post_arr['post_date_gmt']}', post_modified = '" . current_time( 'mysql' ) . "', post_modified_gmt = '" . current_time( 'mysql', 1 ) . "'";  // make sure actual modification time is stored to revision

			if ( $revision_id = wp_insert_post($post_arr) ) {
				$wpdb->query("UPDATE $wpdb->posts SET post_status = 'future', post_parent = '$published_post->ID' $date_clause WHERE ID = '$revision_id'");
			} else {
				$msg = __('Sorry, an error occurred while attempting to save your modification for editorial review!', 'revisionary') . ' ';
			}
			
			require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
			rvy_update_next_publish_date();

			if ( $this->doing_rest ) {
				// prevent alteration of published post, while allowing save operation to complete
				$data = array_intersect_key( (array) $published_post, array_fill_keys( array( 'ID', 'post_name', 'post_status', 'post_parent', 'post_author' ), true ) );
				add_post_meta( $published_post->ID, "_new_scheduled_revision_{$current_user->ID}", $revision_id );
			} else {
				$msg = $this->get_revision_msg( $revision_id, array( 'post_id' => $published_post->ID ) );
				rvy_halt( $msg, __('Scheduled Revision Created', 'revisionary') );
			}
		}

		return $data;
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


// Disable Gutenberg on Revisions Edit screen, for now
if ( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions') ) {
	$post_types = get_post_types( array( 'public' => true ), 'object' );
	foreach( $post_types as $post_type => $type_obj ) {
		if ( ! defined( 'RVY_FORCE_BLOCKEDIT_' . strtoupper($post_type) ) ) {
			add_filter( "use_block_editor_for_{$post_type}", '__return_false', 10 );
		}
	}
}
