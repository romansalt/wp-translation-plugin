<?php

if (!defined('ABSPATH')) {
    exit;
}

function lfp_filter_terms_by_language($terms, $taxonomy = '') {
    // Get user's current language from cookie
    $current_lang = isset($_COOKIE['persistent_language']) ? sanitize_text_field($_COOKIE['persistent_language']) : '';
    
    // Get allowed languages from options
    $allowed_langs = array_keys(get_option('custom_supported_languages', array()));
    
    // If no current language is set or it's not in allowed languages, return all terms
    if (empty($current_lang) || !in_array($current_lang, $allowed_langs)) {
        return $terms;
    }
    
    $filtered_terms = array();
    
    foreach ($terms as $term) {
        // Get term object if ID was passed
        if (is_numeric($term)) {
            $term = get_term($term, $taxonomy);
            if (is_wp_error($term)) {
                continue;
            }
        }
        
        // Check if term has language meta
        $term_lang = get_term_meta($term->term_id, '_custom_language', true);
        
        // Include term if it has no language meta or its language matches current language
        if (empty($term_lang) || $term_lang === $current_lang) {
            $filtered_terms[] = $term;
        }
    }
    
    return $filtered_terms;
}

function lfp_filter_tag_cloud_by_language($args) {
    // Store the original include parameter if it exists
    $original_include = isset($args['include']) ? $args['include'] : array();
    
    // Get all terms of the specified taxonomy
    $all_terms = get_terms(array(
        'taxonomy' => $args['taxonomy'],
        'hide_empty' => $args['hide_empty'],
    ));
    
    // Filter terms by language
    $filtered_terms = lfp_filter_terms_by_language($all_terms);
    
    // Get IDs of filtered terms
    $filtered_term_ids = wp_list_pluck($filtered_terms, 'term_id');
    
    // If original include was set, only include terms that are both in original include and filtered terms
    if (!empty($original_include)) {
        $args['include'] = array_intersect($original_include, $filtered_term_ids);
    } else {
        $args['include'] = $filtered_term_ids;
    }
    
    return $args;
}
add_filter('wp_tag_cloud_args', 'lfp_filter_tag_cloud_by_language');

function lfp_filter_widget_tag_cloud_args($args) {
    return lfp_filter_tag_cloud_by_language($args);
}
add_filter('widget_tag_cloud_args', 'lfp_filter_widget_tag_cloud_args');

/**
 * Filter widget categories list
 */
function lfp_filter_widget_categories_args($args) {
    // Keep original includes if any
    $original_include = isset($args['include']) ? $args['include'] : array();
    
    // Get all terms of the category taxonomy
    $all_categories = get_terms(array(
        'taxonomy' => 'category',
        'hide_empty' => isset($args['hide_empty']) ? $args['hide_empty'] : 0,
    ));
    
    // Filter categories by language
    $filtered_categories = lfp_filter_terms_by_language($all_categories);
    
    // Get IDs of filtered categories
    $filtered_category_ids = wp_list_pluck($filtered_categories, 'term_id');
    
    // If original include was set, only include categories that are both in original include and filtered categories
    if (!empty($original_include)) {
        $args['include'] = array_intersect($original_include, $filtered_category_ids);
    } else {
        $args['include'] = $filtered_category_ids;
    }
    
    return $args;
}
add_filter('widget_categories_args', 'lfp_filter_widget_categories_args');
add_filter('widget_categories_dropdown_args', 'lfp_filter_widget_categories_args');

function lfp_filter_get_terms_by_language($terms, $taxonomies, $args, $term_query) {
    // Only apply on public-facing pages and skip if specifically requested not to filter
    if (is_admin() || (isset($args['lang_filter']) && $args['lang_filter'] === false)) {
        return $terms;
    }
    
    return lfp_filter_terms_by_language($terms);
}
add_filter('get_terms', 'lfp_filter_get_terms_by_language', 10, 4);

function lfp_filter_term_query_by_language($pieces, $taxonomies, $args) {
    // Only apply on public-facing pages
    if (is_admin() || (isset($args['lang_filter']) && $args['lang_filter'] === false)) {
        return $pieces;
    }
    
    // Get user's current language from cookie
    $current_lang = isset($_COOKIE['persistent_language']) ? sanitize_text_field($_COOKIE['persistent_language']) : '';
    
    // Get allowed languages from options
    $allowed_langs = array_keys(get_option('custom_supported_languages', array()));
    
    // If no current language is set or it's not in allowed languages, return original query pieces
    if (empty($current_lang) || !in_array($current_lang, $allowed_langs)) {
        return $pieces;
    }
    
    global $wpdb;
    
    // Join the termmeta table to filter by language
    $pieces['join'] .= " LEFT JOIN {$wpdb->termmeta} AS tm ON t.term_id = tm.term_id AND tm.meta_key = '_custom_language'";
    
    // Filter where no language is set or language matches current language
    $pieces['where'] .= $wpdb->prepare(" AND (tm.meta_value IS NULL OR tm.meta_value = %s)", $current_lang);
    
    return $pieces;
}
add_filter('terms_clauses', 'lfp_filter_term_query_by_language', 10, 3);

function lfp_filter_nav_menu_objects($items) {
    foreach ($items as $key => $item) {
        // Check if this menu item is a taxonomy term
        if ($item->type === 'taxonomy') {
            $term = get_term($item->object_id, $item->object);
            if (!is_wp_error($term)) {
                // Check term language
                $term_lang = get_term_meta($term->term_id, '_custom_language', true);
                
                // Get user's current language
                $current_lang = isset($_COOKIE['persistent_language']) ? sanitize_text_field($_COOKIE['persistent_language']) : '';
                $allowed_langs = array_keys(get_option('custom_supported_languages', array()));
                
                // If term has a language and it doesn't match current language, remove from menu
                if (!empty($term_lang) && !empty($current_lang) && 
                    in_array($current_lang, $allowed_langs) && 
                    $term_lang !== $current_lang) {
                    unset($items[$key]);
                }
            }
        }
    }
    return $items;
}
add_filter('wp_nav_menu_objects', 'lfp_filter_nav_menu_objects');