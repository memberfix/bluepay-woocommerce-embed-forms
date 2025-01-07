<?php 


function bluepay_mfx_create_order_checkout($email, $billingemail) {

  
          // Initialize WooCommerce session for new visitors if not active.
          if (WC()->session && !WC()->session->has_session()) {
              WC()->session->set_customer_session_cookie(true);
              error_log('[Bluepay MFX] WooCommerce session initialized.');
          }
  
          // Validate cart contents.
          if (!WC()->cart || WC()->cart->is_empty()) {
              error_log('[Bluepay MFX] Cart is empty. Session contents: ' . print_r(WC()->session->get_session_data(), true));
              wp_send_json_error(['message' => __('Your cart is empty. Please add items to proceed.', 'woocommerce')]);
          }
  
          // Create a new WooCommerce order.
          $order = wc_create_order();
          error_log('[Bluepay MFX] Order created. ID: ' . $order->get_id());
  
          // Add cart items to the order.
          foreach (WC()->cart->get_cart() as $cart_item) {
              $order->add_product($cart_item['data'], $cart_item['quantity']);
              error_log('[Bluepay MFX] Added product to order: ' . $cart_item['data']->get_name());
          }
  
          // Calculate order totals.
          $order->calculate_totals();
          error_log('[Bluepay MFX] Order totals calculated.');
  
          // Create or fetch the customer ID using the provided email
          if ($billingemail) {
              $user = get_user_by('email', $billingemail);
              if ($user) {
                  $customer_id = $user->ID; // Existing user
              } else {
                  // Create a new user for the email
                  $password = wp_generate_password(); // Generate a random password
                  $customer_id = wp_create_user($billingemail, $password, $billingemail);
                  if (is_wp_error($customer_id)) {
                      wp_send_json_error(['message' => __('Unable to create customer account.', 'woocommerce')]);
                  }
              }
  
              // Assign the customer ID to the order
              $order->set_customer_id($customer_id);
          }
          
              $order->save();

              $base_url = get_option('bluepay_confirmed_order_page_url', '');
  
              $order_id = $order->get_id();
  
  
          // Empty the cart to prevent duplicate orders.
          WC()->cart->empty_cart();
          error_log('[Bluepay MFX] Cart emptied.');
  
            // Redirect to the thank you page with the order ID and email
          if (empty($base_url)) {
              error_log('[Bluepay MFX] Thank you page URL is not set.');
              wp_send_json_error(['message' => __('Thank you page URL is not configured.', 'woocommerce')]);
          }
  
          // Add query arguments for order ID and email
          $bluepay_confirmed_order_page_url = esc_url_raw(
              add_query_arg(
                  [
                      'order_id' => $order->get_id(),
                      'sent_to'  => $email 
                  ],
                  $base_url // Ensure the base URL is sanitized
              )
          );
          
          // Send the response with the redirect URL
          wp_send_json_success(['redirect_url' => $bluepay_confirmed_order_page_url]);
          
  
      return $order_id;
  
  }

function custom_process_checkout($email, $billingemail) {

    if (!class_exists('WC_Checkout')) {
        error_log('[Bluepay MFX] WooCommerce class not loaded.');
        return new WP_Error('woocommerce_missing', 'WooCommerce is not loaded.');
    }

    $checkout = WC()->checkout();

    $order_id = $checkout->process_checkout();

    

    // if (!$checkout) {
    //     error_log('[Bluepay MFX] Failed to retrieve WooCommerce checkout instance.');
    //     return new WP_Error('checkout_instance_missing', 'Failed to retrieve WooCommerce checkout instance.');
    // }

    // if (WC()->session && !WC()->session->has_session()) {
    //     WC()->session->set_customer_session_cookie(true);
    //     error_log('[Bluepay MFX] WooCommerce session initialized.');
    // }

    $_POST = [
        'billing_first_name' => 'John',
        'billing_last_name'  => 'Doe',
        'billing_email'      => $billingemail,
        'billing_phone'      => '1234567890',
        'billing_country'    => 'US',
        'billing_state'      => 'CA',
        'billing_city'       => 'Los Angeles',
        'billing_postcode'   => '90001',
        'billing_address_1'  => '123 Main Street',
        'payment_method'     => 'bluepay_mfx', // Replace with your gateway's ID
    ];


    try {
        error_log('[Bluepay MFX] Attempting to process checkout...');
        $order_id = $checkout->process_checkout();
        error_log('[Bluepay MFX] Checkout processed successfully. Order ID: ' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('[Bluepay MFX] Failed to retrieve order object for ID: ' . $order_id);
            return new WP_Error('order_missing', 'Order not found after checkout processing.');
        }

        error_log('[Bluepay MFX] Order retrieved successfully. Order ID: ' . $order_id);

        $mail_sent = wp_mail(
            $email,
            'Order Created Successfully',
            'Your order ID is: ' . $order_id
        );
        if ($mail_sent) {
            error_log('[Bluepay MFX] Email sent successfully to: ' . $email);
        } else {
            error_log('[Bluepay MFX] Failed to send email to: ' . $email);
        }

        return $order_id;
    } catch (Exception $e) {
        error_log('[Bluepay MFX] Exception during checkout: ' . $e->getMessage());
        return new WP_Error('checkout_exception', $e->getMessage());
    }
}
