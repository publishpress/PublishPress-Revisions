<?php
namespace PublishPress\Revisions;

class PlannerContentBoard extends Planner {
    function __construct() {
        add_action('init', function() {
            if ($this->showingRevisions()) {
                add_filter('PP_Content_Board_item_actions',
                    function($actions, $post_id) {
                        // @todo: support revision trashing
                        if (rvy_in_revision_workflow($post_id)) {
                            $actions['trash'] = '<a class="submitdelete" href="' . esc_url(get_delete_post_link($post_id, false, true)) . '">' . esc_html__('Delete') . '</a>';
                        }

                        return $actions;
                    },
                    10, 2
                );
            }
        });

        add_filter(
			'publishpress_user_post_status_options', 
			function($status_options, $post_type = false) {
                if (class_exists('PP_Revision_Integration') && $this->showingRevisions()) {
                    $planner_compat_mode = rvy_get_option('planner_compat_mode');
                    
                    foreach ($status_options as $k => $opt) {
                        if (isset($opt['value']) && ('future-revision' == $opt['value'])) {
                            unset($status_options[$k]);
                        
                        } elseif (!defined('PUBLISHPRESS_STATUSES_PRO_VERSION')) {
                            unset($status_options[$k]);
                        }
                    }

                    if (version_compare('PUBLISHPRESS_VERSION', '4.6.1-beta', '<')) {
                        // Work around plugin API bug in Planner 4.6.0
                        global $pp_post_type_status_options;
                        $pp_post_type_status_options = [];
                    }
                }

				return $status_options;
			}, 10, 2
		);

        add_action(
            'admin_print_scripts',
            function() {
                if ($this->showingRevisions()) :?>
                    <script type="text/javascript">
                    /* <![CDATA[ */
                    jQuery(document).ready(function ($) {
                        <?php if (!rvy_get_option('permissions_compat_mode')):?>
                        $('div.content-board-inside div.status-future').hide();
                        $('div.content-board-inside div.status-private').hide();
                        <?php endif;?>
                    });
                    /* ]]> */
                    </script>

                    <?php if (!rvy_get_option('permissions_compat_mode') || !defined('PUBLISHPRESS_STATUSES_PRO_VERSION')) :?>
                    <style type="text/css">
                    div.can_not_move_to div.drag-message {opacity: 0 !important;}
                    </style>
                    <?php endif;
                endif;
            }, 50
        );
    }
}
