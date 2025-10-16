<?php
/**
 * Plugin Name: Kitchen POS
 * Plugin URI: https://afgacademy.com/
 * Description: Standalone Point of Sale system for kitchen counter with trainee credit management
 * Version: 1.0.0
 * Author: Tarun
 * Author URI: https://afgacademy.com/
 * Text Domain: kitchen-pos
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('KPOS_VERSION', '1.0.0');
define('KPOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KPOS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KPOS_INCLUDES_DIR', KPOS_PLUGIN_DIR . 'includes/');
define('KPOS_VIEWS_DIR', KPOS_PLUGIN_DIR . 'views/');
define('KPOS_ASSETS_URL', KPOS_PLUGIN_URL . 'assets/');

/**
 * Main Kitchen POS Class
 */
class Kitchen_POS {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Check dependencies
        add_action('plugins_loaded', array($this, 'check_dependencies'));
        
        // Initialize plugin
        if ($this->dependencies_met()) {
            $this->load_dependencies();
            $this->init_hooks();
        }
    }
    
    /**
     * Check if dependencies are met
     */
    private function dependencies_met() {
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        // Check Deshi Kitchen Admin plugin
        if (!class_exists('Deshi_Kitchen_Admin')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check dependencies
     */
    public function check_dependencies() {
        if (!$this->dependencies_met()) {
            add_action('admin_notices', array($this, 'dependencies_notice'));
            return;
        }
    }
    
    /**
     * Dependencies missing notice
     */
    public function dependencies_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Kitchen POS', 'kitchen-pos'); ?></strong></p>
            <?php if (!class_exists('WooCommerce')): ?>
            <p><?php _e('WooCommerce plugin is required. Please install and activate WooCommerce.', 'kitchen-pos'); ?></p>
            <?php endif; ?>
            <?php if (!class_exists('Deshi_Kitchen_Admin')): ?>
            <p><?php _e('Deshi Kitchen Admin plugin is required. Please install and activate it first.', 'kitchen-pos'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once KPOS_INCLUDES_DIR . 'class-permissions.php';
        require_once KPOS_INCLUDES_DIR . 'class-pos-handler.php';
        require_once KPOS_INCLUDES_DIR . 'class-order-creator.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add POS menu
        add_action('admin_menu', array($this, 'add_pos_menu'), 20);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_kpos_search_trainee', array('KPOS_Handler', 'ajax_search_trainee'));
        add_action('wp_ajax_kpos_get_products', array('KPOS_Handler', 'ajax_get_products'));
        add_action('wp_ajax_kpos_create_order', array('KPOS_Order_Creator', 'ajax_create_order'));
        add_action('wp_ajax_kpos_get_trainee_balance', array('KPOS_Handler', 'ajax_get_trainee_balance'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check if admin plugin is active
        if (!class_exists('Deshi_Kitchen_Admin')) {
            wp_die(__('Please install and activate "Deshi Kitchen Admin" plugin first.', 'kitchen-pos'));
        }
        
        // Set activation flag
        set_transient('kpos_activated', true, 30);
    }
    
    /**
     * Add POS menu with submenus
     */
    public function add_pos_menu() {
        // Check permission
        if (!KPOS_Permissions::can_access_pos()) {
            return;
        }
        
        // Main menu - Dashboard
        add_menu_page(
            __('Kitchen POS', 'kitchen-pos'),
            __('Kitchen POS', 'kitchen-pos'),
            'manage_dkm_pos',
            'kitchen-pos',
            array($this, 'render_dashboard'),
            'dashicons-store',
            57
        );
        
        // Submenu: Dashboard (rename first item)
        add_submenu_page(
            'kitchen-pos',
            __('Dashboard', 'kitchen-pos'),
            __('Dashboard', 'kitchen-pos'),
            'manage_dkm_pos',
            'kitchen-pos',
            array($this, 'render_dashboard')
        );
        
        // Submenu: Open POS
        add_submenu_page(
            'kitchen-pos',
            __('Open POS', 'kitchen-pos'),
            __('Open POS', 'kitchen-pos'),
            'manage_dkm_pos',
            'kitchen-pos-terminal',
            array($this, 'render_pos_terminal')
        );
        
        // Submenu: Reports (if user has permission)
        if (current_user_can('view_dkm_reports')) {
            add_submenu_page(
                'kitchen-pos',
                __('Reports', 'kitchen-pos'),
                __('Reports', 'kitchen-pos'),
                'view_dkm_reports',
                'deshi-reports',
                '__return_false' // Handled by Deshi Kitchen Admin
            );
        }
        
        // Submenu: Trainees (if user has permission)
        if (current_user_can('manage_dkm_trainees')) {
            add_submenu_page(
                'kitchen-pos',
                __('Trainees', 'kitchen-pos'),
                __('Trainees', 'kitchen-pos'),
                'manage_dkm_trainees',
                'deshi-trainees',
                '__return_false' // Handled by Deshi Kitchen Admin
            );
        }
        
        // Submenu: Products (if user has permission)
        if (current_user_can('view_dkm_products')) {
            add_submenu_page(
                'kitchen-pos',
                __('Products', 'kitchen-pos'),
                __('Products', 'kitchen-pos'),
                'view_dkm_products',
                'deshi-products',
                '__return_false' // Handled by Deshi Kitchen Admin
            );
        }
        
        // Submenu: Orders
        add_submenu_page(
            'kitchen-pos',
            __('All Orders', 'kitchen-pos'),
            __('All Orders', 'kitchen-pos'),
            'manage_dkm_pos',
            'edit.php?post_type=shop_order',
            '__return_false' // WooCommerce orders
        );
    }
    
    /**
     * Render Dashboard
     */
    public function render_dashboard() {
        if (!KPOS_Permissions::can_access_pos()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = KPOS_VIEWS_DIR . 'dashboard.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Kitchen POS Dashboard', 'kitchen-pos') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Dashboard view file not found.', 'kitchen-pos') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Render POS Terminal
     */
    public function render_pos_terminal() {
        if (!KPOS_Permissions::can_access_pos()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $view_file = KPOS_VIEWS_DIR . 'pos-screen.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Kitchen POS', 'kitchen-pos') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('POS screen view file not found.', 'kitchen-pos') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Load on both dashboard and POS terminal pages
        if ($hook !== 'toplevel_page_kitchen-pos' && $hook !== 'kitchen-pos_page_kitchen-pos-terminal') {
            return;
        }
        
        // Check if we're on dashboard or POS terminal
        if ($hook === 'kitchen-pos_page_kitchen-pos-terminal') {
            // POS Terminal CSS & JS
            wp_enqueue_style(
                'kpos-style',
                KPOS_ASSETS_URL . 'css/pos.css',
                array(),
                KPOS_VERSION
            );
            
            wp_enqueue_script(
                'kpos-script',
                KPOS_ASSETS_URL . 'js/pos.js',
                array('jquery'),
                KPOS_VERSION,
                true
            );
            
            // Localize script for POS
            wp_localize_script('kpos-script', 'kposData', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kpos_nonce'),
                'currency_symbol' => get_option('dkm_currency_symbol', '₹'),
                'credit_limit' => get_option('dkm_credit_limit', 5000),
                'strings' => array(
                    'search_placeholder' => __('Search trainee...', 'kitchen-pos'),
                    'no_trainee' => __('Please select a trainee', 'kitchen-pos'),
                    'empty_cart' => __('Cart is empty', 'kitchen-pos'),
                    'order_success' => __('Order created successfully!', 'kitchen-pos'),
                    'order_error' => __('Failed to create order', 'kitchen-pos'),
                    'credit_limit_exceeded' => __('Credit limit exceeded!', 'kitchen-pos'),
                    'confirm_order' => __('Confirm order?', 'kitchen-pos'),
                )
            ));
        } else {
            // Dashboard CSS & JS
            wp_enqueue_style(
                'kpos-dashboard-style',
                KPOS_ASSETS_URL . 'css/dashboard.css',
                array(),
                KPOS_VERSION
            );
            
            wp_enqueue_script(
                'kpos-dashboard-script',
                KPOS_ASSETS_URL . 'js/dashboard.js',
                array('jquery'),
                KPOS_VERSION,
                true
            );
        }
    }
}

/**
 * Initialize the plugin
 */
function kpos_init() {
    return Kitchen_POS::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'kpos_init');

/**
 * Show admin notice on activation
 */
add_action('admin_notices', function() {
    if (get_transient('kpos_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php _e('Kitchen POS', 'kitchen-pos'); ?></strong></p>
            <p><?php _e('Plugin activated successfully! Access POS from the menu.', 'kitchen-pos'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=kitchen-pos'); ?>" class="button button-primary">
                <?php _e('Open Dashboard', 'kitchen-pos'); ?>
            </a></p>
        </div>
        <?php
        delete_transient('kpos_activated');
    }
});
class KPOS_Screen {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function enqueue_scripts($hook) {
        // Only on POS page
        if ($hook !== 'toplevel_page_kitchen-pos-terminal') {
            return;
        }
        
        // Enqueue Razorpay
        wp_enqueue_script(
            'razorpay-checkout',
            'https://checkout.razorpay.com/v1/checkout.js',
            array(),
            null,
            false // Load in header
        );
        
        // Then your custom script
        wp_enqueue_script(
            'kpos-script',
            plugins_url('assets/js/pos.js', __FILE__),
            array('jquery', 'razorpay-checkout'), // Depends on Razorpay
            '1.0.0',
            true
        );
        
        // Localize script
        wp_localize_script('kpos-script', 'kposConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kpos_nonce'),
            'currency' => get_option('dkm_currency_symbol', '₹'),
            'creditLimit' => get_option('dkm_credit_limit', 5000),
            'razorpayKeyId' => $this->get_razorpay_key(),
            'siteName' => get_bloginfo('name'),
            'siteLogo' => get_site_icon_url()
        ));
    }
    
    private function get_razorpay_key() {
        $settings = get_option('woocommerce_razorpay_settings', array());
        return isset($settings['key_id']) ? $settings['key_id'] : '';
    }
}