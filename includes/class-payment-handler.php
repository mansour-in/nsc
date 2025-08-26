<?php
/**
 * Payment Handler Class
 * 
 * Handles Razorpay payment processing
 */
if (!defined('ABSPATH')) {
    exit;
}

class NSC_Payment_Handler {
    
    /**
     * Razorpay API instance
     */
    private $api;
    
    /**
     * Initialize the payment handler class
     */
    public function __construct() {
        // Add AJAX endpoints
        add_action('wp_ajax_nsc_process_payment', array($this, 'process_payment'));
        
        // Add Gravity Forms hooks for registration
        add_action('gform_after_submission', array($this, 'handle_registration'), 10, 2);
        
        // Add login redirect filter
        add_filter('login_redirect', array($this, 'login_redirect'), 10, 3);

        //Redirect after payment
        //add_action('wp_footer', 'nsc_payment_success_redirect', 100);
    }
    
    /**
     * Get Razorpay API instance
     */
    private function get_api() {
        if (!$this->api) {
            if (!class_exists('Razorpay\Api\Api')) {
                wp_die('Razorpay SDK not loaded. Please check plugin installation.');
            }
            
            $key_id = get_option('nsc_razorpay_key_id', '');
            $key_secret = get_option('nsc_razorpay_secret_key', '');
            
            if (empty($key_id) || empty($key_secret)) {
                wp_die('Razorpay configuration is missing. Please contact administrator.');
            }
            
            $this->api = new Razorpay\Api\Api($key_id, $key_secret);
        }
        
        return $this->api;
    }
    
    /**
     * Process Razorpay webhook
     */
    public function process_webhook() {
        // Get webhook payload
        $webhook_body = file_get_contents('php://input');
        $webhook_data = json_decode($webhook_body, true);
        
        // Verify webhook signature
        $webhook_signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        
        if (empty($webhook_signature)) {
            http_response_code(400);
            exit('Invalid webhook signature');
        }
        
        try {
            $api = $this->get_api();
            $api->utility->verifyWebhookSignature($webhook_body, $webhook_signature, get_option('nsc_razorpay_webhook_secret', ''));
            
            // Process payment event
            if ($webhook_data['event'] === 'payment.captured' || $webhook_data['event'] === 'payment.authorized') {
                $payment_id = $webhook_data['payload']['payment']['entity']['id'];
                $order_id = $webhook_data['payload']['payment']['entity']['order_id'];
                
                // Update payment status in database
                $database = new NSC_Database();
                $database->update_payment($order_id, $payment_id, 'paid');
            }
            
            http_response_code(200);
            exit('Webhook processed successfully');
        } catch (Exception $e) {
            error_log('Razorpay webhook error: ' . $e->getMessage());
            http_response_code(400);
            exit('Webhook processing failed');
        }
    }
    
