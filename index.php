<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

$login_error = null;
if (isset($_SESSION['login_error'])) {
    $login_error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$current_username = $is_logged_in ? $_SESSION['username'] : null;

$users = [];
if ($is_logged_in) {
    try {
        $sql = "
            SELECT 
                u.id, 
                u.username,
                u.profile_picture,
                (SELECT message FROM messages 
                 WHERE (sender_id = u.id AND receiver_id = ?) 
                 OR (sender_id = ? AND receiver_id = u.id)
                 ORDER BY sent_at DESC LIMIT 1) AS last_message,
                (SELECT sent_at FROM messages 
                 WHERE (sender_id = u.id AND receiver_id = ?) 
                 OR (sender_id = ? AND receiver_id = u.id)
                 ORDER BY sent_at DESC LIMIT 1) AS last_message_time
            FROM users u
            WHERE u.id != ?
            ORDER BY last_message_time DESC, u.username ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
        $users = $stmt->fetchAll();
    } catch (PDOException $e) {
        $users = [];
        error_log("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            transition: all 0.3s ease-in-out;
        }

        body {
            background-color: #202124;
            color: #e8eaed;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .chat-container {
            width: 100%;
            max-width: 1200px;
            height: 90vh;
            background-color: #2c2c2e;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        /* Authentication UI Styles */
        .auth-container {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: #2c2c2e;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .auth-container h2 {
            margin-bottom: 20px;
            color: #8ab4f8;
        }

        .auth-container input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #5f6368;
            background: #3c4043;
            color: #e8eaed;
            border-radius: 8px;
            outline: none;
        }

        .auth-container input:focus {
            border-color: #8ab4f8;
            box-shadow: 0 0 5px rgba(138, 180, 248, 0.5);
        }

        .auth-container button {
            width: 100%;
            padding: 12px;
            border: none;
            background: #8ab4f8;
            color: #202124;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }

        .auth-container button:hover {
            background: #a3c4ff;
        }

        .auth-container .switch-form {
            margin-top: 15px;
            font-size: 14px;
        }

        .auth-container .switch-form a {
            color: #8ab4f8;
            text-decoration: none;
        }

        .alert-popup {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            color: white;
            background-color: #f44336;
        }

        /* Chat UI Styles */
        .sidebar {
            width: 350px;
            background-color: #202124;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #3c4043;
        }

        .sidebar-header {
            padding: 15px;
            background-color: #202124;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #3c4043;
        }

        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: #5f6368;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 20px;
            color: #fff;
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-avatar i {
            color: #8ab4f8;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .sidebar-icons {
            display: flex;
            align-items: center;
        }
        
        .sidebar-icons i {
            font-size: 20px;
            color: #8ab4f8;
            cursor: pointer;
            transition: transform 0.2s ease-in-out;
            margin-left: 15px;
        }

        .sidebar-icons i:hover {
            transform: scale(1.1);
        }

        .search-container {
            padding: 10px 15px;
        }

        .search-box {
            background-color: #3c4043;
            border-radius: 20px;
            padding: 8px 15px;
            display: flex;
            align-items: center;
        }

        .search-box i {
            color: #8ab4f8;
        }

        .search-box input {
            background: transparent;
            border: none;
            color: #e8eaed;
            width: 100%;
            padding: 5px 10px;
            outline: none;
        }

        .chat-list {
            flex: 1;
            overflow-y: auto;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #3c4043;
            cursor: pointer;
            position: relative; /* For notification dot */
        }
        
        .chat-item:hover, .chat-item.active {
            background-color: #3c4043;
        }

        .chat-item .user-avatar {
            margin-right: 15px;
        }

        .chat-info {
            flex: 1;
        }

        .chat-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .chat-latest {
            font-size: 14px;
            color: #bdc1c6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* New Styles for Notification Dot */
        .notification-dot {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background-color: #00e676; /* Green dot */
            border-radius: 50%;
            display: none; /* Hide by default */
        }
        
        /* End of New Styles */


        .main-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #202124;
        }

        .chat-header {
            padding: 15px 20px;
            background-color: #202124;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #3c4043;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .current-chat-info {
            display: flex;
            align-items: center;
        }

        .back-icon {
            display: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            margin-right: 15px;
        }

        .current-chat-info .user-avatar {
            margin-right: 15px;
        }

        .messages-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .message {
            max-width: 65%;
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 20px;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .message img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .message.received {
            background-color: #3c4043;
            align-self: flex-start;
            border-top-left-radius: 5px;
        }

        .message.sent {
            background-color: #8ab4f8;
            color: #202124;
            align-self: flex-end;
            border-top-right-radius: 5px;
        }

        .message-time {
            font-size: 11px;
            color: #aebac1;
            text-align: right;
            margin-top: 5px;
        }

        .message.sent .message-time {
            color: #3c4043;
        }

        .message-input-container {
            padding: 10px 15px;
            background-color: #202124;
            display: flex;
            align-items: center;
            gap: 10px;
            border-top: 1px solid #3c4043;
        }
        
        #message-form {
            display: flex;
            flex: 1;
            align-items: center;
        }
        
        .message-input {
            flex: 1;
            background-color: #3c4043;
            border-radius: 25px;
            padding: 5px 15px;
        }

        .message-input input {
            width: 100%;
            background: transparent;
            border: none;
            color: #e8eaed;
            outline: none;
        }

        .send-button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: #8ab4f8;
            color: #202124;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: transform 0.2s ease-in-out;
            margin-left: 10px;
        }

        .send-button:hover {
            transform: scale(1.1);
        }

        /* File Upload Styles */
        .file-upload-button {
            background: none;
            border: none;
            color: #8ab4f8;
            font-size: 20px;
            cursor: pointer;
            margin-right: 10px;
            padding: 10px;
        }
        
        .file-preview {
            display: none;
            position: absolute;
            bottom: 60px;
            left: 20px;
            right: 20px;
            padding: 10px;
            background: #3c4043;
            border-radius: 10px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
        }
        
        .file-preview-name {
            font-size: 14px;
            color: #bdc1c6;
            margin-top: 5px;
            word-break: break-all;
        }
        
        .file-preview .close-preview {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.5);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .pfp-upload-icon {
            cursor: pointer;
            font-size: 20px;
            margin-left: 15px;
        }
        
        /* New Notification Popup Styles */
        #notification-popup {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #3c4043;
            color: #e8eaed;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            max-width: 300px;
            cursor: pointer;
            z-index: 1000;
            display: none; /* Hidden by default */
            animation: slideInFromTop 0.5s ease-out;
        }
        
        #notification-popup h4 {
            margin: 0 0 5px;
        }
        
        #notification-popup p {
            margin: 0;
            font-size: 14px;
            color: #bdc1c6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @keyframes slideInFromTop {
            from { opacity: 0; transform: translateY(-20px) translateX(-50%); }
            to { opacity: 1; transform: translateY(0) translateX(-50%); }
        }

        /* Mobile Optimization */
        @media (max-width: 768px) {
            .chat-container {
                height: 100vh;
                border-radius: 0;
            }

            .sidebar {
                width: 100%;
                display: flex; /* Ensure it's a flex container on mobile */
            }

            .main-chat {
                display: none;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }
            
            .main-chat.active {
                display: flex;
            }

            .sidebar.active {
                display: none;
            }

            .back-icon {
                display: block;
            }
        }
    </style>
