<?php
/**
 * @package     PublishPress\Revisions\RevisionManagerUI
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */
function rvy_metabox_notification_list() {
		global $revisionary;

		$notify_editors = (string) rvy_get_option('pending_rev_notify_admin');
		$notify_author = (string) rvy_get_option('pending_rev_notify_author');
	
		if ( ( '1' !== $notify_editors ) && ( '1' !== (string) $notify_author ) )
			return;
		
		$object_id = rvy_detect_post_id();

		require_once( dirname(REVISIONARY_FILE) . '/revision-workflow_rvy.php' );
		$result = Rvy_Revision_Workflow_UI::default_notification_recipients($object_id);

		foreach (['default_ids', 'post_publishers', 'publisher_ids'] as $var) {
            $$var = $result[$var];
        }

		require_once('agents_checklist_rvy.php');
		
		echo("<div id='rvy_cclist_pending_revision'>");
		
		if ( $default_ids ) {
			RevisionaryAgentsChecklist::agents_checklist( 'user', $post_publishers, 'prev_cc', $default_ids );
		} else {
			if ( ( 'always' === $notify_editors ) && $publisher_ids )
				_e( 'Publishers will be notified (but cannot be selected here).', 'revisionary' );
			else
				_e( 'No email notifications will be sent.', 'revisionary' );
		}
		
		echo('</div>');
}


function rvy_metabox_revisions( $status ) {
	global $revisionary;

	$property_name = $status . '_revisions';
	if ( ! empty( $revisionary->filters_admin_item_ui->$property_name ) )
		echo $revisionary->filters_admin_item_ui->$property_name;
	
	elseif ( ! empty( $_GET['post'] ) ) {
		$args = array( 'format' => 'list', 'parent' => false );
		rvy_list_post_revisions( (int) $_GET['post'], $status, $args );
	}
}

function rvy_metabox_revisions_pending() {
	rvy_metabox_revisions( 'pending-revision' );
}

function rvy_metabox_revisions_future() {
	rvy_metabox_revisions( 'future-revision' );
}

/**
 * Retrieve formatted date timestamp of a revision (linked to that revisions's page).
 *
 * @param int|object $revision Revision ID or revision object.
 * @param bool $link Optional, default is true. Link to revisions's page?
 * @return string i18n formatted datetimestamp or localized 'Current Revision'.
 */
function rvy_post_revision_title( $revision, $link = true, $date_field = 'post_date', $args = array() ) {
	global $revisionary;
	
	$defaults = array( 'post' => false, 'format' => 'list' );
	$args = array_merge( $defaults, (array) $args );
	foreach ( array_keys( $defaults ) as $var ) { $$var = $args[$var]; }
	
	if ( ! is_object($revision) )
		if ( !$revision = get_post( $revision ) )
			return $revision;

	$public_types = array_keys($revisionary->enabled_post_types);
	$public_types []= 'revision';
	
	if ( ! in_array( $revision->post_type, $public_types ) )
		return false;

	/* translators: revision date format, see http://php.net/date */
	$datef = _x( 'j F, Y @ g:i a', 'revision date format', 'revisionary' );
	
	$date = agp_date_i18n( $datef, strtotime( $revision->$date_field ) );
	
	// note: RS filter (un-requiring edit_published/private cap) will be applied to this cap check
	
	if ( $link ) { //&& current_user_can( 'edit_post', $revision->ID ) ) {    // revisions are listed in the Editor even if not editable / restorable / approvable
		if ('inherit' == $revision->post_status) {
			$link = "revision.php?revision=$revision->ID";
		} else {
			$link = rvy_preview_url($revision);
		}

		$date = "<a href='$link' target='_blank'>$date</a>";
	}

	$status_obj = get_post_status_object( $revision->post_status );
	
	if ( $status_obj && ( $status_obj->public || $status_obj->private ) ) {
		$currentf  = __( '%1$s (Current)', 'revisionary' );
		$date = sprintf( $currentf, $date );
		
	} elseif ( rvy_post_id($revision->ID) . "-autosave" === $revision->post_name ) {
		$autosavef = __( '%1$s (Autosave)', 'revisionary' );
		$date = sprintf( $autosavef, $date );
	}

	if ( in_array( $revision->post_status, array( 'inherit', 'pending-revision' ) ) && $post && ( 'list' == $format ) && ( 'post_modified' == $date_field ) ) {
		if ( $post->post_date != $revision->post_date ) {
			$datef = _x( 'j F, Y, g:i a', 'revision schedule date format', 'revisionary' );
			$revision_date = agp_date_i18n( $datef, strtotime( $revision->post_date ) );
		
			if ( 'pending-revision' == $revision->post_status ) {
				$currentf  = __( '%1$s <span class="rvy-revision-pubish-date">(Requested publication: %2$s)</span>', 'revisionary' );
			} else {
				$currentf  = __( '%1$s <span class="rvy-revision-pubish-date">(Publish date: %2$s)</span>', 'revisionary' );
			}

			$date = sprintf( $currentf, $date, $revision_date );
		}
	}
	
	return $date;
}

