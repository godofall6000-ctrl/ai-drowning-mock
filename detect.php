<?php
/**
 * Detection Engine for AI Drowning Detection System
 * Processes video frames and performs analysis
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/motion_detector.php';
require_once 'includes/alert_system.php';
require_once 'includes/ai_detection.php';
require_once 'includes/esp_location.php';

// Set headers for streaming response
header('Content-Type: text/plain');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Initialize components
$db = new Database();
$motion_detector = new MotionDetector();
$alert_system = new AlertSystem();

// Detection state
$detection_active = false;
$last_motion_time = time();
$frame_count = 0;

// Main detection loop
while (true) {
    // Check if detection should be active
    if (isset($_GET['command'])) {
        $command = $_GET['command'];

        switch ($command) {
            case 'start':
                $detection_active = true;
                $motion_detector->reset();
                $last_motion_time = time();
                $frame_count = 0;
                echo "DETECTION_STARTED\n";
                logMessage("Detection started via web interface");
                break;

            case 'stop':
                $detection_active = false;
                echo "DETECTION_STOPPED\n";
                logMessage("Detection stopped via web interface");
                break;

            case 'status':
                $status = $detection_active ? 'ACTIVE' : 'INACTIVE';
                echo "STATUS: {$status}\n";
                break;

            default:
                echo "UNKNOWN_COMMAND\n";
        }
        flush();
        continue;
    }

    if (!$detection_active) {
        sleep(1);
        continue;
    }

    $frame_count++;

    // Simulate frame processing (in real implementation, this would capture from camera)
    $frame_data = simulateFrameCapture();

    // Process motion detection
    $motion_result = $motion_detector->processFrame($frame_data);

    // Analyze motion patterns
    $pattern_analysis = $motion_detector->analyzeMotionPattern($motion_result);

    // Get current location from ESP system
    $current_location = $GLOBALS['esp_location']->getCurrentLocation();
    $current_zone = $GLOBALS['esp_location']->getZoneForLocation($current_location['lat'], $current_location['lng']);

    // Advanced AI behavior analysis
    $ai_analysis = $GLOBALS['ai_detection']->analyzeBehavior($motion_result, $pattern_analysis);

    // Get emergency response information
    $emergency_info = $GLOBALS['esp_location']->getEmergencyResponse($current_location['lat'], $current_location['lng']);

    // Determine detection status
    $current_time = time();
    $time_without_motion = $current_time - $last_motion_time;

    if ($motion_result['motion_detected']) {
        $last_motion_time = $current_time;
    }

    // Generate enhanced status message
    $status_message = generateEnhancedStatusMessage($motion_result, $pattern_analysis, $ai_analysis, $current_location, $current_zone, $time_without_motion);

    // Enhanced alert logic with AI and location
    $alert_details = generateEnhancedAlertMessage(
        $ai_analysis,
        $pattern_analysis,
        $current_location,
        $current_zone,
        $emergency_info,
        $time_without_motion
    );

    // Trigger alerts based on AI analysis
    if ($ai_analysis['behavior'] === 'drowning') {
        $alert_system->triggerDrowningAlert($alert_details, 'high');
        $status_message .= " [🚨 DROWNING ALERT]";
    } elseif ($ai_analysis['behavior'] === 'potential_drowning') {
        $alert_system->triggerMotionAlert($alert_details, 'medium');
        $status_message .= " [⚠️ POTENTIAL DROWNING]";
    } elseif ($ai_analysis['beeping_active']) {
        $status_message .= " [🔊 ALERT ACTIVE]";
    }

    // Log comprehensive detection data
    $db->logDetection(
        $motion_result['motion_detected'],
        true, // AI detected human
        $ai_analysis['confidence'],
        json_encode([
            'motion_data' => $motion_result,
            'pattern_analysis' => $pattern_analysis,
            'ai_analysis' => $ai_analysis,
            'location' => $current_location,
            'zone' => $current_zone,
            'emergency_info' => $emergency_info
        ]),
        'ai_esp_detection_system'
    );

    // Store AI analysis in session for web interface
    $_SESSION['latest_ai_behavior'] = $ai_analysis['behavior'];
    $_SESSION['latest_ai_confidence'] = $ai_analysis['confidence'];
    $_SESSION['beeping_active'] = $ai_analysis['beeping_active'];

    // Log location data
    $GLOBALS['esp_location']->logLocationData($current_location, $ai_analysis['behavior']);

    // Output status
    echo $status_message . "\n";

    // Flush output buffer
    flush();

    // Control processing rate
    usleep(100000); // 100ms delay (10 FPS)
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

/**
 * Generate enhanced status message with AI and location data
 */
function generateEnhancedStatusMessage($motion_result, $pattern_analysis, $ai_analysis, $location, $zone, $time_without_motion) {
    $message = sprintf(
        "Frame: %d | Motion: %.1f%% | AI: %s (%.1f%%) | Zone: %s | GPS: %.4f,%.4f",
        $GLOBALS['frame_count'],
        $motion_result['motion_percentage'],
        ucfirst($ai_analysis['behavior']),
        $ai_analysis['confidence'],
        $zone['name'],
        $location['lat'],
        $location['lng']
    );

    if ($motion_result['motion_detected']) {
        $message .= " | ACTIVITY_DETECTED";
    }

    if ($ai_analysis['beeping_active']) {
        $message .= " | ALERT_ACTIVE";
    }

    return $message;
}

/**
 * Generate enhanced alert message with location and emergency info
 */
function generateEnhancedAlertMessage($ai_analysis, $pattern_analysis, $location, $zone, $emergency_info, $time_without_motion) {
    $alert_parts = [];

    // AI behavior analysis
    $alert_parts[] = "AI Detection: " . ucfirst($ai_analysis['behavior']);
    $alert_parts[] = sprintf("Confidence: %.1f%%", $ai_analysis['confidence']);

    // Location information
    $alert_parts[] = sprintf("Location: %.6f, %.6f", $location['lat'], $location['lng']);
    $alert_parts[] = "Zone: " . $zone['name'] . " (" . $zone['depth'] . " - " . $zone['risk_level'] . " risk)";

    // Motion analysis
    $alert_parts[] = sprintf("Motion Pattern: %s", $pattern_analysis['pattern_type']);
    $alert_parts[] = sprintf("Time without motion: %.1f seconds", $time_without_motion);

    // Emergency response info
    if (isset($emergency_info['nearest_lifeguard'])) {
        $lg = $emergency_info['nearest_lifeguard'];
        $alert_parts[] = sprintf("Nearest Lifeguard: %s (%.1fm, ETA: %.1fs)", $lg['name'], $lg['distance'], $lg['eta']);
    }

    $alert_parts[] = sprintf("Emergency Response Time: %d seconds", $emergency_info['response_time']);

    // Equipment needed
    if (isset($emergency_info['equipment_needed'])) {
        $alert_parts[] = "Required Equipment: " . implode(", ", $emergency_info['equipment_needed']);
    }

    return implode(" | ", $alert_parts);
}
?>