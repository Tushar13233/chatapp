<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Database से user data और profile_picture लें
    $sql = "SELECT id, username, password_hash, profile_picture FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Password को verify करें
    if ($user && password_verify($password, $user['password_hash'])) {
        // Login successful, session start करें
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['profile_picture'] = $user['profile_picture']; // profile_picture को सेशन में स्टोर करें
        
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['login_error'] = "Invalid email or password.";
        header("Location: index.php");
        exit();
    }
}

header("Location: index.php");
exit();
?>