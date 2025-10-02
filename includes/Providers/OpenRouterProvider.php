<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI\Providers;

/**
 * OpenRouter provider implementation
 */
class OpenRouterProvider extends BaseProvider
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    
    protected function get_api_key(): string
    {
        return $this->settings->get_api_key('openrouter');
    }
    
    private function get_model(): string
    {
        return $this->settings->get_ai_model('openrouter') ?: 'openai/gpt-3.5-turbo';
    }
    
    public function generate_response(string $prompt, array $context = []): string
    {
        if (!$this->is_available()) {
            throw new \Exception(__('OpenRouter API key is not configured or external data sharing is disabled.', 'wc-autoresponder-ai'));
        }
        
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
            'Authorization' => 'Bearer ' . $this->api_key,
            'HTTP-Referer' => home_url(),
            'X-Title' => get_bloginfo('name')
        ];
        
        $response = $this->make_api_request(self::API_URL, $data, $headers);
        
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new \Exception(__('Invalid response format from OpenRouter API.', 'wc-autoresponder-ai'));
        }
        
        $raw_response = $response['choices'][0]['message']['content'];
        
        return $this->sanitize_response($raw_response);
    }
    
    public function get_model_name(): string
    {
        return $this->get_model();
    }
    
    public function is_available(): bool
    {
        return !empty($this->api_key) && 
               $this->settings->is_external_data_allowed() &&
               $this->test_connection();
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
                'Authorization' => 'Bearer ' . $this->api_key,
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ];
            
            $response = $this->make_api_request(self::API_URL, $data, $headers);
            
            return isset($response['choices']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
