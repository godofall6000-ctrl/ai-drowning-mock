<?php
/**
 * Advanced AI Detection System for Drowning vs Swimming Recognition
 * Intelligent pattern analysis with smart beeping control
 */

class AIDetectionSystem {
    private $db;
    private $beeping_active = false;
    private $last_detection_time = 0;
    private $detection_history = [];
    private $swimming_patterns = [];
    private $drowning_patterns = [];

    public function __construct() {
        $this->db = new Database();
        $this->loadPatterns();
    }

    /**
     * Analyze detection data to determine swimming vs drowning
     */
    public function analyzeBehavior($motion_data, $pattern_analysis) {
        $current_time = time();

        // Add to detection history
        $this->detection_history[] = [
            'timestamp' => $current_time,
            'motion_percentage' => $motion_data['motion_percentage'],
            'motion_pixels' => $motion_data['motion_pixels'],
            'pattern_type' => $pattern_analysis['pattern_type'],
            'no_motion_duration' => $pattern_analysis['no_motion_duration']
        ];

        // Keep only recent history (last 60 seconds)
        $this->detection_history = array_filter($this->detection_history, function($entry) use ($current_time) {
            return ($current_time - $entry['timestamp']) <= 60;
        });

        // Analyze patterns
        $behavior = $this->classifyBehavior();

        // Control beeping based on behavior
        $this->controlBeeping($behavior, $pattern_analysis);

        // Log detection
        $this->logDetection($behavior, $motion_data, $pattern_analysis);

        return [
            'behavior' => $behavior,
            'beeping_active' => $this->beeping_active,
            'confidence' => $this->calculateConfidence($behavior),
            'location' => $this->getLocationData(),
            'timestamp' => $current_time
        ];
    }

    /**
     * Classify behavior as swimming, drowning, or unknown
     */
    private function classifyBehavior() {
        if (empty($this->detection_history)) {
            return 'unknown';
        }

        $recent_entries = array_slice($this->detection_history, -10); // Last 10 detections

        // Analyze motion patterns
        $avg_motion = array_sum(array_column($recent_entries, 'motion_percentage')) / count($recent_entries);
        $motion_variance = $this->calculateVariance(array_column($recent_entries, 'motion_percentage'));
        $no_motion_count = count(array_filter($recent_entries, function($entry) {
            return $entry['no_motion_duration'] > 5;
        }));

        // Analyze pattern types
        $pattern_counts = array_count_values(array_column($recent_entries, 'pattern_type'));
        $most_common_pattern = array_key_first($pattern_counts) ?? 'unknown';

        // Decision logic
        if ($no_motion_count >= 3 && $avg_motion < 2.0) {
            return 'drowning';
        } elseif ($avg_motion > 5.0 && $motion_variance > 10.0 && $most_common_pattern === 'active_swimming') {
            return 'swimming';
        } elseif ($avg_motion > 3.0 && $motion_variance > 5.0) {
            return 'swimming';
        } elseif ($no_motion_count >= 2) {
            return 'potential_drowning';
        } else {
            return 'monitoring';
        }
    }

    /**
     * Control beeping based on detected behavior
     */
    private function controlBeeping($behavior, $pattern_analysis) {
        $current_time = time();

        switch ($behavior) {
            case 'drowning':
                if (!$this->beeping_active) {
                    $this->startBeeping();
                    $this->beeping_active = true;
                    $this->last_detection_time = $current_time;
                }
                break;

            case 'potential_drowning':
                if (!$this->beeping_active && ($current_time - $this->last_detection_time) > 10) {
                    $this->startBeeping();
                    $this->beeping_active = true;
                    $this->last_detection_time = $current_time;
                }
                break;

            case 'swimming':
                if ($this->beeping_active) {
                    $this->stopBeeping();
                    $this->beeping_active = false;
                }
                break;

            case 'monitoring':
                // Keep current beeping state but reduce frequency
                if ($this->beeping_active && ($current_time - $this->last_detection_time) > 30) {
                    $this->stopBeeping();
                    $this->beeping_active = false;
                }
                break;
        }
    }

    /**
     * Start beeping sequence
     */
    private function startBeeping() {
        // Generate emergency beeping pattern
        $this->generateBeepPattern('emergency');

        // Log alert
        $alert_system = new AlertSystem();
        $alert_system->triggerDrowningAlert(
            "🚨 EMERGENCY: Drowning detected! Location: " . $this->getLocationString(),
            'high'
        );

        logMessage("Emergency beeping started - Drowning detected", 'ALERT');
    }

    /**
     * Stop beeping
     */
    private function stopBeeping() {
        $this->generateBeepPattern('stop');
        logMessage("Beeping stopped - Swimming detected", 'INFO');
    }

