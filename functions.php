<?php
function revisionary() {
    return \PublishPress\Revisions::instance();
}

function revisionary_unrevisioned_postmeta() {
	$exclude = array_fill_keys( array( '_rvy_base_post_id', '_rvy_has_revisions', '_rvy_published_gmt', '_pp_is_autodraft', '_pp_last_parent', '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_attached_file', '_menu_item_classes', '_menu_item_menu_item_parent', '_menu_item_object', '_menu_item_object_id', '_menu_item_target', '_menu_item_type', '_menu_item_url', '_menu_item_xfn', '_rs_file_key', '_scoper_custom', '_scoper_last_parent', '_wp_attachment_backup_sizes', '_wp_attachment_metadata', '_wp_trash_meta_status', '_wp_trash_meta_time', '_last_attachment_ids', '_last_category_ids', '_encloseme', '_pingme' ), true );
	return apply_filters( 'revisionary_unrevisioned_postmeta', $exclude );
}

/**
 * Copies the taxonomies of a post to another post.
 * Based on Yoast Duplicate Post
 *
 * @param \WP_Post $from_post  The source post object.
 * @param int      $target_id  Target post ID.
 * @param array    $args The options array.
 * 
 * @return void
 */
function revisionary_copy_terms($from_post, $target_id, $args = []) {
    global $wpdb;

    $defaults = ['empty_target_only' => false];
    $args = array_merge($defaults, $args);
    foreach (array_keys($defaults) as $var) {
        $$var = $args[$var];
    }

    if ( isset( $wpdb->terms ) ) {
        if (is_scalar($from_post)) {
            $from_post = get_post($from_post);
        }

        if (empty($from_post) || empty($from_post->post_type)) {
            return;
        }

        // Clear default category (added by wp_insert_post).
        wp_set_object_terms( $target_id, null, 'category' );

        $post_taxonomies = get_object_taxonomies( $from_post->post_type );
        
        // Several plugins just add support to post-formats but don't register post_format taxonomy.
        if (!in_array('post_format', $post_taxonomies, true) && post_type_supports($from_post->post_type, 'post-formats')) {
            $post_taxonomies[] = 'post_format';
        }

        /**
         * Filters the taxonomy excludelist when copying a post.
         *
         * @param array $taxonomies_blacklist The taxonomy excludelist from the options.
         *
         * @return array
         */
        $taxonomies_blacklist = apply_filters('revisionary_skip_taxonomies', []);

        foreach (array_diff($post_taxonomies, $taxonomies_blacklist) as $taxonomy) {
            if ($empty_target_only) {
                $target_terms = wp_get_object_terms($target_id, $taxonomy, ['fields' => 'ids']);
                if (!empty($target_terms)) {
                    continue;
                }
            }

            $post_term_slugs = wp_get_object_terms($from_post->ID, $taxonomy, ['fields' => 'slugs', 'orderby' => 'term_order']);
            wp_set_object_terms($target_id, $post_term_slugs, $taxonomy);
        }
    }
}

/**
 * Copies the meta information of a post to another post.
 * Based on Yoast Duplicate Post
 *
 * @param \WP_Post $from_post  The source post object.
 * @param int      $target_id  Target post ID.
 * @param array    $args The options array.
 *
 * @return void
 */
function revisionary_copy_postmeta($from_post, $to_post_id, $args = []) {
    $defaults = ['empty_target_only' => false];
    $args = array_merge($defaults, $args);
    foreach (array_keys($defaults) as $var) {
        $$var = $args[$var];
    }

    if (is_scalar($from_post)) {
        $from_post = get_post($from_post);
    }

    $source_meta_keys = \get_post_custom_keys( $from_post->ID );
    if ( empty( $source_meta_keys ) ) {
        return;
    }

    $meta_excludelist = revisionary_unrevisioned_postmeta();

    $meta_excludelist_string = '(' . implode( ')|(', $meta_excludelist ) . ')';
    if ( strpos( $meta_excludelist_string, '*' ) !== false ) {
        $meta_excludelist_string = str_replace( [ '*' ], [ '[a-zA-Z0-9_]*' ], $meta_excludelist_string );

        $meta_keys = [];
        foreach ( $source_meta_keys as $meta_key ) {
            if ( ! preg_match( '#^' . $meta_excludelist_string . '$#', $meta_key ) ) {
                $meta_keys[] = $meta_key;
            }
        }
    } else {
        $meta_keys = array_diff( $source_meta_keys, $meta_excludelist );
    }

    $meta_keys = apply_filters('revisionary_create_revision_meta_keys', $meta_keys);

    foreach ( $meta_keys as $meta_key ) {
        if ($empty_target_only) {
            $target_values = \get_post_custom_values( $meta_key, $to_post_id );
            if (!empty($target_values)) {
                continue;
            }
        }

        $meta_values = \get_post_custom_values( $meta_key, $from_post->ID );
        foreach ( $meta_values as $meta_value ) {
            $meta_value = maybe_unserialize( $meta_value );
            update_post_meta( $to_post_id, $meta_key, \PublishPress\Revisions\Utils::recursively_slash_strings( $meta_value ) );
        }
    }

    $args = array_merge(
        $args,
        compact('meta_keys', 'source_meta_keys')
    );

    do_action('revisionary_copy_postmeta', $from_post, $to_post_id, $args);
}

