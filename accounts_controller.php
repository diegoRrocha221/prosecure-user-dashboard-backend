<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();
include("database_connection.php");
require "../../../../vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function sendMail($email, $username, $pass, $plan, $link){
    $code = generateUniqueRandomCode();
    $codetosend = encryptString($code, 'a');
    $user = encryptString($username, 'a');
    $password = encryptString($pass, 'a');
    $emailTosend = encryptString($email, 'a');
    $update = updateStatus($email,$username, $code);

    if($update === 1) {
        $welcome_email = ' <div style="text-align: center; background-color: #2C3E50"> ';
        $welcome_email .= '<img src="https://www.prosecurelsp.com/images/logo.png" style="padding-top: 50px"/></br>';
        $welcome_email .= '<h1 style="color:#fff">Welcome to your plan ' . $plan . '</h1> <br/>';
        $welcome_email .= '<h1 style="color:#fff">You need change your infos, please click on Accept Invite </h1> <br/>';
        $welcome_email .= '<a style="color:#fff; padding-bottom: 50px" href="https://prosecurelsp.com/users/newuser/update_infos.php?act=' . $link . '&pp='.$password.'&emt='.$emailTosend.'&cct='.$codetosend.'"><strong>Accept Invite</strong></a>';

        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = '172.31.255.82';
        $mail->SMTPAuth = false;
        $mail->Username = 'jcosta@prosecure.com';
        $mail->Port = 25;
        $mail->SMTPAutoTLS = false;
        $mail->setFrom('no-reply@prosecure.com', 'Welcome');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to your plan :)';
        $mail->Body = $welcome_email;

        if ($mail->Send()) {
            return "Success send mail";
        } else {
            return "Err";
        }
    }else{
        return  'create code';
    }

}
function getPlanByName($plan_name) {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();

    $query = "SELECT id FROM plans WHERE name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $plan_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $plan = $result->fetch_assoc();
        return $plan;
    }

    return null;
}
function getPurchasedPlans($reference){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $username = $conn->real_escape_string($_SESSION['username']);
    $sql = "SELECT purchased_plans FROM master_accounts WHERE reference_uuid = '$reference'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $purchasedPlansJson = $row['purchased_plans'];

        return $purchasedPlansJson;
    } else {
        return null;
    }
}

function encryptString($data, $key) {

    return base64_encode($data);
}
function deserializeJson($json){
    $deserilizedJson = json_decode($json, true);

    return $deserilizedJson;
}
function generateRandomPassword($length = 12) {
    // Caracteres permitidos na senha
    $characters = '0123456789abcdef';

    $password = '';

    for ($i = 0; $i < $length; $i++) {
        // Escolha um caractere aleatório da lista de caracteres
        $randomIndex = mt_rand(0, strlen($characters) - 1);
        $password .= $characters[$randomIndex];
    }

    return $password;
}

function generateUniqueRandomString($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $result = '';

    while (strlen($result) < $length) {
        $randomCharacter = $characters[rand(0, $charactersLength - 1)];
        $result .= $randomCharacter;
    }

    return $result;
}
function generateUniqueRandomCode($length = 8) {
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $result = '';

    while (strlen($result) < $length) {
        $randomCharacter = $characters[rand(0, $charactersLength - 1)];
        $result .= $randomCharacter;
    }

    return $result;
}

function updateStatus($email, $username, $code){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $updateSql = "UPDATE users SET  confirmation_code = '$code'  WHERE username = '$username' AND email = '$email'";
    if ($conn->query($updateSql)) {
        return 1;
    }else{
        return 0;
    }
}
function updatePlansNone($jsonDeserialized, $newEmail, $plan, $username){
    $newJsonPimba = '';

    foreach ($jsonDeserialized as &$item) {
        if ($item['plan_name'] === $plan && $item['is_master'] === 0 && $item['email'] === 'none') {
            $item['username'] = $username;
            $item['email'] = $newEmail;
        }
        }

        $newJson = json_encode($jsonDeserialized);
        return $newJson;
}
function updatePlans($jsonDeserialized, $newEmail, $oldEmail, $plan, $username){
    foreach ($jsonDeserialized as &$item) {
        if ($item['plan_name'] === $plan && $item['is_master'] === 0 && $item['email'] === $oldEmail) {
            $item['username'] = $username;
            $item['email'] = $newEmail;
        }
    }

     $newJson = json_encode($jsonDeserialized);
     return $newJson;
}

