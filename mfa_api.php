<?php
/**
 * MFA API - Versão robusta com error handling melhorado
 */

// Headers primeiro, antes de qualquer output
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Error handling
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Buffer de saída
ob_start();

// Função para enviar resposta JSON limpa
function sendJSONResponse($data, $httpCode = 200) {
    // Limpar buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($httpCode);
    echo json_encode($data);
    exit();
}

// Função para log seguro (não contamina resposta)
function logError($message) {
    error_log("MFA API: " . $message);
}

try {
    // Incluir dependências
    require_once '/var/www/html/controllers/inc.sessions.php';
    
    // Iniciar sessão se necessário
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar autenticação básica primeiro
    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        logError("No username in session");
        sendJSONResponse(['error' => true, 'message' => 'No active session'], 401);
    }
    
    if (!isset($_SESSION['awaiting_mfa']) || $_SESSION['awaiting_mfa'] !== 'true') {
        logError("User not awaiting MFA: " . $_SESSION['username']);
        sendJSONResponse(['error' => true, 'message' => 'MFA not required'], 400);
    }
    
    // Tentar incluir arquivos de integração
    try {
        require_once 'prosecure-api-integration.php';
        require_once 'auth_middleware.php';
    } catch (Exception $e) {
        logError("Failed to include integration files: " . $e->getMessage());
        sendJSONResponse(['error' => true, 'message' => 'Configuration error'], 500);
    }
    
    // Configuração da API MFA
    $MFA_API_BASE_URL = 'http://localhost:7080';
    
    // Função para chamar API MFA
    function callMFAAPI($endpoint, $data = null, $method = 'POST') {
        global $MFA_API_BASE_URL;
        
        $url = $MFA_API_BASE_URL . $endpoint;
        
        $curl = curl_init();
        
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_VERBOSE => false,
        ];
        
        if ($method === 'POST' && $data !== null) {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($curl, $curlOptions);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        
        curl_close($curl);
        
        if ($response === false) {
            logError("cURL Error: " . $curlError);
            return [
                'error' => true,
                'message' => 'Communication error with MFA service',
                'details' => $curlError
            ];
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError("JSON decode error: " . json_last_error_msg());
            return [
                'error' => true,
                'message' => 'Invalid response from MFA service',
                'details' => 'JSON decode error: ' . json_last_error_msg()
            ];
        }
        
        return [
            'error' => $httpCode >= 400,
            'http_code' => $httpCode,
            'data' => $decodedResponse
        ];
    }
    
    // Função para buscar informações do usuário
    function getUserInfo($username) {
        try {
            // Primeiro tentar obter do JWT se disponível
            if (function_exists('getAPIUser')) {
                $jwtUser = getAPIUser();
                if ($jwtUser && $jwtUser['username'] === $username) {
                    return [
                        'username' => $jwtUser['username'],
                        'email' => $jwtUser['email'],
                        'phone_number' => null,
                        'account_type' => $jwtUser['account_type'],
                        'is_master' => $jwtUser['is_master']
                    ];
                }
            }
            
            // Fallback para busca no banco de dados
            require_once('users.php');
            require_once('database_connection.php');
            
            $db = new DatabaseConnection();
            $conn = $db->getConnection();
            
            $sql = "SELECT username, email FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return null;
            }
            
            $userInfo = $result->fetch_assoc();
            
            // Verificar na tabela master_accounts
            $sql = "SELECT * FROM master_accounts WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return $userInfo;
            }
            
            $masterInfo = $result->fetch_assoc();
            return array_merge($userInfo, $masterInfo);
            
        } catch (Exception $e) {
            logError("Error getting user info: " . $e->getMessage());
            return null;
        }
    }
    
    // Processar requisições
    $action = $_GET['action'] ?? '';
    $username = $_SESSION['username'];
    
    logError("Processing action: $action for user: $username");
    
    switch ($action) {
        case 'check_2fa':
            $response = callMFAAPI('/check_2fa', ['username' => $username]);
            
            if ($response['error']) {
                sendJSONResponse([
                    'error' => true,
                    'message' => $response['data']['message'] ?? $response['message'],
                    'details' => $response['details'] ?? ''
                ], $response['http_code'] ?? 500);
            } else {
                sendJSONResponse([
                    'success' => true,
                    'status' => $response['data']['status'] ?? 'unknown',
                    'message' => $response['data']['message'] ?? 'Code sent successfully'
                ]);
            }
            break;
            
        case 'verify_code':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['error' => true, 'message' => 'Method not allowed'], 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $code = $input['code'] ?? '';
            
            if (empty($code)) {
                sendJSONResponse(['error' => true, 'message' => 'Verification code is required'], 400);
            }
            
            if (!preg_match('/^\d{6}$/', $code)) {
                sendJSONResponse(['error' => true, 'message' => 'Invalid code format'], 400);
            }
            
            $response = callMFAAPI('/verify_code', [
                'username' => $username,
                'code' => $code
            ]);
            
            if ($response['error']) {
                sendJSONResponse([
                    'error' => true,
                    'message' => $response['data']['message'] ?? $response['message'],
                    'details' => $response['details'] ?? ''
                ], $response['http_code'] ?? 500);
            } else {
                // MFA verificado com sucesso
                $_SESSION['authorized'] = 'true';
                $_SESSION['awaiting_mfa'] = 'false';
                
                // Tentar gerar token JWT se não existir
                if (function_exists('getAPIUser') && function_exists('integrateAPIAuthentication')) {
                    $jwtUser = getAPIUser();
                    if (!$jwtUser) {
                        try {
                            require_once('users.php');
                            require_once('database_connection.php');
                            $db = new DatabaseConnection();
                            $conn = $db->getConnection();
                            
                            $sql = "SELECT u.email, u.is_master FROM users u WHERE u.username = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param('s', $username);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows === 1) {
                                $userRow = $result->fetch_assoc();
                                $accountType = $userRow['is_master'] == 1 ? 'master' : 'normal';
                                integrateAPIAuthentication($username, '', $accountType);
                            }
                        } catch (Exception $e) {
                            logError("Failed to generate JWT after MFA: " . $e->getMessage());
                        }
                    }
                }
                
                sendJSONResponse([
                    'success' => true,
                    'message' => $response['data']['message'] ?? 'Authentication successful',
                    'redirect' => 'http://prosecurelsp.com/users/dashboard/index.php'
                ]);
            }
            break;
            
        case 'resend_code':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendJSONResponse(['error' => true, 'message' => 'Method not allowed'], 405);
            }
            
            $userInfo = getUserInfo($username);
            
            if (!$userInfo) {
                sendJSONResponse([
                    'error' => true, 
                    'message' => 'User information not found'
                ], 400);
            }
            
            // Verificar telefone
            $phone = null;
            foreach (['phone_number', 'phone', 'cellphone'] as $field) {
                if (!empty($userInfo[$field])) {
                    $phone = $userInfo[$field];
                    break;
                }
            }
            
            if (empty($phone)) {
                sendJSONResponse([
                    'error' => true, 
                    'message' => 'Phone number not found in your account'
                ], 400);
            }
            
            $response = callMFAAPI('/resend_code', [
                'username' => $username,
                'phone' => $phone
            ]);
            
            if ($response['error']) {
                sendJSONResponse([
                    'error' => true,
                    'message' => $response['data']['message'] ?? $response['message'],
                    'details' => $response['details'] ?? ''
                ], $response['http_code'] ?? 500);
            } else {
                sendJSONResponse([
                    'success' => true,
                    'message' => $response['data']['message'] ?? 'Code resent successfully'
                ]);
            }
            break;
            
        case 'debug_user':
            $userInfo = getUserInfo($username);
            $jwtUser = function_exists('getAPIUser') ? getAPIUser() : null;
            
            sendJSONResponse([
                'username' => $username,
                'user_info_db' => $userInfo,
                'jwt_user' => $jwtUser,
                'session' => [
                    'admin' => $_SESSION['admin'] ?? 'not set',
                    'awaiting_mfa' => $_SESSION['awaiting_mfa'] ?? 'not set',
                    'authorized' => $_SESSION['authorized'] ?? 'not set'
                ],
                'jwt_status' => [
                    'has_token' => isset($_SESSION['api_token']),
                    'token_valid' => function_exists('isAPITokenValid') ? isAPITokenValid() : false,
                    'has_refresh' => isset($_SESSION['api_refresh_token'])
                ]
            ]);
            break;
            
        case 'status':
            $jwtUser = function_exists('getAPIUser') ? getAPIUser() : null;
            
            sendJSONResponse([
                'success' => true,
                'username' => $username,
                'session_valid' => isset($_SESSION['authorized']) && $_SESSION['authorized'] === 'true',
                'mfa_pending' => isset($_SESSION['awaiting_mfa']) && $_SESSION['awaiting_mfa'] === 'true',
                'jwt_authenticated' => $jwtUser !== null,
                'user_info' => $jwtUser
            ]);
            break;
            
        default:
            sendJSONResponse(['error' => true, 'message' => 'Invalid action'], 400);
            break;
    }
    
} catch (Exception $e) {
    logError("Fatal error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    sendJSONResponse([
        'error' => true,
        'message' => 'Internal server error',
        'details' => 'A server error occurred. Please try again.'
    ], 500);
} catch (Error $e) {
    logError("Fatal PHP error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    sendJSONResponse([
        'error' => true,
        'message' => 'Internal server error',
        'details' => 'A server error occurred. Please try again.'
    ], 500);
}
?>