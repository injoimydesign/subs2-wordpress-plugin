<?php
/**
 * Subs Helper Functions
 *
 * Global helper functions used throughout the Subs plugin.
 * These functions provide convenient access to common functionality
 * and data retrieval operations.
 *
 * @package Subs
 * @since 1.0.0
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the main Subs instance
 *
 * @return Subs
 * @since 1.0.0
 */
function subs() {
    return Subs::instance();
}

// =============================================================================
// SUBSCRIPTION FUNCTIONS
// =============================================================================

/**
 * Get subscription by ID
 *
 * @param int $subscription_id
 * @return object|null
 * @since 1.0.0
 */
function subs_get_subscription($subscription_id) {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $subscriptions_table WHERE id = %d",
        $subscription_id
    ));
}

/**
 * Get subscriptions by customer ID
 *
 * @param int $customer_id
 * @param string $status Optional status filter
 * @return array
 * @since 1.0.0
 */
function subs_get_customer_subscriptions($customer_id, $status = '') {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    $query = "SELECT * FROM $subscriptions_table WHERE customer_id = %d";
    $args = array($customer_id);

    if (!empty($status)) {
        $query .= " AND status = %s";
        $args[] = $status;
    }

    $query .= " ORDER BY created_date DESC";

    return $wpdb->get_results($wpdb->prepare($query, $args));
}

/**
 * Get subscription status label
 *
 * @param string $status
 * @return string
 * @since 1.0.0
 */
function subs_get_subscription_status_label($status) {
    $statuses = array(
        'active' => __('Active', 'subs'),
        'trialing' => __('Trialing', 'subs'),
        'past_due' => __('Past Due', 'subs'),
        'cancelled' => __('Cancelled', 'subs'),
        'unpaid' => __('Unpaid', 'subs'),
        'incomplete' => __('Incomplete', 'subs'),
        'incomplete_expired' => __('Incomplete Expired', 'subs'),
        'paused' => __('Paused', 'subs'),
    );

    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Check if subscription is active
 *
 * @param int $subscription_id
 * @return bool
 * @since 1.0.0
 */
function subs_is_subscription_active($subscription_id) {
    $subscription = subs_get_subscription($subscription_id);

    if (!$subscription) {
        return false;
    }

    return in_array($subscription->status, array('active', 'trialing'));
}

/**
 * Cancel subscription
 *
 * @param int $subscription_id
 * @param string $reason Optional cancellation reason
 * @return bool
 * @since 1.0.0
 */
function subs_cancel_subscription($subscription_id, $reason = '') {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    $result = $wpdb->update(
        $subscriptions_table,
        array(
            'status' => 'cancelled',
            'cancelled_date' => current_time('mysql'),
            'updated_date' => current_time('mysql'),
        ),
        array('id' => $subscription_id),
        array('%s', '%s', '%s'),
        array('%d')
    );

    if ($result !== false) {
        // Log cancellation
        subs_log_subscription_activity($subscription_id, 'cancelled', array(
            'reason' => $reason
        ));

        do_action('subs_subscription_cancelled', $subscription_id, $reason);

        return true;
    }

    return false;
}

/**
 * Pause subscription
 *
 * @param int $subscription_id
 * @return bool
 * @since 1.0.0
 */
function subs_pause_subscription($subscription_id) {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    $result = $wpdb->update(
        $subscriptions_table,
        array(
            'status' => 'paused',
            'updated_date' => current_time('mysql'),
        ),
        array('id' => $subscription_id),
        array('%s', '%s'),
        array('%d')
    );

    if ($result !== false) {
        subs_log_subscription_activity($subscription_id, 'paused');
        do_action('subs_subscription_paused', $subscription_id);
        return true;
    }

    return false;
}

/**
 * Resume subscription
 *
 * @param int $subscription_id
 * @return bool
 * @since 1.0.0
 */
function subs_resume_subscription($subscription_id) {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    $result = $wpdb->update(
        $subscriptions_table,
        array(
            'status' => 'active',
            'updated_date' => current_time('mysql'),
        ),
        array('id' => $subscription_id),
        array('%s', '%s'),
        array('%d')
    );

    if ($result !== false) {
        subs_log_subscription_activity($subscription_id, 'resumed');
        do_action('subs_subscription_resumed', $subscription_id);
        return true;
    }

    return false;
}

/**
 * Log subscription activity
 *
 * @param int $subscription_id
 * @param string $action
 * @param array $data
 * @return bool
 * @since 1.0.0
 */
function subs_log_subscription_activity($subscription_id, $action, $data = array()) {
    global $wpdb;

    $history_table = $wpdb->prefix . 'subs_subscription_history';

    $result = $wpdb->insert(
        $history_table,
        array(
            'subscription_id' => $subscription_id,
            'action' => $action,
            'data' => json_encode($data),
            'user_id' => get_current_user_id(),
            'created_date' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%d', '%s')
    );

    return $result !== false;
}

/**
 * Get subscription history
 *
 * @param int $subscription_id
 * @param int $limit
 * @return array
 * @since 1.0.0
 */
function subs_get_subscription_history($subscription_id, $limit = 50) {
    global $wpdb;

    $history_table = $wpdb->prefix . 'subs_subscription_history';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $history_table WHERE subscription_id = %d ORDER BY created_date DESC LIMIT %d",
        $subscription_id,
        $limit
    ));
}

