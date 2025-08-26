<?php
/**
 * Database Manager Class
 * 
 * Handles all database operations for the NSC plugin
 */
if (!defined('ABSPATH')) {
    exit;
}

class NSC_Database {
    
    /**
     * Initialize the database class
     */
    public function __construct() {
        // Register activation hook
        register_activation_hook(NSC_PLUGIN_DIR . 'nsc-core.php', array($this, 'create_tables'));
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Participants table
        $participants_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nsc_participants (
            participant_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT(20) UNSIGNED NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            dob DATE NOT NULL,
            category VARCHAR(3) NOT NULL,
            country_code VARCHAR(3) NOT NULL,
            registration_date DATETIME NOT NULL,
            PRIMARY KEY  (participant_id),
            UNIQUE KEY wp_user_id (wp_user_id),
            FOREIGN KEY (wp_user_id) REFERENCES {$wpdb->users}(ID)
        ) $charset_collate;";
        
        // Payments table
        $payments_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nsc_payments (
            payment_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            razorpay_order_id VARCHAR(255),
            razorpay_payment_id VARCHAR(255),
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            order_date DATETIME,
            payment_date DATETIME,
            PRIMARY KEY  (payment_id),
            FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID)
        ) $charset_collate;";
        
        // Uploads table
        $uploads_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nsc_uploads (
            upload_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            video_url VARCHAR(255) NOT NULL,
            category VARCHAR(3) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            upload_date DATETIME NOT NULL,
            marks DECIMAL(5,2) DEFAULT NULL,
            judged_by BIGINT(20) UNSIGNED DEFAULT NULL,
            judge_remarks TEXT DEFAULT NULL,
            PRIMARY KEY  (upload_id),
            FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID)
        ) $charset_collate;";
        
        // Judges table
        $judges_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nsc_judges (
            judge_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            assigned_categories VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY  (judge_id),
            FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($participants_table);
        dbDelta($payments_table);
        dbDelta($uploads_table);
        dbDelta($judges_table);
    }
    
    /**
     * Get participant by user ID
     */
    public function get_participant($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nsc_participants WHERE wp_user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get payment status by user ID
     */
    public function get_payment_status($user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}nsc_payments WHERE user_id = %d ORDER BY payment_date DESC LIMIT 1",
            $user_id
        ));
    }
    
    /**
     * Get upload by user ID
     */
    public function get_upload($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nsc_uploads WHERE user_id = %d ORDER BY upload_date DESC LIMIT 1",
            $user_id
        ));
    }
    /**
     * Update payment after successful Razorpay transaction
     *
     * @param string    $order_id   Razorpay order ID
     * @param string    $payment_id Razorpay payment ID
     * @param string    $status     Payment status
     * @param int|null  $user_id    WordPress user ID for fallback update
     * @return int|false Number of rows updated or false on failure
     */
    public function update_payment($order_id, $payment_id, $status = 'paid', $user_id = null) {
        global $wpdb;

        $table = "{$wpdb->prefix}nsc_payments";
        $data = [
            'razorpay_payment_id' => $payment_id,
            'status' => $status,
            'payment_date' => current_time('mysql')
        ];

        // First attempt: update by order ID
        $result = $wpdb->update(
            $table,
            $data,
            ['razorpay_order_id' => $order_id],
            ['%s', '%s', '%s'],
            ['%s']
        );

        // Fallback: update latest record for user if no rows affected
        if ($result === 0 && $user_id) {
            $latest_id = $wpdb->get_var($wpdb->prepare(
                "SELECT payment_id FROM {$table} WHERE user_id = %d ORDER BY payment_id DESC LIMIT 1",
                $user_id
            ));

            if ($latest_id) {
                $data['razorpay_order_id'] = $order_id;
                $result = $wpdb->update(
                    $table,
                    $data,
                    ['payment_id' => $latest_id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d']
                );
            }
        }

        if ($result === 0 || $result === false) {
            error_log(sprintf('NSC_Database::update_payment failed for order %s and user %s', $order_id, $user_id));
            return false;
        }

        return $result;
    }

    
    /**
     * Save uploaded video information
     */
    public function save_video_upload($user_id, $video_url, $category) {
        global $wpdb;
        return $wpdb->insert(
            "{$wpdb->prefix}nsc_uploads",
            [
                'user_id' => $user_id,
                'video_url' => $video_url,
                'category' => $category,
                'status' => 'pending',
                'upload_date' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Update video status
     */
    public function update_video_status($upload_id, $status) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}nsc_uploads",
            ['status' => $status],
            ['upload_id' => $upload_id],
            ['%s'],
            ['%d']
        );
    }
    
    /**
     * Delete video upload
     */
    public function delete_video_upload($upload_id) {
        global $wpdb;
        return $wpdb->delete(
            "{$wpdb->prefix}nsc_uploads",
            ['upload_id' => $upload_id],
            ['%d']
        );
    }
}
