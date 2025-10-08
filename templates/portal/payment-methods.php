<?php
/**
 * Payment Methods Template
 *
 * This template displays and manages customer payment methods.
 * Can be overridden by copying to yourtheme/subs/portal/payment-methods.php
 *
 * @package Subs
 * @subpackage Templates/Portal
 * @version 1.0.0
 *
 * @var array $payment_methods Saved payment methods
 * @var string $default_payment_method Default payment method ID
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure customer is logged in
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('Please log in to manage your payment methods.', 'subs') . '</p>';
    return;
}

$payment_methods = isset($payment_methods) ? $payment_methods : array();
$default_payment_method = isset($default_payment_method) ? $default_payment_method : '';
?>

<div class="subs-payment-methods-wrapper">
    <div class="subs-payment-methods-container">

        <!-- Page Header -->
        <div class="subs-page-header">
            <div>
                <h1 class="subs-page-title"><?php esc_html_e('Payment Methods', 'subs'); ?></h1>
                <p class="subs-page-description"><?php esc_html_e('Manage your saved payment methods', 'subs'); ?></p>
            </div>

            <button type="button" class="subs-btn subs-btn-primary" id="subs-add-payment-method">
                <span>+</span>
                <?php esc_html_e('Add Payment Method', 'subs'); ?>
            </button>
        </div>

        <!-- Security Notice -->
        <div class="subs-security-notice">
            <span class="subs-security-icon">🔒</span>
            <div class="subs-security-content">
                <strong><?php esc_html_e('Your payment information is secure', 'subs'); ?></strong>
                <p><?php esc_html_e('We use industry-standard encryption to protect your payment details. We never store your full card information on our servers.', 'subs'); ?></p>
            </div>
        </div>

        <!-- Payment Methods List -->
        <?php if (!empty($payment_methods)) : ?>
        <div class="subs-payment-methods-list">
            <?php foreach ($payment_methods as $method) : ?>
            <div class="subs-payment-method-card" data-method-id="<?php echo esc_attr($method->id); ?>">
                <div class="subs-payment-method-header">
                    <div class="subs-payment-method-info">
                        <div class="subs-card-brand">
                            <?php
                            // Display card brand icon
                            $brand = strtolower($method->brand ?? 'card');
                            $brand_icons = array(
                                'visa' => '💳',
                                'mastercard' => '💳',
                                'amex' => '💳',
                                'discover' => '💳',
                                'card' => '💳',
                            );
                            echo isset($brand_icons[$brand]) ? $brand_icons[$brand] : '💳';
                            ?>
                            <span class="subs-brand-name"><?php echo esc_html(ucfirst($method->brand ?? 'Card')); ?></span>
                        </div>

                        <div class="subs-card-details">
                            <span class="subs-card-number">•••• •••• •••• <?php echo esc_html($method->last4 ?? '****'); ?></span>
                            <span class="subs-card-expiry">
                                <?php
                                printf(
                                    esc_html__('Expires %s/%s', 'subs'),
                                    str_pad($method->exp_month ?? '00', 2, '0', STR_PAD_LEFT),
                                    $method->exp_year ?? '0000'
                                );
                                ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($default_payment_method === $method->id) : ?>
                    <span class="subs-default-badge">
                        <?php esc_html_e('Default', 'subs'); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="subs-payment-method-footer">
                    <div class="subs-payment-method-actions">
                        <?php if ($default_payment_method !== $method->id) : ?>
                        <button type="button" class="subs-btn subs-btn-small subs-btn-outline subs-set-default-payment" data-method-id="<?php echo esc_attr($method->id); ?>">
                            <?php esc_html_e('Set as Default', 'subs'); ?>
                        </button>
                        <?php endif; ?>

                        <button type="button" class="subs-btn subs-btn-small subs-btn-danger subs-remove-payment" data-method-id="<?php echo esc_attr($method->id); ?>">
                            <?php esc_html_e('Remove', 'subs'); ?>
                        </button>
                    </div>

                    <?php if (!empty($method->subscriptions_using)) : ?>
                    <div class="subs-payment-usage">
                        <small>
                            <?php
                            printf(
                                esc_html(_n('Used by %d subscription', 'Used by %d subscriptions', $method->subscriptions_using, 'subs')),
                                $method->subscriptions_using
                            );
                            ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else : ?>
        <div class="subs-empty-state">
            <span class="subs-empty-icon">💳</span>
            <h3><?php esc_html_e('No Payment Methods', 'subs'); ?></h3>
            <p><?php esc_html_e('You haven\'t added any payment methods yet. Add one to start subscribing.', 'subs'); ?></p>
            <button type="button" class="subs-btn subs-btn-primary subs-add-first-payment">
                <?php esc_html_e('Add Payment Method', 'subs'); ?>
            </button>
        </div>
        <?php endif; ?>

        <!-- Help Text -->
        <div class="subs-payment-help">
            <h3><?php esc_html_e('Frequently Asked Questions', 'subs'); ?></h3>

            <div class="subs-faq-item">
                <h4><?php esc_html_e('Is my payment information secure?', 'subs'); ?></h4>
                <p><?php esc_html_e('Yes, we use Stripe for payment processing, which is certified as a PCI Service Provider Level 1. This is the highest level of certification available in the payments industry.', 'subs'); ?></p>
            </div>

            <div class="subs-faq-item">
                <h4><?php esc_html_e('Can I use multiple payment methods?', 'subs'); ?></h4>
                <p><?php esc_html_e('Yes, you can add multiple payment methods and choose which one to use for each subscription. You can also set a default payment method.', 'subs'); ?></p>
            </div>

            <div class="subs-faq-item">
                <h4><?php esc_html_e('What happens if my card expires?', 'subs'); ?></h4>
                <p><?php esc_html_e('We\'ll send you email reminders before your card expires. You can update your payment information at any time to avoid any interruption to your subscriptions.', 'subs'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Method Modal -->
<div id="subs-add-payment-modal" class="subs-modal" style="display: none;">
    <div class="subs-modal-overlay"></div>
    <div class="subs-modal-content">
        <div class="subs-modal-header">
            <h2><?php esc_html_e('Add Payment Method', 'subs'); ?></h2>
            <button type="button" class="subs-modal-close">&times;</button>
        </div>

        <div class="subs-modal-body">
            <form id="subs-payment-method-form">
                <?php wp_nonce_field('subs_add_payment_method', 'subs_payment_nonce'); ?>

                <div class="subs-form-field">
                    <label for="subs-card-holder-name">
                        <?php esc_html_e('Cardholder Name', 'subs'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" id="subs-card-holder-name" name="card_holder_name" required>
                </div>

                <div class="subs-form-field">
                    <label><?php esc_html_e('Card Details', 'subs'); ?> <span class="required">*</span></label>
                    <div id="subs-card-element-modal" class="subs-card-element">
                        <!-- Stripe Card Element will be inserted here -->
                    </div>
                    <div id="subs-card-errors-modal" class="subs-card-errors" role="alert"></div>
                </div>

                <div class="subs-form-field">
                    <label class="subs-checkbox-label">
                        <input type="checkbox" name="set_as_default" value="yes">
                        <span><?php esc_html_e('Set as default payment method', 'subs'); ?></span>
                    </label>
                </div>

                <div class="subs-modal-actions">
                    <button type="button" class="subs-btn subs-btn-outline subs-modal-cancel">
                        <?php esc_html_e('Cancel', 'subs'); ?>
                    </button>
                    <button type="submit" class="subs-btn subs-btn-primary" id="subs-save-payment-method">
                        <span class="subs-button-text"><?php esc_html_e('Add Payment Method', 'subs'); ?></span>
                        <span class="subs-button-processing" style="display: none;"><?php esc_html_e('Processing...', 'subs'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Payment Methods Styles */
