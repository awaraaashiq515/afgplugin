/**
 * Kitchen POS - JavaScript - FIXED VERSION
 */

(function($) {
    'use strict';

    // Global state
    let selectedTrainee = null;
    let cart = [];
    let products = [];
    let categories = [];

    // Initialize
    $(document).ready(function() {
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        loadProducts();
        loadWooCommerceCart(); // FIXED: Load cart on page load
        loadRecentOrders();
        
        initEventHandlers();
    });

    /**
     * Update date/time display
     */
    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        $('#kpos-datetime').text(now.toLocaleDateString('en-US', options));
    }

    /**
     * Initialize event handlers
     */
    function initEventHandlers() {
        // Trainee search
        let searchTimeout;
        $('#trainee-search').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if (query.length < 2) {
                $('#trainee-search-results').html('');
                return;
            }
            
            searchTimeout = setTimeout(() => searchTrainee(query), 300);
        });

        // Clear trainee selection
        $(document).on('click', '#clear-trainee', function() {
            clearTraineeSelection();
        });

        // Product search
        $('#product-search').on('input', function() {
            const query = $(this).val().toLowerCase();
            filterProducts(query);
        });

        // Category filter
        $(document).on('click', '.kpos-category-btn', function() {
            $('.kpos-category-btn').removeClass('active');
            $(this).addClass('active');
            
            const category = $(this).data('category');
            filterByCategory(category);
        });

        // Add to cart - FIXED: Better event handling
        $(document).on('click', '.product-card:not(.out-of-stock)', function(e) {
            e.preventDefault();
            const productId = $(this).data('product-id');
            console.log('Adding product to cart:', productId); // Debug
            addToCart(productId);
        });

        // Update cart quantity
        $(document).on('click', '.qty-minus', function(e) {
            e.preventDefault();
            const cartKey = $(this).data('cart-key');
            updateCartQuantity(cartKey, -1);
        });

        $(document).on('click', '.qty-plus', function(e) {
            e.preventDefault();
            const cartKey = $(this).data('cart-key');
            updateCartQuantity(cartKey, 1);
        });

        // Remove from cart
        $(document).on('click', '.cart-item-remove', function(e) {
            e.preventDefault();
            const cartKey = $(this).data('cart-key');
            removeFromCart(cartKey);
        });

        // Clear cart
        $('#clear-cart').on('click', function() {
            if (confirm('Clear entire cart?')) {
                clearCart();
            }
        });

        // Complete sale
        $('#complete-sale').on('click', function() {
            completeSale();
        });

        // Modal buttons
        $('#new-order').on('click', function() {
            $('#order-success-modal').hide();
            resetPOS();
        });

        $('#print-receipt').on('click', function() {
            printReceipt();
        });

        // Select trainee from results
        $(document).on('click', '.trainee-result-item', function() {
            const traineeId = $(this).data('trainee-id');
            selectTrainee(traineeId);
        });
    }

    /**
     * Load products from server
     */
    function loadProducts() {
        $('#products-grid').html('<div class="kpos-loading"><span class="dashicons dashicons-update spin"></span> Loading products...</div>');

        $.ajax({
            url: kposConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kpos_get_products',
                nonce: kposConfig.nonce
            },
            success: function(response) {
                console.log('Products loaded:', response); // Debug
                if (response.success) {
                    products = response.data.products;
                    categories = response.data.categories;
                    
                    renderCategories();
                    renderProducts(products);
                } else {
                    showError('Failed to load products');
                }
            },
            error: function(xhr, status, error) {
                console.error('Product load error:', error);
                showError('Connection error');
            }
        });
    }

    /**
     * Render category buttons
     */
    function renderCategories() {
        let html = '';
        categories.forEach(cat => {
            html += `<button type="button" class="kpos-category-btn" data-category="${cat}">${cat}</button>`;
        });
        $('#category-buttons').html(html);
    }

    /**
     * Render products grid - FIXED VERSION
     */
    function renderProducts(productList) {
        if (productList.length === 0) {
            $('#products-grid').html('<p class="kpos-no-data">No products found</p>');
            return;
        }

        let html = '';
        productList.forEach(product => {
            const stockClass = product.in_stock ? '' : 'out-of-stock';
            const stockText = product.in_stock ? `Stock: ${product.stock_qty}` : 'Out of Stock';
            
            // FIXED: Better image handling
            let imageUrl = product.image_url;
            if (!imageUrl || imageUrl === '') {
                imageUrl = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="150" height="150"%3E%3Crect fill="%23f5f7fa" width="150" height="150"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="14"%3ENo Image%3C/text%3E%3C/svg%3E';
            }

            html += `
                <div class="product-card ${stockClass}" data-product-id="${product.id}">
                    <img src="${imageUrl}" alt="${product.title}" class="product-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22150%22%3E%3Crect fill=%22%23f5f7fa%22 width=%22150%22 height=%22150%22/%3E%3C/svg%3E'">
                    <div class="product-name">${product.title}</div>
                    <div class="product-price">${kposConfig.currency}${parseFloat(product.price).toFixed(2)}</div>
                    <div class="product-stock">${stockText}</div>
                </div>
            `;
        });

        $('#products-grid').html(html);
    }

    /**
     * Filter products
     */
    function filterProducts(query) {
        if (!query) {
            renderProducts(products);
            return;
        }

        const filtered = products.filter(p => 
            p.title.toLowerCase().includes(query) ||
            (p.sku && p.sku.toLowerCase().includes(query))
        );

        renderProducts(filtered);
    }

    /**
     * Filter by category
     */
    function filterByCategory(category) {
        if (!category) {
            renderProducts(products);
            return;
        }

        const filtered = products.filter(p => p.category === category);
        renderProducts(filtered);
    }

    /**
     * Search trainee
     */
    function searchTrainee(query) {
        $.ajax({
            url: kposConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kpos_search_trainee',
                search: query,
                nonce: kposConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderTraineeResults(response.data.trainees);
                } else {
                    $('#trainee-search-results').html('<p class="kpos-no-data">No trainees found</p>');
                }
            }
        });
    }

    /**
     * Render trainee search results
     */
