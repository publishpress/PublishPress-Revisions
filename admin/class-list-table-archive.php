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

		$this->post_types = array_keys( $revisionary->enabled_post_types );
    }

	/**
	 * Override WP_List_Table::prepare_items()
	 */
    public function prepare_items() {
		global $wpdb, $per_page, $current_user;

		$per_page 		= $this->get_items_per_page( 'edit_page_per_page' );
		$paged 			= isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] ) - 1 ) : 0;
		$offset 		= $paged * $per_page;
		$orderby		= $this->check_param( 'orderby' ) && in_array( $_REQUEST['orderby'], ['origin_post_date', 'revision_post_date'] )
			? sanitize_key( $_REQUEST['orderby'] )
			: 'revision_post_date';

		$order			= $this->check_param( 'order' ) && in_array( $_REQUEST['order'], ['asc', 'desc'] )
			? sanitize_key( strtoupper( $_REQUEST['order'] ) )
			: 'DESC';

		// Filters
		$base_query = $this->do_query(
			[],
			$orderby,
			$order
		);

		$results = $wpdb->get_results(
			$wpdb->prepare(
				$base_query . ' LIMIT %d,%d',
				$offset,
				$per_page
			)
		);

		$total_items = $wpdb->get_var(
			$wpdb->prepare(
				$this->count_query( 'total_items', $base_query )
			)
		);

		$this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ] );

		$this->items = $results;

		// 'All Revisions' link with count
		$this->all_revisions_count = $wpdb->get_var(
			$wpdb->prepare(
				$this->count_query( 'all_items', $base_query )
			)
		);

		// 'My Revisions' link with count
		$this->my_revisions_count = $wpdb->get_var(
			$wpdb->prepare(
				$this->count_query(
					'my_items',
					$this->do_query(
						[
							['revision_post_author' => $current_user->ID]
						]
					)
				)
			)
		);
    }

	/**
	 * Generate a heading depeding the filters in use
	 *
	 * @return string
	 */
	public function filters_in_heading() {
		$heading = '';

		$count = 0;

		// Post type
		if( $this->check_param( 'origin_post_type' )
			&& in_array( $_REQUEST['origin_post_type'], $this->post_types )
		) {
			$obj = get_post_type_object( sanitize_key( $_REQUEST['origin_post_type'] ) );
			$heading .= $this->heading_spacing( $count );
			$heading .= $obj->labels->singular_name;
			$count++;
		}

		// Revision post author
		if( $this->check_param( 'revision_post_author' ) ) {
			$heading .= $this->heading_spacing( $count );
			$heading .= sprintf(
				__( 'Revision Author: %s' ,'revisionary' ),
				get_the_author_meta( 'display_name', (int) $_REQUEST['revision_post_author'] )
			);
			$count++;
		}

		// Origin post author
		if( $this->check_param( 'origin_post_author' ) ) {
			$heading .= $this->heading_spacing( $count );
			$heading .= sprintf(
				__( 'Post Author: %s' ,'revisionary' ),
				get_the_author_meta( 'display_name', (int) $_REQUEST['origin_post_author'] )
			);
			$count++;
		}

		if( ! empty( $heading ) ) {
			$heading = ' (' . $heading . ')';
		}

		return $heading;
	}

	/**
	 * Generate a label next to heading for search results
	 *
	 * @return string
	 */
	public function search_in_heading() {
		$heading = '';

		if( $this->check_param( 's' ) && ! empty( trim( $_REQUEST['s'] ) ) ) {
			$heading .= sprintf(
				__( 'Search results for "%s"', 'revisionary' ),
				strtolower(
					sanitize_text_field(
						trim( $_REQUEST['s'] )
					)
				)
			);
		}

		return sprintf(
			'<span class="subtitle">%s</span>',
			$heading
		);
	}

	/**
	 * Generate hidden input fields to use as filters in database
	 *
	 * @param string $alias	A string to differentiate the query for debugging purposes
	 * @param string  $base	The do_query() query to count records from
	 *
	 * @return string
	 */
	private function count_query( $alias, $base ) {
		return "SELECT COUNT(*) as {$alias} FROM ($base) as {$alias}_subquery";
	}

	/**
	 * Build database query select to retrieve data to display later in table
	 *
	 * @param string $orderby	The database field to order by (can be an alias from the query)
	 * @param string $order 	Sort results in DESC or ASC
	 * @param array $having 	Array with database fields from query select to override filters
	 *
	 * @return string
	 */
	private function do_query( $args = [], $orderby = 'revision_post_date', $order = 'DESC' ) {
		global $wpdb;

		// @TODO - Optimize query
		$query = "SELECT
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
		WHERE r.post_type = 'revision'";

		// Only when Search input is valid
		if( $this->check_param( 's' ) && ! empty( trim( $_REQUEST['s'] ) ) ) {
			$query .= $wpdb->prepare(
				" AND LOWER(r.post_title) LIKE '%s'",
				'%' . $wpdb->esc_like( strtolower( sanitize_text_field( trim( $_REQUEST['s'] ) ) ) ) . '%'
			);
		}

		$count = 0;

		// Filter by origin_post_author from URL/form params
		if( $this->check_param( 'origin_post_author' ) ) {
			$query .= $wpdb->prepare(
				$this->having_and( $count ) .
				' origin_post_author LIKE %d',
				$wpdb->esc_like( $_REQUEST['origin_post_author'] )
			);
			$count++;
		}

		// Filter by revision_post_author from URL/form params
		if( $this->check_param( 'revision_post_author' )
			&& ! $this->key_exists_in_args( $args, 'revision_post_author' )
		) {
			$query .= $wpdb->prepare(
				$this->having_and( $count ) .
				' revision_post_author LIKE %d',
				$wpdb->esc_like( $_REQUEST['revision_post_author'] )
			);
			$count++;
		}

		// Filter by origin_post_type from URL/form params
		if( $this->check_param( 'origin_post_type' ) ) {
			$query .= $wpdb->prepare(
				$this->having_and( $count ) .
				' origin_post_type LIKE %s AND origin_post_type IN ("' . implode('","', $this->post_types ) . '")',
				$wpdb->esc_like( $_REQUEST['origin_post_type'] )
			);
			$count++;
		} else {
			$query .= $wpdb->prepare(
				' ' . $this->having_and( $count ) .
				' origin_post_type IN ("' . implode('","', $this->post_types ) . '")'
			);
			$count++;
		}

		/* Filter by revision_post_author as filter to build a different query to output different data
		   e.g. A link to 'My Revisions' above the list table */
		if( count( $args ) && $this->key_exists_in_args( $args, 'revision_post_author' ) ) {
			$query .= $wpdb->prepare(
				$this->having_and( $count ) .
				' revision_post_author LIKE %d',
				$wpdb->esc_like( $this->key_exists_in_args( $args, 'revision_post_author' ) )
			);
			$count++;
		}

		// Set order by and order
		$query .= $wpdb->prepare(
			" ORDER BY {$orderby} " . strtoupper( $order )
		);

		return $query;
	}

	/**
	 * Check if a key exists inside a 2-level array
	 *
	 * @param string $find	Which key are we looking in an array
	 *
	 * @return string|bool
	 */
	private function key_exists_in_args( $array, $find ) {
		foreach ( $array as $item ) {
			if ( array_key_exists( $find, $item ) ) {
				$find_value = $item[$find];
				break;
			}
		}

		if ( isset( $find_value ) ) {
			return $find_value;
		}

		return false;
	}

	/**
	 * Check if a form filter is active
	 *
	 * @param string $field	e.g. 'origin_post_author'
	 *
	 * @return bool
	 */
	private function check_param( $field ) {
		if( isset( $_REQUEST[$field] ) && ! empty( $_REQUEST[$field] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * HAVING clause helper to build dynamic query
	 *
	 * @param int $count Number that later decide the return
	 *
	 * @return string
	 */
	private function having_and( $count ) {
		return $count > 0 ? ' AND' : ' HAVING';
	}

	/**
	 * Generate dynamic spacing
	 *
	 * @param int $count Number that later decide the return
	 *
	 * @return string
	 */
	private function heading_spacing( $count ) {
		return $count > 0 ? ' - ' : '';
	}

	/**
	 * Override WP_List_Table::get_columns()
	 */
    public function get_columns() {
        return array(
            'cb'					=> '<input type="checkbox" />',
			'revision_post_title' 	=> __( 'Revision', 'revisionary' ),
			'origin_post_type' 		=> __( 'Post Type', 'revisionary' ),
			'revision_post_date' 	=> __( 'Revision Date', 'revisionary' ),
			'revision_post_author'	=> __( 'Revised By', 'revisionary' ),
			'origin_post_date'		=> __( 'Published Date', 'revisionary' ),
			'origin_post_author'	=> __( 'Author', 'revisionary' ),
        );
    }

	/**
	 * Make post datetime friendly
	 *
	 * @return html
	 */
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
			$result = date( 'M j, Y h:i a', $timestamp );
		}

		$saved_time = date( 'Y/m/d H:i:s' );

		return '<abbr title="' . esc_attr( $saved_time ) . '">' . $result . '</abbr>';
	}

	/**
	 * Override WP_List_Table::column_default()
	 */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'revision_post_title':
				printf(
					'<strong><a class="row-title rvy-open-popup" href="#" data-label="%s" data-link="%s">%s</a>' . ' (' . $item->ID . ')</strong>',
					esc_attr( $item->$column_name ),
					get_edit_post_link( $item->ID ) . '&width=900&height=600&rvy-popup=true&TB_iframe=1',
					$item->$column_name
				);
				echo $this->handle_revision_row_actions( $item );
				break;

			case 'origin_post_type':
				echo $this->build_filter_url(
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
				echo $this->build_filter_url(
					get_the_author_meta( 'display_name', $item->$column_name ),
					[
						'origin_post_author' => (int) $item->$column_name
					]
				);
				break;

			case 'revision_post_author':
				echo $this->build_filter_url(
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

	/**
	 * Override WP_List_Table::column_cb()
	 */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="post[]" value="%s" />', $item->ID
        );
    }

	/**
	 * Override WP_List_Table::extra_tablenav()
	 */
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

	/**
	 * Create dropdown to list and switch post types filter
	 *
	 * @return html
	 */
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
			<?php foreach( $this->post_types as $type ) :
				$type_obj = get_post_type_object( $type );
				?>
				<option <?php echo $current_option === $type ? 'selected' : '' ?>
					value="<?php echo $type ?>">
					<?php echo $type_obj->labels->singular_name; ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Override WP_List_Table::get_sortable_columns()
	 */
	protected function get_sortable_columns() {
		return [
			'origin_post_date' 		=> 'origin_post_date',
			'revision_post_date' 	=> 'revision_post_date',
		];
	}

	/**
	 * Override WP_List_Table::get_views()
	 */
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
	 * Override WP_List_Table::extra_tablenav()
	 */
	protected function handle_revision_row_actions( $item ) {
		$actions 			= [];
		$title				= _draft_or_post_title();
		$can_read_post		= current_user_can( 'read_post', $item->ID );
		$can_edit_post		= current_user_can( 'edit_post', $item->ID );
		$can_delete_post	= current_user_can( 'delete_post', $item->ID );
		$post_type_object 	= get_post_type_object( $item->origin_post_type );

		if ( $can_delete_post ) {
			if ( $delete_link = get_delete_post_link( $item->ID, '', true ) ) {
				$actions['delete'] = sprintf(
					'<a href="%1$s" class="submitdelete" title="%2$s" aria-label="%2$s">%3$s</a>',
					$delete_link,
					esc_attr( sprintf( esc_html__( 'Delete Revision', 'revisionary' ), $title ) ),
					esc_html__( 'Delete' )
				);
			}
		}

		if ( $can_read_post || $can_edit_post ) {
			$actions['diff'] = sprintf(
				'<a href="%1$s" class="" title="%2$s" aria-label="%2$s" target="_revision_diff">%3$s</a>',
				admin_url( "revision.php?revision=$item->ID" ),
				esc_attr( sprintf( esc_html__('Compare Changes', 'revisionary'), $title ) ),
				_x( 'Compare', 'revisions', 'revisionary' )
			);
		}

		if ( is_post_type_viewable( $post_type_object ) ) {
			if ( $can_read_post && $post_type_object && ! empty( $post_type_object->public ) ) {
				if ( rvy_get_option( 'revision_preview_links' ) || current_user_can('administrator') || is_super_admin() ) {
					do_action('pp_revisions_get_post_link', $item->ID);

					$preview_link = rvy_preview_url( $item );

					$preview_link = remove_query_arg( 'preview_id', $preview_link );
					$actions['view'] = sprintf(
						'<a href="%1$s" rel="bookmark" title="%2$s" aria-label="%2$s">%3$s</a>',
						esc_url( $preview_link ),
						esc_attr( esc_html__( 'Preview Revision', 'revisionary' ) ),
						esc_html__( 'Preview' )
					);

					do_action('pp_revisions_post_link_done', $item->ID);
				}
			}
		}

		return $this->row_actions( $actions );
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
	 * @param string $label		The label to display
	 * @param array $args		URL filter parameters for the generated link
	 * @param integer $count	Number of records to display
	 *
	 * @return html
	 */
	public function build_filter_url( $label, $args, $count = null ) {
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

		$count = $count ==! null ? ' <span class="count">(' . $count . ')</span>' : '';

		return '<a href="' . add_query_arg( $args, admin_url( 'admin.php' ) ) . '">' . sanitize_text_field( $label ) . $count . '</a>';
	}

	/**
	 * Override WP_List_Table::no_items()
	 */
	public function no_items() {
		_e( 'No revisions found.', 'revisionary' );
	}
}
