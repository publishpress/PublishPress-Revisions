<?php
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Revisionary_Archive_List_Table extends WP_List_Table {

	public function __construct( $args ) {
		$args = wp_parse_args(
			$args,
			[
				'plural' => 'posts',
				'screen' => 'revisionary-archive',
			]
		);

		parent::__construct( $args );
    }

    public function prepare_items() {
		global $wpdb, $per_page;

		$filter_types	= ['post', 'page', 'product']; // Post types where revisions belongs to
		$post_type		= 'revision'; // We're looking for revisions
		$per_page 		= $this->get_items_per_page( 'edit_page_per_page' );
		$paged 			= isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] ) - 1 ) : 0;
		$offset 		= $paged * $per_page;

		$base_query		= "SELECT
							r.ID AS revision_ID,
							r.post_title AS revision_post_title,
							r.post_date AS revision_post_date,
							r.post_author AS revision_author,
							IF( r3.comment_count > 0,
								(
									SELECT p2.post_author
									FROM $wpdb->posts p2
									WHERE p2.ID = (
										SELECT r3.comment_count
										FROM $wpdb->posts r3
										WHERE r.post_parent = r3.ID
										ORDER BY r3.ID DESC
										LIMIT 0,1
									)
								),
								(
									SELECT p2.post_author
									FROM $wpdb->posts p2
									WHERE p2.ID = r.post_parent
									ORDER BY p2.ID DESC
									LIMIT 0,1
								)
							) AS origin_post_author,
							IF( r3.comment_count > 0,
								(
									SELECT p2.post_date
									FROM $wpdb->posts p2
									WHERE p2.ID = (
										SELECT r3.comment_count
										FROM $wpdb->posts r3
										WHERE r.post_parent = r3.ID
										ORDER BY r3.ID DESC
										LIMIT 0,1
									)
								),
								(
									SELECT p2.post_date
									FROM $wpdb->posts p2
									WHERE p2.ID = r.post_parent
									ORDER BY p2.ID DESC
									LIMIT 0,1
								)
							) AS origin_post_date,
							IF( r3.comment_count > 0,
								(
									SELECT p2.post_type
									FROM $wpdb->posts p2
									WHERE p2.ID = (
										SELECT r3.comment_count
										FROM $wpdb->posts r3
										WHERE r.post_parent = r3.ID
										ORDER BY r3.ID DESC
										LIMIT 0,1
									)
								),
								(
									SELECT p2.post_type
									FROM $wpdb->posts p2
									WHERE p2.ID = r.post_parent
									ORDER BY p2.ID DESC
									LIMIT 0,1
								)
							) AS origin_post_type
						FROM $wpdb->posts r
						LEFT JOIN $wpdb->posts r3 ON r.post_parent = r3.ID
						WHERE r.post_type = '$post_type'
						ORDER BY r.post_modified DESC";

		$posts_query	= $base_query . " LIMIT %d,%d";
		$results 		= $wpdb->get_results( $wpdb->prepare( $posts_query, $offset, $per_page ) );
		$count_query	= "SELECT COUNT(*) as total_items FROM ($base_query) as subquery";
        $total_items 	= $wpdb->get_var( $wpdb->prepare( $count_query ) );
        $this->items 	= $results;

		$this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ) );

		/*echo '<pre>';
		var_dump($this->items);
		echo '</pre>';*/
    }

    public function get_columns() {
        return array(
            'cb'					=> '<input type="checkbox" />',
			'revision_post_title' 	=> __( 'Revision', 'revisionary' ),
			'origin_post_type' 		=> __( 'Post type', 'revisionary' ),
			'revision_post_date' 	=> __( 'Revision date', 'revisionary' ),
			'origin_post_date'		=> __( 'Published date', 'revisionary' ),
			'revision_author'		=> __( 'Revised by', 'revisionary' ),
			'origin_post_author'			=> __( 'Author', 'revisionary' ),
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'revision_post_title':
				return sprintf(
					'<a class="row-title rvy-open-popup" href="#" data-label="%s" data-link="%s">%s</a>' . ' (' . $item->revision_ID . ')',
					esc_attr( $item->$column_name ),
					get_edit_post_link( $item->ID ) . '&width=900&height=600&rvy-popup=true&TB_iframe=1',
					$item->$column_name
				);
				break;

			case 'revision_post_date':
			case 'revision_post_type':
			case 'origin_post_date':
			case 'origin_post_type':
                return $item->$column_name;
				break;

			case 'origin_post_author':
				return get_the_author_meta(
					'display_name',
					isset( $item->origin_post_author ) ? $item->origin_post_author : $item->revision_author
				);
				break;

			case 'revision_author':
                return get_the_author_meta( 'display_name', $item->$column_name );
				break;

			default:
                return '';
        }
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="post[]" value="%s" />', $item->ID
        );
    }

	/**
	 * Override WP_Posts_List_Table::no_items
	 *
	 */
	public function no_items() {
		_e( 'No items found.' );
	}
}
