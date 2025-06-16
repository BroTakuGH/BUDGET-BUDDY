<?php
include("config.php");
include("firebaseRDB.php");

$name =$_POST['name'];
$email=$_POST['email'];
$password=$_POST['password'];

if($name == ""){
    echo "Name is required";
}else if($email == ""){
    echo "Email is required";
}else if($password == ""){
    echo "Password is required";
}else{
    $rdb = new firebaseRDB($databaseURL);
    $retrieve = $rdb->retrieve("/user","email","EQUAL", $email,);
    $data = json_decode($retrieve, 1);

    if(count($data)>0){
        echo "Email already registered";
    }else{
        $insert = $rdb->insert("/user",[
            "name" => $name,
            "email"=> $email,
            "password"=> $password
        ]);

        $result = json_decode($insert);
        if(isset($result['name'])){
            echo "Signup success, please login";
        }else{
            echo "Sigup failed";
        }
    }
}
