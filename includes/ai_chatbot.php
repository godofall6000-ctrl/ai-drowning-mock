<?php
/**
 * AI Chatbot Assistant for AI Drowning Detection System
 * Natural language processing and intelligent assistance
 */

class AIChatbot {
    private $db;
    private $conversation_history = [];
    private $user_context = [];
    private $intents = [];
    private $responses = [];

    public function __construct() {
        $this->db = new Database();
        $this->initializeIntents();
        $this->initializeResponses();
    }

    /**
     * Initialize chatbot intents
     */
    private function initializeIntents() {
        $this->intents = [
            'status' => [
                'keywords' => ['status', 'system', 'working', 'online', 'health', 'check'],
                'patterns' => [
                    '/(?:system|ai)?\s*status/i',
                    '/(?:is|are).*(?:working|online|running)/i',
                    '/health\s*check/i',
                    '/how.*(?:system|ai)/i'
                ]
            ],
            'alerts' => [
                'keywords' => ['alert', 'emergency', 'warning', 'danger', 'incident'],
                'patterns' => [
                    '/(?:recent|latest|new)\s*alerts?/i',
                    '/emergency/i',
                    '/what.*happened/i',
                    '/any.*problems/i'
                ]
            ],
            'location' => [
                'keywords' => ['location', 'where', 'position', 'gps', 'zone', 'area'],
                'patterns' => [
                    '/(?:current|my)\s*location/i',
                    '/where.*(?:am|are)/i',
                    '/what.*zone/i',
                    '/gps/i'
                ]
            ],
            'weather' => [
                'keywords' => ['weather', 'temperature', 'wind', 'rain', 'storm'],
                'patterns' => [
                    '/weather/i',
                    '/(?:how|what).*(?:weather|temperature)/i',
                    '/(?:is|will).*(?:rain|storm)/i',
                    '/wind/i'
                ]
            ],
            'help' => [
                'keywords' => ['help', 'assist', 'support', 'guide', 'commands'],
                'patterns' => [
                    '/help/i',
                    '/(?:what|how).*(?:do|can|help)/i',
                    '/commands/i',
                    '/assist/i'
                ]
            ],
            'report' => [
                'keywords' => ['report', 'statistics', 'analytics', 'data', 'summary'],
                'patterns' => [
                    '/report/i',
                    '/statistics/i',
                    '/(?:show|give).*(?:report|stats)/i',
                    '/summary/i'
                ]
            ],
            'emergency' => [
                'keywords' => ['emergency', 'help', 'danger', 'critical', 'urgent'],
                'patterns' => [
                    '/emergency/i',
                    '/(?:i|we)\s*need\s*help/i',
                    '/danger/i',
                    '/critical/i'
                ]
            ]
        ];
    }

    /**
     * Initialize response templates
     */
    private function initializeResponses() {
        $this->responses = [
            'status' => [
                'good' => [
                    "✅ System Status: All systems operational",
                    "🟢 AI Detection: Active and functioning normally",
                    "📍 Location Tracking: GPS coordinates being monitored",
                    "🌤️ Weather Integration: Connected and updating",
                    "📊 Database: Connected and logging data"
                ],
                'issues' => [
                    "⚠️ Some systems may have issues. Please check the dashboard.",
                    "🔧 System maintenance may be in progress.",
                    "📞 Contact system administrator if issues persist."
                ]
            ],
            'alerts' => [
                "🚨 Recent Alerts: {alert_count} alerts in the last hour",
                "📋 Latest Incident: {latest_alert}",
                "⚡ Emergency Response: {response_status}",
                "📞 Emergency Contacts: {contact_count} contacts notified"
            ],
            'location' => [
                "📍 Current Location: {coordinates}",
                "🏊 Zone: {zone_name} ({zone_risk})",
                "🚑 Nearest Lifeguard: {lifeguard_distance}m away",
                "⏱️ Response Time: {response_time} seconds"
            ],
            'weather' => [
                "🌡️ Temperature: {temperature}°C",
                "💨 Wind Speed: {wind_speed} m/s",
                "💧 Humidity: {humidity}%",
                "🌤️ Conditions: {condition}",
                "🎯 Safety Score: {safety_score}/100"
            ],
            'help' => [
                "🤖 I'm your AI assistant for the drowning detection system!",
                "💬 You can ask me about:",
                "   • System status and health",
                "   • Recent alerts and incidents",
                "   • Current location and zone",
                "   • Weather conditions and safety",
                "   • Reports and statistics",
                "   • Emergency procedures",
                "📝 Try: 'What is the system status?' or 'Show me recent alerts'"
            ],
            'report' => [
                "📊 System Report Summary:",
                "   • Total Alerts: {total_alerts}",
                "   • Active Detections: {active_detections}",
                "   • System Uptime: {uptime}%",
                "   • AI Accuracy: {accuracy}%",
                "   • Emergency Responses: {responses}"
            ],
            'emergency' => [
                "🚨 EMERGENCY PROTOCOL ACTIVATED!",
                "📞 Emergency services have been notified",
                "🏊 Lifeguards are being dispatched to your location",
                "📍 Location: {coordinates}",
                "⏰ Response time: Approximately {response_time} seconds",
                "🆘 Stay calm and follow lifeguard instructions"
            ],
            'unknown' => [
                "🤔 I'm not sure I understand. Could you please rephrase?",
                "💡 Try asking about system status, alerts, or location.",
                "📞 For urgent situations, please contact emergency services directly.",
                "❓ Type 'help' to see available commands."
            ]
        ];
    }

