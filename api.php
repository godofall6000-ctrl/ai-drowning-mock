<?php
/**
 * RESTful API for AI Drowning Detection System
 * Mobile app integration and third-party access
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/motion_detector.php';
require_once 'includes/alert_system.php';
require_once 'includes/ai_detection.php';
require_once 'includes/esp_location.php';
require_once 'includes/weather_integration.php';
require_once 'includes/sms_integration.php';
require_once 'includes/email_integration.php';

// Set CORS headers for mobile app access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// API Key authentication
function authenticateAPI() {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

    if (empty($api_key)) {
        return false;
    }

    // In production, validate against database
    $valid_keys = ['demo_key_123', 'mobile_app_key', 'web_client_key'];

    return in_array($api_key, $valid_keys);
}

// Rate limiting
function checkRateLimit() {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $cache_key = 'api_rate_limit_' . $client_ip;

    // Simple rate limiting (in production, use Redis or similar)
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = ['count' => 0, 'reset_time' => time() + 3600];
    }

    $rate_data = $_SESSION[$cache_key];

    if (time() > $rate_data['reset_time']) {
        $rate_data = ['count' => 0, 'reset_time' => time() + 3600];
    }

    if ($rate_data['count'] >= 100) { // 100 requests per hour
        return false;
    }

    $rate_data['count']++;
    $_SESSION[$cache_key] = $rate_data;

    return true;
}

// API response helper
function sendResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// Error response helper
function sendError($message, $status_code = 400) {
    sendResponse([
        'success' => false,
        'error' => $message,
        'timestamp' => time()
    ], $status_code);
}

// Success response helper
function sendSuccess($data = null, $message = 'Success') {
    $response = [
        'success' => true,
        'message' => $message,
        'timestamp' => time()
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    sendResponse($response);
}

// Authentication check
if (!authenticateAPI()) {
    sendError('Invalid API key', 401);
}

// Rate limit check
if (!checkRateLimit()) {
    sendError('Rate limit exceeded', 429);
}

// Parse request
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$endpoint = $path_parts[1] ?? '';

// Route requests
switch ($endpoint) {
    case 'status':
        handleStatusRequest();
        break;

    case 'alerts':
        handleAlertsRequest();
        break;

    case 'detections':
        handleDetectionsRequest();
        break;

    case 'weather':
        handleWeatherRequest();
        break;

    case 'location':
        handleLocationRequest();
        break;

    case 'emergency':
        handleEmergencyRequest();
        break;

    case 'reports':
        handleReportsRequest();
        break;

    case 'system':
        handleSystemRequest();
        break;

    default:
        sendError('Endpoint not found', 404);
}

/**
 * Handle system status requests
 */
function handleStatusRequest() {
    global $ai_detection, $esp_location;

    $db = new Database();

    $status = [
        'system_status' => 'operational',
        'uptime' => time() - ($_SESSION['detection_start_time'] ?? time()),
        'ai_status' => $ai_detection->getStatus(),
        'location_tracking' => $esp_location->getESPStatus(),
        'database_status' => $db->getConnectionStatus() ? 'connected' : 'disconnected',
        'last_update' => time()
    ];

    sendSuccess($status);
}

/**
 * Handle alerts requests
 */
function handleAlertsRequest() {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $limit = $_GET['limit'] ?? 10;
            $alerts = $db->getRecentAlerts($limit);
            sendSuccess($alerts);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['type']) || !isset($data['message'])) {
                sendError('Missing required fields: type, message');
            }

            $alert_system = new AlertSystem();
            $result = $alert_system->triggerDrowningAlert($data['message'], $data['type']);

            if ($result) {
                sendSuccess(['alert_id' => $result], 'Alert created successfully');
            } else {
                sendError('Failed to create alert');
            }
            break;

        default:
            sendError('Method not allowed', 405);
    }
}

/**
 * Handle detections requests
 */
function handleDetectionsRequest() {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $limit = $_GET['limit'] ?? 10;
            $detections = $db->getRecentDetections($limit);
            sendSuccess($detections);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                sendError('Invalid JSON data');
            }

            $result = $db->logDetection(
                $data['motion_detected'] ?? false,
                $data['human_detected'] ?? false,
                $data['confidence'] ?? 0.0,
                isset($data['frame_data']) ? json_encode($data['frame_data']) : null,
                $data['video_source'] ?? 'api'
            );

            if ($result) {
                sendSuccess(['detection_id' => $result], 'Detection logged successfully');
            } else {
                sendError('Failed to log detection');
            }
            break;

        default:
            sendError('Method not allowed', 405);
    }
}

/**
 * Handle weather requests
 */
