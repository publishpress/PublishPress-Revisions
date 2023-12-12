<?php

/*
 * Post Edit: UI modifications for Classic Editor
 */
class RvyPostEdit {
    function __construct() {
        add_action('admin_head', array($this, 'act_admin_head') );

        // deal with case where another plugin replaced publish metabox
        add_filter('presspermit_preview_post_label', [$this, 'fltPreviewLabel']);
        add_filter('presspermit_preview_post_title', [$this, 'fltPreviewTitle']);

        add_action('post_submitbox_misc_actions', [$this, 'act_post_submit_revisions_links'], 5);
        add_action('post_submitbox_misc_actions', [$this, 'actPostSubmitboxActions'], 20);

        add_filter('user_has_cap', [$this, 'fltAllowBrowseRevisionsLink'], 50, 3);

        add_filter('revisionary_apply_revision_allowance', [$this, 'fltRevisionAllowance'], 5, 2);
    }

    function act_admin_head() {
        ?>
        <script type="text/javascript">
        /* <![CDATA[ */
        jQuery(document).ready( function($) {
            var rvyNowCaption = "<?php esc_html_e( 'Current Time', 'revisionary' );?>";
            $('#publishing-action #publish').show();
        });
        /* ]]> */
        </script>

        <?php
        global $post;

        if (!empty($post) && !rvy_is_supported_post_type($post->post_type)) {
            return;
        }

        wp_enqueue_script( 'rvy_post', RVY_URLPATH . "/admin/post-edit.js", array('jquery'), PUBLISHPRESS_REVISIONS_VERSION, true );

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

        $do_pending_revisions = rvy_get_option('pending_revisions');
        $do_scheduled_revisions = rvy_get_option('scheduled_revisions');

        if (('revision' == $post->post_type) || rvy_in_revision_workflow($post)) {
            wp_enqueue_script('rvy_object_edit', RVY_URLPATH . "/admin/rvy_revision-classic-edit{$suffix}.js", ['jquery', 'jquery-form'], PUBLISHPRESS_REVISIONS_VERSION, true);

            $args = \PublishPress\Revisions\PostEditorWorkflowUI::revisionLinkParams(compact('post', 'do_pending_revisions', 'do_scheduled_revisions'));

            $args['deleteCaption'] = (defined('RVY_DISCARD_CAPTION')) ? esc_html__( 'Discard Revision', 'revisionary' ) : esc_html__('Delete Revision', 'revisionary');

            $args['submissionDelay'] = (defined('PUBLISHPRESS_VERSION')) ? 2000 : 200;

            wp_localize_script( 'rvy_object_edit', 'rvyObjEdit', $args );

            if (defined('PUBLISHPRESS_VERSION')) {
                wp_dequeue_script('publishpress-custom_status');
                wp_dequeue_style('publishpress-custom_status');
            }

            $type_obj = get_post_type_object($post->post_type);

            if ($type_obj && !empty($type_obj->cap->edit_others_posts) && current_user_can($type_obj->cap->edit_others_posts)) {
                add_action('admin_print_footer_scripts', ['RvyPostEdit', 'author_ui'], 20);
            }

        } elseif (current_user_can('edit_post', $post->ID)) {
            if (rvy_post_revision_supported($post)) {
	            $status_obj = get_post_status_object($post->post_status);

			    if (('future' != $post->post_status) && (!empty($status_obj->public) || !empty($status_obj->private) || rvy_get_option('pending_revision_unpublished'))) {
	                wp_enqueue_script('rvy_object_edit', RVY_URLPATH . "/admin/rvy_post-classic-edit{$suffix}.js", ['jquery', 'jquery-form'], PUBLISHPRESS_REVISIONS_VERSION, true);

	                $args = \PublishPress\Revisions\PostEditorWorkflowUI::postLinkParams(compact('post', 'do_pending_revisions', 'do_scheduled_revisions'));
	                wp_localize_script( 'rvy_object_edit', 'rvyObjEdit', $args );
	            }
            } else {
                return;
            }
        }

        $args = array(
            'nowCaption' => esc_html__( 'Current Time', 'revisionary' ),
        );
        wp_localize_script( 'rvy_post', 'rvyPostEdit', $args );
	}

    public function fltPreviewLabel($preview_caption) {
        global $post;

        $type_obj = get_post_type_object($post->post_type);

        if ($type_obj && empty($type_obj->public)) {
            return $preview_caption;
        }

        $preview_caption = esc_html__('Preview');

        return $preview_caption;
    }

    public function fltPreviewTitle($preview_title) {
        global $post;

        if (!empty($post) && !empty($post->ID) && rvy_in_revision_workflow($post->ID)) {
            $type_obj = get_post_type_object($post->post_type);

            if ($type_obj && !empty($type_obj->public)) {
                $preview_title = esc_html__('View revision in progress', 'revisionary');
            }
        }

        return $preview_title;
    }

    function actPostSubmitboxActions($post) {
        ?>

        <div id="preview-action" style="float: right; padding: 5px 10px 10px 5px">
        <?php self::revision_preview_button($post); ?>
        </div>

        <?php
    }

    public static function revision_preview_button($post)
    {
        if (empty($post) || !is_object($post) || empty($post->ID)) {
            return;
        }

        if (!rvy_in_revision_workflow($post->ID)) {
            return;
        }

        if ($type_obj = get_post_type_object($post->post_type)) {
            if (empty($type_obj->public) && empty($type_obj->publicly_queryable)) {
                return;
            }
        }

        ?>
        <?php
        $preview_link = rvy_preview_url($post->ID);
        $preview_button = esc_html__('View Saved Revision');

        if (current_user_can('edit_post', rvy_post_id($post->ID))) {
            $preview_title = esc_html__('View / moderate saved revision', 'revisionary');

        } elseif ($type_obj && !empty($type_obj->public)) {
            $preview_title = esc_html__('View saved revision', 'revisionary');
        }

        ?>
        <a class="preview button" href="<?php echo esc_url($preview_link); ?>" target="revision-preview" id="revision-preview"
           tabindex="4" title="<?php echo esc_html($preview_title);?>"><?php echo esc_html($preview_button); ?></a>
        <?php
    }

