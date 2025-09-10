<?php
/**
 * Database operations for AI Drowning Detection System
 * Uses PDO for database connectivity
 */

class Database {
    private $pdo;
    private $connected = false;

    public function __construct() {
        $this->connect();
        $this->createTables();
    }

    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->connected = true;

            logMessage("Database connected successfully");

        } catch (PDOException $e) {
            // Fallback to SQLite if MySQL is not available
            $this->connectSQLite();
        }
    }

    private function connectSQLite() {
        try {
            $db_file = DATA_DIR . 'drowning_detection.db';
            $this->pdo = new PDO('sqlite:' . $db_file);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connected = true;

            logMessage("Connected to SQLite database");

        } catch (PDOException $e) {
            logMessage("Database connection failed: " . $e->getMessage(), 'ERROR');
            $this->connected = false;
        }
    }

    private function createTables() {
        if (!$this->connected) return;

        $sql = "
            CREATE TABLE IF NOT EXISTS alerts (
                id INTEGER PRIMARY KEY " . ($this->isSQLite() ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                alert_type VARCHAR(50) NOT NULL,
                details TEXT,
                severity VARCHAR(20) DEFAULT 'medium',
                resolved BOOLEAN DEFAULT FALSE,
                resolved_at DATETIME NULL
            );

            CREATE TABLE IF NOT EXISTS detections (
                id INTEGER PRIMARY KEY " . ($this->isSQLite() ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                motion_detected BOOLEAN DEFAULT FALSE,
                human_detected BOOLEAN DEFAULT FALSE,
                confidence DECIMAL(5,2) DEFAULT 0.00,
                frame_data TEXT,
                video_source VARCHAR(255)
            );

            CREATE TABLE IF NOT EXISTS system_logs (
                id INTEGER PRIMARY KEY " . ($this->isSQLite() ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                level VARCHAR(20) DEFAULT 'INFO',
                message TEXT,
                component VARCHAR(50)
            );

            CREATE TABLE IF NOT EXISTS system_stats (
                id INTEGER PRIMARY KEY " . ($this->isSQLite() ? 'AUTOINCREMENT' : 'AUTO_INCREMENT') . ",
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                cpu_usage DECIMAL(5,2),
                memory_usage DECIMAL(5,2),
                frames_processed INTEGER DEFAULT 0,
                alerts_generated INTEGER DEFAULT 0,
                uptime_seconds INTEGER DEFAULT 0
            );
        ";

        try {
            $this->pdo->exec($sql);
            logMessage("Database tables created successfully");
        } catch (PDOException $e) {
            logMessage("Error creating tables: " . $e->getMessage(), 'ERROR');
        }
    }

    private function isSQLite() {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public function logAlert($alert_type, $details, $severity = 'medium') {
        if (!$this->connected) return false;

        try {
            $sql = "INSERT INTO alerts (alert_type, details, severity) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$alert_type, $details, $severity]);

            $alert_id = $this->pdo->lastInsertId();
            logMessage("Alert logged: $alert_type - $details");

            return $alert_id;

        } catch (PDOException $e) {
            logMessage("Error logging alert: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function logDetection($motion_detected, $human_detected, $confidence = 0.0, $frame_data = null, $video_source = null) {
        if (!$this->connected) return false;

        try {
            $sql = "INSERT INTO detections (motion_detected, human_detected, confidence, frame_data, video_source)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$motion_detected, $human_detected, $confidence, $frame_data, $video_source]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            logMessage("Error logging detection: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function logSystemEvent($level, $message, $component = 'system') {
        if (!$this->connected) return false;

        try {
            $sql = "INSERT INTO system_logs (level, message, component) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$level, $message, $component]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            logMessage("Error logging system event: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function getRecentAlerts($limit = 10) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT * FROM alerts ORDER BY timestamp DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving alerts: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    public function getRecentDetections($limit = 10) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT * FROM detections ORDER BY timestamp DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving detections: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    public function getSystemStats($hours = 24) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT * FROM system_stats
                    WHERE timestamp >= datetime('now', '-{$hours} hours')
                    ORDER BY timestamp DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving system stats: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    public function resolveAlert($alert_id) {
        if (!$this->connected) return false;

        try {
            $sql = "UPDATE alerts SET resolved = TRUE, resolved_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$alert_id]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            logMessage("Error resolving alert: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function getAlertSummary($days = 7) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT
                        DATE(timestamp) as date,
                        COUNT(*) as total_alerts,
                        SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_severity,
                        SUM(CASE WHEN resolved = TRUE THEN 1 ELSE 0 END) as resolved
                    FROM alerts
                    WHERE timestamp >= datetime('now', '-{$days} days')
                    GROUP BY DATE(timestamp)
                    ORDER BY date DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error getting alert summary: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    public function cleanupOldData($days = 30) {
        if (!$this->connected) return false;

        try {
            // Clean old detections (keep last 30 days)
            $sql1 = "DELETE FROM detections WHERE timestamp < datetime('now', '-{$days} days')";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute();

            // Clean old system logs (keep last 7 days)
            $sql2 = "DELETE FROM system_logs WHERE timestamp < datetime('now', '-7 days')";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute();

            // Clean old system stats (keep last 7 days)
            $sql3 = "DELETE FROM system_stats WHERE timestamp < datetime('now', '-7 days')";
            $stmt3 = $this->pdo->prepare($sql3);
            $stmt3->execute();

            logMessage("Cleaned up old data (older than {$days} days)");

            return true;

        } catch (PDOException $e) {
            logMessage("Error cleaning up data: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    // User Management Methods
    public function createUser($username, $password, $email = null, $role = 'user') {
        if (!$this->connected) return false;

        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$username, $password_hash, $email, $role]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            logMessage("Error creating user: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function authenticateUser($username, $password) {
        if (!$this->connected) return false;

        try {
            $sql = "SELECT * FROM users WHERE username = ? AND active = TRUE";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                return $user;
            }

            return false;

        } catch (PDOException $e) {
            logMessage("Error authenticating user: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function updateLastLogin($user_id) {
        if (!$this->connected) return;

        try {
            $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            logMessage("Error updating last login: " . $e->getMessage(), 'ERROR');
        }
    }

    public function getUsers($active_only = true) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT id, username, email, role, created_at, last_login, active FROM users";
            if ($active_only) {
                $sql .= " WHERE active = TRUE";
            }
            $sql .= " ORDER BY username";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving users: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    // Camera Management Methods
    public function registerCamera($camera_id, $name, $location = null, $ip_address = null, $port = null) {
        if (!$this->connected) return false;

        try {
            $sql = "INSERT OR REPLACE INTO cameras (camera_id, name, location, ip_address, port, status, last_seen)
                    VALUES (?, ?, ?, ?, ?, 'online', CURRENT_TIMESTAMP)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$camera_id, $name, $location, $ip_address, $port]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            logMessage("Error registering camera: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function updateCameraStatus($camera_id, $status) {
        if (!$this->connected) return false;

        try {
            $sql = "UPDATE cameras SET status = ?, last_seen = CURRENT_TIMESTAMP WHERE camera_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$status, $camera_id]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            logMessage("Error updating camera status: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function getCameras($active_only = true) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT * FROM cameras";
            if ($active_only) {
                $sql .= " WHERE status = 'online'";
            }
            $sql .= " ORDER BY name";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving cameras: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    // Settings Management
    public function getSetting($key, $default = null) {
        if (!$this->connected) return $default;

        try {
            $sql = "SELECT setting_value, setting_type FROM settings WHERE setting_key = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$key]);
            $result = $stmt->fetch();

            if ($result) {
                return $this->castSettingValue($result['setting_value'], $result['setting_type']);
            }

            return $default;

        } catch (PDOException $e) {
            logMessage("Error retrieving setting: " . $e->getMessage(), 'ERROR');
            return $default;
        }
    }

    public function setSetting($key, $value, $type = 'string', $description = null, $user_id = null) {
        if (!$this->connected) return false;

        try {
            $sql = "INSERT OR REPLACE INTO settings (setting_key, setting_value, setting_type, description, updated_by)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$key, (string)$value, $type, $description, $user_id]);

            return true;

        } catch (PDOException $e) {
            logMessage("Error setting value: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function castSettingValue($value, $type) {
        switch ($type) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'bool':
            case 'boolean':
                return $value === '1' || $value === 'true';
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    // Audit Logging
    public function logAudit($user_id, $action, $table_name = null, $record_id = null,
                           $old_values = null, $new_values = null) {
        if (!$this->connected) return false;

        try {
            $sql = "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $user_id,
                $action,
                $table_name,
                $record_id,
                $old_values ? json_encode($old_values) : null,
                $new_values ? json_encode($new_values) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            logMessage("Error logging audit: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function getAuditLog($limit = 100, $user_id = null) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id";
            if ($user_id) {
                $sql .= " WHERE a.user_id = ?";
            }
            $sql .= " ORDER BY a.timestamp DESC LIMIT ?";

            $stmt = $this->pdo->prepare($sql);
            if ($user_id) {
                $stmt->execute([$user_id, $limit]);
            } else {
                $stmt->execute([$limit]);
            }

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving audit log: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    // Advanced Analytics
    public function getAdvancedAnalytics($date_from = null, $date_to = null) {
        if (!$this->connected) return [];

        try {
            $date_condition = "";
            $params = [];

            if ($date_from && $date_to) {
                $date_condition = "WHERE DATE(timestamp) BETWEEN ? AND ?";
                $params = [$date_from, $date_to];
            }

            $sql = "
                SELECT
                    DATE(timestamp) as date,
                    COUNT(CASE WHEN alert_type = 'DROWNING_ALERT' THEN 1 END) as drowning_alerts,
                    COUNT(CASE WHEN alert_type = 'MOTION_ALERT' THEN 1 END) as motion_alerts,
                    COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_severity,
                    AVG(CASE WHEN processing_time > 0 THEN processing_time END) as avg_processing_time,
                    COUNT(DISTINCT camera_id) as active_cameras,
                    SUM(CASE WHEN motion_detected THEN 1 ELSE 0 END) as total_motion_events
                FROM (
                    SELECT timestamp, alert_type, severity, NULL as processing_time, camera_id, 1 as motion_detected
                    FROM alerts
                    $date_condition

                    UNION ALL

                    SELECT timestamp, 'DETECTION' as alert_type, 'info' as severity,
                           processing_time, camera_id, motion_detected
                    FROM detections
                    $date_condition
                )
                GROUP BY DATE(timestamp)
                ORDER BY date DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving advanced analytics: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    // Report Generation
    public function generateReport($report_type, $date_from, $date_to, $user_id = null) {
        if (!$this->connected) return false;

        try {
            $data = [];

            switch ($report_type) {
                case 'alerts_summary':
                    $data = $this->getAlertSummary(ceil((strtotime($date_to) - strtotime($date_from)) / 86400));
                    break;

                case 'detection_analysis':
                    $data = $this->getAdvancedAnalytics($date_from, $date_to);
                    break;

                case 'system_performance':
                    $data = $this->getSystemStats(ceil((strtotime($date_to) - strtotime($date_from)) / 86400));
                    break;

                case 'user_activity':
                    $data = $this->getAuditLog(1000, $user_id);
                    break;
            }

            // Save report
            $report_data = [
                'type' => $report_type,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'generated_at' => date('Y-m-d H:i:s'),
                'data' => $data
            ];

            $sql = "INSERT INTO reports (report_type, date_range_start, date_range_end, generated_by, data)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $report_type,
                $date_from,
                $date_to,
                $user_id,
                json_encode($report_data)
            ]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            logMessage("Error generating report: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function getReports($user_id = null, $limit = 50) {
        if (!$this->connected) return [];

        try {
            $sql = "SELECT r.*, u.username FROM reports r LEFT JOIN users u ON r.generated_by = u.id";
            if ($user_id) {
                $sql .= " WHERE r.generated_by = ?";
            }
            $sql .= " ORDER BY r.generated_at DESC LIMIT ?";

            $stmt = $this->pdo->prepare($sql);
            if ($user_id) {
                $stmt->execute([$user_id, $limit]);
            } else {
                $stmt->execute([$limit]);
            }

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            logMessage("Error retrieving reports: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    // Data Export
    public function exportTable($table_name, $format = 'json', $conditions = []) {
        if (!$this->connected) return false;

        try {
            $sql = "SELECT * FROM $table_name";
            $params = [];

            if (!empty($conditions)) {
                $where_clauses = [];
                foreach ($conditions as $column => $value) {
                    $where_clauses[] = "$column = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(" AND ", $where_clauses);
            }

            $sql .= " ORDER BY id DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            switch ($format) {
                case 'json':
                    return json_encode($data, JSON_PRETTY_PRINT);

                case 'csv':
                    return $this->arrayToCsv($data);

                case 'xml':
                    return $this->arrayToXml($data, $table_name);

                default:
                    return json_encode($data);
            }

        } catch (PDOException $e) {
            logMessage("Error exporting table: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function arrayToCsv($data) {
        if (empty($data)) return '';

        $output = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function arrayToXml($data, $root_name) {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><{$root_name}s></{$root_name}s>");

        foreach ($data as $item) {
            $item_xml = $xml->addChild($root_name);
            foreach ($item as $key => $value) {
                $item_xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml->asXML();
    }

    // Database Maintenance
    public function optimizeDatabase() {
        if (!$this->connected) return false;

        try {
            if ($this->isSQLite()) {
                $this->pdo->exec('VACUUM');
                logMessage("SQLite database optimized (VACUUM)");
            } else {
                // For MySQL
                $this->pdo->exec('OPTIMIZE TABLE alerts, detections, system_logs, system_stats');
                logMessage("MySQL tables optimized");
            }

            return true;

        } catch (PDOException $e) {
            logMessage("Error optimizing database: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    public function getDatabaseInfo() {
        if (!$this->connected) return [];

        try {
            $info = [
                'type' => $this->isSQLite() ? 'SQLite' : 'MySQL',
                'version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'connection_status' => 'Connected',
                'tables' => []
            ];

            if ($this->isSQLite()) {
                $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
            } else {
                $stmt = $this->pdo->query("SHOW TABLES");
            }

            $info['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return $info;

        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getConnectionStatus() {
        return $this->connected;
    }

    public function __destruct() {
        if ($this->connected) {
            $this->pdo = null;
            $this->connected = false;
        }
    }
}

// Initialize database connection
$db = new Database();
?>