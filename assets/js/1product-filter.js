jQuery(document).ready(function($) {

    function checkAndShowProducts() {
        const plan = $('input[name="plan"]:checked').val();
        const revenue = $('input[name="revenue"]:checked').val();
        
        // Hide button by default when filters change
        $('#update-subscription-btn, #change-subscription-btn').hide();
        
        // Determine which filter triggered this call
        const triggerElement = $(document.activeElement);
        const triggerName = triggerElement.attr('name');
        
        // Check if this is a supplier-specific action
        // If the revenue filter is inside a container with supplier-revenue-filters ID, it's a supplier action
        const isSupplierAction = triggerElement.closest('#supplier-revenue-filters').length > 0;
        
        console.log('checkAndShowProducts - isSupplierAction:', isSupplierAction, 'revenue:', revenue, 'plan:', plan, 'trigger:', triggerName);
        
        if ((isSupplierAction && revenue) || (!isSupplierAction && plan && revenue)) {
            $('#products-container').show();
            updateProducts(isSupplierAction);
        }
    }

    function updateProducts(isSupplierAction = false) {
        // Get the selected revenue value
        let revenueValue = '';
        
        // If this is a supplier action, get revenue from supplier-specific filters
        if (isSupplierAction) {
            revenueValue = $('#supplier-revenue-filters input[name="revenue"]:checked').val() || '';
        } else {
            // Otherwise get from regular filters
            revenueValue = $('input[name="revenue"]:checked').val() || '';
        }
        
        let filterData = {
            action: 'filter_products',
            nonce: productFilterAjax.nonce,
            plan: isSupplierAction ? '' : ($('input[name="plan"]:checked').val() || ''),
            revenue: revenueValue,
            is_supplier: isSupplierAction ? 'true' : 'false'
        };
        
        console.log('updateProducts - sending data:', filterData);
        
        // Show loading state in the appropriate container
        if (isSupplierAction) {
            $('#supplier-products .product-variations').html('<p>Loading...</p>');
        } else {
            $('.product-variations').html('<p>Loading...</p>');
        }
        
        // Make AJAX request
        $.ajax({
            url: productFilterAjax.ajaxurl,
            type: 'POST',
            data: filterData,
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    // Update each section separately
                    $('#membership-products .product-variations').html(response.data.membership || '');
                    $('#premium-service-products .product-variations').html(response.data.premium_service || '');
                    $('#local-chapter-products .product-variations').html(response.data.local_chapter || '');
                    $('#supplier-products .product-variations').html(response.data.supplier || '');
                    
                    // Show/hide button based on server response
                    $('#update-subscription-btn, #change-subscription-btn').toggle(response.data.show_button);
                    
                    // Force supplier checkboxes to be checked and disabled
                    $('#supplier-products input[type="checkbox"]').prop('checked', true).attr('onclick', 'return false;').css('pointer-events', 'none');
                    
                    // Calculate total after updating products
                    calculateTotal();
                    
                    // Initialize any dynamic elements
                    updateTotal();
                } else {
                    $('.product-variations').html('<p>Error loading products. Please try again.</p>');
                }
            },
            error: function() {
                $('.product-variations').html('<p>Error loading products. Please try again.</p>');
            }
        });
    }

    function calculateTotal() {
        var total = 0;
        $('input[type="checkbox"]:checked').each(function() {
            var price = parseFloat($(this).data('price')) || 0;
            total += price;
        });
        $('#total-price').text(total.toFixed(2));
    }

    function updateTotal() {
        let total = 0;
        $('.product-variations input[type="checkbox"]:checked').each(function() {
            total += parseFloat($(this).data('price')) || 0;
        });
        $('.total-amount').text('$' + total.toFixed(2));
    }

    function getSelectedProductIds() {
        let ids = [];
        $('.product-variations input[type="checkbox"]:checked').each(function() {
            ids.push($(this).val());
        });
        return ids;
    }

    function updateSubscription() {
        let selectedProducts = getSelectedProductIds();
        
        if (selectedProducts.length === 0) {
            alert('Please select at least one product to add to your subscription.');
            return;
        }

        $('#update-subscription-btn').prop('disabled', true).text('Processing...');

        $.ajax({
            url: productFilterAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_user_subscription',
                nonce: productFilterAjax.nonce,
                subscription_id: subscriptionId,
                products: selectedProducts
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(response.data || 'Error updating membership. Please try again.');
                }
            },
            error: function() {
                alert('Error updating membership. Please try again.');
            },
            complete: function() {
                $('#update-subscription-btn').prop('disabled', false).text('Update Subscription');
            }
        });
    }

    // Add event listeners
    $('input[name="plan"], input[name="revenue"]').on('change', function() {
        console.log('Filter changed:', $(this).attr('name'), $(this).val(), 'Container:', $(this).closest('.filter-group').attr('id'));
        checkAndShowProducts();
    });
    
    // Add specific listener for supplier revenue filters
    $('#supplier-revenue-filters input[name="revenue"]').on('change', function() {
        console.log('Supplier revenue filter changed:', $(this).val());
        // Get the selected revenue
        const revenue = $(this).val();
        
        // Make AJAX request specifically for supplier products
        $.ajax({
            url: productFilterAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'filter_products',
                nonce: productFilterAjax.nonce,
                plan: '',
                revenue: revenue,
                is_supplier: 'true'
            },
            success: function(response) {
                console.log('Supplier-specific AJAX response:', response);
                if (response.success) {
                    // Update only the supplier products section
                    $('#supplier-products .product-variations').html(response.data.supplier || '');
                    
                    // Show/hide button based on server response
                    $('#update-subscription-btn, #change-subscription-btn').toggle(response.data.show_button);
                    
                    // Calculate total after updating products
                    // Force supplier checkboxes to be checked
                    $('#supplier-products input[type="checkbox"]').prop('checked', true).attr('onclick', 'return false;').css('pointer-events', 'none');
                    calculateTotal();
                    updateTotal();
                } else {
                    $('#supplier-products .product-variations').html('<p>Error loading products. Please try again.</p>');
                }
            },
            error: function() {
                $('#supplier-products .product-variations').html('<p>Error loading products. Please try again.</p>');
            }
        });
    });
    
    $(document).on('change', '.product-variations input[type="checkbox"]', function() {
        // Only non-membership and non-supplier checkboxes can be changed
        if (!$(this).closest('#membership-products').length && !$(this).closest('#supplier-products').length) {
            updateTotal();
            calculateTotal();
        }
    });
    $('#change-subscription-btn, #update-subscription-btn').on('click', updateSubscription);

    // Hide buttons initially
    $('#update-subscription-btn, #change-subscription-btn').hide();
    
    // Initial checks
    checkAndShowProducts();
    updateTotal();
});
