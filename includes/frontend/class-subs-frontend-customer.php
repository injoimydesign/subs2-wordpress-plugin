<?php
/**
 * Frontend Customer Management Class
 *
 * Handles customer-related functionality for the frontend, including
 * customer portal, profile management, and account settings.
 *
 * @package Subs
 * @subpackage Frontend
 * @since 1.0.0
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs Frontend Customer Class
 *
 * @class Subs_Frontend_Customer
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Frontend_Customer {

    /**
     * Current customer data
     *
     * @var object|null
     * @since 1.0.0
     */
    private $current_customer = null;

    /**
     * Constructor
     *
     * Initialize the frontend customer class and set up hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize hooks
        $this->init_hooks();

        // Load current customer if user is logged in
        if (is_user_logged_in()) {
            $this->load_current_customer();
        }
    }

    /**
     * Initialize hooks
     *
     * Set up WordPress hooks for frontend customer functionality.
     *
     * @access private
     * @since 1.0.0
     */
    private function init_hooks() {
        // Frontend form handling
        add_action('wp', array($this, 'handle_customer_forms'));

        // AJAX hooks
        add_action('wp_ajax_subs_update_customer_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_subs_update_customer_address', array($this, 'ajax_update_address'));
        add_action('wp_ajax_subs_update_customer_password', array($this, 'ajax_update_password'));
        add_action('wp_ajax_subs_cancel_account', array($this, 'ajax_cancel_account'));

        // Frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // User login hook
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @access public
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        // Only load on customer portal pages
        if (!$this->should_load_scripts()) {
            return;
        }

        // Customer portal script
        wp_enqueue_script(
            'subs-frontend-customer',
            SUBS_PLUGIN_URL . 'assets/js/frontend-customer.js',
            array('jquery'),
            SUBS_VERSION,
            true
        );

        // Customer portal styles
        wp_enqueue_style(
            'subs-frontend-customer',
            SUBS_PLUGIN_URL . 'assets/css/frontend-customer.css',
            array(),
            SUBS_VERSION
        );

        // Localize script with data
        wp_localize_script('subs-frontend-customer', 'subs_customer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('subs_frontend_ajax'),
            'strings' => array(
                'processing' => __('Processing...', 'subs'),
                'error' => __('An error occurred. Please try again.', 'subs'),
                'profile_updated' => __('Profile updated successfully!', 'subs'),
                'address_updated' => __('Address updated successfully!', 'subs'),
                'password_updated' => __('Password updated successfully!', 'subs'),
                'confirm_cancel' => __('Are you sure you want to cancel your account? This cannot be undone.', 'subs'),
            ),
        ));
    }

    /**
     * Check if scripts should be loaded on current page
     *
     * @return bool
     * @since 1.0.0
     */
    private function should_load_scripts() {
        global $post;

        // Load on pages with customer portal shortcodes
        if (is_page() && $post) {
            if (has_shortcode($post->post_content, 'subs_customer_portal') ||
                has_shortcode($post->post_content, 'subs_customer_profile') ||
                has_shortcode($post->post_content, 'subs_customer_subscriptions')) {
                return true;
            }
        }

        // Allow filtering
        return apply_filters('subs_load_customer_scripts', false);
    }

    /**
     * Handle customer form submissions
     *
     * @access public
     * @since 1.0.0
     */
    public function handle_customer_forms() {
        // Check if this is a customer form submission
        if (!isset($_POST['subs_customer_action']) || !wp_verify_nonce($_POST['subs_customer_nonce'], 'subs_customer_action')) {
            return;
        }

        $action = sanitize_text_field($_POST['subs_customer_action']);

        switch ($action) {
            case 'update_profile':
                $this->handle_update_profile();
                break;

            case 'update_address':
                $this->handle_update_address();
                break;

            case 'update_password':
                $this->handle_update_password();
                break;

            case 'cancel_account':
                $this->handle_cancel_account();
                break;
        }
    }

    /**
     * Load current customer data
     *
     * @access private
     * @since 1.0.0
     */
    private function load_current_customer() {
        if (!is_user_logged_in()) {
            return;
        }

        global $wpdb;
        $customers_table = $wpdb->prefix . 'subs_customers';

        $user_id = get_current_user_id();

        $this->current_customer = $wpdb->get_row($wpdb->prepare(
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
    public function get_current_customer() {
        return $this->current_customer;
    }

    /**
     * Render customer portal
     *
     * @param array $args
     * @return string
     * @since 1.0.0
     */
    public function render_customer_portal($args = array()) {
        if (!is_user_logged_in()) {
            return $this->render_login_form();
        }

        $defaults = array(
            'show_profile' => true,
            'show_subscriptions' => true,
            'show_billing' => true,
            'show_payment_methods' => true,
            'class' => 'subs-customer-portal',
        );

        $args = wp_parse_args($args, $defaults);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['class']); ?>" id="subs-customer-portal">
            <!-- Portal Header -->
            <div class="subs-portal-header">
                <h2><?php _e('My Account', 'subs'); ?></h2>
                <?php $this->render_portal_navigation(); ?>
            </div>

            <!-- Portal Content -->
            <div class="subs-portal-content">
                <!-- Profile Section -->
                <?php if ($args['show_profile']): ?>
                    <div class="subs-portal-section" id="subs-profile-section">
                        <?php $this->render_profile_section(); ?>
                    </div>
                <?php endif; ?>

                <!-- Subscriptions Section -->
                <?php if ($args['show_subscriptions']): ?>
                    <div class="subs-portal-section" id="subs-subscriptions-section">
                        <?php $this->render_subscriptions_section(); ?>
                    </div>
                <?php endif; ?>

                <!-- Billing Section -->
                <?php if ($args['show_billing']): ?>
                    <div class="subs-portal-section" id="subs-billing-section">
                        <?php $this->render_billing_section(); ?>
                    </div>
                <?php endif; ?>

                <!-- Payment Methods Section -->
                <?php if ($args['show_payment_methods']): ?>
                    <div class="subs-portal-section" id="subs-payment-methods-section">
                        <?php $this->render_payment_methods_section(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render portal navigation
     *
     * @access private
     * @since 1.0.0
     */
    private function render_portal_navigation() {
        ?>
        <nav class="subs-portal-nav">
            <ul>
                <li><a href="#subs-profile-section" class="subs-nav-link active"><?php _e('Profile', 'subs'); ?></a></li>
                <li><a href="#subs-subscriptions-section" class="subs-nav-link"><?php _e('Subscriptions', 'subs'); ?></a></li>
                <li><a href="#subs-billing-section" class="subs-nav-link"><?php _e('Billing', 'subs'); ?></a></li>
                <li><a href="#subs-payment-methods-section" class="subs-nav-link"><?php _e('Payment Methods', 'subs'); ?></a></li>
                <li><a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="subs-nav-link"><?php _e('Logout', 'subs'); ?></a></li>
            </ul>
        </nav>
        <?php
    }

    /**
     * Render profile section
     *
     * @access private
     * @since 1.0.0
     */
    private function render_profile_section() {
        $customer = $this->current_customer;
        $user = wp_get_current_user();
        ?>
        <div class="subs-section-header">
            <h3><?php _e('Profile Information', 'subs'); ?></h3>
        </div>

        <div class="subs-section-content">
            <form method="post" id="subs-profile-form" class="subs-customer-form">
                <?php wp_nonce_field('subs_customer_action', 'subs_customer_nonce'); ?>
                <input type="hidden" name="subs_customer_action" value="update_profile">

                <div class="subs-form-row">
                    <div class="subs-form-group">
                        <label for="first_name"><?php _e('First Name', 'subs'); ?></label>
                        <input type="text" name="first_name" id="first_name"
                               value="<?php echo esc_attr($customer ? $customer->first_name : ''); ?>" required>
                    </div>

                    <div class="subs-form-group">
                        <label for="last_name"><?php _e('Last Name', 'subs'); ?></label>
                        <input type="text" name="last_name" id="last_name"
                               value="<?php echo esc_attr($customer ? $customer->last_name : ''); ?>" required>
                    </div>
                </div>

                <div class="subs-form-group">
                    <label for="email"><?php _e('Email Address', 'subs'); ?></label>
                    <input type="email" name="email" id="email"
                           value="<?php echo esc_attr($customer ? $customer->email : $user->user_email); ?>" required>
                    <p class="subs-field-description"><?php _e('This is your billing email address.', 'subs'); ?></p>
                </div>

                <div class="subs-form-group">
                    <label for="phone"><?php _e('Phone Number', 'subs'); ?></label>
                    <input type="tel" name="phone" id="phone"
                           value="<?php echo esc_attr($customer ? $customer->phone : ''); ?>">
                </div>

                <div class="subs-form-actions">
                    <button type="submit" class="subs-btn subs-btn-primary">
                        <?php _e('Update Profile', 'subs'); ?>
                    </button>
                </div>

                <div class="subs-form-result"></div>
            </form>

            <!-- Password Change Form -->
            <div class="subs-password-section">
                <h4><?php _e('Change Password', 'subs'); ?></h4>
                <form method="post" id="subs-password-form" class="subs-customer-form">
                    <?php wp_nonce_field('subs_customer_action', 'subs_customer_nonce'); ?>
                    <input type="hidden" name="subs_customer_action" value="update_password">

                    <div class="subs-form-group">
                        <label for="current_password"><?php _e('Current Password', 'subs'); ?></label>
                        <input type="password" name="current_password" id="current_password" required>
                    </div>

                    <div class="subs-form-group">
                        <label for="new_password"><?php _e('New Password', 'subs'); ?></label>
                        <input type="password" name="new_password" id="new_password" required>
                    </div>

                    <div class="subs-form-group">
                        <label for="confirm_password"><?php _e('Confirm New Password', 'subs'); ?></label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>

                    <div class="subs-form-actions">
                        <button type="submit" class="subs-btn subs-btn-secondary">
                            <?php _e('Change Password', 'subs'); ?>
                        </button>
                    </div>

                    <div class="subs-form-result"></div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render subscriptions section
     *
     * @access private
     * @since 1.0.0
     */
    private function render_subscriptions_section() {
        ?>
        <div class="subs-section-header">
            <h3><?php _e('My Subscriptions', 'subs'); ?></h3>
        </div>

        <div class="subs-section-content">
            <?php
            if ($this->current_customer) {
                // Use the subscription class to render subscriptions
                if (class_exists('Subs_Frontend_Subscription')) {
                    $subscription_class = new Subs_Frontend_Subscription();
                    echo $subscription_class->render_customer_subscriptions($this->current_customer->id);
                }
            } else {
                echo '<p>' . __('No subscriptions found.', 'subs') . '</p>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render billing section
     *
     * @access private
     * @since 1.0.0
     */
    private function render_billing_section() {
        $customer = $this->current_customer;
        ?>
        <div class="subs-section-header">
            <h3><?php _e('Billing Address', 'subs'); ?></h3>
        </div>

        <div class="subs-section-content">
            <form method="post" id="subs-address-form" class="subs-customer-form">
                <?php wp_nonce_field('subs_customer_action', 'subs_customer_nonce'); ?>
                <input type="hidden" name="subs_customer_action" value="update_address">

                <div class="subs-form-group">
                    <label for="address_line_1"><?php _e('Address Line 1', 'subs'); ?></label>
                    <input type="text" name="address_line_1" id="address_line_1"
                           value="<?php echo esc_attr($customer ? $customer->address_line_1 : ''); ?>">
                </div>

                <div class="subs-form-group">
                    <label for="address_line_2"><?php _e('Address Line 2', 'subs'); ?></label>
                    <input type="text" name="address_line_2" id="address_line_2"
                           value="<?php echo esc_attr($customer ? $customer->address_line_2 : ''); ?>">
                </div>

                <div class="subs-form-row">
                    <div class="subs-form-group">
                        <label for="city"><?php _e('City', 'subs'); ?></label>
                        <input type="text" name="city" id="city"
                               value="<?php echo esc_attr($customer ? $customer->city : ''); ?>">
                    </div>

                    <div class="subs-form-group">
                        <label for="state"><?php _e('State/Province', 'subs'); ?></label>
                        <input type="text" name="state" id="state"
                               value="<?php echo esc_attr($customer ? $customer->state : ''); ?>">
                    </div>
                </div>

                <div class="subs-form-row">
                    <div class="subs-form-group">
                        <label for="postal_code"><?php _e('Postal Code', 'subs'); ?></label>
                        <input type="text" name="postal_code" id="postal_code"
                               value="<?php echo esc_attr($customer ? $customer->postal_code : ''); ?>">
                    </div>

                    <div class="subs-form-group">
                        <label for="country"><?php _e('Country', 'subs'); ?></label>
                        <select name="country" id="country">
                            <?php $this->render_country_options($customer ? $customer->country : ''); ?>
                        </select>
                    </div>
                </div>

                <div class="subs-form-actions">
                    <button type="submit" class="subs-btn subs-btn-primary">
                        <?php _e('Update Address', 'subs'); ?>
                    </button>
                </div>

                <div class="subs-form-result"></div>
            </form>

            <!-- Billing History -->
            <div class="subs-billing-history">
                <h4><?php _e('Billing History', 'subs'); ?></h4>
                <?php $this->render_billing_history(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render payment methods section
     *
     * @access private
     * @since 1.0.0
     */
    private function render_payment_methods_section() {
        ?>
        <div class="subs-section-header">
            <h3><?php _e('Payment Methods', 'subs'); ?></h3>
        </div>

        <div class="subs-section-content">
            <?php $this->render_payment_methods_list(); ?>

            <div class="subs-add-payment-method">
                <button type="button" class="subs-btn subs-btn-secondary" id="subs-add-payment-btn">
                    <?php _e('Add Payment Method', 'subs'); ?>
                </button>
            </div>

            <!-- Add Payment Method Form (hidden by default) -->
            <div class="subs-payment-form" id="subs-payment-form" style="display: none;">
                <h4><?php _e('Add New Payment Method', 'subs'); ?></h4>
                <form method="post" id="subs-new-payment-form">
                    <div class="subs-form-group">
                        <label for="card-element-new"><?php _e('Credit or Debit Card', 'subs'); ?></label>
                        <div id="card-element-new" class="subs-stripe-element">
                            <!-- Stripe Elements will create form elements here -->
                        </div>
                        <div id="card-errors-new" class="subs-field-error" role="alert"></div>
                    </div>

                    <div class="subs-form-actions">
                        <button type="submit" class="subs-btn subs-btn-primary">
                            <?php _e('Save Payment Method', 'subs'); ?>
                        </button>
                        <button type="button" class="subs-btn subs-btn-secondary" id="subs-cancel-payment-btn">
                            <?php _e('Cancel', 'subs'); ?>
                        </button>
                    </div>

                    <div class="subs-form-result"></div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render payment methods list
     *
     * @access private
     * @since 1.0.0
     */
    private function render_payment_methods_list() {
        // This would fetch payment methods from Stripe
        // For now, show a placeholder
        ?>
        <div class="subs-payment-methods-list">
            <p class="subs-no-payment-methods"><?php _e('No payment methods on file.', 'subs'); ?></p>
            <!-- Payment methods would be listed here -->
        </div>
        <?php
    }

    /**
     * Render billing history
     *
     * @access private
     * @since 1.0.0
     */
    private function render_billing_history() {
        if (!$this->current_customer) {
            echo '<p>' . __('No billing history found.', 'subs') . '</p>';
            return;
        }

        global $wpdb;
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT pl.*, s.plan_name
             FROM $payment_logs_table pl
             JOIN $subscriptions_table s ON pl.subscription_id = s.id
             WHERE s.customer_id = %d
             ORDER BY pl.processed_date DESC
             LIMIT 10",
            $this->current_customer->id
        ));

        if (empty($payments)) {
            echo '<p>' . __('No billing history found.', 'subs') . '</p>';
            return;
        }
        ?>
        <table class="subs-billing-table">
            <thead>
                <tr>
                    <th><?php _e('Date', 'subs'); ?></th>
                    <th><?php _e('Description', 'subs'); ?></th>
                    <th><?php _e('Amount', 'subs'); ?></th>
                    <th><?php _e('Status', 'subs'); ?></th>
                    <th><?php _e('Invoice', 'subs'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment->processed_date))); ?></td>
                        <td><?php echo esc_html($payment->plan_name ?: __('Subscription Payment', 'subs')); ?></td>
                        <td><?php echo esc_html($this->format_price($payment->amount)); ?></td>
                        <td>
                            <span class="subs-payment-status subs-status-<?php echo esc_attr($payment->status); ?>">
                                <?php echo esc_html(ucfirst($payment->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($payment->stripe_invoice_id)): ?>
                                <a href="#" class="subs-view-invoice" data-invoice="<?php echo esc_attr($payment->stripe_invoice_id); ?>">
                                    <?php _e('View', 'subs'); ?>
                                </a>
                            <?php else: ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render country options
     *
     * @param string $selected
     * @access private
     * @since 1.0.0
     */
    private function render_country_options($selected = '') {
        $countries = array(
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

        echo '<option value="">' . __('Select Country', 'subs') . '</option>';

        foreach ($countries as $code => $name) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($code),
                selected($selected, $code, false),
                esc_html($name)
            );
        }
    }

    /**
     * Render login form
     *
     * @return string
     * @since 1.0.0
     */
    private function render_login_form() {
        ob_start();
        ?>
        <div class="subs-login-form">
            <h3><?php _e('Please Log In', 'subs'); ?></h3>
            <p><?php _e('You must be logged in to access your customer portal.', 'subs'); ?></p>
            <?php wp_login_form(array('redirect' => get_permalink())); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle update profile form submission
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_update_profile() {
        if (!is_user_logged_in()) {
            $this->add_error(__('You must be logged in to update your profile.', 'subs'));
            return;
        }

        $result = $this->update_customer_profile($_POST);

        if ($result['success']) {
            $this->add_success($result['message']);
            wp_redirect(add_query_arg('profile_updated', '1', wp_get_referer()));
            exit;
        } else {
            $this->add_error($result['message']);
        }
    }

    /**
     * Update customer profile
     *
     * @param array $data
     * @return array
     * @since 1.0.0
     */
    private function update_customer_profile($data) {
        if (!$this->current_customer) {
            return array('success' => false, 'message' => __('Customer not found.', 'subs'));
        }

        global $wpdb;
        $customers_table = $wpdb->prefix . 'subs_customers';

        $update_data = array(
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'updated_date' => current_time('mysql'),
        );

        // Validate email
        if (!is_email($update_data['email'])) {
            return array('success' => false, 'message' => __('Please enter a valid email address.', 'subs'));
        }

        // Check if email is already taken by another customer
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $customers_table WHERE email = %s AND id != %d",
            $update_data['email'],
            $this->current_customer->id
        ));

        if ($existing) {
            return array('success' => false, 'message' => __('This email address is already in use.', 'subs'));
        }

        $result = $wpdb->update(
            $customers_table,
            $update_data,
            array('id' => $this->current_customer->id)
        );

        if ($result !== false) {
            do_action('subs_customer_profile_updated', $this->current_customer->id, $update_data);
            return array('success' => true, 'message' => __('Profile updated successfully!', 'subs'));
        }

        return array('success' => false, 'message' => __('Failed to update profile.', 'subs'));
    }

    /**
     * Handle update address form submission
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_update_address() {
        if (!is_user_logged_in()) {
            $this->add_error(__('You must be logged in to update your address.', 'subs'));
            return;
        }

        $result = $this->update_customer_address($_POST);

        if ($result['success']) {
            $this->add_success($result['message']);
            wp_redirect(add_query_arg('address_updated', '1', wp_get_referer()));
            exit;
        } else {
            $this->add_error($result['message']);
        }
    }

    /**
     * Update customer address
     *
     * @param array $data
     * @return array
     * @since 1.0.0
     */
    private function update_customer_address($data) {
        if (!$this->current_customer) {
            return array('success' => false, 'message' => __('Customer not found.', 'subs'));
        }

        global $wpdb;
        $customers_table = $wpdb->prefix . 'subs_customers';

        $update_data = array(
            'address_line_1' => sanitize_text_field($data['address_line_1']),
            'address_line_2' => sanitize_text_field($data['address_line_2']),
            'city' => sanitize_text_field($data['city']),
            'state' => sanitize_text_field($data['state']),
            'postal_code' => sanitize_text_field($data['postal_code']),
            'country' => sanitize_text_field($data['country']),
            'updated_date' => current_time('mysql'),
        );

        $result = $wpdb->update(
            $customers_table,
            $update_data,
            array('id' => $this->current_customer->id)
        );

        if ($result !== false) {
            do_action('subs_customer_address_updated', $this->current_customer->id, $update_data);
            return array('success' => true, 'message' => __('Address updated successfully!', 'subs'));
        }

        return array('success' => false, 'message' => __('Failed to update address.', 'subs'));
    }

    /**
     * Handle update password form submission
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_update_password() {
        if (!is_user_logged_in()) {
            $this->add_error(__('You must be logged in to change your password.', 'subs'));
            return;
        }

        $result = $this->update_customer_password($_POST);

        if ($result['success']) {
            $this->add_success($result['message']);
            wp_redirect(add_query_arg('password_updated', '1', wp_get_referer()));
            exit;
        } else {
            $this->add_error($result['message']);
        }
    }

    /**
     * Update customer password
     *
     * @param array $data
     * @return array
     * @since 1.0.0
     */
    private function update_customer_password($data) {
        $user = wp_get_current_user();

        // Verify current password
        if (!wp_check_password($data['current_password'], $user->user_pass, $user->ID)) {
            return array('success' => false, 'message' => __('Current password is incorrect.', 'subs'));
        }

        // Validate new password
        if (empty($data['new_password']) || strlen($data['new_password']) < 8) {
            return array('success' => false, 'message' => __('New password must be at least 8 characters long.', 'subs'));
        }

        // Check password confirmation
        if ($data['new_password'] !== $data['confirm_password']) {
            return array('success' => false, 'message' => __('New passwords do not match.', 'subs'));
        }

        // Update password
        wp_set_password($data['new_password'], $user->ID);

        // Re-authenticate user
        wp_set_auth_cookie($user->ID);

        do_action('subs_customer_password_updated', $user->ID);

        return array('success' => true, 'message' => __('Password updated successfully!', 'subs'));
    }

    /**
     * Handle cancel account form submission
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_cancel_account() {
        if (!is_user_logged_in()) {
            $this->add_error(__('You must be logged in to cancel your account.', 'subs'));
            return;
        }

        // Check if customer has active subscriptions
        if ($this->has_active_subscriptions()) {
            $this->add_error(__('You must cancel all active subscriptions before closing your account.', 'subs'));
            return;
        }

        $result = $this->cancel_customer_account();

        if ($result['success']) {
            wp_logout();
            wp_redirect(home_url());
            exit;
        } else {
            $this->add_error($result['message']);
        }
    }

    /**
     * Check if customer has active subscriptions
     *
     * @return bool
     * @since 1.0.0
     */
    private function has_active_subscriptions() {
        if (!$this->current_customer) {
            return false;
        }

        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $subscriptions_table WHERE customer_id = %d AND status = 'active'",
            $this->current_customer->id
        ));

        return $count > 0;
    }

    /**
     * Cancel customer account
     *
     * @return array
     * @since 1.0.0
     */
    private function cancel_customer_account() {
        if (!$this->current_customer) {
            return array('success' => false, 'message' => __('Customer not found.', 'subs'));
        }

        global $wpdb;
        $customers_table = $wpdb->prefix . 'subs_customers';

        // Mark customer as inactive
        $result = $wpdb->update(
            $customers_table,
            array(
                'status' => 'inactive',
                'updated_date' => current_time('mysql')
            ),
            array('id' => $this->current_customer->id)
        );

        if ($result !== false) {
            do_action('subs_customer_account_cancelled', $this->current_customer->id);
            return array('success' => true, 'message' => __('Account cancelled successfully.', 'subs'));
        }

        return array('success' => false, 'message' => __('Failed to cancel account.', 'subs'));
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
     * Add error message
     *
     * @param string $message
     * @since 1.0.0
     */
    private function add_error($message) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['subs_errors'])) {
            $_SESSION['subs_errors'] = array();
        }

        $_SESSION['subs_errors'][] = $message;
    }

    /**
     * Add success message
     *
     * @param string $message
     * @since 1.0.0
     */
    private function add_success($message) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['subs_success'])) {
            $_SESSION['subs_success'] = array();
        }

        $_SESSION['subs_success'][] = $message;
    }

    /**
     * Get and clear error messages
     *
     * @return array
     * @since 1.0.0
     */
    public function get_errors() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $errors = isset($_SESSION['subs_errors']) ? $_SESSION['subs_errors'] : array();
        unset($_SESSION['subs_errors']);

        return $errors;
    }

    /**
     * Get and clear success messages
     *
     * @return array
     * @since 1.0.0
     */
    public function get_success_messages() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $messages = isset($_SESSION['subs_success']) ? $_SESSION['subs_success'] : array();
        unset($_SESSION['subs_success']);

        return $messages;
    }

    /**
     * On user login hook
     *
     * @param string $user_login
     * @param WP_User $user
     * @access public
     * @since 1.0.0
     */
    public function on_user_login($user_login, $user) {
        // Update last login date
        global $wpdb;
        $customers_table = $wpdb->prefix . 'subs_customers';

        $wpdb->update(
            $customers_table,
            array('last_login_date' => current_time('mysql')),
            array('user_id' => $user->ID)
        );
    }

    /**
     * AJAX: Update customer profile
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_update_profile() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'subs'));
        }

        $result = $this->update_customer_profile($_POST);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Update customer address
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_update_address() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'subs'));
        }

        $result = $this->update_customer_address($_POST);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Update customer password
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_update_password() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'subs'));
        }

        $result = $this->update_customer_password($_POST);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Cancel account
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_cancel_account() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'subs'));
        }

        // Check for active subscriptions
        if ($this->has_active_subscriptions()) {
            wp_send_json_error(__('You must cancel all active subscriptions before closing your account.', 'subs'));
        }

        $result = $this->cancel_customer_account();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'redirect' => home_url()
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
}
