<?php 
// ULTRA DEBUG - Capturar TODOS os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Handler de erro personalizado
set_error_handler(function($severity, $message, $file, $line) {
    error_log("ERRO CAPTURADO: [$severity] $message em $file:$line");
    return false;
});

// Handler de exceção personalizada
set_exception_handler(function($exception) {
    error_log("EXCEÇÃO NÃO CAPTURADA: " . $exception->getMessage() . " em " . $exception->getFile() . ":" . $exception->getLine());
    error_log("Stack trace: " . $exception->getTraceAsString());
    echo "Critical error occurred: " . $exception->getMessage();
    exit();
});

// Handler de shutdown para capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        error_log("ERRO FATAL DETECTADO: " . json_encode($error));
    }
});

error_log("=== ACCOUNTS API STARTED COM ULTRA DEBUG ===");

try {
    error_log("DEBUG: Carregando includes...");
    require_once '/var/www/html/controllers/inc.sessions.php';
    error_log("DEBUG: inc.sessions.php carregado");
    
    session_start();
    error_log("DEBUG: session_start() executado");
    
    include("database_connection.php");
    error_log("DEBUG: database_connection.php carregado");
    
    require_once '/var/www/html/controllers/hashing_lsp.php';
    error_log("DEBUG: hashing_lsp.php carregado");
    
    require_once '/var/www/html/users/dashboard/pages/controllers/maillers/_new_child_users.php';
    error_log("DEBUG: _new_child_users.php carregado");
    
    require_once '/var/www/html/users/dashboard/pages/controllers/maillers/_remove_child_user_email.php';
    error_log("DEBUG: _remove_child_user_email.php carregado");
    
    require_once '/var/www/html/users/dashboard/pages/controllers/maillers/_switch_accounts_email.php';
    error_log("DEBUG: _switch_accounts_email.php carregado");
    
    require "../../../../vendor/autoload.php";
    error_log("DEBUG: vendor/autoload.php carregado");
    
    error_log("DEBUG: Todos os includes carregados com sucesso");
} catch (Exception $e) {
    error_log("ERRO AO CARREGAR INCLUDES: " . $e->getMessage());
    echo "Error loading required files: " . $e->getMessage();
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Verificar se as funções existem
error_log("DEBUG: Verificando se função decrypt_lsp existe: " . (function_exists('decrypt_lsp') ? 'SIM' : 'NÃO'));
error_log("DEBUG: Verificando se função encrypt_lsp existe: " . (function_exists('encrypt_lsp') ? 'SIM' : 'NÃO'));

// Verificar sessão
error_log("DEBUG: Session ID: " . session_id());
error_log("DEBUG: Session username: " . ($_SESSION['username'] ?? 'NÃO DEFINIDO'));
error_log("DEBUG: Session reference: " . ($_SESSION['reference'] ?? 'NÃO DEFINIDO'));

// Verificar POST
error_log("DEBUG: Dados POST recebidos: " . json_encode($_POST));
error_log("DEBUG: Método de requisição: " . ($_SERVER['REQUEST_METHOD'] ?? 'NÃO DEFINIDO'));

// NOVA FUNÇÃO PARA GERAR UUID
function generateUUIDv4() {
    error_log("DEBUG: Gerando UUID v4...");
    try {
        // Gerar 16 bytes aleatórios
        $data = random_bytes(16);
        
        // Definir os bits de versão (4) e variante
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versão 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante
        
        // Formatar como UUID
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        
        error_log("DEBUG: UUID gerado: $uuid");
        return $uuid;
    } catch (Exception $e) {
        error_log("ERRO ao gerar UUID: " . $e->getMessage());
        // Fallback para método alternativo
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        error_log("DEBUG: UUID fallback gerado: $uuid");
        return $uuid;
    }
}

// Todas as funções necessárias
function getPurchasedPlans(){
  error_log("DEBUG: getPurchasedPlans() chamada");
  try {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $username = $conn->real_escape_string($_SESSION['username']);
    $sql = "SELECT purchased_plans FROM master_accounts WHERE username = '$username'";
    $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $purchasedPlansJson = $row['purchased_plans'];
            error_log("DEBUG: getPurchasedPlans() retornando: " . $purchasedPlansJson);
            return json_decode($purchasedPlansJson, true);
        }
      error_log("DEBUG: getPurchasedPlans() retornando NULL");
      return null;
  } catch (Exception $e) {
    error_log("ERRO em getPurchasedPlans(): " . $e->getMessage());
    return null;
  }
}

function getMasterName(){
  try {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $master_reference = $conn->real_escape_string($_SESSION['reference']);
    $sql = "SELECT name, lname FROM master_accounts WHERE reference_uuid = '$master_reference'";
    $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $full_name = $row['name']. ' ' . $row['lname'];
            return $full_name;
        }
      return null;
  } catch (Exception $e) {
    error_log("ERRO em getMasterName(): " . $e->getMessage());
    return null;
  }
}

