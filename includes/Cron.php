<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI;

/**
 * Handles scheduled tasks and background processing
 */
class Cron
{
    private ReviewProcessor $review_processor;
    private Database $database;
    private Settings $settings;
    
    public function __construct(ReviewProcessor $review_processor)
    {
        $this->review_processor = $review_processor;
        $this->database = new Database();
        $this->settings = new Settings();
        
        add_action('wc_ai_process_review_queue', [$this, 'process_review_queue']);
        add_action('wc_ai_cleanup_old_data', [$this, 'cleanup_old_data']);
        add_action('wc_ai_send_notifications', [$this, 'send_notifications']);
    }
    
    public static function schedule_events(): void
    {
        // Add custom cron interval first
        add_filter('cron_schedules', [__CLASS__, 'add_cron_intervals']);
        
        // Schedule review processing every 5 minutes
        if (!wp_next_scheduled('wc_ai_process_review_queue')) {
            wp_schedule_event(time(), 'wc_ai_5min', 'wc_ai_process_review_queue');
        }
        
        // Schedule daily cleanup
        if (!wp_next_scheduled('wc_ai_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'wc_ai_cleanup_old_data');
        }
        
        // Schedule notification checks every hour
        if (!wp_next_scheduled('wc_ai_send_notifications')) {
            wp_schedule_event(time(), 'hourly', 'wc_ai_send_notifications');
        }
    }
    
    public static function clear_events(): void
    {
        // Check if action scheduler is available before clearing events
        if (function_exists('as_unschedule_all_actions')) {
            // Use action scheduler if available
            as_unschedule_all_actions('wc_ai_process_review_queue');
            as_unschedule_all_actions('wc_ai_cleanup_old_data');
            as_unschedule_all_actions('wc_ai_send_notifications');
        } else {
            // Fallback to WordPress cron
            wp_clear_scheduled_hook('wc_ai_process_review_queue');
            wp_clear_scheduled_hook('wc_ai_cleanup_old_data');
            wp_clear_scheduled_hook('wc_ai_send_notifications');
        }
    }
    
    public static function add_cron_intervals(array $schedules): array
    {
        $schedules['wc_ai_5min'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every 5 Minutes', 'wc-autoresponder-ai')
        ];
        
        return $schedules;
    }
    
    public function queue_review_for_processing(int $review_id): void
    {
        // Add to processing queue (using WordPress transients for simplicity)
        $queue = get_transient('wc_ai_review_queue') ?: [];
        
        // Check if this review is already in the queue to prevent duplicates
        foreach ($queue as $item) {
            if ($item['review_id'] === $review_id) {
                error_log('WC AI: Review ID ' . $review_id . ' already in queue, skipping');
                return;
            }
        }
        
        $queue[] = [
            'review_id' => $review_id,
            'queued_at' => current_time('mysql'),
            'attempts' => 0
        ];
        
        set_transient('wc_ai_review_queue', $queue, HOUR_IN_SECONDS);
        error_log('WC AI: Review ID ' . $review_id . ' added to queue');
    }
    
    public function process_review_queue(): void
    {
        if (!$this->settings->is_automation_enabled()) {
            return;
        }
        
        $queue = get_transient('wc_ai_review_queue') ?: [];
        if (empty($queue)) {
            return;
        }
        
        $processed = [];
        $failed = [];
        
        foreach ($queue as $item) {
            try {
                $result = $this->review_processor->generate_response($item['review_id']);
                
                if ($result) {
                    $processed[] = $item['review_id'];
                } else {
                    $failed[] = $item;
                }
            } catch (\Exception $e) {
                $item['attempts']++;
                $item['last_error'] = $e->getMessage();
                
                if ($item['attempts'] < 3) {
                    $failed[] = $item;
                } else {
                    // Max attempts reached, log as permanently failed
                    $this->database->log_action('review_processing_failed', $item['review_id'], null, [
                        'error' => $e->getMessage(),
                        'attempts' => $item['attempts']
                    ]);
                }
            }
        }
        
        // Update queue with failed items
        if (!empty($failed)) {
            set_transient('wc_ai_review_queue', $failed, HOUR_IN_SECONDS);
        } else {
            delete_transient('wc_ai_review_queue');
        }
        
        // Log processing results
        if (!empty($processed)) {
            $this->database->log_action('batch_processing_completed', null, null, [
                'processed_count' => count($processed),
                'failed_count' => count($failed),
                'processed_reviews' => $processed
            ]);
        }
    }
    
    public function cleanup_old_data(): void
    {
        $retention_days = $this->settings->get_option('privacy_settings.data_retention_days', 365);
        
        // Clean up old logs
        $cleaned_logs = $this->database->cleanup_old_logs($retention_days);
        
        // Clean up old transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value < %s",
                '_transient_wc_ai_%',
                time() - ($retention_days * DAY_IN_SECONDS)
            )
        );
        
        // Log cleanup action
        $this->database->log_action('data_cleanup', null, null, [
            'retention_days' => $retention_days,
            'cleaned_logs' => $cleaned_logs
        ]);
    }
    
    public function send_notifications(): void
    {
        $notification_settings = $this->settings->get_option('notification_settings', []);
        
        if (!($notification_settings['email_notifications'] ?? true)) {
            return;
        }
        
        $notification_email = $notification_settings['notification_email'] ?? get_option('admin_email');
        if (empty($notification_email)) {
            return;
        }
        
        // Check for high volume
        if ($notification_settings['notify_on_high_volume'] ?? true) {
            $this->check_high_volume_notification($notification_email, $notification_settings);
        }
        
        // Check for errors
        if ($notification_settings['notify_on_errors'] ?? true) {
            $this->check_error_notification($notification_email);
        }
    }
    
    private function check_high_volume_notification(string $email, array $settings): void
    {
        $threshold = $settings['high_volume_threshold'] ?? 50;
        
        // Count responses generated in the last 24 hours
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_ai_responses 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        if ($count >= $threshold) {
            $subject = sprintf(
                __('High Volume Alert - %d AI Responses Generated', 'wc-autoresponder-ai'),
                $count
            );
            
            $message = sprintf(
                __('Your WooCommerce AutoResponder AI has generated %d responses in the last 24 hours, which exceeds your threshold of %d responses.', 'wc-autoresponder-ai'),
                $count,
                $threshold
            );
            
            $message .= "\n\n" . __('You may want to review your automation settings or consider upgrading your plan if you\'re using paid AI services.', 'wc-autoresponder-ai');
            
            wp_mail($email, $subject, $message);
            
            // Log the notification
            $this->database->log_action('high_volume_notification', null, null, [
                'count' => $count,
                'threshold' => $threshold,
                'email' => $email
            ]);
        }
    }
    
    private function check_error_notification(string $email): void
    {
        // Check for errors in the last hour
        global $wpdb;
        $error_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_ai_logs 
             WHERE action IN ('provider_error', 'review_processing_error', 'review_processing_failed')
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        if ($error_count > 0) {
            $subject = sprintf(
                __('Error Alert - %d Errors in Last Hour', 'wc-autoresponder-ai'),
                $error_count
            );
            
            $message = sprintf(
                __('Your WooCommerce AutoResponder AI has encountered %d errors in the last hour. Please check your plugin settings and API keys.', 'wc-autoresponder-ai'),
                $error_count
            );
            
            $message .= "\n\n" . __('You can view detailed error logs in the plugin dashboard.', 'wc-autoresponder-ai');
            
            wp_mail($email, $subject, $message);
            
            // Log the notification
            $this->database->log_action('error_notification', null, null, [
                'error_count' => $error_count,
                'email' => $email
            ]);
        }
    }
}