</head>
<body>

    <?php if (!$is_logged_in): ?>
        <div class="auth-container">
            <?php if ($login_error): ?>
                <div class="alert-popup" style="background-color: #f44336;"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <div id="login-form">
                <h2>Login</h2>
                <form id="login-form-id" action="login.php" method="POST">
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
                <div class="switch-form">
                    Don't have an account? <a href="#" onclick="showSignup()">Sign Up</a>
                </div>
            </div>
            <div id="signup-form" style="display: none;">
                <h2>Sign Up</h2>
                <form id="signup-form-id" action="signup.php" method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Sign Up</button>
                </form>
                <div class="switch-form">
                    Already have an account? <a href="#" onclick="showLogin()">Login</a>
                </div>
            </div>
        </div>
        <script>
            function showSignup() {
                document.getElementById('login-form').style.display = 'none';
                document.getElementById('signup-form').style.display = 'block';
            }
            function showLogin() {
                document.getElementById('login-form').style.display = 'block';
                document.getElementById('signup-form').style.display = 'none';
            }
        </script>
    <?php else: ?>
        <div class="chat-container">
            <div class="sidebar">
                <div class="sidebar-header">
                    <div class="user-info">
                        <div class="user-avatar" id="my-pfp-avatar">
                            <?php if (!empty($_SESSION['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="My PFP">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($current_username); ?></div>
                    </div>
                    <div class="sidebar-icons">
                        <i class="fas fa-camera pfp-upload-icon" title="Change Profile Picture"></i>
                        <input type="file" id="pfp-input" style="display: none;" accept="image/*">
                        <a href="logout.php" style="color: inherit;"><i class="fas fa-sign-out-alt" title="Logout"></i></a>
                    </div>
                </div>
                
                <div class="search-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="contact-search" placeholder="Search contacts...">
                    </div>
                </div>
                
                <div class="chat-list">
                    <?php if (empty($users)): ?>
                        <div style="text-align: center; padding: 20px; color: #8696a0;">No other users found.</div>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <div class="chat-item" data-id="<?php echo $user['id']; ?>">
                                <div class="user-avatar">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="PFP">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-info">
                                    <div class="chat-name">
                                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                    <div class="chat-latest">
                                        <?php echo $user['last_message'] ? htmlspecialchars($user['last_message']) : "Tap to start chatting..."; ?>
                                    </div>
                                </div>
                                <div class="notification-dot"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="main-chat" id="main-chat-window">
                <div class="chat-header">
                    <i class="fas fa-arrow-left back-icon" onclick="goBackToContacts()"></i>
                    <div class="current-chat-info">
                        <div class="user-avatar" id="current-chat-avatar">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div class="chat-name" id="current-chat-name">Select a chat</div>
                        </div>
                    </div>
                </div>
                
                <div class="messages-container">
                    <p style="text-align: center; color: #8696a0; margin-top: 20px;">
                        Select a chat to start messaging.
                    </p>
                </div>

                <div class="file-preview" id="file-preview-box" style="display: none;">
                    <span class="close-preview" id="close-preview-button">&times;</span>
                    <div id="file-preview-content"></div>
                    <div class="file-preview-name" id="file-preview-name"></div>
                </div>
                
                <div class="message-input-container" style="display: none;">
                    <form id="message-form" action="send_message.php" method="POST" enctype="multipart/form-data">
                        <input type="file" id="file-input" name="file" style="display: none;">
                        <button type="button" class="file-upload-button" id="file-upload-button">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <div class="message-input">
                            <input type="text" id="message-input" name="message" placeholder="Type a message">
                        </div>
                        <input type="hidden" name="receiver_id" id="receiver-id-input">
                        <div class="send-button">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="notification-popup">
            <h4 id="notification-sender"></h4>
            <p id="notification-message"></p>
        </div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            $(document).ready(function() {
                let currentChatId = null;
                let messageLoadingInterval = null;
                let notificationTimeout = null;

                function scrollToBottom() {
                    const container = $('.messages-container');
                    container.scrollTop(container[0].scrollHeight);
                }

                function loadMessages(isInitialLoad = false) {
                    if (currentChatId) {
                        const container = $('.messages-container');
                        const isAtBottom = container[0].scrollHeight - container.scrollTop() <= container.outerHeight() + 10;
                        
                        $.ajax({
                            url: 'get_messages.php',
                            type: 'GET',
                            data: { contact_id: currentChatId },
                            success: function(data) {
                                container.html(data);
                                if (isInitialLoad || isAtBottom) {
                                    scrollToBottom();
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Error loading messages:', error);
                            }
                        });
                    }
                }

                function checkForNewMessages() {
                    $('.chat-item').each(function() {
                        const contactId = $(this).data('id');
                        const chatItem = $(this);
                        $.ajax({
                            url: 'get_last_message.php',
                            type: 'GET',
                            data: { contact_id: contactId },
                            success: function(response) {
                                const data = JSON.parse(response);
                                const lastMessage = data.last_message;
                                const isRead = data.is_read;
                                
                                if (lastMessage) {
                                    const latestMessageElement = chatItem.find('.chat-latest');
                                    if (latestMessageElement.text() !== lastMessage) {
                                        // New message received
                                        latestMessageElement.text(lastMessage);
                                        chatItem.prependTo($('.chat-list'));
                                        if (!isRead && currentChatId !== contactId) {
                                            chatItem.find('.notification-dot').show();
                                        }
                                    }
                                }
                            }
                        });
                    });
                }
                
                function checkForNotifications() {
                    $.ajax({
                        url: 'check_for_notifications.php',
                        type: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            if (data.status === 'success' && data.sender_id != <?php echo $current_user_id; ?>) {
                                // A new message has arrived from another user
                                const senderName = data.sender_name;
                                const messageText = data.message;
                                const senderId = data.sender_id;
                                
                                // Show the notification popup
                                showInAppNotification(senderName, messageText, senderId);
                                
                                // Also update the main chat list to reflect the new message
                                checkForNewMessages();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error checking for notifications:', error);
                        }
                    });
                }

                function showInAppNotification(senderName, message, senderId) {
                    const popup = $('#notification-popup');
                    
                    // Clear any existing timeout to prevent flickering
                    if(notificationTimeout) {
                        clearTimeout(notificationTimeout);
                    }
                    
                    const notificationSender = $('#notification-sender');
                    const notificationMessage = $('#notification-message');
                    
                    notificationSender.text(senderName);
                    notificationMessage.text(message);
                    popup.attr('data-sender-id', senderId);
                    
                    popup.fadeIn();
                    
                    notificationTimeout = setTimeout(function() {
                        popup.fadeOut();
                    }, 2000); // Hide after 2 seconds
                }
                
                $('#notification-popup').on('click', function() {
                    const senderId = $(this).attr('data-sender-id');
                    if (senderId) {
                        const chatItem = $(`.chat-item[data-id="${senderId}"]`);
                        if (chatItem.length) {
                            chatItem.click();
                            $(this).fadeOut(); // Hide the notification
                        }
                    }
                });

                // Check for new notifications every 3 seconds
                setInterval(checkForNotifications, 3000);
                
                // Initial check for chat list updates
                setInterval(checkForNewMessages, 5000);

                $('#file-upload-button').on('click', function() {
                    $('#file-input').click();
                });

                $('#file-input').on('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewContent = $('#file-preview-content');
                            previewContent.empty();
                            if (file.type.startsWith('image/')) {
                                previewContent.append(`<img src="${e.target.result}" alt="Image Preview">`);
                            } else {
                                previewContent.append(`<i class="fas fa-file-alt" style="font-size: 50px; color: #8ab4f8;"></i>`);
                            }
                            $('#file-preview-name').text(file.name);
                            $('#file-preview-box').show();
                        };
                        reader.readAsDataURL(file);
                    }
                });

                $('#close-preview-button').on('click', function() {
                    $('#file-preview-box').hide();
                    $('#file-input').val('');
                });

                $('.send-button').on('click', function(e) {
                    e.preventDefault();
                    
                    const messageText = $('#message-input').val().trim();
                    const file = $('#file-input')[0].files[0];
                    
                    if (messageText === "" && !file) {
                        return;
                    }
                    const receiverId = $('#receiver-id-input').val();
                    
                    if (receiverId) {
                        const formData = new FormData($('#message-form')[0]);
                        
                        if (messageText !== "") {
                             formData.append('message', messageText);
                        } else {
                            formData.append('message', '');
                        }
                        
                        formData.append('receiver_id', receiverId);
                        
                        let sentMessageHtml = '';
                        let displayMessage = '';

                        if (file && file.type.startsWith('image/')) {
                            sentMessageHtml = `<div class="message sent"><img src="${URL.createObjectURL(file)}" style="max-width:100%; max-height: 200px; border-radius: 10px;"></div>`;
                            displayMessage = 'Image sent';
                        } else if (file) {
                            sentMessageHtml = `<div class="message sent">
                                <i class="fas fa-file" style="font-size: 20px; color: #8ab4f8; margin-right: 5px;"></i>
                                <span>${file.name}</span>
                            </div>`;
                            displayMessage = `File: ${file.name}`;
                        } else {
                            sentMessageHtml = `
                                <div class="message sent">
                                    <div class="message-text">${messageText}</div>
                                    <div class="message-time">Just now</div>
                                </div>`;
                            displayMessage = messageText;
                        }
                        
                        const container = $('.messages-container');
                        container.append(sentMessageHtml);
                        scrollToBottom();
                        
                        $('#message-input').val('');
                        $('#file-input').val('');
                        $('#file-preview-box').hide();

                        const currentContactItem = $(`.chat-item[data-id="${receiverId}"]`);
                        currentContactItem.find('.chat-latest').text(displayMessage);
                        currentContactItem.prependTo($('.chat-list'));
                        
                        $.ajax({
                            url: 'send_message.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            error: function(xhr, status, error) {
                                console.error('Error sending message:', error);
                            }
                        });
                    }
                });

                $('#message-input').on('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        $('.send-button').click();
                    }
                });

                $('.chat-item').on('click', function() {
                    $('.chat-item').removeClass('active');
                    $(this).addClass('active');

                    currentChatId = $(this).data('id');
                    const chatName = $(this).find('.chat-name span').text();
                    
                    $('#current-chat-name').text(chatName);
                    
                    const userAvatarHtml = $(this).find('.user-avatar').html();
                    $('#current-chat-avatar').html(userAvatarHtml);
                    
                    $('#receiver-id-input').val(currentChatId);

                    $('.messages-container').html('<p style="text-align: center; color: #8696a0; margin-top: 20px;">Loading messages...</p>');
                    $('.message-input-container').show();
                    
                    if (messageLoadingInterval) {
                        clearInterval(messageLoadingInterval);
                    }
                    loadMessages(true); // Initial load, always scroll to bottom
                    messageLoadingInterval = setInterval(loadMessages, 3000);
                    
                    // Hide the notification dot when the chat is opened
                    $(this).find('.notification-dot').hide();

                    if ($(window).width() <= 768) {
                        $('.sidebar').removeClass('active');
                        $('#main-chat-window').addClass('active');
                    }
                });

                $('#contact-search').on('keyup', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    $('.chat-item').each(function() {
                        const chatName = $(this).find('.chat-name span').text().toLowerCase();
                        if (chatName.includes(searchTerm)) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });
                
                // PFP Upload Logic
                $('#my-pfp-avatar, .pfp-upload-icon').on('click', function() {
                    $('#pfp-input').click();
                });
                
                $('#pfp-input').on('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const formData = new FormData();
                        formData.append('pfp', file);

                        $.ajax({
                            url: 'upload_pfp.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                alert(response);
                                window.location.reload(); 
                            },
                            error: function(xhr, status, error) {
                                alert('Error uploading PFP.');
                                console.error('PFP upload error:', error);
                            }
                        });
                    }
                });
            });

            function goBackToContacts() {
                // Ensure the sidebar is visible and the main chat window is hidden
                $('.sidebar').removeClass('active');
                $('#main-chat-window').removeClass('active');
            }
        </script>
    <?php endif; ?>

</body>
</html>