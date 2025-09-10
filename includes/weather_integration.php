<?php
/**
 * Real-time Weather Integration for AI Drowning Detection System
 * Integrates with OpenWeatherMap API for environmental monitoring
 */

class WeatherIntegration {
    private $api_key;
    private $base_url = 'https://api.openweathermap.org/data/2.5/';
    private $db;
    private $cache_time = 300; // 5 minutes cache

    public function __construct() {
        $this->api_key = WEATHER_API_KEY ?: 'demo_key'; // Use demo key if not configured
        $this->db = new Database();
    }

    /**
     * Get current weather conditions for location
     */
    public function getCurrentWeather($lat, $lng) {
        // Check cache first
        $cached = $this->getCachedWeather($lat, $lng);
        if ($cached && (time() - strtotime($cached['timestamp'])) < $this->cache_time) {
            return $cached;
        }

        $url = $this->base_url . "weather?lat={$lat}&lon={$lng}&appid={$this->api_key}&units=metric";

        $weather_data = $this->makeApiCall($url);

        if ($weather_data) {
            $processed_data = $this->processWeatherData($weather_data, $lat, $lng);
            $this->cacheWeatherData($processed_data);
            return $processed_data;
        }

        return null;
    }

    /**
     * Get weather forecast for next few hours
     */
    public function getWeatherForecast($lat, $lng, $hours = 12) {
        $url = $this->base_url . "forecast?lat={$lat}&lon={$lng}&appid={$this->api_key}&units=metric";

        $forecast_data = $this->makeApiCall($url);

        if ($forecast_data && isset($forecast_data['list'])) {
            return $this->processForecastData($forecast_data['list'], $hours);
        }

        return [];
    }

    /**
     * Assess swimming safety based on weather conditions
     */
    public function assessSwimmingSafety($weather_data) {
        $safety_score = 100; // Start with perfect score
        $warnings = [];
        $recommendations = [];

        // Temperature assessment
        $temp = $weather_data['temperature'];
        if ($temp < 15) {
            $safety_score -= 30;
            $warnings[] = "Water temperature too cold ({$temp}°C)";
            $recommendations[] = "Consider heated pools or wetsuits";
        } elseif ($temp > 30) {
            $safety_score -= 20;
            $warnings[] = "Water temperature very warm ({$temp}°C)";
            $recommendations[] = "Monitor for heat exhaustion";
        }

        // Wind assessment
        $wind_speed = $weather_data['wind_speed'];
        if ($wind_speed > 20) {
            $safety_score -= 25;
            $warnings[] = "Strong winds ({$wind_speed} m/s)";
            $recommendations[] = "Increased wave activity - use caution";
        }

        // Visibility assessment
        $visibility = $weather_data['visibility'];
        if ($visibility < 1000) {
            $safety_score -= 20;
            $warnings[] = "Poor visibility ({$visibility}m)";
            $recommendations[] = "Limited visual monitoring range";
        }

        // Weather condition assessment
        $condition = strtolower($weather_data['condition']);
        switch ($condition) {
            case 'thunderstorm':
            case 'tornado':
                $safety_score -= 50;
                $warnings[] = "Severe weather: {$weather_data['condition']}";
                $recommendations[] = "EVACUATE IMMEDIATELY - Lightning risk";
                break;
            case 'rain':
                $safety_score -= 15;
                $warnings[] = "Rain reducing visibility";
                $recommendations[] = "Enhanced monitoring required";
                break;
            case 'fog':
            case 'mist':
                $safety_score -= 25;
                $warnings[] = "Fog reducing visibility";
                $recommendations[] = "Use fog horns and enhanced lighting";
                break;
        }

        // UV index assessment
        if (isset($weather_data['uv_index'])) {
            $uv = $weather_data['uv_index'];
            if ($uv > 8) {
                $safety_score -= 15;
                $warnings[] = "Extreme UV index ({$uv})";
                $recommendations[] = "Apply sunscreen and limit exposure";
            }
        }

        return [
            'safety_score' => max(0, min(100, $safety_score)),
            'risk_level' => $this->calculateRiskLevel($safety_score),
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'weather_factors' => [
                'temperature' => $temp,
                'wind_speed' => $wind_speed,
                'visibility' => $visibility,
                'condition' => $condition
            ]
        ];
    }

    /**
     * Get water condition predictions
     */
    public function getWaterConditionPrediction($lat, $lng) {
        $forecast = $this->getWeatherForecast($lat, $lng, 24);

        $predictions = [
            'wave_height_prediction' => $this->predictWaveHeight($forecast),
            'water_temperature_trend' => $this->predictWaterTemperature($forecast),
            'visibility_trend' => $this->predictVisibility($forecast),
            'safety_trend' => $this->predictSafetyTrend($forecast)
        ];

        return $predictions;
    }

    /**
     * Make API call with error handling
     */
    private function makeApiCall($url) {
        if (!function_exists('curl_init')) {
            logMessage("cURL not available for weather API calls", 'ERROR');
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            logMessage("Weather API error: {$error}", 'ERROR');
            return null;
        }

        if ($http_code !== 200) {
            logMessage("Weather API returned HTTP {$http_code}", 'ERROR');
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("Invalid JSON response from weather API", 'ERROR');
            return null;
        }

        return $data;
    }

