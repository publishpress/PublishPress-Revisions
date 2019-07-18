<?php
/**
 * Plugin Name: Revisionary
 * Plugin URI: https://publishpress.com/
 * Description: Enables qualified users to submit changes to currently published posts or pages.  These changes, if approved by an Editor, can be published immediately or scheduled for future publication.
 * Author: PublishPress
 * Author URI: https://publishpress.com
 * Version: 1.3.8
 * Text Domain: revisionary
 * Domain Path: /languages/
 * Min WP Version: 4.1
 * 
 * Copyright (c) 2019 PublishPress
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
 * @package     Revisionary
 * @category    Core
 * @author      Revisionary
 * @copyright   Copyright (C) 2019 PublishPress. All rights reserved.
 *
 **/

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

if ( strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/index-extra.php' ) || strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/update.php' ) )
	return;

if ( defined( 'RVY_VERSION' ) ) {
	// don't allow two copies to run simultaneously
	if ( is_admin() && strpos( $_SERVER['SCRIPT_NAME'], 'p-admin/plugins.php' ) && ! strpos( urldecode($_SERVER['REQUEST_URI']), 'deactivate' ) ) {
		if ( defined( 'RVY_FOLDER' ) )
			$message = sprintf( __( 'Another copy of Revisionary is already activated (version %1$s in "%2$s")', 'rvy' ), RVY_VERSION, RVY_FOLDER );
		else
			$message = sprintf( __( 'Another copy of Revisionary is already activated (version %1$s)', 'rvy' ), RVY_VERSION );
		
		rvy_notice($message);
	}
	return;
}

define ('REVISIONARY_VERSION', '1.3.8');
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

// define URL
define ('RVY_BASENAME', plugin_basename(__FILE__) );
define ('RVY_FOLDER', dirname( plugin_basename(__FILE__) ) );

require_once( dirname(__FILE__).'/rvy_init.php');	// Contains activate, deactivate, init functions. Adds mod_rewrite_rules.

// register these functions before any early exits so normal activation/deactivation can still run with RS_DEBUG
register_activation_hook(__FILE__, 'rvy_activate');

// avoid lockout in case of editing plugin via wp-admin
if ( defined('RS_DEBUG') && is_admin() && ( strpos( urldecode($_SERVER['REQUEST_URI']), 'p-admin/plugin-editor.php' ) || strpos( urldecode($_SERVER['REQUEST_URI']), 'p-admin/plugins.php' ) ) && false === strpos( $_SERVER['REQUEST_URI'], 'activate' ) )
	return;

if ( ! defined('WP_CONTENT_URL') )
	define( 'WP_CONTENT_URL', site_url( 'wp-content', $scheme ) );

if ( ! defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', str_replace('\\', '/', ABSPATH) . 'wp-content' );

define ('RVY_ABSPATH', WP_CONTENT_DIR . '/plugins/' . RVY_FOLDER);

require_once( dirname(__FILE__).'/defaults_rvy.php');

rvy_refresh_options_sitewide();

// since sequence of set_current_user and init actions seems unreliable, make sure our current_user is loaded first
add_action('init', 'rvy_init', 1);
add_action('init', 'rvy_add_revisor_custom_caps', 99);
