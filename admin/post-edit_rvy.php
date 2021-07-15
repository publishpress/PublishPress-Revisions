<?php

class RvyPostEdit {
    function __construct() {
        add_action('the_post', array($this, 'limitRevisionEditorUI'));

        add_action('add_meta_boxes', [$this, 'act_replace_publish_metabox'], 10, 2);

        // deal with case where another plugin replaced publish metabox
        add_filter('preview_post_link', [$this, 'fltPreviewLink'], 10, 2);
        add_filter('presspermit_preview_post_label', [$this, 'fltPreviewLabel']);
        add_filter('presspermit_preview_post_title', [$this, 'fltPreviewTitle']);
        add_action('post_submitbox_misc_actions', [$this, 'actSubmitMetaboxActions']);
		add_action('post_submitbox_start', [$this, 'actSubmitBoxStart']);

        add_action('admin_head', array($this, 'act_admin_head') );

        add_action('post_submitbox_misc_actions', array($this, 'act_post_submit_revisions_links'), 5);

        add_filter('post_updated_messages', [$this, 'fltPostUpdatedMessage']);

        add_filter('user_has_cap', [$this, 'fltAllowBrowseRevisionsLink'], 50, 3);

        add_filter('revisionary_apply_revision_allowance', [$this, 'fltRevisionAllowance'], 5, 2);

        add_action('all_admin_notices', [$this, 'actRevisionExistsNotice']);

        add_action('admin_head', [$this, 'actAdminBarPreventPostClobber'], 5);
    }

    function actRevisionExistsNotice() {
        global $post, $current_user, $wpdb;

        if (empty($post) || agp_user_can('edit_post', $post->ID, '', ['skip_revision_allowance' => true])) {
            return;
        }

        if ($revision_id = revisionary()->getUserRevision($post->ID)) {
            $url = admin_url('post.php') . "?post=$revision_id&action=edit";
            $type_obj = get_post_type_object($post->post_type);
            $type_label = (!empty($type_obj) && !empty($type_obj->labels->singular_name)) ? $type_obj->labels->singular_name : 'post';
            $message = sprintf(__('You have already submitted a revision for this %s. %sEdit the revision%s.', 'revisionary'), $type_label, "<a href='$url'>", '</a>');
            echo "<div id='message' class='notice notice-warning' style='color:black'>" . $message . '</div>';
        }
    }

    function fltPostUpdatedMessage($messages) {
        global $post;

        if (rvy_is_revision_status($post->post_status)) {
            if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
                $preview_url = rvy_preview_url($post);
                $preview_msg = sprintf(__('Revision updated. %sView Preview%s', 'revisionary'), "<a href='$preview_url'>", '</a>');
            } else {
                $preview_msg = __('Revision updated.', 'revisionary');
            }

            $messages['post'][1] = $preview_msg;
            $messages['page'][1] = $preview_msg;
            $messages[$post->post_type][1] = $preview_msg;
        }
        
