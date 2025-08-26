<?php
/**
 * Template: Confirm Video
 */

// Get current user
$current_user = wp_get_current_user();
if (!$current_user->exists()) {
    wp_redirect(home_url('/login'));
    exit;
}

// Get upload record
global $wpdb;
$upload = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}nsc_uploads WHERE user_id = %d AND status = 'pending' ORDER BY upload_date DESC LIMIT 1",
    $current_user->ID
));

if (!$upload) {
    wp_redirect(home_url('/upload-video'));
    exit;
}

// Enqueue video.js
wp_enqueue_style('videojs-css', 'https://vjs.zencdn.net/7.20.3/video-js.css');
wp_enqueue_script('videojs', 'https://vjs.zencdn.net/7.20.3/video.min.js', array(), '7.20.3', true);

get_header();
?>

<div class="nsc-container">
    <h2 class="nsc-page-title"> Step 4: Review Your Submission</h2>
    
    <p>Please review your video below. If you are satisfied with your submission, click "Confirm Submission". If you would like to upload a different video, click "Upload New Video".</p>
    
    <h2>Video Preview</h2>
    
    <div class="nsc-video-container">
        <video 
            id="nsc-video-player"
            class="video-js vjs-default-skin vjs-big-play-centered"
            controls
            width="600" 
            height="350"
            data-setup='{}'>
            <source src="<?php echo esc_url($upload->video_url); ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
    
    <div class="nsc-submission-details">
        <p><strong>Participant Name:</strong> <?php echo esc_html($current_user->first_name); ?> <?php echo esc_html($current_user->last_name); ?></p>
        <p><strong>Your ID:</strong> <?php echo esc_html($current_user->user_login); ?></p>
        <p><strong>Category:</strong> <?php echo esc_html($upload->category); ?></p>
        <p><strong>Upload Date:</strong> <?php echo esc_html(date('F j, Y g:i a', strtotime($upload->upload_date))); ?></p>
    </div>
    
    <div class="nsc-action-buttons">
        <form action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" id="nsc-confirm-form">
            <input type="hidden" name="action" value="nsc_confirm_video">
            <input type="hidden" name="upload_id" value="<?php echo esc_attr($upload->upload_id); ?>">
            <input type="hidden" name="nsc_confirm_nonce" value="<?php echo wp_create_nonce('nsc-confirm-video'); ?>">
            <button type="submit" class="nsc-button nsc-primary-button">Confirm Submission</button>
        </form>
        <br/>
        <a href="<?php echo esc_url(home_url('/upload-video')); ?>" class="nsc-button red">Re-Upload New Video</a>
    </div>
</div>
<style>
div#nsc-video-player {
    text-align: center;
    margin: 0 auto;
}
.nsc-video-player-dimensions {
    width: 100%;
    height: 414px;
}
    </style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle confirm submission button
    var confirmBtn = document.querySelector('.nsc-button.nsc-primary-button');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            var formData = new FormData();
            formData.append('action', 'nsc_confirm_video');
            
            // Simple AJAX request
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                        }
                    } catch (e) {
                        window.location.href = '<?php echo home_url('/thank-you'); ?>';
                    }
                } else {
                    // Fallback redirect
                    window.location.href = '<?php echo home_url('/thank-you'); ?>';
                }
            };
            xhr.onerror = function() {
                // Fallback redirect
                window.location.href = '<?php echo home_url('/thank-you'); ?>';
            };
            xhr.send(formData);
        });
    }
});
</script>

<?php get_footer(); ?>