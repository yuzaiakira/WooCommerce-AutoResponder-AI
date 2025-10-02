<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI;

/**
 * Database operations for the plugin
 */
class Database
{
    private string $table_responses;
    private string $table_logs;
    private string $table_feedback;
    
    public function __construct()
    {
        global $wpdb;
        
        $this->table_responses = $wpdb->prefix . 'wc_ai_responses';
        $this->table_logs = $wpdb->prefix . 'wc_ai_logs';
        $this->table_feedback = $wpdb->prefix . 'wc_ai_feedback';
    }
    
    public static function create_tables(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for AI responses
        $table_responses = $wpdb->prefix . 'wc_ai_responses';
        $sql_responses = "CREATE TABLE $table_responses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            response_text longtext NOT NULL,
            status enum('pending','approved','rejected','published') DEFAULT 'pending',
            ai_provider varchar(50) NOT NULL,
            model_used varchar(100) NOT NULL,
            generation_time decimal(10,3) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            approved_by bigint(20) unsigned DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY review_id (review_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table for operation logs
        $table_logs = $wpdb->prefix . 'wc_ai_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            review_id bigint(20) unsigned DEFAULT NULL,
            response_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            details longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY review_id (review_id),
            KEY response_id (response_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table for feedback on AI responses
        $table_feedback = $wpdb->prefix . 'wc_ai_feedback';
        $sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            response_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            feedback_type enum('positive','negative') NOT NULL,
            feedback_text text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY user_id (user_id),
            KEY feedback_type (feedback_type),
            UNIQUE KEY unique_feedback (response_id, user_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta($sql_responses);
        dbDelta($sql_logs);
        dbDelta($sql_feedback);
        
        // Update database version
        update_option('wc_ai_db_version', WC_AUTORESPONDER_AI_VERSION);
    }
    
    public function save_response(int $review_id, int $product_id, string $response_text, string $ai_provider, string $model_used, float $generation_time = null): int
    {
        global $wpdb;
        
        error_log('WC AI: save_response called with review_id: ' . $review_id . ', product_id: ' . $product_id . ', response_text length: ' . strlen($response_text));
        
        // Limit response text size to prevent max_allowed_packet errors
        if (strlen($response_text) > 16777215) { // LONGTEXT limit
            $response_text = substr($response_text, 0, 16777212) . '...';
        }
        
        // Limit model name length
        if (strlen($model_used) > 100) {
            $model_used = substr($model_used, 0, 97) . '...';
        }
        
        error_log('WC AI: Inserting into table: ' . $this->table_responses);
        
        $result = $wpdb->insert(
            $this->table_responses,
            [
                'review_id' => $review_id,
                'product_id' => $product_id,
                'response_text' => $response_text,
                'ai_provider' => $ai_provider,
                'model_used' => $model_used,
                'generation_time' => $generation_time,
                'status' => 'pending'
            ],
            [
                '%d', '%d', '%s', '%s', '%s', '%f', '%s'
            ]
        );
        
        error_log('WC AI: wpdb->insert result: ' . ($result === false ? 'false' : 'success'));
        if ($result === false) {
            error_log('WC AI: wpdb->last_error: ' . $wpdb->last_error);
            throw new \Exception(__('Failed to save AI response to database.', 'wc-autoresponder-ai'));
        }
        
        $response_id = $wpdb->insert_id;
        error_log('WC AI: Insert ID: ' . $response_id);
        
        // Log the action
        $this->log_action('response_generated', $review_id, $response_id, [
            'ai_provider' => $ai_provider,
            'model_used' => $model_used,
            'generation_time' => $generation_time
        ]);
        
        return $response_id;
    }
    
    public function update_response_status(int $response_id, string $status, int $user_id = null, string $rejection_reason = null): bool
    {
        global $wpdb;
        
        $update_data = ['status' => $status];
        $update_format = ['%s'];
        
        if ($status === 'approved' && $user_id) {
            $update_data['approved_by'] = $user_id;
            $update_data['approved_at'] = current_time('mysql');
            $update_format[] = '%d';
            $update_format[] = '%s';
        }
        
        if ($status === 'rejected' && $rejection_reason) {
            // Limit rejection reason length
            if (strlen($rejection_reason) > 65535) { // TEXT limit
                $rejection_reason = substr($rejection_reason, 0, 65532) . '...';
            }
            $update_data['rejection_reason'] = $rejection_reason;
            $update_format[] = '%s';
        }
        
        $result = $wpdb->update(
            $this->table_responses,
            $update_data,
            ['id' => $response_id],
            $update_format,
            ['%d']
        );
        
        if ($result === false) {
            return false;
        }
        
        // Log the action
        $this->log_action('response_' . $status, null, $response_id, [
            'user_id' => $user_id,
            'rejection_reason' => $rejection_reason
        ]);
        
        return true;
    }
    
    public function get_response(int $response_id): ?array
    {
        global $wpdb;
        
        $response = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_responses} WHERE id = %d",
                $response_id
            ),
            ARRAY_A
        );
        
        return $response ?: null;
    }
    
