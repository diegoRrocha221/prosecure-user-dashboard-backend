<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('./database_connection.php');
require "../../vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function verifyInfos($email, $username){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $sql = "SELECT email from users WHERE username = '$username' AND email = '$email'";
    $result = $conn->query($sql);
    $email = $result->fetch_assoc();
    if(isset($email)){
        return 1;
    }else{return 0;}
    $conn->close();
}

function validate_code($code, $email, $username){
    $db = new DatabaseConnection();
    $conn = $db->getConnection();

    if (!$conn) {
        die("Falha na conexão com o banco de dados: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT confirmation_code FROM users WHERE username = ? AND email = ? AND confirmation_code = ?");
    $stmt->bind_param("sss", $username, $email, $code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return 1;
    } else {
        $stmt->close();
        $conn->close();
        die("Erro na execução da consulta: " . $stmt->error . $username . $email . $code);
        return 0;
    }
}

function updateInfo( $email, $username) {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();

    if (!$conn) {
        die("Falha na conexão com o banco de dados: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("UPDATE users SET confirmation_code = NULL,  email_confirmed = 1, is_active = 1, confirmed_at = NOW() WHERE username = ? AND email = ?");

    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }

    $stmt->bind_param("ss", $username, $email);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return 1;
    }else{
        die("Erro na execução da consulta: " . $stmt->error);
    }

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


function decryptString($data, $key) {
    $datad = base64_decode($data);
    return $datad;
}

if(isset($_POST['emp']) && isset($_POST['act']) && isset($_POST['cct'])){
    $stmt_user = $_POST['act']; $stmt_email = $_POST['emp']; $stmt_code = $_POST['cct'];
    $username = decryptString($stmt_user, '23232ppp2lsp');
    $email = decryptString($stmt_email, '23232ppp2lsp');
    $code = decryptString($stmt_code, '23232ppp2lsp');
    $validate = verifyInfos($email,$username);

    if($validate === 1){
        $verify_code = validate_code($code, $email, $username);
        if($verify_code === 1){
            $update = updateInfo($email, $username);
            if($update === 1) {
                header('Location: ./update_infos.php?acct=' . $stmt_user);
            }else{
                header('Location: ./update_infos.php?err=199&act=' . $stmt_user.'&emp='.$stmt_email.'&cct='.$stmt_code);
            }
        }else{
            header('Location: ./activation.php?erf3=78');
        }
    }else{
        header('Location: ./activation.php?erf2=78');
    }


}
?>