<?php
class RevisionaryActivation {
    function __construct($args = []) {
        $args = (array) $args;
        if (!empty($args['import_legacy'])) {
            $this->importLegacyRevisions();
        }
    }

    function importLegacyRevisions() {
        global $wpdb;

        if (!get_option('revisionary_last_version') && get_site_transient('revisionary_previous_install')) {
            // Trigger dismissible migration notice even if no pending or scheduled revisions currently stored
            set_site_transient('_revisionary_1x_migration', true, 86400);
        }

        if (defined('REVISIONARY_FORCE_REIMPORT') && REVISIONARY_FORCE_REIMPORT) {
            $id_csv = '';
        } elseif ($imported_ids = (array) get_option('revisionary_imported_ids')) {
            $id_csv = implode("','", array_map('intval', $imported_ids));
        } else {
            $imported_ids = [];
            $id_csv = '';
        }

        if (!$revisions = $wpdb->get_results(
            "SELECT r.post_author AS post_author, r.post_date AS rev_date, r.post_date_gmt AS rev_date_gmt,"
            . " r.post_content AS post_content, r.post_title AS post_title, r.post_excerpt AS post_excerpt,"
            . " r.post_status AS post_status, r.post_modified AS post_modified, r.post_modified_gmt AS post_modified_gmt,"
            . " r.ID AS rev_ID, r.post_parent AS comment_count, p.post_parent AS post_parent,"
            . " p.post_type AS post_type, p.post_date AS post_date, p.post_date_gmt AS post_date_gmt, p.guid AS guid,"
            . " p.comment_status AS comment_status, p.ping_status AS ping_status, p.menu_order AS menu_order"
            . " FROM $wpdb->posts AS r"
            . " INNER JOIN $wpdb->posts AS p"
            . " ON r.post_type = 'revision' AND r.post_status IN ('pending', 'future') AND r.post_parent = p.ID"
            . " WHERE r.ID NOT IN('$id_csv')"
            . " ORDER BY p.ID, r.ID"
            )
        ) {
            return;
        }

        $last_post_id = 0;

        foreach($revisions as $old) {
            $new = (array) $old;
            unset($new['rev_date']);
            unset($new['rev_date_gmt']);
            unset($new['rev_ID']);

            if ('future' == $old->post_status) {
                $new['post_status'] = 'future-revision';
                $new['post_date'] = $old->rev_date;
                $new['post_date_gmt'] = $old->rev_date_gmt;
            } else {
                $new['post_status'] = 'pending-revision';
            }

            $wpdb->insert($wpdb->posts, $new);
            $new_revision_id = (int)$wpdb->insert_id;
            
            add_post_meta($new_revision_id, '_rvy_base_post_id', $new['comment_count']);
            add_post_meta($new_revision_id, '_rvy_imported_revision', $old->rev_ID);

            if ($new['comment_count'] != $last_post_id) {  // avoid redundant update for same post
                require_once(dirname(__FILE__).'/rvy_init.php');
                rvy_update_post_meta($new['comment_count'], '_rvy_has_revisions', true);
                $last_post_id = $new['comment_count'];
            }

            if (defined('REVISIONARY_DELETE_LEGACY_REVISIONS')) {
                $wpdb->delete($wpdb->posts, ['ID' => $old->rev_ID]);
            }

            $imported_ids []= $old->rev_ID;
        }

        update_option('revisionary_imported_ids', $imported_ids);
    }
}
