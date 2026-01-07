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
    
    function sendResetEmail($email, $code) {
        try {
            $title = "Password Reset Request";
            $name = explode('@', $email)[0];
            $subtitle = "Verification Code";
            
            $content = '
                <p>We received a request to reset your password. Use the verification code below to continue:</p>
                <div style="text-align: center; margin: 2rem 0;">
                    <div style="display: inline-block; background: #f8f9fa; padding: 1.5rem 2rem; border-radius: 8px; border: 2px solid #157347;">
                        <span style="font-size: 2rem; font-weight: bold; color: #25364D; letter-spacing: 0.5rem;">' . $code . '</span>
                    </div>
                </div>
                <p><strong>This code will expire in 10 minutes.</strong></p>
                <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                <p style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e9ecef; font-size: 0.9rem; color: #6c757d;">
                    For security reasons, never share this code with anyone. ProSecureLSP will never ask you for this code.
                </p>
            ';
            
            $emailBody = new_user_email_template($title, $name, $subtitle, $content);
            
            $mail = new PHPMailer(true);
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = '172.31.255.82';
            $mail->SMTPAuth = false;
            $mail->Port = 25;
            $mail->SMTPAutoTLS = false;
            $mail->setFrom('no-reply@prosecure.com', 'ProSecureLSP');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset - Verification Code';
            $mail->Body = $emailBody;
            
            return $mail->send();
        } catch (Exception $e) {
            logError("Email send error: " . $e->getMessage());
            return false;
        }
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
          'HTTP_CF_CONNECTING_IP', // Cloudflare
          'HTTP_X_FORWARDED_FOR',
          'HTTP_X_REAL_IP',
          'REMOTE_ADDR'
      ];
      
      foreach ($ipHeaders as $header) {
          if (!empty($_SERVER[$header])) {
              $ip = $_SERVER[$header];
              // Se for uma lista de IPs, pegar o primeiro
              if (strpos($ip, ',') !== false) {
                  $ip = trim(explode(',', $ip)[0]);
              }
              
              // Validar se é um IP válido
              if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                  return $ip;
              }
          }
      }
      
      return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
  }
  
  function getLocationFromIP($ip) {
    // Não tentar buscar localização para IPs privados
    if ($ip === 'Unknown' || 
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return [
            'city' => 'Local Network',
            'region' => '',
            'country' => '',
            'timezone' => ''
        ];
    }
    
    // Lista de APIs para tentar (fallback)
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
        ],
        [
            'url' => "https://ipwhois.app/json/{$ip}",
            'parser' => function($data) {
                if (!empty($data['error'])) {
                    return null;
                }
                return [
                    'city' => $data['city'] ?? '',
                    'region' => $data['region'] ?? '',
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
                        logError("Location found for IP $ip: " . json_encode($result));
                        return $result;
                    }
                }
            }
        } catch (Exception $e) {
            logError("Failed to get location from API {$api['url']}: " . $e->getMessage());
            continue;
        }
    }
    
    logError("All location APIs failed for IP: $ip");
    
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
      
      // Detectar sistema operacional/dispositivo
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
      
      // Detectar navegador
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
  
  function sendPasswordChangedNotification($email) {
    try {
        $ip = getClientIP();
        $location = getLocationFromIP($ip);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $agentInfo = parseUserAgent($userAgent);
        
        // Timestamp formatado
        $timestamp = date('F j, Y \a\t g:i A T');
        
        logError("Sending password change notification to $email from IP: $ip, Location: " . json_encode($location));
        
        $emailBody = password_changed_notification_template(
            $email,
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
        
        $result = $mail->send();
        
        if ($result) {
            logError("Password change notification sent successfully to: $email");
        } else {
            logError("Failed to send password change notification to: $email");
        }
        
        return $result;
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
                
                $stmt = $conn->prepare("SELECT username, email, is_master FROM users WHERE email = ? AND is_active = 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendJSONResponse([
                        'success' => true,
                        'message' => 'If an account exists with this email, you will receive a verification code'
                    ]);
                }
                
                $user = $result->fetch_assoc();
                $username = $user['username'];
                $isMaster = $user['is_master'] == 1;
                
                $emailCode = generateSecureCode(6);
                $codeExpiry = date('Y-m-d H:i:s', time() + 600);
                
                logError("Generated EMAIL code for $email: $emailCode (expires: $codeExpiry)");
                
                $stmt = $conn->prepare("UPDATE users SET password_reset_code = ?, password_reset_expires = ? WHERE email = ?");
                $stmt->bind_param('sss', $emailCode, $codeExpiry, $email);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update reset code");
                }
                
                $requiresMFA = false;
                if ($isMaster) {
                    $stmt = $conn->prepare("SELECT mfa_is_enable FROM master_accounts WHERE username = ?");
                    
                    if ($stmt) {
                        $stmt->bind_param('s', $username);
                        $stmt->execute();
                        $mfaResult = $stmt->get_result();
                        
                        if ($mfaResult->num_rows > 0) {
                            $mfaData = $mfaResult->fetch_assoc();
                            $requiresMFA = ($mfaData['mfa_is_enable'] == 1);
                        }
                    }
                }
                
                if (sendResetEmail($email, $emailCode)) {
                    $_SESSION['password_reset_email'] = $email;
                    $_SESSION['password_reset_username'] = $username;
                    $_SESSION['password_reset_mfa_required'] = $requiresMFA;
                    unset($_SESSION['password_reset_email_verified']);
                    unset($_SESSION['password_reset_verified']);
                    
                    logError("Reset email sent successfully to: $email");
                    
                    sendJSONResponse([
                        'success' => true,
                        'message' => 'Verification code sent to your email'
                    ]);
                } else {
                    throw new Exception("Failed to send email");
                }
                
            } catch (Exception $e) {
                logError("Exception in request_reset: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Failed to process request'], 500);
            }
            break;
            
        case 'verify_email_code':
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
            
            $email = $_SESSION['password_reset_email'];
            
            try {
                $db = new DatabaseConnection();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("
                    SELECT password_reset_code, password_reset_expires 
                    FROM users 
                    WHERE email = ? AND password_reset_code = ?
                ");
                $stmt->bind_param('ss', $email, $code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendJSONResponse(['success' => false, 'message' => 'Invalid verification code'], 400);
                }
                
                $resetData = $result->fetch_assoc();
                
                if (strtotime($resetData['password_reset_expires']) < time()) {
                    sendJSONResponse(['success' => false, 'message' => 'Verification code has expired'], 400);
                }
                
                $_SESSION['password_reset_email_verified'] = true;
                
                $requiresMFA = $_SESSION['password_reset_mfa_required'] ?? false;
                
                logError("Email verified for: $email, requires MFA: " . ($requiresMFA ? 'yes' : 'no'));
                
                sendJSONResponse([
                    'success' => true,
                    'message' => 'Email verified successfully',
                    'requires_mfa' => $requiresMFA
                ]);
                
            } catch (Exception $e) {
                logError("Exception in verify_email_code: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Verification failed'], 500);
            }
            break;
            
        case 'resend_email_code':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (!isset($_SESSION['password_reset_email'])) {
                sendJSONResponse(['success' => false, 'message' => 'No active reset session'], 401);
            }
            
            $cooldown = checkCooldown('last_email_resend', 60);
            if (!$cooldown['allowed']) {
                sendJSONResponse([
                    'success' => false,
                    'message' => "Please wait {$cooldown['remaining']} seconds before requesting a new code"
                ], 429);
            }
            
            $email = $_SESSION['password_reset_email'];
            
            try {
                $emailCode = generateSecureCode(6);
                $codeExpiry = date('Y-m-d H:i:s', time() + 600);
                
                logError("Resending EMAIL code for $email: $emailCode");
                
                $db = new DatabaseConnection();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("UPDATE users SET password_reset_code = ?, password_reset_expires = ? WHERE email = ?");
                $stmt->bind_param('sss', $emailCode, $codeExpiry, $email);
                $stmt->execute();
                
                if (sendResetEmail($email, $emailCode)) {
                    $_SESSION['last_email_resend'] = time();
                    
                    sendJSONResponse([
                        'success' => true,
                        'message' => 'New verification code sent to your email'
                    ]);
                } else {
                    sendJSONResponse(['success' => false, 'message' => 'Failed to send email'], 500);
                }
            } catch (Exception $e) {
                logError("Exception in resend_email_code: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Failed to resend code'], 500);
            }
            break;
            
            case 'send_mfa_code':
              if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                  sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
              }
              
              if (!isset($_SESSION['password_reset_email_verified']) || $_SESSION['password_reset_email_verified'] !== true) {
                  sendJSONResponse(['success' => false, 'message' => 'Email verification required'], 401);
              }
              
              if (!isset($_SESSION['password_reset_mfa_required']) || $_SESSION['password_reset_mfa_required'] !== true) {
                  sendJSONResponse(['success' => false, 'message' => 'MFA not required'], 400);
              }
              
              $cooldown = checkCooldown('last_mfa_send', 30);
              if (!$cooldown['allowed']) {
                  sendJSONResponse([
                      'success' => false,
                      'message' => "Please wait {$cooldown['remaining']} seconds before requesting a new code"
                  ], 429);
              }
              
              try {
                  $username = $_SESSION['password_reset_username'];
                  
                  $db = new DatabaseConnection();
                  $conn = $db->getConnection();
                  
                  $stmt = $conn->prepare("SELECT phone_number FROM master_accounts WHERE username = ?");
                  $stmt->bind_param('s', $username);
                  $stmt->execute();
                  $result = $stmt->get_result();
                  
                  if ($result->num_rows === 0) {
                      sendJSONResponse(['success' => false, 'message' => 'Phone number not found'], 400);
                  }
                  
                  $userData = $result->fetch_assoc();
                  $phone = $userData['phone_number'];
                  
                  if (empty($phone)) {
                      sendJSONResponse(['success' => false, 'message' => 'Phone number not configured'], 400);
                  }
                  
                  // Gerar código de 6 dígitos
                  $mfaCode = generateSecureCode(6);
                  $codeExpiry = date('Y-m-d H:i:s', time() + 300); // 5 minutos
                  
                  logError("Generated MFA code for $username: $mfaCode (expires: $codeExpiry)");
                  
                  // Salvar código na tabela master_accounts
                  $stmt = $conn->prepare("UPDATE master_accounts SET code = ?, code_expires = ? WHERE username = ?");
                  $stmt->bind_param('sss', $mfaCode, $codeExpiry, $username);
                  
                  if (!$stmt->execute()) {
                      throw new Exception("Failed to save MFA code");
                  }
                  
                  // Enviar SMS via API MFA
                  $mfaResponse = callMFAAPI('/send_sms', [
                      'phone' => $phone,
                      'code' => $mfaCode
                  ]);
                  
                  // Se a API não tiver endpoint /send_sms, usar /resend_code
                  if ($mfaResponse['error']) {
                      logError("Failed to send SMS via API, trying alternative method");
                      
                      // Tentar endpoint alternativo ou envio direto
                      $mfaResponse = callMFAAPI('/resend_code', [
                          'username' => $username,
                          'phone' => $phone
                      ]);
                      
                      if ($mfaResponse['error']) {
                          logError("MFA API error: " . json_encode($mfaResponse));
                          // Mesmo com erro no envio, salvamos o código para teste
                          logError("Code saved but SMS may not have been sent: $mfaCode");
                      }
                  }
                  
                  $_SESSION['last_mfa_send'] = time();
                  
                  logError("MFA code generation successful for user: $username");
                  
                  sendJSONResponse([
                      'success' => true,
                      'message' => 'SMS code sent to your phone'
                  ]);
                  
              } catch (Exception $e) {
                  logError("Exception in send_mfa_code: " . $e->getMessage());
                  sendJSONResponse(['success' => false, 'message' => 'Failed to send SMS'], 500);
              }
              break;
              
          case 'verify_mfa_code':
              if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                  sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
              }
              
              if (!isset($_SESSION['password_reset_email_verified']) || $_SESSION['password_reset_email_verified'] !== true) {
                  sendJSONResponse(['success' => false, 'message' => 'Email verification required'], 401);
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
                  $username = $_SESSION['password_reset_username'];
                  
                  $db = new DatabaseConnection();
                  $conn = $db->getConnection();
                  
                  // Buscar código salvo na tabela master_accounts
                  $stmt = $conn->prepare("SELECT code, code_expires FROM master_accounts WHERE username = ?");
                  $stmt->bind_param('s', $username);
                  $stmt->execute();
                  $result = $stmt->get_result();
                  
                  if ($result->num_rows === 0) {
                      sendJSONResponse(['success' => false, 'message' => 'Account not found'], 400);
                  }
                  
                  $mfaData = $result->fetch_assoc();
                  $savedCode = $mfaData['code'];
                  $codeExpiry = $mfaData['code_expires'];
                  
                  logError("Verifying MFA code for $username: submitted=$code, saved=$savedCode");
                  
                  // Verificar se o código expirou
                  if (!empty($codeExpiry) && strtotime($codeExpiry) < time()) {
                      logError("MFA code expired for $username");
                      sendJSONResponse(['success' => false, 'message' => 'SMS code has expired'], 400);
                  }
                  
                  // Verificar se o código está correto
                  if ($savedCode !== $code) {
                      logError("MFA code mismatch for $username");
                      sendJSONResponse(['success' => false, 'message' => 'Invalid SMS code'], 400);
                  }
                  
                  // Limpar código após validação bem-sucedida
                  $stmt = $conn->prepare("UPDATE master_accounts SET code = NULL, code_expires = NULL WHERE username = ?");
                  $stmt->bind_param('s', $username);
                  $stmt->execute();
                  
                  $_SESSION['password_reset_verified'] = true;
                  
                  logError("MFA verified successfully for user: $username");
                  
                  sendJSONResponse([
                      'success' => true,
                      'message' => 'SMS verified successfully'
                  ]);
                  
              } catch (Exception $e) {
                  logError("Exception in verify_mfa_code: " . $e->getMessage());
                  sendJSONResponse(['success' => false, 'message' => 'Verification failed'], 500);
              }
              break;
              
          case 'resend_mfa_code':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (!isset($_SESSION['password_reset_email_verified']) || $_SESSION['password_reset_email_verified'] !== true) {
                sendJSONResponse(['success' => false, 'message' => 'Email verification required'], 401);
            }
            
            $cooldown = checkCooldown('last_mfa_send', 30);
            if (!$cooldown['allowed']) {
                sendJSONResponse([
                    'success' => false,
                    'message' => "Please wait {$cooldown['remaining']} seconds before requesting a new code"
                ], 429);
            }
            
            try {
                $username = $_SESSION['password_reset_username'];
                
                $db = new DatabaseConnection();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("SELECT phone_number FROM master_accounts WHERE username = ?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendJSONResponse(['success' => false, 'message' => 'Phone number not found'], 400);
                }
                
                $userData = $result->fetch_assoc();
                $phone = $userData['phone_number'];
                
                // Gerar novo código
                $mfaCode = generateSecureCode(6);
                $codeExpiry = date('Y-m-d H:i:s', time() + 300); // 5 minutos
                
                logError("Resending MFA code for $username: $mfaCode");
                
                // Atualizar código na tabela
                $stmt = $conn->prepare("UPDATE master_accounts SET code = ?, code_expires = ? WHERE username = ?");
                $stmt->bind_param('sss', $mfaCode, $codeExpiry, $username);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to save new MFA code");
                }
                
                // Tentar enviar SMS
                $mfaResponse = callMFAAPI('/send_sms', [
                    'phone' => $phone,
                    'code' => $mfaCode
                ]);
                
                if ($mfaResponse['error']) {
                    $mfaResponse = callMFAAPI('/resend_code', [
                        'username' => $username,
                        'phone' => $phone
                    ]);
                    
                    if ($mfaResponse['error']) {
                        logError("SMS send failed but code saved: $mfaCode");
                    }
                }
                
                $_SESSION['last_mfa_send'] = time();
                
                sendJSONResponse([
                    'success' => true,
                    'message' => 'New SMS code sent to your phone'
                ]);
                
            } catch (Exception $e) {
                logError("Exception in resend_mfa_code: " . $e->getMessage());
                sendJSONResponse(['success' => false, 'message' => 'Failed to resend SMS'], 500);
            }
            break;
            
            case 'reset_password':
              if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                  sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
              }
              
              if (!isset($_SESSION['password_reset_email_verified']) || $_SESSION['password_reset_email_verified'] !== true) {
                  sendJSONResponse(['success' => false, 'message' => 'Email verification required'], 401);
              }
              
              $requiresMFA = $_SESSION['password_reset_mfa_required'] ?? false;
              
              if ($requiresMFA) {
                  if (!isset($_SESSION['password_reset_verified']) || $_SESSION['password_reset_verified'] !== true) {
                      sendJSONResponse(['success' => false, 'message' => 'SMS verification required'], 401);
                  }
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
              $passwordHash = hash('sha256', $password);
              
              try {
                  $db = new DatabaseConnection();
                  $conn = $db->getConnection();
                  
                  $stmt = $conn->prepare("
                      UPDATE users 
                      SET passphrase = ?, 
                          password_reset_code = NULL, 
                          password_reset_expires = NULL,
                          changed_at = NOW()
                      WHERE email = ?
                  ");
                  $stmt->bind_param('ss', $passwordHash, $email);
                  
                  if ($stmt->execute()) {
                      logError("Password reset successful for: $email");
                      
                      // Enviar notificação de segurança por email
                      sendPasswordChangedNotification($email);
                      
                      // Limpar sessão
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