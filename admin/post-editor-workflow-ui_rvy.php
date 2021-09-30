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
            'saveRevision' => __('Update Revision'),
            'scheduledRevisionsEnabled' => $do_scheduled_revisions,
            'multiPreviewActive' => version_compare($wp_version, '5.5-beta', '>='),
            'statusLabel' => __('Status', 'revisionary'),
            'ajaxurl' => rvy_admin_url(''),
            'currentStatus' => str_replace('-revision', '', $post->post_mime_type),
            'onApprovalCaption' => __('(on approval)', 'revisionary'),
        ];

        if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
            $vars['viewURL'] = rvy_preview_url($post);
            $can_publish = current_user_can('edit_post', rvy_post_id($post->ID));

            if ($type_obj && empty($type_obj->public)) {
                $vars['viewURL']  = '';
                $vars['viewCaption'] = '';
                $vars['viewTitle'] = '';

            } elseif ($can_publish) {
                if (version_compare($wp_version, '5.5-beta', '>=')) {
                    $vars['viewCaption'] = ('future-revision' == $post->post_mime_type) ? __('Preview / Publish', 'revisionary') : __('Preview / Approve', 'revisionary');
                } else {
                    $vars['viewCaption'] = ('future-revision' == $post->post_mime_type) ? __('View / Publish', 'revisionary') : __('View / Approve', 'revisionary');
                }

                $vars['viewTitle'] =  __('View / Approve saved changes', 'revisionary');
            } else {
                $vars['viewCaption'] = version_compare($wp_version, '5.5-beta', '>=') ? __('Preview / Submit') :  __('View / Submit');
                $vars['viewTitle'] =  __('View / Submit saved changes', 'revisionary');
            }
        } else {
            $vars['viewURL']  = '';
            $vars['viewCaption'] = '';
            $vars['viewTitle'] =  '';
        }

        $vars['previewTitle'] = __('View unsaved changes', 'revisionary');

        $_revisions = wp_get_post_revisions($post->ID);
        if ($_revisions && count($_revisions) > 1) {
            $vars['revisionEdits'] = sprintf(_n('<span class="dashicons dashicons-backup"></span>&nbsp;%s Revision Edit', '<span class="dashicons dashicons-backup"></span>&nbsp;%s Revision Edits', count($_revisions), 'revisionary'), count($_revisions));
        } else {
            $vars['revisionEdits'] = '';
        }

        $redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect=" . esc_url($_REQUEST['rvy_redirect']) : '';
        $published_post_id = rvy_post_id($post->ID);

        $draft_obj = get_post_status_object('draft-revision');
        $vars['draftStatusCaption'] = $draft_obj->label;

        $vars['draftAjaxField'] = (current_user_can('set_revision_pending-revision', $post->ID)) ? 'submit_revision' : '';
        $vars['draftErrorCaption'] = __('Error Submitting Changes', 'revisionary');
        $vars['draftDeletionURL'] = get_delete_post_link($post->ID, '', false);

        if ($vars['draftAjaxField']) {
            $vars['draftActionCaption'] = __('Submit Change Request', 'revisionary');
            $vars['draftActionURL'] = ''; // wp_nonce_url( rvy_admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=submit$redirect_arg"), "submit-post_$published_post_id|{$post->ID}" );
            $vars['draftCompletedCaption'] = __('Changes Submitted.', 'revisionary');
            $vars['draftCompletedLinkCaption'] = __('view', 'revisionary');
            $vars['draftCompletedURL'] = rvy_preview_url($post);
        } else {
            $vars['draftActionCaption'] = '';
        }

        $pending_obj = get_post_status_object('pending-revision');
        $vars['pendingStatusCaption'] = $pending_obj->label;

        $future_obj = get_post_status_object('future-revision');
        $vars['futureStatusCaption'] = $future_obj->label;

        if ($can_publish) {
            $vars['pendingActionCaption'] = __('Approve Changes', 'revisionary');
            $vars['pendingActionURL'] = wp_nonce_url( rvy_admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=approve$redirect_arg&amp;editor=1"), "approve-post_$published_post_id|{$post->ID}" );

            $vars['futureActionCaption'] = __('Publish Changes', 'revisionary');
            $vars['futureActionURL'] = wp_nonce_url( rvy_admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=publish$redirect_arg&amp;editor=1"), "publish-post_$published_post_id|{$post->ID}" );

            $vars['pendingDeletionURL'] = get_delete_post_link($post->ID, '', false);
            $vars['futureDeletionURL'] = $vars['pendingDeletionURL'];
        } else {
            $vars['pendingActionURL'] = '';
            $vars['futureActionURL'] = '';
            $vars['pendingDeletionURL'] = '';
            $vars['futureDeletionURL'] = '';
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
            $vars['pendingRevisionsCaption'] = sprintf(_n('<span class="dashicons dashicons-edit"></span>&nbsp;%s Change Request', '<span class="dashicons dashicons-edit"></span>&nbsp;%s Change Requests', count($_revisions), 'revisionary'), count($_revisions));

            $vars['pendingRevisionsURL'] = rvy_admin_url("revision.php?post_id=$post->ID&revision=pending-revision");   // @todo: fix i8n
        } else {
            $vars['pendingRevisionsURL'] = '';
        }

        if ($do_scheduled_revisions && $_revisions = rvy_get_post_revisions($post->ID, 'future-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
            $status_obj = get_post_status_object('future-revision');
            $vars['scheduledRevisionsCaption'] = sprintf(_n('<span class="dashicons dashicons-clock"></span>&nbsp;%s Scheduled Change', '<span class="dashicons dashicons-clock"></span>&nbsp;%s Scheduled Changes', count($_revisions), 'revisionary'), count($_revisions));

            $vars['scheduledRevisionsURL'] = rvy_admin_url("revision.php?post_id=$post->ID&revision=future-revision");
        } else {
            $vars['scheduledRevisionsURL'] = '';
        }

        $redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect=" . esc_url($_REQUEST['rvy_redirect']) : '';
        $published_post_id = rvy_post_id($post->ID);

        if (current_user_can('copy_post', $post->ID)) {
            $vars = array_merge($vars, array(
                'actionCaption' => __('Create Working Copy', 'revisionary'),
                'actionTitle' => esc_attr(__('Create a working copy of this post', 'revisionary')),
                'actionDisabledTitle' => esc_attr(__('Update post before creating copy.', 'revisionary')),
                'completedCaption' => __('Working Copy Ready.', 'revisionary'),
                'completedLinkCaption' => __('view', 'revisionary'),
                'completedURL' => rvy_nc_url( add_query_arg('get_new_revision', $post->ID, get_permalink($post->ID))),
                'errorCaption' => __('Error Creating Copy', 'revisionary'),
                'ajaxurl' => rvy_admin_url(''),
                'postID' => $post->ID
            ));
        } else {
            $vars['actionCaption'] = '';
        }

        if (current_user_can($type_obj->cap->publish_posts)) {
            $published_statuses = array_merge(get_post_stati(['public' => true]), get_post_stati(['private' => true]));

            $vars = array_merge($vars, array(
                'publishedStatuses' => $published_statuses,
                'scheduleCaption' => __('Schedule Changes', 'revisionary'),
                'scheduleTitle' => '',
                'scheduleDisabledTitle' => esc_attr(__('For custom field changes, edit a scheduled copy.', 'revisionary')),
                'scheduledCaption' => __('Changes are Scheduled.', 'revisionary'),
                'scheduledLinkCaption' => __('view', 'revisionary'),
                'scheduledURL' => rvy_nc_url( add_query_arg('get_new_revision', $post->ID, get_permalink($post->ID))),
            ));
        }

        return $vars;
    }
}
