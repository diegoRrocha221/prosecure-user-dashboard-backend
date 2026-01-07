<?php
require_once '/var/www/html/has_login.php';
require_once '/var/www/html/controllers/inc.sessions.php';
require_once '/var/www/html/controllers/hashing_lsp.php';
require_once './prosecure-api-integration.php'; // NOVA INTEGRAÇÃO
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('users.php');
require_once('database_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    session_unset();

    $db = new DatabaseConnection();
    $conn = $db->getConnection();

    $user = new User($username, $password);
    $authResult = $user->authenticate($conn);

    if ($authResult === 'master' || $authResult === 'normal' || $authResult === 'payment_error') {
        session_regenerate_id(true);
        $_SESSION['username'] = $user->getUsername();
        $_SESSION['admin'] = ($authResult === 'master') ? 'true' : 'false';
        
        // NOVA INTEGRAÇÃO: Gerar token JWT após autenticação bem-sucedida
        $tokenResult = integrateAPIAuthentication($user->getUsername(), $user->getPassword(), $authResult);
        
        if ($tokenResult['success']) {
            error_log('API token generated successfully for user: ' . $user->getUsername());
            
            // Log das informações do token para debug
            error_log('Token expires at: ' . $tokenResult['expires_at']);
            error_log('User account type: ' . $tokenResult['user']['account_type']);
        } else {
            error_log('Failed to generate API token for user: ' . $user->getUsername() . ' - ' . $tokenResult['message']);
            // Continua o fluxo mesmo sem token (compatibilidade)
        }
        
        // Continuar com o fluxo normal baseado no tipo de conta
        if ($authResult === 'master') {
            error_log("DEBUG - Redirecting to MFA verification");
            $_SESSION['awaiting_mfa'] = 'true';
            $_SESSION['authorized'] = 'false'; // Não autorizado até completar MFA
            header("Location: ./mfa_verification.php");
            //var_dump($_SESSION);
            //echo "MASTER";
            exit();
        } else if ($authResult === 'payment_error') {
            error_log("DEBUG - Redirecting to update_card.php");
            $_SESSION['payment_error'] = 'true';
            $_SESSION['authorized'] = 'true';
            header("Location: ./update_card.php");
            //var_dump($_SESSION);
            //echo "PAY ERRO";
            exit();
        } else {
            // Para contas normais, acesso direto
            error_log("DEBUG - Redirecting to dashboard-not-admin (normal user)");
            $_SESSION['authorized'] = 'true';
            header("Location: ./dashboard-not-admin/index.php");
            //var_dump($_SESSION);
            //echo "NOT MASTER";
            exit();
        }
    } elseif ($authResult === 'validating') {
        // Novo status: informações sendo validadas
        $_SESSION['username'] = $user->getUsername();
        $_SESSION['validating'] = 'true';
        header("Location: ./validating.php");
        exit();
    } elseif ($authResult === 'inactive' || $authResult === 'dea') {
        header('Location: ./index.php?err' . (($authResult === 'inactive') ? '3' : '5') . '=1');
        exit();
    } else {
        $_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1;
        header("Location: ./index.php?err1=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- Google tag (gtag.js) --> <script async src="https://www.googletagmanager.com/gtag/js?id=G-5FCSD1BMSL"></script> <script> window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', 'G-5FCSD1BMSL'); </script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProSecureLSP - Sign In</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- hCaptcha -->
    <script src="https://hcaptcha.com/1/api.js" async defer></script>
    
    <style>
        :root {
            --primary-color: #25364D;
            --secondary-color: #157347;
            --accent-color: #f8f9fa;
            --text-light: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .background-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            pointer-events: none;
            z-index: 0;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        .logo-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .logo-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
        }

        .logo-section img {
            max-width: 200px;
            height: auto;
            filter: brightness(1.1);
            position: relative;
            z-index: 1;
        }

        .form-section {
            padding: 2.5rem 2rem;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 2rem;
        }

        .welcome-text h2 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            padding: 0.875rem 1rem;
        }

        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger-custom {
            background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(21, 115, 71, 0.15);
            background-color: #fff;
        }

        .form-floating > label {
            color: var(--text-light);
            font-weight: 500;
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 0.9rem;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--secondary-color);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #198754 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #198754 0%, var(--secondary-color) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(21, 115, 71, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .form-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .form-links a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-links a:hover {
            color: var(--primary-color);
        }

        .captcha-container {
            display: flex;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .footer-links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .footer-links a {
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.9rem;
            margin: 0 0.5rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--secondary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-container {
                padding: 1rem 0.5rem;
            }
            
            .form-section {
                padding: 2rem 1.5rem;
            }
            
            .logo-section {
                padding: 1.5rem;
            }
            
            .logo-section img {
                max-width: 160px;
            }
        }

        /* Animation for form appearance */
        .login-card {
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading state for button */
        .btn-login.loading {
            position: relative;
            color: transparent;
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* NOVO: Estilo para debug info (só visível em desenvolvimento) */
        .debug-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            max-width: 300px;
            z-index: 1000;
            display: none; /* Oculto por padrão */
        }
        
        .debug-info.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    
    <!-- Debug Info (apenas para desenvolvimento) -->
    <div class="debug-info" id="debugInfo">
        <strong>Debug Info:</strong><br>
        API Integration: <?php echo isset($_SESSION['api_token']) ? 'Active' : 'Inactive'; ?><br>
        Session: <?php echo session_id(); ?><br>
        <?php if (isset($_SESSION['api_user'])): ?>
        User Type: <?php echo $_SESSION['api_user']['account_type']; ?><br>
        MFA: <?php echo $_SESSION['api_user']['mfa_enabled'] ? 'Enabled' : 'Disabled'; ?>
        <?php endif; ?>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <img src="https://www.prosecurelsp.com/images/logo.png" alt="ProSecureLSP Logo">
            </div>
            
            <!-- Form Section -->
            <div class="form-section">
                <div class="welcome-text">
                    <h2>Welcome Back</h2>
                    <p>Sign in to access your security dashboard</p>
                </div>

                <!-- Success Messages -->
                <?php if(isset($_GET['regis'])): ?>
                    <div class="alert alert-success-custom alert-custom">
                        <i class="fas fa-check-circle me-2"></i>
                        Success! Your account has been created
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['scs'])): ?>
                    <div class="alert alert-success-custom alert-custom">
                        <i class="fas fa-check-circle me-2"></i>
                        Password updated successfully
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['scs1'])): ?>
                    <div class="alert alert-success-custom alert-custom">
                        <i class="fas fa-check-circle me-2"></i>
                        Account activated successfully
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['scs2'])): ?>
                    <div class="alert alert-success-custom alert-custom">
                        <i class="fas fa-check-circle me-2"></i>
                        Information updated successfully
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['mfa'])): ?>
                    <div class="alert alert-success-custom alert-custom">
                        <i class="fas fa-shield-alt me-2"></i>
                        Multi-factor Authentication enabled
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if(isset($_GET['err'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Invalid username or passphrase
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['err3'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-envelope me-2"></i>
                        Please confirm your email address via the link sent to you before logging in. Check your inbox
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['err5'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-user-slash me-2"></i>
                        Account inactive
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['err6'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        We were unable to validate your information, please try again
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['err7'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-credit-card me-2"></i>
                        Your payment method was declined. Please update your card information to continue using our services.
                    </div>
                <?php endif; ?>
                                
                <?php if(isset($_GET['err8'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Payment processing error. Please contact support if this issue persists.
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['err10'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-robot me-2"></i>
                        Invalid Captcha
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['err9'])): ?>
                    <div class="alert alert-danger-custom alert-custom">
                        <i class="fas fa-clock me-2"></i>
                        Session expired, please log in again
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['logout'])): ?>
                    <div class="alert alert-success-custom alert-custom">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        You have been logged out successfully
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form class="login-form" method="post" action="./index.php" id="loginForm">
                    <div class="form-floating">
                        <input type="email" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="name@example.com"
                               value="<?php if (isset($_GET['us'])){echo urldecode(decrypt_lsp(($_GET['us']))); } ?>"
                               required>
                        <label for="username">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                    </div>

                    <div class="form-floating password-container">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Password"
                               required>
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>Passphrase
                        </label>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <div class="form-links">
                            <p class="mb-0">
                                <i class="fas fa-question-circle me-2"></i>
                                Forgot your passphrase? 
                                <a href="./1758464/forgot_password.php">Click here</a>
                            </p>
                    </div>
                        <?php if(isset($_SESSION['attempts']) && $_SESSION['attempts'] > 0): ?>
                            <div class="captcha-container">
                                <div class="h-captcha" data-sitekey="2895684a-7158-4357-8c78-18da7039ff4f"></div>
                            </div>      
                        <?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </button>
                </form>

                <div class="footer-links">
                    <a href="../">
                        <i class="fas fa-user-plus me-1"></i>
                        Create an account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- NOVA INTEGRAÇÃO: Include do cliente JavaScript da API -->
    <script src="./prosecure-api-client.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById("password");
            const toggleIcon = document.getElementById("toggleIcon");
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.className = "fas fa-eye-slash";
            } else {
                passwordField.type = "password";
                toggleIcon.className = "fas fa-eye";
            }
        }

        // Add loading state to login button
        document.getElementById('loginForm').addEventListener('submit', function() {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
        });

        // User agent logging (original functionality)
        var userAgent = navigator.userAgent;
        console.log("User-Agent: " + userAgent);

        // NOVA FUNCIONALIDADE: Debug toggle (apenas desenvolvimento)
        document.addEventListener('keydown', function(e) {
            // Ctrl + Shift + D para mostrar/ocultar debug info
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                const debugInfo = document.getElementById('debugInfo');
                debugInfo.classList.toggle('show');
            }
        });

        // Add smooth focus transitions
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Keyboard navigation enhancement
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                const form = document.getElementById('loginForm');
                const firstInvalidField = form.querySelector(':invalid');
                if (!firstInvalidField) {
                    form.submit();
                } else {
                    firstInvalidField.focus();
                }
            }
        });

        // NOVA FUNCIONALIDADE: Log de status da integração
        console.log('ProSecure API Integration Status:', {
            hasToken: <?php echo isset($_SESSION['api_token']) ? 'true' : 'false'; ?>,
            userType: '<?php echo isset($_SESSION['api_user']) ? $_SESSION['api_user']['account_type'] : 'none'; ?>',
            mfaEnabled: <?php echo isset($_SESSION['api_user']) && $_SESSION['api_user']['mfa_enabled'] ? 'true' : 'false'; ?>
        });
    </script>
</body>
</html>