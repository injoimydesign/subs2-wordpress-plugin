<?php
/**
 * Core Subscription Management Class
 *
 * Handles all subscription-related operations including creation,
 * updates, renewals, cancellations, and lifecycle management.
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
 * Subs Subscription Class
 *
 * @class Subs_Subscription
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Subscription {

    /**
     * Subscription statuses
     *
     * @var array
     * @since 1.0.0
     */
    public static $statuses = array(
        'active' => 'Active',
        'trialing' => 'Trialing',
        'past_due' => 'Past Due',
        'cancelled' => 'Cancelled',
        'unpaid' => 'Unpaid',
        'incomplete' => 'Incomplete',
        'incomplete_expired' => 'Incomplete Expired',
        'paused' => 'Paused',
    );

    /**
     * Billing periods
     *
     * @var array
     * @since 1.0.0
     */
    public static $billing_periods = array(
        'day' => 'Daily',
        'week' => 'Weekly',
        'month' => 'Monthly',
        'year' => 'Yearly',
    );

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Hook into WordPress actions
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the subscription system
     *
     * @since 1.0.0
     */
    public function init() {
        // Add custom actions
        add_action('subs_subscription_status_changed', array($this, 'handle_status_change'), 10, 3);
        add_action('subs_process_renewals', array($this, 'process_renewals'));
        add_action('subs_cleanup_expired', array($this, 'cleanup_expired'));
    }

    /**
     * Create a new subscription
     *
     * @param array $args Subscription arguments
     * @return int|WP_Error Subscription ID or error object
     * @since 1.0.0
     */
    public function create_subscription($args) {
        global $wpdb;

        // Validate required arguments
        $required_fields = array('customer_id', 'product_name', 'amount', 'billing_period');
        foreach ($required_fields as $field) {
            if (empty($args[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'subs'), $field));
            }
        }

        // Set defaults
        $defaults = array(
            'status' => 'active',
            'currency' => 'USD',
            'billing_interval' => 1,
            'trial_end' => null,
            'current_period_start' => current_time('mysql'),
            'current_period_end' => $this->calculate_next_payment_date(
                current_time('mysql'),
                $args['billing_period'],
                !empty($args['billing_interval']) ? $args['billing_interval'] : 1
            ),
            'next_payment_date' => null,
            'stripe_subscription_id' => null,
        );

        $subscription_data = wp_parse_args($args, $defaults);

        // Calculate next payment date
        if (empty($subscription_data['next_payment_date'])) {
            $subscription_data['next_payment_date'] = $subscription_data['current_period_end'];
        }

        // Sanitize data
        $subscription_data = $this->sanitize_subscription_data($subscription_data);

        // Insert subscription into database
        $table_name = $wpdb->prefix . 'subs_subscriptions';
        $result = $wpdb->insert($table_name, $subscription_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create subscription in database', 'subs'));
        }

        $subscription_id = $wpdb->insert_id;

        // Add initial history entry
        $this->add_history_entry($subscription_id, 'created', __('Subscription created', 'subs'));

        // Process trial period if applicable
        if (!empty($args['trial_end'])) {
            $this->add_history_entry($subscription_id, 'trial_started',
                sprintf(__('Trial period started until %s', 'subs'), $args['trial_end'])
            );
        }

        // Trigger action for other plugins to hook into
        do_action('subs_subscription_created', $subscription_id, $subscription_data);

        return $subscription_id;
    }

    /**
     * Get subscription by ID
     *
     * @param int $subscription_id
     * @return object|null
     * @since 1.0.0
     */
    public function get_subscription($subscription_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscriptions';
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $subscription_id
        ));

        if ($subscription) {
            // Add meta data
            $subscription->meta = $this->get_subscription_meta($subscription_id);

            // Add customer data
            $subscription->customer = $this->get_subscription_customer($subscription_id);
        }

        return $subscription;
    }

    /**
     * Get subscription by Stripe ID
     *
     * @param string $stripe_id
     * @return object|null
     * @since 1.0.0
     */
    public function get_subscription_by_stripe_id($stripe_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscriptions';
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE stripe_subscription_id = %s",
            $stripe_id
        ));

        if ($subscription) {
            // Add meta data
            $subscription->meta = $this->get_subscription_meta($subscription->id);

            // Add customer data
            $subscription->customer = $this->get_subscription_customer($subscription->id);
        }

        return $subscription;
    }

    /**
     * Update subscription
     *
     * @param int $subscription_id
     * @param array $data
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function update_subscription($subscription_id, $data) {
        global $wpdb;

        // Get current subscription
        $current = $this->get_subscription($subscription_id);
        if (!$current) {
            return new WP_Error('not_found', __('Subscription not found', 'subs'));
        }

        // Sanitize data
        $data = $this->sanitize_subscription_data($data);

        // Update database
        $table_name = $wpdb->prefix . 'subs_subscriptions';
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $subscription_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update subscription', 'subs'));
        }

        // Check for status change
        if (isset($data['status']) && $data['status'] !== $current->status) {
            $this->handle_status_change($subscription_id, $data['status'], $current->status);
        }

        // Add history entry
        $this->add_history_entry($subscription_id, 'updated', __('Subscription updated', 'subs'));

        // Trigger action
        do_action('subs_subscription_updated', $subscription_id, $data, $current);

        return true;
    }

    /**
     * Cancel subscription
     *
     * @param int $subscription_id
     * @param string $reason
     * @param bool $immediate Whether to cancel immediately or at period end
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function cancel_subscription($subscription_id, $reason = '', $immediate = false) {
        $subscription = $this->get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found', 'subs'));
        }

        // Prepare update data
        $update_data = array(
            'status' => 'cancelled',
            'cancellation_date' => current_time('mysql'),
        );

        if (!empty($reason)) {
            $update_data['cancellation_reason'] = $reason;
        }

        // If not immediate, set cancellation for period end
        if (!$immediate && !empty($subscription->current_period_end)) {
            $update_data['cancellation_date'] = $subscription->current_period_end;
            $update_data['status'] = 'active'; // Keep active until period end

            // Set meta to indicate pending cancellation
            $this->update_subscription_meta($subscription_id, '_pending_cancellation', 'yes');
            $this->update_subscription_meta($subscription_id, '_cancellation_reason', $reason);
        }

        // Update subscription
        $result = $this->update_subscription($subscription_id, $update_data);
        if (is_wp_error($result)) {
            return $result;
        }

        // Add history entry
        $history_note = $immediate ?
            __('Subscription cancelled immediately', 'subs') :
            sprintf(__('Subscription set to cancel at period end (%s)', 'subs'), $subscription->current_period_end);

        if (!empty($reason)) {
            $history_note .= '. ' . sprintf(__('Reason: %s', 'subs'), $reason);
        }

        $this->add_history_entry($subscription_id, 'cancelled', $history_note);

        // Cancel with Stripe if applicable
        if (!empty($subscription->stripe_subscription_id)) {
            $stripe = new Subs_Stripe();
            $stripe_result = $stripe->cancel_subscription($subscription->stripe_subscription_id, $immediate);

            if (is_wp_error($stripe_result)) {
                // Log the error but don't fail the cancellation
                error_log('Stripe cancellation failed: ' . $stripe_result->get_error_message());
            }
        }

        // Trigger action
        do_action('subs_subscription_cancelled', $subscription_id, $reason, $immediate);

        return true;
    }

    /**
     * Pause subscription
     *
     * @param int $subscription_id
     * @param string $reason
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function pause_subscription($subscription_id, $reason = '') {
        $subscription = $this->get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found', 'subs'));
        }

        // Can only pause active subscriptions
        if (!in_array($subscription->status, array('active', 'trialing'))) {
            return new WP_Error('invalid_status', __('Can only pause active subscriptions', 'subs'));
        }

        // Update status
        $result = $this->update_subscription($subscription_id, array(
            'status' => 'paused'
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        // Store previous status and pause reason
        $this->update_subscription_meta($subscription_id, '_paused_from_status', $subscription->status);
        if (!empty($reason)) {
            $this->update_subscription_meta($subscription_id, '_pause_reason', $reason);
        }

        // Add history entry
        $history_note = __('Subscription paused', 'subs');
        if (!empty($reason)) {
            $history_note .= '. ' . sprintf(__('Reason: %s', 'subs'), $reason);
        }

        $this->add_history_entry($subscription_id, 'paused', $history_note);

        // Pause with Stripe if applicable
        if (!empty($subscription->stripe_subscription_id)) {
            $stripe = new Subs_Stripe();
            $stripe_result = $stripe->pause_subscription($subscription->stripe_subscription_id);

            if (is_wp_error($stripe_result)) {
                error_log('Stripe pause failed: ' . $stripe_result->get_error_message());
            }
        }

        // Trigger action
        do_action('subs_subscription_paused', $subscription_id, $reason);

        return true;
    }

    /**
     * Resume paused subscription
     *
     * @param int $subscription_id
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function resume_subscription($subscription_id) {
        $subscription = $this->get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found', 'subs'));
        }

        // Can only resume paused subscriptions
        if ($subscription->status !== 'paused') {
            return new WP_Error('invalid_status', __('Can only resume paused subscriptions', 'subs'));
        }

        // Get previous status
        $previous_status = $this->get_subscription_meta($subscription_id, '_paused_from_status', true);
        $new_status = !empty($previous_status) ? $previous_status : 'active';

        // Update status
        $result = $this->update_subscription($subscription_id, array(
            'status' => $new_status
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        // Clean up pause meta
        $this->delete_subscription_meta($subscription_id, '_paused_from_status');
        $this->delete_subscription_meta($subscription_id, '_pause_reason');

        // Add history entry
        $this->add_history_entry($subscription_id, 'resumed', __('Subscription resumed', 'subs'));

        // Resume with Stripe if applicable
        if (!empty($subscription->stripe_subscription_id)) {
            $stripe = new Subs_Stripe();
            $stripe_result = $stripe->resume_subscription($subscription->stripe_subscription_id);

            if (is_wp_error($stripe_result)) {
                error_log('Stripe resume failed: ' . $stripe_result->get_error_message());
            }
        }

        // Trigger action
        do_action('subs_subscription_resumed', $subscription_id);

        return true;
    }

    /**
     * Process subscription renewals
     *
     * Called by cron job to process due renewals.
     *
     * @since 1.0.0
     */
    public function process_renewals() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscriptions';

        // Get subscriptions due for renewal
        $due_subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE status IN ('active', 'trialing')
             AND next_payment_date <= %s
             AND next_payment_date IS NOT NULL
             ORDER BY next_payment_date ASC
             LIMIT 50", // Process in batches
            current_time('mysql')
        ));

        $processed = 0;
        $errors = 0;

        foreach ($due_subscriptions as $subscription) {
            $result = $this->process_renewal($subscription->id);

            if (is_wp_error($result)) {
                $errors++;
                error_log("Failed to process renewal for subscription {$subscription->id}: " . $result->get_error_message());
            } else {
                $processed++;
            }

            // Add small delay to prevent overwhelming the payment processor
            usleep(100000); // 0.1 seconds
        }

        // Log processing results
        if ($processed > 0 || $errors > 0) {
            error_log("Subs: Processed {$processed} renewals, {$errors} errors");
        }

        // Trigger action
        do_action('subs_renewals_processed', $processed, $errors);
    }

    /**
     * Process a single subscription renewal
     *
     * @param int $subscription_id
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function process_renewal($subscription_id) {
        $subscription = $this->get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found', 'subs'));
        }

        // Check if subscription is eligible for renewal
        if (!in_array($subscription->status, array('active', 'trialing'))) {
            return new WP_Error('invalid_status', __('Subscription not eligible for renewal', 'subs'));
        }

        // Process payment through Stripe
        if (!empty($subscription->stripe_subscription_id)) {
            $stripe = new Subs_Stripe();
            $payment_result = $stripe->process_subscription_payment($subscription->stripe_subscription_id);

            if (is_wp_error($payment_result)) {
                // Handle payment failure
                $this->handle_payment_failure($subscription_id, $payment_result->get_error_message());
                return $payment_result;
            }

            // Log successful payment
            $this->log_payment($subscription_id, $payment_result);
        }

        // Update subscription periods
        $next_period_start = $subscription->current_period_end;
        $next_period_end = $this->calculate_next_payment_date(
            $next_period_start,
            $subscription->billing_period,
            $subscription->billing_interval
        );

        $update_data = array(
            'current_period_start' => $next_period_start,
            'current_period_end' => $next_period_end,
            'next_payment_date' => $next_period_end,
        );

        // If subscription was trialing, change status to active
        if ($subscription->status === 'trialing') {
            $update_data['status'] = 'active';
        }

        // Update subscription
        $result = $this->update_subscription($subscription_id, $update_data);
        if (is_wp_error($result)) {
            return $result;
        }

        // Add history entry
        $this->add_history_entry($subscription_id, 'renewed',
            sprintf(__('Subscription renewed for period %s to %s', 'subs'),
                $next_period_start, $next_period_end)
        );

        // Trigger action
        do_action('subs_subscription_renewed', $subscription_id, $subscription);

        return true;
    }

    /**
     * Handle payment failure
     *
     * @param int $subscription_id
     * @param string $error_message
     * @since 1.0.0
     */
    public function handle_payment_failure($subscription_id, $error_message) {
        $subscription = $this->get_subscription($subscription_id);
        if (!$subscription) {
            return;
        }

        // Update subscription status
        $this->update_subscription($subscription_id, array(
            'status' => 'past_due'
        ));

        // Add history entry
        $this->add_history_entry($subscription_id, 'payment_failed',
            sprintf(__('Payment failed: %s', 'subs'), $error_message)
        );

        // Increment failure count
        $failure_count = (int) $this->get_subscription_meta($subscription_id, '_payment_failure_count', true);
        $failure_count++;
        $this->update_subscription_meta($subscription_id, '_payment_failure_count', $failure_count);

        // Schedule retry or cancel if too many failures
        $max_failures = apply_filters('subs_max_payment_failures', 3);
        if ($failure_count >= $max_failures) {
            $this->cancel_subscription($subscription_id,
                sprintf(__('Cancelled due to %d consecutive payment failures', 'subs'), $failure_count),
                true
            );
        } else {
            // Schedule retry
            $retry_date = date('Y-m-d H:i:s', strtotime('+3 days'));
            $this->update_subscription($subscription_id, array(
                'next_payment_date' => $retry_date
            ));
        }

        // Send notification email
        do_action('subs_payment_failed', $subscription_id, $error_message, $failure_count);
    }

    /**
     * Log payment
     *
     * @param int $subscription_id
     * @param array $payment_data
     * @since 1.0.0
     */
    public function log_payment($subscription_id, $payment_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_payment_logs';

        $log_data = array(
            'subscription_id' => $subscription_id,
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'status' => $payment_data['status'],
            'payment_method' => $payment_data['payment_method'] ?? 'stripe',
            'stripe_payment_intent_id' => $payment_data['payment_intent_id'] ?? null,
            'stripe_invoice_id' => $payment_data['invoice_id'] ?? null,
        );

        $wpdb->insert($table_name, $log_data);

        // Reset failure count on successful payment
        if ($payment_data['status'] === 'succeeded') {
            $this->delete_subscription_meta($subscription_id, '_payment_failure_count');
        }
    }

    /**
     * Cleanup expired subscriptions
     *
     * Called by weekly cron job to clean up old data.
     *
     * @since 1.0.0
     */
    public function cleanup_expired() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscriptions';

        // Delete subscriptions cancelled more than 1 year ago
        $one_year_ago = date('Y-m-d H:i:s', strtotime('-1 year'));
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name
             WHERE status = 'cancelled'
             AND cancellation_date < %s",
            $one_year_ago
        ));

        // Clean up orphaned meta data
        $meta_table = $wpdb->prefix . 'subs_subscription_meta';
        $wpdb->query("
            DELETE sm FROM $meta_table sm
            LEFT JOIN $table_name s ON s.id = sm.subscription_id
            WHERE s.id IS NULL
        ");

        // Clean up old payment logs (keep 2 years)
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';
        $two_years_ago = date('Y-m-d H:i:s', strtotime('-2 years'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $payment_logs_table WHERE processed_date < %s",
            $two_years_ago
        ));

        // Log cleanup
        if ($deleted > 0) {
            error_log("Subs: Cleaned up {$deleted} expired subscriptions");
        }

        // Trigger action
        do_action('subs_cleanup_completed', $deleted);
    }

    /**
     * Calculate next payment date
     *
     * @param string $start_date
     * @param string $period
     * @param int $interval
     * @return string
     * @since 1.0.0
     */
    public function calculate_next_payment_date($start_date, $period, $interval = 1) {
        $date = new DateTime($start_date);

        switch ($period) {
            case 'day':
                $date->add(new DateInterval('P' . $interval . 'D'));
                break;
            case 'week':
                $date->add(new DateInterval('P' . ($interval * 7) . 'D'));
                break;
            case 'month':
                $date->add(new DateInterval('P' . $interval . 'M'));
                break;
            case 'year':
                $date->add(new DateInterval('P' . $interval . 'Y'));
                break;
            default:
                // Default to monthly
                $date->add(new DateInterval('P1M'));
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Handle subscription status change
     *
     * @param int $subscription_id
     * @param string $new_status
     * @param string $old_status
     * @since 1.0.0
     */
    public function handle_status_change($subscription_id, $new_status, $old_status) {
        if ($new_status === $old_status) {
            return;
        }

        // Add history entry
        $this->add_history_entry($subscription_id, 'status_changed',
            sprintf(__('Status changed from %s to %s', 'subs'), $old_status, $new_status)
        );

        // Send notification email
        do_action('subs_subscription_status_changed', $subscription_id, $new_status, $old_status);
    }

    /**
     * Add subscription history entry
     *
     * @param int $subscription_id
     * @param string $action
     * @param string $note
     * @param int $user_id
     * @since 1.0.0
     */
    public function add_history_entry($subscription_id, $action, $note = '', $user_id = null) {
        global $wpdb;

        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $table_name = $wpdb->prefix . 'subs_subscription_history';

        $wpdb->insert($table_name, array(
            'subscription_id' => $subscription_id,
            'action' => $action,
            'note' => $note,
            'user_id' => $user_id > 0 ? $user_id : null,
        ));
    }

    /**
     * Get subscription history
     *
     * @param int $subscription_id
     * @param int $limit
     * @return array
     * @since 1.0.0
     */
    public function get_subscription_history($subscription_id, $limit = 50) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscription_history';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, u.display_name as user_display_name
             FROM $table_name h
             LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
             WHERE h.subscription_id = %d
             ORDER BY h.created_date DESC
             LIMIT %d",
            $subscription_id,
            $limit
        ));
    }

    /**
     * Get subscription meta
     *
     * @param int $subscription_id
     * @param string $key
     * @param bool $single
     * @return mixed
     * @since 1.0.0
     */
    public function get_subscription_meta($subscription_id, $key = '', $single = false) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscription_meta';

        if (empty($key)) {
            // Get all meta
            $meta = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM $table_name WHERE subscription_id = %d",
                $subscription_id
            ));

            $result = array();
            foreach ($meta as $item) {
                $result[$item->meta_key] = maybe_unserialize($item->meta_value);
            }
            return $result;
        }

        // Get specific meta
        $meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_value FROM $table_name WHERE subscription_id = %d AND meta_key = %s",
            $subscription_id,
            $key
        ));

        if (empty($meta)) {
            return $single ? '' : array();
        }

        $values = array_map(function($item) {
            return maybe_unserialize($item->meta_value);
        }, $meta);

        return $single ? $values[0] : $values;
    }

    /**
     * Update subscription meta
     *
     * @param int $subscription_id
     * @param string $key
     * @param mixed $value
     * @return bool
     * @since 1.0.0
     */
    public function update_subscription_meta($subscription_id, $key, $value) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscription_meta';
        $value = maybe_serialize($value);

        // Check if meta exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM $table_name WHERE subscription_id = %d AND meta_key = %s",
            $subscription_id,
            $key
        ));

        if ($existing) {
            // Update existing
            return $wpdb->update(
                $table_name,
                array('meta_value' => $value),
                array('subscription_id' => $subscription_id, 'meta_key' => $key),
                array('%s'),
                array('%d', '%s')
            ) !== false;
        } else {
            // Insert new
            return $wpdb->insert(
                $table_name,
                array(
                    'subscription_id' => $subscription_id,
                    'meta_key' => $key,
                    'meta_value' => $value
                )
            ) !== false;
        }
    }

    /**
     * Delete subscription meta
     *
     * @param int $subscription_id
     * @param string $key
     * @return bool
     * @since 1.0.0
     */
    public function delete_subscription_meta($subscription_id, $key) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscription_meta';

        return $wpdb->delete(
            $table_name,
            array(
                'subscription_id' => $subscription_id,
                'meta_key' => $key
            ),
            array('%d', '%s')
        ) !== false;
    }

    /**
     * Get subscription customer data
     *
     * @param int $subscription_id
     * @return object|null
     * @since 1.0.0
     */
    public function get_subscription_customer($subscription_id) {
        global $wpdb;

        $subscription_table = $wpdb->prefix . 'subs_subscriptions';
        $customer_table = $wpdb->prefix . 'subs_customers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.* FROM $customer_table c
             INNER JOIN $subscription_table s ON s.customer_id = c.id
             WHERE s.id = %d",
            $subscription_id
        ));
    }

    /**
     * Get subscriptions by customer
     *
     * @param int $customer_id
     * @param array $args
     * @return array
     * @since 1.0.0
     */
    public function get_subscriptions_by_customer($customer_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => '', // Empty for all statuses
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_date',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $table_name = $wpdb->prefix . 'subs_subscriptions';
        $where = "WHERE customer_id = %d";
        $query_args = array($customer_id);

        if (!empty($args['status'])) {
            $where .= " AND status = %s";
            $query_args[] = $args['status'];
        }

        $order = sprintf("ORDER BY %s %s",
            sanitize_sql_orderby($args['orderby']),
            strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC'
        );

        $limit = '';
        if ($args['limit'] > 0) {
            $limit = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        $query = "SELECT * FROM $table_name $where $order $limit";

        return $wpdb->get_results($wpdb->prepare($query, ...$query_args));
    }

    /**
     * Sanitize subscription data
     *
     * @param array $data
     * @return array
     * @since 1.0.0
     */
    private function sanitize_subscription_data($data) {
        $sanitized = array();

        // Numeric fields
        $numeric_fields = array('customer_id', 'amount', 'billing_interval');
        foreach ($numeric_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = is_numeric($data[$field]) ? $data[$field] : 0;
            }
        }

        // Text fields
        $text_fields = array('product_name', 'currency', 'billing_period', 'status',
                            'stripe_subscription_id', 'cancellation_reason');
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }

        // Date fields
        $date_fields = array('trial_end', 'current_period_start', 'current_period_end',
                            'next_payment_date', 'cancellation_date');
        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = empty($data[$field]) ? null :
                                   date('Y-m-d H:i:s', strtotime($data[$field]));
            }
        }

        return $sanitized;
    }

    /**
     * Get subscription statuses
     *
     * @return array
     * @since 1.0.0
     */
    public static function get_statuses() {
        return apply_filters('subs_subscription_statuses', self::$statuses);
    }

    /**
     * Get billing periods
     *
     * @return array
     * @since 1.0.0
     */
    public static function get_billing_periods() {
        return apply_filters('subs_billing_periods', self::$billing_periods);
    }

    /**
     * Check if subscription can be cancelled
     *
     * @param object $subscription
     * @return bool
     * @since 1.0.0
     */
    public function can_cancel($subscription) {
        $allowed_statuses = array('active', 'trialing', 'past_due');
        return in_array($subscription->status, $allowed_statuses);
    }

    /**
     * Check if subscription can be paused
     *
     * @param object $subscription
     * @return bool
     * @since 1.0.0
     */
    public function can_pause($subscription) {
        $allowed_statuses = array('active', 'trialing');
        return in_array($subscription->status, $allowed_statuses);
    }

    /**
     * Check if subscription can be resumed
     *
     * @param object $subscription
     * @return bool
     * @since 1.0.0
     */
    public function can_resume($subscription) {
        return $subscription->status === 'paused';
    }
}
