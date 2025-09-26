<?php
/**
 * Stripe API Integration Class
 *
 * Handles all Stripe API interactions including payment processing,
 * subscription management, webhook handling, and customer management.
 *
 * @package Subs
 * @subpackage Integration
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs Stripe Integration Class
 *
 * @class Subs_Stripe
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Stripe {

    /**
     * Stripe API key
     *
     * @var string
     * @since 1.0.0
     */
    private $api_key;

    /**
     * Stripe publishable key
     *
     * @var string
     * @since 1.0.0
     */
    private $publishable_key;

    /**
     * Test mode flag
     *
     * @var bool
     * @since 1.0.0
     */
    private $test_mode;

    /**
     * Stripe settings
     *
     * @var array
     * @since 1.0.0
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->load_settings();
        $this->init_stripe();

        // Hook into WordPress actions
        add_action('init', array($this, 'handle_webhook'));
        add_action('wp_ajax_subs_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_nopriv_subs_create_payment_intent', array($this, 'create_payment_intent'));
    }

    /**
     * Load Stripe settings
     *
     * @since 1.0.0
     */
    private function load_settings() {
        $this->settings = get_option('subs_stripe_settings', array());
        $this->test_mode = isset($this->settings['test_mode']) && $this->settings['test_mode'] === 'yes';

        // Set API keys based on mode
        if ($this->test_mode) {
            $this->api_key = $this->settings['test_secret_key'] ?? '';
            $this->publishable_key = $this->settings['test_publishable_key'] ?? '';
        } else {
            $this->api_key = $this->settings['secret_key'] ?? '';
            $this->publishable_key = $this->settings['publishable_key'] ?? '';
        }
    }

    /**
     * Initialize Stripe API
     *
     * @since 1.0.0
     */
    private function init_stripe() {
        if (empty($this->api_key)) {
            return;
        }

        // Set Stripe API key for direct API calls
        // Note: In a real implementation, you would include the Stripe PHP library
        // and set the API key using \Stripe\Stripe::setApiKey($this->api_key);

        // For this example, we'll use direct HTTP requests
        $this->stripe_api_base = 'https://api.stripe.com/v1/';
    }

    /**
     * Make API request to Stripe
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array|WP_Error
     * @since 1.0.0
     */
    private function make_api_request($endpoint, $data = array(), $method = 'POST') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Stripe API key not configured', 'subs'));
        }

        $url = $this->stripe_api_base . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Stripe-Version' => '2023-10-16', // Use latest API version
            ),
            'timeout' => 30,
        );

        if (!empty($data)) {
            if ($method === 'POST') {
                $args['body'] = http_build_query($data);
            } else {
                $url = add_query_arg($data, $url);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        $parsed_response = json_decode($body, true);

        if ($http_code >= 400) {
            $error_message = isset($parsed_response['error']['message']) ?
                           $parsed_response['error']['message'] :
                           __('Stripe API request failed', 'subs');

            return new WP_Error('stripe_error', $error_message, array('response' => $parsed_response));
        }

        return $parsed_response;
    }

    /**
     * Create or retrieve Stripe customer
     *
     * @param int $customer_id Local customer ID
     * @return string|WP_Error Stripe customer ID
     * @since 1.0.0
     */
    public function get_or_create_stripe_customer($customer_id) {
        // Get local customer data
        global $wpdb;
        $customers_table = $wpdb->prefix . 'subs_customers';
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE id = %d",
            $customer_id
        ));

        if (!$customer) {
            return new WP_Error('customer_not_found', __('Customer not found', 'subs'));
        }

        // Check if customer already has Stripe ID
        if (!empty($customer->stripe_customer_id)) {
            return $customer->stripe_customer_id;
        }

        // Create new Stripe customer
        $customer_data = array(
            'email' => $customer->email,
            'name' => trim($customer->first_name . ' ' . $customer->last_name),
            'metadata' => array(
                'local_customer_id' => $customer_id,
            ),
        );

        if (!empty($customer->phone)) {
            $customer_data['phone'] = $customer->phone;
        }

        if (!empty($customer->address_line1)) {
            $customer_data['address'] = array(
                'line1' => $customer->address_line1,
                'line2' => $customer->address_line2,
                'city' => $customer->city,
                'state' => $customer->state,
                'postal_code' => $customer->postal_code,
                'country' => $customer->country,
            );
        }

        $result = $this->make_api_request('customers', $customer_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update local customer with Stripe ID
        $wpdb->update(
            $customers_table,
            array('stripe_customer_id' => $result['id']),
            array('id' => $customer_id),
            array('%s'),
            array('%d')
        );

        return $result['id'];
    }

    /**
     * Create Stripe subscription
     *
     * @param array $subscription_data
     * @return array|WP_Error
     * @since 1.0.0
     */
    public function create_stripe_subscription($subscription_data) {
        // Get or create Stripe customer
        $stripe_customer_id = $this->get_or_create_stripe_customer($subscription_data['customer_id']);
        if (is_wp_error($stripe_customer_id)) {
            return $stripe_customer_id;
        }

        // Create or get Stripe price
        $stripe_price_id = $this->create_stripe_price($subscription_data);
        if (is_wp_error($stripe_price_id)) {
            return $stripe_price_id;
        }

        // Prepare subscription data for Stripe
        $stripe_subscription_data = array(
            'customer' => $stripe_customer_id,
            'items' => array(
                array('price' => $stripe_price_id)
            ),
            'metadata' => array(
                'local_subscription_id' => $subscription_data['local_subscription_id'],
                'product_name' => $subscription_data['product_name'],
            ),
        );

        // Add trial period if specified
        if (!empty($subscription_data['trial_end'])) {
            $stripe_subscription_data['trial_end'] = strtotime($subscription_data['trial_end']);
        }

        // Set payment behavior
        $stripe_subscription_data['payment_behavior'] = 'default_incomplete';
        $stripe_subscription_data['payment_settings'] = array(
            'save_default_payment_method' => 'on_subscription',
        );

        // Create subscription in Stripe
        $result = $this->make_api_request('subscriptions', $stripe_subscription_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Return subscription data including client secret for frontend
        return array(
            'subscription_id' => $result['id'],
            'client_secret' => $result['latest_invoice']['payment_intent']['client_secret'],
            'status' => $result['status'],
        );
    }

    /**
     * Create Stripe price for subscription
     *
     * @param array $subscription_data
     * @return string|WP_Error Stripe price ID
     * @since 1.0.0
     */
    private function create_stripe_price($subscription_data) {
        // Create product first
        $product_data = array(
            'name' => $subscription_data['product_name'],
            'metadata' => array(
                'created_by' => 'subs_plugin',
            ),
        );

        $product_result = $this->make_api_request('products', $product_data);
        if (is_wp_error($product_result)) {
            return $product_result;
        }

        // Create price
        $price_data = array(
            'unit_amount' => intval($subscription_data['amount'] * 100), // Convert to cents
            'currency' => strtolower($subscription_data['currency']),
            'recurring' => array(
                'interval' => $subscription_data['billing_period'],
                'interval_count' => $subscription_data['billing_interval'],
            ),
            'product' => $product_result['id'],
            'metadata' => array(
                'created_by' => 'subs_plugin',
            ),
        );

        $price_result = $this->make_api_request('prices', $price_data);
        if (is_wp_error($price_result)) {
            return $price_result;
        }

        return $price_result['id'];
    }

    /**
     * Cancel Stripe subscription
     *
     * @param string $stripe_subscription_id
     * @param bool $immediate
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function cancel_subscription($stripe_subscription_id, $immediate = false) {
        $data = array();

        if ($immediate) {
            $data['prorate'] = 'false';
        } else {
            $data['at_period_end'] = 'true';
        }

        $result = $this->make_api_request("subscriptions/{$stripe_subscription_id}", $data, 'DELETE');

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Pause Stripe subscription
     *
     * @param string $stripe_subscription_id
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function pause_subscription($stripe_subscription_id) {
        $data = array(
            'pause_collection' => array(
                'behavior' => 'keep_as_draft',
            ),
        );

        $result = $this->make_api_request("subscriptions/{$stripe_subscription_id}", $data);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Resume Stripe subscription
     *
     * @param string $stripe_subscription_id
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function resume_subscription($stripe_subscription_id) {
        $data = array(
            'pause_collection' => '', // Remove pause
        );

        $result = $this->make_api_request("subscriptions/{$stripe_subscription_id}", $data);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Process subscription payment
     *
     * @param string $stripe_subscription_id
     * @return array|WP_Error
     * @since 1.0.0
     */
    public function process_subscription_payment($stripe_subscription_id) {
        // Get subscription from Stripe
        $subscription = $this->make_api_request("subscriptions/{$stripe_subscription_id}", array(), 'GET');

        if (is_wp_error($subscription)) {
            return $subscription;
        }

        // Get the latest invoice
        $invoice_id = $subscription['latest_invoice'];
        if (empty($invoice_id)) {
            return new WP_Error('no_invoice', __('No invoice found for subscription', 'subs'));
        }

        // Get invoice details
        $invoice = $this->make_api_request("invoices/{$invoice_id}", array(), 'GET');
        if (is_wp_error($invoice)) {
            return $invoice;
        }

        // Check payment status
        if ($invoice['status'] === 'paid') {
            return array(
                'status' => 'succeeded',
                'amount' => $invoice['amount_paid'] / 100, // Convert from cents
                'currency' => $invoice['currency'],
                'payment_intent_id' => $invoice['payment_intent'],
                'invoice_id' => $invoice_id,
                'payment_method' => 'stripe',
            );
        }

        // If not paid, try to collect payment
        if ($invoice['status'] === 'open') {
            $collect_result = $this->make_api_request("invoices/{$invoice_id}/pay", array());

            if (is_wp_error($collect_result)) {
                return $collect_result;
            }

            if ($collect_result['status'] === 'paid') {
                return array(
                    'status' => 'succeeded',
                    'amount' => $collect_result['amount_paid'] / 100,
                    'currency' => $collect_result['currency'],
                    'payment_intent_id' => $collect_result['payment_intent'],
                    'invoice_id' => $invoice_id,
                    'payment_method' => 'stripe',
                );
            }
        }

        return new WP_Error('payment_failed', __('Payment could not be processed', 'subs'));
    }

    /**
     * Create payment intent for setup or one-time payment
     *
     * @param array $payment_data
     * @return array|WP_Error
     * @since 1.0.0
     */
    public function create_payment_intent($payment_data = array()) {
        // Handle AJAX request
        if (wp_doing_ajax()) {
            check_ajax_referer('subs_payment_nonce', 'nonce');
            $payment_data = $_POST;
        }

        $data = array(
            'amount' => intval($payment_data['amount'] * 100), // Convert to cents
            'currency' => strtolower($payment_data['currency']),
            'automatic_payment_methods' => array(
                'enabled' => true,
            ),
        );

        // Add customer if provided
        if (!empty($payment_data['customer_id'])) {
            $stripe_customer_id = $this->get_or_create_stripe_customer($payment_data['customer_id']);
            if (!is_wp_error($stripe_customer_id)) {
                $data['customer'] = $stripe_customer_id;
            }
        }

        // Add metadata
        if (!empty($payment_data['metadata'])) {
            $data['metadata'] = $payment_data['metadata'];
        }

        $result = $this->make_api_request('payment_intents', $data);

        if (wp_doing_ajax()) {
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success(array(
                    'client_secret' => $result['client_secret'],
                ));
            }
        }

        return $result;
    }

    /**
     * Update customer payment method
     *
     * @param int $customer_id
     * @param string $payment_method_id
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function update_customer_payment_method($customer_id, $payment_method_id) {
        $stripe_customer_id = $this->get_or_create_stripe_customer($customer_id);
        if (is_wp_error($stripe_customer_id)) {
            return $stripe_customer_id;
        }

        // Attach payment method to customer
        $attach_result = $this->make_api_request("payment_methods/{$payment_method_id}/attach", array(
            'customer' => $stripe_customer_id,
        ));

        if (is_wp_error($attach_result)) {
            return $attach_result;
        }

        // Set as default payment method
        $update_result = $this->make_api_request("customers/{$stripe_customer_id}", array(
            'invoice_settings' => array(
                'default_payment_method' => $payment_method_id,
            ),
        ));

        if (is_wp_error($update_result)) {
            return $update_result;
        }

        return true;
    }

    /**
     * Handle Stripe webhooks
     *
     * @since 1.0.0
     */
    public function handle_webhook() {
        // Only process webhook on our endpoint
        if (!isset($_GET['subs_stripe_webhook'])) {
            return;
        }

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        // Verify webhook signature
        if (!$this->verify_webhook_signature($payload, $sig_header)) {
            status_header(400);
            exit('Invalid signature');
        }

        $event = json_decode($payload, true);
        if (!$event) {
            status_header(400);
            exit('Invalid JSON');
        }

        // Log webhook event
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Stripe webhook received: {$event['type']}");
        }

        // Process webhook based on type
        switch ($event['type']) {
            case 'invoice.payment_succeeded':
                $this->handle_payment_succeeded($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handle_payment_failed($event['data']['object']);
                break;

            case 'customer.subscription.updated':
                $this->handle_subscription_updated($event['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted($event['data']['object']);
                break;

            case 'customer.subscription.trial_will_end':
                $this->handle_trial_will_end($event['data']['object']);
                break;

            default:
                // Log unhandled webhook types
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Unhandled Stripe webhook: {$event['type']}");
                }
        }

        status_header(200);
        exit('OK');
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string $sig_header
     * @return bool
     * @since 1.0.0
     */
    private function verify_webhook_signature($payload, $sig_header) {
        $webhook_secret = $this->settings['webhook_secret'] ?? '';
        if (empty($webhook_secret)) {
            return true; // Skip verification if no secret configured
        }

        $elements = explode(',', $sig_header);
        $signature = null;
        $timestamp = null;

        foreach ($elements as $element) {
            list($key, $value) = explode('=', $element);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signature = $value;
            }
        }

        if (!$signature || !$timestamp) {
            return false;
        }

        // Check timestamp (5 minutes tolerance)
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        // Verify signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $webhook_secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Handle successful payment webhook
     *
     * @param array $invoice
     * @since 1.0.0
     */
    private function handle_payment_succeeded($invoice) {
        // Get subscription ID from invoice
        $stripe_subscription_id = $invoice['subscription'] ?? null;
        if (!$stripe_subscription_id) {
            return;
        }

        // Find local subscription
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription_by_stripe_id($stripe_subscription_id);
        if (!$subscription) {
            return;
        }

        // Log payment
        $payment_data = array(
            'amount' => $invoice['amount_paid'] / 100,
            'currency' => $invoice['currency'],
            'status' => 'succeeded',
            'payment_method' => 'stripe',
            'payment_intent_id' => $invoice['payment_intent'],
            'invoice_id' => $invoice['id'],
        );

        $subscription_handler->log_payment($subscription->id, $payment_data);

        // Add history entry
        $subscription_handler->add_history_entry(
            $subscription->id,
            'payment_succeeded',
            sprintf(__('Payment of %s %s succeeded', 'subs'),
                   $payment_data['amount'],
                   strtoupper($payment_data['currency']))
        );

        // Trigger action for email notifications
        do_action('subs_payment_succeeded', $subscription->id, $payment_data);
    }

    /**
     * Handle failed payment webhook
     *
     * @param array $invoice
     * @since 1.0.0
     */
    private function handle_payment_failed($invoice) {
        $stripe_subscription_id = $invoice['subscription'] ?? null;
        if (!$stripe_subscription_id) {
            return;
        }

        // Find local subscription
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription_by_stripe_id($stripe_subscription_id);
        if (!$subscription) {
            return;
        }

        $error_message = $invoice['last_payment_error']['message'] ?? __('Payment failed', 'subs');

        // Handle payment failure
        $subscription_handler->handle_payment_failure($subscription->id, $error_message);
    }

    /**
     * Handle subscription updated webhook
     *
     * @param array $stripe_subscription
     * @since 1.0.0
     */
    private function handle_subscription_updated($stripe_subscription) {
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription_by_stripe_id($stripe_subscription['id']);
        if (!$subscription) {
            return;
        }

        // Update local subscription status and periods
        $update_data = array(
            'status' => $this->convert_stripe_status($stripe_subscription['status']),
            'current_period_start' => date('Y-m-d H:i:s', $stripe_subscription['current_period_start']),
            'current_period_end' => date('Y-m-d H:i:s', $stripe_subscription['current_period_end']),
        );

        $subscription_handler->update_subscription($subscription->id, $update_data);
    }

    /**
     * Handle subscription deleted webhook
     *
     * @param array $stripe_subscription
     * @since 1.0.0
     */
    private function handle_subscription_deleted($stripe_subscription) {
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription_by_stripe_id($stripe_subscription['id']);
        if (!$subscription) {
            return;
        }

        // Update local subscription to cancelled
        $subscription_handler->update_subscription($subscription->id, array(
            'status' => 'cancelled',
            'cancellation_date' => current_time('mysql'),
        ));

        $subscription_handler->add_history_entry(
            $subscription->id,
            'cancelled',
            __('Subscription cancelled via Stripe webhook', 'subs')
        );
    }

    /**
     * Handle trial ending webhook
     *
     * @param array $stripe_subscription
     * @since 1.0.0
     */
    private function handle_trial_will_end($stripe_subscription) {
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription_by_stripe_id($stripe_subscription['id']);
        if (!$subscription) {
            return;
        }

        // Add history entry
        $subscription_handler->add_history_entry(
            $subscription->id,
            'trial_ending',
            __('Trial period ending soon', 'subs')
        );

        // Trigger action for email notifications
        do_action('subs_trial_ending', $subscription->id);
    }

    /**
     * Convert Stripe subscription status to local status
     *
     * @param string $stripe_status
     * @return string
     * @since 1.0.0
     */
    private function convert_stripe_status($stripe_status) {
        $status_map = array(
            'active' => 'active',
            'past_due' => 'past_due',
            'unpaid' => 'unpaid',
            'canceled' => 'cancelled',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'incomplete_expired',
            'trialing' => 'trialing',
        );

        return $status_map[$stripe_status] ?? 'active';
    }

    /**
     * Get Stripe settings
     *
     * @return array
     * @since 1.0.0
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get publishable key for frontend
     *
     * @return string
     * @since 1.0.0
     */
    public function get_publishable_key() {
        return $this->publishable_key;
    }

    /**
     * Check if Stripe is properly configured
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->publishable_key);
    }

    /**
     * Get webhook endpoint URL
     *
     * @return string
     * @since 1.0.0
     */
    public function get_webhook_url() {
        return add_query_arg('subs_stripe_webhook', '1', home_url('/'));
    }

    /**
     * Test Stripe connection
     *
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function test_connection() {
        $result = $this->make_api_request('balance', array(), 'GET');

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Format amount for display
     *
     * @param float $amount
     * @param string $currency
     * @return string
     * @since 1.0.0
     */
    public function format_amount($amount, $currency = 'USD') {
        $currency_symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C',
            'AUD' => 'A',
        );

        $symbol = $currency_symbols[$currency] ?? $currency;
        return $symbol . number_format($amount, 2);
    }

    /**
     * Get supported currencies
     *
     * @return array
     * @since 1.0.0
     */
    public function get_supported_currencies() {
        return array(
            'USD' => __('US Dollar', 'subs'),
            'EUR' => __('Euro', 'subs'),
            'GBP' => __('British Pound', 'subs'),
            'CAD' => __('Canadian Dollar', 'subs'),
            'AUD' => __('Australian Dollar', 'subs'),
            'JPY' => __('Japanese Yen', 'subs'),
            'CHF' => __('Swiss Franc', 'subs'),
            'NOK' => __('Norwegian Krone', 'subs'),
            'SEK' => __('Swedish Krona', 'subs'),
            'DKK' => __('Danish Krone', 'subs'),
        );
    }
}
