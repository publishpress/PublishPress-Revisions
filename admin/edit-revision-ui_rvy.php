<?php
if( basename(__FILE__) == basename(esc_url_raw($_SERVER['SCRIPT_FILENAME'])) )
	die();

/*
 * Revision Edit: UI modifications for Classic Editor
 */
class RevisionaryEditRevisionUI {
	function __construct () {
		add_action('admin_head', [$this, 'add_js']);
		//add_action('admin_head', [$this, 'add_meta_boxes']);
		//add_action('admin_head', [$this, 'act_tweak_metaboxes'], 11);
	}

	function add_js() {
		wp_enqueue_style('rvy-revision-edit', RVY_URLPATH . '/admin/rvy-revision-edit.css', [], PUBLISHPRESS_REVISIONS_VERSION);

		if (!rvy_get_option('scheduled_revisions')) {
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
				display: none;
			}

			div.edit-post-post-status div.rvy-creation-ui,
			div.edit-post-post-status div.rvy-creation-ui div
			{
				display: inline;
			}
			</style>
			<?php
		}

		?>
		<style>
		div.edit-post-revision-status span {
			width: 45%;
			display: inline-block;
			text-align: left;
		}
		div.edit-post-post-status div.rvy-current-status {
			/*width: 100%;*/
			display: inline-flex !important;
			text-align: right;
			white-space: nowrap;
		}
		</style>
		<?php
	}

	/*
	function add_meta_boxes() {
		$object_type = rvy_detect_post_type();
		
		if (!$revision_id = rvy_detect_post_id()) {
			return;
		}

		// If editing a draft revision, allow Notification recipients (for revision submission) to be adjusted
		if ('draft' == get_post_field('post_status', $revision_id)) {
			require_once( dirname(__FILE__).'/revision-ui_rvy.php' );

			$admin_notify = (string) rvy_get_option( 'pending_rev_notify_admin' );
			$author_notify = (string) rvy_get_option( 'pending_rev_notify_author' );
			
			if ( ( '1' === $admin_notify ) || ( '1' === $author_notify ) ) {
				add_meta_box( 'pending_revision_notify', __( 'Publishers to Notify of Your Revision', 'revisionary'), 'rvy_metabox_notification_list', $object_type );
			}
		}
	}
	
	function act_tweak_metaboxes() {
		static $been_here;
		
		if ( isset($been_here) )
			return;

		$been_here = true;
		
		global $wp_meta_boxes;
		
		if ( empty($wp_meta_boxes) )
			return;
		
		$object_type = awp_post_type_from_uri();
		
		if ( empty($wp_meta_boxes[$object_type]) )
			return;

		$object_id = rvy_detect_post_id();

		// This block will be moved to separate class
		foreach ( $wp_meta_boxes[$object_type] as $context => $priorities ) {
			foreach ( $priorities as $priority => $boxes ) {
				foreach ( array_keys($boxes) as $box_id ) {
					// Remove Revision Notification List metabox if this user is NOT submitting a pending revision
					if ( 'pending_revision_notify' == $box_id ) {
						if (!$object_id || !rvy_get_option('pending_rev_notify_admin') || current_user_can('edit_post', $object_id)) {
							unset( $wp_meta_boxes[$object_type][$context][$priority][$box_id] );
						}
					}
				}
			}
		}		
	}
	*/
}