        return $messages;
    }

    function fltAllowBrowseRevisionsLink($wp_blogcaps, $reqd_caps, $args) {
        if (!empty($args[0]) && ('edit_post' == $args[0]) && !empty($args[2])) {
            if ($_post = get_post($args[2])) {
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

    function actAdminBarPreventPostClobber() {
        global $post;

        // prevent PHP Notice from Multiple Authors code:
        // Notice: Trying to get property of non-object in F:\www\wp50\wp-content\plugins\publishpress-multiple-authors\core\Classes\Utils.php on line 309
        // @todo: address within MA
        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && !empty($_REQUEST['post'])) {
            $post = get_post((int) $_REQUEST['post']);
        }
    }

    function act_admin_head() {
        global $post, $current_user;

        if (!empty($post) && rvy_is_revision_status($post->post_status)):
            $type_obj = get_post_type_object($post->post_type);

            $view_link = rvy_preview_url($post);

            $can_publish = agp_user_can('edit_post', rvy_post_id($post->ID), '', ['skip_revision_allowance' => true]);

            if ($type_obj && empty($type_obj->public)) {
                $view_link = '';
                $view_caption = '';
                $view_title = '';
            } elseif ($can_publish) {
                $view_caption = ('future-revision' == $post->post_status) ? __('View / Publish', 'revisionary') : __('View / Approve', 'revisionary');
                $view_title = __('View / moderate saved revision', 'revisionary');
            } else {
                $view_caption = __('View');
                $view_title = __('View saved revision', 'revisionary');
            }

            $preview_caption = __('View');
            $preview_title = __('View unsaved changes', 'revisionary');
        ?>
    <script type="text/javascript">
    /* <![CDATA[ */
    jQuery(document).ready( function($) {
        $('h1.wp-heading-inline').html('<?php printf(__('Edit %s Revision', 'revisionary'), $type_obj->labels->singular_name);?>');

        $(document).on('click', '#post-body-content *, #wp-content-editor-container *, #tinymce *', function() {
            $('div.rvy-revision-approve').hide();
        });

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

        // Hide the Approve button if editor window is clicked into
        add_filter('tiny_mce_before_init', function($initArray, $editor_id = '') {
                //if (empty($initArray['init_instance_callback'])) {
                    $initArray['init_instance_callback'] = "function (ed) {
                        ed.on('click', function (e) {
                            var revApprove = document.getElementById('rvy_revision_approve');

                            if (revApprove != null) {
                                revApprove.style.display = 'none';
                            }

                            var revPreview = document.getElementById('revision-preview');

                            if (revPreview != null) {
                                revPreview.style.display = 'none';
                            }

                            var postPreview = document.getElementById('post-preview');

                            if (postPreview != null) {
                                postPreview.style.display = 'block';
                            }

                            var revCompare = document.getElementById('rvy_compare_button');

                            if (revCompare != null) {
                                revCompare.style.display = 'none';
                            }
                        });
                    }";
                    
                    return $initArray;
            }, 10, 2
        );

        delete_post_meta( $post->ID, "_save_as_revision_{$current_user->ID}" );
        update_postmeta_cache($post->ID);
    }

    function limitRevisionEditorUI() {
        global $post;
        
        if (!rvy_is_revision_status($post->post_status)) {
            return;
        }

        remove_post_type_support($post->post_type, 'author');
        remove_post_type_support($post->post_type, 'custom-fields'); // todo: filter post_id in query
    }

    public function post_submit_meta_box($post, $args = [])
    {
        require_once(dirname(__FILE__) . '/PostEditSubmitMetabox.php');
        RvyPostEditSubmitMetabox::post_submit_meta_box($post, $args);
    }

    public function actSubmitboxStart() {
        global $post;

        if (!$type_obj = get_post_type_object($post->post_type)) {
            return;
        }

        $can_publish = agp_user_can('edit_post', rvy_post_id($post->ID), '', ['skip_revision_allowance' => true]);

        if ($can_publish && rvy_is_revision_status($post->post_status)):?>
            <?php
            $redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect=" . esc_url($_REQUEST['rvy_redirect']) : '';
            $published_post_id = rvy_post_id($post->ID);

            if (in_array($post->post_status, ['pending-revision'])) {
                $approval_url = wp_nonce_url( admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=approve$redirect_arg"), "approve-post_$published_post_id|{$post->ID}" );
            
            } elseif (in_array($post->post_status, ['future-revision'])) {
                $approval_url = wp_nonce_url( admin_url("admin.php?page=rvy-revisions&amp;revision={$post->ID}&amp;action=publish$redirect_arg"), "publish-post_$published_post_id|{$post->ID}" );
            }
            ?>
            <div id="rvy_revision_approve" class="rvy-revision-approve" style="float:right"><a href="<?php echo $approval_url;?>" title="<?php echo esc_attr(__('Approve saved changes', 'revisionary'));?>"><?php _e('Approve', 'revisionary');?></a></div>
        <?php endif;
    }

    public function actSubmitMetaboxActions() {
        global $post;

        if (rvy_is_revision_status($post->post_status)) :
            $compare_link = admin_url("revision.php?revision=$post->ID");
            $compare_button = _x('Compare', 'revisions', 'revisionary');
            $compare_title = __('Compare this revision to published copy, or to other revisions', 'revisionary');
            ?>

            <a id="rvy_compare_button" class="preview button" href="<?php echo $compare_link; ?>" target="_blank" id="revision-compare"
            tabindex="4" title="<?php echo esc_attr($compare_title);?>"><?php echo $compare_button; ?></a>
        <?php endif;
    }

    public function fltPreviewLink($url, $_post = false) {
        global $post, $revisionary;

        if (!empty($_REQUEST['wp-preview']) && !empty($_post) && !empty($revisionary->last_autosave_id[$_post->ID])) {
            if (defined('REVISIONARY_PREVIEW_LEGACY_ARGS')) {
            	$url = remove_query_arg('_thumbnail_id', $url);
            	$url = add_query_arg('_thumbnail_id', $revisionary->last_autosave_id[$_post->ID], $url);
            }
        } elseif ($post && rvy_is_revision_status($post->post_status)) {
            $type_obj = get_post_type_object($post->post_type);

            if ($type_obj && !empty($type_obj->public)) {
            	$url = rvy_preview_url($post);
        	}
        }

        return $url;
    }

    public function fltPreviewLabel($preview_caption) {
        global $post;

        $type_obj = get_post_type_object($post->post_type);

        if ($type_obj && empty($type_obj->public)) {
            return $preview_caption;
        }

        $can_publish = agp_user_can('edit_post', rvy_post_id($post->ID), '', ['skip_revision_allowance' => true]);
        
        if ($can_publish) {
            $preview_caption = ('future-revision' == $post->post_status) ? __('View / Publish', 'revisionary') : __('View / Approve', 'revisionary');
        } elseif ($type_obj && !empty($type_obj->public)) {
            $preview_caption = __('View');
        }

        return $preview_caption;
    }

    public function fltPreviewTitle($preview_title) {
        global $post;

        $type_obj = get_post_type_object($post->post_type);

        if ($type_obj && empty($type_obj->public)) {
            return $preview_title;
        }

        $can_publish = agp_user_can('edit_post', rvy_post_id($post->ID), '', ['skip_revision_allowance' => true]);
        
        if ($can_publish) {
            $preview_title = __('View / moderate saved revision', 'revisionary');
        } elseif ($type_obj && !empty($type_obj->public)) {
            $preview_title = __('View saved revision', 'revisionary');
        }

        return $preview_title;
    }

    public function act_replace_publish_metabox($post_type, $post)
    {
        global $wp_meta_boxes;

        if (!rvy_is_revision_status($post->post_status)) {
            return;
        }

        if ('attachment' != $post_type) {
            if (!empty($wp_meta_boxes[$post_type]['side']['core']['submitdiv'])) {
                $wp_meta_boxes[$post_type]['side']['core']['submitdiv']['callback'] = [$this, 'post_submit_meta_box'];
            }
        }
    }

    function act_post_submit_revisions_links() {
        global $post;
        
        if (rvy_is_revision_status($post->post_status)) {
            return;
        }
        
        if (!$type_obj = get_post_type_object( $post->post_type )) {
            return;
        }

        if (!agp_user_can('edit_post', $post->ID, '', ['skip_revision_allowance' => true])) {
            return;
        }

        if (rvy_get_option('scheduled_revisions')) {
	        if ($_revisions = rvy_get_post_revisions($post->ID, 'future-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
	            $status_obj = get_post_status_object('future-revision');
	            $caption = sprintf(__('%sScheduled Revisions: %s', 'revisionary'), '<span class="dashicons dashicons-clock"></span>&nbsp;', '<b>' . count($_revisions) . '</b>');
	            
	            $num_revisions = count($_revisions);
	            //$last_revision = array_pop($_revisions);
	            //$url = admin_url("revision.php?revision=$last_revision->ID");   // @todo: fix i8n
	            $url = admin_url("revision.php?post_id=$post->ID&revision=future-revision");
	            ?>
	            <div class="misc-pub-section">
	            <?php
	            echo $caption;
	            ?>
	            <a class="hide-if-no-js"
                    href="<?php echo esc_url($url); ?>" target="_revision_diff"><?php _ex('Compare', 'revisions', 'revisionary'); ?></a>
	            </div>
	            <?php
	        }
        }

        if (rvy_get_option('pending_revisions')) {
	        if ($_revisions = rvy_get_post_revisions($post->ID, 'pending-revision', ['orderby' => 'ID', 'order' => 'ASC'])) {
	            $status_obj = get_post_status_object('pending-revision');
	            $caption = sprintf(__('%sPending Revisions: %s', 'revisionary'), '<span class="dashicons dashicons-edit"></span>&nbsp;', '<b>' . count($_revisions) . '</b>');
	            
	            $num_revisions = count($_revisions);
	            //$last_revision = array_pop($_revisions);
	            //$url = admin_url("revision.php?revision=$last_revision->ID");   // @todo: fix i8n
	            $url = admin_url("revision.php?post_id=$post->ID&revision=pending-revision");
	            ?>
	            <div class="misc-pub-section">
	            <?php
	            echo $caption;
	            ?>
	            <a class="hide-if-no-js"
                    href="<?php echo esc_url($url); ?>" target="_revision_diff"><?php _ex('Compare', 'revisions', 'revisionary'); ?></a>
	            </div>
	            <?php
	           }
	        }
    	}
}