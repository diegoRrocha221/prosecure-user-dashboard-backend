<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start(); 
require_once '/var/www/html/controllers/database_connection.php';

function calculateExecutionDate($masterReference, $conn) {
    // Get invoice due_date - Try regular invoices if trial not found
    $invoiceQuery = $conn->prepare("SELECT due_date, created_at FROM invoices WHERE master_reference = ? ORDER BY created_at DESC LIMIT 1");
    if (!$invoiceQuery) {
        error_log("Failed to prepare invoice query: " . $conn->error);
        return null;
    }
    
    $invoiceQuery->bind_param("s", $masterReference);
    if (!$invoiceQuery->execute()) {
        error_log("Failed to execute invoice query: " . $conn->error);
        $invoiceQuery->close();
        return null;
    }
    
    $invoiceResult = $invoiceQuery->get_result();
    
    if ($invoiceResult->num_rows === 0) {
        $invoiceQuery->close();
        // Return next month as default
        $nextMonth = new DateTime();
        $nextMonth->add(new DateInterval('P1M'));
        return $nextMonth->format('Y-m-d H:i:s');
    }
    
    $invoiceData = $invoiceResult->fetch_assoc();
    $dueDate = new DateTime($invoiceData['due_date']);
    $createdAt = new DateTime($invoiceData['created_at']);
    $invoiceQuery->close();
    
    // Get billing cycle info
    $accountQuery = $conn->prepare("SELECT is_annually FROM master_accounts WHERE reference_uuid = ?");
    if (!$accountQuery) {
        error_log("Failed to prepare account query: " . $conn->error);
        return null;
    }
    
    $accountQuery->bind_param("s", $masterReference);
    if (!$accountQuery->execute()) {
        error_log("Failed to execute account query: " . $conn->error);
        $accountQuery->close();
        return null;
    }
    
    $accountResult = $accountQuery->get_result();
    
    if ($accountResult->num_rows === 0) {
        $accountQuery->close();
        // Default to monthly billing
        $executionDate = new DateTime();
        $executionDate->add(new DateInterval('P1M'));
        return $executionDate->format('Y-m-d H:i:s');
    }
    
    $accountData = $accountResult->fetch_assoc();
    $isAnnually = $accountData['is_annually'];
    $accountQuery->close();
    
    $currentDate = new DateTime();
    $executionDate = new DateTime();
    
    if ($isAnnually == 0) {
        // Monthly billing - next billing cycle
        $nextMonth = intval($currentDate->format('n')) + 1;
        $year = intval($currentDate->format('Y'));
        
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $year += 1;
        }
        
        $executionDate->setDate($year, $nextMonth, $dueDate->format('j'));
        
        // Make sure we don't go to an invalid date (like Feb 30)
        $lastDayOfMonth = intval($executionDate->format('t'));
        $desiredDay = intval($dueDate->format('j'));
        
        if ($desiredDay > $lastDayOfMonth) {
            $executionDate->setDate($year, $nextMonth, $lastDayOfMonth);
        }
    } else {
        // Annual billing - next year
        $executionDate = clone $createdAt;
        $executionDate->add(new DateInterval('P1Y'));
    }
    
    return $executionDate->format('Y-m-d H:i:s');
}

function getSubscriptionId($masterReference, $conn) {
    $query = $conn->prepare("SELECT subscription_id FROM subscriptions WHERE master_reference = ? LIMIT 1");
    if (!$query) {
        error_log("Failed to prepare subscription query: " . $conn->error);
        return null;
    }
    
    $query->bind_param("s", $masterReference);
    if (!$query->execute()) {
        error_log("Failed to execute subscription query: " . $conn->error);
        $query->close();
        return null;
    }
    
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $query->close();
        return $row['subscription_id'];
    }
    
    $query->close();
    return "SUB_" . $masterReference; // Generate a default subscription ID if not found
}

function getMasterChilds($conn, $masterReference) {
    $query = $conn->prepare("SELECT email, plan_id FROM users WHERE master_reference = ?");
    if (!$query) {
        error_log("Failed to prepare master childs query: " . $conn->error);
        return null;
    }
    
    $query->bind_param("s", $masterReference);
    if (!$query->execute()) {
        error_log("Failed to execute master childs query: " . $conn->error);
        $query->close();
        return null;
    }
    
    $result = $query->get_result();
    $childs = array();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $childs[] = array(
                'email' => $row['email'],
                'plan_id' => $row['plan_id']
            );
        }
    }
    
    $query->close();
    return $childs;
}

