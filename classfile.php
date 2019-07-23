<?php
include "dbconn.php";
use Firebase\JWT\JWT;
require("firejwt/JWT.php");
define("SECRET_KEY", "learningjwt");

class jwtclass {
    function encrypt_jwt($issuer, $audience, $user){
        $key = SECRET_KEY;
        $token = array(
            "iss"=>$issuer, "aud"=>$audience, "id"=>$user, "iat"=>time(), "nbf"=>time()
        );
        $hu = new JWT; $done = $hu::encode($token, $key);
        return $done;
    }

    function readtoken($token){
        $JWread = new JWT;
        $decode = jwt::decode($token, "learningjwt", array('HS256'));
        $decoded_array = (array)$decode;
        $user = $decoded_array['id'];
        return $user;
    }
}

class validate {
    public function validatenumber($value) {
        return ctype_digit($value)?true:false;
    }

    public function validatealnum($value) {
        return ctype_alnum($value)?true:false;
    }

    public function validateemail($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL)?true:false;
    }
}

class sqlops extends database {

    public function insert($tab, $col, $val) {
        $sql = "INSERT INTO $tab ($col) VALUES($val)";
        $result = $this->runquery($sql);
        return $result ? true : false;
    }

    public function select($col, $tab, $where) {
        $sql = "SELECT $col FROM $tab WHERE $where";
        $result = $this->runquery($sql);
        if($result) {
            $found = $result->num_rows;
            return $found>0 ? true : false;
        }
    }

    public function select_fetch($col, $tab, $where) {
        $sql = "SELECT $col FROM $tab WHERE $where";
        $result = $this->runquery($sql);
        if ($result) {
            $found = $result->num_rows;
            if ($found > 0) {
                while($fetch = $result->fetch_object()) {
                    return $fetch;
                }
            }
        }
    }

    public function update($tab, $set, $where) {
        $sql = "UPDATE $tab SET $set WHERE $where";
        $result = $this->runquery($sql);
        return $result ? true : false;
    }

    public function create_db($dbname) {
        $sql = "CREATE DATABASE IF NOT EXISTS `$dbname`";
        $result = $this->runquery($sql);
        return $result ? true : false;
    }

    public function select_db($dbname) {
        $sql = mysqli_select_db($this->conn, $dbname);
        return $sql ? true : false;
    }

    public function create_tab($tabname, $cols) {
        $sql = "CREATE TABLE IF NOT EXISTS $tabname($cols)";
        $result=$this->runquery($sql);
        return $result ? true : false;
    }
        
    public function sel_db_create_tab($dbname, $tabname, $cols) {
        $selectdb =  mysqli_select_db($this->conn, $dbname);
        $sql = "CREATE TABLE IF NOT EXISTS $tabname($cols)";
        $result = $this->runquery($sql);
        return $result ? true : false;
    }

    public function fetch_assoc($col, $tab, $where) {
        $sql = "SELECT $col FROM $tab $where";
        $result = $this->runquery($sql);
        if ( $result ) {
            $found = $result->num_rows;
            if ( $found ) {
                $fetcher = [];
                while($fetch = $result->fetch_assoc())
                array_push($fetcher, $fetch);
                return $fetcher;
            }
        }
    }
}

?>