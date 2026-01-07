<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA Activation - ProsecureLsp</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:300" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <style>
        @import url(https://fonts.googleapis.com/css?family=Roboto:300);
        .login-page { width: 460px; padding: 8% 0 0; margin: auto; }
        .form { 
            position: relative; 
            background: #FFFFFF; 
            max-width: 460px; 
            padding: 45px; 
            text-align: center; 
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2); 
        }
        .form input, .form select { 
            font-family: "Roboto", sans-serif; 
            background: #f2f2f2; 
            width: 100%; 
            border: 0; 
            margin: 0 0 15px; 
            padding: 15px; 
            box-sizing: border-box; 
        }
        .phone-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .phone-group select {
            width: 120px;
        }
        .phone-group input {
            flex: 1;
            margin: 0;
        }
        .form button { 
            font-family: "Roboto", sans-serif; 
            text-transform: uppercase;
            background: #4CAF50; 
            width: 100%; 
            border: 0; 
            padding: 15px; 
            color: white; 
            font-size: 14px;
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .form button:hover { background: #43A047; }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
        }
        .error-message {
            color: #d9534f;
            background-color: #f2dede;
            border: 1px solid #d9534f;
        }
        .success-message {
            color: #3c763d;
            background-color: #dff0d8;
            border: 1px solid #3c763d;
        }
        body {
            background: #303e69;
            font-family: "Roboto", sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .loading {
            display: none;
            color: #4CAF50;
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="login-page">
    <div class="form">
        <h2 class="form-title">Multi-Factor Authentication Setup</h2>
        
        <div id="message-container"></div>

        <form id="mfaForm">
            <div class="phone-group">
                <select name="country_code" id="country_code" required>
                    <option value="+1">USA/Canada (+1)</option>
                    <option value="+55">Brazil (+55)</option>
                    <option value="+351">Portugal (+351)</option>
                    <option value="+44">UK (+44)</option>
                    <option value="+34">Spain (+34)</option>
                </select>
                <input type="text" name="phone" id="phone" placeholder="Phone Number" required/>
            </div>
            <div class="loading">Processing...</div>
            <button type="submit">Activate MFA</button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Phone mask function
    function updatePhoneMask() {
        var country = $('#country_code').val();
        var phoneInput = $('#phone');
        
        // Remove previous mask
        phoneInput.unmask();
        
        // Apply mask based on country
        switch(country) {
            case '+1':
                phoneInput.mask('000-000-0000');
                break;
            case '+55':
                phoneInput.mask('(00) 00000-0000');
                break;
            case '+351':
                phoneInput.mask('000-000-000');
                break;
            case '+44':
                phoneInput.mask('00-0000-0000');
                break;
            case '+34':
                phoneInput.mask('000-000-000');
                break;
            default:
                phoneInput.mask('000000000000');
        }
    }

    // Initialize mask
    updatePhoneMask();
    $('#country_code').on('change', updatePhoneMask);

    // Form submission handler
    $('#mfaForm').on('submit', function(e) {
    e.preventDefault();
    
    $('#message-container').empty();
    $('.loading').show();
    $('button[type="submit"]').prop('disabled', true);

    var countryCode = $('#country_code').val();
    var phone = $('#phone').val().replace(/[^0-9]/g, '');
    var fullPhone = countryCode + phone;

    $.ajax({
        url: 'https://mfa.prosecurelsp.com/enable_2fa',
        method: 'POST',
        dataType: 'json', // Adicione esta linha para forçar a interpretação JSON
        contentType: 'application/json',
        data: JSON.stringify({
            username: 'gamerdidi221@gmail.com',
            phone: fullPhone
        }),
        success: function(response) {
            $('.loading').hide();
            $('button[type="submit"]').prop('disabled', false);

            console.log('Resposta completa do servidor:', response);

            // Modifique a condição de verificação
            if (response && response.status === 'pending_verification') {
                $('#message-container').html(
                    '<div class="message success-message">' + 
                    response.message + 
                    '</div>'
                );
                
                // Redirecionar após 2 segundos
                setTimeout(function() {
                    window.location.href = './verify_2fa.html';
                }, 2000);
            } else {
                $('#message-container').html(
                    '<div class="message error-message">Resposta inesperada do servidor: ' + 
                    JSON.stringify(response) + 
                    '</div>'
                );
            }
        },
        error: function(xhr, status, error) {
            $('.loading').hide();
            $('button[type="submit"]').prop('disabled', false);

            console.log('Erro completo:', xhr);
            console.log('Status:', status);
            console.log('Erro:', error);

            // Tente extrair a mensagem de erro de diferentes fontes
            var errorMessage = '';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                errorMessage = xhr.responseText;
            } else {
                errorMessage = 'Ocorreu um erro desconhecido. Tente novamente.';
            }
            
            $('#message-container').html(
                '<div class="message error-message">' + errorMessage + '</div>'
            );
        }
    });
});
});
</script>
</body>
</html>