<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Gutenberg Block Editor support
//
// This script executes on the 'init' action if is_admin() and $pagenow is 'post-new.php' or 'post.php' and the block editor is active.
//

add_action( 'enqueue_block_editor_assets', array( 'RVY_PostBlockEditUI', 'act_object_guten_scripts' ) );

class RVY_PostBlockEditUI {
	public static function act_object_guten_scripts() {
        global $current_user;
        
        if ( ! $post_id = rvy_detect_post_id() ) {
            return;
        }

        $post_type = rvy_detect_post_type();

		if ( ! $type_obj = get_post_type_object( $post_type ) ) {
            return;
        }

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
		
		if ( agp_user_can( $type_obj->cap->edit_post, $post_id, '', array( 'skip_revision_allowance' => true ) ) ) {
            wp_enqueue_script( 'rvy_object_edit', RVY_URLPATH . "/admin/rvy_post-block-edit{$suffix}.js", array('jquery', 'jquery-form'), RVY_VERSION, true );

            // for logged user who can fully edit a published post, clarify the meaning of setting future publish date
            // @todo  ?>
            <!--
            <script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready( function($) {
                postL10n.schedule = "<?php _e('Schedule Revision', 'revisionary' )?>";
            });
            /* ]]> */
            </script>
            -->
            <?php
            $args = array();

            $status = 'future';
            $args = array( 
                'redirectURLscheduled' => admin_url("edit.php?post_type={$post_type}&revision_submitted={$status}&post_id={$post_id}"),
                'userID' => $current_user->ID,
                'ScheduleCaption' => __('Schedule Revision', 'revisionary'),
                'UpdateCaption' => __('Update'),
            );

            // clear scheduled revision redirect flag
            delete_post_meta( $post_id, "_new_scheduled_revision_{$current_user->ID}" );
            
        } else {
            //div.editor-post-publish-panel button.editor-post-publish-button
            wp_enqueue_script( 'rvy_object_edit', RVY_URLPATH . "/admin/rvy_post-block-edit-revisor{$suffix}.js", array('jquery', 'jquery-form'), RVY_VERSION, true );
            
            $status = 'pending';
            $args = array(
                'publish' =>    __('Submit Revision'), 
                'saveAs' =>     __('Submit Revision'), 
                'prePublish' => __( 'Workflow&hellip;', 'revisionary' ),
                'redirectURL' => admin_url("edit.php?post_type={$post_type}&revision_submitted={$status}&post_id={$post_id}"),
            );  
        }

        wp_localize_script( 'rvy_object_edit', 'rvyObjEdit', $args );
    }
} // end class
