<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

try {
    // Fetch the latest unread message
    $sql = "SELECT 
                m.id AS message_id,
                m.message,
                u.id AS sender_id,
                u.username AS sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ? AND m.is_read = 0
            ORDER BY m.sent_at DESC LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $unread_message = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($unread_message) {
        $message_text = $unread_message['message'];
        if (strpos($message_text, 'uploads/chat/') === 0) {
            $message_text = "Sent an image";
        }

        echo json_encode([
            'status' => 'success',
            'message_id' => $unread_message['message_id'],
            'sender_id' => $unread_message['sender_id'],
            'sender_name' => $unread_message['sender_name'],
            'message' => $message_text,
        ]);
    } else {
        echo json_encode(['status' => 'no_new_message']);
    }

} catch (PDOException $e) {
    error_log("Database error in check_for_notifications.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>