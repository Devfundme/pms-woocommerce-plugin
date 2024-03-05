<?php
/**
 * Plugin Name: DevFundMe Payment Gateway
 * Description: Custom WooCommerce payment gateway for PMS (Payment Management System).
 * Version: 2.2.0
 * Author: Freedy Meritus
 * Text Domain: devfundme-pms-gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class FT_AdminNotice {
    
    protected $min_wc = '5.0.0'; //replace '5.0.0' with your dependent plugin version number
    
    /**
     * Register the activation hook
     */
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'ft_install' ) );
    }
    
    /**
     * Check the dependent plugin version
     */
    protected function ft_is_wc_compatible() {          
        return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, $this->min_wc, '>=' );
    }
    
    /**
     * Function to deactivate the plugin
     */
    protected function ft_deactivate_plugin() {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        deactivate_plugins( plugin_basename( __FILE__ ) );
        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
    
    /**
     * Deactivate the plugin and display a notice if the dependent plugin is not compatible or not active.
     */
    public function ft_install() {
        if ( ! $this->ft_is_wc_compatible() || ! class_exists( 'WooCommerce' ) ) {
            $this->ft_deactivate_plugin();
            wp_die( 'Could not be activated. ' . $this->get_ft_admin_notices() );
        } else {
            //do your fancy staff here
        }
    }
    
    /**
     * Writing the admin notice
     */
    protected function get_ft_admin_notices() {
        return sprintf(
            '%1$s requires WooCommerce version %2$s or higher installed and active. You can download WooCommerce latest version %3$s OR go back to %4$s.',
            '<strong>' . $this->plugin_name . '</strong>',
            $this->min_wc,
            '<strong><a href="https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip">from here</a></strong>',
            '<strong><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">plugins page</a></strong>'
        );
    }

}

new FT_AdminNotice();

/*
* This action hook registers our PHP class as a WooCommerce payment gateway
*/
add_filter( 'woocommerce_payment_gateways', 'devfundme_add_gateway_class' );
function devfundme_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Devfundme_PMS_Gateway'; // your class name is here
    return $gateways;
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
            $this->icon = plugin_dir_url( __DIR__ ) . 'assets/icon.png';
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
            $return_url = $this->get_return_url($order);
            $webhooks_url =  bloginfo('url') . '/wc-api/confirm_payment/';
    
            // Prepare API request data
            $api_request_data = array(
                'amount' => $amount,
                'payor_name' => $payor_name,
                'payor_email' => $payor_email,
                'meta_data' => $meta_data,
                'note' => $note,
                'return_url' => $return_url,
                'webhooks_url' => $webhooks_url,
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

            $order = wc_get_order( $_POST[ 'meta_data' ]['order_id'] );
            $order->payment_complete();
            $order->reduce_order_stock();
        
            update_option( 'webhook_debug', $_POST );
        }
 	}
}