function rollback_available_plans($child_email, $plan_id, $master){
  error_log("DEBUG: rollback_available_plans() - Email: $child_email, Plan: $plan_id, Master: $master");
  try {
    $plans_json_unserialized = getPurchasedPlans();
    foreach($plans_json_unserialized as &$item){
      if($item['plan_id'] == $plan_id && $item['is_master'] == 0 && $item['email'] === $child_email){
        $item['username'] = 'none';
        $item['email'] = 'none';
        break;
      }
    }
    $new_json_plans = json_encode($plans_json_unserialized);

    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();
    $sql = "UPDATE master_accounts SET purchased_plans = ? WHERE reference_uuid = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a instrução: " . $conn->error);
    }

    $stmt->bind_param("ss", $new_json_plans, $master);

    if ($stmt->execute()) {
        error_log("DEBUG: rollback_available_plans() - SUCESSO");
        return 'rolledback';
    } else {
        throw new Exception("Erro ao inserir usuário: " . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Erro na função rollback_available_plans: " . $e->getMessage());
    return $e->getMessage();
  }
}

function rollback_user($child_email){
  error_log("DEBUG: rollback_user() - Email: $child_email");
  try {
    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();

    $sql = "DELETE FROM users WHERE email = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a instrução: " . $conn->error);
    }

    $stmt->bind_param("s", $child_email);

    if ($stmt->execute()) {
        error_log("DEBUG: rollback_user() - SUCESSO");
        return 'deleted';
    } else {
        throw new Exception("Err " . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Err delete_user_by_email: " . $e->getMessage());
    return $e->getMessage();
  }
}

function verify_if_is_child_to_remove($child_email,$plan_id){
  try {
    $plans_json_unserialized = getPurchasedPlans();
    $count = 0;
    foreach($plans_json_unserialized as &$item){
      if($item['plan_id'] == $plan_id && $item['is_master'] == 0 && $item['email'] === $child_email){
        $count++;
        break;
      }
    }
    if($count > 0){
      return true;
    }
    return false;
  } catch (Exception $e) {
    error_log("ERRO em verify_if_is_child_to_remove(): " . $e->getMessage());
    return false;
  }
}

function verify_if_child_exist($child_email){
  try {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $child_email_escaped = $conn->real_escape_string($child_email);
    $sql = "SELECT email FROM users WHERE email = '$child_email_escaped'";
    $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            return true;
        }
      return false;
  } catch (Exception $e) {
    error_log("ERRO em verify_if_child_exist(): " . $e->getMessage());
    return false;
  }
}

function send_deactivation_email_to_child($child_email){
  try {
    $title = "Notice";
    $name = $child_email;
    $subtitle = "Unfortunately your account has been disabled";
    $content = "The administrator of your plan has chosen to deactivate your account. If you believe this may be an error, please contact your administrator.
    <br>
    If you are interested in remaining protected, we have some plans that you may be interested in, just click on the link below to view them<br>
    <strong><a href='https://prosecurelsp.com/plans.php'>Access here</a></strong>";
    $html_content_email = remove_user_email_template($title,$name,$subtitle,$content);

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = '172.31.255.82';
    $mail->SMTPAuth = false;
    $mail->Username = 'jcosta@prosecure.com';
    $mail->Port = 25;
    $mail->SMTPAutoTLS = false;
    $mail->setFrom('no-reply@prosecure.com', 'ProsecureLSP');
    $mail->addAddress($child_email);
    $mail->isHTML(true);
    $mail->Subject = 'Notice: account deactivated :(';
    $mail->Body = $html_content_email;
    if ($mail->Send()) {
        return true;
    } else {
        return false;
    }
  } catch (Exception $e) {
    error_log("ERRO em send_deactivation_email_to_child(): " . $e->getMessage());
    return false;
  }
}

function verify_if_child_is_not_master_to_add($email){
  error_log("DEBUG: verify_if_child_is_not_master_to_add() - Email: $email");
  try {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $email_escaped = $conn->real_escape_string($email);
    $sql = "SELECT email FROM master_accounts WHERE email = '$email_escaped'";
    $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            error_log("DEBUG: Email $email JÁ É MASTER ACCOUNT");
            return true;
        }
      error_log("DEBUG: Email $email NÃO É MASTER ACCOUNT - OK");
      return false;
  } catch (Exception $e) {
    error_log("ERRO em verify_if_child_is_not_master_to_add(): " . $e->getMessage());
    return false;
  }
}

function verify_if_account_not_exist($email){
  error_log("DEBUG: verify_if_account_not_exist() - Email: $email");
  try {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    $email_escaped = $conn->real_escape_string($email);
    $sql = "SELECT email FROM users WHERE email = '$email_escaped'";
    $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            error_log("DEBUG: Email $email JÁ EXISTE NA TABELA USERS");
            return true;
        }
      error_log("DEBUG: Email $email NÃO EXISTE NA TABELA USERS - OK");
      return false;
  } catch (Exception $e) {
    error_log("ERRO em verify_if_account_not_exist(): " . $e->getMessage());
    return false;
  }
}

