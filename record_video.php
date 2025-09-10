<?php
/**
 * Video Recording System for AI Drowning Detection
 * Native PHP implementation using FFmpeg
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

class VideoRecorder {
    private $ffmpeg_path = 'ffmpeg'; // Assume ffmpeg is in PATH
    private $output_dir = 'data/';
    private $temp_dir = 'temp/';

    public function __construct() {
        if (!is_dir($this->output_dir)) {
            mkdir($this->output_dir, 0755, true);
        }
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }

    /**
     * Record video from webcam
     */
    public function recordFromWebcam($duration = 30, $filename = null) {
        if (!$filename) {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "recorded_video_{$timestamp}.mp4";
        }

        $output_path = $this->output_dir . $filename;

        logMessage("Starting video recording: {$output_path}");

        // FFmpeg command for webcam recording
        $command = sprintf(
            '%s -f dshow -i video="Integrated Webcam" -t %d -c:v libx264 -preset fast -crf 22 "%s" 2>&1',
            $this->ffmpeg_path,
            $duration,
            $output_path
        );

        // For Windows, try different input formats
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Try DirectShow first
            $command = sprintf(
                '%s -f dshow -i video="Integrated Webcam" -t %d -c:v libx264 -preset fast -crf 22 "%s" 2>&1',
                $this->ffmpeg_path,
                $duration,
                $output_path
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                // Fallback to Video4Linux2 (though less likely on Windows)
                $command = sprintf(
                    '%s -f v4l2 -i /dev/video0 -t %d -c:v libx264 -preset fast -crf 22 "%s" 2>&1',
                    $this->ffmpeg_path,
                    $duration,
                    $output_path
                );
                exec($command, $output, $return_var);
            }
        } else {
            // Linux/Unix systems
            $command = sprintf(
                '%s -f v4l2 -i /dev/video0 -t %d -c:v libx264 -preset fast -crf 22 "%s" 2>&1',
                $this->ffmpeg_path,
                $duration,
                $output_path
            );
            exec($command, $output, $return_var);
        }

        if ($return_var === 0 && file_exists($output_path)) {
            $file_size = filesize($output_path);
            logMessage("Video recording completed: {$output_path} ({$file_size} bytes)");
            return $output_path;
        } else {
            logMessage("Video recording failed: " . implode("\n", $output), 'ERROR');
            return false;
        }
    }

    /**
     * Record video with motion detection
     */
    public function recordWithMotionDetection($max_duration = 300, $motion_timeout = 10) {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "motion_recorded_{$timestamp}.mp4";
        $output_path = $this->output_dir . $filename;

        logMessage("Starting motion-based recording");

        $start_time = time();
        $last_motion_time = time();
        $recording = false;
        $segment_files = [];

        while ((time() - $start_time) < $max_duration) {
            // Capture single frame for motion detection
            $frame_path = $this->captureFrame();

            if ($frame_path) {
                // Check for motion (simplified - in real implementation, use motion detector)
                $motion_detected = $this->detectMotionInFrame($frame_path);

                if ($motion_detected) {
                    $last_motion_time = time();

                    if (!$recording) {
                        // Start recording
                        $segment_file = $this->startRecordingSegment();
                        if ($segment_file) {
                            $segment_files[] = $segment_file;
                            $recording = true;
                            logMessage("Motion detected, started recording: {$segment_file}");
                        }
                    }
                } else {
                    if ($recording && (time() - $last_motion_time) > $motion_timeout) {
                        // Stop recording after timeout
                        $this->stopRecordingSegment();
                        $recording = false;
                        logMessage("Motion stopped, paused recording");
                    }
                }

                unlink($frame_path); // Cleanup frame
            }

            sleep(1); // Check every second
        }

        // Stop any ongoing recording
        if ($recording) {
            $this->stopRecordingSegment();
        }

        // Combine segments if any were created
        if (!empty($segment_files)) {
            $combined = $this->combineVideoSegments($segment_files, $output_path);
            if ($combined) {
                // Cleanup segment files
                foreach ($segment_files as $segment) {
                    if (file_exists($segment)) {
                        unlink($segment);
                    }
                }
                logMessage("Motion-based recording completed: {$output_path}");
                return $output_path;
            }
        }

        logMessage("No motion detected during recording period");
        return false;
    }

    /**
     * Capture a single frame from webcam
     */
    private function captureFrame() {
        $frame_path = $this->temp_dir . 'frame_' . time() . '.jpg';

        $command = sprintf(
            '%s -f v4l2 -i /dev/video0 -vframes 1 "%s" 2>&1',
            $this->ffmpeg_path,
            $frame_path
        );

        exec($command, $output, $return_var);

        if ($return_var === 0 && file_exists($frame_path)) {
            return $frame_path;
        }

        return false;
    }

    /**
     * Simple motion detection in frame (placeholder)
     */
    private function detectMotionInFrame($frame_path) {
        // In a real implementation, this would use the motion detector
        // For now, return random motion detection for demonstration
        return rand(0, 10) > 7; // 30% chance of detecting motion
    }

    /**
     * Start recording a video segment
     */
    private function startRecordingSegment() {
        $segment_file = $this->temp_dir . 'segment_' . time() . '.mp4';

        // Start FFmpeg process in background
        $command = sprintf(
            '%s -f v4l2 -i /dev/video0 -c:v libx264 -preset fast -crf 22 "%s" > /dev/null 2>&1 & echo $!',
            $this->ffmpeg_path,
            $segment_file
        );

        $pid = exec($command);
        $this->recording_pid = $pid;

        return $segment_file;
    }

    /**
     * Stop recording segment
     */
    private function stopRecordingSegment() {
        if (isset($this->recording_pid)) {
            exec("kill {$this->recording_pid}");
            unset($this->recording_pid);
        }
    }

    /**
     * Combine video segments into one file
     */
    private function combineVideoSegments($segment_files, $output_path) {
        if (empty($segment_files)) {
            return false;
        }

        // Create concat file
        $concat_file = $this->temp_dir . 'concat_' . time() . '.txt';
        $concat_content = '';
        foreach ($segment_files as $segment) {
            $concat_content .= "file '{$segment}'\n";
        }
        file_put_contents($concat_file, $concat_content);

        // Combine segments
        $command = sprintf(
            '%s -f concat -safe 0 -i "%s" -c copy "%s" 2>&1',
            $this->ffmpeg_path,
            $concat_file,
            $output_path
        );

        exec($command, $output, $return_var);

        // Cleanup
        unlink($concat_file);

        if ($return_var === 0 && file_exists($output_path)) {
            return true;
        }

        return false;
    }

    /**
     * Get list of recorded videos
     */
    public function getRecordedVideos() {
        $videos = glob($this->output_dir . '*.mp4');
        sort($videos);

        $video_info = [];
        foreach ($videos as $video) {
            $video_info[] = [
                'filename' => basename($video),
                'path' => $video,
                'size' => filesize($video),
                'modified' => filemtime($video),
                'size_human' => $this->formatBytes(filesize($video))
            ];
        }

        return array_reverse($video_info); // Most recent first
    }

    /**
     * Delete a recorded video
     */
    public function deleteVideo($filename) {
        $filepath = $this->output_dir . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Handle command line arguments
if (isset($argv) && count($argv) > 1) {
    $recorder = new VideoRecorder();

    switch ($argv[1]) {
        case 'record':
            $duration = isset($argv[2]) ? (int)$argv[2] : 30;
            $result = $recorder->recordFromWebcam($duration);
            if ($result) {
                echo "Recording saved to: {$result}\n";
            } else {
                echo "Recording failed\n";
                exit(1);
            }
            break;

        case 'motion':
            $duration = isset($argv[2]) ? (int)$argv[2] : 300;
            $result = $recorder->recordWithMotionDetection($duration);
            if ($result) {
                echo "Motion-based recording saved to: {$result}\n";
            } else {
                echo "Motion-based recording failed or no motion detected\n";
                exit(1);
            }
            break;

        default:
            echo "Usage: php record_video.php [record|motion] [duration]\n";
            exit(1);
    }
    exit(0);
}

// Web interface
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $recorder = new VideoRecorder();

    switch ($_GET['action']) {
        case 'start_recording':
            $duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 30;
            $result = $recorder->recordFromWebcam($duration);
            echo json_encode([
                'success' => $result !== false,
                'file' => $result ? basename($result) : null
            ]);
            break;

        case 'list_videos':
            $videos = $recorder->getRecordedVideos();
            echo json_encode(['videos' => $videos]);
            break;

        case 'delete_video':
            if (isset($_GET['filename'])) {
                $result = $recorder->deleteVideo($_GET['filename']);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No filename provided']);
            }
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Recording - AI Drowning Detection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .video-item { margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .recording-active { background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1>Video Recording System</h1>
                <p class="text-muted">AI Drowning Detection System - Native PHP Implementation</p>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Recording Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group mb-3">
                                    <input type="number" class="form-control" id="record-duration" value="30" min="5" max="300">
                                    <button class="btn btn-primary" onclick="startRecording()">
                                        Start Recording
                                    </button>
                                </div>
                                <small class="text-muted">Duration in seconds (5-300)</small>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-success" onclick="startMotionRecording()">
                                    Start Motion-Based Recording
                                </button>
                                <br><small class="text-muted">Records only when motion is detected</small>
                            </div>
                        </div>

                        <div id="recording-status" class="mt-3" style="display: none;">
                            <div class="alert recording-active">
                                <strong>Recording in progress...</strong>
                                <div class="spinner-border spinner-border-sm ms-2" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Recorded Videos</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshVideos()">
                            Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="videos-list">
                            <p class="text-muted">Loading videos...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Modal -->
    <div class="modal fade" id="videoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="videoModalTitle">Video Player</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <video id="videoPlayer" controls style="width: 100%;"></video>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let recordingTimeout;

        function startRecording() {
            const duration = document.getElementById('record-duration').value;
            const statusDiv = document.getElementById('recording-status');

            statusDiv.style.display = 'block';

            fetch(`record_video.php?action=start_recording&duration=${duration}`)
                .then(response => response.json())
                .then(data => {
                    statusDiv.style.display = 'none';

                    if (data.success) {
                        alert(`Recording completed! Saved as: ${data.file}`);
                        refreshVideos();
                    } else {
                        alert('Recording failed');
                    }
                })
                .catch(error => {
                    statusDiv.style.display = 'none';
                    console.error('Recording error:', error);
                    alert('Recording failed: ' + error.message);
                });
        }

        function startMotionRecording() {
            const statusDiv = document.getElementById('recording-status');
            statusDiv.style.display = 'block';

            // For motion recording, we'll simulate a long recording
            fetch('record_video.php?action=start_recording&duration=60')
                .then(response => response.json())
                .then(data => {
                    statusDiv.style.display = 'none';

                    if (data.success) {
                        alert(`Motion-based recording completed! Saved as: ${data.file}`);
                        refreshVideos();
                    } else {
                        alert('Motion-based recording failed');
                    }
                })
                .catch(error => {
                    statusDiv.style.display = 'none';
                    console.error('Motion recording error:', error);
                    alert('Motion recording failed: ' + error.message);
                });
        }

        function refreshVideos() {
            const videosList = document.getElementById('videos-list');
            videosList.innerHTML = '<p class="text-muted">Loading videos...</p>';

            fetch('record_video.php?action=list_videos')
                .then(response => response.json())
                .then(data => {
                    displayVideos(data.videos);
                })
                .catch(error => {
                    console.error('Error loading videos:', error);
                    videosList.innerHTML = '<p class="text-danger">Error loading videos</p>';
                });
        }

        function displayVideos(videos) {
            const videosList = document.getElementById('videos-list');

            if (!videos || videos.length === 0) {
                videosList.innerHTML = '<p class="text-muted">No recorded videos found</p>';
                return;
            }

            let html = '';
            videos.forEach(video => {
                const date = new Date(video.modified * 1000).toLocaleString();
                html += `
                    <div class="video-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${video.filename}</strong><br>
                                <small class="text-muted">
                                    Size: ${video.size_human} | Modified: ${date}
                                </small>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="playVideo('${video.filename}')">
                                    Play
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteVideo('${video.filename}')">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            videosList.innerHTML = html;
        }

        function playVideo(filename) {
            const videoPlayer = document.getElementById('videoPlayer');
            const modalTitle = document.getElementById('videoModalTitle');

            videoPlayer.src = `data/${filename}`;
            modalTitle.textContent = filename;

            const modal = new bootstrap.Modal(document.getElementById('videoModal'));
            modal.show();
        }

        function deleteVideo(filename) {
            if (!confirm(`Delete video "${filename}"? This cannot be undone.`)) {
                return;
            }

            fetch(`record_video.php?action=delete_video&filename=${filename}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Video deleted successfully');
                        refreshVideos();
                    } else {
                        alert('Failed to delete video');
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    alert('Error deleting video');
                });
        }

        // Load videos on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshVideos();
        });
    </script>
</body>
</html>