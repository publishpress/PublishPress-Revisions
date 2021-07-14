<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

class RevisionaryAdminFiltersItemUI {
	private $pending_revisions = array();
	private $future_revisions = array();
	
	// note: in current implementations, this must be instatiated on admin_head action
	function __construct () {
		$this->add_js();
		$this->add_meta_boxes();
		$this->act_tweak_metaboxes();
	}
	
	function add_js() {
		global $post, $revisionary;

		if ( ! $revisionary->isBlockEditorActive() ) {
			if ( ! empty($post->post_type) )
				$object_type = $post->post_type;
			else
				$object_type = awp_post_type_from_uri();

			$object_id = rvy_detect_post_id();
			
			if (!$object_id || agp_user_can('edit_post', $object_id, '', ['skip_revision_allowance' => true])) {
				// for logged user who can fully edit a published post, clarify the meaning of setting future publish date
				
				// @todo: pass post id value, admin URL into JS to support ajax call
				?>
				<script type="text/javascript">
				/* <![CDATA[ */
				jQuery(document).ready( function($) {
					var postL10nschedule = "<?php _e('Schedule Revision', 'revisionary' )?>";

					// Apply "Schedule Revision" button caption even if post is private
					$('#timestampdiv a.save-timestamp').click( function() {
						var aa = $('#aa').val(), mm = $('#mm').val(), jj = $('#jj').val(), hh = $('#hh').val(), mn = $('#mn').val();
						var attemptedDate = new Date( aa, mm - 1, jj, hh, mn );
						var currentDate = new Date( $('#cur_aa').val(), $('#cur_mm').val() -1, $('#cur_jj').val(), $('#cur_hh').val(), $('#cur_mn').val() );

						if ( ! $('#timestampdiv a.save-timestamp').is(':visible') || ! $('#visibility-radio-private').attr('checked') || $('#publish').val() == postL10nschedule ) {
							//return;
						} else {
							// Confirm valid date
							if ( attemptedDate.getFullYear() == aa && (1 + attemptedDate.getMonth()) == mm && attemptedDate.getDate() == jj && attemptedDate.getMinutes() == mn ) {
								// If button caption should be "Schedule Revision," set it. Otherwise, no change
								if ( attemptedDate > currentDate ) {
									if ($('#original_post_status').val() != 'future') {
										$('#publish').val( postL10nschedule );
									}
								} 
							}
						}

						// If button caption should be "Schedule Revision," set it. Otherwise, no change
						if ( attemptedDate > currentDate ) {
							if ($('#original_post_status').val() != 'future') {
								$('#publish').val( postL10nschedule );
							}

							var data = {'rvy_ajax_field': 'set_future_date', 'rvy_ajax_value': 1, 'post_id': <?php echo $object_id;?>};
						} else {
							var data = {'rvy_ajax_field': 'set_future_date', 'rvy_ajax_value': 0, 'post_id': <?php echo $object_id;?>};
						}
						
						$.ajax({
							url: '<?php echo admin_url('');?>', 
							data: data,
							dataType: "html", 
							success: function(response){
							}, 
							error: function(data){}
							}
						);

					} );

					$('#rvy_save_as_pending_rev').click( function() {
						var set_for_pending = $('#rvy_save_as_pending_rev').prop('checked') ? 1 : 0;
						var data = {'rvy_ajax_field': 'save_as_pending', 'rvy_ajax_value': set_for_pending, 'post_id': <?php echo $object_id;?>};
						
						$.ajax({
							url: '<?php echo admin_url('');?>', 
							data: data,
							dataType: "html", 
							success: function(response){
							}, 
							error: function(data){}
							}
						);

					});
				});
				/* ]]> */
				</script>
				<?php
				
				return;
			}
		}

		wp_deregister_script( 'autosave' );
		wp_dequeue_script( 'autosave' );

?>

<?php
global $revisionary;
if ( ! $revisionary->isBlockEditorActive() ) :?>
	
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
	$('#publish').val("<?php _e('Submit Revision', 'revisionary' )?>");
	
	if (typeof(postL10n) != 'undefined') {
		postL10n.update = "<?php _e('Submit Revision', 'revisionary' )?>";
		postL10n.schedule = "<?php _e('Submit Scheduled Revision', 'revisionary' )?>";
	}

	setInterval(
		function() {
			if ($('#publish').val() != "<?php _e('Submit Revision', 'revisionary' )?>") {
				$('#publish').val("<?php _e('Submit Revision', 'revisionary' )?>");
			}
		}
		, 200
	);

	var rvyNowCaption = "<?php _e( 'Current Time', 'revisionary' );?>";
	$('#publishing-action #publish').show();
});
/* ]]> */
</script>

