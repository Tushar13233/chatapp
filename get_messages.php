<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to view messages.");
}

$current_user_id = $_SESSION['user_id'];
$contact_id = $_GET['contact_id'];

// Mark all unread messages from the contact as read
$sql_update = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?";
$stmt_update = $pdo->prepare($sql_update);
$stmt_update->execute([$contact_id, $current_user_id]);

$sql = "SELECT 
            m.message, 
            m.sent_at, 
            u.username AS sender_username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
        OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$current_user_id, $contact_id, $contact_id, $current_user_id]);
$messages = $stmt->fetchAll();

foreach ($messages as $msg) {
    $msg_class = ($msg['sender_username'] == $_SESSION['username']) ? 'sent' : 'received';
    $message_content = htmlspecialchars($msg['message']);
    
    if (strpos($msg['message'], 'uploads/chat/') === 0) {
        $message_content = "<img src='" . htmlspecialchars($msg['message']) . "' alt='Image'>";
    }

    echo "<div class='message " . $msg_class . "'>";
    echo "<div class='message-text'>" . $message_content . "</div>";
    echo "<div class='message-time'>" . $msg['sent_at'] . "</div>";
    echo "</div>";
}
?>