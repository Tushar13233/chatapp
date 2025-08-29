<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['contact_id'])) {
    echo json_encode(['last_message' => null, 'is_read' => true]);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$contact_id = $_GET['contact_id'];

try {
    $sql = "SELECT message, sender_id FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
            OR (sender_id = ? AND receiver_id = ?)
            ORDER BY sent_at DESC LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id, $contact_id, $contact_id, $current_user_id]);
    $last_message = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_read = true;
    if ($last_message && $last_message['sender_id'] != $current_user_id) {
        $is_read = false; 
    }
    
    $message_text = $last_message ? $last_message['message'] : null;
    if ($message_text && strpos($message_text, 'uploads/chat/') === 0) {
        $message_text = "Image";
    }

    echo json_encode([
        'last_message' => $message_text,
        'is_read' => $is_read
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_last_message.php: " . $e->getMessage());
    echo json_encode(['last_message' => null, 'is_read' => true]);
}
?>