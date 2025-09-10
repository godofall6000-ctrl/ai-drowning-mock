<?php
/**
 * Motion Detection for AI Drowning Detection System
 * Uses GD library for image processing
 */

class MotionDetector {
    private $previous_frame = null;
    private $motion_threshold;
    private $min_contour_area;
    private $frame_width;
    private $frame_height;

    public function __construct($threshold = MOTION_THRESHOLD, $min_area = 500) {
        $this->motion_threshold = $threshold;
        $this->min_contour_area = $min_area;
        $this->frame_width = MAX_IMAGE_WIDTH;
        $this->frame_height = MAX_IMAGE_HEIGHT;
    }

    /**
     * Process a frame for motion detection
     * @param string $image_data Raw image data or file path
     * @return array Detection results
     */
    public function processFrame($image_data) {
        $current_frame = $this->loadImage($image_data);

        if (!$current_frame) {
            return [
                'motion_detected' => false,
                'error' => 'Failed to load image'
            ];
        }

        $result = [
            'motion_detected' => false,
            'motion_areas' => [],
            'frame_difference' => 0,
            'processing_time' => 0
        ];

        $start_time = microtime(true);

        if ($this->previous_frame) {
            // Calculate motion
            $motion_data = $this->calculateMotion($this->previous_frame, $current_frame);
            $result = array_merge($result, $motion_data);
        }

        // Store current frame for next comparison
        $this->previous_frame = $current_frame;

        $result['processing_time'] = microtime(true) - $start_time;

        return $result;
    }

    /**
     * Load image from file path or raw data
     */
    private function loadImage($image_data) {
        if (file_exists($image_data)) {
            // Load from file
            $image = imagecreatefromstring(file_get_contents($image_data));
        } else {
            // Assume raw image data
            $image = imagecreatefromstring($image_data);
        }

        if (!$image) {
            return false;
        }

        // Resize to standard dimensions
        $resized = imagecreatetruecolor($this->frame_width, $this->frame_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0,
                          $this->frame_width, $this->frame_height,
                          imagesx($image), imagesy($image));

        imagedestroy($image);
        return $resized;
    }