<?php endif;?>

	<?php
	/*
	$type_obj = get_post_type_object($post->post_type);

	// Use simpler criteria due to early execution of revisions.php access check in revisionary_main.php
	$can_publish = $type_obj && (
		isset($type_obj->cap->edit_published_posts)
		&& !empty($current_user->allcaps[$type_obj->cap->edit_published_posts]) 
		&& (($current_user->ID == $parent_post->ID) || !empty($current_user->allcaps[$type_obj->cap->edit_published_posts]))
	);
	*/

	if (!$revisionary->canEditPost($post, ['simple_cap_check' => true]) && defined('RVY_REVISOR_SUPPRESS_REVISIONS_LINK')):?>
	<style>
	div.num-revisions, div.misc-pub-revisions {display:none;}
	</style>
	<?php endif;?>
<?php
	}
	
	function add_meta_boxes() {
		$object_type = rvy_detect_post_type();
		
		if ( rvy_get_option( 'pending_revisions' ) ) {
			require_once( dirname(__FILE__).'/revision-ui_rvy.php' );
			
			add_meta_box( 'pending_revisions', __( 'Pending Revisions', 'revisionary'), 'rvy_metabox_revisions_pending', $object_type );
			
			$admin_notify = (string) rvy_get_option( 'pending_rev_notify_admin' );
			$author_notify = (string) rvy_get_option( 'pending_rev_notify_author' );
			
			if ( ( '1' === $admin_notify ) || ( '1' === $author_notify ) ) {
				add_meta_box( 'pending_revision_notify', __( 'Publishers to Notify of Your Revision', 'revisionary'), 'rvy_metabox_notification_list', $object_type );
			}
		}
			
		if ( rvy_get_option( 'scheduled_revisions' ) ) {
			require_once( dirname(__FILE__).'/revision-ui_rvy.php' );

			add_meta_box( 'future_revisions', __( 'Scheduled Revisions', 'revisionary'), 'rvy_metabox_revisions_future', $object_type );
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
		
		$src_name = 'post';

		$object_type = awp_post_type_from_uri();
		
		if ( empty($wp_meta_boxes[$object_type]) )
			return;

		$object_id = rvy_detect_post_id();

		// This block will be moved to separate class
		foreach ( $wp_meta_boxes[$object_type] as $context => $priorities ) {
			foreach ( $priorities as $priority => $boxes ) {
				foreach ( array_keys($boxes) as $box_id ) {
					// Remove Scheduled / Pending Revisions metabox if none will be listed
					// If a listing does exist, buffer it for subsequent display
					if ( 'pending_revisions' == $box_id ) {
						if ( ! $object_id || ! $this->pending_revisions = rvy_list_post_revisions( $object_id, 'pending-revision', array( 'format' => 'list', 'parent' => false, 'echo' => false ) ) )
							unset( $wp_meta_boxes[$object_type][$context][$priority][$box_id] );
					
					} elseif ( 'future_revisions' == $box_id ) {
						if ( ! $object_id || ! $this->future_revisions = rvy_list_post_revisions( $object_id, 'future-revision', array( 'format' => 'list', 'parent' => false, 'echo' => false ) ) )
							unset( $wp_meta_boxes[$object_type][$context][$priority][$box_id] );
							
					// Remove Revision Notification List metabox if this user is NOT submitting a pending revision
					} elseif ( 'pending_revision_notify' == $box_id ) {
						if (!$object_id || !rvy_get_option('pending_rev_notify_admin') || agp_user_can('edit_post', $object_id, '', ['skip_revision_allowance' => true])) {
							unset( $wp_meta_boxes[$object_type][$context][$priority][$box_id] );
						}
					}
				}
			}
		}		
	}

} // end class