// =============================================================================
// CUSTOMER FUNCTIONS
// =============================================================================

/**
 * Get customer by ID
 *
 * @param int $customer_id
 * @return object|null
 * @since 1.0.0
 */
function subs_get_customer($customer_id) {
    global $wpdb;

    $customers_table = $wpdb->prefix . 'subs_customers';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE id = %d",
        $customer_id
    ));
}

/**
 * Get customer by email
 *
 * @param string $email
 * @return object|null
 * @since 1.0.0
 */
function subs_get_customer_by_email($email) {
    global $wpdb;

    $customers_table = $wpdb->prefix . 'subs_customers';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE email = %s",
        $email
    ));
}

/**
 * Get customer by user ID
 *
 * @param int $user_id
 * @return object|null
 * @since 1.0.0
 */
function subs_get_customer_by_user_id($user_id) {
    global $wpdb;

    $customers_table = $wpdb->prefix . 'subs_customers';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE user_id = %d",
        $user_id
    ));
}

/**
 * Get current customer
 *
 * @return object|null
 * @since 1.0.0
 */
function subs_get_current_customer() {
    if (!is_user_logged_in()) {
        return null;
    }

    return subs_get_customer_by_user_id(get_current_user_id());
}

/**
 * Check if customer has active subscriptions
 *
 * @param int $customer_id
 * @return bool
 * @since 1.0.0
 */
function subs_customer_has_active_subscriptions($customer_id) {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $subscriptions_table WHERE customer_id = %d AND status IN ('active', 'trialing')",
        $customer_id
    ));

    return $count > 0;
}

/**
 * Get customer total spent
 *
 * @param int $customer_id
 * @return float
 * @since 1.0.0
 */
function subs_get_customer_total_spent($customer_id) {
    global $wpdb;

    $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';
    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(pl.amount)
         FROM $payment_logs_table pl
         JOIN $subscriptions_table s ON pl.subscription_id = s.id
         WHERE s.customer_id = %d AND pl.status = 'succeeded'",
        $customer_id
    ));

    return floatval($total);
}

/**
 * Get customer subscription count
 *
 * @param int $customer_id
 * @param string $status Optional status filter
 * @return int
 * @since 1.0.0
 */
function subs_get_customer_subscription_count($customer_id, $status = '') {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    $query = "SELECT COUNT(*) FROM $subscriptions_table WHERE customer_id = %d";
    $args = array($customer_id);

    if (!empty($status)) {
        $query .= " AND status = %s";
        $args[] = $status;
    }

    return intval($wpdb->get_var($wpdb->prepare($query, $args)));
}

// =============================================================================
// PAYMENT FUNCTIONS
// =============================================================================

/**
 * Get payment by ID
 *
 * @param int $payment_id
 * @return object|null
 * @since 1.0.0
 */
function subs_get_payment($payment_id) {
    global $wpdb;

    $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $payment_logs_table WHERE id = %d",
        $payment_id
    ));
}

/**
 * Get subscription payments
 *
 * @param int $subscription_id
 * @param int $limit
 * @return array
 * @since 1.0.0
 */
function subs_get_subscription_payments($subscription_id, $limit = 50) {
    global $wpdb;

    $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $payment_logs_table WHERE subscription_id = %d ORDER BY processed_date DESC LIMIT %d",
        $subscription_id,
        $limit
    ));
}

/**
 * Log payment
 *
 * @param array $payment_data
 * @return int|false Payment ID on success, false on failure
 * @since 1.0.0
 */
function subs_log_payment($payment_data) {
    global $wpdb;

    $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

    $defaults = array(
        'subscription_id' => 0,
        'amount' => 0.00,
        'currency' => 'USD',
        'status' => 'pending',
        'stripe_payment_intent_id' => '',
        'stripe_invoice_id' => '',
        'error_message' => '',
        'processed_date' => current_time('mysql'),
    );

    $payment_data = wp_parse_args($payment_data, $defaults);

    $result = $wpdb->insert($payment_logs_table, $payment_data);

    if ($result !== false) {
        return $wpdb->insert_id;
    }

    return false;
}

/**
 * Update payment status
 *
 * @param int $payment_id
 * @param string $status
 * @param string $error_message
 * @return bool
 * @since 1.0.0
 */
