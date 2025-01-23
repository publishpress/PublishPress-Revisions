<?php
namespace PublishPress\Revisions;

class Planner {
	private $show_revisions;
	private $usermeta_key;

    function __construct() {
		global $pagenow;

		if (is_admin() && defined('DOING_AJAX') && DOING_AJAX) {
			add_filter('preview_post_link', 
				function($preview_link, $post) {
					if (did_action('wp_ajax_publishpress_calendar_get_data') && rvy_in_revision_workflow($post)) {
						$preview_link = rvy_preview_url($post);
					}

					return $preview_link;
				},
				10, 2
			);

			add_filter('get_delete_post_link', 
				function ($delete_link, $post_id, $force_delete) {
					if (did_action('wp_ajax_publishpress_calendar_get_data') && rvy_in_revision_workflow($post_id)) {
						if ($post_type_object = get_post_type_object(get_post_field('post_type', $post_id))) {
							$delete_link = add_query_arg( 'action', 'delete', admin_url( sprintf( $post_type_object->_edit_link, $post_id ) ) );
							$delete_link = wp_nonce_url( $delete_link, "delete-post_{$post_id}" );
						}
					}

					return $delete_link;
				},
				10, 3
			);

		} elseif (is_admin() && !empty($pagenow) && ('admin.php' == $pagenow) && !empty($_REQUEST['page'])) {
			$plugin_page = sanitize_key($_REQUEST['page']);
			
			$this->usermeta_key = 'pp_calendar_filters'; // default
			
			if ('pp-calendar' == $plugin_page) {
				require_once(dirname(__FILE__).'/PlannerCalendar.php');
				new \PublishPress\Revisions\PlannerCalendar();

			} elseif ('pp-content-board' == $plugin_page) {
				$this->usermeta_key = 'PP_Content_Board_filters';
				
				require_once(dirname(__FILE__).'/PlannerContentBoard.php');
				new \PublishPress\Revisions\PlannerContentBoard();

			} elseif ('pp-content-overview' == $plugin_page) {
				$this->usermeta_key = 'PP_Content_Overview_filters';

				add_action('init', function() {
					if ($this->showingRevisions()) {
						add_filter('PP_Content_Overview_item_actions',
							function($actions, $post_id) {
								// @todo: support revision trashing
								if (rvy_in_revision_workflow($post_id)) {
									$actions['trash'] = '<a class="submitdelete" href="' . esc_url(get_delete_post_link($post_id, false, true)) . '">' . esc_html__('Delete') . '</a>';
								}

								return $actions;
							},
							10, 2
						);
					}
				});

				if (!defined('PUBLISHPRESS_STATUSES_VERSION')) {
					// Work around plugin API bug
					add_filter('publishpress_content_overview_column_value', 
						function($col_val, $post, $module_url) {
							global $pp_content_overview_last_col;

							$pp_content_overview_last_col = $col_val;

							return $col_val;
						},
						9, 3
					);

					add_filter('publishpress_content_overview_column_value', 
						function($col_val, $post, $module_url) {
							global $pp_content_overview_last_col;

							if (('post_status' == $pp_content_overview_last_col) && rvy_in_revision_workflow($post)) {
								if ($status_obj = get_post_status_object($post->post_status)) {
									$col_val = $status_obj->label;
								}
							}

							return $col_val;
						},
						11, 3
					);
				}
			}

			if (in_array($plugin_page, ['pp-calendar', 'pp-content-board', 'pp-content-overview'])) {
				add_action('init', function() {
					if ($this->showingRevisions()) {
						add_filter('preview_post_link',
							function($preview_link, $post) {
								if (rvy_in_revision_workflow($post)) {
									$preview_link = rvy_preview_url($post);
								}

								return $preview_link;
							},
							10, 2
						);
					}
				});
				
				add_filter(
					'_presspermit_get_post_statuses', 
					function($post_statuses, $status_args, $return_args, $function_args) {
						if (!class_exists('PP_Revision_Integration')) {
							return $post_statuses;
						}
		
						$revision_statuses = rvy_revision_statuses();
					
						$permissions_compat_mode = rvy_get_option('permissions_compat_mode');
		
						foreach ($post_statuses as $k => $status) {
							$status_name = (is_object($status)) ? $status->name : $status;
		
							if ($this->showingRevisions()) {
								if ($permissions_compat_mode) {
									if (!in_array($status_name, $revision_statuses)) {
										unset($post_statuses[$k]);
									}
								}
							} else {
								if (in_array($status_name, $revision_statuses)) {
									unset($post_statuses[$k]);
								}
							}
						}
		
						return $post_statuses;
					}, 50, 4
				);
			}
		}

        add_filter('publishpress_calendar_post_statuses', 
			function($statuses) {
				global $pagenow;

				$revision_statuses = rvy_revision_statuses();

				$is_content_board = !empty($pagenow) && ('admin.php' == $pagenow) && !empty($_REQUEST['page']) && ('pp-content-board' == $_REQUEST['page']);

				$permissions_compat_mode = rvy_get_option('permissions_compat_mode');

				foreach ($statuses as $k => $status_obj) {
					if (!class_exists('PP_Revision_Integration')) {
						if (!empty($status_obj->slug) && in_array($status_obj->slug, $revision_statuses)) {
							unset($statuses[$k]);
						}

					} elseif ($this->showingRevisions()) {
						if (!empty($status_obj->slug) && !in_array($status_obj->slug, $revision_statuses)) {
							unset($statuses[$k]);
						}
					} else {
						if (!empty($status_obj->slug) && in_array($status_obj->slug, $revision_statuses)) {
							unset($statuses[$k]);
						}
					}
				}

				// Special provision with query modification for ready-only Content Board display of Revision Statuses if Permissions Compat mode is off
				//if ($is_content_board && $this->showingRevisions() && !$permissions_compat_mode) {
				if (!defined('PUBLISHPRESS_STATUSES_PRO_VERSION') && $this->showingRevisions() && !$permissions_compat_mode) {
					$statuses = rvy_revision_statuses(['output' => 'object']);

					foreach($statuses as $k => $status) {
						$statuses[$k]->slug = $status->name;
					}
				}

				return $statuses;
			}
		);

		add_filter(
			'publishpress_post_status_options', 
			function($status_options) {
				if (!class_exists('PP_Revision_Integration')) {
					return $status_options;
				}

				/*
				if (!rvy_get_option('permissions_compat_mode')) {
					$revision_statuses = rvy_revision_statuses();
					
					foreach ($status_options as $k => $opt) {
						if (isset($opt['value']) && in_array($opt['value'], $revision_statuses)) {
							unset($status_options[$k]);
						}
					}
				}
				*/

				return $status_options;
			}
		);

		add_filter(
			'PP_Content_Board_posts_query_args',
			function ($args) {
				add_filter('posts_fields', [$this, 'fltQueryFields'], 10, 2);
				add_filter('posts_where', [$this, 'fltFilterRevisions'], 10, 2);
				return $args;
			}
		);

		add_filter(
			'PP_Content_Overview_posts_query_args',
			function ($args) {
				add_filter('posts_fields', [$this, 'fltQueryFields'], 10, 2);
				add_filter('posts_where', [$this, 'fltFilterRevisions'], 10, 2);
				return $args;
			}
		);

		add_filter(
			'pp_calendar_posts_query_args',
			function ($args) {
				add_filter('posts_fields', [$this, 'fltQueryFields'], 10, 2);
				add_filter('posts_where', [$this, 'fltFilterRevisions'], 10, 2);
				return $args;
			}
		);
	}

