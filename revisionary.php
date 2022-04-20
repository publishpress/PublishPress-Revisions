<?php
/**
 * Plugin Name: PublishPress Revisions
 * Plugin URI: https://publishpress.com/revisionary/
 * Description: Maintain published content with teamwork and precision using the Revisions model to submit, approve and schedule changes.
 * Author: PublishPress
 * Author URI: https://publishpress.com
 * Version: 3.0.16
 * Text Domain: revisionary
 * Domain Path: /languages/
 * Min WP Version: 4.9.7
 * Requires PHP: 5.6.20
 * 
 * Copyright (c) 2022 PublishPress
 *
 * GNU General Public License, Free Software Foundation <https://www.gnu.org/licenses/gpl-3.0.html>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     PublishPress\Revisions
 * @author      PublishPress
 * @copyright   Copyright (C) 2022 PublishPress. All rights reserved.
 *
 **/

// Temporary usage within this module only; avoids multiple instances of version string
global $pp_revisions_version;
$pp_revisions_version = '3.0.16';

if (!empty($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename(esc_url_raw($_SERVER['SCRIPT_FILENAME'])) )
	die( 'This page cannot be called directly.' );

if (isset($_SERVER['SCRIPT_NAME']) && strpos( esc_url_raw($_SERVER['SCRIPT_NAME']), 'p-admin/index-extra.php' ) || strpos( esc_url_raw($_SERVER['SCRIPT_NAME']), 'p-admin/update.php' ) )
	return;

$pro_active = false;

foreach ((array)get_option('active_plugins') as $plugin_file) {
	if (false !== strpos($plugin_file, 'revisionary-pro.php')) {
		$pro_active = true;
		break;
	}
}

if (!$pro_active && is_multisite()) {
	foreach (array_keys((array)get_site_option('active_sitewide_plugins')) as $plugin_file) {
		if (false !== strpos($plugin_file, 'revisionary-pro.php')) {
			$pro_active = true;
			break;
		}
	}
}

if ($pro_active) {
	add_filter(
        'plugin_row_meta', 
        function($links, $file)
        {
            if ($file == plugin_basename(__FILE__)) {
                $links[]= __('<strong>This plugin can be deleted.</strong>', 'revisionary');
            }

            return $links;
        },
        10, 2
    );
	return;
}

if ( defined('RVY_VERSION') || defined('REVISIONARY_FILE') ) {  // Revisionary 1.x defines RVY_VERSION on load, but does not define REVISIONARY_FILE
	// don't allow two copies to run simultaneously
	if ( is_admin() && isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['REQUEST_URI']) 
	&& strpos( esc_url_raw($_SERVER['SCRIPT_NAME']), 'p-admin/plugins.php' ) && ! strpos( urldecode(esc_url_raw(esc_url_raw($_SERVER['REQUEST_URI']))), 'deactivate' ) ) {
		add_action('all_admin_notices', function()
		{
			if (defined('REVISIONARY_FILE')) {
				$other_version = (defined('REVISIONARY_VERSION')) ? REVISIONARY_VERSION : PUBLISHPRESS_REVISIONS_VERSION;
				$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s: "%2$s")', 'revisionary' ), $other_version, dirname(plugin_basename(REVISIONARY_FILE)) );
			} else {
				$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s)', 'revisionary' ), RVY_VERSION );
			}
			
			echo "<div id='message' class='notice error' style='color:black'>" . esc_html($message) . '</div>';
		}, 5);
	}
	return;
}

define('REVISIONARY_FILE', __FILE__);

add_action(
	'init', 
	function() {
		global $pp_revisions_version;
		require_once(dirname(__FILE__).'/functions.php');
		pp_revisions_plugin_updated($pp_revisions_version);
	},
	2
);

// register these functions before any early exits so normal activation/deactivation can still run with RS_DEBUG
register_activation_hook(__FILE__, function() 
	{
		global $pp_revisions_version;
		require_once(dirname(__FILE__).'/functions.php');
		pp_revisions_plugin_updated($pp_revisions_version);

		// force this timestamp to be regenerated, in case something went wrong before
		delete_option( 'rvy_next_rev_publish_gmt' );

		if (!class_exists('RevisionaryActivation')) {
			require_once(dirname(__FILE__).'/activation_rvy.php');
		}

		require_once(dirname(__FILE__).'/functions.php');

		// import from Revisionary 1.x
		new RevisionaryActivation(['import_legacy' => true]);

		// convert pending / scheduled revisions to v3.0 format
		global $wpdb;
		$revision_status_csv = implode("','", array_map('sanitize_key', rvy_revision_statuses()));

		$wpdb->query("DELETE FROM $wpdb->posts WHERE post_mime_type IN ('draft-revision', 'pending-revision', 'future-revision') AND post_status = 'trash'");

		$wpdb->query("UPDATE $wpdb->posts SET post_mime_type = post_status WHERE post_status IN ('$revision_status_csv')");
		$wpdb->query("UPDATE $wpdb->posts SET post_status = 'draft', post_mime_type = 'draft-revision' WHERE post_status IN ('draft-revision')");
		$wpdb->query("UPDATE $wpdb->posts SET post_status = 'pending', post_mime_type = 'pending-revision' WHERE post_status IN ('pending-revision')");
		$wpdb->query("UPDATE $wpdb->posts SET post_status = 'pending', post_mime_type = 'future-revision' WHERE post_status IN ('future-revision')");
	}
);

