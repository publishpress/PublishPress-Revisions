<?php
namespace PublishPress\Revisions;

class CoreAdmin {
    function __construct() {
        add_action('admin_print_scripts', [$this, 'setUpgradeMenuLink'], 50);

        add_action('publishpress_revisions_settings_sidebar', [$this, 'settingsSidebar']);
        add_filter('publishpress_revisions_settings_sidebar', function($class) {return 'has-right-sidebar';});

        add_filter(\PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER, function ($settings) {
            $settings['revisionary'] = [
                'message' => 'You\'re using PublishPress Revisions Free. The Pro version has more features and support. %sUpgrade to Pro%s',
                'link'    => 'https://publishpress.com/links/revisions-banner',
                'screens' => [
                    ['base' => 'toplevel_page_revisionary-q'],
                    ['base' => 'revisions_page_revisionary-settings'],
                    ['base' => 'revisions_page_revisionary-archive'],
                    ['base' => 'toplevel_page_revisionary-archive'],
                ]
            ];

            return $settings;
        });
    }

    function setUpgradeMenuLink() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $url = 'https://publishpress.com/links/revisions-menu';
        ?>
        <style type="text/css">
        #toplevel_page_revisionary-q ul li:last-of-type a {font-weight: bold !important; color: #FEB123 !important;}
        #toplevel_page_revisionary-archive ul li:last-of-type a {font-weight: bold !important; color: #FEB123 !important;}
        </style>

		<script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#toplevel_page_revisionary-q ul li:last a').attr('href', '<?php echo $url;?>').attr('target', '_blank').css('font-weight', 'bold').css('color', '#FEB123');
                $('#toplevel_page_revisionary-archive ul li:last a').attr('href', '<?php echo $url;?>').attr('target', '_blank').css('font-weight', 'bold').css('color', '#FEB123');
            });
        </script>
		<?php
    }

    function settingsSidebar() {
        ?>
        <div id="side-info-column">
            <div class="inner-sidebar">
                <div id="side-sortables" class="meta-box-sortables ui-sortable">
                    <div id="token-legend" class="postbox">
                        <div>
                            <?php $this->sidebarBannerContent();?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function sidebarBannerContent(){ 
        ?>
        
        <div class="pp-revisions-pro-promo-right-sidebar">
            <div id="postbox-container-1" class="postbox-container">
            <div class="meta-box-sortables">
                <div class="advertisement-box-content postbox">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Upgrade to PublishPress Revisions Pro', 'revisionary'); ?></span>
                        </h3>
                    </div>
        
                    <div class="inside">
                        <p><?php echo esc_html__('Upgrade to PublishPress Revisions Pro for integration with key features of these plugins:', 'revisionary'); ?>
                        </p>
                        <ul>
                            <li><?php echo esc_html__('Elementor', 'revisionary'); ?></li>
                            <li><?php echo esc_html__('Divi Builder', 'revisionary'); ?></li>
                            <li><?php echo esc_html__('Beaver Builder', 'revisionary'); ?></li>
                            <li><?php echo esc_html__('Advanced Custom Fields', 'revisionary'); ?></li>
                            <li><?php echo esc_html__('Pods', 'revisionary'); ?></li>
                            <li><?php echo esc_html__('WooCommerce', 'revisionary'); ?></li>
                            <li><?php echo esc_html__('WPML', 'revisionary'); ?></li>
                            <li><?php echo esc_html__('WPML Translation Management', 'revisionary'); ?></li>
                            <li class="no-icon"><a href="https://publishpress.com/knowledge-base/plugins-revisions-support/" target="__blank"><?php echo esc_html__('plugin integration details...', 'revisionary'); ?></a></li>
                        </ul>

                        <div class="upgrade-btn">
                            <a href="https://publishpress.com/links/revisions-banner/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'revisionary'); ?></a>
                        </div>
                    </div>
                </div>
                <div class="advertisement-box-content postbox">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Need PublishPress Revisions Support?', 'revisionary'); ?></span>
                        </h3>
                    </div>
        
                    <div class="inside">
                        <p><?php echo esc_html__('If you need help or have a new feature request, let us know.', 'revisionary'); ?>
                            <a class="advert-link" href="https://wordpress.org/support/plugin/revisionary/" target="_blank">
                            <?php echo esc_html__('Request Support', 'revisionary'); ?> 
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="linkIcon">
                                    <path
                                        d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"
                                    ></path>
                                </svg>
                            </a>
                        </p>
                        <p>
                        <?php echo esc_html__('Detailed documentation is also available on the plugin website.', 'revisionary'); ?> 
                            <a class="advert-link" href="https://publishpress.com/knowledge-base/start-revisions/" target="_blank">
                            <?php echo esc_html__('View Knowledge Base', 'revisionary'); ?> 
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="linkIcon">
                                    <path
                                        d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"
                                    ></path>
                                </svg>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>    
    </div>

        <?php
    }
}
