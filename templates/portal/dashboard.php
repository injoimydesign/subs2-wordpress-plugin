<?php
/**
 * Customer Portal Dashboard Template
 *
 * This template displays the main customer portal dashboard.
 * Can be overridden by copying to yourtheme/subs/portal/dashboard.php
 *
 * @package Subs
 * @subpackage Templates/Portal
 * @version 1.0.0
 *
 * @var object $customer Current customer object
 * @var array $subscriptions Active subscriptions
 * @var array $stats Customer statistics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure customer is logged in
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('Please log in to access your portal.', 'subs') . '</p>';
    return;
}

$customer = isset($customer) ? $customer : null;
$subscriptions = isset($subscriptions) ? $subscriptions : array();
$stats = isset($stats) ? $stats : array(
    'active_subscriptions' => 0,
    'total_spent' => 0,
    'next_payment' => null,
);
?>

<div class="subs-portal-wrapper">
    <div class="subs-portal-container">

        <!-- Portal Header -->
        <div class="subs-portal-header">
            <div class="subs-portal-welcome">
                <h1><?php printf(esc_html__('Welcome back, %s!', 'subs'), esc_html($customer->first_name ?? 'Customer')); ?></h1>
                <p class="subs-portal-subtitle"><?php esc_html_e('Manage your subscriptions and account settings', 'subs'); ?></p>
            </div>

            <div class="subs-portal-actions">
                <a href="<?php echo esc_url(add_query_arg('action', 'account', get_permalink())); ?>" class="subs-btn subs-btn-secondary">
                    <span class="subs-btn-icon">⚙️</span>
                    <?php esc_html_e('Account Settings', 'subs'); ?>
                </a>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="subs-btn subs-btn-outline">
                    <?php esc_html_e('Logout', 'subs'); ?>
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="subs-dashboard-stats">
            <div class="subs-stat-card">
                <div class="subs-stat-icon subs-stat-primary">
                    <span>📦</span>
                </div>
                <div class="subs-stat-content">
                    <h3 class="subs-stat-value"><?php echo esc_html($stats['active_subscriptions']); ?></h3>
                    <p class="subs-stat-label"><?php esc_html_e('Active Subscriptions', 'subs'); ?></p>
                </div>
            </div>

            <div class="subs-stat-card">
                <div class="subs-stat-icon subs-stat-success">
                    <span>💰</span>
                </div>
                <div class="subs-stat-content">
                    <h3 class="subs-stat-value">$<?php echo esc_html(number_format($stats['total_spent'], 2)); ?></h3>
                    <p class="subs-stat-label"><?php esc_html_e('Total Spent', 'subs'); ?></p>
                </div>
            </div>

            <div class="subs-stat-card">
                <div class="subs-stat-icon subs-stat-info">
                    <span>📅</span>
                </div>
                <div class="subs-stat-content">
                    <h3 class="subs-stat-value">
                        <?php
                        if ($stats['next_payment']) {
                            echo esc_html(date_i18n('M j', strtotime($stats['next_payment'])));
                        } else {
                            esc_html_e('N/A', 'subs');
                        }
                        ?>
                    </h3>
                    <p class="subs-stat-label"><?php esc_html_e('Next Payment', 'subs'); ?></p>
                </div>
            </div>

            <div class="subs-stat-card">
                <div class="subs-stat-icon subs-stat-warning">
                    <span>⭐</span>
                </div>
                <div class="subs-stat-content">
                    <h3 class="subs-stat-value">
                        <?php
                        $member_since = $customer->created_at ?? current_time('mysql');
                        $months = floor((strtotime(current_time('mysql')) - strtotime($member_since)) / (30 * 24 * 60 * 60));
                        echo esc_html($months);
                        ?>
                    </h3>
                    <p class="subs-stat-label"><?php esc_html_e('Months Member', 'subs'); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="subs-quick-actions">
            <h2 class="subs-section-title"><?php esc_html_e('Quick Actions', 'subs'); ?></h2>

            <div class="subs-action-cards">
                <a href="<?php echo esc_url(add_query_arg('action', 'subscriptions', get_permalink())); ?>" class="subs-action-card">
                    <div class="subs-action-icon">📋</div>
                    <h3><?php esc_html_e('View Subscriptions', 'subs'); ?></h3>
                    <p><?php esc_html_e('Manage all your active subscriptions', 'subs'); ?></p>
                </a>

                <a href="<?php echo esc_url(add_query_arg('action', 'payment-methods', get_permalink())); ?>" class="subs-action-card">
                    <div class="subs-action-icon">💳</div>
                    <h3><?php esc_html_e('Payment Methods', 'subs'); ?></h3>
                    <p><?php esc_html_e('Update your payment information', 'subs'); ?></p>
                </a>

                <a href="<?php echo esc_url(add_query_arg('action', 'billing-history', get_permalink())); ?>" class="subs-action-card">
                    <div class="subs-action-icon">📊</div>
                    <h3><?php esc_html_e('Billing History', 'subs'); ?></h3>
                    <p><?php esc_html_e('View past payments and invoices', 'subs'); ?></p>
                </a>

                <a href="<?php echo esc_url(add_query_arg('action', 'addresses', get_permalink())); ?>" class="subs-action-card">
                    <div class="subs-action-icon">📍</div>
                    <h3><?php esc_html_e('Shipping Address', 'subs'); ?></h3>
                    <p><?php esc_html_e('Manage delivery addresses', 'subs'); ?></p>
                </a>
            </div>
        </div>

        <!-- Active Subscriptions Overview -->
        <?php if (!empty($subscriptions)) : ?>
        <div class="subs-subscriptions-overview">
            <div class="subs-section-header">
                <h2 class="subs-section-title"><?php esc_html_e('Your Subscriptions', 'subs'); ?></h2>
                <a href="<?php echo esc_url(add_query_arg('action', 'subscriptions', get_permalink())); ?>" class="subs-view-all-link">
                    <?php esc_html_e('View All', 'subs'); ?> →
                </a>
            </div>

            <div class="subs-subscription-cards">
                <?php foreach (array_slice($subscriptions, 0, 3) as $subscription) : ?>
                <div class="subs-subscription-card">
                    <div class="subs-subscription-header">
                        <h3 class="subs-subscription-name"><?php echo esc_html($subscription->product_name); ?></h3>
                        <span class="subs-subscription-status subs-status-<?php echo esc_attr(strtolower($subscription->status)); ?>">
                            <?php echo esc_html(ucfirst($subscription->status)); ?>
                        </span>
                    </div>

                    <div class="subs-subscription-details">
                        <div class="subs-detail-row">
                            <span class="subs-detail-label"><?php esc_html_e('Amount:', 'subs'); ?></span>
                            <span class="subs-detail-value">
                                $<?php echo esc_html(number_format($subscription->amount, 2)); ?> /
                                <?php echo esc_html($subscription->billing_period); ?>
                            </span>
                        </div>

                        <div class="subs-detail-row">
                            <span class="subs-detail-label"><?php esc_html_e('Next Payment:', 'subs'); ?></span>
                            <span class="subs-detail-value">
                                <?php
                                echo $subscription->next_payment_date
                                    ? esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date)))
                                    : esc_html__('N/A', 'subs');
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="subs-subscription-actions">
                        <a href="<?php echo esc_url(add_query_arg(array('action' => 'view-subscription', 'id' => $subscription->id), get_permalink())); ?>" class="subs-btn subs-btn-small subs-btn-primary">
                            <?php esc_html_e('Manage', 'subs'); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else : ?>
        <div class="subs-no-subscriptions">
            <div class="subs-empty-state">
                <span class="subs-empty-icon">📦</span>
                <h3><?php esc_html_e('No Active Subscriptions', 'subs'); ?></h3>
                <p><?php esc_html_e('Start your first subscription today and enjoy exclusive benefits!', 'subs'); ?></p>
                <a href="<?php echo esc_url(home_url('/shop')); ?>" class="subs-btn subs-btn-primary">
                    <?php esc_html_e('Browse Products', 'subs'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="subs-recent-activity">
            <h2 class="subs-section-title"><?php esc_html_e('Recent Activity', 'subs'); ?></h2>

            <div class="subs-activity-timeline">
                <?php
                // Get recent activity (this would be populated from database)
                $recent_activities = array(
                    array(
                        'type' => 'payment',
                        'title' => __('Payment Successful', 'subs'),
                        'description' => __('Monthly subscription payment processed', 'subs'),
                        'date' => current_time('mysql'),
                        'icon' => '✓',
                    ),
                    // Add more activities as needed
                );

                if (!empty($recent_activities)) :
                    foreach ($recent_activities as $activity) :
                ?>
                <div class="subs-activity-item">
                    <div class="subs-activity-icon subs-activity-<?php echo esc_attr($activity['type']); ?>">
                        <?php echo esc_html($activity['icon']); ?>
                    </div>
                    <div class="subs-activity-content">
                        <h4><?php echo esc_html($activity['title']); ?></h4>
                        <p><?php echo esc_html($activity['description']); ?></p>
                        <span class="subs-activity-date">
                            <?php echo esc_html(human_time_diff(strtotime($activity['date']), current_time('timestamp')) . ' ago'); ?>
                        </span>
                    </div>
                </div>
                <?php
                    endforeach;
                else :
                ?>
                <p class="subs-no-activity"><?php esc_html_e('No recent activity to display.', 'subs'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help & Support -->
        <div class="subs-help-section">
            <div class="subs-help-card">
                <h3><?php esc_html_e('Need Help?', 'subs'); ?></h3>
                <p><?php esc_html_e('Our support team is here to assist you with any questions or concerns.', 'subs'); ?></p>
                <a href="<?php echo esc_url(home_url('/support')); ?>" class="subs-btn subs-btn-outline">
                    <?php esc_html_e('Contact Support', 'subs'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Portal Dashboard Styles */
