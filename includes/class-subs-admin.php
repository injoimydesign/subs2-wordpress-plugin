<?php
/**
 * Admin Interface Controller
 *
 * Handles the WordPress admin interface, menu creation,
 * page routing, and admin-specific functionality.
 *
 * @package Subs
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs Admin Class
 *
 * @class Subs_Admin
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Admin {

    /**
     * Admin pages
     *
     * @var array
     * @since 1.0.0
     */
    private $admin_pages = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_ajax_subs_admin_action', array($this, 'handle_admin_ajax'));

        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Handle bulk actions
        add_action('admin_init', array($this, 'handle_bulk_actions'));

        // Add admin footer text
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
    }

    /**
     * Initialize admin functionality
     *
     * @since 1.0.0
     */
    public function admin_init() {
        // Check if database needs updating
        if (Subs_Install::needs_database_update()) {
            add_action('admin_notices', array($this, 'database_update_notice'));
        }

        // Handle database update
        if (isset($_GET['do_update_subs_db']) && $_GET['do_update_subs_db'] === '1') {
            check_admin_referer('subs_db_update');
            Subs_Install::upgrade();
            wp_redirect(admin_url('admin.php?page=subs&updated=1'));
            exit;
        }

        // Handle settings save
        if (isset($_POST['subs_settings_submit'])) {
            $this->save_settings();
        }

        // Handle export requests
        if (isset($_GET['subs_export']) && current_user_can('manage_subs_subscriptions')) {
            $this->handle_export();
        }
    }

    /**
     * Create admin menu
     *
     * @since 1.0.0
     */
    public function admin_menu() {
        // Main menu page
        $this->admin_pages['main'] = add_menu_page(
            __('Subscriptions', 'subs'),
            __('Subscriptions', 'subs'),
            'manage_subs_subscriptions',
            'subs',
            array($this, 'admin_page_dashboard'),
            'dashicons-update',
            30
        );

        // Dashboard submenu (same as main page)
        $this->admin_pages['dashboard'] = add_submenu_page(
            'subs',
            __('Dashboard', 'subs'),
            __('Dashboard', 'subs'),
            'manage_subs_subscriptions',
            'subs',
            array($this, 'admin_page_dashboard')
        );

        // Subscriptions list
        $this->admin_pages['subscriptions'] = add_submenu_page(
            'subs',
            __('All Subscriptions', 'subs'),
            __('All Subscriptions', 'subs'),
            'manage_subs_subscriptions',
            'subs-subscriptions',
            array($this, 'admin_page_subscriptions')
        );

        // Add new subscription
        $this->admin_pages['add_subscription'] = add_submenu_page(
            'subs',
            __('Add Subscription', 'subs'),
            __('Add New', 'subs'),
            'manage_subs_subscriptions',
            'subs-add-subscription',
            array($this, 'admin_page_add_subscription')
        );

        // Customers
        $this->admin_pages['customers'] = add_submenu_page(
            'subs',
            __('Customers', 'subs'),
            __('Customers', 'subs'),
            'manage_subs_customers',
            'subs-customers',
            array($this, 'admin_page_customers')
        );

        // Reports (if user has permission)
        if (current_user_can('view_subs_reports')) {
            $this->admin_pages['reports'] = add_submenu_page(
                'subs',
                __('Reports', 'subs'),
                __('Reports', 'subs'),
                'view_subs_reports',
                'subs-reports',
                array($this, 'admin_page_reports')
            );
        }

        // Settings
        $this->admin_pages['settings'] = add_submenu_page(
            'subs',
            __('Settings', 'subs'),
            __('Settings', 'subs'),
            'manage_subs_settings',
            'subs-settings',
            array($this, 'admin_page_settings')
        );

        // Hidden page for editing individual subscriptions
        $this->admin_pages['edit_subscription'] = add_submenu_page(
            null, // Hidden from menu
            __('Edit Subscription', 'subs'),
            __('Edit Subscription', 'subs'),
            'manage_subs_subscriptions',
            'subs-edit-subscription',
            array($this, 'admin_page_edit_subscription')
        );

        // Hook for adding page-specific help tabs
        foreach ($this->admin_pages as $page_hook) {
            add_action("load-$page_hook", array($this, 'add_help_tabs'));
        }
    }

    /**
     * Dashboard admin page
     *
     * @since 1.0.0
     */
    public function admin_page_dashboard() {
        // Check permissions
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'subs'));
        }

        $this->render_dashboard();
    }

    /**
     * Subscriptions list admin page
     *
     * @since 1.0.0
     */
    public function admin_page_subscriptions() {
        // Check permissions
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'subs'));
        }

        $this->render_subscriptions_list();
    }

    /**
     * Add subscription admin page
     *
     * @since 1.0.0
     */
    public function admin_page_add_subscription() {
        // Check permissions
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'subs'));
        }

        // Handle form submission
        if ($_POST && check_admin_referer('subs_add_subscription')) {
            $this->handle_add_subscription();
        }

        $this->render_add_subscription_form();
    }

    /**
     * Edit subscription admin page
     *
     * @since 1.0.0
     */
    public function admin_page_edit_subscription() {
        // Check permissions
        if (!current_user_can('manage_subs_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'subs'));
        }

        $subscription_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$subscription_id) {
            wp_die(__('Invalid subscription ID.', 'subs'));
        }

        // Get subscription
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription) {
            wp_die(__('Subscription not found.', 'subs'));
        }

        // Handle form submission
        if ($_POST && check_admin_referer('subs_edit_subscription')) {
            $this->handle_edit_subscription($subscription_id);
        }

        $this->render_edit_subscription_form($subscription);
    }

    /**
     * Customers admin page
     *
     * @since 1.0.0
     */
    public function admin_page_customers() {
        // Check permissions
        if (!current_user_can('manage_subs_customers')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'subs'));
        }

        $this->render_customers_list();
    }

    /**
     * Reports admin page
     *
     * @since 1.0.0
     */
    public function admin_page_reports() {
        // Check permissions
        if (!current_user_can('view_subs_reports')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'subs'));
        }

        $this->render_reports();
    }

    /**
     * Settings admin page
     *
     * @since 1.0.0
     */
    public function admin_page_settings() {
        // Check permissions
        if (!current_user_can('manage_subs_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'subs'));
        }

        $this->render_settings();
    }

    /**
     * Render dashboard
     *
     * @since 1.0.0
     */
    private function render_dashboard() {
        $stats = $this->get_dashboard_stats();
        $recent_activity = $this->get_recent_activity(5);

        ?>
        <div class="wrap">
            <h1><?php _e('Subscriptions Dashboard', 'subs'); ?></h1>

            <!-- Stats Cards -->
            <div class="subs-stats-grid">
                <div class="subs-stat-card">
                    <h3><?php _e('Active Subscriptions', 'subs'); ?></h3>
                    <p class="subs-stat-number"><?php echo esc_html($stats['active'] ?? 0); ?></p>
                </div>

                <div class="subs-stat-card">
                    <h3><?php _e('Monthly Revenue', 'subs'); ?></h3>
                    <p class="subs-stat-number"><?php echo esc_html($this->format_currency($stats['monthly_revenue'] ?? 0)); ?></p>
                </div>

                <div class="subs-stat-card">
                    <h3><?php _e('Total Customers', 'subs'); ?></h3>
                    <p class="subs-stat-number"><?php echo esc_html($stats['total_customers'] ?? 0); ?></p>
                </div>

                <div class="subs-stat-card">
                    <h3><?php _e('This Month', 'subs'); ?></h3>
                    <p class="subs-stat-number"><?php echo esc_html($stats['new_this_month'] ?? 0); ?></p>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="subs-recent-activity">
                <h2><?php _e('Recent Activity', 'subs'); ?></h2>

                <?php if (!empty($recent_activity)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'subs'); ?></th>
                                <th><?php _e('Action', 'subs'); ?></th>
                                <th><?php _e('Subscription', 'subs'); ?></th>
                                <th><?php _e('Customer', 'subs'); ?></th>
                                <th><?php _e('Note', 'subs'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity->created_date))); ?></td>
                                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $activity->action))); ?></td>
                                    <td><?php echo esc_html($activity->product_name ?? 'N/A'); ?></td>
                                    <td><?php echo esc_html($activity->customer_email ?? 'N/A'); ?></td>
                                    <td><?php echo esc_html($activity->note ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No recent activity.', 'subs'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="subs-quick-actions">
                <h2><?php _e('Quick Actions', 'subs'); ?></h2>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=subs-add-subscription')); ?>" class="button button-primary">
                        <?php _e('Add New Subscription', 'subs'); ?>
                    </a>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=subs-customers')); ?>" class="button">
                        <?php _e('Manage Customers', 'subs'); ?>
                    </a>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=subs-settings')); ?>" class="button">
                        <?php _e('Settings', 'subs'); ?>
                    </a>
                </p>
            </div>
        </div>

        <style>
        .subs-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .subs-stat-card {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            text-align: center;
        }

        .subs-stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            color: #23282d;
        }

        .subs-stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
            margin: 0;
        }

        .subs-recent-activity,
        .subs-quick-actions {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        </style>
        <?php
    }

    /**
     * Render subscriptions list
     *
     * @since 1.0.0
     */
    private function render_subscriptions_list() {
        global $wpdb;

        // Handle search and filtering
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Build query
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $customers_table = $wpdb->prefix . 'subs_customers';

        $where = array('1=1');
        $values = array();

        if (!empty($search)) {
            $where[] = "(s.product_name LIKE %s OR c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $values = array_merge($values, array($search_term, $search_term, $search_term, $search_term));
        }

        if (!empty($status_filter)) {
            $where[] = "s.status = %s";
            $values[] = $status_filter;
        }

        $where_clause = implode(' AND ', $where);

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $subscriptions_table s LEFT JOIN $customers_table c ON s.customer_id = c.id WHERE $where_clause";
        $total_items = $wpdb->get_var($values ? $wpdb->prepare($count_query, $values) : $count_query);

        // Get subscriptions
        $query = "
            SELECT s.*, c.email, c.first_name, c.last_name
            FROM $subscriptions_table s
            LEFT JOIN $customers_table c ON s.customer_id = c.id
            WHERE $where_clause
            ORDER BY s.created_date DESC
            LIMIT %d OFFSET %d
        ";

        $query_values = array_merge($values, array($per_page, $offset));
        $subscriptions = $wpdb->get_results($wpdb->prepare($query, $query_values));

        // Pagination
        $total_pages = ceil($total_items / $per_page);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Subscriptions', 'subs'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=subs-add-subscription')); ?>" class="page-title-action">
                <?php _e('Add New', 'subs'); ?>
            </a>

            <!-- Search and Filters -->
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="subs-subscriptions" />
                <p class="search-box">
                    <label class="screen-reader-text" for="subscription-search-input"><?php _e('Search Subscriptions:', 'subs'); ?></label>
                    <input type="search" id="subscription-search-input" name="s" value="<?php echo esc_attr($search); ?>" />

                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'subs'); ?></option>
                        <?php foreach (Subs_Subscription::get_statuses() as $status => $label): ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($status_filter, $status); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php submit_button(__('Search', 'subs'), 'button', '', false, array('id' => 'search-submit')); ?>
                </p>
            </form>

            <!-- Subscriptions Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-cb check-column">
                            <input type="checkbox" />
                        </th>
                        <th scope="col"><?php _e('ID', 'subs'); ?></th>
                        <th scope="col"><?php _e('Product', 'subs'); ?></th>
                        <th scope="col"><?php _e('Customer', 'subs'); ?></th>
                        <th scope="col"><?php _e('Amount', 'subs'); ?></th>
                        <th scope="col"><?php _e('Status', 'subs'); ?></th>
                        <th scope="col"><?php _e('Next Payment', 'subs'); ?></th>
                        <th scope="col"><?php _e('Created', 'subs'); ?></th>
                        <th scope="col"><?php _e('Actions', 'subs'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($subscriptions)): ?>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="subscription[]" value="<?php echo esc_attr($subscription->id); ?>" />
                                </th>
                                <td><?php echo esc_html($subscription->id); ?></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-edit-subscription&id=' . $subscription->id)); ?>">
                                            <?php echo esc_html($subscription->product_name); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo esc_html($subscription->email); ?>
                                    <?php if ($subscription->first_name || $subscription->last_name): ?>
                                        <br><small><?php echo esc_html(trim($subscription->first_name . ' ' . $subscription->last_name)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($this->format_currency($subscription->amount, $subscription->currency)); ?></td>
                                <td>
                                    <span class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                                        <?php echo esc_html(Subs_Subscription::get_statuses()[$subscription->status] ?? $subscription->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $subscription->next_payment_date ?
                                        esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))) :
                                        __('N/A', 'subs'); ?>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->created_date))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=subs-edit-subscription&id=' . $subscription->id)); ?>" class="button button-small">
                                        <?php _e('Edit', 'subs'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9"><?php _e('No subscriptions found.', 'subs'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .subscription-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active { background: #46b450; color: white; }
        .status-cancelled { background: #dc3232; color: white; }
        .status-paused { background: #ffb900; color: white; }
        .status-trialing { background: #00a0d2; color: white; }
        .status-past_due { background: #f56e28; color: white; }
        </style>
        <?php
    }

    /**
     * Render add subscription form
     *
     * @since 1.0.0
     */
    private function render_add_subscription_form() {
        // Get customers for dropdown
        global $wpdb;
        $customers_table = $wpdb->prefix . 'subs_customers';
        $customers = $wpdb->get_results("SELECT id, email, first_name, last_name FROM $customers_table ORDER BY email");

        ?>
        <div class="wrap">
            <h1><?php _e('Add New Subscription', 'subs'); ?></h1>

            <?php settings_errors('subs_admin'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('subs_add_subscription'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="customer_id"><?php _e('Customer', 'subs'); ?> <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <select name="customer_id" id="customer_id" required style="min-width: 300px;">
                                <option value=""><?php _e('Select a customer', 'subs'); ?></option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo esc_attr($customer->id); ?>">
                                        <?php echo esc_html($customer->email); ?>
                                        <?php if ($customer->first_name || $customer->last_name): ?>
                                            - <?php echo esc_html(trim($customer->first_name . ' ' . $customer->last_name)); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select the customer for this subscription.', 'subs'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="product_name"><?php _e('Product Name', 'subs'); ?> <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <input name="product_name" type="text" id="product_name" value="" class="regular-text" required />
                            <p class="description"><?php _e('Enter the name of the subscription product.', 'subs'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="amount"><?php _e('Amount', 'subs'); ?> <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <input name="amount" type="number" id="amount" value="" step="0.01" min="0.01" class="small-text" required />
                            <select name="currency" id="currency">
                                <?php
                                $stripe = new Subs_Stripe();
                                $currencies = $stripe->get_supported_currencies();
                                foreach ($currencies as $code => $name): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected('USD', $code); ?>>
                                        <?php echo esc_html($code); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('The subscription amount per billing period.', 'subs'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="billing_period"><?php _e('Billing Period', 'subs'); ?></label>
                        </th>
                        <td>
                            <?php _e('Every', 'subs'); ?>
                            <input name="billing_interval" type="number" id="billing_interval" value="1" min="1" class="small-text" />
                            <select name="billing_period" id="billing_period">
                                <?php foreach (Subs_Subscription::get_billing_periods() as $period => $label): ?>
                                    <option value="<?php echo esc_attr($period); ?>" <?php selected('month', $period); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('How often the subscription will be billed.', 'subs'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="trial_end"><?php _e('Trial End Date', 'subs'); ?></label>
                        </th>
                        <td>
                            <input name="trial_end" type="datetime-local" id="trial_end" value="" />
                            <p class="description"><?php _e('Optional: Set a trial period end date. Leave empty for no trial.', 'subs'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Create Subscription', 'subs')); ?>
            </form>
        </div>
        <?php
    }


    /**
     * Render admin footer text
     *
     * @param string $footer_text
     * @return string
     * @since 1.0.0
     */
    public function admin_footer_text($footer_text) {
        $screen = get_current_screen();

        if ($screen && strpos($screen->id, 'subs') !== false) {
            $footer_text = sprintf(
                __('Thank you for using %s! Please %s if you like the plugin.', 'subs'),
                '<strong>' . __('Subs', 'subs') . '</strong>',
                '<a href="https://wordpress.org/support/plugin/subs/reviews/?rate=5#new-post" target="_blank">' . __('rate us ★★★★★', 'subs') . '</a>'
            );
        }

        return $footer_text;
    }

    /**
     * Get admin page URL
     *
     * @param string $page
     * @param array $args
     * @return string
     * @since 1.0.0
     */
    public function get_admin_url($page = '', $args = array()) {
        $page = $page ?: 'subs';
        $url = admin_url('admin.php?page=' . $page);

        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }

    /**
     * Check if current user can manage subscriptions
     *
     * @return bool
     * @since 1.0.0
     */
    public function current_user_can_manage() {
        return current_user_can('manage_subs_subscriptions');
    }

    /**
     * Get subscription counts by status
     *
     * @return array
     * @since 1.0.0
     */
    public function get_subscription_counts() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'subs_subscriptions';

        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status"
        );

        $result = array();
        foreach ($counts as $count) {
            $result[$count->status] = (int) $count->count;
        }

        return $result;
    }

    /**
     * Get revenue statistics
     *
     * @param string $period
     * @return array
     * @since 1.0.0
     */
    public function get_revenue_stats($period = 'month') {
        global $wpdb;

        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        $date_format = $period === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $date_interval = $period === 'month' ? '12 MONTH' : '30 DAY';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE_FORMAT(processed_date, %s) as period,
                COUNT(*) as payment_count,
                SUM(amount) as total_revenue
             FROM $payment_logs_table
             WHERE processed_date >= DATE_SUB(NOW(), INTERVAL $date_interval)
             AND status = 'succeeded'
             GROUP BY DATE_FORMAT(processed_date, %s)
             ORDER BY period DESC",
            $date_format,
            $date_format
        ));
    }

    /**
     * Display upgrade notices
     *
     * @since 1.0.0
     */
    public function display_upgrade_notice() {
        $current_version = get_option('subs_version', '0.0.0');

        if (version_compare($current_version, SUBS_VERSION, '<')) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Subs Plugin Updated!', 'subs'); ?></strong>
                    <?php printf(__('Version %s includes new features and improvements.', 'subs'), SUBS_VERSION); ?>
                    <a href="#" class="subs-dismiss-upgrade-notice" data-nonce="<?php echo wp_create_nonce('subs_dismiss_upgrade_notice'); ?>">
                        <?php _e('Dismiss', 'subs'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Handle admin notices dismissal
     *
     * @since 1.0.0
     */
    public function handle_notice_dismissal() {
        if (isset($_POST['action']) && $_POST['action'] === 'subs_dismiss_notice') {
            check_ajax_referer('subs_dismiss_upgrade_notice');

            $notice_type = sanitize_text_field($_POST['notice_type']);
            update_user_meta(get_current_user_id(), 'subs_dismissed_' . $notice_type, true);

            wp_send_json_success();
        }
    }

    /**
     * Add admin bar menu
     *
     * @param WP_Admin_Bar $wp_admin_bar
     * @since 1.0.0
     */
    public function admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_subs_subscriptions')) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id' => 'subs',
            'title' => __('Subscriptions', 'subs'),
            'href' => admin_url('admin.php?page=subs'),
        ));

        $wp_admin_bar->add_node(array(
            'parent' => 'subs',
            'id' => 'subs-dashboard',
            'title' => __('Dashboard', 'subs'),
            'href' => admin_url('admin.php?page=subs'),
        ));

        $wp_admin_bar->add_node(array(
            'parent' => 'subs',
            'id' => 'subs-add-new',
            'title' => __('Add New', 'subs'),
            'href' => admin_url('admin.php?page=subs-add-subscription'),
        ));

        $wp_admin_bar->add_node(array(
            'parent' => 'subs',
            'id' => 'subs-settings',
            'title' => __('Settings', 'subs'),
            'href' => admin_url('admin.php?page=subs-settings'),
        ));
    }

    /**
     * Register dashboard widgets
     *
     * @since 1.0.0
     */
    public function add_dashboard_widgets() {
        if (!current_user_can('manage_subs_subscriptions')) {
            return;
        }

        wp_add_dashboard_widget(
            'subs_dashboard_widget',
            __('Subscription Overview', 'subs'),
            array($this, 'dashboard_widget_content')
        );
    }

    /**
     * Dashboard widget content
     *
     * @since 1.0.0
     */
    public function dashboard_widget_content() {
        $stats = $this->get_dashboard_stats();

        ?>
        <div class="subs-dashboard-widget">
            <div class="subs-widget-stats">
                <div class="subs-stat">
                    <span class="subs-stat-number"><?php echo esc_html($stats['active'] ?? 0); ?></span>
                    <span class="subs-stat-label"><?php _e('Active Subscriptions', 'subs'); ?></span>
                </div>

                <div class="subs-stat">
                    <span class="subs-stat-number"><?php echo esc_html($this->format_currency($stats['monthly_revenue'] ?? 0)); ?></span>
                    <span class="subs-stat-label"><?php _e('Monthly Revenue', 'subs'); ?></span>
                </div>

                <div class="subs-stat">
                    <span class="subs-stat-number"><?php echo esc_html($stats['total_customers'] ?? 0); ?></span>
                    <span class="subs-stat-label"><?php _e('Total Customers', 'subs'); ?></span>
                </div>
            </div>

            <div class="subs-widget-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=subs')); ?>" class="button">
                    <?php _e('View Dashboard', 'subs'); ?>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=subs-add-subscription')); ?>" class="button button-primary">
                    <?php _e('Add Subscription', 'subs'); ?>
                </a>
            </div>
        </div>

        <style>
        .subs-dashboard-widget {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .subs-widget-stats {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .subs-stat {
            text-align: center;
            flex: 1;
        }

        .subs-stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }

        .subs-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .subs-widget-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        </style>
        <?php
    }

    /**
     * Add custom admin columns for subscriptions
     *
     * @since 1.0.0
     */
    public function add_admin_columns() {
        // This would be implemented if we were extending WP_List_Table
        // For now, we're using custom table rendering
    }

    /**
     * Show admin warnings for configuration issues
     *
     * @since 1.0.0
     */
    public function show_configuration_warnings() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'subs') === false) {
            return;
        }

        $warnings = array();

        // Check Stripe configuration
        $stripe_settings = get_option('subs_stripe_settings', array());
        if (empty($stripe_settings['enabled']) || $stripe_settings['enabled'] !== 'yes') {
            $warnings[] = array(
                'type' => 'warning',
                'message' => sprintf(
                    __('Stripe is not enabled. %s to start processing payments.', 'subs'),
                    '<a href="' . esc_url(admin_url('admin.php?page=subs-settings&tab=stripe')) . '">' . __('Configure Stripe', 'subs') . '</a>'
                )
            );
        } elseif (empty($stripe_settings['publishable_key']) || empty($stripe_settings['secret_key'])) {
            $warnings[] = array(
                'type' => 'error',
                'message' => sprintf(
                    __('Stripe API keys are missing. %s to complete setup.', 'subs'),
                    '<a href="' . esc_url(admin_url('admin.php?page=subs-settings&tab=stripe')) . '">' . __('Add API keys', 'subs') . '</a>'
                )
            );
        }

        // Check database tables
        if (!Subs_Install::tables_exist()) {
            $warnings[] = array(
                'type' => 'error',
                'message' => sprintf(
                    __('Database tables are missing. %s to repair.', 'subs'),
                    '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=subs&do_update_subs_db=1'), 'subs_db_update')) . '">' . __('Repair now', 'subs') . '</a>'
                )
            );
        }

        // Display warnings
        foreach ($warnings as $warning) {
            ?>
            <div class="notice notice-<?php echo esc_attr($warning['type']); ?>">
                <p><?php echo wp_kses_post($warning['message']); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Handle plugin activation redirect
     *
     * @since 1.0.0
     */
    public function activation_redirect() {
        if (get_transient('subs_activation_redirect')) {
            delete_transient('subs_activation_redirect');

            if (!isset($_GET['activate-multi'])) {
                wp_redirect(admin_url('admin.php?page=subs&welcome=1'));
                exit;
            }
        }
    }

    /**
     * Show welcome message after activation
     *
     * @since 1.0.0
     */
    public function show_welcome_message() {
        if (isset($_GET['welcome']) && $_GET['welcome'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <h3><?php _e('Welcome to Subs!', 'subs'); ?></h3>
                <p><?php _e('Thank you for installing the Subs subscription management plugin.', 'subs'); ?></p>
                <p>
                    <strong><?php _e('Next steps:', 'subs'); ?></strong>
                </p>
                <ol>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-settings&tab=stripe')); ?>">
                            <?php _e('Configure Stripe integration', 'subs'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-settings&tab=emails')); ?>">
                            <?php _e('Set up email notifications', 'subs'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-add-subscription')); ?>">
                            <?php _e('Create your first subscription', 'subs'); ?>
                        </a>
                    </li>
                </ol>
            </div>
            <?php
        }
    }

    /**
     * Initialize admin functionality
     *
     * @since 1.0.0
     */
    public function init_admin_hooks() {
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));

        // Admin bar
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 100);

        // Activation redirect
        add_action('admin_init', array($this, 'activation_redirect'));

        // Welcome message
        add_action('admin_notices', array($this, 'show_welcome_message'));

        // Configuration warnings
        add_action('admin_notices', array($this, 'show_configuration_warnings'));

        // Notice dismissal
        add_action('wp_ajax_subs_dismiss_notice', array($this, 'handle_notice_dismissal'));
    }

    /**
    * Render email settings
    *
    * @since 1.0.0
    */
    private function render_email_settings() {
      $settings = get_option('subs_email_settings', array()); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="from_name"><?php _e('From Name', 'subs'); ?></label>
            </th>
            <td>
                <input name="subs_email_settings[from_name]" type="text" id="from_name"
                       value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>" class="regular-text" />
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="from_email"><?php _e('From Email', 'subs'); ?></label>
            </th>
            <td>
                <input name="subs_email_settings[from_email]" type="email" id="from_email"
                       value="<?php echo esc_attr($settings['from_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
            </td>
        </tr>

        <tr>
            <th scope="row"><?php _e('Email Notifications', 'subs'); ?></th>
            <td>
                <fieldset>
                    <label>
                        <input name="subs_email_settings[subscription_created_enabled]" type="checkbox" value="yes"
                               <?php checked($settings['subscription_created_enabled'] ?? 'yes', 'yes'); ?> />
                        <?php _e('Subscription Created', 'subs'); ?>
                    </label><br>
                    <label>
                                <input name="subs_email_settings[subscription_cancelled_enabled]" type="checkbox" value="yes"
                                       <?php checked($settings['subscription_cancelled_enabled'] ?? 'yes', 'yes'); ?> />
                                <?php _e('Subscription Cancelled', 'subs'); ?>
                            </label><br>

                            <label>
                                <input name="subs_email_settings[payment_succeeded_enabled]" type="checkbox" value="yes"
                                       <?php checked($settings['payment_succeeded_enabled'] ?? 'yes', 'yes'); ?> />
                                <?php _e('Payment Succeeded', 'subs'); ?>
                            </label><br>

                            <label>
                                <input name="subs_email_settings[payment_failed_enabled]" type="checkbox" value="yes"
                                       <?php checked($settings['payment_failed_enabled'] ?? 'yes', 'yes'); ?> />
                                <?php _e('Payment Failed', 'subs'); ?>
                            </label><br>

                            <label>
                                <input name="subs_email_settings[customer_welcome_enabled]" type="checkbox" value="yes"
                                       <?php checked($settings['customer_welcome_enabled'] ?? 'yes', 'yes'); ?> />
                                <?php _e('Customer Welcome', 'subs'); ?>
                            </label><br>

                            <label>
                                <input name="subs_email_settings[trial_ending_enabled]" type="checkbox" value="yes"
                                       <?php checked($settings['trial_ending_enabled'] ?? 'yes', 'yes'); ?> />
                                <?php _e('Trial Ending', 'subs'); ?>
                            </label><br>

                            <label>
                                <input name="subs_email_settings[admin_notifications_enabled]" type="checkbox" value="yes"
                                       <?php checked($settings['admin_notifications_enabled'] ?? 'yes', 'yes'); ?> />
                                <?php _e('Admin Notifications', 'subs'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="test-email" class="button">
                    <?php _e('Send Test Email', 'subs'); ?>
                </button>
                <span id="email-test-result"></span>
            </p>

            <script>
            document.getElementById('test-email').addEventListener('click', function() {
                var button = this;
                var result = document.getElementById('email-test-result');
                var adminEmail = '<?php echo esc_js(get_option('admin_email')); ?>';

                button.disabled = true;
                button.textContent = '<?php echo esc_js(__('Sending...', 'subs')); ?>';

                fetch(ajaxurl, {
                    method: 'POST',
                    body: new FormData(Object.assign(document.createElement('form'), {
                        innerHTML: `<input name="action" value="subs_test_email">
                                   <input name="nonce" value="<?php echo wp_create_nonce('subs_admin_nonce'); ?>">
                                   <input name="email" value="${adminEmail}">`
                    }))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        result.innerHTML = '<span style="color: green;">✓ Test email sent to ' + adminEmail + '</span>';
                    } else {
                        result.innerHTML = '<span style="color: red;">✗ ' + data.data + '</span>';
                    }
                })
                .catch(error => {
                    result.innerHTML = '<span style="color: red;">✗ Failed to send test email</span>';
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = '<?php echo esc_js(__('Send Test Email', 'subs')); ?>';
                });
            });
            </script>
            <?php
        }

        /**
         * Handle add subscription form submission
         *
         * @since 1.0.0
         */
        private function handle_add_subscription() {
            // Sanitize input data
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
            $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '';
            $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
            $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'USD';
            $billing_period = isset($_POST['billing_period']) ? sanitize_text_field($_POST['billing_period']) : 'month';
            $billing_interval = isset($_POST['billing_interval']) ? intval($_POST['billing_interval']) : 1;
            $trial_end = isset($_POST['trial_end']) && !empty($_POST['trial_end']) ?
                         sanitize_text_field($_POST['trial_end']) : null;

            // Validate required fields
            $errors = array();
            if (empty($customer_id)) {
                $errors[] = __('Customer is required.', 'subs');
            }
            if (empty($product_name)) {
                $errors[] = __('Product name is required.', 'subs');
            }
            if ($amount <= 0) {
                $errors[] = __('Amount must be greater than 0.', 'subs');
            }

            // Display errors if any
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    add_settings_error('subs_admin', 'subscription_error', $error, 'error');
                }
                return;
            }

            // Create subscription
            $subscription_handler = new Subs_Subscription();
            $subscription_data = array(
                'customer_id' => $customer_id,
                'product_name' => $product_name,
                'amount' => $amount,
                'currency' => $currency,
                'billing_period' => $billing_period,
                'billing_interval' => $billing_interval,
                'trial_end' => $trial_end,
            );

            $result = $subscription_handler->create_subscription($subscription_data);

            if (is_wp_error($result)) {
                add_settings_error('subs_admin', 'subscription_error', $result->get_error_message(), 'error');
            } else {
                // Redirect to edit page
                wp_redirect(admin_url('admin.php?page=subs-edit-subscription&id=' . $result . '&created=1'));
                exit;
            }
        }

        /**
         * Handle edit subscription form submission
         *
         * @param int $subscription_id
         * @since 1.0.0
         */
        private function handle_edit_subscription($subscription_id) {
            $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : 'update';

            $subscription_handler = new Subs_Subscription();

            switch ($action) {
                case 'update':
                    $update_data = array();

                    // Only update fields that are present and different
                    $fields = array('product_name', 'amount', 'currency', 'billing_period', 'billing_interval');
                    foreach ($fields as $field) {
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

                    if (!empty($update_data)) {
                        $result = $subscription_handler->update_subscription($subscription_id, $update_data);
                        if (is_wp_error($result)) {
                            add_settings_error('subs_admin', 'subscription_error', $result->get_error_message(), 'error');
                        } else {
                            add_settings_error('subs_admin', 'subscription_updated', __('Subscription updated successfully.', 'subs'), 'updated');
                        }
                    }
                    break;

                case 'cancel':
                    $reason = isset($_POST['cancellation_reason']) ? sanitize_textarea_field($_POST['cancellation_reason']) : '';
                    $immediate = isset($_POST['cancel_immediately']) && $_POST['cancel_immediately'] === '1';

                    $result = $subscription_handler->cancel_subscription($subscription_id, $reason, $immediate);
                    if (is_wp_error($result)) {
                        add_settings_error('subs_admin', 'subscription_error', $result->get_error_message(), 'error');
                    } else {
                        add_settings_error('subs_admin', 'subscription_cancelled', __('Subscription cancelled successfully.', 'subs'), 'updated');
                    }
                    break;

                case 'pause':
                    $reason = isset($_POST['pause_reason']) ? sanitize_textarea_field($_POST['pause_reason']) : '';

                    $result = $subscription_handler->pause_subscription($subscription_id, $reason);
                    if (is_wp_error($result)) {
                        add_settings_error('subs_admin', 'subscription_error', $result->get_error_message(), 'error');
                    } else {
                        add_settings_error('subs_admin', 'subscription_paused', __('Subscription paused successfully.', 'subs'), 'updated');
                    }
                    break;

                case 'resume':
                    $result = $subscription_handler->resume_subscription($subscription_id);
                    if (is_wp_error($result)) {
                        add_settings_error('subs_admin', 'subscription_error', $result->get_error_message(), 'error');
                    } else {
                        add_settings_error('subs_admin', 'subscription_resumed', __('Subscription resumed successfully.', 'subs'), 'updated');
                    }
                    break;
            }
        }

        /**
         * Save settings
         *
         * @since 1.0.0
         */
        private function save_settings() {
            if (!check_admin_referer('subs_settings')) {
                return;
            }

            $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'general';

            switch ($tab) {
                case 'general':
                    if (isset($_POST['subs_general_settings'])) {
                        $settings = array_map('sanitize_text_field', $_POST['subs_general_settings']);
                        update_option('subs_general_settings', $settings);
                    }
                    break;

                case 'stripe':
                    if (isset($_POST['subs_stripe_settings'])) {
                        $settings = array_map('sanitize_text_field', $_POST['subs_stripe_settings']);
                        // Handle checkbox values
                        $settings['enabled'] = isset($_POST['subs_stripe_settings']['enabled']) ? 'yes' : 'no';
                        $settings['test_mode'] = isset($_POST['subs_stripe_settings']['test_mode']) ? 'yes' : 'no';
                        update_option('subs_stripe_settings', $settings);
                    }
                    break;

                case 'emails':
                    if (isset($_POST['subs_email_settings'])) {
                        $settings = array_map('sanitize_text_field', $_POST['subs_email_settings']);
                        // Handle checkbox values
                        $checkboxes = array(
                            'subscription_created_enabled',
                            'subscription_cancelled_enabled',
                            'payment_succeeded_enabled',
                            'payment_failed_enabled',
                            'customer_welcome_enabled',
                            'trial_ending_enabled',
                            'admin_notifications_enabled'
                        );
                        foreach ($checkboxes as $checkbox) {
                            $settings[$checkbox] = isset($_POST['subs_email_settings'][$checkbox]) ? 'yes' : 'no';
                        }
                        update_option('subs_email_settings', $settings);
                    }
                    break;
            }

            add_settings_error('subs_admin', 'settings_saved', __('Settings saved successfully.', 'subs'), 'updated');
        }

        /**
         * Handle export requests
         *
         * @since 1.0.0
         */
        private function handle_export() {
            $export_type = sanitize_text_field($_GET['subs_export']);

            switch ($export_type) {
                case 'subscriptions':
                    $this->export_subscriptions_csv();
                    break;
                case 'customers':
                    $customer_handler = new Subs_Customer();
                    $customer_handler->export_customers_csv();
                    break;
            }
        }

        /**
         * Handle bulk actions
         *
         * @since 1.0.0
         */
        public function handle_bulk_actions() {
            // Implement bulk actions for subscriptions list
            if (isset($_POST['action']) && $_POST['action'] !== '-1') {
                $action = sanitize_text_field($_POST['action']);
                $subscription_ids = isset($_POST['subscription']) ? array_map('intval', $_POST['subscription']) : array();

                if (!empty($subscription_ids) && current_user_can('manage_subs_subscriptions')) {
                    $subscription_handler = new Subs_Subscription();
                    $processed = 0;

                    foreach ($subscription_ids as $subscription_id) {
                        switch ($action) {
                            case 'cancel':
                                $result = $subscription_handler->cancel_subscription($subscription_id, 'Bulk cancellation');
                                if (!is_wp_error($result)) {
                                    $processed++;
                                }
                                break;
                            case 'pause':
                                $result = $subscription_handler->pause_subscription($subscription_id, 'Bulk pause');
                                if (!is_wp_error($result)) {
                                    $processed++;
                                }
                                break;
                        }
                    }

                    if ($processed > 0) {
                        add_settings_error('subs_admin', 'bulk_action_completed',
                            sprintf(__('Successfully processed %d subscriptions.', 'subs'), $processed), 'updated');
                    }
                }
            }
        }

        /**
         * Handle admin AJAX requests
         *
         * @since 1.0.0
         */
        public function handle_admin_ajax() {
            // Verify nonce
            check_ajax_referer('subs_admin_nonce', 'nonce');

            $subs_action = isset($_POST['subs_action']) ? sanitize_text_field($_POST['subs_action']) : '';

            switch ($subs_action) {
                case 'search_customers':
                    $this->ajax_search_customers();
                    break;
                case 'get_subscription_details':
                    $this->ajax_get_subscription_details();
                    break;
                case 'test_stripe_connection':
                    $this->ajax_test_stripe_connection();
                    break;
                case 'test_email':
                    $this->ajax_test_email();
                    break;
                default:
                    wp_send_json_error(__('Invalid action', 'subs'));
            }
        }

        /**
         * AJAX search customers
         *
         * @since 1.0.0
         */
        private function ajax_search_customers() {
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

            if (empty($search)) {
                wp_send_json_error(__('Search term is required', 'subs'));
            }

            $customer_handler = new Subs_Customer();
            $customers = $customer_handler->search_customers(array(
                'search' => $search,
                'limit' => 20,
            ));

            wp_send_json_success($customers);
        }

        /**
         * AJAX get subscription details
         *
         * @since 1.0.0
         */
        private function ajax_get_subscription_details() {
            $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;

            if (!$subscription_id) {
                wp_send_json_error(__('Subscription ID is required', 'subs'));
            }

            $subscription_handler = new Subs_Subscription();
            $subscription = $subscription_handler->get_subscription($subscription_id);

            if (!$subscription) {
                wp_send_json_error(__('Subscription not found', 'subs'));
            }

            wp_send_json_success($subscription);
        }

        /**
         * AJAX test Stripe connection
         *
         * @since 1.0.0
         */
        private function ajax_test_stripe_connection() {
            $stripe = new Subs_Stripe();
            $result = $stripe->test_connection();

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(__('Stripe connection successful!', 'subs'));
        }

        /**
         * AJAX test email
         *
         * @since 1.0.0
         */
        private function ajax_test_email() {
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : get_option('admin_email');

            if (!is_email($email)) {
                wp_send_json_error(__('Invalid email address', 'subs'));
            }

            $emails = new Subs_Emails();
            $result = $emails->test_email($email);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(__('Test email sent successfully!', 'subs'));
        }

        /**
         * Enqueue admin scripts and styles
         *
         * @param string $hook
         * @since 1.0.0
         */
        public function admin_scripts($hook) {
            // Only load on plugin pages
            if (strpos($hook, 'subs') === false) {
                return;
            }

            // Enqueue admin CSS
            wp_enqueue_style(
                'subs-admin',
                SUBS_ASSETS_URL . 'css/admin.css',
                array(),
                SUBS_VERSION
            );

            // Enqueue admin JS
            wp_enqueue_script(
                'subs-admin',
                SUBS_ASSETS_URL . 'js/admin.js',
                array('jquery'),
                SUBS_VERSION,
                true
            );

            // Localize script
            wp_localize_script('subs-admin', 'subs_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('subs_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'subs'),
                    'processing' => __('Processing...', 'subs'),
                    'error' => __('An error occurred. Please try again.', 'subs'),
                ),
            ));
        }

        /**
         * Display admin notices
         *
         * @since 1.0.0
         */
        public function admin_notices() {
            // Check if Stripe is configured
            $stripe_settings = get_option('subs_stripe_settings', array());
            if (empty($stripe_settings['publishable_key']) || empty($stripe_settings['secret_key'])) {
                $current_screen = get_current_screen();
                if ($current_screen && strpos($current_screen->id, 'subs') !== false) {
                    ?>
                    <div class="notice notice-warning">
                        <p>
                            <?php _e('Subs plugin requires Stripe configuration to process payments.', 'subs'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=subs-settings&tab=stripe')); ?>">
                                <?php _e('Configure Stripe now', 'subs'); ?>
                            </a>
                        </p>
                    </div>
                    <?php
                }
            }

            // Show update notice if needed
            if (isset($_GET['updated']) && $_GET['updated'] === '1') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Database updated successfully!', 'subs'); ?></p>
                </div>
                <?php
            }
        }

        /**
         * Database update notice
         *
         * @since 1.0.0
         */
        public function database_update_notice() {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Subs database needs to be updated to the latest version.', 'subs'); ?>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=subs&do_update_subs_db=1'), 'subs_db_update')); ?>" class="button button-primary">
                        <?php _e('Update Database', 'subs'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        /**
         * Add help tabs to admin pages
         *
         * @since 1.0.0
         */
        public function add_help_tabs() {
            $screen = get_current_screen();

            if (!$screen || strpos($screen->id, 'subs') === false) {
                return;
            }

            // Add contextual help based on current page
            switch ($screen->id) {
                case 'toplevel_page_subs':
                    $screen->add_help_tab(array(
                        'id' => 'subs_dashboard_help',
                        'title' => __('Dashboard', 'subs'),
                        'content' => '<p>' . __('The dashboard provides an overview of your subscription metrics, recent activity, and key performance indicators.', 'subs') . '</p>',
                    ));
                    break;

                case 'subscriptions_page_subs-subscriptions':
                    $screen->add_help_tab(array(
                        'id' => 'subs_subscriptions_help',
                        'title' => __('Managing Subscriptions', 'subs'),
                        'content' => '<p>' . __('Here you can view, edit, pause, resume, and cancel subscriptions. Use the bulk actions to perform operations on multiple subscriptions at once.', 'subs') . '</p>',
                    ));
                    break;

                case 'subscriptions_page_subs-customers':
                    $screen->add_help_tab(array(
                        'id' => 'subs_customers_help',
                        'title' => __('Customer Management', 'subs'),
                        'content' => '<p>' . __('Manage your subscription customers, view their subscription history, and update their information.', 'subs') . '</p>',
                    ));
                    break;

                case 'subscriptions_page_subs-settings':
                    $screen->add_help_tab(array(
                        'id' => 'subs_settings_help',
                        'title' => __('Plugin Settings', 'subs'),
                        'content' => '<p>' . __('Configure your subscription plugin settings including Stripe integration, email notifications, and general preferences.', 'subs') . '</p>',
                    ));
                    break;
            }

            // Add support sidebar to all pages
            $screen->set_help_sidebar(
                '<p><strong>' . __('For more information:', 'subs') . '</strong></p>' .
                '<p><a href="https://yoursite.com/docs/subs" target="_blank">' . __('Plugin Documentation', 'subs') . '</a></p>' .
                '<p><a href="https://yoursite.com/support" target="_blank">' . __('Support Forum', 'subs') . '</a></p>'
            );
        }

        /**
         * Get dashboard statistics
         *
         * @return array
         * @since 1.0.0
         */
        private function get_dashboard_stats() {
            global $wpdb;

            $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
            $customers_table = $wpdb->prefix . 'subs_customers';
            $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

            $stats = array();

            // Active subscriptions
            $stats['active'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM $subscriptions_table WHERE status = 'active'"
            );

            // Total customers
            $stats['total_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM $customers_table");

            // New this month
            $stats['new_this_month'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM $subscriptions_table
                 WHERE created_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
            );

            // Monthly revenue (from successful payments this month)
            $stats['monthly_revenue'] = $wpdb->get_var(
                "SELECT SUM(amount) FROM $payment_logs_table
                 WHERE status = 'succeeded'
                 AND processed_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
            ) ?: 0;

            return $stats;
        }

        /**
         * Get recent activity
         *
         * @param int $limit
         * @return array
         * @since 1.0.0
         */
        public function get_recent_activity($limit = 10) {
            global $wpdb;

            $history_table = $wpdb->prefix . 'subs_subscription_history';
            $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
            $customers_table = $wpdb->prefix . 'subs_customers';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT h.*, s.product_name, c.email as customer_email
                 FROM $history_table h
                 LEFT JOIN $subscriptions_table s ON h.subscription_id = s.id
                 LEFT JOIN $customers_table c ON s.customer_id = c.id
                 ORDER BY h.created_date DESC
                 LIMIT %d",
                $limit
            ));
        }

        /**
         * Export subscriptions to CSV
         *
         * @param array $filters
         * @since 1.0.0
         */
        public function export_subscriptions_csv($filters = array()) {
            global $wpdb;

            $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
            $customers_table = $wpdb->prefix . 'subs_customers';

            $where = array('1=1');
            $values = array();

            // Apply filters
            if (!empty($filters['status'])) {
                $where[] = 's.status = %s';
                $values[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = 's.created_date >= %s';
                $values[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = 's.created_date <= %s';
                $values[] = $filters['date_to'];
            }

            $where_clause = implode(' AND ', $where);

            $query = "
                SELECT
                    s.id,
                    s.status,
                    s.product_name,
                    s.amount,
                    s.currency,
                    s.billing_period,
                    s.billing_interval,
                    s.created_date,
                    s.current_period_start,
                    s.current_period_end,
                    s.next_payment_date,
                    c.email as customer_email,
                    c.first_name,
                    c.last_name
                FROM $subscriptions_table s
                LEFT JOIN $customers_table c ON s.customer_id = c.id
                WHERE $where_clause
                ORDER BY s.created_date DESC
            ";

            $results = empty($values) ?
                $wpdb->get_results($query) :
                $wpdb->get_results($wpdb->prepare($query, ...$values));

            // Generate CSV
            $filename = 'subscriptions_export_' . date('Y-m-d_H-i-s') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // CSV headers
            $headers = array(
                'ID', 'Status', 'Product Name', 'Amount', 'Currency',
                'Billing Period', 'Billing Interval', 'Created Date',
                'Current Period Start', 'Current Period End', 'Next Payment Date',
                'Customer Email', 'Customer First Name', 'Customer Last Name'
            );

            fputcsv($output, $headers);

            // CSV data
            foreach ($results as $row) {
                fputcsv($output, array(
                    $row->id,
                    $row->status,
                    $row->product_name,
                    $row->amount,
                    $row->currency,
                    $row->billing_period,
                    $row->billing_interval,
                    $row->created_date,
                    $row->current_period_start,
                    $row->current_period_end,
                    $row->next_payment_date,
                    $row->customer_email,
                    $row->first_name,
                    $row->last_name
                ));
            }

            fclose($output);
            exit;
        }


        /**
         * Render customers list
         *
         * @since 1.0.0
         */
        private function render_customers_list() {
            $customer_handler = new Subs_Customer();

            // Handle search and filtering
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

            // Pagination
            $per_page = 20;
            $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($current_page - 1) * $per_page;

            $customers = $customer_handler->search_customers(array(
                'search' => $search,
                'limit' => $per_page,
                'offset' => $offset,
            ));

            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e('Customers', 'subs'); ?></h1>

                <!-- Search -->
                <form method="get" class="search-form">
                    <input type="hidden" name="page" value="subs-customers" />
                    <p class="search-box">
                        <label class="screen-reader-text" for="customer-search-input"><?php _e('Search Customers:', 'subs'); ?></label>
                        <input type="search" id="customer-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
                        <?php submit_button(__('Search', 'subs'), 'button', '', false, array('id' => 'search-submit')); ?>
                    </p>
                </form>

                <!-- Customers Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('ID', 'subs'); ?></th>
                            <th scope="col"><?php _e('Name', 'subs'); ?></th>
                            <th scope="col"><?php _e('Email', 'subs'); ?></th>
                            <th scope="col"><?php _e('Subscriptions', 'subs'); ?></th>
                            <th scope="col"><?php _e('Total Spent', 'subs'); ?></th>
                            <th scope="col"><?php _e('Created', 'subs'); ?></th>
                            <th scope="col"><?php _e('Actions', 'subs'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo esc_html($customer->id); ?></td>
                                    <td><?php echo esc_html($customer_handler->get_customer_display_name($customer)); ?></td>
                                    <td><?php echo esc_html($customer->email); ?></td>
                                    <td><?php echo esc_html($customer->subscription_count ?? 0); ?></td>
                                    <td><?php echo esc_html($this->format_currency($customer_handler->get_customer_total_spent($customer->id))); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($customer->created_date))); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-subscriptions&customer_id=' . $customer->id)); ?>" class="button button-small">
                                            <?php _e('View Subscriptions', 'subs'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7"><?php _e('No customers found.', 'subs'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        /**
         * Render reports
         *
         * @since 1.0.0
         */
        private function render_reports() {
            ?>
            <div class="wrap">
                <h1><?php _e('Reports', 'subs'); ?></h1>

                <div class="subs-reports-grid">
                    <!-- Revenue Report -->
                    <div class="subs-report-card">
                        <h3><?php _e('Revenue Overview', 'subs'); ?></h3>
                        <canvas id="revenue-chart"></canvas>
                    </div>

                    <!-- Subscription Growth -->
                    <div class="subs-report-card">
                        <h3><?php _e('Subscription Growth', 'subs'); ?></h3>
                        <canvas id="growth-chart"></canvas>
                    </div>

                    <!-- Status Distribution -->
                    <div class="subs-report-card">
                        <h3><?php _e('Status Distribution', 'subs'); ?></h3>
                        <canvas id="status-chart"></canvas>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="subs-export-section">
                    <h2><?php _e('Export Data', 'subs'); ?></h2>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-reports&subs_export=subscriptions')); ?>" class="button">
                            <?php _e('Export Subscriptions', 'subs'); ?>
                        </a>

                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-reports&subs_export=customers')); ?>" class="button">
                            <?php _e('Export Customers', 'subs'); ?>
                        </a>
                    </p>
                </div>
            </div>

            <style>
            .subs-reports-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }

            .subs-report-card {
                background: white;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }

            .subs-export-section {
                background: white;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            </style>

            <script>
            // Placeholder for chart initialization
            document.addEventListener('DOMContentLoaded', function() {
                // Charts would be initialized here with actual data
                console.log('Reports page loaded - charts would be initialized here');
            });
            </script>
            <?php
        }

        /**
         * Render settings
         *
         * @since 1.0.0
         */
        private function render_settings() {
            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

            $tabs = array(
                'general' => __('General', 'subs'),
                'stripe' => __('Stripe', 'subs'),
                'emails' => __('Emails', 'subs'),
            );

            ?>
            <div class="wrap">
                <h1><?php _e('Subscription Settings', 'subs'); ?></h1>

                <!-- Tabs -->
                <nav class="nav-tab-wrapper">
                    <?php foreach ($tabs as $tab_key => $tab_label): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=subs-settings&tab=' . $tab_key)); ?>"
                           class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                            <?php echo esc_html($tab_label); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Tab Content -->
                <form method="post" action="">
                    <?php wp_nonce_field('subs_settings'); ?>
                    <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>" />

                    <?php
                    switch ($active_tab) {
                        case 'general':
                            $this->render_general_settings();
                            break;
                        case 'stripe':
                            $this->render_stripe_settings();
                            break;
                        case 'emails':
                            $this->render_email_settings();
                            break;
                    }
                    ?>

                    <?php submit_button(__('Save Settings', 'subs'), 'primary', 'subs_settings_submit'); ?>
                </form>
            </div>
            <?php
        }

        /**
         * Render general settings
         *
         * @since 1.0.0
         */
        private function render_general_settings() {
            $settings = get_option('subs_general_settings', array());

            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="currency"><?php _e('Default Currency', 'subs'); ?></label>
                    </th>
                    <td>
                        <select name="subs_general_settings[currency]" id="currency">
                            <?php
                            $stripe = new Subs_Stripe();
                            $currencies = $stripe->get_supported_currencies();
                            foreach ($currencies as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['currency'] ?? 'USD', $code); ?>>
                                    <?php echo esc_html($code . ' - ' . $name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="currency_position"><?php _e('Currency Position', 'subs'); ?></label>
                    </th>
                    <td>
                        <select name="subs_general_settings[currency_position]" id="currency_position">
                            <option value="left" <?php selected($settings['currency_position'] ?? 'left', 'left'); ?>><?php _e('Left ($99.99)', 'subs'); ?></option>
                            <option value="right" <?php selected($settings['currency_position'] ?? 'left', 'right'); ?>><?php _e('Right (99.99$)', 'subs'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="decimal_separator"><?php _e('Decimal Separator', 'subs'); ?></label>
                    </th>
                    <td>
                        <input name="subs_general_settings[decimal_separator]" type="text" id="decimal_separator"
                               value="<?php echo esc_attr($settings['decimal_separator'] ?? '.'); ?>" class="small-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="thousand_separator"><?php _e('Thousand Separator', 'subs'); ?></label>
                    </th>
                    <td>
                        <input name="subs_general_settings[thousand_separator]" type="text" id="thousand_separator"
                               value="<?php echo esc_attr($settings['thousand_separator'] ?? ','); ?>" class="small-text" />
                    </td>
                </tr>
            </table>
            <?php
        }

        /**
         * Render Stripe settings
         *
         * @since 1.0.0
         */
        private function render_stripe_settings() {
            $settings = get_option('subs_stripe_settings', array());

            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="stripe_enabled"><?php _e('Enable Stripe', 'subs'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input name="subs_stripe_settings[enabled]" type="checkbox" id="stripe_enabled" value="yes"
                                   <?php checked($settings['enabled'] ?? 'no', 'yes'); ?> />
                            <?php _e('Enable Stripe payment processing', 'subs'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="test_mode"><?php _e('Test Mode', 'subs'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input name="subs_stripe_settings[test_mode]" type="checkbox" id="test_mode" value="yes"
                                   <?php checked($settings['test_mode'] ?? 'yes', 'yes'); ?> />
                            <?php _e('Enable test mode (use test API keys)', 'subs'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="live_publishable_key"><?php _e('Live Publishable Key', 'subs'); ?></label>
                    </th>
                    <td>
                        <input name="subs_stripe_settings[publishable_key]" type="text" id="live_publishable_key"
                               value="<?php echo esc_attr($settings['publishable_key'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="live_secret_key"><?php _e('Live Secret Key', 'subs'); ?></label>
                    </th>
                    <td>
                        <input name="subs_stripe_settings[secret_key]" type="password" id="live_secret_key"
                               value="<?php echo esc_attr($settings['secret_key'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="test_publishable_key"><?php _e('Test Publishable Key', 'subs'); ?></label>
                    </th>
                    <td>
                        <input name="subs_stripe_settings[test_publishable_key]" type="text" id="test_publishable_key"
                               value="<?php echo esc_attr($settings['test_publishable_key'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="test_secret_key"><?php _e('Test Secret Key', 'subs'); ?></label>
                    </th>
                    <td>
                        <input name="subs_stripe_settings[test_secret_key]" type="password" id="test_secret_key"
                               value="<?php echo esc_attr($settings['test_secret_key'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="webhook_secret"><?php _e('Webhook Secret', 'subs'); ?></label>
                    </th>
                    <td>
                        <input name="subs_stripe_settings[webhook_secret]" type="password" id="webhook_secret"
                               value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Webhook URL:', 'subs'); ?>
                            <code><?php echo esc_html(home_url('/?subs_stripe_webhook=1')); ?></code>
                        </p>
                    </td>
                </tr>
            </table>

            <?php if (!empty($settings['enabled']) && $settings['enabled'] === 'yes'): ?>
                <p>
                    <button type="button" id="test-stripe-connection" class="button">
                        <?php _e('Test Stripe Connection', 'subs'); ?>
                    </button>
                    <span id="stripe-test-result"></span>
                </p>

                <script>
                document.getElementById('test-stripe-connection').addEventListener('click', function() {
                    var button = this;
                    var result = document.getElementById('stripe-test-result');

                    button.disabled = true;
                    button.textContent = '<?php echo esc_js(__('Testing...', 'subs')); ?>';

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: new FormData(Object.assign(document.createElement('form'), {
                            innerHTML: `<input name="action" value="subs_test_stripe_connection">
                                       <input name="nonce" value="<?php echo wp_create_nonce('subs_admin_nonce'); ?>">`
                        }))
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            result.innerHTML = '<span style="color: green;">✓ ' + data.data + '</span>';
                        } else {
                            result.innerHTML = '<span style="color: red;">✗ ' + data.data + '</span>';
                        }
                    })
                    .catch(error => {
                        result.innerHTML = '<span style="color: red;">✗ Connection failed</span>';
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.textContent = '<?php echo esc_js(__('Test Stripe Connection', 'subs')); ?>';
                    });
                });
                </script>
            <?php endif; ?>
          }
        }              
