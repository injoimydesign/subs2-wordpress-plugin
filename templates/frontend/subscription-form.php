<?php
/**
 * Subscription Form Template
 *
 * This template displays the subscription form for customers.
 * Can be overridden by copying to yourtheme/subs/frontend/subscription-form.php
 *
 * @package Subs
 * @subpackage Templates/Frontend
 * @version 1.0.0
 *
 * @var int $product_id The product ID
 * @var array $subscription_options Available subscription options
 * @var bool $show_trial Whether to show trial option
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get product details
$product_id = isset($product_id) ? intval($product_id) : 0;
if (!$product_id) {
    echo '<p>' . esc_html__('Invalid product.', 'subs') . '</p>';
    return;
}

// Default subscription options
$subscription_options = isset($subscription_options) ? $subscription_options : array(
    'monthly' => array(
        'label' => __('Monthly', 'subs'),
        'interval' => 1,
        'period' => 'month',
        'price' => 0,
    ),
);

$show_trial = isset($show_trial) ? $show_trial : false;
?>

<div class="subs-subscription-form-wrapper">
    <form id="subs-subscription-form" class="subs-subscription-form" method="post" action="">
        <?php wp_nonce_field('subs_create_subscription', 'subs_subscription_nonce'); ?>
        <input type="hidden" name="action" value="subs_create_subscription">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">

        <div class="subs-form-section">
            <h3 class="subs-form-heading">
                <?php esc_html_e('Choose Your Subscription', 'subs'); ?>
            </h3>

            <div class="subs-subscription-options">
                <?php foreach ($subscription_options as $key => $option) : ?>
                    <div class="subs-subscription-option">
                        <label class="subs-option-label">
                            <input
                                type="radio"
                                name="subscription_plan"
                                value="<?php echo esc_attr($key); ?>"
                                data-interval="<?php echo esc_attr($option['interval']); ?>"
                                data-period="<?php echo esc_attr($option['period']); ?>"
                                data-price="<?php echo esc_attr($option['price']); ?>"
                                <?php checked($key, 'monthly'); ?>
                                required
                            >
                            <span class="subs-option-details">
                                <span class="subs-option-title"><?php echo esc_html($option['label']); ?></span>
                                <span class="subs-option-price">
                                    <?php
                                    $formatted_price = number_format($option['price'], 2);
                                    printf(
                                        esc_html__('$%s / %s', 'subs'),
                                        $formatted_price,
                                        esc_html($option['period'])
                                    );
                                    ?>
                                </span>
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($show_trial) : ?>
        <div class="subs-form-section subs-trial-section">
            <div class="subs-trial-notice">
                <span class="subs-trial-icon">🎁</span>
                <span class="subs-trial-text">
                    <?php esc_html_e('Start with a 7-day free trial!', 'subs'); ?>
                </span>
            </div>
            <label class="subs-checkbox-label">
                <input type="checkbox" name="include_trial" value="yes" checked>
                <span><?php esc_html_e('Include free trial period', 'subs'); ?></span>
            </label>
        </div>
        <?php endif; ?>

        <div class="subs-form-section">
            <h3 class="subs-form-heading">
                <?php esc_html_e('Customer Information', 'subs'); ?>
            </h3>

            <div class="subs-form-row">
                <div class="subs-form-field subs-field-half">
                    <label for="subs_first_name">
                        <?php esc_html_e('First Name', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="subs_first_name"
                        name="first_name"
                        value="<?php echo esc_attr(wp_get_current_user()->first_name); ?>"
                        required
                    >
                </div>

                <div class="subs-form-field subs-field-half">
                    <label for="subs_last_name">
                        <?php esc_html_e('Last Name', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="subs_last_name"
                        name="last_name"
                        value="<?php echo esc_attr(wp_get_current_user()->last_name); ?>"
                        required
                    >
                </div>
            </div>

            <div class="subs-form-row">
                <div class="subs-form-field">
                    <label for="subs_email">
                        <?php esc_html_e('Email Address', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="email"
                        id="subs_email"
                        name="email"
                        value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                        required
                    >
                </div>
            </div>

            <div class="subs-form-row">
                <div class="subs-form-field">
                    <label for="subs_phone">
                        <?php esc_html_e('Phone Number', 'subs'); ?>
                    </label>
                    <input
                        type="tel"
                        id="subs_phone"
                        name="phone"
                        placeholder="(555) 123-4567"
                    >
                </div>
            </div>
        </div>

        <div class="subs-form-section">
            <h3 class="subs-form-heading">
                <?php esc_html_e('Shipping Address', 'subs'); ?>
            </h3>

            <div class="subs-form-row">
                <div class="subs-form-field">
                    <label for="subs_address_line1">
                        <?php esc_html_e('Address Line 1', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="subs_address_line1"
                        name="address_line1"
                        placeholder="<?php esc_attr_e('Street address, P.O. box', 'subs'); ?>"
                        required
                    >
                </div>
            </div>

            <div class="subs-form-row">
                <div class="subs-form-field">
                    <label for="subs_address_line2">
                        <?php esc_html_e('Address Line 2', 'subs'); ?>
                    </label>
                    <input
                        type="text"
                        id="subs_address_line2"
                        name="address_line2"
                        placeholder="<?php esc_attr_e('Apartment, suite, unit, etc.', 'subs'); ?>"
                    >
                </div>
            </div>

            <div class="subs-form-row">
                <div class="subs-form-field subs-field-half">
                    <label for="subs_city">
                        <?php esc_html_e('City', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="subs_city"
                        name="city"
                        required
                    >
                </div>

                <div class="subs-form-field subs-field-half">
                    <label for="subs_state">
                        <?php esc_html_e('State / Province', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="subs_state"
                        name="state"
                        required
                    >
                </div>
            </div>

            <div class="subs-form-row">
                <div class="subs-form-field subs-field-half">
                    <label for="subs_postal_code">
                        <?php esc_html_e('Postal / Zip Code', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="subs_postal_code"
                        name="postal_code"
                        required
                    >
                </div>

                <div class="subs-form-field subs-field-half">
                    <label for="subs_country">
                        <?php esc_html_e('Country', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <select id="subs_country" name="country" required>
                        <option value=""><?php esc_html_e('Select Country', 'subs'); ?></option>
                        <option value="US" selected><?php esc_html_e('United States', 'subs'); ?></option>
                        <option value="CA"><?php esc_html_e('Canada', 'subs'); ?></option>
                        <option value="GB"><?php esc_html_e('United Kingdom', 'subs'); ?></option>
                        <option value="AU"><?php esc_html_e('Australia', 'subs'); ?></option>
                        <!-- Add more countries as needed -->
                        <?php do_action('subs_subscription_form_country_options'); ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="subs-form-section">
            <h3 class="subs-form-heading">
                <?php esc_html_e('Payment Information', 'subs'); ?>
            </h3>

            <div id="subs-card-element" class="subs-card-element">
                <!-- Stripe Card Element will be inserted here -->
            </div>

            <div id="subs-card-errors" class="subs-card-errors" role="alert"></div>
        </div>

        <div class="subs-form-section subs-order-summary">
            <h3 class="subs-form-heading">
                <?php esc_html_e('Order Summary', 'subs'); ?>
            </h3>

            <div class="subs-summary-line">
                <span class="subs-summary-label"><?php esc_html_e('Subscription:', 'subs'); ?></span>
                <span class="subs-summary-value" id="subs-summary-plan">
                    <?php esc_html_e('Monthly', 'subs'); ?>
                </span>
            </div>

            <?php if ($show_trial) : ?>
            <div class="subs-summary-line subs-trial-line">
                <span class="subs-summary-label"><?php esc_html_e('Trial Period:', 'subs'); ?></span>
                <span class="subs-summary-value">
                    <?php esc_html_e('7 days free', 'subs'); ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="subs-summary-line subs-total-line">
                <span class="subs-summary-label">
                    <?php echo $show_trial ? esc_html__('First charge on:', 'subs') : esc_html__('Total today:', 'subs'); ?>
                </span>
                <span class="subs-summary-value" id="subs-summary-total">
                    $0.00
                </span>
            </div>
        </div>

        <div class="subs-form-section subs-terms-section">
            <label class="subs-checkbox-label">
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

        <div class="subs-form-actions">
            <button type="submit" class="subs-submit-button" id="subs-submit-button">
                <span class="subs-button-text">
                    <?php esc_html_e('Start Subscription', 'subs'); ?>
                </span>
                <span class="subs-button-processing" style="display: none;">
                    <?php esc_html_e('Processing...', 'subs'); ?>
                </span>
            </button>
        </div>

        <div class="subs-form-notice" style="display: none;">
            <!-- Success/Error messages will be displayed here -->
        </div>
    </form>
</div>

<style>
/* Subscription Form Styles */
.subs-subscription-form-wrapper {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.subs-subscription-form {
    background: #ffffff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 30px;
}

.subs-form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #e1e1e1;
}

