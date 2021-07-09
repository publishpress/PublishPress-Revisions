<?php
class RvyPostEditSubmitMetabox
{
    /**
     *  Classic Editor Post Submit Metabox: HTML
     */
    public static function post_submit_meta_box($post, $args = [])
    {
        do_action('revisionary_post_submit_meta_box');

        $type_obj = get_post_type_object($post->post_type);
        $post_status = $post->post_status;

        if ('auto-draft' == $post_status)
            $post_status = 'draft';

        if (!$post_status_obj = get_post_status_object($post_status)) {
            $post_status_obj = get_post_status_object('draft');
        }

        // Don't exclude the current status, regardless of other arguments
        $_args = ['include_status' => $post_status_obj->name];

        $post_status_obj = get_post_status_object($post->post_status);

        $moderation_statuses = array($post->post_status => $post_status_obj);

        $can_publish = current_user_can($type_obj->cap->publish_posts);

        $_args = compact('type_obj', 'post_status_obj', 'can_publish', 'moderation_statuses');
        $_args = array_merge($args, $_args);  // in case args passed into metabox are needed within static calls in the future
        ?>
        <div class="submitbox" id="submitpost">

            <div id="minor-publishing">
                <div id="minor-publishing-actions">
                    <div id="save-action">
                        <?php self::post_save_button($post, $_args); ?>
                    </div>
                    <div id="preview-action">
                        <?php self::post_preview_button($post, $_args); ?>
                    </div>
                    <div class="clear"></div>
                </div><?php // minor-publishing-actions ?>

                <div id="misc-publishing-actions">
                    <div class="misc-pub-section">
                        <?php self::post_status_display($post, $_args); ?>
                    </div>
                    <div class="misc-pub-section " id="visibility">
                        <?php self::post_visibility_display($post, $_args); ?>
                    </div>

                    <?php do_action('post_submitbox_misc_sections', $post); ?>

                    <?php
                    if (!empty($args['args']['revisions_count'])) :
                        $revisions_to_keep = wp_revisions_to_keep($post);
                        ?>
                        <div class="misc-pub-section num-revisions">
                            <?php
                            $revisions_caption = (rvy_is_revision_status($post->post_status)) ? __('Revision Edits: %s', 'revisionary') : __('Revisions: %s');

                            if ($revisions_to_keep > 0 && $revisions_to_keep <= $args['args']['revisions_count']) {
                                echo '<span title="' . esc_attr(sprintf(__('Your site is configured to keep only the last %s revisions.', 'revisionary'),
                                        number_format_i18n($revisions_to_keep))) . '">';
                                printf($revisions_caption, '<b>' . number_format_i18n($args['args']['revisions_count']) . '+</b>');
                                echo '</span>';
                            } else {
                                printf($revisions_caption, '<b>' . number_format_i18n($args['args']['revisions_count']) . '</b>');
                            }
                            ?>
                            <a class="hide-if-no-js"
                               href="<?php echo esc_url(admin_url("revision.php?revision={$args['args']['revision_id']}")); ?>" target="_revision_diff"><?php _ex('Compare', 'revisions', 'revisionary'); ?></a>
                        </div>
                    <?php
                    endif;
                    ?>

                    <?php
                    if ($can_publish) : // Contributors don't get to choose the date of publish
                        ?>
                        <div class="misc-pub-section curtime misc-pub-section-last">
                            <?php self::post_time_display($post, $_args); ?>
                        </div>
                    <?php endif; ?>

                    <?php /* see RvyPostEdit::actSubmitMetaboxActions() */  ?>

                    <?php do_action('post_submitbox_misc_actions', $post); ?>
                </div> <?php // misc-publishing-actions ?>

                <div class="clear"></div>
            </div> <?php // minor-publishing ?>

            <div id="major-publishing-actions">
                <?php do_action('post_submitbox_start', $post); ?>

                <div id="delete-action">
                    <?php // PP: no change from WP core
                    if (current_user_can("delete_post", $post->ID)) {
                        if (!EMPTY_TRASH_DAYS)
                            $delete_text =__('Delete Permanently');
                        else
                            $delete_text =__('Move to Trash');
                        ?>
                        <a class="submitdelete deletion"
                           href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
                    } ?>
                </div>

                <div id="publishing-action">
                    <?php self::post_publish_ui($post, $_args); ?>
                </div>
                <div class="clear"></div>
            </div> <?php // major-publishing-actions ?>

        </div> <?php // submitpost ?>

        <?php
    } // end function post_submit_meta_box()


