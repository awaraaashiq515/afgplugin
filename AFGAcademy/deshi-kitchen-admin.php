<?php
/**
 * Plugin Name: AFGAcademy
 * Plugin URI: https://afgacademy.com/
 * Description: Complete kitchen management system with trainee ledger, invoicing, and WooCommerce integration
 * Version: 2.0.0
 * Author: Tarun
 * Author URI: https://afgacademy.com/
 * Text Domain: deshi-kitchen-admin
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
define('DKM_ADMIN_VERSION', '2.0.0');
define('DKM_ADMIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DKM_ADMIN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DKM_ADMIN_INCLUDES_DIR', DKM_ADMIN_PLUGIN_DIR . 'includes/');
define('DKM_ADMIN_VIEWS_DIR', DKM_ADMIN_PLUGIN_DIR . 'admin/views/');
define('DKM_ADMIN_ASSETS_URL', DKM_ADMIN_PLUGIN_URL . 'assets/');

/**
 * Main Plugin Class
 */
class Deshi_Kitchen_Admin {
    
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
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Initialize plugin
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Deshi Kitchen Admin', 'deshi-kitchen-admin'); ?></strong></p>
            <p><?php _e('WooCommerce plugin is required for this plugin to work. Please install and activate WooCommerce.', 'deshi-kitchen-admin'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once DKM_ADMIN_INCLUDES_DIR . 'class-database.php';
        require_once DKM_ADMIN_INCLUDES_DIR . 'class-roles-capabilities.php';
        require_once DKM_ADMIN_INCLUDES_DIR . 'class-woocommerce-integration.php';
        require_once DKM_ADMIN_INCLUDES_DIR . 'class-trainee-ledger.php';
        require_once DKM_ADMIN_INCLUDES_DIR . 'class-whatsapp.php';
        require_once DKM_ADMIN_INCLUDES_DIR . 'class-reports.php';
    
       
        // Admin classes
        require_once DKM_ADMIN_PLUGIN_DIR . 'admin/class-admin-menu.php';
        
         // Public classes (for shortcodes)
         require_once DKM_ADMIN_PLUGIN_DIR . 'public/class-public-pages.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin menu
        add_action('admin_menu', array('DKM_Admin_Menu', 'register_menus'), 10);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX actions
        add_action('wp_ajax_dkm_search_trainee', array('DKM_Trainee_Ledger', 'ajax_search_trainee'));
        add_action('wp_ajax_dkm_get_trainee_balance', array('DKM_Trainee_Ledger', 'ajax_get_balance'));
        add_action('wp_ajax_dkm_add_manual_payment', array('DKM_Trainee_Ledger', 'ajax_add_manual_payment'));
        add_action('wp_ajax_dkm_add_manual_charge', array('DKM_Trainee_Ledger', 'ajax_add_manual_charge'));
        
        // Shortcodes
        add_shortcode('dkm_trainee_portal', array('DKM_Public_Pages', 'render_trainee_portal'));
        add_shortcode('dkm_query_form', array('DKM_Public_Pages', 'render_query_form'));
        add_shortcode('dkm_kitchen_menu', array('DKM_Public_Pages', 'render_kitchen_menu'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        DKM_Database::create_tables();
        
        // Create roles and capabilities
        DKM_Roles_Capabilities::create_roles();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        set_transient('dkm_admin_activated', true, 30);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'dkm_currency_symbol' => '₹',
            'dkm_credit_limit' => 5000,
            'dkm_low_stock_threshold' => 10,
            'dkm_whatsapp_enabled' => 'no',
            'dkm_invoice_prefix' => 'INV',
            'dkm_invoice_due_days' => 7,
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'deshi-') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'dkm-admin-style',
            DKM_ADMIN_ASSETS_URL . 'css/admin-style.css',
            array(),
            DKM_ADMIN_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'dkm-admin-script',
            DKM_ADMIN_ASSETS_URL . 'js/admin-script.js',
            array('jquery'),
            DKM_ADMIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('dkm-admin-script', 'dkmAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dkm_admin_nonce'),
            'currency_symbol' => get_option('dkm_currency_symbol', '₹'),
            'credit_limit' => get_option('dkm_credit_limit', 5000),
        ));
    }
}

/**
 * Initialize the plugin
 */
function dkm_admin_init() {
    return Deshi_Kitchen_Admin::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'dkm_admin_init');

/**
 * Show admin notice on activation
 */
add_action('admin_notices', function() {
    if (get_transient('dkm_admin_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php _e('Deshi Kitchen Admin', 'deshi-kitchen-admin'); ?></strong></p>
            <p><?php _e('Plugin activated successfully! Database tables created and roles configured.', 'deshi-kitchen-admin'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=deshi-kitchen'); ?>" class="button button-primary">
                <?php _e('Go to Dashboard', 'deshi-kitchen-admin'); ?>
            </a></p>
        </div>
        <?php
        delete_transient('dkm_admin_activated');
    }
});
// Get trainee ledger (already shown in trainees.php)
add_action('wp_ajax_dkm_get_trainee_ledger', function() {
    check_ajax_referer('dkm_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_dkm_trainees')) {
        wp_send_json_error('Permission denied');
    }
    
    $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;
    
    $summary = DKM_Trainee_Ledger::get_summary($trainee_id);
    $history = DKM_Trainee_Ledger::get_ledger_history($trainee_id, 50);
    
    $entries = array();
    foreach ($history as $entry) {
        $entries[] = array(
            'id' => $entry->id,
            'type' => $entry->type,
            'amount' => $entry->amount,
            'balance_after' => $entry->balance_after,
            'note' => $entry->note,
            'date' => date('d M Y, h:i A', strtotime($entry->created_at))
        );
    }
    
    wp_send_json_success(array(
        'summary' => $summary,
        'entries' => $entries
    ));
});
