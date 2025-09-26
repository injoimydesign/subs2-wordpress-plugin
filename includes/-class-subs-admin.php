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
            wp_redirect(admin_url('admin.php?page=subs'));
            exit;
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

        // Include dashboard class if not already loaded
        if (!class_exists('Subs_Admin_Dashboard')) {
            include_once SUBS_PLUGIN_PATH . 'includes/admin/class-subs-admin-dashboard.php';
        }

        $dashboard = new Subs_Admin_Dashboard();
        $dashboard->display();
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

        // Include subscriptions class if not already loaded
        if (!class_exists('Subs_Admin_Subscriptions')) {
            include_once SUBS_PLUGIN_PATH . 'includes/admin/class-subs-admin-subscriptions.php';
        }

        $subscriptions_admin = new Subs_Admin_Subscriptions();
        $subscriptions_admin->display();
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

    // Include customers class if not already loaded
    if (!class_exists('Subs_Admin_Customers')) {
        include_once SUBS_PLUGIN_PATH . 'includes/admin/class-subs-admin-customers.php';
    }

    $customers_admin = new Subs_Admin_Customers();
    $customers_admin->display();
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

    // TODO: Implement reports functionality
    echo '<div class="wrap">';
    echo '<h1>' . __('Reports', 'subs') . '</h1>';
    echo '<p>' . __('Reports functionality coming soon.', 'subs') . '</p>';
    echo '</div>';
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

    // Include settings class if not already loaded
    if (!class_exists('Subs_Admin_Settings')) {
        include_once SUBS_PLUGIN_PATH . 'includes/admin/class-subs-admin-settings.php';
    }

    $settings_admin = new Subs_Admin_Settings();
    $settings_admin->display();
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
                        <select name="customer_id" id="customer_id" required>
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
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="CAD">CAD</option>
                            <option value="AUD">AUD</option>
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
                                <option value="<?php echo esc_attr($period); ?>"><?php echo esc_html($label); ?></option>
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
 * Render edit subscription form
 *
 * @param object $subscription
 * @since 1.0.0
 */
