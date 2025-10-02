<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI;

/**
 * Main plugin class
 */
class Plugin
{
    private static ?Plugin $instance = null;
    
    private Admin $admin;
    private ReviewProcessor $review_processor;
    private AIProviderManager $ai_provider_manager;
    private Database $database;
    private Settings $settings;
    private Cron $cron;
    
    private function __construct()
    {
        $this->init_hooks();
        $this->init_components();
    }
    
    public static function get_instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    private function init_hooks(): void
    {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // WooCommerce hooks for reviews - use only the most reliable ones
        add_action('woocommerce_review_created', [$this, 'handle_new_review'], 10, 1);
        add_action('comment_post', [$this, 'handle_comment_post'], 10, 3);
        
        // Additional hook for better coverage of pending comments
        add_action('wp_insert_comment', [$this, 'handle_comment_inserted'], 10, 2);
        
        // Use transition_comment_status for better coverage without duplicates
        add_action('transition_comment_status', [$this, 'handle_comment_status_transition'], 10, 3);
        
        // Force product reviews to be pending in semi_auto mode
        add_filter('pre_comment_approved', [$this, 'force_comment_pending'], 10, 2);
        
        // Ensure AI-generated comments are visible on product pages
        add_action('woocommerce_review_after_comment_text', [$this, 'display_ai_comment_attribution'], 10, 1);
        add_action('woocommerce_review_comment_text', [$this, 'ensure_ai_comments_display'], 10, 1);
        add_filter('woocommerce_product_review_comment_form_args', [$this, 'include_ai_comments_in_reviews']);
        
        // AJAX hooks
        add_action('wp_ajax_wc_ai_generate_response', [$this, 'ajax_generate_response']);
        add_action('wp_ajax_wc_ai_approve_response', [$this, 'ajax_approve_response']);
        add_action('wp_ajax_wc_ai_reject_response', [$this, 'ajax_reject_response']);
        add_action('wp_ajax_wc_ai_feedback_response', [$this, 'ajax_feedback_response']);
        
        // Test AJAX hook
        add_action('wp_ajax_wc_ai_test', [$this, 'ajax_test']);
        add_action('wp_ajax_wc_ai_trigger_cron', [$this, 'ajax_trigger_cron']);
        
        // Debug: Log AJAX hook registration
        error_log('WC AI: AJAX hooks registered');
    }
    
    private function init_components(): void
    {
        $this->database = new Database();
        $this->settings = new Settings();
        $this->ai_provider_manager = new AIProviderManager($this->settings);
        $this->review_processor = new ReviewProcessor($this->ai_provider_manager);
        $this->admin = new Admin($this->settings);
        $this->cron = new Cron($this->review_processor);
        
        // Ensure cron events are scheduled
        $this->ensure_cron_events_scheduled();
    }
    
    private function ensure_cron_events_scheduled(): void
    {
        // Check if cron events are scheduled, if not, schedule them
        if (!wp_next_scheduled('wc_ai_process_review_queue')) {
            error_log('WC AI: Cron event not scheduled, scheduling now');
            Cron::schedule_events();
        }
    }
    
