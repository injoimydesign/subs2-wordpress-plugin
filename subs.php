<?php
/**
 * Plugin Name: Subs - Custom Subscription Management System
 * Plugin URI: https://yoursite.com/subs
 * Description: A comprehensive custom subscription plugin with Stripe integration for recurring payments and subscription management. Independent of WooCommerce products.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: subs
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package Subs
 * @version 1.0.0
 * @author Your Name
 * @copyright Copyright (c) 2025, Your Name
 * @license GPL-2.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SUBS_PLUGIN_FILE', __FILE__);
define('SUBS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SUBS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUBS_VERSION', '1.0.0');
define('SUBS_MINIMUM_PHP_VERSION', '7.4');
define('SUBS_DB_VERSION', '1.0.0');

/**
 * Main Subs Plugin Class
 *
 * This is the main plugin class that handles initialization,
 * loading of components, and plugin lifecycle management.
 *
 * @class Subs
 * @version 1.0.0
 * @since 1.0.0
 * @package Subs
 */
final class Subs {

    /**
     * The single instance of the class
     *
     * @var Subs
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Admin instance
     *
     * @var Subs_Admin
     * @since 1.0.0
     */
    public $admin;

    /**
     * Frontend instance
     *
     * @var Subs_Frontend
     * @since 1.0.0
     */
    public $frontend;

    /**
     * AJAX instance
     *
     * @var Subs_Ajax
     * @since 1.0.0
     */
    public $ajax;

    /**
     * Stripe instance
     *
     * @var Subs_Stripe
     * @since 1.0.0
     */
    public $stripe;

    /**
     * Customer instance
     *
     * @var Subs_Customer
     * @since 1.0.0
     */
    public $customer;

    /**
     * Emails instance
     *
     * @var Subs_Emails
     * @since 1.0.0
     */
    public $emails;

    /**
     * Subscription instance
     *
     * @var Subs_Subscription
     * @since 1.0.0
     */
    public $subscription;

    /**
     * Main Subs Instance
     *
     * Ensures only one instance of Subs is loaded or can be loaded.
     * Implements singleton pattern for global access.
     *
     * @static
     * @return Subs - Main instance
     * @since 1.0.0
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Subs Constructor
     *
     * Initialize the plugin by setting up hooks, loading dependencies,
     * and initializing core components.
     *
     * @access public
     * @since 1.0.0
     */
    public function __construct() {
        // Hook into plugins_loaded to ensure WordPress is fully loaded
        add_action('plugins_loaded', array($this, 'init'), 0);

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Handle plugin updates
        add_action('upgrader_process_complete', array($this, 'upgrade_completed'), 10, 2);
    }

    /**
     * Initialize the plugin
     *
     * This method is called on plugins_loaded hook to ensure
     * WordPress and other plugins are fully loaded.
     *
     * @access public
     * @since 1.0.0
     */
    public function init() {
        // Check if WordPress meets minimum requirements
        if (!$this->check_environment()) {
            return;
        }

        // Load plugin textdomain for translations
        $this->load_plugin_textdomain();

        // Define additional constants
        $this->define_constants();

        // Include required files
        $this->includes();

        // Initialize hooks
        $this->init_hooks();

        // Initialize components
        $this->init_components();

        // Trigger action for other plugins to hook into
        do_action('subs_init');
    }

    /**
     * Check if environment meets plugin requirements
     *
     * @access private
     * @return bool
     * @since 1.0.0
     */
    private function check_environment() {
        // Check PHP version
        if (version_compare(PHP_VERSION, SUBS_MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }

        // Check if WordPress meets minimum version (handled by plugin header)
        // Additional environment checks can be added here

        return true;
    }

    /**
     * Display PHP version notice
     *
     * @access public
     * @since 1.0.0
     */
    public function php_version_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php printf(__('Subs requires PHP %s or higher. You are running version %s.', 'subs'), SUBS_MINIMUM_PHP_VERSION, PHP_VERSION); ?></p>
        </div>
        <?php
    }

