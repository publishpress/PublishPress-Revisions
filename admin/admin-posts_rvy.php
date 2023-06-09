<?php
class RevisionaryAdminPosts {
    private $post_revision_count = array();
	private $trashed_revisions;

    function __construct() {
        if ( ! empty( $_REQUEST['revision_action'] ) ) {
            add_action( 'all_admin_notices', [$this, 'revision_action_notice']);
        }

        add_action('admin_enqueue_scripts', [$this, 'fltAdminPostsListing'], 50);  // 'the_posts' filter is not applied on edit.php for hierarchical types

        add_filter('display_post_states', [$this, 'flt_display_post_states'], 50, 2);
		add_filter('page_row_actions', [$this, 'revisions_row_action_link']);
		add_filter('post_row_actions', [$this, 'revisions_row_action_link']);

		if (!empty($_REQUEST['post_status']) && ('trash' == sanitize_key($_REQUEST['post_status']))) {
			add_filter('display_post_states', [$this, 'fltTrashedPostState'], 20, 2 );
			add_filter('get_comments_number', [$this, 'fltCommentsNumber'], 20, 2);
		}

		// If a revision was just deleted from within post editor, redirect to Revision Queue
		if (!empty($_REQUEST['trashed']) && !empty($_REQUEST['post_type']) && !empty($_REQUEST['ids']) && is_scalar($_REQUEST['ids'])) {
			$post_type = sanitize_key($_REQUEST['post_type']);

			if (in_array($post_type, rvy_get_manageable_types())) {
				$deleted_id = (int) $_REQUEST['ids'];

				if (!empty($_SERVER['HTTP_REFERER']) && (
					(false !== strpos(esc_url_raw($_SERVER['HTTP_REFERER']), admin_url("post.php?post={$deleted_id}&action=edit")))
					|| (false !== strpos(esc_url_raw($_SERVER['HTTP_REFERER']), admin_url("post-new.php")))
				)) {
					$_post = get_post($deleted_id);

					if (!$_post || (('trash' == $_post->post_status) && in_array($_post->post_mime_type, rvy_revision_statuses()))) {
						if (apply_filters('revisionary_deletion_redirect_to_queue', true, $deleted_id, $post_type)) {
							$url = admin_url("admin.php?page=revisionary-q&pp_revisions_deleted={$deleted_id}");
							
							if (!empty($_SERVER['REQUEST_URI']) && false === strpos(esc_url_raw($_SERVER['REQUEST_URI']), $url)) {
								wp_redirect($url);
								exit;
							}
						}
					}
				}
			}
		}

		add_filter('query', [$this, 'fltPostCountQuery']);
    }
    
    function revision_action_notice() {
		if ( ! empty($_GET['restored_post'] ) ) {
			?>
			<div class='updated'><?php esc_html_e('The revision was restored.', 'revisionary');?>
			</div>
			<?php
		} elseif ( ! empty($_GET['scheduled'] ) ) {
			?>
			<div class='updated'><?php esc_html_e('The revision was scheduled for publication.', 'revisionary');?>
			</div>
			<?php
		} elseif ( ! empty($_GET['published_post'] ) ) {
			?>
			<div class='updated'><?php esc_html_e('The revision was published.', 'revisionary');?>
			</div>
			<?php	
    	}
    }
    
    public function fltAdminPostsListing() {
		global $wpdb, $wp_query, $typenow;

		$listed_ids = array();

		if (!empty($typenow) && !rvy_is_supported_post_type($typenow)) {
            return;
        }

		if ( ! empty( $wp_query->posts ) ) {
			foreach ($wp_query->posts as $row) {
				$listed_ids[] = $row->ID;
			}	
		}

		if ($listed_ids) {
			$id_csv = implode("','", array_map('intval', $listed_ids));
			$revision_status_csv = implode("','", array_map('sanitize_key', rvy_revision_statuses()));

			$revision_base_statuses = array_map('sanitize_key', rvy_revision_base_statuses());
			$revision_base_status_csv = implode("','", $revision_base_statuses);

			$results = $wpdb->get_results(
				"SELECT comment_count AS published_post, COUNT(comment_count) AS num_revisions FROM $wpdb->posts WHERE comment_count IN ('$id_csv') AND post_status IN ('$revision_base_status_csv') AND post_mime_type IN ('$revision_status_csv') AND post_type != '' GROUP BY comment_count"
			);
			
			foreach($results as $row) {
				$this->post_revision_count[$row->published_post] = $row->num_revisions;
			}
		}
    }
    
    private function logTrashedRevisions() {
		global $wpdb, $wp_query;
		
		if (!empty($wp_query) && !empty($wp_query->posts)) {
			$listed_ids = [];
			
			foreach($wp_query->posts AS $row) {
				$listed_ids []= $row->ID;
			}

			$listed_post_csv = implode("','", array_map('intval', $listed_ids));
			$this->trashed_revisions = $wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_rvy_base_post_id' AND post_id IN ('$listed_post_csv')");
		} else {
			$this->trashed_revisions = [];
		}
	}