.subs-portal-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px;
}

.subs-portal-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 30px;
}

/* Header */
.subs-portal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px solid #e1e1e1;
}

.subs-portal-welcome h1 {
    font-size: 32px;
    margin: 0 0 10px 0;
    color: #333;
}

.subs-portal-subtitle {
    font-size: 16px;
    color: #666;
    margin: 0;
}

.subs-portal-actions {
    display: flex;
    gap: 10px;
}

/* Buttons */
.subs-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.subs-btn-primary {
    background: #007cba;
    color: #ffffff;
}

.subs-btn-primary:hover {
    background: #005a87;
}

.subs-btn-secondary {
    background: #6c757d;
    color: #ffffff;
}

.subs-btn-secondary:hover {
    background: #5a6268;
}

.subs-btn-outline {
    background: transparent;
    color: #333;
    border: 2px solid #e1e1e1;
}

.subs-btn-outline:hover {
    border-color: #007cba;
    color: #007cba;
}

.subs-btn-small {
    padding: 8px 16px;
    font-size: 13px;
}

/* Statistics Cards */
.subs-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.subs-stat-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 25px;
    display: flex;
    gap: 15px;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.subs-stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.subs-stat-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 28px;
}

.subs-stat-primary {
    background: #e7f3f8;
}

.subs-stat-success {
    background: #d4edda;
}

