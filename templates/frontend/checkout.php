<?php
/**
 * Checkout Template
 *
 * This template displays the checkout page for subscriptions.
 * Can be overridden by copying to yourtheme/subs/frontend/checkout.php
 *
 * @package Subs
 * @subpackage Templates/Frontend
 * @version 1.0.0
 *
 * @var array $cart_items Items in cart
 * @var float $total Total amount
 * @var object $customer Customer object if logged in
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$cart_items = isset($cart_items) ? $cart_items : array();
$total = isset($total) ? $total : 0;
$customer = isset($customer) ? $customer : null;
?>

<div class="subs-checkout-wrapper">
    <div class="subs-checkout-container">

        <!-- Progress Steps -->
        <div class="subs-checkout-progress">
            <div class="subs-progress-step active">
                <span class="subs-step-number">1</span>
                <span class="subs-step-label"><?php esc_html_e('Cart', 'subs'); ?></span>
            </div>
            <div class="subs-progress-line"></div>
            <div class="subs-progress-step active">
                <span class="subs-step-number">2</span>
                <span class="subs-step-label"><?php esc_html_e('Checkout', 'subs'); ?></span>
            </div>
            <div class="subs-progress-line"></div>
            <div class="subs-progress-step">
                <span class="subs-step-number">3</span>
                <span class="subs-step-label"><?php esc_html_e('Complete', 'subs'); ?></span>
            </div>
        </div>

        <div class="subs-checkout-content">

            <!-- Left Column: Forms -->
            <div class="subs-checkout-forms">

                <form id="subs-checkout-form" method="post" action="">
                    <?php wp_nonce_field('subs_process_checkout', 'subs_checkout_nonce'); ?>
                    <input type="hidden" name="action" value="subs_process_checkout">

                    <!-- Customer Information -->
                    <div class="subs-checkout-section">
                        <h2 class="subs-section-title">
                            <span class="subs-section-number">1</span>
                            <?php esc_html_e('Customer Information', 'subs'); ?>
                        </h2>

                        <?php if (!is_user_logged_in()) : ?>
                        <div class="subs-login-notice">
                            <p>
                                <?php
                                printf(
                                    wp_kses(
                                        __('Already have an account? <a href="%s">Log in</a>', 'subs'),
                                        array('a' => array('href' => array()))
                                    ),
                                    esc_url(wp_login_url(get_permalink()))
                                );
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>

                        <div class="subs-form-row">
                            <div class="subs-form-field subs-field-half">
                                <label for="checkout_first_name">
                                    <?php esc_html_e('First Name', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="checkout_first_name"
                                    name="first_name"
                                    value="<?php echo esc_attr($customer->first_name ?? ''); ?>"
                                    required
                                >
                            </div>

                            <div class="subs-form-field subs-field-half">
                                <label for="checkout_last_name">
                                    <?php esc_html_e('Last Name', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="checkout_last_name"
                                    name="last_name"
                                    value="<?php echo esc_attr($customer->last_name ?? ''); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-field">
                                <label for="checkout_email">
                                    <?php esc_html_e('Email Address', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="checkout_email"
                                    name="email"
                                    value="<?php echo esc_attr($customer->email ?? ''); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-field">
                                <label for="checkout_phone">
                                    <?php esc_html_e('Phone Number', 'subs'); ?>
                                </label>
                                <input
                                    type="tel"
                                    id="checkout_phone"
                                    name="phone"
                                    value="<?php echo esc_attr($customer->phone ?? ''); ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="subs-checkout-section">
                        <h2 class="subs-section-title">
                            <span class="subs-section-number">2</span>
                            <?php esc_html_e('Shipping Address', 'subs'); ?>
                        </h2>

                        <div class="subs-form-row">
                            <div class="subs-form-field">
                                <label for="checkout_address_line1">
                                    <?php esc_html_e('Street Address', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="checkout_address_line1"
                                    name="address_line1"
                                    placeholder="<?php esc_attr_e('Street address, P.O. box', 'subs'); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-field">
                                <label for="checkout_address_line2">
                                    <?php esc_html_e('Apartment, Suite, etc.', 'subs'); ?>
                                </label>
                                <input
                                    type="text"
                                    id="checkout_address_line2"
                                    name="address_line2"
                                >
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-field subs-field-half">
                                <label for="checkout_city">
                                    <?php esc_html_e('City', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="checkout_city"
                                    name="city"
                                    required
                                >
                            </div>

                            <div class="subs-form-field subs-field-quarter">
                                <label for="checkout_state">
                                    <?php esc_html_e('State', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="checkout_state"
                                    name="state"
                                    required
                                >
                            </div>

                            <div class="subs-form-field subs-field-quarter">
                                <label for="checkout_postal_code">
                                    <?php esc_html_e('Zip Code', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="checkout_postal_code"
                                    name="postal_code"
                                    required
                                >
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <div class="subs-form-field">
                                <label for="checkout_country">
                                    <?php esc_html_e('Country', 'subs'); ?> <span class="required">*</span>
                                </label>
                                <select id="checkout_country" name="country" required>
                                    <option value="US" selected><?php esc_html_e('United States', 'subs'); ?></option>
                                    <option value="CA"><?php esc_html_e('Canada', 'subs'); ?></option>
                                    <option value="GB"><?php esc_html_e('United Kingdom', 'subs'); ?></option>
                                    <option value="AU"><?php esc_html_e('Australia', 'subs'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="subs-form-row">
                            <label class="subs-checkbox-label">
                                <input type="checkbox" name="save_address" value="yes">
                                <span><?php esc_html_e('Save this address for future orders', 'subs'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="subs-checkout-section">
                        <h2 class="subs-section-title">
                            <span class="subs-section-number">3</span>
                            <?php esc_html_e('Payment Method', 'subs'); ?>
                        </h2>

                        <div class="subs-payment-methods">
                            <label class="subs-payment-option">
                                <input type="radio" name="payment_method" value="stripe" checked>
                                <span class="subs-payment-label">
                                    <span class="subs-payment-icon">💳</span>
                                    <span><?php esc_html_e('Credit / Debit Card', 'subs'); ?></span>
                                </span>
                            </label>
                        </div>

                        <div id="subs-stripe-card-element" class="subs-card-element">
                            <!-- Stripe Card Element will be inserted here -->
                        </div>

                        <div id="subs-card-errors" class="subs-card-errors" role="alert"></div>

                        <div class="subs-secure-notice">
                            <span class="subs-secure-icon">🔒</span>
                            <span><?php esc_html_e('Your payment information is encrypted and secure', 'subs'); ?></span>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="subs-checkout-section">
                        <label class="subs-checkbox-label subs-terms-checkbox">
                            <input type="checkbox" name="agree_terms" required>
                            <span>
                                <?php
                                printf(
                                    wp_kses(
                                        __('I agree to the <a href="%s" target="_blank">Terms of Service</a> and <a href="%s" target="_blank">Privacy Policy</a>', 'subs'),
                                        array('a' => array('href' => array(), 'target' => array()))
                                    ),
                                    esc_url(get_privacy_policy_url()),
                                    esc_url(get_privacy_policy_url())
                                );
                                ?>
                                <span class="required">*</span>
                            </span>
                        </label>
                    </div>

                    <div class="subs-checkout-actions">
                        <button type="submit" class="subs-place-order-button" id="subs-place-order">
                            <span class="subs-button-text">
                                <?php esc_html_e('Complete Order', 'subs'); ?>
                            </span>
                            <span class="subs-button-processing" style="display: none;">
                                <?php esc_html_e('Processing...', 'subs'); ?>
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Column: Order Summary -->
            <div class="subs-checkout-sidebar">
                <div class="subs-order-summary">
                    <h3 class="subs-summary-title">
                        <?php esc_html_e('Order Summary', 'subs'); ?>
                    </h3>

                    <div class="subs-summary-items">
                        <?php if (!empty($cart_items)) : ?>
                            <?php foreach ($cart_items as $item) : ?>
                            <div class="subs-summary-item">
                                <div class="subs-item-details">
                                    <h4 class="subs-item-name"><?php echo esc_html($item['name']); ?></h4>
                                    <p class="subs-item-description">
                                        <?php
                                        if ($item['type'] === 'subscription') {
                                            printf(
                                                esc_html__('Subscription: Every %d %s', 'subs'),
                                                $item['interval'],
                                                $item['period']
                                            );
                                        }
                                        ?>
                                    </p>
                                </div>
                                <div class="subs-item-price">
                                    $<?php echo esc_html(number_format($item['price'], 2)); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="subs-empty-cart"><?php esc_html_e('Your cart is empty', 'subs'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="subs-summary-totals">
                        <div class="subs-summary-line">
                            <span><?php esc_html_e('Subtotal:', 'subs'); ?></span>
                            <span>$<?php echo esc_html(number_format($total, 2)); ?></span>
                        </div>

                        <div class="subs-summary-line">
                            <span><?php esc_html_e('Shipping:', 'subs'); ?></span>
                            <span><?php esc_html_e('Free', 'subs'); ?></span>
                        </div>

                        <div class="subs-summary-line subs-total-line">
                            <span><?php esc_html_e('Total:', 'subs'); ?></span>
                            <span class="subs-total-amount">$<?php echo esc_html(number_format($total, 2)); ?></span>
                        </div>

                        <div class="subs-summary-recurring">
                            <p><?php esc_html_e('Recurring charges will begin after any trial period ends.', 'subs'); ?></p>
                        </div>
                    </div>

                    <div class="subs-summary-benefits">
                        <h4><?php esc_html_e('What you get:', 'subs'); ?></h4>
                        <ul>
                            <li>✓ <?php esc_html_e('Cancel anytime', 'subs'); ?></li>
                            <li>✓ <?php esc_html_e('Free shipping', 'subs'); ?></li>
                            <li>✓ <?php esc_html_e('Priority support', 'subs'); ?></li>
                            <li>✓ <?php esc_html_e('Online portal access', 'subs'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Checkout Styles */