    public function get_responses_by_review(int $review_id): array
    {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_responses} WHERE review_id = %d ORDER BY created_at DESC",
                $review_id
            ),
            ARRAY_A
        );
    }
    
    public function get_responses_by_status(string $status, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        
        // Use WooCommerce CRUD API for product data instead of direct wp_posts query
        $responses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, c.comment_content as review_content 
                 FROM {$this->table_responses} r
                 LEFT JOIN {$wpdb->comments} c ON r.review_id = c.comment_ID
                 WHERE r.status = %s
                 ORDER BY r.created_at DESC
                 LIMIT %d OFFSET %d",
                $status,
                $limit,
                $offset
            ),
            ARRAY_A
        );
        
        // Enhance responses with product data using WooCommerce CRUD API
        foreach ($responses as &$response) {
            $product = wc_get_product($response['product_id']);
            $response['product_title'] = $product ? $product->get_name() : __('Product not found', 'wc-autoresponder-ai');
        }
        
        return $responses;
    }
    
    public function get_statistics(): array
    {
        global $wpdb;
        
        $stats = [];
        
        // Total responses generated
        $stats['total_responses'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_responses}"
        );
        
        // Responses by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_responses} GROUP BY status",
            ARRAY_A
        );
        
        $stats['by_status'] = [];
        foreach ($status_counts as $row) {
            $stats['by_status'][$row['status']] = (int) $row['count'];
        }
        
        // Average generation time
        $stats['avg_generation_time'] = $wpdb->get_var(
            "SELECT AVG(generation_time) FROM {$this->table_responses} WHERE generation_time IS NOT NULL"
        );
        
        // Responses by AI provider
        $provider_counts = $wpdb->get_results(
            "SELECT ai_provider, COUNT(*) as count FROM {$this->table_responses} GROUP BY ai_provider",
            ARRAY_A
        );
        
        $stats['by_provider'] = [];
        foreach ($provider_counts as $row) {
            $stats['by_provider'][$row['ai_provider']] = (int) $row['count'];
        }
        
        // Recent activity (last 30 days)
        $stats['recent_activity'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_responses} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return $stats;
    }
    
    public function log_action(string $action, ?int $review_id, ?int $response_id, array $details = []): void
    {
        global $wpdb;
        
        error_log('WC AI: log_action called with action: ' . $action);
        
        // Limit details size to prevent max_allowed_packet errors
        $details_json = wp_json_encode($details);
        if (strlen($details_json) > 65535) { // MEDIUMTEXT limit
            $details = [
                'error' => 'Details too large, truncated',
                'original_size' => strlen($details_json)
            ];
            $details_json = wp_json_encode($details);
        }
        
        // Use simple user agent
        $user_agent = 'WC AI Plugin';
        
        $result = $wpdb->insert(
            $this->table_logs,
            [
                'action' => $action,
                'review_id' => $review_id,
                'response_id' => $response_id,
                'user_id' => get_current_user_id(),
                'details' => $details_json,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'WC AI Plugin'
            ],
            [
                '%s', '%d', '%d', '%d', '%s', '%s', '%s'
            ]
        );
        
        if ($result === false) {
            error_log('WC AI: log_action failed: ' . $wpdb->last_error);
        }
    }
    
    public function get_logs(int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        
        // Use WooCommerce CRUD API for product data instead of direct wp_posts query
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.display_name as user_name
                 FROM {$this->table_logs} l
                 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                 ORDER BY l.created_at DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
        
        // Enhance logs with product data using WooCommerce CRUD API
        foreach ($logs as &$log) {
            if ($log['review_id']) {
                $comment = get_comment($log['review_id']);
                if ($comment && $comment->comment_post_ID) {
                    $product = wc_get_product($comment->comment_post_ID);
                    $log['product_title'] = $product ? $product->get_name() : __('Product not found', 'wc-autoresponder-ai');
                } else {
                    $log['product_title'] = __('Review not found', 'wc-autoresponder-ai');
                }
            } else {
                $log['product_title'] = '-';
            }
        }
        
        return $logs;
    }
    
    public function record_feedback(int $response_id, string $feedback_type, string $feedback_text = ''): bool
    {
        global $wpdb;
        
        $result = $wpdb->replace(
            $this->table_feedback,
            [
                'response_id' => $response_id,
                'user_id' => get_current_user_id(),
                'feedback_type' => $feedback_type,
                'feedback_text' => $feedback_text
            ],
            [
                '%d', '%d', '%s', '%s'
            ]
        );
        
        if ($result === false) {
            return false;
        }
        
        // Log the action
        $this->log_action('feedback_recorded', null, $response_id, [
            'feedback_type' => $feedback_type,
            'feedback_text' => $feedback_text
        ]);
        
        return true;
    }
    
    public function get_feedback_stats(): array
    {
        global $wpdb;
        
        $stats = [];
        
        $feedback_counts = $wpdb->get_results(
            "SELECT feedback_type, COUNT(*) as count FROM {$this->table_feedback} GROUP BY feedback_type",
            ARRAY_A
        );
        
        $stats['total_feedback'] = 0;
        $stats['positive_feedback'] = 0;
        $stats['negative_feedback'] = 0;
        
        foreach ($feedback_counts as $row) {
            $count = (int) $row['count'];
            $stats['total_feedback'] += $count;
            
            if ($row['feedback_type'] === 'positive') {
                $stats['positive_feedback'] = $count;
            } else {
                $stats['negative_feedback'] = $count;
            }
        }
        
        if ($stats['total_feedback'] > 0) {
            $stats['positive_rate'] = round(($stats['positive_feedback'] / $stats['total_feedback']) * 100, 2);
        } else {
            $stats['positive_rate'] = 0;
        }
        
        return $stats;
    }
    
    private function get_client_ip(): string
    {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public function cleanup_old_logs(int $days = 90): int
    {
        global $wpdb;
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_logs} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        
        return $result ?: 0;
    }
}
