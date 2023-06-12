<?php

// JReviews plugin breaks Pending Revision / Scheduled Revision preview
function _rvy_jreviews_preview_compat() {
    global $JReviews;

    if (empty($JReviews)) {
        return;
    }

    remove_action('admin_footer',                  [$JReviews, 'assets']);

    remove_action('admin_init',                    [$JReviews, 'admin_init']);

    remove_action('admin_menu',                    [$JReviews, 'admin_menu']);

    remove_action('save_post',                     [$JReviews, 'admin_save_menu']);

    remove_filter('admin_head',                    [$JReviews, 'admin_head']);

    // Using priority 100 to force select2 to load after nello content ncselect2 script which causes a conflict
    remove_action('admin_enqueue_scripts',         [$JReviews, 'admin_enqueue'], 100);

    // Display the User ID column in the User Manager in WP

    remove_filter('manage_users_columns',          [$JReviews, 'add_user_id_column']);

    remove_action('manage_users_custom_column',    [$JReviews, 'show_user_id_column_content'], 10, 3);

    /**
    * Site functions
    */
    remove_action('wp_enqueue_scripts',            [$JReviews,'assets']);

    remove_action('wp_footer',                     [$JReviews,'assets']);

    // Ajax

    remove_action('wp_ajax_jreviews_ajax',         [$JReviews, 'ajax']);

    remove_action('wp_ajax_nopriv_jreviews_ajax',  [$JReviews, 'ajax']);

    // WP System functions

    remove_action('init',                          [$JReviews, 'init']);

    remove_action('wp_login',                      [$JReviews, 'endSession']);

    remove_action('wp_logout',                     [$JReviews, 'endSession']);

    remove_action('wp_loaded',                     [$JReviews, 'wp_loaded']);

    remove_action('get_header',                    [$JReviews, 'get_header']);

    remove_action('wp_head',                       [$JReviews, 'wp_head']);

    // Routing functions

    remove_filter('rewrite_rules_array',           [$JReviews, 'rewrite_rules_array']);

    remove_filter('query_vars',                    [$JReviews, 'query_vars']);

    // Template functions

    remove_filter('template_include',              [$JReviews, 'template_include']);

    // SEO functions
    remove_filter('document_title_parts',          [$JReviews, 'page_title_parts'], 20);

    remove_filter('pre_get_document_title',        [$JReviews, 'page_title_override'], 20);

    remove_filter('wp_title',                      [$JReviews, 'page_title'], 20, 3);

    // Widgets

    remove_action('widgets_init',                  [$JReviews, 'widgets_init']);

    // Load scripts with defer attribute

    remove_filter('script_loader_tag',            [$JReviews, 'defer_js_async'], 10, 2 );

    // Stop WP core category queries from running on JReviews pages

    remove_filter('posts_pre_query',              [$JReviews, 'posts_pre_query_filter'], 10, 2);
}