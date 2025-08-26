<?php
/**
 * NSC Database Reset Tool
 * 
 * This standalone script will completely reset all NSC data.
 * To use, upload to your WordPress root directory and access via browser.
 * DELETE THIS FILE AFTER USE!
 */

// Load WordPress
require_once('wp-load.php');

// Include the user functions
require_once(ABSPATH . 'wp-admin/includes/user.php');

// Verify admin access
if (!current_user_can('manage_options')) {
    wp_die('You must be an administrator to access this page.');
}

// Process the reset
$message = '';
$error = '';

if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'RESET') {
    global $wpdb;
    
    // Use try-catch to handle any database errors
    try {
        // 1. Delete all participants
        $participants_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_participants");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}nsc_participants");
        
        // 2. Delete all payments
        $payments_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_payments");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}nsc_payments");
        
        // 3. Delete all uploads
        $uploads_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_uploads");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}nsc_uploads");
        
        // 4. Delete WordPress users with participant role
        $users_count = 0;
        $participant_users = get_users(array('role' => 'participant'));
        foreach ($participant_users as $user) {
            if (wp_delete_user($user->ID)) {
                $users_count++;
            }
        }
        
        // 5. Reset all username counters
        $option_count = 0;
        $category_suffixes = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'nsc_last_suffix_%'"
        );
        foreach ($category_suffixes as $option) {
            update_option($option->option_name, 'AA000000');
            $option_count++;
        }
        
        // Reset main suffix counter for backward compatibility
        update_option('nsc_last_suffix', 'AA000000');
        
        // Success message
        $message = "<h3>Database Reset Successfully Completed!</h3>";
        $message .= "<ul>";
        $message .= "<li><strong>Participants:</strong> {$participants_count} records deleted</li>";
        $message .= "<li><strong>Payments:</strong> {$payments_count} records deleted</li>";
        $message .= "<li><strong>Uploads:</strong> {$uploads_count} records deleted</li>";
        $message .= "<li><strong>WordPress Users:</strong> {$users_count} users with participant role deleted</li>";
        $message .= "<li><strong>Username Counters:</strong> {$option_count} category counters reset to AA000000</li>";
        $message .= "</ul>";
        $message .= "<p>The database is now empty and ready for production use.</p>";
        $message .= "<p style='color:red;'><strong>IMPORTANT: Delete this file from your server immediately!</strong></p>";
        
    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
    }
} 
?>
<!DOCTYPE html>
<html>
<head>
    <title>NSC Database Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        input[type="text"] {
            padding: 8px;
            width: 100%;
            margin-bottom: 10px;
        }
        button {
            background-color: #dc3232;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #b32d2e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>NSC Database Reset Tool</h1>
        
        <?php if (!empty($message)): ?>
        <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (empty($message)): ?>
            <div class="warning">
                <h3>⚠️ WARNING: This will permanently delete all data! ⚠️</h3>
                <p>This tool will completely erase:</p>
                <ul>
                    <li>All participants</li>
                    <li>All payments</li>
                    <li>All video uploads (database records only, not actual files)</li>
                    <li>All WordPress users with participant role</li>
                </ul>
                <p><strong>This action cannot be undone!</strong></p>
            </div>
            
            <h2>Current Data Summary</h2>
            <table>
                <tr>
                    <th>Data Type</th>
                    <th>Count</th>
                </tr>
                <tr>
                    <td>Participants</td>
                    <td><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_participants"); ?></td>
                </tr>
                <tr>
                    <td>Payments</td>
                    <td><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_payments"); ?></td>
                </tr>
                <tr>
                    <td>Uploads</td>
                    <td><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_uploads"); ?></td>
                </tr>
                <tr>
                    <td>WordPress Users (Participant Role)</td>
                    <td><?php echo count(get_users(array('role' => 'participant'))); ?></td>
                </tr>
            </table>
            
            <h2>Reset Database</h2>
            <form method="post" onsubmit="return confirm('Are you ABSOLUTELY SURE you want to delete ALL data? This cannot be undone!');">
                <p>Type "RESET" in the box below to confirm:</p>
                <input type="text" name="confirm_reset" placeholder="Type RESET here" required>
                <button type="submit">Reset All NSC Data</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>