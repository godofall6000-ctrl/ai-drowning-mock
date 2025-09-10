<?php
/**
 * Test System for AI Drowning Detection System
 * Native PHP implementation
 */

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/motion_detector.php';
require_once 'includes/ai_detector.php';
require_once 'includes/alert_system.php';

// Initialize components
$db = new Database();
$motion_detector = $GLOBALS['motion_detector'];
$ai_detector = $GLOBALS['ai_detector'];
$alert_system = $GLOBALS['alert_system'];

class SystemTester {
    private $test_results = [];
    private $temp_dir = 'temp/';

    public function __construct() {
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }

    /**
     * Run all system tests
     */
    public function runAllTests() {
        $this->log("Running AI Drowning Detection System Tests");
        $this->log("=" . str_repeat("=", 50));

        $tests = [
            'testMotionDetection',
            'testAIDetection',
            'testDatabaseOperations',
            'testAlertSystem',
            'testVideoProcessing'
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $test) {
            try {
                $result = $this->$test();
                if ($result) {
                    $passed++;
                    $this->log("✓ {$test} PASSED");
                } else {
                    $this->log("❌ {$test} FAILED");
                }
            } catch (Exception $e) {
                $this->log("❌ {$test} ERROR: " . $e->getMessage());
            }
        }

        $this->log("=" . str_repeat("=", 50));
        $this->log("Test Results: {$passed}/{$total} tests passed");

        if ($passed == $total) {
            $this->log("✅ All tests completed successfully!");
        } else {
            $this->log("⚠️  Some tests failed. Check the logs above.");
        }

        return $passed == $total;
    }

    /**
     * Test motion detection
     */
    private function testMotionDetection() {
        $this->log("Testing motion detection...");

        // Create identical frames (no motion)
        $frame1 = $this->createTestFrame(640, 480, [100, 100, 100]); // Gray
        $frame2 = $this->createTestFrame(640, 480, [100, 100, 100]); // Gray

        $result1 = $motion_detector->processFrame($frame1);
        $no_motion = !$result1['motion_detected'];

        // Create frames with difference (motion)
        $frame3 = $this->createTestFrame(640, 480, [100, 100, 100]); // Gray
        $frame4 = $this->createTestFrame(640, 480, [100, 100, 100]); // Gray
        // Add white rectangle to simulate motion
        $this->addRectangle($frame4, 200, 200, 100, 100, [255, 255, 255]);

        $result2 = $motion_detector->processFrame($frame4);
        $motion = $result2['motion_detected'];

        // Cleanup
        unlink($frame1);
        unlink($frame2);
        unlink($frame3);
        unlink($frame4);

        return $no_motion && $motion;
    }

    /**
     * Test AI detection
     */
    private function testAIDetection() {
        $this->log("Testing AI detection...");

        // Create test frame
        $frame = $this->createTestFrame(320, 240, [200, 150, 100]); // Skin-like color
        $image = imagecreatefromjpeg($frame);

        $human_count = $ai_detector->getHumanCount($image);

        // For basic detection, we expect some detection on skin-colored areas
        $result = is_numeric($human_count) && $human_count >= 0;

        imagedestroy($image);
        unlink($frame);

        return $result;
    }

    /**
     * Test database operations
     */
    private function testDatabaseOperations() {
        $this->log("Testing database operations...");

        // Test alert logging
        $alert_id = $db->logAlert("TEST_ALERT", "Test alert for system testing", "low");
        $alert_logged = $alert_id !== false;

        // Test detection logging
        $detection_id = $db->logDetection(true, false, 0.5);
        $detection_logged = $detection_id !== false;

        // Test retrieval
        $alerts = $db->getRecentAlerts(1);
        $alert_retrieved = !empty($alerts);

        return $alert_logged && $detection_logged && $alert_retrieved;
    }

    /**
     * Test alert system
     */
    private function testAlertSystem() {
        $this->log("Testing alert system...");

        // Test motion alert
        $motion_alert = $alert_system->triggerMotionAlert("Test motion detection");
        $motion_triggered = $motion_alert !== false;

        // Test system alert
        $system_alert = $alert_system->triggerSystemAlert("Test system message");
        $system_triggered = $system_alert !== false;

        return $motion_triggered && $system_triggered;
    }

    /**
     * Test video processing
     */
    private function testVideoProcessing() {
        $this->log("Testing video processing...");

        // Create a simple test video using FFmpeg (if available)
        $test_video = $this->temp_dir . 'test_video.mp4';
        $test_frame = $this->createTestFrame(320, 240, [255, 0, 0]); // Red frame

        // Use FFmpeg to create a short test video
        $command = "ffmpeg -loop 1 -i {$test_frame} -c:v libx264 -t 2 -pix_fmt yuv420p {$test_video} 2>&1";
        exec($command, $output, $return_var);

        $video_created = file_exists($test_video) && $return_var === 0;

        // Cleanup
        if (file_exists($test_frame)) unlink($test_frame);
        if (file_exists($test_video)) unlink($test_video);

        return $video_created;
    }

