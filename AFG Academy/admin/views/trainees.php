<?php
/**
 * Trainees Management Page
 * View all trainees with their balance and ledger
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_dkm_trainees')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;
$currency = get_option('dkm_currency_symbol', '₹');
$ledger_table = $wpdb->prefix . 'trainee_ledger';

// Get all trainees
$trainees = get_users(array(
    'role' => 'customer',
    'orderby' => 'display_name',
    'order' => 'ASC'
));

// Handle viewing specific trainee ledger
$view_trainee_id = isset($_GET['view_trainee']) ? intval($_GET['view_trainee']) : 0;
$view_trainee = $view_trainee_id ? get_userdata($view_trainee_id) : null;

if ($view_trainee) {
    // Get ledger entries
    $ledger_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$ledger_table}
        WHERE trainee_id = %d
        ORDER BY id DESC
        LIMIT 100",
        $view_trainee_id
    ));
    
    $current_balance = $wpdb->get_var($wpdb->prepare(
        "SELECT balance_after FROM {$ledger_table}
        WHERE trainee_id = %d
        ORDER BY id DESC LIMIT 1",
        $view_trainee_id
    ));
    $current_balance = $current_balance ? floatval($current_balance) : 0;
}

?>

<div class="wrap dkm-trainees-page">
    <h1 class="wp-heading-inline"><?php _e('Trainees Management', 'deshi-kitchen-admin'); ?></h1>
    <a href="<?php echo admin_url('user-new.php?role=customer'); ?>" class="page-title-action">
        <?php _e('Add New Trainee', 'deshi-kitchen-admin'); ?>
    </a>
    
    <?php if (!$view_trainee) : ?>
    <!-- Trainees List -->
    <div class="trainees-filters">
        <input type="text" id="trainee-search" placeholder="<?php _e('Search trainees...', 'deshi-kitchen-admin'); ?>">
        <select id="balance-filter">
            <option value=""><?php _e('All Balances', 'deshi-kitchen-admin'); ?></option>
            <option value="positive"><?php _e('Outstanding (Positive Balance)', 'deshi-kitchen-admin'); ?></option>
            <option value="zero"><?php _e('Zero Balance', 'deshi-kitchen-admin'); ?></option>
            <option value="negative"><?php _e('Advance (Negative Balance)', 'deshi-kitchen-admin'); ?></option>
        </select>
    </div>
    
    <table class="wp-list-table widefat fixed striped trainees-table">
        <thead>
            <tr>
                <th><?php _e('Name', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Email', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Phone', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Current Balance', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Registration Date', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Actions', 'deshi-kitchen-admin'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($trainees)) : ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <?php _e('No trainees found.', 'deshi-kitchen-admin'); ?>
                        <a href="<?php echo admin_url('user-new.php?role=customer'); ?>"><?php _e('Add new trainee', 'deshi-kitchen-admin'); ?></a>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($trainees as $trainee) : 
                    $balance = $wpdb->get_var($wpdb->prepare(
                        "SELECT balance_after FROM {$ledger_table}
                        WHERE trainee_id = %d
                        ORDER BY id DESC LIMIT 1",
                        $trainee->ID
                    ));
                    $balance = $balance ? floatval($balance) : 0;
                    $phone = get_user_meta($trainee->ID, 'billing_phone', true);
                    
                    if ($balance > 0) {
                        $balance_class = 'balance-outstanding';
                        $balance_label = 'Outstanding';
                    } elseif ($balance < 0) {
                        $balance_class = 'balance-advance';
                        $balance_label = 'Advance';
                    } else {
                        $balance_class = 'balance-clear';
                        $balance_label = 'Clear';
                    }
                ?>
                    <tr class="trainee-row" data-balance="<?php echo $balance; ?>">
                        <td><strong><?php echo esc_html($trainee->display_name); ?></strong></td>
                        <td><?php echo esc_html($trainee->user_email); ?></td>
                        <td><?php echo esc_html($phone ?: 'N/A'); ?></td>
                        <td>
                            <span class="balance-badge <?php echo $balance_class; ?>">
                                <?php echo $currency . number_format(abs($balance), 2); ?>
                                <small>(<?php echo $balance_label; ?>)</small>
                            </span>
                        </td>
                        <td><?php echo date('d M Y', strtotime($trainee->user_registered)); ?></td>
                        <td>
                            <a href="?page=deshi-trainees&view_trainee=<?php echo $trainee->ID; ?>" class="button button-small">
                                <?php _e('View Ledger', 'deshi-kitchen-admin'); ?>
                            </a>
                            <a href="<?php echo get_edit_user_link($trainee->ID); ?>" class="button button-small">
                                <?php _e('Edit', 'deshi-kitchen-admin'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php else : ?>
    <!-- Trainee Ledger View -->
    <div class="ledger-view">
        <div class="ledger-header">
            <div>
                <h2><?php echo esc_html($view_trainee->display_name); ?></h2>
                <p><?php echo esc_html($view_trainee->user_email); ?></p>
            </div>
            <a href="?page=deshi-trainees" class="button">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('Back to List', 'deshi-kitchen-admin'); ?>
            </a>
        </div>
        
        <div class="ledger-summary">
            <div class="summary-card">
                <h3><?php _e('Current Balance', 'deshi-kitchen-admin'); ?></h3>
                <div class="balance-amount <?php echo $current_balance > 0 ? 'text-danger' : ($current_balance < 0 ? 'text-success' : ''); ?>">
                    <?php echo $currency . number_format(abs($current_balance), 2); ?>
                    <?php if ($current_balance > 0) : ?>
                        <small>(<?php _e('Outstanding', 'deshi-kitchen-admin'); ?>)</small>
                    <?php elseif ($current_balance < 0) : ?>
                        <small>(<?php _e('Advance', 'deshi-kitchen-admin'); ?>)</small>
                    <?php else : ?>
                        <small>(<?php _e('Clear', 'deshi-kitchen-admin'); ?>)</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="quick-actions-ledger">
                <button class="button button-primary" onclick="recordPayment(<?php echo $view_trainee_id; ?>)">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php _e('Record Payment', 'deshi-kitchen-admin'); ?>
                </button>
                <button class="button" onclick="addCharge(<?php echo $view_trainee_id; ?>)">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Charge', 'deshi-kitchen-admin'); ?>
                </button>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'deshi-kitchen-admin'); ?></th>
                    <th><?php _e('Type', 'deshi-kitchen-admin'); ?></th>
                    <th><?php _e('Description', 'deshi-kitchen-admin'); ?></th>
                    <th><?php _e('Amount', 'deshi-kitchen-admin'); ?></th>
                    <th><?php _e('Balance After', 'deshi-kitchen-admin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledger_entries)) : ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <?php _e('No transactions yet.', 'deshi-kitchen-admin'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($ledger_entries as $entry) : ?>
                        <tr>
                            <td><?php echo date('d M Y, h:i A', strtotime($entry->created_at)); ?></td>
                            <td>
                                <span class="type-badge type-<?php echo $entry->type; ?>">
                                    <?php echo ucfirst($entry->type); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($entry->note); ?></td>
                            <td>
                                <strong class="<?php echo $entry->type === 'charge' ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $entry->type === 'charge' ? '+' : '-'; ?>
                                    <?php echo $currency . number_format($entry->amount, 2); ?>
                                </strong>
                            </td>
                            <td><?php echo $currency . number_format($entry->balance_after, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Search filter
    $('#trainee-search').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.trainee-row').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });
    
    // Balance filter
    $('#balance-filter').on('change', function() {
        const filter = $(this).val();
        $('.trainee-row').each(function() {
            const balance = parseFloat($(this).data('balance'));
            let show = true;
            
            if (filter === 'positive') {
                show = balance > 0;
            } else if (filter === 'zero') {
                show = balance === 0;
            } else if (filter === 'negative') {
                show = balance < 0;
            }
            
            $(this).toggle(show);
        });
    });
});

function recordPayment(traineeId) {
    const amount = prompt('<?php _e('Enter payment amount:', 'deshi-kitchen-admin'); ?>');
    if (amount && !isNaN(amount) && parseFloat(amount) > 0) {
        const note = prompt('<?php _e('Add note (optional):', 'deshi-kitchen-admin'); ?>') || 'Manual payment';
        
        jQuery.post(ajaxurl, {
            action: 'dkm_add_manual_payment',
            nonce: '<?php echo wp_create_nonce('dkm_admin_nonce'); ?>',
            trainee_id: traineeId,
            amount: amount,
            note: note
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Payment recorded successfully!', 'deshi-kitchen-admin'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Error:', 'deshi-kitchen-admin'); ?> ' + response.data.message);
            }
        });
    }
}

function addCharge(traineeId) {
    const amount = prompt('<?php _e('Enter charge amount:', 'deshi-kitchen-admin'); ?>');
    if (amount && !isNaN(amount) && parseFloat(amount) > 0) {
        const note = prompt('<?php _e('Add description:', 'deshi-kitchen-admin'); ?>') || 'Manual charge';
        
        jQuery.post(ajaxurl, {
            action: 'dkm_add_manual_charge',
            nonce: '<?php echo wp_create_nonce('dkm_admin_nonce'); ?>',
            trainee_id: traineeId,
            amount: amount,
            note: note
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Charge added successfully!', 'deshi-kitchen-admin'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Error:', 'deshi-kitchen-admin'); ?> ' + response.data.message);
            }
        });
    }
}
</script>

<style>
.trainees-filters {
    background: #fff;
    padding: 15px 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
    display: flex;
    gap: 15px;
}

.trainees-filters input,
.trainees-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    flex: 1;
}

.balance-badge {
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 600;
    display: inline-block;
}

.balance-outstanding {
    background: #ffebee;
    color: #c62828;
}

.balance-advance {
    background: #e8f5e9;
    color: #2e7d32;
}

.balance-clear {
    background: #e3f2fd;
    color: #1976d2;
}

.balance-badge small {
    display: block;
    font-size: 11px;
    font-weight: 400;
    margin-top: 2px;
}

.ledger-view {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.ledger-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #ddd;
}

.ledger-header h2 {
    margin: 0;
}

.ledger-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 8px;
}

.summary-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.balance-amount {
    font-size: 36px;
    font-weight: 700;
}

.balance-amount small {
    display: block;
    font-size: 14px;
    font-weight: 400;
    margin-top: 5px;
}

.quick-actions-ledger {
    display: flex;
    flex-direction: column;
    gap: 10px;
    justify-content: center;
}

.type-badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.type-badge.type-charge {
    background: #ffebee;
    color: #c62828;
}

.type-badge.type-payment {
    background: #e8f5e9;
    color: #2e7d32;
}

.text-danger {
    color: #c62828;
}

.text-success {
    color: #2e7d32;
}
</style>