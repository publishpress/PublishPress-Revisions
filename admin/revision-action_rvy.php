<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

add_action( '_wp_put_post_revision', 'rvy_review_revision' );
	
/**
 * @package     PublishPress\Revisions\RevisionaryAction
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */
function rvy_revision_diff() {
}

// schedules publication of a revision ( or publishes if requested publish date has already passed )
function rvy_revision_approve($revision_id = 0) {
	require_once( ABSPATH . 'wp-admin/admin.php');
	
	if (!$revision_id) {
		$batch_process = false;

		if (empty($_GET['revision'])) {
			return;
		}

	$revision_id = $_GET['revision'];
	} else {
		$batch_process = true;
	}

	$redirect = '';
	
	$blogname = wp_specialchars_decode( get_option('blogname'), ENT_QUOTES );
	
	do {
		if ( !$revision = wp_get_post_revision( $revision_id ) ) {
			if (!$revision = get_post($revision_id)) {
				break;
			}
		}

		$published_id = ('revision' == $revision->post_type) ? $revision->post_parent : rvy_post_id($revision_id);

		if (!$published_id) {
			break;
		}

		if (!$post = get_post($published_id)) {
			break;
		}

		if ( $type_obj = get_post_type_object( $post->post_type ) ) {
			if ( ! agp_user_can( $type_obj->cap->edit_post, $post->ID, '', array( 'skip_revision_allowance' => true ) ) )
				break;
		}

		if (!$batch_process) {
		check_admin_referer( "approve-post_$post->ID|$revision->ID" );
		}
		
		clean_post_cache($post->ID);
		$published_url = get_permalink($post->ID);

		$db_action = false;
		
		// If requested publish date is in the past or now, publish the revision
		if ( strtotime( $revision->post_date_gmt ) <= agp_time_gmt() ) {
			$status_obj = get_post_status_object( $revision->post_status );

			global $wpdb;

			if ( empty($status_obj->public) && empty($status_obj->private) ) { // && ( 'future-revision' != $revision->post_status ) ) {
				$db_action = true;
				
				if ('revision' == $revision->post_type) {
					// prep the revision to look like a normal one so WP doesn't reject it
					$data = array( 'post_status' => 'inherit', 'post_date' => $revision->post_modified, 'post_date_gmt' => $revision->post_modified );
					
					if ( class_exists('WPCom_Markdown') && ! defined( 'RVY_DISABLE_MARKDOWN_WORKAROUND' ) )
						$data['post_content_filtered'] = $revision->post_content;
					
					$wpdb->update( $wpdb->posts, $data, array( 'ID' => $revision->ID ) );
					
					wp_restore_post_revision( $revision->ID, array( 'post_content', 'post_title', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) );

					rvy_format_content( $revision->post_content, $revision->post_content_filtered, $post->ID );
					
					clean_post_cache( $revision->ID );
				} else {
					$_result = rvy_apply_revision($revision->ID, $revision->post_status);
					if (!$_result || is_wp_error($_result)) {
						// Go ahead with the normal redirect because the revision may have been approved / published already.
						// If revision does not exist, preview's Not Found will prevent false impression of success.
						$approval_error = true;
						break;
					}

					clean_post_cache($post->ID);
					$published_url = get_permalink($post->ID);
				}
			}

			$revision_status = '';
			$last_arg = array( 'revision_action' => 1, 'published_post' => $revision->ID );
			$scheduled = '';

		// If requested publish date is in the future, schedule the revision
		} else {
			if ( 'future-revision' != $revision->post_status ) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'future-revision' ), array( 'ID' => $revision->ID ) );
				
				$update_next_publish_date = true;
				
				$db_action = true;
				
				clean_post_cache( $revision->ID );
			} else {
				// this scheduled revision is already approved, so don't included in reported bulk approval count
				$approval_error = true;
			}

			$revision_status = 'future-revision';
			$last_arg = array( "revision_action" => 1, 'scheduled' => $revision->ID );
			$scheduled = $revision->post_date;
		}
		
		// Don't send approval notification on restoration of a past revision
		if ('revision' != $revision->post_type) {
			$type_obj = get_post_type_object( $post->post_type );
			$type_caption = $type_obj->labels->singular_name;

			$title = sprintf(__('[%s] Revision Approval Notice', 'revisionary' ), $blogname );
			$message = sprintf( __('A revision to your %1$s "%2$s" has been approved.', 'revisionary' ), $type_caption, $post->post_title ) . "\r\n\r\n";

			if ( $revisor = new WP_User( $revision->post_author ) )
				$message .= sprintf( __('The submitter was %1$s.', 'revisionary'), $revisor->display_name ) . "\r\n\r\n";

			if ( $scheduled ) {
				$datef = __awp( 'M j, Y @ g:i a' );
				$message .= sprintf( __('It will be published on %s', 'revisionary' ), agp_date_i18n( $datef, strtotime($revision->post_date) ) ) . "\r\n\r\n";
				
				$preview_link = rvy_preview_url($revision);

				$message .= __( 'Preview it here: ', 'revisionary' ) . $preview_link . "\r\n\r\n";

				$message .= __( 'Editor: ', 'revisionary' ) . admin_url("post.php?post={$revision->ID}&action=edit") . "\r\n";
			} else {
				$message .= __( 'View it online: ', 'revisionary' ) . $published_url . "\r\n";	
			}
			
			if ( $db_action && rvy_get_option( 'rev_approval_notify_author' ) ) {
				if (function_exists('get_multiple_authors')) {
					$authors = get_multiple_authors($post);
				} else {
					$author = new WP_User($post->post_author);
					$authors = [$author];
				}

				foreach($authors as $author) {
					if ($author) {
						rvy_mail(
							$author->user_email, 
							$title, 
							$message, 
							[
								'revision_id' => $revision->ID, 
								'post_id' => $post->ID, 
								'notification_type' => 'revision-approval', 
								'notification_class' => 'rev_approval_notify_author'
							]
						);
					}
				}
			}
			
			if ( $db_action && defined( 'RVY_NOTIFY_SUPER_ADMIN' ) && is_multisite() ) {
				$super_admin_logins = get_super_admins();
				foreach( $super_admin_logins as $user_login ) {
					if ( $super = new WP_User($user_login) )
						rvy_mail( 
							$super->user_email, 
							$title, 
							$message, 
							[
								'revision_id' => $revision->ID, 
								'post_id' => $post->ID, 
								'notification_type' => 'revision-approval', 
								'notification_class' => 'rev_approval_notify_super_admin'
							]
						);
				}
			}
			
			if ( $db_action && rvy_get_option( 'rev_approval_notify_revisor' ) ) {
				$title = sprintf(__('[%s] Revision Approval Notice', 'revisionary' ), $blogname );
				$message = sprintf( __('The revision you submitted for the %1$s "%2$s" has been approved.', 'revisionary' ), $type_caption, $revision->post_title ) . "\r\n\r\n";

				if ( $scheduled ) {
					$datef = __awp( 'M j, Y @ g:i a' );
					$message .= sprintf( __('It will be published on %s', 'revisionary' ), agp_date_i18n( $datef, strtotime($revision->post_date) ) ) . "\r\n\r\n";
					
					$preview_link = rvy_preview_url($revision);

					$message .= __( 'Preview it here: ', 'revisionary' ) . $preview_link . "\r\n\r\n";

					$message .= __( 'Editor: ', 'revisionary' ) . admin_url("post.php?post={$revision->ID}&action=edit") . "\r\n";
				} else {
					$message .= __( 'View it online: ', 'revisionary' ) . $published_url . "\r\n";	
				}

				if ( $author = new WP_User( $revision->post_author, '' ) ) {
					rvy_mail( 
						$author->user_email, 
						$title, 
						$message, 
						[
							'revision_id' => $revision->ID, 
							'post_id' => $post->ID, 
							'notification_type' => 'revision-approval', 
							'notification_class' => 'rev_approval_notify_revisor'
						]
					);
				}
			}
		}

		if ( empty( $_REQUEST['rvy_redirect'] ) && ! $scheduled ) {
			$redirect = $published_url;
		} elseif ( !empty($_REQUEST['rvy_redirect']) && 'edit' == $_REQUEST['rvy_redirect'] ) {
			$redirect = add_query_arg( $last_arg, "post.php?post=$revision_id&action=edit" );
		} else {
			$redirect = rvy_preview_url($revision, ['post_type' => $post->post_type]);
		}

	} while (0);
	
	if (!empty($update_next_publish_date)) {
		rvy_update_next_publish_date();
	}
	
	if (!$batch_process) {	
	if ( ! $redirect ) {
		if ( ! empty($post) && is_object($post) && ( 'post' != $post->post_type ) ) {
			$redirect = "edit.php?post_type={$post->post_type}";
		} else
			$redirect = 'edit.php';
	}
	}

	if (empty($approval_error)) {
		do_action( 'revision_approved', $revision->post_parent, $revision->ID );
	}

	if (!$batch_process) {
	wp_redirect( $redirect );
	exit;
}

	if (empty($approval_error)) {
		return true;
	}
}

