<?php
/**
 * ProSecure API Integration - Classe PHP para integração com a API de Pagamentos
 * Versão atualizada com integração de autenticação JWT
 */

class ProSecureAPIClient {
    private $baseURL;
    private $internalSecret;
    
    public function __construct($baseURL = 'http://localhost:8087/api', $internalSecret = 'LSP0197O81r73a8Pd57c39ER3fu11cadSec4fb83d91') {
        $this->baseURL = $baseURL;
        $this->internalSecret = $internalSecret;
    }
    
    /**
     * Faz uma requisição HTTP para a API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $headers = []) {
        $url = $this->baseURL . $endpoint;
        
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Internal-Secret: ' . $this->internalSecret
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL Error: ' . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        return [
            'status_code' => $httpCode,
            'data' => $decodedResponse,
            'raw_response' => $response
        ];
    }
    
    /**
     * Gera token JWT para um usuário após autenticação bem-sucedida
     */
    public function generateTokenForUser($username, $email, $isMaster = false, $isActive = 1, $accountType = 'normal', $mfaEnabled = false) {
        try {
            $response = $this->makeRequest('/internal/generate-token', 'POST', [
                'username' => $username,
                'email' => $email,
                'is_master' => $isMaster,
                'is_active' => $isActive,
                'account_type' => $accountType,
                'mfa_enabled' => $mfaEnabled
            ]);
            
            if ($response['status_code'] === 200 && $response['data']['status'] === 'success') {
                return [
                    'success' => true,
                    'token' => $response['data']['data']['token'],
                    'refresh_token' => $response['data']['data']['refresh_token'],
                    'expires_at' => $response['data']['data']['expires_at'],
                    'user' => $response['data']['data']['user']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['data']['message'] ?? 'Token generation failed'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Valida um token JWT
     */
    public function validateToken($token) {
        try {
            $response = $this->makeRequest('/internal/validate-token', 'POST', [
                'token' => $token
            ]);
            
            if ($response['status_code'] === 200 && $response['data']['status'] === 'success') {
                return [
                    'valid' => true,
                    'user' => $response['data']['data']['user']
                ];
            } else {
                return [
                    'valid' => false,
                    'message' => $response['data']['message'] ?? 'Token validation failed'
                ];
            }
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Renovar token usando refresh token
     */
    public function refreshToken($refreshToken) {
        try {
            $response = $this->makeRequest('/internal/refresh-token', 'POST', [
                'refresh_token' => $refreshToken
            ]);
            
            if ($response['status_code'] === 200 && $response['data']['status'] === 'success') {
                return [
                    'success' => true,
                    'token' => $response['data']['data']['token'],
                    'refresh_token' => $response['data']['data']['refresh_token'],
                    'expires_at' => $response['data']['data']['expires_at'],
                    'user' => $response['data']['data']['user']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['data']['message'] ?? 'Token refresh failed'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar status da conta (endpoint público)
     */
    public function checkAccountStatus($email, $username) {
        try {
            $endpoint = '/check-account-status?' . http_build_query([
                'email' => $email,
                'username' => $username
            ]);
            
            $response = $this->makeRequest($endpoint, 'GET');
            
            return [
                'success' => $response['status_code'] === 200,
                'data' => $response['data']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualizar cartão (endpoint público para casos de payment_error)
     */
    public function updateCard($email, $username, $cardName, $cardNumber, $expiry, $cvv) {
        try {
            $response = $this->makeRequest('/update-card', 'POST', [
                'email' => $email,
                'username' => $username,
                'card_name' => $cardName,
                'card_number' => $cardNumber,
                'expiry' => $expiry,
                'cvv' => $cvv
            ]);
            
            return [
                'success' => $response['status_code'] === 200 && $response['data']['success'],
                'data' => $response['data']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Funções auxiliares para integração com o sistema de autenticação existente
 */

/**
 * NOVA FUNÇÃO: Integra autenticação PHP + geração de token JWT
 */
function integrateAPIAuthentication($username, $password, $authResult) {
    $apiClient = new ProSecureAPIClient();
    
    // Determinar parâmetros baseado no resultado da autenticação PHP
    $isMaster = ($authResult === 'master');
    $accountType = $authResult;
    $isActive = 1;
    
    switch ($authResult) {
        case 'master':
        case 'normal':
            $isActive = 1;
            break;
        case 'payment_error':
            $isActive = 9;
            break;
        case 'dea':
            $isActive = 2;
            break;
        case 'inactive':
        default:
            $isActive = 0;
            break;
    }
    
    // Buscar email do usuário
    global $conn; // Assumindo que a conexão com DB está disponível
    $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $email = $row['email'];
        
        // Verificar se há MFA habilitado para master accounts
        $mfaEnabled = false;
        if ($isMaster) {
            $mfaStmt = $conn->prepare("SELECT mfa_is_enable FROM master_accounts WHERE username = ?");
            $mfaStmt->bind_param('s', $username);
            $mfaStmt->execute();
            $mfaResult = $mfaStmt->get_result();
            if ($mfaResult->num_rows === 1) {
                $mfaRow = $mfaResult->fetch_assoc();
                $mfaEnabled = ($mfaRow['mfa_is_enable'] == 1);
            }
        }
        
        // Gerar token para o usuário
        $tokenResult = $apiClient->generateTokenForUser(
            $username, 
            $email, 
            $isMaster, 
            $isActive, 
            $accountType,
            $mfaEnabled
        );
        
        if ($tokenResult['success']) {
            // Armazenar tokens na sessão para uso posterior
            $_SESSION['api_token'] = $tokenResult['token'];
            $_SESSION['api_refresh_token'] = $tokenResult['refresh_token'];
            $_SESSION['api_token_expires'] = $tokenResult['expires_at'];
            $_SESSION['api_user'] = $tokenResult['user'];
            
            return $tokenResult;
        } else {
            error_log('Failed to generate API token: ' . $tokenResult['message']);
            return [
                'success' => false,
                'message' => $tokenResult['message']
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'User not found'
    ];
}

/**
 * Obtém token da API da sessão atual
 */
function getAPIToken() {
    return $_SESSION['api_token'] ?? null;
}

/**
 * Obtém informações do usuário da sessão
 */
function getAPIUser() {
    return $_SESSION['api_user'] ?? null;
}

/**
 * Verifica se o token da API ainda é válido
 */
function isAPITokenValid() {
    $token = getAPIToken();
    if (!$token) {
        return false;
    }
    
    $expiresAt = $_SESSION['api_token_expires'] ?? null;
    if (!$expiresAt) {
        return false;
    }
    
    return strtotime($expiresAt) > time();
}

/**
 * Renovar token da API se necessário
 */
function refreshAPITokenIfNeeded() {
    if (isAPITokenValid()) {
        return true;
    }
    
    $refreshToken = $_SESSION['api_refresh_token'] ?? null;
    if (!$refreshToken) {
        return false;
    }
    
    $apiClient = new ProSecureAPIClient();
    $result = $apiClient->refreshToken($refreshToken);
    
    if ($result['success']) {
        $_SESSION['api_token'] = $result['token'];
        $_SESSION['api_refresh_token'] = $result['refresh_token'];
        $_SESSION['api_token_expires'] = $result['expires_at'];
        $_SESSION['api_user'] = $result['user'];
        return true;
    } else {
        // Limpar tokens inválidos
        unset($_SESSION['api_token']);
        unset($_SESSION['api_refresh_token']);
        unset($_SESSION['api_token_expires']);
        unset($_SESSION['api_user']);
        return false;
    }
}

/**
 * Limpar tokens da sessão (logout)
 */
function clearAPITokens() {
    unset($_SESSION['api_token']);
    unset($_SESSION['api_refresh_token']);
    unset($_SESSION['api_token_expires']);
    unset($_SESSION['api_user']);
}

/**
 * Exemplo de uso para atualização de cartão integrada
 */
function handleCardUpdate($email, $username, $cardData) {
    $apiClient = new ProSecureAPIClient();
    
    return $apiClient->updateCard(
        $email,
        $username,
        $cardData['card_name'],
        $cardData['card_number'],
        $cardData['expiry'],
        $cardData['cvv']
    );
}

/**
 * Middleware para verificar autenticação em páginas protegidas
 */
function requireAuthentication() {
    if (!isset($_SESSION['username']) || !isset($_SESSION['authorized']) || $_SESSION['authorized'] !== 'true') {
        header('Location: /users/index.php?err9=1');
        exit();
    }
    
    // Tentar renovar token se necessário
    if (!refreshAPITokenIfNeeded()) {
        // Se não conseguir renovar, redirecionar para login
        session_destroy();
        header('Location: /users/index.php?err9=1');
        exit();
    }
}

/**
 * Verificar se usuário tem permissão para função específica
 * RENOMEADA para evitar conflito com auth_middleware.php
 */
function checkAPIPermission($requiredAccountType) {
    $user = getAPIUser();
    if (!$user) {
        return false;
    }
    
    switch ($requiredAccountType) {
        case 'master':
            return $user['is_master'] === true;
        case 'active':
            return in_array($user['account_type'], ['master', 'normal']);
        case 'payment_error':
            return $user['account_type'] === 'payment_error';
        default:
            return true;
    }
}

/**
 * Função para incluir JavaScript de inicialização em páginas HTML
 * Call this function in HTML pages that need API client auto-initialization
 */
function includeAPIClientInit() {
    if (isset($_SESSION['api_token']) && isset($_SESSION['api_refresh_token'])) {
        echo '<script>';
        echo 'window.prosecureTokens = {';
        echo 'token: "' . $_SESSION['api_token'] . '",';
        echo 'refreshToken: "' . $_SESSION['api_refresh_token'] . '"';
        echo '};';
        echo '</script>';
        echo '<script src="https://prosecurelsp.com/users/prosecure-api-init.js"></script>';
    }
}

?>