<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename(esc_url_raw($_SERVER['SCRIPT_FILENAME'])) )
    die();

/*
 * Revision Edit: UI modifications for Classic Editor
 */
class RevisionaryEditRevisionClassicUI {
	function __construct () {
		add_action('admin_head', [$this, 'hide_admin_divs']);

		add_filter('post_updated_messages', [$this, 'fltPostUpdatedMessage']);

		add_action('add_meta_boxes', [$this, 'act_replace_publish_metabox'], 10, 2);
		add_action('post_submitbox_misc_actions', [$this, 'actSubmitMetaboxActions']);

		add_filter('presspermit_editor_ui_status', [$this, 'fltEditorUIstatus'], 10, 3);
		add_filter('presspermit_post_editor_immediate_caption', [$this, 'fltImmediateCaption'], 10, 2);
	}

	function hide_admin_divs() {
		// Hide unrevisionable elements if editing for revisions, regardless of Limited Editing Element settings
		//
		if ( rvy_get_option( 'pending_revisions' ) ) {
			global $post;

			$object_type = (!empty($post->post_type)) ? $post->post_type : awp_post_type_from_uri();

			$unrevisable_css_ids = apply_filters('rvy_hidden_meta_boxes', ['authordiv', 'visibility']);

			if (rvy_in_revision_workflow($post)) {
				$unrevisable_css_ids = array_merge($unrevisable_css_ids, ['publish', 'slugdiv', 'edit-slug-box']);
			}

			echo( "\n<style type='text/css'>\n<!--\n" );

			foreach ( $unrevisable_css_ids as $id ) {
				// thanks to piemanek for tip on using remove_meta_box for any core admin div
				remove_meta_box($id, $object_type, 'normal');
				remove_meta_box($id, $object_type, 'advanced');

				// also hide via CSS in case the element is not a metabox
				echo "#" . esc_attr($id) . "{ display: none !important; }\n";  // this line adapted from Clutter Free plugin by Mark Jaquith
			}

			echo "-->\n</style>\n";

			// display the current status, but hide edit link
			echo "\n<style type='text/css'>\n<!--\n.edit-post-status { display: none !important; }\n-->\n</style>\n";  // this line adapted from Clutter Free plugin by Mark Jaquith
		}
	}

	public function fltEditorUIstatus($status, $post, $args) {
		if (rvy_in_revision_workflow($post)) {
			$status = $post->post_mime_type;
		}

		return $status;
	}

	public function fltImmediateCaption($caption, $post) {
		if (rvy_in_revision_workflow($post)) {
			$caption = esc_html__('Publish <b>on approval</b>', 'revisionary');
		}

		return $caption;
	}

	public function post_submit_meta_box($post, $args = [])
    {
		if (rvy_is_revision_status($post->post_mime_type)) {
            require_once(dirname(__FILE__) . '/RevisionEditSubmitMetabox.php');
			RvyRevisionEditSubmitMetabox::post_submit_meta_box($post, $args);
        }
    }

	public function act_replace_publish_metabox($post_type, $post)
    {
        global $wp_meta_boxes;

        if ('attachment' != $post_type) {
            if (!empty($wp_meta_boxes[$post_type]['side']['core']['submitdiv'])) {
				$wp_meta_boxes[$post_type]['side']['core']['submitdiv']['callback'] = [$this, 'post_submit_meta_box'];
            }
        }
	}

    public function actSubmitMetaboxActions() {
        global $post;

		$compare_link = rvy_admin_url("revision.php?revision=$post->ID");
		$compare_button = _x('Compare', 'revisions', 'revisionary');
		$compare_title = esc_html__('Compare this revision to published copy, or to other revisions', 'revisionary');
		?>

		<a id="rvy_compare_button" class="preview button" href="<?php echo esc_url($compare_link); ?>" target="_blank" id="revision-compare"
		tabindex="4" title="<?php echo esc_attr($compare_title);?>" style="float:right"><?php echo esc_html($compare_button); ?></a>
        <?php
    }

	function fltPostUpdatedMessage($messages) {
		global $post;

		if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
			$preview_url = rvy_preview_url($post);
			$preview_msg = sprintf(esc_html__('Revision updated. %sView Preview%s', 'revisionary'), "<a href='$preview_url'>", '</a>');
		} else {
			$preview_msg = esc_html__('Revision updated.', 'revisionary');
		}

		$messages['post'][1] = $preview_msg;
		$messages['page'][1] = $preview_msg;
		$messages[$post->post_type][1] = $preview_msg;

        return $messages;
	}
}
