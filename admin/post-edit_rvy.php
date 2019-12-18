<?php

class RvyPostEdit {
    function __construct() {
        add_action('the_post', array($this, 'limitRevisionEditorUI'));

        add_action('add_meta_boxes', [$this, 'act_replace_publish_metabox'], 10, 2);

        // deal with case where another plugin replaced publish metabox
        add_filter('preview_post_link', [$this, 'fltPreviewLink']);
        add_filter('presspermit_preview_post_label', [$this, 'fltPreviewLabel']);
        add_filter('presspermit_preview_post_title', [$this, 'fltPreviewTitle']);

        add_action('admin_head', array($this, 'act_admin_head') );

        add_action('post_submitbox_misc_actions', array($this, 'act_post_submit_revisions_links'), 5);

        add_action('admin_head', [$this, 'actAdminBarPreventPostClobber'], 5);
    }

    function actAdminBarPreventPostClobber() {
        global $post;

        // prevent PHP Notice from Multiple Authors code:
        // Notice: Trying to get property of non-object in F:\www\wp50\wp-content\plugins\publishpress-multiple-authors\core\Classes\Utils.php on line 309
        // @todo: address within MA
        if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && !empty($_REQUEST['post'])) {
            $post = get_post($_REQUEST['post']);
        }
    }

    function act_admin_head() {
        global $post;

        if (!empty($post) && rvy_is_revision_status($post->post_status)):
            $type_obj = get_post_type_object($post->post_type);
        ?>
    <script type="text/javascript">
    /* <![CDATA[ */
    jQuery(document).ready( function($) {
        $('h1.wp-heading-inline').html('<?php printf(__('Edit %s Revision', 'revisionary'), $type_obj->labels->singular_name);?>');
    });
    /* ]]> */
    </script>
        <?php endif;
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

    public function fltPreviewLink($url) {
        global $post;

        $_arg = ('page' == $post->post_type) ? 'page_id=' : 'p=';
        return add_query_arg( 'preview', true, str_replace( 'p=', $_arg, get_post_permalink( $post ) ) );
    }

    public function fltPreviewLabel($preview_caption) {
        global $post;

        $type_obj = get_post_type_object($post->post_type);
        $can_publish = $type_obj && agp_user_can($type_obj->cap->edit_post, rvy_post_id($post->ID), '', array('skip_revision_allowance' => true));
        if ($can_publish) {
            $preview_caption = ('future-revision' == $post->post_status) ? __('View / Publish') : __('View / Approve');
        } else {
            $preview_caption = __('View');
        }

        return $preview_caption;
    }

    public function fltPreviewTitle($preview_title) {
        global $post;

        $type_obj = get_post_type_object($post->post_type);
        $can_publish = $type_obj && agp_user_can($type_obj->cap->edit_post, rvy_post_id($post->ID), '', array('skip_revision_allowance' => true));
        if ($can_publish) {
            $preview_title = __('View / moderate saved revision', 'revisionary');
        } else {
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

		if ( $type_obj && ! agp_user_can( $type_obj->cap->edit_post, $post->ID, '', array( 'skip_revision_allowance' => true ) ) ) {
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
                href="<?php echo esc_url($url); ?>" target="_revision_diff"><?php _ex('Compare', 'revisions'); ?></a>
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
                href="<?php echo esc_url($url); ?>" target="_revision_diff"><?php _ex('Compare', 'revisions'); ?></a>
            </div>
            <?php
            }
        }
    }
}