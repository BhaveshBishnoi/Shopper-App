<?php
// Initialize notifications array in session if it doesn't exist
if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [];
}

/**
 * Add a notification to the session
 * @param string $message The notification message
 * @param string $type The type of notification (success, error, warning, info)
 */
function add_notification($message, $type = 'info') {
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    $_SESSION['notifications'][] = [
        'message' => $message,
        'type' => $type,
        'id' => uniqid('notification_')
    ];
}

/**
 * Get and clear all notifications
 * @return array The notifications
 */
function get_notifications() {
    $notifications = $_SESSION['notifications'] ?? [];
    $_SESSION['notifications'] = [];
    return $notifications;
}

/**
 * Check if there are any notifications
 * @return bool True if there are notifications, false otherwise
 */
function has_notifications() {
    return !empty($_SESSION['notifications']);
}
