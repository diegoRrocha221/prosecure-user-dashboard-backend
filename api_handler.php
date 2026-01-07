<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();

// CRITICAL: Configurar timeouts do PHP para operações longas mas com retry
set_time_limit(900); // 15 minutos total
ini_set('max_execution_time', 900);
ini_set('default_socket_timeout', 120); 

// TIMEOUT QUASE ILIMITADO PARA API
define('API_BASE_URL', 'http://localhost:8087/api');
define('API_TIMEOUT', 600); // 10 MINUTOS por tentativa
define('MAX_RETRIES', 1);   // Apenas 1 tentativa com timeout alto
define('RETRY_DELAY', 5);

header('Content-Type: application/json');

// Verificar se usuário está autorizado
if (!isset($_SESSION['username']) || !isset($_SESSION['payment_error'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Verificar ação
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'check_account_status':
        handleCheckAccountStatus();
        break;
        
    case 'update_card':
        handleUpdateCard();
        break;
        
    case 'get_update_history':
        handleGetUpdateHistory();
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}

/**
 * Verificar status da conta
 */
function handleCheckAccountStatus() {
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    
    if (empty($email) || empty($username)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email and username are required'
        ]);
        return;
    }
    
    $url = API_BASE_URL . '/check-account-status?' . http_build_query([
        'email' => $email,
        'username' => $username
    ]);
    
    $response = makeApiCallWithRetry('GET', $url);
    echo json_encode($response);
}

/**
 * Atualizar cartão de crédito
 */
function handleUpdateCard() {
    $requestId = 'php-' . time() . '-' . rand(1000, 9999);
    error_log("[$requestId] Starting card update request processing");
    
    // Obter dados de POST ou JSON
    $input_data = $_POST;
    if (empty($input_data) || count($input_data) <= 1) {
        $json_input = file_get_contents('php://input');
        if (!empty($json_input)) {
            $decoded = json_decode($json_input, true);
            if ($decoded && is_array($decoded)) {
                $input_data = array_merge($_POST, $decoded);
            }
        }
    }
    
    // Validar dados obrigatórios
    $requiredFields = ['email', 'username', 'card_name', 'card_number', 'expiry', 'cvv'];
    $data = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input_data[$field])) {
            error_log("[$requestId] Missing required field: $field");
            echo json_encode([
                'success' => false,
                'message' => 'All fields are required'
            ]);
            return;
        }
        $data[$field] = trim($input_data[$field]);
    }
    
    // Validações básicas
    if (strlen($data['card_name']) < 3) {
        error_log("[$requestId] Invalid card name length");
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid cardholder name'
        ]);
        return;
    }
    
    if (strlen(preg_replace('/\D/', '', $data['card_number'])) < 13) {
        error_log("[$requestId] Invalid card number length");
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid card number'
        ]);
        return;
    }
    
    if (!preg_match('/^\d{2}\/\d{2}$/', $data['expiry'])) {
        error_log("[$requestId] Invalid expiry format");
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid expiry date (MM/YY)'
        ]);
        return;
    }
    
    if (strlen($data['cvv']) < 3 || strlen($data['cvv']) > 4) {
        error_log("[$requestId] Invalid CVV length");
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid CVV'
        ]);
        return;
    }
    
    // Preparar dados para API
    $apiData = [
        'email' => $data['email'],
        'username' => $data['username'],
        'card_name' => $data['card_name'],
        'card_number' => preg_replace('/\D/', '', $data['card_number']),
        'expiry' => $data['expiry'],
        'cvv' => $data['cvv']
    ];
    
    error_log("[$requestId] Sending request to API for user: " . $data['username']);
    
    $url = API_BASE_URL . '/update-card';
    $response = makeApiCallWithRetry('POST', $url, $apiData, $requestId);
    
    // Se foi bem-sucedido, remover flag de erro de pagamento da sessão
    if (isset($response['success']) && $response['success']) {
        unset($_SESSION['payment_error']);
        error_log("[$requestId] Card update successful, session flag removed");
    } else {
        error_log("[$requestId] Card update failed: " . ($response['message'] ?? 'Unknown error'));
    }
    
    echo json_encode($response);
}

/**
 * Obter histórico de atualizações
 */
function handleGetUpdateHistory() {
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    
    if (empty($email) || empty($username)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email and username are required'
        ]);
        return;
    }
    
    $url = API_BASE_URL . '/card-update-history?' . http_build_query([
        'email' => $email,
        'username' => $username
    ]);
    
    $response = makeApiCallWithRetry('GET', $url);
    echo json_encode($response);
}

/**
 * NOVA FUNÇÃO: Fazer chamada para API com retry automático
 */