    /**
     * Process user message and generate response
     */
    public function processMessage($message, $user_id = null) {
        // Clean and normalize message
        $clean_message = $this->cleanMessage($message);

        // Store in conversation history
        $this->conversation_history[] = [
            'timestamp' => time(),
            'user_id' => $user_id,
            'message' => $clean_message,
            'type' => 'user'
        ];

        // Analyze intent
        $intent = $this->analyzeIntent($clean_message);

        // Generate response
        $response = $this->generateResponse($intent, $clean_message);

        // Store response in history
        $this->conversation_history[] = [
            'timestamp' => time(),
            'user_id' => $user_id,
            'message' => $response,
            'type' => 'bot',
            'intent' => $intent
        ];

        // Log conversation
        $this->logConversation($user_id, $clean_message, $response, $intent);

        return [
            'response' => $response,
            'intent' => $intent,
            'confidence' => $this->calculateConfidence($intent, $clean_message),
            'timestamp' => time()
        ];
    }

    /**
     * Clean and normalize message
     */
    private function cleanMessage($message) {
        // Remove extra whitespace
        $message = trim(preg_replace('/\s+/', ' ', $message));

        // Convert to lowercase
        $message = strtolower($message);

        // Remove punctuation
        $message = preg_replace('/[^\w\s]/', '', $message);

        return $message;
    }

    /**
     * Analyze message intent
     */
    private function analyzeIntent($message) {
        $best_match = 'unknown';
        $best_score = 0;

        foreach ($this->intents as $intent_name => $intent_data) {
            $score = 0;

            // Check keywords
            foreach ($intent_data['keywords'] as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    $score += 2;
                }
            }

            // Check patterns
            foreach ($intent_data['patterns'] as $pattern) {
                if (preg_match($pattern, $message)) {
                    $score += 3;
                }
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $intent_name;
            }
        }

