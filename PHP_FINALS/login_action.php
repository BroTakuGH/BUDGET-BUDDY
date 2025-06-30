<?php
session_start();
include("config.php");
include("firebaseRDB.php");

$email = $_POST['email'];
$password = $_POST['password'];

if ($email == "") {
    echo "Email is required";
} else if ($password == "") {
    echo "Password is required";
} else {
    $rdb = new firebaseRDB($databaseURL);
    $retrieve = $rdb->retrieve("/user", "email", "EQUAL", $email);
    $data = json_decode($retrieve, true);

    // 👇 Sanity check
    if (!is_array($data)) {
        echo "Unexpected response from database. Please try again.";
        exit;
    }

    if (count($data) == 0) {
        echo "Email not registered";
    } else {
        $user = reset($data); // Safely get the first user record
        if (isset($user['password']) && $user['password'] == $password) {
            $_SESSION['user'] = $user;
            header("location: dashboard.php");
        } else {
            echo "Login failed";
        }
    }
}

