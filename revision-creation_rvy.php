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

}
