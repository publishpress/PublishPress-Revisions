<?php
/**
 * @package     PublishPress\Revisions\RevisionaryAdmin
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

// @todo: separate out functions specific to Classic Editor

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

define ('RVY_URLPATH', plugins_url('', REVISIONARY_FILE));

class RevisionaryAdmin
{
	var $revision_save_in_progress;
	private $post_revision_count = array();
	private $hide_quickedit = array();
	private $trashed_revisions;

	function __construct() {
		global $pagenow, $post;

		$script_name = $_SERVER['SCRIPT_NAME'];
		$request_uri = esc_url($_SERVER['REQUEST_URI']);
		
		add_action('admin_head', array(&$this, 'admin_head'));
		add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts'));
		add_action('admin_print_scripts', [$this, 'admin_print_scripts'], 99);

		if ( ! defined('XMLRPC_REQUEST') && ! strpos($script_name, 'p-admin/async-upload.php' ) ) {
			global $blog_id;
			if ( RVY_NETWORK && ( is_main_site($blog_id) ) ) {
				require_once( dirname(__FILE__).'/admin_lib-mu_rvy.php' );
				add_action('admin_menu', 'rvy_mu_site_menu', 15 );
			}
			
			add_action('admin_menu', array(&$this,'build_menu'));
			
			if ( strpos($script_name, 'p-admin/plugins.php') ) {
				add_filter( 'plugin_row_meta', array(&$this, 'flt_plugin_action_links'), 10, 2 );
			}
		}

		add_action('admin_footer-edit.php', array(&$this, 'act_hide_quickedit') );
		add_action('admin_footer-edit-pages.php', array(&$this, 'act_hide_quickedit') );

		add_action('admin_head', array(&$this, 'add_editor_ui') );
		add_action('admin_head', array(&$this, 'act_hide_admin_divs') );
		
		if ( ! ( defined( 'SCOPER_VERSION' ) || defined( 'PP_VERSION' ) || defined( 'PPCE_VERSION' ) ) || defined( 'USE_RVY_RIGHTNOW' ) ) {
			require_once( 'admin-dashboard_rvy.php' );
		}
		
		// log this action so we know when to ignore the save_post action
		add_action('inherit_revision', array(&$this, 'act_log_revision_save') );

		add_filter('pre_post_status', array(&$this, 'flt_detect_revision_save'), 50 );
	
		if ( rvy_get_option( 'pending_revisions' ) ) {
			if ( strpos( $script_name, 'p-admin/edit.php') 
			|| strpos( $script_name, 'p-admin/edit-pages.php')
			|| ( strpos( $script_name, 'p-admin/post.php') )
			|| ( strpos( $script_name, 'p-admin/page.php') )
			|| false !== strpos( urldecode($request_uri), 'admin.php?page=rvy-revisions')
			|| false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=revisionary-q')
			) {

				if ( false === strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=revisionary-q') ) {
					add_filter( 'the_title', array(&$this, 'flt_post_title'), 10, 2 );
				}

				add_filter( 'get_delete_post_link', array(&$this, 'flt_delete_post_link'), 10, 2 );
			}
		}

		if ( rvy_get_option( 'pending_revisions' ) || rvy_get_option( 'scheduled_revisions' ) ) {
			if ('revision.php' == $pagenow) {
				require_once( dirname(__FILE__).'/history_rvy.php' );
				new RevisionaryHistory();
			}
		}

		if ( rvy_get_option( 'scheduled_revisions' ) ) {
			add_filter( 'dashboard_recent_posts_query_args', array(&$this, 'flt_dashboard_recent_posts_query_args' ) );
		}

		// ===== Special early exit if this is a plugin install script
		if ( strpos($script_name, 'p-admin/plugins.php') || strpos($script_name, 'p-admin/plugin-install.php') || strpos($script_name, 'p-admin/plugin-editor.php') ) {
			if (strpos($script_name, 'p-admin/plugin-install.php') && !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '=rvy')) {
				add_action('admin_print_scripts', function(){
					echo '<style type="text/css">#plugin_update_from_iframe {display:none;}</style>';
				});
			}
			
			return; // no further filtering on WP plugin maintenance scripts
		}

		// low-level filtering for miscellaneous admin operations which are not well supported by the WP API
		$hardway_uris = array(
		'p-admin/index.php',		'p-admin/revision.php',		'admin.php?page=rvy-revisions', 'admin.php?page=revisionary-q',
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

		add_action('admin_enqueue_scripts', [$this, 'fltAdminPostsListing'], 50);  // 'the_posts' filter is not applied on edit.php for hierarchical types

		add_filter('display_post_states', [$this, 'flt_display_post_states'], 50, 2);
		add_filter( 'page_row_actions', array($this, 'revisions_row_action_link' ) );
		add_filter( 'post_row_actions', array($this, 'revisions_row_action_link' ) );

		if (!empty($_REQUEST['post_status']) && ('trash' == $_REQUEST['post_status'])) {
			add_filter('display_post_states', [$this, 'fltTrashedPostState'], 20, 2 );
			add_filter('get_comments_number', [$this, 'fltCommentsNumber'], 20, 2);
		}

		if ( in_array( $pagenow, array( 'plugins.php', 'plugin-install.php' ) ) ) {
			require_once( dirname(__FILE__).'/admin-plugins_rvy.php' );
			$rvy_plugin_admin = new Rvy_Plugin_Admin();
		}

		if (!empty($_REQUEST['page']) && ('cms-tpv-page-page' == $_REQUEST['page'])) {
			add_action('pre_get_posts', [$this, 'pre_get_posts']);
		}

		add_filter('presspermit_disable_exception_ui', [$this, 'fltDisableExceptionUI'], 10, 4);

		add_filter('presspermit_status_control_scripts', [$this, 'fltDisableStatusControlScripts']);

		add_filter('cme_plugin_capabilities', [$this, 'fltPublishPressCapsSection']);

		add_action('admin_menu', [$this, 'actSettingsPageMaybeRedirect'], 999);
	}

	public function fltPublishPressCapsSection($section_caps) {
		$section_caps['PublishPress Revisions'] = ['edit_others_drafts', 'edit_others_revisions', 'list_others_revisions'];
		return $section_caps;
	}

	public function fltDisableStatusControlScripts($enable_scripts) {
		if ($post_id = rvy_detect_post_id()) {
			if ($post = get_post($post_id)) {
				if (!empty($post) && rvy_is_revision_status($post->post_status)) {
					$enable_scripts = false;
				}
			}
		}
		
		return $enable_scripts;
	}

	public function fltDisableExceptionUI($disable, $src_name, $post_id, $post_type = '') {
		global $pagenow;

		if (!empty($pagenow) && ('term.php' == $pagenow)) {
			return $disable;
		}
		
		if (!$post_id) {
			// Permissions version < 3.1.4 always passes zero value $post_id
			$post_id = rvy_detect_post_id();
		}

		if ($post_id) {
			$post_status = get_post_field('post_status', $post_id);

			if (in_array($post_status, ['pending-revision', 'future-revision'])) {
				return true;
			}
			
			if (!agp_user_can('edit_post', $post_id, '', ['skip_revision_allowance' => true])) {
				return true;
			}
		}

		return $disable;
	}

	// Prevent PublishPress Revisions statuses from confusing the page listing
	public function pre_get_posts($wp_query) {
		$stati = array_diff(get_post_stati(), apply_filters('revisionary_cmstpv_omit_statuses', ['pending-revision', 'future-revision'], rvy_detect_post_type()));
		$wp_query->query['post_status'] = $stati;
		$wp_query->query_vars['post_status'] = $stati;
	}

	public function fltAdminPostsListing() {
		global $wpdb, $wp_query, $typenow;

		$listed_ids = array();

		if ( ! empty( $wp_query->posts ) ) {
			foreach ($wp_query->posts as $row) {
				$listed_ids[] = $row->ID;
			}	
		}

		if ($listed_ids) {
			$id_csv = implode("','", array_map('intval', $listed_ids));
			$results = $wpdb->get_results(
				"SELECT comment_count AS published_post, COUNT(comment_count) AS num_revisions FROM $wpdb->posts WHERE comment_count IN('$id_csv') AND post_status IN ('pending-revision', 'future-revision') GROUP BY comment_count"
			);
			
			foreach($results as $row) {
				$this->post_revision_count[$row->published_post] = $row->num_revisions;
			}
		}

		static $can_edit_published;
		static $can_edit_others;
		static $listed_post_statuses;

		if (is_null($can_edit_others) && ! empty( $wp_query->posts ) ) {
			if (!$type_obj = get_post_type_object($typenow)) {
				$limit_quickedit = false;
				return;
			}
	
			$can_edit_others = agp_user_can($type_obj->cap->edit_others_posts, 0, '', array('skip_revision_allowance' => true));

			$can_edit_published = isset($type_obj->cap->edit_published_posts) && agp_user_can($type_obj->cap->edit_published_posts, 0, '', array('skip_revision_allowance' => true));
			
			if (!$can_edit_others || !$can_edit_published) {
				$listed_post_statuses = array();
				$ids = array();
				foreach ($wp_query->posts as $row) {
					$ids []= $row->ID;
				}

				$id_csv = implode("','", array_map('intval', $ids));
				$results = $wpdb->get_results("SELECT ID, post_status FROM $wpdb->posts WHERE ID IN ('$id_csv')");
				foreach($results as $row ) {
					$listed_post_statuses[$row->ID] = $row->post_status;
				}
			}
		}
		
		if ($can_edit_others && $can_edit_published) {
			return;
		}

		if (!empty($wp_query->posts)) {
			foreach ($wp_query->posts as $row) {
				if (in_array($listed_post_statuses[$row->ID], rvy_filtered_statuses())) {
					// @todo: better cap check precision for filter-applied statuses
					if (!$can_edit_published || (!$can_edit_others && !rvy_is_post_author($row))) {
						$this->hide_quickedit []= $row->ID;
					}
				}
			}
		}
	}

	private function logTrashedRevisions() {
		global $wpdb, $wp_query;
			
		if (!empty($wp_query) && !empty($wp_query->posts)) {
			$listed_ids = [];
			
			foreach($wp_query->posts AS $row) {
				$listed_ids []= $row->ID;
			}

			$listed_post_csv = implode("','", array_map('intval', $listed_ids));
			$this->trashed_revisions = $wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rvy_base_post_id' AND post_id IN ('$listed_post_csv')");
		} else {
			$this->trashed_revisions = [];
		}
	}

	/**
	 * Adds "Pending Revision" or "Scheduled Revision" to the list of display states for trashed revisions in the Posts list table.
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 * @return array Filtered array of post display states.
	 */
	public function fltTrashedPostState($post_states, $post) {
		if (!$post->comment_count) { // revisions buffer base post id to comment_count column for perf
			return $post_states;
		}

		if (!isset($this->trashed_revisions)) {
			$this->logTrashedRevisions();
		}

		if (in_array($post->ID, $this->trashed_revisions)) {		
			if ($orig_revision_status = get_post_meta($post->ID, '_wp_trash_meta_status', true)) {
				if ($status_obj = get_post_status_object($orig_revision_status)) {
					$post_states['rvy_revision'] = $status_obj->label;
				}
			}

			if (!isset($post_states['rvy_revision'])) {
				$post_states['rvy_revision'] = __('Pending Revision', 'revisionary');
			}
		}

		return $post_states;
	}

	function fltCommentsNumber($comment_count, $post_id) {
		if (isset($this->trashed_revisions) && in_array($post_id, $this->trashed_revisions)) {
			$comment_count = 0;
		}

		return $comment_count;
	}

	function flt_display_post_states($post_states, $post) {
		if (!empty($this->post_revision_count[$post->ID]) && !defined('REVISIONARY_SUPPRESS_POST_STATE_DISPLAY')) {
			$post_states []= __('Has Revision', 'revisionary');
		}

		return $post_states;
	}

	function revisions_row_action_link($actions = array()) {
		global $post;

		if (empty($this->post_revision_count[$post->ID])) {
			return $actions;
		}

		if ( 'trash' != $post->post_status && current_user_can( 'edit_post', $post->ID ) && wp_check_post_lock( $post->ID ) === false ) {
			$actions['revision_queue'] = "<a href='admin.php?page=revisionary-q&published_post=$post->ID'>" . __('Revision Queue', 'revisionary') . '</a>';
		}

		return $actions;
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
			$query_args['post_status'] = array('future', 'future-revision');

			add_filter( 'posts_clauses_request', array( &$this, 'flt_dashboard_query_clauses' ) );
		}

		return $query_args;
	}

	function flt_dashboard_query_clauses( $clauses ) {
		global $wpdb, $revisionary;

		$types = array_keys($revisionary->enabled_post_types);
		$post_types_csv = implode( "','", $types );
		$clauses['where'] = str_replace( "$wpdb->posts.post_type = 'post'", "$wpdb->posts.post_type IN ('$post_types_csv')", $clauses['where'] );

		remove_filter( 'posts_clauses_request', array( &$this, 'flt_dashboard_query_clauses' ) );
		return $clauses;
	}

	function handle_submission_redirect() {
		global $revisionary;

		if ( $revised_post = get_post( (int) $_REQUEST['post_id'] ) ) {
			$status = sanitize_key( $_REQUEST['revision_submitted'] );

			// Workaround for Gutenberg stripping published thumbnail, page template on revision creation
			foreach(['_thumbnail_id', '_wp_page_template'] as $meta_key) {
				if ($archived_val = rvy_get_transient("_archive_{$meta_key}_{$revised_post->ID}")) {
					rvy_update_post_meta($revised_post->ID, $meta_key, $archived_val);
					rvy_delete_transient("_archive_{$meta_key}_{$revised_post->ID}");
				}
			}

			if ( $revisions = rvy_get_post_revisions( $revised_post->ID, $status, array( 'order' => 'DESC', 'orderby' => 'ID' ) ) ) {  // @todo: retrieve revision_id in block editor js, pass as redirect arg
				foreach( $revisions as $revision ) {
					if (rvy_is_post_author($revision)) {
						if ( time() - strtotime( $revision->post_modified_gmt ) < 90 ) { // sanity check in finding the revision that was just submitted
							$args = array( 'revision_id' => $revision->ID, 'published_post' => $revised_post, 'object_type' => $revised_post->post_type );
							if ( ! empty( $_REQUEST['cc'] ) ) {
								$args['selected_recipients'] = array_map('intval', explode( ',', $_REQUEST['cc'] ));
							}

							$revisionary->do_notifications( $status, $status, (array) $revised_post, $args );
						}
						
						if (apply_filters('revisionary_do_submission_redirect', true)) {
							rvy_halt( $revisionary->get_revision_msg( $revision, array( 'post_arr' => (array) $revision, 'post_id' => $revised_post->ID ) ) );
						}
					}
				}
			}
		}
	}
		
	function add_editor_ui() {
		global $revisionary, $pagenow;

		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
			global $post;

			if ( $post && rvy_is_supported_post_type($post->post_type)) {
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
					if (in_array($post->post_status, rvy_filtered_statuses()) || ('future' == $post->post_status)) {
						require_once( dirname(__FILE__).'/filters-admin-ui-item_rvy.php' );
						$revisionary->filters_admin_item_ui = new RevisionaryAdminFiltersItemUI();
					} elseif (rvy_is_revision_status($post->post_status) && !$revisionary->isBlockEditorActive()) {
						require_once( dirname(__FILE__).'/edit-revision-ui_rvy.php' );
						$revisionary->filters_admin_item_ui = new RevisionaryEditRevisionUI();
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
	
		if ( ! empty($post) && in_array( $post->post_status, $stati ) && ! current_user_can( $type_obj->cap->publish_posts ) && rvy_is_supported_post_type($post->post_type) ) :
			$datef = __( 'M j, Y @ g:i a', 'revisionary' );
			if ( 0 != $post->ID ) {
				if ( 'future' == $post->post_status ) { // scheduled for publishing at a future date
					$stamp = __('Scheduled for: %s');
				} else if ( 'publish' == $post->post_status || 'private' == $post->post_status ) { // already published
					$stamp = __('Published on: %s');
				} else if ( '0000-00-00 00:00:00' == $post->post_date_gmt ) { // draft, 1 or more saves, no date specified
					$stamp = __('Publish <b>immediately</b>');
				} else if ( time() < strtotime( $post->post_date_gmt . ' +0000' ) ) { // draft, 1 or more saves, future date specified
					$stamp = __('Schedule for: %s');
				} else { // draft, 1 or more saves, date specified
					$stamp = __('Publish on: %s');
				}
				$date = '<b>' . date_i18n( $datef, strtotime( $post->post_date ) ) . '</b>';
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

		if (!rvy_get_option('pending_revisions')) {
			return;
		}

		if (empty($post) || (!in_array($post->post_status, rvy_filtered_statuses()) && ('future' != $post->post_status)) || !rvy_is_supported_post_type($post->post_type)) {
			return;
		}

		if ( $type_obj = get_post_type_object( $post->post_type ) ) {
			if ( ! agp_user_can( $type_obj->cap->edit_post, $post->ID, '', array( 'skip_revision_allowance' => true ) ) ) {
				return;
			}
		}

		$caption = apply_filters('revisionary_pending_checkbox_caption_classic', __( 'Save as Pending Revision', 'revisionary' ), $post);
		$checked = (apply_filters('revisionary_default_pending_revision', false, $post )) ? "checked='checked'" : '';
		
		$title = esc_attr(__('Do not publish current changes yet, but save to Revision Queue', 'revisionary'));
		echo "<div style='margin: 0.5em'><label for='rvy_save_as_pending_rev' title='" . $title . "'><input type='checkbox' style='width: 1em; min-width: 1em; text-align: right;' name='rvy_save_as_pending_rev' value='1' $checked id='rvy_save_as_pending_rev' /> $caption</label></div>";
	}
	
	function act_log_revision_save() {
		$this->revision_save_in_progress = true;
	}
	
	function flt_detect_revision_save( $post_status ) {
		if (rvy_is_revision_status($post_status)) {
			$this->revision_save_in_progress = true;
		}
	
		return $post_status;
	}
	
	// adds an Options link next to Deactivate, Edit in Plugins listing
	function flt_plugin_action_links($links, $file) {
		if ($file == plugin_basename(REVISIONARY_FILE)) {
			$page = ( RVY_NETWORK ) ? 'rvy-net_options' : 'revisionary-settings';
			$links[] = "<a href='admin.php?page=$page'>" . __awp('Settings') . "</a>";
		}
			
		return $links;
	}
	
	function admin_scripts() {
		wp_enqueue_style('revisionary', RVY_URLPATH . '/admin/revisionary.css', [], REVISIONARY_VERSION);

		global $pagenow, $post, $revisionary;
		if ( ('post.php' == $pagenow) && (('revision' == $post->post_type) || rvy_is_revision_status($post->post_status)) ) {
			wp_enqueue_style('rvy-revision-edit', RVY_URLPATH . '/admin/rvy-revision-edit.css', [], REVISIONARY_VERSION);

			if (!rvy_get_option('scheduled_revisions') && !$revisionary->isBlockEditorActive()) {
				?>
				<style>
				#misc-publishing-actions div.curtime {display:none;}
				</style>
				<?php
			}

			if (!class_exists('DS_Public_Post_Preview')) {
				?>
				<style>
				div.edit-post-post-visibility, div.edit-post-post-status div {
					display:none;
				}
				</style>
				<?php
			} else {
				?>
				<style>
				/*
				div.edit-post-post-status #inspector-checkbox-control-1 {
					display:none;
				}
				*/
				</style>
				<?php
			}
		}

		wp_enqueue_style('revisionary-admin-common', RVY_URLPATH . '/common/css/pressshack-admin.css', [], REVISIONARY_VERSION);

		if (defined('REVISIONARY_PRO_VERSION') && ('admin.php' == $pagenow) && !empty($_REQUEST['page']) && in_array($_REQUEST['page'], ['revisionary-settings', 'rvy-net_options']) ) {
			wp_enqueue_style('revisionary-settings', RVY_URLPATH . '/includes-pro/settings-pro.css', [], REVISIONARY_VERSION);
		}
 	}

	function admin_print_scripts() {
		if (class_exists('DS_Public_Post_Preview')) {
			$post_id = rvy_detect_post_id();
			
			if ($post_id && rvy_is_revision_status(get_post_field('post_status', $post_id))):?>
				<script type="text/javascript">
				/* <![CDATA[ */
				jQuery(document).ready( function($) {
					setInterval(function() {
						$("div.edit-post-post-status label:not(:contains('<?php _e('Enable public preview');?>')):not('[for=public-post-preview-url]')").closest('div').closest('div.components-panel__row').hide();
					}, 100);
				});
				/* ]]> */
				</script>
			<?php endif;
		}
	}

	function admin_head() {
		//echo '<link rel="stylesheet" href="' . RVY_URLPATH . '/admin/revisionary.css" type="text/css" />'."\n";

		global $pagenow, $revisionary;

		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && ! defined('RVY_PREVENT_PUBHIST_CAPTION') && ! $revisionary->isBlockEditorActive() ) {
			wp_enqueue_script( 'rvy_post', RVY_URLPATH . "/admin/post-edit.js", array('jquery'), RVY_VERSION, true );

			$args = array(
				'nowCaption' => 			 __( 'Current Time', 'revisionary' ),
				/*'pubHistoryCaption' => __( 'Publication History:', 'revisionary' ) */
			);
			wp_localize_script( 'rvy_post', 'rvyPostEdit', $args );
		}

		if( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions' ) ) {
			
			// add Ajax goodies we need for fancy publish date editing in Revisions Manager and role duration/content date limit editing Bulk Role Admin
			?>
			<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(document).ready( function($) {
				$('#rvy-rev-checkall').on('click', function() {
					$('.rvy-rev-chk').attr( 'checked', this.checked );
				});
			});
			/* ]]> */
			</script>
			<?php

			if ( ( empty( $_GET['action'] ) || in_array( $_GET['action'], array( 'view', 'edit' ) ) ) && ! empty( $_GET['revision'] ) ) {
				if ( $revision = get_post( (int) $_GET['revision'] ) ) {
					$published_id = rvy_post_id($revision->ID);

					if ( !rvy_is_revision_status($revision->post_status) || $post = get_post($published_id) ) {
						require_once( dirname(__FILE__).'/revision-ui_rvy.php' );
					}
				}
			}

			require_once( dirname(__FILE__).'/revision-ui_rvy.php' );
		}
		
		// all required JS functions are present in Role Scoper JS; TODO: review this for future version changes as necessary
		// TODO: replace some of this JS with equivalent JQuery
		if ( ! defined('SCOPER_VERSION') )
			wp_enqueue_script( 'rvy', RVY_URLPATH . "/admin/revisionary.js", array('jquery'), RVY_VERSION, true );
	}

	function moderation_queue() {
		require_once( dirname(__FILE__).'/revision-queue_rvy.php');
	}

	function build_menu() {
		global $current_user;

		if ( strpos( $_SERVER['REQUEST_URI'], 'wp-admin/network/' ) )
			return;
	
		$path = RVY_ABSPATH;

		// For Revisions Manager access, satisfy WordPress' demand that all admin links be properly defined in menu
		if ( false !== strpos( urldecode($_SERVER['REQUEST_URI']), 'admin.php?page=rvy-revisions' ) ) {
			add_submenu_page( 'none', __('Revisions', 'revisionary'), __('Revisions', 'revisionary'), 'read', 'rvy-revisions', 'rvy_include_admin_revisions' );
		}

		if ($types = rvy_get_manageable_types()) {
			$can_edit_any = false;

			foreach ($types as $_post_type) {
				if ($type_obj = get_post_type_object($_post_type)) {
					if (!empty($current_user->allcaps[$type_obj->cap->edit_posts]) || (is_multisite() && is_super_admin())) {
						$can_edit_any = true;
						break;
					}
				}
			}

			if (apply_filters('revisionary_add_menu', $can_edit_any)) {
				$_menu_caption = ( defined( 'RVY_MODERATION_MENU_CAPTION' ) ) ? RVY_MODERATION_MENU_CAPTION : __('Revisions');
				add_menu_page( __($_menu_caption, 'pp'), __($_menu_caption, 'pp'), 'read', 'revisionary-q', array(&$this, 'moderation_queue'), 'dashicons-backup', 29 );

				add_submenu_page('revisionary-q', __('Revision Queue', 'revisionary'), __('Revision Queue', 'revisionary'), 'read', 'revisionary-q', [$this, 'moderation_queue']);
			}
		}

		if ( ! current_user_can( 'manage_options' ) )
			return;

		global $rvy_default_options, $rvy_options_sitewide;
		
		if ( empty($rvy_default_options) )
			rvy_refresh_default_options();

		global $blog_id;
		if ( ! RVY_NETWORK || ( count($rvy_options_sitewide) != count($rvy_default_options) ) ) {
			add_submenu_page( 'revisionary-q', __('PublishPress Revisions Settings', 'revisionary'), __('Settings', 'revisionary'), 'read', 'revisionary-settings', 'rvy_omit_site_options');
			add_action('revisionary_page_revisionary-settings', 'rvy_omit_site_options' );	
		}

		if (!defined('REVISIONARY_PRO_VERSION')) {
			add_submenu_page(
	            'revisionary-q', 
	            __('Upgrade to Pro', 'revisionary'), 
	            __('Upgrade to Pro', 'revisionary'), 
	            'read', 
	            'revisionary-pro', 
	            'rvy_omit_site_options'
	        );
    	}
	}

	function act_hide_quickedit() {
		if ( empty( $this->hide_quickedit ) )
			return;

		$post_type = awp_post_type_from_uri();
		$type_obj = get_post_type_object($post_type);
		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
		<?php foreach( $this->hide_quickedit as $id ): ?>
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

				if ( $type_obj && !empty($post) && (rvy_is_revision_status($post->post_status) || ! agp_user_can( $type_obj->cap->edit_post, $object_id, '', array( 'skip_revision_allowance' => true ) ) ) ) { 
					//if ( 'page' == $object_type )
						//$unrevisable_css_ids = array( 'pageauthordiv', 'pagecustomdiv', 'pageslugdiv', 'pagecommentstatusdiv' );
				 	//else
						//$unrevisable_css_ids = array_merge( $unrevisable_css_ids, array( 'categorydiv', 'authordiv', 'postcustom', 'customdiv', 'slugdiv', 'commentstatusdiv', 'password-span', 'trackbacksdiv',  'tagsdiv-post_tag', 'visibility', 'edit-slug-box', 'postimagediv', 'ef_editorial_meta' ) );

					$unrevisable_css_ids = array( 'authordiv', 'visibility', 'postcustom', 'pagecustom' );  // todo: filter custom fields queries for revision_id

					//foreach( get_taxonomies( array(), 'object' ) as $taxonomy => $tx_obj )
					//	$unrevisable_css_ids []= ( $tx_obj->hierarchical ) ? "{$taxonomy}div" : "tagsdiv-$taxonomy";

					$unrevisable_css_ids = apply_filters( 'rvy_hidden_meta_boxes', $unrevisable_css_ids );
						
					if (rvy_is_revision_status($post->post_status)) {
						$unrevisable_css_ids = array_merge($unrevisable_css_ids, ['publish', 'slugdiv', 'edit-slug-box']);
					}

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

	function flt_delete_post_link( $link, $post_id ) {
      if ( strpos( $link, 'revision.php' ) ) {
			if ( $post_id ) {
				$link = "admin.php?page=rvy-revisions&amp;action=delete&amp;&amp;return=1&amp;revision=". $post_id;

				$link = "javascript:if(confirm('". __('Delete'). "?')) window.location='". wp_nonce_url( $link, 'delete-revision_' . $post_id ). "'";
			}
	    }

		return $link;
    }

	function flt_post_title ( $title, $id = '' ) {
		if ( $id )
			if ( $post = get_post( $id ) ) {
				if (rvy_is_revision_status($post->post_status)) {
					$title = sprintf( __( '%s (revision)', 'revisionary' ), $title );
				}
			}

		return $title;
	}
	
	// only added for edit.php and edit-pages.php
	function flt_get_post_time( $time, $format, $gmt ) {
		if ( function_exists('get_the_ID') && $post_id = get_the_ID() ) {
			if ( $post = get_post( $post_id ) ) {
				if ( 'pending-revision' == $post->post_status ) {
					if ( $gmt )
						$time = mysql2date($format, $post->post_modified_gmt, $gmt);
					else
						$time = mysql2date($format, $post->post_modified, $gmt);
				}
			}		
		}
		
		return $time;
	}

	function publishpressFooter() {
		if (defined('REVISIONARY_PRO_VERSION') && !rvy_get_option('display_pp_branding')) {
			return;
		}

		?>
		<footer>

		<div class="pp-rating">
		<a href="https://wordpress.org/support/plugin/revisionary/reviews/#new-post" target="_blank" rel="noopener noreferrer">
		<?php printf( 
			__('If you like %s, please leave us a %s rating. Thank you!', 'revisionary'),
			'<strong>PublishPress Revisions</strong>',
			'<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>'
			);
		?>
		</a>
		</div>

		<hr>
		<nav>
		<ul>
		<li><a href="https://publishpress.com/revisionary" target="_blank" rel="noopener noreferrer" title="<?php _e('About PublishPress Revisions', 'revisionary');?>"><?php _e('About', 'revisionary');?>
		</a></li>
		<li><a href="https://publishpress.com/documentation/revisions-start" target="_blank" rel="noopener noreferrer" title="<?php _e('PublishPress Revisions Documentation', 'revisionary');?>"><?php _e('Documentation', 'revisionary');?>
		</a></li>
		<li><a href="https://publishpress.com/contact" target="_blank" rel="noopener noreferrer" title="<?php _e('Contact the PublishPress team', 'revisionary');?>"><?php _e('Contact', 'revisionary');?>
		</a></li>
		<li><a href="https://twitter.com/publishpresscom" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-twitter"></span>
		</a></li>
		<li><a href="https://facebook.com/publishpress" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-facebook"></span>
		</a></li>
		</ul>
		</nav>

		<div class="pp-pressshack-logo">
		<a href="https://publishpress.com" target="_blank" rel="noopener noreferrer">

		<img src="<?php echo plugins_url('', REVISIONARY_FILE) . '/common/img/publishpress-logo.png';?>" />
		</a>
		</div>

		</footer>
		<?php
	}

    public function actSettingsPageMaybeRedirect()
    {
        foreach ([
                    'rvy-options' => 'revisionary-settings',
                 ] as $old_slug => $new_slug) {
            if (
                strpos($_SERVER['REQUEST_URI'], "page=$old_slug")
                && (false !== strpos($_SERVER['REQUEST_URI'], 'admin.php'))
            ) {
                global $submenu;

                // Don't redirect if pp-settings is registered by another plugin or theme
                foreach (array_keys($submenu) as $i) {
                    foreach (array_keys($submenu[$i]) as $j) {
                        if (isset($submenu[$i][$j][2]) && ($old_slug == $submenu[$i][$j][2])) {
                            return;
                        }
                    }
                }

                $arr_url = parse_url($_SERVER['REQUEST_URI']);
                wp_redirect(admin_url('admin.php?' . str_replace("page=$old_slug", "page=$new_slug", $arr_url['query'])));
                exit;
            }
        }
    }
} // end class RevisionaryAdmin
