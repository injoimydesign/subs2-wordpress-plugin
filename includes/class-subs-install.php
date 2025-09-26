<?php
/**
 * Installation and Database Setup Class
 *
 * Handles plugin installation, database table creation, upgrades,
 * and initial data setup.
 *
 * @package Subs
 * @subpackage Installation
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subs Installation Class
 *
 * @class Subs_Install
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Install {

    /**
     * Database tables structure
     *
     * @var array
     * @since 1.0.0
     */
    private static $db_tables = array(
        'subs_subscriptions',
        'subs_subscription_meta',
        'subs_subscription_history',
        'subs_payment_logs',
        'subs_customers',
        'subs_customer_meta',
    );

    /**
     * Default plugin options
     *
     * @var array
     * @since 1.0.0
     */
    private static $default_options = array(
        'subs_version' => SUBS_VERSION,
        'subs_db_version' => SUBS_DB_VERSION,
        'subs_installation_date' => null,
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
            'pass_stripe_fees' => 'no',
        ),
        'subs_email_settings' => array(
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'subscription_created_enabled' => 'yes',
            'subscription_cancelled_enabled' => 'yes',
            'payment_failed_enabled' => 'yes',
            'payment_succeeded_enabled' => 'yes',
        ),
    );

    /**
     * Install the plugin
     *
     * Creates database tables, sets up default options,
     * and performs initial setup tasks.
     *
     * @static
     * @since 1.0.0
     */
    public static function install() {
        global $wpdb;

        // Check if we're not already installing
        if (get_transient('subs_installing')) {
            return;
        }

        // Set installing flag
        set_transient('subs_installing', 'yes', MINUTE_IN_SECONDS * 10);

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Create user roles and capabilities
        self::create_roles();

        // Schedule initial cron jobs
        self::schedule_cron_jobs();

        // Set installation date
        if (!get_option('subs_installation_date')) {
            update_option('subs_installation_date', current_time('mysql'));
        }

        // Update version numbers
        update_option('subs_version', SUBS_VERSION);
        update_option('subs_db_version', SUBS_DB_VERSION);

        // Clear installing flag
        delete_transient('subs_installing');

        // Trigger installation complete hook
        do_action('subs_installed');

        // Log installation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: Installation completed successfully');
        }
    }

    /**
     * Create database tables
     *
     * Creates all required database tables with proper indexing
     * and foreign key relationships.
     *
     * @static
     * @since 1.0.0
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Main subscriptions table
        $table_subscriptions = $wpdb->prefix . 'subs_subscriptions';
        $sql_subscriptions = "CREATE TABLE $table_subscriptions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            stripe_subscription_id varchar(255) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            product_name varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT '0.00',
            currency varchar(3) NOT NULL DEFAULT 'USD',
            billing_period varchar(20) NOT NULL DEFAULT 'month',
            billing_interval int(11) NOT NULL DEFAULT 1,
            trial_end datetime DEFAULT NULL,
            current_period_start datetime NOT NULL,
            current_period_end datetime NOT NULL,
            next_payment_date datetime DEFAULT NULL,
            cancellation_date datetime DEFAULT NULL,
            cancellation_reason text DEFAULT NULL,
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modified_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY stripe_subscription_id (stripe_subscription_id),
            KEY status (status),
            KEY next_payment_date (next_payment_date),
            KEY created_date (created_date)
        ) $charset_collate;";

        // Subscription metadata table
        $table_subscription_meta = $wpdb->prefix . 'subs_subscription_meta';
        $sql_subscription_meta = "CREATE TABLE $table_subscription_meta (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY (meta_id),
            KEY subscription_id (subscription_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";

        // Subscription history table
        $table_subscription_history = $wpdb->prefix . 'subs_subscription_history';
        $sql_subscription_history = "CREATE TABLE $table_subscription_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) UNSIGNED NOT NULL,
            action varchar(100) NOT NULL,
            note text DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscription_id (subscription_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_date (created_date)
        ) $charset_collate;";

        // Payment logs table
        $table_payment_logs = $wpdb->prefix . 'subs_payment_logs';
        $sql_payment_logs = "CREATE TABLE $table_payment_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) UNSIGNED NOT NULL,
            stripe_payment_intent_id varchar(255) DEFAULT NULL,
            stripe_invoice_id varchar(255) DEFAULT NULL,
            amount decimal(10,2) NOT NULL DEFAULT '0.00',
            currency varchar(3) NOT NULL DEFAULT 'USD',
            status varchar(50) NOT NULL,
            payment_method varchar(100) DEFAULT NULL,
            failure_reason text DEFAULT NULL,
            processed_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscription_id (subscription_id),
            KEY stripe_payment_intent_id (stripe_payment_intent_id),
            KEY stripe_invoice_id (stripe_invoice_id),
            KEY status (status),
            KEY processed_date (processed_date)
        ) $charset_collate;";

        // Customers table
        $table_customers = $wpdb->prefix . 'subs_customers';
        $sql_customers = "CREATE TABLE $table_customers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) UNSIGNED DEFAULT NULL,
            stripe_customer_id varchar(255) DEFAULT NULL,
            email varchar(100) NOT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            phone varchar(20) DEFAULT NULL,
            address_line1 varchar(255) DEFAULT NULL,
            address_line2 varchar(255) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(100) DEFAULT NULL,
            postal_code varchar(20) DEFAULT NULL,
            country varchar(2) DEFAULT NULL,
            flag_delivery_address text DEFAULT NULL,
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modified_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY wp_user_id (wp_user_id),
            KEY stripe_customer_id (stripe_customer_id),
            KEY created_date (created_date)
        ) $charset_collate;";

        // Customer metadata table
        $table_customer_meta = $wpdb->prefix . 'subs_customer_meta';
        $sql_customer_meta = "CREATE TABLE $table_customer_meta (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY (meta_id),
            KEY customer_id (customer_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";

        // Include WordPress database upgrade function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create tables using dbDelta for proper upgrades
        dbDelta($sql_subscriptions);
        dbDelta($sql_subscription_meta);
        dbDelta($sql_subscription_history);
        dbDelta($sql_payment_logs);
        dbDelta($sql_customers);
        dbDelta($sql_customer_meta);

        // Log table creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: Database tables created/updated');
        }
    }

    /**
     * Set default plugin options
     *
     * @static
     * @since 1.0.0
     */
    public static function set_default_options() {
        foreach (self::$default_options as $option_name => $option_value) {
            // Only set if option doesn't exist (preserve existing settings)
            if (false === get_option($option_name, false)) {
                update_option($option_name, $option_value);
            }
        }

        // Log default options set
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: Default options set');
        }
    }

    /**
     * Create user roles and capabilities
     *
     * Creates custom roles for subscription management.
     *
     * @static
     * @since 1.0.0
     */
    public static function create_roles() {
        global $wp_roles;

        if (!class_exists('WP_Roles')) {
            return;
        }

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        // Add capabilities to administrator
        $capabilities = array(
            'manage_subs_subscriptions',
            'view_subs_reports',
            'manage_subs_settings',
            'manage_subs_customers',
        );

        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }

        // Create Subscription Manager role
        add_role('subscription_manager', __('Subscription Manager', 'subs'), array(
            'read' => true,
            'manage_subs_subscriptions' => true,
            'view_subs_reports' => true,
            'manage_subs_customers' => true,
        ));

        // Create Subscription Viewer role
        add_role('subscription_viewer', __('Subscription Viewer', 'subs'), array(
            'read' => true,
            'view_subs_reports' => true,
        ));

        // Log roles creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: User roles and capabilities created');
        }
    }

    /**
     * Schedule cron jobs
     *
     * @static
     * @since 1.0.0
     */
    public static function schedule_cron_jobs() {
        // Schedule daily renewal processing
        if (!wp_next_scheduled('subs_process_renewals')) {
            wp_schedule_event(time(), 'daily', 'subs_process_renewals');
        }

        // Schedule weekly cleanup
        if (!wp_next_scheduled('subs_cleanup_expired')) {
            wp_schedule_event(time(), 'weekly', 'subs_cleanup_expired');
        }

        // Schedule hourly Stripe webhook processing
        if (!wp_next_scheduled('subs_process_stripe_webhooks')) {
            wp_schedule_event(time(), 'hourly', 'subs_process_stripe_webhooks');
        }

        // Log cron jobs scheduled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: Cron jobs scheduled');
        }
    }

    /**
     * Upgrade the plugin
     *
     * Handles plugin upgrades and database migrations.
     *
     * @static
     * @since 1.0.0
     */
    public static function upgrade() {
        $current_version = get_option('subs_version', '0.0.0');
        $current_db_version = get_option('subs_db_version', '0.0.0');

        // Run version-specific upgrades
        if (version_compare($current_version, '1.0.0', '<')) {
            self::upgrade_to_1_0_0();
        }

        // Update database if needed
        if (version_compare($current_db_version, SUBS_DB_VERSION, '<')) {
            self::create_tables(); // This will update existing tables
        }

        // Update version numbers
        update_option('subs_version', SUBS_VERSION);
        update_option('subs_db_version', SUBS_DB_VERSION);

        // Clear any caches
        self::clear_caches();

        // Trigger upgrade complete hook
        do_action('subs_upgraded', $current_version, SUBS_VERSION);

        // Log upgrade
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Subs Plugin: Upgraded from {$current_version} to " . SUBS_VERSION);
        }
    }

    /**
     * Upgrade to version 1.0.0
     *
     * @static
     * @since 1.0.0
     */
    private static function upgrade_to_1_0_0() {
        // Initial release - no upgrade needed
        // This method is here for future upgrades

        // Example upgrade tasks:
        // - Migrate old data structures
        // - Update option names
        // - Convert old meta values

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: Upgraded to version 1.0.0');
        }
    }

    /**
     * Clear caches
     *
     * Clear any plugin-specific caches after upgrade.
     *
     * @static
     * @since 1.0.0
     */
    private static function clear_caches() {
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_subs_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_subs_%'");

        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Log cache clearing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: Caches cleared');
        }
    }

    /**
     * Uninstall the plugin
     *
     * Removes all plugin data, tables, and options.
     * This should only be called when the plugin is being permanently deleted.
     *
     * @static
     * @since 1.0.0
     */
    public static function uninstall() {
        global $wpdb;

        // Check if user has permission to delete plugins
        if (!current_user_can('delete_plugins')) {
            return;
        }

        // Check if uninstall is allowed
        $allow_uninstall = get_option('subs_allow_uninstall', false);
        if (!$allow_uninstall) {
            return; // Preserve data by default
        }

        // Drop database tables
        foreach (self::$db_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }

        // Remove all plugin options
        $options_to_remove = array(
            'subs_version',
            'subs_db_version',
            'subs_installation_date',
            'subs_general_settings',
            'subs_stripe_settings',
            'subs_email_settings',
            'subs_allow_uninstall',
            'subs_activated',
            'subs_deactivated',
        );

        foreach ($options_to_remove as $option) {
            delete_option($option);
        }

        // Remove user roles
        remove_role('subscription_manager');
        remove_role('subscription_viewer');

        // Remove capabilities from administrator
        $capabilities = array(
            'manage_subs_subscriptions',
            'view_subs_reports',
            'manage_subs_settings',
            'manage_subs_customers',
        );

        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap) {
                $admin_role->remove_cap($cap);
            }
        }

        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('subs_process_renewals');
        wp_clear_scheduled_hook('subs_cleanup_expired');
        wp_clear_scheduled_hook('subs_process_stripe_webhooks');

        // Clear all transients and caches
        self::clear_caches();

        // Remove user meta data
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'subs_%'");

        // Trigger uninstall hook
        do_action('subs_uninstalled');

        // Log uninstall
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subs Plugin: Uninstalled and all data removed');
        }
    }

    /**
     * Check if plugin needs database update
     *
     * @static
     * @return bool
     * @since 1.0.0
     */
    public static function needs_database_update() {
        $current_db_version = get_option('subs_db_version', '0.0.0');
        return version_compare($current_db_version, SUBS_DB_VERSION, '<');
    }

    /**
     * Get database table names
     *
     * @static
     * @return array
     * @since 1.0.0
     */
    public static function get_table_names() {
        global $wpdb;
        $tables = array();

        foreach (self::$db_tables as $table) {
            $tables[$table] = $wpdb->prefix . $table;
        }

        return $tables;
    }

    /**
     * Check if all required tables exist
     *
     * @static
     * @return bool
     * @since 1.0.0
     */
    public static function tables_exist() {
        global $wpdb;

        $tables = self::get_table_names();

        foreach ($tables as $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                return false;
            }
        }

        return true;
    }

    /**
     * Repair database tables if needed
     *
     * @static
     * @since 1.0.0
     */
    public static function repair_tables() {
        if (!self::tables_exist()) {
            self::create_tables();

            // Log repair
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subs Plugin: Database tables repaired');
            }
        }
    }

    /**
     * Get installation information
     *
     * @static
     * @return array
     * @since 1.0.0
     */
    public static function get_installation_info() {
        return array(
            'version' => get_option('subs_version', 'Unknown'),
            'db_version' => get_option('subs_db_version', 'Unknown'),
            'installation_date' => get_option('subs_installation_date', 'Unknown'),
            'tables_exist' => self::tables_exist(),
            'needs_update' => self::needs_database_update(),
        );
    }
}
