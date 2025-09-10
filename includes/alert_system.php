<?php
/**
 * Alert System for AI Drowning Detection System
 * Handles various types of alerts and notifications
 */

class AlertSystem {
    private $db;
    private $alert_history = [];
    private $cooldown_period = 30; // seconds between similar alerts

    public function __construct() {
        $this->db = $GLOBALS['db'];
    }

    /**
     * Trigger a drowning alert
     */
    public function triggerDrowningAlert($details = '', $severity = 'high') {
        // Check cooldown to prevent alert spam
        if ($this->isOnCooldown('drowning', $this->cooldown_period)) {
            logMessage("Drowning alert suppressed due to cooldown");
            return false;
        }

        $alert_data = [
            'type' => 'DROWNING_ALERT',
            'details' => $details ?: 'Potential drowning detected - immediate attention required',
            'severity' => $severity,
            'timestamp' => time()
        ];

        // Log to database
        $alert_id = $this->db->logAlert($alert_data['type'], $alert_data['details'], $alert_data['severity']);

        if ($alert_id) {
            // Execute alert actions
            $this->executeAlertActions($alert_data);

            // Add to history
            $this->alert_history[] = $alert_data;

            logMessage("Drowning alert triggered: " . $alert_data['details'], 'ALERT');

            return $alert_id;
        }

        return false;
    }

    /**
     * Trigger a motion alert
     */
    public function triggerMotionAlert($details = '', $severity = 'medium') {
        if ($this->isOnCooldown('motion', 10)) {
            return false;
        }

        $alert_data = [
            'type' => 'MOTION_ALERT',
            'details' => $details ?: 'Unusual motion pattern detected',
            'severity' => $severity,
            'timestamp' => time()
        ];

        $alert_id = $this->db->logAlert($alert_data['type'], $alert_data['details'], $alert_data['severity']);

        if ($alert_id) {
            $this->executeAlertActions($alert_data);
            $this->alert_history[] = $alert_data;

            logMessage("Motion alert triggered: " . $alert_data['details'], 'ALERT');

            return $alert_id;
        }

        return false;
    }

    /**
     * Trigger a system alert
     */
    public function triggerSystemAlert($message, $severity = 'low') {
        $alert_data = [
            'type' => 'SYSTEM_ALERT',
            'details' => $message,
            'severity' => $severity,
            'timestamp' => time()
        ];

        $alert_id = $this->db->logAlert($alert_data['type'], $alert_data['details'], $alert_data['severity']);

        if ($alert_id) {
            $this->executeAlertActions($alert_data);
            $this->alert_history[] = $alert_data;

            logMessage("System alert: " . $message, 'ALERT');

            return $alert_id;
        }

        return false;
    }