.subs-form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.subs-form-heading {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 20px 0;
    color: #333;
}

.subs-subscription-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.subs-subscription-option {
    position: relative;
}

.subs-option-label {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 2px solid #e1e1e1;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.subs-option-label:hover {
    border-color: #007cba;
    background-color: #f8f9fa;
}

.subs-option-label input[type="radio"] {
    margin-right: 15px;
}

.subs-option-label input[type="radio"]:checked ~ .subs-option-details {
    font-weight: 600;
}

.subs-option-label:has(input:checked) {
    border-color: #007cba;
    background-color: #e7f3f8;
}

.subs-option-details {
    display: flex;
    justify-content: space-between;
    width: 100%;
}

.subs-option-price {
    color: #007cba;
    font-weight: 600;
}

.subs-trial-section {
    background-color: #e7f3f8;
    border: 2px solid #007cba;
    border-radius: 6px;
    padding: 20px;
}

.subs-trial-notice {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 600;
    color: #007cba;
}

.subs-trial-icon {
    font-size: 24px;
    margin-right: 10px;
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
    transition: border-color 0.3s ease;
}

.subs-form-field input:focus,
.subs-form-field select:focus {
    outline: none;
    border-color: #007cba;
}

.required {
    color: #dc3545;
}

.subs-card-element {
    padding: 12px;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    background: #ffffff;
}

