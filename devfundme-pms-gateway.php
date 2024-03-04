<?php
/**
 * Plugin Name: DevFundMe Payment Gateway
 * Description: Custom WooCommerce payment gateway for PMS (Payment Management System).
 * Version: 2.0.1
 * Author: Freedy Meritus
 * Text Domain: devfundme-pms-gateway
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_Payment_Gateway')) {
    /*
    * This action hook registers our PHP class as a WooCommerce payment gateway
    */
    add_filter( 'woocommerce_payment_gateways', 'devfundme_add_gateway_class' );
    function devfundme_add_gateway_class( $gateways ) {
        $gateways[] = 'WC_Devfundme_PMS_Gateway'; // your class name is here
        return $gateways;
    }
} 
else {
    // Display a notice or take appropriate action for incompatible WooCommerce version
    add_action('admin_notices', 'devfundme_pms_wc_version_notice');
}

// Display notice for incompatible WooCommerce version
function devfundme_pms_wc_version_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('DevFundMe Payment Gateway requires WooCommerce', 'devfundme-pms'); ?></p>
    </div>
    <?php
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'devfundme_init_gateway_class' );
function devfundme_init_gateway_class() {

	class WC_Devfundme_PMS_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor
 		 */
        public function __construct() {

            $this->id = 'dfm_pms'; // payment gateway plugin ID
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Devfundme PMS Gateway';
            $this->method_description = 'DCustom WooCommerce payment gateway for PMS (Payment Management System).'; // will be displayed on the options page
        
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
        
            // Method with all the options fields
            $this->init_form_fields();
        
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->api_token = $this->get_option( 'api_token' );

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            
            // You can also register a webhook here
            add_action( 'woocommerce_api_confirm_payment', array( $this, 'webhook' ) );
        }

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable DevFundMe Payment Gateway',
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'DevFundMe Payment Gateway',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay securely using DevFundMe Payment Gateway.',
                ),
                'api_token' => array(
                    'title' => 'Reveal API Token',
                    'type' => 'text',
                    'description' => 'Enter your Reveal API Token.',
                    'default' => '',
                ),
            );
        }

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment($order_id) {
            $order = wc_get_order($order_id);
    
            // Retrieve the order details
            $amount = $order->get_total();
            $payor_name = $order->get_billing_first_name();
            $payor_email = $order->get_billing_email();
            $meta_data = array('order_id' => $order_id);
            $note = 'Payment for order'.' '.$order_id;
            $return_url = './wc-api/confirm_payment/';
    
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
                $error_message = isset($api_response['error']) ? $api_response['error'] : 'API request failed.';
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

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
	
            $order = wc_get_order( $_GET[ 'id' ] );
            $order->payment_complete();
            $order->reduce_order_stock();
        
            update_option( 'webhook_debug', $_GET );

            return wp_redirect($this->get_return_url( $order ));
        }
 	}
}