function makeApiCallWithRetry($method, $url, $data = null, $requestId = null) {
    $lastError = null;
    $lastResponse = null;
    
    for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
        if ($attempt > 1) {
            error_log("[$requestId] Retry attempt $attempt/" . MAX_RETRIES . " after " . RETRY_DELAY . " seconds");
            sleep(RETRY_DELAY);
        }
        
        error_log("[$requestId] API call attempt $attempt: $method $url");
        
        $response = makeApiCall($method, $url, $data, $requestId . "-$attempt");
        
        // Se a resposta foi bem-sucedida, retornar imediatamente
        if (isset($response['success'])) {
            if ($response['success'] === true) {
                error_log("[$requestId] API call successful on attempt $attempt");
                return $response;
            } else {
                // Erro lógico da API (cartão inválido, etc.) - não retry
                error_log("[$requestId] API returned logical error on attempt $attempt: " . ($response['message'] ?? 'Unknown error'));
                return $response;
            }
        }
        
        // Erro de comunicação - tentar novamente
        $lastResponse = $response;
        if (isset($response['debug']['curl_error'])) {
            $lastError = $response['debug']['curl_error'];
            error_log("[$requestId] Communication error on attempt $attempt: $lastError");
        } else {
            $lastError = $response['message'] ?? 'Unknown communication error';
            error_log("[$requestId] Error on attempt $attempt: $lastError");
        }
        
        // Se não é o último attempt, continuar o loop
        if ($attempt < MAX_RETRIES) {
            continue;
        }
    }
    
    // Todas as tentativas falharam
    error_log("[$requestId] All $attempt attempts failed. Last error: $lastError");
    
    return [
        'success' => false,
        'message' => 'Service temporarily unavailable after multiple attempts. Please try again in a few minutes.',
        'debug' => [
            'attempts' => MAX_RETRIES,
            'last_error' => $lastError,
            'last_response' => $lastResponse
        ]
    ];
}

/**
 * OTIMIZADA: Fazer chamada para API com timeouts menores e melhor detecção de erros
 */
