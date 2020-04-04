<?php
namespace PublishPress\Revisions;

class CoreAdmin {
    function __construct() {
        add_action('admin_print_scripts', [$this, 'setUpgradeMenuLink'], 50);

        if (is_admin()) {
            $autoloadPath = RVY_ABSPATH . '/vendor/autoload.php';
			if (file_exists($autoloadPath)) {
				require_once $autoloadPath;
			}

            require_once RVY_ABSPATH . '/vendor/publishpress/wordpress-version-notices/includes.php';
    
            add_filter(\PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER, function ($settings) {
                $settings['revisionary'] = [
                    'message' => 'You\'re using PublishPress Revisions Free. The Pro version has more features and support. %sUpgrade to Pro%s',
                    'link'    => 'https://publishpress.com/links/revisions-banner',
                    'screens' => [
                        ['base' => 'toplevel_page_revisionary-q'],
                        ['base' => 'revisions_page_revisionary-settings'],
                    ]
                ];
    
                return $settings;
            });
        }
    }

    function setUpgradeMenuLink() {
        $url = 'https://publishpress.com/links/revisions-menu';
        ?>
        <style type="text/css">
        #toplevel_page_revisionary-q ul li:last-of-type a {font-weight: bold !important; color: #FEB123 !important;}
        </style>

		<script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#toplevel_page_revisionary-q ul li:last a').attr('href', '<?php echo $url;?>').attr('target', '_blank').css('font-weight', 'bold').css('color', '#FEB123');
            });
        </script>
		<?php
    }
}
