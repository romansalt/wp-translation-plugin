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
   
    // Get translated data for the image
    $translated_title = get_post_meta($attachment_id, "_multilingual_title_{$language}", true);
    $translated_description = get_post_meta($attachment_id, "_multilingual_description_{$language}", true);
    $translated_alt = get_post_meta($attachment_id, "_multilingual_alt_text_{$language}", true);
    
    // Replace original data with translations if they exist
    if (!empty($translated_title)) {
        $data['image_meta']['title'] = $translated_title;
    }
    if (!empty($translated_description)) {
        $data['image_meta']['description'] = $translated_description;
    }
    
    error_log('media metadata with alt: ' . json_encode($data, JSON_PRETTY_PRINT));
    return $data;
}, 10, 2);

add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    $language = determine_current_language();
    $translated_alt = get_post_meta($attachment->ID, "_multilingual_alt_text_{$language}", true);

    error_log('trans alt: ' . $translated_alt);
    if (!empty($translated_alt)) {
        $attr['alt'] = $translated_alt;
    }

    error_log('media alt metadata: ' . json_encode($attr));
    return $attr;
}, 10, 3);

add_filter('wp_get_attachment_image', function($html, $attachment_id) {
    
    $language = determine_current_language();
    $translated_alt = get_post_meta($attachment_id, "_multilingual_alt_text_{$language}", true);
    
    if (!empty($translated_alt)) {
        // Use regex to replace alt attribute in the HTML
        $html = preg_replace('/alt=(["\'])(.*?)\\1/', 'alt="' . esc_attr($translated_alt) . '"', $html);
    }
    
    return $html;
}, 20, 5);

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