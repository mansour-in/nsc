<?php
/**
 * R2 Uploader Class
 * 
 * Handles video uploads to Cloudflare R2 storage.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NSC_R2_Uploader {
    
    /**
     * The S3 client instance
     */
    private static $s3_client = null;
    
    /**
     * R2 bucket name
     */
    private $bucket_name;
    
    /**
     * Initialize the R2 uploader class
     */
    public function __construct() {
        // Add AJAX endpoints
        add_action('wp_ajax_nsc_upload_video', array($this, 'handle_upload'));
        add_action('wp_ajax_nsc_confirm_video', array($this, 'handle_confirmation'));
        add_action('wp_ajax_nsc_delete_video', array($this, 'handle_deletion'));
        
        // Get R2 configuration
        $this->bucket_name = get_option('nsc_r2_bucket', 'acenovation-nsc-india');
        
        // Pre-load SDK for better performance
        $this->ensure_sdk_loaded();
    }
    
    /**
     * Ensure SDK is properly loaded
     */
    private function ensure_sdk_loaded() {
        if (!class_exists('Aws\S3\S3Client')) {
            if (file_exists(plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php')) {
                require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
            }
        }
    }
    
    /**
     * Initialize S3 client with error handling and retries
     */
    private function get_s3_client() {
        if (self::$s3_client !== null) {
            return self::$s3_client;
        }
        
        if (!class_exists('Aws\S3\S3Client')) {
            $this->ensure_sdk_loaded();
            
            if (!class_exists('Aws\S3\S3Client')) {
                error_log('NSC Error: AWS SDK not available after loading attempts');
                wp_send_json_error(array('message' => 'AWS SDK could not be loaded. Please contact support.'));
                exit;
            }
        }
        
        $endpoint = get_option('nsc_r2_endpoint', '');
        $access_key = get_option('nsc_r2_access_key', '');
        $secret_key = get_option('nsc_r2_secret_key', '');
        
        if (empty($endpoint) || empty($access_key) || empty($secret_key)) {
            error_log('NSC Error: R2 configuration missing - Endpoint: ' . (!empty($endpoint) ? 'Set' : 'Missing') . 
                      ', Access Key: ' . (!empty($access_key) ? 'Set' : 'Missing') . 
                      ', Secret Key: ' . (!empty($secret_key) ? 'Set' : 'Missing'));
            wp_send_json_error(array('message' => 'R2 storage configuration is incomplete. Please contact administrator.'));
            exit;
        }
        
        try {
            self::$s3_client = new Aws\S3\S3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => $endpoint,
                'credentials' => [
                    'key' => $access_key,
                    'secret' => $secret_key
                ],
                'use_path_style_endpoint' => true,
                'http' => [
                    'connect_timeout' => 10,
                    'timeout' => 60, // Increased timeout for larger files
                ],
                'retries' => 3 // Auto-retry on transient errors
            ]);
            
            return self::$s3_client;
        } catch (Exception $e) {
            error_log('NSC Error: Failed to initialize S3 client: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to connect to storage service. Please try again later.'));
            exit;
        }
    }
    
    /**
     * Handle video file upload via AJAX with improved error handling
     */
    public function handle_upload() {
        // Start profiling
        $start_time = microtime(true);
        
        // Verify nonce
        if (!isset($_POST['nsc_upload_nonce']) || !wp_verify_nonce($_POST['nsc_upload_nonce'], 'nsc-video-upload')) {
            error_log('NSC Security Error: Invalid nonce for video upload');
            wp_send_json_error(array('message' => 'Security verification failed.'));
            exit;
        }
        
        // Check if file is uploaded
        if (!isset($_FILES['nsc_video_file']) || !is_uploaded_file($_FILES['nsc_video_file']['tmp_name'])) {
            wp_send_json_error(array('message' => 'No file was uploaded.'));
            exit;
        }
        
        // Validate file type
        $file_type = wp_check_filetype($_FILES['nsc_video_file']['name']);
        if ($file_type['type'] !== 'video/mp4') {
            wp_send_json_error(array('message' => 'Only MP4 videos are allowed.'));
            exit;
        }
        
        // Validate file size (30MB max)
        if ($_FILES['nsc_video_file']['size'] > 30 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'File size exceeds the maximum limit of 30MB.'));
            exit;
        }
        
        // Get user and category
        $user_id = get_current_user_id();
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        if (empty($category)) {
            wp_send_json_error(array('message' => 'Category information is missing.'));
            exit;
        }
        
        try {
            // Get timestamp
            $timestamp = time();
            
            // Get username (the auto-generated one like NSC25INDJ3AA000009)
            $user = get_user_by('id', $user_id);
            $username = $user ? $user->user_login : 'user' . $user_id;
            
            // Format date as DDMMYY
            $date_format = date('dmy');
            
            // Generate random alphanumeric code (2 letters, 2 numbers)
            $letters = chr(rand(65, 90)) . chr(rand(65, 90)); // 2 random uppercase letters
            $numbers = rand(10, 99); // 2 random numbers
            $random_code = $letters . $numbers;
            
            // Create filename with the format you requested
            $filename = 'user_' . $username . '_' . $date_format . '_' . $random_code . '.mp4';
            
            // Initialize S3 client
            $s3 = $this->get_s3_client();
            
            // Try to upload to R2
            $result = $s3->putObject([
                'Bucket' => $this->bucket_name,
                'Key' => $filename,
                'Body' => fopen($_FILES['nsc_video_file']['tmp_name'], 'r'),
                'ContentType' => 'video/mp4',
                'ACL' => 'public-read',  // Make sure this line is present
                'Metadata' => [
                    'user_id' => (string) $user_id,
                    'category' => $category,
                    'upload_time' => (string) $timestamp,
                    'original_name' => $_FILES['nsc_video_file']['name']
                ]
            ]);
            
            // Get video URL
            $custom_domain = get_option('nsc_r2_custom_domain', '');
            
            // Build video URL using custom domain if available
            if (!empty($custom_domain)) {
                $video_url = trailingslashit($custom_domain) . $filename;
            } else {
                $video_url = $result['ObjectURL'];
            }
            
            // Performance logging
            $upload_time = microtime(true) - $start_time;
            error_log('NSC Upload Performance: ' . round($upload_time, 2) . 's for ' . round($_FILES['nsc_video_file']['size'] / 1024 / 1024, 2) . 'MB');
            error_log('NSC Upload Filename: ' . $filename);
            
            // Save to database
            $database = new NSC_Database();
            $database->save_video_upload($user_id, $video_url, $category);
        
            $redirect_url = home_url('/confirm-video');
            // Make sure the page exists before redirecting
            $confirm_page = get_page_by_path('confirm-video');
            if (!$confirm_page) {
                $redirect_url = home_url('/upload-video');
                error_log('NSC Warning: confirm-video page not found, redirecting to upload-video');
            }
            // Return success
            wp_send_json_success(array(
                'message' => 'Video uploaded successfully.',
                'video_url' => $video_url,
                'redirect_url' => $redirect_url
            ));
            
        } catch (Exception $e) {
            error_log('NSC Upload Error: ' . $e->getMessage() . ' - User ID: ' . $user_id);
            wp_send_json_error(array('message' => 'Upload failed: ' . $e->getMessage()));
        }
        
        exit;
    }
    
    /**
     * Handle video confirmation via AJAX
     */
    public function handle_confirmation() {
        // Verify nonce with simplified checks
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to confirm videos.'));
            exit;
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        
        // Get the latest pending upload for this user
        global $wpdb;
        $upload = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nsc_uploads WHERE user_id = %d AND status = 'pending' ORDER BY upload_date DESC LIMIT 1",
            $user_id
        ));
        
        if (!$upload) {
            wp_send_json_error(array('message' => 'No pending upload found.'));
            exit;
        }
        
        // Update upload status to 'submitted'
        $database = new NSC_Database();
        $result = $database->update_video_status($upload->upload_id, 'submitted');
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to confirm upload.'));
            exit;
        }
        
        // Return success with redirect
        wp_send_json_success(array(
            'message' => 'Video confirmed successfully.',
            'redirect_url' => home_url('/thank-you')
        ));
        
        exit;
    }
    
    /**
     * Handle video deletion via AJAX
     */
    public function handle_deletion() {
        // Basic verification
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to delete videos.'));
            exit;
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        
        // Get the latest upload for this user
        global $wpdb;
        $upload = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nsc_uploads WHERE user_id = %d ORDER BY upload_date DESC LIMIT 1",
            $user_id
        ));
        
        if (!$upload) {
            wp_send_json_error(array('message' => 'No upload found.'));
            exit;
        }
        
        try {
            // Delete from R2 if URL contains the bucket name
            if (strpos($upload->video_url, $this->bucket_name) !== false || strpos($upload->video_url, 'videos.nationalstorytellingchampionship.com') !== false) {
                // Extract key from URL
                $url_parts = parse_url($upload->video_url);
                $path = $url_parts['path'] ?? '';
                $key = basename($path);
                
                $s3 = $this->get_s3_client();
                $s3->deleteObject([
                    'Bucket' => $this->bucket_name,
                    'Key' => $key
                ]);
                
                error_log('NSC: Deleted file ' . $key . ' from R2 bucket ' . $this->bucket_name);
            }
            
            // Delete from database
            $database = new NSC_Database();
            $result = $database->delete_video_upload($upload->upload_id);
            
            if ($result === false) {
                wp_send_json_error(array('message' => 'Failed to delete upload from database.'));
                exit;
            }
            
            // Return success with redirect
            wp_send_json_success(array(
                'message' => 'Video deleted successfully.',
                'redirect_url' => home_url('/upload-video')
            ));
            
        } catch (Exception $e) {
            error_log('NSC Deletion Error: ' . $e->getMessage() . ' - Upload ID: ' . $upload->upload_id);
            
            // Even if R2 deletion fails, try to delete from database
            $database = new NSC_Database();
            $database->delete_video_upload($upload->upload_id);
            
            wp_send_json_error(array('message' => 'Deletion partially failed: ' . $e->getMessage()));
        }
        
        exit;
    }
}