<?php

/**
* Frontend Subscription Management Class
*
* Handles subscription-related functionality for the frontend, including
* subscription creation, management, and customer portal subscription features.
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
* Subs Frontend Subscription Class
*
* @class Subs_Frontend_Subscription
* @version 1.0.0
* @since 1.0.0
*/
class Subs_Frontend_Subscription {

/**
 * Available subscription plans
 *
 * @var array
 * @since 1.0.0
 */
private $available_plans = array();

/**
 * Current customer data
 *
 * @var object|null
 * @since 1.0.0
 */
private $current_customer = null;

/**
 * Stripe integration instance
 *
 * @var object
 * @since 1.0.0
 */
private $stripe = null;

/**
 * Constructor
 *
 * Initialize the frontend subscription class and set up hooks.
 *
 * @since 1.0.0
 */
public function __construct() {
    // Initialize hooks
    $this->init_hooks();

    // Load available plans
    $this->load_available_plans();

    // Initialize Stripe if available
    if (class_exists('Subs_Stripe')) {
        $this->stripe = new Subs_Stripe();
    }
}

/**
 * Initialize hooks
 *
 * Set up WordPress hooks for frontend subscription functionality.
 *
 * @access private
 * @since 1.0.0
 */
private function init_hooks() {
    // Frontend form handling
    add_action('wp', array($this, 'handle_subscription_forms'));

    // AJAX hooks for logged in and non-logged in users
    add_action('wp_ajax_subs_create_subscription', array($this, 'ajax_create_subscription'));
    add_action('wp_ajax_nopriv_subs_create_subscription', array($this, 'ajax_create_subscription'));

    add_action('wp_ajax_subs_update_subscription', array($this, 'ajax_update_subscription'));
    add_action('wp_ajax_subs_cancel_subscription', array($this, 'ajax_cancel_subscription'));
    add_action('wp_ajax_subs_pause_subscription', array($this, 'ajax_pause_subscription'));
    add_action('wp_ajax_subs_resume_subscription', array($this, 'ajax_resume_subscription'));

    add_action('wp_ajax_subs_update_payment_method', array($this, 'ajax_update_payment_method'));
    add_action('wp_ajax_subs_apply_coupon', array($this, 'ajax_apply_coupon'));

    // Frontend scripts and styles
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

    // Template filters
    add_filter('subs_subscription_form_fields', array($this, 'filter_form_fields'), 10, 2);
    add_filter('subs_subscription_plans', array($this, 'filter_available_plans'));
}

/**
 * Enqueue frontend scripts and styles
 *
 * @access public
 * @since 1.0.0
 */
public function enqueue_scripts() {
    // Only load on pages that need subscription functionality
    if (!$this->should_load_scripts()) {
        return;
    }

    // Stripe.js for payment processing
    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);

    // Main subscription frontend script
    wp_enqueue_script(
        'subs-frontend-subscription',
        SUBS_PLUGIN_URL . 'assets/js/frontend-subscription.js',
        array('jquery', 'stripe-js'),
        SUBS_VERSION,
        true
    );

    // Frontend subscription styles
    wp_enqueue_style(
        'subs-frontend-subscription',
        SUBS_PLUGIN_URL . 'assets/css/frontend-subscription.css',
        array(),
        SUBS_VERSION
    );

    // Get Stripe settings
    $stripe_settings = get_option('subs_stripe_settings', array());
    $test_mode = isset($stripe_settings['test_mode']) && $stripe_settings['test_mode'] === 'yes';

    $publishable_key = $test_mode ?
        (isset($stripe_settings['test_publishable_key']) ? $stripe_settings['test_publishable_key'] : '') :
        (isset($stripe_settings['publishable_key']) ? $stripe_settings['publishable_key'] : '');

