<?php
/**
 * Process all unapproved comments manually
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

echo "=== Processing Unapproved Comments ===\n\n";

// Check settings
echo "=== Current Settings ===\n";
echo "Automation enabled: " . ($settings->is_automation_enabled() ? 'YES' : 'NO') . "\n";
echo "Workflow mode: " . $settings->get_workflow_mode() . "\n";
echo "Process unapproved comments: " . ($settings->get_option('advanced_settings.process_unapproved_comments', false) ? 'YES' : 'NO') . "\n\n";

if (!$settings->is_automation_enabled()) {
    echo "❌ Automation is disabled. Please enable it first.\n";
    exit;
}

// Get unapproved comments
$unapproved_comments = get_comments([
    'post_type' => 'product',
    'status' => 'hold',
    'number' => 20,
    'orderby' => 'comment_date',
    'order' => 'ASC'
]);

if (empty($unapproved_comments)) {
    echo "✅ No unapproved comments found.\n";
    exit;
}

echo "Found " . count($unapproved_comments) . " unapproved comments:\n";
foreach ($unapproved_comments as $comment) {
    echo "  ID: {$comment->comment_ID}, Product: {$comment->comment_post_ID}, Date: {$comment->comment_date}\n";
}

echo "\n=== Processing Comments ===\n";

// Process each comment
$results = $plugin->process_unapproved_comments();

$success_count = 0;
$failed_count = 0;
$skipped_count = 0;

foreach ($results as $result) {
    $status_icon = '';
    switch ($result['status']) {
        case 'success':
            $status_icon = '✅';
            $success_count++;
            break;
        case 'failed':
            $status_icon = '❌';
            $failed_count++;
            break;
        case 'skipped':
            $status_icon = '⏭️';
            $skipped_count++;
            break;
        case 'error':
            $status_icon = '⚠️';
            $failed_count++;
            break;
    }
    
    echo "{$status_icon} Comment ID {$result['comment_id']}: {$result['message']}\n";
}

echo "\n=== Processing Complete ===\n";
echo "✅ Success: {$success_count}\n";
echo "❌ Failed: {$failed_count}\n";
echo "⏭️ Skipped: {$skipped_count}\n";

if ($success_count > 0) {
    echo "\n🎉 AI responses have been generated for {$success_count} comments!\n";
    echo "📝 You can now approve/reject these responses from the admin panel.\n";
}

