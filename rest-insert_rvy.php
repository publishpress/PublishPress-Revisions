<?php

function _rvy_act_rest_insert( $post, $request, $unused ) {
    global $current_user, $revisionary, $wpdb;

    $revisionary->flt_pendingrev_post_status($post->post_status);

    if (!empty($revisionary->impose_pending_rev[$post->ID]) || !empty($revisionary->save_future_rev[$post->ID])) {
        // todo: better revision id logging

        //$revision_id = $revisionary->impose_pending_rev[$post->ID];

        $revision_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_status IN ('pending-revision', 'future-revision') AND "
                . "post_author = %s AND comment_count = %d "
                . "ORDER BY ID DESC LIMIT 1",
                $current_user->ID,
                $post->ID
            )
        );

        // store selected meta array, featured image, template, format and stickiness to revision

        // todo: validate schema support for sticky, page template
        //$schema = $revisionary->get_item_schema();

        //if (! empty( $schema['properties']['format'] ) && isset($request['format'])) {
        if (!empty($request['format']) && post_type_supports($post->post_type, 'post-formats')) {
            set_post_format( $revision_id, $request['format'] );
        }

        //if (! empty( $schema['properties']['featured_media'] ) && isset($request['featured_media'])) {
        if (isset($request['featured_media']) && post_type_supports($post->post_type, 'thumbnail')) {
            $revisionary->handle_featured_media( $request['featured_media'], $revision_id );
        }

        //if ( ! empty( $schema['properties']['sticky'] ) && isset( $request['sticky'] ) ) {
        if ( isset( $request['sticky'] ) ) {
            if ( ! empty( $request['sticky'] ) ) {
                stick_post( $revision_id );
            } else {
                unstick_post( $revision_id );
            }
        }

        //if ( ! empty( $schema['properties']['template'] ) && isset( $request['template'] ) ) {
        if (isset($request['template']) && post_type_supports($post->post_type, 'page-attributes')) {
            $revisionary->handle_template( $request['template'], $revision_id );
        }

		foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
            if ($archived_val = rvy_get_transient("_archive_{$meta_key}_{$post->ID}")) {
                switch ($meta_key) {
                    case '_thumbnail_id':
                        set_post_thumbnail($post->ID, $archived_val);
                        break;

                    case '_wp_page_template':
                        rvy_update_post_meta($post->ID, '_wp_page_template', $archived_val);
                        break;
                }
            }
        }
		
        //if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
        //if (isset($request['meta']) && post_type_supports($post->post_type, 'custom-fields')) {
        if (isset($request['meta'])) {
            $meta = new WP_REST_Post_Meta_Fields( $revisionary->rest->post_type );

            $meta_update = $meta->update_value( $request['meta'], $revision_id );

            if ( is_wp_error( $meta_update ) ) {
                return $meta_update;
            }
        }

        // prevent these selections from updating published post
        foreach(array('meta', 'featured_media', 'template', 'format', 'sticky') as $key) {
            $request[$key] = '';
        }

        // update revision with terms selections, prevent update of published post
        $taxonomies = wp_list_filter( get_object_taxonomies( $revisionary->rest->post_type, 'objects' ), array( 'show_in_rest' => true ) );

        foreach ( $taxonomies as $taxonomy ) {
            $base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

            if ( ! isset( $request[ $base ] ) ) {
                continue;
            }

            $result = wp_set_object_terms( $revision_id, $request[ $base ], $taxonomy->name );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            unset($request[$base]);
        }
    }
}