    /*
     *  Classic Editor Post Submit Metabox: Post Save Button HTML
     */
    public static function post_save_button($post, $args)
    {
        if (empty($args['post_status_obj'])) return;

        $post_status_obj = $args['post_status_obj'];
        ?>
        <?php
        // @todo: confirm we don't need a hidden save button when current status is private */
        if (!$post_status_obj->public && !$post_status_obj->private && empty($post_status_obj->moderation) && ('future' != $post_status_obj->name)) :
            $draft_label = __('Update Revision', 'revisionary');
            ?>
            <input type="submit" name="save" id="save-post" value="<?php echo $draft_label ?>"
                   tabindex="4" class="button button-highlighted"/>
        <?php elseif ($post_status_obj->moderation) : 
            if (apply_filters('revisionary_display_save_as_button', true, $post, $args)):
                $status_label = (!empty($post_status_obj->labels->save_as)) ? $post_status_obj->labels->save_as : sprintf(__('Save as %s'), $post_status_obj->label);
                ?>
                <input type="submit" name="save" id="save-post" value="<?php echo $status_label ?>"
                   tabindex="4" class="button button-highlighted"/>
            <?php 
            endif;
            ?>
        <?php else : ?>
            <input type="submit" name="save" id="save-post" value="<?php esc_attr_e('Save'); ?>"
                   class="button button-highlighted" style="display:none"/>
        <?php endif; ?>

        <span class="spinner" style="margin:2px 2px 0"></span>
        <?php
    }

    /**
     *  Classic Editor Post Submit Metabox: Post Preview Button HTML
     */
    public static function post_preview_button($post, $args)
    {
        global $revisionary;

        if (empty($args['post_status_obj'])) return;

        $post_status_obj = $args['post_status_obj'];
        ?>
        <?php
        if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
            if ($is_revision = rvy_is_revision_status($post->post_status)) {
                $preview_link = rvy_preview_url($post);

                $type_obj = get_post_type_object($post->post_type);

                $can_publish = agp_user_can('edit_post', rvy_post_id($post->ID), '', ['skip_revision_allowance' => true]);
                
                if ($type_obj && empty($type_obj->public)) {
                    return;
                } elseif ($can_publish) {
                    $preview_button = ('future-revision' == $post->post_status) ? __('View / Publish', 'revisionary') : __('View / Approve', 'revisionary');
                    $preview_title = __('View / moderate saved revision', 'revisionary');
                } else {
                    $preview_button = __('View');
                    $preview_title = __('View saved revision', 'revisionary');
                }
                ?>
                <a class="preview button" href="<?php echo $preview_link; ?>" target="_blank" id="revision-preview"
                tabindex="4" title="<?php echo esc_attr($preview_title);?>"><?php echo $preview_button; ?></a>

                <?php
            } 
        }
        
        remove_filter('preview_post_link', [$revisionary->post_edit_ui, 'fltPreviewLink']);
        $preview_link = add_query_arg('rvy', 1, esc_url( get_preview_post_link( $post )));
        $preview_button =__('Preview Changes');
        $style = ($is_revision) ? 'style="display:none;"' : '';

