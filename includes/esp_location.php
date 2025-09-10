<?php
/**
 * ESP Location Tracking System
 * GPS coordinates and zone-based monitoring for drowning detection
 */

class ESPLocationSystem {
    private $db;
    private $esp_devices = [];
    private $monitoring_zones = [];
    private $gps_history = [];

    public function __construct() {
        $this->db = new Database();
        $this->initializeZones();
        $this->loadESPDevices();
    }

    /**
     * Initialize monitoring zones
     */
    private function initializeZones() {
        $this->monitoring_zones = [
            [
                'id' => 'main_pool',
                'name' => 'Main Swimming Pool',
                'coordinates' => [
                    ['lat' => 14.5990, 'lng' => 120.9835],
                    ['lat' => 14.6000, 'lng' => 120.9835],
                    ['lat' => 14.6000, 'lng' => 120.9850],
                    ['lat' => 14.5990, 'lng' => 120.9850]
                ],
                'depth' => 'deep',
                'risk_level' => 'high',
                'description' => 'Main pool area with deep end'
            ],
            [
                'id' => 'shallow_end',
                'name' => 'Shallow End',
                'coordinates' => [
                    ['lat' => 14.5990, 'lng' => 120.9835],
                    ['lat' => 14.5995, 'lng' => 120.9840]
                ],
                'depth' => 'shallow',
                'risk_level' => 'low',
                'description' => 'Shallow water area for beginners'
            ],
            [
                'id' => 'deep_end',
                'name' => 'Deep End',
                'coordinates' => [
                    ['lat' => 14.5995, 'lng' => 120.9845],
                    ['lat' => 14.6000, 'lng' => 120.9850]
                ],
                'depth' => 'very_deep',
                'risk_level' => 'critical',
                'description' => 'Deep diving area - high risk'
            ],
            [
                'id' => 'kids_pool',
                'name' => 'Children\'s Pool',
                'coordinates' => [
                    ['lat' => 14.5985, 'lng' => 120.9830],
                    ['lat' => 14.5990, 'lng' => 120.9835]
                ],
                'depth' => 'very_shallow',
                'risk_level' => 'low',
                'description' => 'Children\'s play area'
            ]
        ];
    }

    /**
     * Load ESP devices from database
     */
    private function loadESPDevices() {
        // In a real implementation, this would load from database
        $this->esp_devices = [
            [
                'id' => 'esp_001',
                'name' => 'ESP32-CAM-01',
                'location' => 'Main Pool Camera',
                'coordinates' => ['lat' => 14.5995, 'lng' => 120.9842],
                'status' => 'online',
                'last_seen' => time(),
                'battery_level' => 85,
                'wifi_signal' => -45
            ],
            [
                'id' => 'esp_002',
                'name' => 'ESP32-CAM-02',
                'location' => 'Deep End Camera',
                'coordinates' => ['lat' => 14.5997, 'lng' => 120.9847],
                'status' => 'online',
                'last_seen' => time(),
                'battery_level' => 92,
                'wifi_signal' => -38
            ],
            [
                'id' => 'esp_003',
                'name' => 'ESP32-GPS-01',
                'location' => 'GPS Tracker',
                'coordinates' => ['lat' => 14.5996, 'lng' => 120.9843],
                'status' => 'online',
                'last_seen' => time(),
                'battery_level' => 78,
                'wifi_signal' => -52
            ]
        ];
    }

    /**
     * Get current GPS location (simulated ESP data)
     */
    public function getCurrentLocation() {
        // Simulate GPS coordinates changing over time
        $base_lat = 14.5995;
        $base_lng = 120.9842;

        // Add some random movement to simulate person swimming
        $lat_offset = (mt_rand(-100, 100) / 10000); // ±0.01 degrees
        $lng_offset = (mt_rand(-100, 100) / 10000);

        $current_location = [
            'lat' => $base_lat + $lat_offset,
            'lng' => $base_lng + $lng_offset,
            'accuracy' => mt_rand(3, 8), // meters
            'timestamp' => time(),
            'speed' => mt_rand(0, 200) / 100, // m/s
            'heading' => mt_rand(0, 359), // degrees
            'altitude' => mt_rand(10, 50) // meters above sea level
        ];

        // Store in history
        $this->gps_history[] = $current_location;

        // Keep only recent history
        if (count($this->gps_history) > 100) {
            array_shift($this->gps_history);
        }

        return $current_location;
    }

