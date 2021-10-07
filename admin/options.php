<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

do_action('revisionary_load_options_ui');

class RvyOptionUI {
	private static $instance = null;

	private $sitewide;
	private $customize_defaults;
	var $form_options;
	private $tab_captions;
	var $section_captions;
	var $option_captions;
	private $all_options;
	private $all_otype_options;
	private $def_otype_options;
	private $display_hints = true;

	public static function instance($args = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new RvyOptionUI($args);
        }

        return self::$instance;
    }

    private function __construct($args = [])
    {
		$defaults = ['sitewide' => false, 'customize_defaults' => false];
		$args = array_merge($defaults, (array)$args);
		
		$this->sitewide = $args['sitewide'];
		$this->customize_defaults = $args['customize_defaults'];
		$this->display_hints = rvy_get_option( 'display_hints' );
    }
	
	function option_checkbox( $option_name, $tab_name, $section_name, $hint_text, $trailing_html, $args = '') {
		$return = array( 'in_scope' => false, 'val' => '', 'subcaption' => '' );
		
		if ( ! is_array($args) )
			$args = array();

		if ( in_array( $option_name, $this->form_options[$tab_name][$section_name] ) ) {
			$this->all_options []= $option_name;
			
			$return['val'] = rvy_get_option($option_name, $this->sitewide, $this->customize_defaults);
				
			$js_clause = ( ! empty($args['js_call']) ) ? 'onclick="' . $args['js_call'] . '"'  : '';
			$style = ( ! empty($args['style']) ) ? $args['style'] : '';
			
			echo "<div class='agp-vspaced_input'>"
				. "<label for='$option_name'><input name='$option_name' type='checkbox' $js_clause $style id='$option_name' value='1' " . checked('1', $return['val'], false) . " /> "
				. $this->option_captions[$option_name]
				. "</label>";
				
			if ( $hint_text && $this->display_hints )
				echo "<div class='rs-subtext'>" . $hint_text . "</div>";
				
			if ( ! empty($args['subcaption']) )
				echo $args['subcaption'];
				
			echo "</div>";

			if ( $trailing_html )
				echo $trailing_html;
			
			$return['in_scope'] = true;	
		}
		
		return $return;
	}
	