    /**
     * Create a test frame
     */
    private function createTestFrame($width, $height, $color) {
        $image = imagecreatetruecolor($width, $height);
        $bg_color = imagecolorallocate($image, $color[0], $color[1], $color[2]);
        imagefill($image, 0, 0, $bg_color);

        $filename = $this->temp_dir . 'test_' . time() . '_' . rand() . '.jpg';
        imagejpeg($image, $filename);
        imagedestroy($image);

        return $filename;
    }

    /**
     * Add a rectangle to an image
     */
    private function addRectangle($image_path, $x, $y, $width, $height, $color) {
        $image = imagecreatefromjpeg($image_path);
        $rect_color = imagecolorallocate($image, $color[0], $color[1], $color[2]);
        imagefilledrectangle($image, $x, $y, $x + $width, $y + $height, $rect_color);
        imagejpeg($image, $image_path);
        imagedestroy($image);
    }

    /**
     * Log test message
     */
    private function log($message) {
        echo $message . PHP_EOL;
        $this->test_results[] = $message;
    }

    /**
     * Get test results
     */
    public function getResults() {
        return $this->test_results;
    }

    /**
     * Cleanup temporary files
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

// Handle command line execution
if (isset($argv) && count($argv) > 0) {
    $tester = new SystemTester();
    $success = $tester->runAllTests();
    $tester->cleanup();
    exit($success ? 0 : 1);
}

// Web interface for tests
if (isset($_GET['action']) && $_GET['action'] === 'run_tests') {
    header('Content-Type: application/json');

    $tester = new SystemTester();
    $success = $tester->runAllTests();
    $results = $tester->getResults();
    $tester->cleanup();

    echo json_encode([
        'success' => $success,
        'results' => $results
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Tests - AI Drowning Detection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .test-pass { background-color: #d4edda; border-color: #c3e6cb; }
        .test-fail { background-color: #f8d7da; border-color: #f5c6cb; }
        .test-running { background-color: #fff3cd; border-color: #ffeaa7; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>System Test Suite</h1>
                <p class="text-muted">AI Drowning Detection System - Native PHP Implementation</p>

                <div class="card">
                    <div class="card-header">
                        <h5>Test Controls</h5>
                    </div>
                    <div class="card-body">
                        <button id="run-tests-btn" class="btn btn-primary btn-lg" onclick="runTests()">
                            Run All Tests
                        </button>
                        <button class="btn btn-secondary" onclick="clearResults()">
                            Clear Results
                        </button>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Test Results</h5>
                    </div>
                    <div class="card-body">
                        <div id="test-results" class="test-results">
                            <p class="text-muted">Click "Run All Tests" to start testing...</p>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Test Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="total-tests">0</h3>
                                    <p>Total Tests</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="passed-tests" class="text-success">0</h3>
                                    <p>Passed</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="failed-tests" class="text-danger">0</h3>
                                    <p>Failed</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="success-rate">0%</h3>
                                    <p>Success Rate</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function runTests() {
            const btn = document.getElementById('run-tests-btn');
            const resultsDiv = document.getElementById('test-results');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Running Tests...';

            resultsDiv.innerHTML = '<div class="test-result test-running">Initializing tests...</div>';

            fetch('test_system.php?action=run_tests')
                .then(response => response.json())
                .then(data => {
                    displayResults(data.results);
                    updateSummary(data.results);

                    btn.disabled = false;
                    btn.innerHTML = 'Run All Tests';
                })
                .catch(error => {
                    console.error('Test error:', error);
                    resultsDiv.innerHTML = '<div class="test-result test-fail">Error running tests: ' + error.message + '</div>';
                    btn.disabled = false;
                    btn.innerHTML = 'Run All Tests';
                });
        }

        function displayResults(results) {
            const resultsDiv = document.getElementById('test-results');
            resultsDiv.innerHTML = '';

            results.forEach(result => {
                const div = document.createElement('div');
                div.className = 'test-result';

                if (result.includes('✓')) {
                    div.classList.add('test-pass');
                } else if (result.includes('❌')) {
                    div.classList.add('test-fail');
                } else if (result.includes('Running') || result.includes('=')) {
                    div.classList.add('test-running');
                } else {
                    div.classList.add('test-running');
                }

                div.textContent = result;
                resultsDiv.appendChild(div);
            });

            resultsDiv.scrollTop = resultsDiv.scrollHeight;
        }

        function updateSummary(results) {
            let total = 0;
            let passed = 0;
            let failed = 0;

            results.forEach(result => {
                if (result.includes('PASSED')) {
                    total++;
                    passed++;
                } else if (result.includes('FAILED') || result.includes('ERROR')) {
                    total++;
                    failed++;
                }
            });

            const successRate = total > 0 ? Math.round((passed / total) * 100) : 0;

            document.getElementById('total-tests').textContent = total;
            document.getElementById('passed-tests').textContent = passed;
            document.getElementById('failed-tests').textContent = failed;
            document.getElementById('success-rate').textContent = successRate + '%';
        }

        function clearResults() {
            document.getElementById('test-results').innerHTML = '<p class="text-muted">Click "Run All Tests" to start testing...</p>';
            document.getElementById('total-tests').textContent = '0';
            document.getElementById('passed-tests').textContent = '0';
            document.getElementById('failed-tests').textContent = '0';
            document.getElementById('success-rate').textContent = '0%';
        }
    </script>
</body>
</html>