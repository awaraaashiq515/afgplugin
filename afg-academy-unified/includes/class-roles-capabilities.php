<?php
/**
 * Roles and Capabilities Management
 * Creates custom user roles with specific permissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_Roles_Capabilities {
    
    /**
     * Create all custom roles and capabilities
     */
    public static function create_roles() {
        // Create POS Operator role
        self::create_pos_operator_role();
        
        // Add capabilities to Administrator
        self::add_admin_capabilities();
        
        // Modify Customer role capabilities
        self::modify_customer_capabilities();
    }
    
    /**
     * Create POS Operator Role
     * Limited access - only POS system
     */
    private static function create_pos_operator_role() {
        // Check if role already exists
        if (get_role('pos_operator')) {
            return;
        }
        
        add_role(
            'pos_operator',
            __('POS Operator', 'deshi-kitchen-admin'),
            array(
                'read' => true, // Basic WordPress capability
                
                // POS Capabilities
                'manage_dkm_pos' => true,
                'view_dkm_products' => true,
                'search_dkm_trainees' => true,
                'create_dkm_orders' => true,
                
                // Limited WooCommerce access
                'read_product' => true,
                'read_private_products' => true,
                
                // Cannot edit products or settings
                'edit_products' => false,
                'delete_products' => false,
                'manage_woocommerce' => false,
            )
        );
    }
    
    /**
     * Add capabilities to Administrator role
     */
    private static function add_admin_capabilities() {
        $admin = get_role('administrator');
        
        if (!$admin) {
            return;
        }
        
        // Kitchen Management Capabilities
        $capabilities = array(
            // POS
            'manage_dkm_pos',
            'view_dkm_products',
            'edit_dkm_products',
            'delete_dkm_products',
            'create_dkm_orders',
            
            // Trainees
            'manage_dkm_trainees',
            'view_dkm_trainees',
            'edit_dkm_trainees',
            'view_dkm_ledger',
            'edit_dkm_ledger',
            'search_dkm_trainees',
            
            // Subscriptions
            'manage_dkm_subscriptions',
            'view_dkm_subscriptions',
            'edit_dkm_subscriptions',
            'delete_dkm_subscriptions',
            
            // Invoices
            'manage_dkm_invoices',
            'view_dkm_invoices',
            'create_dkm_invoices',
            'edit_dkm_invoices',
            'delete_dkm_invoices',
            'send_dkm_invoices',
            
            // Reports
            'view_dkm_reports',
            'export_dkm_reports',
            
            // Queries/Support
            'manage_dkm_queries',
            'view_dkm_queries',
            'respond_dkm_queries',
            'delete_dkm_queries',
            
            // Settings
            'manage_dkm_settings',
            
            // WhatsApp
            'manage_dkm_whatsapp',
            'send_dkm_whatsapp',
        );
        
        foreach ($capabilities as $cap) {
            $admin->add_cap($cap);
        }
    }
    
    /**
     * Modify Customer role (Trainees)
     */
    private static function modify_customer_capabilities() {
        $customer = get_role('customer');
        
        if (!$customer) {
            return;
        }
        
        // Add trainee-specific capabilities
        $capabilities = array(
            'view_own_dkm_ledger' => true,
            'view_own_dkm_invoices' => true,
            'submit_dkm_queries' => true,
            'view_dkm_kitchen_menu' => true,
        );
        
        foreach ($capabilities as $cap => $grant) {
            $customer->add_cap($cap, $grant);
        }
    }
    
    /**
     * Create Kitchen Manager Role (Optional)
     * Can manage everything except settings
     */
    public static function create_kitchen_manager_role() {
        if (get_role('kitchen_manager')) {
            return;
        }
        
        add_role(
            'kitchen_manager',
            __('Kitchen Manager', 'deshi-kitchen-admin'),
            array(
                'read' => true,
                
                // Full POS access
                'manage_dkm_pos' => true,
                'view_dkm_products' => true,
                'edit_dkm_products' => true,
                'create_dkm_orders' => true,
                
                // Trainee management
                'manage_dkm_trainees' => true,
                'view_dkm_trainees' => true,
                'view_dkm_ledger' => true,
                'edit_dkm_ledger' => true,
                'search_dkm_trainees' => true,
                
                // Subscriptions
                'manage_dkm_subscriptions' => true,
                'view_dkm_subscriptions' => true,
                'edit_dkm_subscriptions' => true,
                
                // Invoices
                'manage_dkm_invoices' => true,
                'view_dkm_invoices' => true,
                'create_dkm_invoices' => true,
                'edit_dkm_invoices' => true,
                'send_dkm_invoices' => true,
                
                // Reports
                'view_dkm_reports' => true,
                'export_dkm_reports' => true,
                
                // Queries
                'manage_dkm_queries' => true,
                'view_dkm_queries' => true,
                'respond_dkm_queries' => true,
                
                // WooCommerce products (view only)
                'read_product' => true,
                'read_private_products' => true,
                'edit_products' => true,
                
                // Cannot access settings
                'manage_dkm_settings' => false,
                'manage_woocommerce' => false,
            )
        );
    }
    
    /**
     * Remove all custom roles
     */
    public static function remove_roles() {
        remove_role('pos_operator');
        remove_role('kitchen_manager');
    }
    
    /**
     * Remove all custom capabilities from existing roles
     */
    public static function remove_capabilities() {
        $roles = array('administrator', 'customer');
        
        $capabilities = array(
            'manage_dkm_pos',
            'view_dkm_products',
            'edit_dkm_products',
            'delete_dkm_products',
            'create_dkm_orders',
            'manage_dkm_trainees',
            'view_dkm_trainees',
            'edit_dkm_trainees',
            'view_dkm_ledger',
            'edit_dkm_ledger',
            'search_dkm_trainees',
            'manage_dkm_subscriptions',
            'view_dkm_subscriptions',
            'edit_dkm_subscriptions',
            'delete_dkm_subscriptions',
            'manage_dkm_invoices',
            'view_dkm_invoices',
            'create_dkm_invoices',
            'edit_dkm_invoices',
            'delete_dkm_invoices',
            'send_dkm_invoices',
            'view_dkm_reports',
            'export_dkm_reports',
            'manage_dkm_queries',
            'view_dkm_queries',
            'respond_dkm_queries',
            'delete_dkm_queries',
            'manage_dkm_settings',
            'manage_dkm_whatsapp',
            'send_dkm_whatsapp',
            'view_own_dkm_ledger',
            'view_own_dkm_invoices',
            'submit_dkm_queries',
            'view_dkm_kitchen_menu',
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Check if current user has capability
     */
    public static function current_user_can($capability) {
        $admin->add_cap('manage_dkm_pos');
$admin->add_cap('view_dkm_products');
$admin->add_cap('edit_dkm_products');
$admin->add_cap('delete_dkm_products');
$admin->add_cap('create_dkm_orders');
$admin->add_cap('manage_dkm_trainees');
$admin->add_cap('view_dkm_trainees');
$admin->add_cap('edit_dkm_trainees');
$admin->add_cap('view_dkm_ledger');
$admin->add_cap('edit_dkm_ledger');
$admin->add_cap('search_dkm_trainees');
$admin->add_cap('manage_dkm_subscriptions');
$admin->add_cap('view_dkm_subscriptions');
$admin->add_cap('edit_dkm_subscriptions');
$admin->add_cap('delete_dkm_subscriptions');
$admin->add_cap('manage_dkm_invoices');
$admin->add_cap('view_dkm_invoices');
$admin->add_cap('create_dkm_invoices');
$admin->add_cap('edit_dkm_invoices');
$admin->add_cap('delete_dkm_invoices');
$admin->add_cap('send_dkm_invoices');
$admin->add_cap('view_dkm_reports');
$admin->add_cap('export_dkm_reports');
$admin->add_cap('manage_dkm_queries');
$admin->add_cap('view_dkm_queries');
$admin->add_cap('respond_dkm_queries');
$admin->add_cap('delete_dkm_queries');
$admin->add_cap('manage_dkm_settings');
$admin->add_cap('manage_dkm_whatsapp');
$admin->add_cap('send_dkm_whatsapp');

        return current_user_can($capability);
    }
    
    /**
     * Get all users with specific role
     */
    public static function get_users_by_role($role) {
        return get_users(array('role' => $role));
    }
    
    /**
     * Get all POS operators
     */
    public static function get_pos_operators() {
        return self::get_users_by_role('pos_operator');
    }
    
    /**
     * Get all kitchen managers
     */
    public static function get_kitchen_managers() {
        return self::get_users_by_role('kitchen_manager');
    }
    
    /**
     * Get all trainees (customers)
     */
    public static function get_trainees() {
        return self::get_users_by_role('customer');
    }
    
    /**
     * Check if user is trainee
     */
    public static function is_trainee($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array('customer', $user->roles);
    }
    
    /**
     * Check if user is POS operator
     */
    public static function is_pos_operator($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array('pos_operator', $user->roles);
    }
    
    /**
     * Check if user is kitchen manager
     */
    public static function is_kitchen_manager($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        return $user && in_array('kitchen_manager', $user->roles);
    }
    
    /**
     * Check if user can access POS
     */
    public static function can_access_pos($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_dkm_pos');
    }
    
    /**
     * Check if user can manage trainees
     */
    public static function can_manage_trainees($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'manage_dkm_trainees');
    }
    
    /**
     * Check if user can view reports
     */
    public static function can_view_reports($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'view_dkm_reports');
    }
    
    /**
     * Get user's accessible menu items
     */
    public static function get_accessible_menus($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $menus = array();
        
        // Dashboard (all logged in users)
        $menus[] = array(
            'slug' => 'deshi-kitchen',
            'title' => 'Dashboard',
            'capability' => 'read'
        );
        
        // POS
        if (user_can($user_id, 'manage_dkm_pos')) {
            $menus[] = array(
                'slug' => 'deshi-pos',
                'title' => 'POS',
                'capability' => 'manage_dkm_pos'
            );
        }
        
        // Products
        if (user_can($user_id, 'view_dkm_products')) {
            $menus[] = array(
                'slug' => 'deshi-products',
                'title' => 'Products',
                'capability' => 'view_dkm_products'
            );
        }
        
        // Trainees
        if (user_can($user_id, 'manage_dkm_trainees')) {
            $menus[] = array(
                'slug' => 'deshi-trainees',
                'title' => 'Trainees',
                'capability' => 'manage_dkm_trainees'
            );
        }
        
        // Subscriptions
        if (user_can($user_id, 'manage_dkm_subscriptions')) {
            $menus[] = array(
                'slug' => 'deshi-subscriptions',
                'title' => 'Subscriptions',
                'capability' => 'manage_dkm_subscriptions'
            );
        }
        
        // Invoices
        if (user_can($user_id, 'manage_dkm_invoices')) {
            $menus[] = array(
                'slug' => 'deshi-invoices',
                'title' => 'Invoices',
                'capability' => 'manage_dkm_invoices'
            );
        }
        
        // Queries
        if (user_can($user_id, 'manage_dkm_queries')) {
            $menus[] = array(
                'slug' => 'deshi-queries',
                'title' => 'Queries',
                'capability' => 'manage_dkm_queries'
            );
        }
        
        // Reports
        if (user_can($user_id, 'view_dkm_reports')) {
            $menus[] = array(
                'slug' => 'deshi-reports',
                'title' => 'Reports',
                'capability' => 'view_dkm_reports'
            );
        }
        
        // Settings
        if (user_can($user_id, 'manage_dkm_settings')) {
            $menus[] = array(
                'slug' => 'deshi-settings',
                'title' => 'Settings',
                'capability' => 'manage_dkm_settings'
            );
        }
        
        return $menus;
    }
    
    /**
     * Display capabilities for debugging
     */
    public static function debug_user_capabilities($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            return 'User not found';
        }
        
        echo '<div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">';
        echo '<h3>User Capabilities Debug</h3>';
        echo '<p><strong>User:</strong> ' . $user->display_name . ' (ID: ' . $user_id . ')</p>';
        echo '<p><strong>Roles:</strong> ' . implode(', ', $user->roles) . '</p>';
        echo '<h4>Capabilities:</h4>';
        echo '<ul style="columns: 2;">';
        foreach ($user->allcaps as $cap => $value) {
            if (strpos($cap, 'dkm') !== false) {
                echo '<li>' . $cap . ': ' . ($value ? '✅' : '❌') . '</li>';
            }
        }
        echo '</ul>';
        echo '</div>';
    }
}

