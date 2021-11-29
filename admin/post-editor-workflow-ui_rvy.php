<?php
namespace PublishPress\Revisions;

class PostEditorWorkflowUI {
    public static function revisionLinkParams($args = []) {
        $defaults = ['post' => false, 'do_pending_revisions' => true, 'do_scheduled_revisions' => true];
        $args = array_merge( $defaults, $args );
        foreach( array_keys($defaults) as $var ) { $$var = $args[$var]; }

        global $wp_version;

        if (empty($post)) {
            return [];
        }

        if (!$type_obj = get_post_type_object($post->post_type)) {
            return [];
        }

        $vars = [
            'postID' => $post->ID,
            'saveRevision' => pp_revisions_label('update_revision'),
            'scheduledRevisionsEnabled' => $do_scheduled_revisions,
            'multiPreviewActive' => version_compare($wp_version, '5.5-beta', '>='),
            'statusLabel' => __('Status', 'revisionary'),
            'ajaxurl' => rvy_admin_url(''),
            'currentStatus' => str_replace('-revision', '', $post->post_mime_type),
            'onApprovalCaption' => __('(on approval)', 'revisionary'),
        ];

        $vars['disableRecaption'] = is_plugin_active('gutenberg/gutenberg.php');

        if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
            $vars['viewURL'] = rvy_preview_url($post);
            $can_publish = current_user_can('edit_post', rvy_post_id($post->ID));

            if ($type_obj && empty($type_obj->public)) {
                $vars['viewURL']  = '';
                $vars['viewCaption'] = '';
                $vars['viewTitle'] = '';

            } elseif ($can_publish) {
                if (version_compare($wp_version, '5.5-beta', '>=')) {
                    $vars['viewCaption'] = __('Preview', 'revisionary');
                } else {
                    $vars['viewCaption'] = ('future-revision' == $post->post_mime_type) ? __('View / Publish', 'revisionary') : __('View / Approve', 'revisionary');
                }

                $vars['viewTitle'] =  __('View / Approve saved revision', 'revisionary');
            } else {
                $vars['viewCaption'] = version_compare($wp_version, '5.5-beta', '>=') ? __('Preview / Submit') :  __('View / Submit');
                $vars['viewTitle'] =  __('View / Submit saved revision', 'revisionary');
            }
        } else {
            $vars['viewURL']  = '';
            $vars['viewCaption'] = '';
            $vars['viewTitle'] =  '';
        }

        $vars['previewTitle'] = __('View unsaved changes', 'revisionary');

        $_revisions = wp_get_post_revisions($post->ID);
        if ($_revisions && count($_revisions) > 1) {
            $vars['revisionEdits'] = sprintf(_n('%s%s Revision Edit', '%s%s Revision Edits', count($_revisions), 'revisionary'), '<span class="dashicons dashicons-backup"></span>&nbsp;', count($_revisions));
        } else {
            $vars['revisionEdits'] = '';
        }

        $redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect=" . esc_url($_REQUEST['rvy_redirect']) : '';
        $published_post_id = rvy_post_id($post->ID);

        $draft_obj = get_post_status_object('draft-revision');
        $vars['draftStatusCaption'] = $draft_obj->label;

        $vars['draftAjaxField'] = (current_user_can('set_revision_pending-revision', $post->ID)) ? 'submit_revision' : '';
        $vars['draftErrorCaption'] = __('Revision Submission Error', 'revisionary');
        $vars['draftDeletionURL'] = get_delete_post_link($post->ID, '', false);

        if ($vars['draftAjaxField']) {
            $vars['draftActionCaption'] = pp_revisions_status_label('pending-revision', 'submit');
            $vars['draftActionURL'] = ''; // wp_nonce_url( rvy_admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=submit$redirect_arg"), "submit-post_$published_post_id|{$post->ID}" );
            $vars['draftInProcessCaption'] = pp_revisions_status_label('pending-revision', 'submitting');
            $vars['draftCompletedCaption'] = pp_revisions_status_label('pending-revision', 'submitted');
            $vars['draftCompletedLinkCaption'] = __('Preview', 'revisionary');
            $vars['draftCompletedURL'] = rvy_preview_url($post);
        } else {
            $vars['draftActionCaption'] = '';
        }

        $pending_obj = get_post_status_object('pending-revision');
        $vars['pendingStatusCaption'] = $pending_obj->label;

        $future_obj = get_post_status_object('future-revision');
        $vars['futureStatusCaption'] = $future_obj->label;

