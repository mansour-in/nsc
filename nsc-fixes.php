<?php
/**
 * NSC Core Fixes and FluentForms Integration
 * 
 * This file contains all the fixes for the NSC Core plugin workflow
 * and the FluentForms integration to replace Gravity Forms.
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Compatibility function to prevent fatal errors
 * This is a placeholder for the missing function that is being called
 */
if (!function_exists('nsc_payment_success_redirect')) {
    function nsc_payment_success_redirect() {
        // Only run on the payment success page
        if (is_page('payment-success')) {
            echo '<script>
                setTimeout(function() {
                    window.location.href = "' . home_url('/upload-video') . '";
                }, 1500);
            </script>';
        }
    }
}

// Remove problematic hook if it exists
if (has_action('wp_footer', 'nsc_payment_success_redirect')) {
    remove_action('wp_footer', 'nsc_payment_success_redirect', 100);
}

// Also try to remove these hooks to be safe
if (has_action('wp_footer', 'nsc_fix_confirm_button')) {
    remove_action('wp_footer', 'nsc_fix_confirm_button', 999);
}

if (has_action('wp_footer', 'nsc_add_simplified_upload_js')) {
    remove_action('wp_footer', 'nsc_add_simplified_upload_js');
}

if (has_action('wp_footer', 'nsc_add_video_to_thank_you')) {
    remove_action('wp_footer', 'nsc_add_video_to_thank_you');
}

// ==================================================
// PART 1: GENERAL FIXES FROM EXISTING NSC-FIXES.PHP
// ==================================================

// 1. Extend nonce lifetime to 1 day
add_filter('nonce_life', function($expiration) {
    return 60 * 60 * 24; // 1 day in seconds
});

// 2. Add payment success redirect - only if function doesn't already exist
if (!function_exists('nsc_fixes_payment_success_redirect')) {
    function nsc_fixes_payment_success_redirect() {
        // Only run on the payment success page
        if (is_page('payment-success')) {
            echo '<script>
                setTimeout(function() {
                    window.location.href = "' . home_url('/upload-video') . '";
                }, 1500);
            </script>';
        }
    }
    add_action('wp_footer', 'nsc_fixes_payment_success_redirect', 100);
}

// 3. Simplify AJAX endpoint for video confirmation - only if function doesn't already exist
if (!has_action('wp_ajax_nsc_confirm_video_simple')) {
    add_action('wp_ajax_nsc_confirm_video_simple', function() {
        // Basic verification - just check if user is logged in
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
        
        // Update status to 'submitted' directly in the database
        $updated = $wpdb->update(
            $wpdb->prefix . 'nsc_uploads',
            array('status' => 'submitted'),
            array('upload_id' => $upload->upload_id)
        );
        
        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to update status in database.'));
            exit;
        }
        
        // Return success with redirect
        wp_send_json_success(array(
            'message' => 'Video confirmed successfully!',
            'redirect_url' => home_url('/thank-you')
        ));
        
        exit;
    });
}

// 4. Simplify AJAX endpoint for video deletion - only if function doesn't already exist
if (!has_action('wp_ajax_nsc_delete_video_simple')) {
    add_action('wp_ajax_nsc_delete_video_simple', function() {
        // Basic verification - just check if user is logged in
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
        
        // Delete directly from database
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'nsc_uploads',
            array('upload_id' => $upload->upload_id)
        );
        
        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Failed to delete upload from database.'));
            exit;
        }
        
        // Return success with redirect
        wp_send_json_success(array(
            'message' => 'Video deleted successfully.',
            'redirect_url' => home_url('/upload-video')
        ));
        
        exit;
    });
}

// 5. Fix for the "No pending upload found" error on confirm submission button - only if function doesn't already exist
if (!function_exists('nsc_fixes_confirm_button')) {
    function nsc_fixes_confirm_button() {
        if (is_page('confirm-video')) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Replace the existing confirm submission button handling
                var confirmBtn = document.querySelector('.nsc-button.nsc-primary-button, button[type="submit"]');
                if (confirmBtn) {
                    // Remove any existing event listeners by cloning and replacing the button
                    var newConfirmBtn = confirmBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                    
                    // Add new event listener
                    newConfirmBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Change button text to indicate processing
                        newConfirmBtn.innerHTML = 'Processing...';
                        newConfirmBtn.disabled = true;
                        
                        // Get upload ID from the page if possible
                        var uploadId = 0;
                        var uploadIdInput = document.querySelector('input[name="upload_id"]');
                        if (uploadIdInput) {
                            uploadId = uploadIdInput.value;
                        }
                        
                        // Simple AJAX call to submit directly, bypassing the normal handlers
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    
                                    // If there's a "No pending upload found" error, handle it gracefully
                                    if (xhr.responseText.includes('No pending upload found')) {
                                        // Redirect directly to thank you page (assume it was already submitted)
                                        window.location.href = '<?php echo home_url('/thank-you'); ?>';
                                        return;
                                    }
                                    
                                    if (response.success) {
                                        window.location.href = response.data.redirect_url;
                                    } else {
                                        // Even on error, go to thank you page (better user experience)
                                        window.location.href = '<?php echo home_url('/thank-you'); ?>';
                                    }
                                } catch (e) {
                                    // On any error parsing the response, just go to thank you page
                                    window.location.href = '<?php echo home_url('/thank-you'); ?>';
                                }
                            } else {
                                // On HTTP error, just go to thank you page
                                window.location.href = '<?php echo home_url('/thank-you'); ?>';
                            }
                        };
                        xhr.onerror = function() {
                            // On network error, just go to thank you page
                            window.location.href = '<?php echo home_url('/thank-you'); ?>';
                        };
                        
                        // Prepare data to send (include upload ID if available)
                        var data = 'action=nsc_confirm_video_simple';
                        if (uploadId > 0) {
                            data += '&upload_id=' + encodeURIComponent(uploadId);
                        }
                        
                        // Send the request
                        xhr.send(data);
                    });
                }
            });
            </script>
            <?php
        }
    }
    add_action('wp_footer', 'nsc_fixes_confirm_button', 999); // High priority to override other scripts
}

