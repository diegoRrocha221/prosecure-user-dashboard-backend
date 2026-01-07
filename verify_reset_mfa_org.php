<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();

if (!isset($_SESSION['password_reset_email_verified']) || $_SESSION['password_reset_email_verified'] !== true) {
    header('Location: ./verify_reset_code.php');
    exit();
}

if (!isset($_SESSION['password_reset_mfa_required']) || $_SESSION['password_reset_mfa_required'] !== true) {
    header('Location: ./reset_password.php');
    exit();
}

$email = $_SESSION['password_reset_email'];
$maskedEmail = maskEmail($email);

function maskEmail($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    
    $nameLength = strlen($name);
    if ($nameLength <= 2) {
        $masked = $name[0] . str_repeat('*', $nameLength - 1);
    } else {
        $visibleChars = min(2, floor($nameLength / 3));
        $masked = substr($name, 0, $visibleChars) . str_repeat('*', $nameLength - $visibleChars);
    }
    
    return $masked . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProSecureLSP - SMS Verification</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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

        .verify-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .verify-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: slideInUp 0.6s ease-out;
        }

        .verify-card:hover {
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

        .verify-icon {
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

        .verify-icon i {
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

        .email-info {
            background: var(--accent-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .email-info strong {
            color: var(--primary-color);
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

        .btn-verify:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .resend-section {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .resend-section p {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 1rem;
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
        }

        .btn-resend:hover:not(:disabled) {
            background: var(--secondary-color);
            color: white;
        }

        .btn-resend:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: #ccc;
            color: #ccc;
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

        @media (max-width: 576px) {
            .verify-container {
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
    
    <div class="verify-container">
        <div class="verify-card">
            <div class="header-section">
                <div class="verify-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h2 class="header-title">SMS Verification</h2>
                <p class="header-subtitle">Enter the code sent to your phone</p>
            </div>
            
            <div class="form-section">
                <div class="email-info">
                    <strong>Account:</strong> <?php echo htmlspecialchars($maskedEmail); ?>
                </div>

                <div id="alertContainer"></div>
                
                <div class="text-center mb-4">
                    <h5 style="color: var(--primary-color); margin-bottom: 1rem;">Enter SMS Code</h5>
                    <p style="color: var(--text-light); font-size: 0.9rem;">
                        We've sent a 6-digit code to your registered phone
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

                <div class="resend-section">
                    <p>Didn't receive the SMS?</p>
                    <button type="button" class="btn-resend" id="resendBtn" onclick="resendCode()">
                        <i class="fas fa-redo me-1"></i>
                        Resend SMS
                    </button>
                    <div id="resendTimer" class="mt-2" style="display: none;">
                        <small class="text-muted">
                            Wait <span style="font-weight: 600; color: var(--secondary-color);" id="countdown">30</span> seconds
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let lastResendTime = 0;
        const RESEND_COOLDOWN = 30000; // 30 segundos

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.code-input');
            
            inputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    if (e.target.value.length === 1) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                        inputs[index - 1].focus();
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
                            if (inputs[i]) {
                                inputs[i].value = digit;
                            }
                        });
                        verifyCode();
                    }
                });
            });
            
            inputs[0].focus();
            
            // Enviar SMS automaticamente ao carregar a página
            sendInitialSMS();
        });

        async function sendInitialSMS() {
            try {
                const response = await fetch('./password_reset_api.php?action=send_mfa_code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', 'SMS code sent to your phone');
                    lastResendTime = Date.now();
                    startResendTimer();
                } else {
                    showAlert('danger', data.message || 'Failed to send SMS code');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Failed to send SMS. Click "Resend SMS" to try again.');
            }
        }

        function getCode() {
            let code = '';
            for (let i = 1; i <= 6; i++) {
                const input = document.getElementById('code' + i);
                code += input ? input.value : '';
            }
            return code;
        }

        function clearCodes() {
            for (let i = 1; i <= 6; i++) {
                const input = document.getElementById('code' + i);
                if (input) input.value = '';
            }
            document.getElementById('code1').focus();
        }

        async function verifyCode() {
            const code = getCode();
            
            if (code.length !== 6) {
                showAlert('danger', 'Please enter all 6 digits');
                return;
            }
            
            const verifyBtn = document.getElementById('verifyBtn');
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="loading-spinner"></span>Verifying...';
            
            try {
                const response = await fetch('./password_reset_api.php?action=verify_mfa_code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ code: code })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', data.message || 'SMS verified successfully!');
                    
                    setTimeout(() => {
                        window.location.href = './reset_password.php';
                    }, 1500);
                } else {
                    showAlert('danger', data.message || 'Invalid SMS code');
                    clearCodes();
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Connection error. Please try again.');
            } finally {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Verify Code';
            }
        }

        async function resendCode() {
            const now = Date.now();
            if (lastResendTime > 0 && (now - lastResendTime) < RESEND_COOLDOWN) {
                const remaining = Math.ceil((RESEND_COOLDOWN - (now - lastResendTime)) / 1000);
                showAlert('danger', `Please wait ${remaining} seconds before requesting a new code`);
                return;
            }
            
            const resendBtn = document.getElementById('resendBtn');
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<span class="loading-spinner"></span>Sending...';
            
            try {
                const response = await fetch('./password_reset_api.php?action=resend_mfa_code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', data.message || 'New SMS code sent');
                    clearCodes();
                    lastResendTime = Date.now();
                    startResendTimer();
                } else {
                    showAlert('danger', data.message || 'Failed to resend SMS');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Connection error. Please try again.');
            } finally {
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Resend SMS';
            }
        }

        function startResendTimer() {
            const resendBtn = document.getElementById('resendBtn');
            const resendTimer = document.getElementById('resendTimer');
            const countdown = document.getElementById('countdown');
            
            resendBtn.style.display = 'none';
            resendTimer.style.display = 'block';
            
            let timeLeft = 30;
            countdown.textContent = timeLeft;
            
            const interval = setInterval(() => {
                timeLeft--;
                countdown.textContent = timeLeft;
                
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    resendTimer.style.display = 'none';
                    resendBtn.style.display = 'inline-block';
                }
            }, 1000);
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success-custom' : 'alert-danger-custom';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass} alert-custom">
                    <i class="fas ${icon} me-2"></i>
                    ${message}
                </div>
            `;
            
            if (type === 'success') {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }
        }
    </script>
</body>
</html>