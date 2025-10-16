<?php
/**
 * Kitchen POS Dashboard
 * Main landing page for POS operators
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!KPOS_Permissions::can_access_pos()) {
    wp_die(__('You do not have permission to access this page.', 'kitchen-pos'));
}

// Enqueue dashboard assets
wp_enqueue_style('kpos-dashboard-style', KPOS_ASSETS_URL . 'css/dashboard.css', array(), KPOS_VERSION);
wp_enqueue_script('kpos-dashboard-script', KPOS_ASSETS_URL . 'js/dashboard.js', array('jquery'), KPOS_VERSION, true);

// Get current user
$current_user = wp_get_current_user();

// Get today's stats (from KPOS_Handler)
$today_stats = KPOS_Handler::get_today_stats();

// Get recent orders
$recent_orders = KPOS_Handler::get_recent_orders(5);

$currency = get_option('dkm_currency_symbol', '₹');
?>

<div class="kpos-dashboard-wrapper">
    
    <!-- Header -->
    <div class="kpos-dashboard-header">
        <div class="header-left">
            <div class="brand">
                <span class="brand-icon">🍽️</span>
                <div class="brand-info">
                    <h1><?php echo get_bloginfo('name'); ?></h1>
                    <p><?php _e('Kitchen POS System', 'kitchen-pos'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="header-right">
            <div class="current-time" id="current-time"></div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo get_avatar($current_user->ID, 40); ?>
                </div>
                <div class="user-details">
                    <strong><?php echo esc_html($current_user->display_name); ?></strong>
                    <span><?php _e('POS Operator', 'kitchen-pos'); ?></span>
                </div>
            </div>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="logout-btn" title="<?php _e('Logout', 'kitchen-pos'); ?>">
                <span class="dashicons dashicons-exit"></span>
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="kpos-stats-section">
        <h2><?php _e("Today's Overview", 'kitchen-pos'); ?></h2>
        
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="stat-content">
                    <span class="stat-label"><?php _e('Total Sales', 'kitchen-pos'); ?></span>
                    <h3><?php echo $currency . number_format($today_stats['total_sales'], 2); ?></h3>
                    <p class="stat-meta"><?php echo $today_stats['total_orders']; ?> <?php _e('orders', 'kitchen-pos'); ?></p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-content">
                    <span class="stat-label"><?php _e('Cash Sales', 'kitchen-pos'); ?></span>
                    <h3><?php echo $currency . number_format($today_stats['cash_sales'], 2); ?></h3>
                    <p class="stat-meta"><?php _e('Paid in cash', 'kitchen-pos'); ?></p>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <span class="stat-label"><?php _e('Credit Sales', 'kitchen-pos'); ?></span>
                    <h3><?php echo $currency . number_format($today_stats['credit_sales'], 2); ?></h3>
                    <p class="stat-meta"><?php _e('On credit', 'kitchen-pos'); ?></p>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <span class="dashicons dashicons-list-view"></span>
                </div>
                <div class="stat-content">
                    <span class="stat-label"><?php _e('Total Orders', 'kitchen-pos'); ?></span>
                    <h3><?php echo number_format($today_stats['total_orders']); ?></h3>
                    <p class="stat-meta"><?php echo date('d M Y'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Actions -->
    <div class="kpos-main-actions">
        <a href="<?php echo admin_url('admin.php?page=kitchen-pos&view=pos'); ?>" class="action-card primary">
            <div class="action-icon">
                <span class="dashicons dashicons-store"></span>
            </div>
            <div class="action-content">
                <h3><?php _e('Open POS', 'kitchen-pos'); ?></h3>
                <p><?php _e('Start taking orders', 'kitchen-pos'); ?></p>
            </div>
            <span class="action-arrow">→</span>
        </a>
        
        <?php if (current_user_can('view_dkm_reports')) : ?>
        <a href="<?php echo admin_url('admin.php?page=deshi-reports'); ?>" class="action-card secondary">
            <div class="action-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="action-content">
                <h3><?php _e('Reports', 'kitchen-pos'); ?></h3>
                <p><?php _e('View sales reports', 'kitchen-pos'); ?></p>
            </div>
            <span class="action-arrow">→</span>
        </a>
        <?php endif; ?>
        
        <?php if (current_user_can('manage_dkm_trainees')) : ?>
        <a href="<?php echo admin_url('admin.php?page=deshi-trainees'); ?>" class="action-card secondary">
            <div class="action-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="action-content">
                <h3><?php _e('Trainees', 'kitchen-pos'); ?></h3>
                <p><?php _e('Manage trainee accounts', 'kitchen-pos'); ?></p>
            </div>
            <span class="action-arrow">→</span>
        </a>
        <?php endif; ?>
        
        <?php if (current_user_can('view_dkm_products')) : ?>
        <a href="<?php echo admin_url('admin.php?page=deshi-products'); ?>" class="action-card secondary">
            <div class="action-icon">
                <span class="dashicons dashicons-products"></span>
            </div>
            <div class="action-content">
                <h3><?php _e('Products', 'kitchen-pos'); ?></h3>
                <p><?php _e('View kitchen items', 'kitchen-pos'); ?></p>
            </div>
            <span class="action-arrow">→</span>
        </a>
        <?php endif; ?>
    </div>

    <!-- Recent Orders -->
    <div class="kpos-recent-section">
        <div class="section-header">
            <h2><?php _e('Recent Orders', 'kitchen-pos'); ?></h2>
            <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="view-all-link">
                <?php _e('View All Orders', 'kitchen-pos'); ?> →
            </a>
        </div>
        
        <div class="recent-orders-table">
            <?php if (!empty($recent_orders)) : ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('Order', 'kitchen-pos'); ?></th>
                            <th><?php _e('Customer', 'kitchen-pos'); ?></th>
                            <th><?php _e('Items', 'kitchen-pos'); ?></th>
                            <th><?php _e('Total', 'kitchen-pos'); ?></th>
                            <th><?php _e('Payment', 'kitchen-pos'); ?></th>
                            <th><?php _e('Time', 'kitchen-pos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order) : 
                            $order_obj = wc_get_order($order->ID);
                            if (!$order_obj) continue;
                            
                            $customer = $order_obj->get_user();
                            $payment_method = $order_obj->get_meta('_kpos_payment_method') ?: 'cash';
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $order_obj->get_edit_order_url(); ?>">
                                        <strong>#<?php echo $order_obj->get_order_number(); ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($customer) : ?>
                                        <div class="customer-info">
                                            <?php echo get_avatar($customer->ID, 32); ?>
                                            <span><?php echo esc_html($customer->display_name); ?></span>
                                        </div>
                                    <?php else : ?>
                                        <?php _e('Guest', 'kitchen-pos'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $order_obj->get_item_count(); ?> items</td>
                                <td><strong><?php echo $currency . number_format($order_obj->get_total(), 2); ?></strong></td>
                                <td>
                                    <span class="payment-badge <?php echo $payment_method; ?>">
                                        <?php echo ucfirst($payment_method); ?>
                                    </span>
                                </td>
                                <td><?php echo $order_obj->get_date_created()->date('h:i A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="no-orders">
                    <span class="dashicons dashicons-cart"></span>
                    <p><?php _e('No orders yet today', 'kitchen-pos'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=kitchen-pos&view=pos'); ?>" class="btn-primary">
                        <?php _e('Create First Order', 'kitchen-pos'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Tips -->
    <div class="kpos-tips-section">
        <h3><span class="dashicons dashicons-lightbulb"></span> <?php _e('Quick Tips', 'kitchen-pos'); ?></h3>
        <div class="tips-grid">
            <div class="tip-card">
                <strong>🔍 <?php _e('Search Trainees', 'kitchen-pos'); ?></strong>
                <p><?php _e('Use name, email, or phone to search', 'kitchen-pos'); ?></p>
            </div>
            <div class="tip-card">
                <strong>💳 <?php _e('Credit Payments', 'kitchen-pos'); ?></strong>
                <p><?php _e('Credit limit is', 'kitchen-pos'); ?> <?php echo $currency . number_format(get_option('dkm_credit_limit', 5000), 2); ?></p>
            </div>
            <div class="tip-card">
                <strong>⚡ <?php _e('Keyboard Shortcuts', 'kitchen-pos'); ?></strong>
                <p><?php _e('Press F2 to open POS quickly', 'kitchen-pos'); ?></p>
            </div>
        </div>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    // Update time every second
    function updateTime() {
        const now = new Date();
        const options = { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        $('#current-time').text(now.toLocaleString('en-IN', options));
    }
    
    updateTime();
    setInterval(updateTime, 1000);
    
    // Keyboard shortcut: F2 to open POS
    $(document).on('keydown', function(e) {
        if (e.key === 'F2') {
            e.preventDefault();
            window.location.href = '<?php echo admin_url('admin.php?page=kitchen-pos&view=pos'); ?>';
        }
    });
});
</script>