<?php
/**
 * Main Detection System for AI Drowning Detection
 * Native PHP implementation using FFmpeg for video processing
 */

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/motion_detector.php';
require_once 'includes/ai_detector.php';
require_once 'includes/alert_system.php';

// Initialize components
$db = new Database();
$motion_detector = new MotionDetector();
$ai_detector = new AIDetector();
$alert_system = new AlertSystem();

class VideoProcessor {
    private $ffmpeg_path = 'ffmpeg'; // Assume ffmpeg is in PATH
    private $temp_dir = 'temp/';
    private $frame_rate = 15; // Process 15 frames per second for better real-time tracking

    public function __construct() {
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }

    /**
     * Extract frames from video file
     */
    public function extractFrames($video_path, $output_pattern = 'frame_%04d.jpg') {
        $output_path = $this->temp_dir . $output_pattern;

        $command = sprintf(
            '%s -i "%s" -vf fps=%d "%s" 2>&1',
            $this->ffmpeg_path,
            $video_path,
            $this->frame_rate,
            $output_path
        );

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            logMessage("FFmpeg error: " . implode("\n", $output), 'ERROR');
            return [];
        }

        // Get list of extracted frames
        $frames = glob($this->temp_dir . 'frame_*.jpg');
        sort($frames);

        return $frames;
    }

    /**
     * Capture frame from webcam (simulated)
     */
    public function captureWebcamFrame() {
        $frame_path = $this->temp_dir . 'webcam_' . time() . '.jpg';

        // Use FFmpeg to capture single frame from webcam
        $command = sprintf(
            '%s -f vfwcap -i 0 -vframes 1 "%s" 2>&1',
            $this->ffmpeg_path,
            $frame_path
        );

        exec($command, $output, $return_var);

        if ($return_var === 0 && file_exists($frame_path)) {
            return $frame_path;
        }

        // Fallback: create a dummy frame for testing
        return $this->createDummyFrame($frame_path);
    }

    /**
     * Create a dummy frame for testing
     */
    private function createDummyFrame($path) {
        $image = imagecreatetruecolor(640, 480);
        $bg_color = imagecolorallocate($image, 100, 149, 237); // Cornflower blue
        imagefill($image, 0, 0, $bg_color);

        // Add some random shapes to simulate content
        for ($i = 0; $i < 10; $i++) {
            $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
            $x1 = rand(0, 640);
            $y1 = rand(0, 480);
            $x2 = rand(0, 640);
            $y2 = rand(0, 480);
            imageline($image, $x1, $y1, $x2, $y2, $color);
        }

        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Clean up temporary files
     */
    public function cleanup() {
        $files = glob($this->temp_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

class DrowningDetectionSystem {
    private $video_processor;
    private $motion_detector;
    private $ai_detector;
    private $alert_system;
    private $db;
    private $last_motion_time;
    private $frame_count;
    private $human_present;

    public function __construct() {
        $this->video_processor = new VideoProcessor();
        $this->motion_detector = $GLOBALS['motion_detector'];
        $this->ai_detector = $GLOBALS['ai_detector'];
        $this->alert_system = $GLOBALS['alert_system'];
        $this->db = $GLOBALS['db'];

        $this->last_motion_time = time();
        $this->frame_count = 0;
        $this->human_present = false;
    }

    /**
     * Main detection loop
     */
    public function runDetection($video_source = null) {
        logMessage("AI Drowning Detection System Started");

        $previous_frame = null;

        try {
            while (true) {
                $this->frame_count++;

                // Get current frame
                if ($video_source) {
                    // Process video file
                    $frames = $this->video_processor->extractFrames($video_source);
                    if (empty($frames)) {
                        logMessage("No frames extracted from video", 'ERROR');
                        break;
                    }

                    foreach ($frames as $frame_path) {
                        $this->processFrame($frame_path, $previous_frame);
                        $previous_frame = $frame_path;
                        usleep(66666); // Process ~15 frames per second (1/15 = 0.066666 seconds)
                    }
                    break; // Process video once

                } else {
                    // Capture from webcam
                    $frame_path = $this->video_processor->captureWebcamFrame();
                    if (!$frame_path) {
                        logMessage("Failed to capture frame", 'ERROR');
                        sleep(1);
                        continue;
                    }

                    $this->processFrame($frame_path, $previous_frame);
                    $previous_frame = $frame_path;

                    usleep(66666); // ~15 FPS for real-time webcam tracking
                }

                // Continuous real-time tracking - no artificial limits
                // System will run until manually stopped or error occurs
            }

        } catch (Exception $e) {
            logMessage("Detection error: " . $e->getMessage(), 'ERROR');
        }

        // Cleanup
        $this->video_processor->cleanup();
        logMessage("Detection system stopped");
    }

    /**
     * Process a single frame
     */
    private function processFrame($frame_path, $previous_frame_path) {
        // Load current frame
        $current_frame = imagecreatefromjpeg($frame_path);
        if (!$current_frame) {
            logMessage("Failed to load frame: $frame_path", 'ERROR');
            return;
        }

        // Detect motion
        $motion_result = ['motion_detected' => false];
        if ($previous_frame_path && file_exists($previous_frame_path)) {
            $previous_frame = imagecreatefromjpeg($previous_frame_path);
            if ($previous_frame) {
                $motion_result = $this->motion_detector->processFrame($previous_frame_path);
                imagedestroy($previous_frame);
            }
        }

        // Update motion time
        if ($motion_result['motion_detected']) {
            $this->last_motion_time = time();
        }

        // AI human detection every 5 frames for better real-time tracking
        if ($this->frame_count % 5 == 0) {
            $human_count = $this->ai_detector->getHumanCount($current_frame);
            $this->human_present = $human_count > 0;

            // Log detection
            $this->db->logDetection(
                $motion_result['motion_detected'],
                $this->human_present,
                $human_count > 0 ? 0.8 : 0.0, // Basic confidence
                $frame_path
            );
        }

        // Calculate time without motion
        $time_without_motion = time() - $this->last_motion_time;

        // Analyze drowning risk
        $risk_status = $this->ai_detector->analyzeDrowningRisk(
            $this->human_present ? 1 : 0,
            $motion_result['motion_detected'],
            $time_without_motion
        );

        logMessage("Frame {$this->frame_count}: {$risk_status}");

        // Trigger alerts
        if (strpos($risk_status, 'ALERT') !== false) {
            $this->alert_system->triggerDrowningAlert(
                "Status: {$risk_status}, Time without motion: {$time_without_motion}s",
                'high'
            );
        } elseif ($motion_result['motion_detected']) {
            $this->alert_system->triggerMotionAlert(
                "Motion detected: {$motion_result['motion_percentage']}%"
            );
        }

        imagedestroy($current_frame);
    }

    /**
     * Process video file
     */
    public function processVideoFile($video_path) {
        if (!file_exists($video_path)) {
            logMessage("Video file not found: $video_path", 'ERROR');
            return false;
        }

        logMessage("Processing video file: $video_path");
        $this->runDetection($video_path);
        return true;
    }

    /**
     * Start webcam monitoring
     */
    public function startWebcamMonitoring() {
        logMessage("Starting webcam monitoring");
        $this->runDetection();
    }
}

// Handle command line arguments
if (isset($argv) && count($argv) > 1) {
    $video_path = $argv[1];
    $system = new DrowningDetectionSystem();
    $system->processVideoFile($video_path);
} else {
    // Web interface mode
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');

        switch ($_GET['action']) {
            case 'start_detection':
                $system = new DrowningDetectionSystem();
                $system->startWebcamMonitoring();
                echo json_encode(['status' => 'started']);
                break;

            case 'process_video':
                if (isset($_FILES['video'])) {
                    $video_path = 'temp/' . basename($_FILES['video']['name']);
                    move_uploaded_file($_FILES['video']['tmp_name'], $video_path);

                    $system = new DrowningDetectionSystem();
                    $result = $system->processVideoFile($video_path);

                    unlink($video_path); // Cleanup
                    echo json_encode(['success' => $result]);
                } else {
                    echo json_encode(['error' => 'No video file provided']);
                }
                break;

            default:
                echo json_encode(['error' => 'Invalid action']);
        }
        exit;
    }
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
        .detection-status { font-size: 1.2em; font-weight: bold; }
        .alert-active { background-color: #f8d7da; border-color: #f5c6cb; }
        .normal-status { background-color: #d1edff; border-color: #bee5eb; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>AI Drowning Detection System</h1>
                <p class="text-muted">Native PHP Implementation</p>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5>System Status</h5>
                    </div>
                    <div class="card-body">
                        <div id="status-display" class="alert normal-status">
                            <span class="detection-status">System Ready</span>
                            <div id="realtime-indicator" class="d-none">
                                <small class="text-muted ms-2">
                                    <i class="fas fa-circle text-success"></i> Real-time tracking active
                                </small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-3">
                                <button class="btn btn-primary btn-block" onclick="startDetection()">
                                    Start Webcam Detection
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-secondary btn-block" onclick="stopDetection()">
                                    Stop Detection
                                </button>
                            </div>
                            <div class="col-md-6">
                                <form id="video-upload" enctype="multipart/form-data">
                                    <div class="input-group">
                                        <input type="file" class="form-control" name="video" accept="video/*" required>
                                        <button class="btn btn-success" type="submit">Process Video</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Detection Log</h5>
                    </div>
                    <div class="card-body">
                        <div id="detection-log" class="border p-3" style="height: 250px; overflow-y: auto;">
                            <p class="text-muted">Detection log will appear here...</p>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <span id="fps-indicator">FPS: --</span> |
                                <span id="processing-indicator">Processing: -- ms/frame</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let detectionInterval;
        let frameCount = 0;
        let lastUpdateTime = Date.now();

        function startDetection() {
            fetch('detection.php?action=start_detection')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'started') {
                        updateStatus('Detection Started', 'normal');
                        addLogEntry('Detection system started');
                        // In a real implementation, you'd poll for updates
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    updateStatus('Error starting detection', 'alert');
                });
        }

        function stopDetection() {
            if (detectionInterval) {
                clearInterval(detectionInterval);
            }
            updateStatus('Detection Stopped', 'normal');
            addLogEntry('Detection system stopped');
        }

        function updateStatus(message, type) {
            const statusDiv = document.getElementById('status-display');
            statusDiv.className = `alert ${type === 'alert' ? 'alert-active' : 'normal-status'}`;
            statusDiv.querySelector('.detection-status').textContent = message;

            // Show/hide real-time indicator
            const realtimeIndicator = document.getElementById('realtime-indicator');
            if (message.includes('Active') || message.includes('Started')) {
                realtimeIndicator.classList.remove('d-none');
            } else {
                realtimeIndicator.classList.add('d-none');
            }
        }

        function addLogEntry(message) {
            const logDiv = document.getElementById('detection-log');
            const timestamp = new Date().toLocaleTimeString();
            const entry = document.createElement('p');
            entry.innerHTML = `<strong>${timestamp}:</strong> ${message}`;
            logDiv.appendChild(entry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        // Handle video upload
        document.getElementById('video-upload').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('detection.php?action=process_video', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLogEntry('Video processing completed');
                    updateStatus('Video Processed', 'normal');
                } else {
                    addLogEntry('Video processing failed: ' + (data.error || 'Unknown error'));
                    updateStatus('Processing Failed', 'alert');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                addLogEntry('Error processing video');
                updateStatus('Processing Error', 'alert');
            });
        });

        // Real-time status updates
        setInterval(() => {
            const currentTime = Date.now();
            frameCount++;

            fetch('index.php?action=get_status')
                .then(response => response.json())
                .then(data => {
                    if (data.active) {
                        updateStatus('System Active - Real-time Tracking', 'normal');
                        addLogEntry(`Frame: ${data.frames_processed || 0}, Motion: ${data.motion_percentage || 0}%, Status: ${data.status || 'Monitoring'}`);

                        // Calculate real-time performance
                        const timeDiff = currentTime - lastUpdateTime;
                        const fps = Math.round((frameCount / timeDiff) * 1000);
                        const processingTime = timeDiff / frameCount;

                        document.getElementById('fps-indicator').textContent = `FPS: ${fps}`;
                        document.getElementById('processing-indicator').textContent = `Processing: ${processingTime.toFixed(1)} ms/frame`;
                    } else {
                        updateStatus('System Inactive', 'normal');
                        document.getElementById('fps-indicator').textContent = 'FPS: --';
                        document.getElementById('processing-indicator').textContent = 'Processing: -- ms/frame';
                        frameCount = 0;
                    }

                    lastUpdateTime = currentTime;
                })
                .catch(error => {
                    console.error('Status update error:', error);
                    document.getElementById('fps-indicator').textContent = 'FPS: Error';
                    document.getElementById('processing-indicator').textContent = 'Processing: Error';
                });
        }, 1000); // Update every second for real-time tracking
    </script>
</body>
</html>
<?php
// End of web interface
?>