    /**
     * Calculate motion between two frames
     */
    private function calculateMotion($frame1, $frame2) {
        $width = imagesx($frame1);
        $height = imagesy($frame1);

        $motion_pixels = 0;
        $total_pixels = $width * $height;
        $motion_areas = [];

        // Convert to grayscale and calculate differences
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb1 = imagecolorat($frame1, $x, $y);
                $rgb2 = imagecolorat($frame2, $x, $y);

                $gray1 = $this->rgbToGray($rgb1);
                $gray2 = $this->rgbToGray($rgb2);

                $diff = abs($gray1 - $gray2);

                if ($diff > $this->motion_threshold) {
                    $motion_pixels++;
                    $motion_areas[] = ['x' => $x, 'y' => $y, 'intensity' => $diff];
                }
            }
        }

        $motion_percentage = ($motion_pixels / $total_pixels) * 100;
        $motion_detected = $motion_percentage > 0.1; // 0.1% threshold

        return [
            'motion_detected' => $motion_detected,
            'motion_percentage' => round($motion_percentage, 2),
            'motion_pixels' => $motion_pixels,
            'motion_areas' => array_slice($motion_areas, 0, 100), // Limit to 100 areas
            'frame_difference' => $motion_pixels
        ];
    }

    /**
     * Convert RGB to grayscale
     */
    private function rgbToGray($rgb) {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // Standard luminance formula
        return round(0.299 * $r + 0.587 * $g + 0.114 * $b);
    }

    /**
     * Advanced motion analysis for drowning detection
     */
    public function analyzeMotionPattern($motion_data, $time_window = 10) {
        static $motion_history = [];

        // Add current motion data to history
        $motion_history[] = [
            'timestamp' => time(),
            'motion_detected' => $motion_data['motion_detected'],
            'motion_percentage' => $motion_data['motion_percentage']
        ];

        // Keep only recent history
        $cutoff_time = time() - $time_window;
        $motion_history = array_filter($motion_history, function($entry) use ($cutoff_time) {
            return $entry['timestamp'] > $cutoff_time;
        });

        // Analyze patterns
        $analysis = [
            'no_motion_duration' => $this->calculateNoMotionDuration($motion_history),
            'motion_frequency' => $this->calculateMotionFrequency($motion_history),
            'motion_intensity' => $this->calculateAverageMotion($motion_history),
            'pattern_type' => $this->classifyMotionPattern($motion_history)
        ];

        return $analysis;
    }

    /**
     * Calculate duration of no motion
     */
    private function calculateNoMotionDuration($history) {
        if (empty($history)) return 0;

        $last_motion_time = 0;
        foreach ($history as $entry) {
            if ($entry['motion_detected']) {
                $last_motion_time = $entry['timestamp'];
            }
        }

        if ($last_motion_time == 0) {
            return time() - $history[0]['timestamp'];
        }

        return time() - $last_motion_time;
    }

    /**
     * Calculate motion frequency
     */
    private function calculateMotionFrequency($history) {
        if (empty($history)) return 0;

        $motion_events = 0;
        foreach ($history as $entry) {
            if ($entry['motion_detected']) {
                $motion_events++;
            }
        }

        $total_time = end($history)['timestamp'] - $history[0]['timestamp'];
        return $total_time > 0 ? $motion_events / $total_time : 0;
    }

    /**
     * Calculate average motion intensity
     */
    private function calculateAverageMotion($history) {
        if (empty($history)) return 0;

        $total_motion = 0;
        $count = 0;

        foreach ($history as $entry) {
            if ($entry['motion_detected']) {
                $total_motion += $entry['motion_percentage'];
                $count++;
            }
        }

        return $count > 0 ? $total_motion / $count : 0;
    }

    /**
     * Classify motion pattern
     */
    private function classifyMotionPattern($history) {
        if (empty($history)) return 'unknown';

        $no_motion_duration = $this->calculateNoMotionDuration($history);
        $motion_frequency = $this->calculateMotionFrequency($history);
        $avg_motion = $this->calculateAverageMotion($history);

        // Simple classification logic
        if ($no_motion_duration > ALERT_TIMEOUT) {
            return 'potential_drowning';
        } elseif ($motion_frequency > 0.5) {
            return 'active_swimming';
        } elseif ($avg_motion > 5.0) {
            return 'moderate_activity';
        } elseif ($avg_motion > 0.5) {
            return 'minimal_activity';
        } else {
            return 'no_activity';
        }
    }

    /**
     * Generate motion visualization
     */
    public function generateMotionImage($frame1, $frame2, $motion_data) {
        $width = imagesx($frame1);
        $height = imagesy($frame1);

        // Create new image for visualization
        $motion_image = imagecreatetruecolor($width, $height);

        // Copy original frame
        imagecopy($motion_image, $frame1, 0, 0, 0, 0, $width, $height);

        // Overlay motion areas
        $motion_color = imagecolorallocate($motion_image, 255, 0, 0); // Red

        foreach ($motion_data['motion_areas'] as $area) {
            imagesetpixel($motion_image, $area['x'], $area['y'], $motion_color);
        }

        // Add text overlay
        $text_color = imagecolorallocate($motion_image, 255, 255, 255);
        $bg_color = imagecolorallocate($motion_image, 0, 0, 0);

        $text = sprintf("Motion: %.1f%%", $motion_data['motion_percentage']);
        imagestring($motion_image, 5, 10, 10, $text, $text_color);

        return $motion_image;
    }

    /**
     * Reset detector state
     */
    public function reset() {
        $this->previous_frame = null;
    }

    /**
     * Get detector statistics
     */
    public function getStats() {
        return [
            'frame_width' => $this->frame_width,
            'frame_height' => $this->frame_height,
            'motion_threshold' => $this->motion_threshold,
            'min_contour_area' => $this->min_contour_area,
            'has_previous_frame' => $this->previous_frame !== null
        ];
    }
}

// Initialize motion detector
$motion_detector = new MotionDetector();
?>