<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI;

/**
 * Handles review processing and AI response generation
 */
class ReviewProcessor
{
    private AIProviderManager $ai_provider_manager;
    private Database $database;
    private Settings $settings;
    
    public function __construct(AIProviderManager $ai_provider_manager)
    {
        $this->ai_provider_manager = $ai_provider_manager;
        $this->database = new Database();
        $this->settings = new Settings();
    }
    
    public function generate_response(int $review_id): ?string
    {
        try {
            // Get review data
            $review = $this->get_review_data($review_id);
            if (!$review) {
                throw new \Exception(__('Review not found.', 'wc-autoresponder-ai'));
            }
            
            // Check if review should be processed (skip for manual generation)
            if (!$this->should_process_review($review)) {
                error_log('WC AI: Review should not be processed, using fallback response');
                $fallback_text = $this->get_fallback_response($review);
                
                // Save fallback response to database
                $response_id = $this->database->save_response(
                    $review_id,
                    $review['product_id'],
                    $fallback_text,
                    'fallback',
                    'fallback',
                    0
                );
                
                // Handle workflow mode for fallback responses too
                $workflow_mode = $this->settings->get_workflow_mode();
                
                switch ($workflow_mode) {
                    case 'auto':
                        $this->publish_response($response_id);
                        break;
                    case 'semi_auto':
                        // Create comment but keep it pending for approval
                        $this->create_pending_comment($response_id);
                        break;
                    case 'draft':
                        // Response saved as draft
                        break;
                }
                
                return $fallback_text;
            }
            
            // Get product data
            $product_data = $this->get_product_data($review['product_id']);
            
            // Get review history for tone learning
            $review_history = $this->get_review_history($review['product_id']);
            
            // Build prompt
            $prompt = $this->build_prompt($review, $product_data, $review_history);
            
            // Generate AI response
            error_log('WC AI: Calling AI provider manager with prompt length: ' . strlen($prompt));
            $ai_result = $this->ai_provider_manager->generate_response($prompt, [
                'product_data' => $product_data,
                'review_history' => $review_history
            ]);
            
            error_log('WC AI: AI result: ' . print_r($ai_result, true));
            
            if (!$ai_result['success']) {
                error_log('WC AI: AI generation failed: ' . ($ai_result['message'] ?? 'Unknown error'));
                throw new \Exception($ai_result['message'] ?? __('Failed to generate AI response.', 'wc-autoresponder-ai'));
            }
            
            // Post-process the response to clean it up
            $cleaned_response = $this->post_process_response($ai_result['response']);
            
            // Save response to database
            $response_id = $this->database->save_response(
                $review_id,
                $review['product_id'],
                $cleaned_response,
                $ai_result['provider'],
                $ai_result['model'],
                $ai_result['generation_time']
            );
            
            // Handle workflow mode
            $workflow_mode = $this->settings->get_workflow_mode();
            
            switch ($workflow_mode) {
                case 'auto':
                    $this->publish_response($response_id);
                    break;
                case 'semi_auto':
                    // Create comment but keep it pending for approval
                    $this->create_pending_comment($response_id);
                    break;
                case 'draft':
                    // Response saved as draft
                    break;
            }
            
            return $cleaned_response;
            
        } catch (\Exception $e) {
            error_log('WC AI: Review processing failed: ' . $e->getMessage());
            
            // Log the error
            $this->database->log_action('review_processing_error', $review_id, null, [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    public function approve_response(int $review_id, string $response_text): bool
    {
        try {
            error_log('WC AI: approve_response called with review_id: ' . $review_id . ', response_text length: ' . strlen($response_text));
            
            // Get the latest response for this review
            $responses = $this->database->get_responses_by_review($review_id);
            error_log('WC AI: Found ' . count($responses) . ' responses for review_id: ' . $review_id);
            
            if (empty($responses)) {
                error_log('WC AI: No responses found for review_id: ' . $review_id);
                throw new \Exception(__('No AI response found for this review.', 'wc-autoresponder-ai'));
            }
            
            $response = $responses[0]; // Get the most recent response
            error_log('WC AI: Using response ID: ' . $response['id'] . ' with status: ' . $response['status']);
            
            // Update response status
            $update_result = $this->database->update_response_status($response['id'], 'approved', get_current_user_id());
            error_log('WC AI: Update response status result: ' . ($update_result ? 'success' : 'failed'));
            
            // Publish the response as a comment
            $publish_result = $this->publish_response($response['id'], $response_text);
            error_log('WC AI: Publish response result: ' . ($publish_result ? 'success' : 'failed'));
            
            return $update_result && $publish_result;
            
        } catch (\Exception $e) {
            error_log('WC AI: Response approval failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function approve_response_by_id(int $response_id, string $response_text = null): bool
    {
        try {
            error_log('WC AI: approve_response_by_id called with response_id: ' . $response_id);
            
            // Get the response from database
            $response = $this->database->get_response($response_id);
            if (!$response) {
                error_log('WC AI: Response not found for ID: ' . $response_id);
                throw new \Exception(__('Response not found.', 'wc-autoresponder-ai'));
            }
            
            error_log('WC AI: Found response with review_id: ' . $response['review_id'] . ', status: ' . $response['status']);
            
            // Use provided text or response text from database
            $text_to_use = $response_text ?: $response['response_text'];
            
            // Update response status
            $update_result = $this->database->update_response_status($response_id, 'approved', get_current_user_id());
            error_log('WC AI: Update response status result: ' . ($update_result ? 'success' : 'failed'));
            
            // Publish the response as a comment
            $publish_result = $this->publish_response($response_id, $text_to_use);
            error_log('WC AI: Publish response result: ' . ($publish_result ? 'success' : 'failed'));
            
            return $update_result && $publish_result;
            
        } catch (\Exception $e) {
            error_log('WC AI: Response approval by ID failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function reject_response(int $review_id, string $reason = ''): bool
    {
        try {
            // Get the latest response for this review
            $responses = $this->database->get_responses_by_review($review_id);
            if (empty($responses)) {
                throw new \Exception(__('No AI response found for this review.', 'wc-autoresponder-ai'));
            }
            
            $response = $responses[0]; // Get the most recent response
            
            // Update response status
            $this->database->update_response_status($response['id'], 'rejected', get_current_user_id(), $reason);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('WC AI: Response rejection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    private function get_review_data(int $review_id): ?array
    {
        // Use WordPress comment functions instead of direct database queries for HPOS compatibility
        $comment = get_comment($review_id);
        
        // WooCommerce reviews might have empty comment_type or be 'review'
        if (!$comment || (!empty($comment->comment_type) && $comment->comment_type !== 'review')) {
            return null;
        }
        
        // Get product data using WooCommerce CRUD API
        $product = wc_get_product($comment->comment_post_ID);
        if (!$product) {
            return null;
        }
        
        $review = [
            'comment_ID' => $comment->comment_ID,
            'comment_post_ID' => $comment->comment_post_ID,
            'comment_author' => $comment->comment_author,
            'comment_author_email' => $comment->comment_author_email,
            'comment_content' => $comment->comment_content,
            'comment_date' => $comment->comment_date,
            'comment_approved' => $comment->comment_approved,
            'product_id' => $product->get_id(),
            'product_title' => $product->get_name()
        ];
        
        // Get rating if available using WooCommerce review functions
        $rating = get_comment_meta($review_id, 'rating', true);
        $review['rating'] = $rating ? intval($rating) : 0;
        
        return $review;
    }
    
    private function should_process_review(array $review): bool
    {
        $filters = $this->settings->get_option('review_filters', []);
        
        // Check rating range
        if ($review['rating'] > 0) {
            $min_rating = $filters['min_rating'] ?? 1;
            $max_rating = $filters['max_rating'] ?? 5;
            
            if ($review['rating'] < $min_rating || $review['rating'] > $max_rating) {
                error_log('WC AI: Review rating out of range: ' . $review['rating']);
                return false;
            }
        }
        
        // Check for spam
        if ($filters['exclude_spam'] ?? true) {
            if ($this->is_spam_review($review)) {
                error_log('WC AI: Review detected as spam');
                return false;
            }
        }
        
        // Check for negative-only reviews
        if ($filters['exclude_negative_only'] ?? false) {
            if ($review['rating'] <= 2 && $this->is_negative_only_review($review)) {
                error_log('WC AI: Review detected as negative-only');
                return false;
            }
        }
        
        // Check for questions
        if ($filters['exclude_questions'] ?? false) {
            if ($this->is_question_review($review)) {
                error_log('WC AI: Review detected as question');
                return false;
            }
        }
        
        return true;
    }
    
    private function is_spam_review(array $review): bool
    {
        // Basic spam detection - be more lenient for manual generation
        $content = strtolower($review['comment_content']);
        
        $spam_indicators = [
            'buy now', 'click here', 'free money', 'make money',
            'viagra', 'casino', 'poker', 'lottery'
        ];
        
        foreach ($spam_indicators as $indicator) {
            if (strpos($content, $indicator) !== false) {
                error_log('WC AI: Spam indicator found: ' . $indicator);
                return true;
            }
        }
        
        // Check for excessive links
        if (substr_count($content, 'http') > 2) {
            return true;
        }
        
        // Check for excessive repetition
        $words = explode(' ', $content);
        $word_counts = array_count_values($words);
        foreach ($word_counts as $count) {
            if ($count > 5) {
                return true;
            }
        }
        
        return false;
    }
    
    private function is_negative_only_review(array $review): bool
    {
        $content = strtolower($review['comment_content']);
        
        $negative_words = [
            'terrible', 'awful', 'horrible', 'worst', 'disappointed',
            'waste', 'useless', 'broken', 'defective', 'scam'
        ];
        
        $negative_count = 0;
        foreach ($negative_words as $word) {
            if (strpos($content, $word) !== false) {
                $negative_count++;
            }
        }
        
        // Be more lenient - require at least 3 negative words
        return $negative_count >= 3;
    }
    
    private function is_question_review(array $review): bool
    {
        $content = $review['comment_content'];
        
        // Only check for question marks and specific question patterns
        $question_indicators = ['?', 'how do', 'what is', 'when will', 'where can', 'why does', 'can i get', 'is it possible'];
        
        foreach ($question_indicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_product_data(int $product_id): string
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }
        
        $data = [];
        $fields = $this->settings->get_product_fields();
        
        if ($fields['title'] ?? true) {
            $data[] = __('Product:', 'wc-autoresponder-ai') . ' ' . $product->get_name();
        }
        
        if ($fields['description'] ?? true) {
            $description = $product->get_description();
            if ($description) {
                $data[] = __('Description:', 'wc-autoresponder-ai') . ' ' . wp_strip_all_tags($description);
            }
        }
        
        if ($fields['short_description'] ?? true) {
            $short_description = $product->get_short_description();
            if ($short_description) {
                $data[] = __('Short Description:', 'wc-autoresponder-ai') . ' ' . wp_strip_all_tags($short_description);
            }
        }
        
        if ($fields['attributes'] ?? true) {
            $attributes = $product->get_attributes();
            if (!empty($attributes)) {
                $attr_data = [];
                foreach ($attributes as $attribute) {
                    if ($attribute->is_taxonomy()) {
                        $terms = wp_get_post_terms($product_id, $attribute->get_name());
                        if (!empty($terms)) {
                            $attr_data[] = $attribute->get_name() . ': ' . implode(', ', wp_list_pluck($terms, 'name'));
                        }
                    } else {
                        $attr_data[] = $attribute->get_name() . ': ' . $attribute->get_options();
                    }
                }
                if (!empty($attr_data)) {
                    $data[] = __('Attributes:', 'wc-autoresponder-ai') . ' ' . implode(', ', $attr_data);
                }
            }
        }
        
        if ($fields['sku'] ?? false) {
            $sku = $product->get_sku();
            if ($sku) {
                $data[] = __('SKU:', 'wc-autoresponder-ai') . ' ' . $sku;
            }
        }
        
        if ($fields['price'] ?? false) {
            $price = $product->get_price_html();
            if ($price) {
                $data[] = __('Price:', 'wc-autoresponder-ai') . ' ' . wp_strip_all_tags($price);
            }
        }
        
        if ($fields['categories'] ?? true) {
            $categories = wp_get_post_terms($product_id, 'product_cat');
            if (!empty($categories)) {
                $data[] = __('Categories:', 'wc-autoresponder-ai') . ' ' . implode(', ', wp_list_pluck($categories, 'name'));
            }
        }
        
        if ($fields['tags'] ?? true) {
            $tags = wp_get_post_terms($product_id, 'product_tag');
            if (!empty($tags)) {
                $data[] = __('Tags:', 'wc-autoresponder-ai') . ' ' . implode(', ', wp_list_pluck($tags, 'name'));
            }
        }
        
        return implode("\n", $data);
    }
    
    private function get_review_history(int $product_id, int $limit = 5): string
    {
        // Use WordPress comment functions instead of direct database queries for HPOS compatibility
        $comments = get_comments([
            'post_id' => $product_id,
            'type' => 'review',
            'status' => 'approve',
            'number' => $limit,
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ]);
        
        if (empty($comments)) {
            return '';
        }
        
        $history = [];
        foreach ($comments as $comment) {
            $history[] = sprintf(
                __('Review by %s on %s:', 'wc-autoresponder-ai'),
                $comment->comment_author,
                $comment->comment_date
            ) . "\n" . $comment->comment_content;
        }
        
        return implode("\n\n", $history);
    }
    
    private function build_prompt(array $review, string $product_data, string $review_history): string
    {
        $max_length = $this->settings->get_option('advanced_settings.max_response_length', 500);
        $tone_style = $this->settings->get_tone_style();
        
        $prompt = sprintf(
            __('You are a helpful customer service representative. Write a concise and friendly response to this product review. Keep your response under %d characters and be direct without trailing dots or ellipsis.\n\nReview: %s\n\nRating: %d/5 stars', 'wc-autoresponder-ai'),
            $max_length,
            $review['comment_content'],
            $review['rating']
        );
        
        // Add tone instructions
        $tone_instructions = $this->get_tone_instructions($tone_style);
        $prompt .= "\n\n" . $tone_instructions;
        
        // Add product data if needed but keep it brief
        if ($product_data) {
            $prompt .= "\n\n" . __('Product information:', 'wc-autoresponder-ai') . "\n" . $this->summarize_product_data($product_data);
        }
        
        // Add brief review history if available
        if ($review_history) {
            $prompt .= "\n\n" . __('Recent customer feedback:', 'wc-autoresponder-ai') . "\n" . $this->summarize_review_history($review_history);
        }
        
        // Add final instructions
        $prompt .= "\n\n" . __('Instructions: Write a helpful, concise response. Do not end with dots or ellipsis. Be professional and friendly. Focus on addressing the customer\'s specific concerns or thanking them for their feedback.', 'wc-autoresponder-ai');
        
        return $prompt;
    }
    
    private function get_tone_instructions(string $tone_style): string
    {
        switch ($tone_style) {
            case 'professional':
                return __('Tone: Professional and formal. Use respectful language and maintain a business-appropriate tone.', 'wc-autoresponder-ai');
            case 'friendly':
                return __('Tone: Warm and friendly. Use casual but respectful language. Be personable and approachable.', 'wc-autoresponder-ai');
            case 'casual':
                return __('Tone: Casual and relaxed. Use informal but polite language. Be conversational.', 'wc-autoresponder-ai');
            case 'technical':
                return __('Tone: Technical and precise. Focus on facts and specifications. Be informative and detailed.', 'wc-autoresponder-ai');
            case 'promotional':
                return __('Tone: Enthusiastic and promotional. Highlight benefits and positive aspects. Be encouraging.', 'wc-autoresponder-ai');
            default:
                return __('Tone: Professional and helpful. Be respectful and informative.', 'wc-autoresponder-ai');
        }
    }
    
    private function summarize_product_data(string $product_data): string
    {
        // Limit product data to essential information only
        $lines = explode("\n", $product_data);
        $summary = [];
        $max_lines = 3;
        
        foreach ($lines as $line) {
            if (count($summary) >= $max_lines) break;
            if (strpos($line, 'Product:') === 0 || strpos($line, 'Description:') === 0) {
                $summary[] = $line;
            }
        }
        
        return implode("\n", $summary);
    }
    
    private function summarize_review_history(string $review_history): string
    {
        // Limit review history to most recent and relevant
        $reviews = explode("\n\n", $review_history);
        $summary = [];
        $max_reviews = 2;
        
        foreach ($reviews as $review) {
            if (count($summary) >= $max_reviews) break;
            $lines = explode("\n", $review);
            if (count($lines) >= 2) {
                $summary[] = $lines[0] . "\n" . substr($lines[1], 0, 100) . (strlen($lines[1]) > 100 ? '...' : '');
            }
        }
        
        return implode("\n\n", $summary);
    }
    
    private function post_process_response(string $response): string
    {
        // Remove trailing dots and ellipsis
        $response = rtrim($response, '.…');
        
        // Remove multiple consecutive dots
        $response = preg_replace('/\.{2,}/', '.', $response);
        
        // Remove trailing ellipsis
        $response = rtrim($response, '…');
        
        // Limit response length if it's too long
        $max_length = $this->settings->get_option('advanced_settings.max_response_length', 500);
        if (strlen($response) > $max_length) {
            $response = substr($response, 0, $max_length);
            
            // Find the last complete sentence
            $last_period = strrpos($response, '.');
            if ($last_period !== false && $last_period > $max_length * 0.8) {
                $response = substr($response, 0, $last_period + 1);
            } else {
                // If no complete sentence found, add a proper ending
                $response = rtrim($response) . '.';
            }
        }
        
        // Clean up extra whitespace
        $response = preg_replace('/\s+/', ' ', $response);
        $response = trim($response);
        
        // Ensure it ends properly (no trailing punctuation issues)
        $response = rtrim($response, '.,;:…');
        if (!empty($response) && !preg_match('/[.!?]$/', $response)) {
            $response .= '.';
        }
        
        return $response;
    }
    
    private function get_fallback_response(array $review): string
    {
        $templates = [
            'متشکریم از نظرات شما. بازخورد شما برای بهبود خدمات ما بسیار ارزشمند است',
            'از وقتی که برای ارسال نظر گذاشتید متشکریم. نظرات شما به ما در ارائه خدمات بهتر کمک می‌کند',
            'نظر شما برای ما مهم است و از آن برای بهبود محصولات و خدمات استفاده خواهیم کرد'
        ];
        
        return $templates[array_rand($templates)];
    }
    
    private function publish_response(int $response_id, string $custom_text = null): bool
    {
        try {
            error_log('WC AI: publish_response called with response_id: ' . $response_id);
            
            $response = $this->database->get_response($response_id);
            if (!$response) {
                error_log('WC AI: Response not found for ID: ' . $response_id);
                throw new \Exception(__('Response not found.', 'wc-autoresponder-ai'));
            }
            
            $review = $this->get_review_data(intval($response['review_id']));
            if (!$review) {
                error_log('WC AI: Review not found for ID: ' . $response['review_id']);
                throw new \Exception(__('Review not found.', 'wc-autoresponder-ai'));
            }
            
            $response_text = $custom_text ?: $response['response_text'];
            error_log('WC AI: Response text length: ' . strlen($response_text));
            
            // Add AI attribution if enabled
            if ($this->settings->get_option('advanced_settings.include_ai_attribution', false)) {
                $response_text .= "\n\n" . __('[Response generated with AI assistance]', 'wc-autoresponder-ai');
            }
            
            // Create comment reply
            $workflow_mode = $this->settings->get_workflow_mode();
            $comment_approved = ($workflow_mode === 'auto') ? 1 : 0; // Pending in semi_auto and draft modes
            
            $comment_data = [
                'comment_post_ID' => $review['product_id'],
                'comment_author' => get_bloginfo('name'),
                'comment_author_email' => get_option('admin_email'),
                'comment_author_url' => home_url(),
                'comment_content' => $response_text,
                'comment_parent' => $response['review_id'],
                'comment_approved' => $comment_approved,
                'comment_type' => '', // Ensure it's treated as a regular comment
                'comment_meta' => [
                    'ai_generated' => true,
                    'ai_provider' => $response['ai_provider'],
                    'ai_model' => $response['model_used'],
                    'response_id' => $response_id
                ]
            ];
            
            error_log('WC AI: Comment data: ' . print_r($comment_data, true));
            
            $comment_id = wp_insert_comment($comment_data);
            error_log('WC AI: wp_insert_comment result: ' . ($comment_id ? $comment_id : 'FAILED'));
            
            if (!$comment_id) {
                error_log('WC AI: wp_insert_comment failed');
                throw new \Exception(__('Failed to publish response comment.', 'wc-autoresponder-ai'));
            }
            
            // Add comment meta after successful comment creation
            add_comment_meta($comment_id, 'ai_generated', true);
            add_comment_meta($comment_id, 'ai_provider', $response['ai_provider']);
            add_comment_meta($comment_id, 'ai_model', $response['model_used']);
            add_comment_meta($comment_id, 'response_id', $response_id);
            
            // Set comment status based on workflow mode
            if ($workflow_mode === 'auto') {
                wp_set_comment_status($comment_id, 'approve');
            } else {
                wp_set_comment_status($comment_id, 'hold'); // Keep as pending
            }
            
            // Update response status
            $update_result = $this->database->update_response_status($response_id, 'published', get_current_user_id());
            error_log('WC AI: Update response status to published: ' . ($update_result ? 'success' : 'failed'));
            
            // Log the action
            $this->database->log_action('response_published', intval($response['review_id']), $response_id, [
                'comment_id' => $comment_id
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            error_log('WC AI: Response publishing failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a pending comment for AI response in semi_auto mode
     */
    private function create_pending_comment(int $response_id): bool
    {
        try {
            error_log('WC AI: create_pending_comment called with response_id: ' . $response_id);
            
            $response = $this->database->get_response($response_id);
            if (!$response) {
                error_log('WC AI: Response not found for ID: ' . $response_id);
                return false;
            }
            
            $review = $this->get_review_data(intval($response['review_id']));
            if (!$review) {
                error_log('WC AI: Review not found for ID: ' . $response['review_id']);
                return false;
            }
            
            // Check if comment already exists for this response
            $existing_comments = get_comments([
                'meta_key' => 'response_id',
                'meta_value' => $response_id,
                'number' => 1
            ]);
            
            if (!empty($existing_comments)) {
                error_log('WC AI: Comment already exists for response ID: ' . $response_id);
                return true;
            }
            
            $response_text = $response['response_text'];
            
            // Add AI attribution if enabled
            if ($this->settings->get_option('advanced_settings.include_ai_attribution', false)) {
                $response_text .= "\n\n" . __('[Response generated with AI assistance]', 'wc-autoresponder-ai');
            }
            
            // Create comment as pending
            $comment_data = [
                'comment_post_ID' => $review['product_id'],
                'comment_author' => get_bloginfo('name'),
                'comment_author_email' => get_option('admin_email'),
                'comment_author_url' => home_url(),
                'comment_content' => $response_text,
                'comment_parent' => $response['review_id'],
                'comment_approved' => 0, // Pending
                'comment_type' => '', // Ensure it's treated as a regular comment
            ];
            
            error_log('WC AI: Creating pending comment with data: ' . print_r($comment_data, true));
            
            $comment_id = wp_insert_comment($comment_data);
            error_log('WC AI: wp_insert_comment result: ' . ($comment_id ? $comment_id : 'FAILED'));
            
            if (!$comment_id) {
                error_log('WC AI: wp_insert_comment failed');
                return false;
            }
            
            // Add comment meta
            add_comment_meta($comment_id, 'ai_generated', true);
            add_comment_meta($comment_id, 'ai_provider', $response['ai_provider']);
            add_comment_meta($comment_id, 'ai_model', $response['model_used']);
            add_comment_meta($comment_id, 'response_id', $response_id);
            
            // Ensure comment is pending
            wp_set_comment_status($comment_id, 'hold');
            
            // Update response status to pending
            $this->database->update_response_status($response_id, 'pending', get_current_user_id());
            
            // Log the action
            $this->database->log_action('ai_comment_created', intval($response['review_id']), $response_id, [
                'comment_id' => $comment_id,
                'status' => 'pending'
            ]);
            
            error_log('WC AI: Successfully created pending comment ID: ' . $comment_id);
            return true;
            
        } catch (\Exception $e) {
            error_log('WC AI: create_pending_comment failed: ' . $e->getMessage());
            return false;
        }
    }
}
