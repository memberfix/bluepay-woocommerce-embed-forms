document.addEventListener('DOMContentLoaded', function () {
    console.log('BluePay AJAX script loaded.');

    // Select the form element
    var form = document.getElementById('bluepay-payment-form');

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // Prevent the default form submission temporarily

            // Collect form data
            var formData = new FormData(form);

            // Add the AJAX action
            formData.append('action', 'handle_bluepay_submission');

            // Optional: Disable the submit button and show a loading state
            var submitButton = form.querySelector('input[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.value = 'Processing...';
            }

            // Send AJAX request to update WooCommerce order details
            var xhr = new XMLHttpRequest();
            xhr.open('POST', bluepayAjax.ajax_url, true);

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    var response = JSON.parse(xhr.responseText);

                    if (response.success) {
                        console.log('Order updated successfully:', response.message);

                        // Proceed with the standard form submission
                        form.submit();
                    } else {
                        console.error('Error updating order:', response.message);
                        alert('Error: ' + response.message);

                        // Re-enable the submit button
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.value = 'Make Payment';
                        }
                    }
                } else {
                    console.error('AJAX request failed:', xhr.statusText);
                    alert('An error occurred. Please try again.');

                    // Re-enable the submit button
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.value = 'Make Payment';
                    }
                }
            };

            xhr.onerror = function () {
                console.error('AJAX request failed.');
                alert('An error occurred. Please try again.');

                // Re-enable the submit button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.value = 'Make Payment';
                }
            };

            xhr.send(formData); // Send the form data
        });
    } else {
        console.error('Form with id "bluepay-payment-form" not found.');
    }
});