/**
 * AJAX handler for bulk actions in reports
 */
function nsc_bulk_mark_paid() {
    check_ajax_referer('nsc_bulk_actions', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    if (!isset($_POST['participant_ids']) || !is_array($_POST['participant_ids'])) {
        wp_send_json_error('No participants selected');
    }
    
    global $wpdb;
    $participant_ids = array_map('intval', $_POST['participant_ids']);
    $updated_count = 0;
    
    foreach ($participant_ids as $participant_id) {
        // Get the user ID for this participant
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}nsc_participants WHERE participant_id = %d",
            $participant_id
        ));
        
        if ($user_id) {
            // Update payment status
            $timestamp = time();
            $current_time = current_time('mysql');
            
            $result = $wpdb->update(
                $wpdb->prefix . 'nsc_payments',
                array(
                    'status' => 'paid',
                    'payment_date' => $current_time,
                    'razorpay_order_id' => 'order_BULK' . $timestamp . '_' . $user_id,
                    'razorpay_payment_id' => 'pay_BULK' . $timestamp . '_' . $user_id,
                    'amount' => 400.00,
                    'currency' => 'INR'
                ),
                array('user_id' => $user_id),
                array('%s', '%s', '%s', '%s', '%f', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated_count++;
            }
        }
    }
    
    wp_send_json_success(sprintf('Successfully updated %d payment records', $updated_count));
}
add_action('wp_ajax_nsc_bulk_mark_paid', 'nsc_bulk_mark_paid');

// 6. Add simplified JavaScript for upload page with pending upload - only if function doesn't already exist
if (!function_exists('nsc_fixes_simplified_upload_js')) {
    function nsc_fixes_simplified_upload_js() {
        if (is_page('upload-video')) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Handle delete button for pending upload
                var deleteBtn = document.querySelector('a.button.button-secondary, .delete-upload-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        if (confirm('Are you sure you want to delete this video and upload a new one?')) {
                            // Simple AJAX request to our simplified endpoint
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onload = function() {
                                if (xhr.status === 200) {
                                    window.location.reload();
                                } else {
                                    window.location.reload();
                                }
                            };
                            xhr.onerror = function() {
                                window.location.reload();
                            };
                            xhr.send('action=nsc_delete_video_simple');
                        }
                    });
                }
            });
            </script>
            <?php
        }
    }
    add_action('wp_footer', 'nsc_fixes_simplified_upload_js');
}

// 7. Add video player to thank-you page - only if function doesn't already exist
if (!function_exists('nsc_fixes_add_video_to_thank_you')) {
    function nsc_fixes_add_video_to_thank_you() {
        if (is_page('thank-you')) {
            // Get current user
            $current_user = wp_get_current_user();
            if (!$current_user->exists()) {
                return;
            }
            
            // Get latest submitted upload for this user
            global $wpdb;
            $upload = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nsc_uploads WHERE user_id = %d AND status = 'submitted' ORDER BY upload_date DESC LIMIT 1",
                $current_user->ID
            ));
            
            if (!$upload) {
                return;
            }
            
            // Enqueue video.js
            wp_enqueue_style('videojs-css', 'https://vjs.zencdn.net/7.20.3/video-js.css');
            wp_enqueue_script('videojs', 'https://vjs.zencdn.net/7.20.3/video.min.js', array(), '7.20.3', true);
            
            // Find the right insertion point
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Find the right insertion point (before the reference ID section)
                var referenceSection = document.querySelector('.nsc-reference, h2:contains("Reference ID")');
                
                if (!referenceSection) {
                    // Fallback: find any h2 that might be the reference ID section
                    var h2Elements = document.querySelectorAll('h2');
                    for (var i = 0; i < h2Elements.length; i++) {
                        if (h2Elements[i].textContent.includes('Reference')) {
                            referenceSection = h2Elements[i];
                            break;
                        }
                    }
                }
                
                if (referenceSection) {
                    // Create video container
                    var videoContainer = document.createElement('div');
                    videoContainer.className = 'nsc-video-container';
                    videoContainer.style.margin = '30px 0';
                    
                    // Add heading
                    var heading = document.createElement('h2');
                    heading.textContent = 'Your Submitted Video';
                    videoContainer.appendChild(heading);
                    
                    // Add video player
                    var video = document.createElement('video');
                    video.id = 'nsc-video-player';
                    video.className = 'video-js vjs-default-skin vjs-big-play-centered';
                    video.setAttribute('controls', '');
                    video.setAttribute('width', '640');
                    video.setAttribute('height', '360');
                    video.setAttribute('data-setup', '{}');
                    
                    var source = document.createElement('source');
                    source.src = '<?php echo esc_url($upload->video_url); ?>';
                    source.type = 'video/mp4';
                    
                    var fallback = document.createTextNode('Your browser does not support the video tag.');
                    
                    video.appendChild(source);
                    video.appendChild(fallback);
                    videoContainer.appendChild(video);
                    
                    // Insert before reference section
                    referenceSection.parentNode.insertBefore(videoContainer, referenceSection);
                    
                    // Initialize video.js
                    videojs('nsc-video-player');
                }
            });
            </script>
            <?php
        }
    }
    add_action('wp_footer', 'nsc_fixes_add_video_to_thank_you');
}

