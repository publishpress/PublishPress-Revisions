<?php
if (!empty($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename(esc_url_raw($_SERVER['SCRIPT_FILENAME'])) )
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

	function option_checkbox( $option_name, $tab_name, $section_name, $hint_text, $unused_arg = '', $args = '') {
		$return = array( 'in_scope' => false, 'val' => '', 'subcaption' => '', 'style' => '', 'hide' => false, 'no_escape' => false );

		if ( ! is_array($args) )
			$args = array();

		if ( in_array( $option_name, $this->form_options[$tab_name][$section_name] ) ) {
			$this->all_options []= $option_name;

			$return['val'] = rvy_get_option($option_name, $this->sitewide, $this->customize_defaults, ['bypass_condition_check' => true]);

			echo "<div class='agp-vspaced_input'";

			if (!empty($args['style'])) {
				echo " style='" . esc_attr($args['style']) . "'";
			} elseif (!empty($args['hide'])) {
				echo " style='display:none'";
			}
			
			echo ">";

			echo "<label for='" . esc_attr($option_name) . "'><input name='" . esc_attr($option_name) . "' type='checkbox' id='" . esc_attr($option_name) . "' value='1' " . checked('1', $return['val'], false) . " autocomplete='off' " . " /> ";

			if (!empty($args['no_escape'])) {
				echo $this->option_captions[$option_name];	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo esc_html($this->option_captions[$option_name]);
			}

			echo "</label>";

			if ( $hint_text && $this->display_hints )
				echo "<div class='rvy-subtext'>" . esc_html($hint_text) . "</div>";

			if ( ! empty($args['subcaption']) )
				echo $args['subcaption'];		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			echo "</div>";

			$return['in_scope'] = true;
		}

		return $return;
	}

	function register_option($option_name) {
		if (!in_array($option_name, $this->all_options)) {
			$this->all_options []= $option_name;
		}
	}

function options_ui( $sitewide = false, $customize_defaults = false ) {

global $revisionary;
global $rvy_options_sitewide, $rvy_default_options;

if ( ! current_user_can( 'manage_options' ) || ( $sitewide && ! is_super_admin() ) )
	wp_die('');

?>
<div class="wrap pressshack-admin-wrapper">
<?php

if ( $sitewide )
	$customize_defaults = false;	// this is intended only for storing custom default values for site-specific options

rvy_refresh_default_options();

$this->all_options = array();

$this->tab_captions = array( 'features' => esc_html__( 'Settings', 'revisionary' ), 'optscope' => esc_html__( 'Setting Scope', 'revisionary' ) );

$this->section_captions = array(
	'features' => array(
		'post_types'			=> esc_html__('Post Types', 'revisionary'),
		'working_copy'			=> esc_html__('Revision', 'revisionary'),
		'pending_revisions'		=> esc_html__('Submission', 'revisionary'),
		'scheduled_revisions' 	=> esc_html__('Scheduling', 'revisionary'),
		'revision_queue'		=> esc_html__('Queue', 'revisionary'),
		'preview'				=> esc_html__('Preview', 'revisionary'),
		'revisions'				=> esc_html__('Options', 'revisionary'),
		'notification'			=> esc_html__('Notifications', 'revisionary')
	)
);

// TODO: replace individual _e calls with these (and section, tab captions)
$pending_revision_singular = pp_revisions_status_label('pending-revision', 'name');
$pending_revision_plural = rvy_get_option('revision_statuses_noun_labels') ? pp_revisions_status_label('pending-revision', 'plural') : esc_html__('Submission', 'revisionary');
$pending_revision_basic = pp_revisions_status_label('pending-revision', 'basic');
$future_revision_singular = pp_revisions_status_label('future-revision', 'name');

$pp_notif_url = admin_url('edit.php?post_type=psppnotif_workflow');

$this->option_captions = apply_filters('revisionary_option_captions',
	[
	'revision_statuses_noun_labels' =>			esc_html__('Use alternate labeling: "Working Copy" > "Change Request" > "Scheduled Change"', 'revisionary'),
	'manage_unsubmitted_capability' =>			sprintf(esc_html__('Additional role capability required to manage %s', 'revisionary'), pp_revisions_status_label('draft-revision', 'plural')),
	'copy_posts_capability' =>					rvy_get_option('revision_statuses_noun_labels') ? esc_html__("Additional role capability required to create a Working Copy", 'revisionary') : esc_html__("Additional role capability required to create a new revision", 'revisionary'),
	'caption_copy_as_edit' =>					sprintf(esc_html__('Posts / Pages list: Use "Edit" caption for %s link', 'revisionary'), pp_revisions_status_label('draft-revision', 'submit_short')),
	'pending_revisions' => 						sprintf(esc_html__('Enable %s', 'revisionary'), $pending_revision_plural),
	'revision_limit_per_post' =>				esc_html__("Limit to one active revision per post", 'revisionary'),
	'revision_limit_compat_mode' =>				esc_html__('Refresh "Has Revision" flag before suppressing New Revision', 'revisionary'),
	'revision_unfiltered_html_check' =>			esc_html__("If post contains custom html, require unfiltered_html capability", 'revisionary'),
	'auto_submit_revisions' =>					esc_html__("Auto-submit revisions created by a user with publishing capability", 'revisionary'),
	'scheduled_revisions' => 					sprintf(esc_html__('Enable %s', 'revisionary'), pp_revisions_status_label('future-revision', 'plural')),
	'revise_posts_capability' =>				rvy_get_option('revision_statuses_noun_labels') ? esc_html__("Additional role capability required to submit a Change Request", 'revisionary') : esc_html__("Additional role capability required to submit a revision", 'revisionary'),
	'revisor_lock_others_revisions' =>			esc_html__("Editing others' revisions requires role capability", 'revisionary'),
	'revisor_hide_others_revisions' => 			esc_html__("Listing others' revisions requires role capability", 'revisionary'),
	'admin_revisions_to_own_posts' =>			esc_html__("Users can always administer revisions to their own editable posts", 'revisionary'),
	'revision_update_notifications' =>			esc_html__('Also notify on Revision Update', 'revisionary'),
	'trigger_post_update_actions' => 			esc_html__('Apply API actions to mimic Post Update', 'revisionary'),
	'diff_display_strip_tags' => 				esc_html__('Hide html tags on Compare screen', 'revisionary'),
	'scheduled_publish_cron' =>					esc_html__('Use WP-Cron scheduling', 'revisionary'),
	'wp_cron_usage_detected' =>					esc_html__('Site uses a custom trigger for WP-Cron tasks', 'revisionary'),
	'async_scheduled_publish' => 				esc_html__('Asynchronous Publishing', 'revisionary'),
	'scheduled_revision_update_post_date' => 	esc_html__('Update Publish Date', 'revisionary'),
	'pending_revision_update_post_date' => 		esc_html__('Update Publish Date', 'revisionary'),
	'scheduled_revision_update_modified_date' => esc_html__('Update Modified Date', 'revisionary'),
	'pending_revision_update_modified_date' => 	esc_html__('Update Modified Date', 'revisionary'),
	'use_publishpress_notifications' =>			sprintf(__('Use %1$sPlanner Notifications%2$s', 'revisionary'), "<strong><a href='$pp_notif_url'>", '</a></strong>'),
	'pending_rev_notify_author' => 				sprintf(esc_html__('Email original Author when a %s is submitted', 'revisionary'), $pending_revision_basic),
	'rev_approval_notify_author' => 			sprintf(esc_html__('Email the original Author when a %s is approved', 'revisionary'), $pending_revision_singular),
	'rev_approval_notify_revisor' => 			sprintf(esc_html__('Email the Revisor when a %s is approved', 'revisionary'), $pending_revision_singular),
	'publish_scheduled_notify_author' => 		sprintf(esc_html__('Email the original Author when a %s is published', 'revisionary'), $future_revision_singular),
	'publish_scheduled_notify_revisor' => 		sprintf(esc_html__('Email the Revisor when a %s is published', 'revisionary'), $future_revision_singular),
	'use_notification_buffer' => 				esc_html__('Enable notification buffer', 'revisionary'),
	'revisor_role_add_custom_rolecaps' => 		esc_html__('All custom post types available to Revisors', 'revisionary' ),
	'require_edit_others_drafts' => 			esc_html__("Prevent Revisors from editing other user's drafts", 'revisionary' ),
	'display_hints' => 							esc_html__('Display Hints', 'revisionary'),
	'delete_settings_on_uninstall' => 			esc_html__('Delete settings and Revisions if plugin is deleted', 'revisionary'),
	'revision_preview_links' => 				esc_html__('Show Preview Links', 'revisionary'),
	'preview_link_type' => 						esc_html__('Preview Link Type', 'revisionary'),
	'preview_link_alternate_preview_arg' =>		esc_html__('Modify preview link for better theme compatibility', 'revisionary'),
	'block_editor_extra_preview_button' =>		esc_html__('Extra preview button in Gutenberg Editor top bar', 'revisionary'),
	'home_preview_set_home_flag' =>				esc_html__('Theme Compat: For front page revision preview, set home flag', 'revisionary'),
	'compare_revisions_direct_approval' => 		esc_html__('Approve Button on Compare screen', 'revisionary'),
	'copy_revision_comments_to_post' => 		esc_html__('Copy revision comments to published post', 'revisionary'),
	'past_revisions_order_by' =>				esc_html__('Past Revisions ordering:', 'revisionary'), 
	'list_unsubmitted_revisions' => 			sprintf(esc_html__('List %s in Revision Queue for "My Activity" or "Revisions to My Posts" view', 'revisionary'), pp_revisions_status_label('draft-revision', 'plural')),
	'archive_postmeta' =>						esc_html__('Store custom fields of submitted and scheduled revisions for archive', 'revisionary'),
	'rev_publication_delete_ed_comments' =>		esc_html__('On Revision publication, delete Editorial Comments', 'revisionary'),
	'deletion_queue' => 						esc_html__('Enable deletion queue', 'revisionary'),
	'revision_archive_deletion' => 				esc_html__('Enable deletion in archive', 'revisionary'),
	'revision_restore_require_cap' =>			esc_html__('Additional role capability required to restore a revision', 'revisionary'),
	'permissions_compat_mode' => 				esc_html__('Compatibility Mode', 'revisionary'),
	'planner_notifications_access_limited' =>	esc_html__('Planner Notifications Access-Limited', 'revisionary'),
	]
);


if ( defined('RVY_CONTENT_ROLES') ) {
	$this->option_captions['pending_rev_notify_admin'] = 		sprintf(esc_html__('Email designated Publishers when a %s is submitted', 'revisionary'), $pending_revision_basic);
	$this->option_captions['publish_scheduled_notify_admin'] = 	sprintf(esc_html__('Email designated Publishers when a %s is published', 'revisionary'), $future_revision_singular);
	$this->option_captions['rev_approval_notify_admin'] = 		sprintf(esc_html__('Email designated Publishers when a %s is approved', 'revisionary'), $pending_revision_singular);
} else {
	$this->option_captions['pending_rev_notify_admin'] = 		sprintf(esc_html__('Email Editors and Administrators when a %s is submitted', 'revisionary'), $pending_revision_basic);
	$this->option_captions['publish_scheduled_notify_admin'] = 	sprintf(esc_html__('Email Editors and Administrators when a %s is published', 'revisionary'), $future_revision_singular);
	$this->option_captions['rev_approval_notify_admin'] = 		sprintf(esc_html__('Email Editors and Administrators when a %s is approved', 'revisionary'), $pending_revision_singular);
}


$this->form_options = apply_filters('revisionary_option_sections', [
'features' => [
	'post_types' =>			 ['revisor_role_add_custom_rolecaps', 'enabled_post_types', 'permissions_compat_mode'],
	'working_copy' =>		 ['copy_posts_capability', 'revision_limit_per_post', 'revision_limit_compat_mode', 'revision_unfiltered_html_check', 'auto_submit_revisions', 'caption_copy_as_edit'],
	'scheduled_revisions' => ['scheduled_revisions', 'scheduled_publish_cron', 'async_scheduled_publish', 'wp_cron_usage_detected', 'scheduled_revision_update_post_date', 'scheduled_revision_update_modified_date'],
	'pending_revisions'	=> 	 ['pending_revisions', 'revise_posts_capability', 'pending_revision_update_post_date', 'pending_revision_update_modified_date'],
	'revision_queue' =>		 ['manage_unsubmitted_capability', 'revisor_lock_others_revisions', 'revisor_hide_others_revisions', 'admin_revisions_to_own_posts', 'list_unsubmitted_revisions', 'deletion_queue'],
	'preview' =>			 ['revision_preview_links', 'preview_link_type', 'preview_link_alternate_preview_arg', 'home_preview_set_home_flag', 'block_editor_extra_preview_button', 'compare_revisions_direct_approval', 'diff_display_strip_tags', 'past_revisions_order_by'],
	'revisions'		=>		 ['require_edit_others_drafts', 'trigger_post_update_actions', 'copy_revision_comments_to_post', 'archive_postmeta', 'rev_publication_delete_ed_comments', 'revision_archive_deletion', 'revision_restore_require_cap', 'revision_statuses_noun_labels', 'display_hints', 'delete_settings_on_uninstall'],
	'notification'	=>		 ['use_publishpress_notifications', 'planner_notifications_access_limited', 'pending_rev_notify_admin', 'pending_rev_notify_author', 'revision_update_notifications', 'rev_approval_notify_admin', 'rev_approval_notify_author', 'rev_approval_notify_revisor', 'publish_scheduled_notify_admin', 'publish_scheduled_notify_author', 'publish_scheduled_notify_revisor', 'use_notification_buffer'],
	'license' =>			 ['edd_key'],
]
]);

if ( RVY_NETWORK ) {
	if ( $sitewide )
		$available_form_options = $this->form_options;

	foreach ( $this->form_options as $tab_name => $sections ) {
		foreach ( $sections as $section_name => $option_names ) {
			if ($sitewide) {
				$this->form_options[$tab_name][$section_name] = array_intersect( $this->form_options[$tab_name][$section_name], array_keys($rvy_options_sitewide) );
			} elseif ('license' != $section_name) {
				$this->form_options[$tab_name][$section_name] = array_diff( $this->form_options[$tab_name][$section_name], array_keys($rvy_options_sitewide) );
			}
		}
	}

	foreach ( $this->form_options as $tab_name => $sections )
		foreach ( array_keys($sections) as $section_name )
			if ( empty( $this->form_options[$tab_name][$section_name] ) )
				unset( $this->form_options[$tab_name][$section_name] );

	if (!$sitewide) {
		unset($this->form_options['features']['license']);
	}
}

do_action('revisionary_settings_ui', $this, $sitewide, $customize_defaults);
?>
<header>

<?php
echo '<form action="" method="post" autocomplete="off">';
wp_nonce_field( 'rvy-update-options' );

if ( $sitewide )
	echo "<input type='hidden' name='rvy_options_doing_sitewide' value='1' />";

if ( $customize_defaults )
	echo "<input type='hidden' name='rvy_options_customize_defaults' value='1' />";

?>
<table><tr>
<td>
<h1 class="wp-heading-inline"><?php
if ( $sitewide )
	esc_html_e('PublishPress Revisions Network Settings', 'revisionary');
elseif ( $customize_defaults )
	esc_html_e('PublishPress Revisions Network Defaults', 'revisionary');
elseif ( RVY_NETWORK )
	esc_html_e('PublishPress Revisions Site Settings', 'revisionary');
else
	esc_html_e('PublishPress Revisions Settings', 'revisionary');
?>
</h1>
</td>
<td>
</td>
</tr></table>

</header>

<?php
$div_class = apply_filters('publishpress_revisions_settings_sidebar_class', '');
?>

<div id="poststuff">

	<div id="post-body" class="metabox-holder <?php echo esc_attr($div_class);?>">	

	<div id="post-body-content" style="position: relative;" class="ppseries-settings-body-content">

<?php
if ( $sitewide ) {
	$color_class = 'rs-backgray';

} elseif ( $customize_defaults ) {
	$color_class = 'rs-backgreen';
	echo '<p style="margin-top:0">';
	esc_html_e( 'These are the default settings for options which can be adjusted per-site.', 'revisionary' );
	echo '</p>';

} else
	$color_class = 'rs-settings';

if ( $sitewide || $customize_defaults ) {
	$class_selected = "agp-selected_agent agp-agent $color_class";
	$class_unselected = "agp-unselected_agent agp-agent";

	// todo: prevent line breaks in these links
	echo "<ul class='rs-list_horiz' style='margin-bottom:-0.1em'>"
		. "<li class='" . esc_attr($class_selected) . "'>"
		. "<a id='rvy_show_features' href='javascript:void(0)' onclick=\""
		. "agp_swap_display('rvy-features', 'rvy-optscope', 'rvy_show_features', 'rvy_show_optscope', '" . esc_attr($class_selected) . "', '" . esc_attr($class_unselected) . "');"
		. "\">" . esc_html($this->tab_captions['features']) . '</a>'
		. '</li>';

	if ( $sitewide ) {
		echo "<li class='" . esc_attr($class_unselected) . "'>"
			. "<a id='rvy_show_optscope' href='javascript:void(0)' onclick=\""
			. "agp_swap_display('rvy-optscope', 'rvy-features', 'rvy_show_optscope', 'rvy_show_features', '" . esc_attr($class_selected) . "', '" . esc_attr($class_unselected) . "');"
			. "\">" . esc_html($this->tab_captions['optscope']) . '</a>'
			. '</li>';
	}

	echo '</ul>';
}

// ------------------------- BEGIN Features tab ---------------------------------

$tab = 'features';
echo "<div id='rvy-features' style='clear:both;margin:0' class='" . esc_attr($color_class) . "'>";

if ( rvy_get_option('display_hints', $sitewide, $customize_defaults) ) {
	echo '<div class="rs-optionhint publishpress-headline"><span>';

	if ( $sitewide ) {
		printf( esc_html__('Use this tab to make NETWORK-WIDE changes to PublishPress Revisions settings. %s', 'revisionary'), '' );
		
		if ( count( $rvy_options_sitewide ) < count( $rvy_default_options ) ) {
			printf( esc_html__( 'You can also specify %1$sdefaults for site-specific settings%2$s.', 'revisionary' ), '<a href="admin.php?page=rvy-default_options">', '</a>' );
		}
	} elseif ( $customize_defaults ) {
		esc_html_e('Here you can change the default value for settings which are controlled separately on each site.', 'revisionary');
	}

	if ( RVY_NETWORK && is_super_admin() ) {
		if ( ! $sitewide ) {
			global $blog_id;

			echo ' ';

			if ( is_main_site($blog_id) ) {
				printf( esc_html__('Note that %1$s network-wide settings%2$s may also be available.', 'revisionary'), "<a href='admin.php?page=rvy-net_options'>", '</a>');
			} else {
				printf( esc_html__('Note that %1$s network-wide settings%2$s may also be available.', 'revisionary'), '', '' );
			}
		}
	}
	?>
	</span>

	</div>
	<?php
}
?>

<ul id="publishpress-revisions-settings-tabs" class="nav-tab-wrapper">
	<?php
	if (!empty($_REQUEST['ppr_tab'])) {															//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$setActiveTab = str_replace('ppr-tab-', '', sanitize_key($_REQUEST['ppr_tab']));		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
	} else {
		// Set first tab and content as active
		$setActiveTab = '';
	}

	if (empty($setActiveTab)) {
		$setActiveTab = 'post_types';
	}

	foreach($this->section_captions['features'] as $section_name => $label) {
		if (!empty($this->form_options[$tab][$section_name])) {
		?>
		<li class="nav-tab<?php echo (empty($setActiveTab) || ($setActiveTab == $section_name)) ? ' nav-tab-active' : '' ?>">
			<a href="#ppr-tab-<?php echo esc_attr($section_name) ?>">
				<?php echo esc_html($label) ?>
			</a>
		</li>
		<?php
			if (empty($setActiveTab)) {
				$setActiveTab = $section_name;
			}
		}
	}

	if (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION') && !empty($this->form_options['features']['license'])) {
		?>
		<li class="nav-tab nav-tab-license <?php if ($setActiveTab == 'license') echo 'nav-tab-active';?>">
			<a href="#ppr-tab-license">
				<?php esc_html_e('License', 'revisionary') ?>
			</a>
		</li>
		<?php
	}
	?>
</ul>

<div>

<?php
// possible TODO: replace redundant hardcoded IDs with $id

	if (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION') && !empty($this->form_options['features']['license'])) {
		require_once(REVISIONARY_PRO_ABSPATH . '/includes-pro/SettingsLicense.php');
		$license_ui = new RevisionaryLicenseSettings();
		?>
		<table class="form-table rs-form-table" id="ppr-tab-license"<?php echo ($setActiveTab != 'license') ? ' style="display:none;"' : '' ?>>
			<?php $license_ui->display($sitewide, $customize_defaults); ?>
		</table>
		<?php
	}


	$section = 'post_types';				// --- POST TYPES SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

		<?php
		$option_name = 'enabled_post_types';

		$this->all_options []= $option_name;

		esc_html_e('Enable revision submission for these Post Types:', 'revisionary');
        echo '<br />';

		$hidden_types = ['attachment' => true, 'psppnotif_workflow' => true, 'tablepress_table' => true, 'acf-field-group' => true, 'acf-field' => true, 'nav_menu_item' => true, 'custom_css' => true, 'customize_changeset' => true, 'wp_block' => true, 'wp_template' => true, 'wp_template_part' => true, 'wp_global_styles' => true, 'wp_navigation' => true];
		$locked_types = [];

		$types = get_post_types(['public' => true, 'show_ui' => true], 'object', 'or');

		$types = rvy_order_types($types);

		foreach ($types as $key => $obj) {
			if (!$key) {
				continue;
			}

			$id = $option_name . '-' . $key;
			$name = $option_name . "[$key]";
			?>

			<?php if ('nav_menu' == $key) : ?>
				<input name="<?php echo esc_attr($name); ?>" type="hidden" id="<?php echo esc_attr($id); ?>" value="1"/>
			<?php else : ?>
			<?php if (isset($hidden_types[$key])) : ?>
				<input name="<?php echo esc_attr($name); ?>" type="hidden" value="<?php echo esc_attr($hidden_types[$key]); ?>"/>
			<?php else : 
					$locked = (!empty($locked_types[$key])) ? ' disabled ' : '';
				?>
			<div class="agp-vtight_input">
				<input name="<?php echo esc_attr($name); ?>" type="hidden" value="<?php echo (empty($locked_types[$key])) ? '0' : '1';?>"/>
				<label for="<?php echo esc_attr($id); ?>" title="<?php echo esc_attr($key); ?>">
					<input name="<?php if (empty($locked_types[$key])) echo esc_attr($name); ?>" type="checkbox" id="<?php echo esc_attr($id); ?>"
						value="1" <?php checked('1', !empty($revisionary->enabled_post_types[$key])); echo esc_attr($locked); ?> />

					<?php
					if (isset($obj->labels_pp)) {
						echo esc_html($obj->labels_pp->name);
					} elseif (isset($obj->labels->name)) {
						echo esc_html($obj->labels->name);
					} else {
						echo esc_html($key);
					}

					echo '</label>';
					
					if (!empty($revisionary->enabled_post_types[$key]) && isset($obj->capability_type) && !in_array($obj->capability_type, [$obj->name, 'post', 'page'])) {
						if ($cap_type_obj = get_post_type_object($obj->capability_type)) {
							echo '&nbsp;(' . esc_html(sprintf(__('%s capabilities'), $cap_type_obj->labels->singular_name)) . ')';
						}
					}

					echo '</div>';
				endif;
			endif; // displaying checkbox UI

		} // end foreach src_otype

		echo '<br >';
		$hint = esc_html__('The user role "Revisor" role is now available. Include capabilities for all custom post types in this role?', 'revisionary');
		$this->option_checkbox( 'revisor_role_add_custom_rolecaps', $tab, $section, $hint, '' );
		?>

	<div class="rvy-subtext">
	<?php
	esc_html_e('Note: Third party code may cause some post types to be incompatible with PublishPress Revisions.', 'revisionary');
	?>
	</div>

	<?php
		if (!defined('PUBLISHPRESS_STATUSES_PRO_VERSION')) :
			?>
			<div id="statuses-pro-notice" class="activating rvy-plugin-notice" style="margin-top: 20px;">
			<?php
			printf(
				esc_html__('For custom Revision statuses, install %sPublishPress Statuses Pro%s.', 'revisionary'),
				'<a href="https://publishpress.com/statuses/" target="_blank">',
				'</a>'
			);
			?>
			</div>
		<?php endif;

		if (defined('PUBLISHPRESS_PRO_VERSION') && version_compare(PUBLISHPRESS_PRO_VERSION, '4.6.0-rc', '<')) :
			?>
			<div id="planner-pro-notice" class="activating rvy-plugin-notice" style="margin-top: 20px;">
			<?php
			printf(
				esc_html__('For Revisions integration on the Content Calendar, Overview and Content Board, please update %sPublishPress Planner Pro%s.', 'revisionary'),
				'<a href="' . esc_url(self_admin_url('plugins.php')) . '" target="_blank">',
				'</a>'
			);
			?>
			</div>
		<?php endif;

		echo '<br>';
		$id = 'permissions_compat_mode';

		$this->register_option($id);
		$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);

		echo esc_html($this->option_captions[$id]) . ':';

		$standard_caption = (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION'))
		? esc_html__('Broadest compat including Elementor, Divi, Beaver Builder', 'revisionary')
		: esc_html__('Standard storage schema for broadest 3rd party compat', 'revisionary');

		echo " <select name='" . esc_attr($id) . "' id='" . esc_attr($id) . "' autocomplete='off'>";
		$captions = [
			'' => $standard_caption, 
			1 => esc_html__('Enhanced Revision access control with PublishPress plugins', 'revisionary'),
		];

		foreach ( $captions as $key => $value) {
			$selected = ( $current_setting == $key ) ? 'selected' : '';
			echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
		}
		echo '</select>&nbsp;';

		if (revisionary()->getOption('display_hints')) :?>
			<div class="rvy-subtext">
			<?php _e('In enhanced mode, a Revision\'s status is stored using the standard WordPress schema. Some plugins (including older versions of Revisions) are incompatible.', 'revisionary');?>
			</div>
		<?php endif;
	?>

	</td></tr></table>
	<?php endif; // any options accessable in this section


$section = 'working_copy';			// --- WORKING COPIES SECTION ---

if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
	<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

	<?php
	$hint = esc_html__('Prevent Revision creation if the post already has another Revision in progress.', 'revisionary');
	$this->option_checkbox( 'revision_limit_per_post', $tab, $section, $hint, '' );

	$hide = empty(rvy_get_option('revision_limit_per_post'));
	$hint = esc_html__('Work around cache plugin conflicts by requerying for revisions before suppressing the New Revision link.', 'revisionary');
	$this->option_checkbox( 'revision_limit_compat_mode', $tab, $section, $hint, '', compact('hide') );
	?>
	<script type="text/javascript">
	/* <![CDATA[ */
	jQuery(document).ready( function($) {
		$('#revision_limit_per_post').on('click', function(e) {
			$('#revision_limit_compat_mode').closest('div').toggle($(e).prop('checked'));
		});
	});
	/* ]]> */
	</script>

	<?php
	$hint = sprintf(esc_html__('If the user does not have a regular Edit link, recaption the %s link as "Edit."', 'revisionary'), pp_revisions_status_label('draft-revision', 'submit_short'));
	$this->option_checkbox( 'caption_copy_as_edit', $tab, $section, $hint, '' );

	$hint = esc_html__('This restriction applies to users who are not full editors for the post type. To enable a role, add capabilities: copy_posts, copy_others_pages, etc.', 'revisionary');
	
	if (defined('PRESSPERMIT_VERSION')) {
		$hint .= ' ' . esc_html__('To expand the Posts / Pages listing for non-Editors, add capabilities: list_others_pages, list_published_posts, etc.', 'revisionary');
	}
	
	$this->option_checkbox( 'copy_posts_capability', $tab, $section, $hint, '' );

	$hint = esc_html__('If disabled, revision by a user who does not have the unfiltered_html capability will cause all custom html tags to be stripped out.', 'revisionary');
	$this->option_checkbox( 'revision_unfiltered_html_check', $tab, $section, $hint, '' );
	?>

	<?php
	$hint = esc_html__('When a user who has publishing capabilities creates a Revision, set it to "Submitted" status.', 'revisionary');
	$this->option_checkbox( 'auto_submit_revisions', $tab, $section, $hint, '' );

	do_action('revisionary_auto_submit_setting_ui', $this, $tab, $section);
	?>
	</td></tr></table>
<?php endif; // any options accessable in this section


$pending_revisions_available = ! RVY_NETWORK || $sitewide || empty( $rvy_options_sitewide['pending_revisions'] ) || rvy_get_option( 'pending_revisions', true );
$scheduled_revisions_available = ! RVY_NETWORK || $sitewide || empty( $rvy_options_sitewide['scheduled_revisions'] ) || rvy_get_option( 'scheduled_revisions', true );

if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
$pending_revisions_available ) :
	$section = 'pending_revisions';			// --- PENDING REVISIONS SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

		<?php
		$this->option_checkbox('pending_revisions', $tab, $section, '', '', ['style' => 'margin-bottom: 0']);

		echo "<div class='rvy-subtext'>"
		. sprintf(
			esc_html__( 'Enable published content to be copied, edited, submitted for approval and managed in %sRevision Queue%s.', 'revisionary' ),
			"<a href='" . esc_url(rvy_admin_url('admin.php?page=revisionary-q')) . "'>",
			'</a>'
		)
		. "</div>";

		$hint = esc_html__('This restriction applies to users who are not full editors for the post type. To enable a role, add capabilities: revise_posts, revise_others_pages, etc.', 'revisionary');
		$this->option_checkbox( 'revise_posts_capability', $tab, $section, $hint, '' );

		$hint = sprintf(esc_html__( 'When a %s is published, update post publish date to current time.', 'revisionary' ), pp_revisions_status_label('pending-revision', 'name'));
		$this->option_checkbox( 'pending_revision_update_post_date', $tab, $section, $hint, '' );

		$hint = sprintf(esc_html__( 'When a %s is published, update post modified date to current time.', 'revisionary' ), pp_revisions_status_label('pending-revision', 'name'));
		$this->option_checkbox( 'pending_revision_update_modified_date', $tab, $section, $hint, '' );

		do_action('revisionary_option_ui_pending_revisions', $this, $sitewide, $customize_defaults);
		?>
		</td></tr></table>
	<?php endif; // any options accessable in this section
endif;

if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
	$scheduled_revisions_available ) :

	$section = 'scheduled_revisions';			// --- SCHEDULED REVISIONS SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

		<?php
		$hint = esc_html__( 'If a currently published post or page is edited and a future date set, the change will not be applied until the selected date.', 'revisionary' );
		$this->option_checkbox( 'scheduled_revisions', $tab, $section, $hint, '' );

		$hint = sprintf(esc_html__( 'When a %s is published, update post publish date to current time.', 'revisionary' ), pp_revisions_status_label('future-revision', 'name'));
		$this->option_checkbox( 'scheduled_revision_update_post_date', $tab, $section, $hint, '' );

		$hint = sprintf(esc_html__( 'When a %s is published, update post modified date to current time.', 'revisionary' ), pp_revisions_status_label('future-revision', 'name'));
		$this->option_checkbox( 'scheduled_revision_update_modified_date', $tab, $section, $hint, '' );

		global $wp_version;
		
		$hint = esc_html__( 'Publish scheduled revisions using the WP-Cron mechanism. On some sites, publication will fail if this setting is disabled.', 'revisionary' );
		$this->option_checkbox( 'scheduled_publish_cron', $tab, $section, $hint, '' );

		if (!rvy_get_option('scheduled_publish_cron')) {
			$hint = esc_html__( 'Publish scheduled revisions asynchronously, via a secondary http request from the server.  This is usually best since it eliminates delay, but some servers may not support it.', 'revisionary' );
			$this->option_checkbox( 'async_scheduled_publish', $tab, $section, $hint, '' );
		}

		if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
			$hint = esc_html__( 'The WP-Cron trigger is disabled, but scheduled tasks are still excecuted using a custom trigger.', 'revisionary' );
			$this->option_checkbox( 'wp_cron_usage_detected', $tab, $section, $hint, '' );
		}
		?>
		</td></tr></table>
	<?php endif; // any options accessable in this section
