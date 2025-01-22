jQuery(document).ready(function($) {
    function updateSelectedProducts() {
        let selectedProducts = [];
        $('input[type="checkbox"]:checked').each(function() {
            let id = $(this).val();
            let name = $(this).siblings('.variation-details').find('.name').text();
            selectedProducts.push(`${name} (ID: ${id})`);
        });
        $('.selected-products').html(selectedProducts.join('<br>'));
    }

    function updateTotal() {
        let total = 0;
        // Always include checked checkboxes (including membership which is locked as checked)
        $('input[type="checkbox"]:checked').each(function() {
            total += parseFloat($(this).data('price')) || 0;
        });
        $('.total-amount').text('$' + total.toFixed(2));
        // Update selected products display
        updateSelectedProducts();
    }

    function updateProducts() {
        var selectedPlan = $('#plan-filters input:checked').val();
        var selectedRevenue = $('#revenue-filters input:checked').val();
        
        // Only require plan selection
        if (!selectedPlan) {
            $('.products-section .product-variations').html('<p>Please select a plan to view available options.</p>');
            return;
        }
        
        // Make AJAX request
        $.ajax({
            url: productFilterAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'filter_products',
                nonce: productFilterAjax.nonce,
                plan: selectedPlan,
                revenue: selectedRevenue || ''
            },
            beforeSend: function() {
                $('.products-section .product-variations').html('<p>Loading...</p>');
            },
            success: function(response) {
                if (response.success) {
                    // Update each section with its corresponding products
                    $('#membership-products .product-variations').html(response.data.membership);
                    $('#premium-service-products .product-variations').html(response.data.premium_service);
                    $('#local-chapter-products .product-variations').html(response.data.local_chapter);
                    
                    // Show sections only if they have products
                    $('.products-section').each(function() {
                        $(this).toggle($(this).find('.variations-list').length > 0);
                    });

                    // Calculate initial total and update selected products
                    updateTotal();
                } else {
                    $('.products-section .product-variations').html('<p>Error loading products.</p>');
                }
            },
            error: function() {
                $('.products-section .product-variations').html('<p>Error loading products.</p>');
            }
        });
    }
    
    // Add event listeners
    $('.radio-group input[type="radio"]').on('change', updateProducts);
    $(document).on('change', 'input[type="checkbox"]', updateTotal);
    
    // Initialize sections as hidden
    $('.products-section').hide();
});
