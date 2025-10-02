<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI;

/**
 * Manages AI providers and handles fallback logic
 */
class AIProviderManager
{
    private Settings $settings;
    private array $providers = [];
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->init_providers();
    }
    
    private function init_providers(): void
    {
        $this->providers = [
            'openai' => new Providers\OpenAIProvider($this->settings),
            'gemini' => new Providers\GeminiProvider($this->settings),
            'openrouter' => new Providers\OpenRouterProvider($this->settings)
        ];
    }
    
    public function generate_response(string $prompt, array $context = []): array
    {
        error_log('WC AI: AIProviderManager generate_response called');
        $primary_provider = $this->settings->get_ai_provider();
        $fallback_providers = $this->settings->get_option('fallback_providers', []);
        
        error_log('WC AI: Primary provider: ' . $primary_provider);
        error_log('WC AI: Fallback providers: ' . print_r($fallback_providers, true));
        
        // Try primary provider first
        if ($this->is_provider_available($primary_provider)) {
            error_log('WC AI: Primary provider is available, trying: ' . $primary_provider);
            try {
                $start_time = microtime(true);
                $response = $this->providers[$primary_provider]->generate_response($prompt, $context);
                $generation_time = microtime(true) - $start_time;
                
                return [
                    'success' => true,
                    'response' => $response,
                    'provider' => $primary_provider,
                    'model' => $this->providers[$primary_provider]->get_model_name(),
                    'generation_time' => $generation_time
                ];
            } catch (\Exception $e) {
                error_log('WC AI: Primary provider failed: ' . $e->getMessage());
                
                // Log the error
                if (class_exists('WC_AutoResponder_AI\\Database')) {
                    $database = new Database();
                    $database->log_action('provider_error', null, null, [
                        'provider' => $primary_provider,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Try fallback providers
        foreach ($fallback_providers as $fallback_provider) {
            if ($this->is_provider_available($fallback_provider)) {
                try {
                    $start_time = microtime(true);
                    $response = $this->providers[$fallback_provider]->generate_response($prompt, $context);
                    $generation_time = microtime(true) - $start_time;
                    
                    return [
                        'success' => true,
                        'response' => $response,
                        'provider' => $fallback_provider,
                        'model' => $this->providers[$fallback_provider]->get_model_name(),
                        'generation_time' => $generation_time,
                        'fallback_used' => true
                    ];
                } catch (\Exception $e) {
                    error_log('WC AI: Fallback provider failed: ' . $e->getMessage());
                    
                    // Log the error
                    if (class_exists('WC_AutoResponder_AI\\Database')) {
                        $database = new Database();
                        $database->log_action('provider_error', null, null, [
                            'provider' => $fallback_provider,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // All providers failed
        throw new \Exception(__('All AI providers are unavailable. Please check your API keys and settings.', 'wc-autoresponder-ai'));
    }
    
    public function is_provider_available(string $provider): bool
    {
        if (!isset($this->providers[$provider])) {
            error_log('WC AI: Provider not found: ' . $provider);
            return false;
        }
        
        $is_available = $this->providers[$provider]->is_available();
        error_log('WC AI: Provider ' . $provider . ' is_available: ' . ($is_available ? 'true' : 'false'));
        
        return $is_available;
    }
    
    public function get_provider_status(): array
    {
        $status = [];
        
        foreach ($this->providers as $name => $provider) {
            $status[$name] = [
                'available' => $provider->is_available(),
                'model' => $provider->get_model_name(),
                'has_api_key' => !empty($this->settings->get_api_key($name))
            ];
        }
        
        return $status;
    }
    
    public function test_provider(string $provider): array
    {
        if (!isset($this->providers[$provider])) {
            return [
                'success' => false,
                'message' => __('Provider not found.', 'wc-autoresponder-ai')
            ];
        }
        
        try {
            $test_prompt = __('This is a test message. Please respond with "Test successful" to confirm the connection is working.', 'wc-autoresponder-ai');
            $response = $this->providers[$provider]->generate_response($test_prompt, []);
            
            return [
                'success' => true,
                'message' => __('Connection test successful!', 'wc-autoresponder-ai'),
                'response' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