    /**
     * Determine which zone a location is in
     */
    public function getZoneForLocation($lat, $lng) {
        foreach ($this->monitoring_zones as $zone) {
            if ($this->isPointInZone($lat, $lng, $zone['coordinates'])) {
                return $zone;
            }
        }

        return [
            'id' => 'unknown',
            'name' => 'Unknown Area',
            'depth' => 'unknown',
            'risk_level' => 'unknown',
            'description' => 'Location outside monitored zones'
        ];
    }

    /**
     * Check if a point is inside a zone (simple bounding box)
     */
    private function isPointInZone($lat, $lng, $zone_coords) {
        if (empty($zone_coords)) return false;

        $min_lat = min(array_column($zone_coords, 'lat'));
        $max_lat = max(array_column($zone_coords, 'lat'));
        $min_lng = min(array_column($zone_coords, 'lng'));
        $max_lng = max(array_column($zone_coords, 'lng'));

        return ($lat >= $min_lat && $lat <= $max_lat &&
                $lng >= $min_lng && $lng <= $max_lng);
    }

    /**
     * Calculate distance between two GPS points
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earth_radius = 6371000; // meters

        $lat_delta = deg2rad($lat2 - $lat1);
        $lng_delta = deg2rad($lng2 - $lng1);

        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lng_delta / 2) * sin($lng_delta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius * $c;
    }

    /**
     * Get emergency response information for a location
     */
    public function getEmergencyResponse($lat, $lng) {
        $zone = $this->getZoneForLocation($lat, $lng);

        $emergency_info = [
            'zone' => $zone,
            'nearest_lifeguard' => $this->findNearestLifeguard($lat, $lng),
            'response_time' => $this->calculateResponseTime($zone),
            'emergency_contacts' => [
                'lifeguard_station' => '+63-912-345-6789',
                'emergency_services' => '911',
                'pool_manager' => '+63-917-123-4567'
            ],
            'equipment_needed' => $this->getRequiredEquipment($zone),
            'evacuation_route' => $this->getEvacuationRoute($zone)
        ];

        return $emergency_info;
    }

    /**
     * Find nearest lifeguard station
     */
    private function findNearestLifeguard($lat, $lng) {
        $lifeguard_stations = [
            ['name' => 'Main Station', 'lat' => 14.5992, 'lng' => 120.9838],
            ['name' => 'Deep End Station', 'lat' => 14.5998, 'lng' => 120.9848],
            ['name' => 'Kids Area Station', 'lat' => 14.5987, 'lng' => 120.9832]
        ];

        $nearest = null;
        $min_distance = PHP_FLOAT_MAX;

        foreach ($lifeguard_stations as $station) {
            $distance = $this->calculateDistance($lat, $lng, $station['lat'], $station['lng']);
            if ($distance < $min_distance) {
                $min_distance = $distance;
                $nearest = $station;
                $nearest['distance'] = round($distance, 1);
                $nearest['eta'] = round($distance / 2.5, 1); // Assuming 2.5 m/s running speed
            }
        }

        return $nearest;
    }

    /**
     * Calculate response time based on zone
     */
    private function calculateResponseTime($zone) {
        $base_times = [
            'low' => 30,      // seconds
            'medium' => 45,
            'high' => 60,
            'critical' => 90
        ];

        $base_time = $base_times[$zone['risk_level']] ?? 60;

        // Add random factor for realism
        $random_factor = mt_rand(-10, 10);
        return max(15, $base_time + $random_factor);
    }

    /**
     * Get required emergency equipment
     */
    private function getRequiredEquipment($zone) {
        $equipment = [
            'basic' => ['Life Ring', 'First Aid Kit', 'Whistle'],
            'deep' => ['Life Ring', 'Rescue Tube', 'Spine Board', 'Defibrillator'],
            'shallow' => ['Life Ring', 'First Aid Kit', 'Blanket'],
            'very_deep' => ['Life Ring', 'Rescue Tube', 'Diving Equipment', 'Defibrillator']
        ];

        return $equipment[$zone['depth']] ?? $equipment['basic'];
    }

