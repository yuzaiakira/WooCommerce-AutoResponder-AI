<?php
declare(strict_types=1);

namespace WC_AutoResponder_AI;

/**
 * Plugin settings management
 */
class Settings
{
    private const OPTION_GROUP = 'wc_ai_settings';
    private const OPTION_NAME = 'wc_ai_options';
    
    private array $default_options = [
        'automation_enabled' => true,
        'workflow_mode' => 'semi_auto', // auto, semi_auto, draft
        'ai_provider' => 'gemini',
        'fallback_providers' => ['gemini', 'openrouter'],
        'ai_models' => [
            'openai' => 'gpt-3.5-turbo',
            'gemini' => 'gemini-pro',
            'openrouter' => 'openai/gpt-3.5-turbo'
        ],
        'tone_style' => 'professional',
        'response_templates' => [],
        'product_fields' => [
            'title' => true,
            'description' => true,
            'short_description' => true,
            'attributes' => true,
            'sku' => false,
            'price' => false,
            'categories' => true,
            'tags' => true
        ],
        'review_filters' => [
            'min_rating' => 1,
            'max_rating' => 5,
            'exclude_spam' => true,
            'exclude_negative_only' => false,
            'exclude_questions' => false
        ],
        'privacy_settings' => [
            'allow_external_data' => true,
            'anonymize_customer_data' => true,
            'data_retention_days' => 365
        ],
        'notification_settings' => [
            'email_notifications' => true,
            'notification_email' => '',
            'notify_on_errors' => true,
            'notify_on_high_volume' => true,
            'high_volume_threshold' => 50
        ],
        'advanced_settings' => [
            'max_response_length' => 300,
            'include_product_links' => true,
            'include_contact_info' => true,
            'auto_approve_positive' => false,
            'auto_reject_spam' => true,
            'learning_enabled' => true,
            'process_unapproved_comments' => true
        ]
    ];
    
    public function __construct()
    {
        // Only register settings on admin_init to avoid early translation loading
        if (is_admin()) {
            add_action('admin_init', [$this, 'register_settings']);
        }
    }
    
