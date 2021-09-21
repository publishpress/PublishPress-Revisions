<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Gutenberg Block Editor support
//
// This script executes on the 'init' action if is_admin() and $pagenow is 'post-new.php' or 'post.php' and the block editor is active.
//

if ($post_id = rvy_detect_post_id()) {
    // PublishPress Custom Status module is not relevant to Edit Revision screen, conflicts with Revisions scripts
    if (rvy_in_revision_workflow($post_id)) {
        add_filter(
            'pp_module_dirs', 
            function($pp_modules) {
                unset($pp_modules['custom-status']);
                return $pp_modules;
            }
        );
    }
}

add_action( 'enqueue_block_editor_assets', ['RVY_PostBlockEditUI', 'disablePublishPressStatusesScripts'], 1);
add_action( 'enqueue_block_editor_assets', array( 'RVY_PostBlockEditUI', 'act_object_guten_scripts' ) );

class RVY_PostBlockEditUI {
	public static function disablePublishPressStatusesScripts() {
        global $publishpress;

        if ($post_id = rvy_detect_post_id()) {
            if (rvy_in_revision_workflow($post_id)) {
		        if (!empty($publishpress) && !empty($publishpress->custom_status->module->options)) {
		            $publishpress->custom_status->module->options->post_types = [];
		        }
		    }
        }
    }

	public static function act_object_guten_scripts() {
        global $current_user, $revisionary, $pagenow, $post, $wp_version;

        if ('post-new.php' == $pagenow) {
            return;
        }
        
        if (empty($post)) {
            return;
        }

		if (!$type_obj = get_post_type_object($post->post_type)) {
            return;
        }

        if (empty($revisionary->enabled_post_types[$post->post_type]) || !$revisionary->config_loaded) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        
        $do_pending_revisions = rvy_get_option('pending_revisions');
        $do_scheduled_revisions = rvy_get_option('scheduled_revisions');

        if (('revision' == $post->post_type) || rvy_in_revision_workflow($post)) {
            wp_enqueue_script( 'rvy_object_edit', RVY_URLPATH . "/admin/rvy_revision-block-edit{$suffix}.js", array('jquery', 'jquery-form'), PUBLISHPRESS_REVISIONS_VERSION, true );

            $args = \PublishPress\Revisions\PostEditorWorkflowUI::revisionLinkParams(compact('post', 'do_pending_revisions', 'do_scheduled_revisions'));

        } elseif (current_user_can('edit_post', $post->ID)) {
            $status_obj = get_post_status_object($post->post_status);

		    if (empty($status_obj->public) && empty($status_obj->private) && !rvy_get_option('pending_revision_unpublished')) {
                return;
            }

            wp_enqueue_script( 'rvy_object_edit', RVY_URLPATH . "/admin/rvy_post-block-edit{$suffix}.js", array('jquery', 'jquery-form'), PUBLISHPRESS_REVISIONS_VERSION, true );

            $args = \PublishPress\Revisions\PostEditorWorkflowUI::postLinkParams(compact('post', 'do_pending_revisions', 'do_scheduled_revisions'));
        }

        wp_localize_script( 'rvy_object_edit', 'rvyObjEdit', $args );
    }
} // end class