.subs-checkout-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px;
}

.subs-checkout-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 30px;
}

/* Progress Steps */
.subs-checkout-progress {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 40px;
}

.subs-progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.subs-step-number {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e1e1e1;
    color: #666;
    font-weight: 600;
    transition: all 0.3s ease;
}

.subs-progress-step.active .subs-step-number {
    background: #007cba;
    color: #ffffff;
}

.subs-step-label {
    font-size: 14px;
    color: #666;
}

.subs-progress-step.active .subs-step-label {
    color: #007cba;
    font-weight: 600;
}

.subs-progress-line {
    width: 100px;
    height: 2px;
    background: #e1e1e1;
    margin: 0 15px;
}

/* Content Layout */
.subs-checkout-content {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
}

.subs-checkout-forms {
    background: #ffffff;
    border-radius: 8px;
    padding: 30px;
}

.subs-checkout-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #e1e1e1;
}

.subs-checkout-section:last-child {
    border-bottom: none;
}

.subs-section-title {
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 20px;
    margin: 0 0 20px 0;
    color: #333;
}

.subs-section-number {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #007cba;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
}

.subs-login-notice {
    background: #e7f3f8;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.subs-form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.subs-form-field {
    flex: 1;
}

.subs-field-half {
    flex: 0 0 calc(50% - 7.5px);
}

