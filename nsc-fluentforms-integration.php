<?php
/**
 * NSC FluentForms Integration
 * 
 * Replaces Gravity Forms integration with FluentForms for the NSC Core plugin.
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle FluentForms integration for NSC Core
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
        
        // Register a higher priority redirect handler
        add_action('fluentform/before_submission_confirmation', array($this, 'handle_redirect'), 9, 3);
    }
    
    /**
     * Handle redirection for registered users
     */
    public function handle_redirect($confirmation, $form, $submission_id) {
        // Check if this is the registration form
        $target_form_id = get_option('nsc_registration_form_id', 0);
        if ($form->id != $target_form_id) {
            return $confirmation;
        }
        
        // Get the user_id from our session if available
        $user_id = get_transient('nsc_registered_user_' . $submission_id);
        
        if ($user_id) {
            // If we found a registered user for this submission, redirect to payment
            $confirmation['redirectTo'] = 'customUrl';
            $confirmation['customUrl'] = home_url('/payment');
            
            // Auto-login user if they're not already logged in
            if (!is_user_logged_in()) {
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id, true);
            }
            
            // Remove the transient as we've used it
            delete_transient('nsc_registered_user_' . $submission_id);
        }
        
        return $confirmation;
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
            $dob = isset($form_data['dob']) ? sanitize_text_field($form_data['dob']) : '';
            
            // Log data for debugging
            error_log('NSC FluentForms Registration - Processing form data for entry: ' . $entry_id);
            
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
                
                // Set role - must be 'participant' as your code expects
                $user = new WP_User($user_id);
                $user->set_role('participant');
                
                // Update user metadata
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
                
                // Store phone number in billing_phone meta for compatibility with reports
                if (!empty($phone)) {
                    update_user_meta($user_id, 'billing_phone', $phone);
                }
                
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
            
            // Store the user ID in a transient for the redirect handler
            set_transient('nsc_registered_user_' . $entry_id, $user_id, 5 * MINUTE_IN_SECONDS);
            
            // Set login cookies for the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            
            error_log('NSC FluentForms: Registration complete for user: ' . $user_id);
            
            // Directly set a cookie to help with redirection (belt and suspenders approach)
            setcookie('nsc_redirect_to_payment', '1', time() + 300, '/');
            
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
            // Rest of your existing column code...
            
            return $value;
        }
        
        return $value;
    }
}

// Initialize the class
$nsc_fluentforms_integration = new NSC_FluentForms_Integration();