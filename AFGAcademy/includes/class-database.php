<?php
/**
 * Database Management Class
 * Creates and manages all database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class DKM_Database {
    
    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        self::create_trainee_ledger_table($charset_collate);
        self::create_stock_movements_table($charset_collate);
        self::create_subscriptions_table($charset_collate);
        self::create_invoices_table($charset_collate);
        self::create_queries_table($charset_collate);
        self::create_whatsapp_logs_table($charset_collate);
        
        // Update version
        update_option('dkm_admin_db_version', DKM_ADMIN_VERSION);
    }
    
    /**
     * Trainee Ledger Table
     * Stores all credit/debit transactions for trainees
     */
    private static function create_trainee_ledger_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'trainee_ledger';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainee_id bigint(20) UNSIGNED NOT NULL COMMENT 'WordPress User ID',
            type varchar(20) NOT NULL COMMENT 'charge or payment',
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            balance_after decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Balance after this transaction',
            reference_type varchar(50) DEFAULT NULL COMMENT 'order, invoice, manual, subscription',
            reference_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WooCommerce Order ID or Invoice ID',
            note text DEFAULT NULL COMMENT 'Transaction description',
            created_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user who created this entry',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trainee_id (trainee_id),
            KEY type (type),
            KEY reference_type (reference_type),
            KEY created_at (created_at),
            KEY balance_after (balance_after)
        ) $charset_collate COMMENT='Trainee credit/debit ledger';";
        
        dbDelta($sql);
    }
    
    /**
     * Stock Movements Table
     * Logs all stock changes (in/out)
     */
    private static function create_stock_movements_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'kitchen_stock_movements';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL COMMENT 'WooCommerce Product ID',
            change_qty int(11) NOT NULL COMMENT 'Positive for IN, Negative for OUT',
            stock_before int(11) NOT NULL DEFAULT 0,
            stock_after int(11) NOT NULL DEFAULT 0,
            reason varchar(255) DEFAULT NULL,
            movement_type varchar(50) DEFAULT NULL COMMENT 'sale, purchase, adjustment, waste, return',
            reference_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Order ID or related reference',
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY movement_type (movement_type),
            KEY created_at (created_at)
        ) $charset_collate COMMENT='Product stock movement log';";
        
        dbDelta($sql);
    }
    
    /**
     * Subscriptions Table
     * Monthly recurring packages (training, accommodation, etc.)
     */
    private static function create_subscriptions_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_subscriptions';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainee_id bigint(20) UNSIGNED NOT NULL,
            package_name varchar(255) NOT NULL,
            package_type varchar(50) DEFAULT 'training' COMMENT 'training, accommodation, combo',
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            billing_cycle varchar(20) DEFAULT 'monthly' COMMENT 'monthly, quarterly, yearly',
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            next_billing_date date DEFAULT NULL,
            status varchar(50) DEFAULT 'active' COMMENT 'active, paused, cancelled, expired',
            auto_renew tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trainee_id (trainee_id),
            KEY status (status),
            KEY next_billing_date (next_billing_date)
        ) $charset_collate COMMENT='Trainee subscription packages';";
        
        dbDelta($sql);
    }
    
    /**
     * Monthly Invoices Table
     * Generated monthly bills for trainees
     */
    private static function create_invoices_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_invoices';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) UNIQUE NOT NULL,
            trainee_id bigint(20) UNSIGNED NOT NULL,
            invoice_month varchar(7) NOT NULL COMMENT 'YYYY-MM format',
            training_fee decimal(10,2) DEFAULT 0.00,
            accommodation_fee decimal(10,2) DEFAULT 0.00,
            kitchen_charges decimal(10,2) DEFAULT 0.00,
            other_charges decimal(10,2) DEFAULT 0.00,
            discount decimal(10,2) DEFAULT 0.00,
            total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            paid_amount decimal(10,2) DEFAULT 0.00,
            balance decimal(10,2) NOT NULL DEFAULT 0.00,
            due_date date DEFAULT NULL,
            payment_status varchar(50) DEFAULT 'unpaid' COMMENT 'unpaid, partial, paid, overdue',
            payment_date datetime DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL COMMENT 'cash, online, upi, bank_transfer',
            payment_reference varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            sent_at datetime DEFAULT NULL COMMENT 'When invoice was sent via email/WhatsApp',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY trainee_id (trainee_id),
            KEY invoice_month (invoice_month),
            KEY payment_status (payment_status),
            KEY due_date (due_date)
        ) $charset_collate COMMENT='Monthly invoices for trainees';";
        
        dbDelta($sql);
    }
    
    /**
     * Support Queries Table
     * Student support tickets/queries
     */
    private static function create_queries_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_queries';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_number varchar(50) UNIQUE NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            user_name varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            user_phone varchar(20) DEFAULT NULL,
            subject varchar(255) NOT NULL,
            message text NOT NULL,
            category varchar(100) DEFAULT 'general' COMMENT 'general, billing, kitchen, training, technical',
            priority varchar(20) DEFAULT 'medium' COMMENT 'low, medium, high, urgent',
            attachments text DEFAULT NULL COMMENT 'JSON array of file URLs',
            status varchar(50) DEFAULT 'new' COMMENT 'new, in_progress, resolved, closed',
            assigned_to bigint(20) UNSIGNED DEFAULT NULL,
            admin_notes text DEFAULT NULL,
            resolved_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ticket_number (ticket_number),
            KEY user_id (user_id),
            KEY status (status),
            KEY category (category),
            KEY created_at (created_at)
        ) $charset_collate COMMENT='Support queries and tickets';";
        
        dbDelta($sql);
    }
    
    /**
     * WhatsApp Logs Table
     * Track all WhatsApp messages sent
     */
    private static function create_whatsapp_logs_table($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_whatsapp_logs';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID',
            phone varchar(20) NOT NULL,
            template_name varchar(100) DEFAULT NULL,
            message_type varchar(50) DEFAULT NULL COMMENT 'template, text, document',
            message_content text DEFAULT NULL,
            status varchar(50) DEFAULT 'sent' COMMENT 'sent, delivered, failed, read',
            response text DEFAULT NULL COMMENT 'API response',
            error_message text DEFAULT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY recipient_id (recipient_id),
            KEY phone (phone),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate COMMENT='WhatsApp message logs';";
        
        dbDelta($sql);
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'trainee_ledger',
            $wpdb->prefix . 'kitchen_stock_movements',
            $wpdb->prefix . 'deshi_subscriptions',
            $wpdb->prefix . 'deshi_invoices',
            $wpdb->prefix . 'deshi_queries',
            $wpdb->prefix . 'deshi_whatsapp_logs'
        );
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get database version
     */
    public static function get_db_version() {
        return get_option('dkm_admin_db_version', '0.0.0');
    }
    
    /**
     * Drop all tables (use with caution!)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'trainee_ledger',
            $wpdb->prefix . 'kitchen_stock_movements',
            $wpdb->prefix . 'deshi_subscriptions',
            $wpdb->prefix . 'deshi_invoices',
            $wpdb->prefix . 'deshi_queries',
            $wpdb->prefix . 'deshi_whatsapp_logs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('dkm_admin_db_version');
    }
    
    /**
     * Insert sample data for testing
     */
    public static function insert_sample_data() {
        global $wpdb;
        
        // Check if already has data
        $ledger_table = $wpdb->prefix . 'trainee_ledger';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $ledger_table");
        
        if ($count > 0) {
            return; // Already has data
        }
        
        // Get a sample trainee (first customer user)
        $trainee = get_users(array(
            'role' => 'customer',
            'number' => 1
        ));
        
        if (empty($trainee)) {
            return; // No trainee found
        }
        
        $trainee_id = $trainee[0]->ID;
        
        // Insert sample ledger entries
        $entries = array(
            array(
                'trainee_id' => $trainee_id,
                'type' => 'charge',
                'amount' => 500.00,
                'balance_after' => 500.00,
                'reference_type' => 'manual',
                'note' => 'Opening balance'
            ),
            array(
                'trainee_id' => $trainee_id,
                'type' => 'payment',
                'amount' => 200.00,
                'balance_after' => 300.00,
                'reference_type' => 'manual',
                'note' => 'Cash payment received'
            )
        );
        
        foreach ($entries as $entry) {
            $wpdb->insert($ledger_table, $entry);
        }
    }
}