<?php
/**
 * KPOS Permissions Class
 * Handles permission checks for POS access
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_Permissions {
    
    /**
     * Check if current user can access POS
     */
    public static function can_access_pos() {
        return current_user_can('manage_afg_pos');
    }
    
    /**
     * Check if current user can create orders
     */
    public static function can_create_orders() {
        return current_user_can('create_dkm_orders') || current_user_can('manage_afg_pos');
    }
    
    /**
     * Check if current user can view products
     */
    public static function can_view_products() {
        return current_user_can('view_dkm_products') || current_user_can('manage_afg_pos');
    }
    
    /**
     * Check if current user can search trainees
     */
    public static function can_search_trainees() {
        return current_user_can('search_dkm_trainees') || current_user_can('manage_afg_pos');
    }
    
    /**
     * Verify nonce
     */
    public static function verify_nonce($nonce) {
        return wp_verify_nonce($nonce, 'kpos_nonce');
    }
    
    /**
     * Check AJAX permission
     */
    public static function check_ajax_permission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !self::verify_nonce($_POST['nonce'])) {
            wp_send_json_error(array(
                'message' => __('Invalid security token', 'kitchen-pos')
            ));
        }
        
        // Check if user can access POS
        if (!self::can_access_pos()) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to access POS', 'kitchen-pos')
            ));
        }
    }
    
    /**
     * Get current user info
     */
    public static function get_current_user_info() {
        $user = wp_get_current_user();
        
        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'can_access_pos' => self::can_access_pos(),
            'can_create_orders' => self::can_create_orders()
        );
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
     * Get POS accessible menu items
     */
    public static function get_accessible_menus() {
        $menus = array();
        
        if (self::can_access_pos()) {
            $menus[] = array(
                'slug' => 'kitchen-pos',
                'title' => __('POS', 'kitchen-pos'),
                'icon' => 'dashicons-store'
            );
        }
        
        // Link to admin dashboard if user has permission
        if (current_user_can('read')) {
            $menus[] = array(
                'slug' => 'deshi-kitchen',
                'title' => __('Dashboard', 'kitchen-pos'),
                'icon' => 'dashicons-dashboard',
                'external' => true
            );
        }
        
        return $menus;
    }
}