    // Localize script with data
    wp_localize_script('subs-frontend-subscription', 'subs_subscription', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('subs_frontend_ajax'),
        'stripe_key' => $publishable_key,
        'currency' => $this->get_currency(),
        'strings' => array(
            'processing' => __('Processing...', 'subs'),
            'error' => __('An error occurred. Please try again.', 'subs'),
            'confirm_cancel' => __('Are you sure you want to cancel this subscription?', 'subs'),
            'confirm_pause' => __('Are you sure you want to pause this subscription?', 'subs'),
            'payment_processing' => __('Processing payment...', 'subs'),
            'subscription_created' => __('Subscription created successfully!', 'subs'),
            'subscription_updated' => __('Subscription updated successfully!', 'subs'),
            'subscription_cancelled' => __('Subscription cancelled successfully.', 'subs'),
            'payment_method_updated' => __('Payment method updated successfully.', 'subs'),
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

    // Load on subscription-related pages
    if (is_page() && $post) {
        // Check if page contains subscription shortcodes
        if (has_shortcode($post->post_content, 'subs_subscription_form') ||
            has_shortcode($post->post_content, 'subs_customer_portal') ||
            has_shortcode($post->post_content, 'subs_subscription_plans')) {
            return true;
        }
    }

    // Load on account/profile pages
    if (is_account_page()) {
        return true;
    }

    // Allow filtering
    return apply_filters('subs_load_subscription_scripts', false);
}

/**
 * Handle subscription form submissions
 *
 * @access public
 * @since 1.0.0
 */
public function handle_subscription_forms() {
    // Check if this is a subscription form submission
    if (!isset($_POST['subs_action']) || !wp_verify_nonce($_POST['subs_nonce'], 'subs_frontend_action')) {
        return;
    }

    $action = sanitize_text_field($_POST['subs_action']);

    switch ($action) {
        case 'create_subscription':
            $this->handle_create_subscription();
            break;

        case 'update_subscription':
            $this->handle_update_subscription();
            break;

        case 'cancel_subscription':
            $this->handle_cancel_subscription();
            break;

        case 'pause_subscription':
            $this->handle_pause_subscription();
            break;

        case 'resume_subscription':
            $this->handle_resume_subscription();
            break;

        case 'update_payment_method':
            $this->handle_update_payment_method();
            break;
    }
}

/**
 * Load available subscription plans
 *
 * @access private
 * @since 1.0.0
 */
private function load_available_plans() {
    // This would typically load from database or configuration
    // For now, we'll use a default set of plans
    $this->available_plans = array(
        'basic' => array(
            'id' => 'basic',
            'name' => __('Basic Plan', 'subs'),
            'description' => __('Perfect for getting started', 'subs'),
            'amount' => 9.99,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'trial_period_days' => 7,
            'features' => array(
                __('Basic features', 'subs'),
                __('Email support', 'subs'),
                __('Monthly updates', 'subs'),
            ),
        ),
        'premium' => array(
            'id' => 'premium',
            'name' => __('Premium Plan', 'subs'),
            'description' => __('Most popular choice', 'subs'),
            'amount' => 19.99,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'trial_period_days' => 14,
            'features' => array(
                __('All basic features', 'subs'),
                __('Priority support', 'subs'),
                __('Advanced features', 'subs'),
                __('Weekly updates', 'subs'),
            ),
        ),
        'enterprise' => array(
            'id' => 'enterprise',
            'name' => __('Enterprise Plan', 'subs'),
            'description' => __('For large organizations', 'subs'),
            'amount' => 49.99,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'trial_period_days' => 30,
            'features' => array(
                __('All premium features', 'subs'),
                __('24/7 phone support', 'subs'),
                __('Custom integrations', 'subs'),
                __('Daily updates', 'subs'),
                __('Dedicated account manager', 'subs'),
            ),
        ),
    );

    // Allow filtering of available plans
    $this->available_plans = apply_filters('subs_available_plans', $this->available_plans);
}

/**
 * Get available subscription plans
 *
 * @return array
 * @since 1.0.0
 */
public function get_available_plans() {
    return $this->available_plans;
}

/**
 * Get a specific plan by ID
 *
 * @param string $plan_id
 * @return array|null
 * @since 1.0.0
 */
public function get_plan($plan_id) {
    return isset($this->available_plans[$plan_id]) ? $this->available_plans[$plan_id] : null;
}

/**
 * Render subscription form
 *
 * @param array $args
 * @return string
 * @since 1.0.0
 */
public function render_subscription_form($args = array()) {
    $defaults = array(
        'plan_id' => '',
        'show_plans' => true,
        'show_customer_fields' => true,
        'show_payment_form' => true,
        'redirect_url' => '',
        'class' => 'subs-subscription-form',
    );

    $args = wp_parse_args($args, $defaults);

    ob_start();
    ?>
    <div class="<?php echo esc_attr($args['class']); ?>" id="subs-subscription-form">
        <form method="post" id="subs-subscription-form-element">
            <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
            <input type="hidden" name="subs_action" value="create_subscription">

            <?php if (!empty($args['redirect_url'])): ?>
                <input type="hidden" name="redirect_url" value="<?php echo esc_url($args['redirect_url']); ?>">
            <?php endif; ?>

            <!-- Plan Selection -->
            <?php if ($args['show_plans'] && empty($args['plan_id'])): ?>
                <div class="subs-form-section subs-plan-selection">
                    <h3><?php _e('Choose Your Plan', 'subs'); ?></h3>
                    <?php $this->render_plan_selection(); ?>
                </div>
            <?php elseif (!empty($args['plan_id'])): ?>
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($args['plan_id']); ?>">
                <?php $this->render_selected_plan_summary($args['plan_id']); ?>
            <?php endif; ?>

            <!-- Customer Information -->
            <?php if ($args['show_customer_fields']): ?>
                <div class="subs-form-section subs-customer-info">
                    <h3><?php _e('Your Information', 'subs'); ?></h3>
                    <?php $this->render_customer_fields(); ?>
                </div>
            <?php endif; ?>

            <!-- Payment Information -->
            <?php if ($args['show_payment_form']): ?>
                <div class="subs-form-section subs-payment-info">
                    <h3><?php _e('Payment Information', 'subs'); ?></h3>
                    <?php $this->render_payment_fields(); ?>
                </div>
            <?php endif; ?>

            <!-- Coupon Code -->
            <div class="subs-form-section subs-coupon-section">
                <div class="subs-coupon-toggle">
                    <button type="button" class="subs-toggle-coupon"><?php _e('Have a coupon code?', 'subs'); ?></button>
                </div>
                <div class="subs-coupon-form" style="display: none;">
                    <label for="coupon_code"><?php _e('Coupon Code', 'subs'); ?></label>
                    <div class="subs-coupon-input">
                        <input type="text" name="coupon_code" id="coupon_code" placeholder="<?php esc_attr_e('Enter coupon code', 'subs'); ?>">
                        <button type="button" class="subs-apply-coupon"><?php _e('Apply', 'subs'); ?></button>
                    </div>
                    <div class="subs-coupon-result"></div>
                </div>
            </div>

            <!-- Terms and Conditions -->
            <div class="subs-form-section subs-terms">
                <label class="subs-checkbox-label">
                    <input type="checkbox" name="accept_terms" id="accept_terms" required>
                    <?php printf(
                        __('I agree to the %s and %s', 'subs'),
                        '<a href="#" target="_blank">' . __('Terms of Service', 'subs') . '</a>',
                        '<a href="#" target="_blank">' . __('Privacy Policy', 'subs') . '</a>'
                    ); ?>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="subs-form-section subs-submit">
                <button type="submit" class="subs-submit-button" id="subs-submit-btn">
                    <span class="subs-submit-text"><?php _e('Start Subscription', 'subs'); ?></span>
                    <span class="subs-submit-loading" style="display: none;"><?php _e('Processing...', 'subs'); ?></span>
                </button>
            </div>

            <!-- Error Display -->
            <div class="subs-form-errors" id="subs-form-errors" style="display: none;"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render plan selection options
 *
 * @access private
 * @since 1.0.0
 */
private function render_plan_selection() {
    ?>
    <div class="subs-plans-grid">
        <?php foreach ($this->available_plans as $plan): ?>
            <div class="subs-plan-option">
                <input type="radio" name="plan_id" id="plan_<?php echo esc_attr($plan['id']); ?>"
                       value="<?php echo esc_attr($plan['id']); ?>" required>
                <label for="plan_<?php echo esc_attr($plan['id']); ?>" class="subs-plan-card">
                    <div class="subs-plan-header">
                        <h4 class="subs-plan-name"><?php echo esc_html($plan['name']); ?></h4>
                        <div class="subs-plan-price">
                            <?php echo $this->format_price($plan['amount'], $plan['currency']); ?>
                            <span class="subs-plan-interval">/ <?php echo esc_html($this->format_interval($plan['interval'], $plan['interval_count'])); ?></span>
                        </div>
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
                </label>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render selected plan summary
 *
 * @param string $plan_id
 * @access private
 * @since 1.0.0
 */
private function render_selected_plan_summary($plan_id) {
    $plan = $this->get_plan($plan_id);

    if (!$plan) {
        return;
    }
    ?>
    <div class="subs-selected-plan">
        <h3><?php _e('Selected Plan', 'subs'); ?></h3>
        <div class="subs-plan-summary">
            <div class="subs-plan-name"><?php echo esc_html($plan['name']); ?></div>
            <div class="subs-plan-price">
                <?php echo $this->format_price($plan['amount'], $plan['currency']); ?>
                <span class="subs-plan-interval">/ <?php echo esc_html($this->format_interval($plan['interval'], $plan['interval_count'])); ?></span>
            </div>
            <?php if ($plan['trial_period_days'] > 0): ?>
                <div class="subs-plan-trial">
                    <?php printf(__('%d-day free trial included', 'subs'), $plan['trial_period_days']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render customer information fields
 *
 * @access private
 * @since 1.0.0
 */
private function render_customer_fields() {
    $current_user = wp_get_current_user();
    ?>
    <div class="subs-customer-fields">
        <div class="subs-field-row">
            <div class="subs-field-group">
                <label for="first_name"><?php _e('First Name', 'subs'); ?> <span class="required">*</span></label>
                <input type="text" name="first_name" id="first_name"
                       value="<?php echo esc_attr($current_user->first_name); ?>" required>
            </div>
            <div class="subs-field-group">
                <label for="last_name"><?php _e('Last Name', 'subs'); ?> <span class="required">*</span></label>
                <input type="text" name="last_name" id="last_name"
                       value="<?php echo esc_attr($current_user->last_name); ?>" required>
            </div>
        </div>

        <div class="subs-field-group">
            <label for="email"><?php _e('Email Address', 'subs'); ?> <span class="required">*</span></label>
            <input type="email" name="email" id="email"
                   value="<?php echo esc_attr($current_user->user_email); ?>" required>
        </div>

        <div class="subs-field-group">
            <label for="phone"><?php _e('Phone Number', 'subs'); ?></label>
            <input type="tel" name="phone" id="phone">
        </div>

        <!-- Address Fields -->
        <div class="subs-address-fields">
            <h4><?php _e('Billing Address', 'subs'); ?></h4>

            <div class="subs-field-group">
                <label for="address_line_1"><?php _e('Address Line 1', 'subs'); ?></label>
                <input type="text" name="address_line_1" id="address_line_1">
            </div>

            <div class="subs-field-group">
                <label for="address_line_2"><?php _e('Address Line 2', 'subs'); ?></label>
                <input type="text" name="address_line_2" id="address_line_2">
            </div>

            <div class="subs-field-row">
                <div class="subs-field-group">
                    <label for="city"><?php _e('City', 'subs'); ?></label>
                    <input type="text" name="city" id="city">
                </div>
                <div class="subs-field-group">
                    <label for="state"><?php _e('State/Province', 'subs'); ?></label>
                    <input type="text" name="state" id="state">
                </div>
            </div>

            <div class="subs-field-row">
                <div class="subs-field-group">
                    <label for="postal_code"><?php _e('Postal Code', 'subs'); ?></label>
                    <input type="text" name="postal_code" id="postal_code">
                </div>
                <div class="subs-field-group">
                    <label for="country"><?php _e('Country', 'subs'); ?></label>
                    <select name="country" id="country">
                        <option value="US"><?php _e('United States', 'subs'); ?></option>
                        <option value="CA"><?php _e('Canada', 'subs'); ?></option>
                        <option value="GB"><?php _e('United Kingdom', 'subs'); ?></option>
                        <option value="AU"><?php _e('Australia', 'subs'); ?></option>
                        <!-- Add more countries as needed -->
                    </select>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render payment fields (Stripe Elements)
 *
 * @access private
 * @since 1.0.0
 */
private function render_payment_fields() {
    ?>
    <div class="subs-payment-fields">
        <div class="subs-field-group">
            <label for="card-element"><?php _e('Credit or Debit Card', 'subs'); ?></label>
            <div id="card-element" class="subs-stripe-element">
                <!-- Stripe Elements will create form elements here -->
            </div>
            <div id="card-errors" class="subs-field-error" role="alert"></div>
        </div>

        <!-- Billing Address Same as Above -->
        <div class="subs-field-group">
            <label class="subs-checkbox-label">
                <input type="checkbox" name="billing_same_as_shipping" id="billing_same_as_shipping" checked>
                <?php _e('Billing address is the same as above', 'subs'); ?>
            </label>
        </div>

        <!-- Separate Billing Address (hidden by default) -->
        <div class="subs-billing-address" id="subs-billing-address" style="display: none;">
            <h4><?php _e('Billing Address', 'subs'); ?></h4>
            <!-- Billing address fields would go here -->
        </div>
    </div>
    <?php
}

/**
 * Render customer subscriptions list
 *
 * @param int $customer_id
 * @return string
 * @since 1.0.0
 */
public function render_customer_subscriptions($customer_id = null) {
    if (!$customer_id && is_user_logged_in()) {
        $customer_id = $this->get_customer_id_by_user_id(get_current_user_id());
    }

    if (!$customer_id) {
        return '<p>' . __('Please log in to view your subscriptions.', 'subs') . '</p>';
    }

    $subscriptions = $this->get_customer_subscriptions($customer_id);

    if (empty($subscriptions)) {
        return '<p>' . __('You don\'t have any subscriptions yet.', 'subs') . '</p>';
    }

    ob_start();
    ?>
    <div class="subs-customer-subscriptions">
        <?php foreach ($subscriptions as $subscription): ?>
            <div class="subs-subscription-card" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                <div class="subs-subscription-header">
                    <h4 class="subs-subscription-name">
                        <?php echo esc_html($subscription->plan_name ?: __('Subscription', 'subs')); ?>
                    </h4>
                    <span class="subs-subscription-status subs-status-<?php echo esc_attr($subscription->status); ?>">
                        <?php echo esc_html(ucfirst($subscription->status)); ?>
                    </span>
                </div>

                <div class="subs-subscription-details">
                    <div class="subs-subscription-price">
                        <?php echo $this->format_price($subscription->amount, $subscription->currency); ?>
                        <span class="subs-subscription-interval">
                            / <?php echo esc_html($this->format_interval($subscription->interval_unit, $subscription->interval_count)); ?>
                        </span>
                    </div>

                    <?php if (!empty($subscription->plan_description)): ?>
                        <div class="subs-subscription-description">
                            <?php echo esc_html($subscription->plan_description); ?>
                        </div>
                    <?php endif; ?>

                    <div class="subs-subscription-dates">
                        <div class="subs-subscription-created">
                            <strong><?php _e('Started:', 'subs'); ?></strong>
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->created_date))); ?>
                        </div>

                        <?php if ($subscription->status === 'active' && !empty($subscription->next_payment_date)): ?>
                            <div class="subs-subscription-next-payment">
                                <strong><?php _e('Next Payment:', 'subs'); ?></strong>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($subscription->trial_end_date) && $subscription->trial_end_date !== '0000-00-00 00:00:00'): ?>
                            <div class="subs-subscription-trial">
                                <strong><?php _e('Trial Ends:', 'subs'); ?></strong>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->trial_end_date))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="subs-subscription-actions">
                    <?php $this->render_subscription_actions($subscription); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render subscription action buttons
 *
 * @param object $subscription
 * @access private
 * @since 1.0.0
 */
private function render_subscription_actions($subscription) {
    ?>
    <div class="subs-action-buttons">
        <?php switch ($subscription->status):
            case 'active':
            case 'trialing': ?>
                <button type="button" class="subs-action-btn subs-pause-btn"
                        data-action="pause" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                    <?php _e('Pause', 'subs'); ?>
                </button>
                <button type="button" class="subs-action-btn subs-cancel-btn"
                        data-action="cancel" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                    <?php _e('Cancel', 'subs'); ?>
                </button>
                <button type="button" class="subs-action-btn subs-update-payment-btn"
                        data-action="update_payment" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                    <?php _e('Update Payment Method', 'subs'); ?>
                </button>
                <?php break;

            case 'paused': ?>
                <button type="button" class="subs-action-btn subs-resume-btn"
                        data-action="resume" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                    <?php _e('Resume', 'subs'); ?>
                </button>

                <button type="button" class="subs-action-btn subs-cancel-btn"
                            data-action="cancel" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                        <?php _e('Cancel', 'subs'); ?>
                    </button>
                    <?php break;

                case 'cancelled': ?>
                    <div class="subs-cancelled-info">
                        <span><?php _e('Subscription cancelled', 'subs'); ?></span>
                        <?php if (!empty($subscription->cancelled_date)): ?>
                            <small><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->cancelled_date))); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php break;

                case 'past_due': ?>
                    <button type="button" class="subs-action-btn subs-update-payment-btn"
                            data-action="update_payment" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                        <?php _e('Update Payment Method', 'subs'); ?>
                    </button>
                    <button type="button" class="subs-action-btn subs-cancel-btn"
                            data-action="cancel" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                        <?php _e('Cancel', 'subs'); ?>
                    </button>
                    <?php break;

            endswitch; ?>
        </div>
        <?php
    }

    /**
     * Handle create subscription form submission
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_create_subscription() {
        $plan_id = sanitize_text_field($_POST['plan_id']);
        $plan = $this->get_plan($plan_id);

        if (!$plan) {
            $this->add_error(__('Invalid plan selected.', 'subs'));
            return;
        }

        // Validate required fields
        $required_fields = array('first_name', 'last_name', 'email');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $this->add_error(sprintf(__('%s is required.', 'subs'), ucfirst(str_replace('_', ' ', $field))));
                return;
            }
        }

        // Create or get customer
        $customer_data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'address_line_1' => sanitize_text_field($_POST['address_line_1']),
            'address_line_2' => sanitize_text_field($_POST['address_line_2']),
            'city' => sanitize_text_field($_POST['city']),
            'state' => sanitize_text_field($_POST['state']),
            'postal_code' => sanitize_text_field($_POST['postal_code']),
            'country' => sanitize_text_field($_POST['country']),
        );

        $customer_id = $this->create_or_update_customer($customer_data);

        if (!$customer_id) {
            $this->add_error(__('Failed to create customer record.', 'subs'));
            return;
        }

        // Process payment and create subscription
        $result = $this->create_subscription($customer_id, $plan_id, $_POST);

        if ($result['success']) {
            $redirect_url = !empty($_POST['redirect_url']) ? esc_url_raw($_POST['redirect_url']) : home_url('/account/');
            wp_redirect(add_query_arg('subscription_created', '1', $redirect_url));
            exit;
        } else {
            $this->add_error($result['message']);
        }
    }

    /**
     * Create subscription
     *
     * @param int $customer_id
     * @param string $plan_id
     * @param array $form_data
     * @return array
     * @since 1.0.0
     */
    private function create_subscription($customer_id, $plan_id, $form_data) {
        $plan = $this->get_plan($plan_id);

        if (!$plan) {
            return array('success' => false, 'message' => __('Invalid plan.', 'subs'));
        }

        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        // Calculate trial end date
        $trial_end_date = null;
        if ($plan['trial_period_days'] > 0) {
            $trial_end_date = date('Y-m-d H:i:s', strtotime('+' . $plan['trial_period_days'] . ' days'));
        }

        // Calculate next payment date
        $next_payment_date = $trial_end_date ?: date('Y-m-d H:i:s', strtotime('+1 ' . $plan['interval']));

        // Apply coupon if provided
        $final_amount = $plan['amount'];
        $coupon_applied = null;

        if (!empty($form_data['coupon_code'])) {
            $coupon_result = $this->apply_coupon($form_data['coupon_code'], $plan['amount']);
            if ($coupon_result['success']) {
                $final_amount = $coupon_result['discounted_amount'];
                $coupon_applied = $coupon_result['coupon'];
            }
        }

        // Create subscription record
        $subscription_data = array(
            'customer_id' => $customer_id,
            'plan_name' => $plan['name'],
            'plan_description' => $plan['description'],
            'amount' => $final_amount,
            'currency' => $plan['currency'],
            'interval_unit' => $plan['interval'],
            'interval_count' => $plan['interval_count'],
            'status' => $trial_end_date ? 'trialing' : 'active',
            'trial_end_date' => $trial_end_date,
            'next_payment_date' => $next_payment_date,
            'created_date' => current_time('mysql'),
            'updated_date' => current_time('mysql'),
        );

        $result = $wpdb->insert($subscriptions_table, $subscription_data);

        if ($result === false) {
            return array('success' => false, 'message' => __('Failed to create subscription.', 'subs'));
        }

        $subscription_id = $wpdb->insert_id;

        // Create Stripe subscription if not in trial
        if (!$trial_end_date && $this->stripe) {
            $stripe_result = $this->stripe->create_subscription($customer_id, $plan, $subscription_id);

            if (!$stripe_result['success']) {
                // Delete the subscription record if Stripe fails
                $wpdb->delete($subscriptions_table, array('id' => $subscription_id));
                return array('success' => false, 'message' => $stripe_result['message']);
            }

            // Update subscription with Stripe data
            $wpdb->update(
                $subscriptions_table,
                array('stripe_subscription_id' => $stripe_result['stripe_subscription_id']),
                array('id' => $subscription_id)
            );
        }

        // Log the creation
        $this->log_subscription_activity($subscription_id, 'payment_method_updated');
        do_action('subs_payment_method_updated', $subscription_id);

        return array('success' => true, 'message' => __('Payment method updated successfully.', 'subs'));
    }

    /**
     * Handle update subscription (non-AJAX)
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_update_subscription() {
        // Implementation for non-AJAX subscription updates
    }

    /**
     * Handle cancel subscription (non-AJAX)
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_cancel_subscription() {
        // Implementation for non-AJAX subscription cancellation
    }

    /**
     * Handle pause subscription (non-AJAX)
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_pause_subscription() {
        // Implementation for non-AJAX subscription pause
    }

    /**
     * Handle resume subscription (non-AJAX)
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_resume_subscription() {
        // Implementation for non-AJAX subscription resume
    }

    /**
     * Handle update payment method (non-AJAX)
     *
     * @access private
     * @since 1.0.0
     */
    private function handle_update_payment_method() {
        // Implementation for non-AJAX payment method update
    }

    /**
     * Update subscription plan
     *
     * @param int $subscription_id
     * @param array $data
     * @return array
     * @since 1.0.0
     */
    private function update_subscription_plan($subscription_id, $data) {
        // Implementation for changing subscription plans
        return array('success' => false, 'message' => __('Plan updates not yet implemented.', 'subs'));
    }

    /**
     * Get customer subscriptions
     *
     * @param int $customer_id
     * @return array
     * @since 1.0.0
     */
    private function get_customer_subscriptions($customer_id) {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $subscriptions_table WHERE customer_id = %d ORDER BY created_date DESC",
            $customer_id
        ));
    }

    /**
     * Get customer ID by WordPress user ID
     *
     * @param int $user_id
     * @return int|null
     * @since 1.0.0
     */
    private function get_customer_id_by_user_id($user_id) {
        global $wpdb;

        $customers_table = $wpdb->prefix . 'subs_customers';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $customers_table WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Create or update customer
     *
     * @param array $customer_data
     * @return int|false
     * @since 1.0.0
     */
    private function create_or_update_customer($customer_data) {
        global $wpdb;

        $customers_table = $wpdb->prefix . 'subs_customers';

        // Check if customer exists by email
        $existing_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $customers_table WHERE email = %s",
            $customer_data['email']
        ));

        if ($existing_customer) {
            // Update existing customer
            $customer_data['updated_date'] = current_time('mysql');

            $result = $wpdb->update(
                $customers_table,
                $customer_data,
                array('id' => $existing_customer->id)
            );

            return $result !== false ? $existing_customer->id : false;
        } else {
            // Create new customer
            $customer_data['created_date'] = current_time('mysql');
            $customer_data['updated_date'] = current_time('mysql');

            // Link to WordPress user if logged in
            if (is_user_logged_in()) {
                $customer_data['user_id'] = get_current_user_id();
            }

            $result = $wpdb->insert($customers_table, $customer_data);

            return $result !== false ? $wpdb->insert_id : false;
        }
    }

    /**
     * Apply coupon code
     *
     * @param string $coupon_code
     * @param float $amount
     * @return array
     * @since 1.0.0
     */
    private function apply_coupon($coupon_code, $amount) {
        global $wpdb;

        $coupons_table = $wpdb->prefix . 'subs_coupons';

        // Check if coupons table exists (it might not in basic setup)
        if ($wpdb->get_var("SHOW TABLES LIKE '$coupons_table'") !== $coupons_table) {
            return array('success' => false, 'message' => __('Coupons not supported.', 'subs'));
        }

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $coupons_table WHERE code = %s AND active = 1",
            strtoupper($coupon_code)
        ));

        if (!$coupon) {
            return array('success' => false, 'message' => __('Invalid coupon code.', 'subs'));
        }

        // Check expiration
        if (!empty($coupon->expires_at) && strtotime($coupon->expires_at) < time()) {
            return array('success' => false, 'message' => __('Coupon has expired.', 'subs'));
        }

        // Check usage limits
        if ($coupon->usage_limit > 0 && $coupon->times_used >= $coupon->usage_limit) {
            return array('success' => false, 'message' => __('Coupon usage limit reached.', 'subs'));
        }

        // Calculate discount
        $discount_amount = 0;
        if ($coupon->type === 'percentage') {
            $discount_amount = ($amount * $coupon->value) / 100;
        } else {
            $discount_amount = min($coupon->value, $amount);
        }

        $discounted_amount = max(0, $amount - $discount_amount);

        return array(
            'success' => true,
            'coupon' => $coupon,
            'discount_amount' => $discount_amount,
            'discounted_amount' => $discounted_amount,
            'message' => sprintf(__('Coupon applied! You saved %s', 'subs'), $this->format_price($discount_amount))
        );
    }

    /**
     * Log subscription activity
     *
     * @param int $subscription_id
     * @param string $action
     * @param array $data
     * @since 1.0.0
     */
    private function log_subscription_activity($subscription_id, $action, $data = array()) {
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
            )
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
     * Get currency from settings
     *
     * @return string
     * @since 1.0.0
     */
    private function get_currency() {
        $settings = get_option('subs_general_settings', array());
        return isset($settings['currency']) ? $settings['currency'] : 'USD';
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
     * Filter form fields
     *
     * @param array $fields
     * @param string $context
     * @return array
     * @since 1.0.0
     */
    public function filter_form_fields($fields, $context = 'subscription') {
        // Allow modification of form fields
        return $fields;
    }

    /**
     * Filter available plans
     *
     * @param array $plans
     * @return array
     * @since 1.0.0
     */
    public function filter_available_plans($plans) {
        // Allow modification of available plans
        return $plans;
    }

    /**
     * AJAX: Create subscription
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_create_subscription() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        $plan_id = sanitize_text_field($_POST['plan_id']);
        $plan = $this->get_plan($plan_id);

        if (!$plan) {
            wp_send_json_error(__('Invalid plan selected.', 'subs'));
        }

        // Validate required fields
        $required_fields = array('first_name', 'last_name', 'email');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(sprintf(__('%s is required.', 'subs'), ucfirst(str_replace('_', ' ', $field))));
            }
        }

        // Create customer
        $customer_data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'address_line_1' => sanitize_text_field($_POST['address_line_1']),
            'address_line_2' => sanitize_text_field($_POST['address_line_2']),
            'city' => sanitize_text_field($_POST['city']),
            'state' => sanitize_text_field($_POST['state']),
            'postal_code' => sanitize_text_field($_POST['postal_code']),
            'country' => sanitize_text_field($_POST['country']),
        );

        $customer_id = $this->create_or_update_customer($customer_data);

        if (!$customer_id) {
            wp_send_json_error(__('Failed to create customer record.', 'subs'));
        }

        // Create subscription
        $result = $this->create_subscription($customer_id, $plan_id, $_POST);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'subscription_id' => $result['subscription_id'],
                'redirect_url' => home_url('/account/')
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Update subscription
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_update_subscription() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id']);

        // Verify ownership
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('Access denied.', 'subs'));
        }

        // Handle specific updates based on action
        $action = sanitize_text_field($_POST['action']);

        switch ($action) {
            case 'update_plan':
                $result = $this->update_subscription_plan($subscription_id, $_POST);
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'subs'));
        }

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
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
     * AJAX: Cancel subscription
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_cancel_subscription() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id']);

        // Verify ownership
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('Access denied.', 'subs'));
        }

        $result = $this->cancel_subscription($subscription_id);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Pause subscription
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_pause_subscription() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id']);

        // Verify ownership
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('Access denied.', 'subs'));
        }

        $result = $this->pause_subscription($subscription_id);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Resume subscription
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_resume_subscription() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id']);

        // Verify ownership
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('Access denied.', 'subs'));
        }

        $result = $this->resume_subscription($subscription_id);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Update payment method
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_update_payment_method() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        $subscription_id = intval($_POST['subscription_id']);

        // Verify ownership
        if (!$this->user_owns_subscription($subscription_id)) {
            wp_send_json_error(__('Access denied.', 'subs'));
        }

        // This would integrate with Stripe to update payment method
        $result = $this->update_payment_method($subscription_id, $_POST);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: Apply coupon
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_apply_coupon() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_frontend_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        $coupon_code = sanitize_text_field($_POST['coupon_code']);
        $amount = floatval($_POST['amount']);

        $result = $this->apply_coupon($coupon_code, $amount);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Check if user owns subscription
     *
     * @param int $subscription_id
     * @return bool
     * @since 1.0.0
     */
    private function user_owns_subscription($subscription_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $customers_table = $wpdb->prefix . 'subs_customers';

        $user_id = get_current_user_id();

        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT s.* FROM $subscriptions_table s
             JOIN $customers_table c ON s.customer_id = c.id
             WHERE s.id = %d AND c.user_id = %d",
            $subscription_id,
            $user_id
        ));

        return !empty($subscription);
    }

    /**
     * Cancel subscription
     *
     * @param int $subscription_id
     * @return array
     * @since 1.0.0
     */
    private function cancel_subscription($subscription_id) {
        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $result = $wpdb->update(
            $subscriptions_table,
            array(
                'status' => 'cancelled',
                'cancelled_date' => current_time('mysql'),
                'updated_date' => current_time('mysql')
            ),
            array('id' => $subscription_id)
        );

        if ($result !== false) {
            $this->log_subscription_activity($subscription_id, 'cancelled');
            do_action('subs_subscription_cancelled', $subscription_id);

            return array('success' => true, 'message' => __('Subscription cancelled successfully.', 'subs'));
        }

        return array('success' => false, 'message' => __('Failed to cancel subscription.', 'subs'));
    }

    /**
     * Pause subscription
     *
     * @param int $subscription_id
     * @return array
     * @since 1.0.0
     */
    private function pause_subscription($subscription_id) {
        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $result = $wpdb->update(
            $subscriptions_table,
            array(
                'status' => 'paused',
                'updated_date' => current_time('mysql')
            ),
            array('id' => $subscription_id)
        );

        if ($result !== false) {
            $this->log_subscription_activity($subscription_id, 'paused');
            do_action('subs_subscription_paused', $subscription_id);

            return array('success' => true, 'message' => __('Subscription paused successfully.', 'subs'));
        }

        return array('success' => false, 'message' => __('Failed to pause subscription.', 'subs'));
    }

    /**
     * Resume subscription
     *
     * @param int $subscription_id
     * @return array
     * @since 1.0.0
     */
    private function resume_subscription($subscription_id) {
        global $wpdb;
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $result = $wpdb->update(
            $subscriptions_table,
            array(
                'status' => 'active',
                'updated_date' => current_time('mysql')
            ),
            array('id' => $subscription_id)
        );

        if ($result !== false) {
            $this->log_subscription_activity($subscription_id, 'resumed');
            do_action('subs_subscription_resumed', $subscription_id);

            return array('success' => true, 'message' => __('Subscription resumed successfully.', 'subs'));
        }

        return array('success' => false, 'message' => __('Failed to resume subscription.', 'subs'));
    }

}
