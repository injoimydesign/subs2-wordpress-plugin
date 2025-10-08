<?php
/**
 * Trial Ending Email Template
 *
 * This template is sent when a trial period is about to end.
 * Can be overridden by copying to yourtheme/subs/emails/trial-ending.php
 *
 * @package Subs
 * @subpackage Templates/Emails
 * @version 1.0.0
 *
 * @var object $subscription The subscription object
 * @var object $customer The customer object
 * @var string $customer_name Customer's full name
 * @var string $site_name Site name
 * @var string $site_url Site URL
 * @var string $portal_url Customer portal URL
 * @var string $trial_end_date Trial end date
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Set email heading
$email_heading = __('Your Trial is Ending Soon', 'subs');

// Include email header
include SUBS_PLUGIN_PATH . 'templates/emails/email-header.php';
?>

<p><?php printf(esc_html__('Hello %s,', 'subs'), esc_html($customer_name)); ?></p>

<p><?php printf(
    esc_html__('We hope you\'re enjoying your trial of %s! This is a friendly reminder that your trial period will end soon.', 'subs'),
    '<strong>' . esc_html($subscription->product_name) . '</strong>'
); ?></p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #d1ecf1; padding: 15px; margin: 20px 0; border-left: 4px solid #17a2b8; border-radius: 3px;">
    <tr>
        <td align="center">
            <span style="font-size: 48px; color: #17a2b8;">⏰</span>
            <p style="margin: 10px 0 0 0; color: #0c5460; font-size: 18px; font-weight: bold;">
                <?php printf(
                    esc_html__('Trial Ends: %s', 'subs'),
                    esc_html($trial_end_date)
                ); ?>
            </p>
        </td>
    </tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <tr>
        <td>
            <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 18px;">
                <?php esc_html_e('Subscription Details:', 'subs'); ?>
            </h3>

            <table cellpadding="5" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('Product:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right;">
                        <?php echo esc_html($subscription->product_name); ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('Trial Ends:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right;">
                        <?php echo esc_html($trial_end_date); ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd;">
                        <strong><?php esc_html_e('First Charge:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #dddddd; text-align: right;">
                        <?php echo esc_html($subscription->amount . ' ' . strtoupper($subscription->currency)); ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 8px 0;">
                        <strong><?php esc_html_e('Billing Cycle:', 'subs'); ?></strong>
                    </td>
                    <td style="padding: 8px 0; text-align: right;">
                        <?php
                        printf(
                            esc_html__('Every %d %s', 'subs'),
                            $subscription->billing_interval,
                            esc_html($subscription->billing_period)
                        );
                        ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 3px;">
    <tr>
        <td>
            <p style="margin: 0 0 10px 0; color: #856404;">
                <strong><?php esc_html_e('What happens next?', 'subs'); ?></strong>
            </p>
            <ul style="margin: 0; padding-left: 20px; color: #856404;">
                <li style="margin-bottom: 8px;">
                    <?php printf(
                        esc_html__('After %s, your subscription will automatically convert to a paid subscription.', 'subs'),
                        '<strong>' . esc_html($trial_end_date) . '</strong>'
                    ); ?>
                </li>
                <li style="margin-bottom: 8px;">
                    <?php printf(
                        esc_html__('Your payment method will be charged %s.', 'subs'),
                        '<strong>' . esc_html($subscription->amount . ' ' . strtoupper($subscription->currency)) . '</strong>'
                    ); ?>
                </li>
                <li style="margin-bottom: 0;">
                    <?php esc_html_e('You can cancel anytime before your trial ends to avoid being charged.', 'subs'); ?>
                </li>
            </ul>
        </td>
    </tr>
</table>

<p><?php esc_html_e('To continue your subscription, please ensure your payment method is up to date:', 'subs'); ?></p>

<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 20px 0;">
    <tr>
        <td align="center">
            <a href="<?php echo esc_url($portal_url); ?>" style="display: inline-block; padding: 15px 30px; background-color: #007cba; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; margin: 0 5px 10px 5px;">
                <?php esc_html_e('Manage Subscription', 'subs'); ?>
            </a>
        </td>
    </tr>
</table>

<p><?php esc_html_e('If you don\'t wish to continue, you can cancel your subscription from your customer portal at any time.', 'subs'); ?></p>

<p><?php esc_html_e('Thank you for trying our service!', 'subs'); ?></p>

<p><?php printf(esc_html__('Best regards,%sThe %s Team', 'subs'), '<br>', esc_html($site_name)); ?></p>

<?php
// Include email footer
include SUBS_PLUGIN_PATH . 'templates/emails/email-footer.php';

// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created trial ending email template
// - Displays trial end date and next steps
// - Shows first charge amount
// - Includes manage and cancel options
// - Future: Add "Continue without trial" option
// - Future: Include promotional offer for continuation
?>