function options_ui( $sitewide = false, $customize_defaults = false ) {

global $revisionary;

if ( ! current_user_can( 'manage_options' ) || ( $sitewide && ! is_super_admin() ) )
	wp_die(__awp('Cheatin&#8217; uh?'));

?>
<div class="wrap pressshack-admin-wrapper">
<?php

if ( $sitewide )
	$customize_defaults = false;	// this is intended only for storing custom default values for site-specific options

rvy_refresh_default_options();

$this->all_options = array();

$this->tab_captions = array( 'features' => __( 'Settings', 'revisionary' ), 'optscope' => __( 'Setting Scope', 'revisionary' ) );

$this->section_captions = array(
	'features' => array(
		'role_definition' 	  	=> __('Role Definition', 'revisionary'),
		'scheduled_revisions' 	=> __('Scheduled Revisions', 'revisionary'),
		'pending_revisions'		=> __('Pending Revisions', 'revisionary'),
		'revision_queue'		=> __('Revision Queue', 'revisionary'),		
		'preview'				=> __('Preview / Approval', 'revisionary'),
		'revisions'				=> __('Revision Options', 'revisionary'),
		'notification'			=> __('Email Notification', 'revisionary')
	)
);

// TODO: replace individual _e calls with these (and section, tab captions)
$this->option_captions = apply_filters('revisionary_option_captions', 
	[
	'pending_revisions' => 						__('Enable Pending Revisions', 'revisionary'),
	'scheduled_revisions' => 					__('Enable Scheduled Revisions', 'revisionary'),
	'revisor_lock_others_revisions' =>			__("Editing others&apos; revisions requires role capability", 'revisionary'),
	'revisor_hide_others_revisions' => 			__("Listing others&apos; revisions requires role capability", 'revisionary'),
	'queue_query_all_posts' => 					__('Compatibility Mode', 'revisionary'),
	'revision_update_redirect' =>				__('Confirmation redirect on Revision Update', 'revisionary'),
	'revision_update_notifications' =>			__('Also notify on Revision Update', 'revisionary'),
	'revision_submit_trigger_post_actions' => 	__('Revision Submission: API actions for Post Creation', 'revisionary'),
	'trigger_post_update_actions' => 			__('Revision Publication: API actions to mimic Post Update', 'revisionary'),
	'diff_display_strip_tags' => 				__('Hide html tags on Compare Revisions screen', 'revisionary'),
	'async_scheduled_publish' => 				__('Asynchronous Publishing', 'revisionary'),
	'scheduled_revision_update_post_date' => 	__('Update Publish Date', 'revisionary'),
	'pending_revision_update_post_date' => 		__('Update Publish Date', 'revisionary'),
	'scheduled_revision_update_modified_date' => __('Update Modified Date', 'revisionary'),
	'pending_revision_update_modified_date' => 	__('Update Modified Date', 'revisionary'),
	'pending_rev_notify_author' => 				__('Email original Author when a Pending Revision is submitted', 'revisionary'),
	'rev_approval_notify_author' => 			__('Email the original Author when a Pending Revision is approved', 'revisionary'),
	'rev_approval_notify_revisor' => 			__('Email the Revisor when a Pending Revision is approved', 'revisionary'),
	'publish_scheduled_notify_author' => 		__('Email the original Author when a Scheduled Revision is published', 'revisionary'),
	'publish_scheduled_notify_revisor' => 		__('Email the Revisor when a Scheduled Revision is published', 'revisionary'),
	'use_notification_buffer' => 				__('Enable notification buffer', 'revisionary'),
	'revisor_role_add_custom_rolecaps' => 		__('All custom post types available to Revisors', 'revisionary' ),
	'require_edit_others_drafts' => 			__('Prevent Revisors from editing other user&apos;s drafts', 'revisionary' ),
	'display_hints' => 							__('Display Hints'),
	'revision_preview_links' => 				__('Show Preview Links', 'revisionary'),
	'preview_link_type' => 						__('Preview Link Type', 'revisionary'),
	'compare_revisions_direct_approval' => 		__('Approve Button on Compare Revisions screen', 'revisionary'),
	'copy_revision_comments_to_post' => 		__('Copy revision comments to published post', 'revisionary'),
	'past_revisions_order_by' =>				__('Compare Past Revisions ordering:')
	]
);


if ( defined('RVY_CONTENT_ROLES') ) {
	$this->option_captions['pending_rev_notify_admin'] = 		__('Email designated Publishers when a Pending Revision is submitted', 'revisionary');
	$this->option_captions['publish_scheduled_notify_admin'] = 	__('Email designated Publishers when a Scheduled Revision is published', 'revisionary');
	$this->option_captions['rev_approval_notify_admin'] = 		__('Email designated Publishers when a Pending Revision is approved', 'revisionary');
} else {
	$this->option_captions['pending_rev_notify_admin'] = 		__('Email Editors and Administrators when a Pending Revision is submitted', 'revisionary');
	$this->option_captions['publish_scheduled_notify_admin'] = 	__('Email Editors and Administrators when a Scheduled Revision is published', 'revisionary');
	$this->option_captions['rev_approval_notify_admin'] = 		__('Email Editors and Administrators when a Pending Revision is approved', 'revisionary');
}
	

$this->form_options = apply_filters('revisionary_option_sections', [ 
'features' => [
	'license' =>			 ['edd_key'],
	'role_definition' => 	 ['revisor_role_add_custom_rolecaps', 'require_edit_others_drafts'],
	'scheduled_revisions' => ['scheduled_revisions', 'async_scheduled_publish', 'scheduled_revision_update_post_date', 'scheduled_revision_update_modified_date'],
	'pending_revisions'	=> 	 ['pending_revisions', 'pending_revision_update_post_date', 'pending_revision_update_modified_date'],
	'revision_queue' =>		 ['revisor_lock_others_revisions', 'revisor_hide_others_revisions', 'queue_query_all_posts'],
	'preview' =>			 ['revision_preview_links', 'preview_link_type', 'compare_revisions_direct_approval'],
	'revisions'		=>		 ['revision_submit_trigger_post_actions', 'trigger_post_update_actions', 'copy_revision_comments_to_post', 'diff_display_strip_tags', 'past_revisions_order_by', 'display_hints'],
	'notification'	=>		 ['pending_rev_notify_admin', 'pending_rev_notify_author', 'revision_update_redirect', 'revision_update_notifications', 'rev_approval_notify_admin', 'rev_approval_notify_author', 'rev_approval_notify_revisor', 'publish_scheduled_notify_admin', 'publish_scheduled_notify_author', 'publish_scheduled_notify_revisor', 'use_notification_buffer'],
]
]);

if ( RVY_NETWORK ) {
	if ( $sitewide )
		$available_form_options = $this->form_options;
	
	global $rvy_options_sitewide;
	
	foreach ( $this->form_options as $tab_name => $sections )
		foreach ( $sections as $section_name => $option_names ) {
			if ( $sitewide )
				$this->form_options[$tab_name][$section_name] = array_intersect( $this->form_options[$tab_name][$section_name], array_keys($rvy_options_sitewide) );
			else
				$this->form_options[$tab_name][$section_name] = array_diff( $this->form_options[$tab_name][$section_name], array_keys($rvy_options_sitewide) );
		}
				
	foreach ( $this->form_options as $tab_name => $sections )
		foreach ( array_keys($sections) as $section_name )
			if ( empty( $this->form_options[$tab_name][$section_name] ) )
				unset( $this->form_options[$tab_name][$section_name] );
}

do_action('revisionary_settings_ui', $this, $sitewide, $customize_defaults);
?>
<header>
<!-- <div class='wrap'> -->
<?php
echo '<form action="" method="post">';
wp_nonce_field( 'rvy-update-options' );

if ( $sitewide )
	echo "<input type='hidden' name='rvy_options_doing_sitewide' value='1' />";
	
if ( $customize_defaults )
	echo "<input type='hidden' name='rvy_options_customize_defaults' value='1' />";
	
?>
<table width = "100%"><tr>
<td width = "90%">
<h1><?php 

echo '<span class="dashicons dashicons-backup"></span>&nbsp;';

if ( $sitewide )
	_e('PublishPress Revisions Network Settings', 'revisionary');
elseif ( $customize_defaults )
	_e('PublishPress Revisions Network Defaults', 'revisionary');
elseif ( RVY_NETWORK )
	_e('PublishPress Revisions Site Settings', 'revisionary');
else
	_e('PublishPress Revisions Settings', 'revisionary');
?>
</h1>
</td>
<td>
<div class="submit" style="border:none;float:right;margin:0;">
<input type="submit" name="rvy_submit" class="button-primary" value="<?php _e('Update &raquo;', 'revisionary');?>" />
</div>
</td>
</tr></table>

</header>

<?php
if ( $sitewide ) {
	$color_class = 'rs-backgray';
	
} elseif ( $customize_defaults ) {
	$color_class = 'rs-backgreen';
	echo '<p style="margin-top:0">';
	_e( 'These are the <strong>default</strong> settings for options which can be adjusted per-site.', 'revisionary' );
	echo '</p>';
	
} else
	$color_class = 'rs-settings';

if ( $sitewide || $customize_defaults ) {
	$class_selected = "agp-selected_agent agp-agent $color_class";
	$class_unselected = "agp-unselected_agent agp-agent";

	// todo: prevent line breaks in these links
	$js_call = "agp_swap_display('rvy-features', 'rvy-optscope', 'rvy_show_features', 'rvy_show_optscope', '$class_selected', '$class_unselected');";
	echo "<ul class='rs-list_horiz' style='margin-bottom:-0.1em'>"
		. "<li class='$class_selected'>"
		. "<a id='rvy_show_features' href='javascript:void(0)' onclick=\"$js_call\">" . $this->tab_captions['features'] . '</a>'
		. '</li>';

	if ( $sitewide ) {
		$js_call = "agp_swap_display('rvy-optscope', 'rvy-features', 'rvy_show_optscope', 'rvy_show_features', '$class_selected', '$class_unselected');";
		echo "<li class='$class_unselected'>"
			. "<a id='rvy_show_optscope' href='javascript:void(0)' onclick=\"$js_call\">" . $this->tab_captions['optscope'] . '</a>'
			. '</li>';
	}

	echo '</ul>';
}

// ------------------------- BEGIN Features tab ---------------------------------

$tab = 'features';
echo "<div id='rvy-features' style='clear:both;margin:0' class='rs-options $color_class'>";
	
if ( rvy_get_option('display_hints', $sitewide, $customize_defaults) ) {
	echo '<div class="rs-optionhint publishpress-headline"><span>';
	
	if ( $sitewide ) {
		global $rvy_options_sitewide, $rvy_default_options;
		$site_defaults_caption = ( count( $rvy_options_sitewide ) < count( $rvy_default_options ) ) ? sprintf( __( 'You can also specify %1$sdefaults for site-specific settings%2$s.', 'revisionary' ), '<a href="admin.php?page=rvy-default_options">', '</a>' ) : '';
		printf( __('Use this tab to make <strong>NETWORK-WIDE</strong> changes to PublishPress Revisions settings. %s', 'revisionary'), $site_defaults_caption );
	} elseif ( $customize_defaults ) {
		_e('Here you can change the default value for settings which are controlled separately on each site.', 'revisionary');
	}

	if ( RVY_NETWORK && is_super_admin() ) {
		if ( ! $sitewide ) {
			global $blog_id;
			if ( is_main_site($blog_id) ) {
				$link_open = "<a href='admin.php?page=rvy-net_options'>";
				$link_close = '</a>';
			} else {
				$link_open = '';
				$link_close = '';
			}
			echo ' ';
			printf( __('Note that %1$s network-wide settings%2$s may also be available.', 'revisionary'), $link_open, $link_close );
		}
	}
	?>
	</span>

	</div>
	<?php
}

$table_class = 'form-table rs-form-table';
?>

<table class="<?php echo($table_class);?>" id="rs-admin_table">

<?php
// possible TODO: replace redundant hardcoded IDs with $id

	if (defined('REVISIONARY_PRO_VERSION')) {
		require_once(RVY_ABSPATH . '/includes-pro/SettingsLicense.php');
		$license_ui = new RevisionaryLicenseSettings();
		$license_ui->display($sitewide, $customize_defaults);
	}

	$section = 'role_definition';			// --- ROLE DEFINITION SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<tr valign="top"><th scope="row">
		<?php echo $this->section_captions[$tab][$section]; ?>
		</th><td>
		
		<?php 
		$hint = __('The user role "Revisor" role is now available. Include capabilities for all custom post types in this role?', 'revisionary');
		$this->option_checkbox( 'revisor_role_add_custom_rolecaps', $tab, $section, $hint, '' );
		?>
		
		<?php
		$hint = __( 'If checked, users lacking site-wide publishing capabilities will also be checked for the edit_others_drafts capability', 'revisionary' );
		$this->option_checkbox( 'require_edit_others_drafts', $tab, $section, $hint, '' );
		?>
		
		</td></tr>
	<?php endif; // any options accessable in this section

$pending_revisions_available = ! RVY_NETWORK || $sitewide || empty( $rvy_options_sitewide['pending_revisions'] ) || rvy_get_option( 'pending_revisions', true );
$scheduled_revisions_available = ! RVY_NETWORK || $sitewide || empty( $rvy_options_sitewide['scheduled_revisions'] ) || rvy_get_option( 'scheduled_revisions', true );

if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
$scheduled_revisions_available ) :

	$section = 'scheduled_revisions';			// --- SCHEDULED REVISIONS SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<tr valign="top"><th scope="row">
		<?php echo $this->section_captions[$tab][$section]; ?>
		</th><td>
		
		<?php 
		$hint = __( 'If a currently published post or page is edited and a future date set, the change will not be applied until the selected date.', 'revisionary' );
		$this->option_checkbox( 'scheduled_revisions', $tab, $section, $hint, '' );
		
		$hint = __( 'When a scheduled revision is published, update post publish date to current time.', 'revisionary' );
		$this->option_checkbox( 'scheduled_revision_update_post_date', $tab, $section, $hint, '' );

		$hint = __( 'When a scheduled revision is published, update post modified date to current time.', 'revisionary' );
		$this->option_checkbox( 'scheduled_revision_update_modified_date', $tab, $section, $hint, '' );

		$hint = __( 'Publish scheduled revisions asynchronously, via a secondary http request from the server.  This is usually best since it eliminates delay, but some servers may not support it.', 'revisionary' );
		$this->option_checkbox( 'async_scheduled_publish', $tab, $section, $hint, '' );
		?>
		</td></tr>
	<?php endif; // any options accessable in this section
