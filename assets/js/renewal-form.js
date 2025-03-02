/**
 * BluePay Renewal Form JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Store selected filter values
        let selectedFilters = {};
        
        // Handle subscription selection
        $('#mfx-subscription-select').on('change', function() {
            const subscriptionId = $(this).val();
            
            // Update the hidden field with the selected subscription ID
            $('#selected_subscription_id').text(subscriptionId);
            
            if (subscriptionId) {
                // Show the filters section and loading message
                $('#subscription-filters').show();
                $('.filter-loading').show();
                $('.filter-container').hide().empty();
                
                // Reset selected filters
                selectedFilters = {};
                
                // Make AJAX call to get filters for this subscription
                $.ajax({
                    url: mfx_renewal_form_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_subscription_filters',
                        subscription_id: subscriptionId,
                        nonce: mfx_renewal_form_ajax.nonce
                    },
                    success: function(response) {
                        $('.filter-loading').hide();
                        
                        if (response.success) {
                            // Build filter UI
                            buildFilterUI(response.data.group, response.data.filters);
                        } else {
                            // Show error message
                            $('.filter-container').html('<div class="error-message">' + response.data.message + '</div>').show();
                        }
                    },
                    error: function() {
                        $('.filter-loading').hide();
                        $('.filter-container').html('<div class="error-message">Error loading filters. Please try again.</div>').show();
                    }
                });
            } else {
                // Hide filters if no subscription is selected
                $('#subscription-filters').hide();
                $('.filter-container').empty();
            }
        });
        
        /**
         * Build the filter UI based on the group and filters
         */
        function buildFilterUI(group, filters) {
            const $container = $('.filter-container');
            
            // Add group information
            $container.append('<div class="group-info">Group: ' + group + '</div>');
            
            // Add filters
            $.each(filters, function(attributeName, filterData) {
                const $filterSection = $('<div class="filter-section" data-attribute="' + attributeName + '"></div>');
                
                // Add filter label
                $filterSection.append('<h4>' + filterData.label + '</h4>');
                
                // Add radio buttons for options
                const $radioGroup = $('<div class="radio-group"></div>');
                
                $.each(filterData.options, function(index, optionValue) {
                    const optionId = 'filter-' + attributeName + '-' + index;
                    const $label = $('<label for="' + optionId + '"></label>');
                    
                    const $radio = $('<input type="radio" id="' + optionId + '" name="filter-' + attributeName + '" value="' + optionValue + '">');
                    
                    // Add change event handler
                    $radio.on('change', function() {
                        selectedFilters[attributeName] = optionValue;
                        updateFilterSelection();
                    });
                    
                    $label.append($radio);
                    $label.append(' ' + optionValue);
                    $radioGroup.append($label);
                });
                
                $filterSection.append($radioGroup);
                $container.append($filterSection);
            });
            
            // Add container for product variations that match the filters
            $container.append('<div class="matching-products"></div>');
            
            $container.show();
        }
        
        /**
         * Update the product variations shown based on selected filters
         */
        function updateFilterSelection() {
            const subscriptionId = $('#mfx-subscription-select').val();
            const $matchingProducts = $('.matching-products');
            
            // Check if all filters have been selected
            const filterSections = $('.filter-section').length;
            const selectedFilterCount = Object.keys(selectedFilters).length;
            
            if (filterSections === selectedFilterCount) {
                // Show loading message
                $matchingProducts.html('<p class="loading-variations">Loading matching products...</p>');
                
                // Get the group from the group-info div
                const group = $('.group-info').text().replace('Group: ', '');
                
                // Make AJAX call to get matching product variations
                $.ajax({
                    url: mfx_renewal_form_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_matching_variations',
                        group: group,
                        filters: selectedFilters,
                        nonce: mfx_renewal_form_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            displayMatchingVariations(response.data.variations);
                        } else {
                            $matchingProducts.html('<div class="error-message">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $matchingProducts.html('<div class="error-message">Error loading product variations. Please try again.</div>');
                    }
                });
            }
        }
        
        /**
         * Display matching product variations
         */
        function displayMatchingVariations(variations) {
            const $matchingProducts = $('.matching-products');
            
            if (!variations || variations.length === 0) {
                $matchingProducts.html('<p>No matching products found for the selected filters.</p>');
                return;
            }
            
            // Clear previous content
            $matchingProducts.empty();
            
            // Add heading
            $matchingProducts.append('<h4>Matching Products (' + variations.length + ')</h4>');
            
            // Create product grid
            const $productGrid = $('<div class="product-grid"></div>');
            
            // Add each variation to the grid
            $.each(variations, function(index, variation) {
                const $product = $('<div class="product-item"></div>');
                
                // Format price
                const price = parseFloat(variation.price);
                const formattedPrice = price.toLocaleString('en-US', {
                    style: 'currency',
                    currency: 'USD'
                });
                
                // Build product HTML
                $product.append('<div class="product-type">' + variation.type + '</div>');
                $product.append('<h5>' + variation.parent_name + '</h5>');
                $product.append('<p class="variation-name">' + variation.name + '</p>');
                $product.append('<p class="price">' + formattedPrice + '</p>');
                
                // Add attributes if available
                if (variation.attributes && Object.keys(variation.attributes).length > 0) {
                    const $attributes = $('<ul class="attributes"></ul>');
                    
                    $.each(variation.attributes, function(key, value) {
                        $attributes.append('<li><strong>' + key.replace('pa_', '').replace('-', ' ') + ':</strong> ' + value + '</li>');
                    });
                    
                    $product.append($attributes);
                }
                
                // Add add-to-cart button
                const $addToCartBtn = $('<a href="' + variation.add_to_cart_url + '" class="add-to-cart-btn">Add to Cart</a>');
                $product.append($addToCartBtn);
                
                // Add the product to the grid
                $productGrid.append($product);
            });
            
            $matchingProducts.append($productGrid);
        }
    });
    
})(jQuery);
