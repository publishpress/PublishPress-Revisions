<?php
/**
 * @package     Revisionary\RevisionaryAdmin
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

// menu icons by Jonas Rask: http://www.jonasraskdesign.com/

// @todo: separate out functions specific to Classic Editor

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

$wp_content = ( is_ssl() || ( is_admin() && defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN ) ) ? str_replace( 'http:', 'https:', WP_CONTENT_URL ) : WP_CONTENT_URL;
define ('RVY_URLPATH', $wp_content . '/plugins/' . RVY_FOLDER);

class RevisionaryAdmin
{
	var $tinymce_readonly;

	var $revision_save_in_progress;
	
	function __construct() {
		global $pagenow;

		$script_name = $_SERVER['SCRIPT_NAME'];
		$request_uri = $_SERVER['REQUEST_URI'];
		
		add_action('admin_head', array(&$this, 'admin_head'));
		
		if ( ! defined('XMLRPC_REQUEST') && ! strpos($script_name, 'p-admin/async-upload.php' ) ) {
			global $blog_id;
			if ( RVY_NETWORK && ( 1 == $blog_id ) ) {
				require_once( dirname(__FILE__).'/admin_lib-mu_rvy.php' );
				add_action('network_admin_menu', 'rvy_mu_site_menu' );
				add_action('admin_menu', 'rvy_mu_site_menu' );
			}
			
			add_action('admin_menu', array(&$this,'build_menu'));
			
			if ( strpos($script_name, 'p-admin/plugins.php') ) {
				add_filter( 'plugin_row_meta', array(&$this, 'flt_plugin_action_links'), 10, 2 );
			}
		}
		
		add_action('admin_footer-edit.php', array(&$this, 'act_hide_quickedit_for_revisions') );
		add_action('admin_footer-edit-pages.php', array(&$this, 'act_hide_quickedit_for_revisions') );
		
		add_action('admin_head', array(&$this, 'add_editor_ui') );
		add_action('admin_head', array(&$this, 'act_hide_admin_divs') );
		
		if ( ! ( defined( 'SCOPER_VERSION' ) || defined( 'PP_VERSION' ) || defined( 'PPCE_VERSION' ) ) || defined( 'USE_RVY_RIGHTNOW' ) ) {
			require_once( 'admin-dashboard_rvy.php' );
		}
		
		// log this action so we know when to ignore the save_post action
		add_action('inherit_revision', array(&$this, 'act_log_revision_save') );

		add_action('pre_post_type', array(&$this, 'flt_detect_revision_save') );
	
		if ( rvy_get_option( 'pending_revisions' ) ) {
			if ( strpos( $script_name, 'p-admin/edit.php') 
			|| strpos( $script_name, 'p-admin/edit-pages.php')
			|| ( strpos( $script_name, 'p-admin/post.php') )
			|| ( strpos( $script_name, 'p-admin/page.php') )
			|| false !== strpos( urldecode($request_uri), 'admin.php?page=rvy-revisions')
			) {
				add_filter( 'the_title', array(&$this, 'flt_post_title'), 10, 2 );
				add_filter( 'get_delete_post_link', array(&$this, 'flt_delete_post_link'), 10, 2 );
				add_filter( 'post_link', array(&$this, 'flt_preview_post_link'), 10, 2 );
				
				add_filter( 'page_row_actions', array(&$this, 'add_preview_action'), 10, 2 );
				add_filter( 'post_row_actions', array(&$this, 'add_preview_action'), 10, 2 );
			}
		}

		if ( rvy_get_option( 'pending_revisions' ) || rvy_get_option( 'scheduled_revisions' ) ) {
			add_filter( 'get_edit_post_link', array(&$this, 'flt_edit_post_link'), 10, 3 );
		}

		if ( rvy_get_option( 'scheduled_revisions' ) ) {
			add_filter( 'dashboard_recent_posts_query_args', array(&$this, 'flt_dashboard_recent_posts_query_args' ) );
		}

		// ===== Special early exit if this is a plugin install script
		if ( strpos($script_name, 'p-admin/plugins.php') || strpos($script_name, 'p-admin/plugin-install.php') || strpos($script_name, 'p-admin/plugin-editor.php') ) {
			return; // no further filtering on WP plugin maintenance scripts
		}

		// low-level filtering for miscellaneous admin operations which are not well supported by the WP API
		$hardway_uris = array(
		'p-admin/index.php',		'p-admin/revision.php',		'admin.php?page=rvy-revisions',
		'p-admin/post.php', 		'p-admin/post-new.php', 		'p-admin/page.php', 		'p-admin/page-new.php', 
		'p-admin/link-manager.php', 'p-admin/edit.php', 			'p-admin/edit-pages.php', 	'p-admin/edit-comments.php', 
		'p-admin/categories.php', 	'p-admin/link-category.php', 	'p-admin/edit-link-categories.php', 'p-admin/upload.php',
		'p-admin/edit-tags.php', 	'p-admin/profile.php',			'p-admin/link-add.php',	'p-admin/admin-ajax.php' );

		$hardway_uris = apply_filters('rvy_admin_hardway_uris', $hardway_uris);

		$uri = urldecode($request_uri);
		foreach ( $hardway_uris as $uri_sub ) {	// index.php can only be detected by index.php, but 3rd party-defined hooks may include arguments only present in REQUEST_URI
			if ( defined('XMLRPC_REQUEST') || strpos($script_name, $uri_sub) || strpos($uri, $uri_sub) ) {
				require_once(RVY_ABSPATH . '/hardway/hardway-admin_rvy.php');
				break;
			}
		}
		
		if ( strpos( $request_uri, 'edit.php' ) ) {
			if ( ! empty($_REQUEST['revision_submitted']) && ! empty($_REQUEST['post_id']) ) {
				add_action( 'admin_menu', array( &$this, 'handle_submission_redirect' ) );
			}
			
			add_filter( 'get_post_time', array(&$this, 'flt_get_post_time'), 10, 3 );

			if ( ! empty( $_REQUEST['revision_action'] ) ) {
				add_action( 'all_admin_notices', array( &$this, 'revision_action_notice' ) );
			}
		}

		add_action( 'post_submitbox_start', array( &$this, 'pending_rev_checkbox' ) );
		add_action( 'post_submitbox_misc_actions', array( &$this, 'publish_metabox_time_display' ) );

		if ( in_array( $pagenow, array( 'plugins.php', 'plugin-install.php' ) ) ) {
			require_once( dirname(__FILE__).'/admin-plugins_rvy.php' );
			$rvy_plugin_admin = new Rvy_Plugin_Admin();
		}
	}

	function revision_action_notice() {
			if ( ! empty($_GET['restored_post'] ) ) {
				$msg = __('The revision was restored.', 'revisionary');
				
			} elseif ( ! empty($_GET['scheduled'] ) ) {
				$msg = __('The revision was scheduled for publication.', 'revisionary');
		
			} elseif ( ! empty($_GET['published_post'] ) ) {
				$msg = __('The revision was published.', 'revisionary');
			} else {
				return;
			}

			?>
			<div class='updated'><?php echo $msg ?>
			</div>
			<?php	
	}

	function flt_dashboard_recent_posts_query_args( $query_args ) {
		if ( 'future' == $query_args['post_status'] ) {
			add_filter( 'posts_clauses_request', array( &$this, 'flt_dashboard_query_clauses' ) );
		}

		return $query_args;
	}

	function flt_dashboard_query_clauses( $clauses ) {
		global $wpdb;

		$types = array_merge( get_post_types( array( 'public' => true ), 'names' ), array( 'revision' ) );
		$post_types_csv = implode( "','", $types );
		$clauses['where'] = str_replace( "$wpdb->posts.post_type = 'post'", "$wpdb->posts.post_type IN ('$post_types_csv')", $clauses['where'] );

		remove_filter( 'posts_clauses_request', array( &$this, 'flt_dashboard_query_clauses' ) );
		return $clauses;
	}

	function handle_submission_redirect() {
		global $revisionary, $current_user;

		if ( $revised_post = get_post( (int) $_REQUEST['post_id'] ) ) {
			$status = sanitize_key( $_REQUEST['revision_submitted'] );

			if ( $revisions = rvy_get_post_revisions( $revised_post->ID, $status, array( 'order' => 'DESC', 'orderby' => 'ID' ) ) ) {  // @todo: retrieve revision_id in block editor js, pass as redirect arg
				foreach( $revisions as $revision ) {
					if ( $revision->post_author == $current_user->ID ) {
						if ( time() - strtotime( $revision->post_modified_gmt ) < 90 ) { // sanity check in finding the revision that was just submitted
							$args = array( 'revision_id' => $revision->ID, 'published_post' => $revised_post, 'object_type' => $revised_post->post_type );
							if ( ! empty( $_REQUEST['cc'] ) ) {
								$args['selected_recipients'] = explode( ',', $_REQUEST['cc'] );
							}

							$revisionary->do_notifications( $status, $status, (array) $revision, $args );
						}
						
						rvy_halt( $revisionary->get_revision_msg( $revision, array( 'post_arr' => (array) $revision, 'post_id' => $revised_post->ID ) ) );
					}
				}
			}
		}
	}

	function add_preview_action( $actions, $post ) {
		if ( 'revision' == $post->post_type ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				global $revisionary;
				static $block_editor;

				if ( ! isset( $block_editor ) ) {
					$block_editor = $revisionary->isBlockEditorActive();
				}

				$url = parse_url($_SERVER['REQUEST_URI']);
				$url_query = ( ! empty( $url['query'] ) ) ? $url['query'] : '';
				$redirect_url = admin_url('edit.php?' . $url_query);

				if ( $block_editor ) {
					$type_obj = get_post_type_object( $post->post_type );
					$edit_metacap = ( $type_obj ) ? $type_obj->cap->edit_post : 'edit_post';

					if ( agp_user_can( $edit_metacap, $post->ID, '', array( 'skip_revision_allowance' => true ) ) ) {
						$preview_link = '<a href="' . esc_url( add_query_arg( array( 'preview' => '1', 'post_type' => 'revision', 'rvy_redirect' => esc_url($redirect_url) ), get_post_permalink( $post->ID ) ) )  . '" title="' . esc_attr( sprintf( __( 'Preview & Approve &#8220;%s&#8221;' ), $post->post_title ) ) . '" rel="permalink">' . __( 'Approval' ) . '</a>';
					} else {
						$preview_link = '<a href="' . esc_url( add_query_arg( array( 'preview' => '1', 'post_type' => 'revision', 'rvy_redirect' => esc_url($redirect_url) ), get_post_permalink( $post->ID ) ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $post->post_title ) ) . '" rel="permalink">' . __( 'Preview' ) . '</a>';
					}

					$actions = array_merge( array( 'view' => $preview_link ), $actions );
				} else {
					$actions['view'] = '<a href="' . esc_url( add_query_arg( array( 'preview' => '1', 'post_type' => 'revision', 'rvy_redirect' => esc_url($redirect_url) ), get_post_permalink( $post->ID ) ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $post->post_title ) ) . '" rel="permalink">' . __( 'Preview' ) . '</a>';
				}
			}
		}
		
		return $actions;
	}
		
	function add_editor_ui() {
		global $revisionary;
		
		if ( in_array( $GLOBALS['pagenow'], array( 'post.php', 'post-new.php' ) ) ) {
			global $post;
			if ( $post ) {
				if ( $revisionary->isBlockEditorActive() ) {
					if ( ! $post->ID && ! is_content_administrator_rvy() ) {
						$type_obj = get_post_type_object( $post->post_type );
						if ( $type_obj && ! agp_user_can( $type_obj->cap->edit_post, $post->ID, '', array( 'skip_revision_allowance' => true ) ) ) {
							wp_deregister_script( 'autosave' );
							wp_dequeue_script( 'autosave' );
						}
					}
				}

				if ( $status_obj = get_post_status_object( $post->post_status ) ) {
					// only apply revisionary UI for currently published or scheduled posts
					if ( $status_obj->public || $status_obj->private || ( 'future' == $post->post_status ) ) {
						require_once( dirname(__FILE__).'/filters-admin-ui-item_rvy.php' );
						$GLOBALS['revisionary']->filters_admin_item_ui = new RevisionaryAdminFiltersItemUI();
					}
				}
			}
		}	
	}
	
	function publish_metabox_time_display() {
		global $post, $action;
	
		if ( ! rvy_get_option( 'scheduled_revisions' ) ) {
			return;
		}
	
		$stati = get_post_stati( array( 'public' => true, 'private' => true ), 'names', 'or' );
	
		if ( empty($post) || ! $type_obj = get_post_type_object($post->post_type) ) {
			return;
		}
	
		if ( ! empty($post) && in_array( $post->post_status, $stati ) && ! current_user_can( $type_obj->cap->publish_posts ) ) :
			$datef = __( 'M j, Y @ g:i a' );
			if ( 0 != $post->ID ) {
				if ( 'future' == $post->post_status ) { // scheduled for publishing at a future date
					$stamp = __('Scheduled for: <b>%1$s</b>');
				} else if ( 'publish' == $post->post_status || 'private' == $post->post_status ) { // already published
					$stamp = __('Published on: <b>%1$s</b>');
				} else if ( '0000-00-00 00:00:00' == $post->post_date_gmt ) { // draft, 1 or more saves, no date specified
					$stamp = __('Publish <b>immediately</b>');
				} else if ( time() < strtotime( $post->post_date_gmt . ' +0000' ) ) { // draft, 1 or more saves, future date specified
					$stamp = __('Schedule for: <b>%1$s</b>');
				} else { // draft, 1 or more saves, date specified
					$stamp = __('Publish on: <b>%1$s</b>');
				}
				$date = date_i18n( $datef, strtotime( $post->post_date ) );
			} else { // draft (no saves, and thus no date specified)
				$stamp = __('Publish <b>immediately</b>');
				$date = date_i18n( $datef, strtotime( current_time('mysql') ) );
			}
			?>
			<div class="misc-pub-section curtime">
				<span id="timestamp">
				<?php printf($stamp, $date); ?></span>
				<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js"><?php _e('Edit') ?></a>
				<div id="timestampdiv" class="hide-if-js"><?php touch_time(($action == 'edit'), 1); ?></div>
			</div><?php // /misc-pub-section ?>
		<?php endif;
	}
	
	function pending_rev_checkbox() {
		global $post;

		$status_obj = get_post_status_object( $post->post_status );
		
		if ( ! $status_obj || ( ! $status_obj->public && ! $status_obj->private && ( 'future' != $post->post_status ) ) ) {
			return;
		}

		if ( $type_obj = get_post_type_object( $post->post_type ) ) {
			if ( ! agp_user_can( $type_obj->cap->edit_post, $post->ID, '', array( 'skip_revision_allowance' => true ) ) ) {
				return;
			}
		}

		$caption = __( 'Send to Approval Queue', 'revisionary' );
		$checked = (apply_filters('revisionary_default_pending_revision', false, $post )) ? "checked='checked'" : '';

		echo "<div style='margin: 0.5em'><label for='rvy_save_as_pending_rev'><input type='checkbox' style='width: 1em; min-width: 1em; text-align: right;' name='rvy_save_as_pending_rev' value='1' $checked id='rvy_save_as_pending_rev' /> $caption</label></div>";
	}
	
	function act_log_revision_save() {
		$this->revision_save_in_progress = true;
	}
	
	function flt_detect_revision_save( $post_type ) {
		if ( 'revision' == $post_type )
			$this->revision_save_in_progress = true;
	
		return $post_type;
	}
	
	// adds an Options link next to Deactivate, Edit in Plugins listing
	function flt_plugin_action_links($links, $file) {
		if ( $file == RVY_BASENAME ) {
			$page = ( RVY_NETWORK ) ? 'rvy-site_options' : 'rvy-options';
			$links[] = "<a href='admin.php?page=$page'>" . __awp('Settings') . "</a>";
		}
			
		return $links;
	}
	
	/*
	// if a revision id or post object is passed in, returns parent post object
	function get_published_post( $_post ) {
		if ( is_object( $_post ) ) {
			if ( ! in_array( $_post->post_type, array( 'revision', '_revision' ) ) && ( 'inherit' != $_post->post_status ) )
				return $_post;
			else
				$_post = $_post->ID;
		}

		global $wpdb;
		
		$__post = $wpdb->get_row( "SELECT * FROM $wpdb->posts WHERE ID = '$_post'" ); // direct query so we don't fool ourself with subverted cache entry

		if ( in_array( $__post->post_type, array( 'revision', '_revision' ) ) || ( 'inherit' == $__post->post_status ) ) {
			$__post = get_post( $__post->post_parent );
		}

		return $__post;
	}
	*/
	
	function admin_head() {
		echo '<link rel="stylesheet" href="' . RVY_URLPATH . '/admin/revisionary.css" type="text/css" />'."\n";

		add_filter( 'contextual_help_list', array(&$this, 'flt_contextual_help_list'), 10, 2 );
		
		global $pagenow, $revisionary;

		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && ! defined('RVY_PREVENT_PUBHIST_CAPTION') && ! $revisionary->isBlockEditorActive() ) {
			wp_enqueue_script( 'rvy_post', RVY_URLPATH . "/admin/post-edit.js", array('jquery'), RVY_VERSION, true );

			$args = array(
				'nowCaption' => 			 __( 'Current Time', 'revisionary' ),
				'pubHistoryCaption' => __( 'Publication History:', 'revisionary' ) 
			);
			wp_localize_script( 'rvy_post', 'rvyPostEdit', $args );
		}

		// Gutenberg defaults to pending revision approval via front end preview
		if ( ( 'edit.php' == $pagenow ) && $revisionary->isBlockEditorActive() ) :?>
			<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready( function($) {
				$('table.wp-list-table a.row-title').each(function() {
					$(this).attr('href', $(this).attr('href').replace('wp-admin/admin.php?page=rvy-revisions&action=view&revision=','?post_type=revision&p=') + '&preview=1');
				});
			});
			/* ]]> */
			</script>
		<?php endif;

		if( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions' ) ) {
			
			// add Ajax goodies we need for fancy publish date editing in Revisions Manager and role duration/content date limit editing Bulk Role Admin
			?>
			<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready( function($) {
				$('#rvy-rev-checkall').click(function() {
					$('.rvy-rev-chk').attr( 'checked', this.checked );
				});
			});
			/* ]]> */
			</script>
			<?php
			wp_enqueue_script( 'rvy_edit', RVY_URLPATH . "/admin/revision-edit.js", array('jquery'), RVY_VERSION, true );
			
			if ( ( empty( $_GET['action'] ) || in_array( $_GET['action'], array( 'view', 'edit' ) ) ) && ! empty( $_GET['revision'] ) ) {
				if ( $revision = get_post( $_GET['revision'] ) ) {
					if ( ( 'revision' != $revision->post_type ) || $post = get_post( $revision->post_parent ) ) {
				
						// determine if tinymce textarea should be editable for displayed revision
						global $current_user;

						if ( 'revision' != $revision->post_type ) // we retrieved the parent (current revision) that corresponds to requested revision
							$read_only = true;

						elseif ( ( 'pending' == $revision->post_status ) && ( $revision->post_author == $current_user->ID ) )
							$read_only = false;
						else {
							if ( $type_obj = get_post_type_object( $post->post_type ) )
								$read_only = ! current_user_can( $type_obj->cap->edit_post, $revision->post_parent );
						}

						$this->tinymce_readonly = $read_only;
						
						require_once( dirname(__FILE__).'/revision-ui_rvy.php' );
						
						add_filter( 'tiny_mce_before_init', 'rvy_log_tiny_mce_params', 1, 2 );
						add_filter( 'tiny_mce_before_init', 'rvy_tiny_mce_params', 998 );	// this is only applied to revisionary admin URLs, so not shy about dropping the millennial hammer

						if ( $read_only )
							add_filter( 'tiny_mce_before_init', 'rvy_tiny_mce_readonly', 999 );
						
						// WP Super Edit Workaround - $wp_super_edit->is_tinymce property is currently set true only if URI matches unfilterable list: '/tiny_mce_config\.php|page-new\.php|page\.php|post-new\.php|post\.php/'
						global $wp_super_edit;
						
						if ( ! empty($wp_super_edit) && ! $wp_super_edit->is_tinymce )
							include_once( dirname(__FILE__).'/super-edit-helper_rvy.php' );
						//
					}
				}
			}
			
			// need this for editor swap from visual to html
			if ( empty($read_only) )
				wp_print_scripts( 'editor', 'quicktags' );
			else {
				wp_print_scripts( 'editor' );
				
// if the revision is read-only, also disable the HTML editing area and kill the toolbar which the_editor() forces in
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
	$('#ed_toolbar').hide();
	$('#content').attr('disabled', 'disabled');
});
/* ]]> */
</script>
<?php	
			} // endif read_only

			require_once( dirname(__FILE__).'/revision-ui_rvy.php' );

			rvy_revisions_js();
		}
		
		// all required JS functions are present in Role Scoper JS; TODO: review this for future version changes as necessary
		// TODO: replace some of this JS with equivalent JQuery
		if ( ! defined('SCOPER_VERSION') )
			wp_enqueue_script( 'rvy', RVY_URLPATH . "/admin/revisionary.js", array('jquery'), RVY_VERSION, true );
	}
	
	function flt_contextual_help_list ($help, $screen) {
		if ( is_object($screen) )
			$screen = $screen->id;
		
		if ( in_array( $screen, array( 'edit', 'post', 'settings_page_rvy-revisions', 'settings_page_rvy-options' ) ) ) {
			if ( ! isset($help[$screen]) )
				$help[$screen] = '';

			//$doc_url =
			//$help[$screen] .= sprintf(__('%1$s Revisionary Documentation%2$s', 'revisionary'), "<a href='$doc_url' target='_blank'>", '</a>')
			//$forum_url =
			//$help[$screen] .= ' ' . sprintf(__('%1$s Revisionary Support Forum%2$s', 'revisionary'), "<a href='$forum_url' target='_blank'>", '</a>');
		}

		return $help;
	}

	function build_menu() {
		if ( strpos( $_SERVER['REQUEST_URI'], 'wp-admin/network/' ) )
			return;
	
		$path = RVY_ABSPATH;

		// For Revisions Manager access, satisfy WordPress' demand that all admin links be properly defined in menu
		if ( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions' ) ) {
			add_submenu_page( 'none', __('Revisions', 'revisionary'), __('Revisions', 'revisionary'), 'read', 'rvy-revisions', 'rvy_include_admin_revisions' );
		}

		if ( ! current_user_can( 'manage_options' ) )
			return;

		global $rvy_default_options, $rvy_options_sitewide;
		
		if ( empty($rvy_default_options) )
			rvy_refresh_default_options();

		global $blog_id;
		if ( ! RVY_NETWORK || ( count($rvy_options_sitewide) != count($rvy_default_options) ) ) {
			add_options_page( __('Revisionary Settings', 'revisionary'), __('Revisionary', 'revisionary'), 'read', 'rvy-options');
			add_action('settings_page_rvy-options', 'rvy_omit_site_options' );	
		}
	}

	function act_hide_quickedit_for_revisions() {
		global $rvy_any_listed_revisions;
		
		if ( empty( $rvy_any_listed_revisions ) )
			return;

		$post_type = awp_post_type_from_uri();
		$type_obj = get_post_type_object($post_type);
		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
		<?php foreach( $rvy_any_listed_revisions as $id ): ?>
			$( '#<?php echo( 'post-' . $id );?> span.inline' ).hide();
		<?php endforeach; ?>
		});
		/* ]]> */
		</script>
		<?php
	}

	
	function act_hide_admin_divs() {
		// Hide unrevisionable elements if editing for revisions, regardless of Limited Editing Element settings
		//
		// TODO: allow revisioning of slug, menu order, comment status, ping status ?
		// TODO: leave Revisions metabox for links to user's own pending revisions
		if ( rvy_get_option( 'pending_revisions' ) ) {
			global $post;
			if ( ! empty($post->post_type) )
				$object_type = $post->post_type;
			else
				$object_type = awp_post_type_from_uri();

			$object_id = rvy_detect_post_id();

			if ( $object_id ) {
				$type_obj = get_post_type_object( $object_type );

				if ( $type_obj && ! agp_user_can( $type_obj->cap->edit_post, $object_id, '', array( 'skip_revision_allowance' => true ) ) ) { 
					//if ( 'page' == $object_type )
						$unrevisable_css_ids = array( 'pageparentdiv', 'pageauthordiv', 'pagecustomdiv', 'pageslugdiv', 'pagecommentstatusdiv' );
				 	//else
						$unrevisable_css_ids = array_merge( $unrevisable_css_ids, array( 'categorydiv', 'authordiv', 'postcustom', 'customdiv', 'slugdiv', 'commentstatusdiv', 'password-span', 'trackbacksdiv',  'tagsdiv-post_tag', 'visibility', 'edit-slug-box', 'postimagediv', 'ef_editorial_meta' ) );

					foreach( get_taxonomies( array(), 'object' ) as $taxonomy => $tx_obj )
						$unrevisable_css_ids []= ( $tx_obj->hierarchical ) ? "{$taxonomy}div" : "tagsdiv-$taxonomy";

					$unrevisable_css_ids = apply_filters( 'rvy_hidden_meta_boxes', $unrevisable_css_ids );
						
					echo( "\n<style type='text/css'>\n<!--\n" );
						
					foreach ( $unrevisable_css_ids as $id ) {
						// TODO: determine if id is a metabox or not
						
						// thanks to piemanek for tip on using remove_meta_box for any core admin div
						remove_meta_box($id, $object_type, 'normal');
						remove_meta_box($id, $object_type, 'advanced');
						
						// also hide via CSS in case the element is not a metabox
						echo "#$id { display: none !important; }\n";  // this line adapted from Clutter Free plugin by Mark Jaquith
					}
						
					echo "-->\n</style>\n";
					
					// display the current status, but hide edit link
					echo "\n<style type='text/css'>\n<!--\n.edit-post-status { display: none !important; }\n-->\n</style>\n";  // this line adapted from Clutter Free plugin by Mark Jaquith
				}
			}
		}	
	}

	function convert_link( $link, $topic, $operation, $args = '' ) {
		$defaults = array ( 'object_type' => '', 'id' => '' );
		$args = (array) $args;
		foreach( array_keys( $defaults ) as $var ) {
			$$var = ( isset( $args[$var] ) ) ? $args[$var] : $defaults[$var];
		}

		global $pagenow;
		
		if ( in_array( $pagenow, apply_filters( 'rvy_default_revision_link_pages', array( 'post.php' ), $link, $args ) ) )
			return $link;
		
		if ( 'revision' == $topic ) {
			if ( 'manage' == $operation ) {
				if ( strpos( $link, 'revision.php' ) ) {
					$link = str_replace( 'revision.php', 'admin.php?page=rvy-revisions&action=view', $link );
					$link = str_replace( '?revision=', "&amp;revision=", $link );
				}
			
			} elseif ( 'preview' == $operation ) {
				$link = add_query_arg( array( 'preview' => 1, 'post_type' => 'revision' ), $link );

			} elseif ( 'delete' == $operation ) {
				if ( $object_type && $id ) {
					$link = str_replace( "$object_type.php", 'admin.php?page=rvy-revisions', $link );
					$link = str_replace( '?post=', "&amp;revision=", $link );
					$link = wp_nonce_url( $link, 'delete-revision_' . $id );
				}
			} 
		}
		
		return $link;
	}
	
	function flt_delete_post_link( $link, $post_id ) {
      if ( strpos( $link, 'revision.php' ) ) {
			if ( $post_id ) {
				$link = "admin.php?page=rvy-revisions&amp;action=delete&amp;&amp;return=1&amp;revision=". $post_id;

				$link = "javascript:if(confirm('". __('Delete'). "?')) window.location='". wp_nonce_url( $link, 'delete-revision_' . $post_id ). "'";
			}
	    }

		return $link;
    }
	
	function flt_edit_post_link( $link, $id, $context ) {
		if ( $post = get_post( $id ) ) {
			if ( ( 'revision' == $post->post_type ) && in_array( $post->post_status, array( 'pending', 'future' ) ) ) {
				$link = $this->convert_link( $link, 'revision', 'manage' );

				global $rvy_any_listed_revisions;
				
				if ( ! isset( $rvy_any_listed_revisions ) )
					$rvy_any_listed_revisions = array();
				
				$rvy_any_listed_revisions []= $id;
			}
		}

		return $link;
	}
	
	function flt_preview_post_link( $link, $post ) {
		if ( 'revision' == $post->post_type ) {
			$link = $this->convert_link( $link, 'revision', 'preview' );
		}

		return $link;
	}
	
	function flt_post_title ( $title, $id = '' ) {
		if ( $id )
			if ( $post = get_post( $id ) )
				if ( 'revision' == $post->post_type )
					$title = sprintf( __( '%s (revision)', 'revisionary' ), $title );

		return $title;
	}
	
	// only added for edit.php and edit-pages.php
	function flt_get_post_time( $time, $format, $gmt ) {
		if ( function_exists('get_the_ID') && $post_id = get_the_ID() ) {
			if ( $post = get_post( $post_id ) ) {
				if ( ( 'revision' == $post->post_type ) && ( 'pending' == $post->post_status ) ) {
					if ( $gmt )
						$time = mysql2date($format, $post->post_modified_gmt, $gmt);
					else
						$time = mysql2date($format, $post->post_modified, $gmt);
				}
			}		
		}
		
		return $time;
	}
} // end class RevisionaryAdmin