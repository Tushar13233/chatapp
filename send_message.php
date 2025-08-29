<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to send a message.");
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'];
$message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';

$file_path = null;
if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    
    $upload_dir = 'uploads/chat/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_extension;
    $destination = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $file_path = $destination;
        $message = $file_path;
    }
}

if (!empty($message)) {
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$sender_id, $receiver_id, $message]);
    echo "Message sent successfully!";
} else {
    echo "Error: Empty message or file.";
}
?>