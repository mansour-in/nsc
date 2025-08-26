<?php
/**
 * Admin Payments Template
 */
if (!defined('ABSPATH')) exit;

// Get the current filter value (if any)
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($search) ? $search : '';

// Handle "Mark as Paid" action
if (isset($_GET['action']) && $_GET['action'] === 'mark_as_paid' && isset($_GET['payment_id']) && isset($_GET['_wpnonce'])) {
    $payment_id = intval($_GET['payment_id']);
    $nonce = sanitize_text_field($_GET['_wpnonce']);
    
    if (wp_verify_nonce($nonce, 'mark_payment_paid_' . $payment_id)) {
        global $wpdb;
        
        // Log the attempt
        error_log("NSC: Attempting to mark payment #{$payment_id} as paid");
        
        // Check if payment exists first
        $payment_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if (!$payment_exists) {
            error_log("NSC Error: Payment #{$payment_id} not found in database");
            echo '<div class="notice notice-error is-dismissible"><p>Payment record not found.</p></div>';
        } else {
            // Update the payment status in the database
            $updated = $wpdb->update(
                $wpdb->prefix . 'nsc_payments',
                array(
                    'status' => 'paid',
                    'payment_date' => current_time('mysql'),
                    'razorpay_payment_id' => 'ADMIN-MARKED-' . time()
                ),
                array('payment_id' => $payment_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            if ($updated !== false) {
                error_log("NSC: Successfully marked payment #{$payment_id} as paid");
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                    esc_html__('Payment successfully marked as paid.', 'nsc-core') . 
                    '</p></div>';
            } else {
                error_log("NSC Error: Failed to update payment #{$payment_id}. MySQL Error: " . $wpdb->last_error);
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                    esc_html__('Failed to update payment status. Error logged for review.', 'nsc-core') . 
                    '</p></div>';
            }
        }
    } else {
        // Nonce verification failed
        echo '<div class="notice notice-error is-dismissible"><p>' . 
            esc_html__('Security verification failed. Please try again.', 'nsc-core') . 
            '</p></div>';
    }
}
?>

<div class="wrap">
    <h1><?php esc_html_e('NSC Payments', 'nsc-core'); ?></h1>
    
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <form method="get">
                <input type="hidden" name="page" value="nsc-payments">
                <select name="status">
                    <option value=""><?php esc_html_e('All Statuses', 'nsc-core'); ?></option>
                    <option value="paid" <?php selected($current_status, 'paid'); ?>><?php esc_html_e('Paid', 'nsc-core'); ?></option>
                    <option value="pending" <?php selected($current_status, 'pending'); ?>><?php esc_html_e('Not Paid', 'nsc-core'); ?></option>
                    <option value="failed" <?php selected($current_status, 'failed'); ?>><?php esc_html_e('Failed', 'nsc-core'); ?></option>
                    <option value="cancelled" <?php selected($current_status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'nsc-core'); ?></option>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search payments', 'nsc-core'); ?>">
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'nsc-core'); ?>">
            </form>
        </div>
        <div class="tablenav-pages">
            <?php if ($total_pages > 1): ?>
                <span class="displaying-num"><?php echo sprintf('%d items', $total_items); ?></span>
                <span class="pagination-links">
                    <?php
                    // Previous page
                    if ($current_page > 1) {
                        $prev_page = $current_page - 1;
                        echo '<a class="prev-page button" href="' . add_query_arg('paged', $prev_page) . '">&laquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled">&laquo;</span>';
                    }
                    
                    // Page numbers
                    echo '<span class="paging-input">';
                    echo '<span class="tablenav-paging-text">' . $current_page . ' of ' . $total_pages . '</span>';
                    echo '</span>';
                    
                    // Next page
                    if ($current_page < $total_pages) {
                        $next_page = $current_page + 1;
                        echo '<a class="next-page button" href="' . add_query_arg('paged', $next_page) . '">&raquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled">&raquo;</span>';
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Payment ID', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Participant', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Email', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Amount', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Payment Status', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Order Date', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Payment Date', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Actions', 'nsc-core'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
            <tr>
                <td colspan="8"><?php esc_html_e('No payments found.', 'nsc-core'); ?></td>
            </tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo esc_html($payment->payment_id); ?></td>
                    <td><?php echo esc_html($payment->first_name . ' ' . $payment->last_name); ?></td>
                    <td><?php echo esc_html($payment->user_email); ?></td>
                    <td>
                        <?php 
                        if (!empty($payment->amount) && !empty($payment->currency)) {
                            $currency_symbol = $payment->currency === 'INR' ? '₹' : '$';
                            echo esc_html($currency_symbol . $payment->amount);
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="nsc-status nsc-status-<?php echo esc_attr($payment->status); ?>">
                            <?php echo esc_html($payment->status_display ?? ucfirst($payment->status)); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        echo !empty($payment->order_date) 
                            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->order_date))
                            : '—';
                        ?>
                    </td>
                    <td>
                        <?php 
                        echo !empty($payment->payment_date) 
                            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->payment_date))
                            : '—';
                        ?>
                    </td>
                    <td>
                        <a href="?page=nsc-payments&action=view&id=<?php echo esc_attr($payment->payment_id); ?>" class="button button-small">
                            <?php esc_html_e('View', 'nsc-core'); ?>
                        </a>
                        
                        <?php if ($payment->status === 'pending' || $payment->status === 'created'): ?>
                        <a href="?page=nsc-payments&action=mark_as_paid&payment_id=<?php echo esc_attr($payment->payment_id); ?>&_wpnonce=<?php echo wp_create_nonce('mark_payment_paid_' . $payment->payment_id); ?>" 
                           class="button button-small button-primary mark-as-paid"
                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to mark this payment as paid? This will allow the participant to upload their video.', 'nsc-core'); ?>')">
                            <?php esc_html_e('Mark as Paid', 'nsc-core'); ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($payment->status === 'failed' || $payment->status === 'pending'): ?>
                        <a href="?page=nsc-payments&action=resend&id=<?php echo esc_attr($payment->payment_id); ?>" class="button button-small">
                            <?php esc_html_e('Resend', 'nsc-core'); ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.nsc-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.nsc-status-paid {
    background-color: #d4edda;
    color: #155724;
}

.nsc-status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.nsc-status-created {
    background-color: #cce5ff;
    color: #004085;
}

.nsc-status-failed {
    background-color: #f8d7da;
    color: #721c24;
}

.mark-as-paid {
    margin-left: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add smooth message fadeout
    setTimeout(function() {
        $('.notice').fadeOut('slow');
    }, 3000);
});
</script>