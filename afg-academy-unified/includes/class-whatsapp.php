<?php
/**
 * WhatsApp Integration Class
 * Sends notifications via WhatsApp Business API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_WhatsApp {
    
    private static $api_url = 'https://graph.facebook.com/v17.0/';
    
    /**
     * Check if WhatsApp is enabled
     */
    public static function is_enabled() {
        return get_option('afg_whatsapp_enabled', 'no') === 'yes';
    }
    
    /**
     * Get API credentials
     */
    private static function get_credentials() {
        return array(
            'api_key' => get_option('dkm_whatsapp_api_key', ''),
            'phone_id' => get_option('dkm_whatsapp_phone_id', ''),
            'admin_phone' => get_option('dkm_admin_whatsapp', '')
        );
    }
    
    /**
     * Send WhatsApp message
     */
    public static function send_message($phone, $message, $template_name = null) {
        if (!self::is_enabled()) {
            return false;
        }
        
        $credentials = self::get_credentials();
        
        if (empty($credentials['api_key']) || empty($credentials['phone_id'])) {
            return false;
        }
        
        // Format phone number (remove +, spaces, dashes)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing (assuming India +91)
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }
        
        // Prepare API request
        $url = self::$api_url . $credentials['phone_id'] . '/messages';
        
        $body = array(
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => array(
                'body' => $message
            )
        );
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $credentials['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 15
        );
        
        $response = wp_remote_post($url, $args);
        
        // Log the message
        self::log_message(
            null,
            $phone,
            $template_name,
            'text',
            $message,
            is_wp_error($response) ? 'failed' : 'sent',
            is_wp_error($response) ? null : wp_remote_retrieve_body($response),
            is_wp_error($response) ? $response->get_error_message() : null
        );
        
        return !is_wp_error($response);
    }
    
    /**
     * Send balance update notification
     */
    public static function send_balance_update($trainee_id, $new_balance, $type, $amount) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $currency = get_option('dkm_currency_symbol', '₹');
        
        if ($type === 'charge') {
            $message = "🍽️ *Deshi Kitchen - Balance Update*\n\n";
            $message .= "Dear {$user->display_name},\n\n";
            $message .= "Amount charged: {$currency}" . number_format($amount, 2) . "\n";
            $message .= "Current balance: {$currency}" . number_format($new_balance, 2) . "\n\n";
            $message .= "Thank you for visiting!";
        } else {
            $message = "✅ *Deshi Kitchen - Payment Received*\n\n";
            $message .= "Dear {$user->display_name},\n\n";
            $message .= "Payment received: {$currency}" . number_format($amount, 2) . "\n";
            $message .= "Remaining balance: {$currency}" . number_format($new_balance, 2) . "\n\n";
            $message .= "Thank you!";
        }
        
        return self::send_message($phone, $message, 'balance_update');
    }
    
    /**
     * Send payment received confirmation
     */
    public static function send_payment_received($trainee_id, $amount, $new_balance) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $currency = get_option('dkm_currency_symbol', '₹');
        
        $message = "✅ *Deshi Kitchen - Payment Confirmation*\n\n";
        $message .= "Dear {$user->display_name},\n\n";
        $message .= "We have received your payment of {$currency}" . number_format($amount, 2) . "\n\n";
        $message .= "Current balance: {$currency}" . number_format($new_balance, 2) . "\n";
        $message .= "Payment date: " . date('d M Y, h:i A') . "\n\n";
        $message .= "Thank you for your payment!";
        
        return self::send_message($phone, $message, 'payment_received');
    }
    
    /**
     * Send invoice notification
     */
    public static function send_invoice($trainee_id, $invoice_number, $amount, $due_date) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $currency = get_option('dkm_currency_symbol', '₹');
        
        $message = "📄 *Deshi Kitchen - Invoice*\n\n";
        $message .= "Dear {$user->display_name},\n\n";
        $message .= "Your monthly invoice is ready:\n\n";
        $message .= "Invoice No: {$invoice_number}\n";
        $message .= "Amount: {$currency}" . number_format($amount, 2) . "\n";
        $message .= "Due Date: " . date('d M Y', strtotime($due_date)) . "\n\n";
        $message .= "Please make payment before due date.\n";
        $message .= "View invoice: " . site_url('/my-account/invoices/');
        
        return self::send_message($phone, $message, 'invoice');
    }
    
    /**
     * Send low stock alert to admin
     */
    public static function send_low_stock_alert($product_name, $current_stock) {
        $credentials = self::get_credentials();
        
        if (empty($credentials['admin_phone'])) {
            return false;
        }
        
        $message = "⚠️ *Deshi Kitchen - Low Stock Alert*\n\n";
        $message .= "Product: {$product_name}\n";
        $message .= "Current Stock: {$current_stock}\n\n";
        
        if ($current_stock == 0) {
            $message .= "❌ OUT OF STOCK\n";
        } else {
            $message .= "Please restock soon!";
        }
        
        $message .= "\n\nTime: " . date('d M Y, h:i A');
        
        return self::send_message($credentials['admin_phone'], $message, 'low_stock_alert');
    }
    
    /**
     * Send order confirmation
     */
    public static function send_order_confirmation($trainee_id, $order_id, $items, $total, $payment_method) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $currency = get_option('dkm_currency_symbol', '₹');
        
        $message = "✅ *Deshi Kitchen - Order Confirmation*\n\n";
        $message .= "Dear {$user->display_name},\n\n";
        $message .= "Order #{$order_id}\n";
        $message .= "Time: " . date('h:i A') . "\n\n";
        $message .= "*Items:*\n";
        
        foreach ($items as $item) {
            $message .= "• {$item['name']} x{$item['qty']} - {$currency}{$item['price']}\n";
        }
        
        $message .= "\n*Total:* {$currency}" . number_format($total, 2) . "\n";
        $message .= "*Payment:* " . ucfirst($payment_method) . "\n\n";
        $message .= "Thank you for your order!";
        
        return self::send_message($phone, $message, 'order_confirmation');
    }
    
    /**
     * Send credit limit warning
     */
    public static function send_credit_limit_warning($trainee_id, $current_balance) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $currency = get_option('dkm_currency_symbol', '₹');
        $credit_limit = get_option('dkm_credit_limit', 5000);
        
        $message = "⚠️ *Deshi Kitchen - Credit Limit Warning*\n\n";
        $message .= "Dear {$user->display_name},\n\n";
        $message .= "Your outstanding balance is high:\n\n";
        $message .= "Current Balance: {$currency}" . number_format($current_balance, 2) . "\n";
        $message .= "Credit Limit: {$currency}" . number_format($credit_limit, 2) . "\n\n";
        $message .= "Please clear your dues at the earliest.\n";
        $message .= "You can make payment at the counter or online.";
        
        return self::send_message($phone, $message, 'credit_warning');
    }
    
    /**
     * Send subscription renewal reminder
     */
    public static function send_subscription_reminder($trainee_id, $package_name, $amount, $due_date) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $currency = get_option('dkm_currency_symbol', '₹');
        
        $message = "🔔 *Deshi Kitchen - Subscription Reminder*\n\n";
        $message .= "Dear {$user->display_name},\n\n";
        $message .= "Your subscription is due for renewal:\n\n";
        $message .= "Package: {$package_name}\n";
        $message .= "Amount: {$currency}" . number_format($amount, 2) . "\n";
        $message .= "Due Date: " . date('d M Y', strtotime($due_date)) . "\n\n";
        $message .= "Please renew before expiry to continue services.";
        
        return self::send_message($phone, $message, 'subscription_reminder');
    }
    
    /**
     * Send welcome message to new trainee
     */
    public static function send_welcome_message($trainee_id) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $message = "🎉 *Welcome to Deshi Kitchen!*\n\n";
        $message .= "Dear {$user->display_name},\n\n";
        $message .= "Thank you for joining us!\n\n";
        $message .= "You can now:\n";
        $message .= "✅ Purchase items from kitchen counter\n";
        $message .= "✅ View your account balance\n";
        $message .= "✅ Track your invoices\n";
        $message .= "✅ Submit queries online\n\n";
        $message .= "Portal: " . site_url('/my-account/') . "\n\n";
        $message .= "For any queries, contact us at the reception.";
        
        return self::send_message($phone, $message, 'welcome');
    }
    
    /**
     * Send daily summary to admin
     */
    public static function send_daily_summary() {
        $credentials = self::get_credentials();
        
        if (empty($credentials['admin_phone'])) {
            return false;
        }
        
        global $wpdb;
        
        // Get today's stats
        $today = date('Y-m-d');
        $orders_table = $wpdb->prefix . 'posts';
        $ledger_table = $wpdb->prefix . 'trainee_ledger';
        
        // Total orders
        $total_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $orders_table 
            WHERE post_type = 'shop_order' 
            AND DATE(post_date) = %s",
            $today
        ));
        
        // Total sales
        $total_sales = 0;
        $orders = wc_get_orders(array(
            'date_created' => $today,
            'limit' => -1
        ));
        
        foreach ($orders as $order) {
            $total_sales += $order->get_total();
        }
        
        // Total payments
        $total_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $ledger_table 
            WHERE type = 'payment' 
            AND DATE(created_at) = %s",
            $today
        ));
        
        // Total charges
        $total_charges = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $ledger_table 
            WHERE type = 'charge' 
            AND DATE(created_at) = %s",
            $today
        ));
        
        $currency = get_option('dkm_currency_symbol', '₹');
        
        $message = "📊 *Deshi Kitchen - Daily Summary*\n\n";
        $message .= "Date: " . date('d M Y') . "\n\n";
        $message .= "*Sales:*\n";
        $message .= "• Orders: {$total_orders}\n";
        $message .= "• Amount: {$currency}" . number_format($total_sales, 2) . "\n\n";
        $message .= "*Payments:*\n";
        $message .= "• Received: {$currency}" . number_format($total_payments, 2) . "\n\n";
        $message .= "*Kitchen Charges:*\n";
        $message .= "• Total: {$currency}" . number_format($total_charges, 2) . "\n\n";
        $message .= "View detailed report in admin panel.";
        
        return self::send_message($credentials['admin_phone'], $message, 'daily_summary');
    }
    
    /**
     * Send query received notification
     */
    public static function send_query_notification($query_id, $trainee_name, $subject) {
        $credentials = self::get_credentials();
        
        if (empty($credentials['admin_phone'])) {
            return false;
        }
        
        $message = "💬 *New Support Query*\n\n";
        $message .= "From: {$trainee_name}\n";
        $message .= "Subject: {$subject}\n";
        $message .= "Ticket: #{$query_id}\n\n";
        $message .= "Login to admin panel to respond.\n";
        $message .= admin_url('admin.php?page=deshi-queries');
        
        return self::send_message($credentials['admin_phone'], $message, 'query_notification');
    }
    
    /**
     * Send query response to trainee
     */
    public static function send_query_response($trainee_id, $ticket_number, $response) {
        $user = get_userdata($trainee_id);
        if (!$user) {
            return false;
        }
        
        $phone = get_user_meta($trainee_id, 'billing_phone', true);
        if (empty($phone)) {
            return false;
        }
        
        $message = "✅ *Query Response - Deshi Kitchen*\n\n";
        $message .= "Dear {$user->display_name},\n\n";
        $message .= "Ticket #{$ticket_number}\n\n";
        $message .= "*Response:*\n{$response}\n\n";
        $message .= "If you have more questions, please reply or visit reception.";
        
        return self::send_message($phone, $message, 'query_response');
    }
    
    /**
     * Log WhatsApp message to database
     */
    private static function log_message($recipient_id, $phone, $template_name, $message_type, $content, $status, $response, $error = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_whatsapp_logs';
        
        $wpdb->insert(
            $table,
            array(
                'recipient_id' => $recipient_id,
                'phone' => $phone,
                'template_name' => $template_name,
                'message_type' => $message_type,
                'message_content' => $content,
                'status' => $status,
                'response' => $response,
                'error_message' => $error
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get message logs
     */
    public static function get_logs($limit = 50, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_whatsapp_logs';
        
        $where = $status ? $wpdb->prepare("WHERE status = %s", $status) : "";
        
        return $wpdb->get_results(
            "SELECT * FROM $table 
            $where 
            ORDER BY id DESC 
            LIMIT $limit"
        );
    }
    
    /**
     * Get logs for specific user
     */
    public static function get_user_logs($recipient_id, $limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_whatsapp_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE recipient_id = %d 
            ORDER BY id DESC 
            LIMIT %d",
            $recipient_id, $limit
        ));
    }
    
    /**
     * Get statistics
     */
    public static function get_statistics() {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_whatsapp_logs';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count
            FROM $table"
        );
        
        return array(
            'total_sent' => intval($stats->total_sent),
            'sent' => intval($stats->sent),
            'failed' => intval($stats->failed),
            'delivered' => intval($stats->delivered),
            'read' => intval($stats->read_count),
            'success_rate' => $stats->total_sent > 0 ? round(($stats->sent / $stats->total_sent) * 100, 2) : 0
        );
    }
    
    /**
     * Test WhatsApp connection
     */
    public static function test_connection($phone = null) {
        $credentials = self::get_credentials();
        
        if (empty($credentials['api_key']) || empty($credentials['phone_id'])) {
            return array(
                'success' => false,
                'message' => 'API credentials not configured'
            );
        }
        
        $test_phone = $phone ?: $credentials['admin_phone'];
        
        if (empty($test_phone)) {
            return array(
                'success' => false,
                'message' => 'No phone number provided'
            );
        }
        
        $message = "🧪 *Deshi Kitchen - Test Message*\n\n";
        $message .= "WhatsApp integration is working correctly!\n";
        $message .= "Time: " . date('d M Y, h:i A');
        
        $result = self::send_message($test_phone, $message, 'test');
        
        return array(
            'success' => $result,
            'message' => $result ? 'Test message sent successfully!' : 'Failed to send test message'
        );
    }
    
    /**
     * Bulk send messages
     */
    public static function bulk_send($trainee_ids, $message, $template_name = 'bulk') {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($trainee_ids as $trainee_id) {
            $user = get_userdata($trainee_id);
            if (!$user) {
                $results['failed']++;
                continue;
            }
            
            $phone = get_user_meta($trainee_id, 'billing_phone', true);
            if (empty($phone)) {
                $results['failed']++;
                $results['details'][] = array(
                    'trainee_id' => $trainee_id,
                    'name' => $user->display_name,
                    'status' => 'failed',
                    'error' => 'Phone number not found'
                );
                continue;
            }
            
            $sent = self::send_message($phone, $message, $template_name);
            
            if ($sent) {
                $results['success']++;
                $results['details'][] = array(
                    'trainee_id' => $trainee_id,
                    'name' => $user->display_name,
                    'status' => 'sent'
                );
            } else {
                $results['failed']++;
                $results['details'][] = array(
                    'trainee_id' => $trainee_id,
                    'name' => $user->display_name,
                    'status' => 'failed',
                    'error' => 'API error'
                );
            }
            
            // Sleep to avoid rate limiting
            sleep(1);
        }
        
        return $results;
    }
    
    /**
     * Clear old logs (older than 90 days)
     */
    public static function clear_old_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_whatsapp_logs';
        
        $deleted = $wpdb->query(
            "DELETE FROM $table 
            WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        return $deleted;
    }
}