.subs-field-quarter {
    flex: 0 0 calc(25% - 11.25px);
}

.subs-form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.subs-form-field input[type="text"],
.subs-form-field input[type="email"],
.subs-form-field input[type="tel"],
.subs-form-field select {
    width: 100%;
    padding: 12px;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    font-size: 14px;
}

.subs-form-field input:focus,
.subs-form-field select:focus {
    outline: none;
    border-color: #007cba;
}

.required {
    color: #dc3545;
}

.subs-checkbox-label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
}

.subs-checkbox-label input[type="checkbox"] {
    margin-right: 10px;
    margin-top: 3px;
}

.subs-payment-methods {
    margin-bottom: 20px;
}

.subs-payment-option {
    display: block;
    cursor: pointer;
}

.subs-payment-option input[type="radio"] {
    display: none;
}

.subs-payment-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px;
    border: 2px solid #e1e1e1;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.subs-payment-option input:checked + .subs-payment-label {
    border-color: #007cba;
    background-color: #e7f3f8;
}

.subs-payment-icon {
    font-size: 24px;
}

.subs-card-element {
    padding: 12px;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    background: #ffffff;
    margin-bottom: 15px;
}

.subs-card-errors {
    color: #dc3545;
    font-size: 14px;
    margin-top: 10px;
}