function sendAlert($username,$oldEmail,$reference,$newEmail){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $sql = "SELECT email from master_accounts WHERE reference_uuid = '$reference'";
    $result = $conn->query($sql);
    $masterEmail = $result->fetch_assoc();
    $welcome_email = ' <div style="text-align: center; background-color: #2C3E50"> ';
    $welcome_email .='<img src="https://www.prosecurelsp.com/images/logo.png" style="padding-top: 50px"/></br>';
    $welcome_email .= '<h1 style="color:#fff">Attention</h1> <br/>';
    $welcome_email .= '<h3 style="color:#fff">This account has been desactivated </h3> <br/>';
    $welcome_email .= '<h3 style="color:#fff">username: '. $username . '</h3><br/>';
    $welcome_email .= '<h3 style="color:#fff">email: '. $oldEmail . ' </h3><br/>';
    $welcome_email .= '<h3 style="color:#fff">Because you are registered this new account: '.$newEmail.'</h3> <br/>';


    $mail = new PHPMailer(true);
    $mail->SMTPDebug=0;
    $mail->isSMTP();
    $mail->Host = '172.31.255.82';
    $mail->SMTPAuth = false;
    $mail->Username = 'jcosta@prosecure.com';
    $mail->Port = 25;
    $mail->SMTPAutoTLS = false;
    $mail->setFrom('no-reply@prosecure.com', 'ProsecureLSP');
    $mail->addAddress($masterEmail);
    $mail->isHTML(true);
    $mail->Subject = 'Attention child account deactivated ';
    $mail->Body = $welcome_email;

    if ($mail->Send()) {
        return "Success send mail";
    } else {
        return "Err";
    }
}

function sendAlertToMaster($reference, $oldEmail, $newEmail){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $sql = "SELECT * FROM users WHERE master_reference = '$reference' AND email = '$oldEmail'";
    $result = $conn->query($sql);

    // Verifica se a consulta foi bem-sucedida
    if ($result === false) {
        echo "SQL err:  " . $conn->error;
        return; // ou outra ação apropriada em caso de erro
    }

    if ($result->num_rows > 0) {
        // Loop através dos resultados
        while ($row = $result->fetch_assoc()) {
            $userId = $row['id'];
            $userName = $row['username'];
            sendAlert($userName, $oldEmail, $reference,$newEmail);
        }
    } else {
        // Não foram encontrados resultados
        echo "Users not found";
    }
    $conn->close();

}
function desactiveOldEmail($oldEmail, $reference, $newEmail){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $sql = "UPDATE users SET is_active = 0 WHERE master_reference = '$reference' AND email = '$oldEmail'";
    if ($conn->query($sql) === TRUE) {
        return 'ok';
        $conn->close();
        sendAlertToMaster($reference,$oldEmail,$newEmail);
    }else{
        return  'err to update master account';
    }
}

function hash_pass($pass){
    $hashed_pass = hash('sha256'. $pass);
    return $hashed_pass;
}
function createNewUser($reference,$email, $username, $pass, $plan){
    if(!isset($pass)){ return 'ni';}


    $h = hash_pass($pass);
    $plan_id = getPlanByName($plan);
    $p = $plan_id['id'];
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $sql = "INSERT INTO child_users (email, username, password, master_reference, plan, created_at) 
                VALUES ('$email', '$username', '$pass', '$reference', '$p', NOW())";
    if ($conn->query($sql) === TRUE) {
        $sql = "INSERT INTO users (master_reference, username,email,passphrase, is_master, plan_id, is_active, created_at) 
                    VALUES ('$reference', '$username','$email', '$pass', 0, '$p',0, NOW())";
        if ($conn->query($sql) === TRUE) {
            return 'ok';
            $conn->close();
        }else{
            return  'err to create user' . $conn->error;
        }
    } else {
        return 'err to create child' . $conn->error . ' plan id '. $plan_id['id'];
    }

}

