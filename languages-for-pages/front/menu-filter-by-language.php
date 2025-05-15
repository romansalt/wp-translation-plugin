<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_nav_menu_objects', 'lfp_filter_menu_items_by_language', 10, 2);

// filters menu items by the user's language
function lfp_filter_menu_items_by_language($items, $args) {
    $current_lang = isset($_COOKIE['persistent_language']) ? sanitize_text_field($_COOKIE['persistent_language']) : '';
    $allowed_langs = array_keys(get_option('custom_supported_languages', array()));



    if (!in_array($current_lang, $allowed_langs)) {
        return $items;
    }

    foreach ($items as $key => $item) {
        $visible_langs = get_post_meta($item->ID, 'menu_item_lang_visibility', true);
        
        if(!is_array($visible_langs)) {
            $visible_langs = [get_post_meta($item->ID, '_custom_language', true)];
        } else {
            array_push($visible_langs, get_post_meta($item->ID, '_custom_language', true));
        }

        if (is_array($visible_langs) && !empty($visible_langs)) {
            if (!in_array($current_lang, $visible_langs)) {
                unset($items[$key]); 
            }
        }
    }

    return $items;
}



