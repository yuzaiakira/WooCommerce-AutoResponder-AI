<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI\Providers;

/**
 * Google Gemini provider implementation
 */
class GeminiProvider extends BaseProvider
{
    private const API_URL_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    protected function get_api_key(): string
    {
        return $this->settings->get_api_key('gemini');
    }
    
    private function get_model(): string
    {
        return $this->settings->get_ai_model('gemini') ?: 'gemini-pro';
    }
    
    private function get_api_url(): string
    {
        return self::API_URL_BASE . $this->get_model() . ':generateContent';
    }
    
    public function generate_response(string $prompt, array $context = []): string
    {
        if (!$this->is_available()) {
            throw new \Exception(__('Google Gemini API key is not configured or external data sharing is disabled.', 'wc-autoresponder-ai'));
        }
        
        $full_prompt = $this->build_prompt($prompt, $context);
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $full_prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 500,
                'stopSequences' => []
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];
        
        $url = $this->get_api_url() . '?key=' . $this->api_key;
        
        $response = $this->make_api_request($url, $data);
        
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception(__('Invalid response format from Gemini API.', 'wc-autoresponder-ai'));
        }
        
        $raw_response = $response['candidates'][0]['content']['parts'][0]['text'];
        
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
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Test'
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 1
                ]
            ];
            
            $url = $this->get_api_url() . '?key=' . $this->api_key;
            
            $response = $this->make_api_request($url, $data);
            
            return isset($response['candidates']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
