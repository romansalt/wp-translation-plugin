<?php

if (!defined('ABSPATH')) {
    exit;
}

// This file creates the settings page for the plugin


// Add admin menu for language settings
function lfp_custom_language_settings_menu() {
    add_options_page(
        'Language Settings',
        'Language Settings',
        'manage_options',
        'custom-language-settings',
        'lfp_custom_language_settings_page'
    );
}
add_action('admin_menu', 'lfp_custom_language_settings_menu');

// Register script and styles for the admin page
function lfp_custom_language_admin_scripts($hook) {
    // Only load on our settings page
    if ($hook != 'settings_page_custom-language-settings') {
        return;
    }
    
    wp_add_inline_style('admin-bar', '
        .wp-list-table .column-code { width: 15%; }
        .wp-list-table .column-name { width: 70%; }
        .wp-list-table .column-actions { width: 15%; text-align: right; }
        .add-new-language { margin-top: 10px; }
        .default-star { color: #ffb900; margin-left: 8px; vertical-align: middle; }
        .hidden-row { display: none; }
    ');
}
add_action('admin_enqueue_scripts', 'lfp_custom_language_admin_scripts');

// Create the settings page
function lfp_custom_language_settings_page() {
    // Process language removal if requested
    if (isset($_GET['action']) && $_GET['action'] == 'remove_language' && isset($_GET['code'])) {
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'remove_language')) {
            wp_die('Security check failed');
        }
        
        $code_to_remove = sanitize_text_field($_GET['code']);
        $supported_languages = get_option('custom_supported_languages', array());
        
        if (isset($supported_languages[$code_to_remove])) {
            unset($supported_languages[$code_to_remove]);
            update_option('custom_supported_languages', $supported_languages);
            
            // If we removed the default language, update it
            $default_language = get_option('custom_default_language');
            if ($default_language == $code_to_remove && !empty($supported_languages)) {
                // Set first available language as default
                $first_lang = array_key_first($supported_languages);
                update_option('custom_default_language', $first_lang);
            }

            // Make sure redirect happens properly
            $redirect_url = add_query_arg(
                array(
                    'page' => 'custom-language-settings',
                    'removed' => '1'
                ),
                admin_url('options-general.php')
            );

            // Make sure headers haven't been sent already
            if (!headers_sent()) {
                wp_safe_redirect($redirect_url);
                exit;
            } else {
                // Fallback if headers already sent
                echo '<script type="text/javascript">
                    window.location.href="' . esc_url($redirect_url) . '";
                </script>';
                echo '<noscript>
                    <meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '" />
                </noscript>';
                exit;
            }
        }
    }
    
    // Show success message if language was removed
    if (isset($_GET['removed']) && $_GET['removed'] == '1') {
        echo '<div class="notice notice-success is-dismissible"><p>Language removed successfully!</p></div>';
    }
    
    // Save settings if form was submitted
    if (isset($_POST['submit_language_settings'])) {
        // Verify nonce
        if (!isset($_POST['custom_language_nonce']) || !wp_verify_nonce($_POST['custom_language_nonce'], 'custom_language_settings')) {
            wp_die('Security check failed');
        }
        
        // Save default language
        if (isset($_POST['default_language'])) {
            update_option('custom_default_language', sanitize_text_field($_POST['default_language']));
        }
        
        // Process supported languages
        $supported_languages = array();
        if (isset($_POST['language_codes']) && isset($_POST['language_names'])) {
            $codes = $_POST['language_codes'];
            $names = $_POST['language_names'];
            
            for ($i = 0; $i < count($codes); $i++) {
                if (!empty($codes[$i]) && !empty($names[$i])) {
                    $code = sanitize_text_field($codes[$i]);
                    $name = sanitize_text_field($names[$i]);
                    $supported_languages[$code] = $name;
                }
            }
        }
        
        // Add new language if provided
        if (!empty($_POST['new_language_code']) && !empty($_POST['new_language_name'])) {
            $new_code = sanitize_text_field($_POST['new_language_code']);
            $new_name = sanitize_text_field($_POST['new_language_name']);
            $supported_languages[$new_code] = $new_name;
        }
        
        update_option('custom_supported_languages', $supported_languages);
        
        // Set default language if none exists
        if (empty(get_option('custom_default_language')) && !empty($supported_languages)) {
            $first_lang = array_key_first($supported_languages);
            update_option('custom_default_language', $first_lang);
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current settings
    $default_language = get_option('custom_default_language', substr(get_locale(), 0, 2));
    $supported_languages = get_option('custom_supported_languages', array());
    
    ?>
    <div class="wrap">
        <h1>Language Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('custom_language_settings', 'custom_language_nonce'); ?>
            
            <h2 class="wp-heading-inline">Supported Languages</h2>
            
            <?php if (empty($supported_languages)) : ?>
                <div class="notice notice-warning inline">
                    <p>No languages configured yet. Add your first language below.</p>
                </div>
            <?php else : ?>
                <!-- <div class="tablenav top">
                    <div class="alignleft actions">
                        <label for="default_language" style="margin-right: 5px;"><strong>Default Language:</strong></label>
                        <select name="default_language" id="default_language">
                            <?php foreach ($supported_languages as $code => $name) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($default_language, $code); ?>>
                                    <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" style="display: inline-block; margin-left: 10px;">This language will be assigned to new posts by default</p>
                    </div>
                    <br class="clear">
                </div> -->
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="column-code">Code</th>
                            <th scope="col" class="column-name">Language Name</th>
                            <th scope="col" class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="supported-languages-table">
                        <?php foreach ($supported_languages as $code => $name) : ?>
                        <tr>
                            <td class="column-code">
                                <input type="text" name="language_codes[]" value="<?php echo esc_attr($code); ?>" required size="5">
                            </td>
                            <td class="column-name">
                                <input type="text" name="language_names[]" value="<?php echo esc_attr($name); ?>" required style="width: 100%; max-width: 400px;">
                            </td>
                            <td class="column-actions">
                                <?php
                                $remove_url = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'page' => 'custom-language-settings',
                                            'action' => 'remove_language',
                                            'code' => $code
                                        ),
                                        admin_url('options-general.php')
                                    ),
                                    'remove_language'
                                );
                                ?>
                                <a href="<?php echo esc_url($remove_url); ?>" class="button button-secondary" onclick="return confirm('Are you sure you want to remove this language?');">Remove</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="col" class="column-code">Code</th>
                            <th scope="col" class="column-name">Language Name</th>
                            <th scope="col" class="column-actions">Actions</th>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
            
            <div class="add-new-language">
                <h3>Add New Language</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Language Code</th>
                        <td>
                            <input type="text" name="new_language_code" placeholder="e.g. en" size="5">
                            <p class="description">Two-letter ISO language code</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Language Name</th>
                        <td>
                            <input type="text" name="new_language_name" placeholder="e.g. English">
                            <p class="description">Full language name</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="submit_language_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}