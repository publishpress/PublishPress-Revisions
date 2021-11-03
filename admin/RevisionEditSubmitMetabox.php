<?php
class RvyRevisionEditSubmitMetabox
{
    /**
     *  Classic Editor Post Submit Metabox: HTML
     */
    public static function post_submit_meta_box($post, $args = [])
    {
        do_action('revisionary_post_submit_meta_box');

        $type_obj = get_post_type_object($post->post_type);
        $post_status = $post->post_mime_type;

        $post_status_obj = get_post_status_object($post_status);

        $can_publish = current_user_can($type_obj->cap->publish_posts);

        $_args = compact('type_obj', 'post_status_obj', 'can_publish');
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
        if (!$draft_label = pp_revisions_status_label($post->post_mime_type, 'update')) {
        	$draft_label = pp_revisions_label('update_revision');
        }
        ?>
        <input type="submit" name="save" id="save-post" value="<?php echo $draft_label ?>"
                tabindex="4" class="button button-highlighted"/>

        <span class="spinner" style="margin:2px 2px 0"></span>
        <?php
    }

    /**
     *  Classic Editor Post Submit Metabox: Post Preview Button HTML
     */
    public static function post_preview_button($post, $args)
    {
        if (empty($args['post_status_obj'])) return;

        $post_status_obj = $args['post_status_obj'];
        ?>
        <?php
        if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
            $preview_link = rvy_preview_url($post);

            $type_obj = get_post_type_object($post->post_type);

            $can_publish = current_user_can('edit_post', rvy_post_id($post->ID));

            if ($type_obj && empty($type_obj->public)) {
                return;
            } elseif ($can_publish) {
                //$preview_button = ('future-revision' == $post->post_mime_type) ? __('View / Publish', 'revisionary') : __('Preview / Approve', 'revisionary');
                $preview_button = __('Preview', 'revisionary');
                $preview_title = __('View / moderate saved revision', 'revisionary');
            } else {
                $preview_button = __('Preview', 'revisionary');
                $preview_title = __('View saved revision', 'revisionary');
            }
            ?>
            <a class="preview button" href="<?php echo $preview_link; ?>" target="_blank" id="revision-preview"
            tabindex="4" title="<?php echo esc_attr($preview_title);?>"><?php echo $preview_button; ?></a>

            <?php
        }
    }

    /**
     *  Classic Editor Post Submit Metabox: Post Status Dropdown HTML
     */
    public static function post_status_display($post, $args)
    {
        $defaults = ['post_status_obj' => false];
        $args = array_merge($defaults, $args);
        foreach (array_keys($defaults) as $var) {
            $$var = $args[$var];
        }

        ?>
        <label for="post_status"><?php echo __('Status:'); ?></label>
        <?php
        $status_label = (!empty($post_status_obj->labels->caption)) ? $post_status_obj->labels->caption : $post_status_obj->label;
        ?>
        <span id="post-status-display">
        <?php
        echo $status_label;
        ?>
        </span>&nbsp;

        <?php /* Output status select for js consistency with date select OK / Cancel */?>
        <div id="post-status-select" class="hide-if-js" style="display:none">
            <select name='post_status' id='post_status' tabindex='4'>
                    <option selected value='<?php echo $post_status_obj->name ?>'><?php echo $status_label ?></option>
            </select>
        </div>
        <?php
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

        $published_stati = get_post_stati(['public' => true, 'private' => true], 'names', 'or');

        if ('future-revision' == $post_status_obj->name) { // scheduled for publishing at a future date
            $stamp =__('Scheduled for: %s');

        } elseif (strtotime($post->post_date_gmt) > agp_time_gmt()) {
            $stamp =__('Publish on: %s');

        } else {
            $stamp = __('Publish <b>on approval</b>', 'revisionary');
        }

        $date = '<b>' . date_i18n($datef, strtotime($post->post_date)) . '</b>';

        printf($stamp, $date); ?></span>
        <a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" tabindex='4'><?php echo __('Edit') ?></a>
        <div id="timestampdiv" class="hide-if-js"><?php touch_time(($action == 'edit'), 1, 4); ?></div>
        <?php
    }
}
