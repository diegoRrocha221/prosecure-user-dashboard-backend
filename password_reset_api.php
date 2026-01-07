<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendJSONResponse($data, $httpCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($httpCode);
    echo json_encode($data);
    exit();
}

function logError($message) {
    error_log("Password Reset API: " . $message);
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logError("Fatal error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        sendJSONResponse([
            'success' => false,
            'message' => 'Server configuration error'
        ], 500);
    }
});

try {
    $requiredFiles = [
        '/var/www/html/controllers/inc.sessions.php',
        'database_connection.php',
        'email_templates.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            logError("Required file not found: $file");
            sendJSONResponse([
                'success' => false,
                'message' => 'Server configuration error'
            ], 500);
        }
    }
    
    require_once '/var/www/html/controllers/inc.sessions.php';
    require_once 'database_connection.php';
    require_once 'email_templates.php';
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $autoloadPath = '../../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        logError("Composer autoload not found");
        sendJSONResponse([
            'success' => false,
            'message' => 'Email service unavailable'
        ], 500);
    }
    
    require $autoloadPath;

    $MFA_API_BASE_URL = 'http://localhost:7080';
    
    function callMFAAPI($endpoint, $data = null) {
        global $MFA_API_BASE_URL;
        
        $url = $MFA_API_BASE_URL . $endpoint;
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($response === false) {
            logError("MFA API curl error: $curlError");
            return ['error' => true, 'message' => 'MFA service unavailable'];
        }
        
        $decodedResponse = json_decode($response, true);
        
        return [
            'error' => $httpCode >= 400,
            'http_code' => $httpCode,
            'data' => $decodedResponse
        ];
    }
    
    function generateSecureCode($length = 6) {
        $characters = '0123456789';
        $code = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }
        
        return $code;
    }
    
    function checkCooldown($sessionKey, $cooldownSeconds) {
        if (isset($_SESSION[$sessionKey])) {
            $elapsed = time() - $_SESSION[$sessionKey];
            if ($elapsed < $cooldownSeconds) {
                return [
                    'allowed' => false,
                    'remaining' => $cooldownSeconds - $elapsed
                ];
            }
        }
        return ['allowed' => true];
    }
    
    $action = $_GET['action'] ?? '';
    
    if (empty($action)) {
        sendJSONResponse(['success' => false, 'message' => 'No action specified'], 400);
    }
    
    function getClientIP() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    function getLocationFromIP($ip) {
        if ($ip === 'Unknown' || 
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return [
                'city' => 'Local Network',
                'region' => '',
                'country' => '',
                'timezone' => ''
            ];
        }
        
        $apis = [
            [
                'url' => "https://ipapi.co/{$ip}/json/",
                'parser' => function($data) {
                    if (!empty($data['error'])) {
                        return null;
                    }
                    return [
                        'city' => $data['city'] ?? '',
                        'region' => $data['region'] ?? '',
                        'country' => $data['country_name'] ?? '',
                        'timezone' => $data['timezone'] ?? ''
                    ];
                }
            ],
            [
                'url' => "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,timezone",
                'parser' => function($data) {
                    if ($data['status'] !== 'success') {
                        return null;
                    }
                    return [
                        'city' => $data['city'] ?? '',
                        'region' => $data['regionName'] ?? '',
                        'country' => $data['country'] ?? '',
                        'timezone' => $data['timezone'] ?? ''
                    ];
                }
            ]
        ];
        
        foreach ($apis as $api) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $api['url']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'ProSecureLSP/1.0');
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    
                    if ($data) {
                        $result = $api['parser']($data);
                        
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return [
            'city' => 'Unknown',
            'region' => '',
            'country' => '',
            'timezone' => ''
        ];
    }
    
    function parseUserAgent($userAgent) {
        $device = 'Unknown Device';
        $browser = 'Unknown Browser';
        
        if (preg_match('/Windows NT 10/i', $userAgent)) {
            $device = 'Windows 10';
        } elseif (preg_match('/Windows NT 11/i', $userAgent)) {
            $device = 'Windows 11';
        } elseif (preg_match('/Windows/i', $userAgent)) {
            $device = 'Windows';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
            $device = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $device = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $device = 'Android';
        } elseif (preg_match('/iPhone/i', $userAgent)) {
            $device = 'iPhone';
        } elseif (preg_match('/iPad/i', $userAgent)) {
            $device = 'iPad';
        }
        
        if (preg_match('/Edg/i', $userAgent)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = 'Internet Explorer';
        }
        
        return [
            'device' => $device,
            'browser' => $browser
        ];
    }
    
    function sendPasswordChangedNotification($email, $username) {
        try {
            $ip = getClientIP();
            $location = getLocationFromIP($ip);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $agentInfo = parseUserAgent($userAgent);
            $timestamp = date('F j, Y \a\t g:i A T');
            
            $emailBody = password_changed_notification_template(
                $email,
                $username,
                $ip,
                $location,
                $agentInfo['device'],
                $agentInfo['browser'],
                $timestamp
            );
            
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = '172.31.255.82';
            $mail->SMTPAuth = false;
            $mail->Port = 25;
            $mail->SMTPAutoTLS = false;
            $mail->setFrom('no-reply@prosecure.com', 'ProSecureLSP Security');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Security Alert: Password Changed';
            $mail->Body = $emailBody;
            
            return $mail->send();
        } catch (Exception $e) {
            logError("Error sending password change notification: " . $e->getMessage());
            return false;
        }
    }
    
    switch ($action) {
        case 'request_reset':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendJSONResponse(['success' => false, 'message' => 'Invalid request format'], 400);
            }
            
            $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
            
            if (!$email) {
                sendJSONResponse(['success' => false, 'message' => 'Invalid email address'], 400);
            }
            
            try {
                $db = new DatabaseConnection();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("SELECT username, email, is_master, master_reference FROM users WHERE email = ? AND is_active = 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendJSONResponse([
                        'success' => true,
                        'message' => 'If an account exists with this email, you will receive an SMS code'
                    ]);
                }
                
                $user = $result->fetch_assoc();
                $username = $user['username'];
                $isMaster = $user['is_master'] == 1;
                $masterReference = $user['master_reference'];
                
                $targetMasterUsername = null;
                $targetPhone = null;
                $masterEmail = null;
                
                if ($isMaster) {
                    $stmt = $conn->prepare("SELECT username, phone_number, email FROM master_accounts WHERE username = ?");
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $masterResult = $stmt->get_result();
                    
                    if ($masterResult->num_rows === 0) {
                        sendJSONResponse(['success' => false, 'message' => 'Master phone number not found'], 400);
                    }
                    
                    $masterData = $masterResult->fetch_assoc();
                    $targetMasterUsername = $masterData['username'];
                    $targetPhone = $masterData['phone_number'];
                    $masterEmail = $masterData['email'];
                } else {
                    if (empty($masterReference)) {
                        sendJSONResponse(['success' => false, 'message' => 'No master account associated'], 400);
                    }
                    
                    $stmt = $conn->prepare("SELECT username, phone_number, email FROM master_accounts WHERE reference_uuid = ?");
                    $stmt->bind_param('s', $masterReference);
                    $stmt->execute();
                    $masterResult = $stmt->get_result();
                    
                    if ($masterResult->num_rows === 0) {
                        sendJSONResponse(['success' => false, 'message' => 'Master phone number not found'], 400);
                    }
                    
                    $masterData = $masterResult->fetch_assoc();
                    $targetMasterUsername = $masterData['username'];
                    $targetPhone = $masterData['phone_number'];
                    $masterEmail = $masterData['email'];
                }
                
                if (empty($targetPhone)) {
                    sendJSONResponse(['success' => false, 'message' => 'Phone number not configured'], 400);
                }
                
                $smsCode = generateSecureCode(6);
                $codeExpiry = date('Y-m-d H:i:s', time() + 300);
                
                logError("Generated SMS code for $targetMasterUsername (user: $username): $smsCode (expires: $codeExpiry)");
                
                $stmt = $conn->prepare("UPDATE master_accounts SET code = ?, code_expires = ? WHERE username = ?");
                $stmt->bind_param('sss', $smsCode, $codeExpiry, $targetMasterUsername);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to save SMS code");
                }
                
                $mfaResponse = callMFAAPI('/send_sms', [
                    'phone' => $targetPhone,
                    'code' => $smsCode
                ]);
                
                if ($mfaResponse['error']) {
                    $mfaResponse = callMFAAPI('/resend_code', [
                        'username' => $targetMasterUsername,
                        'phone' => $targetPhone
                    ]);
                    
                    if ($mfaResponse['error']) {
                        logError("SMS send failed but code saved: $smsCode");
                    }
                }
                
                $_SESSION['password_reset_email'] = $email;
                $_SESSION['password_reset_username'] = $username;
                $_SESSION['password_reset_master_username'] = $targetMasterUsername;
                $_SESSION['password_reset_master_email'] = $masterEmail;
                $_SESSION['password_reset_is_master'] = $isMaster;
                $_SESSION['last_sms_send'] = time();
                unset($_SESSION['password_reset_verified']);
                
                logError("SMS sent successfully for: $email (master: $targetMasterUsername)");
                
                sendJSONResponse([
                    'success' => true,
                    'message' => 'SMS code sent to registered phone'
                ]);
                
            } catch (Exception $e) {
                logError("Exception in request_reset: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Failed to process request'], 500);
            }
            break;
            
        case 'verify_sms_code':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (!isset($_SESSION['password_reset_email'])) {
                sendJSONResponse(['success' => false, 'message' => 'No active reset session'], 401);
            }
            
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendJSONResponse(['success' => false, 'message' => 'Invalid request format'], 400);
            }
            
            $code = $input['code'] ?? '';
            
            if (strlen($code) !== 6 || !ctype_digit($code)) {
                sendJSONResponse(['success' => false, 'message' => 'Invalid code format'], 400);
            }
            
            try {
                $masterUsername = $_SESSION['password_reset_master_username'];
                
                $db = new DatabaseConnection();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("SELECT code, code_expires FROM master_accounts WHERE username = ?");
                $stmt->bind_param('s', $masterUsername);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendJSONResponse(['success' => false, 'message' => 'Account not found'], 400);
                }
                
                $mfaData = $result->fetch_assoc();
                $savedCode = $mfaData['code'];
                $codeExpiry = $mfaData['code_expires'];
                
                logError("Verifying SMS code for $masterUsername: submitted=$code, saved=$savedCode");
                
                if (!empty($codeExpiry) && strtotime($codeExpiry) < time()) {
                    logError("SMS code expired for $masterUsername");
                    sendJSONResponse(['success' => false, 'message' => 'SMS code has expired'], 400);
                }
                
                if ($savedCode !== $code) {
                    logError("SMS code mismatch for $masterUsername");
                    sendJSONResponse(['success' => false, 'message' => 'Invalid SMS code'], 400);
                }
                
                $stmt = $conn->prepare("UPDATE master_accounts SET code = NULL, code_expires = NULL WHERE username = ?");
                $stmt->bind_param('s', $masterUsername);
                $stmt->execute();
                
                $_SESSION['password_reset_verified'] = true;
                
                logError("SMS verified successfully for master: $masterUsername");
                
                sendJSONResponse([
                    'success' => true,
                    'message' => 'SMS verified successfully'
                ]);
                
            } catch (Exception $e) {
                logError("Exception in verify_sms_code: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Verification failed'], 500);
            }
            break;
            
        case 'resend_sms_code':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (!isset($_SESSION['password_reset_email'])) {
                sendJSONResponse(['success' => false, 'message' => 'No active reset session'], 401);
            }
            
            $cooldown = checkCooldown('last_sms_send', 30);
            if (!$cooldown['allowed']) {
                sendJSONResponse([
                    'success' => false,
                    'message' => "Please wait {$cooldown['remaining']} seconds before requesting a new code"
                ], 429);
            }
            
            try {
                $masterUsername = $_SESSION['password_reset_master_username'];
                
                $db = new DatabaseConnection();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("SELECT phone_number FROM master_accounts WHERE username = ?");
                $stmt->bind_param('s', $masterUsername);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendJSONResponse(['success' => false, 'message' => 'Phone number not found'], 400);
                }
                
                $userData = $result->fetch_assoc();
                $phone = $userData['phone_number'];
                
                $smsCode = generateSecureCode(6);
                $codeExpiry = date('Y-m-d H:i:s', time() + 300);
                
                logError("Resending SMS code for $masterUsername: $smsCode");
                
                $stmt = $conn->prepare("UPDATE master_accounts SET code = ?, code_expires = ? WHERE username = ?");
                $stmt->bind_param('sss', $smsCode, $codeExpiry, $masterUsername);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to save new SMS code");
                }
                
                $mfaResponse = callMFAAPI('/send_sms', [
                    'phone' => $phone,
                    'code' => $smsCode
                ]);
                
                if ($mfaResponse['error']) {
                    $mfaResponse = callMFAAPI('/resend_code', [
                        'username' => $masterUsername,
                        'phone' => $phone
                    ]);
                    
                    if ($mfaResponse['error']) {
                        logError("SMS send failed but code saved: $smsCode");
                    }
                }
                
                $_SESSION['last_sms_send'] = time();
                
                sendJSONResponse([
                    'success' => true,
                    'message' => 'New SMS code sent to your phone'
                ]);
                
            } catch (Exception $e) {
                logError("Exception in resend_sms_code: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Failed to resend SMS'], 500);
            }
            break;
            
        case 'reset_password':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (!isset($_SESSION['password_reset_verified']) || $_SESSION['password_reset_verified'] !== true) {
                sendJSONResponse(['success' => false, 'message' => 'SMS verification required'], 401);
            }
            
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendJSONResponse(['success' => false, 'message' => 'Invalid request format'], 400);
            }
            
            $password = $input['password'] ?? '';
            $confirmPassword = $input['confirm_password'] ?? '';
            
            if ($password !== $confirmPassword) {
                sendJSONResponse(['success' => false, 'message' => 'Passwords do not match'], 400);
            }
            
            if (strlen($password) < 8 || 
                !preg_match('/[A-Z]/', $password) || 
                !preg_match('/[a-z]/', $password) || 
                !preg_match('/\d/', $password) || 
                !preg_match('/[^A-Za-z0-9]/', $password)) {
                sendJSONResponse(['success' => false, 'message' => 'Password does not meet requirements'], 400);
            }
            
            $email = $_SESSION['password_reset_email'];
            $username = $_SESSION['password_reset_username'];
            $isMaster = $_SESSION['password_reset_is_master'];
            $masterEmail = $_SESSION['password_reset_master_email'];
            $passwordHash = hash('sha256', $password);
            
            try {
                $db = new DatabaseConnection();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET passphrase = ?, 
                        changed_at = NOW()
                    WHERE email = ?
                ");
                $stmt->bind_param('ss', $passwordHash, $email);
                
                if ($stmt->execute()) {
                    logError("Password reset successful for: $email");
                    
                    if ($isMaster) {
                        sendPasswordChangedNotification($masterEmail, $username);
                    } else {
                        sendPasswordChangedNotification($masterEmail, $username);
                    }
                    
                    session_unset();
                    session_destroy();
                    
                    sendJSONResponse([
                        'success' => true,
                        'message' => 'Password reset successful'
                    ]);
                } else {
                    throw new Exception("Failed to update password");
                }
            } catch (Exception $e) {
                logError("Exception in reset_password: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Failed to update password'], 500);
            }
            break;
            
        default:
            sendJSONResponse(['success' => false, 'message' => 'Invalid action'], 400);
            break;
    }
    
} catch (Exception $e) {
    logError("Fatal error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    sendJSONResponse([
        'success' => false,
        'message' => 'Internal server error'
    ], 500);
} catch (Error $e) {
    logError("Fatal PHP error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    sendJSONResponse([
        'success' => false,
        'message' => 'Internal server error'
    ], 500);
}