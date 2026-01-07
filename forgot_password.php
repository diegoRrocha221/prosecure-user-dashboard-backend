<?php
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();

unset($_SESSION['password_reset_email']);
unset($_SESSION['password_reset_verified']);
unset($_SESSION['password_reset_username']);
unset($_SESSION['password_reset_master_username']);
unset($_SESSION['password_reset_is_master']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProSecureLSP - Forgot Password</title>
    
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

        .form-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
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
                    <h2>Reset Your Password</h2>
                    <p>Enter your email address and we'll send you an SMS code</p>
                </div>

                <div id="alertContainer"></div>

                <form id="resetForm">
                    <div class="form-floating">
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               placeholder="name@example.com"
                               required>
                        <label for="email">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                    </div>

                    <button type="submit" class="btn btn-reset" id="resetBtn">
                        <i class="fas fa-mobile-alt me-2"></i>
                        Send SMS Code
                    </button>
                </form>

                <div class="form-links">
                    <p class="mb-0">
                        <i class="fas fa-arrow-left me-2"></i>
                        Remember your password? 
                        <a href="./index.php">Back to Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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

        document.getElementById('resetForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const resetBtn = document.getElementById('resetBtn');
            
            if (!email) {
                showAlert('danger', 'Please enter your email address');
                return;
            }
            
            resetBtn.disabled = true;
            resetBtn.innerHTML = '<span class="loading-spinner"></span>Sending...';
            
            try {
                const response = await fetch('./password_reset_api.php?action=request_reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', data.message || 'SMS code sent to registered phone');
                    
                    setTimeout(() => {
                        window.location.href = './verify_reset_code.php';
                    }, 2000);
                } else {
                    showAlert('danger', data.message || 'Failed to send SMS code');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Connection error. Please try again.');
            } finally {
                resetBtn.disabled = false;
                resetBtn.innerHTML = '<i class="fas fa-mobile-alt me-2"></i>Send SMS Code';
            }
        });

        document.getElementById('email').addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        document.getElementById('email').addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    </script>
</body>
</html>