	/**
	 * Adds "Pending Revision" or "Scheduled Revision" to the list of display states for trashed revisions in the Posts list table.
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 * @return array Filtered array of post display states.
	 */
	public function fltTrashedPostState($post_states, $post) {
		if (!$post->comment_count) { // revisions buffer base post id to comment_count column for perf
			return $post_states;
		}

		if (!isset($this->trashed_revisions)) {
			$this->logTrashedRevisions();
		}

		if (in_array($post->ID, $this->trashed_revisions)) {		
			if ($status_obj = get_post_status_object($post->post_mime_type)) {
				$post_states['rvy_revision'] = $status_obj->label;
			}

			if (!isset($post_states['rvy_revision'])) {
				$post_states['rvy_revision'] = esc_html__('Revision', 'revisionary');
			}
		}

		return $post_states;
	}

	function fltCommentsNumber($comment_count, $post_id) {
		if (isset($this->trashed_revisions) && in_array($post_id, $this->trashed_revisions)) {
			$comment_count = 0;
		}

		return $comment_count;
	}

	function flt_display_post_states($post_states, $post) {
		if (!empty($this->post_revision_count[$post->ID]) && !defined('REVISIONARY_SUPPRESS_POST_STATE_DISPLAY')) {
			$post_states []= esc_html__('Has Revision', 'revisionary');
		}

		return $post_states;
	}

	function revisions_row_action_link($actions = array()) {
		global $post;

		if (!empty($post) && !rvy_is_supported_post_type($post->post_type)) {
            return $actions;
        }

		if (!empty($this->post_revision_count[$post->ID])) {
			if ( 'trash' != $post->post_status && wp_check_post_lock( $post->ID ) === false ) {
				$actions['revision_queue'] = "<a href='admin.php?page=revisionary-q&published_post={$post->ID}&all=1'>" . esc_html__('Revision Queue', 'revisionary') . '</a>';
			}
		}
		
		$status_obj = get_post_status_object($post->post_status);

		if (empty($actions['view'])) {

		}

		if (!empty($status_obj->public) || !empty($status_obj->private) || rvy_get_option('pending_revision_unpublished')) {
			if (rvy_get_option('pending_revisions') && current_user_can('copy_post', $post->ID) && rvy_post_revision_supported($post)) {
				$redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect=" . esc_url_raw($_REQUEST['rvy_redirect']) : '';
				$url = rvy_admin_url("admin.php?page=rvy-revisions&amp;post={$post->ID}&amp;action=revise$redirect_arg");
				
				$caption = (isset($actions['edit']) || !rvy_get_option('caption_copy_as_edit')) ? pp_revisions_status_label('draft-revision', 'submit') : esc_html__('Edit');

				$caption = str_replace(' ', '&nbsp;', $caption);

				$actions['create_revision'] = "<a href='$url'>" . $caption . '</a>';
			}
		}

		return $actions;
	}

	public function fltPostCountQuery($query)
    {
        global $wpdb;

        $posts = $wpdb->posts;

		$matches = [];

        // wp_count_posts() :
        // SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s

        /*
        SELECT COUNT( 1 )
			FROM $wpdb->posts
			WHERE post_type = %s
			AND post_status NOT IN ( '" . implode( "','", $exclude_states ) . "' )
			AND post_author = %d
        */

        $pos_from = strpos($query, "FROM $posts");
		$pos_where = strpos($query, "WHERE ");
        
        // todo: use 'wp_count_posts' filter instead?

        if ((strpos($query, "ELECT post_status, COUNT( * ) AS num_posts ") || (strpos($query, "ELECT COUNT( 1 )") && $pos_from && (!$pos_where || ($pos_from < $pos_where)))) 
        && preg_match("/FROM\s*{$posts}\s*WHERE post_type\s*=\s*'([^ ]+)'/", $query, $matches)
        ) {
            $_post_type = (!empty($matches[1])) ? $matches[1] : PWP::findPostType();

            if ($_post_type) {
				$revision_status_csv = implode("','", array_map('sanitize_key', rvy_revision_statuses()));
				
				if (!function_exists('presspermit')) {
					// avoid counting posts stored with a status that's no longer registered
					$statuses = get_post_stati();
					$statuses_clause = " AND post_status IN ('" 
											. implode("','", array_map('sanitize_key', $statuses)) 
										. "')";
				} else {
					$statuses_clause = '';
				}

				if (!strpos($query, "AND post_mime_type NOT IN ('$revision_status_csv')")) {
					$query = str_replace(
						" post_type = '{$matches[1]}'", 
						"( post_type = '{$matches[1]}' AND post_mime_type NOT IN ('$revision_status_csv'){$statuses_clause} )", 
						$query
					);
				}
			}
		}

		return $query;
	}
}
