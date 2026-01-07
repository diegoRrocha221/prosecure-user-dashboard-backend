<?php
require "../../../../vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$email = $_GET['email'];
$muid = $_GET['muid'];
$inuid = $_GET['inuid'];


    $welcome_email = ' <div style="text-align: center;"> ';
    $welcome_email .= '<h1>Your invoice https://prosecurelsp.com/users/dashboard/pages/partials/_invoice.php?muid='.$muid.'&inuid='.$inuid.'</h1> <br/>';
    $welcome_email .= '<h3>Thank you</h3><br/>';


    $mail = new PHPMailer(true);
    $mail->SMTPDebug=2;
    $mail->isSMTP();
    $mail->Host = '172.31.255.82';
    $mail->SMTPAuth = false;
    $mail->Username = 'jcosta@prosecure.com';
    $mail->Port = 25;
    $mail->SMTPAutoTLS = false;
    $mail->setFrom('no-reply@prosecure.com', 'ProsecureLSP');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your invoice :)';
    $mail->Body = $welcome_email;

    if ($mail->Send()) {
        header('Location: ../invoices.php?scs=1');
    } else {
        header('Location: ../invoices.php?err=1');

    }


?>