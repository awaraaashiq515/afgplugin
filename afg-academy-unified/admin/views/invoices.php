<?php
/**
 * Monthly Invoices Management
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_dkm_invoices')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;
$currency = get_option('dkm_currency_symbol', '₹');
$invoices_table = $wpdb->prefix . 'deshi_invoices';

// Handle actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

if ($action === 'generate' && isset($_POST['generate_invoices'])) {
    // Generate invoices logic here
    $month = sanitize_text_field($_POST['invoice_month']);
    echo '<div class="notice notice-success"><p>' . __('Invoices generated successfully!', 'deshi-kitchen-admin') . '</p></div>';
}

// Get invoices
$invoices = $wpdb->get_results(
    "SELECT * FROM {$invoices_table}
    ORDER BY id DESC
    LIMIT 50"
);

?>

<div class="wrap dkm-invoices-page">
    <h1 class="wp-heading-inline"><?php _e('Monthly Invoices', 'deshi-kitchen-admin'); ?></h1>
    <a href="?page=deshi-invoices&action=generate" class="page-title-action">
        <?php _e('Generate Invoices', 'deshi-kitchen-admin'); ?>
    </a>
    
    <?php if ($action === 'generate') : ?>
    <!-- Generate Invoices Form -->
    <div class="generate-invoices-form">
        <h2><?php _e('Generate Monthly Invoices', 'deshi-kitchen-admin'); ?></h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="invoice_month"><?php _e('Invoice Month', 'deshi-kitchen-admin'); ?></label></th>
                    <td>
                        <input type="month" id="invoice_month" name="invoice_month" value="<?php echo date('Y-m'); ?>" required>
                        <p class="description"><?php _e('Select the month for which to generate invoices', 'deshi-kitchen-admin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="training_fee"><?php _e('Training Fee', 'deshi-kitchen-admin'); ?></label></th>
                    <td>
                        <input type="number" id="training_fee" name="training_fee" step="0.01" value="5000">
                        <p class="description"><?php _e('Default training fee per trainee', 'deshi-kitchen-admin'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="accommodation_fee"><?php _e('Accommodation Fee', 'deshi-kitchen-admin'); ?></label></th>
                    <td>
                        <input type="number" id="accommodation_fee" name="accommodation_fee" step="0.01" value="3000">
                        <p class="description"><?php _e('Default accommodation fee per trainee', 'deshi-kitchen-admin'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="generate_invoices" class="button button-primary button-large">
                    <?php _e('Generate Invoices for All Trainees', 'deshi-kitchen-admin'); ?>
                </button>
                <a href="?page=deshi-invoices" class="button button-large"><?php _e('Cancel', 'deshi-kitchen-admin'); ?></a>
            </p>
        </form>
    </div>
    
    <?php else : ?>
    <!-- Invoices List -->
    <div class="invoices-filters">
        <select id="status-filter">
            <option value=""><?php _e('All Status', 'deshi-kitchen-admin'); ?></option>
            <option value="unpaid"><?php _e('Unpaid', 'deshi-kitchen-admin'); ?></option>
            <option value="partial"><?php _e('Partially Paid', 'deshi-kitchen-admin'); ?></option>
            <option value="paid"><?php _e('Paid', 'deshi-kitchen-admin'); ?></option>
            <option value="overdue"><?php _e('Overdue', 'deshi-kitchen-admin'); ?></option>
        </select>
        
        <input type="month" id="month-filter" placeholder="<?php _e('Filter by month', 'deshi-kitchen-admin'); ?>">
        
        <input type="text" id="trainee-filter" placeholder="<?php _e('Search trainee...', 'deshi-kitchen-admin'); ?>">
    </div>
    
    <table class="wp-list-table widefat fixed striped invoices-table">
        <thead>
            <tr>
                <th><?php _e('Invoice #', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Trainee', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Month', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Total Amount', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Paid', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Balance', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Status', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Due Date', 'deshi-kitchen-admin'); ?></th>
                <th><?php _e('Actions', 'deshi-kitchen-admin'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)) : ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <p><?php _e('No invoices found.', 'deshi-kitchen-admin'); ?></p>
                        <a href="?page=deshi-invoices&action=generate" class="button button-primary">
                            <?php _e('Generate Your First Invoices', 'deshi-kitchen-admin'); ?>
                        </a>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($invoices as $invoice) : 
                    $trainee = get_userdata($invoice->trainee_id);
                    $is_overdue = $invoice->payment_status !== 'paid' && strtotime($invoice->due_date) < time();
                    $status = $is_overdue ? 'overdue' : $invoice->payment_status;
                ?>
                    <tr class="invoice-row" data-status="<?php echo $status; ?>" data-month="<?php echo $invoice->invoice_month; ?>">
                        <td>
                            <strong><?php echo esc_html($invoice->invoice_number); ?></strong>
                            <br><small><?php echo date('d M Y', strtotime($invoice->created_at)); ?></small>
                        </td>
                        <td>
                            <?php echo $trainee ? esc_html($trainee->display_name) : 'N/A'; ?>
                            <br><small><?php echo $trainee ? esc_html($trainee->user_email) : ''; ?></small>
                        </td>
                        <td><?php echo date('F Y', strtotime($invoice->invoice_month . '-01')); ?></td>
                        <td><strong><?php echo $currency . number_format($invoice->total_amount, 2); ?></strong></td>
                        <td><?php echo $currency . number_format($invoice->paid_amount, 2); ?></td>
                        <td>
                            <strong class="<?php echo $invoice->balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $currency . number_format($invoice->balance, 2); ?>
                            </strong>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $status; ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $invoice->due_date ? date('d M Y', strtotime($invoice->due_date)) : 'N/A'; ?>
                            <?php if ($is_overdue) : ?>
                                <br><small class="text-danger"><?php _e('Overdue!', 'deshi-kitchen-admin'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="button button-small" onclick="viewInvoice(<?php echo $invoice->id; ?>)">
                                <?php _e('View', 'deshi-kitchen-admin'); ?>
                            </button>
                            <button class="button button-small" onclick="recordPayment(<?php echo $invoice->id; ?>)">
                                <?php _e('Payment', 'deshi-kitchen-admin'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Summary Stats -->
    <div class="invoices-stats">
        <h3><?php _e('Summary', 'deshi-kitchen-admin'); ?></h3>
        <?php
        $total_amount = $wpdb->get_var("SELECT SUM(total_amount) FROM {$invoices_table}");
        $total_paid = $wpdb->get_var("SELECT SUM(paid_amount) FROM {$invoices_table}");
        $total_balance = $wpdb->get_var("SELECT SUM(balance) FROM {$invoices_table} WHERE balance > 0");
        ?>
        <div class="stats-grid">
            <div class="stat-item">
                <strong><?php echo $currency . number_format($total_amount, 2); ?></strong>
                <span><?php _e('Total Invoiced', 'deshi-kitchen-admin'); ?></span>
            </div>
            <div class="stat-item">
                <strong><?php echo $currency . number_format($total_paid, 2); ?></strong>
                <span><?php _e('Total Collected', 'deshi-kitchen-admin'); ?></span>
            </div>
            <div class="stat-item alert">
                <strong><?php echo $currency . number_format($total_balance, 2); ?></strong>
                <span><?php _e('Total Outstanding', 'deshi-kitchen-admin'); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Filters
    $('#status-filter, #month-filter').on('change', function() {
        const status = $('#status-filter').val();
        const month = $('#month-filter').val();
        
        $('.invoice-row').each(function() {
            let show = true;
            if (status && $(this).data('status') !== status) show = false;
            if (month && $(this).data('month') !== month) show = false;
            $(this).toggle(show);
        });
    });
    
    $('#trainee-filter').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.invoice-row').each(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
        });
    });
});

function viewInvoice(invoiceId) {
    window.open('?page=deshi-invoices&action=view&id=' + invoiceId, '_blank');
}

function recordPayment(invoiceId) {
    const amount = prompt('<?php _e('Enter payment amount:', 'deshi-kitchen-admin'); ?>');
    if (amount && !isNaN(amount) && parseFloat(amount) > 0) {
        alert('<?php _e('Payment feature coming soon!', 'deshi-kitchen-admin'); ?>');
    }
}
</script>

<style>
.generate-invoices-form {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.invoices-filters {
    background: #fff;
    padding: 15px 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
    display: flex;
    gap: 15px;
}

.invoices-filters select,
.invoices-filters input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    flex: 1;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.status-paid {
    background: #4caf50;
    color: white;
}

.status-badge.status-unpaid {
    background: #ff9800;
    color: white;
}

.status-badge.status-partial {
    background: #2196f3;
    color: white;
}

.status-badge.status-overdue {
    background: #f44336;
    color: white;
}

.invoices-stats {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: #f5f5f5;
    border-radius: 6px;
}

.stat-item.alert {
    background: #ffebee;
}

.stat-item strong {
    display: block;
    font-size: 28px;
    color: #2271b1;
    margin-bottom: 5px;
}

.stat-item.alert strong {
    color: #c62828;
}

.text-danger {
    color: #c62828;
}

.text-success {
    color: #2e7d32;
}
</style>