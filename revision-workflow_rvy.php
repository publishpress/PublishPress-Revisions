<?php

class Rvy_Revision_Workflow_UI {
    function do_notifications( $notification_type, $status, $post_arr, $args ) {
        global $revisionary;
        
        if ( 'pending' != $notification_type ) {
            return;
        }

        $defaults = array( 'revision_id' => 0, 'published_post' => false, 'object_type' => '', 'selected_recipients' => array() );
        $args = array_merge( $defaults, $args );
        foreach( array_keys($defaults) as $var ) { $$var = $args[$var]; }

        if ( ! $published_post ) {
            return;
        }

        if ( $revisionary->doing_rest && $revisionary->rest->is_posts_request && ! empty( $revisionary->rest->request ) ) {
            $post_arr = array_merge( $revisionary->rest->request->get_params(), $post_arr );
        }

        $admin_notify = rvy_get_option( 'pending_rev_notify_admin' );
        $author_notify = rvy_get_option( 'pending_rev_notify_author' );
        if ( ( $admin_notify || $author_notify ) && $revision_id ) {
            $type_obj = get_post_type_object( $object_type );
            $type_caption = $type_obj->labels->singular_name;
            $post_arr['post_type'] = $published_post->post_type;
            
            $blogname = wp_specialchars_decode( get_option('blogname'), ENT_QUOTES );
            
            if ( $admin_notify ) {
                $title = sprintf( __('[%s] Pending Revision Notification', 'revisionary'), $blogname );
                
                $message = sprintf( __('A pending revision to the %1$s "%2$s" has been submitted.', 'revisionary'), $type_caption, $post_arr['post_title'] ) . "\r\n\r\n";
                

                if ( $author = new WP_User( $post_arr['post_author'] ) ) {
                    $message .= sprintf( __('It was submitted by %1$s.', 'revisionary' ), $author->display_name ) . "\r\n\r\n";
                }

                if ( $revision_id ) {
                    $preview_link = add_query_arg( array( 'preview' => '1', 'post_type' => 'revision' ), get_post_permalink( $revision_id ) );
                    $message .= __( 'Preview and Approval: ', 'revisionary' ) . $preview_link . "\r\n\r\n";

                    $message .= __( 'Management Screen: ', 'revisionary' ) . admin_url("admin.php?page=rvy-revisions&action=view&revision={$revision_id}") . "\r\n";
                }

                // establish the publisher recipients
                $recipient_ids = array();
                
                if ( defined('RVY_CONTENT_ROLES') && ! defined('SCOPER_DEFAULT_MONITOR_GROUPS') ) {
                    $monitor_groups_enabled = true;
                    $revisionary->content_roles->ensure_init();
                    
                    $recipient_ids = $revisionary->content_roles->get_metagroup_members( 'Pending Revision Monitors' );
                    
                    if ( $type_obj ) {
                        $revisionary->skip_revision_allowance = true;
                        $post_publisher_ids = $revisionary->content_roles->users_who_can( $type_obj->cap->edit_post, $published_post->ID, array( 'cols' => 'id', 'user_ids' => $recipient_ids ) );
                        $revisionary->skip_revision_allowance = false;
                        $recipient_ids = array_intersect( $recipient_ids, $post_publisher_ids );
                    }
                }

                if ( ! $recipient_ids && ( empty($monitor_groups_enabled) || ! defined('RVY_FORCE_MONITOR_GROUPS') ) ) {
                    require_once(ABSPATH . 'wp-admin/includes/user.php');
                    
                    if ( defined( 'SCOPER_MONITOR_ROLES' ) ) {
                        $use_wp_roles = SCOPER_MONITOR_ROLES;
                    } else {
                        $use_wp_roles = ( defined( 'RVY_MONITOR_ROLES' ) ) ? RVY_MONITOR_ROLES : 'administrator,editor';
                    }

                    $use_wp_roles = str_replace( ' ', '', $use_wp_roles );
                    $use_wp_roles = explode( ',', $use_wp_roles );
                    
                    foreach ( $use_wp_roles as $role_name ) {
                        $search = new WP_User_Query( "search=&fields=id&role=$role_name" );
                        $recipient_ids = array_merge( $recipient_ids, $search->results );
                    }
                    
                    if ( $recipient_ids && $type_obj ) {
                        foreach( $recipient_ids as $key => $user_id ) {
                            $_user = new WP_User($user_id);
                            $reqd_caps = map_meta_cap( $type_obj->cap->edit_post, $user_id, $published_post->ID );
                            
                            if ( array_diff( $reqd_caps, array_keys( array_intersect( $_user->allcaps, array( true, 1, '1' ) ) ) ) ) {
                                unset( $recipient_ids[$key] );
                            }
                        }
                    }
                }
                
                if ( ( 'always' != $admin_notify ) && $selected_recipients ) {
                    // intersect default recipients with selected recipients
                    $recipient_ids = array_intersect( $selected_recipients, $recipient_ids );
                }
                
                if ( defined( 'RVY_NOTIFY_SUPER_ADMIN' ) && is_multisite() ) {
                    $super_admin_logins = get_super_admins();
                    foreach( $super_admin_logins as $user_login ) {
                        if ( $super = new WP_User($user_login) ) {
                            $recipient_ids []= $super->ID;
                        }
                    }
                }
            }

            if ( $author_notify ) {
                if ( ( 'always' == $author_notify ) || ( $selected_recipients && in_array( $published_post->post_author, $selected_recipients ) ) ) {
                    $recipient_ids []= $published_post->post_author;
                }
            }

            if ( $recipient_ids ) {
                global $wpdb;
                $to_addresses = array_unique( $wpdb->get_col( "SELECT user_email FROM $wpdb->users WHERE ID IN ('" . implode( "','", $recipient_ids ) . "')" ) );
            } else {
                $to_addresses = array();
            }

            foreach ( $to_addresses as $address ) {
                rvy_mail($address, $title, $message);
            }
        }
    }