endif;

if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
$pending_revisions_available ) :
	$section = 'pending_revisions';			// --- PENDING REVISIONS SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<tr valign="top"><th scope="row">
		<?php echo $this->section_captions[$tab][$section]; ?>
		</th><td>
		
		<?php 
		$hint = sprintf(
			__( 'Enable Contributors to submit revisions to their own published content. Revisors and users who have the edit_others (but not edit_published) capability for the post type can submit revisions to other user\'s content. These Pending Revisions are listed in %sRevision Queue%s.', 'revisionary' ),
			"<a href='" . admin_url('admin.php?page=revisionary-q') . "'>",
			'</a>'	
		);
		$this->option_checkbox( 'pending_revisions', $tab, $section, $hint, '' );
		
		$hint = __( 'When a pending revision is published, update post publish date to current time.', 'revisionary' );
		$this->option_checkbox( 'pending_revision_update_post_date', $tab, $section, $hint, '' );

		$hint = __( 'When a pending revision is published, update post modified date to current time.', 'revisionary' );
		$this->option_checkbox( 'pending_revision_update_modified_date', $tab, $section, $hint, '' );

		do_action('revisionary_option_ui_pending_revisions', $this);
		?>
		</td></tr>
	<?php endif; // any options accessable in this section
endif;


if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
	$pending_revisions_available || $scheduled_revisions_available ) :
	
		$section = 'revision_queue';			// --- REVISION QUEUE SECTION ---
	
		if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
			<tr valign="top"><th scope="row">
			<?php echo $this->section_captions[$tab][$section]; ?>
			</th><td>

			<?php 
			$hint = __('This restriction applies to users who are not full editors for the post type. To enable a role, give it the edit_others_revisions capability.', 'revisionary');
			$this->option_checkbox( 'revisor_lock_others_revisions', $tab, $section, $hint, '' );
			
			$hint = __('This restriction applies to users who are not full editors for the post type. To enable a role, give it the list_others_revisions capability.', 'revisionary');
			$this->option_checkbox( 'revisor_hide_others_revisions', $tab, $section, $hint, '' );

			$hint = __('If some revisions are missing from the queue, disable a performance enhancement for better compatibility with themes and plugins.', 'revisionary');
			$this->option_checkbox( 'queue_query_all_posts', $tab, $section, $hint, '' );
		?>

		<p style="padding-left:22px">
		<a href="<?php echo add_query_arg('rvy_flush_flags', 1, esc_url($_SERVER['REQUEST_URI']))?>"><?php _e('Regenerate "post has revision" flags', 'revisionary');?></a>
		</p>

		</td></tr>
	<?php endif; // any options accessable in this section
