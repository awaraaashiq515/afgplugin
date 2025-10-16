<?php
/**
 * Kitchen Products Management
 * Displays WooCommerce products marked as kitchen items
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('view_dkm_products')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get all kitchen products
$products = DKM_WooCommerce_Integration::get_kitchen_products();
$currency = get_option('dkm_currency_symbol', '₹');

?>

<div class="wrap dkm-products-page">
    <h1 class="wp-heading-inline"><?php _e('Kitchen Products', 'deshi-kitchen-admin'); ?></h1>
    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="page-title-action">
        <?php _e('Add New Product', 'deshi-kitchen-admin'); ?>
    </a>
    
    <div class="products-info">
        <div class="info-box">
            <span class="dashicons dashicons-info"></span>
            <div>
                <p><strong><?php _e('How to add kitchen products:', 'deshi-kitchen-admin'); ?></strong></p>
                <ol>
                    <li><?php _e('Go to WooCommerce → Products → Add New', 'deshi-kitchen-admin'); ?></li>
                    <li><?php _e('Fill in product details (name, price, stock)', 'deshi-kitchen-admin'); ?></li>
                    <li><?php _e('Scroll down to "Kitchen Item Settings" section', 'deshi-kitchen-admin'); ?></li>
                    <li><?php _e('Check "Kitchen Item" checkbox', 'deshi-kitchen-admin'); ?></li>
                    <li><?php _e('Fill in Unit, Cost Price, and Low Stock Threshold', 'deshi-kitchen-admin'); ?></li>
                    <li><?php _e('Save product - it will appear in POS automatically', 'deshi-kitchen-admin'); ?></li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="products-filters">
        <input type="text" id="product-search" placeholder="<?php _e('Search products...', 'deshi-kitchen-admin'); ?>">
        <select id="stock-filter">
            <option value=""><?php _e('All Stock Status', 'deshi-kitchen-admin'); ?></option>
            <option value="in_stock"><?php _e('In Stock', 'deshi-kitchen-admin'); ?></option>
            <option value="low_stock"><?php _e('Low Stock', 'deshi-kitchen-admin'); ?></option>
            <option value="out_of_stock"><?php _e('Out of Stock', 'deshi-kitchen-admin'); ?></option>
        </select>
    </div>
    
    <table class="wp-list-table widefat fixed striped products-table">
        <thead>
            <tr>
                <th><?php _e('Product', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('SKU', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Price', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Cost Price', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Stock', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Unit', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Status', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Actions', 'deshi-kitchen-admin'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)) : ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <p><?php _e('No kitchen products found.', 'deshi-kitchen-admin'); ?></p>
                        <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button button-primary">
                            <?php _e('Add Your First Product', 'deshi-kitchen-admin'); ?>
                        </a>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($products as $product) : 
                    $stock_qty = $product->get_stock_quantity();
                    $threshold = get_post_meta($product->get_id(), '_dkm_low_stock_threshold', true);
                    $unit = get_post_meta($product->get_id(), '_dkm_unit', true);
                    $cost_price = get_post_meta($product->get_id(), '_dkm_cost_price', true);
                    
                    if ($stock_qty === null) {
                        $stock_status = 'unlimited';
                        $stock_class = '';
                    } elseif ($stock_qty == 0) {
                        $stock_status = 'out_of_stock';
                        $stock_class = 'text-danger';
                    } elseif ($threshold && $stock_qty <= $threshold) {
                        $stock_status = 'low_stock';
                        $stock_class = 'text-warning';
                    } else {
                        $stock_status = 'in_stock';
                        $stock_class = 'text-success';
                    }
                    
                    $profit = ($cost_price && $cost_price > 0) ? $product->get_regular_price() - $cost_price : 0;
                ?>
                    <tr class="product-row" data-stock-status="<?php echo $stock_status; ?>">
                        <td>
                            <strong><?php echo esc_html($product->get_name()); ?></strong>
                            <?php if ($product->get_image_id()) : ?>
                                <br><?php echo $product->get_image('thumbnail'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($product->get_sku() ?: 'N/A'); ?></td>
                        <td>
                            <strong><?php echo $currency . number_format($product->get_regular_price(), 2); ?></strong>
                            <?php if ($profit > 0) : ?>
                                <br><small class="profit-margin">Profit: <?php echo $currency . number_format($profit, 2); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $cost_price ? $currency . number_format($cost_price, 2) : 'N/A'; ?></td>
                        <td>
                            <strong class="<?php echo $stock_class; ?>">
                                <?php echo $stock_qty !== null ? $stock_qty : '∞'; ?>
                            </strong>
                            <?php if ($stock_status === 'low_stock') : ?>
                                <br><small class="text-warning">Low Stock!</small>
                            <?php elseif ($stock_status === 'out_of_stock') : ?>
                                <br><small class="text-danger">Out of Stock!</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($unit ?: 'piece'); ?></td>
                        <td>
                            <?php if (get_post_meta($product->get_id(), '_dkm_is_public', true) === 'yes') : ?>
                                <span class="status-badge public">
                                    <span class="dashicons dashicons-visibility"></span> Public
                                </span>
                            <?php else : ?>
                                <span class="status-badge private">
                                    <span class="dashicons dashicons-hidden"></span> Private
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo get_edit_post_link($product->get_id()); ?>" class="button button-small">
                                <?php _e('Edit', 'deshi-kitchen-admin'); ?>
                            </a>
                            <a href="<?php echo get_permalink($product->get_id()); ?>" class="button button-small" target="_blank">
                                <?php _e('View', 'deshi-kitchen-admin'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="products-stats">
        <h3><?php _e('Quick Stats', 'deshi-kitchen-admin'); ?></h3>
        <div class="stats-grid">
            <div class="stat-item">
                <strong><?php echo count($products); ?></strong>
                <span><?php _e('Total Products', 'deshi-kitchen-admin'); ?></span>
            </div>
            <div class="stat-item">
                <strong><?php echo count(DKM_WooCommerce_Integration::get_low_stock_products()); ?></strong>
                <span><?php _e('Low/Out of Stock', 'deshi-kitchen-admin'); ?></span>
            </div>
            <div class="stat-item">
                <strong>
                    <?php 
                    $public_count = 0;
                    foreach ($products as $p) {
                        if (get_post_meta($p->get_id(), '_dkm_is_public', true) === 'yes') {
                            $public_count++;
                        }
                    }
                    echo $public_count;
                    ?>
                </strong>
                <span><?php _e('Public Products', 'deshi-kitchen-admin'); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Search filter
    $('#product-search').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.product-row').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });
    
    // Stock filter
    $('#stock-filter').on('change', function() {
        const filter = $(this).val();
        $('.product-row').each(function() {
            if (filter === '') {
                $(this).show();
            } else {
                $(this).toggle($(this).data('stock-status') === filter);
            }
        });
    });
});
</script>

<style>
.products-info {
    background: #e7f3ff;
    border-left: 4px solid #2271b1;
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.info-box {
    display: flex;
    gap: 15px;
}

.info-box .dashicons {
    font-size: 24px;
    color: #2271b1;
}

.info-box ol {
    margin: 10px 0 0 20px;
}

.products-filters {
    background: #fff;
    padding: 15px 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
    display: flex;
    gap: 15px;
}

.products-filters input,
.products-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    flex: 1;
}

.profit-margin {
    color: #2e7d32;
    font-weight: 600;
}

.text-success {
    color: #2e7d32;
}

.text-warning {
    color: #ff9800;
}

.text-danger {
    color: #c62828;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-badge.public {
    background: #e8f5e9;
    color: #2e7d32;
}

.status-badge.private {
    background: #f5f5f5;
    color: #666;
}

.products-stats {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.products-stats h3 {
    margin: 0 0 15px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 6px;
}

.stat-item strong {
    display: block;
    font-size: 28px;
    color: #2271b1;
    margin-bottom: 5px;
}

.stat-item span {
    font-size: 13px;
    color: #666;
}
</style>