function rvy_revision_restore() {
	require_once( ABSPATH . 'wp-admin/admin.php');
	$revision_id = $_GET['revision'];
	$redirect = '';
	
	do {
		if ( !$revision = wp_get_post_revision( $revision_id ) )
			break;

		if ( !$post = get_post( $revision->post_parent ) )
			break;

		if ( $type_obj = get_post_type_object( $post->post_type ) ) {
			if ( ! agp_user_can( $type_obj->cap->edit_post, $revision->post_parent, '', array( 'skip_revision_allowance' => true ) ) )
				break;
		}

		check_admin_referer( "restore-post_{$post->ID}|$revision->ID" );
		//wp_restore_post_revision( $revision_id );

		$published_url = get_permalink($post->ID);

		global $wpdb;
		
		$data = array( 'post_status' => 'inherit', 'post_date' => $revision->post_modified, 'post_date_gmt' => $revision->post_modified );
		
		if ( class_exists('WPCom_Markdown') && ! defined( 'RVY_DISABLE_MARKDOWN_WORKAROUND' ) )
			$data['post_content_filtered'] = $revision->post_content;
		
		$wpdb->update($wpdb->posts, $data, array('ID' => $revision->ID));
		
		wp_restore_post_revision( $revision->ID, array( 'post_content', 'post_title', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) );

		// restore previous meta fields

		if (!defined('REVISIONARY_PRO_VERSION') || apply_filters('revisionary_copy_core_postmeta', true, $revision, $post, false)) {
			revisionary_copy_meta_field('_thumbnail_id', $revision->ID, $post->ID, false);
			revisionary_copy_meta_field('_wp_page_template', $revision->ID, $post->ID, false);
		}

		revisionary_copy_terms($revision->ID, $post->ID, false);

		/*
		if ( $postmeta = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = '$revision_id'", ARRAY_A ) ) {
			foreach( $postmeta as $row ) {		
				$row['post_id'] = $revision->post_parent;					
				
				if ( is_array($row['meta_value']) && ( count($row['meta_value'] <= 1 ) ) )
					$row['meta_value'] = maybe_unserialize($row['meta_value']);	
				
				if ( $meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = %s AND post_id = %d", $row['meta_key'], $revision->post_parent ) ) ) {						
					$wpdb->update( $wpdb->postmeta, $row, array( 'meta_id' => $meta_id ) );					
				} else {					
					$wpdb->insert( $wpdb->postmeta, $row );					
				}
			}
		}
		*/

		rvy_format_content( $revision->post_content, $revision->post_content_filtered, $post->ID );

		if ( 'inherit' == $revision->post_status ) {
			$last_arg = array( 'revision_action' => 1, 'restored_post' => $post->ID );
		} else {
			$last_arg = array( 'revision_action' => 1, 'published_post' => $post->ID );
		}

		if ( empty( $_REQUEST['rvy_redirect'] ) && ! $scheduled ) {
			$redirect = $published_url;

		} elseif ( 'edit' == $_REQUEST['rvy_redirect'] ) {
			$redirect = add_query_arg( $last_arg, "post.php?post={$post->ID}&action=edit" );
		} else {
			$redirect = add_query_arg( $last_arg, $_REQUEST['rvy_redirect'] );
		}

	} while (0);

	if ( ! $redirect ) {
		if ( ! empty($post) && is_object($post) && ( 'post' != $post->post_type ) ) {
			$redirect = "edit.php?post_type={$post->post_type}";
		} else
			$redirect = 'edit.php';
	}

	wp_redirect( $redirect );
	exit;
}