function subs_update_payment_status($payment_id, $status, $error_message = '') {
    global $wpdb;

    $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

    $update_data = array(
        'status' => $status,
        'processed_date' => current_time('mysql'),
    );

    if (!empty($error_message)) {
        $update_data['error_message'] = $error_message;
    }

    $result = $wpdb->update(
        $payment_logs_table,
        $update_data,
        array('id' => $payment_id),
        array('%s', '%s', '%s'),
        array('%d')
    );

    return $result !== false;
}

// =============================================================================
// FORMATTING FUNCTIONS
// =============================================================================

/**
 * Format price with currency
 *
 * @param float $amount
 * @param string $currency
 * @return string
 * @since 1.0.0
 */
function subs_format_price($amount, $currency = '') {
    $settings = get_option('subs_general_settings', array());

    if (empty($currency)) {
        $currency = isset($settings['currency']) ? $settings['currency'] : 'USD';
    }

    $decimals = isset($settings['number_of_decimals']) ? intval($settings['number_of_decimals']) : 2;
    $decimal_sep = isset($settings['decimal_separator']) ? $settings['decimal_separator'] : '.';
    $thousand_sep = isset($settings['thousand_separator']) ? $settings['thousand_separator'] : ',';
    $position = isset($settings['currency_position']) ? $settings['currency_position'] : 'left';

    $formatted_amount = number_format($amount, $decimals, $decimal_sep, $thousand_sep);
    $currency_symbol = subs_get_currency_symbol($currency);

    switch ($position) {
        case 'right':
            return $formatted_amount . $currency_symbol;
        case 'left_space':
            return $currency_symbol . ' ' . $formatted_amount;
        case 'right_space':
            return $formatted_amount . ' ' . $currency_symbol;
        default: // left
            return $currency_symbol . $formatted_amount;
    }
}

/**
 * Get currency symbol
 *
 * @param string $currency
 * @return string
 * @since 1.0.0
 */
function subs_get_currency_symbol($currency = '') {
    if (empty($currency)) {
        $settings = get_option('subs_general_settings', array());
        $currency = isset($settings['currency']) ? $settings['currency'] : 'USD';
    }

    $symbols = array(
        'AED' => 'د.إ',
        'AFN' => '؋',
        'ALL' => 'L',
        'AMD' => 'AMD',
        'ANG' => 'ƒ',
        'AOA' => 'Kz',
        'ARS' => '$',
        'AUD' => 'A$',
        'AWG' => 'Afl.',
        'AZN' => 'AZN',
        'BAM' => 'KM',
        'BBD' => 'Bds$',
        'BDT' => '৳',
        'BGN' => 'лв.',
        'BHD' => '.د.ب',
        'BIF' => 'FBu',
        'BMD' => 'BD$',
        'BND' => 'B$',
        'BOB' => 'Bs.',
        'BRL' => 'R$',
        'BSD' => 'B$',
        'BTC' => '฿',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYR' => 'Br',
        'BZD' => 'BZ$',
        'CAD' => 'C$',
        'CDF' => 'FC',
        'CHF' => 'CHF',
        'CLP' => '$',
        'CNY' => '¥',
        'COP' => '$',
        'CRC' => '₡',
        'CUC' => 'CUC',
        'CUP' => '$MN',
        'CVE' => '$',
        'CZK' => 'Kč',
        'DJF' => 'Fdj',
        'DKK' => 'kr.',
        'DOP' => 'RD$',
        'DZD' => 'د.ج',
        'EGP' => 'E£',
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => '€',
        'FJD' => 'FJ$',
        'FKP' => '£',
        'GBP' => '£',
        'GEL' => '₾',
        'GGP' => '£',
        'GHS' => '₵',
        'GIP' => '£',
        'GMD' => 'D',
        'GNF' => 'FG',
        'GTQ' => 'Q',
        'GYD' => 'G$',
        'HKD' => 'HK$',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => 'Ft',
        'IDR' => 'Rp',
        'ILS' => '₪',
        'IMP' => '£',
        'INR' => '₹',
        'IQD' => 'ع.د',
        'IRR' => '﷼',
        'ISK' => 'kr.',
        'JEP' => '£',
        'JMD' => 'J$',
        'JOD' => 'د.ا',
        'JPY' => '¥',
        'KES' => 'KSh',
        'KGS' => 'сом',
        'KHR' => '៛',
        'KMF' => 'CF',
        'KPW' => '₩',
        'KRW' => '₩',
        'KWD' => 'د.ك',
        'KYD' => 'CI$',
        'KZT' => '₸',
        'LAK' => '₭',
        'LBP' => 'ل.ل',
        'LKR' => 'Rs',
        'LRD' => 'L$',
        'LSL' => 'L',
        'LYD' => 'ل.د',
        'MAD' => 'د.م.',
        'MDL' => 'L',
        'MGA' => 'Ar',
        'MKD' => 'ден',
        'MMK' => 'K',
        'MNT' => '₮',
        'MOP' => 'P',
        'MRO' => 'UM',
        'MUR' => '₨',
        'MVR' => '.ރ',
        'MWK' => 'MK',
        'MXN' => 'Mex$',
        'MYR' => 'RM',
        'MZN' => 'MT',
        'NAD' => 'N$',
        'NGN' => '₦',
        'NIO' => 'C$',
        'NOK' => 'kr',
        'NPR' => '₨',
        'NZD' => 'NZ$',
        'OMR' => 'ر.ع.',
        'PAB' => 'B/.',
        'PEN' => 'S/.',
        'PGK' => 'K',
        'PHP' => '₱',
        'PKR' => '₨',
        'PLN' => 'zł',
        'PRB' => 'р.',
        'PYG' => '₲',
        'QAR' => 'ر.ق',
        'RMB' => '¥',
        'RON' => 'lei',
        'RSD' => 'дин.',
        'RUB' => '₽',
        'RWF' => 'FRw',
        'SAR' => 'ر.س',
        'SBD' => 'SI$',
        'SCR' => '₨',
        'SDG' => 'ج.س.',
        'SEK' => 'kr',
        'SGD' => 'S$',
        'SHP' => '£',
        'SLL' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '$',
        'SSP' => '£',
        'STD' => 'Db',
        'SYP' => 'ل.س',
        'SZL' => 'L',
        'THB' => '฿',
        'TJS' => 'ЅМ',
        'TMT' => 'm',
        'TND' => 'د.ت',
        'TOP' => 'T$',
        'TRY' => '₺',
        'TTD' => 'TT$',
        'TWD' => 'NT$',
        'TZS' => 'TSh',
        'UAH' => '₴',
        'UGX' => 'USh',
        'USD' => '$',
        'UYU' => '$U',
        'UZS' => 'UZS',
        'VEF' => 'Bs F',
        'VND' => '₫',
        'VUV' => 'VT',
        'WST' => 'T',
        'XAF' => 'FCFA',
        'XCD' => 'EC$',
        'XOF' => 'CFA',
        'XPF' => '₣',
        'YER' => '﷼',
        'ZAR' => 'R',
        'ZMW' => 'ZK',
    );

    return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
}

