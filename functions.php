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
}
