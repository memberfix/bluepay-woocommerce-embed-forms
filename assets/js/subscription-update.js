jQuery(document).ready(function($) {
    $(document).on('click', '#update-subscription-btn', function(e) {
        e.preventDefault();
        
        var selectedVariations = [];
        $('input[type="checkbox"]:checked').each(function() {
            selectedVariations.push($(this).val());
        });
        
        // Get the selected plan from the radio buttons or data attribute
        var selectedPlan = $('input[name="plan"]:checked').val();
        
        // Get the subscription ID from the page
        var subscriptionId = $('#subscription_id').val();
        
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'mfx_update_subscription',
                nonce: ajax_object.nonce,
                subscription_id: subscriptionId,
                selected_variations: selectedVariations,
                selected_plan: selectedPlan
            },
            beforeSend: function() {
                $('#update-subscription-btn').prop('disabled', true).text('Updating membership...');
            },
            success: function(response) {
                if (response.success) {
                    alert('Membership updated successfully!');
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while updating the membership.');
            },
            complete: function() {
                $('#update-subscription-btn').prop('disabled', false).text('Update Membership');
            }
        });
    });
});
