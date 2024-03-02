<?php
/**
 * Plugin Name: DevFundMe Payment Gateway
 * Description: Custom WooCommerce payment gateway for PMS (Payment Management System).
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include the main class for the payment gateway
require_once plugin_dir_path(__FILE__) . 'includes/class-devfundme-pms.php';

// Initialize the payment gateway
add_action('plugins_loaded', 'init_devfundme_pms');

function init_devfundme_pms() {
    if (class_exists('WC_Payment_Gateway')) {
        // Include the payment gateway class
        include_once plugin_dir_path(__FILE__) . 'includes/class-wc-devfundme-pms.php';

        // Register the payment gateway with WooCommerce
        function add_devfundme_pms($methods) {
            $methods[] = 'WC_DevFundMe_PMS';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'add_devfundme_pms');
    }
}

// Activation hook to set up the API token
register_activation_hook(__FILE__, 'devfundme_pms_activate');

function devfundme_pms_activate() {
    // Set up the default API token value on activation
    update_option('devfundme_pms_api_token', '');
}
