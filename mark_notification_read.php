<?php
require_once 'auth/auth.php'; // For session and user_id
require_once 'auth/db_connect.php'; // For $pdo
require_once 'include/helpers.php'; // For markNotificationAsRead

// This script should ideally only be accessible via POST for security,
// but for simplicity with direct links from notifications, GET can be used
// if CSRF protection is considered (though not implemented in this step).

header('Content-Type: application/json'); // Respond with JSON

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$notification_id = $_GET['id'] ?? $_POST['id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Notification ID not provided.']);
    exit;
}

if (markNotificationAsRead($pdo, (int)$notification_id, $user_id)) {
    // Optionally, also return the new unread count
    $unread_count = getUnreadNotificationCount($pdo, $user_id);
    echo json_encode(['success' => true, 'message' => 'Notification marked as read.', 'unread_count' => $unread_count]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read or notification does not belong to user.']);
}
exit;
?>
