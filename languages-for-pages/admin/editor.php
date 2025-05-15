<?php

if (!defined('ABSPATH')) {
    exit;
}

// this page handles anything related to the wordpress page editor

// Add custom language meta box to the page editor
add_action('add_meta_boxes', 'lfp_add_custom_language_meta_box');
function lfp_add_custom_language_meta_box() {
    add_meta_box(
        'custom_language_meta_box',
        'Language',
        'lfp_render_custom_language_meta_box',
        'page',
        'side',
        'default'
    );

    add_meta_box(
        'custom_language_meta_box',
        'Language',
        'lfp_render_custom_language_meta_box',
        'post',
        'side',
        'default'
    );
}
// Render the custom language meta box
function lfp_render_custom_language_meta_box($post) {
    $language = get_post_meta($post->ID, '_custom_language', true);

    $supported_languages = get_option('custom_supported_languages', array());

    ?>
    <label for="_custom_language">Language:</label>
    <select name="_custom_language" id="_custom_language">
        <?php foreach ($supported_languages as $code => $title): ?>
            <option value="<?php echo $code ?>" <?php selected($language, $code); ?>><?php echo $title ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

// Save the custom language meta field
add_action('save_post', 'lfp_save_custom_language_post_meta');
function lfp_save_custom_language_post_meta($post_id) {
    if (array_key_exists('_custom_language', $_POST)) {
        update_post_meta($post_id, '_custom_language', sanitize_text_field($_POST['_custom_language']));

        $parent_post = get_post_meta($post_id, '_translation_pointer', true);

        // if there is a pointer defined or the parent post is not the same as current
        if ($parent_post != $post_id || $parent_post) {
            $parent_translations = get_post_meta($parent_post, '_centralized_translations', true);

            if ($parent_translations) {
                $parent_translations[$_POST['_custom_language']] = $post_id;

                update_post_meta($parent_post, '_centralized_translations', $parent_translations);
            }
        }
    }
}

// Add meta box for translation assignment
add_action('add_meta_boxes', 'lfp_add_translation_assignment_meta_box');
function lfp_add_translation_assignment_meta_box() {
    add_meta_box(
        'translation_assignment_meta_box',
        'Translations',
        'lfp_render_translation_assignment_meta_box_pages',
        'page',
        'side',
        'default'
    );

    add_meta_box(
        'translation_assignment_meta_box',
        'Translations',
        'lfp_render_translation_assignment_meta_box_posts',
        'post',
        'side',
        'default'
    );
}

function lfp_render_translation_assignment_meta_box_pages($post) {
    // Get current page's language
    $current_language = get_post_meta($post->ID, '_custom_language', true) ?: 'en';
        
    // Supported languages
    $languages = get_option('custom_supported_languages', array());
    ?>
    <div class="translation-assignment">
        <h4>Translations</h4>
        <?php wp_nonce_field('translation_assignment_nonce', 'translation_assignment_nonce'); ?>
        
        <div class="current-translations">
            <h5>Current Translations</h5>
            <?php 
            // Retrieve centralized translations
            $centralized_translations = get_post_meta($post->ID, '_centralized_translations', true) ?: array();
            
            if (!empty($centralized_translations)) {
                echo '<ul>';
                foreach ($centralized_translations as $lang => $page_id) {
                    $translation_page = get_post($page_id);
                    echo '<li>' . esc_html($languages[$lang]) . ': ' 
                        . esc_html($translation_page->post_title) 
                        . ' (ID: ' . esc_html($page_id) . ')</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No translations found.</p>';
            }
            ?>
        </div>

        <div class="add-translation">
            <h5>Add Translation</h5>
            <?php foreach ($languages as $lang_code => $lang_name):
                // Skip the current page's language
                if ($lang_code === $current_language) continue;
            ?>
                <div class="translation-language-section">
                    <label for="translation_<?php echo esc_attr($lang_code); ?>">
                        <?php echo esc_html($lang_name); ?>:
                    </label>
                    <select name="translations[<?php echo esc_attr($lang_code); ?>]" 
                            id="translation_<?php echo esc_attr($lang_code); ?>">
                        <option value="">Select Translation</option>
                        <?php 
                        $pages = get_posts(array(
                            'post_type' => 'page',
                            'numberposts' => -1,
                            'exclude' => array($post->ID)
                        ));
                        
                        foreach ($pages as $page) {
                            // Get the language of this page
                            $page_language = get_post_meta($page->ID, '_custom_language', true) ?: 'en';
                            
                            // Only show pages with matching language
                            if ($page_language === $lang_code) {
                                $selected = isset($centralized_translations[$lang_code]) 
                                    && $centralized_translations[$lang_code] == $page->ID 
                                    ? 'selected' : '';
                                echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' 
                                    . esc_html($page->post_title) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function lfp_render_translation_assignment_meta_box_posts($post) {
    // Get current page's language
    $current_language = get_post_meta($post->ID, '_custom_language', true) ?: 'en';
    
    // Supported languages
    $languages = get_option('custom_supported_languages', array());
    ?>
    <div class="translation-assignment">
        <h4>Translations</h4>
        <?php wp_nonce_field('translation_assignment_nonce', 'translation_assignment_nonce'); ?>
        
        <div class="current-translations">
            <h5>Current Translations</h5>
            <?php 
            // Retrieve centralized translations
            $centralized_translations = get_post_meta($post->ID, '_centralized_translations', true) ?: array();
            
            if (!empty($centralized_translations)) {
                echo '<ul>';
                foreach ($centralized_translations as $lang => $page_id) {
                    $translation_page = get_post($page_id);
                    echo '<li>' . esc_html($languages[$lang]) . ': ' 
                         . esc_html($translation_page->post_title) 
                         . ' (ID: ' . esc_html($page_id) . ')</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No translations found.</p>';
            }
            ?>
        </div>

        <div class="add-translation">
            <h5>Add Translation</h5>
            <?php foreach ($languages as $lang_code => $lang_name):
                // Skip the current page's language
                if ($lang_code === $current_language) continue;
            ?>
                <div class="translation-language-section">
                    <label for="translation_<?php echo esc_attr($lang_code); ?>">
                        <?php echo esc_html($lang_name); ?>:
                    </label>
                    <select name="translations[<?php echo esc_attr($lang_code); ?>]" 
                            id="translation_<?php echo esc_attr($lang_code); ?>">
                        <option value="">Select Translation</option>
                        <?php 
                        $pages = get_posts(array(
                            'post_type' => 'post',
                            'numberposts' => -1,
                            'exclude' => array($post->ID)
                        ));
                        
                        foreach ($pages as $page) {
                            // Get the language of this page
                            $page_language = get_post_meta($page->ID, '_custom_language', true) ?: 'en';
                            
                            // Only show pages with matching language
                            if ($page_language === $lang_code) {
                                $selected = isset($centralized_translations[$lang_code]) 
                                    && $centralized_translations[$lang_code] == $page->ID 
                                    ? 'selected' : '';
                                echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' 
                                     . esc_html($page->post_title) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// Save translation assignments
add_action('save_post', 'lfp_save_translation_assignments');
function lfp_save_translation_assignments($post_id) {
    // Check if our nonce is set and verify it
    if (!isset($_POST['translation_assignment_nonce']) || 
        !wp_verify_nonce($_POST['translation_assignment_nonce'], 'translation_assignment_nonce')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_page', $post_id)) {
        return;
    }

    // Process translations if submitted
    if (isset($_POST['translations'])) {
        // Get existing centralized translations
        $centralized_translations = get_post_meta($post_id, '_centralized_translations', true) ?: array();

        
        foreach ($_POST['translations'] as $lang_code => $translation_id) {
            // Remove any existing translation for this language
            unset($centralized_translations[$lang_code]);
            
            // Add new translation if selected
            if (!empty($translation_id)) {
                $centralized_translations[$lang_code] = intval($translation_id);
                
                // Set translation pointer on the translation page
                update_post_meta($translation_id, '_translation_pointer', $post_id);
            }
        }

        $centralized_translations[get_post_meta($post_id, '_custom_language', true)] = $post_id;
        
        // Update centralized translations for current page
        update_post_meta($post_id, '_centralized_translations', $centralized_translations);
    }
}

// Register the custom REST endpoint
function lfp_register_custom_categories_endpoint() {
    register_rest_route('clf/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'lfp_get_filtered_categories',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ));
}
add_action('rest_api_init', 'lfp_register_custom_categories_endpoint');

// Callback to return filtered categories based on language parameter
function lfp_get_filtered_categories($request) {
    $language = $request->get_param('language');
    if (!$language) {
        return new WP_Error('no_language', 'Language parameter is required', array('status' => 400));
    }

    $args = array(
        'taxonomy'   => 'category',
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
        return new WP_Error('no_categories', 'No categories found', array('status' => 404));
    }

    $controller = new WP_REST_Terms_Controller('category');
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

// Override the standard category REST endpoint to filter by language
function lfp_filter_rest_category_query($args, $request) {
    // Only apply to category requests
    if (isset($args['taxonomy']) && $args['taxonomy'] === 'category') {
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
add_filter('rest_category_query', 'lfp_filter_rest_category_query', 10, 2);

// Enqueue JavaScript for the block editor
function lfp_enqueue_block_editor_scripts() {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post') {
        return;
    }
    
    // Enqueue the main script with a version based on file time to prevent caching
    wp_enqueue_script(
        'clf-block-editor-filter',
        plugin_dir_url(dirname(__FILE__)) . 'admin/js/editor-categories-filter.js',
        array('wp-api-fetch', 'wp-data', 'wp-dom-ready', 'wp-element', 'wp-components', 'wp-notices'),
        filemtime(plugin_dir_path(__FILE__) . 'clf-block-editor-filter.js'),
        true
    );
    
    wp_localize_script(
        'clf-block-editor-filter',
        'clfSettings',
        array(
            'restNonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('clf/v1/categories'),
            'adminUrl' => admin_url(),
            'postId' => get_the_ID(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        )
    );
    
    // Also add inline script to force cache invalidation
    wp_add_inline_script('clf-block-editor-filter', 'window.clfTimeStamp = "' . time() . '";', 'before');
}
add_action('enqueue_block_editor_assets', 'lfp_enqueue_block_editor_scripts');

// Modify REST API responses to add cache control headers
function lfp_modify_rest_headers($response, $handler, $request) {
    // Only modify headers for category requests
    if (strpos($request->get_route(), '/categories') !== false) {
        if ($response instanceof WP_REST_Response) {
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }
    }
    return $response;
}
add_filter('rest_request_after_callbacks', 'lfp_modify_rest_headers', 10, 3);

function lfp_register_meta() {
    register_post_meta('post', '_custom_language', array(
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('init', 'lfp_register_meta');