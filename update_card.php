<?php
require_once '/var/www/html/has_login.php';
require_once '/var/www/html/controllers/inc.sessions.php';
session_start();

// Verificar se usuário está autorizado e tem erro de pagamento
if (!isset($_SESSION['username']) || !isset($_SESSION['payment_error'])) {
    header('Location: ./index.php?err9=1');
    exit();
}

require_once('users.php');
require_once('database_connection.php');

$db = new DatabaseConnection();
$conn = $db->getConnection();

$user = new User($_SESSION['username'], ''); // Password não é necessário aqui
$paymentInfo = $user->getPaymentErrorInfo($conn);

if (!$paymentInfo) {
    // Se não há erro de pagamento, redirecionar para dashboard
    header('Location: ./dashboard/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProSecureLSP - Update Payment Method</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <style>
        :root {
            --primary-color: #25364D;
            --secondary-color: #157347;
            --accent-color: #f8f9fa;
            --text-light: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #fd7e14;
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

        .main-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 700px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .update-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: slideInUp 0.6s ease-out;
        }

        .header-section {
            background: linear-gradient(135deg, var(--danger-color) 0%, #e74c3c 100%);
            padding: 2rem;
            text-align: center;
            color: white;
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

        .header-section .logo {
            max-width: 180px;
            height: auto;
            filter: brightness(1.1);
            position: relative;
            z-index: 1;
            margin-bottom: 1rem;
        }

        .header-section h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .header-section p {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .content-section {
            padding: 2.5rem 2rem;
        }

        .alert-section {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: none;
            border-left: 4px solid var(--warning-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #856404;
        }

        .account-info {
            background: var(--accent-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid #e9ecef;
        }

        .account-info h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-light);
        }

        .info-value {
            font-weight: 600;
            color: var(--primary-color);
        }

        .form-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
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

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-floating {
            flex: 1;
        }

        .btn-update {
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

        .btn-update:hover {
            background: linear-gradient(135deg, #198754 0%, var(--secondary-color) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(21, 115, 71, 0.3);
            color: white;
        }

        .btn-update:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--text-light) 0%, #6c757d 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #5a6268 0%, var(--text-light) 100%);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            max-width: 350px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--secondary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        .progress-bar-container {
            background-color: #e9ecef;
            border-radius: 10px;
            padding: 3px;
            margin: 1rem 0;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--secondary-color), #198754);
            height: 10px;
            border-radius: 7px;
            transition: width 0.3s ease;
            width: 0%;
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

        .alert-custom {
            border-radius: 12px;
            border: none;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            padding: 0.875rem 1rem;
            display: none;
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

        @media (max-width: 576px) {
            .main-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }
            
            .content-section {
                padding: 2rem 1.5rem;
            }
            
            .header-section {
                padding: 1.5rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    
    <div class="main-container">
        <div class="update-card">
            <!-- Header Section -->
            <div class="header-section">
                <img src="https://www.prosecurelsp.com/images/logo.png" alt="ProSecureLSP Logo" class="logo">
                <h1><i class="fas fa-exclamation-triangle me-2"></i>Payment Method Update Required</h1>
                <p>We need to update your payment information to continue your service</p>
            </div>
            
            <!-- Content Section -->
            <div class="content-section">
                <!-- Alert Section -->
                <div class="alert-section">
                    <h5><i class="fas fa-credit-card me-2"></i>Action Required</h5>
                    <p class="mb-0">
                        Your payment method was declined during our last billing attempt. 
                        Please update your card information below to reactivate your account and continue enjoying our security services.
                    </p>
                </div>

                <!-- Account Information -->
                <div class="account-info">
                    <h5><i class="fas fa-user me-2"></i>Account Information</h5>
                    <div class="info-item">
                        <span class="info-label">Account Holder:</span>
                        <span class="info-value"><?php echo htmlspecialchars($paymentInfo['name'] . ' ' . $paymentInfo['lname']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($paymentInfo['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username:</span>
                        <span class="info-value"><?php echo htmlspecialchars($paymentInfo['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Monthly Amount:</span>
                        <span class="info-value">$<?php echo number_format($paymentInfo['total_price'], 2); ?></span>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div id="successAlert" class="alert alert-success-custom alert-custom">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="successMessage"></span>
                </div>
                
                <div id="errorAlert" class="alert alert-danger-custom alert-custom">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="errorMessage"></span>
                </div>

                <!-- Update Form -->
                <div class="form-section">
                    <h4><i class="fas fa-credit-card me-2"></i>Update Payment Method</h4>
                    
                    <form id="updateCardForm">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control" 
                                   id="cardName" 
                                   placeholder="Cardholder Name"
                                   required>
                            <label for="cardName">
                                <i class="fas fa-user me-2"></i>Cardholder Name
                            </label>
                        </div>

                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control" 
                                   id="cardNumber" 
                                   placeholder="Card Number"
                                   maxlength="19"
                                   required>
                            <label for="cardNumber">
                                <i class="fas fa-credit-card me-2"></i>Card Number
                            </label>
                        </div>

                        <div class="form-row">
                            <div class="form-floating">
                                <input type="text" 
                                       class="form-control" 
                                       id="expiry" 
                                       placeholder="MM/YY"
                                       maxlength="5"
                                       required>
                                <label for="expiry">
                                    <i class="fas fa-calendar me-2"></i>Expiry Date
                                </label>
                            </div>
                            <div class="form-floating">
                                <input type="text" 
                                       class="form-control" 
                                       id="cvv" 
                                       placeholder="CVV"
                                       maxlength="4"
                                       required>
                                <label for="cvv">
                                    <i class="fas fa-lock me-2"></i>CVV
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-update" id="updateBtn">
                            <i class="fas fa-sync-alt me-2"></i>
                            Update Payment Method
                        </button>
                    </form>

                    <div class="text-center">
                        <a href="../index.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt me-2"></i>Go Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h5 id="loadingTitle">Processing Payment Update</h5>
            <p id="loadingMessage">Please wait while we securely update your payment method...</p>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <small id="progressText">This may take up to 90 seconds</small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
const CONFIG = {
    MAX_RETRIES: 1,
    RETRY_DELAY: 10000,           // 10 segundos
    REQUEST_TIMEOUT: 600000,      // 10 MINUTOS (600 segundos)
    PROGRESS_UPDATE_INTERVAL: 2000, // Atualizar a cada 2 segundos
    TIMEOUT_CHECK_ENABLED: true,
    BACKGROUND_CHECK_DELAY: 15000,  // 15 segundos
};

// Estado global para controle de retry
let retryState = {
    currentAttempt: 0,
    isRetrying: false,
    startTime: null
};

$(document).ready(function() {
    console.log('Update Card JavaScript loaded');
    
    // Format card number input
    $('#cardNumber').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        let formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
        if (formattedValue.length > 19) formattedValue = formattedValue.substr(0, 19);
        $(this).val(formattedValue);
    });
    
    // Format expiry date input
    $('#expiry').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substr(0, 2) + '/' + value.substr(2, 2);
        }
        $(this).val(value);
    });
    
    // Format CVV input
    $('#cvv').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, ''));
    });

    // CORRIGIDO: Event listener principal com melhor debugging
    $('#updateCardForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        
        const formData = {
            email: '<?php echo htmlspecialchars($paymentInfo['email']); ?>',
            username: '<?php echo htmlspecialchars($paymentInfo['username']); ?>',
            card_name: $('#cardName').val(),
            card_number: $('#cardNumber').val().replace(/\s/g, ''),
            expiry: $('#expiry').val(),
            cvv: $('#cvv').val()
        };
        
        console.log('Form data prepared:', {
            email: formData.email,
            username: formData.username,
            card_name: formData.card_name,
            has_card_number: formData.card_number.length > 0,
            has_expiry: formData.expiry.length > 0,
            has_cvv: formData.cvv.length > 0
        });
        
        if (!validateForm(formData)) {
            return;
        }
        
        // Verificar conectividade antes de processar
        console.log('Starting payment update process');
        updateCard(formData);
    });
});

// FUNÇÃO PRINCIPAL CORRIGIDA
function updateCard(formData) {
    console.log('updateCard called');
    
    retryState.currentAttempt = 0;
    retryState.isRetrying = false;
    retryState.startTime = Date.now();
    
    // CRÍTICO: Mostrar loading IMEDIATAMENTE
    showLoading(true);
    $('#updateBtn').prop('disabled', true);
    
    console.log('Loading shown, button disabled');
    
    performUpdateWithRetry(formData);
}

// FUNÇÃO CORRIGIDA: Executar update com retry
function performUpdateWithRetry(formData) {
    retryState.currentAttempt++;
    console.log('performUpdateWithRetry - Attempt:', retryState.currentAttempt);
    
    const isRetry = retryState.currentAttempt > 1;
    
    if (isRetry) {
        retryState.isRetrying = true;
        updateLoadingMessage(`Attempting to reconnect... (Attempt ${retryState.currentAttempt}/${CONFIG.MAX_RETRIES + 1})`);
        $('#progressText').text('Retrying connection to payment service...');
        
        // Delay antes do retry
        setTimeout(() => {
            executeUpdateRequest(formData);
        }, CONFIG.RETRY_DELAY);
    } else {
        executeUpdateRequest(formData);
    }
}

// FUNÇÃO CORRIGIDA: Executar a requisição
function executeUpdateRequest(formData) {
    const isRetry = retryState.currentAttempt > 1;
    console.log('executeUpdateRequest - isRetry:', isRetry, 'Timeout:', CONFIG.REQUEST_TIMEOUT);
    
    // Atualizar UI
    if (!isRetry) {
        updateLoadingMessage('Connecting to payment service...');
        startProgressMonitoring();
    } else {
        updateLoadingMessage(`Retrying payment update... (${retryState.currentAttempt}/${CONFIG.MAX_RETRIES + 1})`);
    }
    
    // Preparar dados para envio
    const postData = {
        action: 'update_card',
        email: formData.email,
        username: formData.username,
        card_name: formData.card_name,
        card_number: formData.card_number,
        expiry: formData.expiry,
        cvv: formData.cvv
    };
    
    console.log('Sending AJAX request with 10 minute timeout...');
    
    // JQUERY AJAX COM TIMEOUT DE 10 MINUTOS
    $.ajax({
        url: 'api_handler.php',
        method: 'POST',
        data: postData,
        timeout: CONFIG.REQUEST_TIMEOUT, // 10 MINUTOS
        dataType: 'json'
    })
    .done(function(data) {
        console.log('AJAX success after', ((Date.now() - retryState.startTime) / 1000).toFixed(1), 'seconds:', data);
        handleUpdateResponse(data, formData);
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        const elapsed = ((Date.now() - retryState.startTime) / 1000).toFixed(1);
        console.log('AJAX failed after', elapsed, 'seconds:', textStatus, errorThrown, jqXHR);
        
        // Criar objeto error compatível
        let error;
        if (textStatus === 'timeout') {
            error = new Error('Request timeout after 10 minutes');
            error.name = 'AbortError';
        } else if (textStatus === 'error') {
            error = new Error(`HTTP ${jqXHR.status}: ${jqXHR.statusText || 'Network Error'}`);
        } else {
            error = new Error(`Request failed: ${textStatus}`);
        }
        
        handleUpdateError(error, formData);
    });
}

// FUNÇÃO CORRIGIDA: Lidar com resposta de sucesso/erro
function handleUpdateResponse(response, formData) {
    console.log('handleUpdateResponse called:', response);
    
    $('#progressBar').css('width', '100%');
    updateLoadingMessage('Update completed!');
    
    setTimeout(() => {
        showLoading(false);
        $('#updateBtn').prop('disabled', false);
        
        console.log('Response success check:', response.success, typeof response.success);
        
        // CORRIGIDO: Verificar sucesso de múltiplas formas
        const isSuccess = response.success === true || 
                          response.success === 'true' || 
                          response.status === 'success';
        
        if (isSuccess) {
            console.log('Success detected, showing success message');
            
            showSuccess(response.message || 'Payment method updated successfully!');
            $('#updateCardForm')[0].reset();
            
            console.log('Form reset, scheduling redirect in 3 seconds');
            
            // Redirecionar após 3 segundos
            setTimeout(() => {
                console.log('Redirecting to dashboard...');
                window.location.href = './dashboard/index.php';
            }, 3000);
        } else {
            console.log('Error detected:', response.message);
            
            showError(response.message || 'Failed to update payment method');
            
            if (response.debug) {
                console.log('Debug info:', response.debug);
            }
        }
    }, 1000);
}

// FUNÇÃO CORRIGIDA: Lidar com erros
function handleUpdateError(error, formData) {
    const elapsed = ((Date.now() - retryState.startTime) / 60000).toFixed(1); // minutos
    console.error('handleUpdateError called after', elapsed, 'minutes:', error);
    
    // Verificar se é timeout após muito tempo
    if (error.name === 'AbortError' || error.message.includes('timeout')) {
        console.log('Long timeout detected after', elapsed, 'minutes');
        
        // Se foi timeout após muito tempo, verificar status
        updateLoadingMessage('Request timed out after ' + elapsed + ' minutes. Checking account status...');
        $('#progressText').text('This is unusual - checking if update was successful...');
        
        setTimeout(() => {
            checkAccountStatusAfterTimeout(formData.email, formData.username)
                .then(statusData => {
                    console.log('Status check completed after timeout:', statusData);
                    
                    if (statusData && (statusData.account_status === 'active' || statusData.payment_status === 3)) {
                        // Sucesso! A operação foi processada mesmo com timeout
                        console.log('Account is active despite timeout');
                        
                        showLoading(false);
                        $('#updateBtn').prop('disabled', false);
                        
                        showSuccess('Payment method updated successfully! Your account is now active. (Completed despite timeout)');
                        $('#updateCardForm')[0].reset();
                        
                        setTimeout(() => {
                            window.location.href = './dashboard/index.php';
                        }, 3000);
                        
                    } else {
                        // Realmente falhou
                        showFinalTimeoutError(elapsed, formData);
                    }
                })
                .catch(() => {
                    showFinalTimeoutError(elapsed, formData);
                });
        }, 3000);
        
        return;
    }
    
    // Outros tipos de erro - tratamento normal
    showLoading(false);
    $('#updateBtn').prop('disabled', false);
    
    let errorMessage = 'Network error. Please try again.';
    
    if (error.message.includes('HTTP 5')) {
        errorMessage = 'Server error. Our payment service is temporarily unavailable. Please try again in a few minutes.';
    } else if (error.message.includes('network') || error.message.includes('Failed to fetch')) {
        errorMessage = 'Connection error. Please check your internet connection and try again.';
    } else if (error.message) {
        errorMessage = error.message;
    }
    
    showError(errorMessage + ` (Failed after ${elapsed} minutes)`);
    
    console.error('Final error details:', {
        error: error.message,
        elapsed_minutes: elapsed,
        formData: {
            email: formData.email,
            username: formData.username
        }
    });
}

// NOVA FUNÇÃO: Verificar status da conta após timeout
function checkAccountStatusAfterTimeout(email, username) {
    console.log('Checking account status after timeout...');
    
    return $.ajax({
        url: 'api_handler.php',
        method: 'POST',
        data: { 
            action: 'check_account_status',
            email: email,
            username: username
        },
        timeout: 15000,
        dataType: 'json'
    })
    .then(function(data) {
        console.log('Account status check result:', data);
        return data;
    })
    .catch(function(error) {
        console.log('Account status check failed:', error);
        return null;
    });
}

// NOVA FUNÇÃO: Lidar com erros de timeout potenciais
function handlePotentialTimeoutError(error, formData) {
    console.log('Checking for potential timeout error:', error.message);
    
    const isTimeoutError = 
        error.name === 'AbortError' ||
        error.message.includes('timeout') ||
        error.message.includes('Operation too slow') ||
        error.message.includes('Request timeout');
    
    if (isTimeoutError && CONFIG.TIMEOUT_CHECK_ENABLED) {
        console.log('Timeout detected, checking account status');
        
        // Mostrar mensagem especial para timeout
        updateLoadingMessage('Request timed out, but processing may continue. Checking status...');
        $('#progressText').text('Checking if update was successful in background...');
        
        // Aguardar um pouco e verificar status da conta
        setTimeout(() => {
            checkAccountStatusAfterTimeout(formData.email, formData.username)
                .then(statusData => {
                    console.log('Status check completed:', statusData);
                    
                    if (statusData && (statusData.account_status === 'active' || statusData.payment_status === 3)) {
                        // Sucesso! A operação foi processada mesmo com timeout
                        console.log('Account is active, showing success');
                        
                        showLoading(false);
                        $('#updateBtn').prop('disabled', false);
                        
                        showSuccess('Payment method updated successfully! Your account is now active.');
                        $('#updateCardForm')[0].reset();
                        
                        setTimeout(() => {
                            console.log('Redirecting to dashboard after timeout recovery');
                            window.location.href = './dashboard/index.php';
                        }, 3000);
                        
                    } else if (statusData && statusData.needs_card_update === false) {
                        // Conta já não precisa de update
                        console.log('Account no longer needs update');
                        
                        showLoading(false);
                        $('#updateBtn').prop('disabled', false);
                        
                        showSuccess('Your account is already active! Redirecting...');
                        
                        setTimeout(() => {
                            window.location.href = './dashboard/index.php';
                        }, 2000);
                        
                    } else {
                        // Ainda com problema, mostrar erro normal
                        console.log('Account still has issues, showing final error');
                        handleFinalTimeoutError(error, formData);
                    }
                })
                .catch(() => {
                    console.log('Status check failed, showing final error');
                    handleFinalTimeoutError(error, formData);
                });
        }, CONFIG.BACKGROUND_CHECK_DELAY);
        
        return true; // Indica que está sendo tratado
    }
    
    return false; // Não é timeout, tratar normalmente
}

// FUNÇÃO: Tratar timeout final
function handleFinalTimeoutError(error, formData) {
    showLoading(false);
    $('#updateBtn').prop('disabled', false);
    
    let errorMessage = 'The update request timed out after 130 seconds. ';
    
    if (error.message.includes('Operation too slow')) {
        errorMessage += 'This usually means the payment processor is experiencing high load. ';
    }
    
    errorMessage += 'Your payment may have been processed successfully. Please check your account status in a few minutes or contact support if the issue persists.';
    
    showError(errorMessage);
}

// FUNÇÃO: Atualizar mensagem de loading
function updateLoadingMessage(message) {
    $('#loadingMessage').text(message);
    console.log('Loading message updated:', message);
}

// FUNÇÃO CORRIGIDA: Mostrar loading com melhor debug
function showLoading(show) {
    console.log('showLoading called:', show);
    
    if (show) {
        $('#loadingOverlay').css('display', 'flex').fadeIn();
        $('#progressBar').css('width', '0%');
        $('#loadingTitle').text('Processing Payment Update');
        $('#loadingMessage').text('Please wait while we securely update your payment method...');
        $('#progressText').text('This may take up to 130 seconds');
        
        retryState.startTime = Date.now();
        console.log('Loading overlay shown');
    } else {
        $('#loadingOverlay').fadeOut();
        retryState = { currentAttempt: 0, isRetrying: false, startTime: null };
        console.log('Loading overlay hidden');
    }
}

// FUNÇÃO CORRIGIDA: startProgressMonitoring
function startProgressMonitoring() {
    console.log('Progress monitoring started');
    
    let progress = 0;
    const progressInterval = setInterval(() => {
        if (retryState.isRetrying) {
            clearInterval(progressInterval);
            return;
        }
        
        progress += Math.random() * 2;
        if (progress > 90) progress = 90;
        
        $('#progressBar').css('width', progress + '%');
        
        const elapsed = Date.now() - retryState.startTime;
        
        if (elapsed < 15000) {
            updateLoadingMessage('Validating payment information...');
        } else if (elapsed < 45000) {
            updateLoadingMessage('Processing with payment gateway...');
        } else if (elapsed < 90000) {
            updateLoadingMessage('Creating secure customer profile...');
            $('#progressText').text('This step can take up to 2 minutes for security');
        } else if (elapsed < 120000) {
            updateLoadingMessage('Finalizing account updates...');
            $('#progressText').text('Almost done, please wait...');
        } else {
            updateLoadingMessage('Processing is taking longer than usual...');
            $('#progressText').text('Your payment may still be processing successfully');
        }
    }, CONFIG.PROGRESS_UPDATE_INTERVAL);
    
    // Limpar intervalo após timeout + buffer
    setTimeout(() => {
        clearInterval(progressInterval);
        console.log('Progress monitoring stopped');
    }, CONFIG.REQUEST_TIMEOUT + 10000);
}

// FUNÇÃO CORRIGIDA: Validação de formulário
function validateForm(data) {
    hideAlerts();
    console.log('Validating form data');
    
    if (!data.card_name || data.card_name.length < 3) {
        console.log('Invalid card name');
        showError('Please enter a valid cardholder name');
        return false;
    }
    
    if (!data.card_number || data.card_number.length < 13) {
        console.log('Invalid card number');
        showError('Please enter a valid card number');
        return false;
    }
    
    if (!data.expiry || !/^\d{2}\/\d{2}$/.test(data.expiry)) {
        console.log('Invalid expiry');
        showError('Please enter a valid expiry date (MM/YY)');
        return false;
    }
    
    if (!data.cvv || data.cvv.length < 3) {
        console.log('Invalid CVV');
        showError('Please enter a valid CVV');
        return false;
    }
    
    console.log('Form validation passed');
    return true;
}

// FUNÇÃO CORRIGIDA: Mostrar sucesso
function showSuccess(message, duration = 5000) {
    console.log('Showing success message:', message);
    
    hideAlerts();
    $('#successMessage').text(message);
    $('#successAlert').slideDown();
    
    setTimeout(function() {
        $('#successAlert').slideUp();
    }, duration);
}

// FUNÇÃO CORRIGIDA: Mostrar erro
function showError(message) {
    console.log('Showing error message:', message);
    
    hideAlerts();
    $('#errorMessage').text(message);
    $('#errorAlert').slideDown();
    
    setTimeout(function() {
        $('#errorAlert').slideUp();
    }, 10000);
}

// FUNÇÃO: Esconder alertas
function hideAlerts() {
    $('#successAlert, #errorAlert').slideUp();
}

// Add smooth focus transitions
$('.form-control').on('focus', function() {
    $(this).parent().css('transform', 'translateY(-2px)');
}).on('blur', function() {
    $(this).parent().css('transform', 'translateY(0)');
});

// LISTENERS para detecção de perda de conexão
window.addEventListener('online', function() {
    if (retryState.isRetrying) {
        console.log('Connection restored, continuing with retry...');
        showSuccess('Internet connection restored!', 2000);
    }
});

window.addEventListener('offline', function() {
    console.log('Connection lost');
    if ($('#loadingOverlay').is(':visible')) {
        updateLoadingMessage('Connection lost. Waiting for internet...');
        $('#progressText').text('Please check your internet connection');
    }
});

// Debug em modo desenvolvimento
if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev') || window.location.hostname.includes('prosecure')) {
    console.log('Update Card Debug Mode Enabled');
    console.log('Config:', CONFIG);
    
    // Adicionar informações de debug
    window.debugCardUpdate = {
        config: CONFIG,
        state: retryState,
        showState: function() {
            console.log('Current state:', retryState);
        },
        testLoading: function() {
            showLoading(true);
            setTimeout(() => showLoading(false), 3000);
        },
        testSuccess: function() {
            showSuccess('Test success message');
        },
        testError: function() {
            showError('Test error message');
        }
    };
    
    console.log('Debug functions available in window.debugCardUpdate');
}
    </script>
</body>
</html>