function rvy_apply_revision( $revision_id, $actual_revision_status = '' ) {
	global $wpdb;
	
	if ( ! $revision = get_post( $revision_id ) ) {
		return $revision;
	}

	if (! $published_id = rvy_post_id($revision_id)) {
		return false;
	}

	$update = (array) $revision;

	$update = wp_slash( $update ); //since data is from db

	//$published = get_post($published_id);
	$published = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $published_id)
	);

	if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
		if (!$published_authors = get_multiple_authors($published_id)) {
			if ($author = MultipleAuthors\Classes\Objects\Author::get_by_user_id((int) $published->post_author)) {
				$published_authors = [$author];
			}
		}
	}

	// published post columns which should not be overwritten by revision values
	//$update = array_diff_key($update, array_fill_keys(array('post_status', 'comment_count', 'post_name', 'guid', 'post_date', 'post_date_gmt' ), true));
	$update = array_merge(
		$update, 
		array(
			'ID' => $published->ID,
			'post_author' => $published->post_author,
			'post_status' => $published->post_status,
			'comment_count' => $published->comment_count,
			'post_name' => $published->post_name,
			'guid' => $published->guid,
		)
	);

	if (
		(('pending-revision' == $revision->post_status) && !rvy_get_option('pending_revision_update_post_date'))
		|| (('future-revision' == $revision->post_status) && !rvy_get_option('scheduled_revision_update_post_date'))
	) {
		$update = array_merge(
			$update, 
			array(
				'post_date' => $published->post_date,
				'post_date_gmt' => $published->post_date_gmt,
			)
		);
	}

	$post_id = wp_update_post( $update );
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Work around unexplained reversion of editor-modified post slug back to default format on some sites  @todo: identify plugin interaction
	$wpdb->update($wpdb->posts, array('post_name' => $published->post_name, 'guid' => $published->guid), array('ID' => $post_id));

	$_post = get_post($post_id);

	// prevent published post status being set to future when a scheduled revision is manually published before the stored post_date
	if ($_post && ($_post->post_status != $published->post_status)) {
		$wpdb->update($wpdb->posts, array('post_status' => $published->post_status), array('ID' => $post_id));
	}

	if (
		(('pending-revision' == $revision->post_status) && rvy_get_option('pending_revision_update_post_date'))
		|| (('future-revision' == $revision->post_status) && rvy_get_option('scheduled_revision_update_post_date'))
	) {
		if ($_post) {
			if ($_post->post_date_gmt != $_post->post_modified_gmt) {
				$wpdb->update(
					$wpdb->posts, 
					['post_date' => $_post->post_modified, 'post_date_gmt' => $_post->post_modified_gmt], 
					['ID' => $post_id]
				);
			}
		}
	}

	$post_modified_gmt = get_post_field('post_modified_gmt', $post_id);

	// also copy all stored postmeta from revision
	global $revisionary;

	$is_imported = get_post_meta($revision_id, '_rvy_imported_revision', true);

	// work around bug in < 2.0.7 that saved all scheduled revisions without terms
	if (!$is_imported && ('future-revision' == $revision->post_status)) {
		if ($install_time = get_option('revisionary_2_install_time')) {
			if (strtotime($revision->post_modified_gmt) < $install_time) {
				$is_imported = true;
			}
		}
	}

	if (!defined('REVISIONARY_PRO_VERSION') || apply_filters('revisionary_copy_core_postmeta', true, $revision, $published, !$is_imported)) {
		revisionary_copy_meta_field('_thumbnail_id', $revision->ID, $published->ID, !$is_imported);
		revisionary_copy_meta_field('_wp_page_template', $revision->ID, $published->ID, !$is_imported);
	}

	// Allow Multiple Authors revisions to be applied to published post. Revision post_author is forced to actual submitting user.
	//$skip_taxonomies = (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) ? ['author'] : [];
	revisionary_copy_terms($revision_id, $post_id, !$is_imported);

	if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && $published_authors) {
		// Make sure Multiple Authors values were not wiped due to incomplete revision data
		if (!get_multiple_authors($post_id)) {
			_rvy_set_ma_post_authors($post_id, $published_authors);
		}
	}

	// @todo save change as past revision?
	//$wpdb->delete($wpdb->posts, array('ID' => $revision_id));
	$wpdb->update($wpdb->posts, array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $post_id, 'comment_count' => 0), array('ID' => $revision_id));

	// @todo save change as past revision?
	$wpdb->delete($wpdb->postmeta, array('post_id' => $revision_id));

	update_post_meta($revision_id, '_rvy_published_gmt', $post_modified_gmt);

	rvy_delete_past_revisions($revision_id);

	return $revision;
}

