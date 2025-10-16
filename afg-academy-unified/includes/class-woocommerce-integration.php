<?php
/**
 * WooCommerce Integration Class - COMPLETE FIXED VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_WooCommerce_Integration {
    
    public function __construct() {
        // Add custom product fields
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_custom_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_product_fields'));
        
        // Add kitchen item category
        add_action('init', array($this, 'create_kitchen_category'));
        
        // Add custom columns to products list
        add_filter('manage_edit-product_columns', array($this, 'add_product_columns'));
        add_action('manage_product_posts_custom_column', array($this, 'render_product_columns'), 10, 2);
        
        // Stock management hooks
        add_action('woocommerce_reduce_order_stock', array($this, 'log_stock_reduction'));
        add_action('woocommerce_restore_order_stock', array($this, 'log_stock_restoration'));
        
        // Low stock notification
        add_action('woocommerce_low_stock', array($this, 'send_low_stock_alert'));
        add_action('woocommerce_no_stock', array($this, 'send_out_of_stock_alert'));
        
        // FIXED: Register AJAX actions properly
        add_action('wp_ajax_kpos_add_to_wc_cart', array($this, 'ajax_add_to_wc_cart'));
        add_action('wp_ajax_kpos_get_wc_cart', array($this, 'ajax_get_wc_cart'));
        add_action('wp_ajax_kpos_update_cart_qty', array($this, 'ajax_update_cart_qty'));
        add_action('wp_ajax_kpos_remove_from_cart', array($this, 'ajax_remove_from_cart'));
        add_action('wp_ajax_kpos_complete_wc_checkout', array($this, 'ajax_complete_wc_checkout'));
        
        // FIXED: Initialize WooCommerce cart session for AJAX
        add_action('wp_loaded', array($this, 'init_wc_cart_session'));
    }
    
    /**
     * FIXED: Initialize WooCommerce cart session
     */
    public function init_wc_cart_session() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            if (!WC()->session->has_session()) {
                WC()->session->set_customer_session_cookie(true);
            }
        }
    }

    /**
     * Add custom fields to product edit page
     */
    public function add_custom_product_fields() {
        global $post;
        
        echo '<div class="options_group dkm_kitchen_fields">';
        
        echo '<h3 style="padding-left: 12px; margin-bottom: 10px;">' . __('Kitchen Item Settings', 'deshi-kitchen-admin') . '</h3>';
        
        // Is Kitchen Item
        woocommerce_wp_checkbox(array(
            'id' => '_dkm_is_kitchen_item',
            'label' => __('Kitchen Item', 'deshi-kitchen-admin'),
            'description' => __('Enable this if product is available at kitchen counter (POS)', 'deshi-kitchen-admin')
        ));
        
        // Is Public (show on website)
        woocommerce_wp_checkbox(array(
            'id' => '_dkm_is_public',
            'label' => __('Show on Website', 'deshi-kitchen-admin'),
            'description' => __('Display this item on public kitchen menu page', 'deshi-kitchen-admin')
        ));
        
        // Unit
        woocommerce_wp_text_input(array(
            'id' => '_dkm_unit',
            'label' => __('Unit', 'deshi-kitchen-admin'),
            'placeholder' => 'piece, cup, plate, glass',
            'desc_tip' => true,
            'description' => __('Unit of measurement (e.g., piece, cup, plate, glass, serving)', 'deshi-kitchen-admin')
        ));
        
        // Cost Price
        woocommerce_wp_text_input(array(
            'id' => '_dkm_cost_price',
            'label' => __('Cost Price', 'deshi-kitchen-admin') . ' (' . get_woocommerce_currency_symbol() . ')',
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            ),
            'desc_tip' => true,
            'description' => __('Product cost price for profit calculation', 'deshi-kitchen-admin')
        ));
        
        // Low Stock Threshold (custom)
        woocommerce_wp_text_input(array(
            'id' => '_dkm_low_stock_threshold',
            'label' => __('Low Stock Alert Threshold', 'deshi-kitchen-admin'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min' => '0'
            ),
            'desc_tip' => true,
            'description' => __('Alert when stock falls below this number (overrides WooCommerce default)', 'deshi-kitchen-admin')
        ));
        
        // Profit margin (calculated)
        $cost_price = get_post_meta($post->ID, '_dkm_cost_price', true);
        $sale_price = get_post_meta($post->ID, '_regular_price', true);
        
        if ($cost_price && $sale_price && $cost_price > 0) {
            $profit = $sale_price - $cost_price;
            $profit_percent = ($profit / $cost_price) * 100;
            
            echo '<p class="form-field" style="padding-left: 12px;">';
            echo '<strong>' . __('Profit Margin:', 'deshi-kitchen-admin') . '</strong> ';
            echo '<span style="color: ' . ($profit > 0 ? 'green' : 'red') . ';">';
            echo get_woocommerce_currency_symbol() . number_format($profit, 2);
            echo ' (' . number_format($profit_percent, 2) . '%)';
            echo '</span>';
            echo '</p>';
        }
        
        echo '</div>';
        
        // Add CSS for styling
        ?>
        <style>
        .dkm_kitchen_fields {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 4px;
            margin-top: 12px;
        }
        .dkm_kitchen_fields h3 {
            color: #2271b1;
        }
        </style>
        <?php
    }
    
    /**
     * Save custom product fields
     */
    public function save_custom_product_fields($post_id) {
        // Kitchen item checkbox
        $is_kitchen_item = isset($_POST['_dkm_is_kitchen_item']) ? 'yes' : 'no';
        update_post_meta($post_id, '_dkm_is_kitchen_item', $is_kitchen_item);
        
        // Public checkbox
        $is_public = isset($_POST['_dkm_is_public']) ? 'yes' : 'no';
        update_post_meta($post_id, '_dkm_is_public', $is_public);
        
        // Unit
        if (isset($_POST['_dkm_unit'])) {
            update_post_meta($post_id, '_dkm_unit', sanitize_text_field($_POST['_dkm_unit']));
        }
        
        // Cost price
        if (isset($_POST['_dkm_cost_price'])) {
            update_post_meta($post_id, '_dkm_cost_price', sanitize_text_field($_POST['_dkm_cost_price']));
        }
        
        // Low stock threshold
        if (isset($_POST['_dkm_low_stock_threshold'])) {
            update_post_meta($post_id, '_dkm_low_stock_threshold', absint($_POST['_dkm_low_stock_threshold']));
        }
    }
    
    /**
     * Create Kitchen Items category
     */
    public function create_kitchen_category() {
        if (!taxonomy_exists('product_cat')) {
            return;
        }
        
        $term = term_exists('Kitchen Items', 'product_cat');
        
        if (!$term) {
            wp_insert_term(
                'Kitchen Items',
                'product_cat',
                array(
                    'description' => 'Internal kitchen items available at counter for trainees',
                    'slug' => 'kitchen-items'
                )
            );
        }
    }
    
    /**
     * Add custom columns to products list
     */
    public function add_product_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add after 'name' column
            if ($key === 'name') {
                $new_columns['dkm_kitchen_item'] = __('Kitchen', 'deshi-kitchen-admin');
                $new_columns['dkm_unit'] = __('Unit', 'deshi-kitchen-admin');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function render_product_columns($column, $post_id) {
        switch ($column) {
            case 'dkm_kitchen_item':
                $is_kitchen = get_post_meta($post_id, '_dkm_is_kitchen_item', true);
                if ($is_kitchen === 'yes') {
                    echo '<span class="dashicons dashicons-yes" style="color: green;" title="Kitchen Item"></span>';
                } else {
                    echo '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>';
                }
                break;
                
            case 'dkm_unit':
                $unit = get_post_meta($post_id, '_dkm_unit', true);
                echo $unit ? esc_html($unit) : '—';
                break;
        }
    }
    
    /**
     * Get all kitchen products
     */
    public static function get_kitchen_products($args = array()) {
        $defaults = array(
            'status' => 'publish',
            'limit' => -1,
            'meta_query' => array(
                array(
                    'key' => '_dkm_is_kitchen_item',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return wc_get_products($args);
    }
    
    /**
     * Get products for POS (formatted)
     */
    public static function get_products_for_pos() {
        if (!class_exists('WooCommerce')) {
            return array();
        }
        
        $products = self::get_kitchen_products();
        $formatted = array();
        
        foreach ($products as $product) {
            // Skip variable products
            if ($product->get_type() !== 'simple') {
                continue;
            }
            
            // Get category
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
            $category = !empty($categories) ? $categories[0] : 'Uncategorized';
            
            // FIXED: Get image URL properly
            $image_id = $product->get_image_id();
            if (!$image_id) {
                $gallery = $product->get_gallery_image_ids();
                $image_id = !empty($gallery) ? $gallery[0] : 0;
            }
            $image_url = $image_id ? wp_get_attachment_url($image_id) : wc_placeholder_img_src();
            
            // Get unit
            $unit = get_post_meta($product->get_id(), '_dkm_unit', true);
            if (empty($unit)) {
                $unit = 'piece';
            }
            
            // Get stock
            $stock_qty = $product->get_stock_quantity();
            if ($stock_qty === null) {
                $stock_qty = 999; // Unlimited stock
            }
            
            $formatted[] = array(
                'id' => $product->get_id(),
                'title' => $product->get_name(),
                'sku' => $product->get_sku() ?: 'N/A',
                'price' => number_format($product->get_regular_price(), 2),
                'price_raw' => floatval($product->get_regular_price()),
                'cost_price' => floatval(get_post_meta($product->get_id(), '_dkm_cost_price', true)),
                'stock_qty' => intval($stock_qty),
                'unit' => $unit,
                'category' => $category,
                'image_url' => $image_url,
                'in_stock' => $product->is_in_stock() 
            );
        }
        
        return $formatted;
    }
    
    /**
     * Reduce stock and log movement
     */
    public static function reduce_stock($product_id, $quantity, $order_note = '', $order_id = null) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        $stock_before = $product->get_stock_quantity();
        if ($stock_before === null) {
            return true;
        }
        
        $new_stock = wc_update_product_stock($product, $quantity, 'decrease');
        
        self::log_stock_movement(
            $product_id,
            -$quantity,
            $stock_before,
            $new_stock,
            'sale',
            $order_note,
            $order_id
        );
        
        self::check_low_stock($product_id, $new_stock);
        
        return $new_stock;
    }
    
    /**
     * Increase stock and log movement
     */
    public static function increase_stock($product_id, $quantity, $reason = '', $reference_id = null) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        $stock_before = $product->get_stock_quantity();
        if ($stock_before === null) {
            $stock_before = 0;
        }
        
        $new_stock = wc_update_product_stock($product, $quantity, 'increase');
        
        self::log_stock_movement(
            $product_id,
            $quantity,
            $stock_before,
            $new_stock,
            'purchase',
            $reason,
            $reference_id
        );
        
        return $new_stock;
    }
    
    /**
     * Log stock movement to database
     */
    private static function log_stock_movement($product_id, $change_qty, $stock_before, $stock_after, $type, $reason, $reference_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'kitchen_stock_movements';
        
        $wpdb->insert(
            $table,
            array(
                'product_id' => $product_id,
                'change_qty' => $change_qty,
                'stock_before' => $stock_before,
                'stock_after' => $stock_after,
                'movement_type' => $type,
                'reason' => $reason,
                'reference_id' => $reference_id,
                'created_by' => get_current_user_id()
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d')
        );
    }
    
    /**
     * Log stock reduction from order
     */
    public function log_stock_reduction($order) {
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
        }
    }
    
    /**
     * Log stock restoration
     */
    public function log_stock_restoration($order) {
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            
            $product = wc_get_product($product_id);
            if ($product) {
                $stock_before = $product->get_stock_quantity() - $quantity;
                $stock_after = $product->get_stock_quantity();
                
                self::log_stock_movement(
                    $product_id,
                    $quantity,
                    $stock_before,
                    $stock_after,
                    'return',
                    'Order cancelled/refunded - #' . $order->get_order_number(),
                    $order->get_id()
                );
            }
        }
    }
    
    /**
     * Check low stock and send alert
     */
    private static function check_low_stock($product_id, $current_stock) {
        $threshold = get_post_meta($product_id, '_dkm_low_stock_threshold', true);
        
        if (!$threshold) {
            $threshold = get_option('dkm_low_stock_threshold', 10);
        }
        
        if ($current_stock <= $threshold && $current_stock > 0) {
            $product = wc_get_product($product_id);
            if ($product && class_exists('DKM_WhatsApp')) {
                DKM_WhatsApp::send_low_stock_alert($product->get_name(), $current_stock);
            }
        }
    }
    
    /**
     * Send low stock alert
     */
    public function send_low_stock_alert($product) {
        if (class_exists('DKM_WhatsApp')) {
            $stock = $product->get_stock_quantity();
            DKM_WhatsApp::send_low_stock_alert($product->get_name(), $stock);
        }
    }
    
    /**
     * Send out of stock alert
     */
    public function send_out_of_stock_alert($product) {
        if (class_exists('DKM_WhatsApp')) {
            DKM_WhatsApp::send_low_stock_alert($product->get_name(), 0);
        }
    }
    
    /**
     * Get product by SKU
     */
    public static function get_product_by_sku($sku) {
        $product_id = wc_get_product_id_by_sku($sku);
        return $product_id ? wc_get_product($product_id) : false;
    }
    
    /**
     * Calculate profit for product
     */
    public static function calculate_profit($product_id, $quantity = 1) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }
        
        $sale_price = floatval($product->get_regular_price());
        $cost_price = floatval(get_post_meta($product_id, '_dkm_cost_price', true));
        
        if ($cost_price > 0) {
            return ($sale_price - $cost_price) * $quantity;
        }
        
        return 0;
    }
    
    /**
     * Get stock movement history
     */
    public static function get_stock_history($product_id, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'kitchen_stock_movements';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE product_id = %d 
            ORDER BY id DESC 
            LIMIT %d",
            $product_id, $limit
        ));
    }
    
    /**
     * Bulk update kitchen items
     */
    public static function bulk_mark_as_kitchen_items($product_ids) {
        foreach ($product_ids as $product_id) {
            update_post_meta($product_id, '_dkm_is_kitchen_item', 'yes');
        }
        
        return count($product_ids);
    }
    
    /**
     * Get products low on stock
     */
    public static function get_low_stock_products() {
        global $wpdb;
        
        $products = self::get_kitchen_products();
        $low_stock = array();
        
        foreach ($products as $product) {
            $stock_qty = $product->get_stock_quantity();
            if ($stock_qty === null) {
                continue;
            }
            
            $threshold = get_post_meta($product->get_id(), '_dkm_low_stock_threshold', true);
            if (!$threshold) {
                $threshold = get_option('dkm_low_stock_threshold', 10);
            }
            
            if ($stock_qty <= $threshold) {
                $low_stock[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'stock' => $stock_qty,
                    'threshold' => $threshold,
                    'status' => $stock_qty == 0 ? 'out_of_stock' : 'low_stock'
                );
            }
        }
        
        return $low_stock;
    }
    
    /**
     * FIXED: AJAX Add to WooCommerce Cart
     */
    public function ajax_add_to_wc_cart() {
        check_ajax_referer('kpos_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => 'Invalid product'));
        }
        
        if (!WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not initialized'));
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => 'Product not found'));
        }
        
        if (!$product->is_in_stock()) {
            wp_send_json_error(array('message' => 'Product out of stock'));
        }
        
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
        
        if ($cart_item_key) {
            wp_send_json_success(array(
                'message' => 'Added to cart',
                'cart_key' => $cart_item_key,
                'cart_count' => WC()->cart->get_cart_contents_count()
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to add to cart'));
        }
    }

    /**
     * FIXED: AJAX Get WooCommerce Cart
     */
    public function ajax_get_wc_cart() {
        check_ajax_referer('kpos_nonce', 'nonce');
        
        if (!WC()->cart) {
            wp_send_json_error(array('message' => 'Cart not initialized'));
        }
        
        $cart_items = array();
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            $image_id = $product->get_image_id();
            if (!$image_id) {
                $gallery = $product->get_gallery_image_ids();
                $image_id = !empty($gallery) ? $gallery[0] : 0;
            }
            $image_url = $image_id ? wp_get_attachment_url($image_id) : wc_placeholder_img_src();
            
            $cart_items[] = array(
                'cart_key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'name' => $product->get_name(),
                'price' => floatval($product->get_price()),
                'quantity' => $cart_item['quantity'],
                'image' => $image_url,
                'subtotal' => floatval($cart_item['line_subtotal'])
            );
        }
        
        wp_send_json_success(array(
            'items' => $cart_items,
            'total' => floatval(WC()->cart->get_cart_contents_total()),
            'count' => WC()->cart->get_cart_contents_count()
        ));
    }

    /**
     * FIXED: AJAX Update Cart Quantity
     */
    public function ajax_update_cart_qty() {
        check_ajax_referer('kpos_nonce', 'nonce');
        
        $cart_key = isset($_POST['cart_key']) ? sanitize_text_field($_POST['cart_key']) : '';
        $change = isset($_POST['change']) ? intval($_POST['change']) : 0;
        
        if (!$cart_key) {
            wp_send_json_error(array('message' => 'Invalid cart key'));
        }
        
        $cart_item = WC()->cart->get_cart_item($cart_key);
        
        if ($cart_item) {
            $new_quantity = $cart_item['quantity'] + $change;
            
            if ($new_quantity <= 0) {
                WC()->cart->remove_cart_item($cart_key);
                wp_send_json_success(array('message' => 'Item removed'));
            } else {
                WC()->cart->set_quantity($cart_key, $new_quantity);
                wp_send_json_success(array('message' => 'Cart updated'));
            }
        } else {
            wp_send_json_error(array('message' => 'Item not found'));
        }
    }

    /**
     * FIXED: AJAX Remove from Cart
     */
    public function ajax_remove_from_cart() {
        check_ajax_referer('kpos_nonce', 'nonce');
        
        $cart_key = isset($_POST['cart_key']) ? sanitize_text_field($_POST['cart_key']) : '';
        
        if (WC()->cart->remove_cart_item($cart_key)) {
            wp_send_json_success(array('message' => 'Item removed'));
        } else {
            wp_send_json_error(array('message' => 'Failed to remove'));
        }
    }

    /**
     * FIXED: AJAX Complete WooCommerce Checkout
     */
