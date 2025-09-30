<?php
/**
 * Admin Settings Class
 *
 * Handles the settings page functionality in the WordPress admin area.
 * Manages configuration options for Stripe, email notifications, and general settings.
 *
 * @package Subs
 * @subpackage Admin
 * @since 1.0.0
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs Admin Settings Class
 *
 * @class Subs_Admin_Settings
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Admin_Settings {

    /**
     * Settings tabs
     *
     * @var array
     * @since 1.0.0
     */
    private $settings_tabs = array();

    /**
     * Current active tab
     *
     * @var string
     * @since 1.0.0
     */
    private $current_tab = '';

    /**
     * Constructor
     *
     * Initialize the settings class and set up hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize hooks
        $this->init_hooks();

        // Set up settings tabs
        $this->init_settings_tabs();

        // Get current tab
        $this->current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    }

    /**
     * Initialize hooks
     *
     * Set up WordPress hooks for settings functionality.
     *
     * @access private
     * @since 1.0.0
     */
    private function init_hooks() {
        // Admin init hook for settings registration
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX hooks for settings actions
        add_action('wp_ajax_subs_test_stripe_connection', array($this, 'test_stripe_connection'));
        add_action('wp_ajax_subs_reset_settings', array($this, 'reset_settings'));

        // Settings page hooks
        add_action('subs_settings_tabs', array($this, 'render_tabs'));
        add_action('subs_settings_content', array($this, 'render_content'));
    }

    /**
     * Initialize settings tabs
     *
     * Define the available settings tabs and their properties.
     *
     * @access private
     * @since 1.0.0
     */
    private function init_settings_tabs() {
        $this->settings_tabs = array(
            'general' => array(
                'label' => __('General', 'subs'),
                'description' => __('Basic plugin configuration and currency settings', 'subs'),
                'icon' => 'dashicons-admin-generic',
                'priority' => 10,
            ),
            'stripe' => array(
                'label' => __('Stripe', 'subs'),
                'description' => __('Configure Stripe payment gateway integration', 'subs'),
                'icon' => 'dashicons-money-alt',
                'priority' => 20,
            ),
            'emails' => array(
                'label' => __('Emails', 'subs'),
                'description' => __('Email notification settings and templates', 'subs'),
                'icon' => 'dashicons-email-alt',
                'priority' => 30,
            ),
            'advanced' => array(
                'label' => __('Advanced', 'subs'),
                'description' => __('Advanced plugin settings and debugging options', 'subs'),
                'icon' => 'dashicons-admin-tools',
                'priority' => 40,
            ),
        );

        // Allow filtering of settings tabs
        $this->settings_tabs = apply_filters('subs_admin_settings_tabs', $this->settings_tabs);

        // Sort tabs by priority
        uasort($this->settings_tabs, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    /**
     * Register settings with WordPress
     *
     * Register all plugin settings with WordPress settings API.
     *
     * @access public
     * @since 1.0.0
     */
    public function register_settings() {
        // Register general settings
        register_setting('subs_general_settings', 'subs_general_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_general_settings'),
        ));

        // Register Stripe settings
        register_setting('subs_stripe_settings', 'subs_stripe_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_stripe_settings'),
        ));

        // Register email settings
        register_setting('subs_email_settings', 'subs_email_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_email_settings'),
        ));

        // Register advanced settings
        register_setting('subs_advanced_settings', 'subs_advanced_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_advanced_settings'),
        ));

        // Add settings sections and fields
        $this->add_settings_sections();
    }

    /**
     * Add settings sections and fields
     *
     * Define and register all settings sections and fields.
     *
     * @access private
     * @since 1.0.0
     */
    private function add_settings_sections() {
        // General settings section
        add_settings_section(
            'subs_general_section',
            __('General Settings', 'subs'),
            array($this, 'general_section_callback'),
            'subs_general_settings'
        );

        // General settings fields
        $this->add_general_settings_fields();

        // Stripe settings section
        add_settings_section(
            'subs_stripe_section',
            __('Stripe Configuration', 'subs'),
            array($this, 'stripe_section_callback'),
            'subs_stripe_settings'
        );

        // Stripe settings fields
        $this->add_stripe_settings_fields();

        // Email settings section
        add_settings_section(
            'subs_email_section',
            __('Email Configuration', 'subs'),
            array($this, 'email_section_callback'),
            'subs_email_settings'
        );

        // Email settings fields
        $this->add_email_settings_fields();

        // Advanced settings section
        add_settings_section(
            'subs_advanced_section',
            __('Advanced Options', 'subs'),
            array($this, 'advanced_section_callback'),
            'subs_advanced_settings'
        );

        // Advanced settings fields
        $this->add_advanced_settings_fields();
    }

    /**
     * Add general settings fields
     *
     * @access private
     * @since 1.0.0
     */
    private function add_general_settings_fields() {
        // Currency field
        add_settings_field(
            'currency',
            __('Currency', 'subs'),
            array($this, 'currency_field_callback'),
            'subs_general_settings',
            'subs_general_section'
        );

        // Currency position field
        add_settings_field(
            'currency_position',
            __('Currency Position', 'subs'),
            array($this, 'currency_position_field_callback'),
            'subs_general_settings',
            'subs_general_section'
        );

        // Decimal separator field
        add_settings_field(
            'decimal_separator',
            __('Decimal Separator', 'subs'),
            array($this, 'decimal_separator_field_callback'),
            'subs_general_settings',
            'subs_general_section'
        );

        // Thousand separator field
        add_settings_field(
            'thousand_separator',
            __('Thousand Separator', 'subs'),
            array($this, 'thousand_separator_field_callback'),
            'subs_general_settings',
            'subs_general_section'
        );

        // Number of decimals field
        add_settings_field(
            'number_of_decimals',
            __('Number of Decimals', 'subs'),
            array($this, 'number_of_decimals_field_callback'),
            'subs_general_settings',
            'subs_general_section'
        );
    }

    /**
     * Add Stripe settings fields
     *
     * @access private
     * @since 1.0.0
     */
    private function add_stripe_settings_fields() {
        // Enable Stripe field
        add_settings_field(
            'enabled',
            __('Enable Stripe', 'subs'),
            array($this, 'stripe_enabled_field_callback'),
            'subs_stripe_settings',
            'subs_stripe_section'
        );

        // Test mode field
        add_settings_field(
            'test_mode',
            __('Test Mode', 'subs'),
            array($this, 'stripe_test_mode_field_callback'),
            'subs_stripe_settings',
            'subs_stripe_section'
        );

        // Live API keys
        add_settings_field(
            'live_keys',
            __('Live API Keys', 'subs'),
            array($this, 'stripe_live_keys_field_callback'),
            'subs_stripe_settings',
            'subs_stripe_section'
        );

        // Test API keys
        add_settings_field(
            'test_keys',
            __('Test API Keys', 'subs'),
            array($this, 'stripe_test_keys_field_callback'),
            'subs_stripe_settings',
            'subs_stripe_section'
        );

        // Webhook settings
        add_settings_field(
            'webhook_settings',
            __('Webhook Settings', 'subs'),
            array($this, 'stripe_webhook_field_callback'),
            'subs_stripe_settings',
            'subs_stripe_section'
        );
    }

    /**
     * Add email settings fields
     *
     * @access private
     * @since 1.0.0
     */
    private function add_email_settings_fields() {
        // From name field
        add_settings_field(
            'from_name',
            __('From Name', 'subs'),
            array($this, 'email_from_name_field_callback'),
            'subs_email_settings',
            'subs_email_section'
        );

        // From email field
        add_settings_field(
            'from_email',
            __('From Email', 'subs'),
            array($this, 'email_from_email_field_callback'),
            'subs_email_settings',
            'subs_email_section'
        );

        // Email notifications
        add_settings_field(
            'notifications',
            __('Email Notifications', 'subs'),
            array($this, 'email_notifications_field_callback'),
            'subs_email_settings',
            'subs_email_section'
        );
    }

    /**
     * Add advanced settings fields
     *
     * @access private
     * @since 1.0.0
     */
    private function add_advanced_settings_fields() {
        // Debug logging field
        add_settings_field(
            'debug_logging',
            __('Debug Logging', 'subs'),
            array($this, 'debug_logging_field_callback'),
            'subs_advanced_settings',
            'subs_advanced_section'
        );

        // Data retention field
        add_settings_field(
            'data_retention',
            __('Data Retention', 'subs'),
            array($this, 'data_retention_field_callback'),
            'subs_advanced_settings',
            'subs_advanced_section'
        );

        // Reset settings field
        add_settings_field(
            'reset_settings',
            __('Reset Settings', 'subs'),
            array($this, 'reset_settings_field_callback'),
            'subs_advanced_settings',
            'subs_advanced_section'
        );
    }

    /**
     * Render settings tabs
     *
     * Output the settings navigation tabs.
     *
     * @access public
     * @since 1.0.0
     */
    public function render_tabs() {
        echo '<nav class="nav-tab-wrapper">';

        foreach ($this->settings_tabs as $tab_key => $tab) {
            $active_class = ($this->current_tab === $tab_key) ? ' nav-tab-active' : '';
            $tab_url = admin_url('admin.php?page=subs-settings&tab=' . $tab_key);

            printf(
                '<a href="%s" class="nav-tab%s"><span class="dashicons %s"></span> %s</a>',
                esc_url($tab_url),
                esc_attr($active_class),
                esc_attr($tab['icon']),
                esc_html($tab['label'])
            );
        }

        echo '</nav>';
    }

    /**
     * Render settings content
     *
     * Output the content for the current settings tab.
     *
     * @access public
     * @since 1.0.0
     */
    public function render_content() {
        // Check if current tab exists
        if (!isset($this->settings_tabs[$this->current_tab])) {
            $this->current_tab = 'general';
        }

        $tab = $this->settings_tabs[$this->current_tab];

        echo '<div class="subs-settings-content">';
        echo '<div class="subs-settings-header">';
        echo '<h2>' . esc_html($tab['label']) . '</h2>';
        echo '<p class="description">' . esc_html($tab['description']) . '</p>';
        echo '</div>';

        // Render form for current tab
        echo '<form method="post" action="options.php">';

        // Add nonce and action fields
        settings_fields('subs_' . $this->current_tab . '_settings');

        // Add settings sections
        do_settings_sections('subs_' . $this->current_tab . '_settings');

        // Submit button
        submit_button(__('Save Settings', 'subs'));

        echo '</form>';
        echo '</div>';
    }

    /**
     * General section callback
     *
     * @access public
     * @since 1.0.0
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure basic plugin settings and currency options.', 'subs') . '</p>';
    }

    /**
     * Stripe section callback
     *
     * @access public
     * @since 1.0.0
     */
    public function stripe_section_callback() {
        echo '<p>' . __('Configure your Stripe payment gateway settings. You can get your API keys from your Stripe dashboard.', 'subs') . '</p>';
    }

    /**
     * Email section callback
     *
     * @access public
     * @since 1.0.0
     */
    public function email_section_callback() {
        echo '<p>' . __('Configure email settings for notifications sent to customers and administrators.', 'subs') . '</p>';
    }

    /**
     * Advanced section callback
     *
     * @access public
     * @since 1.0.0
     */
    public function advanced_section_callback() {
        echo '<p>' . __('Advanced plugin options for debugging and maintenance.', 'subs') . '</p>';
    }

    /**
     * Currency field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function currency_field_callback() {
        $settings = get_option('subs_general_settings', array());
        $value = isset($settings['currency']) ? $settings['currency'] : 'USD';

        $currencies = array(
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'British Pound (£)',
            'CAD' => 'Canadian Dollar (C$)',
            'AUD' => 'Australian Dollar (A$)',
            'JPY' => 'Japanese Yen (¥)',
        );

        echo '<select name="subs_general_settings[currency]" id="currency">';
        foreach ($currencies as $code => $name) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($code),
                selected($value, $code, false),
                esc_html($name)
            );
        }
        echo '</select>';
        echo '<p class="description">' . __('Select which email notifications to send automatically.', 'subs') . '</p>';
    }

    /**
     * Debug logging field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function debug_logging_field_callback() {
        $settings = get_option('subs_advanced_settings', array());
        $value = isset($settings['debug_logging']) ? $settings['debug_logging'] : 'no';

        printf(
            '<input type="checkbox" name="subs_advanced_settings[debug_logging]" id="debug_logging" value="yes"%s />',
            checked($value, 'yes', false)
        );
        echo '<label for="debug_logging">' . __('Enable debug logging', 'subs') . '</label>';
        echo '<p class="description">' . __('Log detailed information for troubleshooting. Logs are stored in /wp-content/uploads/subs-logs/.', 'subs') . '</p>';
    }

    /**
     * Data retention field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function data_retention_field_callback() {
        $settings = get_option('subs_advanced_settings', array());
        $value = isset($settings['data_retention_days']) ? $settings['data_retention_days'] : 365;

        printf(
            '<input type="number" name="subs_advanced_settings[data_retention_days]" id="data_retention_days" value="%s" min="30" max="3650" class="small-text" />',
            esc_attr($value)
        );
        echo ' ' . __('days', 'subs');
        echo '<p class="description">' . __('How long to keep cancelled subscription data before permanent deletion.', 'subs') . '</p>';
    }

    /**
     * Reset settings field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function reset_settings_field_callback() {
        echo '<button type="button" id="reset-settings" class="button button-secondary" data-confirm="' . esc_attr__('Are you sure you want to reset all settings to defaults? This cannot be undone.', 'subs') . '">';
        echo __('Reset All Settings', 'subs');
        echo '</button>';
        echo '<p class="description">' . __('Reset all plugin settings to their default values.', 'subs') . '</p>';
    }

    /**
     * Sanitize general settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function sanitize_general_settings($input) {
        $sanitized = array();

        // Currency
        $sanitized['currency'] = isset($input['currency']) ? sanitize_text_field($input['currency']) : 'USD';

        // Currency position
        $valid_positions = array('left', 'right', 'left_space', 'right_space');
        $sanitized['currency_position'] = isset($input['currency_position']) && in_array($input['currency_position'], $valid_positions)
            ? $input['currency_position'] : 'left';

        // Decimal separator
        $sanitized['decimal_separator'] = isset($input['decimal_separator']) ? substr(sanitize_text_field($input['decimal_separator']), 0, 1) : '.';

        // Thousand separator
        $sanitized['thousand_separator'] = isset($input['thousand_separator']) ? substr(sanitize_text_field($input['thousand_separator']), 0, 1) : ',';

        // Number of decimals
        $sanitized['number_of_decimals'] = isset($input['number_of_decimals']) ? max(0, min(4, intval($input['number_of_decimals']))) : 2;

        return $sanitized;
    }

    /**
     * Sanitize Stripe settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function sanitize_stripe_settings($input) {
        $sanitized = array();

        // Enable Stripe
        $sanitized['enabled'] = isset($input['enabled']) && $input['enabled'] === 'yes' ? 'yes' : 'no';

        // Test mode
        $sanitized['test_mode'] = isset($input['test_mode']) && $input['test_mode'] === 'yes' ? 'yes' : 'no';

        // Live keys
        $sanitized['publishable_key'] = isset($input['publishable_key']) ? sanitize_text_field($input['publishable_key']) : '';
        $sanitized['secret_key'] = isset($input['secret_key']) ? sanitize_text_field($input['secret_key']) : '';

        // Test keys
        $sanitized['test_publishable_key'] = isset($input['test_publishable_key']) ? sanitize_text_field($input['test_publishable_key']) : '';
        $sanitized['test_secret_key'] = isset($input['test_secret_key']) ? sanitize_text_field($input['test_secret_key']) : '';

        // Webhook secret
        $sanitized['webhook_secret'] = isset($input['webhook_secret']) ? sanitize_text_field($input['webhook_secret']) : '';

        // Validate API keys format
        if (!empty($sanitized['publishable_key']) && !preg_match('/^pk_live_/', $sanitized['publishable_key'])) {
            add_settings_error('subs_stripe_settings', 'invalid_live_pk', __('Live publishable key should start with "pk_live_"', 'subs'));
        }

        if (!empty($sanitized['secret_key']) && !preg_match('/^sk_live_/', $sanitized['secret_key'])) {
            add_settings_error('subs_stripe_settings', 'invalid_live_sk', __('Live secret key should start with "sk_live_"', 'subs'));
        }

        if (!empty($sanitized['test_publishable_key']) && !preg_match('/^pk_test_/', $sanitized['test_publishable_key'])) {
            add_settings_error('subs_stripe_settings', 'invalid_test_pk', __('Test publishable key should start with "pk_test_"', 'subs'));
        }

        if (!empty($sanitized['test_secret_key']) && !preg_match('/^sk_test_/', $sanitized['test_secret_key'])) {
            add_settings_error('subs_stripe_settings', 'invalid_test_sk', __('Test secret key should start with "sk_test_"', 'subs'));
        }

        return $sanitized;
    }

    /**
     * Sanitize email settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function sanitize_email_settings($input) {
        $sanitized = array();

        // From name
        $sanitized['from_name'] = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : get_bloginfo('name');

        // From email
        $sanitized['from_email'] = isset($input['from_email']) ? sanitize_email($input['from_email']) : get_option('admin_email');

        // Email notifications
        $notifications = array(
            'subscription_created_enabled',
            'subscription_cancelled_enabled',
            'payment_failed_enabled',
            'payment_succeeded_enabled',
        );

        foreach ($notifications as $notification) {
            $sanitized[$notification] = isset($input[$notification]) && $input[$notification] === 'yes' ? 'yes' : 'no';
        }

        return $sanitized;
    }

    /**
     * Sanitize advanced settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function sanitize_advanced_settings($input) {
        $sanitized = array();

        // Debug logging
        $sanitized['debug_logging'] = isset($input['debug_logging']) && $input['debug_logging'] === 'yes' ? 'yes' : 'no';

        // Data retention
        $sanitized['data_retention_days'] = isset($input['data_retention_days']) ? max(30, min(3650, intval($input['data_retention_days']))) : 365;

        return $sanitized;
    }

    /**
     * Test Stripe connection via AJAX
     *
     * @access public
     * @since 1.0.0
     */
    public function test_stripe_connection() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $settings = get_option('subs_stripe_settings', array());
        $test_mode = isset($settings['test_mode']) && $settings['test_mode'] === 'yes';

        $secret_key = $test_mode ?
            (isset($settings['test_secret_key']) ? $settings['test_secret_key'] : '') :
            (isset($settings['secret_key']) ? $settings['secret_key'] : '');

        if (empty($secret_key)) {
            wp_send_json_error(__('No API key provided', 'subs'));
        }

        // Test connection using Stripe API
        try {
            \Stripe\Stripe::setApiKey($secret_key);
            $account = \Stripe\Account::retrieve();

            wp_send_json_success(array(
                'message' => sprintf(__('Connected successfully! Account: %s (%s)', 'subs'), $account->display_name, $account->id),
                'account' => $account->toArray()
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Reset settings via AJAX
     *
     * @access public
     * @since 1.0.0
     */
    public function reset_settings() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        // Get default options
        $defaults = array(
            'subs_general_settings' => array(
                'currency' => 'USD',
                'currency_position' => 'left',
                'decimal_separator' => '.',
                'thousand_separator' => ',',
                'number_of_decimals' => 2,
            ),
            'subs_stripe_settings' => array(
                'enabled' => 'no',
                'test_mode' => 'yes',
                'publishable_key' => '',
                'secret_key' => '',
                'test_publishable_key' => '',
                'test_secret_key' => '',
                'webhook_secret' => '',
            ),
            'subs_email_settings' => array(
                'from_name' => get_bloginfo('name'),
                'from_email' => get_option('admin_email'),
                'subscription_created_enabled' => 'yes',
                'subscription_cancelled_enabled' => 'yes',
                'payment_failed_enabled' => 'yes',
                'payment_succeeded_enabled' => 'yes',
            ),
            'subs_advanced_settings' => array(
                'debug_logging' => 'no',
                'data_retention_days' => 365,
            ),
        );

        // Update options with defaults
        foreach ($defaults as $option_name => $default_values) {
            update_option($option_name, $default_values);
        }

        wp_send_json_success(__('Settings have been reset to defaults.', 'subs'));
    }

    /**
     * Get settings for a specific tab
     *
     * @param string $tab
     * @return array
     * @since 1.0.0
     */
    public function get_settings($tab = '') {
        if (empty($tab)) {
            $tab = $this->current_tab;
        }

        return get_option('subs_' . $tab . '_settings', array());
    }

    /**
     * Get a specific setting value
     *
     * @param string $tab
     * @param string $key
     * @param mixed $default
     * @return mixed
     * @since 1.0.0
     */
    public function get_setting($tab, $key, $default = '') {
        $settings = $this->get_settings($tab);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}Select the currency for your subscriptions.', 'subs') . '</p>';
    }

    /**
     * Currency position field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function currency_position_field_callback() {
        $settings = get_option('subs_general_settings', array());
        $value = isset($settings['currency_position']) ? $settings['currency_position'] : 'left';

        $positions = array(
            'left' => __('Left ($99.99)', 'subs'),
            'right' => __('Right (99.99$)', 'subs'),
            'left_space' => __('Left with space ($ 99.99)', 'subs'),
            'right_space' => __('Right with space (99.99 $)', 'subs'),
        );

        echo '<select name="subs_general_settings[currency_position]" id="currency_position">';
        foreach ($positions as $code => $name) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($code),
                selected($value, $code, false),
                esc_html($name)
            );
        }
        echo '</select>';
        echo '<p class="description">' . __('Choose where to display the currency symbol.', 'subs') . '</p>';
    }

    /**
     * Decimal separator field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function decimal_separator_field_callback() {
        $settings = get_option('subs_general_settings', array());
        $value = isset($settings['decimal_separator']) ? $settings['decimal_separator'] : '.';

        printf(
            '<input type="text" name="subs_general_settings[decimal_separator]" id="decimal_separator" value="%s" class="small-text" maxlength="1" />',
            esc_attr($value)
        );
        echo '<p class="description">' . __('Character to use as decimal separator.', 'subs') . '</p>';
    }

    /**
     * Thousand separator field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function thousand_separator_field_callback() {
        $settings = get_option('subs_general_settings', array());
        $value = isset($settings['thousand_separator']) ? $settings['thousand_separator'] : ',';

        printf(
            '<input type="text" name="subs_general_settings[thousand_separator]" id="thousand_separator" value="%s" class="small-text" maxlength="1" />',
            esc_attr($value)
        );
        echo '<p class="description">' . __('Character to use as thousand separator.', 'subs') . '</p>';
    }

    /**
     * Number of decimals field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function number_of_decimals_field_callback() {
        $settings = get_option('subs_general_settings', array());
        $value = isset($settings['number_of_decimals']) ? $settings['number_of_decimals'] : 2;

        printf(
            '<input type="number" name="subs_general_settings[number_of_decimals]" id="number_of_decimals" value="%s" min="0" max="4" class="small-text" />',
            esc_attr($value)
        );
        echo '<p class="description">' . __('Number of decimal places to display for prices.', 'subs') . '</p>';
    }

    /**
     * Stripe enabled field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function stripe_enabled_field_callback() {
        $settings = get_option('subs_stripe_settings', array());
        $value = isset($settings['enabled']) ? $settings['enabled'] : 'no';

        printf(
            '<input type="checkbox" name="subs_stripe_settings[enabled]" id="stripe_enabled" value="yes"%s />',
            checked($value, 'yes', false)
        );
        echo '<label for="stripe_enabled">' . __('Enable Stripe payment processing', 'subs') . '</label>';
        echo '<p class="description">' . __('Check this to enable Stripe payments for subscriptions.', 'subs') . '</p>';
    }

    /**
     * Stripe test mode field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function stripe_test_mode_field_callback() {
        $settings = get_option('subs_stripe_settings', array());
        $value = isset($settings['test_mode']) ? $settings['test_mode'] : 'yes';

        printf(
            '<input type="checkbox" name="subs_stripe_settings[test_mode]" id="stripe_test_mode" value="yes"%s />',
            checked($value, 'yes', false)
        );
        echo '<label for="stripe_test_mode">' . __('Enable test mode', 'subs') . '</label>';
        echo '<p class="description">' . __('Use Stripe test mode for development and testing.', 'subs') . '</p>';
    }

    /**
     * Stripe live keys field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function stripe_live_keys_field_callback() {
        $settings = get_option('subs_stripe_settings', array());
        $publishable_key = isset($settings['publishable_key']) ? $settings['publishable_key'] : '';
        $secret_key = isset($settings['secret_key']) ? $settings['secret_key'] : '';

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Publishable Key', 'subs') . '</th>';
        echo '<td>';
        printf(
            '<input type="text" name="subs_stripe_settings[publishable_key]" id="stripe_publishable_key" value="%s" class="regular-text" placeholder="pk_live_..." />',
            esc_attr($publishable_key)
        );
        echo '<p class="description">' . __('Your live publishable key from Stripe.', 'subs') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">' . __('Secret Key', 'subs') . '</th>';
        echo '<td>';
        printf(
            '<input type="password" name="subs_stripe_settings[secret_key]" id="stripe_secret_key" value="%s" class="regular-text" placeholder="sk_live_..." />',
            esc_attr($secret_key)
        );
        echo '<p class="description">' . __('Your live secret key from Stripe.', 'subs') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    /**
     * Stripe test keys field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function stripe_test_keys_field_callback() {
        $settings = get_option('subs_stripe_settings', array());
        $test_publishable_key = isset($settings['test_publishable_key']) ? $settings['test_publishable_key'] : '';
        $test_secret_key = isset($settings['test_secret_key']) ? $settings['test_secret_key'] : '';

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Test Publishable Key', 'subs') . '</th>';
        echo '<td>';
        printf(
            '<input type="text" name="subs_stripe_settings[test_publishable_key]" id="stripe_test_publishable_key" value="%s" class="regular-text" placeholder="pk_test_..." />',
            esc_attr($test_publishable_key)
        );
        echo '<p class="description">' . __('Your test publishable key from Stripe.', 'subs') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">' . __('Test Secret Key', 'subs') . '</th>';
        echo '<td>';
        printf(
            '<input type="password" name="subs_stripe_settings[test_secret_key]" id="stripe_test_secret_key" value="%s" class="regular-text" placeholder="sk_test_..." />',
            esc_attr($test_secret_key)
        );
        echo '<p class="description">' . __('Your test secret key from Stripe.', 'subs') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        // Add test connection button
        echo '<p>';
        echo '<button type="button" id="test-stripe-connection" class="button button-secondary">' . __('Test Connection', 'subs') . '</button>';
        echo ' <span id="stripe-test-result"></span>';
        echo '</p>';
    }

    /**
     * Stripe webhook field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function stripe_webhook_field_callback() {
        $settings = get_option('subs_stripe_settings', array());
        $webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';

        $webhook_url = home_url('/?subs_stripe_webhook=1');

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Webhook URL', 'subs') . '</th>';
        echo '<td>';
        printf('<input type="text" value="%s" class="regular-text" readonly />', esc_attr($webhook_url));
        echo '<p class="description">' . __('Add this URL to your Stripe webhook endpoints.', 'subs') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">' . __('Webhook Secret', 'subs') . '</th>';
        echo '<td>';
        printf(
            '<input type="password" name="subs_stripe_settings[webhook_secret]" id="stripe_webhook_secret" value="%s" class="regular-text" placeholder="whsec_..." />',
            esc_attr($webhook_secret)
        );
        echo '<p class="description">' . __('The webhook signing secret from Stripe.', 'subs') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    /**
     * Email from name field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function email_from_name_field_callback() {
        $settings = get_option('subs_email_settings', array());
        $value = isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');

        printf(
            '<input type="text" name="subs_email_settings[from_name]" id="email_from_name" value="%s" class="regular-text" />',
            esc_attr($value)
        );
        echo '<p class="description">' . __('The "From" name for subscription emails.', 'subs') . '</p>';
    }

    /**
     * Email from email field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function email_from_email_field_callback() {
        $settings = get_option('subs_email_settings', array());
        $value = isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');

        printf(
            '<input type="email" name="subs_email_settings[from_email]" id="email_from_email" value="%s" class="regular-text" />',
            esc_attr($value)
        );
        echo '<p class="description">' . __('The "From" email address for subscription emails.', 'subs') . '</p>';
    }

    /**
     * Email notifications field callback
     *
     * @access public
     * @since 1.0.0
     */
    public function email_notifications_field_callback() {
        $settings = get_option('subs_email_settings', array());

        $notifications = array(
            'subscription_created_enabled' => __('Subscription Created', 'subs'),
            'subscription_cancelled_enabled' => __('Subscription Cancelled', 'subs'),
            'payment_failed_enabled' => __('Payment Failed', 'subs'),
            'payment_succeeded_enabled' => __('Payment Succeeded', 'subs'),
        );

        echo '<fieldset>';
        foreach ($notifications as $key => $label) {
            $value = isset($settings[$key]) ? $settings[$key] : 'yes';
            printf(
                '<label><input type="checkbox" name="subs_email_settings[%s]" value="yes"%s /> %s</label><br />',
                esc_attr($key),
                checked($value, 'yes', false),
                esc_html($label)
            );
        }
        echo '</fieldset>';
        echo '<p class="description">' . __('