private function render_edit_subscription_form($subscription) {
    $subscription_handler = new Subs_Subscription();
    $history = $subscription_handler->get_subscription_history($subscription->id);

    ?>
    <div class="wrap">
        <h1>
            <?php _e('Edit Subscription', 'subs'); ?>
            <span class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                <?php echo esc_html(Subs_Subscription::get_statuses()[$subscription->status] ?? $subscription->status); ?>
            </span>
        </h1>

        <?php settings_errors('subs_admin'); ?>

        <div class="subs-admin-columns">
            <!-- Main Column -->
            <div class="subs-admin-main-column">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Subscription Details', 'subs'); ?></h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('subs_edit_subscription'); ?>
                            <input type="hidden" name="action" value="update" />

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php _e('Customer', 'subs'); ?></th>
                                    <td>
                                        <?php echo esc_html($subscription->customer->email); ?>
                                        <?php if ($subscription->customer->first_name || $subscription->customer->last_name): ?>
                                            - <?php echo esc_html(trim($subscription->customer->first_name . ' ' . $subscription->customer->last_name)); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="product_name"><?php _e('Product Name', 'subs'); ?></label>
                                    </th>
                                    <td>
                                        <input name="product_name" type="text" id="product_name"
                                               value="<?php echo esc_attr($subscription->product_name); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="amount"><?php _e('Amount', 'subs'); ?></label>
                                    </th>
                                    <td>
                                        <input name="amount" type="number" id="amount"
                                               value="<?php echo esc_attr($subscription->amount); ?>"
                                               step="0.01" min="0.01" class="small-text" />
                                        <select name="currency" id="currency">
                                            <?php
                                            $currencies = array('USD', 'EUR', 'GBP', 'CAD', 'AUD');
                                            foreach ($currencies as $currency): ?>
                                                <option value="<?php echo esc_attr($currency); ?>"
                                                        <?php selected($subscription->currency, $currency); ?>>
                                                    <?php echo esc_html($currency); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="billing_period"><?php _e('Billing Period', 'subs'); ?></label>
                                    </th>
                                    <td>
                                        <?php _e('Every', 'subs'); ?>
                                        <input name="billing_interval" type="number" id="billing_interval"
                                               value="<?php echo esc_attr($subscription->billing_interval); ?>"
                                               min="1" class="small-text" />
                                        <select name="billing_period" id="billing_period">
                                            <?php foreach (Subs_Subscription::get_billing_periods() as $period => $label): ?>
                                                <option value="<?php echo esc_attr($period); ?>"
                                                        <?php selected($subscription->billing_period, $period); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button(__('Update Subscription', 'subs'), 'primary', 'submit', false); ?>
                        </form>
                    </div>
                </div>

                <!-- Subscription Actions -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Subscription Actions', 'subs'); ?></h2>
                    <div class="inside">
                        <?php if ($subscription_handler->can_pause($subscription)): ?>
                            <form method="post" action="" style="display: inline;">
                                <?php wp_nonce_field('subs_edit_subscription'); ?>
                                <input type="hidden" name="action" value="pause" />
                                <input type="text" name="pause_reason" placeholder="<?php esc_attr_e('Reason (optional)', 'subs'); ?>" />
                                <input type="submit" class="button" value="<?php esc_attr_e('Pause Subscription', 'subs'); ?>"
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to pause this subscription?', 'subs'); ?>');" />
                            </form>
                        <?php endif; ?>

                        <?php if ($subscription_handler->can_resume($subscription)): ?>
                            <form method="post" action="" style="display: inline;">
                                <?php wp_nonce_field('subs_edit_subscription'); ?>
                                <input type="hidden" name="action" value="resume" />
                                <input type="submit" class="button" value="<?php esc_attr_e('Resume Subscription', 'subs'); ?>" />
                            </form>
                        <?php endif; ?>

                        <?php if ($subscription_handler->can_cancel($subscription)): ?>
                            <form method="post" action="" style="display: inline;">
                                <?php wp_nonce_field('subs_edit_subscription'); ?>
                                <input type="hidden" name="action" value="cancel" />
                                <input type="text" name="cancellation_reason" placeholder="<?php esc_attr_e('Reason (optional)', 'subs'); ?>" />
                                <label>
                                    <input type="checkbox" name="cancel_immediately" value="1" />
                                    <?php _e('Cancel immediately', 'subs'); ?>
                                </label>
                                <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Cancel Subscription', 'subs'); ?>"
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to cancel this subscription?', 'subs'); ?>');" />
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="subs-admin-sidebar">
                <!-- Subscription Info -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Subscription Info', 'subs'); ?></h2>
                    <div class="inside">
                        <p><strong><?php _e('Status:', 'subs'); ?></strong>
                           <?php echo esc_html(Subs_Subscription::get_statuses()[$subscription->status] ?? $subscription->status); ?></p>
                        <p><strong><?php _e('Created:', 'subs'); ?></strong>
                           <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->created_date))); ?></p>
                        <p><strong><?php _e('Current Period:', 'subs'); ?></strong>
                           <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->current_period_start))); ?>
                           - <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))); ?></p>
                        <?php if ($subscription->next_payment_date): ?>
                            <p><strong><?php _e('Next Payment:', 'subs'); ?></strong>
                               <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))); ?></p>
                        <?php endif; ?>
                        <?php if ($subscription->stripe_subscription_id): ?>
                            <p><strong><?php _e('Stripe ID:', 'subs'); ?></strong>
                               <code><?php echo esc_html($subscription->stripe_subscription_id); ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Subscription History -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Recent History', 'subs'); ?></h2>
                    <div class="inside">
                        <?php if (!empty($history)): ?>
                            <ul class="subs-history-list">
                                <?php foreach (array_slice($history, 0, 10) as $entry): ?>
                                    <li>
                                        <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $entry->action))); ?></strong>
                                        <?php if ($entry->note): ?>
                                            <br /><span class="description"><?php echo esc_html($entry->note); ?></span>
                                        <?php endif; ?>
                                        <br /><span class="subs-history-date">
                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->created_date))); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p><?php _e('No history available.', 'subs'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .subs-admin-columns {
        display: flex;
        gap: 20px;
    }
    .subs-admin-main-column {
        flex: 2;
    }
    .subs-admin-sidebar {
        flex: 1;
    }
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
    .subs-history-list { margin: 0; }
    .subs-history-list li {
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .subs-history-list li:last-child { border-bottom: none; }
    .subs-history-date { font-size: 11px; color: #666; }
    </style>
    <?php
}

/**
 * Handle bulk actions
 *
 * @since 1.0.0
 */
public function handle_bulk_actions() {
    // This will be implemented when we create the list tables
    // for subscriptions and customers
}

/**
 * Handle admin AJAX requests
 *
 * @since 1.0.0
 */
public function handle_admin_ajax() {
    // Verify nonce
    check_ajax_referer('subs_admin_nonce', 'nonce');

    $action = isset($_POST['subs_action']) ? sanitize_text_field($_POST['subs_action']) : '';

    switch ($action) {
        case 'search_customers':
            $this->ajax_search_customers();
            break;
        case 'get_subscription_details':
            $this->ajax_get_subscription_details();
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

    global $wpdb;
    $customers_table = $wpdb->prefix . 'subs_customers';

    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT id, email, first_name, last_name
         FROM $customers_table
         WHERE email LIKE %s OR first_name LIKE %s OR last_name LIKE %s
         ORDER BY email
         LIMIT 20",
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%'
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
    // Success message for new installations
    if (get_transient('subs_admin_notice_install')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Subs plugin has been successfully installed and configured!', 'subs'); ?></p>
        </div>
        <?php
        delete_transient('subs_admin_notice_install');
    }

    // Check if Stripe is configured
    $stripe_settings = get_option('subs_stripe_settings', array());
    if (empty($stripe_settings['publishable_key']) || empty($stripe_settings['secret_key'])) {
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
            </a><?php
          }
        }
