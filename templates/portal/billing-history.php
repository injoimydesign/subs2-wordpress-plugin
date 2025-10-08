<?php
/**
 * Billing History Template
 *
 * This template displays customer billing history and invoices.
 * Can be overridden by copying to yourtheme/subs/portal/billing-history.php
 *
 * @package Subs
 * @subpackage Templates/Portal
 * @version 1.0.0
 *
 * @var array $payments Payment history
 * @var float $total_spent Total amount spent
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure customer is logged in
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('Please log in to view your billing history.', 'subs') . '</p>';
    return;
}

$payments = isset($payments) ? $payments : array();
$total_spent = isset($total_spent) ? $total_spent : 0;
?>

<div class="subs-billing-history-wrapper">
    <div class="subs-billing-container">

        <!-- Page Header -->
        <div class="subs-page-header">
            <div>
                <h1 class="subs-page-title"><?php esc_html_e('Billing History', 'subs'); ?></h1>
                <p class="subs-page-description"><?php esc_html_e('View all your past payments and download invoices', 'subs'); ?></p>
            </div>

            <button type="button" class="subs-btn subs-btn-outline" id="subs-export-billing">
                <span>📊</span>
                <?php esc_html_e('Export to CSV', 'subs'); ?>
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="subs-billing-summary">
            <div class="subs-summary-card">
                <div class="subs-summary-icon subs-icon-primary">
                    <span>💰</span>
                </div>
                <div class="subs-summary-content">
                    <h3>$<?php echo esc_html(number_format($total_spent, 2)); ?></h3>
                    <p><?php esc_html_e('Total Spent', 'subs'); ?></p>
                </div>
            </div>

            <div class="subs-summary-card">
                <div class="subs-summary-icon subs-icon-success">
                    <span>✓</span>
                </div>
                <div class="subs-summary-content">
                    <h3><?php echo esc_html(count(array_filter($payments, function($p) { return $p->status === 'succeeded'; }))); ?></h3>
                    <p><?php esc_html_e('Successful Payments', 'subs'); ?></p>
                </div>
            </div>

            <div class="subs-summary-card">
                <div class="subs-summary-icon subs-icon-danger">
                    <span>✕</span>
                </div>
                <div class="subs-summary-content">
                    <h3><?php echo esc_html(count(array_filter($payments, function($p) { return $p->status === 'failed'; }))); ?></h3>
                    <p><?php esc_html_e('Failed Payments', 'subs'); ?></p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="subs-billing-filters">
            <div class="subs-filter-group">
                <label for="subs-filter-year"><?php esc_html_e('Year:', 'subs'); ?></label>
                <select id="subs-filter-year" class="subs-filter-select">
                    <option value=""><?php esc_html_e('All Years', 'subs'); ?></option>
                    <?php
                    $current_year = date('Y');
                    for ($i = 0; $i < 5; $i++) {
                        $year = $current_year - $i;
                        echo '<option value="' . esc_attr($year) . '">' . esc_html($year) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="subs-filter-group">
                <label for="subs-filter-status"><?php esc_html_e('Status:', 'subs'); ?></label>
                <select id="subs-filter-status" class="subs-filter-select">
                    <option value=""><?php esc_html_e('All Statuses', 'subs'); ?></option>
                    <option value="succeeded"><?php esc_html_e('Successful', 'subs'); ?></option>
                    <option value="failed"><?php esc_html_e('Failed', 'subs'); ?></option>
                    <option value="refunded"><?php esc_html_e('Refunded', 'subs'); ?></option>
                </select>
            </div>
        </div>

        <!-- Payments Table -->
        <?php if (!empty($payments)) : ?>
        <div class="subs-billing-table-wrapper">
            <table class="subs-billing-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'subs'); ?></th>
                        <th><?php esc_html_e('Description', 'subs'); ?></th>
                        <th><?php esc_html_e('Amount', 'subs'); ?></th>
                        <th><?php esc_html_e('Status', 'subs'); ?></th>
                        <th><?php esc_html_e('Invoice', 'subs'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment) : ?>
                    <tr class="subs-payment-row" data-year="<?php echo esc_attr(date('Y', strtotime($payment->created_at))); ?>" data-status="<?php echo esc_attr($payment->status); ?>">
                        <td class="subs-payment-date">
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment->created_at))); ?>
                        </td>
                        <td class="subs-payment-description">
                            <div class="subs-payment-info">
                                <strong><?php echo esc_html($payment->description ?? __('Subscription Payment', 'subs')); ?></strong>
                                <?php if (!empty($payment->subscription_id)) : ?>
                                <small><?php printf(esc_html__('Subscription #%s', 'subs'), $payment->subscription_id); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="subs-payment-amount">
                            <strong>$<?php echo esc_html(number_format($payment->amount, 2)); ?></strong>
                            <small><?php echo esc_html(strtoupper($payment->currency)); ?></small>
                        </td>
                        <td class="subs-payment-status">
                            <?php
                            $status_class = '';
                            $status_text = '';
                            switch ($payment->status) {
                                case 'succeeded':
                                    $status_class = 'success';
                                    $status_text = __('Paid', 'subs');
                                    break;
                                case 'failed':
                                    $status_class = 'danger';
                                    $status_text = __('Failed', 'subs');
                                    break;
                                case 'refunded':
                                    $status_class = 'warning';
                                    $status_text = __('Refunded', 'subs');
                                    break;
                                default:
                                    $status_class = 'secondary';
                                    $status_text = ucfirst($payment->status);
                            }
                            ?>
                            <span class="subs-status-badge subs-status-<?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                        <td class="subs-payment-actions">
                            <?php if ($payment->status === 'succeeded') : ?>
                            <button type="button" class="subs-btn-icon subs-download-invoice" data-payment-id="<?php echo esc_attr($payment->id); ?>" title="<?php esc_attr_e('Download Invoice', 'subs'); ?>">
                                <span>📄</span>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php else : ?>
        <div class="subs-empty-state">
            <span class="subs-empty-icon">💳</span>
            <h3><?php esc_html_e('No Payment History', 'subs'); ?></h3>
            <p><?php esc_html_e('You haven\'t made any payments yet.', 'subs'); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Billing History Styles */
