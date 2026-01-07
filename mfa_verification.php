<?php
require_once '/var/www/html/controllers/inc.sessions.php';
require_once 'prosecure-api-integration.php';
require_once 'auth_middleware.php';
session_start();

// Verificar se usuário está logado e aguardando MFA
if (!AuthMiddleware::requireMFAPending()) {
    exit();
}

// Verificar se já tem token JWT gerado
$hasJWTToken = isset($_SESSION['api_token']) && isset($_SESSION['api_user']);
$userInfo = $hasJWTToken ? $_SESSION['api_user'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProSecureLSP - Multi-Factor Authentication</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #25364D;
            --secondary-color: #157347;
            --accent-color: #f8f9fa;
            --text-light: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
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

        .mfa-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .mfa-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .mfa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
        }

        .mfa-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
            z-index: 1;
        }

        .mfa-icon i {
            color: white;
            font-size: 1.5rem;
        }

        .header-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .header-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin: 0.5rem 0 0 0;
            position: relative;
            z-index: 1;
        }

        .form-section {
            padding: 2.5rem 2rem;
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

        .alert-warning-custom {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 4px solid var(--warning-color);
        }

        .code-input-container {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .code-input {
            width: 50px;
            height: 50px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(21, 115, 71, 0.15);
            background-color: #fff;
            outline: none;
        }

        .btn-verify {
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
            color: white;
        }

        .btn-verify:hover {
            background: linear-gradient(135deg, #198754 0%, var(--secondary-color) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(21, 115, 71, 0.3);
        }

        .btn-verify:active {
            transform: translateY(0);
        }

        .btn-verify:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-resend {
            background: transparent;
            border: 2px solid var(--secondary-color);
            color: var(--secondary-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-resend:hover {
            background: var(--secondary-color);
            color: white;
        }

        .resend-info {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .resend-info p {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .timer {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .user-info {
            background: var(--accent-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .user-info strong {
            color: var(--primary-color);
        }

        /* Animation */
        .mfa-card {
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

        /* Responsive */
        @media (max-width: 576px) {
            .mfa-container {
                padding: 1rem 0.5rem;
            }
            
            .form-section {
                padding: 2rem 1.5rem;
            }
            
            .code-input {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    
    <div class="mfa-container">
        <div class="mfa-card">
            <!-- Header Section -->
            <div class="header-section">
                <div class="mfa-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="header-title">Two-Factor Authentication</h2>
                <p class="header-subtitle">Please verify your identity to continue</p>
            </div>
            
            <!-- Form Section -->
            <div class="form-section">
                <!-- User Info -->
                <?php if ($hasJWTToken && $userInfo): ?>
                <div class="user-info">
                    <strong>Welcome:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?><br>
                    <strong>Account Type:</strong> <?php echo ucfirst($userInfo['account_type']); ?><br>
                    <strong>MFA Status:</strong> <span class="text-success">Required</span>
                </div>
                <?php endif; ?>

                <!-- Alert Messages -->
                <div id="alertContainer"></div>
                
                <!-- MFA Code Input -->
                <div class="text-center mb-4">
                    <h5 style="color: var(--primary-color); margin-bottom: 1rem;">Enter Verification Code</h5>
                    <p style="color: var(--text-light); font-size: 0.9rem;">
                        We've sent a 6-digit code to your registered phone number
                    </p>
                </div>

                <div class="code-input-container">
                    <input type="text" class="code-input" maxlength="1" id="code1" />
                    <input type="text" class="code-input" maxlength="1" id="code2" />
                    <input type="text" class="code-input" maxlength="1" id="code3" />
                    <input type="text" class="code-input" maxlength="1" id="code4" />
                    <input type="text" class="code-input" maxlength="1" id="code5" />
                    <input type="text" class="code-input" maxlength="1" id="code6" />
                </div>

                <button type="button" class="btn btn-verify" id="verifyBtn" onclick="verifyCode()">
                    <i class="fas fa-check-circle me-2"></i>
                    Verify Code
                </button>

                <div class="resend-info">
                    <p>Didn't receive the code?</p>
                    <button type="button" class="btn-resend" id="resendBtn" onclick="resendCode()">
                        <i class="fas fa-redo me-1"></i>
                        Resend Code
                    </button>
                    <div id="resendTimer" class="mt-2" style="display: none;">
                        <small class="text-muted">
                            Please wait <span class="timer" id="countdown">45</span> seconds before requesting a new code
                        </small>
                    </div>
                </div>

                <!-- Debug Info (desenvolvimento) -->
                <?php if ($hasJWTToken): ?>
                <div class="mt-3" style="font-size: 0.8rem; color: #666; text-align: center;">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        API Integration Active | Token expires: <?php echo date('H:i:s', strtotime($_SESSION['api_token_expires'])); ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- API Client -->
    <script src="prosecure-api-client.js"></script>
    
    <!-- API Initialization -->
    <?php includeAPIClientInit(); ?>
    
    <script>
        let resendTimeout;
        let countdownInterval;
        let lastCodeSent = 0; // Timestamp do último código enviado
        const RESEND_COOLDOWN = 45000; // 45 segundos em ms

        // Auto-focus e navegação entre inputs
        document.addEventListener('DOMContentLoaded', function() {
            const codeInputs = document.querySelectorAll('.code-input');
            
            codeInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    if (e.target.value.length === 1) {
                        if (index < codeInputs.length - 1) {
                            codeInputs[index + 1].focus();
                        }
                    }
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                        codeInputs[index - 1].focus();
                    }
                    
                    if (e.key === 'Enter') {
                        verifyCode();
                    }
                });
                
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text');
                    if (pastedData.length === 6 && /^\d+$/.test(pastedData)) {
                        pastedData.split('').forEach((digit, i) => {
                            if (codeInputs[i]) {
                                codeInputs[i].value = digit;
                            }
                        });
                        verifyCode();
                    }
                });
            });
            
            // Focus no primeiro input
            codeInputs[0].focus();
            
            // Verificar se devemos carregar status MFA automaticamente
            // Só carrega se for o primeiro acesso (não refresh)
            checkInitialMFAStatus();
        });

        // Função para verificar se deve carregar MFA status automaticamente
        function checkInitialMFAStatus() {
            // Usar performance.navigation para detectar se é refresh
            const navigationType = performance.navigation ? performance.navigation.type : 0;
            const isRefresh = navigationType === 1; // TYPE_RELOAD
            
            // Alternativamente, usar performance.getEntriesByType
            const navEntries = performance.getEntriesByType('navigation');
            const isPageReload = navEntries.length > 0 && navEntries[0].type === 'reload';
            
            if (isRefresh || isPageReload) {
                console.log('Page refresh detected, skipping automatic code request');
                showAlert('warning', 'Page refreshed. Click "Resend Code" if you need a new verification code.');
                return;
            }
            
            // Se não é refresh, carregar status normalmente
            setTimeout(checkMFAStatus, 1000);
        }

        async function checkMFAStatus() {
            try {
                const response = await fetch('./mfa_api.php?action=check_2fa');
                
                // Verificar se a resposta é OK
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Obter texto da resposta para debug
                const responseText = await response.text();
                
                // Tentar fazer parse do JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data.error) {
                    showAlert('warning', data.message || 'Unable to check MFA status');
                } else if (data.success) {
                    showAlert('success', data.message || 'Verification code sent to your phone');
                    // Marcar timestamp de envio
                    lastCodeSent = Date.now();
                    startResendTimer();
                }
            } catch (error) {
                console.error('Error checking MFA status:', error);
                showAlert('danger', 'Connection error. Click "Resend Code" to request a verification code.');
            }
        }

        async function verifyCode() {
            const code = getCodeFromInputs();
            
            if (code.length !== 6) {
                showAlert('warning', 'Please enter all 6 digits');
                return;
            }
            
            const verifyBtn = document.getElementById('verifyBtn');
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="loading-spinner"></span>Verifying...';
            
            try {
                const response = await fetch('./mfa_api.php?action=verify_code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ code: code })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data.error) {
                    showAlert('danger', data.message || 'Verification failed');
                    clearCodeInputs();
                } else if (data.success) {
                    showAlert('success', data.message || 'Verification successful! Redirecting...');
                    
                    // Redirecionar após sucesso
                    setTimeout(() => {
                        window.location.href = data.redirect || './dashboard/index.php';
                    }, 1500);
                } else {
                    showAlert('danger', 'Unexpected response format');
                }
            } catch (error) {
                console.error('Error verifying code:', error);
                showAlert('danger', 'Connection error. Please try again.');
            } finally {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Verify Code';
            }
        }

        async function resendCode() {
            // Verificar cooldown
            const now = Date.now();
            if (lastCodeSent > 0 && (now - lastCodeSent) < RESEND_COOLDOWN) {
                const remaining = Math.ceil((RESEND_COOLDOWN - (now - lastCodeSent)) / 1000);
                showAlert('warning', `Please wait ${remaining} seconds before requesting a new code.`);
                return;
            }
            
            const resendBtn = document.getElementById('resendBtn');
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<span class="loading-spinner"></span>Sending...';
            
            try {
                const response = await fetch('./mfa_api.php?action=resend_code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const responseText = await response.text();
                let data;
                
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data.error) {
                    showAlert('danger', data.message || 'Failed to resend code');
                } else {
                    showAlert('success', data.message || 'New verification code sent');
                    clearCodeInputs();
                    // Atualizar timestamp do último envio
                    lastCodeSent = Date.now();
                    startResendTimer();
                }
            } catch (error) {
                console.error('Error resending code:', error);
                showAlert('danger', 'Connection error. Please try again.');
            } finally {
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Resend Code';
            }
        }

        function getCodeFromInputs() {
            const codeInputs = document.querySelectorAll('.code-input');
            return Array.from(codeInputs).map(input => input.value).join('');
        }

        function clearCodeInputs() {
            const codeInputs = document.querySelectorAll('.code-input');
            codeInputs.forEach(input => {
                input.value = '';
            });
            codeInputs[0].focus();
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = `alert-${type === 'warning' ? 'warning' : type === 'success' ? 'success' : 'danger'}-custom`;
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass} alert-custom">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `;
            
            // Auto-remove success alerts
            if (type === 'success') {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }
        }

        function startResendTimer() {
            const resendBtn = document.getElementById('resendBtn');
            const resendTimer = document.getElementById('resendTimer');
            const countdown = document.getElementById('countdown');
            
            resendBtn.style.display = 'none';
            resendTimer.style.display = 'block';
            
            let timeLeft = 45; // 45 segundos
            countdown.textContent = timeLeft;
            
            countdownInterval = setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    resendTimer.style.display = 'none';
                    resendBtn.style.display = 'inline-block';
                }
            }, 1000);
        }

        // Log de status da integração
        console.log('MFA Verification - JWT Integration Status:', {
            hasToken: <?php echo $hasJWTToken ? 'true' : 'false'; ?>,
            username: '<?php echo $_SESSION['username']; ?>',
            accountType: '<?php echo $userInfo['account_type'] ?? 'unknown'; ?>',
            mfaEnabled: <?php echo ($userInfo['mfa_enabled'] ?? false) ? 'true' : 'false'; ?>
        });
    </script>
</body>
</html>