endif;


$section = 'preview';			// --- PREVIEW SECTION ---
		
if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
	<tr valign="top"><th scope="row">
	<?php echo $this->section_captions[$tab][$section]; ?>
	</th><td>
		
	<?php 
	$hint = __('For themes that block revision preview, hide preview links from non-Administrators', 'revisionary');
	$this->option_checkbox( 'revision_preview_links', $tab, $section, $hint, '' );

	$id = 'preview_link_type';
	if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
		$this->all_options []= $id;
		$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);
		
		echo '<div style="padding-left: 25px">';
		echo "<label for='$id'>" . $this->option_captions[$id] . ': </label>';

		echo " <select name='$id' id='$id'>";
		$captions = array( '' => __('Published Post Slug', 'revisionary'), 'revision_slug' => __('Revision Slug', 'revisionary'), 'id_only' => __('Revision ID only', 'revisionary') );
		foreach ( $captions as $key => $value) {
			$selected = ( $current_setting == $key ) ? 'selected="selected"' : '';
			echo "\n\t<option value='$key' " . $selected . ">$captions[$key]</option>";
		}
		echo '</select>&nbsp;';

		if ( $this->display_hints ) : ?>
			<br />
			<div class="rs-subtext">
			<?php
			_e('Some themes or plugins may require Revision Slug or Revision ID link type for proper template loading and field display.', 'revisionary');
			?>
			</div>
		<?php endif;
		?>
		</div>
		<br />
		<?php
	}

	$hint = __('If disabled, Compare screen links to Revision Preview for approval', 'revisionary');
	$this->option_checkbox( 'compare_revisions_direct_approval', $tab, $section, $hint, '' );
	?>
	</td></tr>
