<?php
/**
 * Admin Settings Template
 */
if (!defined('ABSPATH')) exit;
// Display success message when settings are saved
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
}
?>
<div class="wrap">
    <h1><?php esc_html_e('NSC Settings', 'nsc-core'); ?></h1>
    
    <?php
    // Show settings errors/updates
    settings_errors('nsc_general_settings');
    settings_errors('nsc_razorpay_settings');
    settings_errors('nsc_r2_settings');
    settings_errors('nsc_reset');
    
    // Display any transient success message
    $success_message = get_transient('nsc_reset_success');
    if ($success_message) {
        echo '<div class="notice notice-success is-dismissible"><p>' . $success_message . '</p></div>';
        delete_transient('nsc_reset_success');
    }
    ?>
    
    <div class="nsc-admin-settings">
        <h2 class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'nsc-core'); ?></a>
            <a href="#razorpay" class="nav-tab"><?php esc_html_e('Razorpay', 'nsc-core'); ?></a>
            <a href="#r2-storage" class="nav-tab"><?php esc_html_e('R2 Storage', 'nsc-core'); ?></a>
        </h2>
        
        <div id="general" class="nsc-settings-tab">
            <form method="post" action="options.php">
                <?php settings_fields('nsc_general_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nsc_registration_form_id"><?php esc_html_e('Registration Form ID', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="nsc_registration_form_id" id="nsc_registration_form_id" value="<?php echo esc_attr(get_option('nsc_registration_form_id', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('The Form ID for the registration form.', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <div id="razorpay" class="nsc-settings-tab" style="display:none;">
            <form method="post" action="options.php">
                <?php settings_fields('nsc_razorpay_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nsc_razorpay_key_id"><?php esc_html_e('Razorpay Key ID', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="nsc_razorpay_key_id" id="nsc_razorpay_key_id" value="<?php echo esc_attr(get_option('nsc_razorpay_key_id', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Your Razorpay Key ID from the dashboard.', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nsc_razorpay_secret_key"><?php esc_html_e('Razorpay Secret Key', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="nsc_razorpay_secret_key" id="nsc_razorpay_secret_key" value="<?php echo esc_attr(get_option('nsc_razorpay_secret_key', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Your Razorpay Secret Key from the dashboard.', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nsc_razorpay_webhook_secret"><?php esc_html_e('Webhook Secret', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="nsc_razorpay_webhook_secret" id="nsc_razorpay_webhook_secret" value="<?php echo esc_attr(get_option('nsc_razorpay_webhook_secret', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Webhook secret key for webhook verification.', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e('Webhook Settings', 'nsc-core'); ?></h3>
                <p>
                    <?php esc_html_e('Set up the following webhook in your Razorpay dashboard:', 'nsc-core'); ?><br>
                    <strong><?php esc_html_e('Webhook URL:', 'nsc-core'); ?></strong> <?php echo esc_url(home_url('/razorpay-webhook/')); ?><br>
                    <strong><?php esc_html_e('Events to subscribe:', 'nsc-core'); ?></strong> payment.captured, payment.failed
                </p>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <div id="r2-storage" class="nsc-settings-tab" style="display:none;">
            <form method="post" action="options.php">
                <?php settings_fields('nsc_r2_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nsc_r2_endpoint"><?php esc_html_e('R2 Endpoint URL', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="nsc_r2_endpoint" id="nsc_r2_endpoint" value="<?php echo esc_attr(get_option('nsc_r2_endpoint', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('The Cloudflare R2 endpoint URL. Format: https://{account_id}.r2.cloudflarestorage.com', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nsc_r2_access_key"><?php esc_html_e('R2 Access Key', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="nsc_r2_access_key" id="nsc_r2_access_key" value="<?php echo esc_attr(get_option('nsc_r2_access_key', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Your Cloudflare R2 Access Key.', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nsc_r2_secret_key"><?php esc_html_e('R2 Secret Key', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="nsc_r2_secret_key" id="nsc_r2_secret_key" value="<?php echo esc_attr(get_option('nsc_r2_secret_key', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Your Cloudflare R2 Secret Key.', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nsc_r2_bucket"><?php esc_html_e('R2 Bucket Name', 'nsc-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="nsc_r2_bucket" id="nsc_r2_bucket" value="<?php echo esc_attr(get_option('nsc_r2_bucket', 'nsc-videos')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('The name of your R2 bucket where videos will be stored.', 'nsc-core'); ?></p>
                        </td>
                    </tr>
                    <tr>
                    <th scope="row">
                        <label for="nsc_r2_custom_domain">R2 Custom Domain</label></th>
                    <td>
                        <input type="url" name="nsc_r2_custom_domain" id="nsc_r2_custom_domain" 
                            value="<?php echo esc_attr(get_option('nsc_r2_custom_domain')); ?>" 
                            class="regular-text">
                        <p class="description">Enter your custom domain for R2 videos (e.g., https://videos.nationalstorytellingchampionship.com)</p>
                    </td>
                </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
    
    </div>
</div>

<style>
.nsc-settings-tab {
    background: white;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
}
.button-danger {
    background: #dc3232 !important;
    color: white !important;
    border-color: #a00 !important;
}
.button-danger:hover {
    background: #b32d2e !important;
    border-color: #800 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        
        // Get the tab ID from the href
        var tab = $(this).attr('href');
        
        // Hide all tabs and remove active class
        $('.nsc-settings-tab').hide();
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Show the selected tab and add active class
        $(tab).show();
        $(this).addClass('nav-tab-active');
        
        // Add the hash to the URL without refreshing the page
        if (history.pushState) {
            history.pushState(null, null, tab);
        } else {
            location.hash = tab;
        }
    });
    
    // Show success message if settings are saved
    if (window.location.search.indexOf('settings-updated=true') !== -1) {
        var successMessage = $('<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p><button type="button" class="notice-dismiss"></button></div>');
        $('.wrap h1').after(successMessage);
        
        setTimeout(function() {
            successMessage.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }
    
    // Check if URL has a hash and show the corresponding tab
    if (window.location.hash) {
        var hash = window.location.hash;
        $('.nav-tab-wrapper a[href="' + hash + '"]').click();
    }
    
    // Add click handler for dismiss buttons
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut(300, function() { $(this).remove(); });
    });
});
</script>