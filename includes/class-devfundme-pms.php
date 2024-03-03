<?php
if (!defined('ABSPATH')) {
    exit;
}

class DevFundMe_PMS {

    protected $api_token;

    public function __construct() {
        $this->api_token = get_option('devfundme_pms_api_token', '');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_devfundme_pms', array($this, 'process_admin_options'));
        add_action('admin_init', array($this, 'admin_options_init'));
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Retrieve the order details
        $amount = $order->get_total();
        $payor_name = $order->get_billing_first_name();
        $payor_email = $order->get_billing_email();
        $meta_data = array('order_id' => $order_id);
        $note = __('Payment for order', 'devfundme-pms') . ' ' . $order_id;
        $return_url = $this->get_return_url($order);

        // Prepare API request data
        $api_request_data = array(
            'amount' => $amount,
            'payor_name' => $payor_name,
            'payor_email' => $payor_email,
            'meta_data' => $meta_data,
            'note' => $note,
            'return_url' => $return_url,
        );

        // Perform the API request
        $api_response = $this->make_api_request('/generate_paylink/', $api_request_data);

        // Check if the API request was successful
        if ($api_response && isset($api_response['pay_url'])) {
            // Redirect the customer to the generated checkout link
            return array(
                'result' => 'success',
                'redirect' => $api_response['pay_url'],
            );
        } else {
            // Handle API request failure
            $error_message = isset($api_response['error']) ? $api_response['error'] : __('API request failed.', 'devfundme-pms');
            wc_add_notice($error_message, 'error');
            return array(
                'result' => 'fail',
            );
        }
    }

    // Helper function to make API requests
    private function make_api_request($endpoint, $data) {
        $api_url = 'https://devfundme.com/api/pms' . $endpoint;

        $headers = array(
            'Authorization: Token ' . $this->api_token,
            'Content-Type: application/json',
        );

        $api_response = wp_remote_post(
            $api_url,
            array(
                'headers' => $headers,
                'body' => wp_json_encode($data), // Use wp_json_encode for consistency
            )
        );

        // Check for errors and return the API response
        if (!is_wp_error($api_response)) {
            return json_decode(wp_remote_retrieve_body($api_response), true);
        } else {
            return array('error' => $api_response->get_error_message());
        }
    }

    // Additional function to handle saving the API token on admin options page
    public function admin_options_init() {
        if (isset($_POST['devfundme_pms_api_token'])) {
            update_option('devfundme_pms_api_token', wc_clean(wp_unslash($_POST['devfundme_pms_api_token'])));
        }
    }

    // Additional function to load text domain for translations
    public function load_textdomain() {
        load_plugin_textdomain('devfundme-pms', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}

// Instantiate the class on plugins loaded
add_action('plugins_loaded', 'init_devfundme_pms');

function init_devfundme_pms() {
    if (class_exists('WC_Payment_Gateway')) {
        // Include the payment gateway class
        include_once plugin_dir_path(__FILE__) . 'class-wc-devfundme-pms.php';

        // Register the payment gateway with WooCommerce
        function add_devfundme_pms($methods) {
            $methods[] = 'WC_DevFundMe_PMS';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'add_devfundme_pms');
    }

    // Instantiate the main class
    $devfundme_pms = new DevFundMe_PMS();
}

// Activation hook to set up the API token
register_activation_hook(__FILE__, 'devfundme_pms_activate');

function devfundme_pms_activate() {
    try {
        // Set up the default API token value on activation
        update_option('devfundme_pms_api_token', '');
    } catch (Exception $e) {
        // Handle activation error (e.g., log the error, display a notice)
    }
}