/**
 * Format interval for display
 *
 * @param string $interval
 * @param int $count
 * @return string
 * @since 1.0.0
 */
function subs_format_interval($interval, $count = 1) {
    if ($count == 1) {
        switch ($interval) {
            case 'day':
                return __('day', 'subs');
            case 'week':
                return __('week', 'subs');
            case 'month':
                return __('month', 'subs');
            case 'year':
                return __('year', 'subs');
        }
    }

    $units = array(
        'day' => _n('day', 'days', $count, 'subs'),
        'week' => _n('week', 'weeks', $count, 'subs'),
        'month' => _n('month', 'months', $count, 'subs'),
        'year' => _n('year', 'years', $count, 'subs'),
    );

    $unit_label = isset($units[$interval]) ? $units[$interval] : $interval;

    return sprintf(__('%d %s', 'subs'), $count, $unit_label);
}

/**
 * Format date for display
 *
 * @param string $date
 * @param string $format Optional format string
 * @return string
 * @since 1.0.0
 */
function subs_format_date($date, $format = '') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return __('N/A', 'subs');
    }

    if (empty($format)) {
        $format = get_option('date_format');
    }

    return date_i18n($format, strtotime($date));
}

/**
 * Format date and time for display
 *
 * @param string $datetime
 * @param string $format Optional format string
 * @return string
 * @since 1.0.0
 */
function subs_format_datetime($datetime, $format = '') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return __('N/A', 'subs');
    }

    if (empty($format)) {
        $format = get_option('date_format') . ' ' . get_option('time_format');
    }

    return date_i18n($format, strtotime($datetime));
}

// =============================================================================
// SETTINGS FUNCTIONS
// =============================================================================

/**
 * Get plugin setting
 *
 * @param string $group Setting group (general, stripe, email, advanced)
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed
 * @since 1.0.0
 */