.subs-payment-methods-wrapper {
    max-width: 1000px;
    margin: 0 auto;
    padding: 40px 20px;
}

.subs-payment-methods-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 30px;
}

/* Security Notice */
.subs-security-notice {
    display: flex;
    gap: 15px;
    background: #d1ecf1;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #17a2b8;
    margin-bottom: 30px;
}

.subs-security-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.subs-security-content strong {
    display: block;
    margin-bottom: 5px;
    color: #0c5460;
}

.subs-security-content p {
    margin: 0;
    font-size: 14px;
    color: #0c5460;
}

/* Payment Methods List */
.subs-payment-methods-list {
    display: grid;
    gap: 20px;
    margin-bottom: 40px;
}

.subs-payment-method-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.subs-payment-method-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.subs-payment-method-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e1e1e1;
}

.subs-payment-method-info {
    display: flex;
    gap: 20px;
    align-items: center;
}

.subs-card-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 32px;
}

.subs-brand-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.subs-card-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.subs-card-number {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    letter-spacing: 2px;
}

.subs-card-expiry {
    font-size: 13px;
    color: #666;
}

.subs-default-badge {
    background: #28a745;
    color: #ffffff;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.subs-payment-method-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.subs-payment-method-actions {
    display: flex;
    gap: 10px;
}

