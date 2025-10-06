<artifacts>
<artifact id="workflow-engine-complete" type="application/vnd.ant.code" language="php" title="Complete WorkflowEngine.php">
<?php
// app/services/WorkflowEngine.phpclass WorkflowEngine {
private $pdo;
private $triggers = [];
private $actions = [];public function __construct($pdo) {
    $this->pdo = $pdo;
    $this->registerTriggers();
    $this->registerActions();
}/**
 * Register all available triggers
 */
private function registerTriggers() {
    $this->triggers = [
        'lead_created' => 'When a new lead is created',
        'lead_status_changed' => 'When lead status changes',
        'sale_created' => 'When a new sale is made',
        'payment_received' => 'When payment is received',
        'client_created' => 'When new client is added',
        'task_overdue' => 'When task becomes overdue',
        'site_visit_scheduled' => 'When site visit is scheduled',
        'document_uploaded' => 'When document is uploaded',
        'support_ticket_created' => 'When support ticket is created'
    ];
}/**
 * Register all available actions
 */
private function registerActions() {
    $this->actions = [
        'assign_to_user' => 'Assign to specific user',
        'send_email' => 'Send email notification',
        'send_sms' => 'Send SMS notification',
        'send_whatsapp' => 'Send WhatsApp message',
        'create_task' => 'Create a task',
        'update_status' => 'Update record status',
        'add_to_campaign' => 'Add to marketing campaign',
        'generate_document' => 'Generate document from template',
        'create_notification' => 'Create internal notification',
        'webhook' => 'Call external webhook'
    ];
}/**
 * Process a trigger event
 */
public function processTrigger($triggerName, $data) {
    // Get active workflows for this trigger
    $stmt = $this->pdo->prepare("
        SELECT * FROM workflows 
        WHERE trigger_event = ? 
        AND is_active = 1
    ");
    $stmt->execute([$triggerName]);
    $workflows = $stmt->fetchAll();    foreach ($workflows as $workflow) {
        $this->executeWorkflow($workflow, $data);
    }
}/**
 * Execute a workflow
 */
public function executeWorkflow($workflow, $triggerData) {
    $conditions = json_decode($workflow['conditions'], true);
    $actions = json_decode($workflow['actions'], true);    // Check if conditions are met
    if (!$this->evaluateConditions($conditions, $triggerData)) {
        return false;
    }    // Execute actions
    foreach ($actions as $action) {
        $this->executeAction($action, $triggerData);        // Log workflow execution
        $this->logExecution($workflow['id'], $action, $triggerData);
    }    return true;
}/**
 * Evaluate workflow conditions
 */
private function evaluateConditions($conditions, $data) {
    if (empty($conditions)) {
        return true;
    }    foreach ($conditions as $condition) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];        $fieldValue = $this->getFieldValue($field, $data);        switch ($operator) {
            case 'equals':
                if ($fieldValue != $value) return false;
                break;
            case 'not_equals':
                if ($fieldValue == $value) return false;
                break;
            case 'contains':
                if (strpos($fieldValue, $value) === false) return false;
                break;
            case 'greater_than':
                if ($fieldValue <= $value) return false;
                break;
            case 'less_than':
                if ($fieldValue >= $value) return false;
                break;
            case 'in':
                if (!in_array($fieldValue, $value)) return false;
                break;
        }
    }    return true;
}/**
 * Get field value from data
 */
private function getFieldValue($field, $data) {
    $parts = explode('.', $field);
    $value = $data;    foreach ($parts as $part) {
        if (isset($value[$part])) {
            $value = $value[$part];
        } else {
            return null;
        }
    }    return $value;
}/**
 * Execute a workflow action
 */
