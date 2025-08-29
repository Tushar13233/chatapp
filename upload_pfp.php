<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Error: User not logged in.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pfp'])) {
    $user_id = $_SESSION['user_id'];
    $file = $_FILES['pfp'];
    
    $upload_dir = 'uploads/pfp/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = $user_id . '_' . uniqid() . '.' . $file_extension;
    $destination = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Save the file path to the user's row in the database
        $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$destination, $user_id])) {
            $_SESSION['profile_picture'] = $destination;
            echo "Profile picture updated successfully!";
        } else {
            echo "Error: Could not update database.";
        }
    } else {
        echo "Error: Failed to upload file.";
    }
} else {
    echo "Error: No file received.";
}
?>