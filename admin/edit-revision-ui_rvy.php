<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename(esc_url_raw($_SERVER['SCRIPT_FILENAME'])) )
	die();

/*
 * Revision Edit: UI modifications for Classic Editor
 */
class RevisionaryEditRevisionUI {
	function __construct () {
		add_action('admin_head', [$this, 'add_js']);
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
}
