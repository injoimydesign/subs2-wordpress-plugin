<?php
/**
 * Product Display Template
 *
 * This template displays subscription products with purchase options.
 * Can be overridden by copying to yourtheme/subs/frontend/product-display.php
 *
 * @package Subs
 * @subpackage Templates/Frontend
 * @version 1.0.0
 *
 * @var int $product_id The product ID
 * @var array $product_data Product information
 * @var array $subscription_plans Available subscription plans
 * @var bool $allow_one_time_purchase Whether one-time purchase is allowed
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get product data
$product_id = isset($product_id) ? intval($product_id) : 0;
$product_data = isset($product_data) ? $product_data : array();
$subscription_plans = isset($subscription_plans) ? $subscription_plans : array();
$allow_one_time_purchase = isset($allow_one_time_purchase) ? $allow_one_time_purchase : true;

if (!$product_id || empty($product_data)) {
    echo '<p>' . esc_html__('Product not found.', 'subs') . '</p>';
    return;
}
?>

<div class="subs-product-display" id="subs-product-<?php echo esc_attr($product_id); ?>">
    <div class="subs-product-container">

        <?php if (!empty($product_data['image'])) : ?>
        <div class="subs-product-image">
            <img src="<?php echo esc_url($product_data['image']); ?>" alt="<?php echo esc_attr($product_data['name']); ?>">
        </div>
        <?php endif; ?>

        <div class="subs-product-details">
            <h2 class="subs-product-title">
                <?php echo esc_html($product_data['name']); ?>
            </h2>

            <?php if (!empty($product_data['description'])) : ?>
            <div class="subs-product-description">
                <?php echo wp_kses_post($product_data['description']); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($product_data['features'])) : ?>
            <div class="subs-product-features">
                <h3><?php esc_html_e('Features:', 'subs'); ?></h3>
                <ul>
                    <?php foreach ($product_data['features'] as $feature) : ?>
                        <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <div class="subs-product-purchase">
            <div class="subs-purchase-type-selector">
                <h3><?php esc_html_e('Choose Purchase Type:', 'subs'); ?></h3>

                <div class="subs-purchase-options">
                    <?php if ($allow_one_time_purchase) : ?>
                    <label class="subs-purchase-option">
                        <input
                            type="radio"
                            name="purchase_type"
                            value="one-time"
                            data-target="one-time-section"
                        >
                        <span class="subs-option-content">
                            <span class="subs-option-icon">🛒</span>
                            <span class="subs-option-info">
                                <strong><?php esc_html_e('One-Time Purchase', 'subs'); ?></strong>
                                <small><?php esc_html_e('Buy once, no commitment', 'subs'); ?></small>
                            </span>
                        </span>
                    </label>
                    <?php endif; ?>

                    <label class="subs-purchase-option">
                        <input
                            type="radio"
                            name="purchase_type"
                            value="subscription"
                            data-target="subscription-section"
                            checked
                        >
                        <span class="subs-option-content">
                            <span class="subs-option-icon">🔄</span>
                            <span class="subs-option-info">
                                <strong><?php esc_html_e('Subscribe & Save', 'subs'); ?></strong>
                                <small><?php esc_html_e('Recurring delivery, cancel anytime', 'subs'); ?></small>
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <?php if ($allow_one_time_purchase) : ?>
            <div id="one-time-section" class="subs-purchase-section" style="display: none;">
                <div class="subs-one-time-price">
                    <span class="subs-price-label"><?php esc_html_e('Price:', 'subs'); ?></span>
                    <span class="subs-price-amount">
                        $<?php echo esc_html(number_format($product_data['one_time_price'], 2)); ?>
                    </span>
                </div>

                <button type="button" class="subs-add-to-cart-button" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <?php esc_html_e('Add to Cart', 'subs'); ?>
                </button>
            </div>
            <?php endif; ?>

            <div id="subscription-section" class="subs-purchase-section">
                <div class="subs-subscription-plans">
                    <h4><?php esc_html_e('Select Your Plan:', 'subs'); ?></h4>

                    <?php foreach ($subscription_plans as $plan_key => $plan) : ?>
                    <div class="subs-plan-option">
                        <label class="subs-plan-label">
                            <input
                                type="radio"
                                name="subscription_plan"
                                value="<?php echo esc_attr($plan_key); ?>"
                                data-price="<?php echo esc_attr($plan['price']); ?>"
                                data-interval="<?php echo esc_attr($plan['interval']); ?>"
                                data-period="<?php echo esc_attr($plan['period']); ?>"
                                <?php checked($plan_key, key($subscription_plans)); ?>
                            >
                            <span class="subs-plan-details">
                                <span class="subs-plan-info">
                                    <strong><?php echo esc_html($plan['name']); ?></strong>
                                    <span class="subs-plan-description">
                                        <?php
                                        printf(
                                            esc_html__('Every %d %s', 'subs'),
                                            $plan['interval'],
                                            $plan['period']
                                        );
                                        ?>
                                    </span>
                                </span>
                                <span class="subs-plan-pricing">
                                    <span class="subs-plan-price">
                                        $<?php echo esc_html(number_format($plan['price'], 2)); ?>
                                    </span>
                                    <?php if (!empty($plan['savings'])) : ?>
                                    <span class="subs-plan-savings">
                                        <?php printf(esc_html__('Save %s%%', 'subs'), $plan['savings']); ?>
                                    </span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($product_data['trial_available'])) : ?>
                <div class="subs-trial-badge">
                    <span class="subs-badge-icon">🎁</span>
                    <span class="subs-badge-text">
                        <?php
                        printf(
                            esc_html__('%d-Day Free Trial', 'subs'),
                            $product_data['trial_days'] ?? 7
                        );
                        ?>
                    </span>
                </div>
                <?php endif; ?>

                <button type="button" class="subs-subscribe-button" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <?php esc_html_e('Subscribe Now', 'subs'); ?>
                </button>

                <div class="subs-subscription-features">
                    <ul>
                        <li>✓ <?php esc_html_e('Cancel anytime', 'subs'); ?></li>
                        <li>✓ <?php esc_html_e('Flexible delivery schedule', 'subs'); ?></li>
                        <li>✓ <?php esc_html_e('Priority customer support', 'subs'); ?></li>
                        <li>✓ <?php esc_html_e('Manage online portal', 'subs'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Product Display Styles */
