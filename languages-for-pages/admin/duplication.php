<?php

if (!defined('ABSPATH')) {
    exit;
}

// This file handles everything related to page duplication

// Add the duplicate link to the Pages list
add_filter('page_row_actions', 'lfp_duplicate_page_link', 10, 2);
function lfp_duplicate_page_link($actions, $page) {
    $actions['duplicate'] = '<a href="' . admin_url('admin.php?action=duplicate_page&page_id=' . $page->ID) . '">Duplicate This</a>';
    return $actions;
}

add_filter('post_row_actions', 'lfp_duplicate_post_link', 10, 2);
function lfp_duplicate_post_link($actions, $post) {
    $actions['duplicate'] = '<a href="' . admin_url('admin.php?action=duplicate_post&post_id=' . $post->ID) . '">Duplicate This</a>';
    return $actions;
}

// Add language filter dropdown to Pages
add_action('restrict_manage_posts', 'lfp_add_language_filter');
function lfp_add_language_filter() {
    global $typenow;
    
    // Only add filter to post types that have our column
    if ($typenow !== 'post' && $typenow !== 'page') {
        return;
    }
    
    // Get all available languages - replace with your method of getting languages
    // This example assumes you're storing languages as post meta
    global $wpdb;
    $languages = $wpdb->get_col(
        "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_custom_language' AND meta_value != '' ORDER BY meta_value ASC"
    );
    
    if (empty($languages)) {
        return;
    }
    
    // Get currently selected language from URL parameter
    $current_language = isset($_GET['language_filter']) ? sanitize_text_field($_GET['language_filter']) : '';
    
    // Output the dropdown
    ?>
    <select name="language_filter">
        <option value=""><?php _e('All Languages', 'your-text-domain'); ?></option>
        <?php foreach ($languages as $language) : ?>
            <option value="<?php echo esc_attr($language); ?>" <?php selected($current_language, $language); ?>>
                <?php echo esc_html($language); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

// Filter posts/pages by language
add_filter('parse_query', 'lfp_filter_posts_by_language');
function lfp_filter_posts_by_language($query) {
    global $pagenow, $typenow;
    
    // Only filter in admin list pages for our post types
    if (!is_admin() || $pagenow !== 'edit.php' || ($typenow !== 'post' && $typenow !== 'page')) {
        return $query;
    }
    
    // Check if our filter is set and not empty
    if (isset($_GET['language_filter']) && !empty($_GET['language_filter'])) {
        $language = sanitize_text_field($_GET['language_filter']);
        
        // Add meta query to filter by language
        $query->query_vars['meta_key'] = '_custom_language';
        $query->query_vars['meta_value'] = $language;
    }
    
    return $query;
}

// Handle the duplicate action
add_action('admin_action_duplicate_page', 'lfp_duplicate_page_action');
function lfp_duplicate_page_action() {
    if (!current_user_can('edit_pages')) {
        wp_die('You do not have sufficient permissions to duplicate this page.');
    }

    if (isset($_GET['page_id'])) {
        $page_id = intval($_GET['page_id']);
        $new_page_id = lfp_duplicate_page($page_id);

        if ($new_page_id) {
            wp_redirect(admin_url('post.php?action=edit&post=' . $new_page_id));
            exit;
        } else {
            wp_die('There was an error duplicating the page.');
        }
    } else {
        wp_die('No page ID specified.');
    }
}

// Function to duplicate a page
function lfp_duplicate_page($page_id) {
    $page = get_post($page_id);

    if (!$page) {
        return false;
    }

    global $wpdb;

    $table = $wpdb->prefix . 'posts';

    $now = current_time('mysql');

    $wpdb->insert(
        $table,
        array(
            'post_author'           => get_current_user_id(),
            'post_date'             => $now,
            'post_date_gmt'         => get_gmt_from_date($now),
            'post_content'          => $page->post_content,
            'post_title'            => $page->post_title,
            'post_excerpt'          => '',
            'post_status'           => 'draft',
            'comment_status'        => 'closed',
            'ping_status'           => 'closed',
            'post_password'         => '',
            'post_name'             => sanitize_title($page->post_title),
            'to_ping'               => '',
            'pinged'                => '',
            'post_modified'         => $now,
            'post_modified_gmt'     => get_gmt_from_date($now),
            'post_content_filtered' => '',
            'post_parent'           => $page->post_parent,
            'guid'                  => '', // Can be updated later with get_permalink($id)
            'menu_order'            => $page->menu_order,
            'post_type'             => 'page',
            'post_mime_type'        => '',
            'comment_count'         => 0
        )
    );

    $new_page_id = $wpdb->insert_id;


    if ($new_page_id) {
        $taxonomies = get_object_taxonomies($page->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($page_id, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_page_id, $terms, $taxonomy, false);
        }

        $meta = get_post_meta($page_id);
        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($new_page_id, $key, maybe_unserialize($value));
            }
        }

        // Add custom language meta field
        add_post_meta($new_page_id, '_custom_language', 'en');
        add_post_meta($new_page_id, '_translation_pointer', $page_id);
    }

    return $new_page_id;
}

// Handle the duplicate action
add_action('admin_action_duplicate_post', 'lfp_duplicate_post_action');
function lfp_duplicate_post_action() {
    if (!current_user_can('edit_posts')) {
        wp_die('You do not have sufficient permissions to duplicate this post.');
    }

    if (isset($_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);
        $new_post_id = lfp_duplicate_post($post_id);

        if ($new_post_id) {
            wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
            exit;
        } else {
            wp_die('There was an error duplicating the post.');
        }
    } else {
        wp_die('No post ID specified.');
    }
}

// Function to duplicate a post
function lfp_duplicate_post($post_id) {
    $post = get_post($post_id);

    if (!$post) {
        return false;
    }

    global $wpdb;

    $table = $wpdb->prefix . 'posts';

    $now = current_time('mysql');

    $wpdb->insert(
        $table,
        array(
            'post_author'           => get_current_user_id(),
            'post_date'             => $now,
            'post_date_gmt'         => get_gmt_from_date($now),
            'post_content'          => $post->post_content,
            'post_title'            => $post->post_title,
            'post_excerpt'          => '',
            'post_status'           => 'draft',
            'comment_status'        => 'closed',
            'ping_status'           => 'closed',
            'post_password'         => '',
            'post_name'             => sanitize_title($post->post_title),
            'to_ping'               => '',
            'pinged'                => '',
            'post_modified'         => $now,
            'post_modified_gmt'     => get_gmt_from_date($now),
            'post_content_filtered' => '',
            'post_parent'           => $post->post_parent,
            'guid'                  => '', // Can be updated later with get_permalink($id)
            'menu_order'            => $post->menu_order,
            'post_type'             => 'post',
            'post_mime_type'        => '',
            'comment_count'         => 0
        )
    );

    $new_post_id = $wpdb->insert_id;


    if ($new_post_id) {
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            wp_set_object_terms($new_post_id, $terms, $taxonomy, false);
        }

        $meta = get_post_meta($post_id);
        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($new_post_id, $key, maybe_unserialize($value));
            }
        }

        // Add custom language meta field
        add_post_meta($new_post_id, '_custom_language', 'en');
        add_post_meta($new_post_id, '_translation_pointer', $post_id);
    }

    return $new_post_id;
}