function verify_if_is_valid_email($email){
  error_log("DEBUG: verify_if_is_valid_email() - Email: $email");
  try {
    if(filter_var($email, FILTER_VALIDATE_EMAIL)){
      error_log("DEBUG: Email $email É VÁLIDO");
      return true;
    }else{
      error_log("DEBUG: Email $email É INVÁLIDO");
      return false;
    }
  } catch (Exception $e) {
    error_log("ERRO em verify_if_is_valid_email(): " . $e->getMessage());
    return false;
  }
}

// FUNÇÃO MELHORADA PARA REGISTRAR CONVITE COM UUID
function register_invite($ssid, $master, $child_email, $link){
  error_log("DEBUG: register_invite() - SSID: $ssid, Master: $master, Email: $child_email");
  try {
    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();

    // Primeiro, limpar convites antigos/expirados para o mesmo email
    error_log("DEBUG: Limpando convites antigos para email: $child_email");
    $cleanup_sql = "UPDATE tmp_invites SET status_invite = -1 WHERE email = ? AND status_invite = 0";
    $cleanup_stmt = $conn->prepare($cleanup_sql);
    if ($cleanup_stmt) {
        $cleanup_stmt->bind_param("s", $child_email);
        $cleanup_stmt->execute();
        error_log("DEBUG: Convites antigos marcados como expirados");
    }

    $sql = "INSERT INTO tmp_invites (ssid, master_reference, email, invite_link, status_invite, created_at) VALUES (?, ?, ?, ?, 0, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a instrução: " . $conn->error);
    }

    $stmt->bind_param("ssss", $ssid, $master, $child_email, $link);
    if ($stmt->execute()) {
        error_log("DEBUG: register_invite() - SUCESSO - ID do insert: " . $conn->insert_id);
        return 'created';
    } else {
        throw new Exception("Erro ao inserir convite: " . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Erro na função register_invite: " . $e->getMessage());
    return $e->getMessage();
  }
}

function update_available_plans($master, $child_email, $plan_id){
  error_log("=== DEBUG update_available_plans INICIADO ===");
  error_log("DEBUG: Master: $master");
  error_log("DEBUG: Child Email: $child_email");
  error_log("DEBUG: Plan ID: $plan_id (tipo: " . gettype($plan_id) . ")");
  
  try {
    $plans_json_unserialized = getPurchasedPlans();
    
    if($plans_json_unserialized === null) {
      error_log("ERRO: Não foi possível obter planos do usuário");
      return 'no_plans_found';
    }
    
    error_log("DEBUG: Total de planos encontrados: " . count($plans_json_unserialized));
    error_log("DEBUG: Planos completos: " . json_encode($plans_json_unserialized, JSON_PRETTY_PRINT));
    
    $plan_updated = false;
    $available_slots = 0;
    $same_plan_slots = 0;
    
    foreach($plans_json_unserialized as $index => &$item){
      error_log("DEBUG: [Índice $index] Analisando plano:");
      error_log("  - plan_id: {$item['plan_id']} (tipo: " . gettype($item['plan_id']) . ")");
      error_log("  - is_master: {$item['is_master']} (tipo: " . gettype($item['is_master']) . ")");
      error_log("  - email: '{$item['email']}'");
      error_log("  - username: '{$item['username']}'");
      error_log("  - plan_name: '{$item['plan_name']}'");
      
      // Contadores para diagnóstico
      if($item['email'] === 'none') {
        $available_slots++;
        if($item['plan_id'] == $plan_id) {
          $same_plan_slots++;
        }
      }
      
      // Verificação se é o slot que procuramos
      $plan_id_match = ($item['plan_id'] == $plan_id);
      $not_master = ($item['is_master'] == 0);
      $email_none = ($item['email'] === 'none');
      
      error_log("  - plan_id_match: " . ($plan_id_match ? 'TRUE' : 'FALSE'));
      error_log("  - not_master: " . ($not_master ? 'TRUE' : 'FALSE'));
      error_log("  - email_none: " . ($email_none ? 'TRUE' : 'FALSE'));
      
      if($plan_id_match && $not_master && $email_none){
        error_log("DEBUG: ✅ SLOT DISPONÍVEL ENCONTRADO no índice $index!");
        $item['username'] = $child_email;
        $item['email'] = $child_email;
        $plan_updated = true;
        error_log("DEBUG: Slot atualizado - username: {$item['username']}, email: {$item['email']}");
        break;
      }
    }
    
    error_log("DEBUG: Total de slots disponíveis: $available_slots");
    error_log("DEBUG: Slots do mesmo plano disponíveis: $same_plan_slots");
    error_log("DEBUG: Plan updated: " . ($plan_updated ? 'TRUE' : 'FALSE'));
    
    if(!$plan_updated){
      error_log("ERRO: ❌ NENHUM SLOT DISPONÍVEL ENCONTRADO!");
      error_log("ERRO: Procurando plan_id=$plan_id, is_master=0, email='none'");
      return 'no_slot_available';
    }
    
    $new_json_plans = json_encode($plans_json_unserialized);
    error_log("DEBUG: Novo JSON após atualização: " . $new_json_plans);

    error_log("DEBUG: Iniciando atualização no banco de dados...");
    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();
    $sql = "UPDATE master_accounts SET purchased_plans = ? WHERE reference_uuid = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a instrução: " . $conn->error);
    }

    $stmt->bind_param("ss", $new_json_plans, $master);

    if ($stmt->execute()) {
        error_log("DEBUG: ✅ BANCO ATUALIZADO COM SUCESSO!");
        error_log("=== DEBUG update_available_plans FINALIZADO COM SUCESSO ===");
        return 'created';
    } else {
        throw new Exception("Erro ao atualizar banco: " . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("ERRO CRÍTICO na função update_available_plans: " . $e->getMessage());
    return $e->getMessage();
  }
}

