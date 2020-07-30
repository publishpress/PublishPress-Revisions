<?php
require_once( ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php' );

class Revisionary_List_Table extends WP_Posts_List_Table {
	private $published_post_ids = [];
	private $published_post_count_ids = [];
	private $post_types = [];
	private $posts_clauses_filtered; 

	public function __construct($args = []) {
		global $wpdb, $revisionary;

		parent::__construct([
			'plural' => 'posts',
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		]);

		if ( isset( $args['post_types'] ) )
			$this->post_types = $args['post_types'];
		else
			$this->post_types = array_keys($revisionary->enabled_post_types);
		
		$omit_types = ['forum', 'topic', 'reply'];
		$this->post_types = array_diff( $this->post_types, $omit_types );
		
		add_filter('manage_revisionary-q_columns', [$this, 'rvy_pending_list_register_columns']);

		add_action('manage_posts_custom_column', [$this, 'rvy_pending_custom_col'], 10, 2);
		add_action('manage_pages_custom_column', [$this, 'rvy_pending_custom_col'], 10, 2);

		if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
			// Don't allow MA to change revision author display. Authors taxonomy storage is only for application to published post.
			global $multiple_authors_addon;
			remove_action('the_post', [$multiple_authors_addon, 'fix_post'], 10);
		}
	}

	function do_query( $q = false ) {
		if ( false === $q ) $q = $_GET;

		// === first, query published posts that have any Revisionary revisions ===

		$qp['cat'] = isset($q['cat']) ? (int) $q['cat'] : 0;

		if ( isset($q['post_type']) && in_array( $q['post_type'], $this->post_types ) )
			$qp['post_type'] = $q['post_type'];
		else
			$qp['post_type'] = $this->post_types;
		
		$omit_stati = ['hidden'];
		$qp['post_status'] = array_diff( get_post_stati( ['public' => true, 'private' => true], 'names', 'or' ), $omit_stati );

		if (!empty($q['published_post'])) {
			$qp['p'] = (int) $q['published_post'];
		}

		$qp['posts_per_page'] = -1;
		$qp['fields'] = 'ids';
		
		$qp['meta_key'] = '_rvy_has_revisions';

		if (!empty($q['post_author'])) {
			do_action('revisionary_queue_pre_query');
			$_pre_query = new WP_Query( $qp );
			$this->published_post_count_ids = $_pre_query->posts;
			do_action('revisionary_queue_pre_query_done');

			$qp['author'] = $q['post_author'];
		}

		do_action('revisionary_queue_pre_query');
		add_filter('posts_clauses', [$this, 'pre_query_filter'], 5, 2);
		add_filter('posts_clauses', [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);
		$pre_query = new WP_Query( $qp );
		remove_filter('posts_clauses', [$this, 'pre_query_filter'], 5, 2);
		remove_filter('posts_clauses', [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);
		do_action('revisionary_queue_pre_query_done');

		//echo($pre_query->request . '<br /><br />');

		$this->published_post_ids = $pre_query->posts;

		if (empty($this->published_post_count_ids)) {
			$this->published_post_count_ids = $this->published_post_ids;
		}

		// === now query the revisions ===
		$qr = $q; 

		unset($qr['post_author']);

		$qr['post_type'] = $qp['post_type'];

		if (isset($qr['m']) && strlen($qr['m']) == 6) {
			$qr['date_query'] = [
				'column' => ( ! empty($_REQUEST['post_status']) && 'future-revision' == $_REQUEST['post_status'] ) ? 'post_date' : 'post_modified',
				'year' => substr($qr['m'], 0, 4),
				'month' => substr($qr['m'], 4)
			];

			unset($qr['m']);
		}

		if ( isset( $q['orderby'] ) && !in_array($q['orderby'], ['post_status', 'post_type']) ) {
			$qr['orderby'] = $q['orderby'];
		} else {
			$qr['orderby'] = ( ! empty($_REQUEST['post_status']) && 'future-revision' == $_REQUEST['post_status'] ) ? 'date' : 'modified';
		}

		if ( isset( $q['order'] ) && !in_array($q['orderby'], ['post_status', 'post_type'] ) ) {
			$qr['order'] = $q['order'];
		} else { //if ( isset( $q['post_status'] ) && 'pending-revision' == $q['post_status'] ) {
			$qr['order'] = 'DESC';
		}

		$per_page = "edit_page_per_page";	// use Pages setting
		$qr['posts_per_page'] = (int) get_user_option( $per_page );
		
		if ( empty( $qr['posts_per_page'] ) || $qr['posts_per_page'] < 1 )
			$qr['posts_per_page'] = 20;
		
		if ( isset($q['post_status']) && rvy_is_revision_status( $q['post_status'] ) ) {
			$qr['post_status'] = [$q['post_status']];
		} else {
			$qr['post_status'] = ['pending-revision', 'future-revision'];
		}

		if (!rvy_get_option('pending_revisions')) {
			$qr['post_status'] = array_diff($qr['post_status'], ['pending-revision']);
		}

		if (!rvy_get_option('scheduled_revisions')) {
			$qr['post_status'] = array_diff($qr['post_status'], ['future-revision']);
		}

		global $wp_query;

		add_filter('presspermit_posts_clauses_intercept', [$this, 'flt_presspermit_posts_clauses_intercept'], 10, 4);
		add_filter('posts_clauses', [$this, 'revisions_filter'], 5, 2);
		add_filter('posts_clauses', [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);

		if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
			remove_action('pre_get_posts', ['MultipleAuthors\\Classes\\Query', 'action_pre_get_posts']);
			remove_filter('posts_where', ['MultipleAuthors\\Classes\\Query', 'filter_posts_where'], 10, 2);
			remove_filter('posts_join', ['MultipleAuthors\\Classes\\Query', 'filter_posts_join'], 10, 2);
			remove_filter('posts_groupby', ['MultipleAuthors\\Classes\\Query', 'filter_posts_groupby'], 10, 2);
		}

		if (!empty($_REQUEST['s'])) {
			$qr['s'] = $_REQUEST['s'];
		}

		$qr = apply_filters('revisionary_queue_vars', $qr);
		$wp_query->query($qr);
		do_action('revisionary_queue_done');

		//echo($wp_query->request);

		remove_filter('presspermit_posts_clauses_intercept', [$this, 'flt_presspermit_posts_clauses_intercept'], 10, 4);
		remove_filter('posts_clauses', [$this, 'revisions_filter'], 5, 2);
		remove_filter('posts_clauses', [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);

		return $qr['post_status'];
	}

	function flt_presspermit_posts_clauses_intercept( $intercept, $clauses, $_wp_query, $args) {
		return $clauses;
	}

	function pre_query_where_filter($where, $args = []) {
		global $wpdb, $current_user, $revisionary;
		
		if (!current_user_can('administrator') && empty($args['suppress_author_clause'])) {
			$p = (!empty($args['alias'])) ? $args['alias'] : $wpdb->posts;

			$can_edit_others_types = [];
			
			foreach(array_keys($revisionary->enabled_post_types) as $post_type) {
				if ($type_obj = get_post_type_object($post_type)) {
					if (agp_user_can($type_obj->cap->edit_others_posts, 0, '', ['skip_revision_allowance' => true])) {
						$can_edit_others_types[]= $post_type;
					}
				}
			}

			$can_edit_others_types = apply_filters('revisionary_queue_edit_others_types', $can_edit_others_types);

			$type_clause = ($can_edit_others_types) ? "OR $p.post_type IN ('" . implode("','", $can_edit_others_types) . "')" : '';

			$where .= $wpdb->prepare(" AND ($p.post_author = %d $type_clause)", $current_user->ID );
		}

		return $where;
	}

	function restore_revisions_filter($clauses, $_wp_query = false) {
		if (!empty($this->posts_clauses_filtered) && !defined('REVISIONARY_ENABLE_REVISION_QUEUE_FILTERING')) {
			$clauses = $this->posts_clauses_filtered;
		}

		return $clauses;
	}

	function pre_query_filter($clauses, $_wp_query = false) {
		$clauses['where'] = $this->pre_query_where_filter($clauses['where']);
		$this->posts_clauses_filtered = $clauses;

		return $clauses;
	}

	function revisions_where_filter($where, $args = []) {
		global $wpdb, $current_user, $revisionary;
		
		$p = (!empty($args['alias'])) ? $args['alias'] : $wpdb->posts;

		if (!empty($args['status_count'])) {
			$post_id_csv = "'" . implode("','", $this->published_post_count_ids) . "'";
		} else {
			$post_id_csv = "'" . implode("','", $this->published_post_ids) . "'";
		}

		$own_revision_clause = (empty($_REQUEST['post_author']) || !empty($args['status_count'])) && empty($_REQUEST['published_post'])
		? "OR ($p.post_status = 'pending-revision' AND $p.post_author = '$current_user->ID')" 
		: '';

		$where .= " AND ($p.comment_count IN ($post_id_csv) $own_revision_clause)";

		if (rvy_get_option('revisor_hide_others_revisions') && !current_user_can('administrator') 
			&& !current_user_can('list_others_revisions') && empty($args['suppress_author_clause']) 
		) {
			$can_publish_types = [];
			foreach(array_keys($revisionary->enabled_post_types) as $post_type) {
				$type_obj = get_post_type_object($post_type);

				if (
					isset($type_obj->cap->edit_published_posts)
					&& agp_user_can($type_obj->cap->edit_published_posts, 0, '', ['skip_revision_allowance' => true])
					&& !empty($current_user->allcaps[$type_obj->cap->edit_others_posts])
					&& agp_user_can($type_obj->cap->publish_posts, 0, '', ['skip_revision_allowance' => true])
					&& (!empty($revisionary->enabled_post_types[$post_type]) || !$revisionary->config_loaded)
				) {
					$can_publish_types[]= $post_type;
				}
			}

			$can_publish_types = array_intersect($can_publish_types, apply_filters('revisionary_manageable_types', $can_publish_types));

			if ($can_publish_types) {
				$type_clause = "OR $p.post_type IN ('" . implode("','", $can_publish_types) . "')";
			} else {
				$type_clause = '';
			}

				$where .= $wpdb->prepare(" AND ($p.post_author = %d $type_clause)", $current_user->ID );
		} elseif ($revisionary->config_loaded) {
			$where .= (array_filter($revisionary->enabled_post_types)) 
			? " AND ($p.post_type IN ('" . implode("','", array_keys(array_filter($revisionary->enabled_post_types))) . "'))" 
			: "AND 1=2";
		}

		if (empty($args['suppress_author_clause'])) {
			$status_csv = "'" . implode("','", get_post_stati(['public' => true, 'private' => true], 'names', 'or')) . "'";
			$where .= " AND $p.comment_count IN (SELECT ID FROM $wpdb->posts WHERE post_status IN ($status_csv))";
		}

		return $where;
	}

	function revisions_filter($clauses, $_wp_query = false) {
		$clauses['where'] = $this->revisions_where_filter($clauses['where']);
		$this->posts_clauses_filtered = $clauses;
		return $clauses;
	}
	
	function rvy_pending_list_register_columns( $columns ) {
		global $wp_query;
		foreach( $wp_query->posts as $post ) {
			if ( ('future-revision' == $post->post_status) || (strtotime($post->post_date_gmt) > agp_time_gmt()) ) {
				$have_scheduled = true;
				break;
			}
		}
		
		$arr = [
			'cb' => '<input type="checkbox" />', 
			'title' => __('Revision', 'revisionary'), 
			'post_status' => __('Status', 'revisionary'), 
			'post_type' => __('Post Type', 'revisionary'), 
			'author' => __('Revised By', 'revisionary'), 
			'date' => __('Submission', 'revisionary'),
		];

		if (!empty($_REQUEST['cat'])) {
			$arr['categories'] = get_taxonomy('category')->labels->name;
		}

		if (! empty( $have_scheduled ) || (!empty($_REQUEST['orderby']) && 'date_sched' == $_REQUEST['orderby']) ) {
			$arr['date_sched'] = __('Schedule');
		}

		$arr['published_post'] = __('Published Post', 'revisionary');
		$arr['post_author'] = __('Post Author', 'revisionary');

		return $arr;
	}

	function rvy_pending_custom_col( $column_name, $post_id ) {
		if ( ! $post = get_post( $post_id ) )
			return;
		
		switch ($column_name) {
			case 'post_type':
				if ( $type_obj = get_post_type_object( get_post_field( 'post_type', $post->post_parent ) ) ) {
					$link = add_query_arg( 'post_type', $type_obj->name, esc_url($_SERVER['REQUEST_URI']) );
					echo "<a href='$link'>{$type_obj->labels->singular_name}</a>";
				}

				break;

			case 'post_status':
	
				switch ( $post->post_status ) {
					case 'pending-revision':
						$label = __('Pending', 'revisionary');
						break;
					case 'future-revision':
						$label = __('Scheduled', 'revisionary');
						break;
					default:
						if ( $status_obj = get_post_status_object( $post->post_status) )
							$label = $status_obj->label;
						else
							$label = ucwords($post->post_status);
				}

				$link = add_query_arg( 'post_status', $post->post_status, esc_url($_SERVER['REQUEST_URI']) );
				echo "<a href='$link'>$label</a>";

				break;
		
			case 'date_sched' :
				if ( ('future-revision' === $post->post_status ) || ( strtotime($post->post_date_gmt) > agp_time_gmt() ) ) {
						$t_time = get_the_time( __( 'Y/m/d g:i:s a', 'revisionary' ) );
						$m_time = $post->post_date;
						
						$time = get_post_time( 'G', true, $post );

						$time_diff = time() - $time;

						if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
							$h_time = sprintf( __( '%s ago' ), human_time_diff( $time ) );
						} else {
							//$h_time = mysql2date( __( 'Y/m/d' ), $m_time );
							$h_time = mysql2date( __( 'Y/m/d g:i a', 'revisionary' ), $m_time );
							$h_time = str_replace( ' am', '&nbsp;am', $h_time );
							$h_time = str_replace( ' pm', '&nbsp;pm', $h_time );
							$h_time = str_replace( ' ', '<br />', $h_time );
						}

						if ('future-revision' == $post->post_status) {
							$t_time = sprintf(__('Scheduled publication: %s', 'revisionary'), $t_time);
						} else {
							$h_time = "<span class='rvy-requested-date'>[$h_time]</span>";
							$t_time = sprintf(__('Requested publication: %s', 'revisionary'), $t_time);
						}

						if ( $time_diff > 0 ) {
							echo '<strong class="error-message">' . __( 'Missed schedule' ) . '</strong>';
							echo '<br />';
						} else {
							//_e( 'Scheduled' );
						}

					/** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
					$mode = 'list';
					echo '<abbr title="' . $t_time . '">' . apply_filters( 'rvy_post_schedule_date_column_time', $h_time, $post, 'date', $mode ) . '</abbr>';
				}

				break;

			case 'published_post':
				if ($parent_post = get_post($post->comment_count)) {
					self::column_title($parent_post, true);

				} elseif ($published_id = rvy_post_id($post->ID)) {
					if ($parent_post = get_post($published_id)) {
						self::column_title($parent_post, true);
					} else {
						echo $published_id;
					}
				}

				echo $this->handle_published_row_actions( $parent_post, 'published_post' );

				break;

			case 'post_author':
				$parent_post = get_post(rvy_post_id($post->ID));

				if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
					$authors     = get_multiple_authors($parent_post->ID);
					$authors_str = [];
					foreach ($authors as $author) {
						if (is_object($author)) {
							$url           = add_query_arg('post_author', $author->ID, esc_url($_SERVER['REQUEST_URI']));
							$authors_str[] = '<a href="' . $url . '">' . esc_html($author->display_name) . '</a>';
						}
					}

					if (empty($authors_str)) {
						$authors_str[] = '<span aria-hidden="true">—</span><span class="screen-reader-text">' . __('No author',
							'revisionary') . '</span>';
					}

					echo implode(', ', $authors_str);
				} else {
					$author_caption = get_the_author_meta('display_name', $parent_post->post_author);
					echo $this->apply_edit_link(add_query_arg('post_author', $parent_post->post_author, esc_url($_SERVER['REQUEST_URI'])), $author_caption);
				}
		} // end switch
	}

	protected function handle_published_row_actions( $post, $column_name ) {
		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post    = current_user_can( 'edit_post', $post->ID );
		$actions          = [];

		static $last_past_revision;

		if(!isset($last_past_revision)) {
			$last_past_revision = [];
		}

		if ( $can_edit_post && 'trash' != $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%1$s" title="%2$s" aria-label="%2$s">%3$s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: post title */
				esc_attr('Edit published post'),
				__( 'Edit' )
			);
		}

		$actions['list_filter'] = sprintf(
			'<a href="%1$s" title="%2$s" aria-label="%2$s">%3$s</a>',
			add_query_arg('published_post', $post->ID, esc_url($_SERVER['REQUEST_URI'])),
			/* translators: %s: post title */
			esc_attr( sprintf( __( 'View only revisions of %s', 'revisionary' ), '&#8220;' . $post->post_title . '&#8221;' ) ),
			__( 'Filter' )
		);

		if ( is_post_type_viewable( $post_type_object ) ) {
			$actions['view'] = sprintf(
				'<a href="%1$s" rel="bookmark" title="%2$s" aria-label="%2$s">%3$s</a>',
				get_permalink( $post->ID ),
				/* translators: %s: post title */
				esc_attr( __( 'View published post', 'revisionary' ) ),
				__( 'View' )
			);
		}

		// todo: single query for all listed published posts
		if (!isset($last_past_revision[$post->ID])) {
			global $wpdb;
			if ($revision_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_type='revision' AND post_status='inherit' AND post_parent = %d ORDER BY ID DESC LIMIT 1",
					$post->ID
				)
			)) {
				$last_past_revision[$post->ID] = $revision_id;
			}
		}

		if (!empty($last_past_revision[$post->ID])) {
			$actions['history'] = sprintf(
				'<a href="%1$s" title="%2$s" aria-label="%2$s" target="_revision_diff">%3$s</a>',
				admin_url("revision.php?revision={$last_past_revision[$post->ID]}"),
				/* translators: %s: post title */
				esc_attr(__('Compare Past Revisions', 'revisionary')),
				__( 'History', 'revisionary' )
			);
		}

		return $this->row_actions( $actions );
	}

	/**
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		//return current_user_can( get_post_type_object( $this->screen->post_type )->cap->edit_posts );
		return false;
	}

	/**
	 *
	 * @global array    $avail_post_stati
	 * @global WP_Query $wp_query
	 * @global int      $per_page
	 * @global string   $mode
	 */
	public function prepare_items() {
		global $avail_post_stati, $wp_query, $per_page, $mode;

		// is going to call wp()
		$avail_post_stati = $this->do_query();
		
		$per_page = $this->get_items_per_page( 'edit_page_per_page' );	//  use Pages setting

		$total_items = $wp_query->found_posts;
		
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page' => $per_page
		]);
	}

	public function no_items() {
		$post_type = 'page';
		
		if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] )
			echo get_post_type_object( $post_type )->labels->not_found_in_trash;
		else
			echo get_post_type_object( $post_type )->labels->not_found;
	}

	/**
	 * Determine if the current view is the "All" view.
	 *
	 * @since 4.2.0
	 *
	 * @return bool Whether the current view is the "All" view.
	 */
	protected function is_base_request() {
		$vars = $_GET;
		unset( $vars['paged'] );

		if ( empty( $vars ) ) {
			return true;
		}

		return 1 === count( $vars ) && ! empty( $vars['mode'] );
	}

	private function count_revisions($post_type = '', $statuses = '' ) {
		global $wpdb;

		$status_csv = implode("','", array_map('sanitize_key', (array) $statuses));

		if ($post_type) {
			$type_clause = "AND post_type IN ('" 
			. implode("','", array_map('sanitize_key', (array) $post_type)) 
			. "')";
		}

		$where = $this->revisions_where_filter("post_status IN ('$status_csv') $type_clause", ['status_count' => true]);

		$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE $where";
		$query .= ' GROUP BY post_status';

		$query = apply_filters('presspermit_posts_request', $query, ['has_cap_check' => true]);  // has_cap_check argument triggers inclusion of revision statuses

		$results = (array) $wpdb->get_results( $query, ARRAY_A );
	
		$counts = [];

		foreach ( $results as $row ) {
			$counts[ $row['post_status'] ] = $row['num_posts'];
		}

		if (!rvy_get_option('pending_revisions')) {
			unset($counts['pending-revision']);
		}

		if (!rvy_get_option('scheduled_revisions')) {
			unset($counts['future-revision']);
		}

		$counts = (object) $counts;
	
		return apply_filters( 'revisionary_count_revisions', $counts, $post_type, $statuses );
	}

	/**
	 *
	 * @global array $locked_post_status This seems to be deprecated.
	 * @return array
	 */
	protected function get_views() {
		global $wp_query, $wpdb, $current_user;
		
		$post_types = rvy_get_manageable_types();
		$statuses = ['pending-revision', 'future-revision'];

		$q = ['post_type' => $post_types, 'fields' => 'ids', 'post_parent' => $this->published_post_ids];
		
		if ( ! empty($_REQUEST['s']) ) {
			$q['s'] = $_REQUEST['s'];
		}
		
		if ( ! empty($_REQUEST['m']) ) {
			$q['m'] = (int) $_REQUEST['m'];
		}
		
		$num_posts = $this->count_revisions($post_types, $statuses);

		$links = [];
		$links['all'] = '';

		$where = $this->revisions_where_filter( 
			$wpdb->prepare(
				"$wpdb->posts.post_status IN ('pending-revision', 'future-revision') AND $wpdb->posts.post_author = '%d'", 
				$current_user->ID
			),
			['status_count' => true]
		);

		$_request = apply_filters('presspermit_posts_request',
				"SELECT COUNT($wpdb->posts.ID) FROM $wpdb->posts WHERE $where", 
				['has_cap_check' => true]
		);

		if ($my_count = $wpdb->get_var($_request)) {
			if (!empty($_REQUEST['author']) && ($current_user->ID == $_REQUEST['author']) && empty($_REQUEST['post_type']) && empty($_REQUEST['post_author']) && empty($_REQUEST['published_post']) && empty($_REQUEST['post_status'])) {
				$current_link_class = 'mine';
				$link_class = " class='current'";
			} else {
				$link_class = '';
			}

			$links['mine'] = sprintf(__('%sMy Revisions%s(%s)', 'revisionary'), "<a href='admin.php?page=revisionary-q&author=$current_user->ID'{$link_class}>", '</a>', "<span class='count'>$my_count</span>");
		}

		$where = $this->revisions_where_filter( 
			$wpdb->prepare(
				"r.post_status IN ('pending-revision', 'future-revision') AND p.post_author = '%d'", 
				$current_user->ID
			),
			['alias' => 'r', 'status_count' => true]
		);

		$count_query = apply_filters('presspermit_posts_request',
			"SELECT COUNT(DISTINCT p.ID) FROM $wpdb->posts AS p INNER JOIN $wpdb->posts AS r ON r.comment_count = p.ID WHERE $where", 
			['has_cap_check' => true, 'source_alias' => 'p']
		);

		$status_csv = "'" . implode("','", get_post_stati(['public' => true, 'private' => true], 'names', 'or')) . "'";
		$count_query .= " AND p.post_status IN ($status_csv)";

		// work around some versions of PressPermit inserting non-aliased post_type reference into where clause under some configurations
		$count_query = str_replace("$wpdb->posts.post_type", "p.post_type", $count_query);

		if ($my_post_count = $wpdb->get_var( 
			$count_query
		)) {
			if (!empty($_REQUEST['post_author']) && ($current_user->ID == $_REQUEST['post_author']) && empty($_REQUEST['post_type']) && empty($_REQUEST['author']) && empty($_REQUEST['published_post']) && empty($_REQUEST['post_status'])) {
				$current_link_class = 'my_posts';
				$link_class = " class='current'";
			} else {
				$link_class = '';
			}

			$links['my_posts'] = sprintf(__('%sMy Published Posts%s(%s)', 'revisionary'), "<a href='admin.php?page=revisionary-q&post_author=$current_user->ID'{$link_class}>", '</a>', "<span class='count'>$my_post_count</span>");
		}

		$all_count = 0;
		foreach($statuses as $status) {
			if (!isset($num_posts->$status)) {
				$num_posts->$status = 0;
			}

			if (!empty($num_posts->$status)) {
				$status_obj = get_post_status_object($status);
				
				$status_label = $status_obj ? sprintf(
					translate_nooped_plural( $status_obj->label_count, $num_posts->$status ),
					number_format_i18n( $num_posts->$status )
				) : $status;

				if (!empty($_REQUEST['post_status']) && ($status == $_REQUEST['post_status']) && empty($_REQUEST['post_type']) && empty($_REQUEST['author']) && empty($_REQUEST['post_author']) && empty($_REQUEST['published_post'])) {
					$current_link_class = $status;
					$link_class = " class='current'";
				} else {
					$link_class = '';
				}
				$links[$status] = "<a href='admin.php?page=revisionary-q&post_status=$status'{$link_class}>$status_label</a>";

				$all_count += $num_posts->$status;
			}
		}

		if (empty($current_link_class) && empty($_REQUEST['post_type']) && empty($_REQUEST['author']) && empty($_REQUEST['post_author']) && empty($_REQUEST['published_post']) && empty($_REQUEST['post_status'])) {
			$link_class = " class='current'";
		} else {
			$link_class = '';
		}

		$links['all'] = "<a href='admin.php?page=revisionary-q'{$link_class}>" . sprintf( __('All %s', 'revisionary'), "<span class='count'>($all_count)</span>" ) . '</a>';
		
		return $links;
	}

	/**
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		global $current_user;

		$actions = array();

		$approval_potential = false;

		foreach(rvy_get_manageable_types() as $post_type) {
			$type_obj = get_post_type_object($post_type);
			if (isset($type_obj->cap->edit_published_posts) && !empty($current_user->allcaps[$type_obj->cap->edit_published_posts])) {
				$approval_potential = true;
				break;
			}
		}

		if ($approval_potential = apply_filters('revisionary_bulk_action_approval', $approval_potential)) {
			$actions['approve_revision'] = __('Approve');
			$actions['publish_revision'] = __('Publish');

			if (revisionary()->getOption('scheduled_revisions')) {
				$actions['unschedule_revision'] = __('Unschedule', 'revisionary');
			}
		}

		$actions['delete'] = __( 'Delete Permanently' );
		return $actions;
	}

	protected function categories_dropdown( $post_type ) {
		if ( false !== apply_filters( 'disable_categories_dropdown', false, $post_type ) ) {
			return;
		}

		$cat = (!empty($_REQUEST['cat'])) ? (int) $_REQUEST['cat'] : '';

		//if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
			$dropdown_options = array(
				'show_option_all' => get_taxonomy( 'category' )->labels->all_items,
				'hide_empty' => 0,
				'hierarchical' => 1,
				'show_count' => 0,
				'orderby' => 'name',
				'selected' => $cat
			);

			echo '<label class="screen-reader-text" for="cat">' . __( 'Filter by category' ) . '</label>';
			wp_dropdown_categories( $dropdown_options );
		//}
	}

	
	protected function extra_tablenav( $which ) {
?>
		<div class="alignleft actions">
<?php
		if ( 'top' === $which && !is_singular() ) {
			ob_start();
			
			$this->rvy_months_dropdown();
			
			$this->categories_dropdown( $this->screen->post_type );

			$output = ob_get_clean();

			if ( ! empty( $output ) ) {
				echo $output;
				submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
			}
		}
?>
		</div>
<?php
		do_action( 'manage_posts_extra_tablenav', $which );
	}

	// fork of WP_List_Table::months_dropdown(), modified for revisions
	protected function rvy_months_dropdown() {
		global $wpdb, $wp_locale;

		$extra_checks = "AND post_status != 'auto-draft'";
		
		if (isset($_GET['post_status']) && ('all' != $_GET['post_status'])) {
			$extra_checks = $wpdb->prepare( ' AND post_status = %s', sanitize_key($_GET['post_status']) );
		} else {
			$extra_checks = " AND post_status IN ('pending-revision', 'future-revision')";
		}

		$date_col = ( ! empty($_REQUEST['post_status']) && 'future-revision' == $_REQUEST['post_status'] ) ? 'post_date' : 'post_modified';

		$ids = implode("','", array_map('intval', $this->published_post_ids));
		
		$type_csv = implode("','", array_map('sanitize_key', rvy_get_manageable_types()));

		$months = $wpdb->get_results(
			"SELECT DISTINCT YEAR( $date_col ) AS year, MONTH( $date_col ) AS month
			FROM $wpdb->posts
			WHERE post_type IN ('$type_csv') AND comment_count IN ('$ids')
			$extra_checks
			ORDER BY $date_col DESC" 
		);

		$month_count = count( $months );

		if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
			return;

		$m = isset( $_GET['m'] ) ? (int) $_GET['m'] : 0;
?>
		<label for="filter-by-date" class="screen-reader-text"><?php _e( 'Filter by date', 'revisionary' ); ?></label>
		<select name="m" id="filter-by-date">
			<option<?php selected( $m, 0 ); ?> value="0"><?php _e( 'All dates', 'revisionary' ); ?></option>
<?php
		foreach ( $months as $arc_row ) {
			if ( 0 == $arc_row->year )
				continue;

			$month = zeroise( $arc_row->month, 2 );
			$year = $arc_row->year;

			printf( "<option %s value='%s'>%s</option>\n",
				selected( $m, $year . $month, false ),
				esc_attr( $arc_row->year . $month ),
				/* translators: 1: month name, 2: 4-digit year */
				sprintf( _x( '%1$s %2$d', 'MonthName 4-DigitYear', 'revisionary' ), $wp_locale->get_month( $month ), $year )
			);
		}
