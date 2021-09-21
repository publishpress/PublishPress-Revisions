<?php

function rvy_mu_site_menu() {	
	if ( ! current_user_can( 'manage_options' ) )
		return;

	// WP MU site options
	if ( awp_is_mu() ) {
		// Network-Wide Settings
		add_submenu_page( 'revisionary-q', __('PublishPress Revisions Network Settings', 'revisionary'), __('Network Settings', 'revisionary'), 'read', 'rvy-net_options', 'rvy_mu_include_options_sitewide');
		add_action( 'revisionary_page_rvy-net_options', 'rvy_mu_include_options_sitewide' );	

		global $rvy_default_options, $rvy_options_sitewide;
		
		// omit Option Defaults menu item if all options are controlled network-wide
		if ( empty($rvy_default_options) )
			rvy_refresh_default_options();
		
		if ( count($rvy_options_sitewide) != count($rvy_default_options) ) {
			// Default Options (for per-site settings)
			add_submenu_page( 'revisionary-q', __('PublishPress Revisions Network Defaults', 'revisionary'), __('Network Defaults', 'revisionary'), 'read', 'rvy-default_options', 'rvy_mu_include_options');
			add_action( 'revisionary_page_rvy-default_options', 'rvy_mu_include_options' );	
		}
	}
}

function rvy_mu_include_options_sitewide() {
	rvy_settings_scripts();
	include_once( RVY_ABSPATH . '/admin/options.php');
	rvy_options( true );
}

function rvy_mu_include_options() {
	include_once( RVY_ABSPATH . '/admin/options.php');
	rvy_options( false, true );
}
