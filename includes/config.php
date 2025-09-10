<?php
/**
 * Configuration file for AI Drowning Detection System
 * Vanilla PHP Implementation
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'drowning_detection');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// System configuration
define('SYSTEM_NAME', 'AI Drowning Detection System');
define('VERSION', '1.0.0');

// Detection settings
define('MOTION_THRESHOLD', 25); // Pixel difference threshold for motion detection
define('ALERT_TIMEOUT', 10); // Seconds without motion to trigger alert
define('FRAME_SKIP', 5); // Process every Nth frame for performance

// File paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('DATA_DIR', __DIR__ . '/../data/');
define('TEMP_DIR', __DIR__ . '/../temp/');

// Image processing settings
define('MAX_IMAGE_WIDTH', 640);
define('MAX_IMAGE_HEIGHT', 480);
define('JPEG_QUALITY', 80);

// Alert settings
define('EMAIL_ALERTS', false); // Set to true to enable email alerts
define('EMAIL_FROM', 'alerts@yourdomain.com');
define('EMAIL_TO', 'lifeguard@yourdomain.com');
define('SMTP_HOST', 'smtp.yourdomain.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_smtp_user');
define('SMTP_PASS', 'your_smtp_password');

// API Keys (for external AI services if needed)
define('AI_API_KEY', ''); // For external AI services
define('WEATHER_API_KEY', ''); // For weather data integration

// Debug settings
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
define('LOG_FILE', __DIR__ . '/../logs/system.log');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Performance settings
define('MEMORY_LIMIT', '256M');
define('MAX_EXECUTION_TIME', 300); // 5 minutes

// Set PHP configuration
ini_set('memory_limit', MEMORY_LIMIT);
ini_set('max_execution_time', MAX_EXECUTION_TIME);
ini_set('display_errors', DEBUG_MODE ? 1 : 0);
ini_set('log_errors', LOG_ERRORS ? 1 : 0);
if (LOG_ERRORS) {
    ini_set('error_log', LOG_FILE);
}

// Timezone
date_default_timezone_set('Asia/Manila');

// Create necessary directories
$directories = [UPLOAD_DIR, DATA_DIR, TEMP_DIR, dirname(LOG_FILE)];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Error handling
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " - Error [$errno] $errstr in $errfile on line $errline\n";
    if (LOG_ERRORS) {
        error_log($error_message, 3, LOG_FILE);
    }
    if (DEBUG_MODE) {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; margin: 10px; border: 1px solid #ff9999;'>";
        echo "<strong>PHP Error:</strong> $errstr<br>";
        echo "<strong>File:</strong> $errfile<br>";
        echo "<strong>Line:</strong> $errline";
        echo "</div>";
    }
}

set_error_handler("customErrorHandler");

// Utility functions
function logMessage($message, $level = 'INFO') {
    $log_entry = date('Y-m-d H:i:s') . " [$level] $message\n";
    if (LOG_ERRORS) {
        error_log($log_entry, 3, LOG_FILE);
    }
    if (DEBUG_MODE) {
        echo "<div style='background: #e6f3ff; padding: 5px; margin: 5px; border-left: 3px solid #0066cc;'>";
        echo "[$level] $message";
        echo "</div>";
    }
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function formatDateTime($timestamp = null) {
    $timestamp = $timestamp ?: time();
    return date('Y-m-d H:i:s', $timestamp);
}

// System health check
function systemHealthCheck() {
    $health = [
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'gd_enabled' => extension_loaded('gd'),
        'pdo_enabled' => extension_loaded('pdo'),
        'curl_enabled' => extension_loaded('curl'),
        'upload_dir_writable' => is_writable(UPLOAD_DIR),
        'temp_dir_writable' => is_writable(TEMP_DIR),
        'log_dir_writable' => is_writable(dirname(LOG_FILE))
    ];

    return $health;
}

// Load additional configuration if exists
$config_file = __DIR__ . '/config.local.php';
if (file_exists($config_file)) {
    require_once $config_file;
}
?>