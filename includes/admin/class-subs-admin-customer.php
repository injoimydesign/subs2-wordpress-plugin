<?php
/**
 * Admin Customers Management Class
 *
 * Handles customer management functionality in the WordPress admin area.
 * Provides customer listing, editing, and subscription management for customers.
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
 * Subs Admin Customers Class
 *
 * @class Subs_Admin_Customers
 * @extends WP_List_Table
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Admin_Customers extends WP_List_Table {

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
     * Total customer count
     *
     * @var int
     * @since 1.0.0
     */
    private $total_items = 0;

    /**
     * Constructor
     *
     * Initialize the customers list table.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize parent class
        parent::__construct(array(
            'singular' => 'customer',
            'plural' => 'customers',
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
     * Set up WordPress hooks for customer management.
     *
     * @access private
     * @since 1.0.0
     */
    private function init_hooks() {
        // AJAX hooks for customer actions
        add_action('wp_ajax_subs_update_customer', array($this, 'ajax_update_customer'));
        add_action('wp_ajax_subs_delete_customer', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_subs_export_customers', array($this, 'ajax_export_customers'));
        add_action('wp_ajax_subs_send_customer_email', array($this, 'ajax_send_customer_email'));

        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Screen options
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
        add_action('load-subscriptions_page_subs-customers', array($this, 'add_screen_options'));
    }

    /**
     * Handle page actions
     *
     * Process various customer management actions.
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_actions() {
        // Verify nonce for actions that modify data
        if (!empty($this->current_action) && in_array($this->current_action, array('edit', 'delete', 'bulk_delete', 'add'))) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'subs_customer_action')) {
                wp_die(__('Security check failed', 'subs'));
            }
        }

        switch ($this->current_action) {
            case 'add':
                $this->handle_add_customer();
                break;

            case 'edit':
                $this->handle_edit_customer();
                break;

            case 'delete':
                $this->handle_delete_customer();
                break;

            case 'bulk_delete':
                $this->handle_bulk_delete();
                break;

            case 'export':
                $this->handle_export_customers();
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
            'avatar' => '',
            'name' => __('Name', 'subs'),
            'email' => __('Email', 'subs'),
            'subscriptions' => __('Subscriptions', 'subs'),
            'total_spent' => __('Total Spent', 'subs'),
            'status' => __('Status', 'subs'),
            'created_date' => __('Registered', 'subs'),
            'last_login' => __('Last Login', 'subs'),
            'actions' => __('Actions', 'subs'),
        );

        return apply_filters('subs_admin_customers_columns', $columns);
    }

    /**
     * Get sortable columns
     *
     * @return array
     * @since 1.0.0
     */
    public function get_sortable_columns() {
        $sortable = array(
            'name' => array('last_name', false),
            'email' => array('email', false),
            'subscriptions' => array('subscription_count', false),
            'total_spent' => array('total_spent', false),
            'created_date' => array('created_date', true), // Default sort
            'last_login' => array('last_login', false),
        );

        return apply_filters('subs_admin_customers_sortable_columns', $sortable);
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
            'bulk_export' => __('Export Selected', 'subs'),
            'bulk_email' => __('Send Email', 'subs'),
        );

        return apply_filters('subs_admin_customers_bulk_actions', $actions);
    }

    /**
     * Prepare items for display
     *
     * Query and prepare customer data for the list table.
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
        $customers_table = $wpdb->prefix . 'subs_customers';
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';
        $users_table = $wpdb->users;

        // Base query with subscription counts and total spent
        $query = "SELECT c.*,
                         u.user_login, u.user_registered, u.user_email as wp_email,
                         COUNT(DISTINCT s.id) as subscription_count,
                         SUM(CASE WHEN pl.status = 'succeeded' THEN pl.amount ELSE 0 END) as total_spent,
                         MAX(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as has_active_subscription
                  FROM {$customers_table} c
                  LEFT JOIN {$users_table} u ON c.user_id = u.ID
                  LEFT JOIN {$subscriptions_table} s ON c.id = s.customer_id
                  LEFT JOIN {$payment_logs_table} pl ON s.id = pl.subscription_id";

        // Add WHERE conditions
        $where_conditions = array('1=1');
        $query_vars = array();

        // Search functionality
        if (!empty($_REQUEST['s'])) {
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_conditions[] = '(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR u.user_login LIKE %s)';
            $query_vars = array_merge($query_vars, array($search_term, $search_term, $search_term, $search_term));
        }

        // Status filter
        if (!empty($_REQUEST['status'])) {
            $status = sanitize_text_field($_REQUEST['status']);
            switch ($status) {
                case 'active':
                    $where_conditions[] = 'EXISTS (SELECT 1 FROM ' . $subscriptions_table . ' WHERE customer_id = c.id AND status = "active")';
                    break;
                case 'inactive':
                    $where_conditions[] = 'NOT EXISTS (SELECT 1 FROM ' . $subscriptions_table . ' WHERE customer_id = c.id AND status = "active")';
                    break;
                case 'with_subscriptions':
                    $where_conditions[] = 'EXISTS (SELECT 1 FROM ' . $subscriptions_table . ' WHERE customer_id = c.id)';
                    break;
                case 'without_subscriptions':
                    $where_conditions[] = 'NOT EXISTS (SELECT 1 FROM ' . $subscriptions_table . ' WHERE customer_id = c.id)';
                    break;
            }
        }

        // Date range filter
        if (!empty($_REQUEST['date_from'])) {
            $where_conditions[] = 'c.created_date >= %s';
            $query_vars[] = sanitize_text_field($_REQUEST['date_from']) . ' 00:00:00';
        }

        if (!empty($_REQUEST['date_to'])) {
            $where_conditions[] = 'c.created_date <= %s';
            $query_vars[] = sanitize_text_field($_REQUEST['date_to']) . ' 23:59:59';
        }

        // Add WHERE clause
        if (!empty($where_conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $where_conditions);
        }

        // Add GROUP BY
        $query .= ' GROUP BY c.id';

        // Get total count for pagination
        $count_query = "SELECT COUNT(DISTINCT c.id) FROM ({$query}) as count_table";

        if (!empty($query_vars)) {
            $this->total_items = $wpdb->get_var($wpdb->prepare($count_query, $query_vars));
        } else {
            $this->total_items = $wpdb->get_var($count_query);
        }

        // Add sorting
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_date';
        $order = !empty($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';

        // Map orderby to actual columns
        $orderby_map = array(
            'last_name' => 'c.last_name',
            'email' => 'c.email',
            'subscription_count' => 'subscription_count',
            'total_spent' => 'total_spent',
            'created_date' => 'c.created_date',
            'last_login' => 'c.last_login_date',
        );

        $orderby_column = isset($orderby_map[$orderby]) ? $orderby_map[$orderby] : 'c.created_date';
        $query .= " ORDER BY {$orderby_column} {$order}";

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
            case 'avatar':
                $avatar_url = !empty($item->avatar_url) ? $item->avatar_url : get_avatar_url($item->email, 32);
                return sprintf('<img src="%s" alt="" class="avatar avatar-32" width="32" height="32" />', esc_url($avatar_url));

            case 'name':
                $full_name = trim($item->first_name . ' ' . $item->last_name);
                $display_name = !empty($full_name) ? $full_name : $item->email;

                $edit_url = admin_url('admin.php?page=subs-customers&action=edit&customer_id=' . $item->id);
                $name_link = sprintf('<strong><a href="%s">%s</a></strong>', esc_url($edit_url), esc_html($display_name));

                if (!empty($item->user_login)) {
                    $wp_user_url = admin_url('user-edit.php?user_id=' . $item->user_id);
                    $name_link .= sprintf('<br><small><a href="%s">@%s</a></small>', esc_url($wp_user_url), esc_html($item->user_login));
                }

                return $name_link;

            case 'email':
                $email_link = sprintf('<a href="mailto:%s">%s</a>', esc_attr($item->email), esc_html($item->email));

                if (!empty($item->phone)) {
                    $email_link .= '<br><small>' . esc_html($item->phone) . '</small>';
                }

                return $email_link;

            case 'subscriptions':
                $count = intval($item->subscription_count);

                if ($count > 0) {
                    $subscriptions_url = admin_url('admin.php?page=subs-subscriptions&customer_id=' . $item->id);
                    $link = sprintf('<a href="%s"><strong>%d</strong></a>', esc_url($subscriptions_url), $count);

                    if ($item->has_active_subscription) {
                        $link .= '<br><small class="subs-status-active">' . __('Active', 'subs') . '</small>';
                    } else {
                        $link .= '<br><small class="subs-status-inactive">' . __('Inactive', 'subs') . '</small>';
                    }

                    return $link;
                } else {
                    return '<span class="dashicons dashicons-minus" title="' . __('No subscriptions', 'subs') . '"></span>';
                }

            case 'total_spent':
                $total = floatval($item->total_spent);
                return $total > 0 ? $this->format_price($total) : '<span class="dashicons dashicons-minus"></span>';

            case 'status':
                if ($item->has_active_subscription) {
                    return '<span class="subs-status-badge subs-status-success">' . __('Active Customer', 'subs') . '</span>';
                } elseif ($item->subscription_count > 0) {
                    return '<span class="subs-status-badge subs-status-warning">' . __('Past Customer', 'subs') . '</span>';
                } else {
                    return '<span class="subs-status-badge subs-status-secondary">' . __('No Subscriptions', 'subs') . '</span>';
                }

            case 'created_date':
                $created = new DateTime($item->created_date);
                return sprintf('%s<br><small>%s</small>',
                    $created->format('M j, Y'),
                    $created->format('g:i A')
                );

            case 'last_login':
                if (!empty($item->last_login_date) && $item->last_login_date !== '0000-00-00 00:00:00') {
                    $last_login = new DateTime($item->last_login_date);
                    $now = new DateTime();
                    $diff = $now->diff($last_login);

                    if ($diff->days == 0) {
                        return __('Today', 'subs');
                    } elseif ($diff->days == 1) {
                        return __('Yesterday', 'subs');
                    } elseif ($diff->days < 30) {
                        return sprintf(_n('%d day ago', '%d days ago', $diff->days, 'subs'), $diff->days);
                    } else {
                        return $last_login->format('M j, Y');
                    }
                } else {
                    return '<span class="dashicons dashicons-minus" title="' . __('Never logged in', 'subs') . '"></span>';
                }

            default:
                return apply_filters('subs_admin_customers_column_' . $column_name, '', $item);
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
        return sprintf('<input type="checkbox" name="customer_ids[]" value="%d" />', $item->id);
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

        // Edit customer
        $edit_url = admin_url('admin.php?page=subs-customers&action=edit&customer_id=' . $item->id);
        $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', 'subs'));

        // View subscriptions
        if ($item->subscription_count > 0) {
            $subscriptions_url = admin_url('admin.php?page=subs-subscriptions&customer_id=' . $item->id);
            $actions['subscriptions'] = sprintf('<a href="%s">%s</a>', esc_url($subscriptions_url), __('Subscriptions', 'subs'));
        }

        // Send email
        $actions['email'] = sprintf(
            '<a href="#" class="subs-send-email" data-customer-id="%d" data-email="%s">%s</a>',
            $item->id,
            esc_attr($item->email),
            __('Send Email', 'subs')
        );

        // WordPress user link
        if (!empty($item->user_id)) {
            $user_edit_url = admin_url('user-edit.php?user_id=' . $item->user_id);
            $actions['wp_user'] = sprintf('<a href="%s">%s</a>', esc_url($user_edit_url), __('WP User', 'subs'));
        }

        // Delete customer (with confirmation)
        $actions['delete'] = sprintf(
            '<a href="#" class="subs-delete-customer submitdelete" data-customer-id="%d">%s</a>',
            $item->id,
            __('Delete', 'subs')
        );

        return implode(' | ', apply_filters('subs_admin_customer_actions', $actions, $item));
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
            echo '<button type="button" id="export-customers" class="button">' . __('Export CSV', 'subs') . '</button>';

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

        $statuses = array(
            '' => __('All Customers', 'subs'),
            'active' => __('Active Customers', 'subs'),
            'inactive' => __('Inactive Customers', 'subs'),
            'with_subscriptions' => __('With Subscriptions', 'subs'),
            'without_subscriptions' => __('Without Subscriptions', 'subs'),
        );

        echo '<select name="status" id="status-filter">';
        foreach ($statuses as $status => $label) {
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
     * Handle add customer action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_add_customer() {
        // Handle form submission
        if (isset($_POST['save_customer'])) {
            $result = $this->save_customer(0, $_POST);

            if ($result['success']) {
                wp_redirect(admin_url('admin.php?page=subs-customers&added=1'));
                exit;
            } else {
                $this->add_error_notice($result['message']);
            }
        }

        // Render add customer form
        $this->render_customer_form();
    }

    /**
     * Handle edit customer action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_edit_customer() {
        if (!isset($_REQUEST['customer_id'])) {
            wp_die(__('Customer ID is required', 'subs'));
        }

        $customer_id = intval($_REQUEST['customer_id']);
        $customer = $this->get_customer($customer_id);

        if (!$customer) {
            wp_die(__('Customer not found', 'subs'));
        }

        // Handle form submission
        if (isset($_POST['save_customer'])) {
            $result = $this->save_customer($customer_id, $_POST);

            if ($result['success']) {
                wp_redirect(admin_url('admin.php?page=subs-customers&action=edit&customer_id=' . $customer_id . '&updated=1'));
                exit;
            } else {
                $this->add_error_notice($result['message']);
            }
        }

        // Render edit customer form
        $this->render_customer_form($customer);
    }

    /**
     * Handle delete customer action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_delete_customer() {
        if (!isset($_REQUEST['customer_id'])) {
            wp_die(__('Customer ID is required', 'subs'));
        }

        $customer_id = intval($_REQUEST['customer_id']);

        if ($this->delete_customer($customer_id)) {
            wp_redirect(admin_url('admin.php?page=subs-customers&deleted=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=subs-customers&delete_error=1'));
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
        if (empty($_REQUEST['customer_ids']) || !is_array($_REQUEST['customer_ids'])) {
            wp_redirect(admin_url('admin.php?page=subs-customers&bulk_error=1'));
            exit;
        }

        $deleted_count = 0;
        foreach ($_REQUEST['customer_ids'] as $customer_id) {
            if ($this->delete_customer(intval($customer_id))) {
                $deleted_count++;
            }
        }

        wp_redirect(admin_url('admin.php?page=subs-customers&bulk_deleted=' . $deleted_count));
        exit;
    }

    /**
     * Handle export customers action
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_export_customers() {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        $headers = array(
            'ID',
            'Email',
            'First Name',
            'Last Name',
            'Phone',
            'Address',
            'City',
            'State',
            'Country',
            'Postal Code',
            'Subscriptions Count',
            'Total Spent',
            'Status',
            'Created Date',
            'Last Login'
        );

        fputcsv($output, $headers);

        // Get all customers for export
        global $wpdb;

        $customers_table = $wpdb->prefix . 'subs_customers';
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        $query = "SELECT c.*,
                         COUNT(DISTINCT s.id) as subscription_count,
                         SUM(CASE WHEN pl.status = 'succeeded' THEN pl.amount ELSE 0 END) as total_spent,
                         MAX(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as has_active_subscription
                  FROM {$customers_table} c
                  LEFT JOIN {$subscriptions_table} s ON c.id = s.customer_id
                  LEFT JOIN {$payment_logs_table} pl ON s.id = pl.subscription_id
                  GROUP BY c.id
                  ORDER BY c.id DESC";

        $customers = $wpdb->get_results($query);

        foreach ($customers as $customer) {
            $status = $customer->has_active_subscription ? 'Active' :
                     ($customer->subscription_count > 0 ? 'Past Customer' : 'No Subscriptions');

            $row = array(
                $customer->id,
                $customer->email,
                $customer->first_name,
                $customer->last_name,
                $customer->phone ?: '',
                $customer->address_line_1 ?: '',
                $customer->city ?: '',
                $customer->state ?: '',
                $customer->country ?: '',
                $customer->postal_code ?: '',
                $customer->subscription_count,
                $customer->total_spent ?: '0.00',
                $status,
                $customer->created_date,
                $customer->last_login_date !== '0000-00-00 00:00:00' ? $customer->last_login_date : 'Never'
            );

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Get customer by ID
     *
     * @param int $customer_id
     * @return object|null
     * @since 1.0.0
     */
    private function get_customer($customer_id) {
        global $wpdb;

        $customers_table = $wpdb->prefix . 'subs_customers';
        $users_table = $wpdb->users;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, u.user_login, u.user_email as wp_email
             FROM {$customers_table} c
             LEFT JOIN {$users_table} u ON c.user_id = u.ID
             WHERE c.id = %d",
            $customer_id
        ));
    }

    /**
     * Save customer data
     *
     * @param int $customer_id
     * @param array $data
     * @return array
     * @since 1.0.0
     */
    private function save_customer($customer_id, $data) {
        global $wpdb;

        $customers_table = $wpdb->prefix . 'subs_customers';

        // Sanitize and validate data
        $customer_data = array();

        // Required fields
        if (empty($data['email'])) {
            return array('success' => false, 'message' => __('Email is required.', 'subs'));
        }

        $email = sanitize_email($data['email']);
        if (!is_email($email)) {
            return array('success' => false, 'message' => __('Please enter a valid email address.', 'subs'));
        }

        // Check for duplicate email (exclude current customer if editing)
        $email_check_query = "SELECT id FROM {$customers_table} WHERE email = %s";
        $email_check_args = array($email);

        if ($customer_id > 0) {
            $email_check_query .= " AND id != %d";
            $email_check_args[] = $customer_id;
        }

        $existing_customer = $wpdb->get_var($wpdb->prepare($email_check_query, $email_check_args));

        if ($existing_customer) {
            return array('success' => false, 'message' => __('A customer with this email already exists.', 'subs'));
        }

        $customer_data['email'] = $email;

        // Optional fields
        $customer_data['first_name'] = isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '';
        $customer_data['last_name'] = isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '';
        $customer_data['phone'] = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';

        // Address fields
        $customer_data['address_line_1'] = isset($data['address_line_1']) ? sanitize_text_field($data['address_line_1']) : '';
        $customer_data['address_line_2'] = isset($data['address_line_2']) ? sanitize_text_field($data['address_line_2']) : '';
        $customer_data['city'] = isset($data['city']) ? sanitize_text_field($data['city']) : '';
        $customer_data['state'] = isset($data['state']) ? sanitize_text_field($data['state']) : '';
        $customer_data['country'] = isset($data['country']) ? sanitize_text_field($data['country']) : '';
        $customer_data['postal_code'] = isset($data['postal_code']) ? sanitize_text_field($data['postal_code']) : '';

        // Avatar URL
        $customer_data['avatar_url'] = isset($data['avatar_url']) ? esc_url_raw($data['avatar_url']) : '';

        if ($customer_id > 0) {
            // Update existing customer
            $customer_data['updated_date'] = current_time('mysql');

            $result = $wpdb->update(
                $customers_table,
                $customer_data,
                array('id' => $customer_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($result !== false) {
                do_action('subs_customer_updated', $customer_id, $customer_data);
                return array('success' => true, 'message' => __('Customer updated successfully.', 'subs'));
            }
        } else {
            // Create new customer
            $customer_data['created_date'] = current_time('mysql');
            $customer_data['updated_date'] = current_time('mysql');

            $result = $wpdb->insert(
                $customers_table,
                $customer_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result !== false) {
                $new_customer_id = $wpdb->insert_id;
                do_action('subs_customer_created', $new_customer_id, $customer_data);
                return array('success' => true, 'message' => __('Customer created successfully.', 'subs'));
            }
        }

        return array('success' => false, 'message' => __('Failed to save customer data.', 'subs'));
    }

    /**
     * Delete customer
     *
     * @param int $customer_id
     * @return bool
     * @since 1.0.0
     */
    private function delete_customer($customer_id) {
        global $wpdb;

        $customers_table = $wpdb->prefix . 'subs_customers';
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        // Check if customer has active subscriptions
        $active_subscriptions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$subscriptions_table} WHERE customer_id = %d AND status = 'active'",
            $customer_id
        ));

        if ($active_subscriptions > 0) {
            return false; // Cannot delete customer with active subscriptions
        }

        // Get customer data before deletion
        $customer = $this->get_customer($customer_id);

        if (!$customer) {
            return false;
        }

        $result = $wpdb->delete(
            $customers_table,
            array('id' => $customer_id),
            array('%d')
        );

        if ($result !== false) {
            do_action('subs_customer_deleted', $customer_id, $customer);
            return true;
        }

        return false;
    }

    /**
     * Render customer form
     *
     * @param object|null $customer
     * @access private
     * @since 1.0.0
     */
    private function render_customer_form($customer = null) {
        $is_edit = !empty($customer);
        $page_title = $is_edit ? __('Edit Customer', 'subs') : __('Add New Customer', 'subs');

        <div class="wrap">
            <h1><?php echo esc_html($page_title); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('subs_customer_action'); ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <!-- Main customer information -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Customer Information', 'subs'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><label for="email"><?php _e('Email', 'subs'); ?> <span class="required">*</span></label></th>
                                            <td>
                                                <input type="email" name="email" id="email" value="<?php echo esc_attr($is_edit ? $customer->email : ''); ?>" class="regular-text" required />
                                                <p class="description"><?php _e('Customer email address (required)', 'subs'); ?></p>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="first_name"><?php _e('First Name', 'subs'); ?></label></th>
                                            <td>
                                                <input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($is_edit ? $customer->first_name : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="last_name"><?php _e('Last Name', 'subs'); ?></label></th>
                                            <td>
                                                <input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($is_edit ? $customer->last_name : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="phone"><?php _e('Phone', 'subs'); ?></label></th>
                                            <td>
                                                <input type="tel" name="phone" id="phone" value="<?php echo esc_attr($is_edit ? $customer->phone : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="avatar_url"><?php _e('Avatar URL', 'subs'); ?></label></th>
                                            <td>
                                                <input type="url" name="avatar_url" id="avatar_url" value="<?php echo esc_attr($is_edit ? $customer->avatar_url : ''); ?>" class="regular-text" />
                                                <p class="description"><?php _e('URL to customer avatar image', 'subs'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Address information -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Address Information', 'subs'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><label for="address_line_1"><?php _e('Address Line 1', 'subs'); ?></label></th>
                                            <td>
                                                <input type="text" name="address_line_1" id="address_line_1" value="<?php echo esc_attr($is_edit ? $customer->address_line_1 : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="address_line_2"><?php _e('Address Line 2', 'subs'); ?></label></th>
                                            <td>
                                                <input type="text" name="address_line_2" id="address_line_2" value="<?php echo esc_attr($is_edit ? $customer->address_line_2 : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="city"><?php _e('City', 'subs'); ?></label></th>
                                            <td>
                                                <input type="text" name="city" id="city" value="<?php echo esc_attr($is_edit ? $customer->city : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="state"><?php _e('State/Province', 'subs'); ?></label></th>
                                            <td>
                                                <input type="text" name="state" id="state" value="<?php echo esc_attr($is_edit ? $customer->state : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="country"><?php _e('Country', 'subs'); ?></label></th>
                                            <td>
                                                <select name="country" id="country" class="regular-text">
                                                    <option value=""><?php _e('Select Country', 'subs'); ?></option>
                                                    <?php foreach ($this->get_countries() as $code => $name): ?>
                                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($is_edit ? $customer->country : '', $code); ?>>
                                                            <?php echo esc_html($name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><label for="postal_code"><?php _e('Postal Code', 'subs'); ?></label></th>
                                            <td>
                                                <input type="text" name="postal_code" id="postal_code" value="<?php echo esc_attr($is_edit ? $customer->postal_code : ''); ?>" class="regular-text" />
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container">
                            <!-- Save metabox -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Save Customer', 'subs'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div class="submitbox">
                                        <div id="major-publishing-actions">
                                            <div id="publishing-action">
                                                <?php submit_button($is_edit ? __('Update Customer', 'subs') : __('Add Customer', 'subs'), 'primary', 'save_customer', false); ?>
                                            </div>
                                            <div class="clear"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($is_edit): ?>
                            <!-- Customer statistics -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Customer Statistics', 'subs'); ?></h2>
                                </div>
                                <div class="inside">
                                    <?php $this->render_customer_stats($customer); ?>
                                </div>
                            </div>

                            <!-- WordPress user link -->
                            <?php if (!empty($customer->user_id)): ?>
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('WordPress User', 'subs'); ?></h2>
                                </div>
                                <div class="inside">
                                    <p>
                                        <strong><?php _e('Username:', 'subs'); ?></strong> <?php echo esc_html($customer->user_login); ?><br>
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $customer->user_id)); ?>" class="button">
                                            <?php _e('Edit WP User', 'subs'); ?>
                                        </a>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render customer statistics
     *
     * @param object $customer
     * @access private
     * @since 1.0.0
     */
    private function render_customer_stats($customer) {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        // Get subscription counts by status
        $subscription_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM {$subscriptions_table} WHERE customer_id = %d GROUP BY status",
            $customer->id
        ));

        // Get payment statistics
        $payment_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                MAX(processed_date) as last_payment_date
             FROM {$payment_logs_table} pl
             JOIN {$subscriptions_table} s ON pl.subscription_id = s.id
             WHERE s.customer_id = %d",
            $customer->id
        ));

        ?>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><strong><?php _e('Customer Since:', 'subs'); ?></strong></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($customer->created_date))); ?></td>
                </tr>

                <tr>
                    <td><strong><?php _e('Total Subscriptions:', 'subs'); ?></strong></td>
                    <td>
                        <?php
                        $total_subs = array_sum(wp_list_pluck($subscription_stats, 'count'));
                        echo esc_html($total_subs);
                        ?>
                    </td>
                </tr>

                <?php foreach ($subscription_stats as $stat): ?>
                <tr>
                    <td><?php echo esc_html(ucfirst($stat->status)) . ' ' . __('Subscriptions:', 'subs'); ?></td>
                    <td><?php echo esc_html($stat->count); ?></td>
                </tr>
                <?php endforeach; ?>

                <tr>
                    <td><strong><?php _e('Total Paid:', 'subs'); ?></strong></td>
                    <td><?php echo esc_html($this->format_price($payment_stats->total_paid ?: 0)); ?></td>
                </tr>

                <tr>
                    <td><strong><?php _e('Total Payments:', 'subs'); ?></strong></td>
                    <td><?php echo esc_html($payment_stats->total_payments ?: 0); ?></td>
                </tr>

                <tr>
                    <td><strong><?php _e('Failed Payments:', 'subs'); ?></strong></td>
                    <td><?php echo esc_html($payment_stats->failed_payments ?: 0); ?></td>
                </tr>

                <?php if (!empty($payment_stats->last_payment_date)): ?>
                <tr>
                    <td><strong><?php _e('Last Payment:', 'subs'); ?></strong></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment_stats->last_payment_date))); ?></td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($customer->last_login_date) && $customer->last_login_date !== '0000-00-00 00:00:00'): ?>
                <tr>
                    <td><strong><?php _e('Last Login:', 'subs'); ?></strong></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($customer->last_login_date))); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=subs-subscriptions&customer_id=' . $customer->id)); ?>" class="button">
                <?php _e('View All Subscriptions', 'subs'); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Get countries list
     *
     * @return array
     * @since 1.0.0
     */
    private function get_countries() {
        return array(
            'US' => __('United States', 'subs'),
            'CA' => __('Canada', 'subs'),
            'GB' => __('United Kingdom', 'subs'),
            'AU' => __('Australia', 'subs'),
            'DE' => __('Germany', 'subs'),
            'FR' => __('France', 'subs'),
            'IT' => __('Italy', 'subs'),
            'ES' => __('Spain', 'subs'),
            'NL' => __('Netherlands', 'subs'),
            'BE' => __('Belgium', 'subs'),
            'CH' => __('Switzerland', 'subs'),
            'AT' => __('Austria', 'subs'),
            'SE' => __('Sweden', 'subs'),
            'NO' => __('Norway', 'subs'),
            'DK' => __('Denmark', 'subs'),
            'FI' => __('Finland', 'subs'),
            'JP' => __('Japan', 'subs'),
            'SG' => __('Singapore', 'subs'),
            'HK' => __('Hong Kong', 'subs'),
            'NZ' => __('New Zealand', 'subs'),
        );
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
     * Add error notice
     *
     * @param string $message
     * @access private
     * @since 1.0.0
     */
    private function add_error_notice($message) {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }

    /**
     * AJAX: Update customer
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_update_customer() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_customers')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $customer_id = intval($_POST['customer_id']);
        $result = $this->save_customer($customer_id, $_POST);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Delete customer
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_delete_customer() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_customers')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $customer_id = intval($_POST['customer_id']);

        if ($this->delete_customer($customer_id)) {
            wp_send_json_success(__('Customer deleted successfully.', 'subs'));
        } else {
            wp_send_json_error(__('Cannot delete customer with active subscriptions.', 'subs'));
        }
    }

    /**
     * AJAX: Export customers
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_export_customers() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('export_customers')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        // Redirect to export action
        wp_send_json_success(array(
            'redirect' => admin_url('admin.php?page=subs-customers&action=export&_wpnonce=' . wp_create_nonce('subs_customer_action'))
        ));
    }

    /**
     * AJAX: Send customer email
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_send_customer_email() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_customers')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $customer_id = intval($_POST['customer_id']);
        $subject = sanitize_text_field($_POST['subject']);
        $message = wp_kses_post($_POST['message']);

        $customer = $this->get_customer($customer_id);

        if (!$customer) {
            wp_send_json_error(__('Customer not found.', 'subs'));
        }

        // Send email using WordPress mail function
        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (wp_mail($customer->email, $subject, $message, $headers)) {
            wp_send_json_success(__('Email sent successfully.', 'subs'));
        } else {
            wp_send_json_error(__('Failed to send email.', 'subs'));
        }
    }

    /**
     * Display admin notices
     *
     * @access public
     * @since 1.0.0
     */
    public function admin_notices() {
        if (isset($_GET['added']) && $_GET['added'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Customer added successfully.', 'subs') . '</p></div>';
        }

        if (isset($_GET['updated']) && $_GET['updated'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Customer updated successfully.', 'subs') . '</p></div>';
        }

        if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Customer deleted successfully.', 'subs') . '</p></div>';
        }

        if (isset($_GET['bulk_deleted'])) {
            $count = intval($_GET['bulk_deleted']);
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf(_n('%d customer deleted successfully.', '%d customers deleted successfully.', $count, 'subs'), $count) .
                 '</p></div>';
        }

        if (isset($_GET['delete_error']) && $_GET['delete_error'] == 1) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Cannot delete customer with active subscriptions.', 'subs') . '</p></div>';
        }

        if (isset($_GET['bulk_error']) && $_GET['bulk_error'] == 1) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('No customers selected for bulk action.', 'subs') . '</p></div>';
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
            'label' => __('Customers per page', 'subs'),
            'default' => $this->per_page,
            'option' => 'subs_customers_per_page'
        ));
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
     * Set screen option
     *
     * @param mixed $status
     * @param string $option
     * @param mixed $value
     * @return mixed
     * @since 1.0.0
     */
    public function set_screen_option($status, $option, $value) {
        if ($option === 'subs_customers_per_page') {
            return $value;
        }
        return $status;
    }
}
