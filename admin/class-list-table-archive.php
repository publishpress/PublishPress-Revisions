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
		global $wpdb;

		$post_type 	= 'revision';
		$per_page 	= 10;
		$paged 		= isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] ) - 1 ) : 0;
		$offset 	= $paged * $per_page;
		$query 		= "SELECT r.ID, r.post_title, r.post_date, r.post_parent, r.post_author as revision_author,
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
				    ) AS original_author
				    FROM $wpdb->posts r
				    WHERE r.post_type = %s
				    ORDER BY r.post_modified DESC
				    LIMIT %d,%d";
		//$query = "SELECT ID, post_title, post_date, post_author, (SELECT post_author FROM $wpdb->posts WHERE ID = p.post_parent) as author FROM $wpdb->posts p WHERE post_type = %s ORDER BY post_modified DESC LIMIT %d,%d";
		$results 	= $wpdb->get_results( $wpdb->prepare( $query, $post_type, $offset, $per_page ) );
        $total_items 	= $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", $post_type ) );
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
            'cb' 				=> '<input type="checkbox" />',
			'post_title' 		=> __( 'Revision', 'revisionary' ),
			//'post_date' 		=> __( 'Published date', 'revisionary' ),
			'post_date' 		=> __( 'Revision date', 'revisionary' ),
			'revision_author'		=> __( 'Revised by', 'revisionary' ),
			'original_author'	=> __( 'Author', 'revisionary' ),
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'post_title':
				return sprintf(
					'<a class="row-title rvy-open-popup" href="#" data-label="%s" data-link="%s">%s</a>' . ' (' . $item->ID . ')',
					esc_attr( $item->$column_name ),
					get_edit_post_link( $item->ID ) . '&width=900&height=600&rvy-popup=true&TB_iframe=1', //admin_url( 'admin.php?page=revisionary-archive-single&rvy-popup=true&width=900&height=700&revision=' . $item->ID ),
					$item->$column_name
				);
				break;

			case 'post_date':
                return $item->$column_name;
				break;

			case 'original_author':
				return get_the_author_meta(
					'display_name',
					isset( $item->original_author ) ? $item->original_author : $item->revision_author
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
