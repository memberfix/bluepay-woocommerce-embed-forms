jQuery(document).ready(function($) {
    function refreshSubscriptionDetails() {
        $.ajax({
            url: subscriptionDetailsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'refresh_subscription_details',
                nonce: subscriptionDetailsAjax.nonce
            },
            success: function(response) {
                if (response) {
                    $('.subscription-info').replaceWith(response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error refreshing subscription details:', error);
            }
        });
    }

    // Refresh subscription details when needed (e.g., after subscription update)
    $(document).on('subscription_updated', refreshSubscriptionDetails);
});
