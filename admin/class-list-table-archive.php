<?php
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Revisionary_Archive_List_Table extends WP_List_Table {
	private $post_types = [];
	private $all_revisions_count = null;
	private $my_revisions_count = null;

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

		/*/echo count($_REQUEST);
		echo '<pre>';
		var_dump($_REQUEST);
		//var_dump($_SERVER['REQUEST_URI']);
		echo '</pre>';*/
    }

    public function prepare_items() {
		global $wpdb, $per_page, $current_user;

		$per_page 		= $this->get_items_per_page( 'edit_page_per_page' );
		$paged 			= isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] ) - 1 ) : 0;
		$offset 		= $paged * $per_page;

		// Filters
		$having			= $this->having_filter( [
							'origin_post_author' 	=> isset( $_REQUEST['origin_post_author'] )
														? (int) $_REQUEST['origin_post_author']
														: null,
							'revision_post_author' 	=> isset( $_REQUEST['revision_post_author'] )
														? (int) $_REQUEST['revision_post_author']
														: null
						] );
		$order_by		= $this->orderby_filter();
		$order			= $this->order_filter();
		$base_query		= $this->base_query(
							$having,
							$order_by,
							$order,
							true
						);
		$results 		= $wpdb->get_results( $wpdb->prepare( $base_query . " LIMIT {$offset},{$per_page}" ) );
		$count_query	= $this->count_query( $base_query );
        $total_items 	= $wpdb->get_var( $wpdb->prepare( $count_query ) );
        $this->items 	= $results;

		$this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ] );

		// Get revisions count to use in view links
		$all_revisions_base_query	= $this->base_query( $this->having_filter() );
		$this->all_revisions_count 	= $wpdb->get_var( $wpdb->prepare(
			$this->count_query( $all_revisions_base_query )
		) );

		$my_revisions_base_query	= $this->base_query( $this->having_filter( ['revision_post_author' => $current_user->ID] ) );
		$this->my_revisions_count 	= $wpdb->get_var( $wpdb->prepare(
			$this->count_query( $my_revisions_base_query )
		) );
    }

	private function count_query( $base ) {
		return "SELECT COUNT(*) as total_items FROM ($base) as subquery";
	}

	private function base_query( $having, $order_by = 'revision_post_date', $order = 'DESC', $search = false ) {
		global $wpdb;

		$where 	= "WHERE r.post_type = 'revision'";

		// Only when search is enabled
		$where .= $search && isset( $_REQUEST['s'] ) && ! empty( trim( $_REQUEST['s'] ) )
					? " AND LOWER(r.post_title) LIKE '%" . strtolower( sanitize_text_field( trim( $_REQUEST['s'] ) ) ) . "%'"
					: "";


		// @TODO - Optimize query
		return "SELECT
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
		{$where}
		{$having}
		ORDER BY {$order_by} {$order}";
	}

	private function orderby_filter() {
		return isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], ['origin_post_date', 'revision_post_date'] )
							? sanitize_key( $_REQUEST['orderby'] )
							: 'revision_post_date';
	}

	private function order_filter() {
		return isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], ['asc', 'desc'] )
							? sanitize_key( $_REQUEST['order'] )
							: 'desc';
	}

	private function having_filter( $columns = [] ) {
		$filter = isset( $_REQUEST['origin_post_type'] ) && ! empty( $_REQUEST['origin_post_type'] )
							? "HAVING origin_post_type = '" . sanitize_key( $_REQUEST['origin_post_type'] ) . "'"
							: "HAVING origin_post_type IN ('" . implode( "','", $this->post_types ) . "')";

		foreach( $columns as $key => $value ) {
			$filter .= $value ? " AND {$key} = " . $value : "";
		}

		return $filter;
	}

    public function get_columns() {
        return array(
            'cb'					=> '<input type="checkbox" />',
			'revision_post_title' 	=> __( 'Revision', 'revisionary' ),
			'origin_post_type' 		=> __( 'Post Type', 'revisionary' ),
			'revision_post_date' 	=> __( 'Revision Date', 'revisionary' ),
			'origin_post_date'		=> __( 'Published Date', 'revisionary' ),
			'revision_post_author'	=> __( 'Revised By', 'revisionary' ),
			'origin_post_author'	=> __( 'Author', 'revisionary' ),
        );
    }

	public function friendly_date( $time ) {
		$timezone = get_option( 'timezone_string' );
		if ( $timezone ) {
			date_default_timezone_set( $timezone );
		}

		$timestamp 		= strtotime( $time );
		$current_time 	= time();
		$time_diff		= $current_time - $timestamp;

		if ( $time_diff < 60 ) {
			$result = esc_html__( 'just now', 'revisionary' );
		} elseif ( $time_diff < 3600 ) {
			$result = sprintf(
				esc_html__( '%s minutes ago', 'revisionary' ),
				floor( $time_diff / 60 )
			);
		} elseif ( $time_diff < 86400 ) {
			$result = sprintf(
				esc_html__( '%s hours ago', 'revisionary' ),
				floor( $time_diff / 3600 )
			);
		} else {
			$result = date( 'M j, Y', $timestamp );
		}

		$saved_time = date( 'Y/m/d H:i:s' );

		return '<abbr title="' . esc_attr( $saved_time ) . '">' . $result . '</abbr>';
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
                return $this->friendly_date( $item->$column_name );
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

			if( count( $_REQUEST ) > 1 ) :
				?>
				<a href="<?php echo add_query_arg( ['page' => 'revisionary-archive'], admin_url( 'admin.php' ) ) ?>"
					class="button">
					<?php esc_html_e( 'Reset Filters', 'revisionary' ) ?>
				</a>
				<?php
			endif;
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
		];
	}

	protected function get_views() {
		global $current_user;
		?>
		<ul class="subsubsub">
			<li class="all current">
				<?php
				echo $this->build_filter_url(
					__( 'All', 'revisionary' ),
					[],
					$this->all_revisions_count
				);
				?>
			</li>
			<li class="mine">
				<?php
				echo $this->build_filter_url(
					__( 'My Revisions', 'revisionary' ),
					[
						'revision_post_author' => $current_user->ID
					],
					$this->my_revisions_count
				);
				?>
			</li>
		</ul>
		<?php
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
	public function build_filter_url( $label, $args, $count = false ) {
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

		$count = $count ? ' <span class="count">(' . $count . ')</span>' : '';

		return '<a href="' . add_query_arg( $args, admin_url( 'admin.php' ) ) . '">' . sanitize_text_field( $label ) . $count . '</a>';
	}

	/**
	 * Override WP_Posts_List_Table::no_items
	 *
	 */
	public function no_items() {
		_e( 'No revisions found.', 'revisionary' );
	}
}
