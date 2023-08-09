<?php
namespace PublishPress;

class Revisions {
	private static $instance = null;

	public static function instance($args = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new Revisions();
            self::$instance->load();
        }

        return self::$instance;
    }

    private function __construct()
    {

    }

	private function load($args = [])
    {
		if (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) {
			add_action('admin_init', [$this, 'load_updater']);
		}
	}

	public function load_updater() {
        if (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) {
		    require_once(REVISIONARY_PRO_ABSPATH . '/includes-pro/library/Factory.php');
            $container = \PublishPress\Revisions\Factory::get_container();

            return $container['edd_container']['update_manager'];
        }
	}

	public function keyStatus($refresh = false)
    {
        if (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) {
            require_once(REVISIONARY_PRO_ABSPATH . '/includes-pro/pro-key.php');
            return _revisionary_key_status($refresh);
        }
    }

    public function keyActive($refresh = false)
    {
        return in_array($this->keyStatus($refresh), [true, 'valid', 'expired'], true);                
    }

	public function getOption($option_basename) {
		return ('edd_key' == $option_basename) ? get_site_option('rvy_edd_key') : rvy_get_option($option_basename);
	}

	public function updateOption($option_basename, $option_val, $args = [])
    {
        if ('edd_key' == $option_basename) {
            return update_site_option('rvy_edd_key', $option_val);
        } else {
            $args = (array) $args;
            $sitewide = (isset($args['sitewide'])) ? $args['sitewide'] : -1;
            return rvy_update_option($option_basename, $option_val, $sitewide);
        }
	}
	
	public function deleteOption($option_basename, $args = []) {
        if ('edd_key' == $option_basename) {
            return delete_site_option('rvy_edd_key');
        } else {
            $args = (array) $args;
            $sitewide = (isset($args['sitewide'])) ? $args['sitewide'] : -1;
            return rvy_delete_option($option_basename, $sitewide);
        }
	}
    
    public function getUserRevision($post_id, $args = []) {
        global $wpdb, $current_user;

        $args = (array) $args;
        $user_id = (!empty($args['user_id'])) ? $args['user_id'] : $current_user->ID;

        if (empty($args['force_query']) && !rvy_get_post_meta($post_id, '_rvy_has_revisions')) {
            return false;
        }

        $revision_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE comment_count = %d AND post_author = %d AND post_status IN ('pending-revision', 'future-revision') ORDER BY ID DESC LIMIT 1",
                $post_id,
                $user_id
            )
        );

        return $revision_id;
    }
}
