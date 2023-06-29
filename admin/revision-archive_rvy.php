<?php
set_current_screen( 'revisionary-archive' );

// Modal popup to view changes from a specific revision
add_thickbox();
wp_add_inline_script(
	'thickbox',
	"jQuery(document).ready(function($) {
		$('.rvy-open-popup').unbind('click').click(function (e) {
			e.preventDefault();
			var label = '" . esc_html__( 'Revision for:', 'revisionary' ) . "' + ' ' + $(this).data('label');
			tb_show(label, $(this).attr('href'));
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
			<?php
			esc_html_e( 'Revision Archive', 'revisionary' );
			echo $wp_list_table->filters_in_heading();
			?>
		</h1>
		<?php echo $wp_list_table->search_in_heading(); ?>
	</header>
	<?php $wp_list_table->views(); ?>
	<form method="get">
		<?php
		$wp_list_table->search_box( 'Search Revisions', 'revision' );
		$wp_list_table->hidden_input();
		$wp_list_table->display();
		?>
    </form>

	<?php do_action( 'revisionary_admin_footer' ); ?>
</div>
