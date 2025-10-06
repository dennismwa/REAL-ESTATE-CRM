<?php
// app/services/WhatsAppService.php

class WhatsAppService {
    private $apiUrl;
    private $apiToken;
    private $phoneNumberId;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // WhatsApp Business API credentials (Meta/Facebook)
        $this->apiUrl = 'https://graph.facebook.com/v17.0/';
        $this->apiToken = getenv('WHATSAPP_API_TOKEN'); // Store in environment
        $this->phoneNumberId = getenv('WHATSAPP_PHONE_ID');
    }
    
    /**
     * Send WhatsApp message
     */
    public function sendMessage($to, $message, $type = 'text', $mediaUrl = null) {
        // Format phone number (remove + and spaces)
        $to = preg_replace('/[^0-9]/', '', $to);
        
        // Ensure it starts with country code
        if (substr($to, 0, 3) !== '254') {
            $to = '254' . ltrim($to, '0');
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $type
        ];
        
        switch ($type) {
            case 'text':
                $data['text'] = ['body' => $message];
                break;
                
            case 'image':
                $data['image'] = [
                    'link' => $mediaUrl,
                    'caption' => $message
                ];
                break;
                
            case 'document':
                $data['document'] = [
                    'link' => $mediaUrl,
                    'caption' => $message
                ];
                break;
                
            case 'template':
                $data['template'] = [
                    'name' => $message,
                    'language' => ['code' => 'en']
                ];
                break;
        }
        
        $response = $this->makeApiCall('POST', $this->phoneNumberId . '/messages', $data);
        
        // Log the message
        $this->logMessage($to, $message, $type, $response);
        
        return $response;
    }
    
    /**
     * Send template message with parameters
     */
    public function sendTemplate($to, $templateName, $parameters = [], $language = 'en') {
        $to = preg_replace('/[^0-9]/', '', $to);
        if (substr($to, 0, 3) !== '254') {
            $to = '254' . ltrim($to, '0');
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => []
            ]
        ];
        
        if (!empty($parameters)) {
            $data['template']['components'][] = [
                'type' => 'body',
                'parameters' => array_map(function($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters)
            ];
        }
        
        return $this->makeApiCall('POST', $this->phoneNumberId . '/messages', $data);
    }
    
    /**
     * Send interactive message with buttons
     */
    public function sendInteractiveButtons($to, $bodyText, $buttons) {
        $to = preg_replace('/[^0-9]/', '', $to);
        if (substr($to, 0, 3) !== '254') {
            $to = '254' . ltrim($to, '0');
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => [
                    'buttons' => array_map(function($button) {
                        return [
                            'type' => 'reply',
                            'reply' => [
                                'id' => $button['id'],
                                'title' => substr($button['title'], 0, 20) // Max 20 chars
                            ]
                        ];
                    }, array_slice($buttons, 0, 3)) // Max 3 buttons
                ]
            ]
        ];
        
        return $this->makeApiCall('POST', $this->phoneNumberId . '/messages', $data);
    }
    
    /**
     * Send list message
     */
    public function sendList($to, $bodyText, $buttonText, $sections) {
        $to = preg_replace('/[^0-9]/', '', $to);
        if (substr($to, 0, 3) !== '254') {
            $to = '254' . ltrim($to, '0');
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'action' => [
                    'button' => substr($buttonText, 0, 20),
                    'sections' => $sections
                ]
            ]
        ];
        
        return $this->makeApiCall('POST', $this->phoneNumberId . '/messages', $data);
    }
    
    /**
     * Send location message
     */
    public function sendLocation($to, $latitude, $longitude, $name, $address) {
        $to = preg_replace('/[^0-9]/', '', $to);
        if (substr($to, 0, 3) !== '254') {
            $to = '254' . ltrim($to, '0');
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'location',
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address
            ]
        ];
        
        return $this->makeApiCall('POST', $this->phoneNumberId . '/messages', $data);
    }
    
    /**
     * Process incoming webhook
     */
    public function processWebhook($data) {
        if (!isset($data['entry'][0]['changes'][0]['value'])) {
            return false;
        }
        
        $value = $data['entry'][0]['changes'][0]['value'];
        
        // Handle different webhook events
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->processIncomingMessage($message);
            }
        }
        
        if (isset($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->processStatusUpdate($status);
            }
        }
        
        return true;
    }
    
    /**
     * Process incoming message
     */
    private function processIncomingMessage($message) {
        $from = $message['from'];
        $messageId = $message['id'];
        $timestamp = $message['timestamp'];
        
        // Check if message already processed
        $stmt = $this->pdo->prepare("SELECT id FROM whatsapp_messages WHERE message_id = ?");
        $stmt->execute([$messageId]);
        if ($stmt->fetch()) {
            return;
        }
        
        $messageData = [
            'message_id' => $messageId,
            'from_number' => $from,
            'direction' => 'inbound',
            'timestamp' => date('Y-m-d H:i:s', $timestamp)
        ];
        
        // Handle different message types
        if (isset($message['text'])) {
            $messageData['type'] = 'text';
            $messageData['content'] = $message['text']['body'];
            
            // Process with chatbot
            $this->processChatbotMessage($from, $message['text']['body']);
            
        } elseif (isset($message['button'])) {
            $messageData['type'] = 'button_reply';
            $messageData['content'] = $message['button']['text'];
            $messageData['payload'] = $message['button']['payload'];
            
        } elseif (isset($message['interactive'])) {
            $messageData['type'] = 'interactive_reply';
            if ($message['interactive']['type'] === 'list_reply') {
                $messageData['content'] = $message['interactive']['list_reply']['title'];
                $messageData['payload'] = $message['interactive']['list_reply']['id'];
            }
        }
        
        // Save message to database
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_messages 
            (message_id, from_number, to_number, type, content, payload, direction, timestamp, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'received')
        ");
        
        $stmt->execute([
            $messageData['message_id'],
            $messageData['from_number'],
            $this->phoneNumberId,
            $messageData['type'] ?? 'unknown',
            $messageData['content'] ?? '',
            $messageData['payload'] ?? null,
            $messageData['direction'],
            $messageData['timestamp']
        ]);
        
        // Mark message as read
        $this->markAsRead($messageId);
    }
    
    /**
     * Process with chatbot
     */
    private function processChatbotMessage($from, $message) {
        $message = strtolower(trim($message));
        
        // Check if it's a greeting
        if (in_array($message, ['hi', 'hello', 'hey', 'jambo', 'habari'])) {
            $this->sendWelcomeMessage($from);
            return;
        }
        
        // Check for specific keywords
        if (strpos($message, 'plot') !== false || strpos($message, 'land') !== false) {
            $this->sendAvailablePlots($from);
            return;
        }
        
        if (strpos($message, 'price') !== false || strpos($message, 'cost') !== false) {
            $this->sendPriceList($from);
            return;
        }
        
        if (strpos($message, 'visit') !== false || strpos($message, 'tour') !== false) {
            $this->sendSiteVisitOptions($from);
            return;
        }
        
        if (strpos($message, 'pay') !== false || strpos($message, 'balance') !== false) {
            $this->checkPaymentStatus($from);
            return;
        }
        
        // Default response
        $this->sendDefaultMenu($from);
    }
    
    /**
     * Send welcome message
     */
    private function sendWelcomeMessage($to) {
        $message = "🏠 Welcome to Zuri Real Estate!\n\n";
        $message .= "I'm your virtual assistant. How can I help you today?";
        
        $buttons = [
            ['id' => 'view_plots', 'title' => '🏗️ View Plots'],
            ['id' => 'book_visit', 'title' => '📅 Book Site Visit'],
            ['id' => 'speak_agent', 'title' => '👤 Speak to Agent']
        ];
        
        $this->sendInteractiveButtons($to, $message, $buttons);
    }
    
    /**
     * Send available plots
     */
    private function sendAvailablePlots($to) {
        // Get available plots from database
        $stmt = $this->pdo->query("
            SELECT p.*, pr.project_name, pr.location 
            FROM plots p 
            JOIN projects pr ON p.project_id = pr.id 
            WHERE p.status = 'available' 
            LIMIT 10
        ");
        $plots = $stmt->fetchAll();
        
        if (empty($plots)) {
            $this->sendMessage($to, "Sorry, no plots are currently available. Please check back later or speak to our agent.");
            return;
        }
        
        $sections = [
            [
                'title' => 'Available Plots',
                'rows' => array_map(function($plot) {
                    return [
                        'id' => 'plot_' . $plot['id'],
                        'title' => $plot['plot_number'],
                        'description' => $plot['project_name'] . ' - KES ' . number_format($plot['price'])
                    ];
                }, array_slice($plots, 0, 10))
            ]
        ];
        
        $this->sendList(
            $to, 
            "Here are our available plots. Select one to view more details:",
            "View Plots",
            $sections
        );
    }
    
    /**
     * Send default menu
     */
    private function sendDefaultMenu($to) {
        $sections = [
            [
                'title' => 'Main Menu',
                'rows' => [
                    ['id' => 'menu_plots', 'title' => 'View Available Plots', 'description' => 'Browse our land inventory'],
                    ['id' => 'menu_prices', 'title' => 'Get Price List', 'description' => 'View current prices'],
                    ['id' => 'menu_visit', 'title' => 'Book Site Visit', 'description' => 'Schedule a tour'],
                    ['id' => 'menu_payment', 'title' => 'Payment Info', 'description' => 'Check your balance'],
                    ['id' => 'menu_agent', 'title' => 'Contact Agent', 'description' => 'Speak to a human']
                ]
            ]
        ];
        
        $this->sendList(
            $to,
            "Please select an option from the menu below:",
            "Open Menu",
            $sections
        );
    }
    
    /**
     * Make API call to WhatsApp
     */
    private function makeApiCall($method, $endpoint, $data = null) {
        $url = $this->apiUrl . $endpoint;
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $result];
        } else {
            error_log("WhatsApp API Error: " . $response);
            return ['success' => false, 'error' => $result];
        }
    }
    
    /**
     * Mark message as read
     */
    private function markAsRead($messageId) {
        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];
        
        $this->makeApiCall('POST', $this->phoneNumberId . '/messages', $data);
    }
    
    /**
     * Log message to database
     */
    private function logMessage($to, $message, $type, $response) {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_messages 
            (to_number, type, content, direction, status, api_response, created_at) 
            VALUES (?, ?, ?, 'outbound', ?, ?, NOW())
        ");
        
        $status = $response['success'] ? 'sent' : 'failed';
        
        $stmt->execute([
            $to,
            $type,
            is_array($message) ? json_encode($message) : $message,
            $status,
            json_encode($response)
        ]);
    }
    
    /**
     * Send bulk messages
     */
    public function sendBulkMessages($recipients, $message, $type = 'text') {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? $recipient;
            
            // Rate limiting - 80 messages per second for WhatsApp Business API
            usleep(12500); // 1/80 second delay
            
            $result = $this->sendMessage($phone, $message, $type);
            $results[] = [
                'recipient' => $phone,
                'success' => $result['success'],
                'message_id' => $result['data']['messages'][0]['id'] ?? null
            ];
        }
        
        return $results;
    }
    
    /**
     * Create message template (for approval by Meta)
     */
    public function createMessageTemplate($name, $category, $components, $language = 'en') {
        $data = [
            'name' => $name,
            'category' => $category, // UTILITY, MARKETING, or AUTHENTICATION
            'components' => $components,
            'language' => $language
        ];
        
        return $this->makeApiCall('POST', 'message_templates', $data);
    }
}

// webhook.php - Endpoint for WhatsApp webhooks
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verify webhook (one-time setup)
    $verify_token = 'your_verify_token_here';
    
    if (isset($_GET['hub_mode']) && 
        isset($_GET['hub_verify_token']) && 
        $_GET['hub_verify_token'] === $verify_token) {
        echo $_GET['hub_challenge'];
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process incoming webhook
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    require_once 'config.php';
    require_once 'app/services/WhatsAppService.php';
    
    $whatsapp = new WhatsAppService($pdo);
    $whatsapp->processWebhook($data);
    
    http_response_code(200);
    echo json_encode(['status' => 'received']);
}