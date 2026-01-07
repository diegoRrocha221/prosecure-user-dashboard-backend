<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();
include("database_connection.php");



function getPurchasedPlans(){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $username = $conn->real_escape_string($_SESSION['username']);
    $sql = "SELECT purchased_plans FROM master_accounts WHERE username = '$username'";
    $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $purchasedPlansJson = $row['purchased_plans'];

            return $purchasedPlansJson;
        } else {
            return null;
        }
}

function deserializeJson($json){
    $deserilizedJson = json_decode($json, true);

    return $deserilizedJson;
}
/*
function getAccounts(){
    $plansJson = getPurchasedPlans();
    if($plansJson){
        $plans = deserializeJson($plansJson);

        echo '<div class="accounts-container">';

        foreach ($plans as $index => $plan) {
            echo '<div class="account-details" style="flex-direction: row">';

            echo '<div class="account-field" style="flex-direction: row">';
            echo '<label>Email:</label>';
            echo '<input type="text" class="account-email" value="' . $plan['email'] . '">';
            echo '</div>';

            echo '<div class="account-field">';
            echo '<label>Username:</label>';
            echo '<input type="text" class="account-username" value="' . $plan['username'] . '">';
            echo '</div>';

            echo '<div class="account-field">';
            echo '<label>Select Plan:</label>';
            echo '<select class="plan-select" id="plan-select-' . $index . '">';

            $allPlans = ['Home', 'Business', 'Family','Kids'];
            if ($plan['is_master'] === 1){

            }
            foreach ($plans as $plan_opt) {
                $selected = ($allPlans == $plan_opt['plan_name']) ? 'selected' : '';
                echo '<option value="' . $plan_opt['plan_name'] . '" ' . $selected . '>' . $plan_opt['plan_name'] . '</option>';
            }

            echo '</select>';
            echo '</div>';

            echo '</div>'; // End of account-details
        }

        echo '</div>'; // End of accounts-container

        // Include JavaScript code for handling plan selection
        echo '<script src="./script.js"></script>';
    }
}
*/
function verifyEmailIsSet($email){
    if($email === 'none'){
        return 0;
    }else{
        return 1;
    }
}
function returnHome($plans){
    $element = "";
    foreach ($plans as $index => $plan) {
        if($plan['plan_name'] == "Home"){
            $email = verifyEmailIsSet($plan['email']);
            if ($plan['is_master'] === 0){
                if($email === 1){
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" value="'.$plan['email'].'" data-plan="'.$plan['plan_name'].'" data-index="'.$index.'" data-old = "'.$plan['email'].'"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$plan['email'].'">invite</button>';
                    $element .= '<button class="delete-btn" data-index="'.$index.'" data-plan="'.$plan['plan_name'].'" data-old = "'.$plan['email'].'" style="transform: translate(70px, 0px); color:#fff; background-color: red; border-radius: 4px">Delete</button>';
                    $element .= '</div>';
                }else{
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" placeholder="please insert an email" data-plan="'.$plan['plan_name'].'" data-index="'.$index.'" data-old = "none"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$plan['email'].'">invite</button>';
                    $element .= '</div>';
                }
            }else{
                $element .= '<div class="input-container">';
                $element .=  '<input style="" type="email" value="'.$plan['email'].'" data-plan="'.$plan['plan_name'].'" data-index="'.$index.'" disabled/><button class="invite-admin" disabled>Admin</button>';
                $element .= '</div>';
            }
        }
    }
    
    return $element;
}
function returnBusiness($plans){
    $element = "";
    foreach ($plans as $index => $plan) {
        if($plan['plan_name'] == "Businnes"){
            $email = verifyEmailIsSet($plan['email']);
            if ($plan['is_master'] === 0){
                if($email === 1){
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" value="'.$plan['email'].'" data-plan="'.$plan['plan_name'].'" data-index="'.$index.'" data-old = "'.$email.'"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$email.'">invite</button>';
                    $element .= '<button class="delete-btn" data-index="'.$index.'" data-plan="'.$plan['plan_name'].'" data-old = "'.$plan['email'].'" style="transform: translate(70px, 0px); color:#fff; background-color: red; border-radius: 4px">Delete</button>';
                    $element .= '</div>';
                }else{
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" placeholder="please insert an email" data-index="'.$index.'" data-plan="'.$plan['plan_name'].'" data-old = "none"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$email.'">invite</button>';
                    $element .= '</div>';
                }
            }else{
                $element .= '<div class="input-container">';
                $element .=  '<input style="color:red;" type="email" value="'.$plan['email'].'" data-plan="'.$plan['plan_name'].'" data-index="'.$index.'" disabled/>Master';
                $element .= '</div>';
            }
        }
    }
    return $element;
}

