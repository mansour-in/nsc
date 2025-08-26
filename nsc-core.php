<?php
/**
 * Plugin Name: NSC Core
 * Plugin URI: https://colourful.website
 * Description: Handles registration, payments, and video uploads for NSC.
 * Version: 1.0
 * Author: Mansour
 * Text Domain: nsc-core
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NSC_VERSION', '1.0.0');

// Load Composer autoloader
if (file_exists(NSC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once NSC_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include core files
require_once NSC_PLUGIN_DIR . 'includes/class-database.php';
require_once NSC_PLUGIN_DIR . 'includes/class-r2-uploader.php';
require_once NSC_PLUGIN_DIR . 'includes/class-payment-handler.php';
require_once NSC_PLUGIN_DIR . 'includes/class-admin.php';

// require_once NSC_PLUGIN_DIR . 'includes/admin/class-add-participant.php'; // Commented out as not requested
require_once NSC_PLUGIN_DIR . 'includes/admin/class-reports.php';

require_once NSC_PLUGIN_DIR . 'nsc-fluentforms-integration.php';

/**
 * Main NSC Core Class
 */
class NSC_Core {
    /**
     * Instance of this class.
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * The plugin name.
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * Initialize the plugin.
     */
    public function __construct() {
        $this->plugin_name = 'nsc-core';
        $this->load_dependencies();
        $this->define_hooks();
    }

    /**
     * Return an instance of this class.
     *
     * @return NSC_Core A single instance of this class.
     */
    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Load the required dependencies.
     */
    private function load_dependencies() {
        // The classes are included above with require_once
    }

    /**
     * Define hooks and filters.
     */
    private function define_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add rewrite rules for payment and video upload
        add_action('init', array($this, 'add_rewrite_rules'));

        // Initialize components
        $database = new NSC_Database();
        $r2_uploader = new NSC_R2_Uploader();
        $payment_handler = new NSC_Payment_Handler();
        
        // Load reports class if it exists
        if (file_exists(NSC_PLUGIN_DIR . 'includes/admin/class-reports.php')) {
            require_once NSC_PLUGIN_DIR . 'includes/admin/class-reports.php';
            $reports = new NSC_Reports(); // Initialize reports class
        }
        
        $admin = new NSC_Admin();

        // Template loaders
        add_filter('template_include', array($this, 'template_loader'));
    }

    /**
     * Runs on plugin activation.
     */
    public function activate() {
        $this->create_participant_role();
        $database = new NSC_Database();
        $database->create_tables();
        flush_rewrite_rules();
    }

    /**
     * Create participant role on activation.
     */
    private function create_participant_role() {
        if (!get_role('participant')) {
            add_role(
                'participant',
                'Participant',
                [
                    'read' => true,
                    'upload_files' => true
                ]
            );
        }

        // Also create judge role if it doesn't exist
        if (!get_role('judge')) {
            add_role(
                'judge',
                'Judge',
                [
                    'read' => true,
                    'judge_videos' => true
                ]
            );
        }

        // Create reporter role if it doesn't exist
        if (!get_role('reporter')) {
            add_role(
                'reporter',
                'Reporter',
                [
                    'read' => true,
                    'access_nsc_reports' => true
                ]
            );
        }
    }

    /**
     * Runs on plugin deactivation.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts() {
        wp_enqueue_style('nsc-frontend', NSC_PLUGIN_URL . 'assets/css/nsc-frontend.css', array(), NSC_VERSION);
        wp_enqueue_script('nsc-upload', NSC_PLUGIN_URL . 'assets/js/nsc-upload.js', array('jquery'), NSC_VERSION, true);

        // Localize script with ajax url
        wp_localize_script('nsc-upload', 'nsc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nsc-ajax-nonce'),
            'max_file_size' => 30 * 1024 * 1024, // 30MB in bytes
            'max_duration' => 120 // 2 minutes in seconds
        ));
    }

    /**
     * Add rewrite rules for custom pages.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            'razorpay-webhook/?$',
            'index.php?razorpay-webhook=1',
            'top'
        );
    }

    /**
     * Load custom templates.
     */
    public function template_loader($template) {
        global $post;

        if (is_page('payment') || (isset($post) && $post->post_name === 'payment')) {
            return NSC_PLUGIN_DIR . 'templates/payment-page.php';
        }

        if (is_page('upload-video') || (isset($post) && $post->post_name === 'upload-video')) {
            return NSC_PLUGIN_DIR . 'templates/upload-video.php';
        }

        if (is_page('confirm-video') || (isset($post) && $post->post_name === 'confirm-video')) {
            return NSC_PLUGIN_DIR . 'templates/confirm-video.php';
        }

        if (is_page('thank-you') || (isset($post) && $post->post_name === 'thank-you')) {
            return NSC_PLUGIN_DIR . 'templates/thank-you.php';
        }

        if (isset($_GET['razorpay-webhook'])) {
            $payment_handler = new NSC_Payment_Handler();
            $payment_handler->process_webhook();
            exit;
        }

        return $template;
    }
}

// Initialize the plugin
$nsc_core = NSC_Core::get_instance();
