<?php
global $pagenow, $revisionary;

add_action( 'init', '_rvy_post_edit_ui' );

if ( in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php', 'plugins.php' ) ) ) { 
	add_action( 'all_admin_notices', '_rvy_intro_notice' );

	if (get_site_transient('_revisionary_1x_migration')) {
		add_action( 'all_admin_notices', '_rvy_migration_notice' );
	}
}

function _rvy_post_edit_ui() {
	global $pagenow, $revisionary;

	if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
		if ( $pagenow == 'post.php' ) {
			require_once( dirname(__FILE__).'/post-edit_rvy.php' );
			new RvyPostEdit();
		}

		if ( $revisionary->isBlockEditorActive() ) {
			require_once( dirname(__FILE__).'/post-edit-block-ui_rvy.php' );
		}
	}
}

function rvy_load_textdomain() {
	if ( defined('RVY_TEXTDOMAIN_LOADED') )
		return;

	load_plugin_textdomain('revisionary', false, RVY_FOLDER . '/languages');

	define('RVY_TEXTDOMAIN_LOADED', true);
}

function rvy_admin_init() {
	// @todo: clean up "Restore Revision" URL on Diff screen
	if (!empty($_GET['amp;revision']) && !empty($_GET['amp;action']) && !empty($_GET['amp;_wpnonce'])) {
		$_GET['revision'] = $_GET['amp;revision'];
		$_GET['action'] = $_GET['amp;action'];
		$_GET['_wpnonce'] = $_GET['amp;_wpnonce'];
		$_REQUEST['revision'] = $_REQUEST['amp;revision'];
		$_REQUEST['action'] = $_REQUEST['amp;action'];
		$_REQUEST['_wpnonce'] = $_REQUEST['amp;_wpnonce'];
	}

	if ( ! empty($_POST['rvy_submit']) || ! empty($_POST['rvy_defaults']) ) {
		require_once( RVY_ABSPATH . '/submittee_rvy.php');	
		$handler = new Revisionary_Submittee();
	
		if ( isset($_POST['rvy_submit']) ) {
			$sitewide = isset($_POST['rvy_options_doing_sitewide']);
			$customize_defaults = isset($_POST['rvy_options_customize_defaults']);
			$handler->handle_submission( 'update', $sitewide, $customize_defaults );
			
		} elseif ( isset($_POST['rvy_defaults']) ) {
			$sitewide = isset($_POST['rvy_options_doing_sitewide']);
			$customize_defaults = isset($_POST['rvy_options_customize_defaults']);
			$handler->handle_submission( 'default', $sitewide, $customize_defaults );
		}
		
	// don't bother with the checks in this block unless action arg was passed or rvy_compare_revs field was posted
	} elseif ( ! empty($_GET['action']) || ! empty( $_POST['rvy_compare_revs'] ) || ! empty($_POST['action']) ) {
		if ( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions') ) {
			if ( ! empty( $_POST['rvy_compare_revs'] ) ) {
				require_once( dirname(__FILE__).'/revision-action_rvy.php');	
				add_action( 'wp_loaded', 'rvy_revision_diff' );
				
			} elseif ( ! empty($_GET['action']) && ('restore' == $_GET['action']) ) {
				require_once( dirname(__FILE__).'/revision-action_rvy.php');	
				add_action( 'wp_loaded', 'rvy_revision_restore' );
		
			} elseif ( ! empty($_GET['action']) && ('delete' == $_GET['action']) ) {
				require_once( dirname(__FILE__).'/revision-action_rvy.php');	
				add_action( 'wp_loaded', 'rvy_revision_delete' );
				
			} elseif ( ! empty($_GET['action']) && ('approve' == $_GET['action']) ) {
				require_once( dirname(__FILE__).'/revision-action_rvy.php');	
				add_action( 'wp_loaded', 'rvy_revision_approve' );
				
			} elseif ( ! empty($_GET['action']) && ('publish' == $_GET['action']) ) {
				require_once( dirname(__FILE__).'/revision-action_rvy.php');	
				add_action( 'wp_loaded', 'rvy_revision_publish' );

			} elseif ( ! empty($_GET['action']) && ('unschedule' == $_GET['action']) ) {
				require_once( dirname(__FILE__).'/revision-action_rvy.php');	
				add_action( 'wp_loaded', 'rvy_revision_unschedule' );

			} elseif ( ! empty($_POST['action']) && ('bulk-delete' == $_POST['action'] ) ) {
				require_once( dirname(__FILE__).'/revision-action_rvy.php');	
				add_action( 'wp_loaded', 'rvy_revision_bulk_delete' );
			}
		}
		
	} // endif action arg passed

	if (defined('REVISIONARY_PRO_VERSION') && !empty($_REQUEST['rvy_ajax_settings'])) {
		include_once(RVY_ABSPATH . '/includes-pro/pro-activation-ajax.php');
	}

	if (defined('REVISIONARY_PRO_VERSION') && !empty($_REQUEST['rvy_refresh_updates'])) {
		revisionary()->keyStatus(true);
        set_transient('revisionary-pro-refresh-update-info', true, 86400);

		delete_site_transient('update_plugins');
		delete_option('_site_transient_update_plugins');

		wp_update_plugins();
		//wp_version_check(array(), true);

		if (current_user_can('update_plugins')) {
			$url = remove_query_arg('rvy_refresh_updates', $_SERVER['REQUEST_URI']);
			$url = add_query_arg('rvy_refresh_done', 1, $url);
			$url = "//" . $_SERVER['HTTP_HOST'] . $url;
			wp_redirect($url);
			exit;
		}
	}

	if (!empty($_REQUEST['rvy_refresh_done']) && empty($_POST)) {
		if (current_user_can('activate_plugins')) {
			$url = admin_url('update-core.php');
			wp_redirect($url);
		}
	}
}

