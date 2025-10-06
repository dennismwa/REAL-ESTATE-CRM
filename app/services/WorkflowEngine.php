<?php
// app/services/WorkflowEngine.php

class WorkflowEngine {
    private $pdo;
    private $triggers = [];
    private $actions = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->registerTriggers();
        $this->registerActions();
    }
    
    /**
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
    }
    
    /**
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
    }
    
    /**
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
        $workflows = $stmt->fetchAll();
        
        foreach ($workflows as $workflow) {
            $this->executeWorkflow($workflow, $data);
        }
    }
    
    /**
     * Execute a workflow
     */
    public function executeWorkflow($workflow, $triggerData) {
        $conditions = json_decode($workflow['conditions'], true);
        $actions = json_decode($workflow['actions'], true);
        
        // Check if conditions are met
        if (!$this->evaluateConditions($conditions, $triggerData)) {
            return false;
        }
        
        // Execute actions
        foreach ($actions as $action) {
            $this->executeAction($action, $triggerData);
            
            // Log workflow execution
            $this->logExecution($workflow['id'], $action, $triggerData);
        }
        
        return true;
    }
    
    /**
     * Evaluate workflow conditions
     */
    private function evaluateConditions($conditions, $data) {
        if (empty($conditions)) {
            return true;
        }
        
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            $fieldValue = $this->getFieldValue($field, $data);
            
            switch ($operator) {
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
        }
        
        return true;
    }
    
    /**
     * Execute a workflow action
     */
    private function executeAction($action, $data) {
        switch ($action['type']) {
            case 'assign_to_user':
                $this->assignToUser($data, $action['config']);
                break;
                
            case 'send_email':
                $this->sendEmail($data, $action['config']);
                break;
                
            case 'send_sms':
                $this->sendSMS($data, $action['config']);
                break;
                
            case 'send_whatsapp':
                $this->sendWhatsApp($data, $action['config']);
                break;
                
            case 'create_task':
                $this->createTask($data, $action['config']);
                break;
                
            case 'update_status':
                $this->updateStatus($data, $action['config']);
                break;
                
            case 'add_to_campaign':
                $this->addToCampaign($data, $action['config']);
                break;
                
            case 'generate_document':
                $this->generateDocument($data, $action['config']);
                break;
                
            case 'create_notification':
                $this->createNotification($data, $action['config']);
                break;
                
            case 'webhook':
                $this->callWebhook($data, $action['config']);
                break;
        }
    }
    
    // Action implementations
    private function assignToUser($data, $config) {
        $userId = $config['user_id'] ?? null;
        $entityType = $data['entity_type'];
        $entity