<?php endif; // any options accessable in this section


if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
$pending_revisions_available || $scheduled_revisions_available ) :

	$section = 'revisions';			// --- REVISIONS SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<tr valign="top"><th scope="row">
		<?php echo $this->section_captions[$tab][$section]; ?>
		</th><td>
		
		<?php
		if (!defined('REVISIONARY_PRO_VERSION')) :
		$descript = sprintf(
			__('For compatibility with Advanced Custom Fields, Beaver Builder and WPML, upgrade to <a href="%s" target="_blank">PublishPress Revisions Pro</a>.', 'revisionary'),
			'https://publishpress.com/revisionary/'
		);
		?>

		<div id="revisions-pro-descript" class="activating"><?php echo $descript; ?></div>
		<?php endif;?>

		<?php
		$hint = __('Some plugins may need this disabled.', 'revisionary');
		$this->option_checkbox( 'revision_submit_trigger_post_actions', $tab, $section, $hint, '' );

		$hint = __('This may improve compatibility with some plugins.', 'revisionary');
		$this->option_checkbox( 'trigger_post_update_actions', $tab, $section, $hint, '' );

		$hint = '';
		$this->option_checkbox( 'copy_revision_comments_to_post', $tab, $section, $hint, '' );

		$hint = '';
		$this->option_checkbox( 'diff_display_strip_tags', $tab, $section, $hint, '' );

		echo "<br />";

		$id = 'past_revisions_order_by';
		if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
			echo $this->option_captions[$id];
			
			$this->all_options []= $id;
			$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);
			
			echo " <select name='$id' id='$id'>";
			$captions = ['' => __('Post Date', 'revisionary'), 'modified' => __('Modification Date', 'revisionary')];
			foreach ( $captions as $key => $value) {
				$selected = ( $current_setting == $key ) ? 'selected="selected"' : '';
				echo "\n\t<option value='$key' " . $selected . ">$captions[$key]</option>";
			}
			echo '</select>&nbsp;';
			
			echo "<br /><br />";
		}

		$hint = __( 'Show descriptive captions for PublishPress Revisions settings', 'revisionary' );
		$this->option_checkbox( 'display_hints', $tab, $section, $hint, '' );

		do_action('revisionary_option_ui_revision_options', $this);
		?>

		</td></tr>
	<?php endif; // any options accessable in this section
		
	
	$section = 'notification';			// --- NOTIFICATION SECTION ---
		
	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<tr valign="top"><th scope="row">
		<?php echo $this->section_captions[$tab][$section]; ?>
		</th><td>
		
		<?php 
		if( $pending_revisions_available ) {
			$subcaption = ( defined('RVY_CONTENT_ROLES') && $group_link = $revisionary->content_roles->get_metagroup_edit_link( 'Pending Revision Monitors' ) ) ?
				sprintf( " &bull;&nbsp;<a href='%s'>" . __('select recipients', 'revisionary') . "</a>", $group_link ) : '';
			
			// TODO: $this->option_dropdown() method
			$id = 'pending_rev_notify_admin';
			if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
				$this->all_options []= $id;
				$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);
				
				echo "<select name='$id' id='$id'>";
				$captions = array( 0 => __('Never', 'revisionary'), 1 => __('By default', 'revisionary'), 'always' => __('Always', 'revisionary') );
				foreach ( $captions as $key => $value) {
					$selected = ( $current_setting == $key ) ? 'selected="selected"' : '';
					echo "\n\t<option value='$key' " . $selected . ">$captions[$key]</option>";
				}
				echo '</select>&nbsp;';
				
				echo $this->option_captions[$id];
				echo $subcaption;
				echo "<br />";
			}
			
			$id = 'pending_rev_notify_author';
			if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
				$this->all_options []= $id;
				$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);
				
				echo "<select name='$id' id='$id'>";
				$captions = array( 0 => __('Never', 'revisionary'), 1 => __('By default', 'revisionary'), 'always' => __('Always', 'revisionary') );
				foreach ( $captions as $key => $value) {
					$selected = ( $current_setting == $key ) ? 'selected="selected"' : '';
					echo "\n\t<option value='$key' " . $selected . ">$captions[$key]</option>";
				}
				echo '</select>&nbsp;';
				
				echo $this->option_captions[$id];
				echo "<br />";
			}
			
			if (rvy_get_option('pending_rev_notify_admin') || rvy_get_option('pending_rev_notify_author')) {
				$hint = '';
				$this->option_checkbox( 'revision_update_notifications', $tab, $section, $hint, '' );
			}

			$hint = '';
			$this->option_checkbox( 'revision_update_redirect', $tab, $section, $hint, '' );

			$hint = '';
			$this->option_checkbox( 'pending_rev_notify_revisor', $tab, $section, $hint, '' );
			
			echo '<br />';
			
			$hint = '';
			$this->option_checkbox( 'rev_approval_notify_admin', $tab, $section, $hint, '' );

			$hint = '';
			$this->option_checkbox( 'rev_approval_notify_author', $tab, $section, $hint, '' );
			
			$hint = '';
			$this->option_checkbox( 'rev_approval_notify_revisor', $tab, $section, $hint, '' );
		}
		
		if( $scheduled_revisions_available ) {
			echo '<br />';
					
			$subcaption = ( defined('RVY_CONTENT_ROLES') && $group_link = $revisionary->content_roles->get_metagroup_edit_link( 'Scheduled Revision Monitors' ) ) ?
				sprintf( " &bull;&nbsp;<a href='%s'>" . __('select recipients', 'revisionary') . "</a>", $group_link ) : '';

			$hint = '';
			$this->option_checkbox( 'publish_scheduled_notify_admin', $tab, $section, $hint, '', array( 'subcaption' => $subcaption ) );
			
			$hint = '';
			$this->option_checkbox( 'publish_scheduled_notify_author', $tab, $section, $hint, '' );
			
			$hint = '';
			$this->option_checkbox( 'publish_scheduled_notify_revisor', $tab, $section, $hint, '' );
		}
		
		if( $pending_revisions_available ) {
			if ( in_array( 'pending_rev_notify_admin', $this->form_options[$tab][$section] ) || in_array( 'pending_rev_notify_author', $this->form_options[$tab][$section] ) ) {
				if ( $this->display_hints ) {
					echo '<div class="rs-subtext">';
					if ( defined('RVY_CONTENT_ROLES') )
						_e('Note: "by default" means Pending Revision creators can customize email notification recipients before submitting.  Eligibile "Publisher" email recipients are members of the Pending Revision Monitors group who <strong>also</strong> have the ability to publish the revision.  If not explicitly defined, the Monitors group is all users with a primary WP role of Administrator or Editor.', 'revisionary');
					else
						printf( __('Note: "by default" means Pending Revision creators can customize email notification recipients before submitting.  For more flexibility in moderation and notification, install the %1$s PublishPress Permissions Pro%2$s plugin.', 'revisionary'), "<a href='https://publishpress.com/presspermit/'>", '</a>' );
					echo '</div>';
				}
			}
		}

		echo '<br />';

		$hint = __('To avoid notification failures, buffer emails for delayed sending once minute, hour or day limits are exceeded', 'revisionary');
		$this->option_checkbox( 'use_notification_buffer', $tab, $section, $hint, '' );
		
		if (!empty($_REQUEST['truncate_mail_log'])) {
			delete_option('revisionary_sent_mail');
		}

		if (!empty($_REQUEST['clear_mail_buffer'])) {
			delete_option('revisionary_mail_buffer');
		}

		$uri = esc_url($_SERVER['REQUEST_URI']);

		if (!empty($_REQUEST['mailinfo'])) {
			$verbose = !empty($_REQUEST['verbose']);

			if ($q = get_option('revisionary_mail_buffer')) {
				echo '<h3>' . __('Notification Buffer', 'revisionary') . '</h3>';
				foreach($q as $row) {
					if (!$verbose) {
						unset($row['message']);
					} elseif(!empty($row['message'])) {
						$row['message'] = '<br />' . str_replace("\r\n", '<br />', $row['message']);
					}
					
					$row['time_gmt'] = gmdate('Y-m-d, H:i:s', $row['time_gmt']);
					if (isset($row['time'])) {
						$row['time'] = gmdate('Y-m-d, g:i:s a', $row['time']);
					}

					foreach($row as $k => $val) {
						if ($k != 'message') {
							echo "<b>$k</b> : $val<br />";
						}
					}

					if ($verbose && !empty($row['message'])) {
						echo "<b>message</b> : {$row['message']}<br />";
					}

					echo '<hr />';
				}
			}

			if ($log = get_option('revisionary_sent_mail')) {
				echo '<h3>' . __('Notification Log', 'revisionary') . '</h3>';
				foreach($log as $row) {
					if (!$verbose) {
						unset($row['message']);
					} elseif(!empty($row['message'])) {
						$row['message'] = '<br />' . str_replace("\r\n", '<br />', $row['message']);
					}

					$row['time_gmt'] = gmdate('Y-m-d, H:i:s', $row['time_gmt']);
					if (isset($row['time'])) {
						$row['time'] = gmdate('Y-m-d, g:i:s a', $row['time']);
					}

					foreach($row as $k => $val) {
						if ($k != 'message') {
							echo "<b>$k</b> : $val<br />";
						}
					}

					if ($verbose && !empty($row['message'])) {
						echo "<b>message</b> : {$row['message']}<br />";
					}

					echo '<hr />';
				}
			}

			if (get_option('revisionary_mail_buffer')):?>
				<br />
				<a href="<?php echo(add_query_arg('clear_mail_buffer', '1', $uri));?>"><?php _e('Purge Notification Buffer', 'revisionary');?></a>
				<br />
			<?php endif;?>

			<?php if (get_option('revisionary_sent_mail')):?>
				<br />
				<a href="<?php echo(add_query_arg('truncate_mail_log', '1', $uri));?>"><?php _e('Truncate Notification Log', 'revisionary');?></a>
			<?php endif;

			$mail_info = rvy_mail_check_buffer([], ['log_only' => true]);
			?>
			<br /><br />
			<p><?php printf(__('Sent in last minute: %d / %d', 'revisionary'), $mail_info->sent_counts['minute'], $mail_info->send_limits['minute']);?></p>
			<p><?php printf(__('Sent in last hour: %d / %d', 'revisionary'), $mail_info->sent_counts['hour'], $mail_info->send_limits['hour']);?></p>
			<p><?php printf(__('Sent in last day: %d / %d', 'revisionary'), $mail_info->sent_counts['day'], $mail_info->send_limits['day']);?></p>
			<?php
			if (!empty($q)) {
				if ($cron_timestamp = wp_next_scheduled('rvy_mail_buffer_hook')) {
					$wait_sec = $cron_timestamp - time();
					if ($wait_sec > 0) {
						echo '<br />';
						printf(__('Seconds until next buffer processing time: %d', 'revisionary'), $wait_sec);
					}
				}
			}
		} 

		if (empty($_REQUEST['mailinfo'])):?>
			<br />
			<div style="padding-left:22px">
			<a href="<?php echo(add_query_arg('mailinfo', '1', $uri));?>"><?php _e('Show Notification Log / Buffer', 'revisionary');?></a>
			<br /><br />
			<a href="<?php echo(add_query_arg('verbose', '1', add_query_arg('mailinfo', '1', $uri)));?>"><?php _e('Show with message content', 'revisionary');?></a>
			</div>
		<?php endif;
		
		?>
		</td></tr>

		<?php
		if ((defined('REVISIONARY_PRO_VERSION') || defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) && defined('ICL_SITEPRESS_VERSION') && defined('WPML_TM_VERSION')) :?>

		<tr valign="top"><th scope="row">
		<?php _e('WPML Translation Management', 'revisionary') ?>
		</th></td>
		<td>
		<p>
		<?php
		$url = admin_url('admin.php?page=revisionary-settings&rvy_wpml_sync_needs_update=1');
		?>
		<a href="<?php echo($url);?>"><?php _e('Sync "Needs Update" flags', 'revisionary');?></a>

		<div class="rs-subtext">
		<?php 
		_e('Set "Needs Update" for any post with translations which was updated (possibly by revision approval) more recently than its translations.', 'revisionary');
		?>
		</div>

		</p>
		</td></tr>

		<?php endif;?>

		<!--
		<tr valign="top"><th scope="row">
		<?php _e('Documentation', 'revisionary') ?>
		</th></td>
		<td>
		<p>
		<?php
		/* echo rvy_intro_message(true); */
		?>
		</p>
		</td></tr>
		-->
	<?php endif; // any options accessable in this section
	