.subs-card-errors {
    color: #dc3545;
    font-size: 14px;
    margin-top: 10px;
}

.subs-order-summary {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
}

.subs-summary-line {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e1e1e1;
}

.subs-summary-line:last-child {
    border-bottom: none;
}

.subs-total-line {
    font-weight: 600;
    font-size: 18px;
    color: #007cba;
    padding-top: 15px;
    margin-top: 10px;
    border-top: 2px solid #007cba;
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

.subs-form-actions {
    text-align: center;
    margin-top: 30px;
}

.subs-submit-button {
    background-color: #007cba;
    color: #ffffff;
    padding: 15px 40px;
    font-size: 16px;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    min-width: 200px;
}

.subs-submit-button:hover {
    background-color: #005a87;
}

.subs-submit-button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.subs-form-notice {
    margin-top: 20px;
    padding: 15px;
    border-radius: 4px;
}

.subs-form-notice.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.subs-form-notice.error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

/* Responsive Design */
@media (max-width: 768px) {
    .subs-subscription-form {
        padding: 20px;
    }

    .subs-form-row {
        flex-direction: column;
    }

    .subs-field-half {
        flex: 1;
    }

    .subs-option-details {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<?php
// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created subscription form template with Stripe integration
// - Added customer information fields
// - Included shipping address section
// - Integrated trial period option
// - Added order summary display
// - Responsive design for mobile devices
// - Future: Add address autocomplete
// - Future: Include multiple payment method options
// - Future: Add gift subscription option
?>
