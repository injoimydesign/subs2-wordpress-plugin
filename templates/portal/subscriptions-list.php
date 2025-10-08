<?php
/**
 * Subscriptions List Template
 *
 * This template displays all customer subscriptions.
 * Can be overridden by copying to yourtheme/subs/portal/subscriptions-list.php
 *
 * @package Subs
 * @subpackage Templates/Portal
 * @version 1.0.0
 *
 * @var array $subscriptions All customer subscriptions
 * @var string $filter Current filter (all, active, paused, cancelled)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure customer is logged in
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('Please log in to view your subscriptions.', 'subs') . '</p>';
    return;
}

$subscriptions = isset($subscriptions) ? $subscriptions : array();
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
?>

<div class="subs-subscriptions-list-wrapper">
    <div class="subs-subscriptions-container">

        <!-- Page Header -->
        <div class="subs-page-header">
            <div>
                <h1 class="subs-page-title"><?php esc_html_e('My Subscriptions', 'subs'); ?></h1>
                <p class="subs-page-description"><?php esc_html_e('Manage all your subscriptions in one place', 'subs'); ?></p>
            </div>

            <a href="<?php echo esc_url(home_url('/shop')); ?>" class="subs-btn subs-btn-primary">
                <span>+</span>
                <?php esc_html_e('New Subscription', 'subs'); ?>
            </a>
        </div>

        <!-- Filters -->
        <div class="subs-filters-bar">
            <div class="subs-filter-tabs">
                <a href="<?php echo esc_url(remove_query_arg('filter')); ?>"
                   class="subs-filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <?php esc_html_e('All', 'subs'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('filter', 'active')); ?>"
                   class="subs-filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                    <?php esc_html_e('Active', 'subs'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('filter', 'paused')); ?>"
                   class="subs-filter-tab <?php echo $filter === 'paused' ? 'active' : ''; ?>">
                    <?php esc_html_e('Paused', 'subs'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('filter', 'cancelled')); ?>"
                   class="subs-filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                    <?php esc_html_e('Cancelled', 'subs'); ?>
                </a>
            </div>

            <div class="subs-filter-search">
                <input type="text" id="subs-search-subscriptions" placeholder="<?php esc_attr_e('Search subscriptions...', 'subs'); ?>">
            </div>
        </div>

        <!-- Subscriptions List -->
        <?php if (!empty($subscriptions)) : ?>
        <div class="subs-subscriptions-grid">
            <?php foreach ($subscriptions as $subscription) : ?>
                <?php
                // Filter subscriptions based on selected filter
                if ($filter !== 'all' && strtolower($subscription->status) !== $filter) {
                    continue;
                }
                ?>

                <div class="subs-subscription-item" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                    <div class="subs-subscription-card-header">
                        <div class="subs-subscription-info">
                            <h3 class="subs-subscription-product"><?php echo esc_html($subscription->product_name); ?></h3>
                            <span class="subs-subscription-id">#<?php echo esc_html($subscription->id); ?></span>
                        </div>

                        <span class="subs-subscription-badge subs-badge-<?php echo esc_attr(strtolower($subscription->status)); ?>">
                            <?php echo esc_html(ucfirst($subscription->status)); ?>
                        </span>
                    </div>

                    <div class="subs-subscription-card-body">
                        <div class="subs-subscription-details-grid">
                            <div class="subs-detail-item">
                                <span class="subs-detail-icon">💰</span>
                                <div class="subs-detail-content">
                                    <span class="subs-detail-label"><?php esc_html_e('Amount', 'subs'); ?></span>
                                    <span class="subs-detail-value">
                                        $<?php echo esc_html(number_format($subscription->amount, 2)); ?>
                                        <?php echo esc_html(strtoupper($subscription->currency)); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="subs-detail-item">
                                <span class="subs-detail-icon">🔄</span>
                                <div class="subs-detail-content">
                                    <span class="subs-detail-label"><?php esc_html_e('Billing Cycle', 'subs'); ?></span>
                                    <span class="subs-detail-value">
                                        <?php
                                        printf(
                                            esc_html__('Every %d %s', 'subs'),
                                            $subscription->billing_interval,
                                            esc_html($subscription->billing_period)
                                        );
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="subs-detail-item">
                                <span class="subs-detail-icon">📅</span>
                                <div class="subs-detail-content">
                                    <span class="subs-detail-label"><?php esc_html_e('Next Payment', 'subs'); ?></span>
                                    <span class="subs-detail-value">
                                        <?php
                                        echo $subscription->next_payment_date && $subscription->status === 'active'
                                            ? esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_payment_date)))
                                            : esc_html__('N/A', 'subs');
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="subs-detail-item">
                                <span class="subs-detail-icon">📍</span>
                                <div class="subs-detail-content">
                                    <span class="subs-detail-label"><?php esc_html_e('Shipping To', 'subs'); ?></span>
                                    <span class="subs-detail-value">
                                        <?php
                                        if (!empty($subscription->shipping_address)) {
                                            $address = json_decode($subscription->shipping_address, true);
                                            echo esc_html(($address['city'] ?? '') . ', ' . ($address['state'] ?? ''));
                                        } else {
                                            esc_html_e('No address', 'subs');
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($subscription->trial_end) && strtotime($subscription->trial_end) > current_time('timestamp')) : ?>
                        <div class="subs-trial-notice">
                            <span class="subs-trial-icon">🎁</span>
                            <span>
                                <?php
                                printf(
                                    esc_html__('Trial ends %s', 'subs'),
                                    esc_html(date_i18n(get_option('date_format'), strtotime($subscription->trial_end)))
                                );
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="subs-subscription-card-footer">
                        <div class="subs-subscription-actions">
                            <a href="<?php echo esc_url(add_query_arg(array('action' => 'view-subscription', 'id' => $subscription->id), get_permalink())); ?>"
                               class="subs-btn subs-btn-small subs-btn-primary">
                                <?php esc_html_e('View Details', 'subs'); ?>
                            </a>

                            <?php if ($subscription->status === 'active') : ?>
                            <button type="button"
                                    class="subs-btn subs-btn-small subs-btn-outline subs-pause-subscription"
                                    data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                                <?php esc_html_e('Pause', 'subs'); ?>
                            </button>
                            <?php elseif ($subscription->status === 'paused') : ?>
                            <button type="button"
                                    class="subs-btn subs-btn-small subs-btn-success subs-resume-subscription"
                                    data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                                <?php esc_html_e('Resume', 'subs'); ?>
                            </button>
                            <?php endif; ?>

                            <?php if ($subscription->status !== 'cancelled') : ?>
                            <button type="button"
                                    class="subs-btn subs-btn-small subs-btn-danger subs-cancel-subscription"
                                    data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                                <?php esc_html_e('Cancel', 'subs'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php else : ?>
        <div class="subs-empty-state">
            <span class="subs-empty-icon">📦</span>
            <h3><?php esc_html_e('No Subscriptions Found', 'subs'); ?></h3>
            <p>
                <?php
                if ($filter === 'all') {
                    esc_html_e('You don\'t have any subscriptions yet. Start your first subscription today!', 'subs');
                } else {
                    printf(
                        esc_html__('You don\'t have any %s subscriptions.', 'subs'),
                        esc_html($filter)
                    );
                }
                ?>
            </p>
            <?php if ($filter === 'all') : ?>
            <a href="<?php echo esc_url(home_url('/shop')); ?>" class="subs-btn subs-btn-primary">
                <?php esc_html_e('Browse Products', 'subs'); ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Subscriptions List Styles */
