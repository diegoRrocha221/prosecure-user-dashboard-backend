<?php
class User {
    private $username;
    private $password;
 
    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }
   
    public function getUsername() {
        return $this->username;
    }
   
    public function getPassword() {
        return $this->password;
    }
   
    public function authenticate($conn) {
        $hashedPassword = hash('sha256', $this->getPassword());
        $sql = "SELECT email_confirmed, is_active, is_master, payment_status FROM users WHERE username = ? AND passphrase = ?";
        $stmt = $conn->prepare($sql);
        $username = $this->getUsername();
        $stmt->bind_param('ss', $username, $hashedPassword);
        $stmt->execute();
        $result = $stmt->get_result();
       
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
           
            // Verificar se email foi confirmado primeiro
            if ($row['email_confirmed'] != 1) {
                return 'inactive';
            }
           
            // Verificar status da conta
            switch ($row['is_active']) {
                case 1:
                    if ($row['is_master'] == 1) {
                        // Para master accounts, verificar payment_status
                        switch ($row['payment_status']) {
                            case 0:
                                return 'validating'; // Novo status: informações sendo validadas
                            case 1:
                                return 'payment_error'; // Problema com pagamento
                            case 3:
                                return 'master'; // Tudo OK, pode acessar
                            default:
                                return 'inactive'; // Outros status = inativo
                        }
                    } else {
                        // Para contas normais (não master), não precisa verificar payment_status
                        return 'normal';
                    }
                    break;
                   
                case 2:
                    return 'dea';
                    break;
                   
                case 9:
                    return 'inactive'; // Agora is_active = 9 é apenas inativo
                    break;
                   
                default:
                    return 'inactive';
                    break;
            }
        } else {
            return 'invalid';
        }
    }
   
    public function isMfaEnabled($conn) {
        $sql = "SELECT mfa_is_enable FROM master_accounts WHERE username = ?";
        $stmt = $conn->prepare($sql);
   
        $username = $this->getUsername();
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
   
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['mfa_is_enable'];
        } else {
            return null;
        }
    }
   
    public function getMasterAccountInfo($conn) {
        $sql = "SELECT * FROM master_accounts WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $username = $this->getUsername();
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
       
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row;
        } else {
            return null;
        }
    }
   
    public function getPaymentErrorInfo($conn) {
        // Atualizada para considerar payment_status = 1 ao invés de is_active = 9
        $sql = "SELECT u.username, u.email, ma.name, ma.lname, ma.total_price, ma.reference_uuid
                FROM users u
                JOIN master_accounts ma ON u.username = ma.username
                WHERE u.username = ? AND  u.payment_status = 1";
        $stmt = $conn->prepare($sql);
        $username = $this->getUsername();
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
       
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        } else {
            return null;
        }
    }

    // Novo método para obter informações quando está em validação
    public function getValidatingInfo($conn) {
        $sql = "SELECT u.username, u.email, ma.name, ma.lname
                FROM users u
                JOIN master_accounts ma ON u.username = ma.username
                WHERE u.username = ? AND u.is_active = 1 AND u.payment_status = 0";
        $stmt = $conn->prepare($sql);
        $username = $this->getUsername();
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
       
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        } else {
            return null;
        }
    }
}
?>