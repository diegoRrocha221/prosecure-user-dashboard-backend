<?php
/**
 * Verificação de Login Atualizada - Usando Auth Middleware
 * Substitui o verify_login.php antigo
 */

require_once '/var/www/html/controllers/inc.sessions.php';
require_once 'prosecure-api-integration.php';
require_once 'auth_middleware.php';

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação usando novo middleware
if (!AuthMiddleware::requireAuth()) {
    // O middleware já faz o redirect apropriado
    exit();
}

// Para contas master, verificar se MFA foi completado
if (!AuthMiddleware::requireMFACompleted()) {
    // O middleware já faz o redirect para MFA se necessário
    exit();
}

// Se chegou até aqui, usuário está devidamente autenticado
// Opcionalmente, refreshar token JWT se necessário
refreshAPITokenIfNeeded();

?>