function register_new_user($master, $child_email, $plan_id, $pass) {
  error_log("DEBUG: register_new_user() - Master: $master, Email: $child_email, Plan: $plan_id");
  try {
      $dbConnection = new DatabaseConnection();
      $conn = $dbConnection->getConnection();
      $sql = "INSERT INTO users (master_reference, username, email, passphrase, is_master, plan_id, is_active, email_confirmed, created_at) VALUES (?, ?, ?, ?, 0, ?, 0, 0, NOW())";

      $stmt = $conn->prepare($sql);
      if (!$stmt) {
          throw new Exception("Erro ao preparar a instrução: " . $conn->error);
      }

      $hashed_pass = hash('sha256', $pass);
      $stmt->bind_param("ssssi", $master, $child_email, $child_email, $hashed_pass, $plan_id);

      if ($stmt->execute()) {
          error_log("DEBUG: register_new_user() - SUCESSO - Usuário criado com ID: " . $conn->insert_id);
          return 'created';
      } else {
          throw new Exception("Erro ao inserir usuário: " . $stmt->error);
      }
  } catch (Exception $e) {
      error_log("Erro na função register_new_user: " . $e->getMessage());
      return $e->getMessage();
  }
}

function send_invite_email($child_email, $link, $pass){
  error_log("DEBUG: send_invite_email() - Email: $child_email, Link: $link");
  try {
    $title = "Welcome to your plan";
    $name = $child_email;
    $master_name = getMasterName();
    $subtitle = "You have been invited by ". $master_name .", to protect your digital life with us";
    $content = "This invitation will not generate any costs for you at any time, you are just joining the current plan of ". $master_name.", 
                to accept the invitation and start using the most advanced protection for your data just click on the link below and activate your account
                <br>
                USERNAME:<br>
                <div style='background-color: #dddddd'><p style='font-size:20px;color:#000'>".$child_email."</p></div><br>
                PASSPHRASE:
                <div style='background-color: #dddddd'><p style='font-size:20px;color:#000'>".$pass."</p></div><br>
                <br> <a style='color:#fff; padding-bottom: 50px' href=\"".$link."\"><strong>Activate Account</strong></a>";
    $html_content_email = new_user_email_template($title, $name, $subtitle, $content);

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = '172.31.255.82';
    $mail->SMTPAuth = false;
    $mail->Username = 'jcosta@prosecure.com';
    $mail->Port = 25;
    $mail->SMTPAutoTLS = false;
    $mail->setFrom('no-reply@prosecure.com', 'ProsecureLSP');
    $mail->addAddress($child_email);
    $mail->isHTML(true);
    $mail->Subject = 'Welcome to your plan :)';
    $mail->Body = $html_content_email;
    if ($mail->Send()) {
        error_log("DEBUG: send_invite_email() - EMAIL ENVIADO COM SUCESSO");
        return true;
    } else {
        error_log("DEBUG: send_invite_email() - FALHA AO ENVIAR EMAIL: " . $mail->ErrorInfo);
        return false;
    }
  } catch (Exception $e) {
    error_log("ERRO em send_invite_email(): " . $e->getMessage());
    return false;
  }
}

// FUNÇÃO PARA LIMPAR CONVITES EXPIRADOS
function cleanup_expired_invites(){
  error_log("DEBUG: cleanup_expired_invites() chamada");
  try {
    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();
    
    // Marcar como expirados convites com mais de 24 horas não aceitos
    $sql = "UPDATE tmp_invites SET status_invite = -1 WHERE status_invite = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $result = $conn->query($sql);
    
    if ($result) {
        $affected_rows = $conn->affected_rows;
        error_log("DEBUG: cleanup_expired_invites() - $affected_rows convites marcados como expirados");
        return $affected_rows;
    }
    return 0;
  } catch (Exception $e) {
    error_log("ERRO em cleanup_expired_invites(): " . $e->getMessage());
    return 0;
  }
}

// VERIFICAÇÃO PRINCIPAL COM PROTEÇÃO MÁXIMA
error_log("DEBUG: Iniciando verificação das condições POST...");

if(isset($_POST['action'])) {
  error_log("DEBUG: \$_POST['action'] = " . $_POST['action']);
}

