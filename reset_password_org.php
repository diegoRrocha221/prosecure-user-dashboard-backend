<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();

if (!isset($_SESSION['password_reset_verified']) || $_SESSION['password_reset_verified'] !== true) {
    header('Location: ./forgot_password.php');
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
    <title>ProSecureLSP - Reset Password</title>
    
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

        .reset-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: slideInUp 0.6s ease-out;
        }

        .reset-card:hover {
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

        .password-requirements {
            background: var(--accent-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .password-requirements h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
            color: var(--text-light);
        }

        .password-requirements li {
            margin-bottom: 0.25rem;
        }

        .strength-meter {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: var(--danger-color); width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: var(--success-color); width: 100%; }

        .btn-reset {
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
            color: white;
        }

        .btn-reset:hover {
            background: linear-gradient(135deg, #198754 0%, var(--secondary-color) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(21, 115, 71, 0.3);
        }

        .btn-reset:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
            .reset-container {
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
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    
    <div class="reset-container">
        <div class="reset-card">
            <div class="logo-section">
                <img src="https://www.prosecurelsp.com/images/logo.png" alt="ProSecureLSP Logo">
            </div>
            
            <div class="form-section">
                <div class="welcome-text">
                    <h2>Create New Password</h2>
                    <p>Choose a strong password for your account</p>
                </div>

                <div class="email-info">
                    <strong>Account:</strong> <?php echo htmlspecialchars($maskedEmail); ?>
                </div>

                <div id="alertContainer"></div>

                <form id="resetPasswordForm">
                    <div class="form-floating password-container">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="New Password"
                               required>
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>New Password
                        </label>
                        <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </button>
                    </div>
                    <div class="strength-meter">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>

                    <div class="form-floating password-container">
                        <input type="password" 
                               class="form-control" 
                               id="confirmPassword" 
                               name="confirmPassword" 
                               placeholder="Confirm Password"
                               required>
                        <label for="confirmPassword">
                            <i class="fas fa-lock me-2"></i>Confirm Password
                        </label>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', 'toggleIcon2')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </button>
                    </div>

                    <div class="password-requirements">
                        <h6><i class="fas fa-info-circle me-2"></i>Password Requirements</h6>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Contains uppercase and lowercase letters</li>
                            <li>Contains at least one number</li>
                            <li>Contains at least one special character</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-reset" id="resetBtn">
                        <i class="fas fa-check-circle me-2"></i>
                        Reset Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const strengthBar = document.getElementById('strengthBar');
            strengthBar.className = 'strength-bar';
            
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        function validatePassword(password) {
            const minLength = password.length >= 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            return minLength && hasUpper && hasLower && hasNumber && hasSpecial;
        }

        document.getElementById('password').addEventListener('input', function(e) {
            checkPasswordStrength(e.target.value);
        });

        document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!validatePassword(password)) {
                showAlert('danger', 'Password does not meet the requirements');
                return;
            }
            
            if (password !== confirmPassword) {
                showAlert('danger', 'Passwords do not match');
                return;
            }
            
            const resetBtn = document.getElementById('resetBtn');
            resetBtn.disabled = true;
            resetBtn.innerHTML = '<span class="loading-spinner"></span>Resetting Password...';
            
            try {
                const response = await fetch('./password_reset_api.php?action=reset_password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        password: password,
                        confirm_password: confirmPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', data.message || 'Password reset successful! Redirecting...');
                    
                    setTimeout(() => {
                        window.location.href = '../index.php?scs=1';
                    }, 2000);
                } else {
                    showAlert('danger', data.message || 'Failed to reset password');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Connection error. Please try again.');
            } finally {
                resetBtn.disabled = false;
                resetBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Reset Password';
            }
        });

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

        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>