endif;
?>
	
</table>

</div>


<?php


// ------------------------- BEGIN Option Scope tab ---------------------------------
$tab = 'optscope';

if ( $sitewide ) : ?>
<?php
echo "<div id='rvy-optscope' style='clear:both;margin:0' class='rs-options agp_js_hide $color_class'>";

echo '<ul>';
$all_movable_options = array();

$option_scope_stamp = __( 'network-wide control of "%s"', 'revisionary' );

unset($available_form_options['features']['license']);

foreach ( $available_form_options as $tab_name => $sections ) {
	echo '<li>';
	
	$explanatory_caption = __( 'Specify which PublishPress Revisions Settings to control network-wide. Unselected settings are controlled separately on each site.', 'revisionary' );

	if ( isset( $this->tab_captions[$tab_name] ) )
		$tab_caption = $this->tab_captions[$tab_name];
	else
		$tab_caption = $tab_name;

	echo '<div style="margin:1em 0 1em 0">';
	if ( count( $available_form_options ) > 1 ) {
		if ( $this->display_hints )
			printf( _x( '<span class="rs-h3text">%1$s</span> (%2$s)', 'option_tabname (explanatory note)', 'revisionary' ), $tab_caption, $explanatory_caption );
		else
			echo $tab_caption;
	} elseif ( $this->display_hints ) {
		echo $explanatory_caption;
	}

	echo '</div>';
	
	echo '<ul style="margin-left:2em">';

	foreach ( $sections as $section_name => $option_names ) {
		if ( empty( $sections[$section_name] ) )
			continue;
		
		echo '<li><strong>';

		if ( isset( $this->section_captions[$tab_name][$section_name] ) )
			echo $this->section_captions[$tab_name][$section_name];
		else
			echo ucwords(str_replace('_', ' ', $section_name));
		
		echo '</strong><ul style="margin-left:2em">';
			
		foreach ( $option_names as $option_name ) {
			if ( $option_name && $this->option_captions[$option_name] ) {
				$all_movable_options []= $option_name;
				echo '<li>';
				
				$disabled = ( in_array( $option_name, array( 'file_filtering', 'mu_sitewide_groups' ) ) ) ? "disabled='disabled'" : '';
				
				$id = "{$option_name}_sitewide";
				$val = isset( $rvy_options_sitewide[$option_name] );
				echo "<label for='$id'>";
				echo "<input name='rvy_options_sitewide[]' type='checkbox' id='$id' value='$option_name' $disabled " . checked('1', $val, false) . " />";

				printf( $option_scope_stamp, $this->option_captions[$option_name] );
					
				echo '</label></li>';
			}
		}
		
		echo '</ul><br />';
	}
	echo '</ul><br /><hr />';
}
echo '</ul>';

