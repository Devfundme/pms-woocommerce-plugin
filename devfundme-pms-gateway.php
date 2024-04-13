<?php
/**
 * Plugin Name: DevFundMe Payment Gateway
 * Description: Custom WooCommerce payment gateway for PMS (Payment Management System).
 * Version: 1.2.0
 * Author: Freedy Meritus
 * Author URI: https://devfundme.com/en/pms/service/
 * Text Domain: devfundme-pms-gateway
 */

 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 error_reporting(E_ALL);

if (!defined('ABSPATH')) {
    exit;
}

class FT_AdminNotice {
    
    protected $min_wc = '5.0.0'; // Replace '5.0.0' with your dependent plugin version number
    
    /**
     * Register the activation hook
     */
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'ft_install'));
    }
    
    /**
     * Check the dependent plugin version
     */
    protected function ft_is_wc_compatible() {          
        return defined('WC_VERSION') && version_compare(WC_VERSION, $this->min_wc, '>=');
    }
    
    /**
     * Function to deactivate the plugin
     */
    protected function ft_deactivate_plugin() {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
    
    /**
     * Deactivate the plugin and display a notice if the dependent plugin is not compatible or not active.
     */
    public function ft_install() {
        if (!$this->ft_is_wc_compatible() || !class_exists('WooCommerce')) {
            $this->ft_deactivate_plugin();
            wp_die('Could not be activated. ' . $this->get_ft_admin_notices());
        } else {
            // Do your fancy stuff here
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
            '<strong><a href="' . esc_url(admin_url('plugins.php')) . '">plugins page</a></strong>'
        );
    }

}

new FT_AdminNotice();

/**
 * Register the DevFundMe PMS Gateway class with WooCommerce
 */
add_filter('woocommerce_payment_gateways', 'devfundme_add_gateway_class');
function devfundme_add_gateway_class($gateways) {
    $gateways[] = 'WC_Devfundme_PMS_Gateway';
    return $gateways;
}

/**
 * Initialize the DevFundMe PMS Gateway class after WooCommerce is loaded
 */
add_action('woocommerce_loaded', 'devfundme_init_gateway_class');
function devfundme_init_gateway_class() {

    class WC_Devfundme_PMS_Gateway extends WC_Payment_Gateway {

        public $api_token;

        /**
         * Class constructor
         */
        public function __construct() {

            $this->id = 'dfm_pms'; // Payment gateway plugin ID
            $this->icon = plugins_url('assets/images/icon.png', __FILE__);
            $this->has_fields = true; // In case you need a custom credit card form
            $this->method_title = 'Devfundme PMS Gateway';
            $this->method_description = 'Devfundme PMS(Payment Management System) WooCommerce payment gateway.'; // Will be displayed on the options page
        
            $this->supports = array(
                'products',
                'tokenization',
                'add_payment_method',
                // 'default_credit_card_form', // Add this line for block-based checkout support
            );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->api_token = $this->get_option('api_token');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_dfm_confirm_payment', array($this, 'webhook'));
        }

        /**
         * Define settings fields for the gateway
         */
        public function init_form_fields() {

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

        /**
         * Process the payment
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            $order_desc='';

            foreach ($order->get_items() as $item_key => $item ):

                ## Using WC_Order_Item methods ##
            
                // Item ID is directly accessible from the $item_key in the foreach loop or
                $item_id = $item->get_id();
            
                ## Using WC_Order_Item_Product methods ##
            
                $product      = $item->get_product(); // Get the WC_Product object
            
                $product_id   = $item->get_product_id(); // the Product id
                $variation_id = $item->get_variation_id(); // the Variation id
            
                $item_type    = $item->get_type(); // Type of the order item ("line_item")
            
                $item_name    = $item->get_name(); // Name of the product
                $quantity     = $item->get_quantity();  
                $tax_class    = $item->get_tax_class();
                $line_subtotal     = $item->get_subtotal(); // Line subtotal (non discounted)
                $line_subtotal_tax = $item->get_subtotal_tax(); // Line subtotal tax (non discounted)
                $line_total        = $item->get_total(); // Line total (discounted)
                $line_total_tax    = $item->get_total_tax(); // Line total tax (discounted)
            
                ## Access Order Items data properties (in an array of values) ##
                $item_data    = $item->get_data();
            
                $product_name = $item_data['name'];
                $product_id   = $item_data['product_id'];
                $variation_id = $item_data['variation_id'];
                $quantity     = $item_data['quantity'];
                $tax_class    = $item_data['tax_class'];
                $line_subtotal     = $item_data['subtotal'];
                $line_subtotal_tax = $item_data['subtotal_tax'];
                $line_total        = $item_data['total'];
                $line_total_tax    = $item_data['total_tax'];
            
                // Get data from The WC_product object using methods (examples)
                $product        = $item->get_product(); // Get the WC_Product object
            
                $product_type   = $product->get_type();
                $product_sku    = $product->get_sku();
                $product_price  = $product->get_price();
                $stock_quantity = $product->get_stock_quantity();

                $order_desc = $order_desc . $product_name . '($'.$product_price.')x' . $quantity.';';
            
            endforeach;

            // Retrieve the order details
            $amount = $order->get_total();
            $payor_name = $order->get_billing_first_name();
            $payor_email = $order->get_billing_email();
            $meta_data = array('order_id' => $order_id);
            $note = $order_desc;
            $return_url = $this->get_return_url($order);
            $webhooks_url = get_site_url().'/wc-api/dfm_confirm_payment/';

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
                // $order->update_status('processing');
                // Return the payment redirect URL
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

        /**
         * Helper function to make API requests
         */
        private function make_api_request($endpoint, $data) {
            $api_url = 'https://devfundme.com/api/pms' . $endpoint;

            $headers = array(
                'Authorization' => 'Token ' . $this->api_token,
                'Content-Type' => 'application/json',
            );

            $api_response = wp_remote_post(
                $api_url,
                array(
                    'headers' => $headers,
                    'body' => wp_json_encode($data),
                )
            );

            
            // Check for errors and return the API response
            if (!is_wp_error($api_response)) {
                return json_decode(wp_remote_retrieve_body($api_response), true);
            } else {
                wc_add_notice(wp_remote_retrieve_body($api_response), 'error');
                return array('error' => $api_response->get_error_message());
            }
        }

        /**
         * Webhook handler
         */
        public function webhook() {
            header( 'HTTP/1.1 200 OK' );

            $json = file_get_contents('php://input'); 
            $obj = json_decode($json, true);

            $order_id = $obj['meta_data']['order_id'];

            if ( !empty( $order_id ) ) {

                $order = wc_get_order($order_id);
                $order->payment_complete();

                $order->update_status('completed');

                wc_reduce_stock_levels($order_id);

                $order->save();

            }

            update_option('webhook_debug', $obj);

            echo $order->get_status();
            
            die();
        }
    }
}
