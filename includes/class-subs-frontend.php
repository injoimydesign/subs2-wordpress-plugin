<?php
/**
 * Frontend Controller Class
 *
 * Handles all frontend functionality including customer portal,
 * subscription forms, shortcodes, and user interface elements.
 *
 * @package Subs
 * @subpackage Frontend
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs Frontend Class
 *
 * @class Subs_Frontend
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Frontend {

    /**
     * Add frontend notice
     *
     * @param string $message
     * @param string $type
     * @since 1.0.0
     */
    private function add_notice($message, $type = 'info') {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['subs_notices'])) {
            $_SESSION['subs_notices'] = array();
        }

        $_SESSION['subs_notices'][] = array(
            'message' => $message,
            'type' => $type,
        );
    }

    /**
     * Get and clear frontend notices
     *
     * @return array
     * @since 1.0.0
     */
    public function get_notices() {
        if (!session_id()) {
            session_start();
        }

        $notices = $_SESSION['subs_notices'] ?? array();
        unset($_SESSION['subs_notices']);

        return $notices;
    }

    /**
     * Display frontend notices
     *
     * @since 1.0.0
     */
    public function display_notices() {
        $notices = $this->get_notices();
        if (empty($notices)) {
            return;
        }

        foreach ($notices as $notice) {
            printf(
                '<div class="subs-notice subs-notice-%s">%s</div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    /**
     * Subscription form shortcode
     *
     * @param array $atts
     * @param string $content
     * @return string
     * @since 1.0.0
     */
    public function subscription_form_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'product_name' => '',
            'amount' => '',
            'currency' => 'USD',
            'billing_period' => 'month',
            'billing_interval' => '1',
            'show_trial' => 'no',
            'trial_days' => '7',
            'redirect_url' => '',
        ), $atts);

        ob_start();
        $this->render_subscription_form($atts);
        return ob_get_clean();
    }

    /**
     * Customer portal shortcode
     *
     * @param array $atts
     * @param string $content
     * @return string
     * @since 1.0.0
     */
    public function customer_portal_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'show_subscriptions' => 'yes',
            'show_profile' => 'yes',
            'show_payment_methods' => 'yes',
        ), $atts);

        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your customer portal.', 'subs') . '</p>';
        }

        ob_start();
        $this->render_customer_portal($atts);
        return ob_get_clean();
    }

    /**
     * Subscription status shortcode
     *
     * @param array $atts
     * @param string $content
     * @return string
     * @since 1.0.0
     */
    public function subscription_status_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'subscription_id' => '',
            'show_details' => 'yes',
            'show_actions' => 'yes',
        ), $atts);

        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view subscription status.', 'subs') . '</p>';
        }

        ob_start();
        $this->render_subscription_status($atts);
        return ob_get_clean();
    }

    /**
     * Render subscription form
     *
     * @param array $args
     * @since 1.0.0
     */
    private function render_subscription_form($args) {
        $stripe = new Subs_Stripe();

        ?>
        <div class="subs-subscription-form-wrapper">
            <?php $this->display_notices(); ?>

            <form class="subs-subscription-form" method="post" action="">
                <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                <input type="hidden" name="subs_action" value="create_subscription" />

                <?php if (!is_user_logged_in()): ?>
                    <div class="subs-customer-info">
                        <h3><?php _e('Customer Information', 'subs'); ?></h3>

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="customer_first_name"><?php _e('First Name', 'subs'); ?></label>
                                <input type="text" id="customer_first_name" name="customer_first_name" required />
                            </div>
                            <div class="subs-form-col">
                                <label for="customer_last_name"><?php _e('Last Name', 'subs'); ?></label>
                                <input type="text" id="customer_last_name" name="customer_last_name" required />
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="customer_email"><?php _e('Email Address', 'subs'); ?> *</label>
                                <input type="email" id="customer_email" name="customer_email" required />
                            </div>
                            <div class="subs-form-col">
                                <label for="customer_phone"><?php _e('Phone Number', 'subs'); ?></label>
                                <input type="tel" id="customer_phone" name="customer_phone" />
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="subs-subscription-info">
                    <h3><?php _e('Subscription Details', 'subs'); ?></h3>

                    <div class="subs-form-row">
                        <div class="subs-form-col">
                            <label for="product_name"><?php _e('Product/Service', 'subs'); ?> *</label>
                            <input type="text" id="product_name" name="product_name"
                                   value="<?php echo esc_attr($args['product_name']); ?>"
                                   <?php echo !empty($args['product_name']) ? 'readonly' : 'required'; ?> />
                        </div>
                    </div>

                    <div class="subs-form-row">
                        <div class="subs-form-col-third">
                            <label for="amount"><?php _e('Amount', 'subs'); ?> *</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                                   value="<?php echo esc_attr($args['amount']); ?>"
                                   <?php echo !empty($args['amount']) ? 'readonly' : 'required'; ?> />
                        </div>
                        <div class="subs-form-col-third">
                            <label for="currency"><?php _e('Currency', 'subs'); ?></label>
                            <select id="currency" name="currency" <?php echo !empty($args['currency']) && $args['currency'] !== 'USD' ? 'disabled' : ''; ?>>
                                <?php
                                $stripe = new Subs_Stripe();
                                $currencies = $stripe->get_supported_currencies();
                                foreach ($currencies as $code => $name):
                                ?>
                                    <option value="<?php echo esc_attr($code); ?>"
                                            <?php selected($args['currency'], $code); ?>>
                                        <?php echo esc_html($code . ' - ' . $name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="subs-form-col-third">
                            <label for="billing_frequency"><?php _e('Billing', 'subs'); ?></label>
                            <div class="billing-frequency-wrapper">
                                <?php _e('Every', 'subs'); ?>
                                <input type="number" id="billing_interval" name="billing_interval"
                                       value="<?php echo esc_attr($args['billing_interval']); ?>"
                                       min="1" style="width: 60px;" />
                                <select id="billing_period" name="billing_period">
                                    <?php foreach (Subs_Subscription::get_billing_periods() as $period => $label): ?>
                                        <option value="<?php echo esc_attr($period); ?>"
                                                <?php selected($args['billing_period'], $period); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php if ($args['show_trial'] === 'yes'): ?>
                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="trial_end"><?php _e('Trial End Date (Optional)', 'subs'); ?></label>
                                <input type="datetime-local" id="trial_end" name="trial_end"
                                       value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime('+' . $args['trial_days'] . ' days'))); ?>" />
                                <small><?php printf(__('Leave empty for no trial period. Default: %d days from now.', 'subs'), $args['trial_days']); ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($stripe->is_configured()): ?>
                    <div class="subs-payment-info">
                        <h3><?php _e('Payment Information', 'subs'); ?></h3>
                        <div id="payment-element">
                            <!-- Stripe Elements will be inserted here -->
                        </div>
                        <div id="payment-message" class="hidden"></div>
                    </div>
                <?php endif; ?>

                <div class="subs-form-actions">
                    <button type="submit" class="subs-btn subs-btn-primary" id="submit-button">
                        <?php _e('Create Subscription', 'subs'); ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if ($stripe->is_configured()): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const stripe = Stripe('<?php echo esc_js($stripe->get_publishable_key()); ?>');
                    const elements = stripe.elements();
                    const paymentElement = elements.create('payment');
                    paymentElement.mount('#payment-element');

                    // Handle form submission
                    const form = document.querySelector('.subs-subscription-form');
                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const submitButton = document.getElementById('submit-button');
                        submitButton.disabled = true;
                        submitButton.textContent = '<?php echo esc_js(__('Processing...', 'subs')); ?>';

                        // Create payment intent
                        const formData = new FormData(form);
                        const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success && result.data.client_secret) {
                            // Confirm payment with Stripe
                            const {error} = await stripe.confirmPayment({
                                elements,
                                clientSecret: result.data.client_secret,
                                confirmParams: {
                                    return_url: window.location.href
                                }
                            });

                            if (error) {
                                document.getElementById('payment-message').textContent = error.message;
                                document.getElementById('payment-message').classList.remove('hidden');
                            }
                        } else {
                            // Handle API error
                            document.getElementById('payment-message').textContent = result.data || 'An error occurred';
                            document.getElementById('payment-message').classList.remove('hidden');
                        }

                        submitButton.disabled = false;
                        submitButton.textContent = '<?php echo esc_js(__('Create Subscription', 'subs')); ?>';
                    });
                });
            </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Render customer portal
     *
     * @param array $args
     * @since 1.0.0
     */
    private function render_customer_portal($args) {
        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            echo '<p>' . __('Customer record not found. Please contact support.', 'subs') . '</p>';
            return;
        }

        $subscriptions = $customer_handler->get_customer_subscriptions($customer->id);

        ?>
        <div class="subs-customer-portal">
            <?php $this->display_notices(); ?>

            <div class="subs-portal-header">
                <h2><?php _e('Customer Portal', 'subs'); ?></h2>
                <p><?php printf(__('Welcome back, %s!', 'subs'), esc_html($customer_handler->get_customer_display_name($customer))); ?></p>
            </div>

            <?php if ($args['show_subscriptions'] === 'yes'): ?>
                <div class="subs-portal-section subs-subscriptions-section">
                    <h3><?php _e('Your Subscriptions', 'subs'); ?></h3>

                    <?php if (empty($subscriptions)): ?>
                        <p><?php _e('You don\'t have any subscriptions yet.', 'subs'); ?></p>
                    <?php else: ?>
                        <div class="subs-subscriptions-list">
                            <?php foreach ($subscriptions as $subscription): ?>
                                <div class="subs-subscription-card">
                                    <div class="subscription-header">
                                        <h4><?php echo esc_html($subscription->product_name); ?></h4>
                                        <span class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                                            <?php echo esc_html(Subs_Subscription::get_statuses()[$subscription->status] ?? $subscription->status); ?>
                                        </span>
                                    </div>

                                    <div class="subscription-details">
                                        <p><strong><?php _e('Amount:', 'subs'); ?></strong>
                                           <?php printf('%s %s', esc_html($subscription->amount), esc_html(strtoupper($subscription->currency))); ?></p>
                                        <p><strong><?php _e('Billing:', 'subs'); ?></strong>
                                           <?php printf(__('Every %d %s', 'subs'), $subscription->billing_interval, $subscription->billing_period); ?></p>
                                        <p><strong><?php _e('Next Payment:', 'subs'); ?></strong>
                                           <?php echo $subscription->next_payment_date ?
                                                    esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))) :
                                                    __('N/A', 'subs'); ?></p>
                                    </div>

                                    <div class="subscription-actions">
                                        <?php $subscription_handler = new Subs_Subscription(); ?>

                                        <?php if ($subscription_handler->can_pause($subscription)): ?>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                                                <input type="hidden" name="subs_action" value="pause_subscription" />
                                                <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->id); ?>" />
                                                <button type="submit" class="subs-btn subs-btn-secondary"
                                                        onclick="return confirm('<?php echo esc_js(__('Are you sure you want to pause this subscription?', 'subs')); ?>');">
                                                    <?php _e('Pause', 'subs'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($subscription_handler->can_resume($subscription)): ?>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                                                <input type="hidden" name="subs_action" value="resume_subscription" />
                                                <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->id); ?>" />
                                                <button type="submit" class="subs-btn subs-btn-primary">
                                                    <?php _e('Resume', 'subs'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($subscription_handler->can_cancel($subscription)): ?>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                                                <input type="hidden" name="subs_action" value="cancel_subscription" />
                                                <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->id); ?>" />
                                                <button type="submit" class="subs-btn subs-btn-danger"
                                                        onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this subscription?', 'subs')); ?>');">
                                                    <?php _e('Cancel', 'subs'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($args['show_profile'] === 'yes'): ?>
                <div class="subs-portal-section subs-profile-section">
                    <h3><?php _e('Profile Information', 'subs'); ?></h3>

                    <form method="post" class="subs-customer-info-form">
                        <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                        <input type="hidden" name="subs_action" value="update_customer_info" />

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="first_name"><?php _e('First Name', 'subs'); ?></label>
                                <input type="text" id="first_name" name="first_name"
                                       value="<?php echo esc_attr($customer->first_name); ?>" />
                            </div>
                            <div class="subs-form-col">
                                <label for="last_name"><?php _e('Last Name', 'subs'); ?></label>
                                <input type="text" id="last_name" name="last_name"
                                       value="<?php echo esc_attr($customer->last_name); ?>" />
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="phone"><?php _e('Phone Number', 'subs'); ?></label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo esc_attr($customer->phone); ?>" />
                            </div>
                        </div>

                        <h4><?php _e('Address', 'subs'); ?></h4>

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="address_line1"><?php _e('Address Line 1', 'subs'); ?></label>
                                <input type="text" id="address_line1" name="address_line1"
                                       value="<?php echo esc_attr($customer->address_line1); ?>" />
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="address_line2"><?php _e('Address Line 2', 'subs'); ?></label>
                                <input type="text" id="address_line2" name="address_line2"
                                       value="<?php echo esc_attr($customer->address_line2); ?>" />
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-col-third">
                                <label for="city"><?php _e('City', 'subs'); ?></label>
                                <input type="text" id="city" name="city"
                                       value="<?php echo esc_attr($customer->city); ?>" />
                            </div>
                            <div class="subs-form-col-third">
                                <label for="state"><?php _e('State/Province', 'subs'); ?></label>
                                <input type="text" id="state" name="state"
                                       value="<?php echo esc_attr($customer->state); ?>" />
                            </div>
                            <div class="subs-form-col-third">
                                <label for="postal_code"><?php _e('Postal Code', 'subs'); ?></label>
                                <input type="text" id="postal_code" name="postal_code"
                                       value="<?php echo esc_attr($customer->postal_code); ?>" />
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="country"><?php _e('Country', 'subs'); ?></label>
                                <input type="text" id="country" name="country"
                                       value="<?php echo esc_attr($customer->country); ?>" />
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-col">
                                <label for="flag_delivery_address"><?php _e('Flag Delivery Address', 'subs'); ?></label>
                                <textarea id="flag_delivery_address" name="flag_delivery_address" rows="3"><?php echo esc_textarea($customer->flag_delivery_address); ?></textarea>
                                <small><?php _e('Special delivery instructions for flag orders.', 'subs'); ?></small>
                            </div>
                        </div>

                        <div class="subs-form-actions">
                            <button type="submit" class="subs-btn subs-btn-primary">
                                <?php _e('Update Profile', 'subs'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render subscription status
     *
     * @param array $args
     * @since 1.0.0
     */
    private function render_subscription_status($args) {
        $subscription_id = intval($args['subscription_id']);

        if (!$subscription_id) {
            echo '<p>' . __('Invalid subscription ID.', 'subs') . '</p>';
            return;
        }

        // Verify user owns this subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            echo '<p>' . __('You do not have permission to view this subscription.', 'subs') . '</p>';
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription) {
            echo '<p>' . __('Subscription not found.', 'subs') . '</p>';
            return;
        }

        ?>
        <div class="subs-subscription-status">
            <div class="subscription-header">
                <h3><?php echo esc_html($subscription->product_name); ?></h3>
                <span class="subscription-status status-<?php echo esc_attr($subscription->status); ?>">
                    <?php echo esc_html(Subs_Subscription::get_statuses()[$subscription->status] ?? $subscription->status); ?>
                </span>
            </div>

            <?php if ($args['show_details'] === 'yes'): ?>
                <div class="subscription-details">
                    <div class="detail-row">
                        <span class="label"><?php _e('Amount:', 'subs'); ?></span>
                        <span class="value"><?php printf('%s %s', esc_html($subscription->amount), esc_html(strtoupper($subscription->currency))); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><?php _e('Billing:', 'subs'); ?></span>
                        <span class="value"><?php printf(__('Every %d %s', 'subs'), $subscription->billing_interval, $subscription->billing_period); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><?php _e('Created:', 'subs'); ?></span>
                        <span class="value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->created_date))); ?></span>
                    </div>
                    <?php if ($subscription->next_payment_date): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Next Payment:', 'subs'); ?></span>
                            <span class="value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($args['show_actions'] === 'yes'): ?>
                <div class="subscription-actions">
                    <?php if ($subscription_handler->can_pause($subscription)): ?>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                            <input type="hidden" name="subs_action" value="pause_subscription" />
                            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->id); ?>" />
                            <button type="submit" class="subs-btn subs-btn-secondary">
                                <?php _e('Pause', 'subs'); ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($subscription_handler->can_resume($subscription)): ?>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                            <input type="hidden" name="subs_action" value="resume_subscription" />
                            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->id); ?>" />
                            <button type="submit" class="subs-btn subs-btn-primary">
                                <?php _e('Resume', 'subs'); ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($subscription_handler->can_cancel($subscription)): ?>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('subs_frontend_action', 'subs_nonce'); ?>
                            <input type="hidden" name="subs_action" value="cancel_subscription" />
                            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->id); ?>" />
                            <button type="submit" class="subs-btn subs-btn-danger"
                                    onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this subscription?', 'subs')); ?>');">
                                <?php _e('Cancel', 'subs'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add body classes for frontend pages
     *
     * @param array $classes
     * @return array
     * @since 1.0.0
     */
    public function body_classes($classes) {
        if (get_query_var('subs_page')) {
            $classes[] = 'subs-page';
            $classes[] = 'subs-page-' . sanitize_html_class(get_query_var('subs_page'));
        }

        if (get_query_var('subs_action')) {
            $classes[] = 'subs-action-' . sanitize_html_class(get_query_var('subs_action'));
        }

        return $classes;
    }

    /* Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize frontend functionality
     *
     * @since 1.0.0
     */
    public function init() {
        // Template hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('template_include', array($this, 'template_include'));

        // Shortcodes
        add_shortcode('subs_subscription_form', array($this, 'subscription_form_shortcode'));
        add_shortcode('subs_customer_portal', array($this, 'customer_portal_shortcode'));
        add_shortcode('subs_subscription_status', array($this, 'subscription_status_shortcode'));

        // Rewrite rules for customer portal
        add_rewrite_rule('^subscription-portal/?$', 'index.php?subs_page=portal', 'top');
        add_rewrite_rule('^subscription-portal/([^/]+)/?$', 'index.php?subs_page=portal&subs_action=$matches[1]', 'top');

        // Query vars
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Handle frontend actions
        add_action('template_redirect', array($this, 'handle_frontend_actions'));

        // Body classes
        add_filter('body_class', array($this, 'body_classes'));
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        // Only enqueue on pages that need it
        if (!$this->should_enqueue_scripts()) {
            return;
        }

        // Main frontend stylesheet
        wp_enqueue_style(
            'subs-frontend',
            SUBS_ASSETS_URL . 'css/frontend.css',
            array(),
            SUBS_VERSION
        );

        // Main frontend JavaScript
        wp_enqueue_script(
            'subs-frontend',
            SUBS_ASSETS_URL . 'js/frontend.js',
            array('jquery'),
            SUBS_VERSION,
            true
        );

        // Stripe Elements if configured
        $stripe = new Subs_Stripe();
        if ($stripe->is_configured()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);

            wp_localize_script('subs-frontend', 'subs_stripe', array(
                'publishable_key' => $stripe->get_publishable_key(),
                'create_payment_intent_url' => admin_url('admin-ajax.php?action=subs_create_payment_intent'),
            ));
        }

        // Localize script for AJAX and translations
        wp_localize_script('subs-frontend', 'subs_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('subs_frontend_nonce'),
            'current_user_id' => get_current_user_id(),
            'is_user_logged_in' => is_user_logged_in(),
            'messages' => array(
                'processing' => __('Processing...', 'subs'),
                'error' => __('An error occurred. Please try again.', 'subs'),
                'success' => __('Success!', 'subs'),
                'confirm_cancel' => __('Are you sure you want to cancel this subscription?', 'subs'),
                'confirm_pause' => __('Are you sure you want to pause this subscription?', 'subs'),
            ),
        ));
    }

    /**
     * Check if scripts should be enqueued
     *
     * @return bool
     * @since 1.0.0
     */
    private function should_enqueue_scripts() {
        global $post;

        // Always enqueue on subscription portal pages
        if (get_query_var('subs_page')) {
            return true;
        }

        // Check for shortcodes in post content
        if ($post && has_shortcode($post->post_content, 'subs_subscription_form')) {
            return true;
        }

        if ($post && has_shortcode($post->post_content, 'subs_customer_portal')) {
            return true;
        }

        if ($post && has_shortcode($post->post_content, 'subs_subscription_status')) {
            return true;
        }

        // Allow themes and plugins to force enqueue
        return apply_filters('subs_enqueue_frontend_scripts', false);
    }

    /**
     * Add custom query vars
     *
     * @param array $vars
     * @return array
     * @since 1.0.0
     */
    public function add_query_vars($vars) {
        $vars[] = 'subs_page';
        $vars[] = 'subs_action';
        $vars[] = 'subscription_id';
        return $vars;
    }

    /**
     * Handle template inclusion for custom pages
     *
     * @param string $template
     * @return string
     * @since 1.0.0
     */
    public function template_include($template) {
        $subs_page = get_query_var('subs_page');

        if ($subs_page === 'portal') {
            $custom_template = $this->locate_template('portal/portal.php');
            if ($custom_template) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Locate template file
     *
     * @param string $template_name
     * @return string|false
     * @since 1.0.0
     */
    public function locate_template($template_name) {
        // Check theme directory first
        $theme_template = locate_template(array(
            'subs/' . $template_name,
            'templates/subs/' . $template_name,
        ));

        if ($theme_template) {
            return $theme_template;
        }

        // Check plugin directory
        $plugin_template = SUBS_PLUGIN_PATH . 'templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return false;
    }

    /**
     * Handle frontend actions
     *
     * @since 1.0.0
     */
    public function handle_frontend_actions() {
        if (!isset($_POST['subs_action']) || !wp_verify_nonce($_POST['subs_nonce'], 'subs_frontend_action')) {
            return;
        }

        $action = sanitize_text_field($_POST['subs_action']);

        switch ($action) {
            case 'create_subscription':
                $this->handle_create_subscription();
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

            case 'update_customer_info':
                $this->handle_update_customer_info();
                break;

            case 'update_payment_method':
                $this->handle_update_payment_method();
                break;

            default:
                do_action('subs_handle_frontend_action_' . $action);
        }
    }

    /**
     * Handle subscription creation
     *
     * @since 1.0.0
     */
    private function handle_create_subscription() {
        // Get current user or create customer
        $customer_id = $this->get_or_create_customer();
        if (is_wp_error($customer_id)) {
            $this->add_notice($customer_id->get_error_message(), 'error');
            return;
        }

        // Validate subscription data
        $subscription_data = $this->validate_subscription_form_data($_POST);
        if (is_wp_error($subscription_data)) {
            $this->add_notice($subscription_data->get_error_message(), 'error');
            return;
        }

        $subscription_data['customer_id'] = $customer_id;

        // Create subscription
        $subscription_handler = new Subs_Subscription();
        $subscription_id = $subscription_handler->create_subscription($subscription_data);

        if (is_wp_error($subscription_id)) {
            $this->add_notice($subscription_id->get_error_message(), 'error');
            return;
        }

        // Create Stripe subscription if enabled
        $stripe = new Subs_Stripe();
        if ($stripe->is_configured()) {
            $stripe_data = array_merge($subscription_data, array(
                'local_subscription_id' => $subscription_id
            ));

            $stripe_result = $stripe->create_stripe_subscription($stripe_data);
            if (is_wp_error($stripe_result)) {
                // Delete local subscription if Stripe fails
                $subscription_handler->delete_subscription($subscription_id);
                $this->add_notice($stripe_result->get_error_message(), 'error');
                return;
            }

            // Update subscription with Stripe ID
            $subscription_handler->update_subscription($subscription_id, array(
                'stripe_subscription_id' => $stripe_result['subscription_id']
            ));

            // Store client secret for frontend payment
            set_transient('subs_payment_intent_' . $subscription_id, $stripe_result['client_secret'], HOUR_IN_SECONDS);
        }

        $this->add_notice(__('Subscription created successfully!', 'subs'), 'success');

        // Redirect to customer portal
        wp_redirect(home_url('/subscription-portal/'));
        exit;
    }

    /**
     * Handle subscription cancellation
     *
     * @since 1.0.0
     */
    private function handle_cancel_subscription() {
        if (!is_user_logged_in()) {
            $this->add_notice(__('You must be logged in to cancel subscriptions.', 'subs'), 'error');
            return;
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['cancellation_reason'] ?? '');

        // Verify user owns this subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            $this->add_notice(__('You do not have permission to cancel this subscription.', 'subs'), 'error');
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $result = $subscription_handler->cancel_subscription($subscription_id, $reason);

        if (is_wp_error($result)) {
            $this->add_notice($result->get_error_message(), 'error');
        } else {
            $this->add_notice(__('Subscription cancelled successfully.', 'subs'), 'success');
        }
    }

    /**
     * Handle subscription pause
     *
     * @since 1.0.0
     */
    private function handle_pause_subscription() {
        if (!is_user_logged_in()) {
            $this->add_notice(__('You must be logged in to pause subscriptions.', 'subs'), 'error');
            return;
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['pause_reason'] ?? '');

        // Verify user owns this subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            $this->add_notice(__('You do not have permission to pause this subscription.', 'subs'), 'error');
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $result = $subscription_handler->pause_subscription($subscription_id, $reason);

        if (is_wp_error($result)) {
            $this->add_notice($result->get_error_message(), 'error');
        } else {
            $this->add_notice(__('Subscription paused successfully.', 'subs'), 'success');
        }
    }

    /**
     * Handle subscription resume
     *
     * @since 1.0.0
     */
    private function handle_resume_subscription() {
        if (!is_user_logged_in()) {
            $this->add_notice(__('You must be logged in to resume subscriptions.', 'subs'), 'error');
            return;
        }

        $subscription_id = intval($_POST['subscription_id'] ?? 0);

        // Verify user owns this subscription
        if (!$this->user_owns_subscription($subscription_id)) {
            $this->add_notice(__('You do not have permission to resume this subscription.', 'subs'), 'error');
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $result = $subscription_handler->resume_subscription($subscription_id);

        if (is_wp_error($result)) {
            $this->add_notice($result->get_error_message(), 'error');
        } else {
            $this->add_notice(__('Subscription resumed successfully.', 'subs'), 'success');
        }
    }

    /**
     * Handle customer info update
     *
     * @since 1.0.0
     */
    private function handle_update_customer_info() {
        if (!is_user_logged_in()) {
            $this->add_notice(__('You must be logged in to update customer information.', 'subs'), 'error');
            return;
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            $this->add_notice(__('Customer record not found.', 'subs'), 'error');
            return;
        }

        // Sanitize and validate data
        $update_data = array(
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address_line1' => sanitize_text_field($_POST['address_line1'] ?? ''),
            'address_line2' => sanitize_text_field($_POST['address_line2'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'state' => sanitize_text_field($_POST['state'] ?? ''),
            'postal_code' => sanitize_text_field($_POST['postal_code'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? ''),
            'flag_delivery_address' => sanitize_textarea_field($_POST['flag_delivery_address'] ?? ''),
        );

        // Validate data
        $validation_errors = $customer_handler->validate_customer_data($update_data);
        if (!empty($validation_errors)) {
            foreach ($validation_errors as $error) {
                $this->add_notice($error, 'error');
            }
            return;
        }

        $result = $customer_handler->update_customer($customer->id, $update_data);

        if (is_wp_error($result)) {
            $this->add_notice($result->get_error_message(), 'error');
        } else {
            $this->add_notice(__('Customer information updated successfully.', 'subs'), 'success');
        }
    }

    /**
     * Handle payment method update
     *
     * @since 1.0.0
     */
    private function handle_update_payment_method() {
        if (!is_user_logged_in()) {
            $this->add_notice(__('You must be logged in to update payment methods.', 'subs'), 'error');
            return;
        }

        $payment_method_id = sanitize_text_field($_POST['payment_method_id'] ?? '');
        if (empty($payment_method_id)) {
            $this->add_notice(__('Payment method ID is required.', 'subs'), 'error');
            return;
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            $this->add_notice(__('Customer record not found.', 'subs'), 'error');
            return;
        }

        $stripe = new Subs_Stripe();
        $result = $stripe->update_customer_payment_method($customer->id, $payment_method_id);

        if (is_wp_error($result)) {
            $this->add_notice($result->get_error_message(), 'error');
        } else {
            $this->add_notice(__('Payment method updated successfully.', 'subs'), 'success');
        }
    }

    /**
     * Get or create customer for current user
     *
     * @return int|WP_Error Customer ID or error
     * @since 1.0.0
     */
    private function get_or_create_customer() {
        $customer_handler = new Subs_Customer();

        if (is_user_logged_in()) {
            // Try to get existing customer
            $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());
            if ($customer) {
                return $customer->id;
            }

            // Create customer for logged-in user
            $wp_user = wp_get_current_user();
            $customer_data = array(
                'wp_user_id' => $wp_user->ID,
                'email' => $wp_user->user_email,
                'first_name' => $wp_user->first_name,
                'last_name' => $wp_user->last_name,
            );

            return $customer_handler->create_customer($customer_data);
        } else {
            // Create customer from form data
            $email = sanitize_email($_POST['customer_email'] ?? '');
            if (empty($email)) {
                return new WP_Error('missing_email', __('Email address is required.', 'subs'));
            }

            // Check if customer already exists
            $existing_customer = $customer_handler->get_customer_by_email($email);
            if ($existing_customer) {
                return $existing_customer->id;
            }

            $customer_data = array(
                'email' => $email,
                'first_name' => sanitize_text_field($_POST['customer_first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['customer_last_name'] ?? ''),
                'phone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
            );

            return $customer_handler->create_customer($customer_data);
        }
    }

    /**
     * Validate subscription form data
     *
     * @param array $data
     * @return array|WP_Error
     * @since 1.0.0
     */
    private function validate_subscription_form_data($data) {
        $subscription_data = array();
        $errors = array();

        // Product name
        $product_name = sanitize_text_field($data['product_name'] ?? '');
        if (empty($product_name)) {
            $errors[] = __('Product name is required.', 'subs');
        } else {
            $subscription_data['product_name'] = $product_name;
        }

        // Amount
        $amount = floatval($data['amount'] ?? 0);
        if ($amount <= 0) {
            $errors[] = __('Amount must be greater than 0.', 'subs');
        } else {
            $subscription_data['amount'] = $amount;
        }

        // Currency
        $currency = sanitize_text_field($data['currency'] ?? 'USD');
        $subscription_data['currency'] = $currency;

        // Billing period
        $billing_period = sanitize_text_field($data['billing_period'] ?? 'month');
        $valid_periods = array_keys(Subs_Subscription::get_billing_periods());
        if (!in_array($billing_period, $valid_periods)) {
            $errors[] = __('Invalid billing period.', 'subs');
        } else {
            $subscription_data['billing_period'] = $billing_period;
        }

        // Billing interval
        $billing_interval = intval($data['billing_interval'] ?? 1);
        if ($billing_interval < 1) {
            $errors[] = __('Billing interval must be at least 1.', 'subs');
        } else {
            $subscription_data['billing_interval'] = $billing_interval;
        }

        // Trial end (optional)
        if (!empty($data['trial_end'])) {
            $trial_end = sanitize_text_field($data['trial_end']);
            if (strtotime($trial_end) === false) {
                $errors[] = __('Invalid trial end date.', 'subs');
            } else {
                $subscription_data['trial_end'] = date('Y-m-d H:i:s', strtotime($trial_end));
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return $subscription_data;
    }

    /**
     * Check if current user owns a subscription
     *
     * @param int $subscription_id
     * @return bool
     * @since 1.0.0
     */
    private function user_owns_subscription($subscription_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer_by_wp_user(get_current_user_id());

        if (!$customer) {
            return false;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        return $subscription && $subscription->customer_id == $customer->id;
    }

    /**
     * Add frontend notice
     *
     * @param string $message
     * @param string $type
     * @since 1.0.0
     */
    private function add_notice($message, $type = 'info') {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['subs_notices'])) {
            $_SESSION['subs_notices'] = array();
        }

        $_SESSION['subs_notices'][] = array(
            'message' => $message,
            'type' => $type,
        );
    }
}