function updateMasterJson($json,$reference){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $sql = "UPDATE master_accounts SET purchased_plans = '$json' WHERE reference_uuid = '$reference'";
    if ($conn->query($sql) === TRUE) {
        return 'ok';
        $conn->close();
    }else{
        return  'err to update master account';
    }
}

function verifyDuplicateEntry($email, $plan, $json){
    foreach ($json as &$p){
        if ($p['plan_name'] === $plan && $p['email'] === $email){
            return 1;
        }else{
            return 0;
        }
    }
}

function verifyIfChildIsNotMaster($email){

        $db = new DatabaseConnection();
        $conn = $db->getConnection();

        $query = "SELECT email FROM master_accounts WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $plan = $result->fetch_assoc();
            return 1;
        }

        return null;

}
function verificaArroba($str) {
    if (strpos($str, '@') !== false) {
        return 0;
    } else {
        return 0;
    }
}
if(isset($_POST['action']) && isset($_POST['old_email']) && isset($_POST['plan']) && isset($_POST['input']) && $_POST['action'] == 'add_child_account'){
$old_email = $_POST['old_email'];
$checkold = verificaArroba($old_email);
$plan = $_POST['plan'];
$email_acct = $_POST['input'];
    $masterReference = $_SESSION['reference'];

$plans_purschased_serialized = getPurchasedPlans($masterReference);
$purschasedPlans = deserializeJson($plans_purschased_serialized);
$username = generateUniqueRandomString();
$pass = generateUniqueRandomString();

$check = verifyIfChildIsNotMaster($email_acct);
$checkDuplicate = verifyDuplicateEntry($email_acct,$plan,$purschasedPlans);
if($check !== 1) {
    if($checkDuplicate !== 1) {
        if ($checkold == 0) {
            $updatedJson = updatePlansNone($purschasedPlans, $email_acct, $plan, $username);
            if (!empty($updatedJson)) {
                $createUser = createNewUser($masterReference, $email_acct, $username, $pass, $plan);
                if ($createUser === 'ok') {
                    $updatedMaster = updateMasterJson($updatedJson, $masterReference);
                    if ($updatedMaster === 'ok') {
                        $link = encryptString($username, '23232ppp2lsp');
                        sendMail($email_acct, $username, $pass, $plan, $link);
                        echo "an invitation has been sent to the recipient's email address. don't worry, as soon as they accept your invitation we'll let you know by email";

                    } else {
                        echo 'err to update master ' . $updatedJson;
                    }
                } else {
                    echo $createUser;
                }
            } else {
                echo 'err to generate json ' . var_dump($plans_purschased_serialized) . ' ' . $masterReference;
            }
        } else {
            $updatedJson = updatePlans($purschasedPlans, $email_acct, $old_email, $plan, $username);
            if (!empty($updatedJson)) {
                $createUser = createNewUser($masterReference, $email_acct, $username, $pass, $plan);
                if ($createUser === 'ok') {
                    $desactive = desactiveOldEmail($old_email, $masterReference);
                    if ($desactive === 'ok') {
                        $updatedMaster = updateMasterJson($updatedJson, $masterReference);
                        if ($updatedMaster === 'ok') {
                            $link = encryptString($username, '23232ppp2lsp');
                            sendMail($email_acct, $username, $pass, $plan, $link);
                            echo "an invitation has been sent to the recipient's email address. don't worry, as soon as they accept your invitation we'll let you know by emai. the account belonging to this plan has been deactivatedl";
                        } else {
                            echo 'err to update master';
                        }
                    } else {
                        echo 'err to desactive old';
                    }

                } else {
                    echo $createUser . 'old a: ' . $old_email . 'email  a: ' . $email_acct . 'plan : ' . $plan;
                }
            } else {
                echo 'err to generate json';
            }
        }
    }else{
        echo 'this email already belongs to an account on the same plan';
    }
}else{
    echo "this email belongs to a master account, so it can't be linked as a child";
}
}else{
    echo 'you need fill input email';
}


?>
