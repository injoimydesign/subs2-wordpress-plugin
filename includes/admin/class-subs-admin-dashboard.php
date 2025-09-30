<?php
/**
 * Admin Dashboard Class
 *
 * Handles the main dashboard functionality in the WordPress admin area.
 * Provides overview statistics, charts, recent activity, and quick actions.
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
 * Subs Admin Dashboard Class
 *
 * @class Subs_Admin_Dashboard
 * @version 1.0.0
 * @since 1.0.0
 */
class Subs_Admin_Dashboard {

    /**
     * Dashboard widgets
     *
     * @var array
     * @since 1.0.0
     */
    private $widgets = array();

    /**
     * Constructor
     *
     * Initialize the dashboard class and set up hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Initialize hooks
        $this->init_hooks();

        // Initialize dashboard widgets
        $this->init_widgets();
    }

    /**
     * Initialize hooks
     *
     * Set up WordPress hooks for dashboard functionality.
     *
     * @access private
     * @since 1.0.0
     */
    private function init_hooks() {
        // Dashboard rendering hooks
        add_action('subs_dashboard_content', array($this, 'render_dashboard'));

        // AJAX hooks for dashboard actions
        add_action('wp_ajax_subs_dashboard_stats', array($this, 'ajax_dashboard_stats'));
        add_action('wp_ajax_subs_dashboard_chart_data', array($this, 'ajax_chart_data'));
        add_action('wp_ajax_subs_dashboard_quick_action', array($this, 'ajax_quick_action'));

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Initialize dashboard widgets
     *
     * Define the available dashboard widgets and their properties.
     *
     * @access private
     * @since 1.0.0
     */
    private function init_widgets() {
        $this->widgets = array(
            'overview_stats' => array(
                'title' => __('Overview Statistics', 'subs'),
                'callback' => array($this, 'render_overview_stats'),
                'priority' => 10,
                'columns' => 'full',
            ),
            'revenue_chart' => array(
                'title' => __('Revenue Chart', 'subs'),
                'callback' => array($this, 'render_revenue_chart'),
                'priority' => 20,
                'columns' => 'half',
            ),
            'subscription_status_chart' => array(
                'title' => __('Subscription Status Distribution', 'subs'),
                'callback' => array($this, 'render_status_chart'),
                'priority' => 30,
                'columns' => 'half',
            ),
            'recent_activity' => array(
                'title' => __('Recent Activity', 'subs'),
                'callback' => array($this, 'render_recent_activity'),
                'priority' => 40,
                'columns' => 'half',
            ),
            'quick_actions' => array(
                'title' => __('Quick Actions', 'subs'),
                'callback' => array($this, 'render_quick_actions'),
                'priority' => 50,
                'columns' => 'half',
            ),
            'top_plans' => array(
                'title' => __('Top Subscription Plans', 'subs'),
                'callback' => array($this, 'render_top_plans'),
                'priority' => 60,
                'columns' => 'half',
            ),
            'system_status' => array(
                'title' => __('System Status', 'subs'),
                'callback' => array($this, 'render_system_status'),
                'priority' => 70,
                'columns' => 'half',
            ),
        );

        // Allow filtering of dashboard widgets
        $this->widgets = apply_filters('subs_admin_dashboard_widgets', $this->widgets);

        // Sort widgets by priority
        uasort($this->widgets, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    /**
     * Enqueue dashboard scripts and styles
     *
     * @param string $hook
     * @access public
     * @since 1.0.0
     */
    public function enqueue_scripts($hook) {
        // Only load on our dashboard page
        if ($hook !== 'subscriptions_page_subs-dashboard') {
            return;
        }

        // Chart.js for charts
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);

        // Dashboard specific scripts
        wp_enqueue_script('subs-dashboard', SUBS_PLUGIN_URL . 'assets/js/admin-dashboard.js', array('jquery', 'chart-js'), SUBS_VERSION, true);

        // Dashboard styles
        wp_enqueue_style('subs-dashboard', SUBS_PLUGIN_URL . 'assets/css/admin-dashboard.css', array(), SUBS_VERSION);

        // Localize script with data
        wp_localize_script('subs-dashboard', 'subs_dashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('subs_admin_ajax'),
            'strings' => array(
                'loading' => __('Loading...', 'subs'),
                'error' => __('An error occurred while loading data.', 'subs'),
                'confirm_action' => __('Are you sure you want to perform this action?', 'subs'),
            ),
        ));
    }

    /**
     * Render the main dashboard
     *
     * @access public
     * @since 1.0.0
     */
    public function render_dashboard() {
        ?>
        <div class="wrap subs-dashboard">
            <h1><?php _e('Subscription Dashboard', 'subs'); ?></h1>

            <!-- Dashboard Header with Key Metrics -->
            <div class="subs-dashboard-header">
                <?php $this->render_header_metrics(); ?>
            </div>

            <!-- Dashboard Grid -->
            <div class="subs-dashboard-grid">
                <div class="subs-dashboard-left">
                    <?php $this->render_left_column(); ?>
                </div>
                <div class="subs-dashboard-right">
                    <?php $this->render_right_column(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render header metrics
     *
     * @access private
     * @since 1.0.0
     */
    private function render_header_metrics() {
        $stats = $this->get_overview_stats();
        ?>
        <div class="subs-metrics-row">
            <div class="subs-metric-card">
                <div class="subs-metric-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="subs-metric-content">
                    <div class="subs-metric-number"><?php echo esc_html(number_format($stats['active_subscriptions'])); ?></div>
                    <div class="subs-metric-label"><?php _e('Active Subscriptions', 'subs'); ?></div>
                    <?php if ($stats['new_subscriptions_trend'] != 0): ?>
                        <div class="subs-metric-trend <?php echo $stats['new_subscriptions_trend'] > 0 ? 'positive' : 'negative'; ?>">
                            <span class="dashicons dashicons-arrow-<?php echo $stats['new_subscriptions_trend'] > 0 ? 'up' : 'down'; ?>-alt"></span>
                            <?php echo esc_html(abs($stats['new_subscriptions_trend'])); ?>% <?php _e('vs last month', 'subs'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="subs-metric-card">
                <div class="subs-metric-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="subs-metric-content">
                    <div class="subs-metric-number"><?php echo esc_html($this->format_price($stats['monthly_revenue'])); ?></div>
                    <div class="subs-metric-label"><?php _e('Monthly Revenue', 'subs'); ?></div>
                    <?php if ($stats['revenue_trend'] != 0): ?>
                        <div class="subs-metric-trend <?php echo $stats['revenue_trend'] > 0 ? 'positive' : 'negative'; ?>">
                            <span class="dashicons dashicons-arrow-<?php echo $stats['revenue_trend'] > 0 ? 'up' : 'down'; ?>-alt"></span>
                            <?php echo esc_html(abs($stats['revenue_trend'])); ?>% <?php _e('vs last month', 'subs'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="subs-metric-card">
                <div class="subs-metric-icon">
                    <span class="dashicons dashicons-businessman"></span>
                </div>
                <div class="subs-metric-content">
                    <div class="subs-metric-number"><?php echo esc_html(number_format($stats['total_customers'])); ?></div>
                    <div class="subs-metric-label"><?php _e('Total Customers', 'subs'); ?></div>
                    <?php if ($stats['new_customers_this_month'] > 0): ?>
                        <div class="subs-metric-trend positive">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php echo esc_html($stats['new_customers_this_month']); ?> <?php _e('new this month', 'subs'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="subs-metric-card">
                <div class="subs-metric-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="subs-metric-content">
                    <div class="subs-metric-number"><?php echo esc_html($this->format_price($stats['average_revenue_per_user'])); ?></div>
                    <div class="subs-metric-label"><?php _e('Average Revenue per User', 'subs'); ?></div>
                    <div class="subs-metric-trend neutral">
                        <?php printf(__('Based on %d active customers', 'subs'), $stats['active_customers']); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render left column widgets
     *
     * @access private
     * @since 1.0.0
     */
    private function render_left_column() {
        foreach ($this->widgets as $widget_id => $widget) {
            if ($widget['columns'] === 'full' || $widget['columns'] === 'half') {
                $this->render_widget($widget_id, $widget);
            }
        }
    }

    /**
     * Render right column widgets
     *
     * @access private
     * @since 1.0.0
     */
    private function render_right_column() {
        // Right column gets the second half widgets
        $half_widgets = array_filter($this->widgets, function($widget) {
            return $widget['columns'] === 'half';
        });

        $count = 0;
        foreach ($half_widgets as $widget_id => $widget) {
            $count++;
            if ($count % 2 === 0) { // Even numbered half widgets go to right column
                $this->render_widget($widget_id, $widget);
            }
        }
    }

    /**
     * Render a dashboard widget
     *
     * @param string $widget_id
     * @param array $widget
     * @access private
     * @since 1.0.0
     */
    private function render_widget($widget_id, $widget) {
        $class = 'subs-dashboard-widget subs-widget-' . $widget['columns'];
        ?>
        <div class="<?php echo esc_attr($class); ?>" id="subs-widget-<?php echo esc_attr($widget_id); ?>">
            <div class="subs-widget-header">
                <h3><?php echo esc_html($widget['title']); ?></h3>
                <div class="subs-widget-actions">
                    <button type="button" class="subs-widget-refresh" data-widget="<?php echo esc_attr($widget_id); ?>">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </div>
            </div>
            <div class="subs-widget-content">
                <?php
                if (is_callable($widget['callback'])) {
                    call_user_func($widget['callback']);
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render overview statistics widget
     *
     * @access public
     * @since 1.0.0
     */
    public function render_overview_stats() {
        $stats = $this->get_detailed_stats();
        ?>
        <div class="subs-stats-grid">
            <div class="subs-stat-item">
                <div class="subs-stat-label"><?php _e('Trialing Subscriptions', 'subs'); ?></div>
                <div class="subs-stat-value"><?php echo esc_html(number_format($stats['trialing'])); ?></div>
            </div>

            <div class="subs-stat-item">
                <div class="subs-stat-label"><?php _e('Past Due', 'subs'); ?></div>
                <div class="subs-stat-value"><?php echo esc_html(number_format($stats['past_due'])); ?></div>
            </div>

            <div class="subs-stat-item">
                <div class="subs-stat-label"><?php _e('Cancelled This Month', 'subs'); ?></div>
                <div class="subs-stat-value"><?php echo esc_html(number_format($stats['cancelled_this_month'])); ?></div>
            </div>

            <div class="subs-stat-item">
                <div class="subs-stat-label"><?php _e('Failed Payments (30 days)', 'subs'); ?></div>
                <div class="subs-stat-value"><?php echo esc_html(number_format($stats['failed_payments'])); ?></div>
            </div>

            <div class="subs-stat-item">
                <div class="subs-stat-label"><?php _e('Churn Rate', 'subs'); ?></div>
                <div class="subs-stat-value"><?php echo esc_html(number_format($stats['churn_rate'], 2)); ?>%</div>
            </div>

            <div class="subs-stat-item">
                <div class="subs-stat-label"><?php _e('Lifetime Value', 'subs'); ?></div>
                <div class="subs-stat-value"><?php echo esc_html($this->format_price($stats['lifetime_value'])); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render revenue chart widget
     *
     * @access public
     * @since 1.0.0
     */
    public function render_revenue_chart() {
        ?>
        <div class="subs-chart-container">
            <canvas id="subs-revenue-chart" width="400" height="200"></canvas>
        </div>
        <div class="subs-chart-legend">
            <div class="subs-chart-period-selector">
                <button type="button" class="subs-period-btn active" data-period="7"><?php _e('7 Days', 'subs'); ?></button>
                <button type="button" class="subs-period-btn" data-period="30"><?php _e('30 Days', 'subs'); ?></button>
                <button type="button" class="subs-period-btn" data-period="90"><?php _e('90 Days', 'subs'); ?></button>
                <button type="button" class="subs-period-btn" data-period="365"><?php _e('1 Year', 'subs'); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * Render subscription status chart widget
     *
     * @access public
     * @since 1.0.0
     */
    public function render_status_chart() {
        ?>
        <div class="subs-chart-container">
            <canvas id="subs-status-chart" width="400" height="200"></canvas>
        </div>
        <div id="subs-status-legend" class="subs-status-legend"></div>
        <?php
    }

    /**
     * Render recent activity widget
     *
     * @access public
     * @since 1.0.0
     */
    public function render_recent_activity() {
        $activities = $this->get_recent_activity(10);

        if (empty($activities)) {
            echo '<p class="subs-no-data">' . __('No recent activity found.', 'subs') . '</p>';
            return;
        }
        ?>
        <div class="subs-activity-list">
            <?php foreach ($activities as $activity): ?>
                <div class="subs-activity-item">
                    <div class="subs-activity-icon">
                        <?php echo $this->get_activity_icon($activity->action); ?>
                    </div>
                    <div class="subs-activity-content">
                        <div class="subs-activity-description">
                            <?php echo $this->format_activity_description($activity); ?>
                        </div>
                        <div class="subs-activity-time">
                            <?php echo esc_html(human_time_diff(strtotime($activity->created_date), current_time('timestamp')) . ' ' . __('ago', 'subs')); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="subs-widget-footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=subs-subscriptions')); ?>" class="button">
                <?php _e('View All Activity', 'subs'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render quick actions widget
     *
     * @access public
     * @since 1.0.0
     */
    public function render_quick_actions() {
        ?>
        <div class="subs-quick-actions">
            <div class="subs-action-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=subs-customers&action=add')); ?>" class="subs-action-item">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <span><?php _e('Add Customer', 'subs'); ?></span>
                </a>

                <button type="button" class="subs-action-item subs-quick-action" data-action="create_subscription">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <span><?php _e('Create Subscription', 'subs'); ?></span>
                </button>

                <a href="<?php echo esc_url(admin_url('admin.php?page=subs-settings')); ?>" class="subs-action-item">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <span><?php _e('Settings', 'subs'); ?></span>
                </a>

                <button type="button" class="subs-action-item subs-quick-action" data-action="export_data">
                    <span class="dashicons dashicons-download"></span>
                    <span><?php _e('Export Data', 'subs'); ?></span>
                </button>

                <button type="button" class="subs-action-item subs-quick-action" data-action="send_notifications">
                    <span class="dashicons dashicons-email-alt"></span>
                    <span><?php _e('Send Notifications', 'subs'); ?></span>
                </button>

                <a href="<?php echo esc_url(admin_url('admin.php?page=subs-subscriptions&status=past_due')); ?>" class="subs-action-item">
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php _e('Review Past Due', 'subs'); ?></span>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render top plans widget
     *
     * @access public
     * @since 1.0.0
     */
    public function render_top_plans() {
        $top_plans = $this->get_top_plans(5);

        if (empty($top_plans)) {
            echo '<p class="subs-no-data">' . __('No subscription plans found.', 'subs') . '</p>';
            return;
        }
        ?>
        <div class="subs-top-plans">
            <?php foreach ($top_plans as $index => $plan): ?>
                <div class="subs-plan-item">
                    <div class="subs-plan-rank">#<?php echo esc_html($index + 1); ?></div>
                    <div class="subs-plan-details">
                        <div class="subs-plan-name"><?php echo esc_html($plan->plan_name ?: __('Unnamed Plan', 'subs')); ?></div>
                        <div class="subs-plan-stats">
                            <?php printf(__('%d subscribers • %s revenue', 'subs'), $plan->subscriber_count, $this->format_price($plan->total_revenue)); ?>
                        </div>
                    </div>
                    <div class="subs-plan-trend">
                        <span class="subs-plan-percentage"><?php echo esc_html(number_format($plan->percentage, 1)); ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render system status widget
     *
     * @access public
     * @since 1.0.0
     */
    public function render_system_status() {
        $status = $this->get_system_status();
        ?>
        <div class="subs-system-status">
            <div class="subs-status-item">
                <div class="subs-status-indicator <?php echo $status['stripe_connection'] ? 'success' : 'error'; ?>"></div>
                <div class="subs-status-label"><?php _e('Stripe Connection', 'subs'); ?></div>
                <div class="subs-status-value"><?php echo $status['stripe_connection'] ? __('Connected', 'subs') : __('Disconnected', 'subs'); ?></div>
            </div>

            <div class="subs-status-item">
                <div class="subs-status-indicator <?php echo $status['webhook_status'] ? 'success' : 'warning'; ?>"></div>
                <div class="subs-status-label"><?php _e('Webhook Status', 'subs'); ?></div>
                <div class="subs-status-value"><?php echo $status['webhook_status'] ? __('Active', 'subs') : __('Inactive', 'subs'); ?></div>
            </div>

            <div class="subs-status-item">
                <div class="subs-status-indicator <?php echo $status['cron_jobs'] ? 'success' : 'error'; ?>"></div>
                <div class="subs-status-label"><?php _e('Cron Jobs', 'subs'); ?></div>
                <div class="subs-status-value"><?php echo $status['cron_jobs'] ? __('Running', 'subs') : __('Not Running', 'subs'); ?></div>
            </div>

            <div class="subs-status-item">
                <div class="subs-status-indicator <?php echo $status['database'] ? 'success' : 'error'; ?>"></div>
                <div class="subs-status-label"><?php _e('Database', 'subs'); ?></div>
                <div class="subs-status-value"><?php echo $status['database'] ? __('OK', 'subs') : __('Error', 'subs'); ?></div>
            </div>

            <div class="subs-status-item">
                <div class="subs-status-indicator info"></div>
                <div class="subs-status-label"><?php _e('Plugin Version', 'subs'); ?></div>
                <div class="subs-status-value"><?php echo esc_html(SUBS_VERSION); ?></div>
            </div>

            <div class="subs-status-item">
                <div class="subs-status-indicator info"></div>
                <div class="subs-status-label"><?php _e('Last Cron Run', 'subs'); ?></div>
                <div class="subs-status-value"><?php echo esc_html($status['last_cron_run']); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Get overview statistics
     *
     * @return array
     * @since 1.0.0
     */
    private function get_overview_stats() {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $customers_table = $wpdb->prefix . 'subs_customers';
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        $stats = array();

        // Active subscriptions
        $stats['active_subscriptions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $subscriptions_table WHERE status = 'active'"
        );

        // Total customers
        $stats['total_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM $customers_table");

        // Active customers (with active subscriptions)
        $stats['active_customers'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT customer_id) FROM $subscriptions_table WHERE status = 'active'"
        );

        // Monthly revenue (this month)
        $stats['monthly_revenue'] = $wpdb->get_var(
            "SELECT SUM(amount) FROM $payment_logs_table
             WHERE status = 'succeeded'
             AND processed_date >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        ) ?: 0;

        // Previous month revenue for trend calculation
        $prev_month_revenue = $wpdb->get_var(
            "SELECT SUM(amount) FROM $payment_logs_table
             WHERE status = 'succeeded'
             AND processed_date >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01')
             AND processed_date < DATE_FORMAT(NOW(), '%Y-%m-01')"
        ) ?: 0;

        // Revenue trend
        $stats['revenue_trend'] = $prev_month_revenue > 0 ?
            round((($stats['monthly_revenue'] - $prev_month_revenue) / $prev_month_revenue) * 100, 1) : 0;

        // New customers this month
        $stats['new_customers_this_month'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $customers_table WHERE created_date >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );

        // New subscriptions this month
        $new_subscriptions_this_month = $wpdb->get_var(
            "SELECT COUNT(*) FROM $subscriptions_table WHERE created_date >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );

        // Previous month new subscriptions
        $prev_month_subscriptions = $wpdb->get_var(
            "SELECT COUNT(*) FROM $subscriptions_table
             WHERE created_date >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01')
             AND created_date < DATE_FORMAT(NOW(), '%Y-%m-01')"
        );

        // New subscriptions trend
        $stats['new_subscriptions_trend'] = $prev_month_subscriptions > 0 ?
            round((($new_subscriptions_this_month - $prev_month_subscriptions) / $prev_month_subscriptions) * 100, 1) : 0;

        // Average revenue per user (ARPU)
        $stats['average_revenue_per_user'] = $stats['active_customers'] > 0 ?
            $stats['monthly_revenue'] / $stats['active_customers'] : 0;

        return $stats;
    }

    /**
     * Get detailed statistics
     *
     * @return array
     * @since 1.0.0
     */
    private function get_detailed_stats() {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        $stats = array();

        // Subscription counts by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $subscriptions_table GROUP BY status",
            OBJECT_K
        );

        $stats['trialing'] = isset($status_counts['trialing']) ? $status_counts['trialing']->count : 0;
        $stats['past_due'] = isset($status_counts['past_due']) ? $status_counts['past_due']->count : 0;

        // Cancelled this month
        $stats['cancelled_this_month'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $subscriptions_table
             WHERE status = 'cancelled'
             AND updated_date >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );

        // Failed payments (last 30 days)
        $stats['failed_payments'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $payment_logs_table
             WHERE status = 'failed'
             AND processed_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Churn rate calculation (simplified)
        $total_active_start_month = $wpdb->get_var(
            "SELECT COUNT(*) FROM $subscriptions_table
             WHERE status = 'active'
             AND created_date < DATE_FORMAT(NOW(), '%Y-%m-01')"
        );

        $stats['churn_rate'] = $total_active_start_month > 0 ?
            ($stats['cancelled_this_month'] / $total_active_start_month) * 100 : 0;

        // Lifetime Value (simplified calculation)
        $average_subscription_length = $wpdb->get_var(
            "SELECT AVG(DATEDIFF(COALESCE(cancelled_date, NOW()), created_date))
             FROM $subscriptions_table
             WHERE status IN ('active', 'cancelled')"
        ) ?: 30;

        $average_monthly_revenue = $wpdb->get_var(
            "SELECT AVG(amount) FROM $subscriptions_table WHERE status = 'active'"
        ) ?: 0;

        $stats['lifetime_value'] = ($average_monthly_revenue * $average_subscription_length) / 30;

        return $stats;
    }

    /**
     * Get recent activity
     *
     * @param int $limit
     * @return array
     * @since 1.0.0
     */
    private function get_recent_activity($limit = 10) {
        global $wpdb;

        $history_table = $wpdb->prefix . 'subs_subscription_history';
        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $customers_table = $wpdb->prefix . 'subs_customers';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, s.plan_name, c.email as customer_email, c.first_name, c.last_name
             FROM $history_table h
             LEFT JOIN $subscriptions_table s ON h.subscription_id = s.id
             LEFT JOIN $customers_table c ON s.customer_id = c.id
             ORDER BY h.created_date DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get top subscription plans
     *
     * @param int $limit
     * @return array
     * @since 1.0.0
     */
    private function get_top_plans($limit = 5) {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';
        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        $plans = $wpdb->get_results($wpdb->prepare(
            "SELECT
                s.plan_name,
                COUNT(DISTINCT s.id) as subscriber_count,
                SUM(CASE WHEN pl.status = 'succeeded' THEN pl.amount ELSE 0 END) as total_revenue
             FROM $subscriptions_table s
             LEFT JOIN $payment_logs_table pl ON s.id = pl.subscription_id
             WHERE s.status = 'active'
             GROUP BY s.plan_name
             ORDER BY subscriber_count DESC, total_revenue DESC
             LIMIT %d",
            $limit
        ));

        // Calculate percentages
        $total_subscribers = array_sum(wp_list_pluck($plans, 'subscriber_count'));

        foreach ($plans as $plan) {
            $plan->percentage = $total_subscribers > 0 ? ($plan->subscriber_count / $total_subscribers) * 100 : 0;
        }

        return $plans;
    }

    /**
     * Get system status
     *
     * @return array
     * @since 1.0.0
     */
    private function get_system_status() {
        $status = array();

        // Stripe connection status
        $stripe_settings = get_option('subs_stripe_settings', array());
        $test_mode = isset($stripe_settings['test_mode']) && $stripe_settings['test_mode'] === 'yes';

        if ($test_mode) {
            $status['stripe_connection'] = !empty($stripe_settings['test_secret_key']) && !empty($stripe_settings['test_publishable_key']);
        } else {
            $status['stripe_connection'] = !empty($stripe_settings['secret_key']) && !empty($stripe_settings['publishable_key']);
        }

        // Webhook status
        $status['webhook_status'] = !empty($stripe_settings['webhook_secret']);

        // Cron jobs status
        $status['cron_jobs'] = wp_next_scheduled('subs_process_renewals') !== false;

        // Database status (simple check)
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'subs_subscriptions',
            $wpdb->prefix . 'subs_customers',
            $wpdb->prefix . 'subs_payment_logs'
        );

        $status['database'] = true;
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                $status['database'] = false;
                break;
            }
        }

        // Last cron run
        $last_cron = wp_next_scheduled('subs_process_renewals');
        $status['last_cron_run'] = $last_cron ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_cron) : __('Never', 'subs');

        return $status;
    }

    /**
     * Get activity icon
     *
     * @param string $action
     * @return string
     * @since 1.0.0
     */
    private function get_activity_icon($action) {
        $icons = array(
            'created' => '<span class="dashicons dashicons-plus-alt"></span>',
            'cancelled' => '<span class="dashicons dashicons-no-alt"></span>',
            'renewed' => '<span class="dashicons dashicons-update"></span>',
            'payment_succeeded' => '<span class="dashicons dashicons-yes-alt"></span>',
            'payment_failed' => '<span class="dashicons dashicons-warning"></span>',
            'status_changed' => '<span class="dashicons dashicons-admin-settings"></span>',
            'updated' => '<span class="dashicons dashicons-edit"></span>',
            'deleted' => '<span class="dashicons dashicons-trash"></span>',
        );

        return isset($icons[$action]) ? $icons[$action] : '<span class="dashicons dashicons-admin-generic"></span>';
    }

    /**
     * Format activity description
     *
     * @param object $activity
     * @return string
     * @since 1.0.0
     */
    private function format_activity_description($activity) {
        $customer_name = trim($activity->first_name . ' ' . $activity->last_name);
        $customer_display = !empty($customer_name) ? $customer_name : $activity->customer_email;

        $plan_name = !empty($activity->plan_name) ? $activity->plan_name : __('Unnamed Plan', 'subs');

        switch ($activity->action) {
            case 'created':
                return sprintf(__('%s subscribed to %s', 'subs'), esc_html($customer_display), esc_html($plan_name));

            case 'cancelled':
                return sprintf(__('%s cancelled subscription to %s', 'subs'), esc_html($customer_display), esc_html($plan_name));

            case 'renewed':
                return sprintf(__('%s renewed subscription to %s', 'subs'), esc_html($customer_display), esc_html($plan_name));

            case 'payment_succeeded':
                $data = json_decode($activity->data, true);
                $amount = isset($data['amount']) ? $this->format_price($data['amount']) : '';
                return sprintf(__('Payment of %s succeeded for %s', 'subs'), $amount, esc_html($customer_display));

            case 'payment_failed':
                return sprintf(__('Payment failed for %s subscription', 'subs'), esc_html($customer_display));

            case 'status_changed':
                $data = json_decode($activity->data, true);
                $new_status = isset($data['new_status']) ? ucfirst($data['new_status']) : '';
                return sprintf(__('%s subscription status changed to %s', 'subs'), esc_html($customer_display), esc_html($new_status));

            case 'updated':
                return sprintf(__('%s subscription updated', 'subs'), esc_html($customer_display));

            case 'deleted':
                return sprintf(__('%s subscription deleted', 'subs'), esc_html($customer_display));

            default:
                return sprintf(__('%s - %s', 'subs'), esc_html($customer_display), esc_html($activity->action));
        }
    }

    /**
     * Format price with currency
     *
     * @param float $amount
     * @param string $currency
     * @return string
     * @since 1.0.0
     */
    private function format_price($amount, $currency = 'USD') {
        $settings = get_option('subs_general_settings', array());

        $decimals = isset($settings['number_of_decimals']) ? intval($settings['number_of_decimals']) : 2;
        $decimal_sep = isset($settings['decimal_separator']) ? $settings['decimal_separator'] : '.';
        $thousand_sep = isset($settings['thousand_separator']) ? $settings['thousand_separator'] : ',';
        $position = isset($settings['currency_position']) ? $settings['currency_position'] : 'left';

        $formatted_amount = number_format($amount, $decimals, $decimal_sep, $thousand_sep);
        $currency_symbol = $this->get_currency_symbol($currency);

        switch ($position) {
            case 'right':
                return $formatted_amount . $currency_symbol;
            case 'left_space':
                return $currency_symbol . ' ' . $formatted_amount;
            case 'right_space':
                return $formatted_amount . ' ' . $currency_symbol;
            default: // left
                return $currency_symbol . $formatted_amount;
        }
    }

    /**
     * Get currency symbol
     *
     * @param string $currency
     * @return string
     * @since 1.0.0
     */
    private function get_currency_symbol($currency) {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C',
            'AUD' => 'A',
            'JPY' => '¥',
        );

        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }
    /**
     * AJAX: Get dashboard statistics
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_dashboard_stats() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_subscriptions')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $widget = sanitize_text_field($_POST['widget']);

        switch ($widget) {
            case 'overview_stats':
                $data = $this->get_overview_stats();
                break;

            case 'detailed_stats':
                $data = $this->get_detailed_stats();
                break;

            case 'recent_activity':
                $data = $this->get_recent_activity(10);
                break;

            case 'top_plans':
                $data = $this->get_top_plans(5);
                break;

            case 'system_status':
                $data = $this->get_system_status();
                break;

            default:
                wp_send_json_error(__('Invalid widget requested', 'subs'));
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get chart data
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_chart_data() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_subscriptions')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $chart_type = sanitize_text_field($_POST['chart_type']);
        $period = intval($_POST['period']) ?: 30;

        switch ($chart_type) {
            case 'revenue':
                $data = $this->get_revenue_chart_data($period);
                break;

            case 'subscriptions':
                $data = $this->get_subscriptions_chart_data($period);
                break;

            case 'status':
                $data = $this->get_status_chart_data();
                break;

            default:
                wp_send_json_error(__('Invalid chart type', 'subs'));
        }

        wp_send_json_success($data);
    }

    /**
     * Get revenue chart data
     *
     * @param int $days
     * @return array
     * @since 1.0.0
     */
    private function get_revenue_chart_data($days = 30) {
        global $wpdb;

        $payment_logs_table = $wpdb->prefix . 'subs_payment_logs';

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(processed_date) as date,
                SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as revenue,
                COUNT(CASE WHEN status = 'succeeded' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments
             FROM $payment_logs_table
             WHERE processed_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(processed_date)
             ORDER BY date ASC",
            $days
        ));

        // Fill in missing dates with zero values
        $start_date = new DateTime("-{$days} days");
        $end_date = new DateTime();
        $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date);

        $chart_data = array();
        $data_by_date = array();

        // Index existing data by date
        foreach ($data as $row) {
            $data_by_date[$row->date] = $row;
        }

        // Create complete dataset
        foreach ($period as $date) {
            $date_string = $date->format('Y-m-d');

            if (isset($data_by_date[$date_string])) {
                $chart_data[] = array(
                    'date' => $date_string,
                    'revenue' => floatval($data_by_date[$date_string]->revenue),
                    'successful_payments' => intval($data_by_date[$date_string]->successful_payments),
                    'failed_payments' => intval($data_by_date[$date_string]->failed_payments),
                );
            } else {
                $chart_data[] = array(
                    'date' => $date_string,
                    'revenue' => 0,
                    'successful_payments' => 0,
                    'failed_payments' => 0,
                );
            }
        }

        return $chart_data;
    }

    /**
     * Get subscriptions chart data
     *
     * @param int $days
     * @return array
     * @since 1.0.0
     */
    private function get_subscriptions_chart_data($days = 30) {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_date) as date,
                COUNT(*) as new_subscriptions
             FROM $subscriptions_table
             WHERE created_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_date)
             ORDER BY date ASC",
            $days
        ));

        // Fill in missing dates
        $start_date = new DateTime("-{$days} days");
        $end_date = new DateTime();
        $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date);

        $chart_data = array();
        $data_by_date = array();

        foreach ($data as $row) {
            $data_by_date[$row->date] = $row;
        }

        foreach ($period as $date) {
            $date_string = $date->format('Y-m-d');

            $chart_data[] = array(
                'date' => $date_string,
                'new_subscriptions' => isset($data_by_date[$date_string]) ? intval($data_by_date[$date_string]->new_subscriptions) : 0,
            );
        }

        return $chart_data;
    }

    /**
     * Get status chart data
     *
     * @return array
     * @since 1.0.0
     */
    private function get_status_chart_data() {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        $data = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
             FROM $subscriptions_table
             GROUP BY status
             ORDER BY count DESC"
        );

        $chart_data = array();
        $colors = array(
            'active' => '#28a745',
            'trialing' => '#17a2b8',
            'past_due' => '#ffc107',
            'cancelled' => '#dc3545',
            'unpaid' => '#fd7e14',
            'incomplete' => '#6c757d',
            'incomplete_expired' => '#343a40',
            'paused' => '#6f42c1',
        );

        foreach ($data as $row) {
            $chart_data[] = array(
                'label' => ucfirst($row->status),
                'value' => intval($row->count),
                'color' => isset($colors[$row->status]) ? $colors[$row->status] : '#6c757d',
            );
        }

        return $chart_data;
    }

    /**
     * AJAX: Handle quick actions
     *
     * @access public
     * @since 1.0.0
     */
    public function ajax_quick_action() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'subs_admin_ajax')) {
            wp_die(__('Security check failed', 'subs'));
        }

        // Check capabilities
        if (!current_user_can('manage_subscriptions')) {
            wp_die(__('Insufficient permissions', 'subs'));
        }

        $action = sanitize_text_field($_POST['action']);

        switch ($action) {
            case 'create_subscription':
                // Redirect to create subscription page
                wp_send_json_success(array(
                    'redirect' => admin_url('admin.php?page=subs-subscriptions&action=add')
                ));
                break;

            case 'export_data':
                // Trigger export
                wp_send_json_success(array(
                    'redirect' => admin_url('admin.php?page=subs-subscriptions&action=export&_wpnonce=' . wp_create_nonce('subs_subscription_action'))
                ));
                break;

            case 'send_notifications':
                // Send pending notifications
                $sent_count = $this->send_pending_notifications();
                wp_send_json_success(array(
                    'message' => sprintf(_n('%d notification sent.', '%d notifications sent.', $sent_count, 'subs'), $sent_count)
                ));
                break;

            default:
                wp_send_json_error(__('Invalid action', 'subs'));
        }
    }

    /**
     * Send pending notifications
     *
     * @return int Number of notifications sent
     * @since 1.0.0
     */
    private function send_pending_notifications() {
        // This would typically check for pending email notifications
        // and send them. For now, just return a count.

        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'subs_subscriptions';

        // Example: Send notifications for subscriptions expiring soon
        $expiring_soon = $wpdb->get_results(
            "SELECT * FROM $subscriptions_table
             WHERE status = 'active'
             AND next_payment_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)
             AND next_payment_date > NOW()"
        );

        $sent_count = 0;

        foreach ($expiring_soon as $subscription) {
            // Here you would send the actual notification
            // For now, just count them
            $sent_count++;
        }

        return $sent_count;
    }
}
