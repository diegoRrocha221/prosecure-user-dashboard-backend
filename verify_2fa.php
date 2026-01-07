<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '/var/www/html/controllers/inc.sessions.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $username = $_SESSION['username'];
    $code = $_POST['code'];

    if (empty($username) || empty($code)) {
        $error = "Username or verification code is missing.";
    } else {
        $verify_result = verify_2fa($username, $code);
        if (isset($verify_result['status']) && $verify_result['status'] == 'authenticated') {
            $_SESSION['mfa_enabled'] = true;
            session_unset();
            session_destroy();
            header("Location: ./index.php?mfa=true");
            exit();
        } else {
            $error = isset($verify_result['message']) ? $verify_result['message'] : 'Invalid verification code.';
        }
    }
}

function verify_2fa($username, $code) {
    $curl = curl_init();

    $data = [
        'username' => $username,
        'code' => $code
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://mfa.prosecurelsp.com/verify_code",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        return ['status' => 'error', 'message' => "cURL error: $error"];
    }

    curl_close($curl);
    return json_decode($response, true) ?: ['status' => 'error', 'message' => 'Invalid response'];
}

function resend_code($username) {
    $curl = curl_init();

    $data = [
        'username' => $username
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://mfa.prosecurelsp.com/resend_code",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true) ?: ['status' => 'error', 'message' => 'Invalid response'];
}

if (isset($_POST['resend'])) {
    $resend_result = resend_code($_SESSION['username']);
    if ($resend_result['status'] === 'success') {
        $success = "A new code has been sent to your phone.";
    } else {
        $error = $resend_result['message'] ?? "Error resending code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify MFA Code - ProsecureLsp</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:300" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <style>
        @import url(https://fonts.googleapis.com/css?family=Roboto:300);
        .login-page { width: 460px; padding: 8% 0 0; margin: auto; }
        .form { position: relative; background: #FFFFFF; max-width: 460px; padding: 45px; text-align: center; box-shadow: 0 0 20px rgba(0, 0, 0, 0.2); }
        .form input, .form select { 
            font-family: "Roboto", sans-serif; 
            background: #f2f2f2; 
            width: 100%; 
            border: 0; 
            margin: 0 0 15px; 
            padding: 15px; 
            box-sizing: border-box; 
        }
        .phone-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .phone-group select {
            width: 120px;
        }
        .phone-group input {
            flex: 1;
            margin: 0;
        }
        .form button { 
            font-family: "Roboto", sans-serif; 
            text-transform: uppercase;
            background: #4CAF50; 
            width: 100%; 
            border: 0; 
            padding: 15px; 
            color: white; 
            font-size: 14px;
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .form button:hover { background: #43A047; }
        .error-message {
            color: #EF3B3A;
            margin-bottom: 15px;
        }
        body {
            background: #303e69;
            font-family: "Roboto", sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .logo-container {
            background-color: #2C3E50;
            padding: 10px;
            margin-bottom: 20px;
        }
        .form-title {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        .loading {
            display: none;
            margin: 10px 0;
        }
        .code-input {
            letter-spacing: 5px;
            font-size: 24px;
            text-align: center;
        }
        .resend-button {
            background: none;
            border: none;
            color: #666;
            text-decoration: none;
            cursor: pointer;
            margin-top: 10px;
            font-size: 12px;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }
        .resend-button:hover:not(:disabled) {
            color: #4CAF50;
            text-decoration: underline;
        }
        .resend-button:disabled {
            color: #999;
            cursor: not-allowed;
            text-decoration: none;
        }
        .success-message {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        .timer {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        .resend-container {
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
<div class="login-page">
    <div class="form">
        <div class="logo-container">
            <img id="logo" width="250px" height="80px" src="https://www.prosecurelsp.com/images/logo.png" alt="ProsecureLsp Logo">
        </div>
        
        <h2 class="form-title">Verify Authentication Code</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form id="verifyForm" action="" method="POST">
            <input type="text" name="code" id="code" class="code-input" placeholder="Enter Code" maxlength="6" required/>
            <div class="loading">Verifying...</div>
            <button type="submit">Verify Code</button>
        </form>

        <div class="resend-container">
            <div class="timer" id="timer">
                Wait <span id="countdown">30</span>s to resend
            </div>

            <form id="resendForm" action="" method="POST">
                <input type="hidden" name="resend" value="1">
                <button type="submit" id="resendButton" class="resend-button" disabled>Resend code</button>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let countdownInterval;
    let remainingTime = 30;

    $('#code').mask('000000');

    function startCountdown() {
        $('#resendButton').prop('disabled', true);
        $('#timer').show();
        remainingTime = 30;
        
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }

        countdownInterval = setInterval(function() {
            remainingTime--;
            $('#countdown').text(remainingTime);
            
            if (remainingTime <= 0) {
                clearInterval(countdownInterval);
                $('#resendButton').prop('disabled', false);
                $('#timer').hide();
            }
        }, 1000);
    }

    startCountdown();

    $('#verifyForm').on('submit', function(e) {
        $('.loading').show();
        $('button[type="submit"]').prop('disabled', true);
        
        setTimeout(function() {
            $('.loading').hide();
            $('button[type="submit"]').prop('disabled', false);
        }, 2000);
    });

    $('#resendForm').on('submit', function(e) {
        $('#resendButton').prop('disabled', true);
        startCountdown();
    });
});
</script>
</body>
</html>