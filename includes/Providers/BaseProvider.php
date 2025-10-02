<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI\Providers;

use WC_AutoResponder_AI\Settings;

/**
 * Base class for AI providers
 */
abstract class BaseProvider
{
    protected Settings $settings;
    protected string $api_key;
    protected string $model_name;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->api_key = $this->get_api_key();
        error_log('WC AI: BaseProvider constructor - API key length: ' . strlen($this->api_key));
    }
    
    abstract protected function get_api_key(): string;
    abstract public function generate_response(string $prompt, array $context = []): string;
    abstract public function get_model_name(): string;
    
    public function is_available(): bool
    {
        return !empty($this->api_key) && $this->settings->is_external_data_allowed();
    }
    
    protected function make_api_request(string $url, array $data, array $headers = []): array
    {
        $default_headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'WooCommerce-AutoResponder-AI/' . WC_AUTORESPONDER_AI_VERSION
        ];
        
        $headers = array_merge($default_headers, $headers);
        
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => wp_json_encode($data),
            'timeout' => 30,
            'sslverify' => true
        ];
        
        error_log('WC AI: Making wp_remote_request to: ' . $url);
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('WC AI: wp_remote_request error: ' . $response->get_error_message());
            throw new \Exception($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('WC AI: API response status: ' . $status_code);
        error_log('WC AI: API response body: ' . $body);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = $error_data['error']['message'] ?? $error_data['message'] ?? __('API request failed.', 'wc-autoresponder-ai');
            error_log('WC AI: API error: ' . $error_message);
            throw new \Exception(sprintf(__('API Error (%d): %s', 'wc-autoresponder-ai'), $status_code, $error_message));
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WC AI: JSON decode error: ' . json_last_error_msg());
            throw new \Exception(__('Invalid JSON response from API.', 'wc-autoresponder-ai'));
        }
        
        error_log('WC AI: JSON decoded successfully');
        return $data;
    }
    
    protected function sanitize_response(string $response): string
    {
        error_log('WC AI: sanitize_response called with length: ' . strlen($response));
        
        // Remove any potential harmful content
        $response = wp_strip_all_tags($response);
        $response = trim($response);
        
        // Limit response length
        $max_length = $this->settings->get_option('advanced_settings.max_response_length', 500);
        if (strlen($response) > $max_length) {
            $response = substr($response, 0, $max_length - 3) . '...';
        }
        
        error_log('WC AI: sanitized response length: ' . strlen($response));
        return $response;
    }
    
    protected function build_prompt(string $base_prompt, array $context): string
    {
        error_log('WC AI: build_prompt called with base_prompt length: ' . strlen($base_prompt));
        
        $prompt = $base_prompt;
        
        // Add tone instruction
        $tone = $this->settings->get_tone_style();
        $tone_instructions = [
            'professional' => __('Use a professional and formal tone.', 'wc-autoresponder-ai'),
            'friendly' => __('Use a friendly and approachable tone.', 'wc-autoresponder-ai'),
            'casual' => __('Use a casual and conversational tone.', 'wc-autoresponder-ai'),
            'technical' => __('Use a technical and detailed tone.', 'wc-autoresponder-ai'),
            'promotional' => __('Use a promotional and sales-focused tone.', 'wc-autoresponder-ai')
        ];
        
        if (isset($tone_instructions[$tone])) {
            $prompt .= "\n\n" . $tone_instructions[$tone];
        }
        
        // Add context information
        if (!empty($context['product_data'])) {
            $prompt .= "\n\n" . __('Product Information:', 'wc-autoresponder-ai') . "\n" . $context['product_data'];
        }
        
        if (!empty($context['review_history'])) {
            $prompt .= "\n\n" . __('Previous Review Responses (for tone reference):', 'wc-autoresponder-ai') . "\n" . $context['review_history'];
        }
        
        // Add response guidelines
        $prompt .= "\n\n" . __('Response Guidelines:', 'wc-autoresponder-ai');
        $prompt .= "\n- " . __('Keep the response helpful and relevant to the review.', 'wc-autoresponder-ai');
        $prompt .= "\n- " . __('Do not include personal information about the customer.', 'wc-autoresponder-ai');
        $prompt .= "\n- " . __('If the review is spam or inappropriate, provide a polite generic response.', 'wc-autoresponder-ai');
        $prompt .= "\n- " . __('If you cannot provide a helpful response, suggest contacting customer support.', 'wc-autoresponder-ai');
        
        if ($this->settings->get_option('advanced_settings.include_product_links', true)) {
            $prompt .= "\n- " . __('Include relevant product links when appropriate.', 'wc-autoresponder-ai');
        }
        
        if ($this->settings->get_option('advanced_settings.include_contact_info', true)) {
            $prompt .= "\n- " . __('Include contact information for further assistance.', 'wc-autoresponder-ai');
        }
        
        error_log('WC AI: Final prompt length: ' . strlen($prompt));
        return $prompt;
    }
}
