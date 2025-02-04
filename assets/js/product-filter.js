jQuery(document).ready(function($) {

    function checkAndShowProducts() {
        const plan = $('input[name="plan"]:checked').val();
        const revenue = $('input[name="revenue"]:checked').val();
        
        if (plan && revenue) {
            $('#products-container').show();
            updateProducts();
        } else {
            $('#products-container').hide();
        }
    }

    function updateProducts() {
        let filterData = {
            action: 'filter_products',
            nonce: productFilterAjax.nonce,
            plan: $('input[name="plan"]:checked').val() || '',
            revenue: $('input[name="revenue"]:checked').val() || ''
        };
        
        // Show loading state
        $('.product-variations').html('<p>Loading...</p>');
        
        // Make AJAX request
        $.ajax({
            url: productFilterAjax.ajaxurl,
            type: 'POST',
            data: filterData,
            success: function(response) {
                if (response.success) {
                    // Update each section separately
                    $('#membership-products .product-variations').html(response.data.membership);
                    $('#premium-service-products .product-variations').html(response.data.premium_service);
                    $('#local-chapter-products .product-variations').html(response.data.local_chapter);
                    
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
            alert('Please select at least one product to add to your membership.');
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
                $('#update-subscription-btn').prop('disabled', false).text('Update Membership');
            }
        });
    }

    // Add event listeners
    $('input[name="plan"], input[name="revenue"]').on('change', function() {
        checkAndShowProducts();
    });
    
    $(document).on('change', '.product-variations input[type="checkbox"]', updateTotal);
    $(document).on('change', 'input[type="checkbox"]', calculateTotal);
    $('#change-subscription-btn').on('click', updateSubscription);

    // Initial check for selections
    checkAndShowProducts();
});
