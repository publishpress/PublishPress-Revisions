<?php
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Revisionary_Archive_List_Table extends WP_List_Table {
	private $post_types = [];

	public function __construct( $args ) {
		global $revisionary;

		$args = wp_parse_args(
			$args,
			[
				'plural' => 'posts',
				'screen' => 'revisionary-archive',
			]
		);

		parent::__construct( $args );

		// We only support these post types if enabled in Revisions settings
		$this->post_types = array_intersect( ['post', 'page'], array_keys( $revisionary->enabled_post_types ) );

		$omit_types 		= ['forum', 'topic', 'reply'];
		$this->post_types 	= array_diff( $this->post_types, $omit_types );

		/*echo '<pre>';
		var_dump($_REQUEST);
		var_dump($_SERVER['REQUEST_URI']);
		echo '</pre>';*/
    }

    public function prepare_items() {
		global $wpdb, $per_page;

		$per_page 		= $this->get_items_per_page( 'edit_page_per_page' );
		$paged 			= isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] ) - 1 ) : 0;
		$offset 		= $paged * $per_page;

		// Filters
		$having_filters = isset( $_REQUEST['origin_post_type'] )
							? "HAVING origin_post_type = '" . sanitize_key( $_REQUEST['origin_post_type'] ) . "'"
							: "HAVING origin_post_type IN ('" . implode( "','", $this->post_types ) . "')";

		$having_filters.= isset( $_REQUEST['origin_post_author'] )
								? " AND origin_post_author = " . (int) $_REQUEST['origin_post_author']
								: "";

		$having_filters.= isset( $_REQUEST['revision_post_author'] )
								? " AND revision_post_author = " . (int) $_REQUEST['revision_post_author']
								: "";

		$order_by		= isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], ['origin_post_date', 'revision_post_date'] )
							? sanitize_key( $_REQUEST['orderby'] )
							: 'revision_post_date';

		$order			= isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], ['asc', 'desc'] )
							? sanitize_key( $_REQUEST['order'] )
							: 'desc';

		// @TODO - Optimize query
		$base_query		= "SELECT
							r.ID AS ID,
							r.post_title AS revision_post_title,
							r.post_date AS revision_post_date,
							r.post_author AS revision_post_author,
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
						WHERE r.post_type = 'revision'
						{$having_filters}
						ORDER BY {$order_by} {$order}";

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
			'revision_post_author'		=> __( 'Revised by', 'revisionary' ),
			'origin_post_author'			=> __( 'Author', 'revisionary' ),
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'revision_post_title':
				return sprintf(
					'<a class="row-title rvy-open-popup" href="#" data-label="%s" data-link="%s">%s</a>' . ' (' . $item->ID . ')',
					esc_attr( $item->$column_name ),
					get_edit_post_link( $item->ID ) . '&width=900&height=600&rvy-popup=true&TB_iframe=1',
					$item->$column_name
				);
				break;

			case 'origin_post_type':
				return $this->build_filter_url(
					$item->$column_name,
					[
						'origin_post_type' => sanitize_key( $item->$column_name )
					]
				);
				break;

			case 'revision_post_date':
			case 'origin_post_date':
                return $item->$column_name;
				break;

			case 'origin_post_author':
				return $this->build_filter_url(
					get_the_author_meta( 'display_name', $item->$column_name ),
					[
						'origin_post_author' => (int) $item->$column_name
					]
				);
				break;

			case 'revision_post_author':
				return $this->build_filter_url(
					get_the_author_meta( 'display_name', $item->$column_name ),
					[
						'revision_post_author' => (int) $item->$column_name
					]
				);
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

	protected function extra_tablenav( $which ) {
		?>
		<div class="alignleft actions">
		<?php
		if ( 'top' === $which ) {
			ob_start();

			$this->post_types_dropdown();

			$output = ob_get_clean();

			if ( ! empty( $output ) ) {
				echo $output;
				submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
			}
		}
		?>
		</div>
		<?php
	}

	protected function post_types_dropdown() {
		$current_option = isset( $_REQUEST['origin_post_type'] ) && ! empty( $_REQUEST['origin_post_type'] )
							? sanitize_key( $_REQUEST['origin_post_type'] )
							: '';
		?>
		<select name="origin_post_type" class="postform">
			<option <?php echo $current_option === '' ? 'selected' : '' ?>
				value="">
				<?php esc_html_e( 'All post types', 'revisionary' ) ?>
			</option>
			<?php foreach( $this->post_types as $type ) : ?>
				<option <?php echo $current_option === $type ? 'selected' : '' ?>
					value="<?php echo $type ?>">
					<?php echo $type ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	protected function get_sortable_columns() {
		return [
			'origin_post_date' 		=> 'origin_post_date',
			'revision_post_date' 	=> 'revision_post_date',
			'origin_post_type'		=> 'origin_post_type'
		];
	}

	/**
	 * Generate all the hidden input fields to use as filters in database
	 *
	 * @return html
	 */
	public function hidden_input() {
		?>
		<input type="hidden" name="page" value="revisionary-archive" />
		<?php
		$this->single_hidden_input( 'origin_post_type' );
		$this->single_hidden_input( 'origin_post_author', true );
		$this->single_hidden_input( 'revision_post_author', true );
	}

	/**
	 * Generate hidden input fields to use as filters in database
	 *
	 * @param string $field	The field name from database query
	 * @param bool $integer	The field is a number?
	 *
	 * @return html
	 */
	public function single_hidden_input( $field, $integer = false ) {
		if( isset( $_REQUEST[$field] ) && ! empty( $_REQUEST[$field] ) ) :
			?>
			<input type="hidden"
				name="<?php echo $field ?>"
				value="<?php echo $integer ? (int) $_REQUEST[$field] : sanitize_key( $_REQUEST[$field] ) ?>" />
			<?php
		endif;
	}

	/**
	 * Generate hidden input fields to use as filters in database
	 *
	 * @param string $label	The label to display
	 * @param array $args	URL parameters
	 *
	 * @return html
	 */
	public function build_filter_url( $label, $args ) {
		$args = array_merge(
			[
				'page' => 'revisionary-archive'
			],
			$args
		);

		// Include origin_post_type filter if exists
		if( isset( $_REQUEST['origin_post_type'] ) ) {
			$args = array_merge(
				[
					'origin_post_type' => sanitize_key( $_REQUEST['origin_post_type'] )
				],
				$args
			);
		}

		return '<a href="' . add_query_arg( $args, admin_url( 'admin.php' ) ) . '">' . sanitize_text_field( $label ) . '</a>';
	}

	/**
	 * Override WP_Posts_List_Table::no_items
	 *
	 */
	public function no_items() {
		_e( 'No items found.' );
	}
}
