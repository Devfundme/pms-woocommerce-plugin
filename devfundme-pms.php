<?php
/**
 * Plugin Name: DevFundMe Payment Gateway
 * Description: Custom WooCommerce payment gateway for PMS (Payment Management System).
 * Version: v1.0.3
 * Author: Freedy Meritus
 * Text Domain: devfundme-pms
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load text domain for translations
add_action('plugins_loaded', 'devfundme_pms_load_textdomain');

function devfundme_pms_load_textdomain() {
    load_plugin_textdomain('devfundme-pms', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Include the main class for the payment gateway
require_once plugin_dir_path(__FILE__) . 'includes/class-devfundme-pms.php';

// Initialize the payment gateway
add_action('plugins_loaded', 'devfundme_pms_init');

function devfundme_pms_init() {
    if (class_exists('WC_Payment_Gateway') && version_compare(WC_VERSION, '3.0.0', '>=')) {
        // Include the payment gateway class
        include_once plugin_dir_path(__FILE__) . 'includes/class-wc-devfundme-pms.php';

        // Register the payment gateway with WooCommerce
        add_filter('woocommerce_payment_gateways', 'devfundme_pms_add_gateway');
    } else {
        // Display a notice or take appropriate action for incompatible WooCommerce version
        add_action('admin_notices', 'devfundme_pms_wc_version_notice');
    }
}

// Register the payment gateway with WooCommerce
function devfundme_pms_add_gateway($methods) {
    $methods[] = 'WC_DevFundMe_PMS';
    return $methods;
}

// Activation hook to set up the API token
register_activation_hook(__FILE__, 'devfundme_pms_activate');

function devfundme_pms_activate() {
    try {
        // Set up the default API token value on activation
        update_option('devfundme_pms_api_token', '');
        error_log('DevFundMe PMS activated successfully.');
    } catch (Exception $e) {
        // Handle activation error (e.g., log the error, display a notice)
        error_log('DevFundMe PMS activation error: ' . $e->getMessage());
    }
}

// Display notice for incompatible WooCommerce version
function devfundme_pms_wc_version_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('DevFundMe Payment Gateway requires WooCommerce version 3.0.0 or higher.', 'devfundme-pms'); ?></p>
    </div>
    <?php
}
