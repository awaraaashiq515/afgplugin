<?php
/**
 * Plugin Name: AFG Academy Unified
 * Plugin URI: https://afgacademy.com/
 * Description: Complete kitchen management, POS, trainee ledger, invoicing, and WooCommerce integration in one plugin
 * Version: 3.0.0
 * Author: Tarun
 * Author URI: https://afgacademy.com/
 * Text Domain: afg-academy-unified
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
define('AFG_UNIFIED_VERSION', '3.0.0');
define('AFG_UNIFIED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AFG_UNIFIED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AFG_UNIFIED_INCLUDES_DIR', AFG_UNIFIED_PLUGIN_DIR . 'includes/');
define('AFG_UNIFIED_VIEWS_DIR', AFG_UNIFIED_PLUGIN_DIR . 'admin/views/');
define('AFG_UNIFIED_ASSETS_URL', AFG_UNIFIED_PLUGIN_URL . 'assets/');

/**
 * Main Plugin Class
 */
class AFG_Academy_Unified {

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
            <p><strong><?php _e('AFG Academy Unified', 'afg-academy-unified'); ?></strong></p>
            <p><?php _e('WooCommerce plugin is required for this plugin to work. Please install and activate WooCommerce.', 'afg-academy-unified'); ?></p>
        </div>
        <?php
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-database.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-roles-capabilities.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-woocommerce-integration.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-trainee-ledger.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-whatsapp.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-reports.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-admission-packages.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-admission.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-permissions.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-pos-handler.php';
        require_once AFG_UNIFIED_INCLUDES_DIR . 'class-order-creator.php';

        // Admin classes
        require_once AFG_UNIFIED_PLUGIN_DIR . 'admin/class-admin-menu.php';

        // Public classes
        require_once AFG_UNIFIED_PLUGIN_DIR . 'public/class-public-pages.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Admin menu
        add_action('admin_menu', array('AFG_Admin_Menu', 'register_menus'), 10);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX actions for admin
        add_action('wp_ajax_afg_search_trainee', array('AFG_Trainee_Ledger', 'ajax_search_trainee'));
        add_action('wp_ajax_afg_get_trainee_balance', array('AFG_Trainee_Ledger', 'ajax_get_balance'));
        add_action('wp_ajax_afg_add_manual_payment', array('AFG_Trainee_Ledger', 'ajax_add_manual_payment'));
        add_action('wp_ajax_afg_add_manual_charge', array('AFG_Trainee_Ledger', 'ajax_add_manual_charge'));
        add_action('wp_ajax_afg_get_trainee_ledger', array($this, 'ajax_get_trainee_ledger'));

        // AJAX actions for POS
        add_action('wp_ajax_afg_pos_search_trainee', array('AFG_POS_Handler', 'ajax_search_trainee'));
        add_action('wp_ajax_afg_pos_get_products', array('AFG_POS_Handler', 'ajax_get_products'));
        add_action('wp_ajax_afg_pos_create_order', array('AFG_Order_Creator', 'ajax_create_order'));
        add_action('wp_ajax_afg_pos_get_trainee_balance', array('AFG_POS_Handler', 'ajax_get_trainee_balance'));

        // Shortcodes
        add_shortcode('afg_trainee_portal', array('AFG_Public_Pages', 'render_trainee_portal'));
        add_shortcode('afg_query_form', array('AFG_Public_Pages', 'render_query_form'));
        add_shortcode('afg_kitchen_menu', array('AFG_Public_Pages', 'render_kitchen_menu'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        AFG_Database::create_tables();

        // Create roles and capabilities
        AFG_Roles_Capabilities::create_roles();

        // Set default options
        $this->set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        set_transient('afg_unified_activated', true, 30);
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
            'afg_currency_symbol' => '₹',
            'afg_credit_limit' => 5000,
            'afg_low_stock_threshold' => 10,
            'afg_whatsapp_enabled' => 'no',
            'afg_invoice_prefix' => 'INV',
            'afg_invoice_due_days' => 7,
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
        // Load on plugin pages
        if (strpos($hook, 'afg-') === false) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'afg-admin-style',
            AFG_UNIFIED_ASSETS_URL . 'css/admin-style.css',
            array(),
            AFG_UNIFIED_VERSION
        );

        // Admin JS
        wp_enqueue_script(
            'afg-admin-script',
            AFG_UNIFIED_ASSETS_URL . 'js/admin-script.js',
            array('jquery'),
            AFG_UNIFIED_VERSION,
            true
        );

        // Localize script
        wp_localize_script('afg-admin-script', 'afgAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('afg_admin_nonce'),
            'currency_symbol' => get_option('afg_currency_symbol', '₹'),
            'credit_limit' => get_option('afg_credit_limit', 5000),
        ));
    }

    /**
     * AJAX: Get trainee ledger
     */
    public function ajax_get_trainee_ledger() {
        check_ajax_referer('afg_admin_nonce', 'nonce');

        if (!current_user_can('manage_afg_trainees')) {
            wp_send_json_error('Permission denied');
        }

        $trainee_id = isset($_POST['trainee_id']) ? intval($_POST['trainee_id']) : 0;

        $summary = AFG_Trainee_Ledger::get_summary($trainee_id);
        $history = AFG_Trainee_Ledger::get_ledger_history($trainee_id, 50);

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
    }
}

/**
 * Initialize the plugin
 */
function afg_unified_init() {
    return AFG_Academy_Unified::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'afg_unified_init');

/**
 * Show admin notice on activation
 */
add_action('admin_notices', function() {
    if (get_transient('afg_unified_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php _e('AFG Academy Unified', 'afg-academy-unified'); ?></strong></p>
            <p><?php _e('Plugin activated successfully! Database tables created and roles configured.', 'afg-academy-unified'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=afg-dashboard'); ?>" class="button button-primary">
                <?php _e('Go to Dashboard', 'afg-academy-unified'); ?>
            </a></p>
        </div>
        <?php
        delete_transient('afg_unified_activated');
    }
});