    /**
     * Load plugin textdomain for translations
     *
     * @access public
     * @since 1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('subs', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Define additional plugin constants
     *
     * @access private
     * @since 1.0.0
     */
    private function define_constants() {
        // Define absolute path
        if (!defined('SUBS_ABSPATH')) {
            define('SUBS_ABSPATH', dirname(SUBS_PLUGIN_FILE) . '/');
        }

        // Define template path
        if (!defined('SUBS_TEMPLATE_PATH')) {
            define('SUBS_TEMPLATE_PATH', SUBS_ABSPATH . 'templates/');
        }

        // Define assets URL
        if (!defined('SUBS_ASSETS_URL')) {
            define('SUBS_ASSETS_URL', SUBS_PLUGIN_URL . 'assets/');
        }
    }

    /**
     * Include required core files
     *
     * Load all necessary class files and dependencies.
     * Files are loaded conditionally based on admin/frontend context.
     *
     * @access public
     * @since 1.0.0
     */
    public function includes() {
        // Core includes - loaded in all contexts
        $core_files = array(
            'includes/class-subs-install.php',
            'includes/class-subs-subscription.php',
            'includes/class-subs-customer.php',
            'includes/class-subs-emails.php',
            'includes/class-subs-stripe.php',
            'includes/functions-subs.php', // Helper functions
        );

        foreach ($core_files as $file) {
            $file_path = SUBS_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                include_once $file_path;
            } else {
                // Log missing files for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Subs Plugin: Missing core file - {$file}");
                }
            }
        }

        // Admin includes - loaded only in admin context
        if (is_admin()) {
            $admin_files = array(
                'includes/class-subs-admin.php',
                'includes/admin/class-subs-admin-settings.php',
                'includes/admin/class-subs-admin-subscriptions.php',
                'includes/admin/class-subs-admin-customers.php',
                'includes/admin/class-subs-admin-dashboard.php',
            );

            foreach ($admin_files as $file) {
                $file_path = SUBS_PLUGIN_PATH . $file;
                if (file_exists($file_path)) {
                    include_once $file_path;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Subs Plugin: Missing admin file - {$file}");
                    }
                }
            }
        }

        // Frontend includes - loaded in frontend or AJAX context
        if (!is_admin() || defined('DOING_AJAX')) {
            $frontend_files = array(
                'includes/class-subs-frontend.php',
                'includes/class-subs-ajax.php',
                'includes/frontend/class-subs-frontend-subscription.php',
                'includes/frontend/class-subs-frontend-customer.php',
                'includes/frontend/class-subs-shortcodes.php',
            );

            foreach ($frontend_files as $file) {
                $file_path = SUBS_PLUGIN_PATH . $file;
                if (file_exists($file_path)) {
                    include_once $file_path;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Subs Plugin: Missing frontend file - {$file}");
                    }
                }
            }
        }
    }

    /**
     * Initialize hooks
     *
     * Set up WordPress hooks for plugin functionality.
     *
     * @access public
     * @since 1.0.0
     */
    public function init_hooks() {
        // WordPress initialization hooks
        add_action('init', array($this, 'init_session'), 1);
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'add_rewrite_rules'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // AJAX hooks for non-logged in users
        add_action('wp_ajax_nopriv_subs_process_subscription', array($this, 'handle_ajax_subscription'));
        add_action('wp_ajax_subs_process_subscription', array($this, 'handle_ajax_subscription'));

        // User login/logout hooks
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'on_user_logout'));

        // Cron hooks for subscription processing
        add_action('subs_process_renewals', array($this, 'process_subscription_renewals'));
        add_action('subs_cleanup_expired', array($this, 'cleanup_expired_subscriptions'));
    }

    /**
     * Initialize plugin components
     *
     * Create instances of main plugin classes.
     *
     * @access public
     * @since 1.0.0
     */
    public function init_components() {
        // Initialize core components
        $this->subscription = new Subs_Subscription();
        $this->customer = new Subs_Customer();
        $this->emails = new Subs_Emails();
        $this->stripe = new Subs_Stripe();

        // Initialize admin components
        if (is_admin()) {
            $this->admin = new Subs_Admin();
        }

        // Initialize frontend components
        if (!is_admin() || defined('DOING_AJAX')) {
            $this->frontend = new Subs_Frontend();
            $this->ajax = new Subs_Ajax();
        }

        // Trigger action for component initialization
        do_action('subs_components_loaded', $this);
    }

    /**
     * Plugin activation hook
     *
     * Handles tasks that need to run when plugin is activated.
     *
     * @access public
     * @since 1.0.0
     */
    public function activate() {
        // Include installation class
        if (!class_exists('Subs_Install')) {
            include_once SUBS_PLUGIN_PATH . 'includes/class-subs-install.php';
        }

        // Run installation
        Subs_Install::install();

        // Schedule cron jobs
        $this->schedule_cron_jobs();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('subs_activated', time());

        // Trigger activation hook
        do_action('subs_activated');
    }

    /**
     * Plugin deactivation hook
     *
     * Handles cleanup when plugin is deactivated.
     *
     * @access public
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        $this->clear_cron_jobs();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set deactivation flag
        update_option('subs_deactivated', time());

        // Trigger deactivation hook
        do_action('subs_deactivated');
    }

    /**
     * Schedule cron jobs
     *
     * @access private
     * @since 1.0.0
     */
    private function schedule_cron_jobs() {
        // Schedule renewal processing (daily)
        if (!wp_next_scheduled('subs_process_renewals')) {
            wp_schedule_event(time(), 'daily', 'subs_process_renewals');
        }

        // Schedule cleanup of expired subscriptions (weekly)
        if (!wp_next_scheduled('subs_cleanup_expired')) {
            wp_schedule_event(time(), 'weekly', 'subs_cleanup_expired');
        }
    }

    /**
     * Clear cron jobs
     *
     * @access private
     * @since 1.0.0
     */
    private function clear_cron_jobs() {
        wp_clear_scheduled_hook('subs_process_renewals');
        wp_clear_scheduled_hook('subs_cleanup_expired');
    }

    /**
     * Initialize session
     *
     * Start session for frontend functionality.
     *
     * @access public
     * @since 1.0.0
     */
    public function init_session() {
        if (!session_id() && !is_admin()) {
            session_start();
        }
    }

    /**
     * Register custom post types
     *
     * Register subscription and customer post types.
     *
     * @access public
     * @since 1.0.0
     */
    public function register_post_types() {
        // Register subscription post type
        register_post_type('subs_subscription', array(
            'labels' => array(
                'name' => __('Subscriptions', 'subs'),
                'singular_name' => __('Subscription', 'subs'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Will be added to custom menu
            'supports' => array('title'),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_subs_subscriptions',
                'edit_posts' => 'manage_subs_subscriptions',
                'edit_others_posts' => 'manage_subs_subscriptions',
                'publish_posts' => 'manage_subs_subscriptions',
                'read_private_posts' => 'manage_subs_subscriptions',
                'delete_posts' => 'manage_subs_subscriptions',
            ),
        ));

        // TODO: Add more post types as needed (subscription plans, etc.)
    }

    /**
     * Add custom rewrite rules
     *
     * @access public
     * @since 1.0.0
     */
    public function add_rewrite_rules() {
        // Add custom endpoints for subscription management
        add_rewrite_endpoint('subscription-management', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('subscription-history', EP_ROOT | EP_PAGES);

        // TODO: Add more endpoints as needed
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @access public
     * @since 1.0.0
     */
    public function frontend_scripts() {
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

        // Localize script for AJAX
        wp_localize_script('subs-frontend', 'subs_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('subs_ajax_nonce'),
            'messages' => array(
                'processing' => __('Processing...', 'subs'),
                'error' => __('An error occurred. Please try again.', 'subs'),
            ),
        ));

        // Stripe JavaScript (conditional loading)
        $stripe_settings = get_option('subs_stripe_settings', array());
        if (!empty($stripe_settings['enabled']) && !empty($stripe_settings['publishable_key'])) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        }
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @access public
     * @since 1.0.0
     */
    public function admin_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'subs') === false) {
            return;
        }

        // Admin stylesheet
        wp_enqueue_style(
            'subs-admin',
            SUBS_ASSETS_URL . 'css/admin.css',
            array(),
            SUBS_VERSION
        );

        // Admin JavaScript
        wp_enqueue_script(
            'subs-admin',
            SUBS_ASSETS_URL . 'js/admin.js',
            array('jquery'),
            SUBS_VERSION,
            true
        );

        // Localize admin script
        wp_localize_script('subs-admin', 'subs_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('subs_admin_nonce'),
        ));
    }

    /**
     * Handle AJAX subscription processing
     *
     * @access public
     * @since 1.0.0
     */
    public function handle_ajax_subscription() {
        // Verify nonce
        check_ajax_referer('subs_ajax_nonce', 'nonce');

        // Delegate to AJAX class
        if ($this->ajax) {
            $this->ajax->process_subscription();
        }

        wp_die();
    }

    /**
     * Process subscription renewals (cron job)
     *
     * @access public
     * @since 1.0.0
     */
    public function process_subscription_renewals() {
        if ($this->subscription) {
            $this->subscription->process_renewals();
        }
    }

    /**
     * Cleanup expired subscriptions (cron job)
     *
     * @access public
     * @since 1.0.0
     */
    public function cleanup_expired_subscriptions() {
        if ($this->subscription) {
            $this->subscription->cleanup_expired();
        }
    }

    /**
     * Handle user login
     *
     * @access public
     * @param string $user_login
     * @param WP_User $user
     * @since 1.0.0
     */
    public function on_user_login($user_login, $user) {
        // Update customer last login
        if ($this->customer) {
            $this->customer->update_last_login($user->ID);
        }
    }

    /**
     * Handle user logout
     *
     * @access public
     * @since 1.0.0
     */
    public function on_user_logout() {
        // Clear session data
        if (session_id()) {
            session_destroy();
        }
    }

    /**
     * Handle plugin upgrades
     *
     * @access public
     * @param WP_Upgrader $upgrader
     * @param array $hook_extra
     * @since 1.0.0
     */
    public function upgrade_completed($upgrader, $hook_extra) {
        if (!isset($hook_extra['plugins']) || !is_array($hook_extra['plugins'])) {
            return;
        }

        foreach ($hook_extra['plugins'] as $plugin) {
            if ($plugin === plugin_basename(__FILE__)) {
                // Run upgrade routines
                if (class_exists('Subs_Install')) {
                    Subs_Install::upgrade();
                }
                break;
            }
        }
    }

    /**
     * Get the plugin URL
     *
     * @return string
     * @since 1.0.0
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', SUBS_PLUGIN_FILE));
    }

    /**
     * Get the plugin path
     *
     * @return string
     * @since 1.0.0
     */
    public function plugin_path() {
        return untrailingslashit(plugin_dir_path(SUBS_PLUGIN_FILE));
    }

    /**
     * Get the template path
     *
     * @return string
     * @since 1.0.0
     */
    public function template_path() {
        return apply_filters('subs_template_path', 'subs/');
    }

    /**
     * Get plugin version
     *
     * @return string
     * @since 1.0.0
     */
    public function get_version() {
        return SUBS_VERSION;
    }

    /**
     * Magic getter for backward compatibility
     *
     * @param string $key
     * @return mixed|null
     * @since 1.0.0
     */
    public function __get($key) {
        if (property_exists($this, $key)) {
            return $this->$key;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Subs Plugin: Attempting to access undefined property: {$key}");
        }

        return null;
    }

    /**
     * Magic isset for backward compatibility
     *
     * @param string $key
     * @return bool
     * @since 1.0.0
     */
    public function __isset($key) {
        return property_exists($this, $key);
    }
}

/**
 * Main instance of Subs
 *
 * Returns the main instance of Subs to prevent the need to use globals.
 * Implements global function for easy access throughout the plugin.
 *
 * @since 1.0.0
 * @return Subs
 */
function SUBS() {
    return Subs::instance();
}

/**
 * Initialize the plugin
 *
 * Start the plugin instance.
 */
SUBS();

// Global for backward compatibility - deprecated but maintained for legacy code
$GLOBALS['subs'] = SUBS();
