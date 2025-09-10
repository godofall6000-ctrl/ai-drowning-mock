<?php
/**
 * SMS Integration for AI Drowning Detection System
 * Uses Twilio API for SMS alerts and notifications
 */

class SMSIntegration {
    private $account_sid;
    private $auth_token;
    private $from_number;
    private $db;
    private $api_url = 'https://api.twilio.com/2010-04-01/Accounts/';

    public function __construct() {
        // In production, these would be from environment variables or config
        $this->account_sid = getenv('TWILIO_ACCOUNT_SID') ?: 'demo_sid';
        $this->auth_token = getenv('TWILIO_AUTH_TOKEN') ?: 'demo_token';
        $this->from_number = getenv('TWILIO_FROM_NUMBER') ?: '+1234567890';
        $this->db = new Database();
    }

    /**
     * Send emergency SMS alert
     */
    public function sendEmergencyAlert($phone_number, $message, $priority = 'high') {
        $emergency_message = $this->formatEmergencyMessage($message, $priority);

        $result = $this->sendSMS($phone_number, $emergency_message);

        if ($result) {
            $this->logSMS($phone_number, $emergency_message, 'emergency', $priority);
            logMessage("Emergency SMS sent to {$phone_number}", 'ALERT');
            return true;
        }

        logMessage("Failed to send emergency SMS to {$phone_number}", 'ERROR');
        return false;
    }

