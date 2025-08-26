<?php
/**
 * Admin Judges Template
 */
if (!defined('ABSPATH')) exit;

// Get available categories
$categories = [
    'J1' => __('Junior 1 (3-4 years)', 'nsc-core'),
    'J2' => __('Junior 2 (5-7 years)', 'nsc-core'),
    'J3' => __('Junior 3 (8-12 years)', 'nsc-core'),
    'S1' => __('Senior 1 (13-15 years)', 'nsc-core'),
    'S2' => __('Senior 2 (16-18 years)', 'nsc-core'),
    'S3' => __('Senior 3 (19+ years)', 'nsc-core'),
];
?>

<div class="wrap">
    <h1>
        <?php esc_html_e('NSC Judges', 'nsc-core'); ?>
        <a href="#" class="page-title-action" id="add-judge-btn"><?php esc_html_e('Add Judge', 'nsc-core'); ?></a>
    </h1>
    
    <div id="judge-form" class="nsc-admin-form" style="display:none;">
        <h2><?php esc_html_e('Add New Judge', 'nsc-core'); ?></h2>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="judge-email"><?php esc_html_e('Email', 'nsc-core'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="email" id="judge-email" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Judge\'s email address. Will be used for login.', 'nsc-core'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Assigned Categories', 'nsc-core'); ?></label>
                    </th>
                    <td>
                        <?php foreach ($categories as $code => $name): ?>
                        <label>
                            <input type="checkbox" name="categories[]" value="<?php echo esc_attr($code); ?>">
                            <?php echo esc_html($name); ?>
                        </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="add_judge" class="button button-primary" value="<?php esc_attr_e('Add Judge', 'nsc-core'); ?>">
                <button type="button" class="button" id="cancel-add-judge"><?php esc_html_e('Cancel', 'nsc-core'); ?></button>
            </p>
        </form>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Name', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Email', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Assigned Categories', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Videos Judged', 'nsc-core'); ?></th>
                <th><?php esc_html_e('Actions', 'nsc-core'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($judges)): ?>
            <tr>
                <td colspan="5"><?php esc_html_e('No judges found.', 'nsc-core'); ?></td>
            </tr>
            <?php else: ?>
                <?php foreach ($judges as $judge): ?>
                <tr>
                    <td><?php echo esc_html($judge->display_name); ?></td>
                    <td><?php echo esc_html($judge->user_email); ?></td>
                    <td>
                        <?php 
                        if (!empty($judge->assigned_categories)) {
                            $assigned = explode(',', $judge->assigned_categories);
                            $category_names = [];
                            
                            foreach ($assigned as $code) {
                                if (isset($categories[$code])) {
                                    $category_names[] = $categories[$code];
                                }
                            }
                            
                            echo esc_html(implode(', ', $category_names));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        $videos_judged = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_uploads WHERE judged_by = %d",
                            $judge->judge_id
                        ));
                        echo esc_html($videos_judged);
                        ?>
                    </td>
                    <td>
                        <a href="?page=nsc-judges&action=edit&id=<?php echo esc_attr($judge->judge_id); ?>" class="button button-small">
                            <?php esc_html_e('Edit', 'nsc-core'); ?>
                        </a>
                        <a href="?page=nsc-judges&action=reset-password&id=<?php echo esc_attr($judge->judge_id); ?>" class="button button-small">
                            <?php esc_html_e('Reset Password', 'nsc-core'); ?>
                        </a>
                        <a href="?page=nsc-judges&action=remove&id=<?php echo esc_attr($judge->judge_id); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove this judge?', 'nsc-core'); ?>')">
                            <?php esc_html_e('Remove', 'nsc-core'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.nsc-admin-form {
    background: white;
    border: 1px solid #ddd;
    padding: 20px;
    margin: 20px 0;
    border-radius: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#add-judge-btn').on('click', function(e) {
        e.preventDefault();
        $('#judge-form').show();
    });
    
    $('#cancel-add-judge').on('click', function() {
        $('#judge-form').hide();
    });
});
</script>