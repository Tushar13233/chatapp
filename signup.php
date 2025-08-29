<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Password को hash करें
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Users table में डेटा डालें
    $sql = "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$username, $email, $password_hash]);
        $_SESSION['signup_success'] = "Registration successful! Please login.";
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $_SESSION['signup_error'] = "Error: Email or username already exists.";
        } else {
            $_SESSION['signup_error'] = "Error: " . $e->getMessage();
        }
        header("Location: index.php");
        exit();
    }
}
header("Location: index.php");
exit();
?>