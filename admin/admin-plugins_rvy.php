<?php
class Rvy_Plugin_Admin {
	function __construct() {
		add_filter( 'plugin_row_meta', array( &$this, 'flt_plugin_action_links' ), 10, 2 );
	}
	
	// adds a Settings link in Plugins listing
	function flt_plugin_action_links($links, $file) {
		if ( ! is_network_admin() ) {
			$links[] = "<a href='" . admin_url('admin.php?page=revisionary-settings') . "'>" . _pp_('Settings') . "</a>";
		}
	
		return $links;
	}
} // end class