endif;

if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
	$pending_revisions_available || $scheduled_revisions_available ) :

		$section = 'revision_queue';			// --- REVISION QUEUE SECTION ---

		if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
			<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

			<?php
			$hint = esc_html__('To enable a role, add the manage_unsubmitted_revisions capability.', 'revisionary');
			$this->option_checkbox('manage_unsubmitted_capability', $tab, $section, $hint, '');
	
			$hint = esc_html__('This restriction applies to users who are not full editors for the post type. To enable a role, give it the edit_others_revisions capability.', 'revisionary');
			$this->option_checkbox( 'revisor_lock_others_revisions', $tab, $section, $hint, '' );

			$hint = esc_html__('This restriction applies to users who are not full editors for the post type. To enable a role, give it the list_others_revisions capability.', 'revisionary');
			$this->option_checkbox( 'revisor_hide_others_revisions', $tab, $section, $hint, '' );

			$hint = esc_html__('Bypass the above restrictions for others\' revisions to logged in user\'s own posts.', 'revisionary');
			$this->option_checkbox( 'admin_revisions_to_own_posts', $tab, $section, $hint, '' );

			$hint = '';
			$this->option_checkbox( 'list_unsubmitted_revisions', $tab, $section, $hint, '' );

			do_action('revisionary_option_ui_revision_queue_options', $this, $sitewide, $customize_defaults);
		?>

		<?php if (!empty($_SERVER['REQUEST_URI'])):?>
		<p style="margin-top:25px">
		<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('rvy_flush_flags', 1, esc_url(esc_url_raw($_SERVER['REQUEST_URI']))), 'flush-flags') )?>"><?php esc_html_e('Regenerate "post has revision" flags', 'revisionary');?></a>
		
		<?php if ($this->display_hints) :
			$hint = esc_html__('Apply this maintenance operation if "Has Revision" indicators on the Posts / Pages screen seem out of sync with the Revision Queue listing.', 'revisionary');
			echo "<div class='rvy-subtext'>" . esc_html($hint) . "</div>";
		endif;?>
	
		</p>
		<?php endif;?>

		</td></tr></table>
	<?php endif; // any options accessable in this section
