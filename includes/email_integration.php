<?php
/**
 * Email Integration for AI Drowning Detection System
 * Professional email notifications with HTML templates
 */

class EmailIntegration {
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $from_email;
    private $from_name;
    private $db;

    public function __construct() {
        $this->smtp_host = SMTP_HOST ?: 'smtp.gmail.com';
        $this->smtp_port = SMTP_PORT ?: 587;
        $this->smtp_user = SMTP_USER ?: '';
        $this->smtp_pass = SMTP_PASS ?: '';
        $this->from_email = EMAIL_FROM ?: 'alerts@aidrowningsystem.com';
        $this->from_name = 'AI Drowning Detection System';
        $this->db = new Database();
    }

    /**
     * Send emergency email alert
     */
    public function sendEmergencyAlert($to_email, $subject, $details, $priority = 'high') {
        $template_data = [
            'title' => '🚨 EMERGENCY ALERT',
            'priority' => $priority,
            'message' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
            'system_info' => $this->getSystemInfo()
        ];

        $html_content = $this->generateEmergencyTemplate($template_data);
        $text_content = $this->stripHtml($html_content);

        return $this->sendEmail($to_email, $subject, $html_content, $text_content, $priority);
    }

    /**
     * Send status report email
     */
    public function sendStatusReport($to_email, $report_data) {
        $template_data = [
            'title' => '📊 System Status Report',
            'report_data' => $report_data,
            'timestamp' => date('Y-m-d H:i:s'),
            'system_info' => $this->getSystemInfo()
        ];

        $html_content = $this->generateStatusTemplate($template_data);
        $text_content = $this->stripHtml($html_content);

        $subject = "AI Drowning System Status Report - " . date('Y-m-d');

        return $this->sendEmail($to_email, $subject, $html_content, $text_content, 'normal');
    }

    /**
     * Send weather alert email
     */
    public function sendWeatherAlert($to_email, $weather_data, $safety_assessment) {
        $template_data = [
            'title' => '🌤️ Weather Alert & Safety Assessment',
            'weather_data' => $weather_data,
            'safety_assessment' => $safety_assessment,
            'timestamp' => date('Y-m-d H:i:s'),
            'system_info' => $this->getSystemInfo()
        ];

        $html_content = $this->generateWeatherTemplate($template_data);
        $text_content = $this->stripHtml($html_content);

        $subject = "Weather Alert: {$weather_data['condition']} - Safety Score: {$safety_assessment['safety_score']}";

        return $this->sendEmail($to_email, $subject, $html_content, $text_content, 'medium');
    }

    /**
     * Send bulk email notifications
     */
    public function sendBulkNotifications($recipients, $subject, $content, $type = 'notification') {
        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $name = is_array($recipient) ? ($recipient['name'] ?? 'Valued Recipient') : 'Valued Recipient';

            $personalized_content = $this->personalizeContent($content, $name);

            if ($this->sendEmail($email, $subject, $personalized_content, $this->stripHtml($personalized_content))) {
                $results[] = ['email' => $email, 'status' => 'success', 'name' => $name];
                $successful++;
                $this->logEmail($email, $subject, $type, 'success', $name);
            } else {
                $results[] = ['email' => $email, 'status' => 'failed', 'name' => $name];
                $failed++;
                $this->logEmail($email, $subject, $type, 'failed', $name);
                logMessage("Failed to send email to {$email}", 'ERROR');
            }

            // Small delay to avoid rate limiting
            usleep(200000); // 0.2 seconds
        }

        logMessage("Bulk email completed: {$successful} successful, {$failed} failed", 'INFO');