/**
 * Display list of a post's revisions (modified by PublishPress to include view links).
 *
 * Can output either a UL with edit links or a TABLE with diff interface, and
 * restore action links.
 *
 * Second argument controls parameters:
 *   (bool)   parent : include the parent (the "Current Revision") in the list.
 *   (string) format : 'list' or 'form-table'.  'list' outputs UL, 'form-table'
 *                     outputs TABLE with UI.
 *   (int)    right  : what revision is currently being viewed - used in
 *                     form-table format.
 *   (int)    left   : what revision is currently being diffed against right -
 *                     used in form-table format.
 *
 * @uses wp_get_post_revisions()
 * @uses wp_post_revision_title()
 * @uses get_edit_post_link()
 * @uses get_the_author_meta()
 *
 * @todo split into two functions (list, form-table) ?
 *
 * @param int|object $post_id Post ID or post object.
 * @param string|array $args See description {@link wp_parse_args()}.
 * @return null
 */
function rvy_list_post_revisions( $post_id = 0, $status = '', $args = null ) {
	if ( !$post = get_post( $post_id ) )
		return;
	
	$defaults = array( 'parent' => false, 'right' => false, 'left' => false, 'format' => 'list', 'type' => 'all', 'echo' => true, 'date_field' => '', 'current_id' => 0 );
	$args = wp_parse_args( $args, $defaults );

	foreach( array_keys( $defaults ) as $var ) {
		if ( ! isset( $$var ) ) {
			$$var = ( isset( $args[$var] ) ) ? $args[$var] : $defaults[$var];
		}
	}

	// link to publish date in Edit Form metaboxes, but modification date in Revisions Manager table
	if ( ! $date_field  ) {
		if ( 'list' == $format ) {
			//$date_field = ( in_array( $status, array( 'inherit', 'pending-revision' ) ) ) ? 'post_modified' : 'post_date';
			$date_field = 'post_modified';
			$sort_field = $date_field;
		} else {
			$date_field = 'post_modified';
			$sort_field = 'post_date';
		}
	} else {
		if ( ! $sort_field )
			$sort_field = $date_field;	
	}
			
	global $current_user, $revisionary;
	
	switch ( $type ) {
	case 'autosave' :
		if ( !$autosave = wp_get_post_autosave( $post->ID ) )
			return;
		$revisions = array( $autosave );
		break;
	case 'revision' : // just revisions - remove autosave later
	case 'all' :
	default :
		if ( !$revisions = rvy_get_post_revisions( $post->ID, $status, array( 'orderby' => $sort_field ) ) )
			return;
		break;
	}
	
	/* translators: post revision: 1: when, 2: author name */
	$titlef = _x( '%1$s by %2$s', 'post revision' );

	if ( $parent )
		array_unshift( $revisions, $post );

	$rows = '';
	$class = false;
	
	$can_edit_post = agp_user_can('edit_post', $post->ID, '', ['skip_revision_allowance' => true]);
	
	$hide_others_revisions = ! $can_edit_post && empty($current_user->allcaps['list_others_revisions']) && rvy_get_option('revisor_hide_others_revisions');
	
	$count = 0;
	$left_checked_done = false;
	$right_checked_done = false;
	$can_delete_any = false;
	
	$delete_msg = __( "The revision will be deleted. Are you sure?", 'revisionary' );
	$js_delete_call = "javascript:if (confirm('$delete_msg')) {return true;} else {return false;}";
	
	// TODO: should this buffer listed revision IDs instead of post ID ?
	if ( defined('RVY_CONTENT_ROLES') ) {
		$revisionary->content_roles->add_listed_ids( 'post', $post->post_type, $post->ID );
	}
	
	foreach ( $revisions as $revision ) {
		if ( $status && ( $status != $revision->post_status ) ) 		 // support arg to display only past / pending / future revisions
			if ( ('revision' == $revision->post_type) || rvy_is_revision_status($revision->post_status) )  // but always display current rev
				continue;
		
		if ( 'revision' === $type && wp_is_post_autosave( $revision ) )
			continue;
			
		if ( $hide_others_revisions && ( ( 'revision' == $revision->post_type ) || rvy_is_revision_status($revision->post_status) ) && !rvy_is_post_author($revision) )
			continue;
		
		// todo: set up buffering to restore this in case we (or some other plugin) impose revision-specific read capability
		//if ( ! current_user_can( "read_{$post->post_type}", $revision->ID ) )
		//	continue;

		$date = rvy_post_revision_title( $revision, true, $date_field, compact( 'post', 'format' ) );

		// Just track single post_author for revision. Changes to Authors taxonomy will be applied to published post.
		//
		//if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
		//	ob_start();
		//	do_action("manage_{$revision->post_type}_posts_custom_column", 'authors', $revision->ID);
		//	$name = ob_get_contents();
		//	ob_end_clean();
		//} else {
			$name = get_the_author_meta( 'display_name', $revision->post_author );
		//}

		if ( 'form-table' == $format ) {
			if ( ! $left_checked_done ) {
				if ( $left )
					$left_checked = ( $left == $revision->ID ) ? ' checked="checked"' : '';
				else
					$left_checked = ( $right == $revision->ID ) ? '' : ' checked="checked"';
			}
					
			if ( ! $right_checked_done ) {
				if ( $right )
					$right_checked = ( $right == $revision->ID ) ? ' checked="checked"' : '';
				else
					$right_checked = $left_checked ? '' : ' checked="checked"';
			}
			
			$actions = '';
			if ( $revision->ID == $current_id )
				$class = " class='rvy-revision-row rvy-current-revision'"; 
			elseif ( $class )
				$class = " class='rvy-revision-row'";
			else
				$class = " class='rvy-revision-row alternate'"; 
			
			$datef = __awp( 'M j, Y @ g:i a' );
			
			if ( $post->ID != $revision->ID ) {
				if ('inherit' == $revision->post_status) {
					// @todo: need this case?
					$preview_url = add_query_arg( 'preview', '1', get_post_permalink( $revision->ID ) . '&post_type=revision' );
				} else {
					$preview_url = rvy_preview_url($revision, ['post_type' => $post->post_type]);
				}

				$preview_link = '<a href="' . esc_url($preview_url) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $revision->post_title ) ) . '" rel="permalink">' . __( 'Preview' ) . '</a>';
				
				if ( $can_edit_post ) {
					if ( 'future-revision' == $status ) {
						$link = "admin.php?page=rvy-revisions&amp;action=unschedule&amp;revision={$revision->ID}";
						$actions .= '<a href="' . wp_nonce_url( $link, 'unschedule-revision_' . $revision->ID ) . '" class="rvy-unschedule">' . __('Unschedule') . '</a>&nbsp;|&nbsp;';
					}
					
					if ('inherit' == $status) {
						$link = "admin.php?page=rvy-revisions&amp;action=delete&amp;revision={$revision->ID}";
						$actions .= '<a href="' . wp_nonce_url( $link, 'delete-revision_' . $revision->ID ) . '" class="rvy-delete" onclick="' . $js_delete_call . '" >' . __awp('Delete') . '</a>';
					}
				}
				
				/*
				if ( ( strtotime($revision->post_date_gmt) > agp_time_gmt() ) && ( 'inherit' != $revision->post_status ) )
					$publish_date = '(' . agp_date_i18n( $datef, strtotime($revision->post_date) ) . ')';
				else
					$publish_date = '';
				*/
					
			} else {
				$preview_link = '<a href="' . site_url("?p={$revision->ID}&amp;mark_current_revision=1") . '" target="_blank">' . __awp( 'Preview' ) . '</a>';
				//$preview_link = '<a href="' . get_permalink( $revision->ID ) . '?mark_current_revision=1" target="_blank">' . __awp( 'Preview' ) . '</a>';
				
				// wp_post_revision_title() returns edit post link for current rev.  Convert it to a revisions.php link for viewing here like the rest
				if ( $post->ID == $revision->ID ) {
					$date = str_replace( "{$post->post_type}.php", 'revision.php', $date );
					$date = str_replace( 'action=edit', '', $date );
					$date = str_replace( 'post=', 'revision=', $date );
					$date = str_replace( '?&amp;', '?', $date );
					$date = str_replace( '?&', '?', $date );

					$date = str_replace( '&revision=', "&amp;revision_status=$status&amp;revision=", $date );
					$date = str_replace( '&amp;revision=', "&amp;revision_status=$status&amp;revision=", $date );
				}
				
				//$publish_date = agp_date_i18n( $datef, strtotime($revision->post_date) );
			}

			$rows .= "<tr$class>\n";
			$rows .= "\t<td>$date</td>\n";
			$rows .= "\t<td>$preview_link</td>\n";
			$rows .= "\t<td>$name</td>\n";
			$rows .= "\t<td class='action-links'>$actions</td>\n";
			if ( $post->ID != $revision->ID 
			&& ( $can_edit_post || ( ('pending-revision' == $status) && rvy_is_post_author($revision) ) )	// allow submitters to delete their own still-pending revisions
			) {
				$rows .= "\t<td style='text-align:right'><input class='rvy-rev-chk' type='checkbox' name='delete_revisions[]' value='" . $revision->ID . "' /></td>\n";
				$can_delete_any = true;
			} else
				$rows .= "\t<td></td>\n";
			
			$rows .= "</tr>\n";
			
			if ( $left_checked ) {
				$left_checked = '';
				$left_checked_done = true;
			}
			
			if ( $right_checked ) {
				$right_checked = '';
				$right_checked_done = true;
			}	
			
		} else {
			$title = sprintf( $titlef, $date, $name );
			$rows .= "\t<li>$title</li>\n";
		}
		
		$count++;
	}
	
	if ( 'form-table' == $format ) : 
		if ( $count > 1 ) :
	?>
