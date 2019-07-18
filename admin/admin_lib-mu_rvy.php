<?php

function rvy_mu_site_menu() {	
	if ( ! current_user_can( 'manage_options' ) )
		return;

	$path = RVY_ABSPATH;
	
	// WP MU site options
	if ( awp_is_mu() ) {
		// Network-Wide Settings
		add_options_page( __('Revisionary Network Settings', 'revisionary'), __('Revisionary Network', 'revisionary'), 'read', 'rvy-site_options');
		add_action( "settings_page_rvy-site_options", 'rvy_mu_include_options_sitewide' );	
		
		global $rvy_default_options, $rvy_options_sitewide;
		
		// omit Option Defaults menu item if all options are controlled network-wide
		if ( empty($rvy_default_options) )
			rvy_refresh_default_options();
		
		if ( count($rvy_options_sitewide) != count($rvy_default_options) ) {
			// Default Options (for per-site settings)
			add_options_page( __('Revisionary Network Defaults', 'revisionary'), __('Revisionary Defaults', 'revisionary'), 'read', 'rvy-default_options');
			add_action("settings_page_rvy-default_options", 'rvy_mu_include_options' );	
		}
	}
}

function rvy_mu_include_options_sitewide() {
	include_once( RVY_ABSPATH . '/admin/options.php');
	rvy_options( true );
}

function rvy_mu_include_options() {
	include_once( RVY_ABSPATH . '/admin/options.php');
	rvy_options( false, true );
}
