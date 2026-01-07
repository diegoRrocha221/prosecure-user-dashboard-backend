<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();
require_once '/var/www/html/controllers/hashing_lsp.php';

function callApi($masterReference, $planId, $email) {
    $url = 'http://localhost:7082/editplan'; 

    $data = [
        'master_reference' => $masterReference,
        'plan_id'         => $planId,
        'email'           => $email,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($data))
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $errorResponse = [
            'status' => 'error',
            'message' => 'Failed to connect to API: ' . curl_error($ch),
        ];
        echo json_encode($errorResponse);
        curl_close($ch);
        return;
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['error'])) {
        $errorResponse = [
            'status' => 'error',
            'message' => $responseData['error'], 
        ];
        echo "Service unavailable, please try again";
    } else {
        $successResponse = [
            'status' => 'success',
            'message' => 'Plan updated successfully', 
            'data' => $responseData, 
        ];
        echo "Plan successfully removed, if you are in the 30-day free period your first invoice has already been updated with the new amount. otherwise the actions will only take effect in the next payment cycle.";
    }

    curl_close($ch);
}
if(isset($_POST['action']) && isset($_POST['psp']) && isset($_POST['psr']) && $_POST['action'] == 'cancel_plan'){
  $master_reference = $_SESSION['reference'];
  $plan_id = intval(decrypt_lsp($_POST['psp']));
  $email = decrypt_lsp($_POST['psr']);
  $response = callApi($master_reference, $plan_id, $email);
  echo $response;
}


?>