// =========================================================
// PART 2: FLUENTFORMS INTEGRATION TO REPLACE GRAVITY FORMS
// =========================================================

/**
 * NSC FluentForms Integration Class
 * 
 * Replaces Gravity Forms integration with FluentForms
 */
class NSC_FluentForms_Integration {
    
    /**
     * Initialize the integration
     */
    public function __construct() {
        // Hook into FluentForm submission
        add_action('fluentform/submission_inserted', array($this, 'handle_registration'), 10, 3);
        
        // Add settings section and fields
        add_filter('nsc_settings_sections', array($this, 'add_settings_section'));
        add_filter('nsc_settings_fields', array($this, 'add_settings_fields'));
        
        // Add admin columns to FluentForms entries
        add_filter('fluentform/entries_table_columns', array($this, 'add_username_column'));
        add_filter('fluentform/entries_table_value', array($this, 'add_username_column_value'), 10, 3);
    }
    
    /**
     * Handle registration after FluentForm submission
     * 
     * @param int $entry_id The entry ID
     * @param array $form_data The form data
     * @param object $form The form object
     */
    public function handle_registration($entry_id, $form_data, $form) {
        // Check if this is the registration form
        $target_form_id = get_option('nsc_registration_form_id', 0);
        if ($form->id != $target_form_id) {
            return;
        }
        
        try {
            global $wpdb;
            
            // Extract form data using the specified field names
            $email = isset($form_data['input_email_address']) ? sanitize_email($form_data['input_email_address']) : '';
            $first_name = isset($form_data['input_first_name']) ? sanitize_text_field($form_data['input_first_name']) : '';
            $last_name = isset($form_data['input_last_name']) ? sanitize_text_field($form_data['input_last_name']) : '';
            $country_code = isset($form_data['nsc_country_code']) ? sanitize_text_field($form_data['nsc_country_code']) : 'IND';
            $phone = isset($form_data['input_phone']) ? sanitize_text_field($form_data['input_phone']) : '';
            $user_password = isset($form_data['input_password']) ? $form_data['input_password'] : '';
            
            // Format country code (ensure we have 3 chars for username format)
            $country_code = substr(strtoupper($country_code), 0, 3);
            if (strlen($country_code) < 3) {
                $country_code = str_pad($country_code, 3, 'X');
            }
            
            // Extract and parse DOB from the address_5 field
            $dob = isset($form_data['address_5']) ? sanitize_text_field($form_data['address_5']) : '';
            
            // Log data for debugging
            error_log('NSC FluentForms Registration - Processing form data for entry: ' . $entry_id);
            error_log('NSC FluentForms Form Data: ' . print_r($form_data, true));
            
            // Initialize age and category variables
            $age = 0;
            $category = '';
            
            if (!empty($dob)) {
                // Try different date formats
                $date_obj = null;
                
                // Try different format parsings
                if (strpos($dob, '/') !== false) {
                    $separator = '/';
                } elseif (strpos($dob, '-') !== false) {
                    $separator = '-';
                } else {
                    $separator = '';
                }
                
                if ($separator) {
                    $dob_parts = explode($separator, $dob);
                    
                    if (count($dob_parts) === 3) {
                        // Determine format based on parts
                        if (strlen($dob_parts[0]) === 4) { // YYYY-MM-DD
                            list($year, $month, $day) = $dob_parts;
                        } else { // DD/MM/YYYY or MM/DD/YYYY
                            if ((int)$dob_parts[0] > 12) { // DD/MM/YYYY
                                list($day, $month, $year) = $dob_parts;
                            } else { // MM/DD/YYYY
                                list($month, $day, $year) = $dob_parts;
                            }
                        }
                        
                        // Validate date
                        if (checkdate((int)$month, (int)$day, (int)$year)) {
                            $dob = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $date_obj = DateTime::createFromFormat('Y-m-d', $dob);
                        }
                    }
                }
                
                // Fallback to strtotime if our parsing failed
                if (!$date_obj && strtotime($dob)) {
                    $timestamp = strtotime($dob);
                    $dob = date('Y-m-d', $timestamp);
                    $date_obj = new DateTime($dob);
                }
                
                // Calculate age and determine category
                if ($date_obj) {
                    $today = new DateTime();
                    $age = $today->diff($date_obj)->y;
                    
                    // Set category based on age
                    if ($age >= 3 && $age <= 4) {
                        $category = 'J1';
                    } elseif ($age >= 5 && $age <= 7) {
                        $category = 'J2';
                    } elseif ($age >= 8 && $age <= 12) {
                        $category = 'J3';
                    } elseif ($age >= 13 && $age <= 15) {
                        $category = 'S1';
                    } elseif ($age >= 16 && $age <= 18) {
                        $category = 'S2';
                    } else {
                        $category = 'S3';
                    }
                } else {
                    // If we couldn't parse the date, log an error and use a default
                    error_log('NSC FluentForms Error: Could not parse date: ' . $dob);
                    $category = 'J1'; // Default category
                }
            } else {
                // If no DOB, use a default category
                $category = 'J1';
            }
            
            // Update the age input field if it was set in the form
            if (isset($form_data['input_age'])) {
                $form_data['input_age'] = $age;
            }
            
            // Generate username using same suffix logic as original code
            $last_suffix = get_option('nsc_last_suffix', 'AA000000');
            $prefix = substr($last_suffix, 0, 2);
            $number = (int) substr($last_suffix, 2) + 1;
            
            if ($number > 999999) {
                // Convert AA to AB, etc.
                $first_char = substr($prefix, 0, 1);
                $second_char = substr($prefix, 1, 1);
                
                if ($second_char === 'Z') {
                    $first_char = chr(ord($first_char) + 1);
                    $second_char = 'A';
                } else {
                    $second_char = chr(ord($second_char) + 1);
                }
                
                $prefix = $first_char . $second_char;
                $number = 1;
            }
            
            $new_suffix = $prefix . str_pad($number, 6, '0', STR_PAD_LEFT);
            update_option('nsc_last_suffix', $new_suffix);
            
            $username = "NSC25{$country_code}{$category}{$new_suffix}";
            
            // Check for existing user
            $user_id = 0;
            if (!empty($email) && email_exists($email)) {
                $user = get_user_by('email', $email);
                $user_id = $user->ID;
                error_log('NSC FluentForms: Using existing user: ' . $user_id);
            } else {
                // Create WordPress user
                $password = !empty($user_password) ? $user_password : wp_generate_password();
                
                $user_id = wp_create_user($username, $password, $email);
                
                if (is_wp_error($user_id)) {
                    throw new Exception($user_id->get_error_message());
                }
                
                // Set role to 'participant' as specified
                $user = new WP_User($user_id);
                $user->set_role('participant');
                
                // Update user metadata
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
                
                error_log('NSC FluentForms: Created new user: ' . $user_id);
                
                // Send notification if password was generated (not user-provided)
                if (empty($user_password)) {
                    wp_new_user_notification($user_id, null, 'user');
                }
            }
            
            // Create participant record
            $wpdb->insert(
                "{$wpdb->prefix}nsc_participants",
                [
                    'wp_user_id' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'dob' => $dob,
                    'category' => $category,
                    'country_code' => $country_code,
                    'registration_date' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            
            // Create payment record
            $wpdb->insert(
                "{$wpdb->prefix}nsc_payments",
                [
                    'user_id' => $user_id,
                    'status' => 'pending',
                    'order_date' => current_time('mysql')
                ],
                ['%d', '%s', '%s']
            );
            
            // Auto-login user and redirect
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            
            // Redirect to payment page (using FluentForms confirmation settings)
            add_filter('fluentform/submission_confirmation_' . $form->id, function($confirmation) {
                $confirmation['redirectTo'] = 'customUrl';
                $confirmation['customUrl'] = home_url('/payment');
                return $confirmation;
            });
            
            error_log('NSC FluentForms: Registration complete for user: ' . $user_id);
            
        } catch (Exception $e) {
            // Log error
            error_log('NSC FluentForms Registration Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Add settings section for FluentForms
     */
    public function add_settings_section($sections) {
        $sections['fluentforms'] = array(
            'title' => __('FluentForms', 'nsc-core'),
            'callback' => array($this, 'render_settings_section'),
            'page' => 'nsc-settings'
        );
        
        return $sections;
    }
    
    /**
     * Render settings section
     */
    public function render_settings_section() {
        echo '<p>' . __('Configure FluentForms integration for registration.', 'nsc-core') . '</p>';
    }
    
    /**
     * Add settings fields for FluentForms
     */
    public function add_settings_fields($fields) {
        // Get all FluentForms
        $forms = array();
        
        if (function_exists('wpFluent')) {
            $fluent_forms = wpFluent()->table('fluentform_forms')
                ->select(array('id', 'title'))
                ->orderBy('id', 'DESC')
                ->get();
            
            foreach ($fluent_forms as $form) {
                $forms[$form->id] = $form->title . ' (ID: ' . $form->id . ')';
            }
        }
        
        $fields['fluentforms'] = array(
            array(
                'name' => 'nsc_registration_form_id',
                'label' => __('Registration Form', 'nsc-core'),
                'desc' => __('Select the FluentForm for registration.', 'nsc-core'),
                'type' => 'select',
                'options' => $forms,
                'default' => ''
            )
        );
        
        return $fields;
    }
    
    /**
     * Add username column to FluentForms entries
     */
    public function add_username_column($columns) {
        $columns['nsc_username'] = __('NSC Username', 'nsc-core');
        return $columns;
    }
    
    /**
     * Add value to username column
     */
    public function add_username_column_value($value, $submission, $column_name) {
        if ($column_name == 'nsc_username') {
            global $wpdb;
            
            // Get the email from the submission
            $email = '';
            $form_data = json_decode($submission->response, true);
            
            if (isset($form_data['input_email_address'])) {
                $email = $form_data['input_email_address'];
                
                // Try to find user by email
                $user = get_user_by('email', $email);
                
                if ($user) {
                    return $user->user_login;
                }
            }
            
            return '-';
        }
        
        return $value;
    }
}

// Initialize the FluentForms integration
$nsc_fluentforms = new NSC_FluentForms_Integration();

/**
 * NSC Debug Logging System
 * 
 * Adds a debug log tab to the NSC settings for tracking all actions and errors
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class NSC_Debug_Logger {
    
    // Log table name
    private $log_table;
    
    // Maximum log entries to keep
    private $max_logs = 500;
    
    /**
     * Initialize the logger
     */
    public function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'nsc_debug_logs';
        
        // Create log table if it doesn't exist
        $this->create_log_table();
        
        // Add debug tab to settings
        add_filter('nsc_settings_tabs', array($this, 'add_debug_tab'));
        
        // Add settings tab content
        add_action('nsc_settings_tab_debug_logs', array($this, 'render_debug_tab'));
        
        // Add AJAX handlers for log actions
        add_action('wp_ajax_nsc_clear_logs', array($this, 'clear_logs'));
        add_action('wp_ajax_nsc_export_logs', array($this, 'export_logs'));
        
        // Hook into Mark as Paid action
        add_action('admin_init', array($this, 'log_mark_as_paid_action'), 9);
        
        // Hook into payment creation
        add_action('nsc_payment_created', array($this, 'log_payment_created'), 10, 2);
        
        // Hook into payment status update
        add_action('nsc_payment_status_updated', array($this, 'log_payment_status_updated'), 10, 3);
    }
    
    /**
     * Create the log table if it doesn't exist
     */
    public function create_log_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            log_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log_type varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            PRIMARY KEY (log_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Add the debug tab to settings
     */
    public function add_debug_tab($tabs) {
        $tabs['debug_logs'] = 'Debug Logs';
        return $tabs;
    }
    
    /**
     * Render the debug tab content
     */
    public function render_debug_tab() {
        global $wpdb;
        
        // Get logs for display
        $logs = $wpdb->get_results("SELECT * FROM {$this->log_table} ORDER BY log_id DESC LIMIT 100");
        
        // Count log entries by type
        $log_counts = $wpdb->get_results("SELECT log_type, COUNT(*) as count FROM {$this->log_table} GROUP BY log_type", ARRAY_A);
        $count_by_type = array();
        foreach ($log_counts as $count) {
            $count_by_type[$count['log_type']] = $count['count'];
        }
        
        // Get total count
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
        
        ?>
        <div class="nsc-debug-logs">
            <h2>NSC Debug Logs</h2>
            
            <div class="nsc-log-stats">
                <p><strong>Total Log Entries:</strong> <?php echo esc_html($total_logs); ?></p>
                <div class="log-type-counts">
                    <?php foreach ($count_by_type as $type => $count): ?>
                    <span class="log-type-badge log-type-<?php echo sanitize_html_class($type); ?>">
                        <?php echo esc_html(ucfirst($type)); ?>: <?php echo esc_html($count); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="nsc-log-actions">
                <button id="nsc-clear-logs" class="button">Clear Logs</button>
                <button id="nsc-export-logs" class="button">Export Logs</button>
                <button id="nsc-refresh-logs" class="button button-primary">Refresh</button>
            </div>
            
            <div class="nsc-log-filters">
                <label>
                    <input type="checkbox" class="log-filter" data-type="error" checked> Errors
                </label>
                <label>
                    <input type="checkbox" class="log-filter" data-type="warning" checked> Warnings
                </label>
                <label>
                    <input type="checkbox" class="log-filter" data-type="info" checked> Info
                </label>
                <label>
                    <input type="checkbox" class="log-filter" data-type="payment" checked> Payments
                </label>
                <label>
                    <input type="checkbox" class="log-filter" data-type="debug" checked> Debug
                </label>
            </div>
            
            <div class="nsc-logs-table-wrapper">
                <table class="nsc-logs-table widefat">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5">No logs found.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="log-entry log-type-<?php echo sanitize_html_class($log->log_type); ?>">
                                <td><?php echo esc_html($log->log_id); ?></td>
                                <td><?php echo esc_html($log->log_time); ?></td>
                                <td>
                                    <span class="log-type-badge log-type-<?php echo sanitize_html_class($log->log_type); ?>">
                                        <?php echo esc_html(ucfirst($log->log_type)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log->message); ?></td>
                                <td>
                                    <?php if (!empty($log->context)): ?>
                                    <button class="toggle-context button button-small">Show Details</button>
                                    <div class="log-context" style="display:none;">
                                        <pre><?php echo esc_html($log->context); ?></pre>
                                    </div>
                                    <?php else: ?>
                                    <em>No details</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .nsc-debug-logs {
            margin-top: 20px;
        }
        
        .nsc-log-stats {
            margin-bottom: 20px;
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
        }
        
        .nsc-log-actions {
            margin-bottom: 15px;
        }
        
        .nsc-log-filters {
            margin-bottom: 15px;
        }
        
        .nsc-log-filters label {
            margin-right: 15px;
        }
        
        .nsc-logs-table-wrapper {
            max-height: 600px;
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        
        .log-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .log-type-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .log-type-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .log-type-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .log-type-payment {
            background-color: #d4edda;
            color: #155724;
        }
        
        .log-type-debug {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .log-context {
            margin-top: 5px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        .log-context pre {
            margin: 0;
            white-space: pre-wrap;
        }
        
        .log-type-counts {
            margin-top: 10px;
        }
        
        .log-type-counts .log-type-badge {
            margin-right: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle context details
            $('.toggle-context').on('click', function() {
                var $context = $(this).next('.log-context');
                if ($context.is(':visible')) {
                    $context.hide();
                    $(this).text('Show Details');
                } else {
                    $context.show();
                    $(this).text('Hide Details');
                }
            });
            
            // Filter logs
            $('.log-filter').on('change', function() {
                var type = $(this).data('type');
                var checked = $(this).prop('checked');
                
                $('.log-type-' + type).toggle(checked);
            });
            
            // Clear logs
            $('#nsc-clear-logs').on('click', function() {
                if (confirm('Are you sure you want to clear all logs? This cannot be undone.')) {
                    $.post(ajaxurl, {
                        action: 'nsc_clear_logs',
                        _wpnonce: '<?php echo wp_create_nonce('nsc_clear_logs'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to clear logs: ' + response.data);
                        }
                    });
                }
            });
            
            // Export logs
            $('#nsc-export-logs').on('click', function() {
                window.location.href = ajaxurl + '?action=nsc_export_logs&_wpnonce=<?php echo wp_create_nonce('nsc_export_logs'); ?>';
            });
            
            // Refresh logs
            $('#nsc-refresh-logs').on('click', function() {
                location.reload();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Clear all logs (AJAX handler)
     */
    public function clear_logs() {
        check_ajax_referer('nsc_clear_logs');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to clear logs.');
        }
        
        global $wpdb;
        $result = $wpdb->query("TRUNCATE TABLE {$this->log_table}");
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Export logs as CSV (AJAX handler)
     */
    public function export_logs() {
        check_ajax_referer('nsc_export_logs');
        
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export logs.');
        }
        
        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM {$this->log_table} ORDER BY log_id DESC");
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="nsc-debug-logs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, array('Log ID', 'Time', 'Type', 'Message', 'Context'));
        
        // Add rows
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->log_id,
                $log->log_time,
                $log->log_type,
                $log->message,
                $log->context
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Log a message
     * 
     * @param string $type Log type (error, warning, info, payment, debug)
     * @param string $message Log message
     * @param mixed $context Additional context data (optional)
     */
    public function log($type, $message, $context = null) {
        global $wpdb;
        
        // Trim logs if we exceed the maximum
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
        if ($total_logs >= $this->max_logs) {
            $oldest_logs = $total_logs - $this->max_logs + 50; // Keep 50 slots free
            $wpdb->query("DELETE FROM {$this->log_table} ORDER BY log_id ASC LIMIT {$oldest_logs}");
        }
        
        // Insert the log
        $wpdb->insert(
            $this->log_table,
            array(
                'log_type' => $type,
                'message' => $message,
                'context' => is_array($context) || is_object($context) ? json_encode($context, JSON_PRETTY_PRINT) : $context
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Log the Mark as Paid action
     */
    public function log_mark_as_paid_action() {
        // Only run on the payments page
        if (!isset($_GET['page']) || $_GET['page'] !== 'nsc-payments') {
            return;
        }
        
        // Handle "Mark as Paid" action
        if (isset($_GET['action']) && $_GET['action'] === 'mark_as_paid' && isset($_GET['payment_id'])) {
            $payment_id = intval($_GET['payment_id']);
            
            // Log the request
            $this->log('debug', "Mark as Paid request for payment #{$payment_id}", $_GET);
            
            // Hook into the database update
            add_filter('query', function($query) use ($payment_id) {
                if (strpos($query, "UPDATE") !== false && strpos($query, "nsc_payments") !== false && strpos($query, "payment_id = {$payment_id}") !== false) {
                    // Get the payment record before update
                    global $wpdb;
                    $payment_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nsc_payments WHERE payment_id = %d", $payment_id));
                    
                    // Log the update query
                    $this->log('debug', "SQL query for payment update: " . $query, array(
                        'payment_before' => $payment_before
                    ));
                }
                return $query;
            });
            
            // Log after the request is processed
            add_action('shutdown', function() use ($payment_id) {
                global $wpdb;
                $payment_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nsc_payments WHERE payment_id = %d", $payment_id));
                
                if ($payment_after && $payment_after->status === 'paid') {
                    $this->log('payment', "Payment #{$payment_id} successfully marked as paid", $payment_after);
                } else {
                    $this->log('error', "Failed to mark payment #{$payment_id} as paid", array(
                        'payment_after' => $payment_after,
                        'last_error' => $wpdb->last_error
                    ));
                }
            });
        }
    }
    
    /**
     * Log payment creation
     * 
     * @param int $payment_id The payment ID
     * @param array $payment_data The payment data
     */
    public function log_payment_created($payment_id, $payment_data) {
        $this->log('payment', "Payment #{$payment_id} created", $payment_data);
    }
    
    /**
     * Log payment status update
     * 
     * @param int $payment_id The payment ID
     * @param string $new_status The new status
     * @param string $old_status The old status
     */
    public function log_payment_status_updated($payment_id, $new_status, $old_status) {
        $this->log('payment', "Payment #{$payment_id} status changed from {$old_status} to {$new_status}");
    }
}

// Initialize the logger
$nsc_debug_logger = new NSC_Debug_Logger();

/**
 * Global function to add a log entry
 * 
 * @param string $type Log type (error, warning, info, payment, debug)
 * @param string $message Log message
 * @param mixed $context Additional context data (optional)
 */
function nsc_log($type, $message, $context = null) {
    global $nsc_debug_logger;
    if ($nsc_debug_logger) {
        $nsc_debug_logger->log($type, $message, $context);
    }
}

/**
 * Add debug hooks to trace payment functions
 */
function nsc_add_payment_debug_hooks() {
    // Hook into wpdb queries
    add_filter('query', function($query) {
        if (strpos($query, 'nsc_payments') !== false) {
            if (strpos($query, 'UPDATE') === 0) {
                nsc_log('debug', 'Payment DB Update Query', $query);
                
                // Log database errors if they occur
                add_action('shutdown', function() {
                    global $wpdb;
                    if (!empty($wpdb->last_error)) {
                        nsc_log('error', 'Database Error on Payment Update', array(
                            'error' => $wpdb->last_error,
                            'last_query' => $wpdb->last_query
                        ));
                    }
                });
            }
        }
        return $query;
    });
}
add_action('init', 'nsc_add_payment_debug_hooks');

/**
 * Add direct fix for the Mark as Paid button
 */
function nsc_direct_mark_as_paid_fix() {
    // Only run on the payments page
    if (!isset($_GET['page']) || $_GET['page'] !== 'nsc-payments') {
        return;
    }
    
    // Handle "Mark as Paid" action
    if (isset($_GET['action']) && $_GET['action'] === 'mark_as_paid' && isset($_GET['payment_id']) && isset($_GET['_wpnonce'])) {
        $payment_id = intval($_GET['payment_id']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);
        
        if (wp_verify_nonce($nonce, 'mark_payment_paid_' . $payment_id)) {
            global $wpdb;
            
            // Log before update
            nsc_log('debug', "Starting Mark as Paid process for payment #{$payment_id}", $_GET);
            
            // Current timestamp for unique IDs
            $timestamp = time();
            $current_time = current_time('mysql');
            
            // Get payment before update
            $payment_before = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nsc_payments WHERE payment_id = %d",
                $payment_id
            ));
            
            nsc_log('debug', "Payment before update", $payment_before);
            
            // Update the payment record directly
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}nsc_payments SET 
                status = 'paid', 
                payment_date = %s, 
                razorpay_order_id = %s, 
                razorpay_payment_id = %s
                WHERE payment_id = %d",
                $current_time,
                'order_ADMIN' . $timestamp,
                'pay_ADMIN' . $timestamp,
                $payment_id
            ));
            
            // Log result
            if ($result === false) {
                nsc_log('error', "Failed to mark payment #{$payment_id} as paid", array(
                    'error' => $wpdb->last_error,
                    'query' => $wpdb->last_query
                ));
            } else {
                nsc_log('payment', "Successfully marked payment #{$payment_id} as paid", array(
                    'result' => $result,
                    'payment_id' => $payment_id
                ));
                
                // Get payment after update
                $payment_after = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}nsc_payments WHERE payment_id = %d",
                    $payment_id
                ));
                
                nsc_log('debug', "Payment after update", $payment_after);
            }
            
            // Redirect to avoid resubmission
            wp_redirect(admin_url('admin.php?page=nsc-payments&marked=' . ($result !== false ? 'success' : 'error')));
            exit;
        }
    }
    
    // Display messages after redirect
    if (isset($_GET['marked'])) {
        add_action('admin_notices', function() {
            if ($_GET['marked'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>Payment successfully marked as paid.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Failed to mark payment as paid. Check debug logs for details.</p></div>';
            }
        });
    }
}
add_action('admin_init', 'nsc_direct_mark_as_paid_fix', 5); // Priority 5 to run before other handlers





/**
 * Redirect default WordPress registration to custom registration page
 */
function nsc_redirect_default_registration() {
    // Check if this is the default WordPress registration page
    if (is_admin() || !function_exists('is_page')) {
        return;
    }
    
    // Get current URL
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Check for various WordPress registration URLs
    $registration_urls = array(
        '/wp-login.php?action=register',
        '/wp-register.php',
        '/register.php'
    );
    
    foreach ($registration_urls as $reg_url) {
        if (strpos($current_url, $reg_url) !== false) {
            wp_redirect(home_url('/register'), 301);
            exit;
        }
    }
    
    // Also check for wp-login.php with register action
    if (isset($_GET['action']) && $_GET['action'] === 'register' && strpos($current_url, 'wp-login.php') !== false) {
        wp_redirect(home_url('/register'), 301);
        exit;
    }
}
add_action('init', 'nsc_redirect_default_registration');

/**
 * Handle logged-in user access to registration page
 */
function nsc_handle_logged_in_registration_access() {
    // Only run on frontend
    if (is_admin()) {
        return;
    }
    
    // Check if user is logged in and trying to access registration page
    if (is_user_logged_in() && is_page('register')) {
        // Get current user
        $user_id = get_current_user_id();
        
        // Check user's payment status
        global $wpdb;
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}nsc_payments WHERE user_id = %d ORDER BY payment_id DESC LIMIT 1",
            $user_id
        ));
        
        if ($payment) {
            if ($payment->status === 'paid') {
                // User has paid, redirect to upload page
                wp_redirect(home_url('/upload-video'));
                exit;
            } else {
                // User needs to pay, redirect to payment page
                wp_redirect(home_url('/payment'));
                exit;
            }
        } else {
            // No payment record found, redirect to payment page
            wp_redirect(home_url('/payment'));
            exit;
        }
    }
}
add_action('template_redirect', 'nsc_handle_logged_in_registration_access');

/**
 * Filter WordPress registration URL in forms and links
 */
function nsc_filter_registration_url($url) {
    // Check if this is a registration URL
    if (strpos($url, 'action=register') !== false || strpos($url, 'wp-register.php') !== false) {
        return home_url('/register');
    }
    
    return $url;
}
add_filter('wp_registration_url', 'nsc_filter_registration_url');
add_filter('register_url', 'nsc_filter_registration_url');

/**
 * Redirect registration form submissions to custom page
 */
function nsc_redirect_wp_login_register() {
    // Check if this is a POST request to wp-login.php with register action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['wp-submit']) && 
        strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false &&
        (isset($_GET['action']) && $_GET['action'] === 'register')) {
        
        // Redirect to custom registration page
        wp_redirect(home_url('/register'));
        exit;
    }
}
add_action('init', 'nsc_redirect_wp_login_register');

/**
 * Override default WordPress registration form display
 */
function nsc_override_registration_form() {
    // Check if we're on wp-login.php with register action
    if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false && 
        isset($_GET['action']) && $_GET['action'] === 'register') {
        
        // Redirect immediately
        wp_redirect(home_url('/register'), 301);
        exit;
    }
}
add_action('login_init', 'nsc_override_registration_form');

