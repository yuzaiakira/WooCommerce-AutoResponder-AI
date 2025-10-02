<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI;

/**
 * Admin interface for the plugin
 */
class Admin
{
    private Settings $settings;
    private Database $database;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->database = new Database();
        
        // Only add admin hooks if we're in admin area
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'handle_admin_actions']);
            add_filter('comment_row_actions', [$this, 'add_review_actions'], 10, 2);
            add_action('admin_footer', [$this, 'add_review_modal']);
            
            // Add AI comment indicators to Comments page
            add_filter('comment_row_actions', [$this, 'add_ai_comment_actions'], 10, 2);
            add_action('admin_head-edit-comments.php', [$this, 'add_ai_comment_styles']);
        }
    }
    
    public function add_admin_menu(): void
    {
        // Main menu page
        add_menu_page(
            __('WooCommerce AutoResponder AI', 'wc-autoresponder-ai'),
            __('AI Reviews', 'wc-autoresponder-ai'),
            'manage_woocommerce',
            'wc-autoresponder-ai',
            [$this, 'dashboard_page'],
            'dashicons-format-chat',
            56
        );
        
        // Dashboard submenu
        add_submenu_page(
            'wc-autoresponder-ai',
            __('Dashboard', 'wc-autoresponder-ai'),
            __('Dashboard', 'wc-autoresponder-ai'),
            'manage_woocommerce',
            'wc-autoresponder-ai',
            [$this, 'dashboard_page']
        );
        
        // Settings submenu
        add_submenu_page(
            'wc-autoresponder-ai',
            __('Settings', 'wc-autoresponder-ai'),
            __('Settings', 'wc-autoresponder-ai'),
            'manage_woocommerce',
            'wc-autoresponder-ai-settings',
            [$this, 'settings_page']
        );
        
        // Remove AI Responses submenu - responses will be shown in Comments page
        
        // Logs submenu
        add_submenu_page(
            'wc-autoresponder-ai',
            __('Activity Logs', 'wc-autoresponder-ai'),
            __('Activity Logs', 'wc-autoresponder-ai'),
            'manage_woocommerce',
            'wc-autoresponder-ai-logs',
            [$this, 'logs_page']
        );
    }
    
    public function handle_admin_actions(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        if (isset($_POST['wc_ai_test_provider'])) {
            $this->handle_test_provider();
        }
        
        if (isset($_POST['wc_ai_export_data'])) {
            $this->handle_export_data();
        }
    }
    
    public function dashboard_page(): void
    {
        $stats = $this->database->get_statistics();
        $feedback_stats = $this->database->get_feedback_stats();
        $provider_status = $this->get_provider_status();
        
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce AutoResponder AI Dashboard', 'wc-autoresponder-ai'); ?></h1>
            
            <div class="wc-ai-dashboard">
                <!-- Statistics Cards -->
                <div class="wc-ai-stats-grid">
                    <div class="wc-ai-stat-card">
                        <h3><?php _e('Total Responses', 'wc-autoresponder-ai'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['total_responses']); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <h3><?php _e('Pending Approval', 'wc-autoresponder-ai'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['by_status']['pending'] ?? 0); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <h3><?php _e('Approved', 'wc-autoresponder-ai'); ?></h3>
                        <div class="stat-number"><?php echo esc_html($stats['by_status']['approved'] ?? 0); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <h3><?php _e('Avg. Response Time', 'wc-autoresponder-ai'); ?></h3>
                        <div class="stat-number"><?php echo esc_html(round((float)($stats['avg_generation_time'] ?? 0), 2)); ?>s</div>
                    </div>
                </div>
                
                <!-- Provider Status -->
                <div class="wc-ai-section">
                    <h2><?php _e('AI Provider Status', 'wc-autoresponder-ai'); ?></h2>
                    <div class="wc-ai-provider-status">
                        <?php foreach ($provider_status as $provider => $status): ?>
                            <div class="provider-status-item">
                                <span class="provider-name"><?php echo esc_html(ucfirst($provider)); ?></span>
                                <span class="status-indicator <?php echo $status['available'] ? 'status-ok' : 'status-error'; ?>">
                                    <?php echo $status['available'] ? __('Available', 'wc-autoresponder-ai') : __('Unavailable', 'wc-autoresponder-ai'); ?>
                                </span>
                                <span class="model-name"><?php echo esc_html($status['model']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="wc-ai-section">
                    <h2><?php _e('Recent Activity', 'wc-autoresponder-ai'); ?></h2>
                    <?php $this->display_recent_activity(); ?>
                </div>
                
                <!-- Feedback Statistics -->
                <?php if ($feedback_stats['total_feedback'] > 0): ?>
                <div class="wc-ai-section">
                    <h2><?php _e('Feedback Statistics', 'wc-autoresponder-ai'); ?></h2>
                    <div class="wc-ai-feedback-stats">
                        <div class="feedback-item">
                            <span class="feedback-label"><?php _e('Positive Feedback:', 'wc-autoresponder-ai'); ?></span>
                            <span class="feedback-value"><?php echo esc_html($feedback_stats['positive_feedback']); ?></span>
                        </div>
                        <div class="feedback-item">
                            <span class="feedback-label"><?php _e('Negative Feedback:', 'wc-autoresponder-ai'); ?></span>
                            <span class="feedback-value"><?php echo esc_html($feedback_stats['negative_feedback']); ?></span>
                        </div>
                        <div class="feedback-item">
                            <span class="feedback-label"><?php _e('Positive Rate:', 'wc-autoresponder-ai'); ?></span>
                            <span class="feedback-value"><?php echo esc_html($feedback_stats['positive_rate']); ?>%</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="wc-ai-section">
                    <h2><?php _e('Quick Actions', 'wc-autoresponder-ai'); ?></h2>
                    <div class="wc-ai-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=wc-autoresponder-ai-settings'); ?>" class="button button-primary">
                            <?php _e('Configure Settings', 'wc-autoresponder-ai'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit-comments.php?comment_type=review'); ?>" class="button">
                            <?php _e('View AI Responses', 'wc-autoresponder-ai'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit-comments.php?comment_type=review'); ?>" class="button">
                            <?php _e('Manage Reviews', 'wc-autoresponder-ai'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function settings_page(): void
    {
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce AutoResponder AI Settings', 'wc-autoresponder-ai'); ?></h1>
            
            <?php
            // Show settings errors/success messages
            settings_errors('wc_ai_options');
            
            // Show success message if settings were saved
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wc-autoresponder-ai') . '</p></div>';
            }
            ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_ai_settings');
                do_settings_sections('wc_ai_settings');
                submit_button();
                ?>
            </form>
            

            <!-- Provider Testing Section -->
            <div class="wc-ai-provider-testing">
                <h2><?php _e('Test AI Providers', 'wc-autoresponder-ai'); ?></h2>
                <p><?php _e('Test your AI provider connections to ensure they are working correctly.', 'wc-autoresponder-ai'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('wc_ai_test_provider', 'wc_ai_test_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Test Provider', 'wc-autoresponder-ai'); ?></th>
                            <td>
                                <select name="test_provider">
                                    <option value="openai"><?php _e('OpenAI', 'wc-autoresponder-ai'); ?></option>
                                    <option value="gemini"><?php _e('Google Gemini', 'wc-autoresponder-ai'); ?></option>
                                    <option value="openrouter"><?php _e('OpenRouter', 'wc-autoresponder-ai'); ?></option>
                                </select>
                                <input type="submit" name="wc_ai_test_provider" class="button" value="<?php _e('Test Connection', 'wc-autoresponder-ai'); ?>" />
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
        </div>
        <?php
    }
    
    // responses_page() removed - AI responses are now shown in Comments page
    
    public function logs_page(): void
    {
        $logs = $this->database->get_logs(100);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Activity Logs', 'wc-autoresponder-ai'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Action', 'wc-autoresponder-ai'); ?></th>
                        <th><?php _e('User', 'wc-autoresponder-ai'); ?></th>
                        <th><?php _e('Product', 'wc-autoresponder-ai'); ?></th>
                        <th><?php _e('Details', 'wc-autoresponder-ai'); ?></th>
                        <th><?php _e('IP Address', 'wc-autoresponder-ai'); ?></th>
                        <th><?php _e('Date', 'wc-autoresponder-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No logs found.', 'wc-autoresponder-ai'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <span class="action-badge action-<?php echo esc_attr($log['action']); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $log['action']))); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['user_name'] ?: __('System', 'wc-autoresponder-ai')); ?></td>
                                <td><?php echo esc_html($log['product_title'] ?: '-'); ?></td>
                                <td>
                                    <?php
                                    $details = json_decode($log['details'], true);
                                    if ($details) {
                                        echo esc_html(wp_trim_words(wp_json_encode($details), 10));
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($log['ip_address']); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function add_review_actions(array $actions, \WP_Comment $comment): array
    {
        // Check if this is a product review
        if (get_post_type($comment->comment_post_ID) !== 'product') {
            return $actions;
        }
        
        // Check if it's a review (WooCommerce reviews might have empty comment_type or be 'review')
        if (!empty($comment->comment_type) && $comment->comment_type !== 'review') {
            return $actions;
        }
        
        $actions['wc_ai_generate'] = sprintf(
            '<a href="#" class="wc-ai-generate-response" data-review-id="%d">%s</a>',
            $comment->comment_ID,
            __('Generate AI Response', 'wc-autoresponder-ai')
        );
        
        return $actions;
    }
    
    public function add_review_modal(): void
    {
        ?>
        <div id="wc-ai-response-modal" class="wc-ai-modal" style="display: none;">
            <div class="wc-ai-modal-content">
                <div class="wc-ai-modal-header">
                    <h2><?php _e('AI Generated Response', 'wc-autoresponder-ai'); ?></h2>
                    <span class="wc-ai-modal-close">&times;</span>
                </div>
                <div class="wc-ai-modal-body">
                    <div class="wc-ai-response-content">
                        <p><?php _e('Generating response...', 'wc-autoresponder-ai'); ?></p>
                    </div>
                    <div class="wc-ai-response-actions">
                        <button class="button button-primary wc-ai-approve-response">
                            <?php _e('Approve & Publish', 'wc-autoresponder-ai'); ?>
                        </button>
                        <button class="button wc-ai-reject-response">
                            <?php _e('Reject', 'wc-autoresponder-ai'); ?>
                        </button>
                        <button class="button wc-ai-edit-response">
                            <?php _e('Edit', 'wc-autoresponder-ai'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_provider_status(): array
    {
        if (class_exists('WC_AutoResponder_AI\\AIProviderManager')) {
            $ai_manager = new AIProviderManager($this->settings);
            return $ai_manager->get_provider_status();
        }
        
        return [];
    }
    
    private function display_recent_activity(): void
    {
        $logs = $this->database->get_logs(10);
        
        if (empty($logs)) {
            echo '<p>' . __('No recent activity.', 'wc-autoresponder-ai') . '</p>';
            return;
        }
        
        echo '<ul class="wc-ai-activity-list">';
        foreach ($logs as $log) {
            echo '<li>';
            echo '<span class="activity-action">' . esc_html(ucfirst(str_replace('_', ' ', $log['action']))) . '</span>';
            echo '<span class="activity-time">' . esc_html(human_time_diff(strtotime($log['created_at']))) . ' ' . __('ago', 'wc-autoresponder-ai') . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    private function handle_test_provider(): void
    {
        if (!wp_verify_nonce($_POST['wc_ai_test_nonce'], 'wc_ai_test_provider')) {
            wp_die(__('Security check failed.', 'wc-autoresponder-ai'));
        }
        
        $provider = sanitize_text_field($_POST['test_provider']);
        
        if (class_exists('WC_AutoResponder_AI\\AIProviderManager')) {
            $ai_manager = new AIProviderManager($this->settings);
            $result = $ai_manager->test_provider($provider);
            
            if ($result['success']) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
                });
            }
        }
    }
    
    private function handle_export_data(): void
    {
        // Implementation for data export
        // This would generate a CSV or JSON export of the plugin data
    }
    
    /**
     * Add AI comment actions to Comments page
     */
    public function add_ai_comment_actions(array $actions, \WP_Comment $comment): array
    {
        // Check if this is an AI-generated comment
        $is_ai_generated = get_comment_meta($comment->comment_ID, 'ai_generated', true);
        
        if ($is_ai_generated) {
            // Add AI indicator
            $actions['wc_ai_indicator'] = sprintf(
                '<span class="wc-ai-indicator" title="%s">🤖 %s</span>',
                __('AI Generated Response', 'wc-autoresponder-ai'),
                __('AI Response', 'wc-autoresponder-ai')
            );
            
            // Add provider info
            $ai_provider = get_comment_meta($comment->comment_ID, 'ai_provider', true);
            if ($ai_provider) {
                $actions['wc_ai_provider'] = sprintf(
                    '<span class="wc-ai-provider" title="%s">%s</span>',
                    sprintf(__('Generated by %s', 'wc-autoresponder-ai'), ucfirst($ai_provider)),
                    ucfirst($ai_provider)
                );
            }
        }
        
        return $actions;
    }
    
    /**
     * Add styles for AI comments
     */
    public function add_ai_comment_styles(): void
    {
        ?>
        <style>
        .wc-ai-indicator {
            background: #0073aa;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .wc-ai-provider {
            background: #f0f0f1;
            color: #50575e;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .comment-author .wc-ai-indicator {
            display: inline-block;
            margin-left: 5px;
        }
        
        .comment-content .wc-ai-indicator {
            float: right;
            margin-top: 5px;
        }
        </style>
        <?php
    }
}
