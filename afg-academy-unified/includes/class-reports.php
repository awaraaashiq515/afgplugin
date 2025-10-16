<?php
/**
 * Reports and Analytics Class
 * Generates various business reports
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_Reports {
    
    /**
     * Get dashboard statistics
     */
    public static function get_dashboard_stats() {
        global $wpdb;
        
        $today = date('Y-m-d');
        $currency = get_option('afg_currency_symbol', '₹');
        
        // Today's sales
        $today_sales = self::get_sales_by_date($today, $today);
        
        // This month's sales
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $month_sales = self::get_sales_by_date($month_start, $month_end);
        
        // Active trainees
        $active_trainees = count(DKM_Roles_Capabilities::get_trainees());
        
        // Outstanding balance
        $ledger_table = $wpdb->prefix . 'trainee_ledger';
        $outstanding = $wpdb->get_var(
            "SELECT SUM(balance_after) FROM $ledger_table 
            WHERE id IN (
                SELECT MAX(id) FROM $ledger_table GROUP BY trainee_id
            )"
        );
        
        // Low stock items
        $low_stock_count = count(DKM_WooCommerce_Integration::get_low_stock_products());
        
        // Pending queries
        $queries_table = $wpdb->prefix . 'deshi_queries';
        $pending_queries = $wpdb->get_var(
            "SELECT COUNT(*) FROM $queries_table 
            WHERE status IN ('new', 'in_progress')"
        );
        
        return array(
            'today_sales' => array(
                'amount' => $currency . number_format($today_sales['total'], 2),
                'orders' => $today_sales['orders'],
                'raw_amount' => $today_sales['total']
            ),
            'month_sales' => array(
                'amount' => $currency . number_format($month_sales['total'], 2),
                'orders' => $month_sales['orders'],
                'raw_amount' => $month_sales['total']
            ),
            'active_trainees' => $active_trainees,
            'outstanding_balance' => array(
                'amount' => $currency . number_format($outstanding, 2),
                'raw_amount' => floatval($outstanding)
            ),
            'low_stock_items' => $low_stock_count,
            'pending_queries' => intval($pending_queries)
        );
    }
    
    /**
     * Get sales by date range
     */
    public static function get_sales_by_date($start_date, $end_date) {
        $args = array(
            'limit' => -1,
            'date_created' => $start_date . '...' . $end_date,
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
        );
        
        $orders = wc_get_orders($args);
        
        $total = 0;
        $order_count = count($orders);
        
        foreach ($orders as $order) {
            $total += $order->get_total();
        }
        
        return array(
            'total' => $total,
            'orders' => $order_count,
            'average' => $order_count > 0 ? $total / $order_count : 0
        );
    }
    
    /**
     * Get daily sales report
     */
    public static function get_daily_sales_report($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $sales = self::get_sales_by_date($date, $date);
        
        // Get hourly breakdown
        $args = array(
            'limit' => -1,
            'date_created' => $date,
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
        );
        
        $orders = wc_get_orders($args);
        $hourly_data = array();
        
        foreach ($orders as $order) {
            $hour = $order->get_date_created()->date('H');
            
            if (!isset($hourly_data[$hour])) {
                $hourly_data[$hour] = array(
                    'hour' => $hour . ':00',
                    'orders' => 0,
                    'amount' => 0
                );
            }
            
            $hourly_data[$hour]['orders']++;
            $hourly_data[$hour]['amount'] += $order->get_total();
        }
        
        ksort($hourly_data);
        
        return array(
            'date' => $date,
            'summary' => $sales,
            'hourly' => array_values($hourly_data)
        );
    }
    
    /**
     * Get monthly sales report
     */
    public static function get_monthly_sales_report($month = null, $year = null) {
        if (!$month) {
            $month = date('m');
        }
        if (!$year) {
            $year = date('Y');
        }
        
        $start_date = "$year-$month-01";
        $end_date = date("Y-m-t", strtotime($start_date));
        
        $sales = self::get_sales_by_date($start_date, $end_date);
        
        // Get daily breakdown
        $args = array(
            'limit' => -1,
            'date_created' => $start_date . '...' . $end_date,
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
        );
        
        $orders = wc_get_orders($args);
        $daily_data = array();
        
        foreach ($orders as $order) {
            $day = $order->get_date_created()->date('Y-m-d');
            
            if (!isset($daily_data[$day])) {
                $daily_data[$day] = array(
                    'date' => $day,
                    'orders' => 0,
                    'amount' => 0
                );
            }
            
            $daily_data[$day]['orders']++;
            $daily_data[$day]['amount'] += $order->get_total();
        }
        
        ksort($daily_data);
        
        return array(
            'month' => $month,
            'year' => $year,
            'summary' => $sales,
            'daily' => array_values($daily_data)
        );
    }
    
    /**
     * Get product-wise sales report
     */
    public static function get_product_sales_report($start_date, $end_date) {
        global $wpdb;
        
        $args = array(
            'limit' => -1,
            'date_created' => $start_date . '...' . $end_date,
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
        );
        
        $orders = wc_get_orders($args);
        $product_data = array();
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }
                
                if (!isset($product_data[$product_id])) {
                    $product_data[$product_id] = array(
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'quantity' => 0,
                        'revenue' => 0,
                        'cost' => 0,
                        'profit' => 0
                    );
                }
                
                $qty = $item->get_quantity();
                $revenue = $item->get_total();
                $cost_price = get_post_meta($product_id, '_dkm_cost_price', true);
                $cost = $cost_price ? $cost_price * $qty : 0;
                
                $product_data[$product_id]['quantity'] += $qty;
                $product_data[$product_id]['revenue'] += $revenue;
                $product_data[$product_id]['cost'] += $cost;
                $product_data[$product_id]['profit'] = $product_data[$product_id]['revenue'] - $product_data[$product_id]['cost'];
            }
        }
        
        // Sort by revenue
        usort($product_data, function($a, $b) {
            return $b['revenue'] - $a['revenue'];
        });
        
        return array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'products' => array_values($product_data),
            'total_products' => count($product_data),
            'total_revenue' => array_sum(array_column($product_data, 'revenue')),
            'total_profit' => array_sum(array_column($product_data, 'profit'))
        );
    }
    
    /**
     * Get trainee-wise sales report
     */
    public static function get_trainee_sales_report($start_date, $end_date) {
        global $wpdb;
        
        $args = array(
            'limit' => -1,
            'date_created' => $start_date . '...' . $end_date,
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
        );
        
        $orders = wc_get_orders($args);
        $trainee_data = array();
        
        foreach ($orders as $order) {
            $trainee_id = $order->get_customer_id();
            
            if (!$trainee_id) {
                continue;
            }
            
            if (!isset($trainee_data[$trainee_id])) {
                $user = get_userdata($trainee_id);
                $trainee_data[$trainee_id] = array(
                    'id' => $trainee_id,
                    'name' => $user ? $user->display_name : 'Guest',
                    'email' => $user ? $user->user_email : '',
                    'orders' => 0,
                    'total_spent' => 0,
                    'current_balance' => DKM_Trainee_Ledger::get_balance($trainee_id)
                );
            }
            
            $trainee_data[$trainee_id]['orders']++;
            $trainee_data[$trainee_id]['total_spent'] += $order->get_total();
        }
        
        // Sort by total spent
        usort($trainee_data, function($a, $b) {
            return $b['total_spent'] - $a['total_spent'];
        });
        
        return array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'trainees' => array_values($trainee_data),
            'total_trainees' => count($trainee_data)
        );
    }
    
    /**
     * Get payment collection report
     */
    public static function get_payment_report($start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        // Total payments
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                SUM(amount) as total_amount,
                COUNT(*) as count
            FROM $table 
            WHERE type = 'payment' 
            AND DATE(created_at) BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $start_date, $end_date
        ));
        
        // Total charges
        $charges = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                SUM(amount) as total_amount,
                COUNT(*) as count
            FROM $table 
            WHERE type = 'charge' 
            AND DATE(created_at) BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $start_date, $end_date
        ));
        
        // Payment methods breakdown
        $cash_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table 
            WHERE type = 'payment' 
            AND reference_type = 'manual'
            AND note LIKE %s
            AND DATE(created_at) BETWEEN %s AND %s",
            '%cash%', $start_date, $end_date
        ));
        
        $online_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table 
            WHERE type = 'payment' 
            AND reference_type != 'manual'
            AND DATE(created_at) BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        $total_payments_sum = array_sum(array_column($payments, 'total_amount'));
        $total_charges_sum = array_sum(array_column($charges, 'total_amount'));
        
        return array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'payments' => $payments,
            'charges' => $charges,
            'summary' => array(
                'total_payments' => $total_payments_sum,
                'total_charges' => $total_charges_sum,
                'net' => $total_payments_sum - $total_charges_sum,
                'cash_payments' => floatval($cash_payments),
                'online_payments' => floatval($online_payments)
            )
        );
    }
    
    /**
     * Get stock movement report
     */
    public static function get_stock_movement_report($start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'kitchen_stock_movements';
        
        $movements = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                product_id,
                SUM(CASE WHEN change_qty > 0 THEN change_qty ELSE 0 END) as stock_in,
                SUM(CASE WHEN change_qty < 0 THEN ABS(change_qty) ELSE 0 END) as stock_out,
                COUNT(*) as total_movements
            FROM $table 
            WHERE DATE(created_at) BETWEEN %s AND %s
            GROUP BY product_id",
            $start_date, $end_date
        ));
        
        $report_data = array();
        
        foreach ($movements as $movement) {
            $product = wc_get_product($movement->product_id);
            
            if (!$product) {
                continue;
            }
            
            $report_data[] = array(
                'product_id' => $movement->product_id,
                'product_name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'stock_in' => intval($movement->stock_in),
                'stock_out' => intval($movement->stock_out),
                'net_movement' => intval($movement->stock_in) - intval($movement->stock_out),
                'current_stock' => $product->get_stock_quantity(),
                'total_movements' => intval($movement->total_movements)
            );
        }
        
        return array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'movements' => $report_data,
            'total_products' => count($report_data)
        );
    }
    
    /**
     * Get profit/loss report
     */
    public static function get_profit_loss_report($start_date, $end_date) {
        $product_sales = self::get_product_sales_report($start_date, $end_date);
        
        $total_revenue = $product_sales['total_revenue'];
        $total_cost = array_sum(array_column($product_sales['products'], 'cost'));
        $gross_profit = $total_revenue - $total_cost;
        
        // Get operational expenses (if any)
        $operational_expenses = 0; // Can be extended to include actual expenses
        
        $net_profit = $gross_profit - $operational_expenses;
        $profit_margin = $total_revenue > 0 ? ($gross_profit / $total_revenue) * 100 : 0;
        
        return array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'revenue' => $total_revenue,
            'cost_of_goods' => $total_cost,
            'gross_profit' => $gross_profit,
            'operational_expenses' => $operational_expenses,
            'net_profit' => $net_profit,
            'profit_margin' => round($profit_margin, 2),
            'product_breakdown' => $product_sales['products']
        );
    }
    
    /**
     * Get top selling products
     */
    public static function get_top_products($limit = 10, $start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-01');
        }
        if (!$end_date) {
            $end_date = date('Y-m-t');
        }
        
        $product_sales = self::get_product_sales_report($start_date, $end_date);
        
        return array_slice($product_sales['products'], 0, $limit);
    }
    
    /**
     * Get top spending trainees
     */
    public static function get_top_trainees($limit = 10, $start_date = null, $end_date = null) {
        if (!$start_date) {
            $start_date = date('Y-m-01');
        }
        if (!$end_date) {
            $end_date = date('Y-m-t');
        }
        
        $trainee_sales = self::get_trainee_sales_report($start_date, $end_date);
        
        return array_slice($trainee_sales['trainees'], 0, $limit);
    }
    
    /**
     * Get outstanding balance report
     */
    public static function get_outstanding_balance_report() {
        $trainees = DKM_Trainee_Ledger::get_all_trainees_with_balance();
        
        // Filter only those with balance > 0
        $with_balance = array_filter($trainees, function($trainee) {
            return $trainee['balance'] > 0;
        });
        
        // Sort by balance descending
        usort($with_balance, function($a, $b) {
            return $b['balance'] - $a['balance'];
        });
        
        $total_outstanding = array_sum(array_column($with_balance, 'balance'));
        $credit_limit = get_option('dkm_credit_limit', 5000);
        
        // Categorize by status
        $critical = array_filter($with_balance, function($t) use ($credit_limit) {
            return $t['balance'] >= $credit_limit * 0.7;
        });
        
        $warning = array_filter($with_balance, function($t) use ($credit_limit) {
            return $t['balance'] > 0 && $t['balance'] < $credit_limit * 0.7;
        });
        
        return array(
            'total_outstanding' => $total_outstanding,
            'total_trainees' => count($with_balance),
            'critical_count' => count($critical),
            'warning_count' => count($warning),
            'trainees' => $with_balance,
            'critical_trainees' => array_values($critical),
            'warning_trainees' => array_values($warning)
        );
    }
    
    /**
     * Get subscription report
     */
    public static function get_subscription_report() {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_subscriptions';
        
        // Active subscriptions
        $active = $wpdb->get_results(
            "SELECT * FROM $table 
            WHERE status = 'active' 
            ORDER BY next_billing_date ASC"
        );
        
        // Expiring soon (next 7 days)
        $expiring_soon = $wpdb->get_results(
            "SELECT * FROM $table 
            WHERE status = 'active' 
            AND next_billing_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY next_billing_date ASC"
        );
        
        // Revenue by package type
        $revenue_by_type = $wpdb->get_results(
            "SELECT 
                package_type,
                COUNT(*) as count,
                SUM(amount) as total_amount
            FROM $table 
            WHERE status = 'active'
            GROUP BY package_type"
        );
        
        $total_revenue = array_sum(array_column($revenue_by_type, 'total_amount'));
        
        return array(
            'active_count' => count($active),
            'expiring_soon_count' => count($expiring_soon),
            'total_monthly_revenue' => $total_revenue,
            'active_subscriptions' => $active,
            'expiring_soon' => $expiring_soon,
            'revenue_by_type' => $revenue_by_type
        );
    }
    
    /**
     * Get invoice report
     */
    public static function get_invoice_report($status = 'all', $month = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_invoices';
        
        $where = array();
        
        if ($status !== 'all') {
            $where[] = $wpdb->prepare("payment_status = %s", $status);
        }
        
        if ($month) {
            $where[] = $wpdb->prepare("invoice_month = %s", $month);
        }
        
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $invoices = $wpdb->get_results(
            "SELECT * FROM $table 
            $where_clause
            ORDER BY created_at DESC"
        );
        
        $summary = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_invoices,
                SUM(total_amount) as total_billed,
                SUM(paid_amount) as total_paid,
                SUM(balance) as total_outstanding
            FROM $table 
            $where_clause"
        );
        
        return array(
            'invoices' => $invoices,
            'summary' => array(
                'total_invoices' => intval($summary->total_invoices),
                'total_billed' => floatval($summary->total_billed),
                'total_paid' => floatval($summary->total_paid),
                'total_outstanding' => floatval($summary->total_outstanding)
            )
        );
    }
    
    /**
     * Get queries report
     */
    public static function get_queries_report() {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_queries';
        
        // Count by status
        $by_status = $wpdb->get_results(
            "SELECT 
                status,
                COUNT(*) as count
            FROM $table 
            GROUP BY status"
        );
        
        // Count by category
        $by_category = $wpdb->get_results(
            "SELECT 
                category,
                COUNT(*) as count
            FROM $table 
            GROUP BY category"
        );
        
        // Average resolution time
        $avg_resolution = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) 
            FROM $table 
            WHERE status = 'resolved' 
            AND resolved_at IS NOT NULL"
        );
        
        // Recent queries
        $recent = $wpdb->get_results(
            "SELECT * FROM $table 
            ORDER BY created_at DESC 
            LIMIT 10"
        );
        
        return array(
            'by_status' => $by_status,
            'by_category' => $by_category,
            'avg_resolution_hours' => round($avg_resolution, 2),
            'recent_queries' => $recent
        );
    }
    
    /**
     * Get WhatsApp report
     */
    public static function get_whatsapp_report() {
        if (!class_exists('DKM_WhatsApp')) {
            return array('error' => 'WhatsApp class not found');
        }
        
        $stats = DKM_WhatsApp::get_statistics();
        $recent_logs = DKM_WhatsApp::get_logs(20);
        
        return array(
            'statistics' => $stats,
            'recent_logs' => $recent_logs,
            'is_enabled' => DKM_WhatsApp::is_enabled()
        );
    }
    
    /**
     * Export report to CSV
     */
    public static function export_to_csv($report_type, $data, $filename = null) {
        if (!current_user_can('export_dkm_reports')) {
            wp_die('Permission denied');
        }
        
        if (!$filename) {
            $filename = $report_type . '-' . date('Y-m-d') . '.csv';
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Write headers based on report type
        switch ($report_type) {
            case 'sales':
                fputcsv($output, array('Date', 'Orders', 'Amount'));
                foreach ($data as $row) {
                    fputcsv($output, array($row['date'], $row['orders'], $row['amount']));
                }
                break;
                
            case 'products':
                fputcsv($output, array('Product', 'SKU', 'Quantity', 'Revenue', 'Cost', 'Profit'));
                foreach ($data as $row) {
                    fputcsv($output, array(
                        $row['name'],
                        $row['sku'],
                        $row['quantity'],
                        $row['revenue'],
                        $row['cost'],
                        $row['profit']
                    ));
                }
                break;
                
            case 'trainees':
                fputcsv($output, array('Name', 'Email', 'Orders', 'Total Spent', 'Current Balance'));
                foreach ($data as $row) {
                    fputcsv($output, array(
                        $row['name'],
                        $row['email'],
                        $row['orders'],
                        $row['total_spent'],
                        $row['current_balance']
                    ));
                }
                break;
                
            case 'payments':
                fputcsv($output, array('Date', 'Type', 'Amount', 'Count'));
                foreach ($data as $row) {
                    fputcsv($output, array(
                        $row->date,
                        'Payment',
                        $row->total_amount,
                        $row->count
                    ));
                }
                break;
                
            case 'stock':
                fputcsv($output, array('Product', 'SKU', 'Stock In', 'Stock Out', 'Net', 'Current Stock'));
                foreach ($data as $row) {
                    fputcsv($output, array(
                        $row['product_name'],
                        $row['sku'],
                        $row['stock_in'],
                        $row['stock_out'],
                        $row['net_movement'],
                        $row['current_stock']
                    ));
                }
                break;
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get comparison data (current vs previous period)
     */
    public static function get_comparison_report($start_date, $end_date) {
        // Current period
        $current = self::get_sales_by_date($start_date, $end_date);
        
        // Calculate previous period
        $days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
        $prev_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $prev_start = date('Y-m-d', strtotime($prev_end . ' -' . $days_diff . ' days'));
        
        $previous = self::get_sales_by_date($prev_start, $prev_end);
        
        // Calculate changes
        $revenue_change = $previous['total'] > 0 
            ? (($current['total'] - $previous['total']) / $previous['total']) * 100 
            : 0;
            
        $orders_change = $previous['orders'] > 0 
            ? (($current['orders'] - $previous['orders']) / $previous['orders']) * 100 
            : 0;
        
        return array(
            'current_period' => array(
                'start' => $start_date,
                'end' => $end_date,
                'revenue' => $current['total'],
                'orders' => $current['orders']
            ),
            'previous_period' => array(
                'start' => $prev_start,
                'end' => $prev_end,
                'revenue' => $previous['total'],
                'orders' => $previous['orders']
            ),
            'changes' => array(
                'revenue_change' => round($revenue_change, 2),
                'orders_change' => round($orders_change, 2),
                'revenue_trend' => $revenue_change >= 0 ? 'up' : 'down',
                'orders_trend' => $orders_change >= 0 ? 'up' : 'down'
            )
        );
    }
    
    /**
     * Get chart data for dashboard
     */
    public static function get_chart_data($type = 'sales', $period = 'week') {
        switch ($period) {
            case 'today':
                $start = date('Y-m-d');
                $end = date('Y-m-d');
                break;
            case 'week':
                $start = date('Y-m-d', strtotime('-7 days'));
                $end = date('Y-m-d');
                break;
            case 'month':
                $start = date('Y-m-01');
                $end = date('Y-m-t');
                break;
            case 'year':
                $start = date('Y-01-01');
                $end = date('Y-12-31');
                break;
            default:
                $start = date('Y-m-d', strtotime('-30 days'));
                $end = date('Y-m-d');
        }
        
        if ($type === 'sales') {
            $report = self::get_daily_sales_report($start);
            return $report['hourly'];
        } elseif ($type === 'products') {
            $report = self::get_product_sales_report($start, $end);
            return array_slice($report['products'], 0, 10);
        }
        
        return array();
    }
    
    /**
     * Generate PDF report (requires TCPDF or similar)
     */
    public static function generate_pdf_report($report_type, $data, $filename = null) {
        // This is a placeholder for PDF generation
        // You can implement with TCPDF, FPDF, or DomPDF
        
        if (!$filename) {
            $filename = $report_type . '-' . date('Y-m-d') . '.pdf';
        }
        
        // For now, just return error
        return new WP_Error('not_implemented', 'PDF generation not implemented yet');
    }
    
    /**
     * Schedule automatic reports
     */
    public static function schedule_reports() {
        // Daily report at 11 PM
        if (!wp_next_scheduled('dkm_daily_report')) {
            wp_schedule_event(strtotime('23:00:00'), 'daily', 'dkm_daily_report');
        }
        
        // Weekly report every Monday
        if (!wp_next_scheduled('dkm_weekly_report')) {
            wp_schedule_event(strtotime('next Monday 9:00'), 'weekly', 'dkm_weekly_report');
        }
        
        // Monthly report on 1st of month
        if (!wp_next_scheduled('dkm_monthly_report')) {
            wp_schedule_event(strtotime('first day of next month 9:00'), 'monthly', 'dkm_monthly_report');
        }
    }
    
    /**
     * Send daily report via email
     */
    public static function send_daily_report_email() {
        $admin_email = get_option('admin_email');
        $report = self::get_daily_sales_report();
        
        $subject = 'Deshi Kitchen - Daily Sales Report - ' . date('d M Y');
        
        $currency = get_option('afg_currency_symbol', '₹');
        
        $message = "Daily Sales Report\n\n";
        $message .= "Date: " . date('d M Y') . "\n";
        $message .= "Total Orders: " . $report['summary']['orders'] . "\n";
        $message .= "Total Sales: {$currency}" . number_format($report['summary']['total'], 2) . "\n";
        $message .= "Average Order: {$currency}" . number_format($report['summary']['average'], 2) . "\n\n";
        $message .= "View detailed report in admin panel.\n";
        $message .= admin_url('admin.php?page=deshi-reports');
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Clear schedule on deactivation
     */
    public static function clear_scheduled_reports() {
        wp_clear_scheduled_hook('dkm_daily_report');
        wp_clear_scheduled_hook('dkm_weekly_report');
        wp_clear_scheduled_hook('dkm_monthly_report');
    }
}

// Hook for scheduled reports
add_action('dkm_daily_report', array('DKM_Reports', 'send_daily_report_email'));