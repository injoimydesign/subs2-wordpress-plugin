<?php
/**
 * Customer Management Class
 *
 * Handles all customer-related operations including creation,
 * updates, authentication, and customer data management.
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
 * Subs Customer Class
 *
 * @class Subs_Customer
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Customer {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize customer system
     *
     * @since 1.0.0
     */
    public function init() {
        // Hook into WordPress user system
        add_action('user_register', array($this, 'sync_wp_user_creation'));
        add_action('profile_update', array($this, 'sync_wp_user_update'));
        add_action('wp_login', array($this, 'update_last_login'), 10, 2);

        // Customer management hooks
        add_action('subs_customer_created', array($this, 'send_welcome_email'), 10, 2);
        add_action('subs_customer_updated', array($this, 'handle_customer_update'), 10, 3);
    }

    /**
     * Create a new customer
     *
     * @param array $customer_data Customer data
     * @return int|WP_Error Customer ID or error object
     * @since 1.0.0
     */
    public function create_customer($customer_data) {
        global $wpdb;

        // Validate required fields
        if (empty($customer_data['email'])) {
            return new WP_Error('missing_email', __('Email address is required', 'subs'));
        }

        if (!is_email($customer_data['email'])) {
            return new WP_Error('invalid_email', __('Invalid email address', 'subs'));
        }

        // Check if customer already exists
        if ($this->customer_exists($customer_data['email'])) {
            return new WP_Error('customer_exists', __('Customer with this email already exists', 'subs'));
        }

        // Set defaults
        $defaults = array(
            'wp_user_id' => null,
            'stripe_customer_id' => null,
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
            'flag_delivery_address' => '',
        );

        $customer_data = wp_parse_args($customer_data, $defaults);

        // Sanitize data
        $customer_data = $this->sanitize_customer_data($customer_data);

        // Insert customer into database
        $table_name = $wpdb->prefix . 'subs_customers';
        $result = $wpdb->insert($table_name, $customer_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create customer in database', 'subs'));
        }

        $customer_id = $wpdb->insert_id;

        // Trigger action for other plugins to hook into
        do_action('subs_customer_created', $customer_id, $customer_data);

    /**
     * Get customer display name
     *
     * @param object $customer
     * @return string
     * @since 1.0.0
     */
    public function get_customer_display_name($customer) {
        if (!empty($customer->first_name) || !empty($customer->last_name)) {
            return trim($customer->first_name . ' ' . $customer->last_name);
        }

        return $customer->email;
    }

    /**
     * Get customer full address
     *
     * @param object $customer
     * @return string
     * @since 1.0.0
     */
    public function get_customer_full_address($customer) {
        $address_parts = array();

        if (!empty($customer->address_line1)) {
            $address_parts[] = $customer->address_line1;
        }

        if (!empty($customer->address_line2)) {
            $address_parts[] = $customer->address_line2;
        }

        $city_state_zip = array();
        if (!empty($customer->city)) {
            $city_state_zip[] = $customer->city;
        }
        if (!empty($customer->state)) {
            $city_state_zip[] = $customer->state;
        }
        if (!empty($customer->postal_code)) {
            $city_state_zip[] = $customer->postal_code;
        }

        if (!empty($city_state_zip)) {
            $address_parts[] = implode(' ', $city_state_zip);
        }

        if (!empty($customer->country)) {
            $address_parts[] = $customer->country;
        }

        return implode("\n", $address_parts);
    }

    /**
     * Export customers to CSV
     *
     * @param array $filters
     * @since 1.0.0
     */
    public function export_customers_csv($filters = array()) {
        $customers = $this->search_customers(array_merge($filters, array('limit' => 0)));

        $filename = 'customers_export_' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV headers
        $headers = array(
            'ID',
            'Email',
            'First Name',
            'Last Name',
            'Phone',
            'Address Line 1',
            'Address Line 2',
            'City',
            'State',
            'Postal Code',
            'Country',
            'Flag Delivery Address',
            'Subscription Count',
            'Total Spent',
            'Created Date',
            'Last Login',
            'WordPress User ID',
            'Stripe Customer ID'
        );

        fputcsv($output, $headers);

        // CSV data
        foreach ($customers as $customer) {
            $total_spent = $this->get_customer_total_spent($customer->id);

            fputcsv($output, array(
                $customer->id,
                $customer->email,
                $customer->first_name,
                $customer->last_name,
                $customer->phone,
                $customer->address_line1,
                $customer->address_line2,
                $customer->city,
                $customer->state,
                $customer->postal_code,
                $customer->country,
                $customer->flag_delivery_address,
                $customer->subscription_count ?? 0,
                $total_spent,
                $customer->created_date,
                $customer->last_login,
                $customer->wp_user_id,
                $customer->stripe_customer_id
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Validate customer data
     *
     * @param array $data
     * @return array Array of validation errors
     * @since 1.0.0
     */
    public function validate_customer_data($data) {
        $errors = array();

        // Email validation
        if (empty($data['email'])) {
            $errors['email'] = __('Email address is required', 'subs');
        } elseif (!is_email($data['email'])) {
            $errors['email'] = __('Invalid email address format', 'subs');
        }

        // Phone validation (if provided)
        if (!empty($data['phone'])) {
            // Basic phone validation - can be enhanced based on requirements
            $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $data['phone']);
            if (strlen($phone) < 10) {
                $errors['phone'] = __('Phone number appears to be too short', 'subs');
            }
        }

        // Postal code validation (if provided)
        if (!empty($data['postal_code']) && !empty($data['country'])) {
            // Basic validation - can be enhanced for specific countries
            if ($data['country'] === 'US' && !preg_match('/^\d{5}(-\d{4})?$/', $data['postal_code'])) {
                $errors['postal_code'] = __('Invalid US postal code format', 'subs');
            }
        }

        return $errors;
    }

    /**
     * Merge duplicate customers
     *
     * @param int $primary_customer_id Customer to keep
     * @param int $duplicate_customer_id Customer to merge and remove
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function merge_customers($primary_customer_id, $duplicate_customer_id) {
        global $wpdb;

        if ($primary_customer_id === $duplicate_customer_id) {
            return new WP_Error('same_customer', __('Cannot merge customer with itself', 'subs'));
        }

        $primary_customer = $this->get_customer($primary_customer_id);
        $duplicate_customer = $this->get_customer($duplicate_customer_id);

        if (!$primary_customer || !$duplicate_customer) {
            return new WP_Error('customer_not_found', __('One or both customers not found', 'subs'));
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Move subscriptions to primary customer
            $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
            $wpdb->update(
                $subscriptions_table,
                array('customer_id' => $primary_customer_id),
                array('customer_id' => $duplicate_customer_id),
                array('%d'),
                array('%d')
            );

            // Merge customer meta (don't overwrite existing)
            $duplicate_meta = $this->get_customer_meta($duplicate_customer_id);
            foreach ($duplicate_meta as $key => $value) {
                $existing_value = $this->get_customer_meta($primary_customer_id, $key, true);
                if (empty($existing_value)) {
                    $this->update_customer_meta($primary_customer_id, $key, $value);
                }
            }

            // Delete duplicate customer
            $this->delete_customer($duplicate_customer_id, true);

            // Commit transaction
            $wpdb->query('COMMIT');

            // Trigger action
            do_action('subs_customers_merged', $primary_customer_id, $duplicate_customer_id);

            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            return new WP_Error('merge_failed', $e->getMessage());
        }
    }
}