    /**
     * Generate beep patterns for different scenarios
     */
    private function generateBeepPattern($type) {
        $patterns = [
            'emergency' => [
                ['frequency' => 800, 'duration' => 500, 'repeat' => 3, 'interval' => 200],
                ['frequency' => 1000, 'duration' => 300, 'repeat' => 2, 'interval' => 150]
            ],
            'warning' => [
                ['frequency' => 600, 'duration' => 300, 'repeat' => 2, 'interval' => 300]
            ],
            'stop' => [
                ['frequency' => 400, 'duration' => 200, 'repeat' => 1, 'interval' => 0]
            ]
        ];

        if (isset($patterns[$type])) {
            $_SESSION['beep_pattern'] = $patterns[$type];
            $_SESSION['beep_active'] = ($type !== 'stop');
        }
    }

    /**
     * Calculate confidence in behavior classification
     */
    private function calculateConfidence($behavior) {
        if (empty($this->detection_history)) {
            return 0.0;
        }

        $recent_entries = array_slice($this->detection_history, -5);
        $consistency_count = 0;

        foreach ($recent_entries as $entry) {
            $predicted = $this->predictBehaviorFromEntry($entry);
            if ($predicted === $behavior) {
                $consistency_count++;
            }
        }

        return round(($consistency_count / count($recent_entries)) * 100, 2);
    }

    /**
     * Predict behavior from single entry
     */
    private function predictBehaviorFromEntry($entry) {
        if ($entry['no_motion_duration'] > 5 && $entry['motion_percentage'] < 2.0) {
            return 'drowning';
        } elseif ($entry['motion_percentage'] > 5.0) {
            return 'swimming';
        } else {
            return 'monitoring';
        }
    }

    /**
     * Get location data for the detection
     */
    private function getLocationData() {
        // Simulate GPS coordinates (in real implementation, this would come from ESP)
        $locations = [
            ['lat' => 14.5995, 'lng' => 120.9842, 'zone' => 'Main Pool', 'accuracy' => 5.2],
            ['lat' => 14.5997, 'lng' => 120.9845, 'zone' => 'Deep End', 'accuracy' => 3.8],
            ['lat' => 14.5993, 'lng' => 120.9840, 'zone' => 'Shallow End', 'accuracy' => 4.1],
            ['lat' => 14.5996, 'lng' => 120.9843, 'zone' => 'Diving Area', 'accuracy' => 2.9]
        ];

        // Randomly select a location for simulation
        return $locations[array_rand($locations)];
    }

    /**
     * Get location as formatted string
     */
    private function getLocationString() {
        $location = $this->getLocationData();
        return sprintf("%.6f, %.6f (%s)",
            $location['lat'],
            $location['lng'],
            $location['zone']
        );
    }

    /**
     * Log detection data
     */
    private function logDetection($behavior, $motion_data, $pattern_analysis) {
        $location = $this->getLocationData();

        $this->db->logDetection(
            $motion_data['motion_detected'],
            true, // AI detected human
            $this->calculateConfidence($behavior),
            json_encode([
                'behavior' => $behavior,
                'motion_data' => $motion_data,
                'pattern_analysis' => $pattern_analysis,
                'location' => $location,
                'beeping_active' => $this->beeping_active
            ]),
            'ai_detection_system'
        );
    }

    /**
     * Load predefined patterns for swimming and drowning
     */
    private function loadPatterns() {
        // Swimming patterns (regular, rhythmic motion)
        $this->swimming_patterns = [
            'motion_range' => [5.0, 20.0],
            'motion_variance' => [10.0, 50.0],
            'no_motion_threshold' => 2,
            'pattern_frequency' => [0.5, 2.0]
        ];

        // Drowning patterns (irregular, decreasing motion)
        $this->drowning_patterns = [
            'motion_range' => [0.0, 5.0],
            'motion_variance' => [0.0, 5.0],
            'no_motion_threshold' => 5,
            'pattern_frequency' => [0.0, 0.2]
        ];
    }

    /**
     * Calculate variance of an array
     */
    private function calculateVariance($data) {
        if (empty($data)) return 0.0;

        $mean = array_sum($data) / count($data);
        $variance = 0.0;

        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }

        return $variance / count($data);
    }

    /**
     * Get current system status
     */
    public function getStatus() {
        return [
            'beeping_active' => $this->beeping_active,
            'detection_history_count' => count($this->detection_history),
            'last_detection_time' => $this->last_detection_time,
            'current_location' => $this->getLocationData(),
            'system_health' => 'operational'
        ];
    }

    /**
     * Reset the detection system
     */
    public function reset() {
        $this->beeping_active = false;
        $this->detection_history = [];
        $this->last_detection_time = 0;
        $this->generateBeepPattern('stop');
    }
}

// Initialize AI detection system
$ai_detection = new AIDetectionSystem();
?>