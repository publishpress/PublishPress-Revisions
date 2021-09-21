<?php

function revisionary() {
    return \PublishPress\Revisions::instance();
    
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
function rvy_nc_url($url) {
    return add_query_arg('nc', substr(md5(rand()), 1, 8), $url);
}

// Complete an admin URL, appending a random argument for cache busting
function rvy_admin_url($partial_admin_url) {
    return rvy_nc_url( admin_url($partial_admin_url) );
}