if(isset($_POST['action']) && $_POST['action'] == 'add_child_account') {
  error_log("DEBUG: ✅ Condição de action confirmada");
  
  // Primeiro, limpar convites expirados
  cleanup_expired_invites();
  
  if(isset($_POST['issoai'])) {
    error_log("DEBUG: ✅ issoai existe");
    error_log("DEBUG: issoai valor bruto: " . $_POST['issoai']);
    
    try {
      error_log("DEBUG: Tentando decrypt do issoai...");
      $plan_id_raw = decrypt_lsp($_POST['issoai']);
      error_log("DEBUG: decrypt_lsp result: " . $plan_id_raw);
      
      $plan_id = intval($plan_id_raw);
      error_log("DEBUG: plan_id após intval: $plan_id");
      
    } catch (Exception $e) {
      error_log("ERRO FATAL no decrypt_lsp: " . $e->getMessage());
      echo "Error decrypting plan ID: " . $e->getMessage();
      exit();
    }
  } else {
    error_log("ERRO: issoai não existe no POST");
    echo "Missing plan ID";
    exit();
  }
  
  if(isset($_POST['email'])) {
    error_log("DEBUG: ✅ email existe");
    $email = trim($_POST['email']);
    error_log("DEBUG: email valor: $email");
  } else {
    error_log("ERRO: email não existe no POST");
    echo "Missing email";
    exit();
  }
  
  if(isset($_POST['pass'])) {
    error_log("DEBUG: ✅ pass existe");
    $pass = $_POST['pass'];
    error_log("DEBUG: pass valor: [OCULTO]");
  } else {
    error_log("ERRO: pass não existe no POST");
    echo "Missing password";
    exit();
  }
  
  if(isset($_SESSION['reference'])) {
    error_log("DEBUG: ✅ session reference existe");
    $master = $_SESSION['reference'];
    error_log("DEBUG: master valor: $master");
  } else {
    error_log("ERRO: session reference não existe");
    echo "Session expired";
    exit();
  }
  
  error_log("🚀 === TODAS AS CONDIÇÕES ATENDIDAS - INICIANDO PROCESSAMENTO ===");
  error_log("DEBUG: Plan ID: $plan_id, Email: $email, Master: $master");
  
  try {
    // Passo 1: Verificar se email já é master
    error_log("DEBUG: Passo 1 - Verificando se email é master account...");
    if(verify_if_child_is_not_master_to_add($email)){
      error_log("ERRO: Email já é master account");
      echo "This email is already linked to another master account";
      exit();
    }
    error_log("DEBUG: ✅ Email não é master account");
    
    // Passo 2: Verificar se email já existe como child
    error_log("DEBUG: Passo 2 - Verificando se email já existe...");
    if(verify_if_account_not_exist($email)){
      error_log("ERRO: Email já existe como child account");
      echo "This email is already linked to another account";
      exit();
    }
    error_log("DEBUG: ✅ Email não existe como child account");

    // Passo 3: Validar email
    error_log("DEBUG: Passo 3 - Validando email...");
    if(!verify_if_is_valid_email($email)){
      error_log("ERRO: Email inválido");
      echo "Please use a valid e-mail";
      exit();
    }
    error_log("DEBUG: ✅ Email é válido");
    
    // Passo 4: Gerar convite com UUID
    error_log("DEBUG: Passo 4 - Gerando convite com UUID...");
    
    $ssid_uuid = generateUUIDv4();
    error_log("DEBUG: UUID gerado: $ssid_uuid");
    
    if(!function_exists('encrypt_lsp')) {
      error_log("ERRO FATAL: Função encrypt_lsp não existe!");
      echo "Function encrypt_lsp not found";
      exit();
    }
    
    $ssid_encrypted = encrypt_lsp($ssid_uuid);
    error_log("DEBUG: encrypt_lsp result: $ssid_encrypted");
    
    $encoded_ssid = urlencode($ssid_encrypted);
    $link = 'https://prosecurelsp.com/users/newuser/update_infos.php?ssid=' . $encoded_ssid;
    error_log("DEBUG: Link gerado: $link");

    // Passo 5: CRÍTICO - Atualizar planos disponíveis ANTES de registrar convite
    error_log("DEBUG: Passo 5 - Atualizando planos disponíveis...");
    $update_result = update_available_plans($master, $email, $plan_id);
    if($update_result !== 'created'){
      error_log("ERRO CRÍTICO: Falha ao atualizar planos disponíveis: $update_result");
      if($update_result === 'no_slot_available'){
        echo "No available slots for this plan type. Please purchase additional plans first.";
      } else {
        echo "Service unavailable LSP044: $update_result";
      }
      exit();
    }
    error_log("DEBUG: ✅ Planos disponíveis atualizados");

    // Passo 6: Registrar usuário
    error_log("DEBUG: Passo 6 - Registrando novo usuário...");
    $register_result = register_new_user($master, $email, $plan_id, $pass);
    if($register_result !== "created"){
      error_log("ERRO: Falha ao registrar usuário: $register_result");
      rollback_available_plans($email, $plan_id, $master);
      echo "Service unavailable LSP045: $register_result";
      exit();
    }
    error_log("DEBUG: ✅ Usuário registrado");

    // Passo 7: Registrar convite APÓS usuário estar criado
    error_log("DEBUG: Passo 7 - Registrando convite...");
    $invite_result = register_invite($ssid_encrypted, $master, $email, $link);
    if($invite_result !== 'created'){
      error_log("ERRO: Falha ao registrar convite: $invite_result");
      rollback_available_plans($email, $plan_id, $master);
      rollback_user($email);
      echo "Service unavailable LSP043: $invite_result";
      exit();
    }
    error_log("DEBUG: ✅ Convite registrado");

    // Passo 8: Enviar email
    error_log("DEBUG: Passo 8 - Enviando email de convite...");
    if(!send_invite_email($email, $link, $pass)){
      error_log("ERRO: Falha ao enviar email de convite");
      rollback_available_plans($email,$plan_id, $master);
      rollback_user($email);
      echo "It was not possible to send the invitation at the moment, please try again. LSP046";
      exit();
    }
    error_log("DEBUG: ✅ Email enviado");

    error_log("🎉 === ADD_CHILD_ACCOUNT FINALIZADO COM SUCESSO ===");
    echo "Invitation sent, as soon as this invitation is accepted you will be notified by email";
    exit();
    
  } catch (Exception $e) {
    error_log("EXCEÇÃO CRÍTICA em add_child_account: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo "Critical error occurred. Please contact support. Error: " . $e->getMessage();
    exit();
  }
}

// ========== AÇÃO PARA REMOVER CHILD ACCOUNT ==========
if(isset($_POST['action']) && isset($_POST['old_email']) && isset($_POST['issoai']) && $_POST['action'] == 'remove_child_account'){
  error_log("DEBUG: remove_child_account action called");
  try {
    $plan_id = intval(decrypt_lsp($_POST['issoai']));
    $email = decrypt_lsp($_POST['old_email']);
    $master = $_SESSION['reference'];

    if(!verify_if_is_child_to_remove($email, $plan_id)){
      echo "It is not possible to remove this user. If you have any questions, please contact our support team. LSP0030";
      exit();
    }

    if(!verify_if_child_exist($email)){
      echo "It is not possible to remove this user. If you have any questions, please contact our support team. LSP0031";
      exit();
    }
    
    if($plan_id !== 5){
      if(send_deactivation_email_to_child($email)){
        if(rollback_user($email) !== 'deleted'){
          echo "It is not possible to remove this user. If you have any questions, please contact our support team. LSP0032";
          exit();
        }
        if(rollback_available_plans($email, $plan_id, $master) !== 'rolledback'){
          echo "It is not possible to remove this user. If you have any questions, please contact our support team. LSP0033";
          exit();
        }
        echo "Account successfully removed";
        exit(); 
      }
    } else {
      if(rollback_user($email) !== 'deleted'){
        echo "It is not possible to remove this user. If you have any questions, please contact our support team. LSP0032";
        exit();
      }
      if(rollback_available_plans($email, $plan_id, $master) !== 'rolledback'){
        echo "It is not possible to remove this user. If you have any questions, please contact our support team. LSP0033";
        exit();
      }
      echo "Account successfully removed";
      exit();
    }
  } catch (Exception $e) {
    error_log("ERRO em remove_child_account: " . $e->getMessage());
    echo "Error removing account: " . $e->getMessage();
    exit();
  }
}

// ========== AÇÃO PARA ADD CHILD ACCOUNT KIDS ==========
if(isset($_POST['action']) && isset($_POST['issoai']) && isset($_POST['email']) && isset($_POST['pass']) && isset($_POST['repass']) && $_POST['action'] == 'add_child_account_kids'){
  error_log("DEBUG: add_child_account_kids action called");
  try {
    $plan_id = intval(decrypt_lsp($_POST['issoai']));
    $email = trim($_POST['email']);
    $master = $_SESSION['reference'];
    
    if(verify_if_child_is_not_master_to_add($email)){
      echo "This email is already linked to another master account";
      exit();
    }
    
    if(verify_if_account_not_exist($email)){
      echo "This email is already linked to another account";
      exit();
    }

    if(!verify_if_is_valid_email($email)){
      echo "Please use a valid e-mail";
      exit();
    }

    $update_result = update_available_plans($master, $email, $plan_id);
    if($update_result !== 'created'){
      if($update_result === 'no_slot_available'){
        echo "No available slots for this plan type. Please purchase additional plans first.";
      } else {
        echo "Service unavailable LSP044: $update_result";
      }
      exit();
    }

    $pass = $_POST['pass'];
    $repass = $_POST['repass'];
    if( $pass !== $repass){
      rollback_available_plans($email,$plan_id, $master);
      echo "Password doesn't match";
      exit();    
    }
    
    $register_result = register_new_user($master, $email, $plan_id, $pass);
    if($register_result !== "created"){
      rollback_available_plans($email,$plan_id, $master);
      echo "Service unavailable LSP045: $register_result";
      exit();
    }

    // Para kids account, ativar diretamente a conta
    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();
    $sql = "UPDATE users SET is_active = 1, email_confirmed = 1 WHERE email = ? AND master_reference = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $email, $master);
        $stmt->execute();
    }

    echo "User created, just login on your kids device";
    exit();
  } catch (Exception $e) {
    error_log("ERRO em add_child_account_kids: " . $e->getMessage());
    echo "Error creating kids account: " . $e->getMessage();
    exit();
  }
}

