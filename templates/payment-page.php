<?php
/**
 * NSC Payment Template
 */
if (!defined('ABSPATH')) exit;

// Load Razorpay SDK
if (file_exists(NSC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once NSC_PLUGIN_DIR . 'vendor/autoload.php';
}
use Razorpay\Api\Api;

// Authentication check
if (!is_user_logged_in()) {
    auth_redirect();
}

$user_id = get_current_user_id();
$user = wp_get_current_user();

// Participant role check
if (!in_array('participant', (array) $user->roles)) {
    wp_die('Access denied. This page is for participants only.');
}

// Check if payment is already completed
global $wpdb;
$payment_status = $wpdb->get_var(
    $wpdb->prepare("SELECT status FROM {$wpdb->prefix}nsc_payments WHERE user_id = %d ORDER BY payment_date DESC LIMIT 1", $user_id)
);

if ($payment_status === 'paid') {
    wp_redirect(home_url('/upload-video'));
    exit;
}

try {
    // Get participant data
    $participant = $wpdb->get_row($wpdb->prepare(
        "SELECT country_code, category FROM {$wpdb->prefix}nsc_participants WHERE wp_user_id = %d", 
        $user_id
    ));
    
    if (!$participant) {
        throw new Exception('Participant record not found. Please contact support.');
    }

    // Determine payment amount based on country
    if ($participant->country_code === 'IND') {
        $amount = 40000; // 400 INR in paise
        $display_amount = 400;
        $currency = 'INR';
        $currency_symbol = '₹';
    } else {
        $amount = 1000; // $10 in cents
        $display_amount = 10;
        $currency = 'USD';
        $currency_symbol = '$';
    }

    // Get Razorpay keys from options
    $razorpay_key = get_option('nsc_razorpay_key_id', '');
    $razorpay_secret = get_option('nsc_razorpay_secret_key', '');
    
    if (empty($razorpay_key) || empty($razorpay_secret)) {
        throw new Exception('Payment configuration is incomplete. Please contact the administrator.');
    }

    // Create Razorpay order
    $api = new Api($razorpay_key, $razorpay_secret);
    
    $order_data = [
        'amount' => $amount,
        'currency' => $currency,
        'payment_capture' => 1,
        'notes' => [
            'user_id' => $user_id,
            'category' => $participant->category
        ]
    ];
    
    $order = $api->order->create($order_data);

    // Update or insert payment record
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}nsc_payments WHERE user_id = %d", 
        $user_id
    ));
    
    if ($exists) {
        $wpdb->update(
            "{$wpdb->prefix}nsc_payments",
            [
                'razorpay_order_id' => $order->id,
                'amount' => $amount / 100,
                'currency' => $currency,
                'status' => 'created',
                'order_date' => current_time('mysql')
            ],
            ['user_id' => $user_id],
            ['%s', '%f', '%s', '%s', '%s'],
            ['%d']
        );
    } else {
        $wpdb->insert(
            "{$wpdb->prefix}nsc_payments",
            [
                'user_id' => $user_id,
                'razorpay_order_id' => $order->id,
                'amount' => $amount / 100,
                'currency' => $currency,
                'status' => 'created',
                'order_date' => current_time('mysql')
            ],
            ['%d', '%s', '%f', '%s', '%s', '%s']
        );
    }

} catch (Exception $e) {
    wp_die('<strong>Payment Error:</strong> ' . esc_html($e->getMessage()));
}

// Get header
get_header();
?>

<div class="nsc-container nsc-payment-container">
    <h1><?php esc_html_e('Step 2: Make Payment', 'nsc-core'); ?></h1>

    <div class="nsc-payment-details">
        <h2><?php esc_html_e('Welcome', 'nsc-core'); ?>, <?php echo esc_html($user->display_name); ?>!</h2>
        <p><?php esc_html_e('To complete your registration, please make the payment below:', 'nsc-core'); ?></p>
        
        <div class="nsc-payment-summary">
            <div class="nsc-payment-row">
                <span><?php esc_html_e('Your Category', 'nsc-core'); ?>:</span>
                <span><?php echo esc_html($participant->category); ?></span>
            </div>
            <div class="nsc-payment-row">
                <span><?php esc_html_e('Registration Fee', 'nsc-core'); ?>:</span>
                <span><?php echo esc_html($currency_symbol . $display_amount); ?></span>
            </div>
        </div>
        
        <button id="rzp-button" class="nsc-button"><?php esc_html_e('Proceed to Payment', 'nsc-core'); ?></button>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('rzp-button').onclick = function() {
    var options = {
        key: '<?php echo esc_js($razorpay_key); ?>',
        amount: '<?php echo esc_js($order->amount); ?>',
        currency: '<?php echo esc_js($order->currency); ?>',
        name: '<?php echo esc_js(get_bloginfo('name')); ?>',
        description: '<?php echo esc_js(__('National Storytelling Championship Registration', 'nsc-core')); ?>',
        order_id: '<?php echo esc_js($order->id); ?>',
        handler: function(response) {
            // Show loading indicator
            document.getElementById('rzp-button').innerHTML = '<?php echo esc_js(__('Processing...', 'nsc-core')); ?>';
            document.getElementById('rzp-button').disabled = true;
            
            // Create a form to submit the payment details
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            
            var paymentIdField = document.createElement('input');
            paymentIdField.type = 'hidden';
            paymentIdField.name = 'razorpay_payment_id';
            paymentIdField.value = response.razorpay_payment_id;
            form.appendChild(paymentIdField);
            
            var orderIdField = document.createElement('input');
            orderIdField.type = 'hidden';
            orderIdField.name = 'razorpay_order_id';
            orderIdField.value = '<?php echo esc_js($order->id); ?>';
            form.appendChild(orderIdField);
            
            var signatureField = document.createElement('input');
            signatureField.type = 'hidden';
            signatureField.name = 'razorpay_signature';
            signatureField.value = response.razorpay_signature;
            form.appendChild(signatureField);
            
            var actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = 'nsc_process_payment';
            form.appendChild(actionField);
            
            var nonceField = document.createElement('input');
            nonceField.type = 'hidden';
            nonceField.name = 'nonce';
            nonceField.value = '<?php echo wp_create_nonce('nsc-payment-nonce'); ?>';
            form.appendChild(nonceField);
            
            document.body.appendChild(form);
            form.submit();
        },
        prefill: {
            name: '<?php echo esc_js($user->display_name); ?>',
            email: '<?php echo esc_js($user->user_email); ?>'
        },
        notes: {
            user_id: '<?php echo esc_js($user_id); ?>',
            category: '<?php echo esc_js($participant->category); ?>'
        },
        theme: {
            color: '#4C85B2'
        }
    };
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>

<?php get_footer(); ?>
