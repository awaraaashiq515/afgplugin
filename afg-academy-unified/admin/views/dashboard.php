<?php
/**
 * Admin Dashboard Page
 * Main overview with statistics and quick actions
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get today's sales from WooCommerce orders
$today_sales = 0;
$today_start = strtotime('today midnight');
$today_end   = strtotime('tomorrow midnight') - 1;

$today_orders = wc_get_orders(array(
    'limit' => -1,
    'status' => array('wc-completed', 'wc-processing', 'wc-on-hold'),
    'date_created' => $today_start . '...' . $today_end,
));

foreach ($today_orders as $order) {
    $today_sales += floatval($order->get_total());
}

 $today_sales_display = wc_price($today_sales);

// Active trainees
$active_trainees = count(get_users(array('role' => 'customer')));

// Outstanding balance
$ledger_table = $wpdb->prefix . 'trainee_ledger';
$outstanding = $wpdb->get_var(
    "SELECT SUM(balance_after) FROM (
        SELECT trainee_id, balance_after 
        FROM {$ledger_table}
        WHERE id IN (
            SELECT MAX(id) FROM {$ledger_table} 
            GROUP BY trainee_id
        )
        HAVING balance_after > 0
    ) as latest_balances"
);
$outstanding = $outstanding ? floatval($outstanding) : 0;

// Pending invoices
$invoices_table = $wpdb->prefix . 'deshi_invoices';
$pending_invoices = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$invoices_table} 
    WHERE payment_status IN ('unpaid', 'partial')"
);

// Low stock products
$low_stock_products = DKM_WooCommerce_Integration::get_low_stock_products();

// Recent orders
$recent_orders = wc_get_orders(array(
    'limit' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
    'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
));

// Pending support queries
$queries_table = $wpdb->prefix . 'deshi_queries';
$pending_queries = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$queries_table} 
    WHERE status IN ('new', 'in_progress')"
);

?>

<div class="wrap dkm-dashboard">
    <h1><?php _e('Deshi Kitchen Dashboard', 'deshi-kitchen-admin'); ?></h1>
    
    <!-- Stats Cards -->
    <div class="dkm-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $currency . number_format($today_sales, 2); ?></h3>
                <p><?php _e("Today's Sales", 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($active_trainees); ?></h3>
                <p><?php _e('Active Trainees', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card <?php echo $outstanding > 0 ? 'warning' : ''; ?>">
            <div class="stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $currency . number_format($outstanding, 2); ?></h3>
                <p><?php _e('Outstanding Balance', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
        
        <div class="stat-card <?php echo $pending_invoices > 0 ? 'alert' : ''; ?>">
            <div class="stat-icon">
                <span class="dashicons dashicons-media-text"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($pending_invoices); ?></h3>
                <p><?php _e('Pending Invoices', 'deshi-kitchen-admin'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Two Column Layout -->
    <div class="dkm-dashboard-row">
        
        <!-- Left Column -->
        <div class="dkm-dashboard-col">
            
            <!-- Recent Orders -->
            <div class="dkm-card">
                <div class="card-header">
                    <h2><?php _e('Recent Orders', 'deshi-kitchen-admin'); ?></h2>
                    <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-small">
                        <?php _e('View All', 'deshi-kitchen-admin'); ?>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Order', 'deshi-kitchen-admin'); ?></th>
                                    <th><?php _e('Customer', 'deshi-kitchen-admin'); ?></th>
                                    <th><?php _e('Total', 'deshi-kitchen-admin'); ?></th>
                                    <th><?php _e('Status', 'deshi-kitchen-admin'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $order->get_edit_order_url(); ?>">
                                                #<?php echo $order->get_order_number(); ?>
                                            </a>
                                            <br><small><?php echo $order->get_date_created()->date('d M, h:i A'); ?></small>
                                        </td>
                                        <td><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></td>
                                        <td><strong><?php echo $currency . number_format($order->get_total(), 2); ?></strong></td>
                                        <td>
                                            <span class="order-status status-<?php echo $order->get_status(); ?>">
                                                <?php echo wc_get_order_status_name($order->get_status()); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="no-data"><?php _e('No orders yet.', 'deshi-kitchen-admin'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Support Queries -->
            <?php if ($pending_queries > 0) : ?>
            <div class="dkm-card">
                <div class="card-header">
                    <h2><?php _e('Pending Support Queries', 'deshi-kitchen-admin'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=deshi-queries'); ?>" class="button button-small">
                        <?php _e('View All', 'deshi-kitchen-admin'); ?>
                    </a>
                </div>
                <div class="card-body">
                    <p><?php printf(__('You have %d pending support queries that need attention.', 'deshi-kitchen-admin'), $pending_queries); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Right Column -->
        <div class="dkm-dashboard-col">
            
            <!-- Low Stock Items -->
            <div class="dkm-card">
                <div class="card-header">
                    <h2><?php _e('Low Stock Items', 'deshi-kitchen-admin'); ?></h2>
                    <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-small">
                        <?php _e('Manage Stock', 'deshi-kitchen-admin'); ?>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($low_stock_products)) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Product', 'deshi-kitchen-admin'); ?></th>
                                    <th><?php _e('Stock', 'deshi-kitchen-admin'); ?></th>
                                    <th><?php _e('Action', 'deshi-kitchen-admin'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $item) : ?>
                                    <tr class="<?php echo $item['status'] === 'out_of_stock' ? 'out-of-stock' : ''; ?>">
                                        <td>
                                            <strong><?php echo esc_html($item['name']); ?></strong>
                                            <br><small>SKU: <?php echo esc_html($item['sku']); ?></small>
                                        </td>
                                        <td>
                                            <strong class="<?php echo $item['status'] === 'out_of_stock' ? 'text-danger' : 'text-warning'; ?>">
                                                <?php echo $item['stock']; ?>
                                            </strong>
                                            <span class="stock-status"><?php echo $item['status'] === 'out_of_stock' ? 'Out of Stock' : 'Low Stock'; ?></span>
                                        </td>
                                        <td>
                                            <a href="<?php echo get_edit_post_link($item['id']); ?>" class="button button-small">
                                                <?php _e('Restock', 'deshi-kitchen-admin'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="no-data success"><?php _e('All items are well stocked!', 'deshi-kitchen-admin'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="dkm-card quick-actions">
                <div class="card-header">
                    <h2><?php _e('Quick Actions', 'deshi-kitchen-admin'); ?></h2>
                </div>
                <div class="card-body">
                    <div class="action-buttons">
                        <?php if (current_user_can('manage_dkm_trainees')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=deshi-trainees'); ?>" class="button button-large">
                            <span class="dashicons dashicons-groups"></span>
                            <?php _e('Manage Trainees', 'deshi-kitchen-admin'); ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (current_user_can('manage_dkm_invoices')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=deshi-invoices&action=generate'); ?>" class="button button-large">
                            <span class="dashicons dashicons-media-text"></span>
                            <?php _e('Generate Invoices', 'deshi-kitchen-admin'); ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (current_user_can('view_dkm_reports')) : ?>
                        <a href="<?php echo admin_url('admin.php?page=deshi-reports'); ?>" class="button button-large">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php _e('View Reports', 'deshi-kitchen-admin'); ?>
                        </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-large">
                            <span class="dashicons dashicons-products"></span>
                            <?php _e('Manage Products', 'deshi-kitchen-admin'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dkm-dashboard {
    max-width: 100%;
}

.dkm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0 30px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
    font-size: 28px;
    color: #2c3338;
}

.stat-content p {
    margin: 5px 0 0 0;
    color: #646970;
    font-size: 14px;
}

.dkm-dashboard-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.dkm-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.dkm-card .card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dkm-card .card-header h2 {
    margin: 0;
    font-size: 18px;
}

.dkm-card .card-body {
    padding: 20px;
}

.order-status {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.order-status.status-completed {
    background: #4caf50;
    color: white;
}

.order-status.status-processing {
    background: #2196f3;
    color: white;
}

.order-status.status-on-hold {
    background: #ff9800;
    color: white;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #646970;
}

.no-data.success {
    color: #4caf50;
}

.text-danger {
    color: #f44336;
}

.text-warning {
    color: #ff9800;
}

.out-of-stock {
    background: #ffebee !important;
}

.stock-status {
    display: block;
    font-size: 11px;
    color: #666;
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.action-buttons .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

@media (max-width: 1200px) {
    .dkm-dashboard-row {
        grid-template-columns: 1fr;
    }
}
</style>