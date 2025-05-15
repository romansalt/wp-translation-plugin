<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Multilingual_Media_Support {
    private $selected_language_option = 'multilingual_media_current_language';
    
    public function __construct() {
        // Filter to add language-specific fields
        add_filter('attachment_fields_to_edit', [$this, 'add_language_fields'], 10, 2);
        
        // Filter to save language-specific fields
        add_filter('attachment_fields_to_save', [$this, 'save_language_fields'], 10, 2);
        
        // Handle language switching via AJAX
        add_action('wp_ajax_switch_media_language', [$this, 'ajax_switch_language']);
        add_action('admin_init', [$this, 'handle_language_switch']);
    }
    
    /**
     * Handle language switching via GET parameter
     */
    public function handle_language_switch() {
        // If there's a lang parameter in the URL, update the option
        if (isset($_GET['lang']) && current_user_can('upload_files')) {
            $lang = sanitize_text_field($_GET['lang']);
            $languages = get_option('custom_supported_languages', array());
            
            if (isset($languages[$lang])) {
                update_user_meta(get_current_user_id(), $this->selected_language_option, $lang);
            }
        }
    }
    
    /**
     * AJAX handler for language switching
     */
    public function ajax_switch_language() {
        if (!isset($_POST['lang']) || !current_user_can('upload_files')) {
            wp_send_json_error('Invalid request');
        }
        
        $lang = sanitize_text_field($_POST['lang']);
        $languages = get_option('custom_supported_languages', array());
        
        if (!isset($languages[$lang])) {
            wp_send_json_error('Invalid language');
        }
        
        update_user_meta(get_current_user_id(), $this->selected_language_option, $lang);
        wp_send_json_success();
    }
    
    /**
     * Get the current active language
     */
    public function get_current_language() {
        $user_id = get_current_user_id();
        $selected_lang = get_user_meta($user_id, $this->selected_language_option, true);
        $languages = get_option('custom_supported_languages', array());
        
        // If user has a valid selection, use it
        if ($selected_lang && isset($languages[$selected_lang])) {
            return $selected_lang;
        }
        
        // Otherwise, use site default language
        $default_lang = substr(get_locale(), 0, 2);
        
        // If default language not in supported languages, use first available language
        if (!isset($languages[$default_lang]) && !empty($languages)) {
            $default_lang = array_key_first($languages);
        }
        
        return $default_lang;
    }

    public function add_language_fields($form_fields, $post) {
        // Get supported languages
        $languages = get_option('custom_supported_languages', array());
        
        // Get the current language from user meta
        $current_lang = $this->get_current_language();
        
        // If the current language exists in supported languages, only add fields for that language
        if (isset($languages[$current_lang])) {
            $lang_code = $current_lang;
            $lang_name = $languages[$lang_code];
            
            // Title field
            $form_fields["multilingual_title_{$lang_code}"] = [
                'label' => sprintf('%s Title', $lang_name),
                'input' => 'text',
                'value' => get_post_meta($post->ID, "_multilingual_title_{$lang_code}", true),
                'helps' => sprintf('Title in %s', $lang_name)
            ];

            // Caption field
            $form_fields["multilingual_caption_{$lang_code}"] = [
                'label' => sprintf('%s Caption', $lang_name),
                'input' => 'text',
                'value' => get_post_meta($post->ID, "_multilingual_caption_{$lang_code}", true),
                'helps' => sprintf('Caption in %s', $lang_name)
            ];

            // Alt text field
            $form_fields["multilingual_alt_text_{$lang_code}"] = [
                'label' => sprintf('%s Alt Text', $lang_name),
                'input' => 'text',
                'value' => get_post_meta($post->ID, "_multilingual_alt_text_{$lang_code}", true),
                'helps' => sprintf('Alternative text in %s', $lang_name)
            ];

            // Description field
            $form_fields["multilingual_description_{$lang_code}"] = [
                'label' => sprintf('%s Description', $lang_name),
                'input' => 'textarea',
                'value' => get_post_meta($post->ID, "_multilingual_description_{$lang_code}", true),
                'helps' => sprintf('Description in %s', $lang_name)
            ];
        }

        return $form_fields;
    }

    public function save_language_fields($post, $attachment) {
        // Get supported languages
        $languages = get_option('custom_supported_languages', array());
        
        // Get the current language from user meta
        $current_lang = $this->get_current_language();
        
        // If the current language exists in supported languages, only save fields for that language
        if (isset($languages[$current_lang])) {
            $lang_code = $current_lang;
            
            // Save each field type for the current language
            $this->save_single_language_field($post['ID'], "multilingual_title_{$lang_code}", $attachment, 'sanitize_text_field');
            $this->save_single_language_field($post['ID'], "multilingual_caption_{$lang_code}", $attachment, 'sanitize_text_field');
            $this->save_single_language_field($post['ID'], "multilingual_alt_text_{$lang_code}", $attachment, 'sanitize_text_field');
            $this->save_single_language_field($post['ID'], "multilingual_description_{$lang_code}", $attachment, 'wp_kses_post');
        }

        return $post;
    }
    
    /**
     * Helper method to save a single language field
     */
    private function save_single_language_field($post_id, $field_key, $attachment, $sanitize_callback) {
        if (isset($attachment[$field_key])) {
            update_post_meta(
                $post_id, 
                "_{$field_key}", 
                $sanitize_callback($attachment[$field_key])
            );
        }
    }
}

