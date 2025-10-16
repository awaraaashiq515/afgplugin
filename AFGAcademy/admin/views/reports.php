<?php
/**
 * Reports & Analytics Page
 * Sales reports, trainee reports, product reports
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permission
if (!current_user_can('view_dkm_reports')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;
$currency = get_option('dkm_currency_symbol', '₹');

// Get date range from request
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'sales';

// Sales Table
$sales_table = $wpdb->prefix . 'deshi_sales';
$ledger_table = $wpdb->prefix . 'trainee_ledger';
$stock_table = $wpdb->prefix . 'kitchen_stock_movements';

// Get orders from WooCommerce
$orders_query = "
    SELECT 
        p.ID as order_id,
        p.post_date as order_date,
        pm_total.meta_value as order_total,
        pm_customer.meta_value as customer_id
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
    LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
    AND DATE(p.post_date) BETWEEN %s AND %s
    ORDER BY p.post_date DESC
";

$orders = $wpdb->get_results($wpdb->prepare($orders_query, $start_date, $end_date));

// Calculate totals
$total_sales = 0;
$total_orders = count($orders);
$cash_sales = 0;
$credit_sales = 0;

foreach ($orders as $order) {
    $total_sales += floatval($order->order_total);
    
    // Get payment method
    $payment_method = get_post_meta($order->order_id, '_payment_method_title', true);
    if (strpos(strtolower($payment_method), 'credit') !== false) {
        $credit_sales += floatval($order->order_total);
    } else {
        $cash_sales += floatval($order->order_total);
    }
}

// Get top products
$top_products_query = "
    SELECT 
        pm_product.meta_value as product_id,
        SUM(pm_qty.meta_value) as total_qty,
        SUM(pm_total.meta_value) as total_amount
    FROM {$wpdb->prefix}woocommerce_order_items oi
    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_product ON oi.order_item_id = pm_product.order_item_id AND pm_product.meta_key = '_product_id'
    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_qty ON oi.order_item_id = pm_qty.order_item_id AND pm_qty.meta_key = '_qty'
    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_total ON oi.order_item_id = pm_total.order_item_id AND pm_total.meta_key = '_line_total'
    WHERE oi.order_item_type = 'line_item'
    AND oi.order_id IN (
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'shop_order' 
        AND post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        AND DATE(post_date) BETWEEN %s AND %s
    )
    GROUP BY product_id
    ORDER BY total_qty DESC
    LIMIT 10
";

$top_products = $wpdb->get_results($wpdb->prepare($top_products_query, $start_date, $end_date));

// Get trainee stats
$trainee_stats_query = "
    SELECT 
        pm_customer.meta_value as trainee_id,
        COUNT(DISTINCT p.ID) as order_count,
        SUM(pm_total.meta_value) as total_spent
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
    LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
    AND DATE(p.post_date) BETWEEN %s AND %s
    AND pm_customer.meta_value > 0
    GROUP BY trainee_id
    ORDER BY total_spent DESC
    LIMIT 10
";

$top_trainees = $wpdb->get_results($wpdb->prepare($trainee_stats_query, $start_date, $end_date));

// Outstanding balance
$outstanding_balance = $wpdb->get_var("
    SELECT SUM(balance_after) FROM (
        SELECT trainee_id, balance_after 
        FROM {$ledger_table}
        WHERE id IN (
            SELECT MAX(id) FROM {$ledger_table} 
            GROUP BY trainee_id
        )
        HAVING balance_after > 0
    ) as latest_balances
");

?>

<div class="wrap dkm-reports-page">
    <h1 class="wp-heading-inline">
        <?php _e('Reports & Analytics', 'deshi-kitchen-admin'); ?>
    </h1>
    
    <!-- Date Range Filter -->
    <div class="dkm-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="deshi-reports">
            
            <label for="start_date"><?php _e('From:', 'deshi-kitchen-admin'); ?></label>
            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            
            <label for="end_date"><?php _e('To:', 'deshi-kitchen-admin'); ?></label>
            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            
            <label for="report_type"><?php _e('Report:', 'deshi-kitchen-admin'); ?></label>
            <select id="report_type" name="report_type">
                <option value="sales" <?php selected($report_type, 'sales'); ?>><?php _e('Sales Overview', 'deshi-kitchen-admin'); ?></option>
                <option value="products" <?php selected($report_type, 'products'); ?>><?php _e('Product Analysis', 'deshi-kitchen-admin'); ?></option>
                <option value="trainees" <?php selected($report_type, 'trainees'); ?>><?php _e('Trainee Analysis', 'deshi-kitchen-admin'); ?></option>
                <option value="payments" <?php selected($report_type, 'payments'); ?>><?php _e('Payment Collection', 'deshi-kitchen-admin'); ?></option>
            </select>
            
            <button type="submit" class="button button-primary">
                <?php _e('Apply Filter', 'deshi-kitchen-admin'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=deshi-reports&action=export&start_date=' . $start_date . '&end_date=' . $end_date); ?>" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export CSV', 'deshi-kitchen-admin'); ?>
            </a>
        </form>
    </div>
    
    <!-- Summary Stats -->
    <div class="dkm-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $currency . number_format($total_sales, 2); ?></h3>
                <p><?php _e('Total Sales', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_orders); ?></h3>
                <p><?php _e('Total Orders', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $currency . number_format($cash_sales, 2); ?></h3>
                <p><?php _e('Cash/Online Sales', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $currency . number_format($credit_sales, 2); ?></h3>
                <p><?php _e('Credit Sales', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card alert">
            <div class="stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $currency . number_format($outstanding_balance, 2); ?></h3>
                <p><?php _e('Outstanding Balance', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_orders > 0 ? $currency . number_format($total_sales / $total_orders, 2) : $currency . '0.00'; ?></h3>
                <p><?php _e('Average Order Value', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Report Content Based on Type -->
    <div class="dkm-report-content">
        
        <?php if ($report_type === 'sales' || $report_type === 'products') : ?>
        <!-- Top Selling Products -->
        <div class="dkm-report-section">
            <h2><?php _e('Top Selling Products', 'deshi-kitchen-admin'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Quantity Sold', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Revenue', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Avg. Price', 'deshi-kitchen-admin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($top_products)) : ?>
                        <?php foreach ($top_products as $item) : 
                            $product = wc_get_product($item->product_id);
                            if (!$product) continue;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($product->get_name()); ?></strong>
                                    <br><small>SKU: <?php echo esc_html($product->get_sku()); ?></small>
                                </td>
                                <td><?php echo number_format($item->total_qty); ?></td>
                                <td><?php echo $currency . number_format($item->total_amount, 2); ?></td>
                                <td><?php echo $currency . number_format($item->total_amount / $item->total_qty, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px;">
                                <?php _e('No products sold in this period.', 'deshi-kitchen-admin'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type === 'sales' || $report_type === 'trainees') : ?>
        <!-- Top Trainees -->
        <div class="dkm-report-section">
            <h2><?php _e('Top Trainees by Spending', 'deshi-kitchen-admin'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Trainee', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Total Orders', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Total Spent', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Avg. Order', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Current Balance', 'deshi-kitchen-admin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($top_trainees)) : ?>
                        <?php foreach ($top_trainees as $trainee_stat) : 
                            $user = get_userdata($trainee_stat->trainee_id);
                            if (!$user) continue;
                            
                            // Get current balance
                            $balance = $wpdb->get_var($wpdb->prepare(
                                "SELECT balance_after FROM {$ledger_table} 
                                WHERE trainee_id = %d 
                                ORDER BY id DESC LIMIT 1",
                                $trainee_stat->trainee_id
                            ));
                            $balance = $balance ? floatval($balance) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                    <br><small><?php echo esc_html($user->user_email); ?></small>
                                </td>
                                <td><?php echo number_format($trainee_stat->order_count); ?></td>
                                <td><?php echo $currency . number_format($trainee_stat->total_spent, 2); ?></td>
                                <td><?php echo $currency . number_format($trainee_stat->total_spent / $trainee_stat->order_count, 2); ?></td>
                                <td>
                                    <span class="<?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $currency . number_format($balance, 2); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <?php _e('No trainee activity in this period.', 'deshi-kitchen-admin'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type === 'payments') : ?>
        <!-- Payment Collection Report -->
        <div class="dkm-report-section">
            <h2><?php _e('Payment Collection', 'deshi-kitchen-admin'); ?></h2>
            <?php
            $payments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$ledger_table}
                WHERE type = 'payment'
                AND DATE(created_at) BETWEEN %s AND %s
                ORDER BY created_at DESC
                LIMIT 50",
                $start_date, $end_date
            ));
            
            $total_collected = 0;
            foreach ($payments as $payment) {
                $total_collected += $payment->amount;
            }
            ?>
            
            <div class="payment-summary">
                <h3><?php _e('Total Payments Collected:', 'deshi-kitchen-admin'); ?> 
                    <span class="text-success"><?php echo $currency . number_format($total_collected, 2); ?></span>
                </h3>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Trainee', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Amount', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Balance After', 'deshi-kitchen-admin'); ?></th>
                        <th><?php _e('Note', 'deshi-kitchen-admin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)) : ?>
                        <?php foreach ($payments as $payment) : 
                            $user = get_userdata($payment->trainee_id);
                        ?>
                            <tr>
                                <td><?php echo date('d M Y, h:i A', strtotime($payment->created_at)); ?></td>
                                <td><?php echo $user ? esc_html($user->display_name) : 'N/A'; ?></td>
                                <td class="text-success"><strong><?php echo $currency . number_format($payment->amount, 2); ?></strong></td>
                                <td><?php echo $currency . number_format($payment->balance_after, 2); ?></td>
                                <td><?php echo esc_html($payment->note); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <?php _e('No payments recorded in this period.', 'deshi-kitchen-admin'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<style>
.dkm-reports-page {
    max-width: 100%;
}

.dkm-filters {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.dkm-filters form {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.dkm-filters label {
    font-weight: 600;
}

.dkm-filters input[type="date"],
.dkm-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.dkm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
}

.stat-card.warning {
    border-left: 4px solid #ff9800;
}

.stat-card.alert {
    border-left: 4px solid #f44336;
}

.stat-icon {
    font-size: 40px;
    color: #2271b1;
}

.stat-content h3 {
    margin: 0;
    font-size: 24px;
    color: #2c3338;
}

.stat-content p {
    margin: 5px 0 0 0;
    color: #646970;
    font-size: 13px;
}

.dkm-report-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.dkm-report-section h2 {
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #2271b1;
}

.payment-summary {
    background: #e8f5e9;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.payment-summary h3 {
    margin: 0;
}

.text-success {
    color: #2e7d32;
}

.text-danger {
    color: #c62828;
}

@media (max-width: 768px) {
    .dkm-filters form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>