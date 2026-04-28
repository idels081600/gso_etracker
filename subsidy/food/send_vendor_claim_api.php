<?php

/**
 * Send batch data to Food Voucher Vendor Claim API
 * 
 * @param array $vendor Vendor data array
 * @param string $batch_number Generated batch number
 * @param float $total_amount Total batch amount
 * @param array $vouchers Array of voucher numbers in this batch
 * @return array Result status with details
 */
function sendVendorClaimAPI($vendor, $batch_number, $total_amount, $vouchers = [])
{
    $result = [
        'success' => false,
        'http_code' => null,
        'message' => '',
        'response' => null
    ];

    try {
        $vendorClaimPayload = [
            "market" => $vendor['area'],
            "stallNo" => $vendor['vendor_serial'],
            "vendorName" => $vendor['vendor_name'],
            "claimControlNo" => $batch_number,
            "totalAmount" => $total_amount,
            "vouchers" => $vouchers
        ];

        $vendorClaimJson = json_encode($vendorClaimPayload);

        if ($vendorClaimJson === false) {
            throw new Exception("Vendor Claim JSON encode error: " . json_last_error_msg());
        }

        $chClaim = curl_init("http://192.168.101.232/api/foodvoucher/vendor-claim");

        curl_setopt_array($chClaim, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "x-api-key: " . env('API_SECRET')
            ],
            CURLOPT_POSTFIELDS => $vendorClaimJson,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for localhost
            CURLOPT_VERBOSE => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $claimResponse = curl_exec($chClaim);

        if ($claimResponse === false) {
            $claimError = curl_error($chClaim);
            curl_close($chClaim);
            error_log("Vendor Claim API cURL Error: " . $claimError);

            $result['message'] = 'cURL Error: ' . $claimError;
            $result['success'] = false;
        } else {
            $claimHttpCode = curl_getinfo($chClaim, CURLINFO_HTTP_CODE);

            error_log("Vendor Claim API Response Code: " . $claimHttpCode);
            error_log("Vendor Claim API Raw Response: " . $claimResponse);

            $result['http_code'] = $claimHttpCode;
            $result['response'] = $claimResponse;

            if ($claimHttpCode !== 200 && $claimHttpCode !== 201) {
                error_log("Vendor Claim API HTTP Error: " . $claimHttpCode);
                $result['message'] = 'HTTP Error: ' . $claimHttpCode;
                $result['success'] = false;
            } else {
                error_log("Vendor Claim API Success: Batch " . $batch_number . " sent successfully");
                $result['message'] = 'API Call Success';
                $result['success'] = true;
            }
        }

        curl_close($chClaim);
    } catch (Exception $claimException) {
        $errorMsg = "Exception: " . $claimException->getMessage();
        error_log("Vendor Claim API " . $errorMsg);
        $result['message'] = $errorMsg;
        $result['success'] = false;
    }

    return $result;
}