function subs_get_setting($group, $key, $default = '') {
    $settings = get_option('subs_' . $group . '_settings', array());
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Update plugin setting
 *
 * @param string $group Setting group
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool
 * @since 1.0.0
 */
function subs_update_setting($group, $key, $value) {
    $settings = get_option('subs_' . $group . '_settings', array());
    $settings[$key] = $value;
    return update_option('subs_' . $group . '_settings', $settings);
}

/**
 * Check if Stripe is enabled
 *
 * @return bool
 * @since 1.0.0
 */
function subs_is_stripe_enabled() {
    return subs_get_setting('stripe', 'enabled') === 'yes';
}

/**
 * Check if Stripe test mode is enabled
 *
 * @return bool
 * @since 1.0.0
 */
function subs_is_stripe_test_mode() {
    return subs_get_setting('stripe', 'test_mode') === 'yes';
}

/**
 * Get Stripe API key
 *
 * @param string $type 'publishable' or 'secret'
 * @return string
 * @since 1.0.0
 */
function subs_get_stripe_key($type = 'secret') {
    $test_mode = subs_is_stripe_test_mode();

    if ($type === 'publishable') {
        $key = $test_mode ? 'test_publishable_key' : 'publishable_key';
    } else {
        $key = $test_mode ? 'test_secret_key' : 'secret_key';
    }

    return subs_get_setting('stripe', $key);
}

// =============================================================================
// EMAIL FUNCTIONS
// =============================================================================

/**
 * Send subscription email
 *
 * @param int $subscription_id
 * @param string $email_type Type of email to send
 * @return bool
 * @since 1.0.0
 */
function subs_send_email($subscription_id, $email_type) {
    $subscription = subs_get_subscription($subscription_id);

    if (!$subscription) {
        return false;
    }

    $customer = subs_get_customer($subscription->customer_id);

    if (!$customer) {
        return false;
    }

    // Check if this email type is enabled
    $email_enabled = subs_get_setting('email', $email_type . '_enabled', 'yes');

    if ($email_enabled !== 'yes') {
        return false;
    }

    $to = $customer->email;
    $from_name = subs_get_setting('email', 'from_name', get_bloginfo('name'));
    $from_email = subs_get_setting('email', 'from_email', get_option('admin_email'));

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
    );

    $subject = '';
    $message = '';

    // Build email based on type
    switch ($email_type) {
        case 'subscription_created':
            $subject = sprintf(__('Your subscription to %s', 'subs'), $subscription->plan_name);
            $message = subs_get_subscription_created_email_content($subscription, $customer);
            break;

        case 'subscription_cancelled':
            $subject = sprintf(__('Subscription cancelled: %s', 'subs'), $subscription->plan_name);
            $message = subs_get_subscription_cancelled_email_content($subscription, $customer);
            break;

        case 'payment_succeeded':
            $subject = sprintf(__('Payment successful for %s', 'subs'), $subscription->plan_name);
            $message = subs_get_payment_succeeded_email_content($subscription, $customer);
            break;

        case 'payment_failed':
            $subject = sprintf(__('Payment failed for %s', 'subs'), $subscription->plan_name);
            $message = subs_get_payment_failed_email_content($subscription, $customer);
            break;
    }

    // Allow filtering of email content
    $subject = apply_filters('subs_email_subject', $subject, $email_type, $subscription, $customer);
    $message = apply_filters('subs_email_message', $message, $email_type, $subscription, $customer);
    $headers = apply_filters('subs_email_headers', $headers, $email_type, $subscription, $customer);

    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Get subscription created email content
 *
 * @param object $subscription
 * @param object $customer
 * @return string
 * @since 1.0.0
 */
function subs_get_subscription_created_email_content($subscription, $customer) {
    $customer_name = trim($customer->first_name . ' ' . $customer->last_name);
    if (empty($customer_name)) {
        $customer_name = $customer->email;
    }

    ob_start();
    ?>
    <p><?php printf(__('Hi %s,', 'subs'), esc_html($customer_name)); ?></p>

    <p><?php _e('Thank you for subscribing! Your subscription has been activated.', 'subs'); ?></p>

    <h3><?php _e('Subscription Details:', 'subs'); ?></h3>
    <ul>
        <li><strong><?php _e('Plan:', 'subs'); ?></strong> <?php echo esc_html($subscription->plan_name); ?></li>
        <li><strong><?php _e('Amount:', 'subs'); ?></strong> <?php echo subs_format_price($subscription->amount, $subscription->currency); ?></li>
        <li><strong><?php _e('Billing:', 'subs'); ?></strong> <?php echo subs_format_interval($subscription->interval_unit, $subscription->interval_count); ?></li>
        <?php if (!empty($subscription->trial_end_date) && $subscription->trial_end_date !== '0000-00-00 00:00:00'): ?>
            <li><strong><?php _e('Trial ends:', 'subs'); ?></strong> <?php echo subs_format_date($subscription->trial_end_date); ?></li>
        <?php endif; ?>
        <?php if (!empty($subscription->next_payment_date) && $subscription->next_payment_date !== '0000-00-00 00:00:00'): ?>
            <li><strong><?php _e('Next payment:', 'subs'); ?></strong> <?php echo subs_format_date($subscription->next_payment_date); ?></li>
        <?php endif; ?>
    </ul>

    <p><?php _e('You can manage your subscription at any time from your account page.', 'subs'); ?></p>

    <p><?php _e('Thank you!', 'subs'); ?></p>
    <?php
    return ob_get_clean();
}

/**
 * Get subscription cancelled email content
 *
 * @param object $subscription
 * @param object $customer
 * @return string
 * @since 1.0.0
 */
function subs_get_subscription_cancelled_email_content($subscription, $customer) {
    $customer_name = trim($customer->first_name . ' ' . $customer->last_name);
    if (empty($customer_name)) {
        $customer_name = $customer->email;
    }

    ob_start();
    ?>
    <p><?php printf(__('Hi %s,', 'subs'), esc_html($customer_name)); ?></p>

    <p><?php _e('Your subscription has been cancelled as requested.', 'subs'); ?></p>

    <h3><?php _e('Cancelled Subscription:', 'subs'); ?></h3>
    <ul>
        <li><strong><?php _e('Plan:', 'subs'); ?></strong> <?php echo esc_html($subscription->plan_name); ?></li>
        <li><strong><?php _e('Cancelled on:', 'subs'); ?></strong> <?php echo subs_format_date($subscription->cancelled_date); ?></li>
    </ul>

    <p><?php _e('We\'re sorry to see you go! If you have any feedback, we\'d love to hear from you.', 'subs'); ?></p>

    <p><?php _e('You can reactivate your subscription at any time from your account page.', 'subs'); ?></p>
    <?php
    return ob_get_clean();
}

/**
 * Get payment succeeded email content
 *
 * @param object $subscription
 * @param object $customer
 * @return string
 * @since 1.0.0
 */
function subs_get_payment_succeeded_email_content($subscription, $customer) {
    $customer_name = trim($customer->first_name . ' ' . $customer->last_name);
    if (empty($customer_name)) {
        $customer_name = $customer->email;
    }

    ob_start();
    ?>
    <p><?php printf(__('Hi %s,', 'subs'), esc_html($customer_name)); ?></p>

    <p><?php _e('Your payment has been processed successfully.', 'subs'); ?></p>

    <h3><?php _e('Payment Details:', 'subs'); ?></h3>
    <ul>
        <li><strong><?php _e('Plan:', 'subs'); ?></strong> <?php echo esc_html($subscription->plan_name); ?></li>
        <li><strong><?php _e('Amount:', 'subs'); ?></strong> <?php echo subs_format_price($subscription->amount, $subscription->currency); ?></li>
        <li><strong><?php _e('Date:', 'subs'); ?></strong> <?php echo subs_format_date(current_time('mysql')); ?></li>
        <?php if (!empty($subscription->next_payment_date) && $subscription->next_payment_date !== '0000-00-00 00:00:00'): ?>
            <li><strong><?php _e('Next payment:', 'subs'); ?></strong> <?php echo subs_format_date($subscription->next_payment_date); ?></li>
        <?php endif; ?>
    </ul>

    <p><?php _e('Thank you for your continued subscription!', 'subs'); ?></p>
    <?php
    return ob_get_clean();
}

/**
 * Get payment failed email content
 *
 * @param object $subscription
 * @param object $customer
 * @return string
 * @since 1.0.0
 */
function subs_get_payment_failed_email_content($subscription, $customer) {
    $customer_name = trim($customer->first_name . ' ' . $customer->last_name);
    if (empty($customer_name)) {
        $customer_name = $customer->email;
    }

    ob_start();
    ?>
    <p><?php printf(__('Hi %s,', 'subs'), esc_html($customer_name)); ?></p>

    <p><?php _e('We were unable to process your subscription payment.', 'subs'); ?></p>

    <h3><?php _e('Subscription Details:', 'subs'); ?></h3>
    <ul>
        <li><strong><?php _e('Plan:', 'subs'); ?></strong> <?php echo esc_html($subscription->plan_name); ?></li>
        <li><strong><?php _e('Amount:', 'subs'); ?></strong> <?php echo subs_format_price($subscription->amount, $subscription->currency); ?></li>
    </ul>

    <p><?php _e('To continue your subscription, please update your payment method in your account.', 'subs'); ?></p>

    <p><?php _e('If you need assistance, please contact our support team.', 'subs'); ?></p>
    <?php
    return ob_get_clean();
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Check if user can manage subscriptions
 *
 * @param int $user_id Optional user ID, defaults to current user
 * @return bool
 * @since 1.0.0
 */
function subs_user_can_manage_subscriptions($user_id = 0) {
    if (empty($user_id)) {
        $user_id = get_current_user_id();
    }

    return user_can($user_id, 'manage_subscriptions') || user_can($user_id, 'manage_options');
}

/**
 * Check if user owns subscription
 *
 * @param int $subscription_id
 * @param int $user_id Optional user ID, defaults to current user
 * @return bool
 * @since 1.0.0
 */
function subs_user_owns_subscription($subscription_id, $user_id = 0) {
    if (empty($user_id)) {
        $user_id = get_current_user_id();
    }

    if (empty($user_id)) {
        return false;
    }

    $customer = subs_get_customer_by_user_id($user_id);

    if (!$customer) {
        return false;
    }

    $subscription = subs_get_subscription($subscription_id);

    if (!$subscription) {
        return false;
    }

    return $subscription->customer_id === $customer->id;
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
function subs_get_subscription_meta($subscription_id, $key = '', $single = false) {
    global $wpdb;

    $meta_table = $wpdb->prefix . 'subs_subscription_meta';

    if (empty($key)) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $meta_table WHERE subscription_id = %d",
            $subscription_id
        ));
    }

    $meta = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_value FROM $meta_table WHERE subscription_id = %d AND meta_key = %s",
        $subscription_id,
        $key
    ));

    if ($single) {
        return !empty($meta) ? maybe_unserialize($meta[0]->meta_value) : '';
    }

    return array_map(function($item) {
        return maybe_unserialize($item->meta_value);
    }, $meta);
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
function subs_update_subscription_meta($subscription_id, $key, $value) {
    global $wpdb;

    $meta_table = $wpdb->prefix . 'subs_subscription_meta';

    $value = maybe_serialize($value);

    // Check if meta exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_id FROM $meta_table WHERE subscription_id = %d AND meta_key = %s",
        $subscription_id,
        $key
    ));

    if ($existing) {
        // Update existing meta
        $result = $wpdb->update(
            $meta_table,
            array('meta_value' => $value),
            array('subscription_id' => $subscription_id, 'meta_key' => $key),
            array('%s'),
            array('%d', '%s')
        );
    } else {
        // Insert new meta
        $result = $wpdb->insert(
            $meta_table,
            array(
                'subscription_id' => $subscription_id,
                'meta_key' => $key,
                'meta_value' => $value,
            ),
            array('%d', '%s', '%s')
        );
    }

    return $result !== false;
}

