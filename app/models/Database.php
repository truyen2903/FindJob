<?php
// app/models/Database.php
class Database {
    private $host;
    private $user;
    private $pass;
    private $dbname;
    public $conn;

    public function __construct() {
        $this->host   = getenv('DB_HOST') ?: 'localhost';
        $this->user   = getenv('DB_USER') ?: 'root';
        $this->pass   = getenv('DB_PASS') ?: '';
        $this->dbname = getenv('DB_NAME') ?: 'jobfinder';

        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        if ($this->conn->connect_error) {
            die("Lỗi kết nối CSDL: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
    }
}

