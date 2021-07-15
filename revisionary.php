<?php
/**
 * Plugin Name: PublishPress Revisions
 * Plugin URI: https://publishpress.com/revisionary/
 * Description: Maintain published content with teamwork and precision using the Revisions model to submit, approve and schedule changes.
 * Author: PublishPress
 * Author URI: https://publishpress.com
 * Version: 2.6.1
 * Text Domain: revisionary
 * Domain Path: /languages/
 * Min WP Version: 4.9.7
 * Requires PHP: 5.6.20
 * 
 * Copyright (c) 2021 PublishPress
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
 * @copyright   Copyright (C) 2021 PublishPress. All rights reserved.
 *
 **/

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

if ( strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/index-extra.php' ) || strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/update.php' ) )
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
	if ( is_admin() && strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/plugins.php' ) && ! strpos( urldecode($_SERVER['REQUEST_URI']), 'deactivate' ) ) {
		add_action('all_admin_notices', function()
		{
			if (defined('REVISIONARY_FILE')) {
				$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s: "%2$s")', 'revisionary' ), RVY_VERSION, dirname(plugin_basename(REVISIONARY_FILE)) );
			} else {
				$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s)', 'revisionary' ), RVY_VERSION );
			}
			
			echo "<div id='message' class='notice error' style='color:black'>" . $message . '</div>';
		}, 5);
	}
	return;
}

define('REVISIONARY_FILE', __FILE__);

// register these functions before any early exits so normal activation/deactivation can still run with RS_DEBUG
register_activation_hook(__FILE__, function() 
	{
		$current_version = '2.6.1';

		$last_ver = get_option('revisionary_last_version');

		if ($current_version != $last_ver) {
			require_once( dirname(__FILE__).'/lib/agapetry_wp_core_lib.php');
			require_once(dirname(__FILE__).'/rvy_init.php');
			revisionary_refresh_revision_flags();

			// mirror to REVISIONARY_VERSION
			update_option('revisionary_last_version', $current_version);
		}

		// force this timestamp to be regenerated, in case something went wrong before
		delete_option( 'rvy_next_rev_publish_gmt' );

		if (!class_exists('RevisionaryActivation')) {
			require_once(dirname(__FILE__).'/activation_rvy.php');
		}

		new RevisionaryActivation(['import_legacy' => true]);
	}
);

register_deactivation_hook(__FILE__, function()
	{
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
			if ( is_admin() && strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/plugins.php' ) && ! strpos( urldecode($_SERVER['REQUEST_URI']), 'deactivate' ) ) {
				add_action('all_admin_notices', function()
				{
					if (defined('REVISIONARY_FILE')) {
						$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s: "%2$s")', 'revisionary' ), RVY_VERSION, dirname(plugin_basename(REVISIONARY_FILE)) );
					} else {
						$message = sprintf( __( 'Another copy of PublishPress Revisions (or Revisionary) is already activated (version %1$s)', 'revisionary' ), RVY_VERSION );
					}

					echo "<div id='message' class='notice error' style='color:black'>" . $message . '</div>';
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
				add_action('all_admin_notices', function(){echo "<div id='message' class='notice error'>" . sprintf(__('PublishPress Revisions requires PHP version %s or higher.', 'revisionary'), '5.6.20') . "</div>"; });
			}
			return;
		}

		if (version_compare($wp_version, $min_wp_version, '<')) {
			if (is_admin() && current_user_can('activate_plugins')) {
				add_action('all_admin_notices', function(){echo "<div id='message' class='notice error'>" . sprintf(__('PublishPress Revisions requires WordPress version %s or higher.', 'revisionary'), '4.9.7') . "</div>"; });
			}
			return;
		}

		define('REVISIONARY_VERSION', '2.6.1');

		if ( ! defined( 'RVY_VERSION' ) ) {
			define( 'RVY_VERSION', REVISIONARY_VERSION );  // back compat
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
			if ( false !== strpos($_SERVER['SCRIPT_NAME'], 'update.php') )
				return;
		}

		require_once( dirname(__FILE__).'/classes/PublishPress/Revisionary.php');
		require_once( dirname(__FILE__).'/rvy_init.php');	// Contains activate, deactivate, init functions. Adds mod_rewrite_rules.
		require_once( dirname(__FILE__).'/functions.php');

		// avoid lockout in case of editing plugin via wp-admin
		if ( defined('RS_DEBUG') && is_admin() && ( strpos( urldecode($_SERVER['REQUEST_URI']), 'p-admin/plugin-editor.php' ) || strpos( urldecode($_SERVER['REQUEST_URI']), 'p-admin/plugins.php' ) ) && false === strpos( $_SERVER['REQUEST_URI'], 'activate' ) )
			return;

		define('RVY_ABSPATH', __DIR__);

		if (is_admin() && !defined('REVISIONARY_PRO_VERSION')) {
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
