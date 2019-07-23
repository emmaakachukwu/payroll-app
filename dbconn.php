<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'payroll');

class database {
    public $host = DB_HOST;
    public $user = DB_USER;
    public $password = DB_PASS;
    public $db_name = DB_NAME;

    public $conn;
    public $error;

    public function __construct(){
        $this->connect();
    }

    private function connect(){
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->db_name);

        if (!$this->conn) {
            $this->$error = "Connection failed: ".$this->conn->connect_error;
            return false;
        }else{
            return $this->conn;
        }
    }

    protected function runquery($sql){
        $result = $this->conn->query($sql) or die($this->conn->error.''.__LINE__);
        return $result;
    }
}
?>