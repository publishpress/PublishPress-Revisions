<?php

class RevisionaryFront {
	function __construct() {
		if ( ! defined('RVY_CONTENT_ROLES') || ! $GLOBALS['revisionary']->content_roles->is_direct_file_access() ) {
			add_filter( 'posts_request', array( &$this, 'flt_view_revision' ) );
			add_action('template_redirect', array( &$this, 'act_template_redirect' ), 5 );
		}

		if (defined('PUBLISHPRESS_MULTIPLE_AUTHORS_VERSION')) {
			add_filter('the_author', [$this, 'fltAuthor'], 20);
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
			$published_post_id = $_GET['preview_id'];
			
			remove_filter( 'posts_request', array( &$this, 'flt_view_revision' ) ); // no infinite recursion!

			if ( $preview = wp_get_post_autosave($published_post_id) )
				$request = str_replace( "ID = '$published_post_id'", "ID = '$preview->ID'", $request );
				
			add_filter( 'posts_request', array( &$this, 'flt_view_revision' ) );

		} else {
			$revision_id = (isset($_REQUEST['page_id'])) ? $_REQUEST['page_id'] : 0;

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
		
		global $wp_query;
		if ($wp_query->is_404) {
			return;
		}

		if (!empty($_REQUEST['page_id'])) {
			$revision_id = $_REQUEST['page_id'];
		} elseif (!empty($_REQUEST['p'])) {
			$revision_id = $_REQUEST['p'];
		} else {
			global $post;
			if ($post) {
				$revision_id = $post->ID;
			}
		}

		do_action('revisionary_front', $revision_id);

		if( !$post = get_post($revision_id)) {
			if (!$post = wp_get_post_revision($revision_id)) {
				return;
			}
		}

		if (rvy_is_revision_status($post->post_status) || ('revision' == $post->post_type) || (!empty($_REQUEST['mark_current_revision']))) {
			add_filter('redirect_canonical', array($this, 'flt_revision_preview_url'), 10, 2);
			
			$published_post_id = rvy_post_id($revision_id);	

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
			if ( $type_obj = get_post_type_object( $post->post_type ) ) {
				$cap_name = $type_obj->cap->edit_post;	
			}

			$orig_skip = ! empty( $GLOBALS['revisionary']->skip_revision_allowance );
			$GLOBALS['revisionary']->skip_revision_allowance = true;

			$can_publish = agp_user_can( $cap_name, $published_post_id, '', array( 'skip_revision_allowance' => true ) );

			$redirect_arg = ( ! empty($_REQUEST['rvy_redirect']) ) ? "&rvy_redirect={$_REQUEST['rvy_redirect']}" : '';

			load_plugin_textdomain('revisionary', false, RVY_FOLDER . '/languages');
			
			$published_url = ($published_post_id) ? get_permalink($published_post_id) : '';
			$diff_url = admin_url("revision.php?revision=$revision_id");
			
			if (current_user_can( 'read_post', $revision_id)) { 
				$view_published = ($published_url) 
				? sprintf(
					__("%sCompare%s%sView&nbsp;Published&nbsp;Post%s", 'revisionary'),
					"<span><a href='$diff_url' class='rvy_preview_linkspan' target='_revision_diff'>",
					'</a></span>',
					"<span><a href='$published_url' class='rvy_preview_linkspan'>",
					'</a></span>'
					)
				: '';
			} else { // @todo
				$view_published = ($published_url) 
				? sprintf(
					__("%sView&nbsp;Published&nbsp;Post%s", 'revisionary'), 
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
					if ( strtotime( $post->post_date_gmt ) > agp_time_gmt() ) {
						$class = 'pending_future';
						$publish_button = ($can_publish) ? '<span><a href="' . $publish_url . '" class="rvy_preview_linkspan">' . __( 'Approve', 'revisionary' ) . '</a></span>' : '';
						$message = sprintf( __('This is a Pending Revision (requested publish date: %s). %s %s %s', 'revisionary'), $date, $view_published, $edit_button, $publish_button );
					} else {
						$class = 'pending';
						$publish_button = ($can_publish) ? '<span><a href="' . $publish_url . '" class="rvy_preview_linkspan">' . __( 'Publish now', 'revisionary' ) . '</a></span>' : '';
						$message = sprintf( __('This is a Pending Revision. %s %s %s', 'revisionary'), $view_published, $edit_button, $publish_button );
					}
					break;
				
				case 'future-revision' :
					$class = 'future';

					// work around quirk of new scheduled revision preview not displaying page template and post thumbnail when accessed immediately after creation
					if (time() < strtotime($post->post_modified_gmt) + 15) {
						$current_url = set_url_scheme( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
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
			
			$GLOBALS['revisionary']->skip_revision_allowance = $orig_skip;
		}
	}

	function rvyFrontCSS() {
		$wp_content = ( is_ssl() || ( is_admin() && defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN ) ) ? str_replace( 'http:', 'https:', WP_CONTENT_URL ) : WP_CONTENT_URL;
		$path = $wp_content . '/plugins/' . RVY_FOLDER;
		
		echo '<link rel="stylesheet" href="' . $path . '/revisionary-front.css" type="text/css" />'."\n";
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
	var $html;
	var $action;
	var $priority;

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