    public function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => $this->default_options
            ]
        );
        
        // General Settings Section
        add_settings_section(
            'wc_ai_general',
            __('General Settings', 'wc-autoresponder-ai'),
            [$this, 'general_section_callback'],
            'wc_ai_settings'
        );
        
        add_settings_field(
            'automation_enabled',
            __('Enable Automation', 'wc-autoresponder-ai'),
            [$this, 'checkbox_field_callback'],
            'wc_ai_settings',
            'wc_ai_general',
            [
                'field' => 'automation_enabled',
                'description' => __('Enable automatic AI response generation for new reviews.', 'wc-autoresponder-ai')
            ]
        );
        
        add_settings_field(
            'workflow_mode',
            __('Workflow Mode', 'wc-autoresponder-ai'),
            [$this, 'select_field_callback'],
            'wc_ai_settings',
            'wc_ai_general',
            [
                'field' => 'workflow_mode',
                'options' => [
                    'auto' => __('Auto-publish responses', 'wc-autoresponder-ai'),
                    'semi_auto' => __('Require approval before publishing', 'wc-autoresponder-ai'),
                ],
                'description' => __('Choose how AI-generated responses are handled.', 'wc-autoresponder-ai')
            ]
        );
        
        add_settings_field(
            'tone_style',
            __('Response Tone', 'wc-autoresponder-ai'),
            [$this, 'select_field_callback'],
            'wc_ai_settings',
            'wc_ai_general',
            [
                'field' => 'tone_style',
                'options' => [
                    'professional' => __('Professional', 'wc-autoresponder-ai'),
                    'friendly' => __('Friendly', 'wc-autoresponder-ai'),
                    'casual' => __('Casual', 'wc-autoresponder-ai'),
                    'technical' => __('Technical', 'wc-autoresponder-ai'),
                    'promotional' => __('Promotional', 'wc-autoresponder-ai')
                ],
                'description' => __('Select the tone for AI-generated responses.', 'wc-autoresponder-ai')
            ]
        );
        
        // AI Provider Settings Section
        add_settings_section(
            'wc_ai_providers',
            __('AI Provider Settings', 'wc-autoresponder-ai'),
            [$this, 'providers_section_callback'],
            'wc_ai_settings'
        );
        
        add_settings_field(
            'ai_provider',
            __('Primary AI Provider', 'wc-autoresponder-ai'),
            [$this, 'select_field_callback'],
            'wc_ai_settings',
            'wc_ai_providers',
            [
                'field' => 'ai_provider',
                'options' => [
                    'openai' => __('OpenAI (GPT)', 'wc-autoresponder-ai'),
                    'gemini' => __('Google Gemini', 'wc-autoresponder-ai'),
                    'openrouter' => __('OpenRouter', 'wc-autoresponder-ai')
                ],
                'description' => __('Select the primary AI provider for response generation.', 'wc-autoresponder-ai')
            ]
        );
        
        add_settings_field(
            'openai_model',
            __('OpenAI Model', 'wc-autoresponder-ai'),
            [$this, 'text_field_callback'],
            'wc_ai_settings',
            'wc_ai_providers',
            [
                'field' => 'ai_models.openai',
                'placeholder' => 'gpt-3.5-turbo',
                'description' => __('Enter the OpenAI model name. Examples: gpt-3.5-turbo, gpt-4, gpt-4-turbo, gpt-4o. Leave empty to use default.', 'wc-autoresponder-ai'),
                'class' => 'model-selection-field'
            ]
        );
        
        add_settings_field(
            'gemini_model',
            __('Gemini Model', 'wc-autoresponder-ai'),
            [$this, 'text_field_callback'],
            'wc_ai_settings',
            'wc_ai_providers',
            [
                'field' => 'ai_models.gemini',
                'placeholder' => 'gemini-pro',
                'description' => __('Enter the Gemini model name. Examples: gemini-pro, gemini-pro-vision, gemini-1.5-pro, gemini-1.5-flash. Leave empty to use default.', 'wc-autoresponder-ai'),
                'class' => 'model-selection-field'
            ]
        );
        
        add_settings_field(
            'openrouter_model',
            __('OpenRouter Model', 'wc-autoresponder-ai'),
            [$this, 'text_field_callback'],
            'wc_ai_settings',
            'wc_ai_providers',
            [
                'field' => 'ai_models.openrouter',
                'placeholder' => 'openai/gpt-3.5-turbo',
                'description' => __('Enter the OpenRouter model name. Examples: openai/gpt-3.5-turbo, qwen/qwen3-next-80b-a3b-thinking, anthropic/claude-3-sonnet, meta-llama/llama-3-70b-instruct. Leave empty to use default.', 'wc-autoresponder-ai'),
                'class' => 'model-selection-field'
            ]
        );
        
        // API Keys Section
        add_settings_section(
            'wc_ai_api_keys',
            __('API Keys', 'wc-autoresponder-ai'),
            [$this, 'api_keys_section_callback'],
            'wc_ai_settings'
        );
        
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'wc-autoresponder-ai'),
            [$this, 'password_field_callback'],
            'wc_ai_settings',
            'wc_ai_api_keys',
            [
                'field' => 'openai_api_key',
                'description' => __('Enter your OpenAI API key. Get one from https://platform.openai.com/api-keys', 'wc-autoresponder-ai')
            ]
        );
        
        add_settings_field(
            'gemini_api_key',
            __('Google Gemini API Key', 'wc-autoresponder-ai'),
            [$this, 'password_field_callback'],
            'wc_ai_settings',
            'wc_ai_api_keys',
            [
                'field' => 'gemini_api_key',
                'description' => __('Enter your Google Gemini API key. Get one from https://makersuite.google.com/app/apikey', 'wc-autoresponder-ai')
            ]
        );
        
        add_settings_field(
            'openrouter_api_key',
            __('OpenRouter API Key', 'wc-autoresponder-ai'),
            [$this, 'password_field_callback'],
            'wc_ai_settings',
            'wc_ai_api_keys',
            [
                'field' => 'openrouter_api_key',
                'description' => __('Enter your OpenRouter API key. Get one from https://openrouter.ai/keys', 'wc-autoresponder-ai')
            ]
        );
        
        // Product Fields Section
        add_settings_section(
            'wc_ai_product_fields',
            __('Product Data Fields', 'wc-autoresponder-ai'),
            [$this, 'product_fields_section_callback'],
            'wc_ai_settings'
        );
        
        add_settings_field(
            'product_fields',
            __('Include Product Fields', 'wc-autoresponder-ai'),
            [$this, 'checkbox_group_field_callback'],
            'wc_ai_settings',
            'wc_ai_product_fields',
            [
                'field' => 'product_fields',
                'options' => [
                    'title' => __('Product Title', 'wc-autoresponder-ai'),
                    'description' => __('Product Description', 'wc-autoresponder-ai'),
                    'short_description' => __('Short Description', 'wc-autoresponder-ai'),
                    'attributes' => __('Product Attributes', 'wc-autoresponder-ai'),
                    'sku' => __('SKU', 'wc-autoresponder-ai'),
                    'price' => __('Price', 'wc-autoresponder-ai'),
                    'categories' => __('Categories', 'wc-autoresponder-ai'),
                    'tags' => __('Tags', 'wc-autoresponder-ai')
                ],
                'description' => __('Select which product fields to include when generating AI responses.', 'wc-autoresponder-ai')
            ]
        );
        
        // Privacy Settings Section
        add_settings_section(
            'wc_ai_privacy',
            __('Privacy & Security', 'wc-autoresponder-ai'),
            [$this, 'privacy_section_callback'],
            'wc_ai_settings'
        );
        
        add_settings_field(
            'allow_external_data',
            __('Allow External Data Sharing', 'wc-autoresponder-ai'),
            [$this, 'checkbox_field_callback'],
            'wc_ai_settings',
            'wc_ai_privacy',
            [
                'field' => 'privacy_settings.allow_external_data',
                'description' => __('Allow sending data to external AI services. Disable for local-only processing.', 'wc-autoresponder-ai')
            ]
        );
        
        add_settings_field(
            'anonymize_customer_data',
            __('Anonymize Customer Data', 'wc-autoresponder-ai'),
            [$this, 'checkbox_field_callback'],
            'wc_ai_settings',
            'wc_ai_privacy',
            [
                'field' => 'privacy_settings.anonymize_customer_data',
                'description' => __('Remove or anonymize customer names and personal information from AI prompts.', 'wc-autoresponder-ai')
            ]
        );
        
        // Advanced Settings Section
        add_settings_section(
            'wc_ai_advanced',
            __('Advanced Settings', 'wc-autoresponder-ai'),
            [$this, 'advanced_section_callback'],
            'wc_ai_settings'
        );
        
        add_settings_field(
            'max_response_length',
            __('Maximum Response Length', 'wc-autoresponder-ai'),
            [$this, 'number_field_callback'],
            'wc_ai_settings',
            'wc_ai_advanced',
            [
                'field' => 'advanced_settings.max_response_length',
                'min' => 100,
                'max' => 1000,
                'step' => 50,
                'description' => __('Maximum number of characters for AI-generated responses. Recommended: 200-400 characters.', 'wc-autoresponder-ai')
            ]
        );
    }
    
    public function get_options(): array
    {
        $options = get_option(self::OPTION_NAME, $this->default_options);
        return wp_parse_args($options, $this->default_options);
    }
    
    public function get_option(string $key, $default = null)
    {
        $options = $this->get_options();
        
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $options;
            
            foreach ($keys as $k) {
                if (is_array($value) && array_key_exists($k, $value)) {
                    $value = $value[$k];
                } else {
                    return $default;
                }
            }
            
            return $value;
        }
        
        return $options[$key] ?? $default;
    }
    
    public function update_option(string $key, $value): bool
    {
        $options = $this->get_options();
        
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $current = &$options;
            
            foreach ($keys as $k) {
                if (!is_array($current)) {
                    $current = [];
                }
                if (!array_key_exists($k, $current)) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
            
            $current = $value;
        } else {
            $options[$key] = $value;
        }
        
        return update_option(self::OPTION_NAME, $options);
    }
    
    public static function set_default_options(): void
    {
        $default_options = [
            'automation_enabled' => true,
            'workflow_mode' => 'semi_auto', // auto, semi_auto, draft
            'ai_provider' => 'openai',
            'fallback_providers' => ['gemini', 'openrouter'],
            'ai_models' => [
                'openai' => 'gpt-3.5-turbo',
                'gemini' => 'gemini-pro',
                'openrouter' => 'openai/gpt-3.5-turbo'
            ],
            'tone_style' => 'professional',
            'response_templates' => [],
            'product_fields' => [
                'title' => true,
                'description' => true,
                'short_description' => true,
                'attributes' => true,
                'sku' => false,
                'price' => false,
                'categories' => true,
                'tags' => true
            ],
            'review_filters' => [
                'min_rating' => 1,
                'max_rating' => 5,
                'exclude_spam' => true,
                'exclude_negative_only' => false,
                'exclude_questions' => false
            ],
            'privacy_settings' => [
                'allow_external_data' => true,
                'anonymize_customer_data' => true,
                'data_retention_days' => 365
            ],
            'notification_settings' => [
                'email_notifications' => true,
                'notification_email' => '',
                'notify_on_errors' => true,
                'notify_on_high_volume' => true,
                'high_volume_threshold' => 50
            ],
        'advanced_settings' => [
            'max_response_length' => 300,
                'include_product_links' => true,
                'include_contact_info' => true,
                'auto_approve_positive' => false,
                'auto_reject_spam' => true,
                'learning_enabled' => true,
                'process_unapproved_comments' => true
            ]
        ];
        
        if (!get_option(self::OPTION_NAME)) {
            update_option(self::OPTION_NAME, $default_options);
        }
    }
    
    public function sanitize_options($input): array
    {
        // Debug: Log the input data for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC AI: Raw input data: ' . print_r($input, true));
            error_log('WC AI: ai_models input: ' . print_r($input['ai_models'] ?? 'NOT SET', true));
            
            // Check if the data is coming in a different format
            if (isset($input['ai_models'])) {
                error_log('WC AI: ai_models structure: ' . print_r($input['ai_models'], true));
            }
            
            // Check all keys that contain 'ai_models'
            foreach ($input as $key => $value) {
                if (strpos($key, 'ai_models') !== false) {
                    error_log('WC AI: Found ai_models key: ' . $key . ' = ' . print_r($value, true));
                }
            }
        }
        
        $sanitized = [];
        
        // Sanitize automation settings
        $sanitized['automation_enabled'] = !empty($input['automation_enabled']);
        $sanitized['workflow_mode'] = sanitize_text_field($input['workflow_mode'] ?? 'semi_auto');
        $sanitized['tone_style'] = sanitize_text_field($input['tone_style'] ?? 'professional');
        
        // Sanitize AI provider settings
        $sanitized['ai_provider'] = sanitize_text_field($input['ai_provider'] ?? 'openai');
        $sanitized['fallback_providers'] = array_map('sanitize_text_field', $input['fallback_providers'] ?? []);
        
        // Sanitize AI model settings
        $sanitized['ai_models'] = [
            'openai' => sanitize_text_field($input['ai_models']['openai'] ?? ''),
            'gemini' => sanitize_text_field($input['ai_models']['gemini'] ?? ''),
            'openrouter' => sanitize_text_field($input['ai_models']['openrouter'] ?? '')
        ];
        
        // If no values provided, use defaults
        foreach ($sanitized['ai_models'] as $provider => $model) {
            if (empty($model)) {
                $sanitized['ai_models'][$provider] = $this->default_options['ai_models'][$provider];
            }
        }
        
        // Validate model names (basic validation)
        foreach ($sanitized['ai_models'] as $provider => $model) {
            if (!empty($model)) {
                // Allow alphanumeric, hyphens, underscores, slashes, dots, and colons
                if (!preg_match('/^[a-zA-Z0-9\-_\/\.:]+$/', $model)) {
                    $sanitized['ai_models'][$provider] = '';
                    add_settings_error(
                        'wc_ai_options',
                        'invalid_model_name',
                        sprintf(__('Invalid model name for %s. Only alphanumeric characters, hyphens, underscores, slashes, dots, and colons are allowed.', 'wc-autoresponder-ai'), ucfirst($provider))
                    );
                }
            }
        }
        
        // Debug: Log the sanitized models for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC AI: Sanitized models: ' . print_r($sanitized['ai_models'], true));
        }
        
        // Sanitize API keys
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');
        $sanitized['gemini_api_key'] = sanitize_text_field($input['gemini_api_key'] ?? '');
        $sanitized['openrouter_api_key'] = sanitize_text_field($input['openrouter_api_key'] ?? '');
        
        // Sanitize product fields
        $sanitized['product_fields'] = [];
        $allowed_fields = array_keys($this->default_options['product_fields']);
        
        // Debug: Log product fields input for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC AI: Product fields input: ' . print_r($input['product_fields'] ?? 'NOT SET', true));
        }
        
        foreach ($allowed_fields as $field) {
            $sanitized['product_fields'][$field] = !empty($input['product_fields'][$field]);
        }
        
        // Debug: Log sanitized product fields
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WC AI: Sanitized product fields: ' . print_r($sanitized['product_fields'], true));
        }
        
        // Sanitize review filters
        $sanitized['review_filters'] = [
            'min_rating' => max(1, min(5, intval($input['review_filters']['min_rating'] ?? 1))),
            'max_rating' => max(1, min(5, intval($input['review_filters']['max_rating'] ?? 5))),
            'exclude_spam' => !empty($input['review_filters']['exclude_spam']),
            'exclude_negative_only' => !empty($input['review_filters']['exclude_negative_only']),
            'exclude_questions' => !empty($input['review_filters']['exclude_questions'])
        ];
        
        // Sanitize privacy settings
        $sanitized['privacy_settings'] = [
            'allow_external_data' => !empty($input['privacy_settings']['allow_external_data']),
            'anonymize_customer_data' => !empty($input['privacy_settings']['anonymize_customer_data']),
            'data_retention_days' => max(30, min(3650, intval($input['privacy_settings']['data_retention_days'] ?? 365)))
        ];
        
        // Sanitize notification settings
        $sanitized['notification_settings'] = [
            'email_notifications' => !empty($input['notification_settings']['email_notifications']),
            'notification_email' => sanitize_email($input['notification_settings']['notification_email'] ?? ''),
            'notify_on_errors' => !empty($input['notification_settings']['notify_on_errors']),
            'notify_on_high_volume' => !empty($input['notification_settings']['notify_on_high_volume']),
            'high_volume_threshold' => max(10, min(1000, intval($input['notification_settings']['high_volume_threshold'] ?? 50)))
        ];
        
        // Sanitize advanced settings
        $sanitized['advanced_settings'] = [
            'max_response_length' => max(100, min(2000, intval($input['advanced_settings']['max_response_length'] ?? 500))),
            'include_product_links' => !empty($input['advanced_settings']['include_product_links']),
            'include_contact_info' => !empty($input['advanced_settings']['include_contact_info']),
            'auto_approve_positive' => !empty($input['advanced_settings']['auto_approve_positive']),
            'auto_reject_spam' => !empty($input['advanced_settings']['auto_reject_spam']),
            'learning_enabled' => !empty($input['advanced_settings']['learning_enabled']),
            'process_unapproved_comments' => !empty($input['advanced_settings']['process_unapproved_comments'])
        ];
        
        return wp_parse_args($sanitized, $this->default_options);
    }
    
    // Field callback methods
    public function general_section_callback(): void
    {
        echo '<p>' . __('Configure the basic automation settings for AI response generation.', 'wc-autoresponder-ai') . '</p>';
    }
    
    public function providers_section_callback(): void
    {
        echo '<p>' . __('Configure AI provider settings and API keys.', 'wc-autoresponder-ai') . '</p>';
    }
    
    public function api_keys_section_callback(): void
    {
        echo '<p>' . __('Enter your API keys for the AI providers you want to use.', 'wc-autoresponder-ai') . '</p>';
    }
    
    public function product_fields_section_callback(): void
    {
        echo '<p>' . __('Select which product information to include when generating AI responses.', 'wc-autoresponder-ai') . '</p>';
    }
    
    public function privacy_section_callback(): void
    {
        echo '<p>' . __('Configure privacy and security settings for data handling.', 'wc-autoresponder-ai') . '</p>';
    }
    
    public function advanced_section_callback(): void
    {
        echo '<p>' . __('Configure advanced settings for AI response generation.', 'wc-autoresponder-ai') . '</p>';
    }
    
    public function checkbox_field_callback(array $args): void
    {
        $value = $this->get_option($args['field'], false);
        $checked = checked($value, true, false);
        
        // Handle nested field names properly
        $field_name = $args['field'];
        if (strpos($field_name, '.') !== false) {
            // Convert privacy_settings.allow_external_data to privacy_settings][allow_external_data for proper WordPress array handling
            $parts = explode('.', $field_name);
            $field_name = $parts[0] . '][' . $parts[1];
        }
        
        echo '<input type="checkbox" id="' . esc_attr($args['field']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($field_name) . ']" value="1" ' . $checked . ' />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function select_field_callback(array $args): void
    {
        $value = $this->get_option($args['field'], '');
        $class = !empty($args['class']) ? ' class="' . esc_attr($args['class']) . '"' : '';
        
        // Handle nested field names properly
        $field_name = $args['field'];
        if (strpos($field_name, '.') !== false) {
            // Convert nested.field to nested][field for proper WordPress array handling
            $parts = explode('.', $field_name);
            $field_name = $parts[0] . '][' . $parts[1];
        }
        
        echo '<select id="' . esc_attr($args['field']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($field_name) . ']"' . $class . '>';
        
        foreach ($args['options'] as $option_value => $option_label) {
            $selected = selected($value, $option_value, false);
            echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function text_field_callback(array $args): void
    {
        $value = $this->get_option($args['field'], '');
        $class = !empty($args['class']) ? ' class="' . esc_attr($args['class']) . '"' : '';
        $placeholder = !empty($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';
        
        // Handle nested field names properly
        $field_name = $args['field'];
        if (strpos($field_name, '.') !== false) {
            // Convert ai_models.openai to ai_models][openai for proper WordPress array handling
            $parts = explode('.', $field_name);
            $field_name = $parts[0] . '][' . $parts[1];
        }
        
        echo '<input type="text" id="' . esc_attr($args['field']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($field_name) . ']" value="' . esc_attr($value) . '" class="regular-text"' . $class . $placeholder . ' />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function password_field_callback(array $args): void
    {
        $value = $this->get_option($args['field'], '');
        
        // Handle nested field names properly
        $field_name = $args['field'];
        if (strpos($field_name, '.') !== false) {
            // Convert nested.field to nested][field for proper WordPress array handling
            $parts = explode('.', $field_name);
            $field_name = $parts[0] . '][' . $parts[1];
        }
        
        echo '<input type="password" id="' . esc_attr($args['field']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($field_name) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function checkbox_group_field_callback(array $args): void
    {
        $values = $this->get_option($args['field'], []);
        
        echo '<fieldset>';
        
        foreach ($args['options'] as $option_value => $option_label) {
            // Check if the field is enabled (for boolean array structure like product_fields)
            $is_checked = false;
            if (is_array($values)) {
                if (isset($values[$option_value])) {
                    $is_checked = (bool) $values[$option_value];
                } else {
                    // Fallback for array of values structure
                    $is_checked = in_array($option_value, $values);
                }
            }
            
            $checked = checked($is_checked, true, false);
            echo '<label>';
            echo '<input type="checkbox" name="' . self::OPTION_NAME . '[' . esc_attr($args['field']) . '][' . esc_attr($option_value) . ']" value="1" ' . $checked . ' />';
            echo ' ' . esc_html($option_label);
            echo '</label><br>';
        }
        
        echo '</fieldset>';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    public function number_field_callback(array $args): void
    {
        $value = $this->get_option($args['field'], '');
        $min = !empty($args['min']) ? ' min="' . esc_attr($args['min']) . '"' : '';
        $max = !empty($args['max']) ? ' max="' . esc_attr($args['max']) . '"' : '';
        $step = !empty($args['step']) ? ' step="' . esc_attr($args['step']) . '"' : '';
        
        // Handle nested field names properly
        $field_name = $args['field'];
        if (strpos($field_name, '.') !== false) {
            // Convert nested.field to nested][field for proper WordPress array handling
            $parts = explode('.', $field_name);
            $field_name = $parts[0] . '][' . $parts[1];
        }
        
        echo '<input type="number" id="' . esc_attr($args['field']) . '" name="' . self::OPTION_NAME . '[' . esc_attr($field_name) . ']" value="' . esc_attr($value) . '" class="small-text"' . $min . $max . $step . ' />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    // Helper methods
    public function is_automation_enabled(): bool
    {
        return $this->get_option('automation_enabled', false);
    }
    
    public function get_workflow_mode(): string
    {
        return $this->get_option('workflow_mode', 'semi_auto');
    }
    
    public function get_ai_provider(): string
    {
        return $this->get_option('ai_provider', 'openai');
    }
    
    public function get_api_key(string $provider): string
    {
        $api_key = $this->get_option($provider . '_api_key', '');
        error_log('WC AI: get_api_key for ' . $provider . ': ' . (empty($api_key) ? 'empty' : 'has key'));
        return $api_key;
    }
    
    public function get_tone_style(): string
    {
        return $this->get_option('tone_style', 'professional');
    }
    
    public function get_product_fields(): array
    {
        return $this->get_option('product_fields', []);
    }
    
    public function is_external_data_allowed(): bool
    {
        $allowed = $this->get_option('privacy_settings.allow_external_data', true);
        error_log('WC AI: is_external_data_allowed: ' . ($allowed ? 'true' : 'false'));
        return $allowed;
    }
    
    public function is_customer_data_anonymized(): bool
    {
        return $this->get_option('privacy_settings.anonymize_customer_data', true);
    }
    
    public function get_ai_model(string $provider): string
    {
        $models = $this->get_option('ai_models', []);
        $model = $models[$provider] ?? $this->default_options['ai_models'][$provider] ?? '';
        error_log('WC AI: get_ai_model for ' . $provider . ': ' . $model);
        return $model;
    }
    
    public function get_all_ai_models(): array
    {
        return $this->get_option('ai_models', $this->default_options['ai_models']);
    }
    
    /**
     * Enable automation and set up default settings
     */
    public function enable_automation(): bool
    {
        $result1 = $this->update_option('automation_enabled', true);
        $result2 = $this->update_option('workflow_mode', 'semi_auto');
        
        return $result1 && $result2;
    }
    
    /**
     * Check if plugin is properly configured
     */
    public function is_configured(): bool
    {
        if (!$this->is_automation_enabled()) {
            return false;
        }
        
        // Check if at least one API key is set
        $providers = ['openai', 'gemini', 'openrouter'];
        foreach ($providers as $provider) {
            if (!empty($this->get_api_key($provider))) {
                return true;
            }
        }
        
        return false;
    }
}
