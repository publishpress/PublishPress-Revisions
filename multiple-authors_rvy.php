<?php

/**
 * Save a custom field with the post authors' name. Add compatibility to
 * Yoast for using in the custom title, and other 3rd party plugins.
 *
 * @param $post_id
 * @param $authors
 */
function _rvy_set_ma_post_authors_custom_field($post_id, $authors)
{
	global $wpdb, $multiple_authors_addon;

	if ( ! is_array($authors)) {
		$authors = [];
	}

	$metadata = 'ppma_authors_name';

	if (empty($authors)) {
		delete_post_meta($post_id, $metadata);
	} else {
		$names = [];

		foreach ($authors as $author) {
			// since this function may be passed a term object with no name property, do a fresh query
			if (!is_numeric($author)) {
				if (empty($author->term_id)) {
					return;
				}

				$author = $author->term_id;
			}

			$taxonomy = (!empty($multiple_authors_addon) && !empty($multiple_authors_addon->coauthor_taxonomy)) 
			? $multiple_authors_addon->coauthor_taxonomy 
			: 'author';

			//$author = Author::get_by_term_id($author);  // this returns an object with term_id property and no name
			//$author = get_term($author, 'author');	  // 'author' is actually an invalid taxonomy name per WP API
			$author = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id"
					. " WHERE tt.taxonomy = %s AND t.term_id = %d"
					, $taxonomy, $author
				)
			);

			if (!empty($author->name)) {
				$names[] = $author->name;
			}
		}

		if (!empty($names)) {
			$names = implode(', ', $names);
			rvy_update_post_meta($post_id, $metadata, $names);
		} else {
			delete_post_meta($post_id, $metadata);
		}
	}
}