function returnFamily($plans){
    $element = "";
    foreach ($plans as $index => $plan) {
        if($plan['plan_name'] == "Family"){
            $email = verifyEmailIsSet($plan['email']);
            if ($plan['is_master'] === 0){
                if($email === 1){
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" value="'.$plan['email'].'" data-plan="'.$plan['plan_name'].'" data-index="'.$index.'" data-old = "'.$email.'"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$email.'">invite</button>';
                    $element .= '<button class="delete-btn" data-index="'.$index.'" data-plan="'.$plan['plan_name'].'" data-old = "'.$plan['email'].'" style="transform: translate(70px, 0px); color:#fff; background-color: red; border-radius: 4px">Delete</button>';
                    $element .= '</div>';
                }else{
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" placeholder="please insert an email" data-index="'.$index.'" data-plan="'.$plan['plan_name'].'" data-old = "none"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$email.'">invite</button>';
                    $element .= '</div>';
                }
            }else{
                $element .= '<div class="input-container">';
                $element .=  '<input style="color:red;" type="email" value="'.$plan['email'].'" data-plan="'.$plan['plan_name'].'" data-index="'.$index.'" disabled/>Master';
                $element .= '</div>';
            }
        }
    }
    return $element;
}

function returnKids($plans){
    $element = "";
    foreach ($plans as $index => $plan) {
        if($plan['plan_name'] == "Kids Plan"){
            $email = verifyEmailIsSet($plan['email']);
            if ($plan['is_master'] === 0){
                if($email === 1){
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" value="'.$plan['email'].'" data-index="'.$index.'" data-old = "'.$email.'"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$email.'">invite</button>';
                    $element .= '<button class="delete-btn" data-index="'.$index.'" data-plan="'.$plan['plan_name'].'" data-old = "'.$plan['email'].'" style="transform: translate(70px, 0px); color:#fff; background-color: red; border-radius: 4px">Delete</button>';
                    $element .= '</div>';
                }else{
                    $element .= '<div class="input-container">';
                    $element .=  '<input type="email" placeholder="please insert an email" data-index="'.$index.'" data-old = "none"/>';
                    $element .= '<button class="invite-btn" data-index="' . $index . '" data-plan="'.$plan['plan_name'].'" data-old = "'.$email.'">invite</button>';
                    $element .= '</div>';
                }
            }else{
                $element .= '<div class="input-container">';
                $element .=  '<input style="color:red;" type="email" value="'.$plan['email'].'" data-index="'.$index.'" disabled/>Master';
                $element .= '</div>';
            }
        }
    }
    return $element;
}

function getAccounts(){
    $plansJson = getPurchasedPlans();
    if($plansJson){
        $plans = deserializeJson($plansJson);
        $home = 0;
        $business = 0;
        $family = 0;
        $kids = 0;

        foreach ($plans as $plan){
            if ($plan['plan_name'] == 'Home'){
                $home = 1;
            }
            if ($plan['plan_name'] == 'Businnes'){
                $business = 1;
            }
            if ($plan['plan_name'] == 'Family'){
                $family = 1;
            }
            if ($plan['plan_name'] == 'Kids Plan'){
                $kids = 1;
            }
        }

        $element = '<div class="accounts-container">';
        if ($home === 1){
            $element .= '<div id="home">';
            $element .= '<div class="transfer-section-header" style="margin-top: 30px;transform: translateY(30%)">';
            $element .= '<h3 style="#fff">Home</h3>';
            $element .= '</div>';
            $element .= returnHome($plans);
            $element .= '</div>';


        }
        if ($business === 1){
            $element .= '<div id="home">';
            $element .= '<div class="transfer-section-header" style="margin-top: 30px;transform: translateY(30%)">';
            $element .= '<h3 style="#fff" style="font-size: 50px">Business</h3>';
            $element .= '</div>';
            $element .= returnBusiness($plans);
            $element .= '</div>';


        }
        if($family === 1){
            $element .= '<div id="home">';
            $element .= '<div class="transfer-section-header" style="margin-top: 30px;transform: translateY(30%)">';
            $element .= '<h3 style="#fff">Family</h3>';
            $element .= '</div>';
            $element .= returnFamily($plans);
            $element .= '</div>';


        }
        if($kids === 1){
            $element .= '<div id="home">';
            $element .= '<div class="transfer-section-header" style="margin-top: 30px;transform: translateY(30%)">';
            $element .= '<h3 style="#fff">Kids</h3>';
            $element .= '</div>';
            $element .= returnKids($plans);
            $element .= '</div>';


        }
        $element .= '</div>';

        echo $element;
    }
}










?>