    /**
     * Get evacuation route
     */
    private function getEvacuationRoute($zone) {
        $routes = [
            'main_pool' => 'Exit through main gate, follow blue markers to assembly point A',
            'deep_end' => 'Exit through emergency door, follow red markers to assembly point B',
            'shallow_end' => 'Exit through side gate, follow green markers to assembly point C',
            'kids_pool' => 'Exit through children\'s gate, follow yellow markers to assembly point D'
        ];

        return $routes[$zone['id']] ?? 'Follow nearest exit signs to assembly point';
    }

    /**
     * Track person movement patterns
     */
    public function trackMovementPattern() {
        if (count($this->gps_history) < 5) {
            return ['pattern' => 'insufficient_data'];
        }

        $recent_points = array_slice($this->gps_history, -10);
        $speeds = [];
        $headings = [];

        for ($i = 1; $i < count($recent_points); $i++) {
            $prev = $recent_points[$i - 1];
            $curr = $recent_points[$i];

            $distance = $this->calculateDistance(
                $prev['lat'], $prev['lng'],
                $curr['lat'], $curr['lng']
            );

            $time_diff = $curr['timestamp'] - $prev['timestamp'];
            $speed = $time_diff > 0 ? $distance / $time_diff : 0;

            $speeds[] = $speed;
            $headings[] = $curr['heading'] ?? 0;
        }

        $avg_speed = array_sum($speeds) / count($speeds);
        $speed_variance = $this->calculateVariance($speeds);
        $heading_variance = $this->calculateVariance($headings);

        // Analyze movement pattern
        if ($avg_speed < 0.1) {
            $pattern = 'stationary';
        } elseif ($avg_speed > 1.5 && $speed_variance < 0.5) {
            $pattern = 'steady_swimming';
        } elseif ($speed_variance > 1.0) {
            $pattern = 'erratic_movement';
        } elseif ($heading_variance > 1000) {
            $pattern = 'random_movement';
        } else {
            $pattern = 'normal_movement';
        }

        return [
            'pattern' => $pattern,
            'avg_speed' => round($avg_speed, 2),
            'speed_variance' => round($speed_variance, 2),
            'heading_variance' => round($heading_variance, 2),
            'confidence' => $this->calculatePatternConfidence($pattern, $speeds, $headings)
        ];
    }

    /**
     * Calculate confidence in movement pattern
     */
    private function calculatePatternConfidence($pattern, $speeds, $headings) {
        // Simple confidence calculation based on data consistency
        $speed_consistency = 1 - (count($speeds) > 1 ? $this->calculateVariance($speeds) / max($speeds) : 0);
        $heading_consistency = 1 - (count($headings) > 1 ? $this->calculateVariance($headings) / 360 : 0);

        return round(($speed_consistency + $heading_consistency) / 2 * 100, 1);
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
     * Get ESP device status
     */
    public function getESPStatus() {
        return [
            'devices' => $this->esp_devices,
            'total_devices' => count($this->esp_devices),
            'online_devices' => count(array_filter($this->esp_devices, function($device) {
                return $device['status'] === 'online';
            })),
            'zones' => $this->monitoring_zones,
            'gps_history_points' => count($this->gps_history)
        ];
    }

    /**
     * Log location data
     */
    public function logLocationData($location, $behavior = 'unknown') {
        $zone = $this->getZoneForLocation($location['lat'], $location['lng']);

        $this->db->logDetection(
            true, // motion detected
            true, // human detected
            95.0, // high confidence for GPS
            json_encode([
                'location' => $location,
                'zone' => $zone,
                'behavior' => $behavior,
                'movement_pattern' => $this->trackMovementPattern()
            ]),
            'esp_location_system'
        );
    }

    /**
     * Get all monitoring zones
     */
    public function getMonitoringZones() {
        return $this->monitoring_zones;
    }

    /**
     * Get GPS history
     */
    public function getGPSHistory($limit = 50) {
        return array_slice($this->gps_history, -$limit);
    }
}

// Initialize ESP location system
$esp_location = new ESPLocationSystem();
?>