/**
 * Delete subscription meta
 *
 * @param int $subscription_id
 * @param string $key
 * @return bool
 * @since 1.0.0
 */
function subs_delete_subscription_meta($subscription_id, $key) {
    global $wpdb;

    $meta_table = $wpdb->prefix . 'subs_subscription_meta';

    $result = $wpdb->delete(
        $meta_table,
        array(
            'subscription_id' => $subscription_id,
            'meta_key' => $key,
        ),
        array('%d', '%s')
    );

    return $result !== false;
}

/**
 * Get customer meta
 *
 * @param int $customer_id
 * @param string $key
 * @param bool $single
 * @return mixed
 * @since 1.0.0
 */
function subs_get_customer_meta($customer_id, $key = '', $single = false) {
    global $wpdb;

    $meta_table = $wpdb->prefix . 'subs_customer_meta';

    if (empty($key)) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $meta_table WHERE customer_id = %d",
            $customer_id
        ));
    }

    $meta = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_value FROM $meta_table WHERE customer_id = %d AND meta_key = %s",
        $customer_id,
        $key
    ));

    if ($single) {
        return !empty($meta) ? maybe_unserialize($meta[0]->meta_value) : '';
    }

    return array_map(function($item) {
        return maybe_unserialize($item->meta_value);
    }, $meta);
}