function insertCancelLog($conn, $masterUsername, $masterEmail, $childs) {
    try {
        $sql = "INSERT INTO cancel_logs(master_username, master_email, master_childs) VALUES(?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing log query: " . $conn->error);
        }
        
        $childsJson = json_encode($childs);
        $stmt->bind_param("sss", $masterUsername, $masterEmail, $childsJson);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            throw new Exception("Error executing log query: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Error in insertCancelLog: " . $e->getMessage());
        return false;
    }
}

function updateMasterAccountCancellation($masterReference, $conn) {
    $query = $conn->prepare("UPDATE master_accounts SET has_cancellation = 1 WHERE reference_uuid = ?");
    if (!$query) {
        error_log("Failed to prepare master account update query: " . $conn->error);
        return false;
    }
    
    $query->bind_param("s", $masterReference);
    $success = $query->execute();
    $query->close();
    
    return $success;
}

// Main processing logic
if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
    // Set error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display errors to user, but log them
    
    try {
        $db = new DatabaseConnection();
        $conn = $db->getConnection();
        
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $conn->begin_transaction();
        
        $masterReference = $_SESSION['reference'] ?? null;
        $masterEmail = $_SESSION['email'] ?? null;
        $masterUsername = $_SESSION['username'] ?? null;
        
        if (!$masterReference || !$masterEmail || !$masterUsername) {
            throw new Exception("Session data not found");
        }
        
        $submissionTime = date('Y-m-d H:i:s');
        
        // Get subscription ID
        $subscriptionId = getSubscriptionId($masterReference, $conn);
        if (!$subscriptionId) {
            throw new Exception("Subscription not found");
        }
        
        // Calculate execution date
        $executionDate = calculateExecutionDate($masterReference, $conn);
        if (!$executionDate) {
            throw new Exception("Could not calculate execution date");
        }
        
        // Get master childs for logging
        $childs = getMasterChilds($conn, $masterReference);
        if ($childs === null) {
            throw new Exception("Could not retrieve account information");
        }
        
        // Create account details for cancellation
        $accountDetails = [
            'master_email' => $masterEmail,
            'master_username' => $masterUsername,
            'total_accounts' => count($childs),
            'cancellation_reason' => 'User requested account cancellation'
        ];
        $accountDetailsJson = json_encode($accountDetails);
        
        // Insert into cancellations table - Note: column name is plan_detail (singular)
        $insertQuery = $conn->prepare("INSERT INTO cancellations (master_reference, subscription_id, operation, plan_id, plan_detail, submission_time, execution_date, process_at) VALUES (?, ?, 'all', 0, ?, ?, ?, NULL)");
        if (!$insertQuery) {
            throw new Exception("Failed to prepare cancellation insert query: " . $conn->error);
        }
        
        $insertQuery->bind_param("sssss", $masterReference, $subscriptionId, $accountDetailsJson, $submissionTime, $executionDate);
        
        if (!$insertQuery->execute()) {
            throw new Exception("Failed to insert cancellation record: " . $insertQuery->error);
        }
        $insertQuery->close();
        
        // Insert into cancel_logs for historical tracking
        if (!insertCancelLog($conn, $masterUsername, $masterEmail, $childs)) {
            throw new Exception("Failed to create cancellation log");
        }
        
        // Update master_accounts to mark as having cancellation
        if (!updateMasterAccountCancellation($masterReference, $conn)) {
            throw new Exception("Failed to update master account cancellation status");
        }
        
        $conn->commit();
        
        // Format execution date for response
        $executionDateTime = new DateTime($executionDate);
        $formattedDate = $executionDateTime->format('F j, Y');
        
        echo "Account cancellation scheduled successfully! You can continue using your service until " . $formattedDate . ". Your account will be deactivated on your next billing cycle.";
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log("Account cancellation error: " . $e->getMessage());
        echo "Service unavailable, please try again later.";
    }
} else {
    echo "Invalid request.";
}
?>