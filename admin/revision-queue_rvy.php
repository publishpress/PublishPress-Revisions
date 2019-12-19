<?php
/**
 * @global string       $post_type
 * @global WP_Post_Type $post_type_object
 */
global $post_type, $post_type_object, $wpdb;

if ( ! $post_types = rvy_get_manageable_types() ) {
	wp_die( __( 'You are not allowed to manage revisions.' ) );
}

if (!rvy_get_option('pending_revisions') && !rvy_get_option('scheduled_revisions')) {
	wp_die( __( 'Pending Revisions and Scheduled Revisions are both disabled. See Revisions > Settings.' ) );
}

set_current_screen( 'revisionary-q' );

require_once( dirname(__FILE__).'/class-list-table_rvy.php');
$wp_list_table = new Revisionary_List_Table(['screen' => 'revisionary-q', 'post_types' => $post_types]);
$pagenum = $wp_list_table->get_pagenum();

$parent_file = 'admin.php?page=revisionary-q';
$submenu_file = 'admin.php?page=revisionary-q';

$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer('bulk-posts');

	$sendback = remove_query_arg( array('trashed', 'untrashed', 'approved', 'published', 'deleted', 'locked', 'ids'), wp_get_referer() );
	if ( ! $sendback )
		$sendback = admin_url( $parent_file );
	$sendback = add_query_arg( 'paged', $pagenum, $sendback );

	if ( 'delete_all' == $doaction ) {
		// Prepare for deletion of all posts with a specified post status (i.e. Empty trash).
		$post_status = preg_replace('/[^a-z0-9_-]+/i', '', $_REQUEST['post_status']);
		// Verify the post status exists.
		if ( get_post_status_object( $post_status ) ) {
			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", $post_type, $post_status ) );
		}
		$doaction = 'delete';
	} elseif ( isset( $_REQUEST['media'] ) ) {
		$post_ids = $_REQUEST['media'];
	} elseif ( isset( $_REQUEST['ids'] ) ) {
		$post_ids = explode( ',', $_REQUEST['ids'] );
	} elseif ( !empty( $_REQUEST['post'] ) ) {
		$post_ids = array_map('intval', $_REQUEST['post']);
	}

	if ( !isset( $post_ids ) ) {
		exit;
	}

	switch ( $doaction ) {
		case 'approve': // If pending revisions has a requested publish date, schedule it, otherwise schedule for near future. Leave currently scheduled revisions alone. 
		case 'publish': // Schedule all selected revisions for near future publishing.
			$approved = 0;
			//$published = 0;

			$is_administrator = current_user_can('administrator');

			foreach ((array) $post_ids as $post_id) {
				//$publish_ids = [];
				$approve_ids = [];

				if (!$revision = get_post($post_id)) {
					continue;
				}
				
				if (!rvy_is_revision_status($revision->post_status)) {
					continue;
				}
				
				if ( !$is_administrator 
				&& !agp_user_can($type_obj->cap->edit_post, rvy_post_id($revision->ID), '', ['skip_revision_allowance' => true])
				) {
					if (count($post_ids) == 1) {
						wp_die( __('Sorry, you are not allowed to approve this revision.') );
					} else {
						continue;
					}
				}

				if (('publish' == $doaction) || ('future-revision' != $revision->post_status)) {
					//$publish_ids []= $post_id;
					$approve_ids []= $post_id;
				} /* else {
					// If the revision is already scheduled, leave it alone
					if ('future-revision' == $revision->post_status) {
						continue;
					}
					
					// If the pending revision has a requested publish date, schedule that
					if (strtotime($revision->post_date_gmt) > agp_time_gmt()) {
						$approve_ids []= $post_id;
					} else {
						$publish_ids []= $post_id;
					}
				}
				*/

				/*
				if ( !wp_delete_post($post_id) ) {
					wp_die( __('Error in deleting.') );
				}
				*/
			}

			require_once( dirname(__FILE__).'/revision-action_rvy.php');

			foreach ($approve_ids as $revision_id) {
				rvy_revision_approve($revision_id);

				//$wpdb->update( $wpdb->posts, array('post_status' => 'future-revision'), array('ID' => $revision_id));
				//clean_post_cache($revision_id);
				//$approved++;
			}

			if ($approve_ids) {
				$sendback = add_query_arg('approved', count($approve_ids), $sendback);
			}

			/*
			if ($published) {
				$sendback = add_query_arg('published', $published, $sendback);
			}
			*/

			/*
			if ($approved || $published) {
				rvy_update_next_publish_date();
			}
			*/

			break;

		case 'delete':
			$deleted = 0;
			foreach ( (array) $post_ids as $post_id ) {
				if ( ! $revision = get_post($post_id) )
					continue;
				
				if ( ! rvy_is_revision_status($revision->post_status) )
					continue;
				
				if ( ! current_user_can('administrator') && ! current_user_can( 'delete_post', rvy_post_id($revision->ID) ) ) {  // @todo: review Administrator cap check
					if (('pending-revision' != $revision->post_status) || !rvy_is_post_author($revision)) {	// allow submitters to delete their own still-pending revisions
						wp_die( __('Sorry, you are not allowed to delete this revision.') );
					}
				} 

				if ( !wp_delete_post($post_id) )
					wp_die( __('Error in deleting.') );

				$deleted++;
			}
			$sendback = add_query_arg('deleted', $deleted, $sendback);
			break;
		default:
			$sendback = apply_filters( 'handle_bulk_actions-' . get_current_screen()->id, $sendback, $doaction, $post_ids );
			break;
	}

	$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view'), $sendback );
}