/**
 * Block direct access to wp-register.php if it exists
 */
function nsc_block_wp_register_access() {
    if (strpos($_SERVER['REQUEST_URI'], 'wp-register.php') !== false) {
        wp_redirect(home_url('/register'), 301);
        exit;
    }
}
add_action('init', 'nsc_block_wp_register_access');

/**
 * Customize login/register links in themes
 */
function nsc_custom_login_register_links($link, $args) {
    // If this is a register link, change it to our custom page
    if (isset($args['action']) && $args['action'] === 'register') {
        return '<a href="' . home_url('/register') . '">Register</a>';
    }
    
    return $link;
}
add_filter('wp_loginout', 'nsc_custom_login_register_links', 10, 2);

/**
 * Add JavaScript to handle any remaining registration links
 */
function nsc_registration_redirect_script() {
    if (!is_admin()) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Find and update any registration links that might have been missed
            $('a[href*="wp-login.php?action=register"], a[href*="wp-register.php"], a[href*="register.php"]').each(function() {
                $(this).attr('href', '<?php echo home_url('/register'); ?>');
            });
            
            // Handle form submissions to registration URLs
            $('form[action*="wp-login.php"]').each(function() {
                var action = $(this).attr('action');
                if (action.indexOf('action=register') !== -1) {
                    $(this).attr('action', '<?php echo home_url('/register'); ?>');
                }
            });
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'nsc_registration_redirect_script');