endif;


$section = 'preview';			// --- PREVIEW SECTION ---

if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
	<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

	<?php
	$hint = esc_html__('Some themes may block revision preview.', 'revisionary');
	$this->option_checkbox( 'revision_preview_links', $tab, $section, $hint, '' );

	$preview_links = rvy_get_option('revision_preview_links');

	$id = 'preview_link_type';
	if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
		$this->all_options []= $id;
		$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);
		?>
		<div id="rvy_preview_options" style="padding-left: 25px;<?php if (!$preview_links) echo 'display: none;';?>">
		<label for="<?php echo esc_attr($id);?>"><?php echo esc_html($this->option_captions[$id]);?>: </label>

		<select name="<?php echo esc_attr($id);?>" id="<?php echo esc_attr($id);?>" autocomplete="off">

		<?php
		$captions = [
			'' => esc_html__('Published Post Slug', 'revisionary'), 
			'revision_slug' => esc_html__('Revision Slug', 'revisionary'), 
			'id_only' => esc_html__('Revision ID only', 'revisionary') 
		];

		foreach ( $captions as $key => $value) {
			$selected = ( $current_setting == $key ) ? 'selected' : '';
			echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
		}
		?>
		</select>&nbsp;

		<?php
		if ( $this->display_hints ) : ?>
			<br />
			<div class="rvy-subtext">
			<?php
			esc_html_e('Some themes or plugins may require Revision Slug or Revision ID link type for proper template loading and field display.', 'revisionary');
			?>
			</div>
		<?php endif;

		do_action('revisionary_option_ui_preview_options', $this, $sitewide, $customize_defaults);

		if (defined('RVY_PREVIEW_ARG_LOCKED') && defined('RVY_PREVIEW_ARG')) {
			printf(
				esc_html__(
					'The revision preview argument is configured by constant definition: %s',
					'revisionary'
				),
				esc_html(RVY_PREVIEW_ARG)
			);
		} else {
			$hint = esc_html__('Adjust preview links to use "rv_preview" argument instead of "preview". Experiment to see which works best with your theme.', 'revisionary');
			$this->option_checkbox( 'preview_link_alternate_preview_arg', $tab, $section, $hint, '' );
		}

		$hint = esc_html__('Some themes may require this setting for correct revision preview display.', 'revisionary');
		$this->option_checkbox( 'home_preview_set_home_flag', $tab, $section, $hint, '' );

		$hint = '';
		$this->option_checkbox( 'block_editor_extra_preview_button', $tab, $section, $hint, '' );
		?>
		</div>
		
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
			$('#revision_preview_links').on('click', function(e) {
				$('#rvy_preview_options').toggle($(e).prop('checked'));
			});
		});
		/* ]]> */
		</script>
		<?php
	}

	echo '<h4 style="margin-top: 30px; margin-bottom:8px">' . esc_html__('Compare Revisions Screen:', 'revisionary') . '</h4>';

	if ($this->display_hints) {
		$hint = esc_html__('The screen to compare all unpublished revisions for an individual post is linked in the post editor and preview top bar.', 'revisionary');
		echo "<div class='rvy-subtext'>" . esc_html($hint) . "</div>";
	}

	$id = 'past_revisions_order_by';
	if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
		echo esc_html($this->option_captions[$id]);

		$this->all_options []= $id;
		$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);

		echo " <select name='" . esc_attr($id) . "' id='" . esc_attr($id) . "' autocomplete='off'>";
		$captions = ['' => esc_html__('Post Date', 'revisionary'), 'modified' => esc_html__('Modification Date', 'revisionary')];
		foreach ( $captions as $key => $value) {
			$selected = ( $current_setting == $key ) ? 'selected' : '';
			echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
		}
		echo '</select>&nbsp;';

		echo "<br />";
	}

	$hint = '';
	$this->option_checkbox( 'diff_display_strip_tags', $tab, $section, $hint, '' );

	$hint = esc_html__('This allows immediate approval within the Compare screen. If disabled, a Preview link is provided instead.', 'revisionary');
	$this->option_checkbox( 'compare_revisions_direct_approval', $tab, $section, $hint, '' );
	?>
	</td></tr></table>