.subs-subscriptions-list-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px;
}

.subs-subscriptions-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 30px;
}

/* Page Header */
.subs-page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
}

.subs-page-title {
    font-size: 32px;
    font-weight: 700;
    margin: 0 0 8px 0;
    color: #333;
}

.subs-page-description {
    font-size: 16px;
    color: #666;
    margin: 0;
}

/* Filters */
.subs-filters-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    gap: 20px;
}

.subs-filter-tabs {
    display: flex;
    gap: 10px;
    background: #ffffff;
    padding: 5px;
    border-radius: 8px;
}

.subs-filter-tab {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    color: #666;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.subs-filter-tab:hover {
    background: #f8f9fa;
    color: #333;
}

.subs-filter-tab.active {
    background: #007cba;
    color: #ffffff;
}

.subs-filter-search input {
    padding: 10px 15px;
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    font-size: 14px;
    width: 250px;
}

.subs-filter-search input:focus {
    outline: none;
    border-color: #007cba;
}

/* Subscriptions Grid */
.subs-subscriptions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.subs-subscription-item {
    background: #ffffff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.subs-subscription-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.subs-subscription-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
}

.subs-subscription-product {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 5px 0;
    color: #ffffff;
}

.subs-subscription-id {
    font-size: 12px;
    opacity: 0.9;
}

