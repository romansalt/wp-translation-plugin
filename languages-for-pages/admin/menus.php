<?php

if (!defined('ABSPATH')) {
    exit;
}

// This file handles anything related to the menus category of wordpress

add_action('wp_nav_menu_item_custom_fields', 'add_languages_checkboxes_to_menu_item_with_all', 10, 4);

// adds option to menus to add in which languages should a menu option be visibile
function add_languages_checkboxes_to_menu_item_with_all($item_id, $item, $depth, $args) {
    $saved_langs = get_post_meta($item_id, 'menu_item_lang_visibility', true);
    $menu_lang = get_post_meta($item_id, '_custom_language', true);
    $saved_langs = is_array($saved_langs) ? $saved_langs : [];

    $all_selected = empty($saved_langs);

    $langs = get_option('custom_supported_languages', array());

    ?>
    <div class="language-visibility-settings" data-item-id="<?php echo esc_attr($item_id); ?>">
        <p class="field-custom description description-wide">
            <label>Language Visibility</label><br>
            <?php foreach ($langs as $code => $label) : ?>
                <label style="margin-right: 10px;">
                    <input type="checkbox"
                        class="lang-vis-checkbox lang-<?php echo esc_attr($code); ?>"
                        name="menu_item_lang_visibility[<?php echo esc_attr($item_id); ?>][]"
                        value="<?php echo esc_attr($code); ?>"
                        <?php
                        if ($code === 'all') {
                            checked($all_selected);
                        } else {
                            checked(!$all_selected && in_array($code, $saved_langs));
                        }
                        ?>>
                    <?php echo esc_html($label); ?>
                </label>
                <br>
            <?php endforeach; ?>
            <input class="hidden-lang-menu-input" type="hidden" value="<?php echo $menu_lang ?>">
        </p>
    </div>
    <?php
}

add_action('wp_update_nav_menu_item', 'save_languages_checkboxes_with_all_option', 10, 3);

// saves menu languages
function save_languages_checkboxes_with_all_option($menu_id, $menu_item_db_id, $args) {
    if (isset($_POST['menu_item_lang_visibility'][$menu_item_db_id])) {
        $langs = array_map('sanitize_text_field', $_POST['menu_item_lang_visibility'][$menu_item_db_id]);

        // If "all" is checked, we treat it as "no specific language restriction"
        if (in_array('all', $langs)) {
            delete_post_meta($menu_item_db_id, 'menu_item_lang_visibility');
        } else {
            update_post_meta($menu_item_db_id, 'menu_item_lang_visibility', $langs);
        }
    } else {
        delete_post_meta($menu_item_db_id, 'menu_item_lang_visibility');
    }
}

add_action('wp_update_nav_menu', 'save_menu_language_meta');
function save_menu_language_meta($menu_id) {
    if (isset($_POST['menu-language-value'])) {
        update_post_meta($menu_id, '_menu_language', sanitize_text_field($_POST['menu-language-value']));
    }
}

add_action('admin_enqueue_scripts', 'lfp_enqueue_language_toggler_script');

// enqueues the js script for the language checkbox
function lfp_enqueue_language_toggler_script($hook) {
    if ($hook !== 'nav-menus.php') {
        return;
    }

    if (isset($_GET['menu'])) {
        $current_menu_id = intval($_GET['menu']);
    } else {
        // WordPress fallback: get the first available menu
        $nav_menus = wp_get_nav_menus();
        $current_menu_id = !empty($nav_menus) ? $nav_menus[0]->term_id : 0;
    }

    if (isset($_GET['lang'])) {
        $current_lang = strval($_GET['lang']);
    } else {
        $current_lang = substr(get_locale(), 0, 2);
    }

    if(get_post_meta($current_menu_id, '_menu_language', true)) {
        $current_menu_lang = get_post_meta($current_menu_id, '_menu_language', true);
    }

    // add in supported language codes so they can be set up inside the script
    wp_enqueue_script('lfp-menu-language-toggler-js', plugin_dir_url(dirname(__FILE__)) . 'admin/js/menu_lang_toggler.js', ['jquery'], null, true);

    wp_localize_script('lfp-menu-language-toggler-js', 'languages', [
        'codes' => get_option('custom_supported_languages', array()),
        'current_language' => $current_lang,
        'ajax_url' => admin_url('admin-ajax.php'),
        'current_menu' => $current_menu_id,
        'current_menu_lang' => $current_menu_lang ? $current_menu_lang : false,
    ]);
}