<?php endif; // any options accessable in this section


if ( 	// To avoid confusion, don't display any revision settings if pending revisions / scheduled revisions are unavailable
$pending_revisions_available || $scheduled_revisions_available ) :

	$section = 'revisions';			// --- REVISIONS SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

		<?php
		$hint = esc_html__('If checked, the edit_others_drafts capability will be required for users lacking site-wide publishing capabilities', 'revisionary');
		$this->option_checkbox( 'require_edit_others_drafts', $tab, $section, $hint, '' );
		?>

		<?php
		echo '<h4 style="margin-top: 30px; margin-bottom:0">' . esc_html__('Revision Publication:', 'revisionary') . '</h4>';

		if (defined('PUBLISHPRESS_VERSION')) {
			$this->option_checkbox( 'rev_publication_delete_ed_comments', $tab, $section, '', '' );
		}

		$this->option_checkbox( 'copy_revision_comments_to_post', $tab, $section, '', '' );

		$hint = esc_html__('This may improve compatibility with some plugins.', 'revisionary');
		$this->option_checkbox( 'trigger_post_update_actions', $tab, $section, $hint, '' );

		echo '<h4 style="margin-top: 30px; margin-bottom:0">' . esc_html__('Revision Archive:', 'revisionary') . '</h4>';

		$this->option_checkbox( 'archive_postmeta', $tab, $section, '', '' );

		$hint = '';
		$this->option_checkbox( 'revision_archive_deletion', $tab, $section, $hint, '' );

		if (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) {
			$hint = esc_html__('Non-Administrators cannot restore a revision without the restore_revisions capability', 'revisionary');
			$this->option_checkbox( 'revision_restore_require_cap', $tab, $section, $hint, '' );
		}

		do_action('revisionary_option_ui_revision_options', $this, $sitewide, $customize_defaults);
		?>
		<?php
		echo '<h4 style="margin-top: 30px; margin-bottom:0">' . esc_html__('Revision Status Labels', 'revisionary') . '</h4>';

		$hint = esc_html__('Default labels are "Not Submitted for Approval", "Submitted for Approval", "Scheduled Revision"', 'revisionary');
		$this->option_checkbox( 'revision_statuses_noun_labels', $tab, $section, $hint, '' );
		
		echo "<br />";

		$hint = esc_html__( 'Show descriptive captions for PublishPress Revisions settings', 'revisionary' );
		$this->option_checkbox( 'display_hints', $tab, $section, $hint, '' );

		$hint = esc_html__('note: Plugin settings, Revision Queue contents and related data will be deleted, but only after the last copy of Revisions / Revisions Pro is deleted.', 'revisionary');
		$this->option_checkbox('delete_settings_on_uninstall', $tab, $section, $hint);
		?>

		</td></tr></table>
	<?php endif; // any options accessable in this section


	$section = 'notification';			// --- NOTIFICATION SECTION ---

	if ( ! empty( $this->form_options[$tab][$section] ) ) :?>
		<table class="form-table rs-form-table" id="<?php echo esc_attr("ppr-tab-$section");?>"<?php echo ($setActiveTab != $section) ? ' style="display:none;"' : '' ?>><tr><td>

		<?php
		if (!defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) :
			?>
			<div id="rvy-planner-notice" class="activating rvy-plugin-notice" style="margin-bottom: 20px">
			<?php
			printf(
				esc_html__('For enhanced notifications, upgrade to %sPublishPress Revisions Pro%s.', 'revisionary'),
				'<a href="https:/publishpress.com/revisions" target="_blank">',
				'</a>'
			);
			?>
			</div>
		<?php
		elseif (!defined('PUBLISHPRESS_VERSION')) :
			?>
			<div id="rvy-planner-notice" class="activating rvy-plugin-notice" style="margin-bottom: 20px">
			<?php
			printf(
				esc_html__('For enhanced notifications, install %sPublishPress Planner%s.', 'revisionary'),
				'<a href="' . esc_url(admin_url('plugin-install.php?s=publishpress%2520planner&tab=search&type=term')) . '" target="_blank">',
				'</a>'
			);
			?>
			</div>
		<?php elseif (!version_compare(PUBLISHPRESS_VERSION, '4.6-beta', '>=')) :
			?>
			<div id="rvy-planner-notice" class="activating rvy-plugin-notice" style="margin-bottom: 20px">
			<?php
			printf(
				esc_html__('For enhanced notifications, update %sPublishPress Planner%s.', 'revisionary'),
				'<a href="' . esc_url(admin_url('plugin-install.php?s=publishpress%2520planner&tab=search&type=term')) . '" target="_blank">',
				'</a>'
			);
			?>
			</div>
		<?php endif;

		if (defined('PUBLISHPRESS_VERSION') && version_compare(PUBLISHPRESS_VERSION, '4.6-beta', '>=')) {
			$hint = '';
			$this->option_checkbox( 'use_publishpress_notifications', $tab, $section, $hint, '', ['no_escape' => true] );

			$pp_notifications = rvy_get_option('use_publishpress_notifications');

			if ($pp_notifications && defined('PRESSPERMIT_VERSION') && defined('RVY_CONTENT_ROLES')) {
				echo '<br />';
				$hint = __('Users matching Planner > Notifications configuration get revision notifications only if they can edit the published post', 'revisionary');
				$this->option_checkbox( 'planner_notifications_access_limited', $tab, $section, $hint, '', ['no_escape' => true] );
			}

			echo '<h3 style="margin-top:30px;';
			
			if ($pp_notifications) echo 'display:none;';

			echo '">';
			_e('Legacy Email Notifications:');
			echo '</h3>';
		}

		?>

		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
			$('#use_publishpress_notifications').on('click', function(e) {
				$('div.rvy_legacy_email').toggle($(e).prop('checked'));
			});
		});
		/* ]]> */
		</script>

		<div class="rvy_legacy_email" style="<?php if (!empty($pp_notifications)) echo 'display:none';?>">

		<?php
		if( $pending_revisions_available ) {
			$id = 'pending_rev_notify_admin';
			if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
				$this->all_options []= $id;
				$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);

				echo "<select name='" . esc_attr($id) . "' id='" . esc_attr($id) . "' autocomplete='off'>";
				$captions = array( 0 => esc_html__('Never', 'revisionary'), 1 => esc_html__('By default', 'revisionary'), 'always' => esc_html__('Always', 'revisionary') );
				foreach ( $captions as $key => $value) {
					$selected = ( $current_setting == $key ) ? 'selected' : '';
					echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
				}
				echo '</select>&nbsp;';

				echo esc_html($this->option_captions[$id]);

				// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInTernaryCondition
				echo ( defined('RVY_CONTENT_ROLES') && $group_link = $revisionary->content_roles->get_metagroup_edit_link( 'Pending Revision Monitors' ) ) ?
				sprintf( " &bull;&nbsp;<a href='%s'>" . esc_html__('select recipients', 'revisionary') . "</a>", esc_url($group_link) ) : '';

				echo "<br />";
			}

			$id = 'pending_rev_notify_author';
			if ( in_array( $id, $this->form_options[$tab][$section] ) ) {
				$this->all_options []= $id;
				$current_setting = rvy_get_option($id, $sitewide, $customize_defaults);

				echo "<select name='" . esc_attr($id) . "' id='" . esc_attr($id) . "' autocomplete='off'>";
				$captions = array( 0 => esc_html__('Never', 'revisionary'), 1 => esc_html__('By default', 'revisionary'), 'always' => esc_html__('Always', 'revisionary') );
				foreach ( $captions as $key => $value) {
					$selected = ( $current_setting == $key ) ? 'selected' : '';
					echo "\n\t<option value='" . esc_attr($key) . "' " . esc_attr($selected) . ">" . esc_html($captions[$key]) . "</option>";
				}
				echo '</select>&nbsp;';

				echo esc_html($this->option_captions[$id]);
				echo "<br />";
			}

			if (rvy_get_option('pending_rev_notify_admin') || rvy_get_option('pending_rev_notify_author')) {
				$hint = '';
				$this->option_checkbox( 'revision_update_notifications', $tab, $section, $hint, '' );
			}

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

			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInTernaryCondition
			$subcaption = ( defined('RVY_CONTENT_ROLES') && $group_link = $revisionary->content_roles->get_metagroup_edit_link( 'Scheduled Revision Monitors' ) ) ?
				sprintf( " &bull;&nbsp;<a href='%s'>" . esc_html__('select recipients', 'revisionary') . "</a>", $group_link ) : '';

			$hint = '';
			$this->option_checkbox( 'publish_scheduled_notify_admin', $tab, $section, $hint, '', array( 'subcaption' => $subcaption ) );

			$hint = '';
			$this->option_checkbox( 'publish_scheduled_notify_author', $tab, $section, $hint, '' );

			$hint = '';
			$this->option_checkbox( 'publish_scheduled_notify_revisor', $tab, $section, $hint, '' );
		}

		echo '<br />';

		$hint = esc_html__('To avoid notification failures, buffer emails for delayed sending once minute, hour or day limits are exceeded', 'revisionary');
		$this->option_checkbox( 'use_notification_buffer', $tab, $section, $hint, '' );

		if (!empty($_REQUEST['truncate_mail_log'])) {										//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_option('revisionary_sent_mail');
		}

		if (!empty($_REQUEST['clear_mail_buffer'])) {										//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_option('revisionary_mail_buffer');
		}

		if (!empty($_SERVER['REQUEST_URI'])) {
			$uri = esc_url(esc_url_raw($_SERVER['REQUEST_URI']));
		} else {
			$uri = '';
		}

		if (!empty($_REQUEST['mailinfo'])) {												//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$verbose = !empty($_REQUEST['verbose']);										//phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ($q = get_option('revisionary_mail_buffer')) {
				echo '<h3>' . esc_html__('Notification Buffer', 'revisionary') . '</h3>';
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
							echo "<b>" . esc_html($k) . "</b> : " . esc_html($val) . "<br />";
						}
					}

					if ($verbose && !empty($row['message'])) {
						echo "<b>message</b> : " . esc_html($row['message']) . "<br />";
					}

					echo '<hr />';
				}
			}

			if ($log = get_option('revisionary_sent_mail')) {
				echo '<h3>' . esc_html__('Notification Log', 'revisionary') . '</h3>';
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
							echo "<b>" . esc_html($k) . "</b> : " . esc_html($val) . "<br />";
						}
					}

					if ($verbose && !empty($row['message'])) {
						echo "<b>message</b> : " . esc_html($row['message']) . "<br />";
					}

					echo '<hr />';
				}
			}

			if (get_option('revisionary_mail_buffer')):?>
				<br />
				<a href="<?php echo esc_url(add_query_arg('clear_mail_buffer', '1', $uri));?>"><?php esc_html_e('Purge Notification Buffer', 'revisionary');?></a>
				<br />
			<?php endif;?>

			<?php if (get_option('revisionary_sent_mail')):?>
				<br />
				<a href="<?php echo esc_url(add_query_arg('truncate_mail_log', '1', $uri));?>"><?php esc_html_e('Truncate Notification Log', 'revisionary');?></a>
			<?php endif;

			$mail_info = rvy_mail_check_buffer([], ['log_only' => true]);
			?>
			<br /><br />
			<p><?php echo esc_html(sprintf(__('Sent in last minute: %d / %d', 'revisionary'), $mail_info->sent_counts['minute'], $mail_info->send_limits['minute']));?></p>
			<p><?php echo esc_html(sprintf(__('Sent in last hour: %d / %d', 'revisionary'), $mail_info->sent_counts['hour'], $mail_info->send_limits['hour']));?></p>
			<p><?php echo esc_html(sprintf(__('Sent in last day: %d / %d', 'revisionary'), $mail_info->sent_counts['day'], $mail_info->send_limits['day']));?></p>
			<?php
			if (!empty($q)) {
				if ($cron_timestamp = wp_next_scheduled('rvy_mail_buffer_hook')) {
					$wait_sec = $cron_timestamp - time();
					if ($wait_sec > 0) {
						echo '<br />';
						echo esc_html(sprintf(__('Seconds until next buffer processing time: %d', 'revisionary'), $wait_sec));
					}
				}
			}
		}

		if (empty($_REQUEST['mailinfo'])):							//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
			<br />
			<div style="padding-left:22px">

			<a href="<?php echo esc_url(add_query_arg('ppr_tab', 'notification', add_query_arg('mailinfo', '1', $uri)));?>"><?php esc_html_e('Show Notification Log / Buffer', 'revisionary');?></a>
			<br /><br />
			<a href="<?php echo esc_url(add_query_arg('ppr_tab', 'notification', add_query_arg('verbose', '1', add_query_arg('mailinfo', '1', $uri))));?>"><?php esc_html_e('Show with message content', 'revisionary');?></a>
			</div>
		<?php endif;

		?>
		</div>

		</td></tr>

		<?php
		if ((defined('REVISIONARY_PRO_VERSION') || defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) && defined('ICL_SITEPRESS_VERSION') && defined('WPML_TM_VERSION')) :?>

		<tr><th scope="row">
		<?php esc_html_e('WPML Translation Management', 'revisionary') ?>
		</th></td>
		<td>
		<p>
		<?php
		$url = admin_url('admin.php?page=revisionary-settings&rvy_wpml_sync_needs_update=1');
		?>
		<a href="<?php echo esc_url($url);?>"><?php esc_html_e('Sync "Needs Update" flags', 'revisionary');?></a>

		<div class="rvy-subtext">
		<?php
		esc_html_e('Set "Needs Update" for any post with translations which was updated (possibly by revision approval) more recently than its translations.', 'revisionary');
		?>
		</div>

		</p>
		</td></tr>

		<?php endif;?>

		</table>
	<?php endif; // any options accessable in this section

