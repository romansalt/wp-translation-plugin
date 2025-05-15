<?php

if (!defined('ABSPATH')) {
    exit;
}


// Add quick edit field for both posts and pages
add_action('quick_edit_custom_box', 'lfp_add_quick_edit_posts_language_field', 10, 2);
function lfp_add_quick_edit_posts_language_field($column_name, $post_type) {
    // Only show this field if the column is 'custom_language' and post type is either 'post' or 'page'
    if ($column_name == 'custom_language' && ($post_type == 'post' || $post_type == 'page')) {
        $languages = get_option('custom_supported_languages', array());
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label class="inline-edit-group">
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
}

// Add language column after the title column for pages
add_filter('manage_pages_columns', 'lfp_add_language_column_pages');
function lfp_add_language_column_pages($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['custom_language'] = __('Language');
        }
    }
    return $new_columns;
}

// Add language column after the title column for posts
add_filter('manage_posts_columns', 'lfp_add_language_column_posts');
function lfp_add_language_column_posts($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['custom_language'] = __('Language');
        }
    }
    return $new_columns;
}

// Display the language in the column
add_action('manage_posts_custom_column', 'lfp_display_language_column', 10, 2);
add_action('manage_pages_custom_column', 'lfp_display_language_column', 10, 2);
function lfp_display_language_column($column_name, $post_id) {
    if ($column_name == 'custom_language') {
        $language_code = get_post_meta($post_id, '_custom_language', true);
        $languages = get_option('custom_supported_languages', array());
        echo isset($languages[$language_code]) ? esc_html($languages[$language_code]) : '';
    }
}

// Save the quick edit language field
add_action('save_post', 'lfp_save_quick_edit_language', 10, 2);
function lfp_save_quick_edit_language($post_id, $post) {
    // Skip autosaves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_id;
    if (wp_is_post_revision($post_id)) return $post_id;
    
    // Check permissions
    if ('page' === $post->post_type && !current_user_can('edit_page', $post_id)) return $post_id;
    elseif (!current_user_can('edit_post', $post_id)) return $post_id;
    
    // Save language if it was submitted
    if (isset($_REQUEST['custom_language'])) {
        update_post_meta($post_id, '_custom_language', sanitize_text_field($_REQUEST['custom_language']));
    }
    
    return $post_id;
}

// Add JavaScript to populate the quick edit field with the current value
add_action('admin_footer', 'lfp_quick_edit_language_js');
function lfp_quick_edit_language_js() {
    $screen = get_current_screen();
    
    // Only add to post/page list screens
    if (!($screen->base === 'edit' && ($screen->post_type === 'post' || $screen->post_type === 'page'))) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Store the language data when the page loads
        var languageData = {};
        
        $('.language-value').each(function() {
            var postId = $(this).closest('tr').attr('id').replace('post-', '');
            languageData[postId] = $(this).data('language');
        });
        
        // Populate the field when quick edit is clicked
        $('.editinline').on('click', function() {
            var postId = $(this).closest('tr').attr('id').replace('post-', '');
            var languageValue = languageData[postId];
            
            // Brief timeout to ensure the quick edit form is available
            setTimeout(function() {
                $('select[name="custom_language"]').val(languageValue);
            }, 200);
        });
    });
    </script>
    <?php
}

// Add hidden field to store language data for JavaScript
add_action('manage_posts_custom_column', 'lfp_add_language_data_for_js', 11, 2);
add_action('manage_pages_custom_column', 'lfp_add_language_data_for_js', 11, 2);
function lfp_add_language_data_for_js($column_name, $post_id) {
    if ($column_name == 'custom_language') {
        $language_code = get_post_meta($post_id, '_custom_language', true);
        echo '<span class="language-value" data-language="' . esc_attr($language_code) . '" style="display:none;"></span>';
    }
}