// Initialize the plugin
function initialize_multilingual_media_support() {
    new Multilingual_Media_Support();
}
add_action('plugins_loaded', 'initialize_multilingual_media_support');

// Helper function to set supported languages
function set_custom_supported_languages($languages) {
    update_option('custom_supported_languages', $languages);
}

function get_localized_media_metadata($post_id, $meta_key, $lang_code = null) {
    if ($lang_code === null) {
        $lang_code = substr(get_locale(), 0, 2);
    }

    // Retrieve localized metadata
    return get_post_meta($post_id, "_multilingual_{$meta_key}_{$lang_code}", true);
}

add_action('admin_menu', function () {
    add_action('admin_footer-upload.php', 'lfp_inject_media_language_switcher_script');
});

/**
 * Function to inject the language switcher script in the media library page
 */
function lfp_inject_media_language_switcher_script() {
    // Get current language (from URL parameter or current locale)
    $current_lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : substr(get_locale(), 0, 2);
    
    // Get supported languages from options
    $languages = get_option('custom_supported_languages', array());
    
    // Base URL for the media library
    $base_url = admin_url('upload.php');
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Target the .wrap element which contains the media library interface
        const target = document.querySelector('.wrap h1');
        if (!target) return;
       
        // Create language switcher element
        const switcher = document.createElement('div');
        switcher.className = 'language-switcher';
        switcher.style.padding = '10px 0';
        switcher.style.marginBottom = '15px';
        switcher.style.borderBottom = '1px solid #ccd0d4';
        switcher.style.backgroundColor = '#f1f1f1';
        switcher.style.width = '100%';
        switcher.style.display = 'flex';
        switcher.style.alignItems = 'center';
        
        // Create the language selector HTML
        switcher.innerHTML = `<strong style="margin-right: 10px; margin-left: 10px;">Language:</strong>
            <?php foreach ($languages as $code => $label):
                $url = esc_url(add_query_arg('lang', $code, $base_url));
                $active = ($current_lang === $code) ? 'style="font-weight: bold; text-decoration: underline;"' : '';
            ?>
                <a href="<?= $url ?>" <?= $active ?>><?= $label ?></a>&nbsp;&nbsp;
            <?php endforeach; ?>`;
       
        // Insert the language switcher after the page heading
        target.parentNode.insertBefore(switcher, target.nextSibling);
        
        // Apply language parameter to attachment detail modal links
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('attachment')) {
                const currentUrl = new URL(window.location.href);
                const lang = currentUrl.searchParams.get('lang');
                
                if (lang) {
                    // Wait for the modal to be created
                    setTimeout(function() {
                        const modalUrl = new URL(document.location.href);
                        if (modalUrl.searchParams.has('item')) {
                            modalUrl.searchParams.set('lang', lang);
                            history.replaceState(null, '', modalUrl);
                        }
                    }, 100);
                }
            }
        });
    });
    </script>
    <?php
}