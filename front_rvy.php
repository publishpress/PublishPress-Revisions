<?php

class RevisionaryFront {
	function __construct() {
		global $revisionary;

		if ( ! defined('RVY_CONTENT_ROLES') || !$revisionary->content_roles->is_direct_file_access() ) {
			add_filter('posts_request', [$this, 'flt_view_revision'] );
			add_action('template_redirect', [$this, 'act_template_redirect'], 5 );
		}

		if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
			add_filter('the_author', [$this, 'fltAuthor'], 20);
		}

		if (!empty($_REQUEST['_ppp'])) {
			add_action('template_redirect', [$this, 'actRevisionPreviewRedirect'], 1);
		}

		do_action('revisionary_front_init');
	}

	public function actRevisionPreviewRedirect() {
		if (!class_exists('DS_Public_Post_Preview')) {
			return;
		}

		if ($_post = get_post(rvy_detect_post_id())) {
			if (('revision' == $_post->post_type) && ('inherit' == $_post->post_status)) {
				if ($url = get_permalink(rvy_post_id($_post->ID))) {
					wp_redirect($url);
				}
			}
		}
	}

	public function fltAuthor($display_name) {
		if ($_post = get_post(rvy_detect_post_id())) {
			if (rvy_is_revision_status($_post->post_status)) {
				// we only need this workaround when multiple authors were not successfully stored
				if ($authors = get_multiple_authors($_post->ID, false)) {
					return $display_name;
				}

				if ($authors = get_multiple_authors(rvy_post_id($_post->ID), false)) {
					$author_displays = [];
					foreach($authors as $author) {
						$author_displays []= $author->display_name;
					}

					if (in_array($display_name,$author_displays)) {
						return $display_name;
					}

					return implode(', ', $author_displays);
				}
			}
		}

		return $display_name;
	}

	function flt_revision_preview_url($redirect_url, $requested_url) {
		remove_filter('redirect_canonical', array($this, 'flt_revision_preview_url'), 10, 2);
		return $requested_url;
	}
	
	function flt_view_revision($request) {
		//WP post/page preview passes this arg
		if ( ! empty( $_GET['preview_id'] ) ) {
			$published_post_id = (int) $_GET['preview_id'];
			
			remove_filter( 'posts_request', array( &$this, 'flt_view_revision' ) ); // no infinite recursion!

			if ( $preview = wp_get_post_autosave($published_post_id) )
				$request = str_replace( "ID = '$published_post_id'", "ID = '$preview->ID'", $request );
				
			add_filter( 'posts_request', array( &$this, 'flt_view_revision' ) );

		} else {
			$revision_id = (isset($_REQUEST['page_id'])) ? (int) $_REQUEST['page_id'] : 0;

			if (!$revision_id) {
				$revision_id = rvy_detect_post_id();
			}

			if (!$revision = wp_get_post_revision($revision_id)) {
				return $request;
			}

			// rvy_list_post_revisions passes these args
			if($revision && ('revision' == $revision->post_type)) {
				if ($pub_post = get_post($revision->post_parent)) {
					if ( $type_obj = get_post_type_object( $pub_post->post_type ) ) {
						if (current_user_can('read_post', $revision_id ) || current_user_can('edit_post', $revision_id)) {
							$request = str_replace( "post_type = 'post'", "post_type = 'revision'", $request );
							$request = str_replace( "post_type = '{$pub_post->post_type}'", "post_type = 'revision'", $request );
						}
					}
				}
			}
		}

		return $request;
	}

	// allows for front-end viewing of a revision by those who can edit the current revision (also needed for post preview by users editing for pending revision)
	function act_template_redirect() {
		if ( is_admin() ) {
			return;
		}

		global $wp_query, $revisionary;
		if ($wp_query->is_404) {
			return;
		}

		if (!empty($_REQUEST['page_id'])) {
			$revision_id = (int) $_REQUEST['page_id'];
		} elseif (!empty($_REQUEST['p'])) {
			$revision_id = (int) $_REQUEST['p'];
		} else {
			global $post;
			if ($post) {
				$revision_id = $post->ID;
			}
		}

		do_action('revisionary_front', $revision_id);

		global $wpdb;
		
		if (!$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->posts WHERE ID = %d",
				$revision_id
			))
		) {
			if (!$post = wp_get_post_revision($revision_id)) {
				return;
			}
		}

		if (rvy_is_revision_status($post->post_status) || ('revision' == $post->post_type) || (!empty($_REQUEST['mark_current_revision']))) {
			add_filter('redirect_canonical', array($this, 'flt_revision_preview_url'), 10, 2);
			
			$published_post_id = rvy_post_id($revision_id);

			do_action('revisionary_preview_load', $revision_id, $published_post_id);

			if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION') && !defined('REVISIONARY_DISABLE_MA_PREVIEW_CORRECTION') && rvy_is_revision_status($post->post_status)) {
				$_authors = get_multiple_authors($revision_id);
			
				if (count($_authors) == 1) {
					$_author = reset($_authors);

					if ($_author && empty($_author->ID)) { // @todo: is this still necessary?
						$_author = MultipleAuthors\Classes\Objects\Author::get_by_term_id($_author->term_id);
					}
				}

				// If revision does not have valid multiple authors stored, correct to published post values
				if (empty($_authors) || (!empty($_author) && $_author->ID == $post->post_author)) {
					if (!$published_authors = wp_get_object_terms($published_post_id, 'author')) {
						if ($published_post = get_post($published_post_id)) {
							if ($author = MultipleAuthors\Classes\Objects\Author::get_by_user_id((int) $published_post->post_author)) {
								$published_authors = [$author];
							}
						}
					}

					rvy_set_ma_post_authors($revision_id, $published_authors);
				}
			}

			$datef = __awp( 'M j, Y @ g:i a' );
			$date = agp_date_i18n( $datef, strtotime( $post->post_date ) );

			$color = '#ccc';
			$class = '';
			$message = '';

			// This topbar is presently only for those with restore / approve / publish rights
			$type_obj = get_post_type_object( $post->post_type );

			$orig_skip = ! empty( $revisionary->skip_revision_allowance );
			$revisionary->skip_revision_allowance = true;

			$can_publish = agp_user_can('edit_post', $published_post_id, '', ['skip_revision_allowance' => true]);

			$redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect=" . esc_url($_REQUEST['rvy_redirect']) : '';

			load_plugin_textdomain('revisionary', false, dirname(plugin_basename(REVISIONARY_FILE)) . '/languages');
			
			$published_url = ($published_post_id) ? get_permalink($published_post_id) : '';
			$diff_url = admin_url("revision.php?revision=$revision_id");
			
			if ((!rvy_get_option('revisor_hide_others_revisions') && !empty($type_obj) && current_user_can($type_obj->cap->edit_posts)) || current_user_can('read_post', $revision_id)) { 
				$view_published = ($published_url) 
				? sprintf(
					apply_filters(
						'revisionary_preview_compare_view_caption', 
						__("%sCompare%s%sView&nbsp;Published&nbsp;Post%s", 'revisionary'),
						$post // revision
					),
					"<span><a href='$diff_url' class='rvy_preview_linkspan' target='_revision_diff'>",
					'</a></span>',
					"<span><a href='$published_url' class='rvy_preview_linkspan'>",
					'</a></span>'
					)
				: '';
			} else { // @todo
				$view_published = ($published_url) 
				? sprintf(
					apply_filters(
						'revisionary_preview_view_caption',
						__("%sView&nbsp;Published&nbsp;Post%s", 'revisionary'), 
						$post // revision
					),
					"<span><a href='$published_url' class='rvy_preview_linkspan'>",
					"</a></span>"
					) 
				: '';
			}

			if (agp_user_can('edit_post', $revision_id)) {
				$edit_url = admin_url("post.php?action=edit&amp;post=$revision_id");
				$edit_button = "<span><a href='$edit_url' class='rvy_preview_linkspan'>" . __('Edit', 'revisionary') . '</a></span>';
			} else {
				$edit_button = '';
			}

			if ($can_edit = agp_user_can('edit_post', rvy_post_id($revision_id), 0, ['skip_revision_allowance' => true])) {
				if ( in_array( $post->post_status, array( 'pending-revision' ) ) ) {
					$publish_url = wp_nonce_url( admin_url("admin.php?page=rvy-revisions&amp;revision=$revision_id&amp;action=approve$redirect_arg"), "approve-post_$published_post_id|$revision_id" );
				
				} elseif ( in_array( $post->post_status, array( 'future-revision' ) ) ) {
					$publish_url = wp_nonce_url( admin_url("admin.php?page=rvy-revisions&amp;revision=$revision_id&amp;action=publish$redirect_arg"), "publish-post_$published_post_id|$revision_id" );
				
				} elseif ( in_array( $post->post_status, array( 'inherit' ) ) ) {
					$publish_url = wp_nonce_url( admin_url("admin.php?page=rvy-revisions&amp;revision=$revision_id&amp;action=restore$redirect_arg"), "restore-post_$published_post_id|$revision_id" );
				
				} else {
					$publish_url = '';
				}
			} else {
				$publish_url = '';
			}

			if (('revision' == $post->post_type) && (get_post_field('post_modified_gmt', $post->post_parent) == get_post_meta($revision_id, '_rvy_published_gmt', true) && empty($_REQUEST['mark_current_revision']))
			) {
				if ($post = get_post($post->post_parent)) {
					if ('revision' != $post->post_type && !rvy_is_revision_status($post->post_status)) {
						$url = add_query_arg('mark_current_revision', 1, get_permalink($post->ID));
						wp_redirect($url);
						exit;
					}
				}
			} else {
				switch ( $post->post_status ) {
				case 'pending-revision' :
					$approve_caption = __( 'Approve', 'revisionary' );

					if ( strtotime( $post->post_date_gmt ) > agp_time_gmt() ) {
						$class = 'pending_future';
						$publish_button = ($can_publish) ? '<span><a href="' . $publish_url . '" class="rvy_preview_linkspan">' . $approve_caption . '</a></span>' : '';
						$message = sprintf( __('This is a Pending Revision (requested publish date: %s). %s %s %s', 'revisionary'), $date, $view_published, $edit_button, $publish_button );
					} else {
						$class = 'pending';
						$status_obj = get_post_status_object(get_post_field('post_status', rvy_post_id($revision_id)));
						$publish_caption = (!empty($status_obj->public) || !empty($status_obj->private)) ? __('Publish now', 'revisionary') : $approve_caption;
						$publish_button = ($can_publish) ? '<span><a href="' . $publish_url . '" class="rvy_preview_linkspan">' . $publish_caption . '</a></span>' : '';
						$message = sprintf( __('This is a Pending Revision. %s %s %s', 'revisionary'), $view_published, $edit_button, $publish_button );
					}
					break;
				
				case 'future-revision' :
					$class = 'future';

					// work around quirk of new scheduled revision preview not displaying page template and post thumbnail when accessed immediately after creation
					if (time() < strtotime($post->post_modified_gmt) + 15) {
						$current_url = set_url_scheme( esc_url('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) );
						$title = esc_attr(__('This revision is very new, preview may not be synchronized with theme.', 'revisionary'));
						$reload_link = " <a href='$current_url' title='$title'>" . __('Reload', 'revisionary') . '</a>';
					} else {
						$reload_link = '';
					}

					$edit_url = admin_url("post.php?action=edit&amp;post=$revision_id");
					$publish_button = ($can_publish) ? '<span><a href="' . $publish_url . '" class="rvy_preview_linkspan">' . __( 'Publish now', 'revisionary' ) . '</a></span>' : '';
					$publish_button .= $reload_link;
					$message = sprintf( __('This is a Scheduled Revision (for publication on %s). %s %s %s', 'revisionary'), $date, $view_published, $edit_button, $publish_button );
					break;

				case 'inherit' :
					if ( current_user_can('edit_post', $revision_id ) ) {
						$class = 'past';
						$date = agp_date_i18n( $datef, strtotime( $post->post_modified ) );
						$publish_button = ($can_publish) ? '<span><a href="' . $publish_url . '" class="rvy_preview_linkspan">' . __( 'Restore', 'revisionary' ) . '</a></span>' : '';
						$message = sprintf( __('This is a Past Revision (from %s). %s %s', 'revisionary'), $date, $view_published, $publish_button );
					}
					break;

				default:
					if (!empty($_REQUEST['mark_current_revision'])) {
						$class = 'published';
						
						if (!$can_edit) {
							$edit_button = '';
						}
						
						$message = sprintf( __('This is the Current Revision. %s', 'revisionary'), $edit_button );
					} else {
						return;
					}
				}

				add_action('wp_head', [$this, 'rvyFrontCSS']);

				add_action('wp_enqueue_scripts', [$this, 'rvyEnqueuePreviewJS']);

				if (!defined('REVISIONARY_PREVIEW_BAR_RELATIVE')) {
					add_action('wp_print_footer_scripts', [$this, 'rvyPreviewJS'], 50);
				}

				$html = '<div class="rvy_view_revision rvy_view_' . $class . '">' .
						'<span class="rvy_preview_msgspan">' . $message . '</span>';

				$html .= '</div>';

				new RvyScheduledHtml( $html, 'wp_head', 99 );  // this should be inserted at the top of <body> instead, but currently no way to do it 
			}
			
			$revisionary->skip_revision_allowance = $orig_skip;
		}
	}

	function rvyFrontCSS() {
		echo '<link rel="stylesheet" href="' . plugins_url('', REVISIONARY_FILE) . '/revisionary-front.css" type="text/css" />'."\n";
	}
	
	function rvyEnqueuePreviewJS() {
		wp_enqueue_script('jquery');
	}

	function rvyPreviewJS() {
		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		jQuery(document).ready( function($) {
			var rvyAdminBarMenuZindex = 100001;

			if ($('#wpadminbar').length) {
				var rvyAdminBarHeight = $('#wpadminbar').height();
				var barZ = parseInt($('#wpadminbar').css('z-index'));
				if (barZ > rvyAdminBarMenuZindex) {
					rvyAdminBarMenuZindex = barZ;
				}
			} else {
				var rvyAdminBarHeight = 0; 
			}

			$('div.rvy_view_revision').css('position', 'fixed').css('top', '32px');

			var rvyTotalHeight = $('div.rvy_view_revision').height() + rvyAdminBarHeight;
			var rvyTopBarZindex = $('div.rvy_view_revision').css('z-index');
			var rvyOtherElemZindex = 0;

			$('div.rvy_view_revision').css('top', rvyAdminBarHeight);

			$('body').css('padding-top', $('div.rvy_view_revision').height());

			$('header,div').each(function(i,e) { 
				if ($(this).css('position') == 'fixed' && ($(this).attr('id') != 'wpadminbar') && (!$(this).hasClass('rvy_view_revision'))) {
					if ($(this).position().top < rvyTotalHeight ) {
						rvyOtherElemZindex = parseInt($(this).css('z-index'));

						if (rvyOtherElemZindex >= rvyAdminBarMenuZindex) {
							rvyOtherElemZindex = rvyAdminBarMenuZindex - 1;
						}

						if (rvyOtherElemZindex >= rvyTopBarZindex) {
							rvyTopBarZindex = rvyOtherElemZindex + 1;
							$('div.rvy_view_revision').css('z-index', rvyTopBarZindex);
						}

						$(this).css('padding-top', rvyTotalHeight.toString() + 'px');

						return false;
					}
				}
			});
		});
		/* ]]> */
		</script>
		<?php
	}
}

class RvyScheduledHtml {
	private $html;
	private $action;
	private $priority;

	function __construct( $html, $action, $priority = 10 ) {
		$this->html = $html;
		$this->action = $action;
		$this->priority = $priority;

		add_action( $action, array( $this, 'echo_html' ), $priority );
	}

	function echo_html() {
		echo $this->html;
		remove_action( $this->action, array( $this, 'echo_html' ), $this->priority );
	}
}
