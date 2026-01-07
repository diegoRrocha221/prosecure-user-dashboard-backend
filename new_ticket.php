<?php
require_once('/var/www/html/controllers/inc.sessions.php'); 
session_start();
require_once('./database_connection.php');
require "../../../../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function validate_recaptcha($recaptcha_token) {
    $recaptcha_secret = '6LfbZUgqAAAAAMffJkv2vu94TelprDGyA8C3dsYx';
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_data = [
        'secret' => $recaptcha_secret,
        'response' => $recaptcha_token
    ];
    $recaptcha_options = [
        'http' => [
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'method' => 'POST',
            'content' => http_build_query($recaptcha_data)
        ]
    ];
    $recaptcha_context = stream_context_create($recaptcha_options);
    $recaptcha_result = file_get_contents($recaptcha_url, false, $recaptcha_context);
    $recaptcha_result_data = json_decode($recaptcha_result, true);

    return isset($recaptcha_result_data['success']) && $recaptcha_result_data['success'] && $recaptcha_result_data['score'] >= 0.5;
}

function send_notification_email($email, $ticket_number) {
    $mail = new PHPMailer(true);
    try {

        $mail->isSMTP();
        $mail->Host = '172.31.255.82';
        $mail->SMTPAuth = false;
        $mail->Username = 'jcosta@prosecure.com';
        $mail->Port = 25;
        $mail->SMTPAutoTLS = false;

        $mail->setFrom('support@prosecurelsp.com', 'ProsecureLsp.com');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Support Ticket has been created';
        $mail->Body = "Support Ticket created,<br><br>protocol number: <strong>$ticket_number</strong>.<br><br>Regards,<br>ProsecureLsp.com Support Team";


        $mail->send();
    } catch (Exception $e) {

        echo "Err: {$mail->ErrorInfo}";
    }
}

if (isset($_POST['title']) && isset($_POST['message']) && isset($_POST['recaptcha_token'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $recaptcha_token = $_POST['recaptcha_token'];
    $reference_uuid = $_SESSION['reference'];
    $email = $_SESSION['email'];

    if (!validate_recaptcha($recaptcha_token)) {
        echo "Captcha validation failed.";
        exit();
    }

    $ticket_number = random_int(100000, 999999);

    $messages = [
        ["user" => "customer", "message" => $message],
        ["user" => "support", "message" => ""]
    ];
    $message_json = json_encode($messages);

    $db = new DatabaseConnection();
    $conn = $db->getConnection();

    $query = "INSERT INTO customer_tickets (reference_uuid,  title, message, status, ticket_number,created_at,  updated_at) VALUES (?, ?, ?, 0, ?, NOW(), NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $reference_uuid, $title, $message_json , $ticket_number);

    if ($stmt->execute()) {
   
        send_notification_email($email, $ticket_number);
        echo "Ticket created successfully!";
    } else {
        echo "Error creating the ticket. Please try again.";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Please fill in all fields.";
}
?>
