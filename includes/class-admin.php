<?php
/**
 * Admin Class
 * 
 * Handles admin interface and settings
 */
if (!defined('ABSPATH')) {
    exit;
}

class NSC_Admin {
    
    /**
     * Initialize the admin class
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $current_user = wp_get_current_user();
        $is_reporter = in_array('reporter', $current_user->roles) && !current_user_can('manage_options');
        
        if ($is_reporter) {
            // Reporter only gets access to reports
            add_menu_page(
                'NSC Reports',
                'NSC Reports',
                'access_nsc_reports',
                'nsc-reports',
                array($this, 'display_reports'),
                'dashicons-chart-bar',
                6
            );
            return;
        }
        
        add_menu_page(
            'NSC Contest',
            'NSC Contest',
            'manage_options',
            'nsc-contest',
            array($this, 'display_dashboard'),
            'dashicons-awards',
            6
        );
        
        add_submenu_page(
            'nsc-contest',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'nsc-contest',
            array($this, 'display_dashboard')
        );
        
        add_submenu_page(
            'nsc-contest',
            'Participants',
            'Participants',
            'manage_options',
            'nsc-participants',
            array($this, 'display_participants')
        );
        
        add_submenu_page(
            'nsc-contest',
            'Payments',
            'Payments',
            'manage_options',
            'nsc-payments',
            array($this, 'display_payments')
        );
        
        add_submenu_page(
            'nsc-contest',
            'Video Uploads',
            'Video Uploads',
            'manage_options',
            'nsc-uploads',
            array($this, 'display_uploads')
        );
        
        add_submenu_page(
            'nsc-contest',
            'Judges',
            'Judges',
            'manage_options',
            'nsc-judges',
            array($this, 'display_judges')
        );
        
        add_submenu_page(
            'nsc-contest',
            'Settings',
            'Settings',
            'manage_options',
            'nsc-settings',
            array($this, 'display_settings')
        );
        
        add_submenu_page(
            'nsc-contest',
            'Logs',
            'Logs',
            'manage_options',
            'nsc-logs',
            array($this, 'display_logs')
        );
        
        add_submenu_page(
            'nsc-contest',
            'Reports',
            'Reports',
            'manage_options',
            'nsc-reports',
            array($this, 'display_reports')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('nsc_general_settings', 'nsc_registration_form_id');
        
        register_setting('nsc_r2_settings', 'nsc_r2_endpoint');
        register_setting('nsc_r2_settings', 'nsc_r2_access_key');
        register_setting('nsc_r2_settings', 'nsc_r2_secret_key');
        register_setting('nsc_r2_settings', 'nsc_r2_bucket');
        register_setting('nsc_r2_settings', 'nsc_r2_custom_domain');
        
        register_setting('nsc_razorpay_settings', 'nsc_razorpay_key_id');
        register_setting('nsc_razorpay_settings', 'nsc_razorpay_secret_key');
        register_setting('nsc_razorpay_settings', 'nsc_razorpay_webhook_secret');
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard() {
        global $wpdb;
        
        // Get statistics
        $total_participants = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_participants");
        $total_payments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_payments WHERE status = 'paid'");
        $total_uploads = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nsc_uploads WHERE status = 'submitted'");
        
        // Get category counts
        $categories = $wpdb->get_results("
            SELECT category, COUNT(*) as count 
            FROM {$wpdb->prefix}nsc_participants 
            GROUP BY category
        ");
        
        // Get recent registrations
        $recent_registrations = $wpdb->get_results("
            SELECT p.*, u.user_email 
            FROM {$wpdb->prefix}nsc_participants p
            JOIN {$wpdb->users} u ON p.wp_user_id = u.ID
            ORDER BY p.registration_date DESC
            LIMIT 10
        ");
        
        // Display dashboard
        include NSC_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
    
    /**
     * Display participants page
     */
    public function display_participants() {
        global $wpdb;
        
        // Pagination settings
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Filter settings
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $payment_status = isset($_GET['payment_status']) ? sanitize_text_field($_GET['payment_status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Build WHERE clause
        $where_conditions = array();
        $where_params = array();
        
        if (!empty($category)) {
            $where_conditions[] = "p.category = %s";
            $where_params[] = $category;
        }
        
        if (!empty($payment_status)) {
            if ($payment_status === 'paid') {
                $where_conditions[] = "pm.status = 'paid'";
            } else {
                $where_conditions[] = "(pm.status IS NULL OR pm.status != 'paid')";
            }
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(p.first_name LIKE %s OR p.last_name LIKE %s OR u.user_email LIKE %s)";
            $where_params[] = $like;
            $where_params[] = $like;
            $where_params[] = $like;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_participants p
                       JOIN {$wpdb->users} u ON p.wp_user_id = u.ID
                       LEFT JOIN {$wpdb->prefix}nsc_payments pm ON p.wp_user_id = pm.user_id
                       $where_clause";
        
        if (!empty($where_params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_params));
        } else {
            $total_items = $wpdb->get_var($total_query);
        }
        
        // Get participants with pagination
        $query = "SELECT p.*, u.user_email, 
                         COALESCE(pm.status, 'pending') as payment_status,
                         up.upload_id,
                         CASE 
                             WHEN pm.status = 'paid' THEN 'Paid'
                             WHEN pm.status = 'failed' THEN 'Failed'
                             WHEN pm.status = 'cancelled' THEN 'Cancelled'
                             ELSE 'Not Paid'
                         END as payment_display
                  FROM {$wpdb->prefix}nsc_participants p
                  JOIN {$wpdb->users} u ON p.wp_user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}nsc_payments pm ON p.wp_user_id = pm.user_id
                  LEFT JOIN {$wpdb->prefix}nsc_uploads up ON p.wp_user_id = up.user_id
                  $where_clause
                  ORDER BY p.registration_date DESC
                  LIMIT %d OFFSET %d";
        
        $params = array_merge($where_params, array($per_page, $offset));
        $participants = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Calculate pagination
        $total_pages = ceil($total_items / $per_page);
        
        // Display participants
        include NSC_PLUGIN_DIR . 'templates/admin/participants.php';
    }
    
    /**
     * Display payments page
     */
    public function display_payments() {
        global $wpdb;
        
        // Pagination settings
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Filter settings
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Build WHERE clause
        $where_conditions = array();
        $where_params = array();
        
        if (!empty($status_filter)) {
            $where_conditions[] = "pm.status = %s";
            $where_params[] = $status_filter;
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(p.first_name LIKE %s OR p.last_name LIKE %s OR u.user_email LIKE %s OR CAST(pm.payment_id AS CHAR) LIKE %s)";
            $where_params[] = $like;
            $where_params[] = $like;
            $where_params[] = $like;
            $where_params[] = $like;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_payments pm
                       JOIN {$wpdb->prefix}nsc_participants p ON pm.user_id = p.wp_user_id
                       JOIN {$wpdb->users} u ON pm.user_id = u.ID
                       $where_clause";
        
        if (!empty($where_params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_params));
        } else {
            $total_items = $wpdb->get_var($total_query);
        }
        
        // Get payments with pagination and status translation
        $query = "SELECT pm.*, p.first_name, p.last_name, u.user_email,
                         CASE 
                             WHEN pm.status = 'paid' THEN 'Paid'
                             WHEN pm.status = 'failed' THEN 'Failed'
                             WHEN pm.status = 'cancelled' THEN 'Cancelled'
                             ELSE 'Not Paid'
                         END as status_display
                  FROM {$wpdb->prefix}nsc_payments pm
                  JOIN {$wpdb->prefix}nsc_participants p ON pm.user_id = p.wp_user_id
                  JOIN {$wpdb->users} u ON pm.user_id = u.ID
                  $where_clause
                  ORDER BY pm.order_date DESC
                  LIMIT %d OFFSET %d";
        
        $params = array_merge($where_params, array($per_page, $offset));
        $payments = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Calculate pagination
        $total_pages = ceil($total_items / $per_page);
        
        // Display payments
        include NSC_PLUGIN_DIR . 'templates/admin/payments.php';
    }
    
    /**
     * Display uploads page
     */
    public function display_uploads() {
        global $wpdb;
        
        // Pagination settings
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Filter settings
        $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        // Build WHERE clause
        $where_conditions = array();
        $where_params = array();
        
        if (!empty($category_filter)) {
            $where_conditions[] = "up.category = %s";
            $where_params[] = $category_filter;
        }
        
        if (!empty($status_filter)) {
            $where_conditions[] = "up.status = %s";
            $where_params[] = $status_filter;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_uploads up
                       JOIN {$wpdb->prefix}nsc_participants p ON up.user_id = p.wp_user_id
                       JOIN {$wpdb->users} u ON up.user_id = u.ID
                       $where_clause";
        
        if (!empty($where_params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_params));
        } else {
            $total_items = $wpdb->get_var($total_query);
        }
        
        // Get uploads with pagination
        $query = "SELECT up.*, p.first_name, p.last_name, u.user_email
                  FROM {$wpdb->prefix}nsc_uploads up
                  JOIN {$wpdb->prefix}nsc_participants p ON up.user_id = p.wp_user_id
                  JOIN {$wpdb->users} u ON up.user_id = u.ID
                  $where_clause
                  ORDER BY up.upload_date DESC
                  LIMIT %d OFFSET %d";
        
        $params = array_merge($where_params, array($per_page, $offset));
        $uploads = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Calculate pagination
        $total_pages = ceil($total_items / $per_page);
        
        // Display uploads
        include NSC_PLUGIN_DIR . 'templates/admin/uploads.php';
    }
    
    /**
     * Display judges page
     */
    public function display_judges() {
        global $wpdb;
        
        // Handle form submission
        if (isset($_POST['add_judge'])) {
            $email = sanitize_email($_POST['email']);
            $categories = isset($_POST['categories']) ? sanitize_text_field(implode(',', $_POST['categories'])) : '';
            
            if (email_exists($email)) {
                $user = get_user_by('email', $email);
                $user_id = $user->ID;
                
                // Set role to judge
                $user->set_role('judge');
            } else {
                // Create user
                $username = 'judge_' . wp_generate_password(6, false);
                $password = wp_generate_password();
                $user_id = wp_create_user($username, $password, $email);
                
                if (!is_wp_error($user_id)) {
                    $user = new WP_User($user_id);
                    $user->set_role('judge');
                    
                    // Send email with credentials
                    wp_mail(
                        $email,
                        'NSC Judge Account Created',
                        "Your NSC Judge account has been created.\n\nUsername: $username\nPassword: $password\n\nPlease login at " . wp_login_url()
                    );
                }
            }
            
            // Create or update judge record
            $judge = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_judges WHERE user_id = %d",
                $user_id
            ));
            
            if ($judge) {
                $wpdb->update(
                    "{$wpdb->prefix}nsc_judges",
                    ['assigned_categories' => $categories],
                    ['user_id' => $user_id],
                    ['%s'],
                    ['%d']
                );
            } else {
                $wpdb->insert(
                    "{$wpdb->prefix}nsc_judges",
                    [
                        'user_id' => $user_id,
                        'assigned_categories' => $categories
                    ],
                    ['%d', '%s']
                );
            }
        }
        
