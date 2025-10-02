<?php
/**
 * Uninstall script for WooCommerce AutoResponder AI
 * 
 * This file is executed when the plugin is deleted through the WordPress admin.
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('wc_ai_options');
delete_option('wc_ai_db_version');

// Remove database tables
global $wpdb;

$tables = [
    $wpdb->prefix . 'wc_ai_responses',
    $wpdb->prefix . 'wc_ai_logs',
    $wpdb->prefix . 'wc_ai_feedback'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Clear scheduled events
wp_clear_scheduled_hook('wc_ai_process_review_queue');
wp_clear_scheduled_hook('wc_ai_cleanup_old_data');
wp_clear_scheduled_hook('wc_ai_send_notifications');

// Remove transients
delete_transient('wc_ai_review_queue');

// Clear any cached data
wp_cache_flush();
