<?php
/**
 * Plugin Name: WooCommerce AutoResponder AI
 * Plugin URI: https://github.com/yuzaiakira/WooCommerce-AutoResponder-AI
 * Description: Automatically generate AI-powered responses to WooCommerce product reviews using OpenAI, Gemini, or OpenRouter.
 * Version: 1.0.0
 * Author: Akira Yuzai
 * Author URI: https://yuzaiakira.github.io
 * Text Domain: wc-autoresponder-ai
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_AUTORESPONDER_AI_VERSION', '1.0.0');
define('WC_AUTORESPONDER_AI_PLUGIN_FILE', __FILE__);
define('WC_AUTORESPONDER_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_AUTORESPONDER_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AUTORESPONDER_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Check WordPress and WooCommerce requirements
add_action('admin_init', 'wc_autoresponder_ai_check_requirements');

function wc_autoresponder_ai_check_requirements(): void
{
    $errors = [];
    
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.8', '<')) {
        $errors[] = __('WooCommerce AutoResponder AI requires WordPress 5.8 or higher.', 'wc-autoresponder-ai');
    }
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        $errors[] = __('WooCommerce AutoResponder AI requires PHP 8.0 or higher.', 'wc-autoresponder-ai');
    }
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        $errors[] = __('WooCommerce AutoResponder AI requires WooCommerce to be installed and activated.', 'wc-autoresponder-ai');
    } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, '5.0', '<')) {
        $errors[] = __('WooCommerce AutoResponder AI requires WooCommerce 5.0 or higher.', 'wc-autoresponder-ai');
    }
    
    if (!empty($errors)) {
        deactivate_plugins(WC_AUTORESPONDER_AI_PLUGIN_BASENAME);
        wp_die(
            '<h1>' . __('Plugin Activation Error', 'wc-autoresponder-ai') . '</h1>' .
            '<p>' . implode('</p><p>', $errors) . '</p>' .
            '<p><a href="' . admin_url('plugins.php') . '">' . __('Return to Plugins', 'wc-autoresponder-ai') . '</a></p>'
        );
    }
}

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'WC_AutoResponder_AI\\';
    $base_dir = WC_AUTORESPONDER_AI_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin after text domain is loaded
add_action('init', 'wc_autoresponder_ai_init', 2);

function wc_autoresponder_ai_init(): void
{
    // Initialize the main plugin class
    if (class_exists('WC_AutoResponder_AI\\Plugin')) {
        WC_AutoResponder_AI\Plugin::get_instance();
    }
}

// Load text domain for localization at the correct time
add_action('init', 'wc_autoresponder_ai_load_textdomain', 1);

function wc_autoresponder_ai_load_textdomain(): void
{
    load_plugin_textdomain(
        'wc-autoresponder-ai',
        false,
        dirname(WC_AUTORESPONDER_AI_PLUGIN_BASENAME) . '/languages'
    );
}

// Also load text domain early for admin area to prevent early loading warnings
if (is_admin()) {
    add_action('admin_init', 'wc_autoresponder_ai_load_textdomain_admin', 1);
}

function wc_autoresponder_ai_load_textdomain_admin(): void
{
    // Only load if not already loaded to avoid duplicate loading
    if (!is_textdomain_loaded('wc-autoresponder-ai')) {
        load_plugin_textdomain(
            'wc-autoresponder-ai',
            false,
            dirname(WC_AUTORESPONDER_AI_PLUGIN_BASENAME) . '/languages'
        );
    }
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'wc_autoresponder_ai_activate');
register_deactivation_hook(__FILE__, 'wc_autoresponder_ai_deactivate');

function wc_autoresponder_ai_activate(): void
{
    // Create database tables
    WC_AutoResponder_AI\Database::create_tables();
    
    // Set default options
    WC_AutoResponder_AI\Settings::set_default_options();
    
    // Schedule cron events
    WC_AutoResponder_AI\Cron::schedule_events();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

function wc_autoresponder_ai_deactivate(): void
{
    // Wait for WordPress to be fully loaded before clearing events
    if (did_action('wp_loaded')) {
        // Clear scheduled events
        WC_AutoResponder_AI\Cron::clear_events();
    } else {
        // Schedule the clearing for after WordPress is loaded
        add_action('wp_loaded', function() {
            WC_AutoResponder_AI\Cron::clear_events();
        });
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