// Restore a past revision (post_status = inherit)
function rvy_do_revision_restore( $revision_id, $actual_revision_status = '' ) {
	global $wpdb;

	if ( $revision = wp_get_post_revision( $revision_id ) ) {
		if ('future-revision' == $revision->post_status) {
			rvy_publish_scheduled_revisions(array('force_revision_id' => $revision->ID));
			return $revision;
		}
		
		$revision_date = $revision->post_date;
		$revision_date_gmt = $revision->post_date_gmt;

		//$fields = array( 'post_content', 'post_title', 'post_modified', 'post_modified_gmt' );
		
		wp_restore_post_revision( $revision_id );

		// @todo: why do revision post_date, post_date_gmt get changed?
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_date = %s, post_date_gmt = %s WHERE ID = %d", $revision_date, $revision_date_gmt, $revision->ID ) );

		// @todo: why does a redundant revision with post_author = 0 get created at revision publication?
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->posts WHERE post_type = 'revision' AND post_author = 0 AND post_parent = %d", $revision->post_parent ) );
	}

	if ( $revision && ! empty($revision->post_parent) ) {
		rvy_format_content( $revision->post_content, $revision->post_content_filtered, $revision->post_parent );
	}
	
	clean_post_cache( $revision_id );

	return $revision;
}

