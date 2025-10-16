<?php
/**
 * POS Screen View
 * Main POS interface for sales
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!KPOS_Permissions::can_access_pos()) {
    wp_die(__('You do not have permission to access this page.', 'kitchen-pos'));
}

$currency = get_option('dkm_currency_symbol', '₹');
$credit_limit = get_option('dkm_credit_limit', 5000);
?>

<div class="kpos-wrapper">
    
    <!-- Header -->
    <div class="kpos-header">
        <div class="kpos-logo">
            <h1><?php echo get_bloginfo('name'); ?></h1>
            <span class="kpos-subtitle"><?php _e('Point of Sale', 'kitchen-pos'); ?></span>
        </div>
        
        <div class="kpos-user-info">
            <span class="kpos-operator-name">
                <?php 
                $user = wp_get_current_user();
                echo esc_html($user->display_name); 
                ?>
            </span>
            <span class="kpos-datetime" id="kpos-datetime"></span>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="kpos-logout">
                <span class="dashicons dashicons-exit"></span>
                <?php _e('Logout', 'kitchen-pos'); ?>
            </a>
        </div>
    </div>

    <div class="kpos-container">
        
        <!-- Left Panel: Trainee Search & Selection -->
        <div class="kpos-left-panel">
            <div class="kpos-section">
                <h3><?php _e('Select Trainee', 'kitchen-pos'); ?></h3>
                
                <div class="kpos-search-box">
                    <input 
                        type="text" 
                        id="trainee-search" 
                        placeholder="<?php _e('Search by name, email, phone...', 'kitchen-pos'); ?>"
                        autocomplete="off"
                    />
                    <span class="dashicons dashicons-search"></span>
                </div>

                <!-- Search Results -->
                <div id="trainee-search-results" class="kpos-search-results"></div>

                <!-- Selected Trainee -->
                <div id="selected-trainee" class="kpos-selected-trainee" style="display:none;">
                    <div class="trainee-card">
                        <div class="trainee-avatar">
                            <img id="trainee-avatar" src="" alt="">
                        </div>
                        <div class="trainee-info">
                            <h4 id="trainee-name"></h4>
                            <p id="trainee-email"></p>
                            <p id="trainee-phone"></p>
                        </div>
                        <button type="button" class="kpos-btn-remove" id="clear-trainee">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    
                    <div class="trainee-balance">
                        <div class="balance-item">
                            <span><?php _e('Current Balance:', 'kitchen-pos'); ?></span>
                            <strong id="trainee-balance" class="balance-amount">₹0.00</strong>
                        </div>
                        <div class="balance-item">
                            <span><?php _e('Credit Limit:', 'kitchen-pos'); ?></span>
                            <strong><?php echo $currency . number_format($credit_limit, 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="kpos-section">
                <h3><?php _e('Recent Orders', 'kitchen-pos'); ?></h3>
                <div id="recent-orders" class="kpos-recent-orders">
                    <p class="kpos-no-data"><?php _e('No recent orders', 'kitchen-pos'); ?></p>
                </div>
            </div>
        </div>

        <!-- Center Panel: Products -->
        <div class="kpos-center-panel">
            <div class="kpos-section">
                <div class="kpos-section-header">
                    <h3><?php _e('Products', 'kitchen-pos'); ?></h3>
                    
                    <!-- Category Filter -->
                    <div class="kpos-category-filter">
                        <button type="button" class="kpos-category-btn active" data-category="">
                            <?php _e('All', 'kitchen-pos'); ?>
                        </button>
                        <div id="category-buttons"></div>
                    </div>
                    
                    <!-- Product Search -->
                    <div class="kpos-product-search">
                        <input 
                            type="text" 
                            id="product-search" 
                            placeholder="<?php _e('Search products...', 'kitchen-pos'); ?>"
                        />
                    </div>
                </div>

                <!-- Products Grid -->
                <div id="products-grid" class="kpos-products-grid">
                    <div class="kpos-loading">
                        <span class="dashicons dashicons-update spin"></span>
                        <?php _e('Loading products...', 'kitchen-pos'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Cart & Checkout -->
        <div class="kpos-right-panel">
            <div class="kpos-section">
                <h3><?php _e('Order Cart', 'kitchen-pos'); ?></h3>
                
                <!-- Cart Items -->
                <div id="cart-items" class="kpos-cart-items">
                    <div class="kpos-empty-cart">
                        <span class="dashicons dashicons-cart"></span>
                        <p><?php _e('Cart is empty', 'kitchen-pos'); ?></p>
                        <small><?php _e('Add products to get started', 'kitchen-pos'); ?></small>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="kpos-cart-summary">
                    <div class="summary-row">
                        <span><?php _e('Subtotal:', 'kitchen-pos'); ?></span>
                        <strong id="cart-subtotal"><?php echo $currency; ?>0.00</strong>
                    </div>
                    <div class="summary-row total">
                        <span><?php _e('Total:', 'kitchen-pos'); ?></span>
                        <strong id="cart-total"><?php echo $currency; ?>0.00</strong>
                    </div>
                </div>

                
<!-- Payment Methods (Dynamic from WooCommerce) -->
<!-- Payment Methods (Dynamic from WooCommerce) -->
<div class="kpos-payment-methods">
    <h4><?php _e('Payment Method', 'kitchen-pos'); ?></h4>
    <div class="payment-options">
        <?php
        // Get available payment gateways
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        if (empty($available_gateways)) {
            echo '<p class="kpos-no-data">' . __('No payment methods available', 'kitchen-pos') . '</p>';
        } else {
            // Add Credit option first (custom)
            ?>
            <label class="payment-option">
                <input type="radio" name="payment_method" value="credit" checked>
                <span class="payment-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </span>
                <span class="payment-label"><?php _e('Credit Account', 'kitchen-pos'); ?></span>
            </label>
            
            <?php
            // Then show WooCommerce payment gateways
            foreach ($available_gateways as $gateway) :
                // Skip if gateway is not enabled
                if ($gateway->enabled !== 'yes') {
                    continue;
                }
            ?>
            <label class="payment-option">
                <input type="radio" name="payment_method" value="<?php echo esc_attr($gateway->id); ?>">
                <span class="payment-icon">
                    <?php if ($gateway->icon) : ?>
                        <img src="<?php echo esc_url($gateway->icon); ?>" alt="<?php echo esc_attr($gateway->get_title()); ?>" style="max-height:24px;">
                    <?php else : ?>
                        <span class="dashicons dashicons-cart"></span>
                    <?php endif; ?>
                </span>
                <span class="payment-label"><?php echo esc_html($gateway->get_title()); ?></span>
            </label>
            <?php 
            endforeach;
        }
        ?>
    </div>
</div>

<!-- Order Note -->
                <div class="kpos-order-note">
                    <textarea 
                        id="order-note" 
                        placeholder="<?php _e('Add note (optional)', 'kitchen-pos'); ?>"
                        rows="2"
                    ></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="kpos-actions">
                    <button type="button" id="clear-cart" class="kpos-btn kpos-btn-secondary">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clear Cart', 'kitchen-pos'); ?>
                    </button>
                    <button type="button" id="complete-sale" class="kpos-btn kpos-btn-primary" disabled>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Complete Sale', 'kitchen-pos'); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Order Success Modal -->
<div id="order-success-modal" class="kpos-modal" style="display:none;">
    <div class="kpos-modal-content">
        <div class="kpos-modal-header success">
            <span class="dashicons dashicons-yes-alt"></span>
            <h2><?php _e('Order Completed!', 'kitchen-pos'); ?></h2>
        </div>
        <div class="kpos-modal-body">
            <div class="order-success-details">
                <p><strong><?php _e('Order Number:', 'kitchen-pos'); ?></strong> <span id="success-order-number"></span></p>
                <p><strong><?php _e('Total:', 'kitchen-pos'); ?></strong> <span id="success-total"></span></p>
                <p><strong><?php _e('Payment:', 'kitchen-pos'); ?></strong> <span id="success-payment"></span></p>
                <p id="success-new-balance" style="display:none;">
                    <strong><?php _e('New Balance:', 'kitchen-pos'); ?></strong> 
                    <span id="success-balance-amount"></span>
                </p>
            </div>
        </div>
        <div class="kpos-modal-footer">
            <button type="button" class="kpos-btn kpos-btn-secondary" id="print-receipt">
                <span class="dashicons dashicons-printer"></span>
                <?php _e('Print Receipt', 'kitchen-pos'); ?>
            </button>
            <button type="button" class="kpos-btn kpos-btn-primary" id="new-order">
                <?php _e('New Order', 'kitchen-pos'); ?>
            </button>
        </div>
    </div>
</div>

<script>
// Pass PHP variables to JavaScript
var kposConfig = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('kpos_nonce'); ?>',
    currency: '<?php echo $currency; ?>',
    creditLimit: <?php echo $credit_limit; ?>
};
</script>