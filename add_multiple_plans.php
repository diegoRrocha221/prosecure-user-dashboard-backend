<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();
include("database_connection.php");

$db = new DatabaseConnection();
$conn = $db->getConnection();

// Start the transaction
$conn->begin_transaction();

$user_reference = $_SESSION['reference'];
$cart = $_POST['cart'];

// Check if the cart data is passed and properly formatted
if (empty($cart) || !is_array($cart)) {
    error_log("Invalid cart data passed.");
    echo json_encode(["status" => "error", "message" => "Invalid cart data."]);
    $conn->rollback();
    exit();
}

// Fetch the current purchased plans from master_accounts (including is_annually)
$query = $conn->prepare("SELECT purchased_plans FROM master_accounts WHERE reference_uuid = ?");
if (!$query) {
    error_log("Failed to prepare query: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Failed to prepare query."]);
    $conn->rollback();
    exit();
}
$query->bind_param("s", $user_reference);
$query->execute();
$query->bind_result($purchasedPlans);
$query->fetch();
$query->close();

// Decode the purchased_plans JSON column
$purchasedPlansArray = json_decode($purchasedPlans, true) ?: [];

$monthlyTotal = 0;
$proRataTotal = 0;
$simultaneousUsers = 0;

// Iterate through each item in the cart
foreach ($cart as $plan) {
    // Cast `plan_id` and `quantity` to integers
    $planId = isset($plan['plan_id']) ? (int)$plan['plan_id'] : null;
    $planQuantity = isset($plan['quantity']) ? (int)$plan['quantity'] : null;

    // Validate the casted values
    if ($planId === null || $planQuantity === null || $planQuantity <= 0) {
        error_log("Invalid plan data received: " . json_encode($plan));
        echo json_encode(["status" => "error", "message" => "Invalid plan data."]);
        $conn->rollback();
        exit();
    }

    // Fetch plan details from the database
    $planQuery = $conn->prepare("SELECT name, price FROM plans WHERE id = ?");
    if (!$planQuery) {
        error_log("Failed to prepare plan query: " . $conn->error);
        echo json_encode(["status" => "error", "message" => "Failed to prepare plan query."]);
        $conn->rollback();
        exit();
    }
    $planQuery->bind_param("i", $planId);
    $planQuery->execute();
    $planQuery->bind_result($planName, $planPrice);
    $planQuery->fetch();
    $planQuery->close();

    if (empty($planName)) {
        error_log("Plan not found for plan_id: " . $planId);
        echo json_encode(["status" => "error", "message" => "Plan not found."]);
        $conn->rollback();
        exit();
    }

    // Get is_annually from the `cart` data (passed from frontend or existing plans)
    $isAnnually = isset($plan['annually']) ? (int)$plan['annually'] : 0;

    // Calculate pro-rata if the plan is annual
    $proRata = 0;
    if ($isAnnually) {
        $currentDate = new DateTime();
        $daysInYear = 365;
        $dayOfYear = (int)$currentDate->format('z');
        $remainingDays = $daysInYear - $dayOfYear;
        $proRata = ($planPrice / 12) * ($remainingDays / $daysInYear);
    }

    // Add the new plans to the purchasedPlansArray
    for ($i = 0; $i < $planQuantity; $i++) {
        $newPlanEntry = [
            'plan_id' => $planId,
            'username' => 'none',
            'email' => 'none',
            'is_master' => 0,
            'plan_name' => $planName,
            'annually' => $isAnnually // Get the annually flag from the frontend or default to 0
        ];

        $purchasedPlansArray[] = $newPlanEntry;
        $simultaneousUsers++;
    }

    if ($isAnnually) {
        $proRataTotal += $proRata * $planQuantity;
    } else {
        $monthlyTotal += $planPrice * $planQuantity;
    }
}

// Calculate the grand total
$grandTotal = $monthlyTotal + $proRataTotal;

// Update the purchased plans in the master_accounts table
$updatedPurchasedPlans = json_encode($purchasedPlansArray);
$updateQuery = $conn->prepare("UPDATE master_accounts SET purchased_plans = ?, simultaneus_users = simultaneus_users + ? WHERE reference_uuid = ?");
if (!$updateQuery) {
    error_log("Failed to prepare update query: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Failed to prepare update query."]);
    $conn->rollback();
    exit();
}
$updateQuery->bind_param("sis", $updatedPurchasedPlans, $simultaneousUsers, $user_reference);
$updateQuery->execute();
$updateQuery->close();

$insertInvoice = $conn->prepare("INSERT INTO invoices (master_reference, total, due_date, is_paid, created_at) VALUES (?, ?, NOW() + INTERVAL 1 MONTH, 0, NOW())");
if (!$insertInvoice) {
    error_log("Failed to prepare invoice query: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Failed to prepare invoice query."]);
    $conn->rollback();
    exit();
}
$insertInvoice->bind_param("sd", $user_reference, $grandTotal);
$insertInvoice->execute();
$insertInvoice->close();


if ($conn->commit()) {
    echo json_encode(["status" => "success", "message" => "Plans added successfully."]);
} else {
    error_log("Failed to commit transaction.");
    echo json_encode(["status" => "error", "message" => "Failed to commit transaction."]);
    $conn->rollback();
}

?>
