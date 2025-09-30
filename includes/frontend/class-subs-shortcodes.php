<?php
/**
 * Shortcodes Class
 *
 * Handles all shortcode functionality for the Subs plugin.
 * Provides easy-to-use shortcodes for embedding subscription forms,
 * customer portals, and other plugin features.
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
 * Subs Shortcodes Class
 *
 * @class Subs_Shortcodes
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Shortcodes {

    /**
     * Subscription instance
     *
     * @var Subs_Frontend_Subscription
     * @since 1.0.0
     */
    private $subscription;

    /**
     * Customer instance
     *
     * @var Subs_Frontend_Customer
     * @since 1.0.0
     */
    private $customer;

    /**
     * Constructor
     *
     * Initialize the shortcodes class and register all shortcodes.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize component instances
        $this->init_components();

        // Register shortcodes
        $this->register_shortcodes();
    }

    /**
     * Initialize component instances
     *
     * @access private
     * @since 1.0.0
     */
    private function init_components() {
        if (class_exists('Subs_Frontend_Subscription')) {
            $this->subscription = new Subs_Frontend_Subscription();
        }

        if (class_exists('Subs_Frontend_Customer')) {
            $this->customer = new Subs_Frontend_Customer();
        }
    }

    /**
     * Register all shortcodes
     *
     * @access private
     * @since 1.0.0
     */
    private function register_shortcodes() {
        // Subscription shortcodes
        add_shortcode('subs_subscription_form', array($this, 'subscription_form_shortcode'));
        add_shortcode('subs_subscription_plans', array($this, 'subscription_plans_shortcode'));
        add_shortcode('subs_my_subscriptions', array($this, 'my_subscriptions_shortcode'));

        // Customer portal shortcodes
        add_shortcode('subs_customer_portal', array($this, 'customer_portal_shortcode'));
        add_shortcode('subs_customer_profile', array($this, 'customer_profile_shortcode'));
        add_shortcode('subs_customer_subscriptions', array($this, 'customer_subscriptions_shortcode'));
        add_shortcode('subs_billing_history', array($this, 'billing_history_shortcode'));

        // Single plan shortcode
        add_shortcode('subs_plan', array($this, 'single_plan_shortcode'));

        // Login/Register shortcodes
        add_shortcode('subs_login_form', array($this, 'login_form_shortcode'));
        add_shortcode('subs_register_form', array($this, 'register_form_shortcode'));

        // Utility shortcodes
        add_shortcode('subs_account_link', array($this, 'account_link_shortcode'));
        add_shortcode('subs_logout_link', array($this, 'logout_link_shortcode'));
    }

    /**
     * Subscription form shortcode
     *
     * Usage: [subs_subscription_form plan="premium" show_plans="true"]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function subscription_form_shortcode($atts) {
        if (!$this->subscription) {
            return $this->render_error(__('Subscription component not available.', 'subs'));
        }

        $atts = shortcode_atts(array(
            'plan' => '',
            'show_plans' => 'true',
            'show_customer_fields' => 'true',
            'show_payment_form' => 'true',
            'redirect_url' => '',
            'class' => 'subs-subscription-form',
        ), $atts, 'subs_subscription_form');

        // Convert string booleans to actual booleans
        $atts['show_plans'] = filter_var($atts['show_plans'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_customer_fields'] = filter_var($atts['show_customer_fields'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_payment_form'] = filter_var($atts['show_payment_form'], FILTER_VALIDATE_BOOLEAN);

        // Set plan_id instead of plan
        $atts['plan_id'] = $atts['plan'];

        return $this->subscription->render_subscription_form($atts);
    }

    /**
     * Subscription plans shortcode
     *
     * Usage: [subs_subscription_plans columns="3" featured="premium"]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function subscription_plans_shortcode($atts) {
        if (!$this->subscription) {
            return $this->render_error(__('Subscription component not available.', 'subs'));
        }

        $atts = shortcode_atts(array(
            'columns' => '3',
            'featured' => '',
            'show_trial' => 'true',
            'show_features' => 'true',
            'button_text' => __('Subscribe Now', 'subs'),
            'class' => 'subs-plans-listing',
        ), $atts, 'subs_subscription_plans');

        $atts['show_trial'] = filter_var($atts['show_trial'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_features'] = filter_var($atts['show_features'], FILTER_VALIDATE_BOOLEAN);

        return $this->render_subscription_plans($atts);
    }

    /**
     * My subscriptions shortcode
     *
     * Usage: [subs_my_subscriptions]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function my_subscriptions_shortcode($atts) {
        if (!$this->subscription) {
            return $this->render_error(__('Subscription component not available.', 'subs'));
        }

        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        $atts = shortcode_atts(array(
            'class' => 'subs-my-subscriptions',
        ), $atts, 'subs_my_subscriptions');

        return '<div class="' . esc_attr($atts['class']) . '">' .
               $this->subscription->render_customer_subscriptions() .
               '</div>';
    }

    /**
     * Customer portal shortcode
     *
     * Usage: [subs_customer_portal]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function customer_portal_shortcode($atts) {
        if (!$this->customer) {
            return $this->render_error(__('Customer component not available.', 'subs'));
        }

        $atts = shortcode_atts(array(
            'show_profile' => 'true',
            'show_subscriptions' => 'true',
            'show_billing' => 'true',
            'show_payment_methods' => 'true',
            'class' => 'subs-customer-portal',
        ), $atts, 'subs_customer_portal');

        // Convert string booleans
        $atts['show_profile'] = filter_var($atts['show_profile'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_subscriptions'] = filter_var($atts['show_subscriptions'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_billing'] = filter_var($atts['show_billing'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_payment_methods'] = filter_var($atts['show_payment_methods'], FILTER_VALIDATE_BOOLEAN);

        return $this->customer->render_customer_portal($atts);
    }

    /**
     * Customer profile shortcode
     *
     * Usage: [subs_customer_profile]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function customer_profile_shortcode($atts) {
        if (!$this->customer) {
            return $this->render_error(__('Customer component not available.', 'subs'));
        }

        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        $atts = shortcode_atts(array(
            'class' => 'subs-customer-profile',
        ), $atts, 'subs_customer_profile');

        // Render only the profile section
        return $this->customer->render_customer_portal(array(
            'show_profile' => true,
            'show_subscriptions' => false,
            'show_billing' => false,
            'show_payment_methods' => false,
            'class' => $atts['class'],
        ));
    }

    /**
     * Customer subscriptions shortcode
     *
     * Usage: [subs_customer_subscriptions]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function customer_subscriptions_shortcode($atts) {
        return $this->my_subscriptions_shortcode($atts);
    }

    /**
     * Billing history shortcode
     *
     * Usage: [subs_billing_history limit="10"]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function billing_history_shortcode($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        $atts = shortcode_atts(array(
            'limit' => '10',
            'class' => 'subs-billing-history',
        ), $atts, 'subs_billing_history');

        return $this->render_billing_history($atts);
    }

    /**
     * Single plan shortcode
     *
     * Usage: [subs_plan id="premium" style="card"]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function single_plan_shortcode($atts) {
        if (!$this->subscription) {
            return $this->render_error(__('Subscription component not available.', 'subs'));
        }

        $atts = shortcode_atts(array(
            'id' => '',
            'style' => 'card',
            'show_button' => 'true',
            'button_text' => __('Subscribe Now', 'subs'),
            'button_url' => '',
            'class' => 'subs-single-plan',
        ), $atts, 'subs_plan');

        if (empty($atts['id'])) {
            return $this->render_error(__('Plan ID is required.', 'subs'));
        }

        $atts['show_button'] = filter_var($atts['show_button'], FILTER_VALIDATE_BOOLEAN);

        return $this->render_single_plan($atts);
    }

    /**
     * Login form shortcode
     *
     * Usage: [subs_login_form redirect="/account/"]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function login_form_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already logged in.', 'subs') . '</p>';
        }

        $atts = shortcode_atts(array(
            'redirect' => '',
            'class' => 'subs-login-form',
        ), $atts, 'subs_login_form');

        $redirect = !empty($atts['redirect']) ? $atts['redirect'] : get_permalink();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <?php wp_login_form(array(
                'redirect' => $redirect,
                'label_username' => __('Username or Email', 'subs'),
                'label_password' => __('Password', 'subs'),
                'label_remember' => __('Remember Me', 'subs'),
                'label_log_in' => __('Log In', 'subs'),
            )); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Register form shortcode
     *
     * Usage: [subs_register_form redirect="/account/"]
     *
     * @param array $atts
     * @return string
     * @since 1.0.0
     */
    public function register_form_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already registered and logged in.', 'subs') . '</p>';
        }

        $atts = shortcode_atts(array(
            'redirect' => '',
            'class' => 'subs-register-form',
        ), $atts, 'subs_register_form');

        return $this->render_register_form($atts);
    }

    /**
     * Account link shortcode
     *
     * Usage: [subs_account_link]My Account[/subs_account_link]
     *
     * @param array $atts
     * @param string $content
     * @return string
     * @since 1.0.0
     */
    public function account_link_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'url' => '/account/',
            'class' => 'subs-account-link',
        ), $atts, 'subs_account_link');

        if (!is_user_logged_in()) {
            return '';
        }

        $link_text = $content ? $content : __('My Account', 'subs');

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url(home_url($atts['url'])),
            esc_attr($atts['class']),
            esc_html($link_text)
        );
    }

    /**
     * Logout link shortcode
     *
     * Usage: [subs_logout_link]Logout[/subs_logout_link]
     *
     * @param array $atts
     * @param string $content
     * @return string
     * @since 1.0.0
     */
    public function logout_link_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'redirect' => '',
            'class' => 'subs-logout-link',
        ), $atts, 'subs_logout_link');

        if (!is_user_logged_in()) {
            return '';
        }

        $link_text = $content ? $content : __('Logout', 'subs');
        $redirect = !empty($atts['redirect']) ? $atts['redirect'] : home_url();

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url(wp_logout_url($redirect)),
            esc_attr($atts['class']),
            esc_html($link_text)
        );
    }

    /**
     * Render subscription plans
     *
     * @param array $args
     * @return string
     * @since 1.0.0
     */
    private function render_subscription_plans($args) {
        $plans = $this->subscription->get_available_plans();

        if (empty($plans)) {
            return '<p>' . __('No subscription plans available.', 'subs') . '</p>';
        }

        $columns = intval($args['columns']);
        $grid_class = 'subs-plans-grid subs-plans-cols-' . $columns;

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['class']); ?>">
            <div class="<?php echo esc_attr($grid_class); ?>">
                <?php foreach ($plans as $plan_id => $plan): ?>
                    <?php
                    $is_featured = $args['featured'] === $plan_id;
                    $card_class = 'subs-plan-card' . ($is_featured ? ' subs-plan-featured' : '');
                    ?>
                    <div class="<?php echo esc_attr($card_class); ?>" data-plan-id="<?php echo esc_attr($plan_id); ?>">
                        <?php if ($is_featured): ?>
                            <div class="subs-plan-badge"><?php _e('Most Popular', 'subs'); ?></div>
                        <?php endif; ?>

                        <div class="subs-plan-header">
                            <h3 class="subs-plan-name"><?php echo esc_html($plan['name']); ?></h3>
                            <div class="subs-plan-price">
                                <?php echo $this->format_price($plan['amount'], $plan['currency']); ?>
                                <span class="subs-plan-interval">/ <?php echo esc_html($this->format_interval($plan['interval'], $plan['interval_count'])); ?></span>
                            </div>
                        </div>

                        <div class="subs-plan-description">
                            <p><?php echo esc_html($plan['description']); ?></p>
                        </div>

                        <?php if ($args['show_features'] && !empty($plan['features'])): ?>
                            <div class="subs-plan-features">
                                <ul>
                                    <?php foreach ($plan['features'] as $feature): ?>
                                        <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($args['show_trial'] && $plan['trial_period_days'] > 0): ?>
                            <div class="subs-plan-trial">
                                <?php printf(__('%d-day free trial', 'subs'), $plan['trial_period_days']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="subs-plan-action">
                            <a href="?plan=<?php echo esc_attr($plan_id); ?>#subs-subscription-form" class="subs-btn subs-btn-primary">
                                <?php echo esc_html($args['button_text']); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single plan
     *
     * @param array $args
     * @return string
     * @since 1.0.0
     */
    private function render_single_plan($args) {
        $plan = $this->subscription->get_plan($args['id']);

        if (!$plan) {
            return $this->render_error(__('Plan not found.', 'subs'));
        }

        $button_url = !empty($args['button_url']) ? $args['button_url'] : '?plan=' . $args['id'] . '#subs-subscription-form';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['class']); ?> subs-plan-style-<?php echo esc_attr($args['style']); ?>">
            <div class="subs-plan-content">
                <h3 class="subs-plan-name"><?php echo esc_html($plan['name']); ?></h3>

                <div class="subs-plan-price">
                    <?php echo $this->format_price($plan['amount'], $plan['currency']); ?>
                    <span class="subs-plan-interval">/ <?php echo esc_html($this->format_interval($plan['interval'], $plan['interval_count'])); ?></span>
                </div>

                <div class="subs-plan-description">
                    <p><?php echo esc_html($plan['description']); ?></p>
                </div>

                <?php if (!empty($plan['features'])): ?>
                    <div class="subs-plan-features">
                        <ul>
                            <?php foreach ($plan['features'] as $feature): ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($plan['trial_period_days'] > 0): ?>
                    <div class="subs-plan-trial">
                        <?php printf(__('%d-day free trial', 'subs'), $plan['trial_period_days']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($args['show_button']): ?>
                    <div class="subs-plan-action">
                        <a href="<?php echo esc_url($button_url); ?>" class="subs-btn subs-btn-primary">
                            <?php echo esc_html($args['button_text']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render billing history
     *
     * @param array $args
     * @return string
     * @since 1.0.0
     */
    private function render_billing_history($args) {
        if (!$this->customer) {
            return $this->render_error(__('Customer component not available.', 'subs'));
        }

        $customer = $this->customer->get_current_customer();

        if (!$customer) {
            return '<p>' . __('No billing history found.', 'subs') . '</p>';
        }

        global $wpdb;
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $limit = intval($args['limit']);

        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT pl.*, s.plan_name
             FROM $payment_logs_table pl
             JOIN $subscriptions_table s ON pl.subscription_id = s.id
             WHERE s.customer_id = %d
             ORDER BY pl.processed_date DESC
             LIMIT %d",
            $customer->id,
            $limit
        ));

        if (empty($payments)) {
            return '<p>' . __('No billing history found.', 'subs') . '</p>';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['class']); ?>">
            <table class="subs-billing-table">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'subs'); ?></th>
                        <th><?php _e('Description', 'subs'); ?></th>
                        <th><?php _e('Amount', 'subs'); ?></th>
                        <th><?php _e('Status', 'subs'); ?></th>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render register form
     *
     * @param array $args
     * @return string
     * @since 1.0.0
     */
    private function render_register_form($args) {
        $redirect = !empty($args['redirect']) ? $args['redirect'] : get_permalink();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['class']); ?>">
            <form method="post" action="<?php echo esc_url(wp_registration_url()); ?>">
                <div class="subs-form-group">
                    <label for="user_login"><?php _e('Username', 'subs'); ?> <span class="required">*</span></label>
                    <input type="text" name="user_login" id="user_login" required>
                </div>

                <div class="subs-form-group">
                    <label for="user_email"><?php _e('Email', 'subs'); ?> <span class="required">*</span></label>
                    <input type="email" name="user_email" id="user_email" required>
                </div>

                <div class="subs-form-group">
                    <label for="user_password"><?php _e('Password', 'subs'); ?> <span class="required">*</span></label>
                    <input type="password" name="user_password" id="user_password" required>
                </div>

                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">

                <div class="subs-form-actions">
                    <button type="submit" class="subs-btn subs-btn-primary">
                        <?php _e('Register', 'subs'); ?>
                    </button>
                </div>

                <p class="subs-login-link">
                    <?php _e('Already have an account?', 'subs'); ?>
                    <a href="<?php echo esc_url(wp_login_url($redirect)); ?>"><?php _e('Login', 'subs'); ?></a>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login required message
     *
     * @return string
     * @since 1.0.0
     */
    private function render_login_required() {
        ob_start();
        ?>
        <div class="subs-login-required">
            <p><?php _e('You must be logged in to view this content.', 'subs'); ?></p>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="subs-btn subs-btn-primary">
                <?php _e('Login', 'subs'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render error message
     *
     * @param string $message
     * @return string
     * @since 1.0.0
     */
    private function render_error($message) {
        return '<div class="subs-error">' . esc_html($message) . '</div>';
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
            'USD' => ',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C,
            'AUD' => 'A,
            'JPY' => '¥',
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
    private function format_interval($interval, $count = 1) {
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
     * Get all registered shortcodes
     *
     * @return array
     * @since 1.0.0
     */
    public function get_registered_shortcodes() {
        return array(
            'subs_subscription_form' => array(
                'description' => __('Display subscription form', 'subs'),
                'attributes' => array(
                    'plan' => __('Specific plan ID to display', 'subs'),
                    'show_plans' => __('Show plan selection (true/false)', 'subs'),
                    'show_customer_fields' => __('Show customer information fields (true/false)', 'subs'),
                    'show_payment_form' => __('Show payment form (true/false)', 'subs'),
                    'redirect_url' => __('URL to redirect after subscription', 'subs'),
                ),
            ),
            'subs_subscription_plans' => array(
                'description' => __('Display available subscription plans', 'subs'),
                'attributes' => array(
                    'columns' => __('Number of columns (1-4)', 'subs'),
                    'featured' => __('ID of featured plan', 'subs'),
                    'show_trial' => __('Show trial period info (true/false)', 'subs'),
                    'show_features' => __('Show plan features (true/false)', 'subs'),
                    'button_text' => __('Subscribe button text', 'subs'),
                ),
            ),
            'subs_my_subscriptions' => array(
                'description' => __('Display user\'s subscriptions', 'subs'),
                'attributes' => array(),
            ),
            'subs_customer_portal' => array(
                'description' => __('Display complete customer portal', 'subs'),
                'attributes' => array(
                    'show_profile' => __('Show profile section (true/false)', 'subs'),
                    'show_subscriptions' => __('Show subscriptions section (true/false)', 'subs'),
                    'show_billing' => __('Show billing section (true/false)', 'subs'),
                    'show_payment_methods' => __('Show payment methods section (true/false)', 'subs'),
                ),
            ),
            'subs_customer_profile' => array(
                'description' => __('Display customer profile only', 'subs'),
                'attributes' => array(),
            ),
            'subs_billing_history' => array(
                'description' => __('Display billing history', 'subs'),
                'attributes' => array(
                    'limit' => __('Number of records to show', 'subs'),
                ),
            ),
            'subs_plan' => array(
                'description' => __('Display single subscription plan', 'subs'),
                'attributes' => array(
                    'id' => __('Plan ID (required)', 'subs'),
                    'style' => __('Display style (card/inline)', 'subs'),
                    'show_button' => __('Show subscribe button (true/false)', 'subs'),
                    'button_text' => __('Button text', 'subs'),
                    'button_url' => __('Button URL', 'subs'),
                ),
            ),
            'subs_login_form' => array(
                'description' => __('Display login form', 'subs'),
                'attributes' => array(
                    'redirect' => __('URL to redirect after login', 'subs'),
                ),
            ),
            'subs_register_form' => array(
                'description' => __('Display registration form', 'subs'),
                'attributes' => array(
                    'redirect' => __('URL to redirect after registration', 'subs'),
                ),
            ),
            'subs_account_link' => array(
                'description' => __('Display account link (only for logged in users)', 'subs'),
                'attributes' => array(
                    'url' => __('Account page URL', 'subs'),
                ),
            ),
            'subs_logout_link' => array(
                'description' => __('Display logout link (only for logged in users)', 'subs'),
                'attributes' => array(
                    'redirect' => __('URL to redirect after logout', 'subs'),
                ),
            ),
        );
    }
}
