<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
    die();

/*
 * Revision Edit: UI modifications for Classic Editor
 */
class RevisionaryEditRevisionClassicUI {
	function __construct () {
		add_action('admin_head', [$this, 'add_js']);
		add_action('admin_head', [$this, 'hide_admin_divs']);

		add_filter('post_updated_messages', [$this, 'fltPostUpdatedMessage']);

		add_action('add_meta_boxes', [$this, 'act_replace_publish_metabox'], 10, 2);
		add_action('post_submitbox_misc_actions', [$this, 'actSubmitMetaboxActions']);

		add_filter('presspermit_editor_ui_status', [$this, 'fltEditorUIstatus'], 10, 3);
		add_filter('presspermit_post_editor_immediate_caption', [$this, 'fltImmediateCaption'], 10, 2);
	}

	function add_js() {
		global $post;

		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
			if (typeof(postL10n) != 'undefined') {
				postL10n.saveDraft = "<?php _e('Update Revision', 'revisionary' )?>";
			}

            var rvyNowCaption = "<?php _e( 'Current Time', 'revisionary' );?>";
            $('#publishing-action #publish').show();
        });
        /* ]]> */
        </script>

		<?php if ('draft-revision' == $post->post_mime_type):?>
			<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready( function($) {
				$('#publish').val("<?php _e('Submit Changes', 'revisionary' )?>");

				if (typeof(postL10n) != 'undefined') {
					postL10n.update = "<?php _e('Submit Changes', 'revisionary' )?>";
					postL10n.schedule = "<?php _e('Submit Change Schedule', 'revisionary' )?>";
				}

				setInterval(
					function() {
						if ($('#publish').val() != "<?php _e('Submit Changes', 'revisionary' )?>") {
							$('#publish').val("<?php _e('Submit Changes', 'revisionary' )?>");
						}
					}
					, 200
				);
			});
			/* ]]> */
			</script>
    	<?php else:?>
			<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready( function($) {
				$('#publish').val("<?php _e('Update Revision', 'revisionary' )?>");

				if (typeof(postL10n) != 'undefined') {
					postL10n.update = "<?php _e('Update Revision', 'revisionary' )?>";

				}

				setInterval(
					function() {
						if ($('#publish').val() != "<?php _e('Update Revision', 'revisionary' )?>") {
							$('#publish').val("<?php _e('Update Revision', 'revisionary' )?>");
						}
					}
					, 200
				);
			});
			/* ]]> */
			</script>
		<?php endif;

		global $post, $current_user;

		if (!empty($post)):
			$type_obj = get_post_type_object($post->post_type);

			$view_link = rvy_preview_url($post);

			$can_publish = current_user_can('edit_post', rvy_post_id($post->ID));

			if ($type_obj && empty($type_obj->public)) {
				$view_link = '';
				$view_caption = '';
				$view_title = '';
			} elseif ($can_publish) {
				$view_caption = __('Preview');
				$view_title = __('View / moderate saved revision', 'revisionary');
			} else {
				$view_caption = __('Preview');
				$view_title = __('View saved revision', 'revisionary');
			}

			$preview_caption = __('View');
			$preview_title = __('View unsaved changes', 'revisionary');
			?>

			<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready( function($) {
				$('h1.wp-heading-inline').html('<?php printf(__('Edit %s Revision', 'revisionary'), $type_obj->labels->singular_name);?>');

				<?php if ($view_link) :?>
					// remove preview event handlers
					original = $('#minor-publishing-actions #post-preview');
					$(original).after(original.clone().attr('href', '<?php echo $view_link;?>').attr('target', '_blank').attr('id', 'revision-preview'));
					$(original).hide();
				<?php endif;?>

				<?php if ($view_caption) :?>
					$('#minor-publishing-actions #revision-preview').html('<?php echo $view_caption;?>');
				<?php endif;?>

				<?php if ($preview_title) :?>
					$('#minor-publishing-actions #post-preview').html('<?php echo $preview_caption;?>');
					$('#minor-publishing-actions #post-preview').attr('title', '<?php echo $preview_title;?>');
				<?php endif;?>

			});
			/* ]]> */
			</script>
		<?php endif;
	}

	function hide_admin_divs() {
		// Hide unrevisionable elements if editing for revisions, regardless of Limited Editing Element settings
		//
		if ( rvy_get_option( 'pending_revisions' ) ) {
			global $post;

			$object_type = (!empty($post->post_type)) ? $post->post_type : awp_post_type_from_uri();

			$unrevisable_css_ids = apply_filters('rvy_hidden_meta_boxes', ['authordiv', 'visibility', 'postcustom', 'pagecustom']);

			if (rvy_in_revision_workflow($post)) {
				$unrevisable_css_ids = array_merge($unrevisable_css_ids, ['publish', 'slugdiv', 'edit-slug-box']);
			}

			echo( "\n<style type='text/css'>\n<!--\n" );

			foreach ( $unrevisable_css_ids as $id ) {
				// thanks to piemanek for tip on using remove_meta_box for any core admin div
				remove_meta_box($id, $object_type, 'normal');
				remove_meta_box($id, $object_type, 'advanced');

				// also hide via CSS in case the element is not a metabox
				echo "#$id { display: none !important; }\n";  // this line adapted from Clutter Free plugin by Mark Jaquith
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
			$caption = __('Publish <b>on approval</b>', 'revisionary');
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
		$compare_title = __('Compare this revision to published copy, or to other revisions', 'revisionary');
		?>

		<a id="rvy_compare_button" class="preview button" href="<?php echo $compare_link; ?>" target="_blank" id="revision-compare"
		tabindex="4" title="<?php echo esc_attr($compare_title);?>"><?php echo $compare_button; ?></a>
        <?php
    }

	function fltPostUpdatedMessage($messages) {
		global $post;

		if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
			$preview_url = rvy_preview_url($post);
			$preview_msg = sprintf(__('Revision updated. %sView Preview%s', 'revisionary'), "<a href='$preview_url'>", '</a>');
		} else {
			$preview_msg = __('Revision updated.', 'revisionary');
		}

		$messages['post'][1] = $preview_msg;
		$messages['page'][1] = $preview_msg;
		$messages[$post->post_type][1] = $preview_msg;

        return $messages;
	}
}
