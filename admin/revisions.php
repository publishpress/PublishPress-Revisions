<?php

if( basename(__FILE__) == basename(esc_url_raw($_SERVER['SCRIPT_FILENAME'])) )
	die( 'This page cannot be called directly.' );
	
/**
 * @package     PublishPress\Revisions\RevisionManager
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

include_once( dirname(__FILE__).'/revision-ui_rvy.php' ); 

global $revisionary;

if ( defined( 'FV_FCK_NAME' ) && current_user_can('activate_plugins') ) {
	echo( '<div class="error">' );
	_e( "<strong>Note:</strong> For visual display of revisions, add the following code to foliopress-wysiwyg.php:<br />&nbsp;&nbsp;if ( strpos( $" . "_SERVER['REQUEST_URI'], 'admin.php?page=rvy-revisions' ) ) return;", 'revisionary');
	echo( '</div><br />' );
}
//wp_reset_vars( array('revision', 'left', 'right', 'action', 'revision_status') );

if ( ! empty($_GET['revision']) )
	$revision_id = absint($_GET['revision']);

if ( ! empty($_GET['left']) )
	$left = absint($_GET['left']);
else
	$left = '';

if ( ! empty($_GET['right']) )
	$right = absint($_GET['right']);
else
	$right = '';
	
$revision_status = 'inherit';

if ( ! empty($_GET['action']) )
	$action = sanitize_key($_GET['action']);
else
	$action = '';

if ( ! empty($_GET['restored_post'] ) ) {
	$revision_id = (int) $_GET['restored_post'];
}

if ( empty($revision_id) && ! $left && ! $right ) {
	echo( '<div><br />' );
	_e( 'No revision specified.', 'revisionary');
	echo( '</div>' );
	return;
}

$revision_status_captions = array( 
	'inherit' => __( 'Past', 'revisionary' ), 
	'pending-revision' => __awp('Pending', 'revisionary'), 
	'future-revision' => __awp( 'Scheduled', 'revisionary' ) 
);

if( 'edit' == $action )
	$action = 'view';

switch ( $action ) :
case 'diff' :
	break;
case 'view' :
default :
	$left = 0;
	$right = 0;
	$h2 = '';
	
	if ( ! $revision = wp_get_post_revision( $revision_id ) ) {
		if ($revision = get_post($revision_id)) {
			if (!rvy_in_revision_workflow($revision)) {
				$revision = false;
			}
		}
	}

	if ( ! $revision ) {
		// Support published post/page in revision argument
		if ( ! $rvy_post = get_post( $revision_id) )
			break;

		if ( ! in_array( $rvy_post->post_type, array_keys($revisionary->enabled_post_types) ) ) {
			$rvy_post = '';  // todo: is this necessary?
			break;
		}

		// revision_id is for a published post.  List all its revisions - either for type specified or default to past
		if ( ! $revision_status )
			$revision_status = 'inherit';

		if (!current_user_can('edit_post', $rvy_post->ID) && !rvy_is_post_author($rvy_post))
			wp_die();

	} else {
		if ( !$rvy_post = get_post( rvy_post_id($revision->ID) ) )
			break;

		// actual status of compared objects overrides any revision_Status arg passed in
		$revision_status = $revision->post_mime_type;

		if (!current_user_can( 'edit_post', $rvy_post->ID ) && !rvy_is_post_author($revision)) {
			wp_die();
		}
	}

	if ( $type_obj = get_post_type_object( $rvy_post->post_type ) ) {
		$edit_cap = 'edit_post';
		$edit_others_cap = $type_obj->cap->edit_others_posts;
		$delete_cap = $type_obj->cap->delete_post;
	}

	$published_title = "<a href='post.php?action=edit&post=$rvy_post->ID'>$rvy_post->post_title</a>";
	?>
	<h1><?php printf(__('Revisions of %s', 'revisionary'), $published_title);?></h1>
	<?php

	// Sets up the diff radio buttons
	$right = $rvy_post->ID;

	if ( $revision ) {
		$left = $revision_id;
		$post_title = "<a href='post.php?action=edit&post=$rvy_post->ID'>$rvy_post->post_title</a>";
	} else {
		$revision = $rvy_post;	
	}

	// pending revisions are newer than current revision
	if ( 'pending-revision' == $revision_status ) {
		$buffer_left = $left;
		$left  = $right;
		$right = $buffer_left;
	}

	break;
endswitch;


if ( empty($revision) && empty($right_revision) && empty($left_revision) ) {
	echo( '<div><br />' );
	_e( 'The requested revision does not exist.', 'revisionary');
	echo( '</div>' );
	return;
}

if ( ! $revision_status )
	$revision_status = 'inherit'; 	// default to showing past revisions
?>

<div class="wrap">

<?php
if (!$can_fully_edit_post = current_user_can( $edit_cap, $rvy_post->ID)) {
	// post-assigned Revisor role is sufficient to edit others' revisions, but post-assigned Contributor role is not
	$_can_edit_others = (!rvy_get_option('revisor_lock_others_revisions') || rvy_is_full_editor($rvy_post)) && current_user_can( $edit_others_cap, $rvy_post->ID);
}

if ( 'diff' != $action ) {
	$can_edit = ( ( 'revision' == $revision->post_type ) || rvy_in_revision_workflow($revision) ) && (
		$can_fully_edit_post || 
		( (rvy_is_post_author($revision) || $_can_edit_others) && (in_array($revision->post_mime_type, ['draft-revision', 'pending-revision']) ))
		);
}
?>

<?php
if ( $is_administrator = is_content_administrator_rvy() ) {
	global $wpdb;

	$base_status_csv = implode("','", array_merge(rvy_revision_base_statuses(), ['inherit']));
	$revision_status_csv = implode("','", array_map('sanitize_key', rvy_revision_statuses()));

	$results = $wpdb->get_results( 
		$wpdb->prepare(
			"SELECT post_mime_type, COUNT( * ) AS num_posts FROM {$wpdb->posts}"
			. " WHERE post_status IN ('$base_status_csv')"
			. " AND ((post_type = 'revision' AND post_status = 'inherit' AND post_parent = %d) OR (post_type != 'revision' AND post_mime_type IN ('$revision_status_csv') AND comment_count = %d))"
			. " GROUP BY post_mime_type",
			$rvy_post->ID,
			$rvy_post->ID
		)
	);
	
	$num_revisions = array( '' => 0, 'pending-revision' => 0, 'future-revision' => 0 );
	foreach( $results as $row ) {
		$num_revisions[$row->post_mime_type] = $row->num_posts;
	}

	$num_revisions['inherit'] = $num_revisions[''];
	unset($num_revisions['']);

	$num_revisions = (object) $num_revisions;
}

$status_links = '<ul class="subsubsub">';
foreach ( array_keys($revision_status_captions) as $_revision_status ) {
	$post_id = ( ! empty($rvy_post->ID) ) ? $rvy_post->ID : $revision_id;
	
	if ('inherit' == $_revision_status) {
		$link = "admin.php?page=rvy-revisions&amp;revision={$post_id}&amp;revision_status=$_revision_status";
		$target = '';
	} else {
		$link = rvy_admin_url("admin.php?page=revisionary-q&published_post={$rvy_post->ID}&post_status={$_revision_status}");
		$target = "target='_blank'";
	}

	$class = ( $revision_status == $_revision_status ) ? ' class="rvy_current_status rvy_select_status"' : 'class="rvy_select_status"';

	switch( $_revision_status ) {
		case 'inherit':
			$status_caption = __( 'Past Revisions', 'revisionary' );
			break;
		case 'draft-revision':
		case 'pending-revision':
		case 'future-revision':
			$status_caption = pp_revisions_status_label($_revision_status, 'plural');
			break;
	}
	
	if ( $is_administrator ) {
		if ($num_revisions->$_revision_status) {
			$label = __( '%1$s <span class="count"> (%2$s)</span>', 'revisionary' );
			$status_links .= "<li $class><a href='$link' $target>" . sprintf( _nx( $label, $label, $num_revisions->$_revision_status, $label ), $status_caption, number_format_i18n( $num_revisions->$_revision_status ) ) . '</a></li>';
		}
	} else
		$status_links .= "<li $class><a href='$link' $target>" . $status_caption . '</a></li>';
}
$status_links .= '</ul>';

echo $status_links;

$args = array( 'format' => 'form-table', 'parent' => true, 'right' => $right, 'left' => $left, 'current_id' => isset($revision_id) ? $revision_id : 0 );

$count = rvy_list_post_revisions( $rvy_post, $revision_status, $args );
if ( $count < 2 ) {
	echo( '<br class="clear" /><p>' );
	printf( __( 'no %s revisions available.', 'revisionary'), strtolower($revision_status_captions[$revision_status]) );
	echo( '</p>' );
}

?>

</div>
