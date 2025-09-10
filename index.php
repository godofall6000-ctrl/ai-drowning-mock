<?php
/**
 * AI Drowning Detection System - Main Entry Point
 * Vanilla PHP Implementation
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/motion_detector.php';
require_once 'includes/alert_system.php';

// Start session for web interface
session_start();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');

    try {
        switch ($_GET['action']) {
            case 'start_detection':
                $result = startDetection();
                echo json_encode($result);
                break;

            case 'stop_detection':
                $result = stopDetection();
                echo json_encode($result);
                break;

            case 'get_status':
                $result = getDetectionStatus();
                echo json_encode($result);
                break;

            case 'get_alerts':
                $result = getRecentAlerts();
                echo json_encode($result);
                break;

            case 'process_frame':
                $result = processDetectionFrame();
                echo json_encode($result);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Web interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Drowning Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #6c757d; }
        .status-alert { background-color: #dc3545; }
        .video-container { max-width: 800px; margin: 0 auto; }
        .alert-item { padding: 10px; border-left: 4px solid #dc3545; margin-bottom: 10px; }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                🔍 AI Drowning Detection System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="database.php">Database</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="test_system.php">Test System</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="login.php">Login</a></li>
                            <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h1 class="text-center mb-4">AI Drowning Detection System</h1>

                <!-- Status Panel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <span class="status-indicator status-inactive" id="status-indicator"></span>
                                <span id="status-text">System Inactive</span>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-success" id="start-btn">Start Detection</button>
                                <button class="btn btn-danger" id="stop-btn" disabled>Stop Detection</button>
                            </div>
                            <div class="col-md-4">
                                <span id="last-update">Last Update: Never</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Video Feed -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Live Video Feed</h5>
                    </div>
                    <div class="card-body">
                        <div class="video-container">
                            <img id="video-feed" src="placeholder.jpg" class="img-fluid" alt="Video Feed">
                            <canvas id="motion-canvas" style="display: none;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Current Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Current Detection Status</h5>
                    </div>
                    <div class="card-body">
                        <div id="current-status">No active detection</div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div id="uptime-info">Uptime: 0m 0s</div>
                            </div>
                            <div class="col-md-3">
                                <div id="frames-info">Frames: 0</div>
                            </div>
                            <div class="col-md-3">
                                <div id="motion-info">Motion: 0%</div>
                            </div>
                            <div class="col-md-3">
                                <div id="ai-status">AI: Analyzing...</div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Location Tracking (ESP)</h6>
                                <div id="location-info">
                                    <div id="gps-coordinates">GPS: --</div>
                                    <div id="zone-info">Zone: Unknown</div>
                                    <div id="risk-level">Risk Level: Unknown</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Emergency Response</h6>
                                <div id="emergency-info">
                                    <div id="nearest-lifeguard">Lifeguard: --</div>
                                    <div id="response-time">Response Time: --</div>
                                    <div id="beeping-status">Alert Status: Inactive</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Alerts</h5>
                    </div>
                    <div class="card-body">
                        <div id="alerts-container">
                            <p class="text-muted">No recent alerts</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        let detectionInterval;
        let isRunning = false;

        // DOM elements
        const statusIndicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');
        const startBtn = document.getElementById('start-btn');
        const stopBtn = document.getElementById('stop-btn');
        const lastUpdate = document.getElementById('last-update');
        const currentStatus = document.getElementById('current-status');
        const alertsContainer = document.getElementById('alerts-container');

        // Start detection
        startBtn.addEventListener('click', function() {
            startBtn.disabled = true;
            startBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Starting...';

            fetch('index.php?action=start_detection')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    startBtn.disabled = false;
                    startBtn.innerHTML = 'Start Detection';

                    if (data.success) {
                        isRunning = true;
                        updateStatus('active', 'System Active');
                        startBtn.disabled = true;
                        stopBtn.disabled = false;
                        startStatusUpdates();

                        // Show success message
                        showAlert('Detection started successfully!', 'success');
                    } else {
                        alert('Error starting detection: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    startBtn.disabled = false;
                    startBtn.innerHTML = 'Start Detection';
                    alert('Error starting detection: ' + error.message);
                });
        });

        // Stop detection
        stopBtn.addEventListener('click', function() {
            stopBtn.disabled = true;
            stopBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Stopping...';

            fetch('index.php?action=stop_detection')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    stopBtn.disabled = false;
                    stopBtn.innerHTML = 'Stop Detection';

                    if (data.success) {
                        isRunning = false;
                        updateStatus('inactive', 'System Inactive');
                        startBtn.disabled = false;
                        stopBtn.disabled = true;
                        stopStatusUpdates();

                        // Show success message
                        showAlert('Detection stopped successfully!', 'info');
                    } else {
                        alert('Error stopping detection: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    stopBtn.disabled = false;
                    stopBtn.innerHTML = 'Stop Detection';
                    alert('Error stopping detection: ' + error.message);
                });
        });

        // Update status display
        function updateStatus(status, text) {
            statusText.textContent = text;
            statusIndicator.className = 'status-indicator status-' + status;
        }

        // Show alert messages
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Start periodic status updates
        function startStatusUpdates() {
            detectionInterval = setInterval(() => {
                fetch('index.php?action=get_status')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        lastUpdate.textContent = 'Last Update: ' + new Date().toLocaleTimeString();

                        if (data.status) {
                            currentStatus.textContent = data.status;

                            if (data.status.includes('ALERT')) {
                                updateStatus('alert', 'ALERT - Drowning Detected!');
                                showAlert('🚨 ALERT: Potential drowning detected!', 'danger');
                                playAlertSound();
                            } else if (data.active) {
                                updateStatus('active', 'System Active');
                            }
                        }

                        // Update additional info
                        if (data.uptime) {
                            document.getElementById('uptime-info').textContent = `Uptime: ${Math.floor(data.uptime / 60)}m ${data.uptime % 60}s`;
                        }

                        if (data.frames_processed) {
                            document.getElementById('frames-info').textContent = `Frames: ${data.frames_processed}`;
                        }

                        if (data.motion_percentage !== undefined) {
                            document.getElementById('motion-info').textContent = `Motion: ${data.motion_percentage}%`;
                        }

                        // Update AI status
                        if (data.ai_behavior) {
                            document.getElementById('ai-status').textContent = `AI: ${data.ai_behavior} (${data.ai_confidence || 0}%)`;
                        }

                        // Update location information
                        if (data.location) {
                            document.getElementById('gps-coordinates').textContent =
                                `GPS: ${data.location.lat.toFixed(6)}, ${data.location.lng.toFixed(6)}`;
                        }

                        if (data.zone) {
                            document.getElementById('zone-info').textContent = `Zone: ${data.zone.name}`;
                            document.getElementById('risk-level').textContent = `Risk Level: ${data.zone.risk_level}`;
                        }

                        // Update emergency information
                        if (data.emergency_info) {
                            if (data.emergency_info.nearest_lifeguard) {
                                const lg = data.emergency_info.nearest_lifeguard;
                                document.getElementById('nearest-lifeguard').textContent =
                                    `Lifeguard: ${lg.name} (${lg.distance}m)`;
                            }

                            document.getElementById('response-time').textContent =
                                `Response Time: ${data.emergency_info.response_time}s`;
                        }

                        // Update beeping status
                        if (data.beeping_active !== undefined) {
                            const beepingText = data.beeping_active ? '🔊 ALERT ACTIVE' : '🔇 Alert Inactive';
                            document.getElementById('beeping-status').textContent = `Alert Status: ${beepingText}`;
                        }
                    })
                    .catch(error => {
                        console.error('Error updating status:', error);
                        currentStatus.textContent = 'Connection error - check system';
                        updateStatus('inactive', 'Connection Error');
                    });

                // Also update alerts periodically
                fetch('index.php?action=get_alerts')
                    .then(response => response.json())
                    .then(data => {
                        if (data.alerts && data.alerts.length > 0) {
                            updateAlerts(data.alerts);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating alerts:', error);
                    });
            }, 2000); // Update every 2 seconds
        }

        // Stop status updates
        function stopStatusUpdates() {
            if (detectionInterval) {
                clearInterval(detectionInterval);
            }
        }

        // Update alerts display
        function updateAlerts(alerts) {
            if (alerts.length === 0) {
                alertsContainer.innerHTML = '<p class="text-muted">No recent alerts</p>';
                return;
            }

            let html = '';
            alerts.forEach(alert => {
                html += `
                    <div class="alert-item">
                        <strong>${alert.timestamp}</strong><br>
                        ${alert.alert_type}: ${alert.details}
                    </div>
                `;
            });
            alertsContainer.innerHTML = html;
        }

        // Initial alerts load
        fetch('index.php?action=get_alerts')
            .then(response => response.json())
            .then(data => {
                if (data.alerts) {
                    updateAlerts(data.alerts);
                }
            })
            .catch(error => {
                console.error('Error loading alerts:', error);
            });

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch('login.php?logout=1')
                    .then(() => {
                        window.location.href = 'login.php';
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        window.location.href = 'login.php';
                    });
            }
        }
    </script>
</body>
</html>

<?php
// PHP functions for AJAX handling

function startDetection() {
    global $motion_detector, $alert_system;

    try {
        // Initialize detection components
        if (!isset($motion_detector)) {
            require_once 'includes/motion_detector.php';
            $motion_detector = new MotionDetector();
        }

        if (!isset($alert_system)) {
            require_once 'includes/alert_system.php';
            $alert_system = new AlertSystem();
        }

        // Reset detector state
        $motion_detector->reset();

        // Set session variables
        $_SESSION['detection_active'] = true;
        $_SESSION['detection_start_time'] = time();
        $_SESSION['last_motion_time'] = time();
        $_SESSION['frame_count'] = 0;

        logMessage("Detection started by user");

        return [
            'success' => true,
            'message' => 'Detection started successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        logMessage("Error starting detection: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Failed to start detection: ' . $e->getMessage()
        ];
    }
}

function stopDetection() {
    try {
        $_SESSION['detection_active'] = false;

        logMessage("Detection stopped by user");

        return [
            'success' => true,
            'message' => 'Detection stopped successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to stop detection: ' . $e->getMessage()
        ];
    }
}

function getDetectionStatus() {
    global $ai_detection, $esp_location;

    if (!isset($_SESSION['detection_active']) || !$_SESSION['detection_active']) {
        return [
            'status' => 'System inactive',
            'active' => false,
            'uptime' => 0
        ];
    }

    $uptime = time() - ($_SESSION['detection_start_time'] ?? time());
    $frame_count = $_SESSION['frame_count'] ?? 0;
    $last_motion = $_SESSION['last_motion_time'] ?? time();
    $time_without_motion = time() - $last_motion;

    // Get AI detection status
    $ai_status = $ai_detection->getStatus();

    // Get current location
    $current_location = $esp_location->getCurrentLocation();
    $current_zone = $esp_location->getZoneForLocation($current_location['lat'], $current_location['lng']);

    // Get emergency response information
    $emergency_info = $esp_location->getEmergencyResponse($current_location['lat'], $current_location['lng']);

    // Generate enhanced status based on AI analysis
    $status = 'Monitoring - System operational';

    if ($ai_status['beeping_active']) {
        $status = '🚨 ALERT - Drowning detected! Emergency response activated!';
    } elseif ($ai_status['detection_history_count'] > 0) {
        // Get latest AI analysis from session or generate new one
        $latest_behavior = $_SESSION['latest_ai_behavior'] ?? 'monitoring';
        $latest_confidence = $_SESSION['latest_ai_confidence'] ?? 0;

        switch ($latest_behavior) {
            case 'drowning':
                $status = '🚨 CRITICAL - Drowning detected!';
                break;
            case 'potential_drowning':
                $status = '⚠️ WARNING - Potential drowning detected';
                break;
            case 'swimming':
                $status = '✅ Normal - Active swimming detected';
                break;
            case 'monitoring':
                $status = '👁️ Monitoring - System operational';
                break;
            default:
                $status = 'Monitoring - System operational';
        }
    }

    return [
        'status' => $status,
        'active' => true,
        'uptime' => $uptime,
        'frames_processed' => $frame_count,
        'time_without_motion' => $time_without_motion,
        'motion_percentage' => $_SESSION['motion_percentage'] ?? 0,
        'ai_behavior' => $_SESSION['latest_ai_behavior'] ?? 'unknown',
        'ai_confidence' => $_SESSION['latest_ai_confidence'] ?? 0,
        'beeping_active' => $ai_status['beeping_active'],
        'location' => $current_location,
        'zone' => $current_zone,
        'emergency_info' => $emergency_info
    ];
}

function getRecentAlerts() {
    try {
        $db = new Database();
        $alerts = $db->getRecentAlerts(5);

        // Format alerts for JSON response
        $formatted_alerts = [];
        foreach ($alerts as $alert) {
            $formatted_alerts[] = [
                'id' => $alert['id'],
                'timestamp' => $alert['timestamp'],
                'alert_type' => $alert['alert_type'],
                'details' => $alert['details'],
                'severity' => $alert['severity']
            ];
        }

        return [
            'success' => true,
            'alerts' => $formatted_alerts
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'alerts' => [],
            'error' => $e->getMessage()
        ];
    }
}

function processDetectionFrame() {
    global $motion_detector, $alert_system;

    if (!isset($_SESSION['detection_active']) || !$_SESSION['detection_active']) {
        return ['success' => false, 'error' => 'Detection not active'];
    }

    try {
        // Simulate frame capture (in real implementation, this would capture from camera)
        $frame_data = simulateFrameCapture();

        // Process motion detection
        $motion_result = $motion_detector->processFrame($frame_data);

        // Update session data
        $_SESSION['frame_count'] = ($_SESSION['frame_count'] ?? 0) + 1;
        $_SESSION['motion_percentage'] = $motion_result['motion_percentage'];

        if ($motion_result['motion_detected']) {
            $_SESSION['last_motion_time'] = time();
        }

        // Analyze motion patterns
        $pattern_analysis = $motion_detector->analyzeMotionPattern($motion_result);

        // Check for alerts
        $time_without_motion = time() - ($_SESSION['last_motion_time'] ?? time());
        if ($pattern_analysis['no_motion_duration'] > ALERT_TIMEOUT) {
            $alert_details = sprintf(
                "No motion detected for %.1f seconds. Pattern: %s",
                $pattern_analysis['no_motion_duration'],
                $pattern_analysis['pattern_type']
            );

            $alert_system->triggerDrowningAlert($alert_details);
        }

        // Log detection data
        $db = new Database();
        $db->logDetection(
            $motion_result['motion_detected'],
            false, // human_detected
            0.0,   // confidence
            json_encode($motion_result),
            'webcam_simulated'
        );

        return [
            'success' => true,
            'motion_detected' => $motion_result['motion_detected'],
            'motion_percentage' => $motion_result['motion_percentage'],
            'pattern_type' => $pattern_analysis['pattern_type'],
            'time_without_motion' => $time_without_motion,
            'alert_triggered' => ($pattern_analysis['no_motion_duration'] > ALERT_TIMEOUT)
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Simulate frame capture (replace with actual camera capture)
 */
function simulateFrameCapture() {
    // Create a simple test image
    $width = MAX_IMAGE_WIDTH;
    $height = MAX_IMAGE_HEIGHT;

    $image = imagecreatetruecolor($width, $height);

    // Fill with blue (water-like color)
    $water_color = imagecolorallocate($image, 100, 149, 237);
    imagefill($image, 0, 0, $water_color);

    // Add some random noise to simulate motion
    if (rand(1, 10) > 7) { // 30% chance of motion
        $motion_color = imagecolorallocate($image, 255, 255, 255);
        $x = rand(50, $width - 50);
        $y = rand(50, $height - 50);
        imagefilledellipse($image, $x, $y, 20, 20, $motion_color);
    }

    // Convert to string
    ob_start();
    imagejpeg($image, null, JPEG_QUALITY);
    $image_data = ob_get_clean();

    imagedestroy($image);

    return $image_data;
}
?>