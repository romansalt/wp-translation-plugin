<?php

if (!defined('ABSPATH')) {
    exit;
}

// Helper function to find the translated page
function lfp_get_translated_page($original_page_id, $target_language) {
    $centralized_translations = get_post_meta($original_page_id, '_centralized_translations', true);
    
    if (!$centralized_translations) return null;

    return isset($centralized_translations[$target_language]) 
        ? $centralized_translations[$target_language] 
        : null;
}

// Modify content filter to work with persistent language
add_filter('the_content', 'lfp_filter_content_by_persistent_language', 20);
function lfp_filter_content_by_persistent_language($content) {
    // Prevent recursion with a static flag
    static $filtering = false;
    if ($filtering) {
        return $content;
    }

    if (!is_page() && !is_single()) return $content;

    $current_page_id = get_the_ID();
    $current_page_language = get_post_meta($current_page_id, '_custom_language', true) ?: 'en';
   
    // Get persistent language
    $persistent_language = isset($_COOKIE['persistent_language'])
        ? $_COOKIE['persistent_language']
        : 'en';

        error_log($persistent_language);
    // If the persistent language matches the current page language, return original content
    if ($persistent_language == $current_page_language) {
        return $content;
    }

    // If the current page is not the main page (translation), check the translation pointer
    $translation_pointer = get_post_meta($current_page_id, '_translation_pointer', true);

    if ($translation_pointer) {
        // If this is a translation page, use the pointer to get main translations
        $centralized_translations = get_post_meta($translation_pointer, '_centralized_translations', true);
    } else {
        // Otherwise, use the current page's translations
        $centralized_translations = get_post_meta($current_page_id, '_centralized_translations', true);
    }

    // Try to find a translation
    if (!empty($centralized_translations)) {
        $translated_page_id = isset($centralized_translations[$persistent_language])
            ? $centralized_translations[$persistent_language]
            : null;
       
        if ($translated_page_id) {
            $filtering = true;
            $translated_page = get_post($translated_page_id);
            if ($translated_page) {
                $content = $translated_page->post_content;
            }
            $filtering = false;
        }
    }

    return $content;
}


// Modify the content filter to prioritize translated pages
add_filter('template_redirect', 'lfp_redirect_to_translated_page');
function lfp_redirect_to_translated_page() {
    // Only run on single pages
    if (!is_page() && !is_single()) return;

    // Get the current page
    $current_page_id = get_the_ID();
    
    // Get the persistent language from cookie or default to English
    $persistent_language = isset($_COOKIE['persistent_language']) 
        ? sanitize_text_field($_COOKIE['persistent_language']) 
        : 'en';
    
    // If we're already on the correct language page, do nothing
    $current_page_language = get_post_meta($current_page_id, '_custom_language', true) ?: 'en';
    if ($current_page_language === $persistent_language) return;

    // Find the translated page
    $translated_page_id = lfp_get_translated_page($current_page_id, $persistent_language);
    
    // If a translation exists, redirect to it
    if ($translated_page_id) {
        wp_redirect(get_permalink($translated_page_id), 301);
        exit;
    }
}

// Modify language switcher to set persistent language
add_action('wp_footer', 'lfp_add_persistent_language_switcher');
function lfp_add_persistent_language_switcher() {
    //if (!is_page()) return;

    $languages = get_option('custom_supported_languages', array());

    $current_page_id = get_the_ID();
    $current_language = get_post_meta($current_page_id, '_custom_language', true) ?: 'en';
    $persistent_language = isset($_COOKIE['persistent_language']) 
        ? $_COOKIE['persistent_language'] 
        : 'en';

    echo '<div class="language-switcher">';
    echo '<strong>Language:</strong> ';
    foreach ($languages as $code => $name) {
        // Create a link that sets the persistent language
        $language_switch_url = add_query_arg('set_language', $code, $_SERVER['REQUEST_URI']);
        
        echo '<a href="' . esc_url($language_switch_url) . '">'
        . ($persistent_language === $code 
            ? '<strong>' . esc_html($name) . ' ✓</strong>' 
            : esc_html($name))
        . '</a> ';
    }
    echo '</div>';
}

// Handle setting persistent language
add_action('init', 'lfp_handle_persistent_language_setting');
function lfp_handle_persistent_language_setting() {
    if (isset($_GET['set_language'])) {
        $language = sanitize_text_field($_GET['set_language']);
        
        // Set a long-lived cookie for the selected language
        setcookie('persistent_language', $language, time() + (365 * 24 * 3600), '/');
        
        // Redirect back to the current page to avoid duplicate language param
        wp_redirect(remove_query_arg('set_language'));
        exit;
    }
}

function lfp_custom_language_switcher($current_language) {
    // Check if a language has been selected via your switcher
    if (isset($_COOKIE['persistent_language']) && in_array($_COOKIE['persistent_language'], ['en', 'lv', 'ru'])) {
        $language_map = [
            'en' => 'en_US',
            'lv' => 'lv_LV',
            'ru' => 'ru_RU'
        ];
        
        return $language_map[$_COOKIE['persistent_language']];
    }
    
    return $current_language;
}
add_filter('locale', 'lfp_custom_language_switcher');