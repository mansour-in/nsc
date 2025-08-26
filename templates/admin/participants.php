<?php
/**
 * Admin Participants Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('NSC Participants', 'nsc-core'); ?></h1>
    
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <form method="get">
                <input type="hidden" name="page" value="nsc-participants">
                <select name="category">
                    <option value=""><?php esc_html_e('All Categories', 'nsc-core'); ?></option>
                    <option value="J1" <?php selected($category, 'J1'); ?>><?php esc_html_e('Junior 1 (3-4 years)', 'nsc-core'); ?></option>
                    <option value="J2" <?php selected($category, 'J2'); ?>><?php esc_html_e('Junior 2 (5-7 years)', 'nsc-core'); ?></option>
                    <option value="J3" <?php selected($category, 'J3'); ?>><?php esc_html_e('Junior 3 (8-12 years)', 'nsc-core'); ?></option>
                    <option value="S1" <?php selected($category, 'S1'); ?>><?php esc_html_e('Senior 1 (13-15 years)', 'nsc-core'); ?></option>
                    <option value="S2" <?php selected($category, 'S2'); ?>><?php esc_html_e('Senior 2 (16-18 years)', 'nsc-core'); ?></option>
                    <option value="S3" <?php selected($category, 'S3'); ?>><?php esc_html_e('Senior 3 (19+ years)', 'nsc-core'); ?></option>
                </select>
                <select name="payment_status">
                    <option value=""><?php esc_html_e('All Payment Statuses', 'nsc-core'); ?></option>
                    <option value="paid" <?php selected($payment_status, 'paid'); ?>><?php esc_html_e('Paid', 'nsc-core'); ?></option>
                    <option value="not_paid" <?php selected($payment_status, 'not_paid'); ?>><?php esc_html_e('Not Paid', 'nsc-core'); ?></option>
                </select>
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
                <th><?php esc_html_e('Name', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Email', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Category', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Country', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Registration Date', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Payment Status', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Video Upload', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Actions', 'nsc-core'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($participants)): ?>
            <tr>
                <td colspan="8"><?php esc_html_e('No participants found.', 'nsc-core'); ?></td>
            </tr>
            <?php else: ?>
                <?php foreach ($participants as $participant): ?>
                <tr>
                    <td><?php echo esc_html($participant->first_name . ' ' . $participant->last_name); ?></td>
                    <td><?php echo esc_html($participant->user_email); ?></td>
                    <td><?php echo esc_html($participant->category); ?></td>
                    <td><?php echo esc_html($participant->country_code); ?></td>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($participant->registration_date)); ?></td>
                    <td>
                        <span class="nsc-status nsc-status-<?php echo esc_attr($participant->payment_status); ?>">
                            <?php echo esc_html($participant->payment_display); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $has_upload = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_uploads WHERE user_id = %d AND status = 'submitted'",
                            $participant->wp_user_id
                        ));
                        echo $has_upload ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>';
                        ?>
                    </td>
                    <td>
                        <a href="?page=nsc-participants&action=view&id=<?php echo esc_attr($participant->participant_id); ?>" class="button button-small">
                            <?php esc_html_e('View', 'nsc-core'); ?>
                        </a>
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

.nsc-status-failed {
    background-color: #f8d7da;
    color: #721c24;
}
</style>