<?php
/**
 * Trainee Ledger Management Class
 * Handles credit/debit transactions for trainees
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_Trainee_Ledger {
    
    /**
     * Get trainee current balance
     */
    public static function get_balance($trainee_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        $last_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT balance_after FROM $table 
            WHERE trainee_id = %d 
            ORDER BY id DESC 
            LIMIT 1",
            $trainee_id
        ));
        
        return $last_entry ? floatval($last_entry->balance_after) : 0;
    }
    
    /**
     * Add charge to trainee account
     */
    public static function add_charge($trainee_id, $amount, $reference_type = 'manual', $reference_id = null, $note = '') {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than 0');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        // Get current balance
        $current_balance = self::get_balance($trainee_id);
        $new_balance = $current_balance + $amount;
        
        // Check credit limit
        $credit_limit = get_option('afg_credit_limit', 5000);
        if ($new_balance > $credit_limit) {
            return new WP_Error('credit_limit_exceeded', 'Credit limit exceeded');
        }
        
        // Insert entry
        $inserted = $wpdb->insert(
            $table,
            array(
                'trainee_id' => $trainee_id,
                'type' => 'charge',
                'amount' => $amount,
                'balance_after' => $new_balance,
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'note' => $note,
                'created_by' => get_current_user_id()
            ),
            array('%d', '%s', '%f', '%f', '%s', '%d', '%s', '%d')
        );
        
        if ($inserted) {
            // Send WhatsApp notification if enabled
            if (class_exists('DKM_WhatsApp') && get_option('dkm_whatsapp_enabled') === 'yes') {
                DKM_WhatsApp::send_balance_update($trainee_id, $new_balance, 'charge', $amount);
            }
            
            return array(
                'success' => true,
                'new_balance' => $new_balance,
                'entry_id' => $wpdb->insert_id
            );
        }
        
        return new WP_Error('db_error', 'Failed to add charge');
    }
    
    /**
     * Add payment to trainee account
     */
    public static function add_payment($trainee_id, $amount, $reference_type = 'manual', $reference_id = null, $note = '') {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than 0');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        // Get current balance
        $current_balance = self::get_balance($trainee_id);
        $new_balance = $current_balance - $amount;
        
        // Don't allow negative balance from payments
        if ($new_balance < 0) {
            $amount = $current_balance; // Only pay what's owed
            $new_balance = 0;
        }
        
        // Insert entry
        $inserted = $wpdb->insert(
            $table,
            array(
                'trainee_id' => $trainee_id,
                'type' => 'payment',
                'amount' => $amount,
                'balance_after' => $new_balance,
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'note' => $note,
                'created_by' => get_current_user_id()
            ),
            array('%d', '%s', '%f', '%f', '%s', '%d', '%s', '%d')
        );
        
        if ($inserted) {
            // Send WhatsApp notification
            if (class_exists('DKM_WhatsApp') && get_option('dkm_whatsapp_enabled') === 'yes') {
                DKM_WhatsApp::send_payment_received($trainee_id, $amount, $new_balance);
            }
            
            return array(
                'success' => true,
                'new_balance' => $new_balance,
                'entry_id' => $wpdb->insert_id
            );
        }
        
        return new WP_Error('db_error', 'Failed to add payment');
    }
    
    /**
     * Get ledger history for trainee
     */
    public static function get_ledger_history($trainee_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE trainee_id = %d 
            ORDER BY id DESC 
            LIMIT %d OFFSET %d",
            $trainee_id, $limit, $offset
        ));
    }
    
    /**
     * Get ledger summary
     */
    public static function get_summary($trainee_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN type = 'charge' THEN amount ELSE 0 END) as total_charges,
                SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as total_payments,
                COUNT(*) as total_transactions
            FROM $table 
            WHERE trainee_id = %d",
            $trainee_id
        ));
        
        return array(
            'current_balance' => self::get_balance($trainee_id),
            'total_charges' => floatval($summary->total_charges),
            'total_payments' => floatval($summary->total_payments),
            'total_transactions' => intval($summary->total_transactions)
        );
    }
    
    /**
     * Get all trainees with balances
     */
    public static function get_all_trainees_with_balance() {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        // Get latest balance for each trainee
        $results = $wpdb->get_results(
            "SELECT trainee_id, balance_after 
            FROM $table 
            WHERE id IN (
                SELECT MAX(id) FROM $table GROUP BY trainee_id
            )
            ORDER BY balance_after DESC"
        );
        
        $trainees_data = array();
        
        foreach ($results as $row) {
            $user = get_userdata($row->trainee_id);
            if ($user) {
                $trainees_data[] = array(
                    'id' => $row->trainee_id,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'phone' => get_user_meta($row->trainee_id, 'billing_phone', true),
                    'balance' => floatval($row->balance_after)
                );
            }
        }
        
        return $trainees_data;
    }
    
     /**
      * Search trainees by name, email or phone
      */
    public static function search_trainees($search_term) {
        $args = array(
            'role' => 'customer',
            'search' => '*' . esc_attr($search_term) . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 20
        );
        
        $users = get_users($args);
        $results = array();
        
        foreach ($users as $user) {
            $phone = get_user_meta($user->ID, 'billing_phone', true);
            
            $name_match = stripos($user->display_name, $search_term) !== false;
            $email_match = stripos($user->user_email, $search_term) !== false;
            $phone_match = !empty($phone) && stripos($phone, $search_term) !== false;
            
            if (!$name_match && !$email_match && !$phone_match) {
                continue;
            }
            
            $balance = self::get_balance($user->ID);
            
            $results[] = array(
                'id' => $user->ID,
                'trainee_id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'phone' => $phone,
                'balance' => $balance,
                'balance_status' => self::get_balance_status($balance)
            );
        }
        
        return $results;
    }
    /**
     * Get balance status (ok, warning, critical)
     */
    public static function get_balance_status($balance) {
        $credit_limit = get_option('afg_credit_limit', 5000);
        $warning_threshold = $credit_limit * 0.7; // 70% of limit
        
        if ($balance == 0) {
            return 'ok';
        } elseif ($balance < $warning_threshold) {
            return 'warning';
        } else {
            return 'critical';
        }
    }
    
    /**
     * Get trainees by balance status
     */
    public static function get_trainees_by_status($status = 'all') {
        $all_trainees = self::get_all_trainees_with_balance();
        
        if ($status === 'all') {
            return $all_trainees;
        }
        
        return array_filter($all_trainees, function($trainee) use ($status) {
            return self::get_balance_status($trainee['balance']) === $status;
        });
    }
    
    /**
     * Get monthly statement for trainee
     */
    public static function get_monthly_statement($trainee_id, $month, $year) {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        $start_date = "$year-$month-01 00:00:00";
        $end_date = date("Y-m-t 23:59:59", strtotime($start_date));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE trainee_id = %d 
            AND created_at BETWEEN %s AND %s
            ORDER BY id ASC",
            $trainee_id, $start_date, $end_date
        ));
    }
    
    /**
     * Delete ledger entry (admin only)
     */
    public static function delete_entry($entry_id) {
        if (!current_user_can('manage_dkm_settings')) {
            return new WP_Error('permission_denied', 'Permission denied');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        // Get entry
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return new WP_Error('not_found', 'Entry not found');
        }
        
        // Delete
        $deleted = $wpdb->delete($table, array('id' => $entry_id), array('%d'));
        
        if ($deleted) {
            // Recalculate balances for this trainee
            self::recalculate_balances($entry->trainee_id);
            return true;
        }
        
        return new WP_Error('db_error', 'Failed to delete entry');
    }
    
    /**
     * Recalculate all balances for a trainee
     */
    private static function recalculate_balances($trainee_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        // Get all entries for this trainee
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE trainee_id = %d 
            ORDER BY id ASC",
            $trainee_id
        ));
        
        $balance = 0;
        
        foreach ($entries as $entry) {
            if ($entry->type === 'charge') {
                $balance += $entry->amount;
            } else {
                $balance -= $entry->amount;
            }
            
            // Update balance_after
            $wpdb->update(
                $table,
                array('balance_after' => $balance),
                array('id' => $entry->id),
                array('%f'),
                array('%d')
            );
        }
    }
    
    /**
     * AJAX: Search trainee
     */