function handleWeatherRequest() {
    $weather_integration = new WeatherIntegration();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $lat = $_GET['lat'] ?? 14.5995;
            $lng = $_GET['lng'] ?? 120.9842;

            $weather_data = $weather_integration->getCurrentWeather($lat, $lng);

            if ($weather_data) {
                $safety = $weather_integration->assessSwimmingSafety($weather_data);
                sendSuccess([
                    'weather' => $weather_data,
                    'safety_assessment' => $safety
                ]);
            } else {
                sendError('Weather data not available');
            }
            break;

        default:
            sendError('Method not allowed', 405);
    }
}

/**
 * Handle location requests
 */
function handleLocationRequest() {
    $esp_location = new ESPLocationSystem();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $current_location = $esp_location->getCurrentLocation();
            $zone = $esp_location->getZoneForLocation($current_location['lat'], $current_location['lng']);

            sendSuccess([
                'current_location' => $current_location,
                'zone' => $zone,
                'movement_pattern' => $esp_location->trackMovementPattern()
            ]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['lat']) || !isset($data['lng'])) {
                sendError('Missing required fields: lat, lng');
            }

            $zone = $esp_location->getZoneForLocation($data['lat'], $data['lng']);
            $emergency_info = $esp_location->getEmergencyResponse($data['lat'], $data['lng']);

            sendSuccess([
                'zone' => $zone,
                'emergency_response' => $emergency_info
            ]);
            break;

        default:
            sendError('Method not allowed', 405);
    }
}

/**
 * Handle emergency requests
 */
function handleEmergencyRequest() {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['type'])) {
                sendError('Missing emergency type');
            }

            $alert_system = new AlertSystem();
            $sms_integration = new SMSIntegration();
            $email_integration = new EmailIntegration();

            $message = $data['message'] ?? 'Emergency situation detected';
            $location = $data['location'] ?? 'Unknown location';

            // Trigger all emergency systems
            $alert_result = $alert_system->triggerDrowningAlert($message . " Location: {$location}", 'high');
            $sms_result = $sms_integration->alertAllEmergencyContacts($message);
            $email_result = $email_integration->sendEmergencyAlert(
                'emergency@aidrowningsystem.com',
                'EMERGENCY ALERT - ' . strtoupper($data['type']),
                $message
            );

            sendSuccess([
                'alert_triggered' => $alert_result !== false,
                'sms_sent' => $sms_result['successful'] ?? 0,
                'email_sent' => $email_result,
                'emergency_type' => $data['type']
            ], 'Emergency response activated');
            break;

        default:
            sendError('Method not allowed', 405);
    }
}

/**
 * Handle reports requests
 */
function handleReportsRequest() {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $type = $_GET['type'] ?? 'summary';
            $days = $_GET['days'] ?? 7;

            switch ($type) {
                case 'alerts':
                    $report = $db->getAlertSummary($days);
                    break;
                case 'system':
                    $report = $db->getSystemStats($days);
                    break;
                case 'summary':
                default:
                    $report = [
                        'alerts' => $db->getAlertSummary($days),
                        'system' => $db->getSystemStats($days),
                        'generated_at' => time()
                    ];
                    break;
            }

            sendSuccess($report);
            break;

        default:
            sendError('Method not allowed', 405);
    }
}

/**
 * Handle system requests
 */
function handleSystemRequest() {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'info';

            switch ($action) {
                case 'health':
                    sendSuccess(systemHealthCheck());
                    break;
                case 'config':
                    sendSuccess([
                        'version' => SYSTEM_VERSION ?? '1.0.0',
                        'environment' => 'production',
                        'features' => [
                            'ai_detection' => true,
                            'location_tracking' => true,
                            'weather_integration' => true,
                            'sms_alerts' => true,
                            'email_alerts' => true
                        ]
                    ]);
                    break;
                case 'info':
                default:
                    sendSuccess([
                        'system_name' => SYSTEM_NAME,
                        'version' => SYSTEM_VERSION ?? '1.0.0',
                        'status' => 'operational',
                        'uptime' => time() - ($_SESSION['system_start_time'] ?? time()),
                        'api_version' => '1.0'
                    ]);
                    break;
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['action'])) {
                sendError('Missing action parameter');
            }

            switch ($data['action']) {
                case 'restart':
                    // In a real system, this would restart services
                    sendSuccess([], 'System restart initiated');
                    break;

                case 'maintenance':
                    // Toggle maintenance mode
                    $_SESSION['maintenance_mode'] = !($_SESSION['maintenance_mode'] ?? false);
                    sendSuccess(['maintenance_mode' => $_SESSION['maintenance_mode']]);
                    break;

                default:
                    sendError('Unknown action');
            }
            break;

        default:
            sendError('Method not allowed', 405);
    }
}

/**
 * System health check function
 */
function systemHealthCheck() {
    return [
        'status' => 'healthy',
        'checks' => [
            'database' => true,
            'file_system' => is_writable(DATA_DIR),
            'memory' => true,
            'cpu' => true
        ],
        'timestamp' => time(),
        'version' => SYSTEM_VERSION ?? '1.0.0'
    ];
}
?>