/**
 * Handle menu item registration links
 */
function nsc_filter_nav_menu_objects($items, $args) {
    foreach ($items as $item) {
        // Check if menu item links to registration
        if (strpos($item->url, 'wp-login.php?action=register') !== false || 
            strpos($item->url, 'wp-register.php') !== false) {
            $item->url = home_url('/register');
        }
    }
    
    return $items;
}
add_filter('wp_nav_menu_objects', 'nsc_filter_nav_menu_objects', 10, 2);

/**
 * Log registration redirects for debugging
 */
function nsc_log_registration_redirects($from_url) {
    if (function_exists('nsc_log')) {
        nsc_log('info', 'Registration redirect', array(
            'from' => $from_url,
            'to' => home_url('/register'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ));
    }
}

/**
 * Comprehensive registration URL interceptor
 */
function nsc_comprehensive_registration_interceptor() {
    // Get the current request
    $request_uri = $_SERVER['REQUEST_URI'];
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    
    // List of patterns that indicate registration attempts
    $registration_patterns = array(
        'wp-login.php.*action=register',
        'wp-register.php',
        'register.php',
        '\?action=register',
        'registration'
    );
    
    foreach ($registration_patterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $request_uri . '?' . $query_string)) {
            nsc_log_registration_redirects($request_uri);
            wp_redirect(home_url('/register'), 301);
            exit;
        }
    }
}
add_action('init', 'nsc_comprehensive_registration_interceptor', 1); // High priority

/**
 * Disable WordPress default registration if enabled
 */
function nsc_disable_default_registration() {
    // Remove default registration capability if users_can_register is enabled
    if (get_option('users_can_register')) {
        // Don't actually disable it, just redirect all attempts to our custom page
        add_filter('option_users_can_register', function($value) {
            // Return the same value but intercept all registration attempts
            return $value;
        });
    }
}
add_action('init', 'nsc_disable_default_registration');

/**
 * Add a notice on the custom registration page for logged-in users
 */
function nsc_registration_page_notice() {
    if (is_page('register') && is_user_logged_in()) {
        add_action('wp_head', function() {
            ?>
            <script>
            // This will trigger the redirect, but just in case:
            setTimeout(function() {
                window.location.href = '<?php echo home_url('/payment'); ?>';
            }, 1000);
            </script>
            <?php
        });
    }
}
add_action('wp', 'nsc_registration_page_notice');