public static function search_trainees($search_term) {
    $args = array(
        'role' => 'customer',
        'search' => '*' . esc_attr($search_term) . '*',
        'search_columns' => array('user_login', 'user_email', 'display_name'),
        'number' => 20
    );
    
    $users = get_users($args);
    $results = array();
    
    foreach ($users as $user) {
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        
        $name_match = stripos($user->display_name, $search_term) !== false;
        $email_match = stripos($user->user_email, $search_term) !== false;
        $phone_match = !empty($phone) && stripos($phone, $search_term) !== false;
        
        if (!$name_match && !$email_match && !$phone_match) {
            continue;
        }
        
        $balance = self::get_balance($user->ID);
        
        $results[] = array(
            'id' => $user->ID,
            'trainee_id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'balance' => $balance,
            'balance_status' => self::get_balance_status($balance)
        );
    }
    
    return $results;
}    
     /**
      * AJAX: Get trainee balance
      */
    public static function ajax_get_trainee_balance() {
        check_ajax_referer('dkm_admin_nonce', 'nonce');
        
        $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;
        
        if (!$trainee_id) {
            wp_send_json_error(array(
                'message' => 'Trainee ID required'
            ));
        }
        
        $user = get_userdata($trainee_id);
        if (!$user) {
            wp_send_json_error(array(
                'message' => 'Trainee not found'
            ));
        }
        
        $balance = self::get_balance($trainee_id);
        $summary = self::get_summary($trainee_id);
        
        wp_send_json_success(array(
            'trainee_id' => $trainee_id,
            'id' => $trainee_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($trainee_id, 'billing_phone', true),
            'balance' => $balance,
            'summary' => $summary,
            'credit_limit' => get_option('afg_credit_limit', 5000)
        ));
    }
     * AJAX: Add manual payment
     */
    public static function ajax_add_manual_payment() {
        check_ajax_referer('dkm_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_dkm_ledger')) {
            wp_send_json_error('Permission denied');
        }
        
        $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $note = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        
        if (!$trainee_id || $amount <= 0) {
            wp_send_json_error('Invalid data');
        }
        
        $result = self::add_payment($trainee_id, $amount, 'manual', null, $note);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Add manual charge
     */
    public static function ajax_add_manual_charge() {
        check_ajax_referer('dkm_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_dkm_ledger')) {
            wp_send_json_error('Permission denied');
        }
        
        $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $note = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
        
        if (!$trainee_id || $amount <= 0) {
            wp_send_json_error('Invalid data');
        }
        
        $result = self::add_charge($trainee_id, $amount, 'manual', null, $note);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Export ledger to CSV
     */
    public static function export_to_csv($trainee_id = null) {
        if (!current_user_can('view_dkm_reports')) {
            wp_die('Permission denied');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        $where = $trainee_id ? $wpdb->prepare("WHERE trainee_id = %d", $trainee_id) : "";
        
        $entries = $wpdb->get_results("SELECT * FROM $table $where ORDER BY id DESC");
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ledger-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array('ID', 'Trainee', 'Type', 'Amount', 'Balance After', 'Reference', 'Note', 'Date'));
        
        // Data rows
        foreach ($entries as $entry) {
            $user = get_userdata($entry->trainee_id);
            fputcsv($output, array(
                $entry->id,
                $user ? $user->display_name : 'Unknown',
                ucfirst($entry->type),
                $entry->amount,
                $entry->balance_after,
                $entry->reference_type . ($entry->reference_id ? ' #' . $entry->reference_id : ''),
                $entry->note,
                $entry->created_at
            ));
        }
        
        fclose($output);
        exit;
    }
}