function rvy_intro_message($abbreviated = false) {
	
	$guide_link = sprintf(
		__('For more details on setting up PublishPress Revisions, %sread this guide%s.', 'revisionary'),
		'<a href="https://publishpress.com/documentation/revisionary-start" target="_blank">',
		'</a>'
	);

	return ($abbreviated) ? $guide_link : sprintf(
		__('<strong>Welcome to PublishPress Revisions!</strong> Here&apos;s how it works:%s<li>"Contributors" can submit revisions to their published posts.</li><li>"Revisors" can submit revisions to posts and pages published by others.</li><li>"Authors", "Editors" and "Administrators" can approve revisions or schedule their own revisions.</li>%s%s%s', 'revisionary'),
		'<ul style="list-style-type:disc; padding-left:10px;margin-bottom:0">',
		'</ul><p>',
		$guide_link,
		'</p>'
	);
}

function rvy_migration_message() {
	return sprintf(
		__('<strong>Revisionary is now PublishPress Revisions!</strong> Note the new Revisions menu and %sRevision Queue%s screen, where Pending and Scheduled Revisions are listed. %s', 'revisionary'),
		'<a href="' . admin_url('admin.php?page=revisionary-q') . '">',
		'</a>',
		'<div style="margin-top:10px">' . rvy_intro_message(true) . '</div>'
	);
}

function _rvy_intro_notice() {
	if ( current_user_can( 'edit_users') ) {
		rvy_dismissable_notice( 'intro_revisor_role', rvy_intro_message());
	}
}

function _rvy_migration_notice() {
	if ( current_user_can( 'edit_users') ) {
		rvy_dismissable_notice( 'revisionary_migration', rvy_migration_message());
	}
}

function rvy_dismissable_notice( $msg_id, $message ) {
	$dismissals = (array) rvy_get_option( 'dismissals' );

	if ( ! isset( $dismissals[$msg_id] ) ) :
		$class = 'rvy-admin-notice rvy-admin-notice-plugin';
		?>
		<div class='updated rvy-notice' class='<?php echo $class;?>' id='rvy_dashboard_message'>
		<span style="float:right"><a href="javascript:void(0);" onclick="RvyDismissNotice();"><?php _e("Dismiss", "pp") ?></a></span>
		<?php echo $message ?></div>
		<script type="text/javascript">
			function RvyDismissNotice(){
				jQuery("#rvy_dashboard_message").slideUp();
				jQuery.post(ajaxurl, {action:"rvy_dismiss_msg", msg_id:"<?php echo $msg_id ?>", cookie: encodeURIComponent(document.cookie)});
			}
		</script>
	<?php 
	endif;
}