<?php
/**
 * Convert existing AI responses to actual WordPress comments
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if plugin is active
if (!class_exists('WC_AutoResponder_AI\Plugin')) {
    echo "❌ Plugin is not active.\n";
    exit;
}

$database = new WC_AutoResponder_AI\Database();
$settings = new WC_AutoResponder_AI\Settings();

echo "=== Converting AI Responses to Comments ===\n\n";

// Get all pending AI responses
$responses = $database->get_responses_by_status('pending', 100);

if (empty($responses)) {
    echo "✅ No pending AI responses found.\n";
    exit;
}

echo "Found " . count($responses) . " pending AI responses to convert:\n";

$converted_count = 0;
$failed_count = 0;

foreach ($responses as $response) {
    echo "\n🔄 Converting response ID {$response['id']} for review ID {$response['review_id']}...\n";
    
    try {
        // Check if comment already exists for this response
        $existing_comments = get_comments([
            'meta_key' => 'response_id',
            'meta_value' => $response['id'],
            'number' => 1
        ]);
        
        if (!empty($existing_comments)) {
            echo "⏭️ Comment already exists for this response, skipping...\n";
            continue;
        }
        
        // Get the original review
        $review = get_comment($response['review_id']);
        if (!$review) {
            echo "❌ Original review not found, skipping...\n";
            $failed_count++;
            continue;
        }
        
        // Create AI comment
        $workflow_mode = $settings->get_workflow_mode();
        $comment_approved = ($workflow_mode === 'auto') ? 1 : 0;
        
        $comment_data = [
            'comment_post_ID' => $response['product_id'],
            'comment_author' => get_bloginfo('name'),
            'comment_author_email' => get_option('admin_email'),
            'comment_author_url' => home_url(),
            'comment_content' => $response['response_text'],
            'comment_parent' => $response['review_id'],
            'comment_approved' => $comment_approved,
            'comment_type' => '',
        ];
        
        $comment_id = wp_insert_comment($comment_data);
        
        if ($comment_id) {
            // Add comment meta
            add_comment_meta($comment_id, 'ai_generated', true);
            add_comment_meta($comment_id, 'ai_provider', $response['ai_provider']);
            add_comment_meta($comment_id, 'ai_model', $response['model_used']);
            add_comment_meta($comment_id, 'response_id', $response['id']);
            
            // Set comment status
            if ($workflow_mode === 'auto') {
                wp_set_comment_status($comment_id, 'approve');
            } else {
                wp_set_comment_status($comment_id, 'hold');
            }
            
            // Update response status
            $database->update_response_status($response['id'], 'published', get_current_user_id());
            
            echo "✅ Successfully converted to comment ID {$comment_id}\n";
            $converted_count++;
        } else {
            echo "❌ Failed to create comment\n";
            $failed_count++;
        }
        
    } catch (Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n";
        $failed_count++;
    }
}

echo "\n=== Conversion Complete ===\n";
echo "✅ Converted: {$converted_count}\n";
echo "❌ Failed: {$failed_count}\n";

if ($converted_count > 0) {
    echo "\n🎉 AI responses have been converted to WordPress comments!\n";
    echo "📝 You can now manage them from WordPress Admin > Comments\n";
    echo "🔧 AI comments will be marked with 🤖 indicator\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Go to WordPress Admin > Comments\n";
echo "2. Look for comments with 🤖 AI Response indicator\n";
echo "3. Approve both user comments and AI responses\n";
echo "4. Both will appear on the product page after approval\n";

