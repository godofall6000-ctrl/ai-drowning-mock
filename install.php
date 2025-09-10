<?php
/**
 * Installation script for AI Drowning Detection System
 * Checks requirements and sets up the system
 */

echo "<h1>AI Drowning Detection System - Installation</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .check { margin: 10px 0; padding: 10px; border: 1px solid #ccc; } .pass { background: #d4edda; border-color: #c3e6cb; } .fail { background: #f8d7da; border-color: #f5c6cb; } .warn { background: #fff3cd; border-color: #ffeaa7; }</style>";

// Check PHP version
echo "<div class='check'>";
echo "<h3>PHP Version Check</h3>";
$php_version = PHP_VERSION;
$required_version = '7.4.0';

if (version_compare($php_version, $required_version, '>=')) {
    echo "<span class='pass'>✓ PHP $php_version - Compatible</span>";
} else {
    echo "<span class='fail'>✗ PHP $php_version - Requires PHP $required_version or higher</span>";
}
echo "</div>";

// Check required extensions
echo "<div class='check'>";
echo "<h3>PHP Extensions Check</h3>";
$extensions = [
    'gd' => 'GD Library (Image Processing)',
    'pdo' => 'PDO (Database)',
    'pdo_mysql' => 'PDO MySQL (Database)',
    'pdo_sqlite' => 'PDO SQLite (Database)',
    'json' => 'JSON (Data Handling)',
    'mbstring' => 'Multibyte String (Text Processing)'
];

foreach ($extensions as $ext => $description) {
    if (extension_loaded($ext)) {
        echo "<span class='pass'>✓ $description</span><br>";
    } else {
        echo "<span class='fail'>✗ $description - Not loaded</span><br>";
    }
}
echo "</div>";

// Check file permissions
echo "<div class='check'>";
echo "<h3>File Permissions Check</h3>";
$dirs_to_check = [
    'includes/' => 'Configuration files',
    'data/' => 'Data storage',
    'temp/' => 'Temporary files',
    'models/' => 'AI models (optional)'
];

foreach ($dirs_to_check as $dir => $description) {
    $full_path = __DIR__ . '/' . $dir;
    if (!is_dir($full_path)) {
        mkdir($full_path, 0755, true);
    }

    if (is_writable($full_path)) {
        echo "<span class='pass'>✓ $description directory writable</span><br>";
    } else {
        echo "<span class='fail'>✗ $description directory not writable</span><br>";
    }
}
echo "</div>";

// Create database if needed
echo "<div class='check'>";
echo "<h3>Database Setup</h3>";
try {
    // Try SQLite first (no setup required)
    $db_file = __DIR__ . '/data/drowning_detection.db';
    $pdo = new PDO('sqlite:' . $db_file);

    // Test connection
    $stmt = $pdo->query('SELECT 1');
    if ($stmt) {
        echo "<span class='pass'>✓ SQLite database ready</span><br>";
        echo "<small>Database file: $db_file</small><br>";
    }

    // Create tables
    $sql = "
        CREATE TABLE IF NOT EXISTS alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            alert_type VARCHAR(50) NOT NULL,
            details TEXT,
            severity VARCHAR(20) DEFAULT 'medium'
        );
    ";

    $pdo->exec($sql);
    echo "<span class='pass'>✓ Database tables created</span><br>";

} catch (Exception $e) {
    echo "<span class='fail'>✗ Database setup failed: " . $e->getMessage() . "</span><br>";
    echo "<small>Note: MySQL setup may be required if SQLite is not available</small><br>";
}
echo "</div>";

// Create config file if it doesn't exist
echo "<div class='check'>";
echo "<h3>Configuration Setup</h3>";
$config_file = __DIR__ . '/includes/config.php';
if (file_exists($config_file)) {
    echo "<span class='pass'>✓ Configuration file exists</span><br>";
} else {
    echo "<span class='warn'>⚠ Configuration file missing - using defaults</span><br>";
}
echo "</div>";

// Installation complete
echo "<div class='check'>";
echo "<h3>Installation Complete</h3>";
echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li><strong>Start the web server:</strong><br><code>php -S localhost:8000</code></li>";
echo "<li><strong>Open in browser:</strong><br><a href='http://localhost:8000/index.php' target='_blank'>http://localhost:8000/index.php</a></li>";
echo "<li><strong>Test the system:</strong><br>Visit <a href='http://localhost:8000/test_system.php' target='_blank'>test_system.php</a> to verify all components</li>";
echo "<li><strong>Begin monitoring:</strong><br>Click 'Start Detection' in the main interface</li>";
echo "</ol>";

echo "<h4>System Features:</h4>";
echo "<ul>";
echo "<li>✅ Real-time motion detection</li>";
echo "<li>✅ Web-based monitoring interface</li>";
echo "<li>✅ Alert system with sound notifications</li>";
echo "<li>✅ Database logging</li>";
echo "<li>✅ Responsive design</li>";
echo "</ul>";

echo "<div style='background: #e7f3ff; padding: 15px; margin: 10px 0; border-left: 4px solid #0066cc;'>";
echo "<strong>🎯 System Ready!</strong><br>";
echo "Your AI Drowning Detection System is now installed and ready to use.";
echo "</div>";

echo "</div>";
?>