    /**
     * Process Razorpay payment via AJAX
     */
    public function process_payment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nsc-payment-nonce')) {
            wp_die('Security verification failed.');
        }
        
        // Get payment details
        $payment_id = sanitize_text_field($_POST['razorpay_payment_id'] ?? '');
        $order_id = sanitize_text_field($_POST['razorpay_order_id'] ?? '');
        $signature = sanitize_text_field($_POST['razorpay_signature'] ?? '');
        
        // Validate parameters
        if (empty($payment_id) || empty($order_id) || empty($signature)) {
            wp_die('Missing payment parameters.');
        }
        
        try {
            // Verify payment signature
            $api = $this->get_api();
            $attributes = array(
                'razorpay_order_id' => $order_id,
                'razorpay_payment_id' => $payment_id,
                'razorpay_signature' => $signature
            );
            
            $api->utility->verifyPaymentSignature($attributes);
            
            // Update payment status in database
            $database = new NSC_Database();
            $result = $database->update_payment($order_id, $payment_id, 'paid');
            
            if ($result === false) {
                throw new Exception('Failed to update payment record.');
            }
            
            // Redirect to upload page
            wp_redirect(home_url('/upload-video'));
            exit;
        } catch (Exception $e) {
            wp_die('Payment verification failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle registration after Gravity Forms submission
     */
    public function handle_registration($entry, $form) {
        // Check if this is the NSC registration form
        $target_form_id = get_option('nsc_registration_form_id', 0);
        if ($form['id'] != $target_form_id) {
            return;
        }
        
        try {
            global $wpdb;
            
            // Extract form data (adjust field IDs as needed)
            $email = rgar($entry, '13'); // Email field
            $first_name = rgar($entry, '15.3'); // First name
            $last_name = rgar($entry, '15.6'); // Last name
            $country_code = substr(strtoupper(rgar($entry, '6') ?? 'IND'), 0, 3); // Country field
            
            // Parse DOB
            $dob = rgar($entry, '9'); // DOB field
            $separator = (strpos($dob, '/') !== false) ? '/' : '-';
            $dob_parts = explode($separator, $dob);
            
            if (count($dob_parts) !== 3) {
                throw new Exception('Invalid date format');
            }
            
            // Reorder to YYYY-MM-DD
            if (strlen($dob_parts[0]) === 4) { // YYYY-MM-DD
                list($year, $month, $day) = $dob_parts;
            } else { // DD/MM/YYYY
                list($day, $month, $year) = $dob_parts;
            }
            
            if (!checkdate($month, $day, $year)) {
                throw new Exception('Invalid date components');
            }
            
            $dob = "$year-$month-$day";
            $dob_obj = DateTime::createFromFormat('Y-m-d', $dob);
            
            // Calculate age & category
            $today = new DateTime();
            $age = $today->diff($dob_obj)->y;
            
            $category = '';
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
            
            // Generate username
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
            if (email_exists($email)) {
                $user = get_user_by('email', $email);
                $user_id = $user->ID;
            } else {
                // Create WordPress user
                $password = wp_generate_password();
                $user_id = wp_create_user($username, $password, $email);
                
                if (is_wp_error($user_id)) {
                    throw new Exception($user_id->get_error_message());
                }
                
                // Set role
                $user = new WP_User($user_id);
                $user->set_role('participant');
                
                // Update user metadata
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
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

            // Create or update payment record
            $existing_payment = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT payment_id FROM {$wpdb->prefix}nsc_payments WHERE user_id = %d",
                    $user_id
                )
            );

            if ($existing_payment) {
                $wpdb->update(
                    "{$wpdb->prefix}nsc_payments",
                    [
                        'status' => 'pending',
                        'order_date' => current_time('mysql')
                    ],
                    ['payment_id' => $existing_payment],
                    ['%s', '%s'],
                    ['%d']
                );
            } else {
                $wpdb->insert(
                    "{$wpdb->prefix}nsc_payments",
                    [
                        'user_id' => $user_id,
                        'status' => 'pending',
                        'order_date' => current_time('mysql')
                    ],
                    ['%d', '%s', '%s']
                );
            }
            
            // Auto-login user and redirect
            wp_set_auth_cookie($user_id, true);
            
            // Set redirect URL
            GFFormsModel::update_lead_property($entry['id'], 'post_id', home_url('/payment'));
            
        } catch (Exception $e) {
            // Log error
            error_log('NSC Registration Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Redirect participant after login
     */
    public function login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && in_array('participant', (array) $user->roles)) {
            // Get payment status
            $database = new NSC_Database();
            $payment_status = $database->get_payment_status($user->ID);
            
            // Check video upload status
            $upload = $database->get_upload($user->ID);
            
            if ($payment_status === 'paid') {
                if ($upload && $upload->status === 'submitted') {
                    return home_url('/thank-you');
                } else {
                    return home_url('/upload-video');
                }
            } else {
                return home_url('/payment');
            }
        }
        
        return $redirect_to;
    }

    // Force redirect to upload video page after successful payment
    function nsc_payment_success_redirect() {
        echo '<script>
            setTimeout(function() {
                window.location.href = "' . home_url('/upload-video') . '";
            }, 1500);
        </script>';
    }
}
