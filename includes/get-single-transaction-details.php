<?php

// Include the BluePay library
//include_once "BluePayPayment_BP20Post.php";
require_once plugin_dir_path( __FILE__ ) . '../vendor/BluePayPayment_BP10Emu.php';
/**
 * Get transaction details from BluePay by Transaction ID.
 *
 * @param string $transaction_id The Transaction ID to query.
 * @param string $start_date The start date for the report (format: YYYY-MM-DD).
 * @param string $end_date The end date for the report (format: YYYY-MM-DD).
 * @return array|WP_Error Transaction details on success, or WP_Error on failure.
 */

 


function get_bluepay_transaction_details($transaction_id ) {
    // Replace these with your actual BluePay credentials or pull them dynamically
    $accountID  = get_option('bluepay_merchant_id'); // Merchant Account ID
    $secretKey  = get_option('bluepay_tamper_proof_seal'); // Secret Key
    $mode       = get_option('bluepay_mode_variation'); // Mode: TEST or LIVE

    // Validate required credentials
    if (empty($accountID) || empty($secretKey) || empty($mode)) {
        return new WP_Error('missing_credentials', 'BluePay credentials are missing. Please check settings.');
    }

    // Initialize the BluePay query object
    $query = new BluePayPayment_BP10Emu($accountID, $secretKey, $mode);


    try {
        // Run the Single Transaction Query
        //$query->getSingleTransQuery($start_date, $end_date, '1'); // '1' excludes errored transactions
        $query->queryByTransactionID($transaction_id); // Add Transaction ID to query

        // Process the request
        $query->process();

        // Check for errors
        if ($query->isError()) {
            return new WP_Error('query_error', 'Error querying BluePay: ' . $query->getMessage());
        }

        // Prepare transaction details into an associative array
        $transaction_details = [
            'response'         => $query->getResponse(),
            'first_name'       => $query->getName1(),
            'last_name'        => $query->getName2(),
            'transaction_id'   => $query->getID(),
            'payment_type'     => $query->getPaymentType(),
            'transaction_type' => $query->getTransType(),
            'amount'           => $query->getAmount(),
        ];

        return $transaction_details;

    } catch (Exception $e) {
        return new WP_Error('exception', 'Exception occurred: ' . $e->getMessage());
    }
}
