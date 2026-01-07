<?php

class DatabaseConnection {
    private $servername = "172.31.255.26";
    private $username = "root";
    private $password = "Security.4uall!";
    private $dbname = "prosecure_web";
    private $conn;

    public function __construct() {
        $this->conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);

        if ($this->conn->connect_error) {
            die("Falha na conexão com o banco de dados: " . $this->conn->connect_error);
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}

?>
