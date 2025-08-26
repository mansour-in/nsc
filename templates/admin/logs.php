<?php
/**
 * Admin Logs Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('NSC Logs', 'nsc-core'); ?></h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="?page=nsc-logs&download_logs=1&_wpnonce=<?php echo wp_create_nonce('download_logs'); ?>" class="button">
                <?php esc_html_e('Download Logs', 'nsc-core'); ?>
            </a>
            <a href="?page=nsc-logs&clear_logs=1&_wpnonce=<?php echo wp_create_nonce('clear_logs'); ?>" class="button" onclick="return confirm('Are you sure you want to clear all logs?')">
                <?php esc_html_e('Clear Logs', 'nsc-core'); ?>
            </a>
        </div>
    </div>
    
    <div class="postbox">
        <div class="postbox-header">
            <h2><?php esc_html_e('Recent Log Entries (Last 100 lines)', 'nsc-core'); ?></h2>
        </div>
        <div class="inside">
            <?php if (!empty($logs)): ?>
                <textarea readonly style="width: 100%; height: 400px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($logs); ?></textarea>
            <?php else: ?>
                <p><?php esc_html_e('No logs found.', 'nsc-core'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>