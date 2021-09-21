<?php
class RevisionaryDashboard {
	public function recentPostsQueryArgs($query_args) {
		//$query_args['post_status'] = array('future', 'future-revision');
		add_filter('posts_clauses_request', [&$this, 'fltDashboardQueryClauses']);

		return $query_args;
	}

	public function fltDashboardQueryClauses( $clauses ) {
		global $wpdb, $revisionary;

		$types = array_keys($revisionary->enabled_post_types);
		$post_types_csv = implode( "','", $types );
		$clauses['where'] = str_replace( "$wpdb->posts.post_type = 'post'", "$wpdb->posts.post_type IN ('$post_types_csv')", $clauses['where'] );

		remove_filter('posts_clauses_request', [&$this, 'fltDashboardQueryClauses']);
		return $clauses;
	}

	public static function glancePending() {
		return;

		// @todo: modify this function for post_mime_type schema
		
		/*
		if ( ( defined( 'SCOPER_VERSION' ) || defined( 'PP_VERSION' ) || defined( 'PPCE_VERSION' ) || defined( 'RVY_CONTENT_ROLES' ) ) && ! defined( 'USE_RVY_RIGHTNOW' ) )
			return;

		global $revisionary;

		foreach (array_keys($revisionary->enabled_post_types) as $post_type) {
			$cache_key = _count_posts_cache_key( $post_type );
			wp_cache_delete( $cache_key, 'counts' );

			if ( $num_posts = wp_count_posts( $post_type ) ) {   // @todo: PressPermit compat for count_posts filtering with revision statuses

				foreach( array( 'pending-revision', 'future-revision' ) as $status ) {
					if ( ! empty($num_posts->$status) ) {
						$post_type_obj = get_post_type_object($post_type);
						$status_obj = get_post_status_object($status);

						echo '<div class="rvy-glance-pending">';
				
						$num = number_format_i18n( $num_posts->$status );

						$status_label = str_replace('(%s)', '', reset($status_obj->label_count));

						if ( intval($num_posts->$status) <= 1 )
							$text = sprintf( __('%1$s %2$s Revision', 'revisionary'), $status_label, $post_type_obj->labels->singular_name);
						else
							$text = sprintf( __('%1$s %2$s Revisions', 'revisionary'), $status_label, $post_type_obj->labels->singular_name);
						
						$url = "admin.php?page=revisionary-q&post_status=$status&post_type=$post_type";

						if (current_user_can('administrator') || (isset($post_type_obj->cap->edit_published_posts) && current_user_can($post_type_obj->cap->edit_published_posts) && current_user_can($post_type_obj->cap->edit_others_posts))) {  // hide count from non-Admins until it is properly filtered
							echo "<a class='waiting' href='$url'><span class='pending-count'>$num</span> $text</a>";
						} else {
							echo "<a class='waiting' href='$url'>" . sprintf( __("View %s", 'revisionary'), $text) . "</a>";
						}
						
						echo "</div>";
					}
				}
			}
		}
		*/
	}
}