    /**
     * Send bulk SMS alerts to multiple recipients
     */
    public function sendBulkAlerts($recipients, $message, $type = 'alert') {
        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $phone = is_array($recipient) ? $recipient['phone'] : $recipient;
            $name = is_array($recipient) ? ($recipient['name'] ?? 'Unknown') : 'Unknown';

            $personalized_message = $this->personalizeMessage($message, $name);

            if ($this->sendSMS($phone, $personalized_message)) {
                $results[] = ['phone' => $phone, 'status' => 'success', 'name' => $name];
                $successful++;
                $this->logSMS($phone, $personalized_message, $type, 'normal', $name);
            } else {
                $results[] = ['phone' => $phone, 'status' => 'failed', 'name' => $name];
                $failed++;
                logMessage("Failed to send SMS to {$phone} ({$name})", 'ERROR');
            }

            // Small delay to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }

        logMessage("Bulk SMS completed: {$successful} successful, {$failed} failed", 'INFO');

        return [
            'total' => count($recipients),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Send SMS using Twilio API
     */
    private function sendSMS($to, $message) {
        if ($this->account_sid === 'demo_sid') {
            // Demo mode - simulate successful send
            logMessage("DEMO MODE: Would send SMS to {$to}: {$message}");
            return true;
        }

        $url = $this->api_url . $this->account_sid . '/Messages.json';

        $data = [
            'From' => $this->from_number,
            'To' => $this->formatPhoneNumber($to),
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->account_sid . ':' . $this->auth_token);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            logMessage("SMS API error: {$error}", 'ERROR');
            return false;
        }

        if ($http_code !== 201) {
            logMessage("SMS API returned HTTP {$http_code}", 'ERROR');
            return false;
        }

        $response_data = json_decode($response, true);
        if (isset($response_data['sid'])) {
            return $response_data['sid']; // Message SID
        }

        return false;
    }

    /**
     * Format phone number for international format
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // Add country code if missing (assuming Philippines +63)
        if (strlen($phone) === 10) {
            $phone = '63' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            $phone = '63' . substr($phone, 1);
        }

        return '+' . $phone;
    }

    /**
     * Format emergency message with priority indicators
     */
    private function formatEmergencyMessage($message, $priority) {
        $prefix = '';

        switch ($priority) {
            case 'critical':
                $prefix = '🚨 CRITICAL EMERGENCY: ';
                break;
            case 'high':
                $prefix = '⚠️ URGENT ALERT: ';
                break;
            case 'medium':
                $prefix = 'ℹ️ ALERT: ';
                break;
            default:
                $prefix = '📢 NOTIFICATION: ';
        }

        // Truncate message if too long for SMS (160 characters is safe limit)
        $full_message = $prefix . $message;
        if (strlen($full_message) > 160) {
            $full_message = substr($full_message, 0, 157) . '...';
        }

        return $full_message;
    }

    /**
     * Personalize message for individual recipients
     */
    private function personalizeMessage($message, $name) {
        if ($name && $name !== 'Unknown') {
            return str_replace(['{name}', '{NAME}'], $name, $message);
        }
        return $message;
    }

    /**
     * Get emergency contact list
     */
    public function getEmergencyContacts() {
        // In a real implementation, this would come from database
        return [
            ['name' => 'Pool Manager', 'phone' => '+639123456789', 'role' => 'manager'],
            ['name' => 'Head Lifeguard', 'phone' => '+639987654321', 'role' => 'lifeguard'],
            ['name' => 'Emergency Services', 'phone' => '+63917100110', 'role' => 'emergency'],
            ['name' => 'Security Office', 'phone' => '+639112345678', 'role' => 'security']
        ];
    }

    /**
     * Send alert to all emergency contacts
     */
    public function alertAllEmergencyContacts($message, $priority = 'high') {
        $contacts = $this->getEmergencyContacts();
        return $this->sendBulkAlerts($contacts, $message, 'emergency');
    }

    /**
     * Send location-based alert to nearest responders
     */
    public function sendLocationBasedAlert($message, $location) {
        // Find contacts within certain radius of the location
        $nearby_contacts = $this->findNearbyContacts($location);

        if (empty($nearby_contacts)) {
            // Fallback to all emergency contacts
            $nearby_contacts = $this->getEmergencyContacts();
        }

        $location_message = $message . " Location: {$location['lat']}, {$location['lng']}";

        return $this->sendBulkAlerts($nearby_contacts, $location_message, 'location_alert');
    }

    /**
     * Find contacts near a specific location
     */
    private function findNearbyContacts($location, $radius_km = 5) {
        $contacts = $this->getEmergencyContacts();
        $nearby = [];

        foreach ($contacts as $contact) {
            // In a real implementation, contacts would have location data
            // For now, we'll assume all contacts are nearby
            if (isset($contact['location'])) {
                $distance = $this->calculateDistance(
                    $location['lat'], $location['lng'],
                    $contact['location']['lat'], $contact['location']['lng']
                );

                if ($distance <= $radius_km) {
                    $contact['distance'] = round($distance, 2);
                    $nearby[] = $contact;
                }
            } else {
                // If no location data, include all contacts
                $nearby[] = $contact;
            }
        }

        return $nearby;
    }

    /**
     * Calculate distance between two points (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earth_radius = 6371; // km

        $lat_delta = deg2rad($lat2 - $lat1);
        $lng_delta = deg2rad($lng2 - $lng1);

        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lng_delta / 2) * sin($lng_delta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius * $c;
    }

    /**
     * Log SMS message to database
     */
    private function logSMS($phone, $message, $type, $priority = 'normal', $recipient_name = null) {
        $this->db->logSMS($phone, $message, $type, $priority, $recipient_name);
    }

    /**
     * Get SMS delivery statistics
     */
    public function getSMSStats($days = 7) {
        return $this->db->getSMSStats($days);
    }

    /**
     * Test SMS functionality
     */
    public function testSMS($phone_number) {
        $test_message = "AI Drowning Detection System - SMS Test Message at " . date('Y-m-d H:i:s');

        if ($this->sendSMS($phone_number, $test_message)) {
            return ['success' => true, 'message' => 'Test SMS sent successfully'];
        }

        return ['success' => false, 'message' => 'Failed to send test SMS'];
    }

    /**
     * Send weather alert SMS
     */
    public function sendWeatherAlert($phone_number, $weather_data) {
        $message = "Weather Alert: {$weather_data['condition']} - {$weather_data['description']}. ";
        $message .= "Temp: {$weather_data['temperature']}°C, Wind: {$weather_data['wind_speed']} m/s. ";
        $message .= "Safety Score: " . $this->calculateWeatherSafetyScore($weather_data);

        return $this->sendSMS($phone_number, $message);
    }

    /**
     * Calculate weather safety score for SMS
     */
    private function calculateWeatherSafetyScore($weather_data) {
        $score = 100;

        if ($weather_data['temperature'] < 15 || $weather_data['temperature'] > 30) $score -= 20;
        if ($weather_data['wind_speed'] > 15) $score -= 25;
        if ($weather_data['visibility'] < 1000) $score -= 20;
        if (strtolower($weather_data['condition']) === 'thunderstorm') $score -= 50;

        $score = max(0, min(100, $score));

        if ($score >= 80) return 'Good';
        if ($score >= 60) return 'Fair';
        if ($score >= 40) return 'Poor';
        return 'Critical';
    }

    /**
     * Send scheduled status updates
     */
    public function sendStatusUpdate($phone_number, $system_status) {
        $message = "System Status Update - " . date('H:i:s') . "\n";
        $message .= "Active Cameras: {$system_status['active_cameras']}\n";
        $message .= "AI Status: {$system_status['ai_status']}\n";
        $message .= "Alerts Today: {$system_status['alerts_today']}\n";
        $message .= "System Health: {$system_status['health']}";

        return $this->sendSMS($phone_number, $message);
    }
}

// Initialize SMS integration
$sms_integration = new SMSIntegration();
?>