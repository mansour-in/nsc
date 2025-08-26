<?php
/**
 * Admin Dashboard Template
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('NSC Contest Dashboard', 'nsc-core'); ?></h1>
    
    <div class="nsc-admin-stats">
        <div class="nsc-stat-card">
            <h3><?php esc_html_e('Total Participants', 'nsc-core'); ?></h3>
            <div class="nsc-stat-value"><?php echo esc_html($total_participants); ?></div>
        </div>
        
        <div class="nsc-stat-card">
            <h3><?php esc_html_e('Payments Received', 'nsc-core'); ?></h3>
            <div class="nsc-stat-value"><?php echo esc_html($total_payments); ?></div>
        </div>
        
        <div class="nsc-stat-card">
            <h3><?php esc_html_e('Videos Submitted', 'nsc-core'); ?></h3>
            <div class="nsc-stat-value"><?php echo esc_html($total_uploads); ?></div>
        </div>
    </div>
    
    <div class="nsc-admin-category-stats">
        <h2><?php esc_html_e('Registration by Category', 'nsc-core'); ?></h2>
        <div class="nsc-category-grid">
            <?php foreach ($categories as $category): ?>
            <div class="nsc-category-card">
                <h3><?php echo esc_html($category->category); ?></h3>
                <div class="nsc-category-count"><?php echo esc_html($category->count); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="nsc-admin-recent">
        <h2><?php esc_html_e('Recent Registrations', 'nsc-core'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'nsc-core'); ?></th>
                    <th><?php esc_html_e('Email', 'nsc-core'); ?></th>
                    <th><?php esc_html_e('Category', 'nsc-core'); ?></th>
                    <th><?php esc_html_e('Date', 'nsc-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_registrations as $registration): ?>
                <tr>
                    <td><?php echo esc_html($registration->first_name . ' ' . $registration->last_name); ?></td>
                    <td><?php echo esc_html($registration->user_email); ?></td>
                    <td><?php echo esc_html($registration->category); ?></td>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($registration->registration_date)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.nsc-admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.nsc-stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
}

.nsc-stat-value {
    font-size: 2.5em;
    font-weight: bold;
    color: #4C85B2;
}

.nsc-category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.nsc-category-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    text-align: center;
}

.nsc-category-count {
    font-size: 1.8em;
    font-weight: bold;
    color: #4C85B2;
}

.nsc-admin-recent {
    margin-top: 30px;
}
</style>