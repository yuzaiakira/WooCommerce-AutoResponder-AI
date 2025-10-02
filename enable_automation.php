<?php
/**
 * Simple script to enable automation
 * Run this once to enable the plugin
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if plugin is active
if (!class_exists('WC_AutoResponder_AI\Plugin')) {
    echo "❌ Plugin is not active. Please activate the plugin first.\n";
    exit;
}

$settings = new WC_AutoResponder_AI\Settings();

echo "=== Enabling WooCommerce AutoResponder AI ===\n\n";

// Enable automation
$result = $settings->enable_automation();

if ($result) {
    echo "✅ Automation enabled successfully!\n";
    echo "✅ Workflow mode set to 'Require approval before publishing'\n\n";
    
    // Verify settings
    echo "=== Current Settings ===\n";
    echo "Automation: " . ($settings->is_automation_enabled() ? 'ENABLED' : 'DISABLED') . "\n";
    echo "Workflow Mode: " . $settings->get_workflow_mode() . "\n";
    echo "AI Provider: " . $settings->get_ai_provider() . "\n\n";
    
    // Check API keys
    echo "=== API Keys Status ===\n";
    $providers = ['openai', 'gemini', 'openrouter'];
    $has_api_key = false;
    
    foreach ($providers as $provider) {
        $api_key = $settings->get_api_key($provider);
        $status = empty($api_key) ? 'NOT SET' : 'SET';
        echo "{$provider}: {$status}\n";
        
        if (!empty($api_key)) {
            $has_api_key = true;
        }
    }
    
    echo "\n";
    
    if ($has_api_key) {
        echo "✅ Plugin is ready! New product reviews will be processed automatically.\n";
        echo "📝 Responses will be saved as pending for your approval.\n";
    } else {
        echo "⚠️  WARNING: No API keys configured!\n";
        echo "📝 Please set up at least one API key in the plugin settings:\n";
        echo "   WordPress Admin > WooCommerce > AutoResponder AI > Settings\n\n";
        echo "Available providers:\n";
        echo "  - OpenAI: https://platform.openai.com/api-keys\n";
        echo "  - Google Gemini: https://makersuite.google.com/app/apikey\n";
        echo "  - OpenRouter: https://openrouter.ai/keys\n";
    }
    
} else {
    echo "❌ Failed to enable automation. Please check plugin settings.\n";
}

echo "\n=== Setup Complete ===\n";