?>
		</select>
<?php
	}

	/**
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return [
			'title'    => 'title',
			'post_status' => 'post_status',
			'post_type' => 'post_type',
			'author'	=> 'author',
			'date'     => array( 'modified', true ),
			'date_sched'     => array( 'date_sched', true ),
			'published_post' => 'published_post',
			'post_author'	=> 'post_author',
		];
	}
	
	// Overriding parent class method here to make column sort link double as filter clearance (todo: jQuery?)
	public function print_column_headers( $with_id = true ) {		
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = set_url_scheme( esc_url('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ));
		$current_url = remove_query_arg( 'paged', $current_url );

		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = sanitize_key($_GET['orderby']);
		} else {
			$current_orderby = '';
		}

		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
				. '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				list( $orderby, $desc_first ) = $sortable[ $column_key ];

				if ( $current_orderby === $orderby ) {
					$order   = 'asc' === $current_order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					$order   = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				// kevinB modification: make column sort links double as filter clearance
				// (If results are already filtered by column, first header click clears the filter, second click applies sorting)
				if (!empty($_REQUEST[$column_key])) {

					// use post status and post type column headers to reset filter, but not for sorting
					$_url = remove_query_arg($orderby, $current_url);
					
					/*
					if (!empty($_REQUEST['post_status']) && ('future-revision' == $_REQUEST['post_status'])) {
						$order = 'ASC';
						$orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array('post_type', 'post_status'))) ?  : 'date_sched';
					} else {
						$order = 'DESC';
						$orderby = 'date';
					}
					*/

					$column_display_name = '<a href="' . esc_url($_url) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
				} else {
					$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
				}
			}

			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}

	public function column_title( $post, $simple_link = false ) {
		$can_edit_post = current_user_can( 'edit_post', $post->ID ) || $simple_link;

		echo "<strong>";

		$title = _draft_or_post_title($post);

		if ( $can_edit_post && $post->post_status != 'trash' ) {
			printf(
				'<a class="row-title" href="%s" aria-label="%s">%s%s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: post title */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) ),
				'',
				$title
			);
		} else {
			echo $title;
		}

		echo "</strong>\n";
	}

	public function column_date( $post ) {
		$t_time = get_the_modified_time( __( 'Y/m/d g:i:s a', 'revisionary' ), $post );
		$time = strtotime($post->post_modified_gmt);
		$time_diff = time() - $time;

		if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
			$h_time = sprintf( __( '%s ago' ), human_time_diff( $time ) );
			$h_time = str_replace( ' ', '&nbsp;', $h_time );
		} else {
			$h_time = mysql2date( __( 'Y/m/d g:i a', 'revisionary' ), $t_time );
			$h_time = str_replace( ' am', '&nbsp;am', $h_time );
			$h_time = str_replace( ' pm', '&nbsp;pm', $h_time );
			$h_time = str_replace( ' ', '<br />', $h_time );
		}
		
		echo '<abbr title="' . $t_time . '">' . apply_filters( 'post_date_column_time', $h_time, $post, 'date' ) . '</abbr>';
	}
	
	protected function apply_edit_link( $url, $label ) {
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			$label
		);
	}

	public function column_author( $post ) {
		// Just track single post_author for revision. Authors taxonomy is applied to revise

		//if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
		//	do_action("manage_{$post->post_type}_posts_custom_column", 'authors', $post->ID);
		//} else {
			$args = ['author' => get_the_author_meta( 'ID' )];
			echo $this->apply_edit_link( add_query_arg('author', $args['author'], esc_url($_SERVER['REQUEST_URI'])), get_the_author() );
		//}
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @since 4.3.0
	 * @access protected
	 *
	 * @param object $post        Post being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions output for posts.
	 */

	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post->post_type );

		$can_read_post = current_user_can('read_post', $post->ID);

		$can_edit_post    = current_user_can( 'edit_post', $post->ID );

		$can_read_post = $can_read_post || $can_edit_post; // @todo

		$actions          = array();
		$title            = _draft_or_post_title();

		if ( $can_edit_post && 'trash' != $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%1$s" title="%2$s" aria-label="%2$s">%3$s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: post title */
				esc_attr('Edit Revision'),
				__( 'Edit' )
			);
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			$actions['delete'] = sprintf(
				'<a href="%1$s" class="submitdelete" title="%2$s" aria-label="%2$s">%3$s</a>',
				get_delete_post_link( $post->ID, '', true ),
				/* translators: %s: post title */
				esc_attr( sprintf( __( 'Delete Revision', 'revisionary' ), $title ) ),
				__( 'Delete' )
			);
		}

		if ( is_post_type_viewable( $post_type_object ) ) {
			if ( $can_read_post ) {
				if (rvy_get_option('revision_preview_links') || current_user_can('administrator') || is_super_admin()) {
					$preview_link = rvy_preview_url($post);

					//$preview_link = remove_query_arg( 'post_type', $preview_link );
					$preview_link = remove_query_arg( 'preview_id', $preview_link );
					$actions['view'] = sprintf(
						'<a href="%1$s" rel="bookmark" title="%2$s" aria-label="%2$s">%3$s</a>',
						esc_url( $preview_link ),
						/* translators: %s: post title */
						esc_attr( __( 'Preview Revision', 'revisionary' ) ),
						__( 'Preview' )
					);
				}
			}

			//if ( current_user_can( 'read_post', $post->ID ) ) { // @todo make this work for Author with Revision exceptions
			if ( $can_read_post || $can_edit_post ) {  
				$actions['diff'] = sprintf(
					'<a href="%1$s" class="" title="%2$s" aria-label="%2$s" target="_revision_diff">%3$s</a>',
					admin_url("revision.php?revision=$post->ID"),
					/* translators: %s: post title */
					esc_attr( sprintf( __('Compare Changes', 'revisionary'), $title ) ),
					_x('Compare', 'revisions', 'revisionary')
				);
			}
		}

		return $this->row_actions( $actions );
	}

	// override default nonce field
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-revision-queue' );
		}
		?>
	<div class="tablenav <?php echo esc_attr( $which ); ?>">

		<?php if ( $this->has_items() ) : ?>
		<div class="alignleft actions bulkactions">
			<?php $this->bulk_actions( $which ); ?>
		</div>
			<?php
		endif;
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		?>

		<br class="clear" />
	</div>
		<?php
	}

	/**
	 * Outputs the hidden row displayed when inline editing
	 *
	 * @since 3.1.0
	 *
	 * @global string $mode
	 */
	public function inline_edit() {
	}
}
