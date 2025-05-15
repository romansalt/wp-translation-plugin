<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook into the tag add/edit forms
add_action('post_tag_add_form_fields', 'lfp_add_custom_language_field_to_tag');
add_action('post_tag_edit_form_fields', 'lfp_edit_custom_language_field_in_tag');
add_action('created_post_tag', 'lfp_save_custom_language_meta_tag', 10, 2);
add_action('edited_post_tag', 'lfp_save_custom_language_meta_tag', 10, 2);


// Tag editing


// Display custom field on Add tag page
function lfp_add_custom_language_field_to_tag() {
    $languages = get_option('custom_supported_languages', array());
    ?>
    <div class="form-field">
        <label for="custom_language">Language</label>
        <select name="custom_language" id="custom_language">
            <?php foreach ($languages as $code => $label): ?>
                <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select the language for this tag.</p>
    </div>
    <?php
}

// Display custom field on Edit tag page
function lfp_edit_custom_language_field_in_tag($term) {
    $value = get_term_meta($term->term_id, '_custom_language', true);
    $languages = get_option('custom_supported_languages', array());
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="custom_language">Language</label></th>
        <td>
            <select name="custom_language" id="custom_language">
                <?php foreach ($languages as $code => $label): ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Select the language for this tag.</p>
        </td>
    </tr>
    <?php
}

// ** table

// Save the custom meta value
function lfp_save_custom_language_meta_tag($term_id) {
    if (isset($_POST['custom_language'])) {
        update_term_meta($term_id, '_custom_language', sanitize_text_field($_POST['custom_language']));
    }
}

// Add language column to tag admin table
add_filter('manage_edit-post_tag_columns', 'lfp_add_language_column_to_tag_table');
function lfp_add_language_column_to_tag_table($columns) {
    $columns['custom_language'] = 'Language';
    return $columns;
}

// Display language in the column
add_filter('manage_post_tag_custom_column', 'lfp_show_language_column_content_tag', 10, 3);
function lfp_show_language_column_content_tag($content, $column_name, $term_id) {
    if ($column_name === 'custom_language') {
        $languages = get_option('custom_supported_languages', array());
        $lang_code = get_term_meta($term_id, '_custom_language', true);
        if ($lang_code && isset($languages[$lang_code])) {
            $content = '<span class="custom-language-display" data-lang-code="' . esc_attr($lang_code) . '">' . esc_html($languages[$lang_code]) . '</span>';
        } else {
            $content = '<span class="custom-language-display" data-lang-code=""></span><em>None</em>';
        }
    }
    return $content;
}

// Add language field to quick edit panel
add_action('quick_edit_custom_box', 'lfp_add_quick_edit_tag_language_field', 10, 3);
function lfp_add_quick_edit_tag_language_field($column_name, $screen, $taxonomy) {
    if ($column_name !== 'custom_language' || $taxonomy !== 'post_tag') {
        return;
    }

    $languages = get_option('custom_supported_languages', array());
    ?>
    <fieldset>
        <div class="inline-edit-col">
            <label>
                <span class="title">Language</span>
                <select name="custom_language" class="custom_language">
                    <?php foreach ($languages as $code => $label): ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </fieldset>
    <?php
}

// Save quick edit changes
add_action('edited_post_tag', 'lfp_tag_save_quick_edit_language_meta', 10, 2);
function lfp_tag_save_quick_edit_language_meta($term_id, $taxonomy) {
    if ($taxonomy === 'post_tag' && isset($_POST['custom_language'])) {
        update_term_meta($term_id, '_custom_language', sanitize_text_field($_POST['custom_language']));
    }
}

// Enqueue JavaScript for quick edit functionality
add_action('admin_enqueue_scripts', 'lfp_enqueue_tags_lang_script');
function lfp_enqueue_tags_lang_script($hook) {
    if ('edit-tags.php' === $hook && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'post_tag') {
        wp_enqueue_script('lfp-tags-lang-js', plugin_dir_url(dirname(__FILE__)) . 'admin/js/tags_lang.js', ['jquery'], null, true);
    }
}


//-----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------

// Register the custom REST endpoint
function tlf_register_custom_tags_endpoint() {
    register_rest_route('tlf/v1', '/tags', array(
        'methods' => 'GET',
        'callback' => 'tlf_get_filtered_tags',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ));
}
add_action('rest_api_init', 'tlf_register_custom_tags_endpoint');

