<?php
/**
 * Admin Video Uploads Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('NSC Video Uploads', 'nsc-core'); ?></h1>
    
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <form method="get">
                <input type="hidden" name="page" value="nsc-uploads">
                <select name="category">
                    <option value=""><?php esc_html_e('All Categories', 'nsc-core'); ?></option>
                    <option value="J1" <?php selected($category_filter, 'J1'); ?>><?php esc_html_e('Junior 1 (3-4 years)', 'nsc-core'); ?></option>
                    <option value="J2" <?php selected($category_filter, 'J2'); ?>><?php esc_html_e('Junior 2 (5-7 years)', 'nsc-core'); ?></option>
                    <option value="J3" <?php selected($category_filter, 'J3'); ?>><?php esc_html_e('Junior 3 (8-12 years)', 'nsc-core'); ?></option>
                    <option value="S1" <?php selected($category_filter, 'S1'); ?>><?php esc_html_e('Senior 1 (13-15 years)', 'nsc-core'); ?></option>
                    <option value="S2" <?php selected($category_filter, 'S2'); ?>><?php esc_html_e('Senior 2 (16-18 years)', 'nsc-core'); ?></option>
                    <option value="S3" <?php selected($category_filter, 'S3'); ?>><?php esc_html_e('Senior 3 (19+ years)', 'nsc-core'); ?></option>
                </select>
                <select name="status">
                    <option value=""><?php esc_html_e('All Statuses', 'nsc-core'); ?></option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Pending', 'nsc-core'); ?></option>
                    <option value="submitted" <?php selected($status_filter, 'submitted'); ?>><?php esc_html_e('Submitted', 'nsc-core'); ?></option>
                    <option value="approved" <?php selected($status_filter, 'approved'); ?>><?php esc_html_e('Approved', 'nsc-core'); ?></option>
                    <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php esc_html_e('Rejected', 'nsc-core'); ?></option>
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
                <th><?php esc_html_e('Upload ID', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Participant', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Email', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Category', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Upload Date', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Status', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Score', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Actions', 'nsc-core'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($uploads)): ?>
            <tr>
                <td colspan="8"><?php esc_html_e('No uploads found.', 'nsc-core'); ?></td>
            </tr>
            <?php else: ?>
                <?php foreach ($uploads as $upload): ?>
                <tr>
                    <td><?php echo esc_html($upload->upload_id); ?></td>
                    <td><?php echo esc_html($upload->first_name . ' ' . $upload->last_name); ?></td>
                    <td><?php echo esc_html($upload->user_email); ?></td>
                    <td><?php echo esc_html($upload->category); ?></td>
                    <td>
                        <?php 
                        echo !empty($upload->upload_date) 
                            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($upload->upload_date))
                            : '—';
                        ?>
                    </td>
                    <td>
                        <span class="nsc-status nsc-status-<?php echo esc_attr($upload->status); ?>">
                            <?php echo esc_html(ucfirst($upload->status)); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo !empty($upload->marks) ? esc_html($upload->marks) : '—'; ?>
                    </td>
                    <td>
                        <?php if (!empty($upload->video_url)): ?>
                        <a href="<?php echo esc_url($upload->video_url); ?>" target="_blank" class="button button-small">
                            <?php esc_html_e('View', 'nsc-core'); ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($upload->status === 'submitted'): ?>
                        <a href="?page=nsc-uploads&action=judge&id=<?php echo esc_attr($upload->upload_id); ?>" class="button button-small">
                            <?php esc_html_e('Judge', 'nsc-core'); ?>
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

.nsc-status-submitted {
    background-color: #d1ecf1;
    color: #0c5460;
}

.nsc-status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.nsc-status-approved {
    background-color: #d4edda;
    color: #155724;
}

.nsc-status-rejected {
    background-color: #f8d7da;
    color: #721c24;
}
</style>