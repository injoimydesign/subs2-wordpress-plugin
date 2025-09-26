<?php
/**
 * Email Notification System Class
 *
 * Handles all email notifications for subscription events,
 * customer communications, and administrative alerts.
 *
 * @package Subs
 * @subpackage Emails
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs Emails Class
 *
 * @class Subs_Emails
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Emails {

    /**
     * Email settings
     *
     * @var array
     * @since 1.0.0
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->load_settings();
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize email system
     *
     * @since 1.0.0
     */
    public function init() {
        // Hook into subscription events
        add_action('subs_subscription_created', array($this, 'send_subscription_created_email'), 10, 2);
        add_action('subs_subscription_cancelled', array($this, 'send_subscription_cancelled_email'), 10, 3);
        add_action('subs_subscription_paused', array($this, 'send_subscription_paused_email'), 10, 2);
        add_action('subs_subscription_resumed', array($this, 'send_subscription_resumed_email'), 10, 1);
        add_action('subs_subscription_renewed', array($this, 'send_subscription_renewed_email'), 10, 2);

        // Hook into payment events
        add_action('subs_payment_succeeded', array($this, 'send_payment_succeeded_email'), 10, 2);
        add_action('subs_payment_failed', array($this, 'send_payment_failed_email'), 10, 3);

        // Hook into customer events
        add_action('subs_send_customer_welcome_email', array($this, 'send_customer_welcome_email'), 10, 2);
        add_action('subs_trial_ending', array($this, 'send_trial_ending_email'), 10, 1);

        // Hook into WordPress email filters
        add_filter('wp_mail_from', array($this, 'get_from_email'));
        add_filter('wp_mail_from_name', array($this, 'get_from_name'));
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));
    }

    /**
     * Load email settings
     *
     * @since 1.0.0
     */
    private function load_settings() {
        $this->settings = get_option('subs_email_settings', array(
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'subscription_created_enabled' => 'yes',
            'subscription_cancelled_enabled' => 'yes',
            'payment_failed_enabled' => 'yes',
            'payment_succeeded_enabled' => 'yes',
            'customer_welcome_enabled' => 'yes',
            'trial_ending_enabled' => 'yes',
            'admin_notifications_enabled' => 'yes',
        ));
    }

    /**
     * Send subscription created email
     *
     * @param int $subscription_id
     * @param array $subscription_data
     * @since 1.0.0
     */
    public function send_subscription_created_email($subscription_id, $subscription_data) {
        if ($this->settings['subscription_created_enabled'] !== 'yes') {
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || !$subscription->customer) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Welcome! Your %s subscription is confirmed', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'portal_url' => home_url('/subscription-portal/'),
        );

        $message = $this->get_email_template('subscription-created', $template_data);

        $headers = $this->get_email_headers();

        $sent = wp_mail($customer->email, $subject, $message, $headers);

        if (!$sent) {
            error_log("Failed to send subscription created email to {$customer->email}");
        }

        // Send admin notification if enabled
        if ($this->settings['admin_notifications_enabled'] === 'yes') {
            $this->send_admin_notification(
                sprintf(__('New subscription created: %s', 'subs'), $subscription->product_name),
                sprintf(__('Customer %s (%s) has created a new subscription for %s.', 'subs'),
                       $customer->email,
                       trim($customer->first_name . ' ' . $customer->last_name),
                       $subscription->product_name)
            );
        }
    }

    /**
     * Send subscription cancelled email
     *
     * @param int $subscription_id
     * @param string $reason
     * @param bool $immediate
     * @since 1.0.0
     */
    public function send_subscription_cancelled_email($subscription_id, $reason, $immediate) {
        if ($this->settings['subscription_cancelled_enabled'] !== 'yes') {
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || !$subscription->customer) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Your %s subscription has been cancelled', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'reason' => $reason,
            'immediate' => $immediate,
            'cancellation_date' => $subscription->cancellation_date,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'support_email' => $this->settings['from_email'],
        );

        $message = $this->get_email_template('subscription-cancelled', $template_data);

        $headers = $this->get_email_headers();

        $sent = wp_mail($customer->email, $subject, $message, $headers);

        if (!$sent) {
            error_log("Failed to send subscription cancelled email to {$customer->email}");
        }
    }

    /**
     * Send subscription paused email
     *
     * @param int $subscription_id
     * @param string $reason
     * @since 1.0.0
     */
    public function send_subscription_paused_email($subscription_id, $reason) {
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || !$subscription->customer) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Your %s subscription has been paused', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'reason' => $reason,
            'site_name' => get_bloginfo('name'),
            'portal_url' => home_url('/subscription-portal/'),
        );

        $message = $this->get_email_template('subscription-paused', $template_data);

        $headers = $this->get_email_headers();

        wp_mail($customer->email, $subject, $message, $headers);
    }

    /**
     * Send subscription resumed email
     *
     * @param int $subscription_id
     * @since 1.0.0
     */
    public function send_subscription_resumed_email($subscription_id) {
        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || !$subscription->customer) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Your %s subscription has been resumed', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'site_name' => get_bloginfo('name'),
            'portal_url' => home_url('/subscription-portal/'),
        );

        $message = $this->get_email_template('subscription-resumed', $template_data);

        $headers = $this->get_email_headers();

        wp_mail($customer->email, $subject, $message, $headers);
    }

    /**
     * Send subscription renewed email
     *
     * @param int $subscription_id
     * @param object $subscription
     * @since 1.0.0
     */
    public function send_subscription_renewed_email($subscription_id, $subscription) {
        if (!$subscription->customer) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Your %s subscription has been renewed', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'site_name' => get_bloginfo('name'),
            'portal_url' => home_url('/subscription-portal/'),
        );

        $message = $this->get_email_template('subscription-renewed', $template_data);

        $headers = $this->get_email_headers();

        wp_mail($customer->email, $subject, $message, $headers);
    }

    /**
     * Send payment succeeded email
     *
     * @param int $subscription_id
     * @param array $payment_data
     * @since 1.0.0
     */
    public function send_payment_succeeded_email($subscription_id, $payment_data) {
        if ($this->settings['payment_succeeded_enabled'] !== 'yes') {
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || !$subscription->customer) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Payment received for %s', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'payment' => $payment_data,
            'amount_formatted' => $this->format_amount($payment_data['amount'], $payment_data['currency']),
            'site_name' => get_bloginfo('name'),
            'portal_url' => home_url('/subscription-portal/'),
        );

        $message = $this->get_email_template('payment-succeeded', $template_data);

        $headers = $this->get_email_headers();

        wp_mail($customer->email, $subject, $message, $headers);
    }

    /**
     * Send payment failed email
     *
     * @param int $subscription_id
     * @param string $error_message
     * @param int $failure_count
     * @since 1.0.0
     */
    public function send_payment_failed_email($subscription_id, $error_message, $failure_count) {
        if ($this->settings['payment_failed_enabled'] !== 'yes') {
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || !$subscription->customer) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Payment failed for %s', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'error_message' => $error_message,
            'failure_count' => $failure_count,
            'max_failures' => apply_filters('subs_max_payment_failures', 3),
            'site_name' => get_bloginfo('name'),
            'portal_url' => home_url('/subscription-portal/'),
            'support_email' => $this->settings['from_email'],
        );

        $message = $this->get_email_template('payment-failed', $template_data);

        $headers = $this->get_email_headers();

        wp_mail($customer->email, $subject, $message, $headers);

        // Send admin notification for payment failures
        if ($this->settings['admin_notifications_enabled'] === 'yes') {
            $this->send_admin_notification(
                sprintf(__('Payment failed: %s (Attempt #%d)', 'subs'), $subscription->product_name, $failure_count),
                sprintf(__('Payment failed for customer %s (%s). Error: %s', 'subs'),
                       $customer->email,
                       trim($customer->first_name . ' ' . $customer->last_name),
                       $error_message)
            );
        }
    }

    /**
     * Send customer welcome email
     *
     * @param int $customer_id
     * @param array $customer_data
     * @since 1.0.0
     */
    public function send_customer_welcome_email($customer_id, $customer_data) {
        if ($this->settings['customer_welcome_enabled'] !== 'yes') {
            return;
        }

        $customer_handler = new Subs_Customer();
        $customer = $customer_handler->get_customer($customer_id);

        if (!$customer) {
            return;
        }

        $subject = sprintf(__('Welcome to %s!', 'subs'), get_bloginfo('name'));

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'customer' => $customer,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'portal_url' => home_url('/subscription-portal/'),
            'support_email' => $this->settings['from_email'],
        );

        $message = $this->get_email_template('customer-welcome', $template_data);

        $headers = $this->get_email_headers();

        wp_mail($customer->email, $subject, $message, $headers);
    }

    /**
     * Send trial ending email
     *
     * @param int $subscription_id
     * @since 1.0.0
     */
    public function send_trial_ending_email($subscription_id) {
        if ($this->settings['trial_ending_enabled'] !== 'yes') {
            return;
        }

        $subscription_handler = new Subs_Subscription();
        $subscription = $subscription_handler->get_subscription($subscription_id);

        if (!$subscription || !$subscription->customer || !$subscription->trial_end) {
            return;
        }

        $customer = $subscription->customer;

        $subject = sprintf(__('Your %s trial is ending soon', 'subs'), $subscription->product_name);

        $template_data = array(
            'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'subscription' => $subscription,
            'customer' => $customer,
            'trial_end_date' => date_i18n(get_option('date_format'), strtotime($subscription->trial_end)),
            'site_name' => get_bloginfo('name'),
            'portal_url' => home_url('/subscription-portal/'),
        );

        $message = $this->get_email_template('trial-ending', $template_data);

        $headers = $this->get_email_headers();

        wp_mail($customer->email, $subject, $message, $headers);
    }

    /**
     * Send admin notification
     *
     * @param string $subject
     * @param string $message
     * @since 1.0.0
     */
    public function send_admin_notification($subject, $message) {
        $admin_email = get_option('admin_email');
        $headers = $this->get_email_headers();

        wp_mail($admin_email, '[' . get_bloginfo('name') . '] ' . $subject, $message, $headers);
    }

    /**
     * Get email template
     *
     * @param string $template_name
     * @param array $template_data
     * @return string
     * @since 1.0.0
     */
    public function get_email_template($template_name, $template_data = array()) {
        // Extract template variables
        extract($template_data);

        // Try to load custom template from theme first
        $theme_template = locate_template(array(
            "subs/emails/{$template_name}.php",
            "templates/subs/emails/{$template_name}.php",
        ));

        if ($theme_template) {
            ob_start();
            include $theme_template;
            $content = ob_get_clean();
        } else {
            // Use default template
            $content = $this->get_default_template($template_name, $template_data);
        }

        // Apply filters for customization
        return apply_filters("subs_email_template_{$template_name}", $content, $template_data);
    }

    /**
     * Get default email template
     *
     * @param string $template_name
     * @param array $template_data
     * @return string
     * @since 1.0.0
     */
    private function get_default_template($template_name, $template_data) {
        extract($template_data);

        $templates = array(
            'subscription-created' => $this->get_subscription_created_template($template_data),
            'subscription-cancelled' => $this->get_subscription_cancelled_template($template_data),
            'subscription-paused' => $this->get_subscription_paused_template($template_data),
            'subscription-resumed' => $this->get_subscription_resumed_template($template_data),
            'subscription-renewed' => $this->get_subscription_renewed_template($template_data),
            'payment-succeeded' => $this->get_payment_succeeded_template($template_data),
            'payment-failed' => $this->get_payment_failed_template($template_data),
            'customer-welcome' => $this->get_customer_welcome_template($template_data),
            'trial-ending' => $this->get_trial_ending_template($template_data),
        );

        return $templates[$template_name] ?? $this->get_fallback_template($template_data);
    }

    /**
     * Get subscription created template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_subscription_created_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;
        $subscription = $data['subscription'];

        return sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>%s</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
        .subscription-details { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .button { display: inline-block; padding: 12px 24px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>%s</h1>
        </div>
        <div class="content">
            <p>Hello %s,</p>

            <p>Thank you for subscribing to <strong>%s</strong>! Your subscription has been successfully created and is now active.</p>

            <div class="subscription-details">
                <h3>Subscription Details:</h3>
                <ul>
                    <li><strong>Product:</strong> %s</li>
                    <li><strong>Amount:</strong> %s %s</li>
                    <li><strong>Billing:</strong> Every %d %s</li>
                    <li><strong>Status:</strong> %s</li>
                    <li><strong>Next Payment:</strong> %s</li>
                </ul>
            </div>

            <p>You can manage your subscription, update payment methods, and view your billing history in your customer portal:</p>

            <p style="text-align: center;">
                <a href="%s" class="button">Manage Subscription</a>
            </p>

            <p>If you have any questions, please don\'t hesitate to contact us.</p>

            <p>Best regards,<br>The %s Team</p>
        </div>
        <div class="footer">
            <p>This email was sent from %s</p>
        </div>
    </div>
</body>
</html>',
            __('Subscription Confirmed', 'subs'),
            __('Welcome to Your Subscription!', 'subs'),
            esc_html($customer_name),
            esc_html($data['site_name']),
            esc_html($subscription->product_name),
            esc_html($subscription->amount),
            esc_html(strtoupper($subscription->currency)),
            $subscription->billing_interval,
            esc_html($subscription->billing_period),
            esc_html(ucfirst($subscription->status)),
            $subscription->next_payment_date ? esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))) : __('N/A', 'subs'),
            esc_url($data['portal_url']),
            esc_html($data['site_name']),
            esc_html($data['site_url'])
        );
    }

    /**
     * Get subscription cancelled template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_subscription_cancelled_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;
        $subscription = $data['subscription'];

        return sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>%s</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>%s</h1>
        </div>
        <div class="content">
            <p>Hello %s,</p>

            <p>We\'re sorry to see you go. Your subscription to <strong>%s</strong> has been cancelled as requested.</p>

            %s

            %s

            <p>You will continue to have access to your subscription benefits until %s.</p>

            <p>If you change your mind or have any questions, please don\'t hesitate to contact us at %s.</p>

            <p>Thank you for being a valued customer.</p>

            <p>Best regards,<br>The %s Team</p>
        </div>
        <div class="footer">
            <p>This email was sent from %s</p>
        </div>
    </div>
</body>
</html>',
            __('Subscription Cancelled', 'subs'),
            __('Subscription Cancellation Confirmation', 'subs'),
            esc_html($customer_name),
            esc_html($subscription->product_name),
            !empty($data['reason']) ? '<p><strong>Reason:</strong> ' . esc_html($data['reason']) . '</p>' : '',
            $data['immediate'] ? '<p><strong>Note:</strong> Your subscription has been cancelled immediately.</p>' : '',
            esc_html(date_i18n(get_option('date_format'), strtotime($subscription->current_period_end))),
            esc_html($data['support_email']),
            esc_html($data['site_name']),
            esc_html($data['site_url'])
        );
    }

    /**
     * Get payment failed template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_payment_failed_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;
        $subscription = $data['subscription'];

        return sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>%s</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #fef2f2; padding: 20px; text-align: center; border: 1px solid #fca5a5; }
        .content { padding: 20px; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
        .button { display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 4px; }
        .warning { background: #fef3cd; padding: 15px; border: 1px solid #f59e0b; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>%s</h1>
        </div>
        <div class="content">
            <p>Hello %s,</p>

            <p>We were unable to process the payment for your <strong>%s</strong> subscription.</p>

            <p><strong>Error:</strong> %s</p>

            %s

            <p>To avoid any interruption to your service, please update your payment method as soon as possible:</p>

            <p style="text-align: center;">
                <a href="%s" class="button">Update Payment Method</a>
            </p>

            <p>If you continue to experience issues, please contact us at %s for assistance.</p>

            <p>Best regards,<br>The %s Team</p>
        </div>
        <div class="footer">
            <p>This email was sent from %s</p>
        </div>
    </div>
</body>
</html>',
            __('Payment Failed', 'subs'),
            __('Payment Failed - Action Required', 'subs'),
            esc_html($customer_name),
            esc_html($subscription->product_name),
            esc_html($data['error_message']),
            $data['failure_count'] >= $data['max_failures'] ?
                '<div class="warning"><strong>Important:</strong> This was attempt #' . $data['failure_count'] . ' of ' . $data['max_failures'] . '. Your subscription may be cancelled if payment continues to fail.</div>' :
                '<p>This was payment attempt #' . $data['failure_count'] . '. We will retry a few more times.</p>',
            esc_url($data['portal_url']),
            esc_html($data['support_email']),
            esc_html($data['site_name']),
            esc_html($data['site_url'])
        );
    }

    /**
     * Get payment succeeded template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_payment_succeeded_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;
        $subscription = $data['subscription'];

        return sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>%s</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f0fdf4; padding: 20px; text-align: center; border: 1px solid #86efac; }
        .content { padding: 20px; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
        .payment-details { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>%s</h1>
        </div>
        <div class="content">
            <p>Hello %s,</p>

            <p>Thank you! We have successfully received your payment for <strong>%s</strong>.</p>

            <div class="payment-details">
                <h3>Payment Details:</h3>
                <ul>
                    <li><strong>Amount:</strong> %s</li>
                    <li><strong>Date:</strong> %s</li>
                    <li><strong>Next Payment:</strong> %s</li>
                </ul>
            </div>

            <p>Your subscription will continue uninterrupted. You can view your complete payment history in your customer portal.</p>

            <p>Thank you for your continued business!</p>

            <p>Best regards,<br>The %s Team</p>
        </div>
        <div class="footer">
            <p>This email was sent from %s</p>
        </div>
    </div>
</body>
</html>',
            __('Payment Received', 'subs'),
            __('Payment Confirmation', 'subs'),
            esc_html($customer_name),
            esc_html($subscription->product_name),
            esc_html($data['amount_formatted']),
            esc_html(date_i18n(get_option('date_format'))),
            $subscription->next_payment_date ? esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date))) : __('N/A', 'subs'),
            esc_html($data['site_name']),
            esc_html($data['site_url'])
        );
    }

    /**
     * Get customer welcome template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_customer_welcome_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;

        return sprintf('
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>%s</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
        .button { display: inline-block; padding: 12px 24px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>%s</h1>
        </div>
        <div class="content">
            <p>Hello %s,</p>

            <p>Welcome to %s! We\'re excited to have you as a customer.</p>

            <p>Your customer account has been created and you can now manage your subscriptions, update your information, and view your billing history through your customer portal.</p>

            <p style="text-align: center;">
                <a href="%s" class="button">Access Customer Portal</a>
            </p>

            <p>If you have any questions or need assistance, please don\'t hesitate to contact us at %s.</p>

            <p>Best regards,<br>The %s Team</p>
        </div>
        <div class="footer">
            <p>This email was sent from %s</p>
        </div>
    </div>
</body>
</html>',
            __('Welcome!', 'subs'),
            sprintf(__('Welcome to %s!', 'subs'), $data['site_name']),
            esc_html($customer_name),
            esc_html($data['site_name']),
            esc_url($data['portal_url']),
            esc_html($data['support_email']),
            esc_html($data['site_name']),
            esc_html($data['site_url'])
        );
    }

    /**
     * Get subscription paused template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_subscription_paused_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;

        return sprintf('
            <p>Hello %s,</p>
            <p>Your subscription to %s has been paused.</p>
            %s
            <p>You can resume your subscription anytime from your customer portal.</p>
            <p>Best regards, The %s Team</p>',
            esc_html($customer_name),
            esc_html($data['subscription']->product_name),
            !empty($data['reason']) ? '<p>Reason: ' . esc_html($data['reason']) . '</p>' : '',
            esc_html($data['site_name'])
        );
    }

    /**
     * Get subscription resumed template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_subscription_resumed_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;

        return sprintf('
            <p>Hello %s,</p>
            <p>Great news! Your subscription to %s has been resumed.</p>
            <p>Your next payment will be processed on %s.</p>
            <p>Best regards, The %s Team</p>',
            esc_html($customer_name),
            esc_html($data['subscription']->product_name),
            esc_html(date_i18n(get_option('date_format'), strtotime($data['subscription']->next_payment_date))),
            esc_html($data['site_name'])
        );
    }

    /**
     * Get subscription renewed template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_subscription_renewed_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;

        return sprintf('
            <p>Hello %s,</p>
            <p>Your subscription to %s has been renewed for another billing period.</p>
            <p>Your next payment will be processed on %s.</p>
            <p>Best regards, The %s Team</p>',
            esc_html($customer_name),
            esc_html($data['subscription']->product_name),
            esc_html(date_i18n(get_option('date_format'), strtotime($data['subscription']->next_payment_date))),
            esc_html($data['site_name'])
        );
    }

    /**
     * Get trial ending template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_trial_ending_template($data) {
        $customer_name = !empty($data['customer_name']) ? $data['customer_name'] : $data['customer']->email;

        return sprintf('
            <p>Hello %s,</p>
            <p>Your trial for %s is ending on %s.</p>
            <p>To continue your subscription, please ensure your payment method is up to date.</p>
            <p>Best regards, The %s Team</p>',
            esc_html($customer_name),
            esc_html($data['subscription']->product_name),
            esc_html($data['trial_end_date']),
            esc_html($data['site_name'])
        );
    }

    /**
     * Get fallback template
     *
     * @param array $data
     * @return string
     * @since 1.0.0
     */
    private function get_fallback_template($data) {
        return sprintf('
            <p>Hello,</p>
            <p>This is an automated message from %s.</p>
            <p>Best regards, The %s Team</p>',
            esc_html($data['site_name'] ?? get_bloginfo('name')),
            esc_html($data['site_name'] ?? get_bloginfo('name'))
        );
    }

    /**
     * Get email headers
     *
     * @return array
     * @since 1.0.0
     */
    private function get_email_headers() {
        $headers = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>';

        return $headers;
    }

    /**
     * Get from email address
     *
     * @param string $email
     * @return string
     * @since 1.0.0
     */
    public function get_from_email($email = '') {
        return !empty($this->settings['from_email']) ? $this->settings['from_email'] : get_option('admin_email');
    }

    /**
     * Get from name
     *
     * @param string $name
     * @return string
     * @since 1.0.0
     */
    public function get_from_name($name = '') {
        return !empty($this->settings['from_name']) ? $this->settings['from_name'] : get_bloginfo('name');
    }

    /**
     * Get content type
     *
     * @param string $content_type
     * @return string
     * @since 1.0.0
     */
    public function get_content_type($content_type = '') {
        return 'text/html';
    }

    /**
     * Format amount for display
     *
     * @param float $amount
     * @param string $currency
     * @return string
     * @since 1.0.0
     */
    private function format_amount($amount, $currency) {
        $currency_symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C',
            'AUD' => 'A',
        );

        $symbol = $currency_symbols[$currency] ?? $currency;
        return $symbol . number_format($amount, 2);
    }

    /**
     * Test email sending
     *
     * @param string $to
     * @return bool|WP_Error
     * @since 1.0.0
     */
    public function test_email($to) {
        $subject = sprintf(__('[%s] Email Test', 'subs'), get_bloginfo('name'));
        $message = sprintf(__('This is a test email from the Subs plugin. If you received this, your email configuration is working correctly. Sent at %s.', 'subs'), current_time('mysql'));

        $headers = $this->get_email_headers();

        $sent = wp_mail($to, $subject, $message, $headers);

        if (!$sent) {
            return new WP_Error('email_failed', __('Failed to send test email.', 'subs'));
        }

        return true;
    }

    /**
     * Get email settings
     *
     * @return array
     * @since 1.0.0
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Update email settings
     *
     * @param array $settings
     * @return bool
     * @since 1.0.0
     */
    public function update_settings($settings) {
        $this->settings = array_merge($this->settings, $settings);
        return update_option('subs_email_settings', $this->settings);
    }
}