private function executeAction($action, $data) {
    switch ($action['type']) {
        case 'assign_to_user':
            $this->assignToUser($data, $action['config']);
            break;        case 'send_email':
            $this->sendEmail($data, $action['config']);
            break;        case 'send_sms':
            $this->sendSMS($data, $action['config']);
            break;        case 'send_whatsapp':
            $this->sendWhatsApp($data, $action['config']);
            break;        case 'create_task':
            $this->createTask($data, $action['config']);
            break;        case 'update_status':
            $this->updateStatus($data, $action['config']);
            break;        case 'add_to_campaign':
            $this->addToCampaign($data, $action['config']);
            break;        case 'generate_document':
            $this->generateDocument($data, $action['config']);
            break;        case 'create_notification':
            $this->createNotification($data, $action['config']);
            break;        case 'webhook':
            $this->callWebhook($data, $action['config']);
            break;
    }
}// Action implementations
private function assignToUser($data, $config) {
    $userId = $config['user_id'] ?? null;
    $entityType = $data['entity_type'];
    $entityId = $data['entity_id'];    if (!$userId || !$entityId) {
        return;
    }    switch ($entityType) {
        case 'lead':
            $stmt = $this->pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$userId, $entityId]);
            break;        case 'task':
            $stmt = $this->pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$userId, $entityId]);
            break;        case 'ticket':
            $stmt = $this->pdo->prepare("UPDATE support_tickets SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$userId, $entityId]);
            break;
    }
}private function sendEmail($data, $config) {
    require_once __DIR__ . '/CampaignService.php';    $emailService = new EmailService($this->pdo);    $to = $this->resolveRecipient($data, $config['recipient']);
    $subject = $this->replaceVariables($config['subject'], $data);
    $content = $this->replaceVariables($config['content'], $data);    $emailService->send($to, $subject, $content);
}private function sendSMS($data, $config) {
    require_once __DIR__ . '/CampaignService.php';    $smsService = new SMSService($this->pdo);    $to = $this->resolveRecipient($data, $config['recipient']);
    $message = $this->replaceVariables($config['message'], $data);    $smsService->send($to, $message);
}private function sendWhatsApp($data, $config) {
    require_once __DIR__ . '/WhatsAppService.php';    $whatsapp = new WhatsAppService($this->pdo);    $to = $this->resolveRecipient($data, $config['recipient']);
    $message = $this->replaceVariables($config['message'], $data);    $whatsapp->sendMessage($to, $message);
}private function createTask($data, $config) {
    $title = $this->replaceVariables($config['title'], $data);
    $description = $this->replaceVariables($config['description'], $data);
    $assignedTo = $config['assigned_to'] ?? null;
    $priority = $config['priority'] ?? 'medium';
    $dueDate = $this->calculateDueDate($config['due_in_days'] ?? 1);    $stmt = $this->pdo->prepare("
        INSERT INTO tasks 
        (title, description, assigned_to, assigned_by, related_to, related_id, priority, status, due_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");    $stmt->execute([
        $title,
        $description,
        $assignedTo,
        1, // System user
        $data['entity_type'],
        $data['entity_id'],
        $priority,
        $dueDate
    ]);
}private function updateStatus($data, $config) {
    $entityType = $data['entity_type'];
    $entityId = $data['entity_id'];
    $newStatus = $config['status'];    switch ($entityType) {
        case 'lead':
            $stmt = $this->pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $entityId]);
            break;        case 'sale':
            $stmt = $this->pdo->prepare("UPDATE sales SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $entityId]);
            break;        case 'ticket':
            $stmt = $this->pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $entityId]);
            break;
    }
}private function addToCampaign($data, $config) {
    $campaignId = $config['campaign_id'];
    $recipientType = $data['entity_type'];
    $recipientId = $data['entity_id'];    if (!$campaignId) {
        return;
    }    $stmt = $this->pdo->prepare("
        INSERT IGNORE INTO campaign_recipients 
        (campaign_id, recipient_type, recipient_id, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");    $stmt->execute([$campaignId, $recipientType, $recipientId]);
}private function generateDocument($data, $config) {
    require_once __DIR__ . '/DocumentGeneratorService.php';    $generator = new DocumentGeneratorService($this->pdo);    $templateId = $config['template_id'];
    $entityType = $data['entity_type'];
    $entityId = $data['entity_id'];    $generator->generateFromTemplate($templateId, $entityType, $entityId);
}private function createNotification($data, $config) {
    $userId = $config['user_id'] ?? $data['user_id'] ?? null;
    $title = $this->replaceVariables($config['title'], $data);
    $message = $this->replaceVariables($config['message'], $data);
    $type = $config['type'] ?? 'info';
    $link = $this->buildLink($data);    if ($userId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, link, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");        $stmt->execute([$userId, $title, $message, $type, $link]);
    }
}private function callWebhook($data, $config) {
    $url = $config['url'];
    $method = $config['method'] ?? 'POST';
    $headers = $config['headers'] ?? [];
    $payload = $this->buildWebhookPayload($data, $config['payload'] ?? []);    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        $headers[] = 'Content-Type: application/json';
    }    if (!empty($headers)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);    // Log webhook execution
    $this->logWebhookExecution($url, $payload, $response, $httpCode);
}// Helper methods
private function resolveRecipient($data, $recipientConfig) {
    if (isset($recipientConfig['field'])) {
        return $this->getFieldValue($recipientConfig['field'], $data);
    }    return $recipientConfig['value'] ?? '';
}private function replaceVariables($text, $data) {
    preg_match_all('/{{(.*?)}}/', $text, $matches);    foreach ($matches[1] as $variable) {
        $value = $this->getFieldValue($variable, $data);
        $text = str_replace('{{' . $variable . '}}', $value, $text);
    }    return $text;
}private function calculateDueDate($daysFromNow) {
    return date('Y-m-d H:i:s', strtotime("+$daysFromNow days"));
}private function buildLink($data) {
    $entityType = $data['entity_type'];
    $entityId = $data['entity_id'];    $links = [
        'lead' => "/leads.php?action=view&id=$entityId",
        'client' => "/clients.php?action=view&id=$entityId",
        'sale' => "/sales.php?action=view&id=$entityId",
        'task' => "/tasks.php?action=view&id=$entityId",
        'ticket' => "/support.php?action=view&id=$entityId"
    ];    return $links[$entityType] ?? '#';
}private function buildWebhookPayload($data, $payloadConfig) {
    $payload = [];    foreach ($payloadConfig as $key => $value) {
        if (is_string($value) && strpos($value, '{{') !== false) {
            $payload[$key] = $this->replaceVariables($value, $data);
        } else {
            $payload[$key] = $value;
        }
    }    return $payload;
}private function logExecution($workflowId, $action, $data) {
    $stmt = $this->pdo->prepare("
        INSERT INTO workflow_executions 
        (workflow_id, action, data, executed_at)
        VALUES (?, ?, ?, NOW())
    ");    $stmt->execute([
        $workflowId,
        json_encode($action),
        json_encode($data)
    ]);
}private function logWebhookExecution($url, $payload, $response, $httpCode) {
    $stmt = $this->pdo->prepare("
        INSERT INTO webhook_logs 
        (url, payload, response, http_code, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");    $stmt->execute([
        $url,
        json_encode($payload),
        $response,
        $httpCode
    ]);
}/**
 * Create a new workflow
 */
