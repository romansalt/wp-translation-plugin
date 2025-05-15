<?php
/*
Plugin Name: Languages for pages
Description: Adds support for adding languages to pages for localizing your website for other countries. 
Version: 1.5.6
Author: Patriks
*/

if (!defined('ABSPATH')) {
    exit;
}

// admin
require_once plugin_dir_path(__FILE__) . 'admin/categories.php';
require_once plugin_dir_path(__FILE__) . 'admin/config.php';
require_once plugin_dir_path(__FILE__) . 'admin/duplication.php';
require_once plugin_dir_path(__FILE__) . 'admin/editor.php';
require_once plugin_dir_path(__FILE__) . 'admin/media.php';
require_once plugin_dir_path(__FILE__) . 'admin/menus.php';
require_once plugin_dir_path(__FILE__) . 'admin/quick-edit.php';
require_once plugin_dir_path(__FILE__) . 'admin/tags.php';

require_once plugin_dir_path(__FILE__) . 'admin/init.php';

// front
require_once plugin_dir_path(__FILE__) . 'front/language-switcher.php';
require_once plugin_dir_path(__FILE__) . 'front/media-filter.php';
require_once plugin_dir_path(__FILE__) . 'front/menu-filter-by-language.php';
require_once plugin_dir_path(__FILE__) . 'front/widget-filter-by-language.php';


register_activation_hook(__FILE__, 'lfp_custom_translation_plugin_activation');
