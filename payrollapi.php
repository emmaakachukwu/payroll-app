<?php
header("Access-Control-Allow-Origin:*");
header("Access-Control-Allow-Headers: X-Requested-With, Authorrization, content-type, access-control-allow-origin, access-control-allow-methods, access-control-allow-headers");

include "classfile.php";
$validate = new validate; // class for validating values
$class = new sqlops; // class for sql operations
$valjwt = new jwtclass; // class for jwt validation (jwt stands for json web token)
$post = json_decode(file_get_contents('php://input'),TRUE);
$key = $post['key'];

if ( $key == "01" || $key == "02" || $key == "03" || $key == "04" || $key == "05" || $key == "06" || $key == "07" ){
    $code = "";
    $cookie = "";

    //for signup. default status code is 0
    if ($key == '01') {
        foreach ($post as $key => $value) {
            if(empty($value)){
                $code = '01'; $message = $key.' cannot be empty';
            }else{
                if($key == 'email'){
                    if($validate->validateemail($value) == false){
                        $code = '01'; $message = 'enter a valid email address';
                    }
                }elseif($key == 'password'){
                    if(strlen($value) < 6){
                        $code = '01'; $message = $key.' must be at least six characters';
                    }elseif($value !== $post['confirm_password']){
                        $code = '01'; $message = $key.'s do not match';
                    }
                }
            }
        }
        //if there is no error above
        if($code != '01'){
            $col = 'email';
            $tab = 'users';
            $where = "email = '".$post['email']."'";

            $sel = $class->select($col, $tab, $where);
            if($sel){
                $code = '02'; $message = 'email is already in use';
            }else{
                $sid = md5($post['email'].time());
                $enc_pass = password_hash($post['password'], PASSWORD_BCRYPT);
                $col = "email, password, service_id";
                $value = "'".$post['email']."', '".$enc_pass."', '".$sid."'";
                $tab = 'users';

                $ins = $class->insert($tab, $col, $value);
                if($ins){
                    $s_col = "service_id";
                    $s_value = "'".$sid."'";
                    $s_tab = 'service';

                    $serv = $class->insert($s_tab, $s_col, $s_value);

                    $issuer = "http://localhost:4200";
                    $audience = "http://localhost:4200/dashboard";
                    $user = "'".$post['email']."'";
                    $varJWT = $valjwt->encrypt_jwt($issuer, $audience, $user);

                    $code = '00'; $message = 'account creation successfull'; $cookie = $varJWT;
                }
            }
        }
    }

    //for login
    if($key == "02") {
        foreach ($post as $key => $value) {
            if(empty($value)){
                $code = '01'; $message = $key.' cannot be empty';
            }else{
                if($key == 'email'){
                    if($validate->validateemail($value) == false){
                        $code = '01'; $message = 'enter a valid email';
                    }
                }
            }
        }
        //if there are no errors above
        if($code != '01'){
            $tab = 'users';
            $col =  'email, password, status';
            $where = "email = '".$post['email']."'";
            $selfetch = $class->select_fetch($col, $tab, $where);

            if(!$selfetch){
                $code = '020'; $message = 'Invalid login';
            }elseif(password_verify($post['password'], $selfetch->password)){
                $issuer = "http://localhost:4200";
                $audience = "http://localhost:4200/dashboard";
                $user = "'".$post['email']."'";
                $varJWT = $valjwt->encrypt_jwt($issuer, $audience, $user);
                
                $code = '00'; $message = 'Login Successful'; $cookie = $varJWT;

                if($selfetch->status == 0){
                    $code = '00'; $message = $selfetch->status; $cookie = $varJWT;
                }
            }else{
                $code = '03'; $message = 'Invalid login';
            }
        } 
    }

    //for creating of service, user's status is updated to '1' on successful creation of service
    if ($key == "03") {
        //validate user
        if(isset($post['cookie'])){
            $read = $valjwt->readtoken($post['cookie']);
            if ($read == true) {
                $sid = $post['sid'];

                //bring out 8 character name to be used for creating user's db
                $newdb = substr($sid, 0, 4).substr($sid, -4, 4);

                //validate for empty and invalid values
                foreach ($post as $key => $value) {
                    if (empty($value)) {
                    $code = '01'; $message = $key.' cannot be empty';
                    } else {
                        if($key == 'phone'){
                            if($validate->validatenumber($value) == false){
                                $code = '01'; $message = $key.' can only be numeric';
                            }elseif(strlen($value) < 10 || strlen($value) > 11){
                                $code = '01'; $message = $key.' is invalid';
                            }
                        }
                    }
                }
                //if there are no errors above
                if($code != '01'){
                    //check if service_url already exists
                    $col = 'service_url';
                    $tab = 'service';
                    $where = "service_url = '".$post['url']."'";

                    $sel = $class->select($col, $tab, $where);
                    if($sel == null){
                        //url does not exist, update table
                        $tab = 'service';
                        $set = "service_name = '".$post['service_name']."', service_url = '".$post['url']."', address = '".$post['address']."', phone = '".$post['phone']."'";
                        $where = "service_id = '".$post['sid']."'";

                        $update = $class->update($tab, $set, $where);
                        if($update){
                            $code = '00'; $message = 'profile updated';
                        }
                        //create a new db for user
                        $createdb = $class->create_db($newdb);

                        if($createdb){
                            $code = '00'; $message = $newdb;
                            //update user's status to '1'
                            $tabStat = 'users';
                            $setStat = "status = 1, db_name = '$newdb'";
                            $whereStat = "service_id = '$sid'";

                            $updateStat = $class->update($tabStat, $setStat, $whereStat);

                            //select the created db
                            $seldb = $class->select_db($newdb);

                            if (!$seldb) {
                                $code = "02"; $message = "error in creating service";
                            } else {
                                //create various tables needed for the user
                                //to create table "designations"
                                $tabname1 = "designations";
                                $cols1 = "
                                    id int(15) not null auto_increment primary key, grades varchar(100) not null, levels varchar(20) not null, work_pay int(15) not null
                                ";
                                $tab1 = $class->create_tab($tabname1, $cols1);

                                //to create table "employees"
                                $tabname2 = "employees";
                                $cols2 = "
                                    id int(15) not null auto_increment primary key, name varchar(100) not null, email varchar(100) not null, address varchar(100) not null, phone varchar(100) not null, grade varchar(100) not null, work_pay int(15) not null
                                ";
                                $tab2 = $class->create_tab($tabname2, $cols2);

                                //to create table "deductions"
                                $tabname3 = "deductions";
                                $cols3 = "
                                    id int(15) not null auto_increment primary key, description varchar(200) not null, amount int(15) not null
                                ";
                                $tab3 = $class->create_tab($tabname3, $cols3);

                                //to create table "penalties"
                                $tabname4 = "penalties";
                                $cols4 = "
                                    id int(15) not null auto_increment primary key, description varchar(200) not null, amount int(15) not null
                                ";
                                $tab4 = $class->create_tab($tabname4, $cols4);

                                if ( $tab1 && $tab2 && $tab3 && $tab4 ) {
                                    $code = "00"; $message = "service creation complete";
                                }
                            }                            
                        }
                    } else {
                        //if url inputed already exists in db
                        $code="02"; $message="url already exists";
                    }
                }
            } else {
                //if user validaton fails
                $code = '02'; $message = 'error! please login again';
            }
        } else {
            //if cookie is not set
            $code = '02'; $message = 'error! please login';
        }
    }

    //fetches user data from db using email
    if ($key == "04") {
        $col = "email, service_id";
        $tab = "users";
        $where = "email = '".$post['email']."'";

        $fetch = $class->select_fetch($col, $tab, $where);

        if ($fetch) {
            $code = "00"; $message = $fetch;
        } else {
            $code = "02"; $message = "unable to get data";
        }
    }

    //inserts data into designation table
    if ($key == "05") {
        if ( isset($post['cookie'])) {
            $read = $valjwt->readtoken($post['cookie']);
            if ( $read == true ) {
                foreach ($post as $key => $value) {
                    if ( empty($value) ) {
                        $code = '01'; $message = $key.' cannot be empty';
                    }
                }
                if ( $code != '01' ) {
                    $db = substr($post['sid'], 0, 4).substr($post['sid'], -4, 4);
                    if ( $class->select_db($db) ) {
                        $col = 'grades, levels';
                        $tab = 'designations';
                        $where = "grades = '".$post['grade']."' && levels = '".$post['level']."'";

                        if ( $class->select($col, $tab, $where) ) {
                            $code = '02'; $message = 'Designation already exists. Add a new designation';
                        } else {
                            $tab = 'designations';
                            $col = 'grades, levels, work_pay';
                            $val = "'".$post['grade']."', '".$post['level']."', '".$post['pay']."'";
                            if ( $class->insert($tab, $col, $val) ) {
                                $code = '00'; $message = 'Designation added successfully';
                            }
                        }
                    } else {
                        $code = '02'; $message = $db.' not found';
                    }
                }
            } else {
                $code = '02'; $message = 'error! please login again';
            }
        } else {
            $code = '02'; $message = 'error! please login';
        }   
    }

    //fetches data from designations table
    if ( $key == "06" ) {
        if ( isset($post['cookie']) ) {
            if ( $read = $valjwt->readtoken($post['cookie']) ) {
                $sid = $post['sid'];
                $db = substr($sid, 0, 4).substr($sid, -4, 4);

                if ( $class->select_db($db) ) {
                    $col = '*';
                    $tab = 'designations';
                    $where = "WHERE delete_status != 1";

                    if ( $fetch = $class->fetch_assoc($col, $tab, $where ) ) {
                        $code = '00'; $message = $fetch;
                    } else {
                        $code = '02'; $message = '';
                    }
                } else {
                    $code = '02'; $message = $db.' not found';
                }
            } else {
                $code = '02'; $message = 'error! please login again';
            }
        } else {
            $code = '02'; $message = 'error! please login';
        }
    }

    //to delete grades. delete status is updated to 1
    if ( $key == "07" ) {
        if ( isset($post['cookie']) ) {
            if ( $read = $valjwt->readtoken($post['cookie']) ) {
                $sid = $post['sid'];
                $db = substr($sid, 0, 4).substr($sid, -4, 4);

                if ( $class->select_db($db) ) {
                    $tab = 'designations';
                    $set = "delete_status = 1";
                    $where = "id = '".$post['id']."'";

                    if ( $class->update( $tab, $set, $where ) ) {
                        $code = '00'; $message = 'Designation deleted';
                    } else {
                        $code = '02'; $message = 'Operation not successfull';
                    }
                } else {
                    $code = '02'; $message = $db.' not found';
                }
            } else {
                $code = '02'; $message = 'error! please login again';
            }
        } else {
            $code = '02'; $message = 'error! please login';
        }
    }
}

echo json_encode(['code'=>$code, 'message'=>$message, 'cookie'=>$cookie]);

?>