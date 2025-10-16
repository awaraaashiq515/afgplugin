<?php
/**
 * KPOS Order Creator Class
 * Handles order creation from POS
 */

if (!defined('ABSPATH')) {
    exit;
}

class KPOS_Order_Creator {
    
    /**
     * AJAX: Create order from POS
     */
    public static function ajax_create_order() {
        KPOS_Permissions::check_ajax_permission();
        
        // Get data
        $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;
        $cart_items = isset($_POST['cart_items']) ? json_decode(stripslashes($_POST['cart_items']), true) : array();
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'cash';
        $note = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        
        // Validate
        if (!$trainee_id) {
            wp_send_json_error(array(
                'message' => __('Please select a trainee', 'kitchen-pos')
            ));
        }
        
        if (empty($cart_items)) {
            wp_send_json_error(array(
                'message' => __('Cart is empty', 'kitchen-pos')
            ));
        }
        
        // Validate cart
        $validation = KPOS_Handler::validate_cart($cart_items);
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => $validation['message']
            ));
        }
        
        // Calculate total
        $total = KPOS_Handler::calculate_cart_total($cart_items);
        
        // Check credit limit if credit payment
        if ($payment_method === 'credit') {
            $credit_check = KPOS_Handler::check_credit_limit($trainee_id, $total);
            if (!$credit_check['allowed']) {
                wp_send_json_error(array(
                    'message' => $credit_check['message']
                ));
            }
        }
        
        // Create order
        $result = self::create_order($trainee_id, $cart_items, $payment_method, $note);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Create WooCommerce order
     */
    public static function create_order($trainee_id, $cart_items, $payment_method, $note = '') {
        try {
            // Create order
            $order = wc_create_order();
            
            if (!$order) {
                return new WP_Error('order_creation_failed', __('Failed to create order', 'kitchen-pos'));
            }
            
            // Set customer
            $order->set_customer_id($trainee_id);
            
            // Add products
            foreach ($cart_items as $item) {
                $product = wc_get_product($item['product_id']);
                
                if (!$product) {
                    $order->delete(true);
                    return new WP_Error('product_not_found', sprintf(__('Product #%d not found', 'kitchen-pos'), $item['product_id']));
                }
                
                $order->add_product($product, $item['quantity']);
                
                // Reduce stock
                if (class_exists('DKM_WooCommerce_Integration')) {
                    DKM_WooCommerce_Integration::reduce_stock(
                        $item['product_id'],
                        $item['quantity'],
                        'POS Sale',
                        $order->get_id()
                    );
                }
            }
            
            // Set payment method
            if ($payment_method === 'cash') {
                $order->set_payment_method('cod');
                $order->set_payment_method_title(__('Cash', 'kitchen-pos'));
            } elseif ($payment_method === 'credit') {
                $order->set_payment_method('cod');
                $order->set_payment_method_title(__('Credit', 'kitchen-pos'));
            } else {
                $order->set_payment_method('other');
                $order->set_payment_method_title(__('Online/UPI', 'kitchen-pos'));
            }
            
            // Add note
            if (!empty($note)) {
                $order->add_order_note($note);
            }
            
            // Mark as POS order
            $order->update_meta_data('_kpos_order', 'yes');
            $order->update_meta_data('_kpos_operator', get_current_user_id());
            $order->update_meta_data('_kpos_payment_method', $payment_method);
            $order->update_meta_data('_kpos_created_at', current_time('mysql'));
            
            // Calculate totals
            $order->calculate_totals();
            
            // Set status based on payment method
            if ($payment_method === 'credit') {
                $order->update_status('on-hold', __('Awaiting credit payment', 'kitchen-pos'));
                
                // Add to trainee ledger
                if (class_exists('DKM_Trainee_Ledger')) {
                    $result = DKM_Trainee_Ledger::add_charge(
                        $trainee_id,
                        $order->get_total(),
                        'order',
                        $order->get_id(),
                        sprintf(__('POS Order #%s', 'kitchen-pos'), $order->get_order_number())
                    );
                    
                    if (is_wp_error($result)) {
                        $order->delete(true);
                        return $result;
                    }
                }
            } else {
                $order->payment_complete();
            }
            
            // Save order
            $order->save();
            
            // Send WhatsApp notification if enabled
            if (class_exists('DKM_WhatsApp') && get_option('dkm_whatsapp_enabled') === 'yes') {
                $items_for_whatsapp = array();
                foreach ($order->get_items() as $item) {
                    $items_for_whatsapp[] = array(
                        'name' => $item->get_name(),
                        'qty' => $item->get_quantity(),
                        'price' => number_format($item->get_total(), 2)
                    );
                }
                
                DKM_WhatsApp::send_order_confirmation(
                    $trainee_id,
                    $order->get_order_number(),
                    $items_for_whatsapp,
                    $order->get_total(),
                    $payment_method
                );
            }
            
            // Get new balance if credit
            $new_balance = 0;
            if ($payment_method === 'credit' && class_exists('DKM_Trainee_Ledger')) {
                $new_balance = DKM_Trainee_Ledger::get_balance($trainee_id);
            }
            
            return array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total' => $order->get_total(),
                'payment_method' => $payment_method,
                'status' => $order->get_status(),
                'new_balance' => $new_balance,
                'message' => sprintf(
                    __('Order #%s created successfully!', 'kitchen-pos'),
                    $order->get_order_number()
                )
            );
            
        } catch (Exception $e) {
            return new WP_Error('order_error', $e->getMessage());
        }
    }
    
    /**
     * Get order details
     */
    public static function get_order_details($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return null;
        }
        
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
                'product_id' => $item->get_product_id(),
                'image' => $product ? wp_get_attachment_url($product->get_image_id()) : ''
            );
        }
        
        $customer = $order->get_user();
        
        return array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'customer' => array(
                'id' => $order->get_customer_id(),
                'name' => $customer ? $customer->display_name : '',
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone()
            ),
            'items' => $items,
            'total' => $order->get_total(),
            'payment_method' => $order->get_meta('_kpos_payment_method'),
            'status' => $order->get_status(),
            'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'operator_id' => $order->get_meta('_kpos_operator')
        );
    }
    
    /**
     * Print receipt data
     */
    public static function get_receipt_data($order_id) {
        $details = self::get_order_details($order_id);
        
        if (!$details) {
            return null;
        }
        
        $currency = get_option('dkm_currency_symbol', '₹');
        
        return array(
            'title' => get_bloginfo('name'),
            'order_number' => $details['order_number'],
            'date' => date('d M Y, h:i A', strtotime($details['date'])),
            'customer' => $details['customer']['name'],
            'items' => $details['items'],
            'total' => $currency . number_format($details['total'], 2),
            'payment_method' => ucfirst($details['payment_method']),
            'status' => ucfirst($details['status'])
        );
    }
    
    /**
     * Cancel/Refund order
     */
    public static function cancel_order($order_id, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', __('Order not found', 'kitchen-pos'));
        }
        
        // Restore stock
        foreach ($order->get_items() as $item) {
            if (class_exists('DKM_WooCommerce_Integration')) {
                DKM_WooCommerce_Integration::increase_stock(
                    $item->get_product_id(),
                    $item->get_quantity(),
                    'Order cancelled - #' . $order->get_order_number(),
                    $order->get_id()
                );
            }
        }
        
        // Reverse ledger entry if credit
        $payment_method = $order->get_meta('_kpos_payment_method');
        if ($payment_method === 'credit' && class_exists('DKM_Trainee_Ledger')) {
            DKM_Trainee_Ledger::add_payment(
                $order->get_customer_id(),
                $order->get_total(),
                'order_refund',
                $order->get_id(),
                sprintf(__('Order #%s cancelled', 'kitchen-pos'), $order->get_order_number())
            );
        }
        
        // Update order status
        $order->update_status('cancelled', $reason);
        
        return true;
    }
}