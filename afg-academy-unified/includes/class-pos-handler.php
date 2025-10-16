<?php
/**
 * KPOS Handler Class
 * Handles POS data operations and AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class KPOS_Handler {
    
    /**
     * AJAX: Search trainee
     */
    public static function ajax_search_trainee() {
        KPOS_Permissions::check_ajax_permission();
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_error(array(
                'message' => __('Search term required', 'kitchen-pos')
            ));
        }
        
        // Use DKM_Trainee_Ledger for search
        if (!class_exists('DKM_Trainee_Ledger')) {
            wp_send_json_error(array(
                'message' => __('Admin plugin not found', 'kitchen-pos')
            ));
        }
        
        $results = DKM_Trainee_Ledger::search_trainees($search);
        
        wp_send_json_success(array(
            'trainees' => $results
        ));
    }
    
    /**
     * AJAX: Get products for POS
     */
    public static function ajax_get_products() {
        KPOS_Permissions::check_ajax_permission();
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        
        // Use DKM_WooCommerce_Integration to get products
        if (!class_exists('DKM_WooCommerce_Integration')) {
            wp_send_json_error(array(
                'message' => __('Admin plugin not found', 'kitchen-pos')
            ));
        }
        
        $products = DKM_WooCommerce_Integration::get_products_for_pos();
        
        // Filter by category if provided
        if (!empty($category)) {
            $products = array_filter($products, function($product) use ($category) {
                return $product['category'] === $category;
            });
        }
        
        // Get categories
        $categories = array_unique(array_column($products, 'category'));
        
        wp_send_json_success(array(
            'products' => array_values($products),
            'categories' => array_values($categories),
            'total' => count($products)
        ));
    }
    
    /**
     * AJAX: Get trainee balance
     */
    public static function ajax_get_trainee_balance() {
        KPOS_Permissions::check_ajax_permission();
        
        $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;
        
        if (!$trainee_id) {
            wp_send_json_error(array(
                'message' => __('Trainee ID required', 'kitchen-pos')
            ));
        }
        
        if (!class_exists('DKM_Trainee_Ledger')) {
            wp_send_json_error(array(
                'message' => __('Admin plugin not found', 'kitchen-pos')
            ));
        }
        
        $balance = DKM_Trainee_Ledger::get_balance($trainee_id);
        $summary = DKM_Trainee_Ledger::get_summary($trainee_id);
        $user = get_userdata($trainee_id);
        
        wp_send_json_success(array(
            'trainee_id' => $trainee_id,
            'name' => $user ? $user->display_name : '',
            'email' => $user ? $user->user_email : '',
            'phone' => get_user_meta($trainee_id, 'billing_phone', true),
            'balance' => $balance,
            'summary' => $summary,
            'credit_limit' => get_option('dkm_credit_limit', 5000)
        ));
    }
    
    /**
     * Get product by ID
     */
    public static function get_product($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return null;
        }
        
        // Check if kitchen item
        $is_kitchen = get_post_meta($product_id, '_dkm_is_kitchen_item', true);
        if ($is_kitchen !== 'yes') {
            return null;
        }
        
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
        
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_regular_price(),
            'stock' => $product->get_stock_quantity(),
            'unit' => get_post_meta($product_id, '_dkm_unit', true) ?: 'piece',
            'image' => $image_url,
            'in_stock' => $product->is_in_stock()
        );
    }
    
    /**
     * Validate cart items
     */
    public static function validate_cart($cart_items) {
        if (empty($cart_items)) {
            return array(
                'valid' => false,
                'message' => __('Cart is empty', 'kitchen-pos')
            );
        }
        
        foreach ($cart_items as $item) {
            $product = wc_get_product($item['product_id']);
            
            if (!$product) {
                return array(
                    'valid' => false,
                    'message' => sprintf(__('Product #%d not found', 'kitchen-pos'), $item['product_id'])
                );
            }
            
            // Check stock
            $stock = $product->get_stock_quantity();
            if ($stock !== null && $stock < $item['quantity']) {
                return array(
                    'valid' => false,
                    'message' => sprintf(
                        __('%s: Only %d in stock', 'kitchen-pos'),
                        $product->get_name(),
                        $stock
                    )
                );
            }
        }
        
        return array('valid' => true);
    }
    
    /**
     * Calculate cart total
     */
    public static function calculate_cart_total($cart_items) {
        $total = 0;
        
        foreach ($cart_items as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product) {
                $total += floatval($product->get_regular_price()) * intval($item['quantity']);
            }
        }
        
        return $total;
    }
    
    /**
     * Check credit limit
     */
    public static function check_credit_limit($trainee_id, $amount) {
        if (!class_exists('DKM_Trainee_Ledger')) {
            return array(
                'allowed' => false,
                'message' => __('Admin plugin not found', 'kitchen-pos')
            );
        }
        
        $current_balance = DKM_Trainee_Ledger::get_balance($trainee_id);
        $new_balance = $current_balance + $amount;
        $credit_limit = get_option('dkm_credit_limit', 5000);
        
        if ($new_balance > $credit_limit) {
            return array(
                'allowed' => false,
                'current_balance' => $current_balance,
                'new_balance' => $new_balance,
                'credit_limit' => $credit_limit,
                'message' => sprintf(
                    __('Credit limit exceeded! Current: %s, New: %s, Limit: %s', 'kitchen-pos'),
                    get_option('dkm_currency_symbol', '₹') . number_format($current_balance, 2),
                    get_option('dkm_currency_symbol', '₹') . number_format($new_balance, 2),
                    get_option('dkm_currency_symbol', '₹') . number_format($credit_limit, 2)
                )
            );
        }
        
        return array(
            'allowed' => true,
            'current_balance' => $current_balance,
            'new_balance' => $new_balance,
            'credit_limit' => $credit_limit
        );
    }
    
    /**
     * Get recent orders
     */
    public static function get_recent_orders($limit = 10) {
        $args = array(
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_kpos_order',
            'meta_value' => 'yes'
        );
        
        return wc_get_orders($args);
    }
    
    /**
     * Get today's stats
     */
    public static function get_today_stats() {
        $today = date('Y-m-d');
        
        $args = array(
            'limit' => -1,
            'date_created' => $today,
            'meta_key' => '_kpos_order',
            'meta_value' => 'yes'
        );
        
        $orders = wc_get_orders($args);
        
        $total_sales = 0;
        $cash_sales = 0;
        $credit_sales = 0;
        
        foreach ($orders as $order) {
            $amount = $order->get_total();
            $total_sales += $amount;
            
            $payment_method = $order->get_payment_method();
            if ($payment_method === 'cod') {
                $cash_sales += $amount;
            } else {
                $credit_sales += $amount;
            }
        }
        
        return array(
            'total_orders' => count($orders),
            'total_sales' => $total_sales,
            'cash_sales' => $cash_sales,
            'credit_sales' => $credit_sales
        );
    }
}