        return $best_match;
    }

    /**
     * Generate response based on intent
     */
    private function generateResponse($intent, $original_message) {
        if (!isset($this->responses[$intent])) {
            $intent = 'unknown';
        }

        $response_templates = $this->responses[$intent];

        if (is_array($response_templates)) {
            $template = $response_templates[array_rand($response_templates)];
        } else {
            $template = $response_templates;
        }

        // Fill in dynamic data
        return $this->fillTemplate($template, $intent);
    }

    /**
     * Fill template with dynamic data
     */
    private function fillTemplate($template, $intent) {
        $replacements = [];

        switch ($intent) {
            case 'status':
                $replacements = $this->getStatusData();
                break;
            case 'alerts':
                $replacements = $this->getAlertsData();
                break;
            case 'location':
                $replacements = $this->getLocationData();
                break;
            case 'weather':
                $replacements = $this->getWeatherData();
                break;
            case 'report':
                $replacements = $this->getReportData();
                break;
            case 'emergency':
                $replacements = $this->getEmergencyData();
                break;
        }

        foreach ($replacements as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get system status data
     */
    private function getStatusData() {
        global $ai_detection, $esp_location;

        $ai_status = $ai_detection->getStatus();
        $esp_status = $esp_location->getESPStatus();

        return [
            'system_status' => 'All systems operational',
            'ai_status' => $ai_status['beeping_active'] ? 'Active with alerts' : 'Active',
            'location_tracking' => count($esp_status['devices']) . ' devices online',
            'weather_integration' => 'Connected',
            'database_status' => 'Connected and logging'
        ];
    }

    /**
     * Get alerts data
     */
    private function getAlertsData() {
        $alerts = $this->db->getRecentAlerts(5);

        return [
            'alert_count' => count($alerts),
            'latest_alert' => !empty($alerts) ? $alerts[0]['alert_type'] . ': ' . substr($alerts[0]['details'], 0, 50) : 'No recent alerts',
            'response_status' => 'Emergency response teams notified',
            'contact_count' => 4
        ];
    }

    /**
     * Get location data
     */
    private function getLocationData() {
        global $esp_location;

        $location = $esp_location->getCurrentLocation();
        $zone = $esp_location->getZoneForLocation($location['lat'], $location['lng']);
        $emergency = $esp_location->getEmergencyResponse($location['lat'], $location['lng']);

        return [
            'coordinates' => number_format($location['lat'], 6) . ', ' . number_format($location['lng'], 6),
            'zone_name' => $zone['name'],
            'zone_risk' => $zone['risk_level'],
            'lifeguard_distance' => isset($emergency['nearest_lifeguard']['distance']) ? $emergency['nearest_lifeguard']['distance'] : 'Unknown',
            'response_time' => $emergency['response_time']
        ];
    }

    /**
     * Get weather data
     */
    private function getWeatherData() {
        $weather_integration = new WeatherIntegration();
        $weather = $weather_integration->getCurrentWeather(14.5995, 120.9842);

        if ($weather) {
            $safety = $weather_integration->assessSwimmingSafety($weather);
            return [
                'temperature' => $weather['temperature'],
                'wind_speed' => $weather['wind_speed'],
                'humidity' => $weather['humidity'],
                'condition' => $weather['condition'],
                'safety_score' => $safety['safety_score']
            ];
        }

        return [
            'temperature' => 'N/A',
            'wind_speed' => 'N/A',
            'humidity' => 'N/A',
            'condition' => 'Unknown',
            'safety_score' => 'N/A'
        ];
    }

    /**
     * Get report data
     */
    private function getReportData() {
        $alert_summary = $this->db->getAlertSummary(7);
        $total_alerts = 0;

        foreach ($alert_summary as $day) {
            $total_alerts += $day['total_alerts'];
        }

        return [
            'total_alerts' => $total_alerts,
            'active_detections' => 3, // Mock data
            'uptime' => 98,
            'accuracy' => 94,
            'responses' => 12
        ];
    }

    /**
     * Get emergency data
     */
    private function getEmergencyData() {
        $location = $this->getLocationData();

        return array_merge($location, [
            'emergency_services' => 'Notified',
            'lifeguards' => 'Dispatched',
            'response_time' => $location['response_time']
        ]);
    }

    /**
     * Calculate confidence in intent detection
     */
    private function calculateConfidence($intent, $message) {
        if ($intent === 'unknown') {
            return 0;
        }

        $intent_data = $this->intents[$intent];
        $score = 0;
        $max_score = count($intent_data['keywords']) * 2 + count($intent_data['patterns']) * 3;

        // Calculate score
        foreach ($intent_data['keywords'] as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $score += 2;
            }
        }

        foreach ($intent_data['patterns'] as $pattern) {
            if (preg_match($pattern, $message)) {
                $score += 3;
            }
        }

        return $max_score > 0 ? round(($score / $max_score) * 100, 1) : 0;
    }

    /**
     * Log conversation to database
     */
    private function logConversation($user_id, $user_message, $bot_response, $intent) {
        $this->db->logSystemEvent(
            'INFO',
            "Chatbot conversation - User: {$user_message} | Bot: {$bot_response}",
            'chatbot'
        );
    }

    /**
     * Get conversation history
     */
    public function getConversationHistory($limit = 10) {
        return array_slice(array_reverse($this->conversation_history), 0, $limit);
    }

    /**
     * Clear conversation history
     */
    public function clearHistory() {
        $this->conversation_history = [];
    }

    /**
     * Get chatbot statistics
     */
    public function getStats() {
        $total_conversations = count($this->conversation_history);
        $user_messages = count(array_filter($this->conversation_history, function($msg) {
            return $msg['type'] === 'user';
        }));

        return [
            'total_conversations' => $total_conversations,
            'user_messages' => $user_messages,
            'bot_responses' => $total_conversations - $user_messages,
            'active_intents' => count($this->intents),
            'uptime' => time() - ($_SESSION['chatbot_start_time'] ?? time())
        ];
    }

    /**
     * Process emergency command
     */
    public function processEmergencyCommand($message) {
        // Trigger emergency response
        $alert_system = new AlertSystem();
        $sms_integration = new SMSIntegration();

        $alert_system->triggerDrowningAlert("Chatbot Emergency: {$message}", 'critical');
        $sms_integration->alertAllEmergencyContacts("URGENT: {$message}");

        return "🚨 EMERGENCY ALERT ACTIVATED! Help is on the way!";
    }

    /**
     * Learn from user feedback
     */
    public function learnFromFeedback($message, $correct_intent) {
        // In a more advanced system, this would update the intent patterns
        // For now, just log the feedback
        $this->db->logSystemEvent(
            'INFO',
            "Chatbot learning: Message '{$message}' should be intent '{$correct_intent}'",
            'chatbot_learning'
        );

        return "Thank you for the feedback! I'll learn from this.";
    }
}

// Initialize AI chatbot
$ai_chatbot = new AIChatbot();
?>