	protected function showingRevisions() {
        global $current_user;

		if (!isset($this->show_revisions)) {
			if (!class_exists('PP_Revision_Integration')) {
				$this->show_revisions = false;

			} elseif (isset($_REQUEST['hide_revision'])) {
				$this->show_revisions = empty($_REQUEST['hide_revision']);
			} else {
				$user_filters = apply_filters(
					'pp_get_user_meta', 
					get_user_meta($current_user->ID, $this->usermeta_key, true), 
					$current_user->ID, 
					$this->usermeta_key, 
					true
				);

				$this->show_revisions = empty($user_filters['hide_revision']);
			}
		}

		return $this->show_revisions;
    }

	function fltQueryFields($fields, $wp_query) {
		global $wpdb;
		
		if (class_exists('PP_Revision_Integration') && $this->showingRevisions()) {
			$fields = "ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_mime_type AS post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count";
		}

		return $fields;
	}

	function fltFilterRevisions($where, $wp_query) {
		global $wpdb;
		
		$revision_statuses = rvy_revision_statuses();

		// Prevent inactive revisions from being displayed as normal posts if Statuses Pro was deactivated
		if (!defined('PUBLISHPRESS_STATUSES_PRO_VERSION') && !$this->showingRevisions()) {
			$revision_statuses = array_merge($revision_statuses, ['revision-deferred', 'revision-needs-work', 'revision-rejected']);
			
			if (!taxonomy_exists('pp_revision_status')) {
				register_taxonomy(
					'pp_revision_status',
					'post',
					[
						'hierarchical'          => false,
						'query_var'             => false,
						'rewrite'               => false,
						'show_ui'               => false,
					]
				);
			}
			
			static $stored_statuses;

			if (!isset($stored_statuses)) {
				$stored_statuses = get_terms('pp_revision_status', ['hide_empty' => false, 'return' => 'name']);
			}

			foreach ($stored_statuses as $status) {
				if (!in_array($status->slug, $revision_statuses)) {
					$revision_statuses[] = $status->slug;
				}
			}
		}

		$revision_status_csv = implode("','", array_map('sanitize_key', $revision_statuses));

		$where .= ($this->showingRevisions()) ? " AND post_mime_type IN ('$revision_status_csv')" : " AND post_mime_type NOT IN ('$revision_status_csv')";

		if (class_exists('PP_Revision_Integration') && $this->showingRevisions() && !rvy_get_option('permissions_compat_mode')) {
			$where = str_replace("(($wpdb->posts.post_status =", "(($wpdb->posts.post_mime_type =", $where);
		}

		remove_filter('posts_where', [$this, 'fltFilterRevisions'], 10, 2);

		return $where;
	}
}