$wp_list_table->prepare_items();

$bulk_counts = array(
	'deleted'   => isset( $_REQUEST['deleted'] )   ? absint( $_REQUEST['deleted'] )   : 0,
	'updated' => 0,
	'locked' => 0,
	'approved' => 0,
	'published' => 0,
	'deleted' => 0,
	'trashed' => 0,
	'untrashed' => 0,
);

$bulk_messages = [];
$bulk_messages['post'] = array(
	'approved'   => _n( '%s revision approved.', '%s revisions approved.', $bulk_counts['approved'], 'revisionary' ),
	'published'   => _n( '%s revision published.', 'Publishing triggered for %s revisions.', $bulk_counts['published'], 'revisionary' ),
	'deleted'   => _n( '%s revision permanently deleted.', '%s revisions permanently deleted.', $bulk_counts['deleted'] ),
);

$bulk_messages['page'] = $bulk_messages['post'];

/**
 * Filters the bulk action updated messages.
 *
 * By default, custom post types use the messages for the 'post' post type.
 *
 * @since 3.7.0
 *
 * @param array $bulk_messages Arrays of messages, each keyed by the corresponding post type. Messages are
 *                             keyed with 'updated', 'locked', 'deleted', 'trashed', and 'untrashed'.
 * @param array $bulk_counts   Array of item counts for each message, used to build internationalized strings.
 */
$bulk_messages = apply_filters( 'bulk_post_updated_messages', $bulk_messages, $bulk_counts );
$bulk_counts = array_filter( $bulk_counts );

require_once( ABSPATH . 'wp-admin/admin-header.php' );
?>
<div class="wrap pressshack-admin-wrapper revision-q">
<header>
<h1 class="wp-heading-inline"><?php

echo '<span class="dashicons dashicons-backup"></span>&nbsp;';

if ( ! empty( $_REQUEST['post_type'] ) ) {
	$type_obj = get_post_type_object( $_REQUEST['post_type'] );
}

if (!empty($_REQUEST['published_post'])) {
	if ($_post = get_post((int) $_REQUEST['published_post'])) {
		$published_title = $_post->post_title;
	}
}

$filters = [];

if (!empty($_REQUEST['author'])) {
	if ($_user = new WP_User($_REQUEST['author'])) {
		$filters['author'] = (!empty($_REQUEST['post_status']) || !empty($_REQUEST['post_status'])) 
		? sprintf(__('%s: ', 'revisionary'), $_user->display_name)
		: $_user->display_name;
	}
}

if (!empty($_REQUEST['post_status'])) {
	if ($status_obj = get_post_status_object($_REQUEST['post_status'])) {
		$filters['post_status'] = $status_obj->labels->plural;
	}
}

if (!empty($_REQUEST['post_type']) && empty($published_title)) {
	$filters['post_type'] = (!empty($_REQUEST['post_status'])) 
	? sprintf(__('of %s', 'revisionary'), $type_obj->labels->name) 
	: $type_obj->labels->name;
}

if (!empty($_REQUEST['post_author']) && empty($published_title)) {
	if ($_user = new WP_User($_REQUEST['post_author'])) {
		$filters['post_author'] = $filters 
		? sprintf(__('%sPost Author: %s', 'revisionary'), ' - ', $_user->display_name) 
		: sprintf(__('%sPost Author: %s', 'revisionary'), '', $_user->display_name);
	}
}

$filter_csv = ($filters) ? ' (' . implode(" ", $filters) . ')' : '';

if (!empty($published_title)) {
	printf( __('Revision Queue for "%s"%s', 'revisionary'), $published_title, $filter_csv );
} else
	printf( __('Revision Queue %s', 'revisionary' ), $filter_csv);
?></h1>

<?php
if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
	/* translators: %s: search keywords */
	printf( ' <span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', get_search_query() );
}
?>

</header>
<!--<hr class="wp-header-end">-->

<?php
// If we have a bulk message to issue:
$messages = array();
foreach ( $bulk_counts as $message => $count ) {
	if ( $message == 'trashed' && isset( $_REQUEST['ids'] ) ) {
		$ids = preg_replace( '/[^0-9,]/', '', $_REQUEST['ids'] );
		$messages[] = '<a href="' . esc_url( wp_nonce_url( "edit.php?post_type=$post_type&doaction=undo&action=untrash&ids=$ids", "bulk-posts" ) ) . '">' . __('Undo') . '</a>';
	}
}

if ( $messages )
	echo '<div id="message" class="updated notice is-dismissible"><p>' . join( ' ', $messages ) . '</p></div>';
unset( $messages );

$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'locked', 'skipped', 'updated', 'approved', 'published', 'deleted', 'trashed', 'untrashed' ), $_SERVER['REQUEST_URI'] );
?>

<?php $wp_list_table->views(); ?>

<form id="posts-filter" method="get">

<?php $wp_list_table->search_box( 'Search', 'post' ); ?>

<input type="hidden" name="page" class="post_status_page" value="revisionary-q" />
<input type="hidden" name="post_status" class="post_status_page" value="<?php echo !empty($_REQUEST['post_status']) ? esc_attr($_REQUEST['post_status']) : 'all'; ?>" />

<?php if ( ! empty( $_REQUEST['show_sticky'] ) ) { ?>
<input type="hidden" name="show_sticky" value="1" />
<?php } ?>

<?php $wp_list_table->display(); ?>

</form>

<div id="ajax-response"></div>
<br class="clear" />

<?php
global $revisionary;
$revisionary->admin->publishpressFooter();
?>

</div>

<?php