endif;
?>

</div>

</div>

<?php


// ------------------------- BEGIN Option Scope tab ---------------------------------
$tab = 'optscope';

if ( $sitewide ) : ?>
<?php
echo "<div id='rvy-optscope' style='clear:both;margin:0' class='rs-options agp_js_hide " . esc_attr($color_class) . "'>";

echo '<ul>';
$all_movable_options = array();

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
			printf( esc_html(_x( '%1$s%2$s%3$s (%4$s)', 'opentag option_tabname closetag (explanatory note)', 'revisionary' )), '<span class="rs-h3text">', esc_html($tab_caption), '</span>', esc_html($explanatory_caption) );
		else
			echo esc_html($tab_caption);
	} elseif ( $this->display_hints ) {
		echo esc_html($explanatory_caption);
	}

	echo '</div>';

	echo '<ul style="margin-left:2em">';

	foreach ( $sections as $section_name => $option_names ) {
		if ( empty( $sections[$section_name] ) )
			continue;

		echo '<li><strong>';

		if ( isset( $this->section_captions[$tab_name][$section_name] ) )
			echo esc_html($this->section_captions[$tab_name][$section_name]);
		else
			echo esc_html(ucwords(str_replace('_', ' ', $section_name)));

		echo '</strong><ul style="margin-left:2em">';

		foreach ( $option_names as $option_name ) {
			if ( $option_name && !empty($this->option_captions[$option_name]) ) {
				$all_movable_options []= $option_name;
				echo '<li>';

				$disabled = ( in_array( $option_name, array( 'file_filtering', 'mu_sitewide_groups' ) ) ) ? "disabled" : '';

				$id = "{$option_name}_sitewide";
				$val = isset( $rvy_options_sitewide[$option_name] );
				echo "<label for='" . esc_attr($id) . "'>";
				echo "<input name='rvy_options_sitewide[]' type='checkbox' id='" . esc_attr($id) . "' value='" . esc_attr($option_name) . "' " . esc_attr($disabled) . " " . checked('1', $val, false) . " />";

				printf( esc_html__( 'network-wide control of "%s"', 'revisionary' ), esc_html($this->option_captions[$option_name]) );

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
echo "<input type='hidden' name='rvy_all_movable_options' value='" . esc_attr($all_movable_options) . "' />";

endif; // any options accessable in this tab
// ------------------------- END Option Scope tab ---------------------------------


$this->all_options = implode(',', $this->all_options);
echo "<input type='hidden' name='all_options' value='" . esc_attr($this->all_options) . "' />";

echo "<input type='hidden' name='rvy_submission_topic' value='options' />";
?>
<p class="submit">
<input type="submit" name="rvy_submit" class="button button-primary" value="<?php echo esc_attr__('Save Changes', 'revisionary');?>" />
<input type="submit" name="rvy_defaults" class="button button-secondary" value="<?php echo esc_attr__('Revert to Defaults', 'revisionary') ?>" onclick="<?php 
echo "javascript:if (confirm('" 
. esc_html__( "All settings in this form (including those on unselected tabs) will be reset to DEFAULTS.  Are you sure?", 'revisionary' ) 
. "')) {return true;} else {return false;}";
?>" style="float:right;" />
</p>

</div>

</div>


<?php do_action('publishpress_revisions_settings_sidebar');?>

</div>

</form>

<p style='clear:both'></p>

<?php
do_action('revisionary_admin_footer');
?>

</div>

<?php
} // end function
} // end class RvyOptionUI


function rvy_options( $sitewide = false, $customize_defaults = false ) {
	$ui = RvyOptionUI::instance(compact('sitewide', 'customize_defaults'));
	$ui->options_ui($sitewide, $customize_defaults);
}
