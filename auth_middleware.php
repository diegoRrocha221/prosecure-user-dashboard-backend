<?php
/**
 * Middleware de Autenticação JWT para ProSecure
 * Funções para verificar e validar tokens JWT em páginas PHP
 */

class AuthMiddleware {
    
    /**
     * Verificar se o usuário está autenticado (sessão + JWT)
     */
    public static function requireAuth($redirectUrl = '/users/index.php?err9=1') {
        // Verificar se há sessão PHP básica
        if (!isset($_SESSION['username']) || !isset($_SESSION['authorized']) || $_SESSION['authorized'] !== 'true') {
            self::redirectToLogin($redirectUrl);
            return false;
        }
        
        // Verificar se há token JWT válido
        if (!isAPITokenValid()) {
            // Tentar renovar o token
            if (!refreshAPITokenIfNeeded()) {
                // Se não conseguir renovar, fazer logout
                self::logout();
                self::redirectToLogin($redirectUrl);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verificar se o usuário é uma conta master
     */
    public static function requireMaster($redirectUrl = '/users/dashboard-not-admin/index.php') {
        if (!self::requireAuth()) {
            return false;
        }
        
        $user = getAPIUser();
        if (!$user || !$user['is_master']) {
            header("Location: $redirectUrl");
            exit();
        }
        
        return true;
    }
    
    /**
     * Verificar se o usuário tem tipo de conta específico
     */
    public static function requireAccountType($accountType, $redirectUrl = null) {
        if (!self::requireAuth()) {
            return false;
        }
        
        $user = getAPIUser();
        if (!$user || $user['account_type'] !== $accountType) {
            if ($redirectUrl) {
                header("Location: $redirectUrl");
                exit();
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar se o usuário tem conta ativa (não payment_error, não inactive)
     */
    public static function requireActiveAccount($redirectUrl = '/users/update_card.php') {
        if (!self::requireAuth()) {
            return false;
        }
        
        $user = getAPIUser();
        if (!$user) {
            return false;
        }
        
        if (in_array($user['account_type'], ['payment_error', 'inactive', 'dea'])) {
            if ($user['account_type'] === 'payment_error') {
                header("Location: /users/update_card.php");
            } else {
                header("Location: /users/index.php?err5=1");
            }
            exit();
        }
        
        return true;
    }
    
    /**
     * Permitir acesso apenas para contas com erro de pagamento
     */
    public static function requirePaymentError($redirectUrl = '/users/dashboard/index.php') {
        if (!self::requireAuth()) {
            return false;
        }
        
        $user = getAPIUser();
        if (!$user || $user['account_type'] !== 'payment_error') {
            header("Location: $redirectUrl");
            exit();
        }
        
        return true;
    }
    
    /**
     * Verificar se usuário está aguardando MFA
     */
    public static function requireMFAPending($redirectUrl = '/users/dashboard/index.php') {
        if (!isset($_SESSION['awaiting_mfa']) || $_SESSION['awaiting_mfa'] !== 'true') {
            header("Location: $redirectUrl");
            exit();
            return false;
        }
        
        if (!isset($_SESSION['username'])) {
            header("Location: /users/index.php?err9=1");
            exit();
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar se MFA foi completado para contas master
     */
    public static function requireMFACompleted() {
        if (!self::requireAuth()) {
            return false;
        }
        
        $user = getAPIUser();
        if ($user && $user['is_master'] && $user['mfa_enabled']) {
            if (isset($_SESSION['awaiting_mfa']) && $_SESSION['awaiting_mfa'] === 'true') {
                header("Location: /users/mfa_verification.php");
                exit();
            }
        }
        
        return true;
    }
    
    /**
     * Obter informações do usuário autenticado
     */
    public static function getAuthenticatedUser() {
        if (!self::requireAuth()) {
            return null;
        }
        
        return getAPIUser();
    }
    
    /**
     * Verificar se o usuário tem permissão específica
     */
    public static function hasPermission($permission) {
        $user = getAPIUser();
        if (!$user) {
            return false;
        }
        
        switch ($permission) {
            case 'admin':
            case 'master':
                return $user['is_master'] === true;
                
            case 'active':
                return in_array($user['account_type'], ['master', 'normal']);
                
            case 'payment_update':
                return $user['account_type'] === 'payment_error';
                
            case 'dashboard_access':
                return in_array($user['account_type'], ['master', 'normal']);
                
            default:
                return false;
        }
    }
    
    /**
     * Fazer logout completo (sessão + JWT)
     */
    public static function logout() {
        // Limpar tokens JWT
        clearAPITokens();
        
        // Limpar sessão PHP
        session_unset();
        session_destroy();
        
        // Iniciar nova sessão para mensagens
        session_start();
        $_SESSION['logout'] = 'true';
    }
    
    /**
     * Redirecionar para login
     */
    private static function redirectToLogin($url) {
        header("Location: $url");
        exit();
    }
    
    /**
     * Obter informações de debug da autenticação
     */
    public static function getAuthDebugInfo() {
        return [
            'session_username' => $_SESSION['username'] ?? null,
            'session_authorized' => $_SESSION['authorized'] ?? null,
            'session_admin' => $_SESSION['admin'] ?? null,
            'session_awaiting_mfa' => $_SESSION['awaiting_mfa'] ?? null,
            'has_jwt_token' => isset($_SESSION['api_token']),
            'jwt_token_valid' => isAPITokenValid(),
            'jwt_user' => getAPIUser(),
            'session_id' => session_id()
        ];
    }
    
    /**
     * Renderizar informações de debug (apenas desenvolvimento)
     */
    public static function renderDebugInfo($show = false) {
        if (!$show) return '';
        
        $debug = self::getAuthDebugInfo();
        ob_start();
        ?>
        <div style="position: fixed; top: 10px; right: 10px; background: rgba(0,0,0,0.9); color: white; padding: 10px; border-radius: 5px; font-size: 11px; max-width: 300px; z-index: 9999;">
            <strong>Auth Debug:</strong><br>
            Session User: <?php echo $debug['session_username'] ?: 'None'; ?><br>
            Authorized: <?php echo $debug['session_authorized'] ?: 'No'; ?><br>
            Admin: <?php echo $debug['session_admin'] ?: 'No'; ?><br>
            MFA Pending: <?php echo $debug['session_awaiting_mfa'] ?: 'No'; ?><br>
            JWT Token: <?php echo $debug['has_jwt_token'] ? 'Yes' : 'No'; ?><br>
            JWT Valid: <?php echo $debug['jwt_token_valid'] ? 'Yes' : 'No'; ?><br>
            <?php if ($debug['jwt_user']): ?>
            Account Type: <?php echo $debug['jwt_user']['account_type']; ?><br>
            Is Master: <?php echo $debug['jwt_user']['is_master'] ? 'Yes' : 'No'; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Funções auxiliares de conveniência (com nomes únicos)
 */

function authRequireAuth($redirectUrl = '/users/index.php?err9=1') {
    return AuthMiddleware::requireAuth($redirectUrl);
}

function authRequireMaster($redirectUrl = '/users/dashboard-not-admin/index.php') {
    return AuthMiddleware::requireMaster($redirectUrl);
}

function authRequireActiveAccount($redirectUrl = '/users/update_card.php') {
    return AuthMiddleware::requireActiveAccount($redirectUrl);
}

function authGetAuthenticatedUser() {
    return AuthMiddleware::getAuthenticatedUser();
}

function authHasPermission($permission) {
    return AuthMiddleware::hasPermission($permission);
}

function authLogout() {
    AuthMiddleware::logout();
}

/**
 * Função para incluir no início de páginas protegidas
 */
function protectedPage($requiredPermission = 'active', $redirectUrl = null) {
    switch ($requiredPermission) {
        case 'master':
            return authRequireMaster($redirectUrl);
        case 'payment_error':
            return AuthMiddleware::requirePaymentError($redirectUrl);
        case 'mfa_pending':
            return AuthMiddleware::requireMFAPending($redirectUrl);
        case 'active':
        default:
            return authRequireActiveAccount($redirectUrl);
    }
}

?>