    function get_revision_msg( $revision, $args = array() ) {
        global $current_user;

        $defaults = array( 'post_id' => 0, 'post_arr' => array(), 'object_type' => '', 'future_date' => null );
        $args = array_merge( $defaults, (array) $args );
        foreach( array_keys($defaults) as $var ) { $$var = $args[$var]; }
        
        if ( ! $revision ) {
            $msg = __('Sorry, an error occurred while attempting to save your revision.', 'revisionary'); 
        } else {
            if ( is_scalar ( $revision ) ) {
                $revision = wp_get_post_revision( $revision );
            }

            if ( ! $post_arr ) {
                $post_arr = (array) $revision;
            }

            if ( ! $post_id ) {
                $post_id = $revision->post_parent;
            }

            if ( ! $object_type ) {
                if ( $post = get_post( $post_id ) ) {
                    $object_type = $post->post_type;
                }
            }

            if ( null === $future_date ) {
                $future_date = $post_arr && ! empty( $post_arr['post_date_gmt'] ) && strtotime($post_arr['post_date_gmt'] ) > agp_time_gmt();
            }

            $manage_link = $this->get_manage_link( $object_type );
            
            switch( $revision->post_status ) {
            case 'future':
                delete_post_meta( $post_id, "_new_scheduled_revision_{$current_user->ID}" );  // clear the flag which triggered a redirect from Gutenberg editor

                $msg = __('Your modification was saved as a Scheduled Revision.', 'revisionary') . ' ';
            
                $preview_link = add_query_arg( array( 'preview' => '1', 'post_type' => 'revision' ), get_post_permalink( $revision->ID ) );
			   
                $msg .= '<ul><li>';
                $msg .= sprintf( '<a href="%s">' . __( 'Preview it', 'revisionary' ) . '</a>', $preview_link );
                $msg .= '<br /><br /></li><li>';
                $msg .= sprintf( '<a href="%s">' . __('Go to Revisions Manager', 'revisionary') . '</a>', "admin.php?page=rvy-revisions&amp;revision={$revision->ID}&amp;action=view" );
                $msg .= '<br /><br /></li><li>';
                $msg .= sprintf( '<a href="%s">' . __('Go back to schedule another revision', 'revisionary') . '</a>', "javascript:history.back(1);" );
                $msg .= '<br /><br /></li><li>';
                $msg .= sprintf( '<a href="%s">' . $manage_link->caption . '</a>', admin_url($manage_link->uri) );
                $msg .= '</li></ul>';

                break;

            case 'pending':
            default:
                $msg = __('Your modification has been saved for editorial review.', 'revisionary') . ' <br /><br />';
                
                if ( $future_date ) {
                    $msg .= __('If approved by an editor, it will be published on the date you specified.', 'revisionary') . ' ';
                } else {
                    $msg .= __('It will be published when an editor approves it.', 'revisionary') . ' ';
                }

                $preview_link = add_query_arg( array( 'preview' => '1', 'post_type' => 'revision' ), get_post_permalink( $revision->ID ) );

                $msg .= '<ul><li>';
                $msg .= sprintf( '<a href="%s">' . __( 'Preview it', 'revisionary' ) . '</a>', $preview_link );
                $msg .= '<br /><br /></li><li>';
                $msg .= sprintf( '<a href="%s">' . __('Go to Revisions Manager', 'revisionary') . '</a>', "admin.php?page=rvy-revisions&amp;revision={$revision->ID}&amp;action=view" );
                $msg .= '<br /><br /></li><li>';
                        
                if ( $future_date ) {
                    $msg .= sprintf( '<a href="%s">' . __('Go back to submit another revision', 'revisionary') . '</a>', "javascript:history.back(1);" );
                    $msg .= '<br /><br /></li><li>';
                }

                $msg .= sprintf( '<a href="%s">' . $manage_link->caption . '</a>', admin_url($manage_link->uri) );
                $msg .= '</li></ul>';
            }
        }

        return $msg;
    }

    function get_manage_link( $post_type ) {
		$arr = (object) array();
		
		// maintaining these for back compat with existing translations
		if ( 'post' == $post_type ) {
			$arr->uri = 'edit.php';
			$arr->caption = __( 'Return to Edit Posts', 'revisionary' );
		} elseif ( 'page' == $post_type ) {
			$arr->uri = "edit.php?post_type=$post_type";
			$arr->caption = __( 'Return to Edit Pages', 'revisionary' );
		} else {
			$wp_post_type = get_post_type_object( $post_type );
			$arr->uri = "edit.php?post_type=$post_type";
			$arr->caption = sprintf( __( 'Return to Edit %s', 'revisionary' ), $wp_post_type->labels->name );
		}
		
		return $arr;
	}
}
