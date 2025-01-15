<?php
function bluepay_gateway_order_confirmation_shortcode() {
    // Get parameters from the URL
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
    $sent_to = isset($_GET['sent_to']) ? sanitize_email($_GET['sent_to']) : null;
    $additional_details = get_option('bluepay_confirmed_order_page_additional_details', '');

    // Validate URL parameters
    if (!$order_id) {
        return '<p>' . esc_html__('Invalid or missing order details.', 'woocommerce') . '</p>';
    }

    // Get order details
    $order = wc_get_order($order_id);
    if (!$order) {
        return '<p>' . esc_html__('Order not found.', 'woocommerce') . '</p>';
    }

    // Prepare order items and details
    $order_items = '';
    foreach ($order->get_items() as $item) {
        $order_items .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%d</td>
                <td>%s</td>
            </tr>',
            esc_html($item->get_name()),
            intval($item->get_quantity()),
            wc_price($item->get_total())
        );
    }
    $order_total = wc_price($order->get_total());

    // Branding and disclaimer
    $branding_text = ''; //text may be added here
    $logo_url = esc_url(wp_upload_dir()['baseurl'] . '/2024/09/Techserve-alliance-logo.png');
    $disclaimer = $additional_details;

    // Styling (applied universally)
    ?>
    <style>
        body, html {
            font-family: "Helvetica Neue", Roboto, Arial, "Droid Sans", sans-serif;
            line-height: 1.5;
        }
        .bluepay-form {
            padding: 30px;
            margin: 0 auto;
            max-width: 800px;
            border: 1px solid #ddd;
        }
        .header {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .header img {
            max-height: 80px;
        }
        .header .branding {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .order-details {
            margin-top: 20px;
        }
        .order-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-details th, .order-details td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
        }
        .disclaimer {
            margin-top: 20px;
            font-size: 10px;
            color: #666;
        }

	.payment-link-wrapper {
            margin: 1rem 0;
        }
        .payment-link-copy-block {
            width: 100%;
            display: flex;
            gap: 0.5rem;
            margin-top: 0.8rem;
            position: relative;
        }
        .payment-link {
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            background: #efefef;
            flex-grow: 1;
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        .payment-link-copy-msg {
            position: absolute;
            top: 0;
            right: 0;
            background: #059B95;
            color: #fff;
            font-size: 14px;
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            opacity: 0;
            z-index: -3;
            transition: all ease 0.4s;
        }
        .payment-link-copy-msg.active {
            z-index: 24;
            opacity: 1;
            top: -2rem;
        }
    </style>
    <?php

    // Display the form
    ob_start();

    if ($sent_to) {
        ?>
        <div class="bluepay-form">

            <h2><?php echo esc_html__('Thank you!', 'woocommerce'); ?></h2>
            <p>
                <?php echo sprintf(
                    esc_html__('Payment details for order #%d were successfully sent to %s.', 'woocommerce'),
                    esc_html($order_id),
                    esc_html($sent_to)
                ); ?>
            </p>
        </div>
        <?php
    }
    ?>
    <div class="bluepay-form">
        <div class="header">
            <img src="<?php echo $logo_url; ?>" alt="TechServe Alliance Logo">
            <div class="branding"><?php echo esc_html($branding_text); ?></div>
        </div>
		<br>
		<br>
        <div class="order-details">
            <h3><?php echo esc_html__('Order #' . $order_id , 'woocommerce'); ?></h3>
			<br>
			<br>
			<br>
            <table>
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Product', 'woocommerce'); ?></th>
                        <th><?php echo esc_html__('Quantity', 'woocommerce'); ?></th>
                        <th><?php echo esc_html__('Total', 'woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $order_items; ?>
                </tbody>
            </table>
            <p><strong><?php echo esc_html__('Order Total:', 'woocommerce'); ?></strong> <?php echo $order_total; ?></p>
        </div>
		<br>
		<br>
            <div>
                    <a href="/form-bluepay?order_id=<?php echo esc_attr($order_id); ?> " class="elementor-button elementor-button-link elementor-size-sm">
                        Pay Now with Credit Card
                    </a>
            </div>
	<div class="payment-link-wrapper">
            <p>Or copy the payment link:</p>
            <div class="payment-link-copy-block">
                <div class="payment-link" id="payment_link_string">
                    <?php echo $_SERVER['HTTP_HOST']; ?>/form-bluepay?order_id=<?php echo esc_attr($order_id); ?>
                </div>
                <button id="payment_link_copy_btn" class="elementor-button elementor-button-link elementor-size-sm"
                    title="Click to copy the payment link">
                    Copy Link
                </button>
                <div class="payment-link-copy-msg" id="payment_link_copy_msg">
                    Link copied
                </div>
            </div>
        </div>
        <script>
            var payment_link_string = document.querySelector('#payment_link_string');
            var payment_link_copy_btn = document.querySelector('#payment_link_copy_btn');
            var payment_link_copy_msg = document.querySelector('#payment_link_copy_msg');

            payment_link_copy_btn.addEventListener('click', function () {
                var content = payment_link_string.innerText;
                navigator.clipboard.writeText(content).then(function () {
                    payment_link_copy_msg.classList.add('active');
                    setTimeout(() => {
                        payment_link_copy_msg.classList.remove('active');
                    }, 2000);
                }, function (err) {
                    console.error('Error copying text: ', err);
                });
            });
        </script>

        <br>
        <br>

        <div class="footer">
            <p><strong>Have questions or need assistance?</strong></p>
            <p>Phone: 703-838-2050</p>
            <p>Email: membership@techservealliance.org</p>
        </div>
        <div class="disclaimer">
            <?php echo esc_html($disclaimer); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('bluepay_gateway_order_confirmation', 'bluepay_gateway_order_confirmation_shortcode');