echo '</div>';

$all_movable_options = implode(',', $all_movable_options);
echo "<input type='hidden' name='rvy_all_movable_options' value='$all_movable_options' />";

endif; // any options accessable in this tab
// ------------------------- END Option Scope tab ---------------------------------



// this was required for Access Types section, which was removed
//$all = implode(',', $all_otypes);
//echo "<input type='hidden' name='all_object_types' value='$all' />";

$this->all_options = implode(',', $this->all_options);
echo "<input type='hidden' name='all_options' value='$this->all_options' />";

echo "<input type='hidden' name='rvy_submission_topic' value='options' />";
?>
<p class="submit" style="border:none;float:right">
<input type="submit" name="rvy_submit" class="button-primary" value="<?php _e('Update &raquo;', 'revisionary');?>" />
</p>

<?php
$msg = __( "All settings in this form (including those on unselected tabs) will be reset to DEFAULTS.  Are you sure?", 'revisionary' );
$js_call = "javascript:if (confirm('$msg')) {return true;} else {return false;}";
?>
<p class="submit" style="border:none;float:left">

<input type="submit" name="rvy_defaults" value="<?php _e('Revert to Defaults', 'revisionary') ?>" onclick="<?php echo $js_call;?>" />
</p>
</form>
<p style='clear:both'></p>

<?php 
global $revisionary;
$revisionary->admin->publishpressFooter();
?>

</div>

<!--</div>-->

<?php
} // end function
} // end class RvyOptionUI


function rvy_options( $sitewide = false, $customize_defaults = false ) {
	$ui = RvyOptionUI::instance(compact('sitewide', 'customize_defaults'));
	$ui->options_ui($sitewide, $customize_defaults);
}