/**
 * Update customer meta
 *
 * @param int $customer_id
 * @param string $key
 * @param mixed $value
 * @return bool
 * @since 1.0.0
 */
function subs_update_customer_meta($customer_id, $key, $value) {
    global $wpdb;

    $meta_table = $wpdb->prefix . 'subs_customer_meta';

    $value = maybe_serialize($value);

    // Check if meta exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_id FROM $meta_table WHERE customer_id = %d AND meta_key = %s",
        $customer_id,
        $key
    ));

    if ($existing) {
        // Update existing meta
        $result = $wpdb->update(
            $meta_table,
            array('meta_value' => $value),
            array('customer_id' => $customer_id, 'meta_key' => $key),
            array('%s'),
            array('%d', '%s')
        );
    } else {
        // Insert new meta
        $result = $wpdb->insert(
            $meta_table,
            array(
                'customer_id' => $customer_id,
                'meta_key' => $key,
                'meta_value' => $value,
            ),
            array('%d', '%s', '%s')
        );
    }

    return $result !== false;
}

/**
 * Delete customer meta
 *
 * @param int $customer_id
 * @param string $key
 * @return bool
 * @since 1.0.0
 */
function subs_delete_customer_meta($customer_id, $key) {
    global $wpdb;

    $meta_table = $wpdb->prefix . 'subs_customer_meta';

    $result = $wpdb->delete(
        $meta_table,
        array(
            'customer_id' => $customer_id,
            'meta_key' => $key,
        ),
        array('%d', '%s')
    );

    return $result !== false;
}

/**
 * Sanitize subscription data
 *
 * @param array $data
 * @return array
 * @since 1.0.0
 */
