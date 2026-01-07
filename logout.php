<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();

// Log do logout para auditoria
if (isset($_SESSION['username'])) {
    error_log("User logout: " . $_SESSION['username'] . " at " . date('Y-m-d H:i:s'));
}

// Limpar todas as variáveis de sessão
session_unset();

// Destruir a sessão
session_destroy();

// Limpar cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Iniciar nova sessão limpa para mostrar mensagem de logout
session_start();
session_regenerate_id(true);

header('Location: https://prosecurelsp.com/users/intermediate_logout.php');
exit();
?>