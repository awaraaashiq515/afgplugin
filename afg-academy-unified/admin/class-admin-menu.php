<?php
/**
 * Admin Menu Management
 * Creates admin panel menu structure
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_Admin_Menu {
    
    /**
     * Register all admin menus
     */
    public static function register_menus() {
        // Main menu - Deshi Kitchen
        add_menu_page(
            __('AFG Academy', 'afg-academy-unified'),
            __('AFG Academy', 'afg-academy-unified'),
            'read', // Minimum capability - dashboard accessible to all
            'deshi-kitchen',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-store',
            56
        );
        
        // Dashboard submenu (same as main)
        add_submenu_page(
            'deshi-kitchen',
            __('Dashboard', 'afg-academy-unified'),
            __('Dashboard', 'afg-academy-unified'),
            'read',
            'deshi-kitchen',
            array(__CLASS__, 'render_dashboard')
        );
        
    // Products
        add_submenu_page(
            'deshi-kitchen',
            __('Kitchen Products', 'afg-academy-unified'),
            __('Products', 'afg-academy-unified'),
            'view_dkm_products',
            'deshi-products',
            array(__CLASS__, 'render_products')
        );
        
        // Trainees Management
        add_submenu_page(
            'deshi-kitchen',
            __('Trainees Management', 'afg-academy-unified'),
            __('Trainees', 'afg-academy-unified'),
            'manage_dkm_trainees',
            'deshi-trainees',
            array(__CLASS__, 'render_trainees')
        );
        
      
        // Subscriptions
        add_submenu_page(
            'deshi-kitchen',
            __('Subscriptions', 'afg-academy-unified'),
            __('Subscriptions', 'afg-academy-unified'),
            'manage_dkm_subscriptions',
            'deshi-subscriptions',
            array(__CLASS__, 'render_subscriptions')
        );
        
                
            // Invoices
        add_submenu_page(
            'deshi-kitchen',
            __('Monthly Invoices', 'afg-academy-unified'),
            __('Invoices', 'afg-academy-unified'),
            'manage_dkm_invoices',
            'deshi-invoices',
            array(__CLASS__, 'render_invoices')
        );
                
                // Support Queries
               // Queries
        add_submenu_page(
            'deshi-kitchen',
            __('Support Queries', 'afg-academy-unified'),
            __('Support', 'afg-academy-unified'),
            'manage_dkm_queries',
            'deshi-queries',
            array(__CLASS__, 'render_queries')
        );
        
        // Reports
        add_submenu_page(
            'deshi-kitchen',
            __('Reports & Analytics', 'afg-academy-unified'),
            __('Reports', 'afg-academy-unified'),
            'view_dkm_reports',
            'deshi-reports',
            array(__CLASS__, 'render_reports')
        );
        
        // Settings
        add_submenu_page(
            'deshi-kitchen',
            __('Settings', 'afg-academy-unified'),
            __('Settings', 'afg-academy-unified'),
            'manage_dkm_settings',
            'deshi-settings',
            array(__CLASS__, 'render_settings')
        );
    }
    
    /**
     * Dashboard page
     */
    public static function render_dashboard() {
        // Check permission
        if (!current_user_can('read')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Load dashboard view
        $view_file = DKM_ADMIN_VIEWS_DIR . 'dashboard.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Dashboard', 'afg-academy-unified') . '</h1>';
            echo '<div class="notice notice-warning"><p>' . __('Dashboard view file not found.', 'afg-academy-unified') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Products page
     */
    public static function render_products() {
        if (!current_user_can('view_dkm_products')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = DKM_ADMIN_VIEWS_DIR . 'products.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Kitchen Products', 'afg-academy-unified') . '</h1>';
            echo '<div class="notice notice-info">';
            echo '<p>' . __('Products are managed through WooCommerce. Go to:', 'afg-academy-unified') . '</p>';
            echo '<p><a href="' . admin_url('edit.php?post_type=product') . '" class="button button-primary">';
            echo __('WooCommerce Products', 'afg-academy-unified') . '</a></p>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Trainees page
     */
    public static function render_trainees() {
        if (!current_user_can('manage_dkm_trainees')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = DKM_ADMIN_VIEWS_DIR . 'trainees.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            self::render_placeholder('Trainees Management', 'trainees.php');
        }
    }
    
    /**
     * Subscriptions page
     */
    public static function render_subscriptions() {
        if (!current_user_can('manage_dkm_subscriptions')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = DKM_ADMIN_VIEWS_DIR . 'subscriptions.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            self::render_placeholder('Subscriptions', 'subscriptions.php');
        }
    }
    
    /**
     * Invoices page
     */
    public static function render_invoices() {
        if (!current_user_can('manage_dkm_invoices')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = DKM_ADMIN_VIEWS_DIR . 'invoices.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            self::render_placeholder('Monthly Invoices', 'invoices.php');
        }
    }
    
    /**
     * Queries page
     */
    public static function render_queries() {
        if (!current_user_can('manage_dkm_queries')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = DKM_ADMIN_VIEWS_DIR . 'queries.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            self::render_placeholder('Support Queries', 'queries.php');
        }
    }
    
    /**
     * Reports page
     */
    public static function render_reports() {
        if (!current_user_can('view_dkm_reports')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = DKM_ADMIN_VIEWS_DIR . 'reports.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            self::render_placeholder('Reports & Analytics', 'reports.php');
        }
    }
    
    /**
     * Settings page
     */
    public static function render_settings() {
        if (!current_user_can('manage_dkm_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Handle form submission
        if (isset($_POST['dkm_save_settings']) && check_admin_referer('dkm_settings_nonce')) {
            self::save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'afg-academy-unified') . '</p></div>';
        }
        
        $view_file = DKM_ADMIN_VIEWS_DIR . 'settings.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            self::render_settings_page();
        }
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        $settings = array(
            'dkm_currency_symbol',
            'dkm_credit_limit',
            'dkm_low_stock_threshold',
            'dkm_whatsapp_enabled',
            'dkm_whatsapp_api_key',
            'dkm_whatsapp_phone_id',
            'dkm_admin_whatsapp',
            'dkm_invoice_prefix',
            'dkm_invoice_due_days',
            'dkm_email_notifications',
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }
    }
    
    /**
     * Render basic settings page
     */
    private static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Deshi Kitchen Settings', 'afg-academy-unified'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('dkm_settings_nonce'); ?>
                
                <table class="form-table">
                    <!-- Currency -->
                    <tr>
                        <th scope="row">
                            <label for="dkm_currency_symbol"><?php _e('Currency Symbol', 'afg-academy-unified'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="dkm_currency_symbol" 
                                   name="dkm_currency_symbol" 
                                   value="<?php echo esc_attr(get_option('dkm_currency_symbol', '₹')); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Currency symbol to display (e.g., ₹, $, €)', 'afg-academy-unified'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Credit Limit -->
                    <tr>
                        <th scope="row">
                            <label for="dkm_credit_limit"><?php _e('Credit Limit', 'afg-academy-unified'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dkm_credit_limit" 
                                   name="dkm_credit_limit" 
                                   value="<?php echo esc_attr(get_option('dkm_credit_limit', 5000)); ?>" 
                                   class="regular-text"
                                   step="100">
                            <p class="description"><?php _e('Maximum credit balance allowed per trainee', 'afg-academy-unified'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Low Stock Threshold -->
                    <tr>
                        <th scope="row">
                            <label for="dkm_low_stock_threshold"><?php _e('Low Stock Alert', 'afg-academy-unified'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dkm_low_stock_threshold" 
                                   name="dkm_low_stock_threshold" 
                                   value="<?php echo esc_attr(get_option('dkm_low_stock_threshold', 10)); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Alert when stock falls below this number', 'afg-academy-unified'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- WhatsApp -->
                    <tr>
                        <th scope="row">
                            <label for="dkm_whatsapp_enabled"><?php _e('WhatsApp Notifications', 'afg-academy-unified'); ?></label>
                        </th>
                        <td>
                            <select id="dkm_whatsapp_enabled" name="dkm_whatsapp_enabled">
                                <option value="no" <?php selected(get_option('dkm_whatsapp_enabled', 'no'), 'no'); ?>><?php _e('Disabled', 'afg-academy-unified'); ?></option>
                                <option value="yes" <?php selected(get_option('dkm_whatsapp_enabled', 'no'), 'yes'); ?>><?php _e('Enabled', 'afg-academy-unified'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Invoice Prefix -->
                    <tr>
                        <th scope="row">
                            <label for="dkm_invoice_prefix"><?php _e('Invoice Prefix', 'afg-academy-unified'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="dkm_invoice_prefix" 
                                   name="dkm_invoice_prefix" 
                                   value="<?php echo esc_attr(get_option('dkm_invoice_prefix', 'INV')); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Prefix for invoice numbers (e.g., INV-2024-001)', 'afg-academy-unified'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Invoice Due Days -->
                    <tr>
                        <th scope="row">
                            <label for="dkm_invoice_due_days"><?php _e('Invoice Due Days', 'afg-academy-unified'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="dkm_invoice_due_days" 
                                   name="dkm_invoice_due_days" 
                                   value="<?php echo esc_attr(get_option('dkm_invoice_due_days', 7)); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Number of days from invoice generation to due date', 'afg-academy-unified'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="dkm_save_settings" class="button button-primary">
                        <?php _e('Save Settings', 'afg-academy-unified'); ?>
                    </button>
                </p>
            </form>
            
            <!-- System Info -->
            <hr>
            <h2><?php _e('System Information', 'afg-academy-unified'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Plugin Version', 'afg-academy-unified'); ?></strong></td>
                        <td><?php echo DKM_ADMIN_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Database Version', 'afg-academy-unified'); ?></strong></td>
                        <td><?php echo DKM_Database::get_db_version(); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WooCommerce Active', 'afg-academy-unified'); ?></strong></td>
                        <td><?php echo class_exists('WooCommerce') ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Tables Exist', 'afg-academy-unified'); ?></strong></td>
                        <td><?php echo DKM_Database::tables_exist() ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Total Trainees', 'afg-academy-unified'); ?></strong></td>
                        <td><?php echo count(DKM_Roles_Capabilities::get_trainees()); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('POS Operators', 'afg-academy-unified'); ?></strong></td>
                        <td><?php echo count(DKM_Roles_Capabilities::get_pos_operators()); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render placeholder page for missing views
     */
    private static function render_placeholder($title, $filename) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <div class="notice notice-warning">
                <p><strong><?php _e('View file not found!', 'afg-academy-unified'); ?></strong></p>
                <p><?php printf(__('Please create the view file: %s', 'afg-academy-unified'), '<code>' . $filename . '</code>'); ?></p>
                <p><?php printf(__('Location: %s', 'afg-academy-unified'), '<code>' . DKM_ADMIN_VIEWS_DIR . '</code>'); ?></p>
            </div>
            
            <div style="background: #fff; border: 1px solid #ddd; padding: 20px; margin-top: 20px;">
                <h2><?php _e('Page Under Development', 'afg-academy-unified'); ?></h2>
                <p><?php _e('This page is under development. The view file will be created soon.', 'afg-academy-unified'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add admin body classes
     */
    public static function add_admin_body_class($classes) {
        $screen = get_current_screen();
        
        if ($screen && strpos($screen->id, 'deshi-') !== false) {
            $classes .= ' dkm-admin-page';
        }
        
        return $classes;
    }
    
    /**
     * Get current menu slug
     */
    public static function get_current_menu_slug() {
        return isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    }
    
    /**
     * Check if on specific admin page
     */
    public static function is_admin_page($page_slug) {
        return self::get_current_menu_slug() === $page_slug;
    }
}

// Add admin body class
add_filter('admin_body_class', array('DKM_Admin_Menu', 'add_admin_body_class'));