add_action('admin_menu', function () {
    add_action('admin_footer-nav-menus.php', 'lfp_inject_language_switcher_script');
});
function lfp_inject_language_switcher_script() {
    $current_lang = $_GET['lang'] ?? substr(get_locale(), 0, 2);

    if($_GET['lang']) {
        $lang_from_selection = true;
    }
    $languages = get_option('custom_supported_languages', array());

    $base_url = admin_url('nav-menus.php');
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Target the nav-tabs-wrapper which is right below the "Menus" heading
        const target = document.querySelector('.nav-tab-wrapper');
        if (!target) return;
        
        const switcher = document.createElement('div');
        switcher.className = 'language-switcher';
        switcher.style.padding = '10px 0';
        switcher.style.borderBottom = '1px solid #ccd0d4';
        switcher.style.backgroundColor = '#f1f1f1';
        switcher.style.width = '100%';
        switcher.style.display = 'flex';
        switcher.style.alignItems = 'center';
        switcher.innerHTML = `<strong style="margin-right: 10px; margin-left: 10px;">Language:</strong>
            <?php foreach ($languages as $code => $label):
                $url = esc_url(add_query_arg('lang', $code, $base_url));
                $active = ($current_lang === $code) ? 'style="font-weight: bold; text-decoration: underline;"' : '';
            ?>
                <a href="<?= $url ?>" <?= $active ?>><?= $label ?></a>&nbsp;&nbsp;
            <?php endforeach; ?>`;
        
        // Insert it after the nav-tab-wrapper
        target.parentNode.insertBefore(switcher, target.nextSibling);
    });
    </script>
    <?php
}


add_action('admin_init', function() {
    if (is_admin() && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'nav-menus.php') !== false) {
        error_log('Admin menu page loaded: ' . $_SERVER['REQUEST_URI']);
    }
});

// Filter pages in the Page menu section specifically
add_filter('nav_menu_items_page', 'lfp_filter_nav_menu_items_by_language', 10, 3);
add_filter('nav_menu_items_post', 'lfp_filter_nav_menu_items_by_language', 10, 3);
add_filter('nav_menu_items_post_recent', 'lfp_filter_nav_menu_items_by_language', 10, 3);
add_filter('nav_menu_items_page_recent', 'lfp_filter_nav_menu_items_by_language', 10, 3);

function lfp_filter_nav_menu_items_by_language($items, $menu, $args) {
    
    // Get language filter from URL or REQUEST
    $language_filter = isset($_REQUEST['lang']) ? sanitize_text_field($_REQUEST['lang']) : substr(get_locale(), 0, 2);
    
    $filtered_items = array();
    
    foreach ($items as $key => $item) {
        $page_id = $item->ID;
        $page_language = get_post_meta($page_id, '_custom_language', true);
        
        if ($page_language === $language_filter) {
            $filtered_items[] = $item;
        }
    }
    
    return $filtered_items;
}

add_action('wp_ajax_lfp_sync_menu', 'lfp_synchronize_menu_items_ajax');

