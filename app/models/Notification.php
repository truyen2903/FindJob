<?php
require_once __DIR__ . '/Database.php';

class Notification extends Database
{
    public function create(int $userId, string $title, string $message, array $options = []): bool
    {
        if ($userId <= 0 || $title === '' || $message === '') {
            return false;
        }

        $icon = trim((string)($options['icon'] ?? 'fa-solid fa-bell'));
        $actionUrl = trim((string)($options['action_url'] ?? ''));
        if ($actionUrl !== '') {
            $message = rtrim($message) . "\n" . $actionUrl;
        }
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, title, message, icon_path, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('isss', $userId, $title, $message, $icon);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function countUnread(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = 0;
        if ($result && ($row = $result->fetch_assoc())) {
            $count = (int)($row['total'] ?? 0);
            $result->free();
        }
        $stmt->close();
        return $count;
    }

    public function getRecent(int $userId, int $limit = 5): array
    {
        if ($userId <= 0) {
            return [];
        }
        $limit = max(1, $limit);
        $stmt = $this->conn->prepare("SELECT id, title, message, icon_path, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['is_read'] = (bool)($row['is_read'] ?? 0);
                $notifications[] = $row;
            }
            $result->free();
        }
        $stmt->close();
        return $notifications;
    }

    public function markAllRead(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function markAsRead(int $userId, int $notificationId): bool
    {
        if ($userId <= 0 || $notificationId <= 0) {
            return false;
        }
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('ii', $notificationId, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}
