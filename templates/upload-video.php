<?php
/**
 * NSC Video Upload Template
 */
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/upload-video')));
    exit;
}

global $wpdb;
$user_id = get_current_user_id();

// Check payment status
$payment_status = $wpdb->get_var($wpdb->prepare(
    "SELECT status FROM {$wpdb->prefix}nsc_payments 
    WHERE user_id = %d 
    ORDER BY payment_date DESC 
    LIMIT 1",
    $user_id
));

if ($payment_status !== 'paid') {
    wp_redirect(home_url('/payment'));
    exit;
}

// Check if already uploaded
$already_uploaded = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_uploads 
    WHERE user_id = %d AND status = 'submitted'",
    $user_id
));

if ($already_uploaded) {
    wp_redirect(home_url('/thank-you'));
    exit;
}

// Check for pending uploads (not yet confirmed)
$pending_upload = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}nsc_uploads 
    WHERE user_id = %d AND status = 'pending'
    ORDER BY upload_date DESC
    LIMIT 1",
    $user_id
));

// Get participant data for category information
$participant = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}nsc_participants 
    WHERE wp_user_id = %d",
    $user_id
));

// Load header
get_header();
?>

<div class="nsc-container nsc-upload-container">
    <h1><?php esc_html_e('Step 3: Upload Your Video', 'nsc-core'); ?></h1>
    
    <div class="nsc-upload-instructions">
        <h2><?php esc_html_e('Instructions', 'nsc-core'); ?></h2>
        <ul>
            <li><?php esc_html_e('Your video must be in MP4 format.', 'nsc-core'); ?></li>
            <li><?php esc_html_e('Maximum video length: 2 minutes.', 'nsc-core'); ?></li>
            <li><?php esc_html_e('Maximum file size: 30MB.', 'nsc-core'); ?></li>
            <li><?php esc_html_e('After uploading, you can preview your video before final submission.', 'nsc-core'); ?></li>
        </ul>
    </div>
    
    <?php if ($pending_upload): ?>
    <div class="nsc-pending-upload">
        <h3><?php esc_html_e('You have a pending upload', 'nsc-core'); ?></h3>
        <p><?php esc_html_e('You uploaded a video on', 'nsc-core'); ?> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($pending_upload->upload_date)); ?></p>
        <div class="nsc-actions">
            <a href="<?php echo esc_url(home_url('/confirm-video')); ?>" class="nsc-button"><?php esc_html_e('Review & Confirm', 'nsc-core'); ?></a>
            <button class="nsc-button nsc-button-secondary" id="nsc-delete-video" data-upload-id="<?php echo esc_attr($pending_upload->upload_id); ?>"><?php esc_html_e('Delete & Re-upload', 'nsc-core'); ?></button>
        </div>
    </div>
    <?php else: ?>
    <div class="nsc-upload-form-container">
        <form id="nsc-video-upload-form" enctype="multipart/form-data">
            <div class="nsc-form-row">
                <label for="nsc-video-file"><?php esc_html_e('Select Video File (MP4)', 'nsc-core'); ?></label>
                <input type="file" id="nsc-video-file" name="nsc_video_file" accept="video/mp4" required>
            </div>
            
            <div class="nsc-form-row">
                <div class="nsc-progress-container" style="display: none;">
                    <div class="nsc-progress-bar-wrapper">
                        <div class="nsc-progress-bar"></div>
                    </div>
                    <div class="nsc-progress-percentage">0%</div>
                </div>
            </div>
            
            <div class="nsc-form-row">
                <button type="submit" class="nsc-button" id="nsc-upload-button"><?php esc_html_e('Upload My Video', 'nsc-core'); ?></button>
            </div>
            
            <?php wp_nonce_field('nsc-video-upload', 'nsc_upload_nonce'); ?>
            <input type="hidden" name="action" value="nsc_upload_video">
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
            <input type="hidden" name="category" value="<?php echo esc_attr($participant->category); ?>">
        </form>
        
        <div id="nsc-upload-error" class="nsc-error-message" style="display: none;"></div>
    </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