function rvy_revision_base_statuses($args = []) {
	$defaults = ['output' => 'names', 'return' => 'array'];
	$args = array_merge($defaults, $args);
	foreach (array_keys($defaults) as $var) {
		$$var = $args[$var];
	}

	$arr = array_map('sanitize_key', (array) apply_filters('rvy_revision_base_statuses', ['draft', 'pending', 'future']));

	if ('object' == $output) {
		$status_keys = array_value($arr);
		$arr = [];

		foreach($status_keys as $k) {
			$arr[$k] = get_post_status_object($k);
		}
	}

	return ('csv' == $return) ? "'" . implode("','", $arr) . "'" : $arr;
}

function rvy_revision_statuses($args = []) {
	$defaults = ['output' => 'names', 'return' => 'array'];
	$args = array_merge($defaults, $args);
	foreach (array_keys($defaults) as $var) {
		$$var = $args[$var];
	}
	
	$arr = array_map('sanitize_key', (array) apply_filters('rvy_revision_statuses', ['draft-revision', 'pending-revision', 'future-revision']));

	if ('object' == $output) {
		$status_keys = array_value($arr);
		$arr = [];

		foreach($status_keys as $k) {
			$arr[$k] = get_post_status_object($k);
		}
	}

	return ('csv' == $return) ? "'" . implode("','", $arr) . "'" : $arr;
}

function rvy_is_revision_status($post_status) {
	return in_array($post_status, rvy_revision_statuses());
}

function rvy_in_revision_workflow($post) {
	if (!empty($post) && is_numeric($post)) {
		$post = get_post($post);
	}

	if (empty($post) || empty($post->post_mime_type)) {
		return false;
	}

    return rvy_is_revision_status($post->post_mime_type) && in_array($post->post_status, rvy_revision_base_statuses()) ? $post->post_mime_type : false;
}

function rvy_post_id($revision_id) {
	static $busy;

	if (!empty($busy)) {
		return;
	}

	$busy = true;
	$published_id = rvy_get_post_meta( $revision_id, '_rvy_base_post_id', true );
	$busy = false;

	if (empty($published_id)) {
		if ($_post = get_post($revision_id)) {
			// if ID passed in is not a revision, return it as is
			if (('revision' != $_post->post_type) && !rvy_in_revision_workflow($_post)) {
				return $revision_id;

			} elseif ('revision' == $_post->post_type) {
				return $_post->post_parent;

			} else {
				// Restore missing postmeta field
				/*
				if ($_post->comment_count) {
					rvy_update_post_meta( $revision_id, '_rvy_base_post_id', $_post->comment_count );
				}
				*/

				return $_post->comment_count;
			}
		}
	}

	return ($published_id) ? $published_id : 0;
}

// Append a random argument for cache busting
function rvy_nc_url($url, $args = []) {
    $nc = (!empty($args['nc'])) ? $args['nc'] : substr(md5(rand()), 1, 8);
    return add_query_arg('nc', $nc, $url);
}

// Complete an admin URL, appending a random argument for cache busting
function rvy_admin_url($partial_admin_url) {
    return rvy_nc_url( admin_url($partial_admin_url) );
}

function pp_revisions_plugin_updated($current_version) {
    $last_ver = get_option('revisionary_last_version');

    if (version_compare($last_ver, '3.0.5-beta', '<')) {
        if ($role = @get_role('revisor')) {
            $role->add_cap('list_others_posts');
            $role->add_cap('list_others_pages');
            $role->add_cap('list_published_posts');
            $role->add_cap('list_published_pages');
            $role->add_cap('list_private_posts');
            $role->add_cap('list_private_pages');
        }
    }

    if (version_compare($last_ver, '3.0.1', '<')) {
        // convert pending / scheduled revisions to v3.0 format
		global $wpdb;
		$revision_status_csv = rvy_revision_statuses(['return' => 'csv']);
		$wpdb->query("UPDATE $wpdb->posts SET post_mime_type = post_status WHERE post_status IN ($revision_status_csv)");
		$wpdb->query("UPDATE $wpdb->posts SET post_status = 'draft' WHERE post_status IN ('draft-revision')");
		$wpdb->query("UPDATE $wpdb->posts SET post_status = 'pending' WHERE post_status IN ('pending-revision')");
		$wpdb->query("UPDATE $wpdb->posts SET post_status = 'future' WHERE post_status IN ('future-revision')");
    } 

    if (version_compare($last_ver, '3.0.2', '<')) {
        // delete revisions that were erroneously trashed instead of deleted
		global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->posts WHERE post_mime_type IN ('draft-revision', 'pending-revision', 'future-revision') AND post_status = 'trash'");
    }

    if (version_compare($last_ver, '3.0-rc7', '<')) {
        if ($role = @get_role('administrator')) {
            $role->add_cap('manage_unsubmitted_revisions');
        }

        if ($role = @get_role('revisor')) {
            $role->add_cap('upload_files');
        }
    } 

    /*
    elseif (version_compare($last_ver, '2.4.3-beta4', '<')) { // Empty Queue condition was reported with Elementor, so default this setting
        if (defined('ELEMENTOR_VERSION') || defined('ELEMENTOR_PRO_VERSION')) {
            update_option('rvy_queue_query_all_posts', 1);
        }
    }
    */

    if ($current_version != $last_ver) {
        require_once( dirname(__FILE__).'/lib/agapetry_wp_core_lib.php');
        require_once(dirname(__FILE__).'/rvy_init.php');
        revisionary_refresh_revision_flags();

        // mirror to REVISIONARY_VERSION
        update_option('revisionary_last_version', $current_version);

        delete_option('revisionary_sent_mail');
    }
}