.subs-secure-notice {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #d4edda;
    padding: 12px;
    border-radius: 4px;
    font-size: 14px;
    color: #155724;
    margin-top: 15px;
}

.subs-checkout-actions {
    margin-top: 30px;
}

.subs-place-order-button {
    width: 100%;
    padding: 18px;
    font-size: 18px;
    font-weight: 600;
    background-color: #28a745;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.subs-place-order-button:hover {
    background-color: #218838;
}

.subs-place-order-button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

/* Sidebar */
.subs-checkout-sidebar {
    position: sticky;
    top: 20px;
    height: fit-content;
}

.subs-order-summary {
    background: #ffffff;
    border-radius: 8px;
    padding: 25px;
}

.subs-summary-title {
    font-size: 20px;
    margin: 0 0 20px 0;
    color: #333;
}

.subs-summary-items {
    border-bottom: 1px solid #e1e1e1;
    padding-bottom: 20px;
    margin-bottom: 20px;
}

.subs-summary-item {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 15px;
}

.subs-item-name {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 5px 0;
    color: #333;
}

.subs-item-description {
    font-size: 13px;
    color: #666;
    margin: 0;
}

.subs-item-price {
    font-size: 16px;
    font-weight: 600;
    color: #007cba;
    white-space: nowrap;
}

.subs-summary-totals {
    border-bottom: 1px solid #e1e1e1;
    padding-bottom: 20px;
    margin-bottom: 20px;
}

.subs-summary-line {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
}

.subs-total-line {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    padding-top: 15px;
    margin-top: 10px;
    border-top: 2px solid #e1e1e1;
}

.subs-total-amount {
    color: #28a745;
}

.subs-summary-recurring {
    background: #fff3cd;
    padding: 12px;
    border-radius: 4px;
    margin-top: 15px;
}

.subs-summary-recurring p {
    margin: 0;
    font-size: 13px;
    color: #856404;
}

.subs-summary-benefits h4 {
    font-size: 16px;
    margin: 0 0 10px 0;
    color: #333;
}

.subs-summary-benefits ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.subs-summary-benefits li {
    padding: 6px 0;
    font-size: 14px;
    color: #666;
}

/* Responsive Design */
@media (max-width: 992px) {
    .subs-checkout-content {
        grid-template-columns: 1fr;
    }

    .subs-checkout-sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .subs-checkout-container {
        padding: 20px;
    }

    .subs-checkout-forms {
        padding: 20px;
    }

    .subs-progress-line {
        width: 50px;
    }

    .subs-step-label {
        display: none;
    }

    .subs-form-row {
        flex-direction: column;
    }

    .subs-field-half,
    .subs-field-quarter {
        flex: 1;
    }
}
</style>

<?php
// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created checkout template with multi-step display
// - Added customer information section
// - Included shipping address fields
// - Integrated Stripe payment element
// - Created order summary sidebar
// - Progress indicator for checkout steps
// - Responsive design for all devices
// - Future: Add billing address option
// - Future: Include coupon code field
// - Future: Add express checkout options (Apple Pay, Google Pay)
?>