.subs-product-display {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.subs-product-container {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 30px;
    background: #ffffff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 30px;
}

.subs-product-image img {
    width: 100%;
    height: auto;
    border-radius: 6px;
}

.subs-product-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 15px 0;
    color: #333;
}

.subs-product-description {
    font-size: 16px;
    line-height: 1.6;
    color: #666;
    margin-bottom: 20px;
}

.subs-product-features h3 {
    font-size: 18px;
    margin: 20px 0 10px 0;
    color: #333;
}

.subs-product-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.subs-product-features li {
    padding: 8px 0;
    padding-left: 25px;
    position: relative;
}

.subs-product-features li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #28a745;
    font-weight: bold;
}

.subs-purchase-type-selector h3 {
    font-size: 18px;
    margin: 0 0 15px 0;
    color: #333;
}

.subs-purchase-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 25px;
}

.subs-purchase-option {
    display: block;
    cursor: pointer;
}

.subs-purchase-option input[type="radio"] {
    display: none;
}

.subs-option-content {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 2px solid #e1e1e1;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.subs-purchase-option input:checked + .subs-option-content {
    border-color: #007cba;
    background-color: #e7f3f8;
}

.subs-option-icon {
    font-size: 24px;
    margin-right: 15px;
}

.subs-option-info {
    display: flex;
    flex-direction: column;
}

.subs-option-info strong {
    font-size: 16px;
    color: #333;
}

.subs-option-info small {
    font-size: 13px;
    color: #666;
}

.subs-purchase-section {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
}

.subs-one-time-price {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 18px;
}

.subs-price-amount {
    font-size: 32px;
    font-weight: 700;
    color: #007cba;
}

.subs-subscription-plans h4 {
    font-size: 16px;
    margin: 0 0 15px 0;
    color: #333;
}

.subs-plan-option {
    margin-bottom: 12px;
}

.subs-plan-label {
    display: block;
    cursor: pointer;
}

.subs-plan-label input[type="radio"] {
    display: none;
}

.subs-plan-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 2px solid #e1e1e1;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.subs-plan-label input:checked ~ .subs-plan-details {
    border-color: #007cba;
    background-color: #ffffff;
}

.subs-plan-info {
    display: flex;
    flex-direction: column;
}

.subs-plan-description {
    font-size: 13px;
    color: #666;
}

.subs-plan-pricing {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.subs-plan-price {
    font-size: 24px;
    font-weight: 700;
    color: #007cba;
}

.subs-plan-savings {
    font-size: 12px;
    color: #28a745;
    font-weight: 600;
}

.subs-trial-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 20px;
    font-weight: 600;
    margin: 15px 0;
}

.subs-badge-icon {
    font-size: 20px;
}

.subs-add-to-cart-button,
.subs-subscribe-button {
    width: 100%;
    padding: 15px;
    font-size: 16px;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 20px;
}

.subs-add-to-cart-button {
    background-color: #28a745;
    color: #ffffff;
}

.subs-add-to-cart-button:hover {
    background-color: #218838;
}

.subs-subscribe-button {
    background-color: #007cba;
    color: #ffffff;
}

.subs-subscribe-button:hover {
    background-color: #005a87;
}

.subs-subscription-features {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e1e1e1;
}

.subs-subscription-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.subs-subscription-features li {
    padding: 6px 0;
    font-size: 14px;
    color: #666;
}

/* Responsive Design */
@media (max-width: 992px) {
    .subs-product-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .subs-product-container {
        padding: 20px;
    }

    .subs-product-title {
        font-size: 24px;
    }

    .subs-price-amount {
        font-size: 28px;
    }

    .subs-plan-details {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .subs-plan-pricing {
        align-items: flex-start;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle purchase type selection
    $('input[name="purchase_type"]').on('change', function() {
        $('.subs-purchase-section').hide();
        $('#' + $(this).data('target')).show();
    });

    // Trigger initial selection
    $('input[name="purchase_type"]:checked').trigger('change');

    // Handle subscribe button click
    $('.subs-subscribe-button').on('click', function() {
        var productId = $(this).data('product-id');
        var selectedPlan = $('input[name="subscription_plan"]:checked').val();

        // Redirect to subscription form or show modal
        window.location.href = '?page=subscribe&product_id=' + productId + '&plan=' + selectedPlan;
    });

    // Handle add to cart button click
    $('.subs-add-to-cart-button').on('click', function() {
        var productId = $(this).data('product-id');

        // Add to cart logic here
        console.log('Adding product ' + productId + ' to cart');
    });
});
</script>

<?php
// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created product display template with subscription options
// - Added one-time purchase vs subscription selector
// - Included multiple subscription plan display
// - Added trial badge for products with trials
// - Responsive grid layout
// - JavaScript for interactive plan selection
// - Future: Add quantity selector
// - Future: Include product reviews section
// - Future: Add related products
?>