<form action="" method="post">
<?php
wp_nonce_field( 'rvy-revisions' ); 
?>

<table class="widefat post-revisions" cellspacing="0">
	<col class="rvy-col1" />
	<col class="rvy-col2" />
	<col class="rvy-col3" />
	<col class="rvy-col4" />
	<col class="rvy-col5" />
<thead>
<tr>
	<th scope="col"><?php 
/*
switch( $status ) :
case 'inherit' :
	_e( 'Modified Date (click to view/restore)', 'revisionary' ); 
	break;
case 'pending-revision' :
	_e( 'Modified Date (click to view/approve)', 'revisionary' ); 
	break;
case 'future-revision' :
	_e( 'Modified Date (click to view/publish)', 'revisionary' );
	break;
endswitch;
*/
_e( 'Modified Date', 'revisionary' ); 
?></th>
	<th scope="col"></th>
	<th scope="col"><?php echo __awp( 'Author' ); ?></th>
	<th scope="col" class="action-links"><?php _e( 'Actions' ); ?></th>
	<th scope="col"  style='text-align:right'><input id='rvy-rev-checkall' type='checkbox' name='rvy-rev-checkall' value='' /></th>
</tr>
</thead>
<tbody>

<?php echo $rows; ?>

</tbody>
</table>

<?php if( $can_delete_any ):?>
<br />
<div class="alignright actions">
<select name="action">
<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="bulk-delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="rvy-action" id="rvy-action" class="button-secondary action" />
</div>
<?php endif; ?>

</form>

<?php
	   endif; // more than one table row displayed
	   
	   // we echoed the table, now return row count
	   return ( $count );
	   
	else :
		// return / echo a simple list
		$output = ( $rows ) ? "<ul class='post-revisions'>\n$rows</ul>" : '';
				
		if ( $echo ) {
			echo $output;
			return $count;	
		} else
			return $output;
	endif; // list or table

} // END FUNCTION rvy_list_post_revisions
