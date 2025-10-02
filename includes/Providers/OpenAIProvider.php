<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI\Providers;

/**
 * OpenAI GPT provider implementation
 */
class OpenAIProvider extends BaseProvider
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    
    protected function get_api_key(): string
    {
        return $this->settings->get_api_key('openai');
    }
    
    private function get_model(): string
    {
        return $this->settings->get_ai_model('openai') ?: 'gpt-3.5-turbo';
    }
    
    public function generate_response(string $prompt, array $context = []): string
    {
        error_log('WC AI: OpenAI generate_response called');
        
        if (!$this->is_available()) {
            error_log('WC AI: OpenAI not available');
            throw new \Exception(__('OpenAI API key is not configured or external data sharing is disabled.', 'wc-autoresponder-ai'));
        }
        
        error_log('WC AI: OpenAI is available, building prompt');
        $full_prompt = $this->build_prompt($prompt, $context);
        
        $data = [
            'model' => $this->get_model(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => __('You are a helpful customer service representative for an e-commerce store. You respond to product reviews with helpful, professional, and brand-appropriate messages.', 'wc-autoresponder-ai')
                ],
                [
                    'role' => 'user',
                    'content' => $full_prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key
        ];
        
        error_log('WC AI: Making API request to OpenAI');
        $response = $this->make_api_request(self::API_URL, $data, $headers);
        
        error_log('WC AI: OpenAI API response: ' . print_r($response, true));
        
        if (!isset($response['choices'][0]['message']['content'])) {
            error_log('WC AI: Invalid response format from OpenAI API');
            throw new \Exception(__('Invalid response format from OpenAI API.', 'wc-autoresponder-ai'));
        }
        
        $raw_response = $response['choices'][0]['message']['content'];
        error_log('WC AI: Raw response from OpenAI: ' . $raw_response);
        
        return $this->sanitize_response($raw_response);
    }
    
    public function get_model_name(): string
    {
        return $this->get_model();
    }
    
    public function is_available(): bool
    {
        $has_api_key = !empty($this->api_key);
        $external_data_allowed = $this->settings->is_external_data_allowed();
        
        error_log('WC AI: OpenAI is_available check - API key: ' . ($has_api_key ? 'yes' : 'no') . ', external data: ' . ($external_data_allowed ? 'yes' : 'no'));
        
        return $has_api_key && $external_data_allowed;
    }
    
    private function test_connection(): bool
    {
        try {
            $data = [
                'model' => $this->get_model(),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Test'
                    ]
                ],
                'max_tokens' => 1
            ];
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->api_key
            ];
            
            $response = $this->make_api_request(self::API_URL, $data, $headers);
            
            return isset($response['choices']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