        // Get judges
        $judges = $wpdb->get_results("
            SELECT j.*, u.user_email, u.display_name 
            FROM {$wpdb->prefix}nsc_judges j
            JOIN {$wpdb->users} u ON j.user_id = u.ID
            ORDER BY u.display_name
        ");
        
        // Display judges
        include NSC_PLUGIN_DIR . 'templates/admin/judges.php';
    }
    
    /**
     * Display settings page
     */
    public function display_settings() {
        include NSC_PLUGIN_DIR . 'templates/admin/settings.php';
    }
    
    /**
     * Display reports page for Reporter role and admin
     */
    public function display_reports() {
        // Check if user has access
        if (!current_user_can('access_nsc_reports') && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Force load the reports class if not already loaded
        if (!class_exists('NSC_Reports')) {
            require_once NSC_PLUGIN_DIR . 'includes/admin/class-reports.php';
        }
        
        // Display reports
        if (class_exists('NSC_Reports')) {
            // Check if we're displaying content already to avoid duplication
            static $reports_displayed = false;
            if ($reports_displayed) {
                return;
            }
            $reports_displayed = true;
            
            // Manually enqueue scripts for this page since normal hook registration might not work
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', false);
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
            
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    $(".date-picker").datepicker({
                        dateFormat: "yy-mm-dd",
                        changeMonth: true,
                        changeYear: true
                    });
                });
            ');
            
            $reports = new NSC_Reports();
            $reports->render_reports_page();
        } else {
            echo '<div class="wrap">';
            echo '<h1>NSC Reports</h1>';
            echo '<p>Reports module loading failed.</p>';
            echo '<p>Debug info: File exists: ' . (file_exists(NSC_PLUGIN_DIR . 'includes/admin/class-reports.php') ? 'Yes' : 'No') . '</p>';
            echo '<p>Path: ' . NSC_PLUGIN_DIR . 'includes/admin/class-reports.php</p>';
            echo '</div>';
        }
    }
    
    /**
     * Display logs page
     */
    public function display_logs() {
        $log_file = WP_CONTENT_DIR . '/nsc-debug.log';
        
        // Handle log download
        if (isset($_GET['download_logs']) && wp_verify_nonce($_GET['_wpnonce'], 'download_logs')) {
            if (file_exists($log_file)) {
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="nsc-debug-' . date('Y-m-d-H-i-s') . '.log"');
                readfile($log_file);
                exit;
            }
        }
        
        // Handle log clear
        if (isset($_GET['clear_logs']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_logs')) {
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
                echo '<div class="notice notice-success"><p>Logs cleared successfully.</p></div>';
            }
        }
        
        $logs = '';
        if (file_exists($log_file)) {
            $logs = file_get_contents($log_file);
            // Get last 100 lines
            $log_lines = explode("\n", $logs);
            $logs = implode("\n", array_slice($log_lines, -100));
        }
        
        include NSC_PLUGIN_DIR . 'templates/admin/logs.php';
    }
}