function rvy_revision_delete() {
	require_once( ABSPATH . 'wp-admin/admin.php');
	$revision_id = $_GET['revision'];
	$redirect = '';
	
	do {
		// this function is only used for past revisions (status=inherit)
		if ( ! $revision = wp_get_post_revision( $revision_id ) )
			break;

		if ( ! $post = get_post( $revision->post_parent ) )
			break;

		if ( $type_obj = get_post_type_object( $post->post_type ) ) {
			if ( ! current_user_can( $type_obj->cap->delete_post, $revision->post_parent ) ) {
				break;
			}
		}
		
		check_admin_referer('delete-revision_' .  $revision_id);
		
		// before deleting the revision, note its status for redirect
		wp_delete_post_revision( $revision_id );
		$redirect = "admin.php?page=rvy-revisions&revision={$revision->post_parent}&action=view&revision_status={$revision->post_status}&deleted=1";

		rvy_delete_past_revisions($revision_id);
	} while (0);
	
	if ( ! empty( $_GET['return'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$redirect = str_replace( 'trashed=', 'deleted=', $_SERVER['HTTP_REFERER'] );

	} elseif ( ! $redirect ) {
		if ( ! empty($post) && is_object($post) && ( 'post' != $post->post_type ) ) {
			$redirect = "edit.php?post_type={$post->post_type}";
		} else
			$redirect = 'edit.php';
	}

	wp_redirect( $redirect );
	exit;
}

function rvy_revision_bulk_delete() {
	require_once( ABSPATH . 'wp-admin/admin.php');

	check_admin_referer( 'rvy-revisions' );
	
	$redirect = '';
	$delete_count = 0;
	$post_id = 0;
	$revision_status = '';
	
	if ( empty($_POST['delete_revisions']) || empty($_POST['delete_revisions']) ) {

		if ( ! empty( $_POST['left'] ) )
			$post_id = 	$_POST['left'];

		elseif ( ! empty( $_POST['right'] ) )
			$post_id = 	$_POST['right'];	

	} else {
		foreach ( $_POST['delete_revisions'] as $revision_id ) {
			// this function is only used for past revisions (status=inherit)
			if ( ! $revision = wp_get_post_revision( $revision_id ) )
				continue;

			if ( ! $post_id ) {
				if ( $post = get_post( $revision->post_parent ) )
					$post_id = $post->ID;
				else
					continue;
			}

			if ( $post = get_post( $revision->post_parent ) ) {
				if ( $type_obj = get_post_type_object( $post->post_type ) ) {
					if ( ! current_user_can( $type_obj->cap->delete_post, $revision->post_parent ) ) {
						continue;
					}
				}
			}
	
			// before deleting the revision, note its status for redirect
			$revision_status = $revision->post_status;
			wp_delete_post_revision( $revision_id );
			$delete_count++;

			rvy_delete_past_revisions($revision_id);
		}
	}

	$redirect = "admin.php?page=rvy-revisions&revision=$post_id&action=view&revision_status=$revision_status&bulk_deleted=$delete_count";
	
	wp_redirect( $redirect );
	exit;
}

function rvy_revision_unschedule() {
	require_once( ABSPATH . 'wp-admin/admin.php');
	$revision_id = $_GET['revision'];
	$redirect = '';
	
	do {
		if (!$revision = get_post($revision_id)) {
			break;
		}

		if (!rvy_is_revision_status($revision->post_status)) {
			break;
		}

		$published_id = rvy_post_id($revision->ID);

		if (!$post = get_post($published_id)) {
			break;
		}

		if ( $type_obj = get_post_type_object( $revision->post_type ) ) {
			if ( ! agp_user_can( $type_obj->cap->edit_post, $published_id, '', array( 'skip_revision_allowance' => true ) ) )
				break;
		}
		
		check_admin_referer('unschedule-revision_' .  $revision_id);

		global $wpdb;
		$wpdb->query( "UPDATE $wpdb->posts SET post_status = 'pending-revision' WHERE ID = '$revision_id'" );
		
		rvy_update_next_publish_date();

		$redirect = rvy_preview_url($revision);
	} while (0);
	
	if ( ! $redirect ) {
		if ( ! empty($post) && is_object($post) && ( 'post' != $post->post_type ) ) {
			$redirect = "edit.php?post_type={$post->post_type}";
		} else
			$redirect = 'edit.php';
	}

	wp_redirect( $redirect );
	exit;
}

function rvy_revision_publish($revision_id = false) {
	if ($revision_id) {
		$batch_process = true;
	} else {
	$revision_id = $_GET['revision'];
	$redirect = site_url();
		$batch_process = false;
	}
	
	do {
		if ( !$revision = get_post($revision_id ) ) {
			break;
		}

		if ( 'future-revision' != $revision->post_status ) {
			break;
		}

		if (!$published_id = rvy_post_id($revision_id)) {
			break;
		}

		if (!$post = get_post($published_id)) {
			break;
		}

		if ( $type_obj = get_post_type_object( $post->post_type ) ) {
			if ( ! agp_user_can( $type_obj->cap->edit_post, $post->ID, '', array( 'skip_revision_allowance' => true ) ) )
				break;
		}

		if (!$batch_process) {
		check_admin_referer( "publish-post_$post->ID|$revision->ID" );
		}

		$do_publish = true;
		do_action( 'revision_published', rvy_post_id($revision->ID), $revision->ID );
	} while (0);
	
	if (!empty($do_publish)) {
	rvy_publish_scheduled_revisions(array('force_revision_id' => $revision->ID));

	if ($post) {
		clean_post_cache($post->ID);
	}
	}

	if (!$batch_process) {
		if ($post) {
		$redirect = get_permalink($post->ID); // published URL
	}

	wp_redirect( $redirect );
	exit;
	}

	if (!empty($do_publish)) {
		return true;
	}
}

// rvy_init action passes Revisionary object
function _rvy_publish_scheduled_revisions() {
	rvy_publish_scheduled_revisions();
}

function rvy_publish_scheduled_revisions($args = array()) {
	global $wpdb;
	
	rvy_confirm_async_execution( 'publish_scheduled_revisions' );

	// Prevent this function from being triggered simultaneously by another site request
	update_option( 'rvy_next_rev_publish_gmt', '2035-01-01 00:00:00' );
	
	$time_gmt = current_time('mysql', 1);
	
	$restored_post_ids = array();
	$skip_revision_ids = array();
	$blogname = wp_specialchars_decode( get_option('blogname'), ENT_QUOTES );

	$revised_uris = array();

	if ( ! empty( $_GET['rs_debug'] ) )
		echo "current time: $time_gmt";

	if (!empty($args['force_revision_id']) && is_scalar($args['force_revision_id'])) {
		$results = $wpdb->get_results( 
			$wpdb->prepare( 
				"SELECT * FROM $wpdb->posts WHERE post_status = 'future-revision' AND ID = %d",
				(int) $args['force_revision_id']
			)
		);
	} else {
		$results = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_status = 'future-revision' AND post_date_gmt <= '$time_gmt' ORDER BY post_date_gmt DESC" );
	}

	if ( $results ) {
		foreach ( $results as $row ) {
			$published_id = rvy_post_id($row->ID);

			if ( ! isset($restored_post_ids[$published_id]) ) {
				$revised_uris []= get_permalink( $row->ID );

				$published_url = get_permalink($published_id);

				$_result = rvy_apply_revision($row->ID, 'future-revision');
				if (!$_result || is_wp_error($_result)) {
					// Don't trip an error because revision may have already been published by a different site request.
					// If not, the redirect to the published URL will indicate the current status
					continue;
				}

				if ( ! empty( $_GET['rs_debug'] ) )
					echo '<br />' . "publishing revision $row->ID";

				$restored_post_ids[$published_id] = true;
				
				$post = get_post( $published_id );

				$type_obj = get_post_type_object( $post->post_type );
				$type_caption = $type_obj->labels->singular_name;
				
				if ( rvy_get_option( 'publish_scheduled_notify_revisor' ) ) {
					$title = sprintf( __('[%s] Scheduled Revision Publication Notice', 'revisionary' ), $blogname );
					$message = sprintf( __('The scheduled revision you submitted for the %1$s "%2$s" has been published.', 'revisionary' ), $type_caption, $row->post_title ) . "\r\n\r\n";

					if ( ! empty($post->ID) )
						$message .= __( 'View it online: ', 'revisionary' ) . $published_url . "\r\n";

					if ( $author = new WP_User( $row->post_author ) )
						rvy_mail( 
							$author->user_email, 
							$title, 
							$message, 
							[
								'revision_id' => $row->ID, 
								'post_id' => $published_id, 
								'notification_type' => 'publish-scheduled', 
								'notification_class' => 'publish_scheduled_notify_revisor'
							]
						);
				}

				// Prior to 1.3, notification was sent to author even if also revision submitter
				if ( ( ( $post->post_author != $row->post_author ) || defined( 'RVY_LEGACY_SCHEDULED_REV_POST_AUTHOR_NOTIFY' ) ) && rvy_get_option( 'publish_scheduled_notify_author' ) ) {
					$title = sprintf( __('[%s] Scheduled Revision Publication Notice', 'revisionary' ), $blogname );
					$message = sprintf( __('A scheduled revision to your %1$s "%2$s" has been published.', 'revisionary' ), $type_caption, $post->post_title ) . "\r\n\r\n";

					if ( $revisor = new WP_User( $row->post_author ) )
						$message .= sprintf( __('It was submitted by %1$s.'), $revisor->display_name ) . "\r\n\r\n";

					if ( ! empty($post->ID) )
						$message .= __( 'View it online: ', 'revisionary' ) . $published_url . "\r\n";
				
					if (function_exists('get_multiple_authors')) {
						$authors = get_multiple_authors($post);
					} else {
						$author = new WP_User($post->post_author);
						$authors = [$author];
					}
	
					foreach($authors as $author) {
						if ($author && !empty($author->user_email)) {
							rvy_mail( 
								$author->user_email, 
								$title, 
								$message, 
								[
									'revision_id' => $row->ID, 
									'post_id' => $published_id, 
									'notification_type' => 'publish-scheduled', 
									'notification_class' => 'publish_scheduled_notify_author'
								]
							);
						}
					}
				}
				
				if ( rvy_get_option( 'publish_scheduled_notify_admin' ) ) {
					$title = sprintf(__('[%s] Scheduled Revision Publication'), $blogname );
					
					$message = sprintf( __('A scheduled revision to the %1$s "%2$s" has been published.'), $type_caption, $row->post_title ) . "\r\n\r\n";

					if ( $author = new WP_User( $row->post_author ) )
						$message .= sprintf( __('It was submitted by %1$s.'), $author->display_name ) . "\r\n\r\n";

					if ( ! empty($post->ID) )
						$message .= __( 'View it online: ', 'revisionary' ) . $published_url . "\r\n";

					$object_id = ( isset($post) && isset($post->ID) ) ? $post->ID : $row->ID;
					$object_type = ( isset($post) && isset($post->post_type) ) ? $post->post_type : 'post';
					

					// if it was not stored, or cleared, use default recipients
					$to_addresses = array();
					
					if ( defined('RVY_CONTENT_ROLES') && ! defined('SCOPER_DEFAULT_MONITOR_GROUPS') ) { // e-mail to Scheduled Revision Montiors metagroup if Role Scoper is activated
						global $revisionary;
						
						$monitor_groups_enabled = true;
						$revisionary->content_roles->ensure_init();

						if ( $default_ids = $revisionary->content_roles->get_metagroup_members( 'Scheduled Revision Monitors' ) ) {
							if ( $type_obj = get_post_type_object( $object_type ) ) {
								$revisionary->skip_revision_allowance = true;
								$cols = ( defined('COLS_ALL_RS') ) ? COLS_ALL_RS : 'all';
								$post_publishers = $revisionary->content_roles->users_who_can( $type_obj->cap->edit_post, $object_id, array( 'cols' => $cols ) );
								$revisionary->skip_revision_allowance = false;
								
								foreach ( $post_publishers as $user )
									if ( in_array( $user->ID, $default_ids ) )
										$to_addresses []= $user->user_email;
							}
						}
					} 
					
					if ( ! $to_addresses && ( empty($monitor_groups_enabled) || ! defined('RVY_FORCE_MONITOR_GROUPS') ) ) {  // if RS/PP are not active, monitor groups have been disabled or no monitor group members can publish this post...
						$use_wp_roles = ( defined( 'SCOPER_MONITOR_ROLES' ) ) ? SCOPER_MONITOR_ROLES : 'administrator,editor';
						
						$use_wp_roles = str_replace( ' ', '', $use_wp_roles );
						$use_wp_roles = explode( ',', $use_wp_roles );
						
						$recipient_ids = array();

						foreach ( $use_wp_roles as $role_name ) {
							$search = new WP_User_Query( "search=&fields=id&role=$role_name" );
							$recipient_ids = array_merge( $recipient_ids, $search->results );
						}
						
						foreach ( $recipient_ids as $userid ) {
							$user = new WP_User($userid);
							$to_addresses []= $user->user_email;
						}
					}
					
					if ( defined( 'RVY_NOTIFY_SUPER_ADMIN' ) && is_multisite() ) {
						$super_admin_logins = get_super_admins();
						foreach( $super_admin_logins as $user_login ) {
							if ( $super = new WP_User($user_login) )
								$to_addresses []= $super->user_email;
						}
					}
					
					$to_addresses = array_unique( $to_addresses );
					
					//dump($to_addresses);
					
					foreach ( $to_addresses as $address )
						rvy_mail( 
							$address, 
							$title, 
							$message, 
							[
								'revision_id' => $row->ID, 
								'post_id' => $published_id, 
								'notification_type' => 'publish-scheduled', 
								'notification_class' => 'publish_scheduled_notify_admin'
							]
						);
				}
				
				
			} else {
				$skip_revision_ids[$row->ID] = true;
			}
		}
		
		if ( $skip_revision_ids ) {
			// if more than one scheduled revision was not yet published, convert the older ones to regular revisions
			$id_clause = "AND ID IN ('" . implode( "','", array_keys($skip_revision_ids) ) . "')";
			$wpdb->query( "UPDATE $wpdb->posts SET post_type = 'revision', post_status = 'inherit' WHERE post_status = 'future-revision' $id_clause" );
		}
	}

	rvy_update_next_publish_date();
	
	// if this was initiated by an asynchronous remote call, we're done.
	if ( ! empty( $_GET['action']) && ( 'publish_scheduled_revisions' == $_GET['action'] ) ) {
		exit( 0 );
	} elseif ( in_array( $_SERVER['REQUEST_URI'], $revised_uris ) ) {
		wp_redirect( $_SERVER['REQUEST_URI'] );  // if one of the revised pages is being accessed now, redirect back so revision is published on first access
	}
}

function rvy_update_next_publish_date() {
	global $wpdb;
	
	if ( $next_publish_date_gmt = $wpdb->get_var( "SELECT post_date_gmt FROM $wpdb->posts WHERE post_status = 'future-revision' ORDER BY post_date_gmt ASC LIMIT 1" ) ) {
		// wp_schedule_single_event( strtotime( $next_publish_date_gmt ), 'publish_revision_rvy' );  // @todo: wp_cron testing
	} else {
		$next_publish_date_gmt = '2035-01-01 00:00:00';
	}

	update_option( 'rvy_next_rev_publish_gmt', $next_publish_date_gmt );
}

// @todo: is this still needed (now only create normal revisions when publishing scheduled post and more than one change was scheduled.)
function rvy_review_revision( $revision_id ) {
	if ( class_exists('WPCom_Markdown') && ! defined( 'RVY_DISABLE_MARKDOWN_WORKAROUND' ) ) {
		$revision = wp_get_post_revision( $revision_id );
		if ( ! $revision->post_content_filtered ) {
			if ( $post = get_post( $revision->post_parent ) ) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_content_filtered' => $post->post_content_filtered ), array( 'ID' => $revision_id ) );
			}
		}
	}
}

function rvy_delete_past_revisions($post_id) {
	global $wpdb;

	$revision_ids = $wpdb->get_col($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_type = 'revision' AND post_status = 'inherit' AND post_parent = %d", $post_id));

	// delete any associated "inherit" copies that were generated due to revision editing
	foreach($revision_ids as $_revision_id) {
		$wpdb->delete($wpdb->postmeta, array('post_id' => $_revision_id));
	}

	if ($revision_ids) {
		$wpdb->delete($wpdb->posts, array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $post_id));
	}
}

// apply any necessary third-party transformations to post content after publishing a revision
function rvy_format_content( $content, $content_filtered, $post_id, $args = array() ) {
	$defaults = array( 'update_db' => true );
	$args = array_merge( $defaults, $args );
	$args = apply_filters( 'rvy_format_content_args', $args, $post_id );
	
	foreach( array_keys( $defaults ) as $var ) {
		if ( ! isset( $$var) ) {
			$$var = ( isset( $args[$var] ) ) ? $args[$var] : $defaults[$var];
		}
	}

	if ( ! $content_filtered )
		$content_filtered = $content;
	
	$formatted_content = $content;
	
	if ( class_exists('WPCom_Markdown') && ! defined( 'RVY_DISABLE_MARKDOWN_WORKAROUND' ) ) {
		$wpcmd = WPCom_Markdown::get_instance();
		
		if ( method_exists( $wpcmd, 'transform' ) ) {
			$formatted_content = $wpcmd->transform( $content_filtered, array( 'post_id' => $post_id ) );
			
			if ( $update_db ) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_content' => $formatted_content, 'post_content_filtered' => $content_filtered ), array( 'ID' => $post_id ) );
			}
		}
	}

	$formatted_content = apply_filters( 'rvy_formatted_content', $formatted_content, $post_id, $content, $args );
	
	return $formatted_content;
}
