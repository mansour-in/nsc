<?php
/**
 * NSC Thank You Template
 */
if (!defined('ABSPATH')) exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/thank-you')));
    exit;
}

global $wpdb;
$user_id = get_current_user_id();

// Check if video was submitted
$submitted_video = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}nsc_uploads 
    WHERE user_id = %d AND status = 'submitted'
    ORDER BY upload_date DESC
    LIMIT 1",
    $user_id
));

// If no submitted video, redirect to upload page
if (!$submitted_video) {
    wp_redirect(home_url('/upload-video'));
    exit;
}

// Get participant data
$participant = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}nsc_participants 
    WHERE wp_user_id = %d",
    $user_id
));

// Enqueue video.js
wp_enqueue_style('videojs-css', 'https://vjs.zencdn.net/7.20.3/video-js.css');
wp_enqueue_script('videojs', 'https://vjs.zencdn.net/7.20.3/video.min.js', array(), '7.20.3', true);

// Load header
get_header();
?>

<div class="nsc-container nsc-thank-you-container">
    <div class="nsc-thank-you-message">
        <h1><?php esc_html_e('Thank You for Your Submission!', 'nsc-core'); ?></h1>
        
        <div class="nsc-success-checkmark">
            <svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-check-circle">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
        </div>
        
        <p><?php esc_html_e('Your video has been successfully submitted for the National Storytelling Championship!', 'nsc-core'); ?></p>
        
        <div class="nsc-submission-details">
            <h3><?php esc_html_e('Submission Details', 'nsc-core'); ?></h3>

            <?php if ($submitted_video && !empty($submitted_video->video_url)): ?>
        <div class="nsc-video-section">
            <div class="nsc-video-container">
                <video 
                    id="nsc-video-player"
                    class="video-js vjs-default-skin vjs-big-play-centered"
                    controls
                    width="640" 
                    height="360"
                    data-setup='{}'>
                    <source src="<?php echo esc_url($submitted_video->video_url); ?>" type="video/mp4">
                    <?php esc_html_e('Your browser does not support the video tag.', 'nsc-core'); ?>
                </video>
            </div>
        </div>
        <?php endif; ?>
            
            <div class="nsc-detail-row">
                <span class="nsc-detail-label"><?php esc_html_e('Participant:', 'nsc-core'); ?></span>
                <span class="nsc-detail-value"><?php echo esc_html(get_user_meta($user_id, 'first_name', true) . ' ' . get_user_meta($user_id, 'last_name', true)); ?></span>
            </div>
            <div class="nsc-detail-row">
                <span class="nsc-detail-label"><?php esc_html_e('Your ID:', 'nsc-core'); ?></span>
                <span class="nsc-detail-value"><?php echo esc_html(wp_get_current_user()->user_login); ?></span>
            </div>
            <div class="nsc-detail-row">
                <span class="nsc-detail-label"><?php esc_html_e('Category:', 'nsc-core'); ?></span>
                <span class="nsc-detail-value"><?php echo esc_html($participant->category); ?></span>
            </div>
            <div class="nsc-detail-row">
                <span class="nsc-detail-label"><?php esc_html_e('Submission Date:', 'nsc-core'); ?></span>
                <span class="nsc-detail-value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submitted_video->upload_date)); ?></span>
            </div>
        </div>
        
        <div class="nsc-next-steps">
            <h3><?php esc_html_e('What\'s Next?', 'nsc-core'); ?></h3>
            <p><?php esc_html_e('Our judges will review your submission and you will be notified of the results once judging is complete.', 'nsc-core'); ?></p>
            <p><?php esc_html_e('Thank you for participating in the National Storytelling Championship!', 'nsc-core'); ?></p>
        </div>
        
        <div class="nsc-thank-you-actions">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="nsc-button"><?php esc_html_e('Return to Homepage', 'nsc-core'); ?></a>
        </div>
    </div>
</div>

<style>
/* Styles for video container */
.nsc-video-section {
    margin: 25px 0;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 5px;
}
.nsc-video-container {
    max-width: 100%;
    margin: 15px auto;
    display: flex;
    justify-content: center;
}
.video-js {
    width: 100%;
    max-width: 640px;
    height: auto;
    aspect-ratio: 16/9;
}
/* Make video player responsive */
@media (max-width: 768px) {
    .video-js {
        max-width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize video player if it exists
    if (document.getElementById('nsc-video-player')) {
        var player = videojs('nsc-video-player', {
            responsive: true,
            fluid: true
        });
    }
});
</script>

<?php get_footer(); ?>