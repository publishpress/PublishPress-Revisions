<?php
namespace PublishPress\Revisions;

class PlannerCalendar extends Planner {
    function __construct() {
        add_action(
            'admin_print_scripts',
            function() {
                if ($this->showingRevisions()) :?>
                <script type="text/javascript">
                /* <![CDATA[ */
                jQuery(document).ready(function ($) {
                    $('div.pp-show-revision-btn').on('click', function() {
                        $('#pp-content-filters button.post_status').attr('disabled', 'disabled');

                        $('div.content-calendar-modal').hide();
                    });
                    
                    setInterval(() => {
                        if ($('#pp-content-calendar-general-modal-container:visible').length) {
                            $('div.pp-popup-modal-header div.post-delete a').html('<?php _e('Delete');?>');

                            var href = $('div.pp-popup-modal-header div.post-delete a').attr('href');
                            $('div.pp-popup-modal-header div.post-delete a').attr('href', href.replace('action=trash', 'action=delete'));
                        }
                    }, 100);
                });
                /* ]]> */
                </script>
                <?php endif;
            }, 50
        );
    }
}
