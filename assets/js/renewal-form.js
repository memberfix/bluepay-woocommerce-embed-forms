/**
 * BluePay Renewal Form JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Store selected filter values
        let selectedFilters = {};
        
        /**
         * Load subscription filters based on subscription ID
         */
        function loadSubscriptionFilters() {
            const subscriptionId = $('#selected_subscription_id').text();
            
            if (subscriptionId) {
                // Show the filters section and loading message
                $('#membership-filters').show();
                $('.filter-loading').show();
                $('.filter-container').hide().empty();
                
                // Reset selected filters
                selectedFilters = {};
                
                // Make AJAX call to get filters for this membership
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
                // Hide filters if no subscription ID is found
                $('#membership-filters').hide();
                $('.filter-container').empty();
            }
        }
        
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
            
            // Create form for selected variations
            const $form = $('<form id="selected-variations-form"></form>');
            const $productList = $('<ul class="product-list"></ul>');
            
            // Add each variation to the list
            $.each(variations, function(index, variation) {
                // Create product item with attribute classes
                const attributeClasses = variation.attribute_classes ? variation.attribute_classes.join(' ') : '';
                const $product = $('<li class="product-item ' + attributeClasses + '"></li>');
                
                // Format price
                const price = parseFloat(variation.price);
                const formattedPrice = price.toLocaleString('en-US', {
                    style: 'currency',
                    currency: 'USD'
                });
                
                // Add checkbox
                const $checkbox = $('<input type="checkbox" name="selected_variations[]" value="' + variation.variation_id + '" id="variation-' + variation.variation_id + '" class="variation-checkbox" data-price="' + variation.price_raw + '">');
                
                // Check if this is a Type A product and auto-check it with reduced opacity
                if (variation.type === 'Type A' || (variation.attribute_classes && variation.attribute_classes.includes('attr-type-a'))) {
                    $checkbox.prop('checked', true);
                    $checkbox.addClass('auto-checked');
                }
                
                const $label = $('<label for="variation-' + variation.variation_id + '" class="variation-label"></label>');
                
                // Build product HTML inside the label
                $label.append('<div class="product-type">' + variation.type + '</div>');
                $label.append('<h5>' + variation.parent_name + '</h5>');
                $label.append('<p class="variation-name">' + variation.name + '</p>');
                $label.append('<p class="price">' + formattedPrice + '</p>');
                
                // Add attributes if available
                if (variation.attributes && Object.keys(variation.attributes).length > 0) {
                    const $attributes = $('<ul class="attributes"></ul>');
                    
                    $.each(variation.attributes, function(key, value) {
                        const cleanKey = key.replace('pa_', '').replace('-', ' ');
                        const attrClass = 'attr-item-' + key.replace('pa_', '') + '-' + value.toLowerCase().replace(/\s+/g, '-');
                        $attributes.append('<li class="' + attrClass + '"><strong>' + cleanKey + ':</strong> ' + value + '</li>');
                    });
                    
                    $label.append($attributes);
                }
                
                // Append checkbox and label to product item
                $product.append($checkbox);
                $product.append($label);
                
                // Add to list
                $productList.append($product);
            });
            
            // Add the list to the form
            $form.append($productList);
            
            // Add total section
            const $totalSection = $('<div class="total-section">' +
                '<div class="total-label">Total: <span class="total-amount">$0.00</span></div>' +
                '<div class="total-items">0 items selected</div>' +
            '</div>');
            $form.append($totalSection);
            
            // Add submit button
            const $submitButton = $('<button type="button" id="process-selected-variations" class="button">Update Membership</button>');
            $form.append($submitButton);
            
            // Add the form to the matching products section
            $matchingProducts.append($form);
            
            // Add event listener for checkboxes to update total
            $form.on('change', '.variation-checkbox', function() {
                updateTotal();
            });
            
            // Add event listener for the submit button
            $submitButton.on('click', function() {
                processSelectedVariations();
            });
            
            // Update total to include auto-checked items
            updateTotal();
        }
        
        /**
         * Update the total price and count of selected items
         */
        function updateTotal() {
            let totalPrice = 0;
            let itemCount = 0;
            
            // Get all checked checkboxes
            $('.variation-checkbox:checked').each(function() {
                itemCount++;
                
                // Get price from data attribute
                const price = parseFloat($(this).data('price'));
                if (!isNaN(price)) {
                    totalPrice += price;
                }
            });
            
            // Format the total price
            const formattedTotal = totalPrice.toLocaleString('en-US', {
                style: 'currency',
                currency: 'USD'
            });
            
            // Update the total section
            $('.total-amount').text(formattedTotal);
            $('.total-items').text(itemCount + ' item' + (itemCount !== 1 ? 's' : '') + ' selected');
        }
        
        /**
         * Process selected variations
         */
        function processSelectedVariations() {
            const selectedVariations = [];
            
            // Get all checked checkboxes
            $('.variation-checkbox:checked').each(function() {
                selectedVariations.push($(this).val());
            });
            
            if (selectedVariations.length === 0) {
                alert('Please select at least one product variation.');
                return;
            }
            
            // Get the subscription ID from the hidden div
            const subscriptionId = $('#selected_subscription_id').text();
            
            if (!subscriptionId) {
                alert('No subscription ID found. Please try refreshing the page.');
                return;
            }
            
            // Update total one more time to ensure accuracy
            updateTotal();
            
            // Show loading state
            const $button = $('#process-selected-variations');
            const originalText = $button.text();
            $button.text('Processing...').prop('disabled', true);
            
            // Make AJAX call to update the subscription with selected variations
            $.ajax({
                url: mfx_renewal_form_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_subscription_with_variations',
                    subscription_id: subscriptionId,
                    variations: selectedVariations,
                    nonce: mfx_renewal_form_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // If there are errors for some variations, log them to console
                        if (response.data.errors && response.data.errors.length > 0) {
                            console.log('Some variations could not be added:', response.data.errors);
                        }
                        
                        // Redirect to subscription view page
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            // Fallback if no redirect URL is provided
                            alert(response.data.message || 'Membership updated successfully');
                            $button.text(originalText).prop('disabled', false);
                        }
                    } else {
                        // Show error message
                        alert(response.data.message);
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error updating subscription. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        }
        
        // Load subscription filters after a slight delay to ensure everything is loaded
        setTimeout(function() {
            loadSubscriptionFilters();
        }, 300);
    });
    
})(jQuery);
