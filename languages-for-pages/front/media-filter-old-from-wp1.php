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

    

    // Pārbaudām, vai ir tulkoti dati attēlam
    $translated_title = get_post_meta($attachment_id, "_multilingual_title_{$language}", true);
    $translated_description = get_post_meta($attachment_id, "_multilingual_description_{$language}", true);

    // Aizstāj oriģinālos datus ar tulkotajiem, ja tie eksistē
    if (!empty($translated_title)) {
        $data['title'] = $translated_title;
    }
    if (!empty($translated_description)) {
        $data['description'] = $translated_description;
    }

    error_log(json_encode($data));

    return $data;
}, 10, 2);

// Filtrēt attēla atribūtus (piemēram, alt tekstu)
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    $language = determine_current_language();
    $translated_alt = get_post_meta($attachment->ID, "_multilingual_alt_text_{$language}", true);

    if (!empty($translated_alt)) {
        $attr['alt'] = $translated_alt;
    }

    return $attr;
}, 10, 3);

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