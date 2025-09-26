<?php
/**
 * AJAX Request Handler Class
 *
 * Handles all AJAX requests from both frontend and admin interfaces,
 * including subscription management, customer operations, and real-time updates.
 *
 * @package Subs
 * @subpackage Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs AJAX Class
 *
 * @class Subs_Ajax
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Ajax {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize AJAX handlers
     *
     * @since 1.0.0
     */
    public function init() {
        // Frontend AJAX actions (logged in and not logged in)
        add_action('wp_ajax_subs_create_subscription', array($this, 'create_subscription'));
        add_action('wp_ajax_nopriv_subs_create_subscription', array($this, 'create_subscription'));

        add_action('wp_ajax_subs_update_subscription', array($this, 'update_subscription'));
        add_action('wp_ajax_subs_cancel_subscription', array($this, 'cancel_subscription'));
        add_action('wp_ajax_subs_pause_subscription', array($this, 'pause_subscription'));
        add_action('wp_ajax_subs_resume_subscription', array($this, 'resume_subscription'));

        add_action('wp_ajax_subs_update_customer', array($this, 'update_customer'));
        add_action('wp_ajax_subs_update_payment_method', array($this, 'update_payment_method'));

        add_action('wp_ajax_subs_get_subscription_details', array($this, 'get_subscription_details'));
        add_action('wp_ajax_subs_get_customer_subscriptions', array($this, 'get_customer_subscriptions'));

        // Stripe payment intents
        add_action('wp_ajax_subs_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_nopriv_subs_create_payment_intent', array($this, 'create_payment_intent'));

        // Admin-only AJAX actions
        add_action('wp_ajax_subs_search_customers', array($this, 'search_customers'));
        add_action('wp_ajax_subs_bulk_subscription_action', array($this, 'bulk_subscription_action'));
        add_action('wp_ajax_subs_export_data', array($this, 'export_data'));
        add_action('wp_ajax_subs_test_stripe_connection', array($this, 'test_stripe_connection'));
        add_action('wp_ajax_subs_sync_stripe_subscription', array($this, 'sync_stripe_subscription'));

        // Real-time updates
        add_action('wp_ajax_subs_get_dashboard_stats', array($this, 'get_dashboard_stats'));
        add_action('wp_ajax_subs_refresh_subscription_status', array($this, 'refresh_subscription_status'));
    }

    /**
     * Create subscription via AJAX
     *
     * @since 1.0.0
     */
    public function create_subscription() {
        $this->verify_nonce('subs_frontend_nonce');

        try {
            // Get or create customer
            $customer_id = $this->get_or_create_customer_from_request();
            if (is_wp_error($customer_id)) {
                wp_send_json_error($customer_id->get_error_message());
            }

            // Validate subscription data
            $subscription_data = $this->validate_subscription_data($_POST);
            if (is_wp_error($subscription_data)) {
                wp_send_json_error($subscription_data->get_error_message());
            }

            $subscription_data['customer_id'] = $customer_id;

            // Create subscription
            $subscription_handler = new Subs_Subscription();
            $subscription_id = $subscription_handler->create_subscription($subscription_data);

            if (is_wp_error($subscription_id)) {
                wp_send_json_error($subscription_id->get_error_message());
            }

            // Create Stripe subscription if configured
            $stripe = new Subs_Stripe();
            if ($stripe->is_configured()) {
                $stripe_data = array_merge($subscription_data, array(
                    'local_subscription_id' => $subscription_id
                ));

                $stripe_result = $stripe->create_stripe_subscription($stripe_data);
                if (is_wp_error($stripe_result)) {
                    // Clean up local subscription
                    $subscription_handler->update_subscription($subscription_id, array('status' => 'incomplete'));
                    wp_send_json_error($stripe_result->get_error_message());
                }

                // Update subscription with Stripe data
                $subscription_handler->update_subscription($subscription_id, array(
                    'stripe_subscription_id' => $stripe_result['subscription_id']
                ));

                wp_send_json_success(array(
                    'subscription_id' => $subscription_id,
                    'client_secret' => $stripe_result['client_secret'],
                    'redirect_url' => home_url('/subscription-portal/')
                ));
            } else {
                wp_send_json_success(array(
                    'subscription_id' => $subscription_id,
                    'redirect_url' => home_url('/subscription-portal/')
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Update subscription via AJAX
     *
     * @since 1.0.0
     */
    public function update_subscription() {
        $this->verify_nonce('subs_frontend_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to update subscriptions.', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        if (!$subscription_id) {
            wp_send_json_error(__('Invalid subscription ID.', 'subs'));
        }

        // Verify user owns subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('You do not have permission to update this subscription.', 'subs'));
        }

        $update_data = array();
        $allowed_fields = array('product_name', 'amount', 'currency', 'billing_period', 'billing_interval');

        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                if ($field === 'amount') {
                    $value = floatval($value);
                } elseif (in_array($field, array('billing_interval'))) {
                    $value = intval($value);
                }
                $update_data[$field] = $value;
            }
        }

        if (empty($update_data)) {
            wp_send_json_error(__('No valid fields to update.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $result = $subscription_handler->update_subscription($subscription_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Subscription updated successfully.', 'subs'));
    }

    /**
     * Cancel subscription via AJAX
     *
     * @since 1.0.0
     */
    public function cancel_subscription() {
        $this->verify_nonce('subs_frontend_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to cancel subscriptions.', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        $immediate = isset($_POST['immediate']) && $_POST['immediate'] === '1';

        if (!$subscription_id) {
            wp_send_json_error(__('Invalid subscription ID.', 'subs'));
        }

        // Verify user owns subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('You do not have permission to cancel this subscription.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $result = $subscription_handler->cancel_subscription($subscription_id, $reason, $immediate);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Subscription cancelled successfully.', 'subs'));
    }

    /**
     * Pause subscription via AJAX
     *
     * @since 1.0.0
     */
    public function pause_subscription() {
        $this->verify_nonce('subs_frontend_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to pause subscriptions.', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$subscription_id) {
            wp_send_json_error(__('Invalid subscription ID.', 'subs'));
        }

        // Verify user owns subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('You do not have permission to pause this subscription.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $result = $subscription_handler->pause_subscription($subscription_id, $reason);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Subscription paused successfully.', 'subs'));
    }

    /**
     * Resume subscription via AJAX
     *
     * @since 1.0.0
     */
    public function resume_subscription() {
        $this->verify_nonce('subs_frontend_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to resume subscriptions.', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);

        if (!$subscription_id) {
            wp_send_json_error(__('Invalid subscription ID.', 'subs'));
        }

        // Verify user owns subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('You do not have permission to resume this subscription.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $result = $subscription_handler->resume_subscription($subscription_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Subscription resumed successfully.', 'subs'));
    }

    /**
     * Update customer via AJAX
     *
     * @since 1.0.0
     */
    public function update_customer() {
        $this->verify_nonce('subs_frontend_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to update customer information.', 'subs'));
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            wp_send_json_error(__('Customer record not found.', 'subs'));
        }

        // Sanitize and validate data
        $update_data = array(
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address_line1' => sanitize_text_field($_POST['address_line1'] ?? ''),
            'address_line2' => sanitize_text_field($_POST['address_line2'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'state' => sanitize_text_field($_POST['state'] ?? ''),
            'postal_code' => sanitize_text_field($_POST['postal_code'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? ''),
            'flag_delivery_address' => sanitize_textarea_field($_POST['flag_delivery_address'] ?? ''),
        );

        // Validate data
        $validation_errors = $customer_handler->validate_customer_data($update_data);
        if (!empty($validation_errors)) {
            wp_send_json_error(implode(' ', $validation_errors));
        }

        $result = $customer_handler->update_customer($customer->id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Customer information updated successfully.', 'subs'));
    }

    /**
     * Update payment method via AJAX
     *
     * @since 1.0.0
     */
    public function update_payment_method() {
        $this->verify_nonce('subs_frontend_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to update payment methods.', 'subs'));
        }

        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');
        if (empty($payment_method_id)) {
            wp_send_json_error(__('Payment method ID is required.', 'subs'));
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            wp_send_json_error(__('Customer record not found.', 'subs'));
        }

        $stripe = new Subs_Stripe();
        $result = $stripe->update_customer_payment_method($customer->id, $payment_method_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Payment method updated successfully.', 'subs'));
    }

    /**
     * Get subscription details via AJAX
     *
     * @since 1.0.0
     */
    public function get_subscription_details() {
        $this->verify_nonce('subs_frontend_nonce');

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        if (!$subscription_id) {
            wp_send_json_error(__('Invalid subscription ID.', 'subs'));
        }

        // Check permissions
        if (!is_user_logged_in() || !$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('You do not have permission to view this subscription.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription) {
            wp_send_json_error(__('Subscription not found.', 'subs'));
        }

        // Get subscription history
        $history = $subscription_handler->get_subscription_history($subscription_id, 10);

        wp_send_json_success(array(
            'subscription' => $subscription,
            'history' => $history,
            'formatted_amount' => number_format($subscription->amount, 2) . ' ' . strtoupper($subscription->currency),
            'status_label' => Subs_Subscription::get_statuses()[$subscription->status] ?? $subscription->status,
        ));
    }

    /**
     * Get customer subscriptions via AJAX
     *
     * @since 1.0.0
     */
    public function get_customer_subscriptions() {
        $this->verify_nonce('subs_frontend_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to view subscriptions.', 'subs'));
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            wp_send_json_error(__('Customer record not found.', 'subs'));
        }

        $subscriptions = $customer_handler->get_customer_subscriptions($customer->id);

        wp_send_json_success(array(
            'subscriptions' => $subscriptions,
            'total_count' => count($subscriptions),
        ));
    }

    /**
     * Create payment intent via AJAX
     *
     * @since 1.0.0
     */
    public function create_payment_intent() {
        $this->verify_nonce('subs_payment_nonce');

        $amount = floatval($_POST['amount'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'USD');

        if ($amount <= 0) {
            wp_send_json_error(__('Invalid amount.', 'subs'));
        }

        $payment_data = array(
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => array(
                'source' => 'subs_plugin',
                'created_via' => 'ajax'
            )
        );

        // Add customer if logged in
        if (is_user_logged_in()) {
            $customer_handler = new Subs_Customer();
            $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());
            if ($customer) {
                $payment_data['customer_id'] = $customer->id;
            }
        }

        $stripe = new Subs_Stripe();
        $result = $stripe->create_payment_intent($payment_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'client_secret' => $result['client_secret']
        ));
    }

    /**
     * Search customers via AJAX (admin only)
     *
     * @since 1.0.0
     */
    public function search_customers() {
        if (!current_user_can('manage_subs_customers')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        $search = sanitize_text_field($_POST['search'] ?? '');
        $limit = intval($_POST['limit'] ?? 20);

        $customer_handler = new Subs_Customer();
        $customers = $customer_handler->search_customers(array(
            'search' => $search,
            'limit' => $limit,
        ));

        wp_send_json_success($customers);
    }

    /**
     * Bulk subscription action via AJAX (admin only)
     *
     * @since 1.0.0
     */
    public function bulk_subscription_action() {
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        $action = sanitize_text_field($_POST['action'] ?? '');
        $subscription_ids = array_map('intval', $_POST['subscription_ids'] ?? array());

        if (empty($action) || empty($subscription_ids)) {
            wp_send_json_error(__('Invalid action or subscription IDs.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $results = array();
        $errors = array();

        foreach ($subscription_ids as $subscription_id) {
            switch ($action) {
                case 'cancel':
                    $result = $subscription_handler->cancel_subscription($subscription_id, 'Bulk cancellation');
                    break;

                case 'pause':
                    $result = $subscription_handler->pause_subscription($subscription_id, 'Bulk pause');
                    break;

                case 'resume':
                    $result = $subscription_handler->resume_subscription($subscription_id);
                    break;

                default:
                    $result = new WP_Error('invalid_action', 'Invalid action');
            }

            if (is_wp_error($result)) {
                $errors[] = sprintf(__('Subscription %d: %s', 'subs'), $subscription_id, $result->get_error_message());
            } else {
                $results[] = $subscription_id;
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => __('Some operations failed:', 'subs'),
                'errors' => $errors,
                'successful' => $results,
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Successfully processed %d subscriptions.', 'subs'), count($results)),
            'processed' => $results,
        ));
    }

    /**
     * Export data via AJAX (admin only)
     *
     * @since 1.0.0
     */
    public function export_data() {
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        $export_type = sanitize_text_field($_POST['export_type'] ?? '');
        $filters = $_POST['filters'] ?? array();

        switch ($export_type) {
            case 'subscriptions':
                $admin = new Subs_Admin();
                $admin->export_subscriptions_csv($filters);
                break;

            case 'customers':
                $customer_handler = new Subs_Customer();
                $customer_handler->export_customers_csv($filters);
                break;

            default:
                wp_send_json_error(__('Invalid export type.', 'subs'));
        }

        // If we get here, the export failed
        wp_send_json_error(__('Export failed.', 'subs'));
    }

    /**
     * Test Stripe connection via AJAX (admin only)
     *
     * @since 1.0.0
     */
    public function test_stripe_connection() {
        if (!current_user_can('manage_subs_settings')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        $stripe = new Subs_Stripe();
        $result = $stripe->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Stripe connection successful!', 'subs'));
    }

    /**
     * Sync Stripe subscription via AJAX (admin only)
     *
     * @since 1.0.0
     */
    public function sync_stripe_subscription() {
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        if (!$subscription_id) {
            wp_send_json_error(__('Invalid subscription ID.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || empty($subscription->stripe_subscription_id)) {
            wp_send_json_error(__('Subscription not found or not linked to Stripe.', 'subs'));
        }

        // Get subscription from Stripe and sync
        $stripe = new Subs_Stripe();
        $stripe_subscription = $stripe->make_api_request("subscriptions/{$subscription->stripe_subscription_id}", array(), 'GET');

        if (is_wp_error($stripe_subscription)) {
            wp_send_json_error($stripe_subscription->get_error_message());
        }

        // Update local subscription with Stripe data
        $update_data = array(
            'status' => $stripe->convert_stripe_status($stripe_subscription['status']),
            'current_period_start' => date('Y-m-d H:i:s', $stripe_subscription['current_period_start']),
            'current_period_end' => date('Y-m-d H:i:s', $stripe_subscription['current_period_end']),
        );

        $result = $subscription_handler->update_subscription($subscription_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Subscription synced successfully with Stripe.', 'subs'));
    }

    /**
     * Get dashboard stats via AJAX (admin only)
     *
     * @since 1.0.0
     */
    public function get_dashboard_stats() {
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        $admin = new Subs_Admin();
        $customer_handler = new Subs_Customer();

        $stats = array(
            'subscription_counts' => $admin->get_subscription_counts(),
            'customer_stats' => $customer_handler->get_customer_stats(),
            'recent_activity' => $admin->get_recent_activity(5),
            'revenue_stats' => $admin->get_revenue_stats('month'),
        );

        wp_send_json_success($stats);
    }

    /**
     * Refresh subscription status via AJAX
     *
     * @since 1.0.0
     */
    public function refresh_subscription_status() {
        $this->verify_nonce('subs_frontend_nonce');

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        if (!$subscription_id) {
            wp_send_json_error(__('Invalid subscription ID.', 'subs'));
        }

        // Check permissions
        if (!is_user_logged_in() || !$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('You do not have permission to view this subscription.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription) {
            wp_send_json_error(__('Subscription not found.', 'subs'));
        }

        wp_send_json_success(array(
            'status' => $subscription->status,
            'status_label' => Subs_Subscription::get_statuses()[$subscription->status] ?? $subscription->status,
            'next_payment_date' => $subscription->next_payment_date ?
                                  date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date)) :
                                  null,
        ));
    }

    /**
     * Verify AJAX nonce
     *
     * @param string $action
     * @since 1.0.0
     */
    private function verify_nonce($action) {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $action)) {
            wp_send_json_error(__('Security check failed.', 'subs'));
        }
    }

    /**
     * Get or create customer from AJAX request
     *
     * @return int|WP_Error Customer ID or error
     * @since 1.0.0
     */
    private function get_or_create_customer_from_request() {
        $customer_handler = new Subs_Customer();

        if (is_user_logged_in()) {
            // Try to get existing customer
            $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());
            if ($customer) {
                return $customer->id;
            }

            // Create customer for logged-in user
            $wp_user = wp_get_current_user();
            $customer_data = array(
                'wp_user_id' => $wp_user->ID,
                'email' => $wp_user->user_email,
                'first_name' => $wp_user->first_name,
                'last_name' => $wp_user->last_name,
            );

            return $customer_handler->create_customer($customer_data);
        } else {
            // Create customer from form data
            $email = sanitize_email($_POST['customer_email'] ?? '');
            if (empty($email)) {
                return new WP_Error('missing_email', __('Email address is required.', 'subs'));
            }

            // Check if customer already exists
            $existing_customer = $customer_handler->get_customer_by_email($email);
            if ($existing_customer) {
                return $existing_customer->id;
            }

            $customer_data = array(
                'email' => $email,
                'first_name' => sanitize_text_field($_POST['customer_first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['customer_last_name'] ?? ''),
                'phone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
            );

            return $customer_handler->create_customer($customer_data);
        }
    }

    /**
     * Validate subscription data from AJAX request
     *
     * @param array $data
     * @return array|WP_Error
     * @since 1.0.0
     */
    private function validate_subscription_data($data) {
        $subscription_data = array();
        $errors = array();

        // Product name
        $product_name = sanitize_text_field($data['product_name'] ?? '');
        if (empty($product_name)) {
            $errors[] = __('Product name is required.', 'subs');
        } else {
            $subscription_data['product_name'] = $product_name;
        }

        // Amount
        $amount = floatval($data['amount'] ?? 0);
        if ($amount <= 0) {
            $errors[] = __('Amount must be greater than 0.', 'subs');
        } else {
            $subscription_data['amount'] = $amount;
        }

        // Currency
        $currency = sanitize_text_field($data['currency'] ?? 'USD');
        $subscription_data['currency'] = $currency;

        // Billing period
        $billing_period = sanitize_text_field($data['billing_period'] ?? 'month');
        $valid_periods = array_keys(Subs_Subscription::get_billing_periods());
        if (!in_array($billing_period, $valid_periods)) {
            $errors[] = __('Invalid billing period.', 'subs');
        } else {
            $subscription_data['billing_period'] = $billing_period;
        }

        // Billing interval
        $billing_interval = intval($data['billing_interval'] ?? 1);
        if ($billing_interval < 1) {
            $errors[] = __('Billing interval must be at least 1.', 'subs');
        } else {
            $subscription_data['billing_interval'] = $billing_interval;
        }

        // Trial end (optional)
        if (!empty($data['trial_end'])) {
            $trial_end = sanitize_text_field($data['trial_end']);
            if (strtotime($trial_end) === false) {
                $errors[] = __('Invalid trial end date.', 'subs');
            } else {
                $subscription_data['trial_end'] = date('Y-m-d H:i:s', strtotime($trial_end));
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return $subscription_data;
    }

    /**
     * Check if current user owns a subscription
     *
     * @param int $subscription_id
     * @return bool
     * @since 1.0.0
     */
    private function user_owns_subscription($subscription_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            return false;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        return $subscription && $subscription->customer_id == $customer->id;
    }

    /**
     * Sanitize array of IDs
     *
     * @param array $ids
     * @return array
     * @since 1.0.0
     */
    private function sanitize_ids_array($ids) {
        if (!is_array($ids)) {
            return array();
        }

        return array_filter(array_map('intval', $ids));
    }

    /**
     * Log AJAX action for debugging
     *
     * @param string $action
     * @param array $data
     * @since 1.0.0
     */
    private function log_ajax_action($action, $data = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_data = array(
            'action' => $action,
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => current_time('mysql'),
            'data' => $data,
        );

        error_log('Subs AJAX: ' . wp_json_encode($log_data));
    }

    /**
     * Rate limit AJAX requests
     *
     * @param string $action
     * @param int $limit
     * @param int $window
     * @return bool
     * @since 1.0.0
     */
    private function rate_limit($action, $limit = 10, $window = 60) {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "subs_rate_limit_{$action}_{$user_id}_{$ip}";

        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, $window);
            return true;
        }

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Handle file upload for imports (admin only)
     *
     * @since 1.0.0
     */
    public function handle_file_upload() {
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(__('No file uploaded.', 'subs'));
        }

        $file = $_FILES['import_file'];
        $allowed_types = array('text/csv', 'application/csv', 'text/plain');

        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(__('Invalid file type. Please upload a CSV file.', 'subs'));
        }

        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            wp_send_json_error(__('File too large. Maximum size is 5MB.', 'subs'));
        }

        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'] . '/subs_import_' . time() . '.csv';

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            wp_send_json_error(__('Failed to save uploaded file.', 'subs'));
        }

        // Process the CSV file
        $result = $this->process_import_file($upload_path);

        // Clean up uploaded file
        unlink($upload_path);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Process import file
     *
     * @param string $file_path
     * @return array|WP_Error
     * @since 1.0.0
     */
    private function process_import_file($file_path) {
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_error', __('Could not read import file.', 'subs'));
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return new WP_Error('invalid_file', __('Invalid CSV file format.', 'subs'));
        }

        $required_headers = array('email', 'product_name', 'amount');
        $missing_headers = array_diff($required_headers, $headers);

        if (!empty($missing_headers)) {
            fclose($handle);
            return new WP_Error('missing_headers', sprintf(__('Missing required headers: %s', 'subs'), implode(', ', $missing_headers)));
        }

        $imported = 0;
        $errors = array();
        $row = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;

            if (count($data) !== count($headers)) {
                $errors[] = sprintf(__('Row %d: Column count mismatch', 'subs'), $row);
                continue;
            }

            $row_data = array_combine($headers, $data);

            // Process row data
            $result = $this->import_subscription_row($row_data);

            if (is_wp_error($result)) {
                $errors[] = sprintf(__('Row %d: %s', 'subs'), $row, $result->get_error_message());
            } else {
                $imported++;
            }
        }

        fclose($handle);

        return array(
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => $row - 1,
        );
    }

    /**
     * Import single subscription row
     *
     * @param array $data
     * @return int|WP_Error
     * @since 1.0.0
     */
    private function import_subscription_row($data) {
        // Create or get customer
        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_email($data['email']);

        if (!$customer) {
            $customer_data = array(
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
            );

            $customer_id = $customer_handler->create_customer($customer_data);
            if (is_wp_error($customer_id)) {
                return $customer_id;
            }
        } else {
            $customer_id = $customer->id;
        }

        // Create subscription
        $subscription_data = array(
            'customer_id' => $customer_id,
            'product_name' => $data['product_name'],
            'amount' => floatval($data['amount']),
            'currency' => $data['currency'] ?? 'USD',
            'billing_period' => $data['billing_period'] ?? 'month',
            'billing_interval' => intval($data['billing_interval'] ?? 1),
            'status' => $data['status'] ?? 'active',
        );

        $subscription_handler = new Subs_Subscription();
        return $subscription_handler->create_subscription($subscription_data);
    }

    /**
     * Get subscription analytics data (admin only)
     *
     * @since 1.0.0
     */
    public function get_analytics_data() {
        if (!current_user_can('view_subs_reports')) {
            wp_send_json_error(__('Insufficient permissions.', 'subs'));
        }

        $this->verify_nonce('subs_admin_nonce');

        $period = sanitize_text_field($_POST['period'] ?? 'month');
        $metric = sanitize_text_field($_POST['metric'] ?? 'revenue');

        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        $data = array();

        switch ($metric) {
            case 'revenue':
                $date_format = $period === 'day' ? '%Y-%m-%d' : '%Y-%m';
                $interval = $period === 'day' ? '30 DAY' : '12 MONTH';

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT
                        DATE_FORMAT(p.processed_date, %s) as period,
                        SUM(p.amount) as total
                     FROM $payment_logs_table p
                     WHERE p.status = 'succeeded'
                     AND p.processed_date >= DATE_SUB(NOW(), INTERVAL $interval)
                     GROUP BY DATE_FORMAT(p.processed_date, %s)
                     ORDER BY period ASC",
                    $date_format,
                    $date_format
                ));

                foreach ($results as $result) {
                    $data[] = array(
                        'period' => $result->period,
                        'value' => floatval($result->total),
                    );
                }
                break;

            case 'subscriptions':
                $date_format = $period === 'day' ? '%Y-%m-%d' : '%Y-%m';
                $interval = $period === 'day' ? '30 DAY' : '12 MONTH';

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT
                        DATE_FORMAT(created_date, %s) as period,
                        COUNT(*) as total
                     FROM $subscriptions_table
                     WHERE created_date >= DATE_SUB(NOW(), INTERVAL $interval)
                     GROUP BY DATE_FORMAT(created_date, %s)
                     ORDER BY period ASC",
                    $date_format,
                    $date_format
                ));

                foreach ($results as $result) {
                    $data[] = array(
                        'period' => $result->period,
                        'value' => intval($result->total),
                    );
                }
                break;

            case 'churn':
                // Calculate churn rate by period
                $date_format = $period === 'day' ? '%Y-%m-%d' : '%Y-%m';
                $interval = $period === 'day' ? '30 DAY' : '12 MONTH';

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT
                        DATE_FORMAT(cancellation_date, %s) as period,
                        COUNT(*) as cancelled,
                        (SELECT COUNT(*) FROM $subscriptions_table s2
                         WHERE DATE_FORMAT(s2.created_date, %s) = DATE_FORMAT(s.cancellation_date, %s)) as created
                     FROM $subscriptions_table s
                     WHERE cancellation_date >= DATE_SUB(NOW(), INTERVAL $interval)
                     AND cancellation_date IS NOT NULL
                     GROUP BY DATE_FORMAT(cancellation_date, %s)
                     ORDER BY period ASC",
                    $date_format,
                    $date_format,
                    $date_format,
                    $date_format
                ));

                foreach ($results as $result) {
                    $churn_rate = $result->created > 0 ? ($result->cancelled / $result->created) * 100 : 0;
                    $data[] = array(
                        'period' => $result->period,
                        'value' => round($churn_rate, 2),
                    );
                }
                break;
        }

        wp_send_json_success($data);
    }

    /**
     * Process subscription via AJAX (for complex scenarios)
     *
     * @since 1.0.0
     */
    public function process_subscription() {
        // This method can be called by external systems or complex workflows
        $this->verify_nonce('subs_process_nonce');

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $action = sanitize_text_field($_POST['action'] ?? '');

        if (!$subscription_id || !$action) {
            wp_send_json_error(__('Missing required parameters.', 'subs'));
        }

        $subscription_handler = new Subs_Subscription();

        switch ($action) {
            case 'renew':
                $result = $subscription_handler->process_renewal($subscription_id);
                break;

            case 'retry_payment':
                $subscription = $subscription_handler->get_subscription($subscription_id);
                if (!$subscription || empty($subscription->stripe_subscription_id)) {
                    wp_send_json_error(__('Invalid subscription or Stripe ID missing.', 'subs'));
                }

                $stripe = new Subs_Stripe();
                $result = $stripe->process_subscription_payment($subscription->stripe_subscription_id);
                break;

            default:
                wp_send_json_error(__('Invalid action.', 'subs'));
        }

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }
}