// ========== FUNÇÕES PARA GERENCIAMENTO DE PLANOS ==========
function verify_if_plan_is_blank($plan_id){
  try {
    $plans_json_unserialized = getPurchasedPlans();
    foreach($plans_json_unserialized as &$item){
      if($item['plan_id'] == $plan_id && $item['is_master'] == 0 && $item['email'] === 'none' && $item['username'] === 'none'){
        return true;
        break;
      }
    }
    return false;
  } catch (Exception $e) {
    error_log("ERRO em verify_if_plan_is_blank(): " . $e->getMessage());
    return false;
  }
}

function turn_current_admin_plan_blank($plan_id, $master_reference, $master_email, $master_username){
  try {
    $plans_json_unserialized = getPurchasedPlans();

    foreach($plans_json_unserialized as &$item){
      if($item['is_master'] === 1 && $item['email'] === $master_email && $item['username'] === $master_username){
        $item['is_master'] = 0;
        $item['email'] = 'none';
        $item['username'] = 'none';
        break;
      }
    }
    $plans_json_with_master_deassociated = json_encode($plans_json_unserialized);

    $plans_json_with_new_master_plan = associate_admin_new_plan($plans_json_with_master_deassociated,$plan_id, $master_reference, $master_email, $master_username);  

    return $plans_json_with_new_master_plan;
  } catch (Exception $e) {
    error_log("ERRO em turn_current_admin_plan_blank(): " . $e->getMessage());
    return null;
  }
}

