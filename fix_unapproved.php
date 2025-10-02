<?php
/**
 * Fix unapproved comments processing
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if plugin is active
if (!class_exists('WC_AutoResponder_AI\Plugin')) {
    echo "❌ Plugin is not active.\n";
    exit;
}

$settings = new WC_AutoResponder_AI\Settings();

echo "=== Fixing Unapproved Comments Processing ===\n\n";

// Enable automation if not already enabled
if (!$settings->is_automation_enabled()) {
    $result = $settings->update_option('automation_enabled', true);
    echo "✅ Automation enabled: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
}

// Set workflow mode to semi_auto
$result = $settings->update_option('workflow_mode', 'semi_auto');
echo "✅ Workflow mode set to semi_auto: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

// Enable processing of unapproved comments
$result = $settings->update_option('advanced_settings.process_unapproved_comments', true);
echo "✅ Process unapproved comments enabled: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

// Verify settings
echo "\n=== Current Settings ===\n";
echo "Automation enabled: " . ($settings->is_automation_enabled() ? 'YES' : 'NO') . "\n";
echo "Workflow mode: " . $settings->get_workflow_mode() . "\n";
echo "Process unapproved comments: " . ($settings->get_option('advanced_settings.process_unapproved_comments', false) ? 'YES' : 'NO') . "\n";

// Check for unapproved comments
echo "\n=== Unapproved Comments ===\n";
$unapproved_comments = get_comments([
    'post_type' => 'product',
    'status' => 'hold',
    'number' => 5,
    'orderby' => 'comment_date',
    'order' => 'DESC'
]);

if (empty($unapproved_comments)) {
    echo "No unapproved comments found.\n";
    echo "To test: Create a product review and leave it unapproved.\n";
} else {
    echo "Found " . count($unapproved_comments) . " unapproved comments:\n";
    foreach ($unapproved_comments as $comment) {
        echo "  ID: {$comment->comment_ID}, Product: {$comment->comment_post_ID}, Date: {$comment->comment_date}\n";
    }
    
    // Check if any have AI responses
    $database = new WC_AutoResponder_AI\Database();
    echo "\nAI Responses for unapproved comments:\n";
    foreach ($unapproved_comments as $comment) {
        $responses = $database->get_responses_by_review($comment->comment_ID);
        echo "  Comment ID {$comment->comment_ID}: " . count($responses) . " responses\n";
    }
}

echo "\n=== Setup Complete ===\n";
echo "✅ Unapproved comments will now be processed automatically.\n";
echo "📝 AI responses will be saved as pending for approval.\n";
echo "🔄 You can approve/reject responses from the admin panel.\n";

