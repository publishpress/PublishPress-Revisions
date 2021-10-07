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

		$this->options = (array) apply_filters('revisionary_creation_options', []);
	}

    function flt_maybe_insert_revision($data, $postarr) {
		if (!empty($this->revisionary)) {
			$revisionary = $this->revisionary;
		} else {
			global $revisionary;
		}

		if ($revisionary->disable_revision_trigger) {
			return $data;
		}

        if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) ) {
            return $data;
		}
		
        if ( empty( $postarr['ID'] ) || empty($revisionary->impose_pending_rev[ $postarr['ID'] ]) ) {
            return $data;
        }

		if (!empty($postarr['post_type']) && empty($revisionary->enabled_post_types[$postarr['post_type']])) {
			return $data;
		}

        // todo: consolidate functions
        $this->flt_pendingrev_post_status($data['post_status']);

        if ( $revisionary->doing_rest && ! $revisionary->rest->is_posts_request ) {
            return $data;
		}

        if ( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions' ) ) {
            return $data;
        }

        if ( isset($_POST['action']) && ( 'autosave' == $_POST['action'] ) ) {
            if ( $revisionary->doing_rest ) {
                exit;
            } else {
                rvy_halt( __('Autosave disabled when editing a published post/page to create a pending revision.', 'revisionary' ) );
            }
		}
		
        return $this->flt_pending_revision_data($data, $postarr);
    }

    function flt_pendingrev_post_status($status) {
        if (!empty($this->revisionary)) {
			$revisionary = $this->revisionary;
		} else {
			global $revisionary;
		}
		
        if (rvy_is_revision_status($status) || ('inherit' == $status)) {
			return $status;
		}

		if ( $revisionary->doing_rest && $revisionary->rest->is_posts_request ) {
			$post_id = $revisionary->rest->post_id;
		} elseif ( ! empty( $_POST['post_ID'] ) ) {
			$post_id = (int) $_POST['post_ID'];
		} else {
			$post_id = rvy_detect_post_id();
		}
		
		if ( empty( $post_id ) || !is_scalar($post_id) ) {
			return $status;
		}

		global $current_user;

		if (rvy_get_post_meta( $post_id, "_save_as_revision_{$current_user->ID}", true )) {
			if (!empty($_POST)) {
				$_POST['skip_sitepress_actions'] = true;
				$_REQUEST['skip_sitepress_actions'] = true;
			}

			$revisionary->impose_pending_rev[$post_id] = true;
			return $status;
		}

		// Make sure the stored post is published / scheduled		
		// With Events Manager plugin active, Role Scoper 1.3 to 1.3.12 caused this filter to fire prematurely as part of object_id detection, flagging for pending_rev needlessly on update of an unpublished post
		$stored_post = get_post($post_id);

		if (empty($stored_post) || !isset($stored_post->post_status) || (!in_array($stored_post->post_status, rvy_filtered_statuses()) && ('future' != $stored_post->post_status))) {
			return $status;
		}
		
		if ( ! empty( $_POST['rvy_save_as_pending_rev'] ) && ! empty($post_id) ) {
			$revisionary->impose_pending_rev[$post_id] = true;
			
			if (!empty($_POST)) {
				$_POST['skip_sitepress_actions'] = true;
				$_REQUEST['skip_sitepress_actions'] = true;
			}
		}
		
		if ( is_content_administrator_rvy() )
			return $status;
		
		if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) ) {
			return $status;
		}	

		if ( $revisionary->doing_rest && $revisionary->rest->is_posts_request ) {
			$post_type = $revisionary->rest->post_type;
		} elseif ( ! empty( $_POST['post_type'] ) ) {
			$post_type = sanitize_key($_POST['post_type']);
		} else {
			$post_type = rvy_detect_post_type();
		} 
			
		if ( ! empty( $post_type ) ) {
			if (empty($revisionary->enabled_post_types[$post_type])) {
				return $status;
			}

			if (!agp_user_can('edit_post', $post_id, '', ['skip_revision_allowance' => true])) {
				$revisionary->impose_pending_rev[$post_id] = true;

				if (!empty($_POST)) {
					$_POST['skip_sitepress_actions'] = true;
					$_REQUEST['skip_sitepress_actions'] = true;
				}
			}
		}
		
		return $status;
	}

    // impose pending revision
    function flt_pending_revision_data( $data, $postarr ) {
        global $wpdb, $current_user, $pagenow;
		
		if (!empty($this->revisionary)) {
			$revisionary = $this->revisionary;
		} else {
			global $revisionary;
		}

		if ($revisionary->disable_revision_trigger) {
			return $data;
		}

		if (!empty($postarr['post_type']) && empty($revisionary->enabled_post_types[$postarr['post_type']])) {
			return $data;
		}

		if (!empty($pagenow) && ('post.php' == $pagenow)) {
			if (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], ['delete', 'trash'])) {
				return $data;
			}
		}

        if ( $revisionary->doing_rest && $revisionary->rest->is_posts_request && ! empty( $revisionary->rest->request ) ) {
            $postarr = array_merge( $revisionary->rest->request->get_params(), $postarr );
            
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
		
        if ( $revisionary->isBlockEditorActive() && !$revisionary->doing_rest ) {
            if (!empty($_REQUEST['meta-box-loader']) && !empty($_REQUEST['action']) && ('editpost' == $_REQUEST['action'])) {
                // Use logged revision ID from preceding REST query
                if (!$revision_id = (int) rvy_get_transient("_rvy_pending_revision_{$current_user->ID}_{$postarr['ID']}")) {
                    return $data;
                }
            } else {
                //delete_transient("_rvy_pending_revision_{$current_user->ID}_{$postarr['ID']}");
            }
        }

		// sanity check: don't create revision if same user created another pending revision for this post less than 5 seconds ago
		$min_seconds = (!empty($this->options['min_seconds'])) ? $this->options['min_seconds'] : 5;

		$last_revision = $wpdb->get_row($wpdb->prepare("SELECT ID, post_modified_gmt FROM $wpdb->posts WHERE post_status IN ('pending-revision', 'future-revision') AND comment_count = %d AND post_author = %d ORDER BY post_modified_gmt DESC LIMIT 1", $published_post->ID, $current_user->ID));	

		if ($last_revision && (strtotime(current_time('mysql', 1)) - strtotime($last_revision->post_modified_gmt) < $min_seconds )) {
			// return currently stored published post data
			$data = array_merge($data, (array) get_post($published_post->ID));
			return $data;
		}

        if (!empty($_POST)) {
			$_POST['skip_sitepress_actions'] = true;
			$_REQUEST['skip_sitepress_actions'] = true;
        }

		// ACF: prevent this filter application, called by ACF after wp_update_post(), from stripping attachment field postmeta out of revision
		add_filter('attachment_fields_to_save', 
			function($fields) {
				add_filter('update_post_metadata', ['\PublishPress\Revisions\RevisionCreation', 'fltInterruptPostMetaOperation']);
				add_filter('delete_post_metadata', ['\PublishPress\Revisions\RevisionCreation', 'fltInterruptPostMetaOperation']);
				return $fields;
			},
			1
		);

		add_filter('attachment_fields_to_save', 
			function($fields) {
				remove_filter('update_post_metadata', ['\PublishPress\Revisions\RevisionCreation', 'fltInterruptPostMetaOperation']);
				remove_filter('delete_post_metadata', ['\PublishPress\Revisions\RevisionCreation', 'fltInterruptPostMetaOperation']);
				return $fields;
			},
			999
		);

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

			if ($data['post_name'] != $published_post->post_name) {
				$requested_slug = $data['post_name'];
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
                    $data['post_category'] = $revisionary->content_roles->filter_object_terms( $data['post_category'], 'category' );
                }
            }	
            */
            
            if ( $future_date = ! empty($data['post_date']) && ( strtotime($data['post_date_gmt'] ) > agp_time_gmt() ) ) {  // $future_date is also passed to get_revision_msg()
                // round down to zero seconds
                $data['post_date_gmt'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date_gmt'] ) );
                $data['post_date'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date'] ) );
            }

			$emoji_fields = array( 'post_title', 'post_content', 'post_excerpt' );

			foreach ( $emoji_fields as $emoji_field ) {
				if ( isset( $data[ $emoji_field ] ) ) {
					$charset = $wpdb->get_col_charset( $wpdb->posts, $emoji_field );
					if ( 'utf8' === $charset ) {
						$data[ $emoji_field ] = wp_encode_emoji( $data[ $emoji_field ] );
					}
				}
			}

			if (defined('RVY_REVISION_CREATION_DO_UNSLASH')) {
				$data = wp_unslash( $data );
			}

			if ($bypass_data = apply_filters('revisionary_bypass_revision_creation', false, $data, $published_post)) {
				return $bypass_data;
			}

			$revision_id = $this->create_revision($data, $postarr);

			if (!is_scalar($revision_id)) { // update_post_data() returns array or object on update abandon / failure
				$data['ID'] = $revision_id;
				return $data;
            }

            $post = get_post($revision_id);
        }

        // Pro: better compatibility in third party action handlers
        $revision_id = (int) $revision_id;

        unset($revisionary->impose_pending_rev[ $published_post->ID ]);
		
		if (defined('WPML_TM_VERSION')) {
			// don't let revision submission trigger needs_update flagging
			remove_action( 'wpml_tm_save_post', 'wpml_tm_save_post', 10, 3 );
		}

        if ( $revision_id ) {
			$revisionary->last_revision[$published_post->ID] = $revision_id;
            rvy_set_transient("_rvy_pending_revision_{$current_user->ID}_{$published_post->ID}", $revision_id, 30);

            rvy_update_post_meta($revision_id, '_rvy_base_post_id', $published_post->ID);
            rvy_update_post_meta($published_post->ID, '_rvy_has_revisions', true);

			if (!empty($requested_slug)) {
				add_post_meta($revision_id, '_requested_slug', $requested_slug);
			}

            $post_id = $published_post->ID;						  // passing args ensures back compat by using variables directly rather than retrieving revision, post data
            $object_type = isset($postarr['post_type']) ? $postarr['post_type'] : '';
            $msg = $revisionary->get_revision_msg( $revision_id, compact( 'data', 'post_id', 'object_type', 'future_date' ) );
        
			foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
				if ($archived_val = rvy_get_transient("_archive_{$meta_key}_{$published_post->ID}")) {
					switch ($meta_key) {
						case '_thumbnail_id':
							set_post_thumbnail($published_post->ID, $archived_val);
							rvy_update_post_meta($published_post->ID, '_thumbnail_id', $archived_val);
							break;
	
						case '_wp_page_template':
							rvy_update_post_meta($published_post->ID, '_wp_page_template', $archived_val);
							break;
					}
				}
			}
        } else {
            $msg = __('Sorry, an error occurred while attempting to submit your revision!', 'revisionary') . ' ';
            rvy_halt( $msg, __('Revision Submission Error', 'revisionary') );
        }

		// Prevent unintended clearance of Page Template on revision submission
		if ($revisionary->isBlockEditorActive()) {
			if ($published_template = get_post_meta($published_post->ID, '_wp_page_template', true)) {
				if (!get_post_meta($revision_id, '_wp_page_template', true)) {
					rvy_update_post_meta($revision_id, '_wp_page_template', $published_template);
				}
			}
		}

        if (!$revisionary->doing_rest) {
            $_POST['ID'] = $revision_id;
            $_REQUEST['ID'] = $revision_id;

			do_action( 'revisionary_save_revision', $post );
			
			if (rvy_option('revision_submit_trigger_post_actions')) {
				do_action( "save_post_{$post->post_type}", $revision_id, $post, false );
				do_action( 'save_post', $revision_id, $post, false );
				do_action( 'wp_insert_post', $revision_id, $post, false );
			}
			
			do_action( 'revisionary_saved_revision', $post );
        }

		// Stop WooCommerce from setting new pending revision Products to "out of stock"
		if (get_post_meta($revision_id, '_stock_status')) {
			revisionary_copy_meta_field('_stock_status', $published_post->ID, $revision_id);
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
                    $_author = \MultipleAuthors\Classes\Objects\Author::get_by_term_id($_author->term_id);
                }
            }

            $published_authors = get_multiple_authors($published_post->ID);

            // If multiple authors could not be stored, restore original authors from published post
            if (empty($_authors) || (!empty($_author) && $_author->ID == $current_user->ID)) {
                if (!$published_authors) {
                    if ($author = \MultipleAuthors\Classes\Objects\Author::get_by_user_id((int) $published_post->post_author)) {
                        $published_authors = [$author];
                    }
                }

                if ($published_authors) {
                    // This sets author taxonomy terms and meta field ppma_author_name
                    rvy_set_ma_post_authors($revision_id, $published_authors);

                    // Also ensure meta field is set for published post
                    rvy_set_ma_post_authors($published_post->ID, $published_authors);
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

        if ( $revisionary->doing_rest || apply_filters('revisionary_limit_revision_fields', false, $post, $published_post) ) {
			// prevent alteration of published post, while allowing save operation to complete
			
			$keys = array_fill_keys( array( 'post_type', 'post_name', 'post_status', 'post_parent', 'post_author', 'post_content' ), true );

			if (!isset($data['ID']) || ($data['ID'] != $published_post->ID)) {
				$keys['ID'] = true;
			}

            $data = array_intersect_key( (array) $published_post, $keys );
        }

		if (!rvy_is_revision_status($published_post->post_status)) {
			do_action('revisionary_created_revision', $post);

			if (apply_filters('revisionary_do_revision_notice', !$revisionary->doing_rest, $post, $published_post)) {
				$object_type = isset($postarr['post_type']) ? $postarr['post_type'] : '';
				$args = compact( 'revision_id', 'published_post', 'object_type' );
				if ( ! empty( $_REQUEST['prev_cc_user'] ) ) {
					$args['selected_recipients'] = array_map('intval', $_REQUEST['prev_cc_user']);
				}
				$revisionary->do_notifications( 'pending-revision', 'pending-revision', $postarr, $args );

				if (apply_filters('revisionary_do_submission_redirect', true)) {
					rvy_halt($msg, __('Pending Revision Created', 'revisionary'));
				}
			} else {
				// return currently stored published post data
				$data = array_intersect_key((array) get_post($published_post->ID), $data);
			}
		}

        return $data;
    }

	static function fltInterruptPostMetaOperation($interrupt) {
		return true;
	}

    function flt_create_scheduled_rev( $data, $post_arr ) {
		global $current_user, $wpdb;

		if (!empty($this->revisionary)) {
			$revisionary = $this->revisionary;
		} else {
			global $revisionary;
		}

		if ($revisionary->disable_revision_trigger) {
			return $data;
		}

		if ( empty( $post_arr['ID'] ) ) {
			return $data;
		}

		if (!empty($post_arr['post_type']) && empty($revisionary->enabled_post_types[$post_arr['post_type']])) {
			return $data;
		}
		
		if ( ! $published_post = get_post( $post_arr['ID'] ) ) {
			return $data;
		}

		// sanity check: don't create revision if same user created another pending revision for this post less than 5 seconds ago
		$min_seconds = (!empty($this->options['min_seconds'])) ? $this->options['min_seconds'] : 5;

		$last_revision = $wpdb->get_row($wpdb->prepare("SELECT ID, post_modified_gmt FROM $wpdb->posts WHERE comment_count = %d AND post_author = %d ORDER BY post_modified_gmt DESC LIMIT 1", $published_post->ID, $current_user->ID));	

		if ($last_revision && (strtotime(current_time('mysql', 1)) - strtotime($last_revision->post_modified_gmt) < $min_seconds )) {
			return $data;
		}
		if ( isset($_POST['wp-preview']) && ( 'dopreview' == $_POST['wp-preview'] ) ) {
			return $data;
		}
		
		if ( isset($_POST['action']) && ( 'autosave' == $_POST['action'] ) ) {
			return $data;
		}

		if ( $revisionary->doing_rest && ! $revisionary->rest->is_posts_request ) {
			return $data;
		}

		if ( $revisionary->doing_rest && $revisionary->rest->is_posts_request && ! empty( $revisionary->rest->request ) ) {
			$post_arr = array_merge( $revisionary->rest->request->get_params(), $post_arr );
			
			if (isset($post_arr['featured_media'])) {
				$post_arr['_thumbnail_id'] = $post_arr['featured_media'];
			}
		}

		if ( $revisionary->isBlockEditorActive() && !$revisionary->doing_rest ) {
			if (!empty($_REQUEST['meta-box-loader']) && !empty($_REQUEST['action']) && ('editpost' == $_REQUEST['action'])) {
				// Use logged revision ID from preceding REST query
				if (!$revision_id = (int) rvy_get_transient("_rvy_scheduled_revision_{$current_user->ID}_{$post_arr['ID']}")) {
					return $data;
				}
			} else {
				//delete_transient("_rvy_scheduled_revision_{$current_user->ID}_{$post_arr['ID']}");
			}
		}

		if ( $revisionary->doing_rest ) {
			$original_post_status = get_post_field( 'post_status', $post_arr['ID']);
		} else { 
			// @todo: eliminate this?
			$original_post_status = ( isset( $_POST['original_post_status'] ) ) ? $_POST['original_post_status'] : '';
			
			if ( ! $original_post_status ) {
				$original_post_status = ( isset( $_POST['hidden_post_status'] ) ) ? $_POST['hidden_post_status'] : '';
			}

			if (!$original_post_status) {
				$original_post_status = get_post_field('post_status', $post_arr['ID']);
			}
		}

		// don't interfere with scheduling of unpublished drafts
		if ( ! $stored_status_obj = get_post_status_object( $original_post_status ) ) {
			return $data;
		}

		if ( empty( $stored_status_obj->public ) && empty( $stored_status_obj->private ) ) {
			return $data;
		}
		
		if ( empty($post_arr['post_date_gmt']) || ( strtotime($post_arr['post_date_gmt'] ) <= agp_time_gmt() ) ) {
			// Allow continued processing for non-REST followup query after REST operation
			if (empty($_REQUEST['meta-box-loader']) || empty($_REQUEST['action']) || ('editpost' != $_REQUEST['action'])) {		
				return apply_filters('revisionary_future_rev_submit_data', $data, $published_post);
			}
		}

		if (!agp_user_can('edit_post', $published_post->ID, $current_user->ID, ['skip_revision_allowance' => true])) {
			return $data;
		}
		
		if (!empty($_POST)) {
			$_POST['skip_sitepress_actions'] = true;
			$_REQUEST['skip_sitepress_actions'] = true;
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
				$post_arr['post_category'] = $revisionary->content_roles->filter_object_terms( $post_arr['post_category'], 'category' );
			}
		}
		*/

		$revisionary->save_future_rev[$published_post->ID] = true;

		if (!empty($revision_id) && $post = get_post($revision_id)) {
			$post_ID = $revision_id;
			$post_arr['post_ID'] = $revision_id;
			$data = (!defined('REVISIONARY_NO_UNSLASH')) ? (array) $post : wp_unslash((array) $post);
		} else {
			unset($data['post_ID']);

			// round down to zero seconds
			$data['post_date_gmt'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date_gmt'] ) );
			$data['post_date'] = date( 'Y-m-d H:i:00', strtotime( $data['post_date'] ) );

			$data = apply_filters('revisionary_future_rev_creation_data', $data, $published_post);

			$revision_id = $this->create_revision($data, $post_arr);

			if (!is_scalar($revision_id)) { // update_post_data() returns array or object on update abandon / failure
				$data = array_intersect_key( (array) $published_post, array_fill_keys( array( 'ID', 'post_type', 'post_name', 'post_status', 'post_parent', 'post_author', 'post_content' ), true ) );
				return $data;
			}

			if ($revision_id) {
				rvy_set_transient("_rvy_scheduled_revision_{$current_user->ID}_{$post_arr['ID']}", $revision_id, 30);

				rvy_update_post_meta($revision_id, '_rvy_base_post_id', $published_post->ID);
				rvy_update_post_meta($published_post->ID, '_rvy_has_revisions', true);

				foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
					$published_meta_val = get_post_meta($published_post->ID, $meta_key, true);
					rvy_set_transient("_archive_{$meta_key}_{$published_post->ID}", $published_meta_val, 30);
				}
			} else {
				$msg = __('Sorry, an error occurred while attempting to schedule your revision!', 'revisionary') . ' ';
				rvy_halt( $msg, __('Revision Scheduling Error', 'revisionary') );
			}

			$post = get_post($revision_id);
		}
	
		// Pro: better compatibility in third party action handlers
		$revision_id = (int) $revision_id;

		if (defined('WPML_TM_VERSION')) {
			// don't let revision submission trigger needs_update flagging
			remove_action( 'wpml_tm_save_post', 'wpml_tm_save_post', 10, 3 );
		}

		// Prevent unintended clearance of Page Template on revision submission
		if ($revisionary->isBlockEditorActive()) {
			if ($published_template = get_post_meta($published_post->ID, '_wp_page_template', true)) {
				if (!get_post_meta($revision_id, '_wp_page_template', true)) {
					rvy_update_post_meta($revision_id, '_wp_page_template', $published_template);
				}
			}
		}

		if (!$revisionary->doing_rest) {
			$_POST['ID'] = $revision_id;
			$_REQUEST['ID'] = $revision_id;

			do_action( 'revisionary_save_revision', $post );

			if (rvy_option('revision_submit_trigger_post_actions')) {
				do_action( "save_post_{$post->post_type}", $revision_id, $post, false );
				do_action( 'save_post', $revision_id, $post, false );
				do_action( 'wp_insert_post', $revision_id, $post, false );
			}

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

		if ( $revisionary->doing_rest ) {
			// prevent alteration of published post, while allowing save operation to complete
			$data = array_intersect_key( (array) $published_post, array_fill_keys( array( 'ID', 'post_name', 'post_status', 'post_parent', 'post_author' ), true ) );
			rvy_update_post_meta( $published_post->ID, "_new_scheduled_revision_{$current_user->ID}", $revision_id );
		} else {
			if (apply_filters('revisionary_do_schedule_redirect', true)) {
				$msg = $revisionary->get_revision_msg( $revision_id, array( 'post_id' => $published_post->ID ) );
				rvy_halt( $msg, __('Scheduled Revision Created', 'revisionary') );
			}
		}

		return apply_filters('revisionary_future_rev_return_data', $data, $published_post, $revision_id);
    }
    
    private function create_revision($data, $postarr) {
		global $wpdb, $current_user;

		$data['post_author'] = $current_user->ID;		// store current user as revision author (but will retain current post_author on restoration)

		$data['post_modified'] = current_time( 'mysql' );
		$data['post_modified_gmt'] = current_time( 'mysql', 1 );

		if (!defined('REVISIONARY_NO_UNSLASH')) {
			$data = wp_unslash($data);
		}

		$post_type = $data['post_type'];
		unset($data['ID']);

		if ( false === $wpdb->insert( $wpdb->posts, $data ) ) {
			if (!empty($wpdb->last_error)) {
				return new WP_Error( 'db_insert_error', __( 'Could not insert revision into the database', 'revisionary' ), $wpdb->last_error );
			} else {
				return 0;
			}
		}

		if (!empty($_POST)) {
			$_POST['skip_sitepress_actions'] = true;
			$_REQUEST['skip_sitepress_actions'] = true;
        }

		$post_ID = (int) $wpdb->insert_id; // revision_id
		$revision_id = $post_ID;

		$published_post_id = rvy_post_id($data['comment_count']);

		// Workaround for Gutenberg stripping post thumbnail, page template on revision creation
		$archived_meta = [];
		foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
			if (!$archived_meta[$meta_key] = rvy_get_transient("_archive_{$meta_key}_{$published_post_id}")) {
				$archived_meta[$meta_key] = get_post_meta($published_post_id, $meta_key, true);
			}
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

		if (is_object_in_taxonomy( $post_type, 'post_tag' )) {
			if (isset($postarr['tags_input'])) {
				wp_set_post_tags( $post_ID, $postarr['tags_input'] );

			} elseif (isset($postarr['tags'])) {
				wp_set_post_tags( $post_ID, $postarr['tags'] );

			} elseif ($tags = wp_get_object_terms($published_post_id, 'post_tag', ['fields' => 'ids'])) {
				wp_set_post_tags( $post_ID, $tags );
			}
		}
	
		// New-style support for all custom taxonomies.
		$set_taxonomies = [];
		if ( ! empty( $postarr['tax_input'] ) ) {
			foreach ( $postarr['tax_input'] as $taxonomy => $tags ) {
				$taxonomy_obj = get_taxonomy( $taxonomy );
				if ( ! $taxonomy_obj ) {
					/* translators: %s: taxonomy name */
					_doing_it_wrong( __FUNCTION__, sprintf( __( 'Invalid taxonomy: %s', 'revisionary' ), $taxonomy ), '4.4.0' );
					continue;
				}
	
				// array = hierarchical, string = non-hierarchical.
				if ( is_array( $tags ) ) {
					$tags = array_filter( $tags );
				}
				
				if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
					wp_set_post_terms( $post_ID, $tags, $taxonomy );
					$set_taxonomies[$taxonomy] = true;
				}
			}
		}

		// If term selections are not posted for revision, store current published terms
		foreach(get_taxonomies() as $taxonomy) {
			if (empty($set_taxonomies[$taxonomy]) && !in_array($taxonomy, ['category', 'post_tag'])) {
				if ($published_terms = wp_get_object_terms($published_post_id, $taxonomy, ['fields' => 'ids'])) {
					wp_set_object_terms( $post_ID, $published_terms, $taxonomy );
				}
			}
		}
	
		if ( ! empty( $postarr['meta_input'] ) ) {
			foreach ( $postarr['meta_input'] as $field => $value ) {
				rvy_update_post_meta( $post_ID, $field, $value );
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
				rvy_update_post_meta( $post_ID, '_wp_attachment_context', $postarr['context'], true );
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
					return new WP_Error( 'invalid_page_template', __( 'Invalid page template.', 'revisionary' ) );
				}
				rvy_update_post_meta( $post_ID, '_wp_page_template', 'default' );
			} else {
				rvy_update_post_meta( $post_ID, '_wp_page_template', $postarr['page_template'] );
			}
		}
	
		// Workaround for Gutenberg stripping post thumbnail, page template on revision creation
		foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
			if (!empty($archived_meta[$meta_key])) {
				rvy_update_post_meta($published_post_id, $meta_key, $archived_meta[$meta_key]);
			}
		}

		if (rvy_get_option('revision_submit_trigger_post_actions')) {
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
		}

		$data['ID'] = $revision_id;
		do_action('revisionary_create_revision', $data);

		return (int) $revision_id; // only return array in calling function should return
	}
}