.subs-subscription-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.subs-badge-active {
    background: #28a745;
    color: #ffffff;
}

.subs-badge-paused {
    background: #ffc107;
    color: #000000;
}

.subs-badge-cancelled {
    background: #dc3545;
    color: #ffffff;
}

.subs-subscription-card-body {
    padding: 20px;
}

.subs-subscription-details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

.subs-detail-item {
    display: flex;
    gap: 10px;
}

.subs-detail-icon {
    font-size: 20px;
}

.subs-detail-content {
    display: flex;
    flex-direction: column;
}

.subs-detail-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 3px;
}

.subs-detail-value {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.subs-trial-notice {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff3cd;
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
    color: #856404;
    margin-top: 15px;
}

.subs-trial-icon {
    font-size: 18px;
}

.subs-subscription-card-footer {
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #e1e1e1;
}

.subs-subscription-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.subs-btn-success {
    background: #28a745;
    color: #ffffff;
}

.subs-btn-success:hover {
    background: #218838;
}

.subs-btn-danger {
    background: transparent;
    color: #dc3545;
    border: 1px solid #dc3545;
}

.subs-btn-danger:hover {
    background: #dc3545;
    color: #ffffff;
}

/* Empty State */
.subs-empty-state {
    background: #ffffff;
    border-radius: 8px;
    padding: 80px 20px;
    text-align: center;
}

.subs-empty-icon {
    font-size: 80px;
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
    margin: 0 0 25px 0;
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

    .subs-filters-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .subs-filter-tabs {
        width: 100%;
        overflow-x: auto;
    }

    .subs-filter-search input {
        width: 100%;
    }

    .subs-subscriptions-grid {
        grid-template-columns: 1fr;
    }

    .subs-subscription-details-grid {
        grid-template-columns: 1fr;
    }

    .subs-subscription-actions {
        flex-direction: column;
    }

    .subs-subscription-actions .subs-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Search functionality
    $('#subs-search-subscriptions').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();

        $('.subs-subscription-item').each(function() {
            var productName = $(this).find('.subs-subscription-product').text().toLowerCase();
            var subscriptionId = $(this).find('.subs-subscription-id').text().toLowerCase();

            if (productName.includes(searchTerm) || subscriptionId.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Pause subscription
    $('.subs-pause-subscription').on('click', function() {
        var subscriptionId = $(this).data('subscription-id');

        if (confirm('<?php esc_html_e('Are you sure you want to pause this subscription?', 'subs'); ?>')) {
            // AJAX call to pause subscription
            console.log('Pausing subscription: ' + subscriptionId);
        }
    });

    // Resume subscription
    $('.subs-resume-subscription').on('click', function() {
        var subscriptionId = $(this).data('subscription-id');

        // AJAX call to resume subscription
        console.log('Resuming subscription: ' + subscriptionId);
    });

    // Cancel subscription
    $('.subs-cancel-subscription').on('click', function() {
        var subscriptionId = $(this).data('subscription-id');

        if (confirm('<?php esc_html_e('Are you sure you want to cancel this subscription? This action cannot be undone.', 'subs'); ?>')) {
            // AJAX call to cancel subscription
            console.log('Cancelling subscription: ' + subscriptionId);
        }
    });
});
</script>

<?php
// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created subscriptions list template
// - Added filtering by status (all, active, paused, cancelled)
// - Included search functionality
// - Displayed subscription details in card format
// - Added quick action buttons (pause, resume, cancel)
// - Trial period indicator
// - Responsive grid layout
// - Future: Add bulk actions
// - Future: Include export to CSV
// - Future: Add sorting options
?>