/**
 * AJAX Complete WooCommerce Checkout - WITH PAYMENT GATEWAY
 */
/**
 * AJAX Complete WooCommerce Checkout - WITH PAYMENT GATEWAY
 */
public function ajax_complete_wc_checkout() {
    check_ajax_referer('kpos_nonce', 'nonce');
    
    $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'cod';
    $order_note = isset($_POST['order_note']) ? sanitize_textarea_field($_POST['order_note']) : '';
    
    // Validate
    if (!$trainee_id) {
        wp_send_json_error(array('message' => 'Please select a trainee'));
    }
    
    $trainee = get_userdata($trainee_id);
    if (!$trainee) {
        wp_send_json_error(array('message' => 'Invalid trainee'));
    }
    
    if (!WC()->cart || WC()->cart->is_empty()) {
        wp_send_json_error(array('message' => 'Cart is empty'));
    }
    
    try {
        // Set customer for checkout
        WC()->customer->set_id($trainee_id);
        WC()->customer->set_billing_email($trainee->user_email);
        WC()->customer->set_billing_first_name($trainee->display_name);
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if ($phone) {
            WC()->customer->set_billing_phone($phone);
        }
        
        // Create order from cart
        $checkout = WC()->checkout();
        
        // Prepare checkout data
        $data = array(
            'payment_method' => $payment_method,
            'billing_email' => $trainee->user_email,
            'billing_first_name' => $trainee->display_name,
            'billing_phone' => $phone,
            'order_comments' => $order_note
        );
        
        // Process checkout
        $order_id = $checkout->create_order($data);
        
        if (is_wp_error($order_id)) {
            wp_send_json_error(array('message' => $order_id->get_error_message()));
        }
        
        $order = wc_get_order($order_id);
        
        // Add KPOS metadata
        $order->update_meta_data('_kpos_order', 'yes');
        $order->update_meta_data('_kpos_operator', get_current_user_id());
        $order->save();
        
        // Handle credit payment
        if ($payment_method === 'credit') {
            $order->update_status('on-hold', 'Awaiting payment - Credit');
            
            if (class_exists('DKM_Trainee_Ledger')) {
                DKM_Trainee_Ledger::add_charge(
                    $trainee_id,
                    $order->get_total(),
                    'order',
                    $order->get_id(),
                    sprintf('POS Order #%s', $order->get_order_number())
                );
            }
            
            $new_balance = DKM_Trainee_Ledger::get_balance($trainee_id);
            
            // Clear cart
            WC()->cart->empty_cart();
            
            wp_send_json_success(array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total' => floatval($order->get_total()),
                'payment_method' => 'credit',
                'status' => $order->get_status(),
                'new_balance' => $new_balance,
                'message' => sprintf('Order #%s created successfully!', $order->get_order_number())
            ));
        }
        
        // For online payments - redirect to payment page
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        if (isset($available_gateways[$payment_method])) {
            $gateway = $available_gateways[$payment_method];
            
            // Process payment
            $result = $gateway->process_payment($order_id);
            
            if (isset($result['result']) && $result['result'] === 'success') {
                // Payment gateway will redirect
                wp_send_json_success(array(
                    'redirect' => $result['redirect'],
                    'order_id' => $order_id,
                    'order_number' => $order->get_order_number(),
                    'requires_redirect' => true
                ));
            }
        }
        
        // Fallback - mark as completed
        $order->payment_complete();
        WC()->cart->empty_cart();
        
        wp_send_json_success(array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'total' => floatval($order->get_total()),
            'payment_method' => $payment_method,
            'status' => $order->get_status(),
            'message' => sprintf('Order #%s created successfully!', $order->get_order_number())
        ));
        
    } catch (Exception $e) {
        error_log('KPOS Exception: ' . $e->getMessage());
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
    
}
// Initialize
new DKM_WooCommerce_Integration();