function makeApiCall($method, $url, $data = null, $requestId = null) {
    $startTime = microtime(true);
    
    if (!$requestId) {
        $requestId = 'api-' . time();
    }
    
    error_log("[$requestId] Making API call: $method $url");
    
    $curl = curl_init();
    
    // TIMEOUT ESPECÍFICO para update-card
    $isUpdateCard = strpos($url, '/update-card') !== false;
    $timeout = $isUpdateCard ? 600 : 300; // 10 minutos para update-card, 5 minutos para outros
    
    // CONFIGURAÇÕES PARA TIMEOUT MUITO ALTO
    $curlOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,        // 10 MINUTOS
        CURLOPT_CONNECTTIMEOUT => 30,       // 30 segundos para conectar
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'ProSecureLSP-PHP-Client/2.0',
        CURLOPT_NOSIGNAL => 1,
        
        // CONFIGURAÇÕES PARA CONEXÕES LONGAS
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_KEEPIDLE => 60,
        CURLOPT_TCP_KEEPINTVL => 30,
        CURLOPT_LOW_SPEED_LIMIT => 1,       
        CURLOPT_LOW_SPEED_TIME => 120,      // 2 MINUTOS de tolerância para velocidade baixa
        
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'Expect:',
        ]
    ];
    
    // Configurações específicas por método
    if ($method === 'POST') {
        $curlOptions[CURLOPT_POST] = true;
        $curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        
        if ($data) {
            $jsonData = json_encode($data);
            $curlOptions[CURLOPT_POSTFIELDS] = $jsonData;
            error_log("[$requestId] POST data size: " . strlen($jsonData) . " bytes");
        }
    } elseif ($method === 'GET') {
        $curlOptions[CURLOPT_HTTPGET] = true;
    }
    
    curl_setopt_array($curl, $curlOptions);
    
    // Log específico para update de cartão
    if ($isUpdateCard) {
        error_log("[$requestId] Starting LONG TIMEOUT card update API call with timeout: {$timeout}s (10 minutes)");
    }
    
    // Executar requisição
    error_log("[$requestId] Executing cURL request...");
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    $totalTime = curl_getinfo($curl, CURLINFO_TOTAL_TIME);
    $connectTime = curl_getinfo($curl, CURLINFO_CONNECT_TIME);
    
    curl_close($curl);
    
    $elapsedTime = microtime(true) - $startTime;
    error_log("[$requestId] API call completed in {$elapsedTime}s (cURL: {$totalTime}s, connect: {$connectTime}s), HTTP: $httpCode");
    
    // Verificação de erros de cURL com diagnóstico
    if ($response === false || !empty($error)) {
        $errorMsg = $error ?: 'Unknown cURL error';
        
        // Diagnosticar tipos específicos de erro
        if (strpos($errorMsg, 'Operation timed out') !== false || strpos($errorMsg, 'timeout') !== false) {
            if ($isUpdateCard) {
                $errorMsg = "Card update operation timed out after {$timeout} seconds (10 minutes). This is unusual - please contact support.";
            } else {
                $errorMsg = "Connection timeout after {$timeout} seconds. Server may be experiencing issues.";
            }
        } elseif (strpos($errorMsg, 'Empty reply from server') !== false) {
            $errorMsg = 'Server closed connection unexpectedly after long processing time.';
        } elseif (strpos($errorMsg, 'Couldn\'t connect to server') !== false) {
            $errorMsg = 'Unable to connect to payment service. Service may be down.';
        }
        
        error_log("[$requestId] cURL Error: " . $error . " (Diagnosed: $errorMsg)");
        
        return [
            'success' => false,
            'message' => 'Communication error with payment service. Please try again.',
            'debug' => [
                'curl_error' => $error,
                'diagnosed_error' => $errorMsg,
                'http_code' => $httpCode,
                'elapsed_time' => $elapsedTime,
                'connect_time' => $connectTime,
                'total_time' => $totalTime,
                'timeout_used' => $timeout
            ]
        ];
    }

    // Rest of your existing error handling code remains the same...
    
    // Verificar código HTTP
    if ($httpCode >= 500) {
        error_log("[$requestId] API Server Error: HTTP $httpCode - Response: " . substr($response, 0, 500));
        return [
            'success' => false,
            'message' => 'Payment service temporarily unavailable. Please try again later.',
            'debug' => [
                'http_code' => $httpCode,
                'response_preview' => substr($response, 0, 200)
            ]
        ];
    }
    
    // Verificar se resposta está vazia
    if (empty($response)) {
        error_log("[$requestId] Empty response from API");
        return [
            'success' => false,
            'message' => 'Empty response from payment service',
            'debug' => [
                'http_code' => $httpCode,
                'elapsed_time' => $elapsedTime
            ]
        ];
    }
    
    // Decodificar resposta JSON
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[$requestId] JSON Decode Error: " . json_last_error_msg() . " - Response preview: " . substr($response, 0, 200));
        return [
            'success' => false,
            'message' => 'Invalid response format from payment service',
            'debug' => [
                'json_error' => json_last_error_msg(),
                'response_preview' => substr($response, 0, 200)
            ]
        ];
    }
    
    // Log da resposta bem-sucedida (sem dados sensíveis)
    $logData = $decodedResponse;
    if (isset($logData['details'])) {
        unset($logData['details']);
    }
    error_log("[$requestId] API Response successful: " . json_encode($logData));
    
    // Tratar códigos de erro HTTP específicos
    if ($httpCode >= 400) {
        $message = $decodedResponse['message'] ?? 'Unknown error occurred';
        
        switch ($httpCode) {
            case 400:
                $message = 'Invalid request: ' . $message;
                break;
            case 401:
                $message = 'Authentication failed';
                break;
            case 402:
                $message = 'Payment declined: ' . $message;
                break;
            case 404:
                $message = 'Account not found';
                break;
            case 409:
                $message = 'Request conflict: ' . $message;
                break;
            case 408:
                $message = 'Request timeout: The operation took longer than 10 minutes. Please contact support.';
                break;
            default:
                $message = 'Request failed: ' . $message;
                break;
        }
        
        return [
            'success' => false,
            'message' => $message,
            'debug' => [
                'http_code' => $httpCode,
                'original_message' => $decodedResponse['message'] ?? null
            ]
        ];
    }
    
    // Verificar se a resposta tem campo success
    if (isset($decodedResponse['success'])) {
        return $decodedResponse;
    }
    
    // Se não tem campo success, assumir sucesso se status é success
    if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'success') {
        $decodedResponse['success'] = true;
        return $decodedResponse;
    }
    
    // Fallback para resposta sem campo success/status
    return [
        'success' => true,
        'data' => $decodedResponse
    ];
}

/**
 * Validar formato de email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar algoritmo de Luhn para cartão de crédito
 */
function isValidCardNumber($cardNumber) {
    $cardNumber = preg_replace('/\D/', '', $cardNumber);
    
    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        return false;
    }
    
    $sum = 0;
    $isEven = strlen($cardNumber) % 2 === 0;
    
    for ($i = 0; $i < strlen($cardNumber); $i++) {
        $digit = intval($cardNumber[$i]);
        
        if ($isEven === ($i % 2 === 0)) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    
    return $sum % 10 === 0;
}

/**
 * Sanitizar dados de entrada
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>