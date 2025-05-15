<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function determine_current_language() {
    $current_lang = isset($_COOKIE['persistent_language']) ? sanitize_text_field($_COOKIE['persistent_language']) : '';
    $allowed_langs = array_keys(get_option('custom_supported_languages', array()));


    if (in_array($current_lang, $allowed_langs)) {
        return $current_lang;
    } else {
        return 'en';
    }
}

add_filter('wp_get_attachment_metadata', function($data, $attachment_id) {
    $language = determine_current_language();
    global $wpdb;
    
    // Get all translated metadata for this attachment in a single SQL query
    $translated_meta = $wpdb->get_results($wpdb->prepare("
        SELECT meta_key, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id = %d 
        AND meta_key IN ('_multilingual_title_{$language}', '_multilingual_description_{$language}', '_multilingual_alt_text_{$language}')
    ", $attachment_id), OBJECT_K);
    
    // Replace original data with translations if they exist
    if (isset($translated_meta["_multilingual_title_{$language}"])) {
        $data['image_meta']['title'] = $translated_meta["_multilingual_title_{$language}"]->meta_value;
    }
    
    if (isset($translated_meta["_multilingual_description_{$language}"])) {
        $data['image_meta']['description'] = $translated_meta["_multilingual_description_{$language}"]->meta_value;
    }
    
    error_log('media metadata with alt: ' . json_encode($data, JSON_PRETTY_PRINT));
    return $data;
}, 10, 2);

add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    $language = determine_current_language();
    global $wpdb;
    
    // Get translated alt text with direct SQL query
    $translated_alt = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id = %d 
        AND meta_key = %s
    ", $attachment->ID, "_multilingual_alt_text_{$language}"));
    
    error_log('trans alt: ' . $translated_alt);
    if (!empty($translated_alt)) {
        $attr['alt'] = $translated_alt;
    }
    
    error_log('media alt metadata: ' . json_encode($attr));
    return $attr;
}, 10, 3);

add_filter('wp_get_attachment_image', function($html, $attachment_id) {
    $language = determine_current_language();
    global $wpdb;
    
    // Get translated alt text with direct SQL query
    $translated_alt = $wpdb->get_var($wpdb->prepare("
        SELECT meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id = %d 
        AND meta_key = %s
    ", $attachment_id, "_multilingual_alt_text_{$language}"));
    
    if (!empty($translated_alt)) {
        // Use regex to replace alt attribute in the HTML
        $html = preg_replace('/alt=(["\'])(.*?)\\1/', 'alt="' . esc_attr($translated_alt) . '"', $html);
    }
    
    return $html;
}, 20, 5);

function get_attachment_translated_meta($attachment_id, $language = null) {
    static $cache = [];
    
    if ($language === null) {
        $language = determine_current_language();
    }
    
    // Return cached result if available
    $cache_key = $attachment_id . '_' . $language;
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }
    
    global $wpdb;
    
    // Get all translated metadata for this attachment in a single SQL query
    $meta_prefix = "_multilingual_";
    $translated_meta = $wpdb->get_results($wpdb->prepare("
        SELECT meta_key, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id = %d 
        AND meta_key LIKE %s
    ", $attachment_id, $meta_prefix . "%_{$language}"), OBJECT_K);
    
    // Process results into a more usable format
    $result = [];
    foreach ($translated_meta as $key => $row) {
        // Extract the field name from meta_key (remove prefix and language suffix)
        $field = str_replace([$meta_prefix, "_{$language}"], '', $key);
        $result[$field] = $row->meta_value;
    }
    
    // Cache the result
    $cache[$cache_key] = $result;
    
    return $result;
}

add_action('rest_api_init', function() {
    register_rest_field('attachment', 'translated_meta', [
        'get_callback' => function($object) {
            $language = determine_current_language();
            return [
                'title' => get_post_meta($object['id'], "_multilingual_title_{$language}", true) ?: $object['title']['rendered'],
                'description' => get_post_meta($object['id'], "_multilingual_description_{$language}", true) ?: $object['description']['rendered'],
                'alt_text' => get_post_meta($object['id'], "_multilingual_alt_text_{$language}", true) ?: $object['alt_text'],
            ];
        },
        'schema' => [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'alt_text' => ['type' => 'string'],
            ],
        ],
    ]);
});