function lfp_synchronize_menu_items_ajax() {
    // Check and sanitize inputs
    $menu_id = intval($_GET['menu_id']);
    $lang = sanitize_text_field($_GET['target_language']); 
    $source_lang = isset($_GET['current_language']) ? sanitize_text_field($_GET['current_language']) : 'default';

    if (empty($menu_id) || empty($lang)) {
        wp_send_json_error('Missing menu ID or target language.');
        return;
    }

    $option_name = 'connected_menus';
    $connected_menus = get_option($option_name, []);

    // Get the menu items for the current menu
    $all_menu_items = wp_get_nav_menu_items($menu_id);
    
    if (!$all_menu_items) {
        wp_send_json_error('No menu items found in the source menu.');
        return;
    }
    
    // First, assign source language to any items without a language
    if ($source_lang !== 'default') {
        foreach ($all_menu_items as $item) {
            $item_lang = get_post_meta($item->ID, '_custom_language', true);
            if (empty($item_lang)) {
                // This is an unassigned language item - tag it with the source language
                update_post_meta($item->ID, '_custom_language', $source_lang);
            }
        }
    }
    
    // Filter out only the items from the source language
    $source_items = [];
    foreach ($all_menu_items as $item) {
        $item_lang = get_post_meta($item->ID, '_custom_language', true);
        // Include items that match source language or have no language set (for default language)
        if ($item_lang === $source_lang || ($source_lang === 'default' && empty($item_lang))) {
            $source_items[] = $item;
        }
    }
    
    if (empty($source_items)) {
        wp_send_json_error('No menu items found for the source language: ' . $source_lang);
        return;
    }
    
    $item_mapping = []; // Original ID => New ID (for parent menu items)
    $processed_items = []; // Keep track of already processed items

    // First pass: Create new menu items
    foreach ($source_items as $item) {
        // Check if this item already has a translation in this language
        $connected_item_id = get_post_meta($item->ID, '_connected_item_' . $lang, true);
        
        // Skip if we already have a translation for this item and it still exists
        if (!empty($connected_item_id) && get_post($connected_item_id)) {
            $item_mapping[$item->ID] = $connected_item_id;
            $processed_items[] = $connected_item_id;
            continue;
        }
        
        $object_id = $item->object_id; // default is the original object_id
        
        // Handle post/page translation if needed
        if (in_array($item->object, ['page', 'post'])) {
            // Fetch centralized translations meta
            $centralized_translations = get_post_meta($object_id, '_centralized_translations', true);
        
            if (!is_array($centralized_translations)) {
                $centralized_translations = [];
            }
        
            if (!empty($centralized_translations[$lang])) {
                // There is already a translation
                $object_id = $centralized_translations[$lang];
            } else {
                // No translation: duplicate the page/post
                $original_post = get_post($object_id);
        
                $duplicate_post = [
                    'post_title'    => $original_post->post_title,
                    'post_content'  => $original_post->post_content,
                    'post_status'   => 'publish',
                    'post_type'     => $original_post->post_type,
                ];
                $new_post_id = wp_insert_post($duplicate_post);
        
                // Add meta to new post
                update_post_meta($new_post_id, '_translation_pointer', $object_id);
                update_post_meta($new_post_id, '_custom_language', $lang);
        
                // Update centralized translations for original and duplicate
                $centralized_translations[$lang] = $new_post_id;
                update_post_meta($object_id, '_centralized_translations', $centralized_translations);
                update_post_meta($new_post_id, '_centralized_translations', $centralized_translations);
        
                $object_id = $new_post_id;
            }
        }
        
        // Create the new menu item (with parent=0 initially)
        $args = [
            'menu-item-object-id' => $object_id,
            'menu-item-object' => $item->object,
            'menu-item-parent-id' => 0, // We'll fix parents in second pass
            'menu-item-type' => $item->type,
            'menu-item-title' => $item->title,
            'menu-item-url' => $item->url,
            'menu-item-status' => 'publish',
            'menu-item-position' => $item->menu_order,
        ];
        
        // Add to the same menu (not creating a new menu)
        $new_item_id = wp_update_nav_menu_item($menu_id, 0, $args);
        
        // Store the language information
        update_post_meta($new_item_id, '_custom_language', $lang);
        
        // Create bidirectional connection between original and translated item
        update_post_meta($item->ID, '_connected_item_' . $lang, $new_item_id);
        update_post_meta($new_item_id, '_translation_pointer', $item->ID);
        
        // Add this item to our mapping for parent relationships
        $item_mapping[$item->ID] = $new_item_id;
        $processed_items[] = $new_item_id;
        
        // Store connection in the option
        $connection_id = get_post_meta($item->ID, '_connected_menu_option_id', true);
        if (empty($connection_id)) {
            $connection_id = uniqid('menu_item_sync_', true);
            update_post_meta($item->ID, '_connected_menu_option_id', $connection_id);
        }
        
        // Save to the connections option
        if (!isset($connected_menus[$connection_id])) {
            $connected_menus[$connection_id] = [];
        }
        
        // Store the connection
        $connected_menus[$connection_id][$source_lang] = $item->ID;
        $connected_menus[$connection_id][$lang] = $new_item_id;
        
        // Set language visibility
        $langs = [$lang];
        update_post_meta($new_item_id, 'menu_item_lang_visibility', $langs);
    }
    
    // Second pass: fix parent relationships
    foreach ($source_items as $item) {
        if ($item->menu_item_parent && isset($item_mapping[$item->ID])) {
            // Get the translated parent if available
            $parent_item_id = $item->menu_item_parent;
            $translated_parent_id = isset($item_mapping[$parent_item_id]) ? $item_mapping[$parent_item_id] : 0;
            
            // If we have a translated parent, use it; otherwise leave as top-level item
            if ($translated_parent_id) {
                $args = [
                    'menu-item-parent-id' => $translated_parent_id,
                ];
                
                wp_update_nav_menu_item($menu_id, $item_mapping[$item->ID], $args);
            }
        }
    }
    
    // Save the connections back to options
    update_option($option_name, $connected_menus);
    
    // Clean up any orphaned menu items for this language
    // Only remove items that are supposed to be connected to source items but weren't processed
    $orphaned_items = [];
    $all_menu_items = wp_get_nav_menu_items($menu_id);
    
    foreach ($all_menu_items as $menu_item) {
        $item_lang = get_post_meta($menu_item->ID, '_custom_language', true);
        if ($item_lang === $lang) {
            // Get the original item this is connected to
            $original_item = get_post_meta($menu_item->ID, '_translation_pointer', true);
            
            // If it has a translation pointer but wasn't processed in this run
            if ($original_item && !in_array($menu_item->ID, $processed_items)) {
                // Check if the original item exists and is from our source language
                $original_lang = get_post_meta($original_item, '_custom_language', true);
                $is_from_source = ($original_lang === $source_lang) || 
                                 ($source_lang === 'default' && empty($original_lang));
                
                // If it's connected to a source language item but wasn't processed, it's orphaned
                if ($is_from_source) {
                    $orphaned_items[] = $menu_item->ID;
                }
            }
        }
    }
    
    // Delete orphaned items
    foreach ($orphaned_items as $orphaned_id) {
        wp_delete_post($orphaned_id, true);
    }
    
    wp_send_json_success([
        'message' => 'Menu items synchronization complete.',
        'menu_id' => $menu_id,
        'source_language' => $source_lang,
        'target_language' => $lang,
        'items_created' => count($item_mapping),
        'orphaned_items_removed' => count($orphaned_items),
    ]);
}

add_action('admin_print_footer_scripts-nav-menus.php', 'lfp_filter_menu_dropdown_by_language');
function lfp_filter_menu_dropdown_by_language() {
    // Get the language from POST or locale
    $selected_lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : substr(get_locale(), 0, 2);

    // Get all menu IDs with their language
    $menus = wp_get_nav_menus();
    $menu_lang_map = [];
    foreach ($menus as $menu) {
        $lang = get_post_meta($menu->term_id, '_menu_language', true);
        $menu_lang_map[$menu->term_id] = $lang;
    }

    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const lang = <?php echo json_encode($selected_lang); ?>;
        const menuLangMap = <?php echo json_encode($menu_lang_map); ?>;
        console.log(lang);
        console.log(menuLangMap);

        const select = document.querySelector('#select-menu-to-edit'); // The menu dropdown
        if (!select) return;

        const form = select.closest('form');

        const hiddenLang = document.createElement('input');
        hiddenLang.type = 'hidden';
        hiddenLang.name = 'lang';
        hiddenLang.value = <?php echo json_encode($selected_lang); ?>;


        form.appendChild(hiddenLang);
    });
    </script>
    <?php
}