    /**
     * Process raw weather data
     */
    private function processWeatherData($data, $lat, $lng) {
        return [
            'location' => [
                'lat' => $lat,
                'lng' => $lng,
                'name' => $data['name'] ?? 'Unknown Location'
            ],
            'temperature' => round($data['main']['temp'] ?? 0, 1),
            'feels_like' => round($data['main']['feels_like'] ?? 0, 1),
            'humidity' => $data['main']['humidity'] ?? 0,
            'pressure' => $data['main']['pressure'] ?? 0,
            'wind_speed' => round($data['wind']['speed'] ?? 0, 1),
            'wind_direction' => $data['wind']['deg'] ?? 0,
            'visibility' => $data['visibility'] ?? 10000,
            'condition' => $data['weather'][0]['main'] ?? 'Unknown',
            'description' => $data['weather'][0]['description'] ?? 'Unknown',
            'cloud_cover' => $data['clouds']['all'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s'),
            'sunrise' => isset($data['sys']['sunrise']) ? date('H:i:s', $data['sys']['sunrise']) : null,
            'sunset' => isset($data['sys']['sunset']) ? date('H:i:s', $data['sys']['sunset']) : null
        ];
    }

    /**
     * Process forecast data
     */
    private function processForecastData($forecast_list, $hours) {
        $processed = [];
        $end_time = time() + ($hours * 3600);

        foreach ($forecast_list as $forecast) {
            if ($forecast['dt'] > $end_time) break;

            $processed[] = [
                'timestamp' => date('Y-m-d H:i:s', $forecast['dt']),
                'temperature' => round($forecast['main']['temp'], 1),
                'condition' => $forecast['weather'][0]['main'],
                'wind_speed' => round($forecast['wind']['speed'], 1),
                'humidity' => $forecast['main']['humidity']
            ];
        }

        return $processed;
    }

    /**
     * Calculate risk level from safety score
     */
    private function calculateRiskLevel($score) {
        if ($score >= 80) return 'low';
        if ($score >= 60) return 'medium';
        if ($score >= 40) return 'high';
        return 'critical';
    }

    /**
     * Cache weather data
     */
    private function cacheWeatherData($data) {
        $cache_key = md5($data['location']['lat'] . ',' . $data['location']['lng']);

        // In a real implementation, you'd use Redis or similar
        // For now, we'll store in session
        $_SESSION['weather_cache'][$cache_key] = $data;
    }

    /**
     * Get cached weather data
     */
    private function getCachedWeather($lat, $lng) {
        $cache_key = md5($lat . ',' . $lng);
        return $_SESSION['weather_cache'][$cache_key] ?? null;
    }

    /**
     * Predict wave height based on forecast
     */
    private function predictWaveHeight($forecast) {
        // Simplified wave height prediction based on wind
        $avg_wind = array_sum(array_column($forecast, 'wind_speed')) / count($forecast);

        if ($avg_wind < 5) return 'calm (0-0.5m)';
        if ($avg_wind < 10) return 'moderate (0.5-1.5m)';
        if ($avg_wind < 15) return 'rough (1.5-2.5m)';
        return 'very rough (2.5m+)';
    }

    /**
     * Predict water temperature trend
     */
    private function predictWaterTemperature($forecast) {
        if (empty($forecast)) return 'stable';

        $first_temp = $forecast[0]['temperature'];
        $last_temp = end($forecast)['temperature'];

        $diff = $last_temp - $first_temp;

        if ($diff > 2) return 'warming';
        if ($diff < -2) return 'cooling';
        return 'stable';
    }

    /**
     * Predict visibility trend
     */
    private function predictVisibility($forecast) {
        // This would be more complex in reality
        return 'stable'; // Placeholder
    }

    /**
     * Predict safety trend
     */
    private function predictSafetyTrend($forecast) {
        // Analyze forecast for safety trends
        $wind_trend = array_column($forecast, 'wind_speed');
        $avg_wind = array_sum($wind_trend) / count($wind_trend);

        if ($avg_wind > 15) return 'deteriorating';
        if ($avg_wind < 5) return 'improving';
        return 'stable';
    }

    /**
     * Get weather-based emergency protocols
     */
    public function getEmergencyProtocols($weather_data) {
        $protocols = [];

        $condition = strtolower($weather_data['condition']);
        $wind_speed = $weather_data['wind_speed'];
        $visibility = $weather_data['visibility'];

        if (in_array($condition, ['thunderstorm', 'tornado', 'hurricane'])) {
            $protocols[] = 'IMMEDIATE EVACUATION - Seek shelter immediately';
            $protocols[] = 'Stop all water activities';
            $protocols[] = 'Move to designated storm shelters';
        }

        if ($wind_speed > 20) {
            $protocols[] = 'Secure loose equipment and umbrellas';
            $protocols[] = 'Monitor for falling objects';
        }

        if ($visibility < 500) {
            $protocols[] = 'Activate fog horns and warning signals';
            $protocols[] = 'Use spotlights for enhanced visibility';
            $protocols[] = 'Reduce swimming area boundaries';
        }

        if ($weather_data['temperature'] < 10) {
            $protocols[] = 'Monitor for hypothermia symptoms';
            $protocols[] = 'Have warming blankets ready';
        }

        return $protocols;
    }
}

// Initialize weather integration
$weather_integration = new WeatherIntegration();
?>