// Callback to return filtered tags based on language parameter
function tlf_get_filtered_tags($request) {
    $language = $request->get_param('language');
    if (!$language) {
        return new WP_Error('no_language', 'Language parameter is required', array('status' => 400));
    }

    $args = array(
        'taxonomy'   => 'post_tag',
        'hide_empty' => false,
        'meta_query' => array(
            array(
                'key'     => '_custom_language',
                'value'   => $language,
                'compare' => '='
            )
        ),
    );

    $terms = get_terms($args);
    if (is_wp_error($terms)) {
        return new WP_Error('no_tags', 'No tags found', array('status' => 404));
    }

    $controller = new WP_REST_Terms_Controller('post_tag');
    $data = array();
    foreach ($terms as $term) {
        $response = $controller->prepare_item_for_response($term, $request);
        $data[] = $controller->prepare_response_for_collection($response);
    }

    // Add cache control headers to the response
    $response = rest_ensure_response($data);
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->header('Pragma', 'no-cache');
    $response->header('Expires', '0');

    return $response;
}

// Override the standard post_tag REST endpoint to filter by language
function tlf_filter_rest_tag_query($args, $request) {
    // Only apply to post_tag requests
    if (isset($args['taxonomy']) && $args['taxonomy'] === 'post_tag') {
        // Get language from post meta or request
        $language = null;
        
        // First check if we're in a post context and can get the language
        $post_id = url_to_postid($request->get_header('referer'));
        if ($post_id) {
            $language = get_post_meta($post_id, '_custom_language', true);
        }
        
        // If no language found, check if it's in the request params
        if (!$language) {
            $language = $request->get_param('language');
        }
        
        // Default to 'en' if still no language
        if (!$language) {
            $language = 'en';
        }
        
        // Add meta_query to filter by language
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = array();
        }
        
        $args['meta_query'][] = array(
            'key'     => '_custom_language',
            'value'   => $language,
            'compare' => '='
        );
    }
    
    return $args;
}
add_filter('rest_post_tag_query', 'tlf_filter_rest_tag_query', 10, 2);

// Enqueue JavaScript for the block editor
function tlf_enqueue_block_editor_scripts() {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post') {
        return;
    }
    
    // Enqueue the main script with a version based on file time to prevent caching
    wp_enqueue_script(
        'tlf-block-editor-filter',
        plugin_dir_url(dirname(__FILE__)) . 'admin/js/editor-tags-filter.js',
        array('wp-api-fetch', 'wp-data', 'wp-dom-ready', 'wp-element', 'wp-components', 'wp-notices'),
        filemtime(plugin_dir_url(dirname(__FILE__)) . 'admin/js/editor-tags-filter.js'),
        true
    );
    
    wp_localize_script(
        'tlf-block-editor-filter',
        'tlfSettings',
        array(
            'restNonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('tlf/v1/tags'),
            'adminUrl' => admin_url(),
            'postId' => get_the_ID(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        )
    );
    
    // Also add inline script to force cache invalidation
    wp_add_inline_script('tlf-block-editor-filter', 'window.tlfTimeStamp = "' . time() . '";', 'before');
}
add_action('enqueue_block_editor_assets', 'tlf_enqueue_block_editor_scripts');

// Modify REST API responses to add cache control headers
function tlf_modify_rest_headers($response, $handler, $request) {
    // Only modify headers for tag requests
    if (strpos($request->get_route(), '/tags') !== false) {
        if ($response instanceof WP_REST_Response) {
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }
    }
    return $response;
}
add_filter('rest_request_after_callbacks', 'tlf_modify_rest_headers', 10, 3);

// Add a notice to remind users to refresh if they see issues
function tlf_admin_notices() {
    $screen = get_current_screen();
    if ($screen && $screen->base === 'post') {
        ?>
        <div id="tlf-refresh-notice" class="notice notice-info is-dismissible" style="display:none;">
            <p><?php _e('Tag language filter updated. If tags don\'t update when changing language, try refreshing the page or manually clicking the "Refresh" button in the Tags panel.', 'tag-language-filter'); ?></p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Show the notice if this is the first time after an update
                if (!localStorage.getItem('tlf_notice_dismissed_<?php echo filemtime(plugin_dir_path(__FILE__) . 'tlf-block-editor-filter.js'); ?>')) {
                    $('#tlf-refresh-notice').show();
                }
                
                // Dismiss notice and remember
                $(document).on('click', '#tlf-refresh-notice .notice-dismiss', function() {
                    localStorage.setItem('tlf_notice_dismissed_<?php echo filemtime(plugin_dir_path(__FILE__) . 'tlf-block-editor-filter.js'); ?>', '1');
                });
            });
        </script>
        <?php
    }
}
add_action('admin_notices', 'tlf_admin_notices');