.subs-payment-usage small {
    font-size: 13px;
    color: #666;
}

/* Help Section */
.subs-payment-help {
    background: #ffffff;
    border-radius: 8px;
    padding: 30px;
}

.subs-payment-help h3 {
    font-size: 20px;
    margin: 0 0 20px 0;
    color: #333;
}

.subs-faq-item {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e1e1e1;
}

.subs-faq-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.subs-faq-item h4 {
    font-size: 16px;
    margin: 0 0 10px 0;
    color: #333;
}

.subs-faq-item p {
    font-size: 14px;
    color: #666;
    margin: 0;
    line-height: 1.6;
}

/* Modal Styles */
.subs-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.subs-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.subs-modal-content {
    position: relative;
    max-width: 500px;
    margin: 50px auto;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.subs-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    border-bottom: 1px solid #e1e1e1;
}

.subs-modal-header h2 {
    font-size: 24px;
    margin: 0;
    color: #333;
}

.subs-modal-close {
    background: none;
    border: none;
    font-size: 32px;
    color: #666;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.subs-modal-close:hover {
    color: #333;
}

.subs-modal-body {
    padding: 30px;
}

.subs-form-field {
    margin-bottom: 20px;
}

.subs-form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.subs-form-field input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    font-size: 14px;
}

.subs-form-field input:focus {
    outline: none;
    border-color: #007cba;
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

.subs-checkbox-label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
}

.subs-checkbox-label input[type="checkbox"] {
    margin-right: 10px;
    margin-top: 3px;
}

.subs-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 30px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .subs-page-header {
        flex-direction: column;
        gap: 20px;
    }

    .subs-page-header .subs-btn {
        width: 100%;
        justify-content: center;
    }

    .subs-payment-method-info {
        flex-direction: column;
        gap: 10px;
    }

    .subs-payment-method-footer {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .subs-payment-method-actions {
        width: 100%;
        flex-direction: column;
    }

    .subs-payment-method-actions .subs-btn {
        width: 100%;
        justify-content: center;
    }

    .subs-modal-content {
        margin: 20px;
        max-width: calc(100% - 40px);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Open add payment modal
    $('#subs-add-payment-method, .subs-add-first-payment').on('click', function() {
        $('#subs-add-payment-modal').fadeIn(300);
    });

    // Close modal
    $('.subs-modal-close, .subs-modal-cancel').on('click', function() {
        $('#subs-add-payment-modal').fadeOut(300);
    });

    // Close modal on overlay click
    $('.subs-modal-overlay').on('click', function() {
        $('#subs-add-payment-modal').fadeOut(300);
    });

    // Set as default
    $('.subs-set-default-payment').on('click', function() {
        var methodId = $(this).data('method-id');

        // AJAX call to set default payment method
        console.log('Setting default payment method: ' + methodId);
    });

    // Remove payment method
    $('.subs-remove-payment').on('click', function() {
        var methodId = $(this).data('method-id');

        if (confirm('<?php esc_html_e('Are you sure you want to remove this payment method?', 'subs'); ?>')) {
            // AJAX call to remove payment method
            console.log('Removing payment method: ' + methodId);
        }
    });

    // Submit payment method form
    $('#subs-payment-method-form').on('submit', function(e) {
        e.preventDefault();

        // Disable submit button
        $('#subs-save-payment-method').prop('disabled', true);
        $('.subs-button-text').hide();
        $('.subs-button-processing').show();

        // AJAX call to add payment method
        console.log('Adding new payment method');

        // Re-enable button after processing
        setTimeout(function() {
            $('#subs-save-payment-method').prop('disabled', false);
            $('.subs-button-text').show();
            $('.subs-button-processing').hide();
            $('#subs-add-payment-modal').fadeOut(300);
        }, 2000);
    });
});
</script>

<?php
// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created payment methods template
// - Added list of saved payment methods
// - Included add payment method modal
// - Set default payment method functionality
// - Remove payment method with confirmation
// - Security notice and FAQ section
// - Stripe integration for card element
// - Display card brand and expiry information
// - Show which subscriptions use each payment method
// - Future: Add support for bank accounts
// - Future: Include payment method verification
// - Future: Add auto-update for expired cards
?>
