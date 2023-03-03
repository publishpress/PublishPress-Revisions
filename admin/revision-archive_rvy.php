<?php
if ( ! $post_types = rvy_get_manageable_types() ) {
	wp_die( esc_html__( 'You are not allowed to manage revisions.', 'revisionary' ) );
}

if ( ! rvy_get_option( 'pending_revisions' ) && ! rvy_get_option( 'scheduled_revisions' ) ) {
	wp_die( sprintf( esc_html__(
		'%s and %s are both disabled. See Revisions > Settings.', 'revisionary' ),
		esc_html(pp_revisions_status_label('pending-revision', 'plural')),
		esc_html(pp_revisions_status_label('future-revision', 'plural'))
	) );
}

set_current_screen( 'revisionary-archive' );

// Modal popup to view changes from a specific revision
add_thickbox();
wp_add_inline_script(
	'thickbox',
	"jQuery(document).ready(function($) {
		$('.rvy-open-popup').unbind('click').click(function (e) {
			e.preventDefault();
			var label = '" . esc_html__( 'Revision for:', 'revisionary' ) . "' + ' ' + $(this).data('label');
			tb_show(label, $(this).data('link'));
		});
	});"
);

require_once( dirname( __FILE__ ) . '/class-list-table-archive.php' );
$wp_list_table = new Revisionary_Archive_List_Table(['screen' => 'revisionary-archive']);
$wp_list_table->prepare_items();
?>
<div class="wrap pressshack-admin-wrapper revision-archive">
	<header>
		<h1 class="wp-heading-inline">
			<span class="dashicons dashicons-backup"></span>
			<?php esc_html_e( 'Revision Archive', 'revisionary' ) ?>
		</h1>
	</header>
	<form method="post">
        <?php $wp_list_table->display(); ?>
    </form>

	<?php do_action( 'revisionary_admin_footer' ); ?>
</div>
