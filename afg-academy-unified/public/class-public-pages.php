<?php
/**
 * Public Pages and Shortcodes
 * Handles frontend trainee portal, query form, kitchen menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFG_Public_Pages {
    
    /**
     * Render trainee portal shortcode
     */
    public static function render_trainee_portal($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>Please log in to access your trainee portal.</p>';
        }
        
        $user = wp_get_current_user();
        $trainee_id = $user->ID;
        
        ob_start();
        ?>
        <div class="dkm-trainee-portal">
            <h2>Your Trainee Portal</h2>
            
            <?php
            $balance = DKM_Trainee_Ledger::get_balance($trainee_id);
            $subscriptions = DKM_Trainee_Ledger::get_subscriptions($trainee_id);
            ?>
            
            <div class="dkm-balance">
                <h3>Current Balance: <?php echo esc_html(get_option('dkm_currency_symbol', '₹') . number_format($balance, 2)); ?></h3>
            </div>
            
            <div class="dkm-subscriptions">
                <h3>Your Subscriptions</h3>
                <?php if ($subscriptions): ?>
                    <ul>
                        <?php foreach ($subscriptions as $sub): ?>
                            <li><?php echo esc_html($sub->package_name); ?> - <?php echo esc_html($sub->status); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No active subscriptions.</p>
                <?php endif; ?>
            </div>
            
            <div class="dkm-ledger-history">
                <h3>Recent Transactions</h3>
                <?php
                $history = DKM_Trainee_Ledger::get_ledger_history($trainee_id, 10);
                if ($history):
                ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $entry): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($entry->created_at)); ?></td>
                                    <td><?php echo esc_html($entry->type); ?></td>
                                    <td><?php echo esc_html(get_option('dkm_currency_symbol', '₹') . $entry->amount); ?></td>
                                    <td><?php echo esc_html($entry->note); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No transactions yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render query form shortcode
     */
    public static function render_query_form($atts) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $current_name = $user->display_name;
            $current_email = $user->user_email;
        } else {
            $current_name = '';
            $current_email = '';
        }
        
        ob_start();
        ?>
        <div class="dkm-query-form">
            <h2>Submit a Query</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('dkm_query_form'); ?>
                
                <p>
                    <label for="query_name">Name *</label>
                    <input type="text" id="query_name" name="query_name" value="<?php echo esc_attr($current_name); ?>" required>
                </p>
                
                <p>
                    <label for="query_email">Email *</label>
                    <input type="email" id="query_email" name="query_email" value="<?php echo esc_attr($current_email); ?>" required>
                </p>
                
                <p>
                    <label for="query_phone">Phone</label>
                    <input type="tel" id="query_phone" name="query_phone">
                </p>
                
                <p>
                    <label for="query_subject">Subject *</label>
                    <input type="text" id="query_subject" name="query_subject" required>
                </p>
                
                <p>
                    <label for="query_category">Category</label>
                    <select id="query_category" name="query_category">
                        <option value="general">General</option>
                        <option value="billing">Billing</option>
                        <option value="kitchen">Kitchen</option>
                        <option value="training">Training</option>
                        <option value="technical">Technical</option>
                    </select>
                </p>
                
                <p>
                    <label for="query_message">Message *</label>
                    <textarea id="query_message" name="query_message" rows="5" required></textarea>
                </p>
                
                <p>
                    <button type="submit" name="dkm_submit_query">Submit Query</button>
                </p>
            </form>
            
            <?php
            // Handle form submission
            if (isset($_POST['dkm_submit_query']) && wp_verify_nonce($_POST['dkm_query_form'], 'dkm_query_form')) {
                self::handle_query_submission();
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render kitchen menu shortcode
     */
    public static function render_kitchen_menu($atts) {
        // Get kitchen products from WooCommerce
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            )
        );
        
        $products = new WP_Query($args);
        
        ob_start();
        ?>
        <div class="dkm-kitchen-menu">
            <h2>Kitchen Menu</h2>
            
            <?php if ($products->have_posts()): ?>
                <div class="dkm-products-grid">
                    <?php while ($products->have_posts()): $products->the_post(); ?>
                        <?php global $product; ?>
                        <div class="dkm-product-item">
                            <h3><?php the_title(); ?></h3>
                            <p class="price"><?php echo $product->get_price_html(); ?></p>
                            <?php if ($product->is_in_stock()): ?>
                                <p class="stock">In Stock</p>
                            <?php else: ?>
                                <p class="stock out">Out of Stock</p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No products available.</p>
            <?php endif; ?>
            
            <?php wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle query form submission
     */
    private static function handle_query_submission() {
        $name = sanitize_text_field($_POST['query_name']);
        $email = sanitize_email($_POST['query_email']);
        $phone = sanitize_text_field($_POST['query_phone']);
        $subject = sanitize_text_field($_POST['query_subject']);
        $category = sanitize_text_field($_POST['query_category']);
        $message = sanitize_textarea_field($_POST['query_message']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'deshi_queries';
        
        $wpdb->insert($table, array(
            'ticket_number' => 'QRY-' . time(),
            'user_id' => get_current_user_id(),
            'user_name' => $name,
            'user_email' => $email,
            'user_phone' => $phone,
            'subject' => $subject,
            'message' => $message,
            'category' => $category,
            'status' => 'new'
        ));
        
        echo '<div class="notice notice-success">Your query has been submitted successfully!</div>';
    }
}

// Enqueue public styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dkm-public-style', DKM_ADMIN_PLUGIN_URL . 'assets/css/public-style.css', array(), DKM_ADMIN_VERSION);
});

// Create public CSS file if not exists
if (!file_exists(DKM_ADMIN_PLUGIN_DIR . 'assets/css/public-style.css')) {
    $css = "
    .dkm-trainee-portal, .dkm-query-form, .dkm-kitchen-menu {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .dkm-products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }
    .dkm-product-item {
        border: 1px solid #ddd;
        padding: 15px;
        text-align: center;
    }
    ";
    file_put_contents(DKM_ADMIN_PLUGIN_DIR . 'assets/css/public-style.css', $css);
}