    private function has_existing_response(int $review_id): bool
    {
        // Check database for existing responses
        $existing_responses = $this->database->get_responses_by_review($review_id);
        if (!empty($existing_responses)) {
            return true;
        }
        
        // Also check if there are AI-generated comments for this review
        $ai_comments = get_comments([
            'post_id' => get_comment($review_id)->comment_post_ID,
            'parent' => $review_id,
            'meta_query' => [
                [
                    'key' => 'ai_generated',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        return !empty($ai_comments);
    }
    
    public function init(): void
    {
        // Initialize any additional components that need WordPress to be fully loaded
    }
    
    public function enqueue_scripts(): void
    {
        // Frontend scripts if needed
    }
    
    public function enqueue_admin_scripts(string $hook): void
    {
        // Only load on our admin pages
        if (strpos($hook, 'wc-autoresponder-ai') === false && 
            strpos($hook, 'product-reviews') === false) {
            return;
        }
        
        error_log('WC AI: Enqueuing admin scripts for hook: ' . $hook);
        
        wp_enqueue_script(
            'wc-autoresponder-ai-admin',
            WC_AUTORESPONDER_AI_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            WC_AUTORESPONDER_AI_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wc-autoresponder-ai-admin',
            WC_AUTORESPONDER_AI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WC_AUTORESPONDER_AI_VERSION
        );
        
        // Use simple strings to avoid early translation loading
        wp_localize_script('wc-autoresponder-ai-admin', 'wcAutoResponderAI', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ai_nonce'),
            'strings' => [
                'generating' => 'Generating response...',
                'error' => 'An error occurred. Please try again.',
                'success' => 'Response generated successfully!',
                'confirmApprove' => 'Are you sure you want to approve this response?',
                'confirmReject' => 'Are you sure you want to reject this response?',
            ]
        ]);
    }
    
    public function handle_new_review($review_id): void
    {
        if (!$this->settings->is_automation_enabled()) {
            error_log('WC AI: Automation is disabled, skipping review ID: ' . $review_id);
            return;
        }
        
        // Check if this review is already being processed to prevent duplicates
        $processing_key = 'wc_ai_processing_' . $review_id;
        if (get_transient($processing_key)) {
            error_log('WC AI: Review ID ' . $review_id . ' is already being processed, skipping');
            return;
        }
        
        // Check if this is actually a product review first
        $comment = get_comment($review_id);
        if (!$comment) {
            error_log('WC AI: Comment not found for ID: ' . $review_id);
            return;
        }
        
        if (get_post_type($comment->comment_post_ID) !== 'product') {
            error_log('WC AI: Not a product review, skipping ID: ' . $review_id);
            return;
        }
        
        // In WooCommerce, product reviews might have empty comment_type or be 'review'
        if (!empty($comment->comment_type) && $comment->comment_type !== 'review' && $comment->comment_type !== '') {
            error_log('WC AI: Not a review type (' . $comment->comment_type . '), skipping ID: ' . $review_id);
            return;
        }
        
        // Check if we already have a response for this review
        if ($this->has_existing_response($review_id)) {
            error_log('WC AI: Response already exists for review ID: ' . $review_id . ', skipping');
            return;
        }
        
        // Mark this review as being processed for 5 minutes
        set_transient($processing_key, true, 5 * MINUTE_IN_SECONDS);
        
        // Log the review processing attempt
        error_log('WC AI: Processing new review ID: ' . $review_id);
        
        // Try immediate processing first for auto mode
        $workflow_mode = $this->settings->get_workflow_mode();
        if ($workflow_mode === 'auto') {
            // Process immediately for auto mode
            $result = $this->process_review_immediately($review_id);
            if ($result) {
                // If immediate processing succeeded, no need to queue
                return;
            }
        }
        
        // Queue the review for AI processing if immediate processing didn't work
        $this->cron->queue_review_for_processing($review_id);
    }
    
    private function process_review_immediately(int $review_id): bool
    {
        // Double-check automation is enabled before immediate processing
        if (!$this->settings->is_automation_enabled()) {
            error_log('WC AI: Automation disabled, skipping immediate processing for review ID: ' . $review_id);
            return false;
        }
        
        // Check if this is actually a product review
        $comment = get_comment($review_id);
        if (!$comment) {
            error_log('WC AI: Comment not found for immediate processing, ID: ' . $review_id);
            return false;
        }
        
        if (get_post_type($comment->comment_post_ID) !== 'product') {
            error_log('WC AI: Not a product review for immediate processing, skipping ID: ' . $review_id);
            return false;
        }
        
        // Check if comment is approved or we should process unapproved comments
        $process_unapproved = $this->settings->get_option('advanced_settings.process_unapproved_comments', true);
        $workflow_mode = $this->settings->get_workflow_mode();
        
        // Process comments based on workflow mode
        if ($workflow_mode === 'auto') {
            // Auto mode: process all comments regardless of approval status
        } elseif ($workflow_mode === 'semi_auto') {
            // Semi-auto mode: process all comments regardless of approval status
        } elseif ($comment->comment_approved != 1 && !$process_unapproved) {
            error_log('WC AI: Comment not approved and process_unapproved_comments is false, skipping immediate processing for ID: ' . $review_id);
            return false;
        }
        
        try {
            error_log('WC AI: Starting immediate processing for review ID: ' . $review_id);
            $result = $this->review_processor->generate_response($review_id);
            if ($result) {
                error_log('WC AI: Immediate processing successful for review ID: ' . $review_id);
                return true;
            } else {
                error_log('WC AI: Immediate processing failed for review ID: ' . $review_id);
                return false;
            }
        } catch (\Exception $e) {
            error_log('WC AI: Immediate processing error for review ID ' . $review_id . ': ' . $e->getMessage());
            return false;
        }
    }
    
    public function handle_comment_post(int $comment_id, int $comment_approved, array $commentdata): void
    {
        // Check if this is a product review
        if (!isset($commentdata['comment_post_ID']) || 
            get_post_type($commentdata['comment_post_ID']) !== 'product') {
            return;
        }
        
        // Check if it's a review type (WooCommerce reviews might have empty comment_type)
        if (isset($commentdata['comment_type']) && !empty($commentdata['comment_type']) && $commentdata['comment_type'] !== 'review') {
            return;
        }
        
        // Process comments based on workflow mode and approval status
        $workflow_mode = $this->settings->get_workflow_mode();
        $process_unapproved = $this->settings->get_option('advanced_settings.process_unapproved_comments', true);
        
        $should_process = false;
        
        if ($workflow_mode === 'auto') {
            // Auto mode: process all comments regardless of approval status
            $should_process = true;
        } elseif ($workflow_mode === 'semi_auto') {
            // Semi-auto mode: process ALL comments (both approved and unapproved)
            // The AI response will be saved as pending for approval
            $should_process = true;
        } elseif ($workflow_mode === 'draft') {
            // Draft mode: process based on approval status and settings
            if ($comment_approved === 1) {
                // Always process approved comments
                $should_process = true;
            } elseif ($process_unapproved) {
                // Process unapproved comments if setting allows
                $should_process = true;
            }
        }
        
        if ($should_process) {
            error_log('WC AI: Comment posted via comment_post hook, ID: ' . $comment_id . ', approved: ' . $comment_approved . ', workflow_mode: ' . $workflow_mode . ', process_unapproved: ' . ($process_unapproved ? 'yes' : 'no'));
            $this->handle_new_review($comment_id);
        } else {
            error_log('WC AI: Comment not processed, ID: ' . $comment_id . ', approved: ' . $comment_approved . ', workflow_mode: ' . $workflow_mode . ', process_unapproved: ' . ($process_unapproved ? 'yes' : 'no'));
        }
    }
    
    /**
     * Handle comment insertion - covers all comments including pending ones
     */
    public function handle_comment_inserted(int $comment_id, \WP_Comment $comment): void
    {
        // Check if this is a product review
        if (get_post_type($comment->comment_post_ID) !== 'product') {
            return;
        }
        
        // Check if it's a review type (WooCommerce reviews might have empty comment_type)
        if (!empty($comment->comment_type) && $comment->comment_type !== 'review' && $comment->comment_type !== '') {
            return;
        }
        
        $workflow_mode = $this->settings->get_workflow_mode();
        
        // In semi_auto mode, process all comments regardless of approval status
        if ($workflow_mode === 'semi_auto') {
            error_log('WC AI: Comment inserted via wp_insert_comment hook, ID: ' . $comment_id . ', approved: ' . $comment->comment_approved . ', workflow_mode: ' . $workflow_mode);
            $this->handle_new_review($comment_id);
        }
    }
    
    public function handle_comment_status_transition(string $new_status, string $old_status, \WP_Comment $comment): void
    {
        // Check if this is a product review
        if (get_post_type($comment->comment_post_ID) !== 'product') {
            return;
        }
        
        // Check if it's a review (not a reply) - WooCommerce reviews might have empty comment_type
        if (!empty($comment->comment_type) && $comment->comment_type !== 'review' && $comment->comment_type !== '') {
            return;
        }
        
        $workflow_mode = $this->settings->get_workflow_mode();
        $process_unapproved = $this->settings->get_option('advanced_settings.process_unapproved_comments', true);
        
        // Process when comment is approved
        if ($new_status === 'approve') {
            error_log('WC AI: Comment approved via transition_comment_status hook, ID: ' . $comment->comment_ID);
            $this->handle_new_review($comment->comment_ID);
        }
        // Process unapproved comments if setting allows and workflow mode supports it
        elseif ($new_status === 'hold' && $process_unapproved && ($workflow_mode === 'semi_auto' || $workflow_mode === 'draft')) {
            error_log('WC AI: Unapproved comment detected via transition_comment_status hook, ID: ' . $comment->comment_ID);
            $this->handle_new_review($comment->comment_ID);
        }
    }
    
    /**
     * Force product reviews to be pending in semi_auto mode
     */
    public function force_comment_pending($approved, $commentdata): string
    {
        // Only apply to product reviews
        if (!isset($commentdata['comment_post_ID']) || 
            get_post_type($commentdata['comment_post_ID']) !== 'product') {
            return $approved;
        }
        
        // Check if it's a review type
        if (isset($commentdata['comment_type']) && !empty($commentdata['comment_type']) && $commentdata['comment_type'] !== 'review') {
            return $approved;
        }
        
        $workflow_mode = $this->settings->get_workflow_mode();
        
        // In semi_auto mode, force all product reviews to be pending
        if ($workflow_mode === 'semi_auto') {
            error_log('WC AI: Forcing product review to pending in semi_auto mode');
            return '0'; // 0 = pending
        }
        
        return (string) $approved;
    }

    /**
     * Process all unapproved comments manually
     */
    public function process_unapproved_comments(): array
    {
        $results = [];
        
        // Get all unapproved product comments
        $unapproved_comments = get_comments([
            'post_type' => 'product',
            'status' => 'hold',
            'number' => 50, // Limit to prevent timeout
            'orderby' => 'comment_date',
            'order' => 'ASC'
        ]);
        
        foreach ($unapproved_comments as $comment) {
            // Check if we already have a response for this comment
            if ($this->has_existing_response($comment->comment_ID)) {
                $results[] = [
                    'comment_id' => $comment->comment_ID,
                    'status' => 'skipped',
                    'message' => 'Response already exists'
                ];
                continue;
            }
            
            try {
                $result = $this->review_processor->generate_response($comment->comment_ID);
                if ($result) {
                    $results[] = [
                        'comment_id' => $comment->comment_ID,
                        'status' => 'success',
                        'message' => 'Response generated successfully'
                    ];
                } else {
                    $results[] = [
                        'comment_id' => $comment->comment_ID,
                        'status' => 'failed',
                        'message' => 'Failed to generate response'
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'comment_id' => $comment->comment_ID,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Approve both user comment and AI response together
     */
    public function approve_comment_and_response(int $comment_id): bool
    {
        try {
            // First, approve the user comment
            $comment_approved = wp_set_comment_status($comment_id, 'approve');
            if (!$comment_approved) {
                error_log('WC AI: Failed to approve comment ID: ' . $comment_id);
                return false;
            }
            
            // Then, approve the AI response
            $database = new Database();
            $responses = $database->get_responses_by_review($comment_id);
            
            if (!empty($responses)) {
                $response = $responses[0]; // Get the most recent response
                $review_processor = new ReviewProcessor();
                $result = $review_processor->approve_response($comment_id, $response['response_text']);
                
                if ($result) {
                    error_log('WC AI: Successfully approved both comment and AI response for ID: ' . $comment_id);
                    return true;
                } else {
                    error_log('WC AI: Failed to approve AI response for comment ID: ' . $comment_id);
                    return false;
                }
            } else {
                error_log('WC AI: No AI response found for comment ID: ' . $comment_id);
                return true; // Comment approved, but no AI response to approve
            }
            
        } catch (\Exception $e) {
            error_log('WC AI: Error approving comment and response: ' . $e->getMessage());
            return false;
        }
    }

    public function ajax_generate_response(): void
    {
        error_log('WC AI: AJAX generate_response called');
        error_log('WC AI: POST data: ' . print_r($_POST, true));
        
        check_ajax_referer('wc_ai_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            error_log('WC AI: Insufficient permissions');
            wp_die(__('Insufficient permissions.', 'wc-autoresponder-ai'));
        }
        
        $review_id = intval($_POST['review_id'] ?? 0);
        error_log('WC AI: Review ID: ' . $review_id);
        
        if (!$review_id) {
            error_log('WC AI: Invalid review ID');
            wp_send_json_error(__('Invalid review ID.', 'wc-autoresponder-ai'));
        }
        
        try {
            error_log('WC AI: Calling generate_response for review ID: ' . $review_id);
            
            // Temporarily enable automation for manual generation
            $original_automation = $this->settings->get_option('automation_enabled', false);
            $this->settings->update_option('automation_enabled', true);
            
            $response = $this->review_processor->generate_response($review_id);
            
            // Restore original automation setting
            $this->settings->update_option('automation_enabled', $original_automation);
            
            error_log('WC AI: Response generated: ' . ($response ? 'success' : 'failed'));
            
            if ($response) {
                wp_send_json_success([
                    'response' => $response,
                    'message' => __('Response generated successfully!', 'wc-autoresponder-ai')
                ]);
            } else {
                wp_send_json_error(__('Failed to generate response.', 'wc-autoresponder-ai'));
            }
        } catch (\Exception $e) {
            error_log('WC AI: Exception in generate_response: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_approve_response(): void
    {
        error_log('WC AI: AJAX approve_response called');
        
        
        try {
            check_ajax_referer('wc_ai_nonce', 'nonce');
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(__('Insufficient permissions.', 'wc-autoresponder-ai'));
                return;
            }
        } catch (\Exception $e) {
            error_log('WC AI: AJAX nonce/permission error: ' . $e->getMessage());
            wp_send_json_error(__('Security check failed.', 'wc-autoresponder-ai'));
            return;
        }
        
        // Accept both review_id and response_id for flexibility
        $review_id = intval($_POST['review_id'] ?? 0);
        $response_id = intval($_POST['response_id'] ?? 0);
        $response_text = sanitize_textarea_field($_POST['response_text'] ?? '');
        
        // Debug logging
        error_log('WC AI: Approve request - review_id: ' . $review_id . ', response_id: ' . $response_id . ', response_text length: ' . strlen($response_text));
        error_log('WC AI: POST data: ' . print_r($_POST, true));
        error_log('WC AI: Nonce received: ' . ($_POST['nonce'] ?? 'NOT SET'));
        error_log('WC AI: Nonce expected: ' . wp_create_nonce('wc_ai_nonce'));
        
        // If response_id is provided, get the review_id from the response
        if ($response_id && !$review_id) {
            $response = $this->database->get_response($response_id);
            if ($response) {
                $review_id = intval($response['review_id']);
                error_log('WC AI: Found review_id from response: ' . $review_id);
            } else {
                error_log('WC AI: Response not found for ID: ' . $response_id);
                wp_send_json_error(__('Response not found.', 'wc-autoresponder-ai'));
                return;
            }
        }
        
        if (!$review_id) {
            wp_send_json_error(__('Invalid review ID provided.', 'wc-autoresponder-ai'));
        }
        
        // If no response_text provided, get it from the database
        if (!$response_text && $response_id) {
            $response = $this->database->get_response($response_id);
            if ($response) {
                $response_text = $response['response_text'];
                error_log('WC AI: Retrieved response_text from response_id: ' . strlen($response_text) . ' chars');
            } else {
                error_log('WC AI: No response found for response_id: ' . $response_id);
            }
        }
        
        // If still no response_text, try to get it from the latest response for this review
        if (!$response_text && $review_id) {
            $responses = $this->database->get_responses_by_review($review_id);
            if (!empty($responses)) {
                $response_text = $responses[0]['response_text'];
                error_log('WC AI: Retrieved response_text from review_id: ' . strlen($response_text) . ' chars');
            } else {
                error_log('WC AI: No responses found for review_id: ' . $review_id);
            }
        }
        
        if (!$response_text) {
            error_log('WC AI: No response text found - review_id: ' . $review_id . ', response_id: ' . $response_id);
            wp_send_json_error(__('No response text found in database.', 'wc-autoresponder-ai'));
        }
        
        try {
            // Use the new method that works with response_id directly
            if ($response_id) {
                $result = $this->review_processor->approve_response_by_id($response_id, $response_text);
            } else {
                $result = $this->review_processor->approve_response($review_id, $response_text);
            }
            
            if ($result) {
                wp_send_json_success([
                    'message' => __('Response approved and published!', 'wc-autoresponder-ai')
                ]);
            } else {
                wp_send_json_error(__('Failed to approve response.', 'wc-autoresponder-ai'));
            }
        } catch (\Exception $e) {
            error_log('WC AI: Approve response error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_reject_response(): void
    {
        check_ajax_referer('wc_ai_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'wc-autoresponder-ai'));
        }
        
        // Accept both review_id and response_id for flexibility
        $review_id = intval($_POST['review_id'] ?? 0);
        $response_id = intval($_POST['response_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        // If response_id is provided, get the review_id from the response
        if ($response_id && !$review_id) {
            $response = $this->database->get_response($response_id);
            if ($response) {
                $review_id = intval($response['review_id']);
            }
        }
        
        if (!$review_id) {
            wp_send_json_error(__('Invalid review ID provided.', 'wc-autoresponder-ai'));
        }
        
        try {
            $result = $this->review_processor->reject_response($review_id, $reason);
            
            if ($result) {
                wp_send_json_success([
                    'message' => __('Response rejected.', 'wc-autoresponder-ai')
                ]);
            } else {
                wp_send_json_error(__('Failed to reject response.', 'wc-autoresponder-ai'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function include_ai_comments_in_reviews(array $args): array
    {
        // Ensure AI-generated comments are included in review queries
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }
        
        // Add OR condition to include AI-generated comments
        $args['meta_query']['relation'] = 'OR';
        $args['meta_query'][] = [
            'key' => 'ai_generated',
            'value' => '1',
            'compare' => '='
        ];
        
        return $args;
    }
    
    public function ensure_ai_comments_display(\WP_Comment $comment): void
    {
        // Check if this is an AI-generated comment
        $is_ai_generated = get_comment_meta($comment->comment_ID, 'ai_generated', true);
        
        if ($is_ai_generated) {
            // Add a small indicator that this is an AI-generated response
            echo '<div class="ai-comment-indicator" style="font-size: 0.8em; color: #666; margin-top: 5px; font-style: italic;">' . 
                 __('[AI Response]', 'wc-autoresponder-ai') . '</div>';
        }
    }
    
    public function display_ai_comment_attribution(\WP_Comment $comment): void
    {
        // Check if this is an AI-generated comment
        $is_ai_generated = get_comment_meta($comment->comment_ID, 'ai_generated', true);
        
        // if ($is_ai_generated) {
        //     $ai_provider = get_comment_meta($comment->comment_ID, 'ai_provider', true);
        //     $ai_model = get_comment_meta($comment->comment_ID, 'ai_model', true);
            
        //     echo '<div class="ai-comment-attribution" style="font-size: 0.8em; color: #666; margin-top: 5px;">';
        //     echo '<em>' . __('Response generated with AI assistance', 'wc-autoresponder-ai');
        //     if ($ai_provider && $ai_model) {
        //         echo ' (' . esc_html(ucfirst($ai_provider)) . ' - ' . esc_html($ai_model) . ')';
        //     }
        //     echo '</em>';
        //     echo '</div>';
        // }
    }
    
    public function ajax_feedback_response(): void
    {
        check_ajax_referer('wc_ai_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'wc-autoresponder-ai'));
        }
        
        $response_id = intval($_POST['response_id'] ?? 0);
        $feedback = sanitize_text_field($_POST['feedback'] ?? '');
        
        if (!$response_id || !in_array($feedback, ['positive', 'negative'])) {
            wp_send_json_error(__('Invalid feedback data.', 'wc-autoresponder-ai'));
        }
        
        try {
            $result = $this->database->record_feedback($response_id, $feedback);
            
            if ($result) {
                wp_send_json_success([
                    'message' => __('Feedback recorded successfully!', 'wc-autoresponder-ai')
                ]);
            } else {
                wp_send_json_error(__('Failed to record feedback.', 'wc-autoresponder-ai'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_test(): void
    {
        error_log('WC AI: Test AJAX called');
        wp_send_json_success(['message' => 'Test successful', 'time' => current_time('mysql')]);
    }
    
    public function ajax_trigger_cron(): void
    {
        check_ajax_referer('wc_ai_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'wc-autoresponder-ai'));
        }
        
        try {
            // Manually trigger the cron job
            $this->cron->process_review_queue();
            
            // Get queue status
            $queue = get_transient('wc_ai_review_queue') ?: [];
            
            wp_send_json_success([
                'message' => __('Cron job triggered successfully!', 'wc-autoresponder-ai'),
                'queue_count' => count($queue),
                'time' => current_time('mysql')
            ]);
        } catch (\Exception $e) {
            error_log('WC AI: Cron trigger error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
}