.subs-stat-info {
    background: #d1ecf1;
}

.subs-stat-warning {
    background: #fff3cd;
}

.subs-stat-value {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 5px 0;
    color: #333;
}

.subs-stat-label {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Section Titles */
.subs-section-title {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: #333;
}

.subs-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.subs-view-all-link {
    color: #007cba;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}

.subs-view-all-link:hover {
    text-decoration: underline;
}

/* Quick Actions */
.subs-quick-actions {
    margin-bottom: 40px;
}

.subs-action-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.subs-action-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.subs-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.subs-action-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.subs-action-card h3 {
    font-size: 18px;
    margin: 0 0 10px 0;
    color: #333;
}

.subs-action-card p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Subscriptions Overview */
.subs-subscriptions-overview {
    margin-bottom: 40px;
}

.subs-subscription-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.subs-subscription-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.subs-subscription-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e1e1e1;
}

.subs-subscription-name {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.subs-subscription-status {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.subs-status-active {
    background: #d4edda;
    color: #155724;
}

.subs-status-paused {
    background: #fff3cd;
    color: #856404;
}

.subs-status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.subs-subscription-details {
    margin-bottom: 15px;
}

.subs-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
}

.subs-detail-label {
    color: #666;
}

.subs-detail-value {
    font-weight: 600;
    color: #333;
}

.subs-subscription-actions {
    padding-top: 15px;
    border-top: 1px solid #e1e1e1;
}

/* Empty State */
.subs-no-subscriptions {
    background: #ffffff;
    border-radius: 8px;
    padding: 60px 20px;
    text-align: center;
    margin-bottom: 40px;
}

.subs-empty-icon {
    font-size: 64px;
    display: block;
    margin-bottom: 20px;
}

.subs-empty-state h3 {
    font-size: 24px;
    margin: 0 0 10px 0;
    color: #333;
}

.subs-empty-state p {
    font-size: 16px;
    color: #666;
    margin: 0 0 20px 0;
}

/* Recent Activity */
.subs-recent-activity {
    margin-bottom: 40px;
}

.subs-activity-timeline {
    background: #ffffff;
    border-radius: 8px;
    padding: 25px;
}

.subs-activity-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #e1e1e1;
}

.subs-activity-item:last-child {
    border-bottom: none;
}

.subs-activity-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e7f3f8;
    font-size: 18px;
    flex-shrink: 0;
}

.subs-activity-content h4 {
    font-size: 16px;
    margin: 0 0 5px 0;
    color: #333;
}

.subs-activity-content p {
    font-size: 14px;
    color: #666;
    margin: 0 0 5px 0;
}

.subs-activity-date {
    font-size: 12px;
    color: #999;
}

/* Help Section */
.subs-help-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    padding: 40px;
    text-align: center;
}

.subs-help-card h3 {
    font-size: 24px;
    color: #ffffff;
    margin: 0 0 10px 0;
}

.subs-help-card p {
    font-size: 16px;
    color: rgba(255,255,255,0.9);
    margin: 0 0 20px 0;
}

.subs-help-section .subs-btn-outline {
    background: #ffffff;
    color: #667eea;
    border-color: #ffffff;
}

.subs-help-section .subs-btn-outline:hover {
    background: rgba(255,255,255,0.9);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .subs-dashboard-stats,
    .subs-action-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .subs-subscription-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .subs-portal-header {
        flex-direction: column;
        gap: 20px;
    }

    .subs-dashboard-stats,
    .subs-action-cards,
    .subs-subscription-cards {
        grid-template-columns: 1fr;
    }

    .subs-portal-welcome h1 {
        font-size: 24px;
    }

    .subs-portal-actions {
        width: 100%;
        flex-direction: column;
    }

    .subs-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created customer portal dashboard template
// - Added statistics overview cards
// - Included quick action buttons
// - Displayed active subscriptions preview
// - Added recent activity timeline
// - Included help and support section
// - Responsive design for all devices
// - Future: Add data export functionality
// - Future: Include preference management
// - Future: Add subscription recommendations
?>
