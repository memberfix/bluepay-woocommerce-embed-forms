<?php 

function getBluePayTransactionDetails($accountID, $tamperProofSeal, $reportStartDate, $reportEndDate, $transactionID, $mode) {
    // Generate the Tamper Proof Seal
  

    // API endpoint
    $url = 'https://secure.bluepay.com/interfaces/bp10emu';

    // Request data
    $postData = [
        'MODE' => $mode,
        'MERCHANT' => $accountID,
        'TAMPER_PROOF_SEAL' => $tamperProofSeal,
        'id' => $transactionID,
        'REPORT_START_DATE' => $reportStartDate,
        'REPORT_END_DATE' => $reportEndDate
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing purposes; set to true in production

    // Execute request and fetch response
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return [
            'error' => true,
            'message' => 'cURL Error: ' . curl_error($ch),
        ];
    }

    curl_close($ch);

    // Parse response
    $responseData = [];
    parse_str($response, $responseData);

    // Return parsed response
    return $responseData;
}


function get_single_transaction_details($transactionID) {
    $accountID = get_option('bluepay_merchant_id'); // Merchant Account ID
    $tamperProofSeal = get_option('bluepay_tamper_proof_seal'); // Tamper Proof Seal
    $reportStartDate = '2024-12-01 00:00:00'; // Replace with your actual start date
    $reportEndDate = '2024-12-18 23:59:59'; 
    $mode = get_option('bluepay_mode_variation'); // Mode: TEST or LIVE

    if (!$accountID || !$tamperProofSeal || !$mode) {
        return [
            'error' => true,
            'message' => 'BluePay credentials are missing. Please check the plugin settings.',
        ];
    }

    $transaction_response = getBluePayTransactionDetails($accountID, $tamperProofSeal, $reportStartDate, $reportEndDate, $transactionID, $mode);

    return $transaction_response;
}