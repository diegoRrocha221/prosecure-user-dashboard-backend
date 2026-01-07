<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function sendMail($email, $username, $pass, $plan){
    $welcome_email = ' <div style="text-align: center;"> ';
    $welcome_email .= '<h1>Welcome to your plan '. $plan . '</h1> <br/>';
    $welcome_email .= '<h3>Your username:'. $username . '</h3><br/>';
    $welcome_email .= '<h3>Your temporary password:'. $pass . ' </h3><br/>';
    $welcome_email .= '<a href="https://prosecurelsp.com/users"><strong>Login here</strong></a>';

    $mail = new PHPMailer(true);
    $mail->SMTPDebug=2;
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

}

?>