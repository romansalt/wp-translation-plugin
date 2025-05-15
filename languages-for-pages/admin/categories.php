<?php

if (!defined('ABSPATH')) {
    exit;
}

// Hook into the category add/edit forms
add_action('category_add_form_fields', 'lfp_add_custom_language_field_to_category');
add_action('category_edit_form_fields', 'lfp_edit_custom_language_field_in_category');
add_action('created_category', 'lfp_save_custom_language_meta', 10, 2);
add_action('edited_category', 'lfp_save_custom_language_meta', 10, 2);

// Display custom field on Add Category page
function lfp_add_custom_language_field_to_category() {
    $languages = get_option('custom_supported_languages', array());
    ?>
    <div class="form-field">
        <label for="custom_language">Language</label>
        <select name="custom_language" id="custom_language">
            <?php foreach ($languages as $code => $label): ?>
                <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select the language for this category.</p>
    </div>
    <?php
}

// Display custom field on Edit Category page
function lfp_edit_custom_language_field_in_category($term) {
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
            <p class="description">Select the language for this category.</p>
        </td>
    </tr>
    <?php
}

// Save the custom meta value
function lfp_save_custom_language_meta($term_id) {
    if (isset($_POST['custom_language'])) {
        update_term_meta($term_id, '_custom_language', sanitize_text_field($_POST['custom_language']));
    }
}

add_filter('manage_edit-category_columns', 'lfp_add_language_column_to_category_table');
function lfp_add_language_column_to_category_table($columns) {
    $columns['custom_language'] = 'Language';
    return $columns;
}

// Display language in the column
add_filter('manage_category_custom_column', 'lfp_show_language_column_content', 10, 3);
function lfp_show_language_column_content($content, $column_name, $term_id) {
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

// quick edit logic

add_action('quick_edit_custom_box', 'lfp_add_quick_edit_category_language_field', 10, 3);
function lfp_add_quick_edit_category_language_field($column_name, $screen, $taxonomy) {
    if ($column_name !== 'custom_language' || $taxonomy !== 'category') {
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

add_action('edited_terms', 'lfp_save_quick_edit_language_meta', 10, 2);
function lfp_save_quick_edit_language_meta($term_id, $taxonomy) {
    if ($taxonomy === 'category' && isset($_POST['custom_language'])) {
        update_term_meta($term_id, '_custom_language', sanitize_text_field($_POST['custom_language']));
    }
}

add_action('admin_enqueue_scripts', 'lfp_enqueue_categories_lang_script');
function lfp_enqueue_categories_lang_script($hook) {
    if ($hook == 'edit-tags.php') {
        $screen = get_current_screen();
        if (is_object($screen) && $screen->taxonomy == 'category') {
            wp_enqueue_script('lfp-categories-lang-js', plugin_dir_url(dirname(__FILE__)) . 'admin/js/categories_lang.js', ['jquery'], null, true);
        }
    }
}