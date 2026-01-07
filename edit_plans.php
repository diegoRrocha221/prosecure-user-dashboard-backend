<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();
include("database_connection.php");
require_once '/var/www/html/controllers/hashing_lsp.php';

function getPurchasedPlans(){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $username = $conn->real_escape_string($_SESSION['username']);
    $sql = "SELECT purchased_plans FROM master_accounts WHERE username = '$username'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $purchasedPlansJson = $row['purchased_plans'];
        return json_decode($purchasedPlansJson, true);
    }
    return null;    
}

function has_email_associated($email_json_node){
    return $email_json_node !== 'none';
}

function formatEndDate($endDate) {
    if (empty($endDate) || $endDate === 'none') {
        return 'Active';
    }
    
    $date = new DateTime($endDate);
    return $date->format('M j, Y');
}

function create_html_element($plans_json_unserialized){
    $html_element = '';
    foreach ($plans_json_unserialized as $index => $plan) {
        $email = has_email_associated($plan['email']);
        $isCanceled = isset($plan['cancel_at']);
        $endDate = isset($plan['end_date']) ? $plan['end_date'] : '';
        
        if($email === true && $plan['is_master'] == 0){
            $hash_plan_id = encrypt_lsp($plan['plan_id']);
            $hash_email = encrypt_lsp($plan['email']);
            
            $cancelButton = $isCanceled ? 
                '<span class="status-canceled">Canceled</span>' : 
                '<button class="button-error" data-psp='.$hash_plan_id.' data-psr='.$hash_email.'>Cancel</button>';
            
            $html_element .= '
                <tr class="' . ($isCanceled ? 'plan-canceled' : '') . '">
                    <td>'.$plan['plan_name'].'</td>
                    <td>'.$plan['email'].'</td>
                    <td class="end-date-cell">'.formatEndDate($endDate).'</td>
                    <td>'.$cancelButton.'</td>
                </tr>                
            ';
        }
        
        if($email === false && $plan['is_master'] == 0){
            $hash_plan_id = encrypt_lsp($plan['plan_id']);
            $hash_email = encrypt_lsp('null');
            
            $cancelButton = $isCanceled ? 
                '<span class="status-canceled">Canceled</span>' : 
                '<button class="button-error" data-psp='.$hash_plan_id.' data-psr='.$hash_email.'>Cancel</button>';
            
            $html_element .= '
                <tr class="' . ($isCanceled ? 'plan-canceled' : '') . '">
                    <td>'.$plan['plan_name'].'</td>
                    <td>Not associated</td>
                    <td class="end-date-cell">'.formatEndDate($endDate).'</td>
                    <td>'.$cancelButton.'</td>
                </tr>                
            ';
        }
    }
    return $html_element;
}

function get_plans(){
    $plans_json_unserialized = getPurchasedPlans();
    $accounts_partial = create_html_element($plans_json_unserialized);
    echo $accounts_partial;
}
?>