register_deactivation_hook(__FILE__, function()
	{
		global $wpdb;

		require_once(dirname(__FILE__).'/functions.php');

		// convert pending / scheduled revisions to v2.x format, which also prevents them from being listed as regular drafts / pending posts
		$revision_status_csv = implode("','", array_map('sanitize_key', rvy_revision_statuses()));
		$wpdb->query("UPDATE $wpdb->posts SET post_status = post_mime_type WHERE post_mime_type IN ('$revision_status_csv')");
		$wpdb->query("UPDATE $wpdb->posts SET post_mime_type = '' WHERE post_mime_type IN ('$revision_status_csv')");
		
		if ($timestamp = wp_next_scheduled('rvy_mail_buffer_hook')) {
		   wp_unschedule_event( $timestamp,'rvy_mail_buffer_hook');
		}
	}
);

// negative priority to precede any default WP action handlers
add_action(
	'plugins_loaded', 
	function()
	{
		if ( defined('RVY_VERSION') ) {  // Revisionary 1.x defines RVY_VERSION on load, but does not define REVISIONARY_FILE
			// don't allow two copies to run simultaneously
			if ( is_admin() && isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'])
			&& strpos( esc_url_raw($_SERVER['SCRIPT_NAME']), 'p-admin/plugins.php' ) && ! strpos( urldecode(esc_url_raw($_SERVER['REQUEST_URI'])), 'deactivate' ) 
			) {
				add_action('all_admin_notices', function()
				{
					if (defined('REVISIONARY_FILE')) {
						$other_version = (defined('REVISIONARY_VERSION')) ? REVISIONARY_VERSION : PUBLISHPRESS_REVISIONS_VERSION;
						$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s: "%2$s")', 'revisionary' ), $other_version, dirname(plugin_basename(REVISIONARY_FILE)) );
					} else {
						$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s)', 'revisionary' ), RVY_VERSION );
					}

					echo "<div id='message' class='notice error' style='color:black'>" . esc_html($message) . '</div>';
				}, 5);
			}
			return;
		}

		global $wp_version;

		$min_wp_version = '4.9.7';
		$min_php_version = '5.6.20';

		$php_version = phpversion();

		// Critical errors that prevent initialization
		if (version_compare($min_php_version, $php_version, '>')) {
			if (is_admin() && current_user_can('activate_plugins')) {
				add_action('all_admin_notices', function(){echo "<div id='message' class='notice error'>" . sprintf(esc_html__('PublishPress Revisions requires PHP version %s or higher.', 'revisionary'), '5.6.20') . "</div>"; });
			}
			return;
		}

		if (version_compare($wp_version, $min_wp_version, '<')) {
			if (is_admin() && current_user_can('activate_plugins')) {
				add_action('all_admin_notices', function(){echo "<div id='message' class='notice error'>" . sprintf(esc_html__('PublishPress Revisions requires WordPress version %s or higher.', 'revisionary'), '4.9.7') . "</div>"; });
			}
			return;
		}

		global $pp_revisions_version;
		define('PUBLISHPRESS_REVISIONS_VERSION', $pp_revisions_version);

		if ( ! defined( 'RVY_VERSION' ) ) {
			define( 'RVY_VERSION', PUBLISHPRESS_REVISIONS_VERSION );  // back compat
		}

		define ('COLS_ALL_RVY', 0);
		define ('COL_ID_RVY', 1);

		if ( defined('RS_DEBUG') ) {
			include_once( dirname(__FILE__).'/lib/debug.php');
			add_action( 'admin_footer', 'rvy_echo_usage_message' );
		} else
			include_once( dirname(__FILE__).'/lib/debug_shell.php');

		require_once( dirname(__FILE__).'/defaults_rvy.php');

		// === awp_is_mu() function definition and usage: must be executed in this order, and before any checks of IS_MU_RVY constant ===
		require_once( dirname(__FILE__).'/lib/agapetry_wp_core_lib.php');
		define( 'IS_MU_RVY', awp_is_mu() );
		// -------------------------------------------

		require_once( dirname(__FILE__).'/content-roles_rvy.php');

		if ( is_admin() || defined('XMLRPC_REQUEST') ) {
			require_once( dirname(__FILE__).'/lib/agapetry_wp_admin_lib.php');
				
			// skip WP version check and init operations when a WP plugin auto-update is in progress
			if (isset($_SERVER['SCRIPT_NAME']) && false !== strpos(esc_url_raw($_SERVER['SCRIPT_NAME']), 'update.php') )
				return;
		}

		require_once( dirname(__FILE__).'/classes/PublishPress/Revisionary.php');
		require_once( dirname(__FILE__).'/rvy_init.php');	// Contains activate, deactivate, init functions. Adds mod_rewrite_rules.
		require_once( dirname(__FILE__).'/functions.php');

		// avoid lockout in case of editing plugin via wp-admin
		if ( defined('RS_DEBUG') && is_admin() && isset($_SERVER['REQUEST_URI']) && ( strpos( urldecode(esc_url_raw($_SERVER['REQUEST_URI'])), 'p-admin/plugin-editor.php' ) || strpos( urldecode(esc_url_raw($_SERVER['REQUEST_URI'])), 'p-admin/plugins.php' ) ) && false === strpos( esc_url_raw($_SERVER['REQUEST_URI']), 'activate' ) )
			return;

		define('RVY_ABSPATH', __DIR__);

		if (is_admin() && !defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) {
			require_once(__DIR__ . '/includes/CoreAdmin.php');
			new \PublishPress\Revisions\CoreAdmin();
		}

		rvy_refresh_options_sitewide();

		// since sequence of set_current_user and init actions seems unreliable, make sure our current_user is loaded first
		add_action('init', 'rvy_init', 1);
		add_action('init', 'rvy_add_revisor_custom_caps', 99);
		add_action('init', 'rvy_configuration_late_init', PHP_INT_MAX - 1);

		revisionary();
	}
	, -10
);
