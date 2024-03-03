<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

class WC_DevFundMe_PMS extends WC_Payment_Gateway {

    protected $api_token;

    public function __construct() {
        $this->id = 'devfundme_pms';
        $this->method_title = __('DevFundMe Payment Gateway', 'devfundme-pms');
        $this->title = $this->get_option('title');
        $this->has_fields = true;

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Retrieve the API token from options
        $this->api_token = get_option('devfundme_pms_api_token', '');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_init', array($this, 'admin_options_init'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'devfundme-pms'),
                'type' => 'checkbox',
                'label' => __('Enable DevFundMe Payment Gateway', 'devfundme-pms'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'devfundme-pms'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'devfundme-pms'),
                'default' => __('DevFundMe Payment Gateway', 'devfundme-pms'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'devfundme-pms'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'devfundme-pms'),
                'default' => __('Pay securely using DevFundMe Payment Gateway.', 'devfundme-pms'),
            ),
            'api_token' => array(
                'title' => __('Reveal API Token', 'devfundme-pms'),
                'type' => 'text',
                'description' => __('Enter your Reveal API Token.', 'devfundme-pms'),
                'default' => '',
            ),
        );
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
        if (isset($_POST[$this->plugin_id . $this->id . '_api_token'])) {
            update_option('devfundme_pms_api_token', wc_clean(wp_unslash($_POST[$this->plugin_id . $this->id . '_api_token'])));
        }
    }

    public function admin_options() {
        ?>
        <h2><?php _e('DevFundMe Payment Gateway Settings', 'devfundme-pms'); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?php _e('Reveal API Token', 'devfundme-pms'); ?></th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Reveal API Token', 'devfundme-pms'); ?></span></legend>
                        <input type="text" name="<?php echo esc_attr($this->plugin_id . $this->id . '_api_token'); ?>" id="<?php echo esc_attr($this->plugin_id . $this->id . '_api_token'); ?>" value="<?php echo esc_attr($this->api_token); ?>" />
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }
}