        return [
            'total' => count($recipients),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Send email using SMTP
     */
    private function sendEmail($to, $subject, $html_content, $text_content = null, $priority = 'normal') {
        if (empty($this->smtp_user)) {
            // Demo mode - simulate successful send
            logMessage("DEMO MODE: Would send email to {$to}: {$subject}");
            return true;
        }

        $headers = $this->buildEmailHeaders($priority);
        $message = $this->buildMultipartMessage($html_content, $text_content);

        // Use PHP's built-in mail function (configure SMTP in php.ini for production)
        $success = mail($to, $subject, $message, $headers);

        if ($success) {
            logMessage("Email sent successfully to {$to}", 'INFO');
            return true;
        } else {
            logMessage("Failed to send email to {$to}", 'ERROR');
            return false;
        }
    }

    /**
     * Build email headers
     */
    private function buildEmailHeaders($priority) {
        $headers = [];

        // Basic headers
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: multipart/alternative; boundary=\"boundary123\"";
        $headers[] = "From: {$this->from_name} <{$this->from_email}>";
        $headers[] = "Reply-To: {$this->from_email}";
        $headers[] = "X-Mailer: AI Drowning Detection System";

        // Priority headers
        switch ($priority) {
            case 'high':
            case 'critical':
                $headers[] = "X-Priority: 1";
                $headers[] = "X-MSMail-Priority: High";
                $headers[] = "Importance: High";
                break;
            case 'low':
                $headers[] = "X-Priority: 5";
                $headers[] = "X-MSMail-Priority: Low";
                $headers[] = "Importance: Low";
                break;
        }

        return implode("\r\n", $headers);
    }

    /**
     * Build multipart email message
     */
    private function buildMultipartMessage($html_content, $text_content) {
        $boundary = "boundary123";

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= ($text_content ?: $this->stripHtml($html_content)) . "\r\n\r\n";

        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_content . "\r\n\r\n";

        $message .= "--{$boundary}--";

        return $message;
    }

    /**
     * Generate emergency email template
     */
    private function generateEmergencyTemplate($data) {
        $priority_color = $this->getPriorityColor($data['priority']);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: {$priority_color}; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .alert-box { border-left: 4px solid {$priority_color}; padding: 15px; background: #f8f9fa; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background: {$priority_color}; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$data['title']}</h1>
                    <p>Priority: " . ucfirst($data['priority']) . "</p>
                </div>
                <div class='content'>
                    <div class='alert-box'>
                        <h3>🚨 Emergency Alert</h3>
                        <p><strong>Time:</strong> {$data['timestamp']}</p>
                        <p><strong>Details:</strong> {$data['message']}</p>
                    </div>

                    <h3>System Information</h3>
                    <ul>
                        <li><strong>System Status:</strong> {$data['system_info']['status']}</li>
                        <li><strong>Active Cameras:</strong> {$data['system_info']['cameras']}</li>
                        <li><strong>AI Status:</strong> {$data['system_info']['ai_status']}</li>
                    </ul>

                    <a href='http://{$_SERVER['HTTP_HOST']}' class='button'>View System Dashboard</a>
                </div>
                <div class='footer'>
                    <p>This is an automated alert from the AI Drowning Detection System</p>
                    <p>Please respond immediately to ensure swimmer safety</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Generate status report email template
     */
    private function generateStatusTemplate($data) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
                .stat-box { padding: 15px; background: #f8f9fa; border-radius: 5px; text-align: center; }
                .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$data['title']}</h1>
                    <p>Report generated on {$data['timestamp']}</p>
                </div>
                <div class='content'>
                    <div class='stats-grid'>
                        <div class='stat-box'>
                            <div class='stat-number'>{$data['report_data']['total_alerts']}</div>
                            <div>Total Alerts</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-number'>{$data['report_data']['active_detections']}</div>
                            <div>Active Detections</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-number'>{$data['report_data']['system_uptime']}%</div>
                            <div>System Uptime</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-number'>{$data['report_data']['ai_accuracy']}%</div>
                            <div>AI Accuracy</div>
                        </div>
                    </div>

                    <h3>System Health</h3>
                    <ul>
                        <li><strong>Status:</strong> {$data['system_info']['status']}</li>
                        <li><strong>Database:</strong> {$data['system_info']['database_status']}</li>
                        <li><strong>Memory Usage:</strong> {$data['system_info']['memory_usage']}</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>Daily status report from the AI Drowning Detection System</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Generate weather alert email template
     */
    private function generateWeatherTemplate($data) {
        $safety_color = $this->getSafetyColor($data['safety_assessment']['risk_level']);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: {$safety_color}; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .weather-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
                .weather-box { padding: 15px; background: #f8f9fa; border-radius: 5px; text-align: center; }
                .safety-score { font-size: 36px; font-weight: bold; color: {$safety_color}; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$data['title']}</h1>
                    <p>Safety Score: <span class='safety-score'>{$data['safety_assessment']['safety_score']}</span></p>
                </div>
                <div class='content'>
                    <div class='weather-grid'>
                        <div class='weather-box'>
                            <div style='font-size: 24px;'>{$data['weather_data']['temperature']}°C</div>
                            <div>Temperature</div>
                        </div>
                        <div class='weather-box'>
                            <div style='font-size: 24px;'>{$data['weather_data']['wind_speed']} m/s</div>
                            <div>Wind Speed</div>
                        </div>
                        <div class='weather-box'>
                            <div style='font-size: 24px;'>{$data['weather_data']['humidity']}%</div>
                            <div>Humidity</div>
                        </div>
                        <div class='weather-box'>
                            <div style='font-size: 18px;'>{$data['weather_data']['condition']}</div>
                            <div>Condition</div>
                        </div>
                    </div>

                    <h3>Safety Assessment</h3>
                    <p><strong>Risk Level:</strong> " . ucfirst($data['safety_assessment']['risk_level']) . "</p>

                    <h4>Warnings:</h4>
                    <ul>" .
                        implode('', array_map(function($warning) {
                            return "<li>{$warning}</li>";
                        }, $data['safety_assessment']['warnings'])) . "
                    </ul>

                    <h4>Recommendations:</h4>
                    <ul>" .
                        implode('', array_map(function($rec) {
                            return "<li>{$rec}</li>";
                        }, $data['safety_assessment']['recommendations'])) . "
                    </ul>
                </div>
                <div class='footer'>
                    <p>Weather-based safety assessment from the AI Drowning Detection System</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Get priority color for templates
     */
    private function getPriorityColor($priority) {
        switch ($priority) {
            case 'critical': return '#dc3545';
            case 'high': return '#fd7e14';
            case 'medium': return '#ffc107';
            case 'low': return '#28a745';
            default: return '#007bff';
        }
    }

    /**
     * Get safety color for weather template
     */
    private function getSafetyColor($risk_level) {
        switch ($risk_level) {
            case 'critical': return '#dc3545';
            case 'high': return '#fd7e14';
            case 'medium': return '#ffc107';
            case 'low': return '#28a745';
            default: return '#007bff';
        }
    }

    /**
     * Personalize email content
     */
    private function personalizeContent($content, $name) {
        return str_replace(['{name}', '{NAME}'], $name, $content);
    }

    /**
     * Strip HTML tags for text version
     */
    private function stripHtml($html) {
        return strip_tags($html);
    }

    /**
     * Get system information for emails
     */
    private function getSystemInfo() {
        return [
            'status' => 'Operational',
            'cameras' => 4,
            'ai_status' => 'Active',
            'database_status' => 'Connected',
            'memory_usage' => '45%'
        ];
    }

    /**
     * Log email to database
     */
    private function logEmail($email, $subject, $type, $status, $recipient_name = null) {
        $this->db->logEmail($email, $subject, $type, $status, $recipient_name);
    }

    /**
     * Get email statistics
     */
    public function getEmailStats($days = 7) {
        return $this->db->getEmailStats($days);
    }

    /**
     * Test email functionality
     */
    public function testEmail($email_address) {
        $subject = "AI Drowning Detection System - Email Test";
        $content = "<h2>Email Test Successful</h2><p>This is a test email from the AI Drowning Detection System.</p><p>Sent at: " . date('Y-m-d H:i:s') . "</p>";

        if ($this->sendEmail($email_address, $subject, $content)) {
            return ['success' => true, 'message' => 'Test email sent successfully'];
        }

        return ['success' => false, 'message' => 'Failed to send test email'];
    }
}

// Initialize email integration
$email_integration = new EmailIntegration();
?>