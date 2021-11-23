<?php
namespace PublishPress\Revisions;

class RevisionCreation {
	var $revisionary;
	var $options = [];

	function __construct($args = []) {
		// Support instantiation downstream from Revisionary constructor (before its return value sets global variable)
		if (!empty($args) && is_array($args) && !empty($args['revisionary'])) {
			$this->revisionary = $args['revisionary'];
		}
	}

	// @todo: status change handler for draft => future-revision
	function flt_future_revision_status_change($revision_status, $old_status, $revision_id) {
		if ('future-revision' == $revision_status) {
			require_once( dirname(__FILE__).'/admin/revision-action_rvy.php');
			rvy_update_next_publish_date();
		}

		if ('pending-revision' == $revision_status) {
			$revision = get_post($revision_id);
			
			do_action('revisionary_submit_revision', $revision);

			/**
			* Trigger after a revision creation.
			*
			* @param int $revision The revision object.
			*/
			do_action('revisionary_created_revision', $revision);
		}
	}

	function flt_pending_revision_data( $data, $postarr ) {

		if (rvy_is_revision_status($postarr['post_mime_type'])) {
			if ($data['post_name'] != $postarr['post_name']) {
				add_post_meta($revision_id, '_requested_slug', $data['post_name']);
				$data['post_name'] = $postarr['post_name'];
			}
		}

		return $data;	
	}

	static function fltInterruptPostMetaOperation($interrupt) {
		return true;
	}
	
	// Create a new revision, usually 'draft-revision' (Working Copy) or 'future-revision' (Scheduled Revision)

	// If an autosave was stored for the current user prior to this creation, it will be retrieve in place of the main revision. 
	function createRevision($post_id, $revision_status, $args = []) {
        global $wpdb, $current_user;

        $published_post = get_post($post_id);

		if (rvy_in_revision_workflow($published_post)) {
			return;
		}

		/*
        if (!empty($_POST)) {
            $_POST['skip_sitepress_actions'] = true;
		}
		*/

		$set_post_properties = [       
			'post_content',          
			'post_content_filtered', 
			'post_title',            
			'post_excerpt',                   
			'comment_status',        
			'ping_status',           
			'post_password',                            
			'menu_order',                 
		];

		$data = [];

		if ($autosave_post = Utils::get_post_autosave($post_id, $current_user->ID)) {
			if (strtotime($autosave_post->post_modified_gmt) > strtotime($published_post->post_modified_gmt)) {
				$use_autosave = true;
				$args['meta_post_id'] = $autosave_post->ID;
			}
		}

		foreach($set_post_properties as $prop) {
			$data[$prop] = (!empty($use_autosave) && !empty($autosave_post->$prop)) ? $autosave_post->$prop : $published_post->$prop;
		}

		$data['post_type'] = $published_post->post_type;
		$data['post_parent'] = $published_post->post_parent;

		if (!empty($args['time_gmt'])) {
			$timestamp = $args['time_gmt'];
			$data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp);
			$data['post_date'] = gmdate( 'Y-m-d H:i:s', $timestamp + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ));
		}

		$revision_id = $this->insert_revision($data, $published_post->ID, $revision_status, $args);

		if (!empty($use_autosave)) {
			$wpdb->delete($wpdb->posts, ['ID' => $autosave_post->ID]);
		}

		if (!$revision_id || !is_scalar($revision_id)) { // update_post_data() returns array or object on update abandon / failure
			return;
		}

		$post = get_post($revision_id);

		$url = apply_filters('revisionary_create_revision_redirect', rvy_admin_url("post.php?post=$revision_id&action=edit"), $revision_id);

		if (!empty($args['suppress_redirect'])) {
			return $revision_id;
		}

		wp_redirect($url);
		exit;
	}

    private function insert_revision($data, $base_post_id, $revision_status, $args = []) {
		global $wpdb, $current_user;

		$data['post_mime_type'] = $revision_status;

		switch($revision_status) {
			case 'draft-revision':
				$data['post_status'] = 'draft';

				$data['post_date'] = current_time( 'mysql' );
				$data['post_date_gmt'] = current_time( 'mysql', 1 );
				break;

			case 'future-revision':
				$data['post_status'] = 'future';
				break;

			default:
				$data['post_status'] = 'pending';
		}

		$data['comment_count'] = $base_post_id; 	// buffer this value in posts table for query efficiency (actual comment count stored for published post will not be overwritten)

		$data['post_author'] = $current_user->ID;		// store current user as revision author (but will retain current post_author on restoration)

		$data['post_modified'] = current_time( 'mysql' );
		$data['post_modified_gmt'] = current_time( 'mysql', 1 );

		if ( $future_date = ! empty($data['post_date']) && ( strtotime($data['post_date_gmt'] ) > agp_time_gmt() ) ) {  // in past versions, $future_date was also passed to get_revision_msg()
			// round down to zero seconds
			$data['post_date_gmt'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date_gmt'] ) );
			$data['post_date'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date'] ) );
		}

		// @todo: confirm this is still needed
		$data['guid'] = '';
		$data['post_name'] = '';

		$revision_id = wp_insert_post(\wp_slash($data), true);

		if (is_wp_error($revision_id)) {
			return new \WP_Error(__( 'Could not insert revision into the database', 'revisionary'));
		}

		$wpdb->update($wpdb->posts, ['comment_count' => $base_post_id], ['ID' => $revision_id]);

		rvy_update_post_meta($revision_id, '_rvy_base_post_id', $base_post_id);
		rvy_update_post_meta($base_post_id, '_rvy_has_revisions', true);

		// Use the newly generated $post_ID.
		$where = array( 'ID' => $revision_id );
		
		// @todo: confirm never needed
		/*
		$data['post_name'] = wp_unique_post_slug( sanitize_title( $data['post_title'], $post_ID ), $post_ID, $data['post_status'], $data['post_type'], $data['post_parent'] );
		$wpdb->update( $wpdb->posts, array( 'post_name' => $data['post_name'] ), $where );
		*/

		// make sure autosave still exists
		if (!empty($args['meta_post_id'])) {
			$post = get_post($args['meta_post_id']);

			if (empty($post) || empty($post->post_type)) {
				unset($args['meta_post_id']);
			}
		}

		if (!empty($args['meta_post_id'])) {
			revisionary_copy_terms($args['meta_post_id'], $revision_id);
			revisionary_copy_postmeta($args['meta_post_id'], $revision_id);

			// For taxonomies and meta keys not stored for the autosave, use published copies
			revisionary_copy_terms($base_post_id, $revision_id, ['empty_target_only' => true]);
			revisionary_copy_postmeta($base_post_id, $revision_id, ['empty_target_only' => true]);
		} else {
			// If term selections are not posted for revision, store current published terms
			revisionary_copy_terms($base_post_id, $revision_id);
			revisionary_copy_postmeta($base_post_id, $revision_id);
		}
	
		// Set GUID.  @todo: still needed?
		if ( '' == get_post_field( 'guid', $revision_id ) ) {
			// need to give revision a guid for 3rd party editor compat (post_ID is ID of revision)
			$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $revision_id ) ), $where );
		}
	
		$data['ID'] = $revision_id;
		do_action('revisionary_new_revision', $revision_id, $revision_status);

		return (int) $revision_id; // only return array in calling function should return
	}
}