function associate_admin_new_plan($plans_json_with_master_deassociated,$plan_id, $master_reference, $master_email, $master_username){
  try {
    $plans_json_unserialized = json_decode($plans_json_with_master_deassociated, true);
    foreach($plans_json_unserialized as &$item){
      if($item['plan_id'] == $plan_id && $item['is_master'] == 0 && $item['email'] === 'none' && $item['username'] === 'none'){
        $item['is_master'] = 1;
        $item['email'] = $master_email;
        $item['username'] = $master_username;
        break;
      }
    }
    $new_json_plans_with_new_master_plan = json_encode($plans_json_unserialized);
    return $new_json_plans_with_new_master_plan;
  } catch (Exception $e) {
    error_log("ERRO em associate_admin_new_plan(): " . $e->getMessage());
    return null;
  }
}

function rewrite_purchased_plans($new_json_plans, $master_reference){
  try {
    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();
    $sql = "UPDATE master_accounts SET purchased_plans = ? WHERE reference_uuid = ? ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing query: " . $conn->error);
    }

    $stmt->bind_param("ss", $new_json_plans, $master_reference);

    if ($stmt->execute()) {
        return 'rewrite';
    } else {
        throw new Exception("Error inserting query: " . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Error function rewrite_purchased_plans: " . $e->getMessage());
    return $e->getMessage();
  }
}

// ========== AÇÃO PARA TORNAR PLANO VAZIO EM MASTER ACCOUNT ==========
if(isset($_POST['action']) && isset($_POST['issoai']) && $_POST['action'] == 'turn_blank_into_master_account'){
  error_log("DEBUG: turn_blank_into_master_account action called");
  try {
    $plan_id = intval(decrypt_lsp($_POST['issoai']));
    $master_reference = $_SESSION['reference'];
    $master_email = $_SESSION['email'];
    $master_username = $_SESSION['username'];   
    
    if(!verify_if_plan_is_blank($plan_id)){
      echo 'This plan is already linked to another account LSP050';
      exit();
    }

    $new_json_plans_with_new_master_plan = turn_current_admin_plan_blank($plan_id, $master_reference, $master_email, $master_username);

    if($new_json_plans_with_new_master_plan !== NULL){
      if(rewrite_purchased_plans($new_json_plans_with_new_master_plan, $master_reference) !== 'rewrite'){
        echo 'Service unavailable LSP052';
        exit();
      }
      echo 'Plan successfully updated';
    }
  } catch (Exception $e) {
    error_log("ERRO em turn_blank_into_master_account: " . $e->getMessage());
    echo "Error updating plan: " . $e->getMessage();
    exit();
  }
}

