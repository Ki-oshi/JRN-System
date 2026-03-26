<?php

class ActivityLogger
{
    private $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    /**
     * Log an activity
     */
    public function log($user_id, $user_type, $action, $description)
    {
        $ip_address = $this->getIpAddress();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

        $stmt = $this->conn->prepare("
            INSERT INTO activity_logs (user_id, user_type, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        // Handle NULL user_id for failed login attempts
        if ($user_id === null) {
            $null_id = null;
            $stmt->bind_param("isssss", $null_id, $user_type, $action, $description, $ip_address, $user_agent);
        } else {
            $stmt->bind_param("isssss", $user_id, $user_type, $action, $description, $ip_address, $user_agent);
        }

        $stmt->execute();
    }

    /**
     * Get real IP address
     */
    private function getIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }

    /**
     * Get logs with filters
     */
    public function getLogs($filters = [])
    {
        $where = [];
        $params = [];
        $types = '';

        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

        if (!empty($filters['user_type'])) {
            $where[] = "user_type = ?";
            $params[] = $filters['user_type'];
            $types .= 's';
        }

        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $limit = $filters['limit'] ?? 100;

        $sql = "SELECT al.*, 
                COALESCE(u.email, e.email, 'Deleted User') as user_email,
                COALESCE(u.first_name, e.first_name, '') as first_name,
                COALESCE(u.last_name, e.last_name, '') as last_name
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id AND al.user_type = 'user'
                LEFT JOIN employees e ON al.user_id = e.id AND al.user_type IN ('admin', 'employee')";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY al.created_at DESC LIMIT ?";

        $stmt = $this->conn->prepare($sql);

        if (!empty($params)) {
            $types .= 'i';
            $params[] = $limit;
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param('i', $limit);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

/**
 * Quick helper function
 */
function logActivity($user_id, $user_type, $action, $description)
{
    global $conn;
    if (!isset($conn)) return;

    $logger = new ActivityLogger($conn);
    $logger->log($user_id, $user_type, $action, $description);
}