.subs-billing-history-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px;
}

.subs-billing-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 30px;
}

/* Summary Cards */
.subs-billing-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.subs-summary-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 25px;
    display: flex;
    gap: 15px;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.subs-summary-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 28px;
    flex-shrink: 0;
}

.subs-icon-primary {
    background: #e7f3f8;
}

.subs-icon-success {
    background: #d4edda;
}

.subs-icon-danger {
    background: #f8d7da;
}

.subs-summary-content h3 {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 5px 0;
    color: #333;
}

.subs-summary-content p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Filters */
.subs-billing-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: #ffffff;
    border-radius: 8px;
}

.subs-filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.subs-filter-group label {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.subs-filter-select {
    padding: 8px 12px;
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    font-size: 14px;
    background: #ffffff;
}

.subs-filter-select:focus {
    outline: none;
    border-color: #007cba;
}

/* Table */
.subs-billing-table-wrapper {
    background: #ffffff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.subs-billing-table {
    width: 100%;
    border-collapse: collapse;
}

.subs-billing-table thead {
    background: #f8f9fa;
}

.subs-billing-table th {
    padding: 15px 20px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.subs-billing-table tbody tr {
    border-bottom: 1px solid #e1e1e1;
    transition: background-color 0.2s ease;
}

.subs-billing-table tbody tr:hover {
    background: #f8f9fa;
}

.subs-billing-table td {
    padding: 20px;
    font-size: 14px;
}

.subs-payment-date {
    color: #666;
}

.subs-payment-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.subs-payment-info strong {
    color: #333;
}

.subs-payment-info small {
    color: #999;
    font-size: 12px;
}

.subs-payment-amount {
    display: flex;
    flex-direction: column;
}

.subs-payment-amount strong {
    font-size: 16px;
    color: #333;
}

.subs-payment-amount small {
    color: #999;
    font-size: 11px;
}

.subs-status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.subs-status-success {
    background: #d4edda;
    color: #155724;
}

.subs-status-danger {
    background: #f8d7da;
    color: #721c24;
}

.subs-status-warning {
    background: #fff3cd;
    color: #856404;
}

.subs-status-secondary {
    background: #e2e3e5;
    color: #383d41;
}

.subs-btn-icon {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 20px;
    padding: 5px;
    transition: transform 0.2s ease;
}

.subs-btn-icon:hover {
    transform: scale(1.1);
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
    margin: 0;
}

/* Responsive Design */
@media (max-width: 992px) {
    .subs-billing-summary {
        grid-template-columns: 1fr;
    }

    .subs-billing-table-wrapper {
        overflow-x: auto;
    }

    .subs-billing-table {
        min-width: 600px;
    }
}

@media (max-width: 768px) {
    .subs-page-header {
        flex-direction: column;
        gap: 20px;
    }

    .subs-page-header .subs-btn {
        width: 100%;
        justify-content: center;
    }

    .subs-billing-filters {
        flex-direction: column;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Filter by year
    $('#subs-filter-year').on('change', function() {
        var selectedYear = $(this).val();

        $('.subs-payment-row').each(function() {
            var rowYear = $(this).data('year');

            if (selectedYear === '' || rowYear == selectedYear) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        applyStatusFilter();
    });

    // Filter by status
    $('#subs-filter-status').on('change', function() {
        applyStatusFilter();
    });

    function applyStatusFilter() {
        var selectedStatus = $('#subs-filter-status').val();
        var selectedYear = $('#subs-filter-year').val();

        $('.subs-payment-row').each(function() {
            var rowStatus = $(this).data('status');
            var rowYear = $(this).data('year');
            var showYear = selectedYear === '' || rowYear == selectedYear;
            var showStatus = selectedStatus === '' || rowStatus === selectedStatus;

            if (showYear && showStatus) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    // Download invoice
    $('.subs-download-invoice').on('click', function() {
        var paymentId = $(this).data('payment-id');

        // AJAX call to generate and download invoice
        console.log('Downloading invoice for payment: ' + paymentId);

        // Redirect to invoice download URL
        window.location.href = '?action=download-invoice&payment_id=' + paymentId;
    });

    // Export to CSV
    $('#subs-export-billing').on('click', function() {
        // AJAX call to export billing history
        console.log('Exporting billing history to CSV');

        window.location.href = '?action=export-billing-csv';
    });
});
</script>

<?php
// CHANGELOG:
// Version 1.0.0 - Initial release
// - Created billing history template
// - Added summary statistics cards
// - Included year and status filters
// - Displayed payments in table format
// - Added invoice download functionality
// - Export to CSV option
// - Responsive design
// - Future: Add pagination for large datasets
// - Future: Include payment method details
// - Future: Add date range picker
?>