    /**
     * Execute alert actions based on severity and type
     */
    private function executeAlertActions($alert_data) {
        $actions = $this->getAlertActions($alert_data);

        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'sound':
                    $this->playSoundAlert($action['params']);
                    break;

                case 'email':
                    $this->sendEmailAlert($alert_data, $action['params']);
                    break;

                case 'log':
                    $this->logAlertToFile($alert_data, $action['params']);
                    break;

                case 'webhook':
                    $this->sendWebhookAlert($alert_data, $action['params']);
                    break;

                case 'sms':
                    $this->sendSMSAlert($alert_data, $action['params']);
                    break;
            }
        }
    }

    /**
     * Get alert actions based on alert type and severity
     */
    private function getAlertActions($alert_data) {
        $actions = [];

        switch ($alert_data['type']) {
            case 'DROWNING_ALERT':
                $actions[] = ['type' => 'sound', 'params' => ['frequency' => 800, 'duration' => 1000]];
                $actions[] = ['type' => 'log', 'params' => ['file' => 'drowning_alerts.log']];

                if (EMAIL_ALERTS && $alert_data['severity'] === 'high') {
                    $actions[] = ['type' => 'email', 'params' => ['urgent' => true]];
                }
                break;

            case 'MOTION_ALERT':
                $actions[] = ['type' => 'sound', 'params' => ['frequency' => 600, 'duration' => 500]];
                $actions[] = ['type' => 'log', 'params' => ['file' => 'motion_alerts.log']];
                break;

            case 'SYSTEM_ALERT':
                $actions[] = ['type' => 'log', 'params' => ['file' => 'system_alerts.log']];
                break;
        }

        return $actions;
    }

    /**
     * Play sound alert (generates audio file for web playback)
     */
    private function playSoundAlert($params) {
        $frequency = $params['frequency'] ?? 800;
        $duration = $params['duration'] ?? 1000;

        // Generate simple audio file (WAV format)
        $audio_file = $this->generateAudioFile($frequency, $duration);

        if ($audio_file) {
            // Store audio file path for web interface
            $_SESSION['alert_audio'] = $audio_file;

            logMessage("Sound alert generated: {$audio_file}");
        }
    }

    /**
     * Generate simple audio file for alert
     */
    private function generateAudioFile($frequency, $duration_ms) {
        // For web-based alerts, we'll use HTML5 audio instead of file generation
        // This is a simplified approach

        $audio_id = 'alert_' . time() . '_' . rand(1000, 9999);
        $audio_file = "alerts/{$audio_id}.wav";

        // In a real implementation, you would generate actual WAV file
        // For now, we'll just return the path for HTML5 audio

        return $audio_file;
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert($alert_data, $params) {
        if (!EMAIL_ALERTS) return;

        $subject = "AI Drowning Detection Alert: " . $alert_data['type'];
        $message = "
        Alert Type: {$alert_data['type']}
        Severity: {$alert_data['severity']}
        Time: " . date('Y-m-d H:i:s', $alert_data['timestamp']) . "
        Details: {$alert_data['details']}

        Please check the system immediately.
        ";

        $headers = "From: " . EMAIL_FROM . "\r\n";
        $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        if (mail(EMAIL_TO, $subject, $message, $headers)) {
            logMessage("Email alert sent to: " . EMAIL_TO);
        } else {
            logMessage("Failed to send email alert", 'ERROR');
        }
    }

    /**
     * Log alert to file
     */
    private function logAlertToFile($alert_data, $params) {
        $log_file = DATA_DIR . ($params['file'] ?? 'alerts.log');

        $log_entry = sprintf(
            "[%s] %s - %s: %s\n",
            date('Y-m-d H:i:s', $alert_data['timestamp']),
            $alert_data['type'],
            $alert_data['severity'],
            $alert_data['details']
        );

        if (file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX)) {
            logMessage("Alert logged to file: {$log_file}");
        } else {
            logMessage("Failed to log alert to file", 'ERROR');
        }
    }

    /**
     * Send webhook alert
     */
    private function sendWebhookAlert($alert_data, $params) {
        $webhook_url = $params['url'] ?? '';

        if (empty($webhook_url)) return;

        $payload = json_encode([
            'alert_type' => $alert_data['type'],
            'severity' => $alert_data['severity'],
            'timestamp' => $alert_data['timestamp'],
            'details' => $alert_data['details'],
            'system' => SYSTEM_NAME
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $payload
            ]
        ]);

        $result = file_get_contents($webhook_url, false, $context);

        if ($result !== false) {
            logMessage("Webhook alert sent to: {$webhook_url}");
        } else {
            logMessage("Failed to send webhook alert", 'ERROR');
        }
    }

    /**
     * Send SMS alert (placeholder - would need SMS service integration)
     */
    private function sendSMSAlert($alert_data, $params) {
        // Placeholder for SMS integration
        // Would need to integrate with SMS service like Twilio
        logMessage("SMS alert not implemented yet", 'WARNING');
    }

    /**
     * Check if alert type is on cooldown
     */
    private function isOnCooldown($alert_type, $cooldown_seconds) {
        foreach ($this->alert_history as $alert) {
            if ($alert['type'] === strtoupper($alert_type) &&
                (time() - $alert['timestamp']) < $cooldown_seconds) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get alert statistics
     */
    public function getAlertStats($hours = 24) {
        return $this->db->getAlertSummary($hours / 24);
    }

    /**
     * Clear alert history
     */
    public function clearHistory() {
        $this->alert_history = [];
    }

    /**
     * Generate HTML5 audio element for alerts
     */
    public function getAudioElement() {
        if (isset($_SESSION['alert_audio'])) {
            $audio_file = $_SESSION['alert_audio'];
            unset($_SESSION['alert_audio']);

            return "<audio autoplay><source src='{$audio_file}' type='audio/wav'></audio>";
        }

        return '';
    }

    /**
     * Create JavaScript alert sound
     */
    public function getJavaScriptAlert() {
        return "
        <script>
        function playAlertSound(frequency = 800, duration = 1000) {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
                oscillator.type = 'sine';

                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration / 1000);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + duration / 1000);
            } catch (e) {
                console.log('Web Audio API not supported');
            }
        }
        </script>
        ";
    }
}

// Initialize alert system
$alert_system = new AlertSystem();
?>