        global $wp_version;
        ?>
        <a class="preview button" href="<?php echo $preview_link; ?>" target="<?php echo version_compare($wp_version, '5.3', '>=') ? 'wp-preview-' . (int) $post->ID : 'wp-preview';?>" id="post-preview"
        tabindex="4" <?php echo $style;?>><?php echo $preview_button; ?></a>
        <input type="hidden" name="wp-preview" id="wp-preview" value=""/>
        <?php
        add_filter('preview_post_link', [$revisionary->post_edit_ui, 'fltPreviewLink']);
    }

    /**
     *  Classic Editor Post Submit Metabox: Post Status Dropdown HTML
     */
    public static function post_status_display($post, $args)
    {
        $defaults = ['post_status_obj' => false, 'can_publish' => false, 'moderation_statuses' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        ?>
        <label for="post_status"><?php echo __('Status:'); ?></label>
        <?php
        $post_status = $post_status_obj->name;
        $status_label = (!empty($post_status_obj->labels->caption)) ? $post_status_obj->labels->caption : $post_status_obj->label;
        ?>
        <span id="post-status-display">
        <?php
        if ($post_status_obj->private)
            echo(PWP::__wp('Privately Published'));
        elseif ($post_status_obj->public)
            echo(PWP::__wp('Published'));
        elseif (!empty($post_status_obj->labels->caption))
            echo $post_status_obj->labels->caption;
        else
            echo $post_status_obj->label;
        ?>
        </span>&nbsp;
        <?php
        // multiple moderation stati are selectable or a single non-current moderation stati is selectable
        $select_moderation = (count($moderation_statuses) > 1 || ($post_status != key($moderation_statuses)));

        if ($post_status_obj->public || $post_status_obj->private || $can_publish || $select_moderation) { ?>
            <a href="#post_status"
               <?php if ($post_status_obj->private || ($post_status_obj->public && 'publish' != $post_status)) { ?>style="display:none;"
               <?php } ?>class="edit-post-status hide-if-no-js" tabindex='4'><?php echo __('Edit') ?></a>

            <div id="post-status-select" class="hide-if-js">
                <input type="hidden" name="hidden_post_status" id="hidden_post_status"
                       value="<?php echo $post_status; ?>"/>
                <select name='post_status' id='post_status' tabindex='4'>

                    <?php if ($post_status_obj->public || $post_status_obj->private || ('future' == $post_status)) : ?>
                        <option<?php selected(true, true); ?>
                                value='publish'><?php echo $status_label ?></option>
                    <?php endif; ?>

                    <?php
                    foreach ($moderation_statuses as $_status => $_status_obj) : ?>
                        <option<?php selected($post_status, $_status); ?>
                                value='<?php echo $_status ?>'><?php echo $status_label ?></option>
                    <?php endforeach ?>
                </select>
                <a href="#post_status" class="save-post-status hide-if-no-js button"><?php echo __('OK'); ?></a>
                <a href="#post_status" class="cancel-post-status hide-if-no-js"><?php echo __('Cancel'); ?></a>
            </div>

        <?php } // endif status editable
    }

    /**
     *  Classic Editor Post Submit Metabox: Post Visibility HTML
     */
    public static function post_visibility_display($post, $args)
    {
        $defaults = ['type_obj' => false, 'post_status_obj' => false, 'can_publish' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        echo __('Visibility:', 'revisionary'); ?>
        <span id="post-visibility-display"><?php

            if ('future' == $post_status_obj->name) {  // indicate eventual visibility of scheduled post
                if (!$vis_status = get_post_meta($post->ID, '_scheduled_status', true))
                    $vis_status = 'publish';

                $vis_status_obj = get_post_status_object($vis_status);
            } else {
                $vis_status = $post_status_obj->name;
                $vis_status_obj = $post_status_obj;
            }

            if ('publish' == $vis_status) {
                $post->post_password = '';
                $visibility = 'public';

                if (('post' == $post->post_type || post_type_supports($post->post_type, 'sticky')) && is_sticky($post->ID)) {
                    $visibility_trans =__('Public, Sticky');
                } else {
                    $visibility_trans =__('Public');
                }
            } elseif ($vis_status_obj->public) {
                $post->post_password = '';
                $visibility = $vis_status;

                if (('post' == $post->post_type || post_type_supports($post->post_type, 'sticky')) && is_sticky($post->ID)) {
                    $visibility_trans = sprintf(__('%s, Sticky'), $vis_status_obj->label);
                } else {
                    $visibility_trans = $vis_status_obj->labels->visibility;
                }
            } else {
                $visibility = 'public';
                $visibility_trans =__('Public');
            }

            echo esc_html($visibility_trans); ?>
        </span>

        <?php if ($can_publish) { ?>
        <a href="#visibility" class="edit-visibility hide-if-no-js"><?php echo __('Edit'); ?></a>

        <div id="post-visibility-select" class="hide-if-js">
            <input type="hidden" name="hidden_post_visibility" id="hidden-post-visibility" value="<?php echo esc_attr($visibility); ?>"/>

            <input type="radio" name="visibility" id="visibility-radio-public" value="public" <?php checked($visibility, 'public'); ?> /> 
                <label for="visibility-radio-public" class="selectit"><?php echo __('Public'); ?></label><br/>

            <p>
                <a href="#visibility" class="save-post-visibility hide-if-no-js button"><?php echo __('OK'); ?></a>
                <a href="#visibility" class="cancel-post-visibility hide-if-no-js"><?php echo __('Cancel'); ?></a>
            </p>
        </div>
    <?php }
    }

    /**
     *  Classic Editor Post Submit Metabox: Post Time Display HTML
     */
    public static function post_time_display($post, $args)
    {
        global $action;

        if (empty($args['post_status_obj'])) return;

        $post_status_obj = $args['post_status_obj'];
        ?>
        <span id="timestamp">
        <?php
        // translators: Publish box date formt, see http://php.net/date
        $datef =__('M j, Y @ G:i', 'revisionary');

        if (0 != $post->ID) {
            $published_stati = get_post_stati(['public' => true, 'private' => true], 'names', 'or');

            if ('future' == $post_status_obj->name) { // scheduled for publishing at a future date
                $stamp =__('Scheduled for: %s');
            } elseif (in_array($post_status_obj->name, $published_stati)) { // already published
                $stamp =__('Published on: %s');
            } elseif ('0000-00-00 00:00:00' == $post->post_date_gmt) { // draft, 1 or more saves, no date specified
                $stamp =__('Publish <b>immediately</b>');
            } elseif (time() < strtotime($post->post_date_gmt . ' +0000')) { // draft, 1 or more saves, future date specified
                $stamp =__('Schedule for: %s');
            } else { // draft, 1 or more saves, date specified
                $stamp =__('Publish on: %s');
            }
            $date = '<b>' . date_i18n($datef, strtotime($post->post_date)) . '</b>';
        } else { // draft (no saves, and thus no date specified)
            $stamp =__('Publish <b>immediately</b>');
            $date = date_i18n($datef, strtotime(current_time('mysql')));
        }
        printf($stamp, $date); ?></span>
        <a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" tabindex='4'><?php echo __('Edit') ?></a>
        <div id="timestampdiv" class="hide-if-js"><?php touch_time(($action == 'edit'), 1, 4); ?></div>
        <?php
    }

    /**
     *  Classic Editor Post Submit Metabox: Post Publish Button HTML
     */
    public static function post_publish_ui($post, $args)
    {
        $defaults = ['post_status_obj' => false, 'can_publish' => false, 'moderation_statuses' => []];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }
        ?>
        <span class="spinner"></span>

        <?php
        if ((!$post_status_obj->public && !$post_status_obj->private && ('future' != $post_status_obj->name))) {
            $status_obj = $post_status_obj;

            if (!empty($status_obj->public) || !empty($status_obj->private)) :
                if (!empty($post->post_date_gmt) && time() < strtotime($post->post_date_gmt . ' +0000')) :
                    $future_status_obj = get_post_status_object('future');
                    ?>
                    <input name="original_publish" type="hidden" id="original_publish" value="<?php echo $future_status_obj->labels->publish ?>"/>
                    <input name="publish" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo $future_status_obj->labels->publish ?>"/>
                <?php
                else :
                    $publish_status_obj = get_post_status_object('publish');
                    ?>
                    <input name="original_publish" type="hidden" id="original_publish" value="<?php echo $publish_status_obj->labels->publish ?>"/>
                    <input name="publish" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo $publish_status_obj->labels->publish ?>"/>
                <?php
                endif;
            else :
                echo '<input name="pp_submission_status" type="hidden" id="pp_submission_status" value="' . $status_obj->name . '" />';
                ?>
                <input name="original_publish" type="hidden" id="original_publish" value="<?php echo $status_obj->labels->publish ?>"/>
                <input name="publish" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo $status_obj->labels->publish ?>"/>
            <?php
            endif;
        } else { ?>
            <input name="original_publish" type="hidden" id="original_publish" value="<?php echo esc_attr(PWP::__wp('Update')); ?>"/>
            <input name="save" type="submit" class="button button-primary button-large" id="publish" tabindex="5" accesskey="p" value="<?php echo esc_attr(PWP::__wp('Update')); ?>"/>
            <?php
        }
    }
}