        if ($can_publish) {
            $vars['pendingActionCaption'] = pp_revisions_status_label('pending-revision', 'approve');
            $vars['pendingActionURL'] = wp_nonce_url( rvy_admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=approve$redirect_arg&amp;editor=1"), "approve-post_$published_post_id|{$post->ID}" );

            $vars['pendingInProcessCaption'] = pp_revisions_status_label('pending-revision', 'approving');

            $vars['futureActionCaption'] = pp_revisions_status_label('future-revision', 'publish');
            $vars['futureActionURL'] = wp_nonce_url( rvy_admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=publish$redirect_arg&amp;editor=1"), "publish-post_$published_post_id|{$post->ID}" );

            $vars['pendingDeletionURL'] = get_delete_post_link($post->ID, '', false);
            $vars['futureDeletionURL'] = $vars['pendingDeletionURL'];
        } else {
            $vars['pendingActionURL'] = '';
            $vars['futureActionURL'] = '';
            $vars['pendingDeletionURL'] = '';
            $vars['futureDeletionURL'] = '';
        }

        if (\PublishPress\Revisions\Utils::isBlockEditorActive()) {
            $vars['updateCaption'] =  __('Update Revision', 'revisionary');
        } else {
            if (!$vars['updateCaption'] = pp_revisions_status_label($post->post_mime_type, 'update')) {
                $vars['updateCaption'] = pp_revisions_label('update_revision');
            }
        }

        return $vars;
    }

    public static function postLinkParams($args = []) {
        $defaults = ['post' => false, 'do_pending_revisions' => true, 'do_scheduled_revisions' => true];
        $args = array_merge( $defaults, $args );
        foreach( array_keys($defaults) as $var ) { $$var = $args[$var]; }

        global $wp_version;

        if (empty($post)) {
            return [];
        }

        if (!$type_obj = get_post_type_object($post->post_type)) {
            return [];
        }

        $vars = ['postID' => $post->ID, 'currentStatus' => $post->post_status];

        if ($do_pending_revisions && $_revisions = rvy_get_post_revisions($post->ID, 'pending-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
            $status_obj = get_post_status_object('pending-revision');

            $status_label = (count($_revisions) <= 1) ? pp_revisions_status_label('pending-revision', 'name') : pp_revisions_status_label('pending-revision', 'plural');
            $vars['pendingRevisionsCaption'] = sprintf('<span class="dashicons dashicons-edit"></span>&nbsp;%s %s', count($_revisions), $status_label);

            $vars['pendingRevisionsURL'] = rvy_admin_url("revision.php?post_id=$post->ID&revision=pending-revision");   // @todo: fix i8n
        } else {
            $vars['pendingRevisionsURL'] = '';
        }

        if ($do_scheduled_revisions && $_revisions = rvy_get_post_revisions($post->ID, 'future-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
            $status_obj = get_post_status_object('future-revision');

            $status_label = (count($_revisions) <= 1) ? pp_revisions_status_label('future-revision', 'name') : pp_revisions_status_label('future-revision', 'plural');
            $vars['scheduledRevisionsCaption'] = sprintf('<span class="dashicons dashicons-clock"></span>&nbsp;%s %s', count($_revisions), $status_label);

            $vars['scheduledRevisionsURL'] = rvy_admin_url("revision.php?post_id=$post->ID&revision=future-revision");
        } else {
            $vars['scheduledRevisionsURL'] = '';
        }

        $redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect=" . esc_url($_REQUEST['rvy_redirect']) : '';
        $published_post_id = rvy_post_id($post->ID);

        if (rvy_get_option('pending_revisions') && current_user_can('copy_post', $post->ID)) {
            $vars = array_merge($vars, array(
                'actionCaption' => pp_revisions_status_label('draft-revision', 'submit'),
                'actionTitle' => esc_attr(sprintf(__('Create a %s of this post', 'revisionary'), strtolower(pp_revisions_status_label('draft-revision', 'basic')))),
                'actionDisabledTitle' => esc_attr(sprintf(__('Update post before creating %s.', 'revisionary'), strtolower(pp_revisions_status_label('draft-revision', 'basic')))),
                'creatingCaption' => pp_revisions_status_label('draft-revision', 'submitting'),
                'completedCaption' => pp_revisions_status_label('draft-revision', 'submitted'),
                'completedLinkCaption' => __('Preview', 'revisionary'),
                'completedURL' => rvy_nc_url( add_query_arg('get_new_revision', $post->ID, get_permalink($post->ID))),
                'errorCaption' => __('Error Creating Revision', 'revisionary'),
                'ajaxurl' => rvy_admin_url(''),
                'update' => __('Update', 'revisionary'),
                'postID' => $post->ID
            ));
        } else {
            $vars['actionCaption'] = '';
        }

        if (rvy_get_option('scheduled_revisions') && current_user_can($type_obj->cap->publish_posts)) {
            $published_statuses = array_merge(get_post_stati(['public' => true]), get_post_stati(['private' => true]));

            $vars = array_merge($vars, array(
                'publishedStatuses' => $published_statuses,
                'scheduleCaption' => pp_revisions_status_label('future-revision', 'submit'),
                'scheduleTitle' => '',
                'scheduleDisabledTitle' => esc_attr(sprintf(__('For custom field changes, edit a scheduled %s.', 'revisionary'), strtolower(pp_revisions_status_label('draft-revision', 'basic')))),
                'scheduledCaption' => pp_revisions_status_label('future-revision', 'submitted'),
                'scheduledLinkCaption' => __('Preview', 'revisionary'),
                'scheduledURL' => rvy_nc_url( add_query_arg('get_new_revision', $post->ID, get_permalink($post->ID))),
                'update' => __('Update', 'revisionary'),
            ));

            if (empty($vars['actionCaption'])) {
                $vars = array_merge($vars, array(
                    'actionCaption' => '',
                    'ajaxurl' => rvy_admin_url(''),
                ));
            }
        }

        return $vars;
    }
}
