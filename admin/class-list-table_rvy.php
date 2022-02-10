<?php
require_once( ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php' );

class Revisionary_List_Table extends WP_Posts_List_Table {
	private $published_post_ids_query = '';
	private $published_post_count_ids_query = '';
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

		if (!empty($_REQUEST['published_post']) && !rvy_get_post_meta((int) $_REQUEST['published_post'], '_rvy_has_revisions', true)) {
			revisionary_refresh_postmeta((int) $_REQUEST['published_post']);
		}

		// Gutenberg will not allow immediate deletion of revisions from within editor
		if (!empty($_REQUEST['pp_revisions_deleted'])) {
			global $current_user;
			
			$delete_id = (int) $_REQUEST['pp_revisions_deleted'];

			if (('trash' == get_post_field('post_status', $delete_id)) && (get_post_field('post_author', $delete_id) == $current_user->ID)) {
				wp_delete_post($delete_id, true);
			}
		}

		$this->correctCommentCounts();
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

		$qp['post_status'] = array_diff( rvy_filtered_statuses(), $omit_stati );

		if (!empty($q['published_post'])) {
			$qp['p'] = (int) $q['published_post'];
		}

		$qp['posts_per_page'] = -1;
		$qp['fields'] = 'ids';
		
		/*
		if (empty($q['published_post']) && empty($q['post_author']) && !rvy_get_option('queue_query_all_posts')) { // support defeat of performance enhancement in case _has_revisions flag is not being stored reliably due to plugin integration issues
			$qp['meta_key'] = '_rvy_has_revisions';
		}
		*/

		if (!empty($q['post_author'])) {
			do_action('revisionary_queue_pre_query');
			$_pre_query = new WP_Query( $qp );
			$this->published_post_count_ids_query = $_pre_query->request;
			do_action('revisionary_queue_pre_query_done');

			$qp['author'] = $q['post_author'];
		}

		$filter_name = (defined('REVISIONARY_QUEUE_LEGACY_FILTER')) ? 'posts_clauses' : 'posts_clauses_request';

		do_action('revisionary_queue_pre_query');
		add_filter($filter_name, [$this, 'pre_query_filter'], 5, 2);
		add_filter($filter_name, [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);
		$pre_query = new WP_Query( $qp );
		remove_filter($filter_name, [$this, 'pre_query_filter'], 5, 2);
		remove_filter($filter_name, [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);
		do_action('revisionary_queue_pre_query_done');

		$this->published_post_ids_query = $pre_query->request;

		// === now query the revisions ===
		$qr = $q; 

		unset($qr['post_author']);

		$qr['post_type'] = $qp['post_type'];

		$qr['post_status'] = rvy_revision_base_statuses();

		if (isset($qr['m']) && strlen($qr['m']) == 6) {
			$qr['date_query'] = [
				'column' => ( ! empty($_REQUEST['post_mime_type']) && 'future-revision' == $_REQUEST['post_mime_type'] ) ? 'post_date' : 'post_modified',
				'year' => substr($qr['m'], 0, 4),
				'month' => substr($qr['m'], 4)
			];

			unset($qr['m']);
		}

		if ( isset( $q['orderby'] ) && !in_array($q['orderby'], ['post_mime_type', 'post_type']) ) {
			$qr['orderby'] = $q['orderby'];
		} else {
			$qr['orderby'] = ( ! empty($_REQUEST['post_mime_type']) && 'future-revision' == $_REQUEST['post_mime_type'] ) ? 'date' : 'modified';
		}

		if ( isset( $q['order'] ) && !in_array($q['orderby'], ['post_mime_type', 'post_type'] ) ) {
			$qr['order'] = $q['order'];
		} else {
			$qr['order'] = 'DESC';
		}

		$per_page = "edit_page_per_page";	// use Pages setting
		$qr['posts_per_page'] = (int) get_user_option( $per_page );
		
		if ( empty( $qr['posts_per_page'] ) || $qr['posts_per_page'] < 1 )
			$qr['posts_per_page'] = 20;
		
		if ( isset($q['post_status']) && rvy_is_revision_status( $q['post_status'] ) ) {
			$qr['post_mime_type'] = [$q['post_status']];
		} else {
			$qr['post_mime_type'] = rvy_revision_statuses();
		}

		if (!rvy_get_option('pending_revisions')) {
			$qr['post_mime_type'] = array_diff($qr['post_mime_type'], ['draft-revision', 'pending-revision']);
		}

		if (!rvy_get_option('scheduled_revisions')) {
			$qr['post_mime_type'] = array_diff($qr['post_mime_type'], ['future-revision']);
		}

		global $wp_query;

		$filter_name = (defined('REVISIONARY_QUEUE_LEGACY_FILTER')) ? 'posts_clauses' : 'posts_clauses_request';

		add_filter('presspermit_posts_clauses_intercept', [$this, 'flt_presspermit_posts_clauses_intercept'], 10, 4);
		add_filter($filter_name, [$this, 'revisions_filter'], 5, 2);
		add_filter($filter_name, [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);

		if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
			remove_action('pre_get_posts', ['MultipleAuthors\\Classes\\Query', 'action_pre_get_posts']);
			remove_filter('posts_where', ['MultipleAuthors\\Classes\\Query', 'filter_posts_where'], 10, 2);
			remove_filter('posts_join', ['MultipleAuthors\\Classes\\Query', 'filter_posts_join'], 10, 2);
			remove_filter('posts_groupby', ['MultipleAuthors\\Classes\\Query', 'filter_posts_groupby'], 10, 2);
		}

		if (!empty($_REQUEST['s'])) {
			$qr['s'] = sanitize_text_field($_REQUEST['s']);
		}

		$qr = apply_filters('revisionary_queue_vars', $qr);

		$wp_query->is_revisions_query = true;

		add_filter('posts_where', [$this, 'fltFixMimeTypeClause']);
		$wp_query->query($qr);
		remove_filter('posts_where', [$this, 'fltFixMimeTypeClause']);

		$wp_query->is_revisions_query = false;

		do_action('revisionary_queue_done');

		// prevent default display of all revisions
		if (!$wp_query->posts) {
			$wp_query->posts = [true];
		}

		remove_filter('presspermit_posts_clauses_intercept', [$this, 'flt_presspermit_posts_clauses_intercept'], 10, 4);
		remove_filter($filter_name, [$this, 'revisions_filter'], 5, 2);
		remove_filter($filter_name, [$this, 'restore_revisions_filter'], PHP_INT_MAX - 1, 2);

		return $qr['post_mime_type'];
	}

	function fltFixMimeTypeClause($where) {
		return str_replace("-revision/%'", "-revision'", $where);
	}

	function flt_presspermit_posts_clauses_intercept( $intercept, $clauses, $_wp_query, $args) {
		return $clauses;
	}

	function pre_query_where_filter($where, $args = []) {
		global $wpdb, $current_user, $revisionary;
		
		if (!current_user_can('administrator') && empty($args['suppress_author_clause']) && empty($_REQUEST['post_author'])) { //} && (!defined('PRESSPERMIT_COLLAB_VERSION') || defined('PRESSPERMIT_EXTRA_REVISION_QUEUE_FILTER'))) {
			if (rvy_get_option('revisor_hide_others_revisions') && !current_user_can('list_others_revisions') ) {
			
				$p = (!empty($args['alias'])) ? $args['alias'] : $wpdb->posts;

				$can_edit_others_types = [];

				foreach(array_keys($revisionary->enabled_post_types) as $post_type) {
					if ($type_obj = get_post_type_object($post_type)) {
						if (current_user_can($type_obj->cap->edit_others_posts)) {
							$can_edit_others_types[]= $post_type;
						}
					}
				}

				$can_edit_others_types = array_map('sanitize_key', apply_filters('revisionary_queue_edit_others_types', $can_edit_others_types));

				$type_clause = ($can_edit_others_types) ? "OR $p.post_type IN ('" . implode("','", $can_edit_others_types) . "')" : '';

				$where .= $wpdb->prepare(" AND ($p.post_author = %d $type_clause)", $current_user->ID );
			}
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

	function append_revisions_where($where, $args=[]) {
		// relocated to calling function for clarity
		return '';
	}

	function revisions_where_filter($where, $args = []) {
		global $wpdb, $current_user, $revisionary;
		
		$p = (!empty($args['alias'])) ? sanitize_text_field($args['alias']) : $wpdb->posts;

		$is_count_query = empty($args['revision_query']);

		$is_my_activity = empty($_REQUEST['all']) && empty($_REQUEST['author']) && empty($_REQUEST['post_author']) && empty($_REQUEST['post_status']);

		if (!empty($args['status_count'])) {
			$id_subquery = (!empty($this->published_post_count_ids_query)) ? $this->published_post_count_ids_query : $this->published_post_ids_query;
		} else {
			$id_subquery = $this->published_post_ids_query;
		}

		if ((empty($_REQUEST['post_author']) || !empty($args['status_count'])) && empty($_REQUEST['published_post']) && empty($args['my_published_count'])) {
			$revision_status_csv =  implode("','", array_map('sanitize_key', rvy_revision_statuses()));

			$own_revision_and = '';

			if (defined('ICL_SITEPRESS_VERSION')) {
				if (!empty($_REQUEST['lang'])) {
					$lang = sanitize_text_field($_REQUEST['lang']);
				} else {
					global $sitepress;
					if (!empty($sitepress) && method_exists($sitepress, 'get_admin_language_cookie')) {
						$lang = sanitize_text_field($sitepress->get_admin_language_cookie());
					}
				}

				if (!empty($lang)) {
					$own_revision_and = $wpdb->prepare(
						" AND $p.comment_count IN (SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE 'post_%' AND language_code = %s)",
						$lang
					);
				}
			}

			$own_revision_clause = $wpdb->prepare(
				" OR ($p.post_status IN ('draft', 'pending') AND $p.post_mime_type IN ('$revision_status_csv') AND $p.post_author = %d {$own_revision_and})",
				$current_user->ID
			);
		} else {
			$own_revision_clause = '';
		}

		if ((!$is_my_activity && !$is_count_query && (empty($args['revision_query']) || (!empty($_REQUEST['author']) && ($current_user->ID != $_REQUEST['author']))))
		|| rvy_get_option('list_unsubmitted_revisions')
		) {
			$revision_status_clause = '';
		
		} elseif ((!$is_my_activity && !$is_count_query
		&& (empty($_REQUEST['all'])
		&& (empty($_REQUEST['post_status']) || ('draft-revision' != sanitize_key($_REQUEST['post_status'])))
		)) || !empty($args['my_published_count'])) {
			$revision_status_clause = "AND $p.post_mime_type != 'draft-revision' ";

		} elseif (($is_my_activity && !$is_count_query) || (rvy_get_option('manage_unsubmitted_capability') && !current_user_can("manage_unsubmitted_revisions"))) {
			$revision_status_clause = "AND ($p.post_mime_type != 'draft-revision' OR $p.post_author = '$current_user->ID')";
		} else {
			$revision_status_clause = '';
		}

		$where_append = "($p.comment_count IN ($id_subquery) {$revision_status_clause}$own_revision_clause)";

		$status_csv = implode("','", array_map('sanitize_key', rvy_filtered_statuses()));

		$own_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_status IN ('$status_csv') AND post_author = %d",
				$current_user->ID
			)
		);

		if (rvy_get_option('admin_revisions_to_own_posts')) {
			$own_posts = apply_filters('revisionary_own_post_ids', $own_posts, $current_user->ID);
		} else {
			$own_posts = [];
		}

		$own_posts_csv = implode("','", array_map('intval', $own_posts));

		if (rvy_get_option('revisor_hide_others_revisions') && !current_user_can('administrator') 
			&& !current_user_can('list_others_revisions') && empty($args['suppress_author_clause']) 
		) {
			$allow_post_types = apply_filters('revisionary_queue_allow_post_types', []);

			$can_publish_types = [];
			foreach(array_keys($revisionary->enabled_post_types) as $post_type) {
				$type_obj = get_post_type_object($post_type);

				if (
					(
					!empty($allow_post_types[$post_type]) 
					|| (isset($type_obj->cap->edit_published_posts)
						&& current_user_can($type_obj->cap->edit_published_posts)
						&& !empty($current_user->allcaps[$type_obj->cap->edit_others_posts])
						&& current_user_can($type_obj->cap->publish_posts)
					))
					&& (!empty($revisionary->enabled_post_types[$post_type]) || !$revisionary->config_loaded)
				) {
					$can_publish_types[]= $post_type;
				}
			}

			$can_publish_types = array_intersect($can_publish_types, apply_filters('revisionary_manageable_types', $can_publish_types));

			if ($can_publish_types){
				$type_clause = "OR $p.post_type IN ('" . implode("','", array_map('sanitize_key', $can_publish_types)) . "')";
			} else {
				$type_clause = '';
			}

			$where_append .= $wpdb->prepare(" AND (($p.post_author = %d $type_clause) OR ($p.comment_count IN ('$own_posts_csv') $type_clause))", $current_user->ID );

		} elseif ($revisionary->config_loaded) {
			$where_append .= (array_filter($revisionary->enabled_post_types)) 
			? " AND ($p.post_type IN ('" 
				. implode("','", 
					array_map(
						'sanitize_key', 
						array_keys(
							array_filter($revisionary->enabled_post_types)
						)
					)
				) . "'))" 
			
			: " AND 1=2";
		}

		if (empty($args['suppress_author_clause'])) {
			$status_csv = implode("','", array_map('sanitize_key', rvy_filtered_statuses()));

			$where_append .= " AND $p.comment_count IN (SELECT ID FROM $wpdb->posts WHERE post_status IN ('$status_csv'))";
		}

		$where .= " AND $where_append";

		return $where;
	}

	function revisions_filter($clauses, $_wp_query = false) {
		$clauses['where'] = $this->revisions_where_filter($clauses['where'], ['revision_query' => true]);
		$this->posts_clauses_filtered = $clauses;
		return $clauses;
	}
	
	function correctCommentCounts() {
		global $wpdb;

		$revision_base_status_csv = implode("','", array_map('sanitize_key', rvy_revision_base_statuses()));
		$revision_status_csv = implode("','", array_map('sanitize_key', rvy_revision_statuses()));

		if ($revision_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_status IN ('$revision_base_status_csv') AND post_mime_type IN ('$revision_status_csv') AND comment_count = 0")) {
			foreach($revision_ids as $revision_id) {
				if ($main_post_id = get_post_meta($revision_id, '_rvy_base_post_id', true)) {
					$wpdb->update($wpdb->posts, ['comment_count' => $main_post_id], ['ID' => $revision_id]);
				}
			}
		}
	}

	function rvy_pending_list_register_columns( $columns ) {
		global $wp_query;
		foreach( $wp_query->posts as $post ) {
			if ( !empty($post) && is_object($post) && (('future-revision' == $post->post_mime_type && 'future' == $post->post_status) || (strtotime($post->post_date_gmt) > agp_time_gmt())) ) {
				$have_scheduled = true;
				break;
			}
		}
		
		$arr = [
			'cb' => '<input type="checkbox" />', 
			'title' => pp_revisions_label('queue_col_revision'), 
			'post_status' => __('Status', 'revisionary'), 
			'post_type' => __('Post Type', 'revisionary'), 
			'author' => pp_revisions_label('queue_col_revised_by'), 
			'date' =>  pp_revisions_label('queue_col_revision_date'),
		];

		if (!empty($_REQUEST['cat'])) {
			$arr['categories'] = get_taxonomy('category')->labels->name;
		}

		if (! empty( $have_scheduled ) || (!empty($_REQUEST['orderby']) && 'date_sched' == $_REQUEST['orderby']) ) {
			$arr['date_sched'] = __('Schedule');
		}

		$arr['published_post'] = pp_revisions_label('queue_col_published_post');

		$arr['post_author'] = pp_revisions_label('queue_col_post_author');

		return $arr;
	}

	function rvy_pending_custom_col( $column_name, $post_id ) {
		if ( ! $post = get_post( $post_id ) )
			return;
		
		$request_url = add_query_arg($_REQUEST,rvy_admin_url('admin.php?page=revisionary-q'));

		switch ($column_name) {
			case 'post_type':
				$post_type = get_post_field('post_type', $post_id);

				if ( $type_obj = get_post_type_object( $post_type ) ) {
					$link = add_query_arg('post_type', $type_obj->name, $request_url);
					echo "<a href='$link'>{$type_obj->labels->singular_name}</a>";
				} else {
					echo "($post_type)";
				}

				break;

			case 'post_status':
				if (rvy_is_revision_status($post->post_mime_type)) {
					$label = pp_revisions_status_label($post->post_mime_type, 'short');
				} else {
					$label = ucwords($post->post_mime_type);
				}

				$link = add_query_arg('post_status', $post->post_mime_type, $request_url);
				echo "<a href='$link'>$label</a>";

				break;
		
			case 'date_sched' :
				if ( ('future-revision' === $post->post_mime_type ) || ( strtotime($post->post_date_gmt) > agp_time_gmt() ) ) {
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

						if ('future-revision' == $post->post_mime_type) {
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
							$url           = add_query_arg('post_author', $author->ID, $request_url);
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
					echo $this->apply_edit_link(add_query_arg('post_author', $parent_post->post_author, $request_url), $author_caption);
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
			if ($edit_link = get_edit_post_link( $post->ID )) {
				$actions['edit'] = sprintf(
					'<a href="%1$s" title="%2$s" aria-label="%2$s">%3$s</a>',
					$edit_link,
					/* translators: %s: post title */
					esc_attr('Edit published post'),
					__( 'Edit' )
				);
			}
		}

		$request_url = add_query_arg($_REQUEST, rvy_admin_url('admin.php?page=revisionary-q'));

		$actions['list_filter'] = sprintf(
			'<a href="%1$s" title="%2$s" aria-label="%2$s">%3$s</a>',

			add_query_arg('published_post', $post->ID, $request_url),
			/* translators: %s: post title */
			esc_attr( sprintf( __( 'View only revisions of %s', 'revisionary' ), '&#8220;' . $post->post_title . '&#8221;' ) ),
			__( 'Filter' )
		);

		if ( is_post_type_viewable( $post_type_object ) ) {
			$status_obj = get_post_status_object($post->post_status);

			if (!empty($status_obj->public) || !empty($status_obj->private)) {
				$actions['view'] = sprintf(
					'<a href="%1$s" rel="bookmark" title="%2$s" aria-label="%2$s">%3$s</a>',
					get_permalink( $post->ID ),
					/* translators: %s: post title */
					esc_attr( __( 'View published post', 'revisionary' ) ),
					__( 'View' )
				);
			} else {
				$actions['view'] = sprintf(
					'<a href="%1$s" rel="bookmark" title="%2$s" aria-label="%2$s">%3$s</a>',
					get_preview_post_link( $post->ID ),
					/* translators: %s: post title */
					esc_attr( __( 'View published post', 'revisionary' ) ),
					__( 'Preview' )
				);
			}
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
		
		// auto-flush revision flags 
		/*
		if (!$total_items && !rvy_get_option('queue_query_all_posts') && !get_transient('revisionary_flushed_has_revision_flag')) {
			revisionary_refresh_revision_flags();
			set_transient('revisionary_flushed_has_revision_flag', true, 60);
		}
		*/

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page' => $per_page
		]);

		if (empty($_REQUEST['all']) && empty($_REQUEST['author']) && empty($_REQUEST['post_author']) && empty($_REQUEST['post_status'])) :?>
		<script type="text/javascript">
        /* <![CDATA[ */
        jQuery(document).ready( function($) {
            $('span.ppr-my-activity-count').html('(<?php echo $total_items;?>)&nbsp;');
        });
        /* ]]> */
		</script>
		<?php endif;
	}

	public function no_items() {
		$post_type = 'page';
		
		if (isset($_REQUEST['post_status']) && 'trash' === sanitize_key($_REQUEST['post_status']))
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

		$where = $this->revisions_where_filter("post_mime_type IN ('$status_csv') $type_clause", ['status_count' => true]);

		$query = "SELECT post_mime_type, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE $where";
		$query .= ' GROUP BY post_mime_type';

		// @todo: review Permissions integration for revision count filtering
		//$query = apply_filters('presspermit_posts_request', $query, ['has_cap_check' => true]);  // has_cap_check argument triggers inclusion of revision statuses

		$results = (array) $wpdb->get_results( $query, ARRAY_A );
	
		$counts = [];

		foreach ( $results as $row ) {
			$counts[ $row['post_mime_type'] ] = $row['num_posts'];
		}

		if (!rvy_get_option('pending_revisions')) {
			unset($counts['draft-revision']);
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
	 * @return array
	 */
	protected function get_views() {
		global $wp_query, $wpdb, $current_user;
		
		$post_types = rvy_get_manageable_types();
		$revision_statuses = rvy_revision_statuses();

		$num_posts = $this->count_revisions($post_types, $revision_statuses);

		$links = [];

		$links[''] = '';
		$links['all'] = '';

		$revision_status_csv = implode("','", array_map('sanitize_key', rvy_revision_statuses()));

		$where = $this->revisions_where_filter( 
			$wpdb->prepare(
				"$wpdb->posts.post_mime_type IN ('$revision_status_csv') AND $wpdb->posts.post_author = '%d'", 
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

			$links['mine'] = sprintf(
				translate_nooped_plural(pp_revisions_label('my_revisions'), $my_count), 
				"<a href='admin.php?page=revisionary-q&author=$current_user->ID'{$link_class}>", '</a>', "<span class='count'>$my_count</span>"
			) .'&nbsp;';
		}

		$where = $this->revisions_where_filter( 
			$wpdb->prepare(
				"r.post_mime_type IN ('$revision_status_csv') AND p.post_author = '%d'", 
				$current_user->ID
			),
			['alias' => 'r', 'status_count' => true, 'my_published_count' => true]
		);

		$count_query = apply_filters('presspermit_posts_request',
			"SELECT COUNT(r.ID) FROM $wpdb->posts AS p INNER JOIN $wpdb->posts AS r ON r.comment_count = p.ID WHERE $where", 
			['has_cap_check' => true, 'source_alias' => 'p']
		);

		$status_csv = implode("','", array_map('sanitize_key', rvy_filtered_statuses()));
		$count_query .= " AND p.post_status IN ('$status_csv')";

		// work around some versions of PressPermit inserting non-aliased post_type reference into where clause under some configurations
		$count_query = str_replace("$wpdb->posts.post_type ", "p.post_type ", $count_query);

		if ($my_post_count = $wpdb->get_var( 
			$count_query
		)) {
			if (!empty($_REQUEST['post_author']) && ($current_user->ID == $_REQUEST['post_author']) && empty($_REQUEST['post_type']) && empty($_REQUEST['author']) && empty($_REQUEST['published_post']) && empty($_REQUEST['post_status'])) {
				$current_link_class = 'my_posts';
				$link_class = " class='current'";
			} else {
				$link_class = '';
			}

			$links['my_posts'] = sprintf(
				translate_nooped_plural(pp_revisions_label('my_published_posts'), $my_post_count), 
				"<a href='admin.php?page=revisionary-q&post_author=$current_user->ID'{$link_class}>", '</a>', "<span class='count'>$my_post_count</span>"
			) . '&nbsp;';
		}

		$all_count = 0;
		foreach($revision_statuses as $status) {
			if (!isset($num_posts->$status)) {
				$num_posts->$status = 0;
			}

			if (!empty($num_posts->$status)) {
				$status_obj = get_post_status_object($status);

				$status_label = $status_obj ? sprintf(
					translate_nooped_plural( $status_obj->label_count, $num_posts->$status ),
					number_format_i18n( $num_posts->$status )
				) : $status;

				if (!empty($_REQUEST['post_status']) && ($status == sanitize_key($_REQUEST['post_status'])) && empty($_REQUEST['post_type']) && empty($_REQUEST['author']) && empty($_REQUEST['post_author']) && empty($_REQUEST['published_post'])) {
					$current_link_class = $status;
					$link_class = " class='current'";
				} else {
					$link_class = '';
				}
				$links[$status] = "<a href='admin.php?page=revisionary-q&post_status=$status'{$link_class}>$status_label</a>";

				$all_count += $num_posts->$status;
			}
		}

		if (empty($current_link_class) && empty($_REQUEST['post_type']) && empty($_REQUEST['author']) && empty($_REQUEST['post_author']) && empty($_REQUEST['published_post']) && empty($_REQUEST['post_status']) && empty($_REQUEST['all'])) {
			$link_class = " class='current'";
		} else {
			$link_class = '';
		}

		$links[''] = "<a href='admin.php?page=revisionary-q'{$link_class}>" . sprintf( __('My Activity', 'revisionary'), "<span class='count'>($all_count)</span>" ) . '</a><span class="ppr-my-activity-count"></span>';

		if (empty($current_link_class) && empty($_REQUEST['post_type']) && empty($_REQUEST['author']) && empty($_REQUEST['post_author']) && empty($_REQUEST['published_post']) && empty($_REQUEST['post_status']) && !empty($_REQUEST['all'])) {
			$link_class = " class='current'";
		} else {
			$link_class = '';
		}

		$links['all'] = "<a href='admin.php?page=revisionary-q&all=1'{$link_class}>" . sprintf( __('All %s', 'revisionary'), "<span class='count'>($all_count)</span>" ) . '</a>';
		
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

		$actions['submit_revision'] = __('Submit');

		if ($approval_potential = apply_filters('revisionary_bulk_action_approval', $approval_potential)) {
			$actions['approve_revision'] = __('Approve');
			$actions['publish_revision'] = __('Publish');

			if (rvy_get_option('scheduled_revisions')) {
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
		/*
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
		*/
?>
		</div>
<?php
		do_action( 'manage_posts_extra_tablenav', $which );
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

		$current_url = set_url_scheme( esc_url('http://' . esc_url_raw($_SERVER['HTTP_HOST']). esc_url_raw($_SERVER['REQUEST_URI']) ));
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
		$can_edit_post = current_user_can( 'edit_post', $post->ID) || $simple_link;

		echo "<strong>";

		$title = _draft_or_post_title($post);

		if ( $can_edit_post && $post->post_status != 'trash' && $edit_link = get_edit_post_link( $post->ID )) {
			printf(
				'<a class="row-title" href="%s" aria-label="%s">%s%s</a>',
				$edit_link,
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
			$request_url = add_query_arg($_REQUEST, rvy_admin_url('admin.php?page=revisionary-q'));

			$args = ['author' => get_the_author_meta( 'ID' )];
			echo $this->apply_edit_link( add_query_arg('author', $args['author'], $request_url), get_the_author() );
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
			if ($edit_link = get_edit_post_link( $post->ID )) {
				$actions['edit'] = sprintf(
					'<a href="%1$s" title="%2$s" aria-label="%2$s">%3$s</a>',
					get_edit_post_link( $post->ID ),
					/* translators: %s: post title */
					esc_attr('Edit Revision'),
					__( 'Edit' )
				);
			}
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ($delete_link = get_delete_post_link( $post->ID, '', true )) {
				$actions['delete'] = sprintf(
					'<a href="%1$s" class="submitdelete" title="%2$s" aria-label="%2$s">%3$s</a>',
					$delete_link,
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Delete Revision', 'revisionary' ), $title ) ),
					__( 'Delete' )
				);
			}
		}

		if ( is_post_type_viewable( $post_type_object ) ) {
			if ($can_read_post && $post_type_object && !empty($post_type_object->public)) {
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

		$actions = apply_filters('revisionary_queue_row_actions', $actions, $post);


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

		$_SERVER['REQUEST_URI'] = str_replace('#038;', '&', esc_url_raw($_SERVER['REQUEST_URI']));
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