    function act_post_submit_revisions_links() {
        global $post;

        // These links do not apply when editing a revision
        if (rvy_in_revision_workflow($post) || !current_user_can('edit_post', $post->ID) || !rvy_is_supported_post_type($post->post_type)) {
            return;
        }

        if (rvy_get_option('scheduled_revisions')) {
	        if ($_revisions = rvy_get_post_revisions($post->ID, 'future-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
	            ?>
	            <div class="misc-pub-section">
	            <?php
	            printf('%s' . esc_html(pp_revisions_status_label('future-revision', 'plural')) . ': %s', '<span class="dashicons dashicons-clock"></span>&nbsp;', '<b>' . esc_html(count($_revisions)) . '</b>');
	            ?>
	            <a class="hide-if-no-js"
                    href="<?php echo esc_url(admin_url("revision.php?post_id=$post->ID&revision=future-revision")); ?>" target="_revision_diff"><?php _ex('Compare', 'revisions', 'revisionary'); ?></a>
	            </div>
	            <?php
	        }
        }

        if (rvy_get_option('pending_revisions')) {
	        if ($_revisions = rvy_get_post_revisions($post->ID, 'pending-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
	            ?>
	            <div class="misc-pub-section">
	            <?php
	            printf('%s' . esc_html(pp_revisions_status_label('pending-revision', 'plural')) . ': %s', '<span class="dashicons dashicons-edit"></span>&nbsp;', '<b>' . esc_html(count($_revisions)) . '</b>');
	            ?>
	            <a class="hide-if-no-js"
                    href="<?php echo esc_url(admin_url("revision.php?post_id=$post->ID&revision=pending-revision")); ?>" target="_revision_diff"><?php _ex('Compare', 'revisions', 'revisionary'); ?></a>
	            </div>
	            <?php
	        }
	    }
    }

    function fltAllowBrowseRevisionsLink($wp_blogcaps, $reqd_caps, $args) {
        if (!empty($args[0]) && ('edit_post' == $args[0]) && !empty($args[2])) {
            if ($_post = get_post((int) $args[2])) {
                if ('revision' == $_post->post_type && current_user_can('edit_post', $_post->post_parent)) {
                    if (did_action('post_submitbox_minor_actions')) {
                        if (!did_action('post_submitbox_misc_actions')) {
                            $wp_blogcaps = array_merge($wp_blogcaps, array_fill_keys($reqd_caps, true));
                        } else {
                            remove_filter('user_has_cap', [$this, 'fltAllowBrowseRevisionsLink'], 50, 3);
                        }
                    }
                }
            }

        }

        return $wp_blogcaps;
    }

    function fltRevisionAllowance($allowance, $post_id) {
        // Ensure that revision "edit" link is not suppressed for the Revisions > Browse link
        if (did_action('post_submitbox_minor_actions') && !did_action('post_submitbox_misc_actions')) {
            $allowance = true;
        }

        return $allowance;
    }

    // @todo: merge with RVY_PostBlockEditUI::author_ui()
    public static function author_ui() {
        global $post;

        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
            return [];
        }

        if (!$type_obj = get_post_type_object($post->post_type)) {
            return [];
        }

        $published_post_id = rvy_post_id($post->ID);
        $published_author = get_post_field('post_author', $published_post_id);

        $author_selection = get_post_meta($post->ID, '_rvy_author_selection', true);

        $select_html = wp_dropdown_users(
            array(
                'capability'       => [$type_obj->cap->edit_posts],
                'name'             => 'rvy_post_author',
                'selected'         => ($author_selection) ? $author_selection : $published_author,
                'include_selected' => true,
                'show'             => 'display_name_with_login',
                'echo'             => false,
            )
        );

        $select_html = str_replace(["\n", "\r"], '', $select_html);
        ?>
		<script type="text/javascript">
        /* <![CDATA[ */
		jQuery(document).ready( function($) {
            //$(document).on('loaded-ui', 'div.rvy-submission-div', function() {
                $('div.misc-pub-section:first').after(
                    "<div class='misc-pub-section'><div class='rvy-author-selection'>"
                    + '<label>' + '<?php _e("Author", 'revisionary');?>&nbsp;</label>'
                    + '</div>'
                    + "<div class='rvy-author-selection'>"
                    + "<?php echo $select_html;?>"
                    + '</div></div>'
                );
            //});

            $(document).on('change', 'div.rvy-author-selection select', function(e) {
                var data = {'rvy_ajax_field': 'author_select', 'rvy_ajax_value': <?php echo $post->ID;?>, 'rvy_selection': $('div.rvy-author-selection select').val(), 'nc': Math.floor(Math.random() * 99999999)};

                $('div.rvy-author-selection select').attr('disabled', 'disabled');

                $.ajax({
                    url: rvyObjEdit.ajaxurl,
                    data: data,
                    dataType: "html",
                    success: revisionarySelectAuthorDone,
                    error: revisionarySelectAuthorError
                });
            });

            function revisionarySelectAuthorDone() {
                $('div.rvy-author-selection select').removeAttr('disabled');
            }

            function revisionarySelectAuthorError() {
                $('div.rvy-author-selection select').removeAttr('disabled');
            }
        });
		/* ]]> */
		</script>
		<?php
    }
}
