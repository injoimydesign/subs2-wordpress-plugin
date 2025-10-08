<?php
/**
 * Payment Succeeded Email Template
 *
 * This template is sent when a payment is successfully processed.
 * Can be overridden by copying to yourtheme/subs/emails/payment-succeeded.php
 *
 * @package Subs
 * @subpackage Templates/Emails
 * @version 1.0.0
 *
 * @var object $subscription The subscription object
 * @var object $customer The customer object
 * @var object $payment The payment object
 * @var string $customer_name Customer's full name
 * @var string $site_name Site name
 * @var string $site_url Site URL
 * @var string $portal_url Customer portal URL
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Set email heading
$email_heading = __('Payment Received', 'subs');

// Include email header
include SUBS_PLUGIN_PATH . 'templates/emails/email-header.php';
?>

<p><?php printf(esc_html__('Hello %s,', 'subs'), esc_html($customer_name)); ?></p>

<p><?php esc_html_e('Thank you! Your payment has been successfully processed.', 'subs'); ?></p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #d4edda; padding: 15px; margin: 20px 0; border-left: 4px solid #28a745; border-radius: 3px;">
    <tr>
        <td align="center">
            <span style="font-size: 48px; color: #28a745;">✓</span>
            <p style="margin: 10px 0 0 0; color: #155724; font-size: 18px; font-weight: bold;">
                <?php esc_html_e('Payment Successful', 'subs'); ?>
            </p>
        </td>
    </tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <tr>
        <td>
            <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 18px;">
                <?php esc_html_e('Payment Details:', 'subs'); ?>
            </h3>

            <table cellpadding="5" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('Amount Paid:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right;">
                        <span style="font-size: 18px; color: #28a745; font-weight: bold;">
                            <?php echo esc_html($payment->amount . ' ' . strtoupper($payment->currency)); ?>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('Payment Date:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right;">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->created_at))); ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('Payment Method:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right;">
                        <?php
                        if (!empty($payment->payment_method_details)) {
                            echo esc_html(ucfirst($payment->payment_method_details));
                        } else {
                            esc_html_e('Card ending in ****', 'subs');
                        }
                        ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('Transaction ID:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right; font-family: monospace; font-size: 12px;">
                        <?php echo esc_html($payment->stripe_payment_intent_id ?? $payment->id); ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0;">
                        <strong><?php esc_html_e('Subscription:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; text-align: right;">
                        <?php echo esc_html($subscription->product_name); ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <tr>
        <td>
            <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 18px;">
                <?php esc_html_e('Subscription Status:', 'subs'); ?>
            </h3>

            <table cellpadding="5" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('Status:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right;">
                        <span style="color: #28a745; font-weight: bold;">
                            <?php echo esc_html(ucfirst($subscription->status)); ?>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0;">
                        <strong><?php esc_html_e('Next Payment Date:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; text-align: right;">
                        <?php
                        echo !empty($subscription->next_payment_date)
                            ? esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date)))
                            : esc_html__('N/A', 'subs');
                        ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p><?php esc_html_e('Your subscription remains active. You can view your complete payment history and manage your subscription in the customer portal:', 'subs'); ?></p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 20px 0;">
    <tr>
        <td align="center">
            <a href="<?php echo esc_url($portal_url); ?>" style="display: inline-block; padding: 15px 30px; background-color: #007cba; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                <?php esc_html_e('View Payment History', 'subs'); ?>
            </a>
        </td>
    </tr>
</table>

<p><?php esc_html_e('Thank you for your continued subscription!', 'subs'); ?></p>

<p><?php printf(esc_html__('Best regards,%sThe %s Team', 'subs'), '<br>', esc_html($site_name)); ?></p>

<?php
// Include email footer
include SUBS_PLUGIN_PATH . 'templates/emails/email-footer.php';

// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created payment succeeded email template
// - Displays payment details and transaction info
// - Shows subscription status and next payment date
// - Includes link to payment history
// - Future: Add downloadable receipt option
// - Future: Include invoice attachment
?>
