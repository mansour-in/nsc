<?php
/**
 * NSC Add Participant Admin Form
 * 
 * Allows administrators to manually create participants
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle the Add Participant admin functionality
 */
class NSC_Admin_Add_Participant {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // Add admin menu item
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle form submission
        add_action('admin_init', array($this, 'handle_form_submission'));
    }
    
    /**
     * Add submenu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'nsc-contest',
            'Add Participant',
            'Add Participant',
            'manage_options',
            'nsc-add-participant',
            array($this, 'render_add_participant_page')
        );
    }
    
    /**
     * Render the Add Participant page
     */
    public function render_add_participant_page() {
        ?>
        <div class="wrap">
            <h1>Add New Participant</h1>
            
            <?php
            // Show success message if set
            if (isset($_GET['message']) && $_GET['message'] == 'success') {
                $username = isset($_GET['username']) ? sanitize_text_field($_GET['username']) : '';
                echo '<div class="notice notice-success is-dismissible"><p>Participant successfully created with username: <strong>' . esc_html($username) . '</strong></p></div>';
            }
            ?>
            
            <div class="card">
                <h2>Create a New Participant</h2>
                <p>Use this form to manually add a participant to the National Storytelling Championship. 
                   This will create a WordPress user account, participant record, and optionally a paid payment record.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('nsc_create_participant', 'nsc_participant_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="first_name">First Name <span class="required">*</span></label></th>
                            <td>
                                <input type="text" name="first_name" id="first_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="last_name">Last Name <span class="required">*</span></label></th>
                            <td>
                                <input type="text" name="last_name" id="last_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email">Email Address <span class="required">*</span></label></th>
                            <td>
                                <input type="email" name="email" id="email" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="password">Password</label></th>
                            <td>
                                <input type="password" name="password" id="password" class="regular-text">
                                <p class="description">Leave blank to generate a random password.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="country_code">Country Code <span class="required">*</span></label></th>
                            <td>
                                <input type="text" name="country_code" id="country_code" class="regular-text" required placeholder="IND" maxlength="3">
                                <p class="description">3-letter country code (e.g., IND, USA)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="dob">Date of Birth <span class="required">*</span></label></th>
                            <td>
                                <input type="date" name="dob" id="dob" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="category">Category</label></th>
                            <td>
                                <select name="category" id="category">
                                    <option value="J1">J1 (3-4 years)</option>
                                    <option value="J2">J2 (5-7 years)</option>
                                    <option value="J3">J3 (8-12 years)</option>
                                    <option value="S1">S1 (13-15 years)</option>
                                    <option value="S2">S2 (16-18 years)</option>
                                    <option value="S3">S3 (19+ years)</option>
                                </select>
                                <p class="description">Will be automatically determined based on date of birth, but can be overridden here.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="payment_status">Payment Status</label></th>
                            <td>
                                <select name="payment_status" id="payment_status">
                                    <option value="created">Created (Payment Required)</option>
                                    <option value="paid">Paid (Payment Marked as Paid)</option>
                                </select>
                                <p class="description">If marked as paid, the participant can directly upload videos without making a payment.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <input type="hidden" name="action" value="nsc_create_participant">
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Create Participant">
                    </p>
                </form>
            </div>
        </div>
        <style>
            .required {
                color: red;
            }
            .card {
                max-width: 800px;
                padding: 20px;
                background: white;
                border: 1px solid #ccc;
                border-radius: 3px;
                margin-top: 20px;
            }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Auto-calculate category based on date of birth
            $('#dob').on('change', function() {
                var dob = new Date($(this).val());
                var today = new Date();
                var age = today.getFullYear() - dob.getFullYear();
                
                // Adjust age if birthday hasn't occurred yet this year
                if (today.getMonth() < dob.getMonth() || 
                    (today.getMonth() == dob.getMonth() && today.getDate() < dob.getDate())) {
                    age--;
                }
                
                // Set category based on age
                var category = '';
                if (age >= 3 && age <= 4) {
                    category = 'J1';
                } else if (age >= 5 && age <= 7) {
                    category = 'J2';
                } else if (age >= 8 && age <= 12) {
                    category = 'J3';
                } else if (age >= 13 && age <= 15) {
                    category = 'S1';
                } else if (age >= 16 && age <= 18) {
                    category = 'S2';
                } else {
                    category = 'S3';
                }
                
                $('#category').val(category);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'nsc_create_participant') {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nsc_participant_nonce']) || !wp_verify_nonce($_POST['nsc_participant_nonce'], 'nsc_create_participant')) {
            wp_die('Security check failed. Please try again.');
        }
        
        // Validate required fields
        $required_fields = array('first_name', 'last_name', 'email', 'country_code', 'dob');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_die('Please fill in all required fields.');
            }
        }
        
        // Get form data
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $password = !empty($_POST['password']) ? $_POST['password'] : wp_generate_password();
        $country_code = strtoupper(sanitize_text_field($_POST['country_code']));
        $dob = sanitize_text_field($_POST['dob']);
        $category = sanitize_text_field($_POST['category']);
        $payment_status = sanitize_text_field($_POST['payment_status']);
        
        // Format country code (ensure we have 3 chars for username format)
        $country_code = substr($country_code, 0, 3);
        if (strlen($country_code) < 3) {
            $country_code = str_pad($country_code, 3, 'X');
        }
        
        // Generate username using category-specific suffix counter
        $option_name = 'nsc_last_suffix_' . $category;
        $last_suffix = get_option($option_name, 'AA000000');
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
        update_option($option_name, $new_suffix);

        $username = "NSC25{$country_code}{$category}{$new_suffix}";
        
        try {
            global $wpdb;
            
            // Check for existing user
            $user_id = 0;
            if (email_exists($email)) {
                wp_die('A user with this email already exists. Please use a different email address.');
            }
            
            // Create WordPress user
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                wp_die($user_id->get_error_message());
            }
            
            // Set role
            $user = new WP_User($user_id);
            $user->set_role('participant');
            
            // Update user metadata
            update_user_meta($user_id, 'first_name', $first_name);
            update_user_meta($user_id, 'last_name', $last_name);
            
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
            
            // Current timestamp
            $timestamp = time();
            $current_time = current_time('mysql');
            
            // Create payment record
            $payment_data = [
                'user_id' => $user_id,
                'status' => $payment_status,
                'amount' => '400.00', // Set to 400.00 as in the example
                'currency' => 'INR',
                'payment_method' => 'admin',
                'order_date' => $current_time
            ];
            
            // Add additional fields for paid status
            if ($payment_status === 'paid') {
                $payment_data['razorpay_order_id'] = 'order_ADMIN' . $timestamp;
                $payment_data['razorpay_payment_id'] = 'pay_ADMIN' . $timestamp;
                $payment_data['transaction_id'] = 'ADMIN-' . $timestamp;
                $payment_data['payment_date'] = $current_time;
            }
            
            $wpdb->insert(
                "{$wpdb->prefix}nsc_payments",
                $payment_data,
                array_fill(0, count($payment_data), '%s') // Use string format for all fields
            );
            
            // Redirect to success page
            wp_redirect(admin_url('admin.php?page=nsc-add-participant&message=success&username=' . urlencode($username)));
            exit;
            
        } catch (Exception $e) {
            wp_die('Error creating participant: ' . $e->getMessage());
        }
    }
}

// Initialize the class
$nsc_admin_add_participant = new NSC_Admin_Add_Participant();