/**
 * Render trainee search results - FIXED
 */
function renderTraineeResults(trainees) {
    console.log('Rendering trainees:', trainees); // ✅ DEBUG
    
    if (trainees.length === 0) {
        $('#trainee-search-results').html('<p class="kpos-no-data">No trainees found</p>');
        return;
    }

    let html = '';
    trainees.forEach(trainee => {
        // ✅ Use trainee.id or trainee.trainee_id
        const traineeId = trainee.id || trainee.trainee_id;
        
        console.log('Trainee ID:', traineeId, trainee); // ✅ DEBUG
        
        html += `
            <div class="trainee-result-item" data-trainee-id="${traineeId}">
                <h4>${trainee.name}</h4>
                <p>${trainee.email}</p>
                ${trainee.phone ? `<p>${trainee.phone}</p>` : ''}
                <p class="trainee-balance">Balance: ${kposConfig.currency}${Math.abs(trainee.balance).toFixed(2)}</p>
            </div>
        `;
    });

    $('#trainee-search-results').html(html);
}
    
/**
 * FIXED: Select trainee function
 */
function selectTrainee(traineeId) {
    console.log('selectTrainee called with ID:', traineeId);
    
    if (!traineeId) {
        console.error('Invalid trainee ID');
        showError('Please select a valid trainee');
        return;
    }
    
    $.ajax({
        url: kposConfig.ajaxUrl,
        type: 'POST',
        data: {
            action: 'kpos_get_trainee_balance',
            trainee_id: traineeId,
            nonce: kposConfig.nonce
        },
        success: function(response) {
            console.log('Full Response:', response);
            console.log('Response Data:', response.data);
            
            // ✅ FIX 1: Check if response has data property
            if (!response.success || !response.data) {
                console.error('Invalid response structure:', response);
                showError('Failed to load trainee data');
                return;
            }
            
            const traineeData = response.data;
            
            // ✅ FIX 2: Validate all required fields
            if (!traineeData.trainee_id) {
                console.error('Missing trainee_id in response:', traineeData);
                showError('Trainee ID not found in response');
                return;
            }
            
            if (!traineeData.name) {
                console.error('Missing name in response:', traineeData);
                showError('Trainee name not found');
                return;
            }
            
            // ✅ FIX 3: Safely convert balance to number
            const balance = traineeData.balance !== undefined ? parseFloat(traineeData.balance) : 0;
            const email = traineeData.email || 'N/A';
            const phone = traineeData.phone || 'N/A';
            
            console.log('Trainee Data Validated:', {
                trainee_id: traineeData.trainee_id,
                name: traineeData.name,
                balance: balance,
                email: email,
                phone: phone
            });
            
            // ✅ FIX 4: Store the correct object
            selectedTrainee = {
                id: traineeData.trainee_id,
                trainee_id: traineeData.trainee_id,
                name: traineeData.name,
                email: email,
                phone: phone,
                balance: balance,
                summary: traineeData.summary || {},
                credit_limit: traineeData.credit_limit || 0
            };
            
            // ✅ FIX 5: Update UI with safe values
            const avatarLetter = traineeData.name.charAt(0).toUpperCase();
            $('#trainee-avatar').html(avatarLetter);
            $('#trainee-name').text(traineeData.name);
            $('#trainee-email').text(email);
            $('#trainee-phone').text(phone);
            
            // Format balance display
            const balanceText = kposConfig.currency + Math.abs(balance).toFixed(2);
            $('#trainee-balance').text(balanceText);
            
            // Add balance color (red if negative/owing, green if positive/credit)
            const balanceEl = $('#trainee-balance');
            balanceEl.removeClass('negative positive');
            
            if (balance < 0) {
                balanceEl.addClass('negative'); // Owing money
            } else if (balance > 0) {
                balanceEl.addClass('positive'); // Has credit
            }
            
            $('#selected-trainee').show();
            $('#trainee-search-results').html('');
            $('#trainee-search').val('');
            
            updateCheckoutButton();
            
            console.log('✅ Trainee selected successfully');
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response Text:', xhr.responseText);
            
            // Try to parse error response
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                console.error('Parsed Error:', errorResponse);
                showError(errorResponse.data?.message || 'Connection error');
            } catch(e) {
                showError('Connection error: ' + error);
            }
        }
    });
}
    /**
     * Clear trainee selection
     */
    function clearTraineeSelection() {
        selectedTrainee = null;
        $('#selected-trainee').hide();
        $('#trainee-search').val('');
        updateCheckoutButton();
    }

    /**
     * FIXED: Add product to WooCommerce cart
     */
    function addToCart(productId) {
        console.log('addToCart called with:', productId); // Debug
        
        // Show loading state
        $(`.product-card[data-product-id="${productId}"]`).addClass('adding');
        
        $.ajax({
            url: kposConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kpos_add_to_wc_cart',
                product_id: productId,
                quantity: 1,
                nonce: kposConfig.nonce
            },
            success: function(response) {
                console.log('Add to cart response:', response); // Debug
                $(`.product-card[data-product-id="${productId}"]`).removeClass('adding');
                
                if (response.success) {
                    // Show success feedback
                    showSuccess('Product added to cart');
                    loadWooCommerceCart(); // Reload cart
                    updateCheckoutButton();
                } else {
                    showError(response.data.message || 'Failed to add product');
                }
            },
            error: function(xhr, status, error) {
                console.error('Add to cart error:', error);
                $(`.product-card[data-product-id="${productId}"]`).removeClass('adding');
                showError('Connection error');
            }
        });
    }

    /**
     * FIXED: Load WooCommerce cart
     */
    function loadWooCommerceCart() {
        $.ajax({
            url: kposConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kpos_get_wc_cart',
                nonce: kposConfig.nonce
            },
            success: function(response) {
                console.log('Cart loaded:', response); // Debug
                if (response.success) {
                    cart = response.data.items || [];
                    renderCart();
                    updateCheckoutButton();
                }
            },
            error: function(xhr, status, error) {
                console.error('Cart load error:', error);
            }
        });
    }

    /**
     * Update cart quantity (WooCommerce)
     */
    function updateCartQuantity(cartKey, change) {
        $.ajax({
            url: kposConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kpos_update_cart_qty',
                cart_key: cartKey,
                change: change,
                nonce: kposConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadWooCommerceCart();
                } else {
                    showError(response.data.message);
                }
            }
        });
    }

    /**
     * Remove from cart
     */
    function removeFromCart(cartKey) {
        $.ajax({
            url: kposConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kpos_remove_from_cart',
                cart_key: cartKey,
                nonce: kposConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadWooCommerceCart();
                } else {
                    showError(response.data.message);
                }
            }
        });
    }

    /**
     * Clear cart
     */
    function clearCart() {
        // Remove all items one by one
        const removePromises = cart.map(item => {
            return $.ajax({
                url: kposConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kpos_remove_from_cart',
                    cart_key: item.cart_key,
                    nonce: kposConfig.nonce
                }
            });
        });
        
        Promise.all(removePromises).then(() => {
            loadWooCommerceCart();
        });
    }

    /**
     * FIXED: Render cart (from WooCommerce)
     */
    function renderCart() {
        if (!cart || cart.length === 0) {
            $('#cart-items').html(`
                <div class="kpos-empty-cart">
                    <span class="dashicons dashicons-cart"></span>
                    <p>Cart is empty</p>
                    <small>Add products to get started</small>
                </div>
            `);
            updateCartSummary(0);
            return;
        }

        let html = '';
        let total = 0;

        cart.forEach((item) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;

            html += `
                <div class="cart-item" data-cart-key="${item.cart_key}">
                    <img src="${item.image}" alt="${item.name}" class="cart-item-image">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">${kposConfig.currency}${item.price.toFixed(2)} × ${item.quantity}</div>
                    </div>
                    <div class="cart-item-qty">
                        <button type="button" class="qty-btn qty-minus" data-cart-key="${item.cart_key}">−</button>
                        <span class="qty-value">${item.quantity}</span>
                        <button type="button" class="qty-btn qty-plus" data-cart-key="${item.cart_key}">+</button>
                    </div>
                    <button type="button" class="cart-item-remove" data-cart-key="${item.cart_key}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
        });

        $('#cart-items').html(html);
        updateCartSummary(total);
    }

    /**
     * Update cart summary
     */
    function updateCartSummary(total) {
        $('#cart-subtotal').text(kposConfig.currency + total.toFixed(2));
        $('#cart-total').text(kposConfig.currency + total.toFixed(2));
    }

    /**
     * Update checkout button state
     */
    function updateCheckoutButton() {
        const canCheckout = selectedTrainee && cart && cart.length > 0;
        $('#complete-sale').prop('disabled', !canCheckout);
    }

    /**
     * Complete sale
     */
// ✅ NEW CODE (Fixed)
/**
 * Complete sale - FIXED VERSION
 */
/**
 * Complete sale - WITH PAYMENT GATEWAY SUPPORT
 */
/**
 * Complete sale - WITH PAYMENT GATEWAY SUPPORT
 */
function completeSale() {
    console.log('=== Complete Sale Started ===');
    
    if (!selectedTrainee || !selectedTrainee.id) {
        console.error('No trainee selected');
        return showError('Please select a trainee');
    }
    
    if (!cart || cart.length === 0) {
        console.error('Cart is empty');
        return showError('Cart is empty');
    }
    
    const paymentMethod = $('input[name="payment_method"]:checked').val();
    if (!paymentMethod) {
        return showError('Select payment method');
    }
    
    console.log('Trainee ID:', selectedTrainee.id);
    console.log('Payment:', paymentMethod);
    console.log('Cart items:', cart.length);
    
    $('#complete-sale').prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processing...');
    
    $.ajax({
        url: kposConfig.ajaxUrl,
        type: 'POST',
        data: {
            action: 'kpos_complete_wc_checkout',
            trainee_id: selectedTrainee.id,
            payment_method: paymentMethod,
            order_note: $('#order-note').val(),
            nonce: kposConfig.nonce
        },
        success: function(response) {
            console.log('Response:', response);
            
            if (response.success === true) {
                // Check if payment gateway requires redirect
                if (response.data.requires_redirect && response.data.redirect) {
                    console.log('Redirecting to payment gateway...');
                    window.location.href = response.data.redirect;
                    return;
                }
                
                // Show success for cash/credit
                console.log('✅ Order Success:', response.data);
                showOrderSuccess(response.data);
            } else {
                console.error('❌ Order Failed:', response);
                const errorMsg = (response.data && response.data.message) ? response.data.message : 'Checkout failed';
                showError(errorMsg);
                $('#complete-sale').prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Complete Sale');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Connection error: ' + error);
            $('#complete-sale').prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Complete Sale');
        }
    });
}



/**
     * Show order success modal
     */
    function showOrderSuccess(data) {
        $('#success-order-number').text(data.order_number);
        $('#success-total').text(kposConfig.currency + parseFloat(data.total).toFixed(2));
        $('#success-payment').text(data.payment_method.toUpperCase());
        
        if (data.payment_method === 'credit' && data.new_balance) {
            $('#success-balance-amount').text(kposConfig.currency + parseFloat(data.new_balance).toFixed(2));
            $('#success-new-balance').show();
        } else {
            $('#success-new-balance').hide();
        }

        $('#order-success-modal').show();
        
        // Store order ID for printing
        $('#print-receipt').data('order-id', data.order_id);
        
        loadRecentOrders();
    }

    /**
     * Reset POS after order
     */
    function resetPOS() {
        cart = [];
        clearTraineeSelection();
        $('#order-note').val('');
        $('#complete-sale').html('<span class="dashicons dashicons-yes-alt"></span> Complete Sale');
        loadWooCommerceCart(); // Reload to ensure cart is empty
    }

    /**
     * Print receipt
     */
    function printReceipt() {
        const orderId = $('#print-receipt').data('order-id');
        if (!orderId) return;

        // Open print window
        window.open(
            kposConfig.ajaxUrl + '?action=kpos_print_receipt&order_id=' + orderId + '&nonce=' + kposConfig.nonce,
            '_blank',
            'width=300,height=600'
        );
    }

    /**
     * Load recent orders
     */
    function loadRecentOrders() {
        $.ajax({
            url: kposConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kpos_get_recent_orders',
                nonce: kposConfig.nonce
            },
            success: function(response) {
                if (response.success && response.data.orders && response.data.orders.length > 0) {
                    let html = '';
                    response.data.orders.forEach(order => {
                        html += `
                            <div class="recent-order-item">
                                <strong>#${order.order_number}</strong>
                                ${order.customer} - ${kposConfig.currency}${order.total}
                            </div>
                        `;
                    });
                    $('#recent-orders').html(html);
                }
            }
        });
    }

    /**
     * Show error message
     */
    function showError(message) {
        // You can replace this with a better notification system
        alert('Error: ' + message);
        console.error('Error:', message);
    }

    /**
     * FIXED: Show success message
     */
    function showSuccess(message) {
        // Simple success notification - you can enhance this
        const notification = $('<div class="kpos-notification success">' + message + '</div>');
        $('body').append(notification);
        
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 2000);
    }

})(jQuery);