// ========== FUNÇÕES PARA SWITCH DE CONTAS ==========
function attribute_plan_to_master($child_plan_id, $child_email, $master_email, $master_username){
  try {
    $plans_json_unserialized = getPurchasedPlans();
    foreach($plans_json_unserialized as &$item){
      if($item['plan_id'] == $child_plan_id && $item['is_master'] == 0 && $item['email'] === $child_email){
        $item['is_master'] = 1;
        $item['email'] = $master_email;
        $item['username'] = $master_username;
        break;
      }
    }
    $new_json_plans_multiple = json_encode($plans_json_unserialized);
    
    $new_json_plans_switched = switch_accounts($new_json_plans_multiple, $child_email, $master_email, $master_username, $child_plan_id);
    return $new_json_plans_switched;
  } catch (Exception $e) {
    error_log("ERRO em attribute_plan_to_master(): " . $e->getMessage());
    return null;
  }
}

function switch_accounts($new_json_plans_multiple, $child_email, $master_email, $master_username, $child_plan_id){
  try {
    $plans_json_unserialized = json_decode($new_json_plans_multiple, true);
    foreach($plans_json_unserialized as &$item){
      if($item['plan_id'] != $child_plan_id && $item['is_master'] === 1 && $item['email'] === $master_email){
        $item['is_master'] = 0;
        $item['email'] = $child_email;
        $item['username'] = $child_email;
        break;
      }
    }
    $new_json_plans_switched = json_encode($plans_json_unserialized);
    return $new_json_plans_switched;
  } catch (Exception $e) {
    error_log("ERRO em switch_accounts(): " . $e->getMessage());
    return null;
  }
}

function send_email_to_child($new_json_plans, $child_email){
  try {
    $plans_json_unserialized = json_decode($new_json_plans, true);
    $new_child_plan_name = '';
    foreach($plans_json_unserialized as &$item){
      if($item['is_master'] === 0 && $item['email'] === $child_email){
        $new_child_plan_name = $item['plan_name'];
        break;
      }
    }
    $title = "NOTICE";
    $name = $child_email;
    $master_name = getMasterName();
    $subtitle = "Your plan administrator ". $master_name ." has changed your plan to ". $new_child_plan_name;
    $content = "if you think there has been a mistake please contact your administrator";
    $html_content_email = switch_accounts_email_template($title, $name, $subtitle, $content);

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = '172.31.255.82';
    $mail->SMTPAuth = false;
    $mail->Username = 'jcosta@prosecure.com';
    $mail->Port = 25;
    $mail->SMTPAutoTLS = false;
    $mail->setFrom('no-reply@prosecure.com', 'ProsecureLSP');
    $mail->addAddress($child_email);
    $mail->isHTML(true);
    $mail->Subject = 'Notice';
    $mail->Body = $html_content_email;
    if ($mail->Send()) {
        return true;
    } else {
        return false;
    }
  } catch (Exception $e) {
    error_log("ERRO em send_email_to_child(): " . $e->getMessage());
    return false;
  }
}

function switch_accounts_on_db($new_json_plans, $master_reference){
  try {
    $dbConnection = new DatabaseConnection();
    $conn = $dbConnection->getConnection();
    $sql = "UPDATE master_accounts SET purchased_plans = ? WHERE reference_uuid = ? ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing query: " . $conn->error);
    }

    $stmt->bind_param("ss", $new_json_plans, $master_reference);

    if ($stmt->execute()) {
        return 'rewrite';
    } else {
        throw new Exception("Error inserting query: " . $stmt->error);
    }
  } catch (Exception $e) {
    error_log("Error function switch_accounts_on_db: " . $e->getMessage());
    return $e->getMessage();
  }
}

// ========== AÇÃO PARA SWITCH CHILD AND MASTER ==========
if(isset($_POST['action']) && isset($_POST['dataOld']) && isset($_POST['issoai']) && $_POST['action'] == 'switch_child_and_master'){
  error_log("DEBUG: switch_child_and_master action called");
  try {
    $master_email = $_SESSION['email'];
    $master_reference = $_SESSION['reference'];
    $master_username = $_SESSION['username'];

    $child_email = decrypt_lsp($_POST['dataOld']);
    $plan_id = intval(decrypt_lsp($_POST['issoai']));

    if(verify_if_child_is_not_master_to_add($child_email)){
      echo 'This plan already belongs to a master account LSP060';
      exit();
    }

    if(!verify_if_child_exist($child_email)){
      echo 'This child account does not exist LSP061';
      exit();
    }
    
    $new_json_plans = attribute_plan_to_master($plan_id, $child_email, $master_email, $master_username);
    if($new_json_plans !== NULL){
      if(!send_email_to_child($new_json_plans, $child_email)){
        echo 'Service unavailable LSP062';
        exit();
      }
      if(switch_accounts_on_db($new_json_plans,$master_reference) !== 'rewrite'){
        echo 'Service unavailable LSP063';
        exit();
      }
      echo 'Plans successfully switched. An email will be sent to '. $child_email;
    }
  } catch (Exception $e) {
    error_log("ERRO em switch_child_and_master: " . $e->getMessage());
    echo "Error switching accounts: " . $e->getMessage();
    exit();
  }
}

error_log("=== FINAL DO SCRIPT ACCOUNTS API ===");
?>