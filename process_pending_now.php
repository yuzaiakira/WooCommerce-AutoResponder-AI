<?php
/**
 * Process all pending comments immediately
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if plugin is active
if (!class_exists('WC_AutoResponder_AI\Plugin')) {
    echo "❌ Plugin is not active.\n";
    exit;
}

$plugin = WC_AutoResponder_AI\Plugin::get_instance();
$settings = new WC_AutoResponder_AI\Settings();

echo "=== Processing Pending Comments Immediately ===\n\n";

// Check settings
echo "=== Current Settings ===\n";
echo "Automation enabled: " . ($settings->is_automation_enabled() ? 'YES' : 'NO') . "\n";
echo "Workflow mode: " . $settings->get_workflow_mode() . "\n";
echo "Process unapproved comments: " . ($settings->get_option('advanced_settings.process_unapproved_comments', false) ? 'YES' : 'NO') . "\n\n";

if (!$settings->is_automation_enabled()) {
    echo "❌ Automation is disabled. Please enable it first.\n";
    exit;
}

// Get all pending product comments
$pending_comments = get_comments([
    'post_type' => 'product',
    'status' => 'hold',
    'number' => 100,
    'orderby' => 'comment_date',
    'order' => 'ASC'
]);

if (empty($pending_comments)) {
    echo "✅ No pending comments found.\n";
    exit;
}

echo "Found " . count($pending_comments) . " pending comments:\n";
foreach ($pending_comments as $comment) {
    echo "  ID: {$comment->comment_ID}, Product: {$comment->comment_post_ID}, Date: {$comment->comment_date}\n";
}

echo "\n=== Processing Comments ===\n";

$database = new WC_AutoResponder_AI\Database();
$success_count = 0;
$failed_count = 0;
$skipped_count = 0;

foreach ($pending_comments as $comment) {
    // Check if we already have a response for this comment
    $existing_responses = $database->get_responses_by_review($comment->comment_ID);
    
    if (!empty($existing_responses)) {
        echo "⏭️ Comment ID {$comment->comment_ID}: Response already exists\n";
        $skipped_count++;
        continue;
    }
    
    try {
        echo "🔄 Processing comment ID {$comment->comment_ID}...\n";
        
        // Simulate the wp_insert_comment hook
        $plugin->handle_comment_inserted($comment->comment_ID, $comment);
        
        // Check if response was generated
        $responses = $database->get_responses_by_review($comment->comment_ID);
        
        if (!empty($responses)) {
            $response = $responses[0];
            echo "✅ Comment ID {$comment->comment_ID}: AI response generated (Status: {$response['status']})\n";
            $success_count++;
        } else {
            echo "❌ Comment ID {$comment->comment_ID}: No AI response generated\n";
            $failed_count++;
        }
        
    } catch (Exception $e) {
        echo "⚠️ Comment ID {$comment->comment_ID}: Error - {$e->getMessage()}\n";
        $failed_count++;
    }
}

echo "\n=== Processing Complete ===\n";
echo "✅ Success: {$success_count}\n";
echo "❌ Failed: {$failed_count}\n";
echo "⏭️ Skipped: {$skipped_count}\n";

if ($success_count > 0) {
    echo "\n🎉 AI responses have been generated for {$success_count} pending comments!\n";
    echo "📝 All responses are saved as pending for approval.\n";
    echo "🔧 You can approve them from WordPress Admin > Comments\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Check WordPress Admin > Comments for pending comments\n";
echo "2. Approve both user comments and AI responses\n";
echo "3. Both will appear on the product page after approval\n";