public function createWorkflow($name, $triggerEvent, $conditions, $actions, $createdBy) {
    $stmt = $this->pdo->prepare("
        INSERT INTO workflows 
        (name, trigger_event, conditions, actions, is_active, created_by, created_at)
        VALUES (?, ?, ?, ?, 1, ?, NOW())
    ");    return $stmt->execute([
        $name,
        $triggerEvent,
        json_encode($conditions),
        json_encode($actions),
        $createdBy
    ]);
}/**
 * Get all workflows
 */
public function getAllWorkflows() {
    $stmt = $this->pdo->query("
        SELECT w.*, u.full_name as created_by_name
        FROM workflows w
        JOIN users u ON w.created_by = u.id
        ORDER BY w.created_at DESC
    ");    return $stmt->fetchAll();
}/**
 * Toggle workflow status
 */
public function toggleWorkflow($workflowId) {
    $stmt = $this->pdo->prepare("
        UPDATE workflows 
        SET is_active = NOT is_active 
        WHERE id = ?
    ");    return $stmt->execute([$workflowId]);
}/**
 * Delete workflow
 */
public function deleteWorkflow($workflowId) {
    $stmt = $this->pdo->prepare("DELETE FROM workflows WHERE id = ?");
    return $stmt->execute([$workflowId]);
}
}// Usage example in other parts of the system:
//
// When a lead is created:
// workflowEngine=newWorkflowEngine(workflowEngine = new WorkflowEngine(
workflowEngine=newWorkflowEngine(pdo);
// $workflowEngine->processTrigger('lead_created', [
//     'entity_type' => 'lead',
//     'entity_id' => $leadId,
//     'lead' => $leadData,
//     'user_id' => $assignedTo
// ]);
</artifact>
</artifacts>