function subs_sanitize_subscription_data($data) {
    $sanitized = array();

    if (isset($data['plan_name'])) {
        $sanitized['plan_name'] = sanitize_text_field($data['plan_name']);
    }

    if (isset($data['plan_description'])) {
        $sanitized['plan_description'] = sanitize_textarea_field($data['plan_description']);
    }

    if (isset($data['amount'])) {
        $sanitized['amount'] = floatval($data['amount']);
    }

    if (isset($data['currency'])) {
        $sanitized['currency'] = strtoupper(sanitize_text_field($data['currency']));
    }

    if (isset($data['interval_unit'])) {
        $sanitized['interval_unit'] = sanitize_text_field($data['interval_unit']);
    }

    if (isset($data['interval_count'])) {
        $sanitized['interval_count'] = intval($data['interval_count']);
    }

    if (isset($data['status'])) {
        $sanitized['status'] = sanitize_text_field($data['status']);
    }

    return $sanitized;
}

/**
 * Sanitize customer data
 *
 * @param array $data
 * @return array
 * @since 1.0.0
 */
function subs_sanitize_customer_data($data) {
    $sanitized = array();

    if (isset($data['first_name'])) {
        $sanitized['first_name'] = sanitize_text_field($data['first_name']);
    }

    if (isset($data['last_name'])) {
        $sanitized['last_name'] = sanitize_text_field($data['last_name']);
    }

    if (isset($data['email'])) {
        $sanitized['email'] = sanitize_email($data['email']);
    }

    if (isset($data['phone'])) {
        $sanitized['phone'] = sanitize_text_field($data['phone']);
    }

    if (isset($data['address_line_1'])) {
        $sanitized['address_line_1'] = sanitize_text_field($data['address_line_1']);
    }

    if (isset($data['address_line_2'])) {
        $sanitized['address_line_2'] = sanitize_text_field($data['address_line_2']);
    }

    if (isset($data['city'])) {
        $sanitized['city'] = sanitize_text_field($data['city']);
    }

    if (isset($data['state'])) {
        $sanitized['state'] = sanitize_text_field($data['state']);
    }

    if (isset($data['postal_code'])) {
        $sanitized['postal_code'] = sanitize_text_field($data['postal_code']);
    }

    if (isset($data['country'])) {
        $sanitized['country'] = sanitize_text_field($data['country']);
    }

    return $sanitized;
}

/**
 * Calculate next payment date
 *
 * @param string $start_date
 * @param string $interval_unit
 * @param int $interval_count
 * @return string
 * @since 1.0.0
 */
function subs_calculate_next_payment_date($start_date, $interval_unit, $interval_count = 1) {
    $date = new DateTime($start_date);

    switch ($interval_unit) {
        case 'day':
            $date->modify('+' . $interval_count . ' days');
            break;
        case 'week':
            $date->modify('+' . $interval_count . ' weeks');
            break;
        case 'month':
            $date->modify('+' . $interval_count . ' months');
            break;
        case 'year':
            $date->modify('+' . $interval_count . ' years');
            break;
    }

    return $date->format('Y-m-d H:i:s');
}

/**
 * Check if date is in the past
 *
 * @param string $date
 * @return bool
 * @since 1.0.0
 */
function subs_is_date_past($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return false;
    }

    return strtotime($date) < time();
}

/**
 * Get time until date
 *
 * @param string $date
 * @return string
 * @since 1.0.0
 */
function subs_time_until($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return __('N/A', 'subs');
    }

    return human_time_diff(time(), strtotime($date));
}

/**
 * Get subscription count by status
 *
 * @param string $status
 * @return int
 * @since 1.0.0
 */
function subs_get_subscription_count($status = '') {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

    if (empty($status)) {
        return intval($wpdb->get_var("SELECT COUNT(*) FROM $subscriptions_table"));
    }

    return intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $subscriptions_table WHERE status = %s",
        $status
    )));
}

/**
 * Get total revenue
 *
 * @param string $period Optional period (day, week, month, year, all)
 * @return float
 * @since 1.0.0
 */
function subs_get_total_revenue($period = 'all') {
    global $wpdb;

    $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

    $query = "SELECT SUM(amount) FROM $payment_logs_table WHERE status = 'succeeded'";

    switch ($period) {
        case 'day':
            $query .= " AND processed_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $query .= " AND processed_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $query .= " AND processed_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $query .= " AND processed_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }

    return floatval($wpdb->get_var($query));
}

/**
 * Debug log
 *
 * @param mixed $message
 * @param string $level
 * @since 1.0.0
 */
function subs_log($message, $level = 'info') {
    // Only log if debug mode is enabled
    if (subs_get_setting('advanced', 'debug_logging') !== 'yes') {
        return;
    }

    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    $log_entry = sprintf(
        '[%s] [%s] %s',
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message
    );

    error_log($log_entry);

    // Also write to custom log file if possible
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/subs-logs';

    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    $log_file = $log_dir . '/subs-' . date('Y-m-d') . '.log';
    error_log($log_entry . PHP_EOL, 3, $log_file);
}
