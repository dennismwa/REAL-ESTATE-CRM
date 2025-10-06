<?php
// app/services/CampaignService.php

class CampaignService {
    private $pdo;
    private $emailService;
    private $smsService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->emailService = new EmailService($pdo);
        $this->smsService = new SMSService($pdo);
    }
    
    /**
     * Create a new campaign
     */
    public function createCampaign($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO campaigns 
            (name, type, target_audience, template_id, status, scheduled_at, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['name'],
            $data['type'],
            json_encode($data['target_audience']),
            $data['template_id'],
            $data['status'] ?? 'draft',
            $data['scheduled_at'] ?? null,
            $data['created_by']
        ]);
        
        $campaignId = $this->pdo->lastInsertId();
        
        // Add recipients based on target audience
        $this->addRecipients($campaignId, $data['target_audience']);
        
        return $campaignId;
    }
    
    /**
     * Add recipients to campaign
     */
    private function addRecipients($campaignId, $targetAudience) {
        $recipients = $this->getTargetRecipients($targetAudience);
        
        foreach ($recipients as $recipient) {
            $stmt = $this->pdo->prepare("
                INSERT INTO campaign_recipients 
                (campaign_id, recipient_type, recipient_id, status, created_at) 
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $campaignId,
                $recipient['type'],
                $recipient['id']
            ]);
        }
        
        return count($recipients);
    }
    
    /**
     * Get target recipients based on criteria
     */
    private function getTargetRecipients($criteria) {
        $recipients = [];
        
        // Target leads
        if (isset($criteria['leads'])) {
            $query = "SELECT id, 'lead' as type FROM leads WHERE 1=1";
            $params = [];
            
            if (isset($criteria['leads']['status'])) {
                $query .= " AND status IN (" . str_repeat('?,', count($criteria['leads']['status']) - 1) . "?)";
                $params = array_merge($params, $criteria['leads']['status']);
            }
            
            if (isset($criteria['leads']['source'])) {
                $query .= " AND source = ?";
                $params[] = $criteria['leads']['source'];
            }
            
            if (isset($criteria['leads']['score_min'])) {
                $query .= " AND id IN (SELECT lead_id FROM lead_scores WHERE score >= ?)";
                $params[] = $criteria['leads']['score_min'];
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $leads = $stmt->fetchAll();
            
            foreach ($leads as $lead) {
                $recipients[] = ['type' => 'lead', 'id' => $lead['id']];
            }
        }
        
        // Target clients
        if (isset($criteria['clients'])) {
            $query = "SELECT id, 'client' as type FROM clients WHERE 1=1";
            $params = [];
            
            if (isset($criteria['clients']['has_balance'])) {
                $query .= " AND id IN (SELECT client_id FROM sales WHERE balance > 0)";
            }
            
            if (isset($criteria['clients']['project_id'])) {
                $query .= " AND id IN (
                    SELECT client_id FROM sales s 
                    JOIN plots p ON s.plot_id = p.id 
                    WHERE p.project_id = ?
                )";
                $params[] = $criteria['clients']['project_id'];
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $clients = $stmt->fetchAll();
            
            foreach ($clients as $client) {
                $recipients[] = ['type' => 'client', 'id' => $client['id']];
            }
        }
        
        return $recipients;
    }
    
    /**
     * Execute a campaign
     */
    public function executeCampaign($campaignId) {
        // Get campaign details
        $stmt = $this->pdo->prepare("
            SELECT c.*, ct.* 
            FROM campaigns c
            JOIN communication_templates ct ON c.template_id = ct.id
            WHERE c.id = ?
        ");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch();
        
        if (!$campaign) {
            throw new Exception("Campaign not found");
        }
        
        // Update campaign status
        $stmt = $this->pdo->prepare("UPDATE campaigns SET status = 'running' WHERE id = ?");
        $stmt->execute([$campaignId]);
        
        // Get pending recipients
        $stmt = $this->pdo->prepare("
            SELECT * FROM campaign_recipients 
            WHERE campaign_id = ? AND status = 'pending'
        ");
        $stmt->execute([$campaignId]);
        $recipients = $stmt->fetchAll();
        
        $results = ['sent' => 0, 'failed' => 0];
        
        foreach ($recipients as $recipient) {
            $recipientData = $this->getRecipientData($recipient['recipient_type'], $recipient['recipient_id']);
            
            if (!$recipientData) continue;
            
            // Personalize content
            $personalizedContent = $this->personalizeContent(
                $campaign['content'],
                $recipientData,
                json_decode($campaign['variables'], true)
            );
            
            // Send based on type
            $sent = false;
            if ($campaign['type'] === 'email') {
                $sent = $this->emailService->send(
                    $recipientData['email'],
                    $campaign['subject'],
                    $personalizedContent
                );
            } elseif ($campaign['type'] === 'sms') {
                $sent = $this->smsService->send(
                    $recipientData['phone'],
                    $personalizedContent
                );
            } elseif ($campaign['type'] === 'whatsapp') {
                $whatsapp = new WhatsAppService($this->pdo);
                $result = $whatsapp->sendMessage(
                    $recipientData['phone'],
                    $personalizedContent
                );
                $sent = $result['success'];
            }
            
            // Update recipient status
            $status = $sent ? 'sent' : 'failed';
            $stmt = $this->pdo->prepare("
                UPDATE campaign_recipients 
                SET status = ?, sent_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $recipient['id']]);
            
            if ($sent) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
            
            // Rate limiting
            usleep(100000); // 0.1 second delay
        }
        
        // Update campaign stats
        $stmt = $this->pdo->prepare("
            UPDATE campaigns 
            SET status = 'completed', stats = ? 
            WHERE id = ?
        ");
        $stmt->execute([json_encode($results), $campaignId]);
        
        return $results;
    }
    
    /**
     * Get recipient data
     */
    private function getRecipientData($type, $id) {
        if ($type === 'lead') {
            $stmt = $this->pdo->prepare("SELECT * FROM leads WHERE id = ?");
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = ?");
        }
        
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Personalize content with variables
     */
    private function personalizeContent($content, $recipientData, $variables) {
        // Replace standard variables
        $content = str_replace('{{name}}', $recipientData['full_name'], $content);
        $content = str_replace('{{first_name}}', explode(' ', $recipientData['full_name'])[0], $content);
        $content = str_replace('{{phone}}', $recipientData['phone'], $content);
        $content = str_replace('{{email}}', $recipientData['email'] ?? '', $content);
        
        // Replace custom variables
        if ($variables) {
            foreach ($variables as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
        }
        
        // Add tracking pixel for emails
        if (strpos($content, '</body>') !== false) {
            $trackingPixel = '<img src="' . getenv('APP_URL') . '/api/track/email-open.php?id=' . 
                             $recipientData['id'] . '&type=' . $type . '" width="1" height="1" />';
            $content = str_replace('</body>', $trackingPixel . '</body>', $content);
        }
        
        return $content;
    }
}

// Email Service
class EmailService {
    private $pdo;
    private $apiKey;
    private $fromEmail;
    private $fromName;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->apiKey = getenv('SENDGRID_API_KEY'); // or any email service
        $this->fromEmail = getenv('MAIL_FROM');
        $this->fromName = getenv('MAIL_FROM_NAME');
    }
    
    public function send($to, $subject, $content, $attachments = []) {
        // Using SendGrid as example
        $data = [
            'personalizations' => [
                ['to' => [['email' => $to]]]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName
            ],
            'subject' => $subject,
            'content' => [
                ['type' => 'text/html', 'value' => $content]
            ]
        ];
        
        if (!empty($attachments)) {
            $data['attachments'] = $attachments;
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Log email
        $stmt = $this->pdo->prepare("
            INSERT INTO email_log 
            (recipient, subject, status, sent_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed';
        $stmt->execute([$to, $subject, $status]);
        
        return $status === 'sent';
    }
    
    public function sendTemplate($to, $templateId, $variables = []) {
        // Get template
        $stmt = $this->pdo->prepare("
            SELECT * FROM communication_templates 
            WHERE id = ? AND type = 'email'
        ");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        
        if (!$template) {
            return false;
        }
        
        // Replace variables
        $content = $template['content'];
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $this->send($to, $template['subject'], $content);
    }
}

// SMS Service (Using Africa's Talking)
class SMSService {
    private $pdo;
    private $username;
    private $apiKey;
    private $senderId;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->username = getenv('AT_USERNAME');
        $this->apiKey = getenv('AT_API_KEY');
        $this->senderId = getenv('SMS_SENDER_ID');
    }
    
    public function send($to, $message) {
        // Format phone number
        $to = $this->formatPhoneNumber($to);
        
        $data = [
            'username' => $this->username,
            'to' => $to,
            'message' => $message,
            'from' => $this->senderId
        ];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://api.africastalking.com/version1/messaging');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'apiKey: ' . $this->apiKey,
            'Accept: application/json'
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        // Log SMS
        $stmt = $this->pdo->prepare("
            INSERT INTO sms_log 
            (recipient, message, status, sent_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $status = isset($result['SMSMessageData']['Recipients'][0]['status']) 
                  && $result['SMSMessageData']['Recipients'][0]['status'] === 'Success' 
                  ? 'sent' : 'failed';
        
        $stmt->execute([$to, $message, $status]);
        
        return $status === 'sent';
    }
    
    public function sendBulk($recipients, $message) {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $sent = $this->send($recipient, $message);
            $results[] = ['recipient' => $recipient, 'sent' => $sent];
            
            // Rate limiting
            usleep(50000); // 0.05 second delay
        }
        
        return $results;
    }
    
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '+254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) === '254') {
            $phone = '+' . $phone;
        } elseif (substr($phone, 0, 4) !== '+254') {
            $phone = '+254' . $phone;
        }
        
        return $phone;
    }
}

// campaigns.php - Campaign Management Interface
$pageTitle = 'Marketing Campaigns';
require_once 'config.php';
requirePermission('marketing', 'view');

// Handle campaign creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $campaignService = new CampaignService($pdo);
    
    if ($_POST['action'] === 'create') {
        $campaignData = [
            'name' => sanitize($_POST['name']),
            'type' => $_POST['type'],
            'template_id' => $_POST['template_id'],
            'target_audience' => $_POST['target_audience'],
            'status' => $_POST['status'],
            'scheduled_at' => $_POST['scheduled_at'] ?? null,
            'created_by' => getUserId()
        ];
        
        $campaignId = $campaignService->createCampaign($campaignData);
        
        if ($_POST['status'] === 'send_now') {
            $campaignService->executeCampaign($campaignId);
            flashMessage('Campaign sent successfully!');
        } else {
            flashMessage('Campaign created successfully!');
        }
        
        redirect('/campaigns.php');
    }
}

// Get campaigns
$stmt = $pdo->query("
    SELECT c.*, u.full_name as created_by_name,
           (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = c.id) as recipient_count,
           (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = c.id AND status = 'sent') as sent_count
    FROM campaigns c
    LEFT JOIN users u ON c.created_by = u.id
    ORDER BY c.created_at DESC
");
$campaigns = $stmt->fetchAll();

// Get templates
$stmt = $pdo->query("SELECT * FROM communication_templates ORDER BY name");
$templates = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Marketing Campaigns</h1>
            <p class="text-gray-600 mt-1">Email, SMS, and WhatsApp campaigns</p>
        </div>
        <button onclick="openCampaignModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90">
            <i class="fas fa-plus mr-2"></i>Create Campaign
        </button>
    </div>
    
    <!-- Campaign Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Total Campaigns</p>
            <p class="text-2xl font-bold text-primary"><?php echo count($campaigns); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Active</p>
            <p class="text-2xl font-bold text-green-600">
                <?php echo count(array_filter($campaigns, fn($c) => $c['status'] === 'running')); ?>
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Scheduled</p>
            <p class="text-2xl font-bold text-blue-600">
                <?php echo count(array_filter($campaigns, fn($c) => $c['status'] === 'scheduled')); ?>
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600">Total Sent</p>
            <p class="text-2xl font-bold text-purple-600">
                <?php echo array_sum(array_column($campaigns, 'sent_count')); ?>
            </p>
        </div>
    </div>
    
    <!-- Campaigns List -->
    <div class="bg-white rounded-lg shadow">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Campaign</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Recipients</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Schedule</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Created By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($campaigns as $campaign): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-semibold"><?php echo sanitize($campaign['name']); ?></p>
                                <p class="text-xs text-gray-600">Created <?php echo date('M d, Y', strtotime($campaign['created_at'])); ?></p>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php echo $campaign['type'] === 'email' ? 'bg-blue-100 text-blue-800' : 
                                          ($campaign['type'] === 'sms' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800'); ?>">
                                <?php echo ucfirst($campaign['type']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm">
                                <p><?php echo $campaign['sent_count']; ?> / <?php echo $campaign['recipient_count']; ?></p>
                                <div class="w-20 bg-gray-200 rounded-full h-1 mt-1">
                                    <div class="bg-primary h-1 rounded-full" 
                                         style="width: <?php echo $campaign['recipient_count'] > 0 ? ($campaign['sent_count'] / $campaign['recipient_count']) * 100 : 0; ?>%">
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php 
                                $statusColors = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'scheduled' => 'bg-yellow-100 text-yellow-800',
                                    'running' => 'bg-blue-100 text-blue-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'paused' => 'bg-orange-100 text-orange-800'
                                ];
                                echo $statusColors[$campaign['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>">
                                <?php echo ucfirst($campaign['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo $campaign['scheduled_at'] ? date('M d, Y H:i', strtotime($campaign['scheduled_at'])) : 'Not scheduled'; ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo sanitize($campaign['created_by_name']); ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <button onclick="viewCampaign(<?php echo $campaign['id']; ?>)" 
                                        class="text-primary hover:underline text-sm">View</button>
                                <?php if ($campaign['status'] === 'draft' || $campaign['status'] === 'scheduled'): ?>
                                <button onclick="sendCampaign(<?php echo $campaign['id']; ?>)" 
                                        class="text-green-600 hover:underline text-sm">Send</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Campaign Modal -->
<div id="campaignModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6">
        <h2 class="text-xl font-bold mb-4">Create New Campaign</h2>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Campaign Name</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Type</label>
                    <select name="type" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Template</label>
                    <select name="template_id" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select Template</option>
                        <?php foreach ($templates as $template): ?>
                        <option value="<?php echo $template['id']; ?>">
                            <?php echo sanitize($template['name']); ?> (<?php echo $template['type']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Target Audience</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="target_audience[leads][all]" value="1" class="mr-2">
                            All Leads
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="target_audience[clients][all]" value="1" class="mr-2">
                            All Clients
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="target_audience[clients][has_balance]" value="1" class="mr-2">
                            Clients with Balance
                        </label>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Send</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="send_now">Send Now</option>
                        <option value="draft">Save as Draft</option>
                        <option value="scheduled">Schedule for Later</option>
                    </select>
                </div>
                
                <div id="scheduleField" class="hidden">
                    <label class="block text-sm font-semibold mb-2">Schedule Date/Time</label>
                    <input type="datetime-local" name="scheduled_at" class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 bg-primary text-white py-2 rounded-lg">Create Campaign</button>
                <button type="button" onclick="closeCampaignModal()" class="px-6 py-2 bg-gray-200 rounded-lg">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCampaignModal() {
    document.getElementById('campaignModal').classList.remove('hidden');
}

function closeCampaignModal() {
    document.getElementById('campaignModal').classList.add('hidden');
}

document.querySelector('[name="status"]').addEventListener('change', function() {
    document.getElementById('scheduleField').classList.toggle('hidden', this.value !== 'scheduled');
});
</script>

<?php include 'includes/footer.php'; ?>