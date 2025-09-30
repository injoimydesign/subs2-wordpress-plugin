<?php
/**
 * Admin Subscriptions Management Class
 *
 * Handles the subscriptions list table, editing, and management functionality
 * in the WordPress admin area. Provides comprehensive subscription administration.
 *
 * @package Subs
 * @subpackage Admin
 * @since 1.0.0
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include WP_List_Table if not already loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Subs Admin Subscriptions Class
 *
 * @class Subs_Admin_Subscriptions
 * @extends WP_List_Table
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Admin_Subscriptions extends WP_List_Table {

    /**
     * Current page action
     *
     * @var string
     * @since 1.0.0
     */
    private $current_action = '';

    /**
     * Items per page
     *
     * @var int
     * @since 1.0.0
     */
    private $per_page = 20;

    /**
     * Total subscription count
     *
     * @var int
     * @since 1.0.0
     */
    private $total_items = 0;

    /**
     * Valid subscription statuses
     *
     * @var array
     * @since 1.0.0
     */
    private $valid_statuses = array();

    /**
     * Constructor
     *
     * Initialize the subscriptions list table.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize valid statuses
        $this->valid_statuses = array(
            'active' => __('Active', 'subs'),
            'trialing' => __('Trialing', 'subs'),
            'past_due' => __('Past Due', 'subs'),
            'cancelled' => __('Cancelled', 'subs'),
            'unpaid' => __('Unpaid', 'subs'),
            'incomplete' => __('Incomplete', 'subs'),
            'incomplete_expired' => __('Incomplete Expired', 'subs'),
            'paused' => __('Paused', 'subs'),
        );

        // Initialize parent class
        parent::__construct(array(
            'singular' => 'subscription',
            'plural' => 'subscriptions',
            'ajax' => true,
        ));

        // Set current action
        $this->current_action = $this->current_action();

        // Initialize hooks
        $this->init_hooks();

        // Handle actions
        $this->handle_actions();
    }

    /**
     * Initialize hooks
     *
     * Set up WordPress hooks for subscription management.
     *
     * @access private
     * @since 1.0.0
     */
    private function init_hooks() {
        // AJAX hooks for subscription actions
        add_action('wp_ajax_subs_change_subscription_status', array($this, 'ajax_change_status'));
        add_action('wp_ajax_subs_delete_subscription', array($this, 'ajax_delete_subscription'));
        add_action('wp_ajax_subs_export_subscriptions', array($this, 'ajax_export_subscriptions'));

        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Screen options
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
        add_action('load-subscriptions_page_subs-subscriptions', array($this, 'add_screen_options'));
    }

    /**
     * Handle page actions
     *
     * Process various subscription management actions.
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_actions() {
        // Verify nonce for actions
        if (!empty($this->current_action) && !wp_verify_nonce($_REQUEST['_wpnonce'], 'subs_subscription_action')) {
            wp_die(__('Security check failed', 'subs'));
        }

        switch ($this->current_action) {
            case 'edit':
                $this->handle_edit_subscription();
                break;

            case 'delete':
                $this->handle_delete_subscription();
                break;

            case 'bulk_delete':
                $this->handle_bulk_delete();
                break;

            case 'bulk_status_change':
                $this->handle_bulk_status_change();
                break;

            case 'export':
                $this->handle_export_subscriptions();
                break;
        }
    }

    /**
     * Get columns for the list table
     *
     * @return array
     * @since 1.0.0
     */
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'subs'),
            'customer' => __('Customer', 'subs'),
            'plan' => __('Plan', 'subs'),
            'status' => __('Status', 'subs'),
            'amount' => __('Amount', 'subs'),
            'interval' => __('Billing', 'subs'),
            'trial_end' => __('Trial Ends', 'subs'),
            'next_payment' => __('Next Payment', 'subs'),
            'created_date' => __('Created', 'subs'),
            'actions' => __('Actions', 'subs'),
        );

        return apply_filters('subs_admin_subscriptions_columns', $columns);
    }

    /**
     * Get sortable columns
     *
     * @return array
     * @since 1.0.0
     */
    public function get_sortable_columns() {
        $sortable = array(
            'id' => array('id', false),
            'customer' => array('customer_email', false),
            'status' => array('status', false),
            'amount' => array('amount', false),
            'next_payment' => array('next_payment_date', false),
            'created_date' => array('created_date', true), // Default sort
        );

        return apply_filters('subs_admin_subscriptions_sortable_columns', $sortable);
    }

    /**
     * Get bulk actions
     *
     * @return array
     * @since 1.0.0
     */
    public function get_bulk_actions() {
        $actions = array(
            'bulk_delete' => __('Delete', 'subs'),
            'bulk_status_active' => __('Set to Active', 'subs'),
            'bulk_status_cancelled' => __('Cancel', 'subs'),
            'bulk_status_paused' => __('Pause', 'subs'),
        );

        return apply_filters('subs_admin_subscriptions_bulk_actions', $actions);
    }

    /**
     * Prepare items for display
     *
     * Query and prepare subscription data for the list table.
     *
     * @since 1.0.0
     */
    public function prepare_items() {
        global $wpdb;

        // Set up columns
        $this->_column_headers = array(
            $this->get_columns(),
            array(), // Hidden columns
            $this->get_sortable_columns()
        );

        // Get current page
        $current_page = $this->get_pagenum();

        // Build query
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $customers_table = $wpdb->prefix . 'subs_customers';

        // Base query
        $query = "SELECT s.*, c.email as customer_email, c.first_name, c.last_name
                  FROM {$subscriptions_table} s
                  LEFT JOIN {$customers_table} c ON s.customer_id = c.id";

        // Add WHERE conditions
        $where_conditions = array('1=1');
        $query_vars = array();

        // Filter by status
        if (!empty($_REQUEST['status']) && isset($this->valid_statuses[$_REQUEST['status']])) {
            $where_conditions[] = 's.status = %s';
            $query_vars[] = sanitize_text_field($_REQUEST['status']);
        }

        // Search functionality
        if (!empty($_REQUEST['s'])) {
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_conditions[] = '(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR s.id LIKE %s)';
            $query_vars = array_merge($query_vars, array($search_term, $search_term, $search_term, $search_term));
        }

        // Date range filter
        if (!empty($_REQUEST['date_from'])) {
            $where_conditions[] = 's.created_date >= %s';
            $query_vars[] = sanitize_text_field($_REQUEST['date_from']) . ' 00:00:00';
        }

        if (!empty($_REQUEST['date_to'])) {
            $where_conditions[] = 's.created_date <= %s';
            $query_vars[] = sanitize_text_field($_REQUEST['date_to']) . ' 23:59:59';
        }

        // Add WHERE clause
        if (!empty($where_conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $where_conditions);
        }

        // Get total count for pagination
        $count_query = str_replace('SELECT s.*, c.email as customer_email, c.first_name, c.last_name', 'SELECT COUNT(*)', $query);

        if (!empty($query_vars)) {
            $this->total_items = $wpdb->get_var($wpdb->prepare($count_query, $query_vars));
        } else {
            $this->total_items = $wpdb->get_var($count_query);
        }

        // Add sorting
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = !empty($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';
        $query .= " ORDER BY {$orderby} {$order}";

        // Add pagination
        $offset = ($current_page - 1) * $this->per_page;
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $this->per_page, $offset);

        // Execute query
        if (!empty($query_vars)) {
            $this->items = $wpdb->get_results($wpdb->prepare($query, $query_vars));
        } else {
            $this->items = $wpdb->get_results($query);
        }

        // Set pagination
        $this->set_pagination_args(array(
            'total_items' => $this->total_items,
            'per_page' => $this->per_page,
            'total_pages' => ceil($this->total_items / $this->per_page),
        ));
    }

    /**
     * Default column output
     *
     * @param object $item
     * @param string $column_name
     * @return string
     * @since 1.0.0
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return sprintf('<strong>#%d</strong>', $item->id);

            case 'customer':
                $customer_name = trim($item->first_name . ' ' . $item->last_name);
                if (empty($customer_name)) {
                    $customer_name = $item->customer_email;
                }

                $edit_url = admin_url('admin.php?page=subs-customers&action=edit&customer_id=' . $item->customer_id);
                return sprintf('<a href="%s">%s</a><br><small>%s</small>',
                    esc_url($edit_url),
                    esc_html($customer_name),
                    esc_html($item->customer_email)
                );

            case 'plan':
                return sprintf('<strong>%s</strong><br><small>%s</small>',
                    esc_html($item->plan_name ?: __('N/A', 'subs')),
                    esc_html($item->plan_description ?: '')
                );

            case 'status':
                return $this->get_status_badge($item->status);

            case 'amount':
                return $this->format_price($item->amount, $item->currency);

            case 'interval':
                return $this->format_billing_interval($item->interval_count, $item->interval_unit);

            case 'trial_end':
                if (!empty($item->trial_end_date) && $item->trial_end_date !== '0000-00-00 00:00:00') {
                    $trial_end = new DateTime($item->trial_end_date);
                    $now = new DateTime();

                    if ($trial_end > $now) {
                        $diff = $now->diff($trial_end);
                        return sprintf('%s<br><small>%s left</small>',
                            $trial_end->format('M j, Y'),
                            $this->format_time_diff($diff)
                        );
                    } else {
                        return '<span class="dashicons dashicons-minus" title="' . __('Trial ended', 'subs') . '"></span>';
                    }
                }
                return '<span class="dashicons dashicons-minus" title="' . __('No trial', 'subs') . '"></span>';

            case 'next_payment':
                if (!empty($item->next_payment_date) && $item->next_payment_date !== '0000-00-00 00:00:00') {
                    $next_payment = new DateTime($item->next_payment_date);
                    return $next_payment->format('M j, Y');
                }
                return '<span class="dashicons dashicons-minus"></span>';

            case 'created_date':
                $created = new DateTime($item->created_date);
                return sprintf('%s<br><small>%s</small>',
                    $created->format('M j, Y'),
                    $created->format('g:i A')
                );

            default:
                return apply_filters('subs_admin_subscriptions_column_' . $column_name, '', $item);
        }
    }

    /**
     * Checkbox column
     *
     * @param object $item
     * @return string
     * @since 1.0.0
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="subscription_ids[]" value="%d" />', $item->id);
    }

    /**
     * Actions column
     *
     * @param object $item
     * @return string
     * @since 1.0.0
     */
    public function column_actions($item) {
        $actions = array();

        // Edit link
        $edit_url = admin_url('admin.php?page=subs-subscriptions&action=edit&subscription_id=' . $item->id);
        $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'subs'));

        // Status-specific actions
        switch ($item->status) {
            case 'active':
            case 'trialing':
                $actions['cancel'] = sprintf(
                    '<a href="#" class="subs-change-status" data-id="%d" data-status="cancelled">%s</a>',
                    $item->id,
                    __('Cancel', 'subs')
                );
                $actions['pause'] = sprintf(
                    '<a href="#" class="subs-change-status" data-id="%d" data-status="paused">%s</a>',
                    $item->id,
                    __('Pause', 'subs')
                );
                break;

            case 'cancelled':
                $actions['reactivate'] = sprintf(
                    '<a href="#" class="subs-change-status" data-id="%d" data-status="active">%s</a>',
                    $item->id,
                    __('Reactivate', 'subs')
                );
                break;

            case 'paused':
                $actions['resume'] = sprintf(
                    '<a href="#" class="subs-change-status" data-id="%d" data-status="active">%s</a>',
                    $item->id,
                    __('Resume', 'subs')
                );
                $actions['cancel'] = sprintf(
                    '<a href="#" class="subs-change-status" data-id="%d" data-status="cancelled">%s</a>',
                    $item->id,
                    __('Cancel', 'subs')
                );
                break;
        }

        // View customer
        $customer_url = admin_url('admin.php?page=subs-customers&action=edit&customer_id=' . $item->customer_id);
        $actions['view_customer'] = sprintf('<a href="%s">%s</a>', esc_url($customer_url), __('View Customer', 'subs'));

        // Delete (with confirmation)
        $actions['delete'] = sprintf(
            '<a href="#" class="subs-delete-subscription submitdelete" data-id="%d">%s</a>',
            $item->id,
            __('Delete', 'subs')
        );

        return implode(' | ', apply_filters('subs_admin_subscription_actions', $actions, $item));
    }

    /**
     * Extra tablenav content
     *
     * Add filters and export functionality above the table.
     *
     * @param string $which
     * @since 1.0.0
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';

            // Status filter
            $this->render_status_filter();

            // Date range filter
            $this->render_date_filter();

            // Export button
            echo '<button type="button" id="export-subscriptions" class="button">' . __('Export CSV', 'subs') . '</button>';

            echo '</div>';
        }
    }

    /**
     * Render status filter dropdown
     *
     * @access private
     * @since 1.0.0
     */
    private function render_status_filter() {
        $current_status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';

        echo '<select name="status" id="status-filter">';
        echo '<option value="">' . __('All Statuses', 'subs') . '</option>';

        foreach ($this->valid_statuses as $status => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($status),
                selected($current_status, $status, false),
                esc_html($label)
            );
        }

        echo '</select>';
    }

    /**
     * Render date filter inputs
     *
     * @access private
     * @since 1.0.0
     */
    private function render_date_filter() {
        $date_from = isset($_REQUEST['date_from']) ? sanitize_text_field($_REQUEST['date_from']) : '';
        $date_to = isset($_REQUEST['date_to']) ? sanitize_text_field($_REQUEST['date_to']) : '';

        printf(
            '<input type="date" name="date_from" value="%s" placeholder="%s" />',
            esc_attr($date_from),
            esc_attr__('From date', 'subs')
        );

        printf(
            '<input type="date" name="date_to" value="%s" placeholder="%s" />',
            esc_attr($date_to),
            esc_attr__('To date', 'subs')
        );

        submit_button(__('Filter', 'subs'), 'secondary', 'filter_action', false);
    }

    /**
     * Handle edit subscription action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_edit_subscription() {
        if (!isset($_REQUEST['subscription_id'])) {
            wp_die(__('Subscription ID is required', 'subs'));
        }

        $subscription_id = intval($_REQUEST['subscription_id']);
        $subscription = $this->get_subscription($subscription_id);

        if (!$subscription) {
            wp_die(__('Subscription not found', 'subs'));
        }

        // Handle form submission
        if (isset($_POST['save_subscription'])) {
            $this->save_subscription($subscription_id, $_POST);
        }

        // Include edit form template
        $this->render_edit_form($subscription);
    }

    /**
     * Handle delete subscription action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_delete_subscription() {
        if (!isset($_REQUEST['subscription_id'])) {
            wp_die(__('Subscription ID is required', 'subs'));
        }

        $subscription_id = intval($_REQUEST['subscription_id']);

        if ($this->delete_subscription($subscription_id)) {
            wp_redirect(admin_url('admin.php?page=subs-subscriptions&deleted=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=subs-subscriptions&delete_error=1'));
            exit;
        }
    }

    /**
     * Handle bulk delete action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_bulk_delete() {
        if (empty($_REQUEST['subscription_ids']) || !is_array($_REQUEST['subscription_ids'])) {
            wp_redirect(admin_url('admin.php?page=subs-subscriptions&bulk_error=1'));
            exit;
        }

        $deleted_count = 0;
        foreach ($_REQUEST['subscription_ids'] as $subscription_id) {
            if ($this->delete_subscription(intval($subscription_id))) {
                $deleted_count++;
            }
        }

        wp_redirect(admin_url('admin.php?page=subs-subscriptions&bulk_deleted=' . $deleted_count));
        exit;
    }

    /**
     * Handle bulk status change action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_bulk_status_change() {
        if (empty($_REQUEST['subscription_ids']) || !is_array($_REQUEST['subscription_ids'])) {
            wp_redirect(admin_url('admin.php?page=subs-subscriptions&bulk_error=1'));
            exit;
        }

        // Extract status from action (e.g., 'bulk_status_active' -> 'active')
        $status = str_replace('bulk_status_', '', $this->current_action);

        if (!isset($this->valid_statuses[$status])) {
            wp_redirect(admin_url('admin.php?page=subs-subscriptions&status_error=1'));
            exit;
        }

        $updated_count = 0;
        foreach ($_REQUEST['subscription_ids'] as $subscription_id) {
            if ($this->update_subscription_status(intval($subscription_id), $status)) {
                $updated_count++;
            }
        }

        wp_redirect(admin_url('admin.php?page=subs-subscriptions&bulk_updated=' . $updated_count));
        exit;
    }

    /**
     * Handle export subscriptions action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_export_subscriptions() {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="subscriptions-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        $headers = array(
            'ID',
            'Customer Email',
            'Customer Name',
            'Plan',
            'Status',
            'Amount',
            'Currency',
            'Billing Interval',
            'Trial End',
            'Next Payment',
            'Created Date'
        );

        fputcsv($output, $headers);

        // Get all subscriptions for export
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $customers_table = $wpdb->prefix . 'subs_customers';

        $query = "SELECT s.*, c.email as customer_email, c.first_name, c.last_name
                  FROM {$subscriptions_table} s
                  LEFT JOIN {$customers_table} c ON s.customer_id = c.id
                  ORDER BY s.id DESC";

        $subscriptions = $wpdb->get_results($query);

        foreach ($subscriptions as $subscription) {
            $customer_name = trim($subscription->first_name . ' ' . $subscription->last_name);

            $row = array(
                $subscription->id,
                $subscription->customer_email,
                $customer_name ?: 'N/A',
                $subscription->plan_name ?: 'N/A',
                $this->valid_statuses[$subscription->status] ?? $subscription->status,
                $subscription->amount,
                $subscription->currency,
                $this->format_billing_interval($subscription->interval_count, $subscription->interval_unit),
                $subscription->trial_end_date !== '0000-00-00 00:00:00' ? $subscription->trial_end_date : 'N/A',
                $subscription->next_payment_date !== '0000-00-00 00:00:00' ? $subscription->next_payment_date : 'N/A',
                $subscription->created_date
            );

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Get subscription by ID
     *
     * @param int $subscription_id
     * @return object|null
     * @since 1.0.0
     */
    private function get_subscription($subscription_id) {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $customers_table = $wpdb->prefix . 'subs_customers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, c.email as customer_email, c.first_name, c.last_name
             FROM {$subscriptions_table} s
             LEFT JOIN {$customers_table} c ON s.customer_id = c.id
             WHERE s.id = %d",
            $subscription_id
        ));
    }

    /**
     * Save subscription data
     *
     * @param int $subscription_id
     * @param array $data
     * @return bool
     * @since 1.0.0
     */
    private function save_subscription($subscription_id, $data) {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        // Sanitize and validate data
        $update_data = array();

        if (isset($data['status']) && isset($this->valid_statuses[$data['status']])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }

        if (isset($data['amount']) && is_numeric($data['amount'])) {
            $update_data['amount'] = floatval($data['amount']);
        }

        if (isset($data['next_payment_date']) && !empty($data['next_payment_date'])) {
            $update_data['next_payment_date'] = sanitize_text_field($data['next_payment_date']);
        }

        if (isset($data['plan_name'])) {
            $update_data['plan_name'] = sanitize_text_field($data['plan_name']);
        }

        if (isset($data['plan_description'])) {
            $update_data['plan_description'] = sanitize_textarea_field($data['plan_description']);
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $subscriptions_table,
            $update_data,
            array('id' => $subscription_id),
            array('%s', '%f', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Log the change
            $this->log_subscription_change($subscription_id, 'updated', $update_data);

            // Trigger action hook
            do_action('subs_subscription_updated', $subscription_id, $update_data);

            return true;
        }

        return false;
    }

    /**
     * Delete subscription
     *
     * @param int $subscription_id
     * @return bool
     * @since 1.0.0
     */
    private function delete_subscription($subscription_id) {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        // Get subscription data before deletion for logging
        $subscription = $this->get_subscription($subscription_id);

        if (!$subscription) {
            return false;
        }

        $result = $wpdb->delete(
            $subscriptions_table,
            array('id' => $subscription_id),
            array('%d')
        );

        if ($result !== false) {
            // Log the deletion
            $this->log_subscription_change($subscription_id, 'deleted', array());

            // Trigger action hook
            do_action('subs_subscription_deleted', $subscription_id, $subscription);

            return true;
        }

        return false;
    }

    /**
     * Update subscription status
     *
     * @param int $subscription_id
     * @param string $status
     * @return bool
     * @since 1.0.0
     */
    private function update_subscription_status($subscription_id, $status) {
        if (!isset($this->valid_statuses[$status])) {
            return false;
        }

        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $result = $wpdb->update(
            $subscriptions_table,
            array('status' => $status),
            array('id' => $subscription_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            // Log the status change
            $this->log_subscription_change($subscription_id, 'status_changed', array('new_status' => $status));

            // Trigger action hook
            do_action('subs_subscription_status_changed', $subscription_id, $status);

            return true;
        }

        return false;
    }

    /**
     * Log subscription changes
     *
     * @param int $subscription_id
     * @param string $action
     * @param array $data
     * @since 1.0.0
     */
    private function log_subscription_change($subscription_id, $action, $data = array()) {
        global $wpdb;

        $history_table = $wpdb->prefix . 'subs_subscription_history';

        $wpdb->insert(
            $history_table,
            array(
                'subscription_id' => $subscription_id,
                'action' => $action,
                'data' => json_encode($data),
                'user_id' => get_current_user_id(),
                'created_date' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Render edit subscription form
     *
     * @param object $subscription
     * @access private
     * @since 1.0.0
     */
    private function render_edit_form($subscription) {
        ?>
        <div class="wrap">
            <h1><?php _e('Edit Subscription', 'subs'); ?> #<?php echo esc_html($subscription->id); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('subs_subscription_action'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Customer', 'subs'); ?></th>
                        <td>
                            <?php
                            $customer_name = trim($subscription->first_name . ' ' . $subscription->last_name);
                            echo esc_html($customer_name ?: $subscription->customer_email);
                            ?>
                            <br><small><?php echo esc_html($subscription->customer_email); ?></small>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="status"><?php _e('Status', 'subs'); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <?php foreach ($this->valid_statuses as $status => $label): ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($subscription->status, $status); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="plan_name"><?php _e('Plan Name', 'subs'); ?></label></th>
                        <td>
                            <input type="text" name="plan_name" id="plan_name" value="<?php echo esc_attr($subscription->plan_name); ?>" class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="plan_description"><?php _e('Plan Description', 'subs'); ?></label></th>
                        <td>
                            <textarea name="plan_description" id="plan_description" rows="3" class="large-text"><?php echo esc_textarea($subscription->plan_description); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="amount"><?php _e('Amount', 'subs'); ?></label></th>
                        <td>
                            <input type="number" name="amount" id="amount" value="<?php echo esc_attr($subscription->amount); ?>" step="0.01" min="0" class="small-text" />
                            <span><?php echo esc_html(strtoupper($subscription->currency)); ?></span>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="next_payment_date"><?php _e('Next Payment Date', 'subs'); ?></label></th>
                        <td>
                            <input type="datetime-local" name="next_payment_date" id="next_payment_date"
                                   value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($subscription->next_payment_date))); ?>" />
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Subscription', 'subs'), 'primary', 'save_subscription'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get status badge HTML
     *
     * @param string $status
     * @return string
     * @since 1.0.0
     */
    private function get_status_badge($status) {
        $label = isset($this->valid_statuses[$status]) ? $this->valid_statuses[$status] : $status;

        $class_map = array(
            'active' => 'success',
            'trialing' => 'info',
            'past_due' => 'warning',
            'cancelled' => 'danger',
            'unpaid' => 'danger',
            'incomplete' => 'warning',
            'incomplete_expired' => 'danger',
            'paused' => 'secondary',
        );

        $class = isset($class_map[$status]) ? $class_map[$status] : 'default';

        return sprintf('<span class="subs-status-badge subs-status-%s">%s</span>', esc_attr($class), esc_html($label));
    }

    /**
     * Format price with currency
     *
     * @param float $amount
     * @param string $currency
     * @return string
     * @since 1.0.0
     */
    private function format_price($amount, $currency = 'USD') {
        $settings = get_option('subs_general_settings', array());

        $decimals = isset($settings['number_of_decimals']) ? intval($settings['number_of_decimals']) : 2;
        $decimal_sep = isset($settings['decimal_separator']) ? $settings['decimal_separator'] : '.';
        $thousand_sep = isset($settings['thousand_separator']) ? $settings['thousand_separator'] : ',';
        $position = isset($settings['currency_position']) ? $settings['currency_position'] : 'left';

        $formatted_amount = number_format($amount, $decimals, $decimal_sep, $thousand_sep);
        $currency_symbol = $this->get_currency_symbol($currency);

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
    private function get_currency_symbol($currency) {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C',
            'AUD' => 'A',
            'JPY' => '¥',
        );

        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }

    /**
     * Format billing interval
     *
     * @param int $count
     * @param string $unit
     * @return string
     * @since 1.0.0
     */
    private function format_billing_interval($count, $unit) {
        if ($count == 1) {
            switch ($unit) {
                case 'day':
                    return __('Daily', 'subs');
                case 'week':
                    return __('Weekly', 'subs');
                case 'month':
                    return __('Monthly', 'subs');
                case 'year':
                    return __('Yearly', 'subs');
            }
        }

        $units = array(
            'day' => _n('day', 'days', $count, 'subs'),
            'week' => _n('week', 'weeks', $count, 'subs'),
            'month' => _n('month', 'months', $count, 'subs'),
            'year' => _n('year', 'years', $count, 'subs'),
        );

        $unit_label = isset($units[$unit]) ? $units[$unit] : $unit;

        return sprintf(__('Every %d %s', 'subs'), $count, $unit_label);
    }

    /**
     * Format time difference
     *
     * @param DateInterval $diff
     * @return string
     * @since 1.0.0
     */
    private function format_time_diff($diff) {
        if ($diff->days > 0) {
            return sprintf(_n('%d day', '%d days', $diff->days, 'subs'), $diff->days);
        } elseif ($diff->h > 0) {
            return sprintf(_n('%d hour', '%d hours', $diff->h, 'subs'), $diff->h);
        } else {
            return sprintf(_n('%d minute', '%d minutes', $diff->i, 'subs'), $diff->i);
        }
    }

    /**
     * AJAX: Change subscription status
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_change_status() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_subscriptions')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id']);
        $status = sanitize_text_field($_POST['status']);

        if ($this->update_subscription_status($subscription_id, $status)) {
            wp_send_json_success(array(
                'message' => __('Subscription status updated successfully.', 'subs'),
                'status' => $status,
                'status_badge' => $this->get_status_badge($status)
            ));
        } else {
            wp_send_json_error(__('Failed to update subscription status.', 'subs'));
        }
    }

    /**
     * AJAX: Delete subscription
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_delete_subscription() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_subscriptions')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id']);

        if ($this->delete_subscription($subscription_id)) {
            wp_send_json_success(__('Subscription deleted successfully.', 'subs'));
        } else {
            wp_send_json_error(__('Failed to delete subscription.', 'subs'));
        }
    }

    /**
     * AJAX: Export subscriptions
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_export_subscriptions() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('export_subscriptions')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        // Redirect to export action
        wp_send_json_success(array(
            'redirect' => admin_url('admin.php?page=subs-subscriptions&action=export&_wpnonce=' . wp_create_nonce('subs_subscription_action'))
        ));
    }

    /**
     * Display admin notices
     *
     * @access public
     * @since 1.0.0
     */
    public function admin_notices() {
        if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Subscription deleted successfully.', 'subs') . '</p></div>';
        }

        if (isset($_GET['bulk_deleted'])) {
            $count = intval($_GET['bulk_deleted']);
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf(_n('%d subscription deleted successfully.', '%d subscriptions deleted successfully.', $count, 'subs'), $count) .
                 '</p></div>';
        }

        if (isset($_GET['bulk_updated'])) {
            $count = intval($_GET['bulk_updated']);
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf(_n('%d subscription updated successfully.', '%d subscriptions updated successfully.', $count, 'subs'), $count) .
                 '</p></div>';
        }

        if (isset($_GET['delete_error']) && $_GET['delete_error'] == 1) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to delete subscription.', 'subs') . '</p></div>';
        }

        if (isset($_GET['bulk_error']) && $_GET['bulk_error'] == 1) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('No subscriptions selected for bulk action.', 'subs') . '</p></div>';
        }

        if (isset($_GET['status_error']) && $_GET['status_error'] == 1) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Invalid status for bulk action.', 'subs') . '</p></div>';
        }
    }

    /**
     * Add screen options
     *
     * @access public
     * @since 1.0.0
     */
    public function add_screen_options() {
        add_screen_option('per_page', array(
            'label' => __('Subscriptions per page', 'subs'),
            'default' => $this->per_page,
            'option' => 'subs_subscriptions_per_page'
        ));
    }

    /**
     * Set screen option
     *
     * @param mixed $status
     * @param string $option
     * @param mixed $value
     * @return mixed
     * @since 1.0.0
     */
    public function set_screen_option($status, $option, $value) {
        if ($option === 'subs_subscriptions_per_page') {
            return $value;
        }
        return $status;
    }
}
