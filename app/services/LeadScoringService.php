<?php
// app/services/LeadScoringService.php

class LeadScoringService {
    private $pdo;
    private $scoringWeights = [
        'budget' => 30,
        'timeline' => 20,
        'engagement' => 25,
        'source' => 15,
        'demographics' => 10
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate comprehensive lead score
     */
    public function calculateScore($leadId) {
        $stmt = $this->pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        
        if (!$lead) {
            return false;
        }
        
        $score = 0;
        $factors = [];
        
        // 1. Budget Score (0-30 points)
        $budgetScore = $this->calculateBudgetScore($lead);
        $score += $budgetScore;
        $factors['budget'] = $budgetScore;
        
        // 2. Timeline Score (0-20 points)
        $timelineScore = $this->calculateTimelineScore($lead);
        $score += $timelineScore;
        $factors['timeline'] = $timelineScore;
        
        // 3. Engagement Score (0-25 points)
        $engagementScore = $this->calculateEngagementScore($leadId);
        $score += $engagementScore;
        $factors['engagement'] = $engagementScore;
        
        // 4. Source Score (0-15 points)
        $sourceScore = $this->calculateSourceScore($lead['source']);
        $score += $sourceScore;
        $factors['source'] = $sourceScore;
        
        // 5. Demographics Score (0-10 points)
        $demographicsScore = $this->calculateDemographicsScore($lead);
        $score += $demographicsScore;
        $factors['demographics'] = $demographicsScore;
        
        // Determine grade
        $grade = $this->determineGrade($score);
        
        // Save or update lead score
        $stmt = $this->pdo->prepare("
            INSERT INTO lead_scores (lead_id, score, factors, grade, last_calculated) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            score = VALUES(score), 
            factors = VALUES(factors), 
            grade = VALUES(grade),
            last_calculated = NOW()
        ");
        
        $stmt->execute([$leadId, $score, json_encode($factors), $grade]);
        
        // Trigger actions based on score
        $this->triggerScoreActions($leadId, $score, $grade);
        
        return [
            'lead_id' => $leadId,
            'score' => $score,
            'grade' => $grade,
            'factors' => $factors
        ];
    }
    
    private function calculateBudgetScore($lead) {
        // Get average plot prices
        $stmt = $this->pdo->query("SELECT AVG(price) as avg_price FROM plots WHERE status = 'available'");
        $avgPrice = $stmt->fetch()['avg_price'];
        
        // Extract budget from notes or custom field
        $budget = $this->extractBudget($lead['notes']);
        
        if ($budget >= $avgPrice * 2) return 30;
        if ($budget >= $avgPrice * 1.5) return 25;
        if ($budget >= $avgPrice) return 20;
        if ($budget >= $avgPrice * 0.7) return 15;
        if ($budget >= $avgPrice * 0.5) return 10;
        return 5;
    }
    
    private function calculateTimelineScore($lead) {
        // Check last activity
        $stmt = $this->pdo->prepare("
            SELECT MAX(created_at) as last_activity 
            FROM lead_activities 
            WHERE lead_id = ?
        ");
        $stmt->execute([$lead['id']]);
        $lastActivity = $stmt->fetch()['last_activity'];
        
        if (!$lastActivity) {
            $lastActivity = $lead['created_at'];
        }
        
        $daysSinceActivity = (time() - strtotime($lastActivity)) / 86400;
        
        if ($daysSinceActivity < 1) return 20;
        if ($daysSinceActivity < 3) return 18;
        if ($daysSinceActivity < 7) return 15;
        if ($daysSinceActivity < 14) return 10;
        if ($daysSinceActivity < 30) return 5;
        return 2;
    }
    
    private function calculateEngagementScore($leadId) {
        $score = 0;
        
        // Check email opens
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as email_opens 
            FROM email_tracking 
            WHERE lead_id = ? AND action = 'opened'
        ");
        $stmt->execute([$leadId]);
        $emailOpens = $stmt->fetch()['email_opens'];
        $score += min($emailOpens * 2, 8);
        
        // Check link clicks
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as clicks 
            FROM email_tracking 
            WHERE lead_id = ? AND action = 'clicked'
        ");
        $stmt->execute([$leadId]);
        $clicks = $stmt->fetch()['clicks'];
        $score += min($clicks * 3, 9);
        
        // Check site visits attendance
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as visits 
            FROM site_visit_attendees sva
            JOIN site_visits sv ON sva.site_visit_id = sv.id
            WHERE sva.lead_id = ? AND sv.status = 'completed'
        ");
        $stmt->execute([$leadId]);
        $visits = $stmt->fetch()['visits'];
        $score += min($visits * 4, 8);
        
        return min($score, 25);
    }
    
    private function calculateSourceScore($source) {
        $sourceScores = [
            'referral' => 15,
            'website' => 12,
            'walk_in' => 10,
            'facebook' => 8,
            'instagram' => 8,
            'other' => 5
        ];
        
        return $sourceScores[$source] ?? 5;
    }
    
    private function calculateDemographicsScore($lead) {
        $score = 0;
        
        // Has email
        if (!empty($lead['email'])) $score += 3;
        
        // Has complete phone number
        if (strlen(preg_replace('/[^0-9]/', '', $lead['phone'])) >= 10) $score += 3;
        
        // Has been qualified
        if ($lead['status'] !== 'new') $score += 4;
        
        return min($score, 10);
    }
    
    private function determineGrade($score) {
        if ($score >= 85) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 55) return 'C';
        if ($score >= 40) return 'D';
        return 'E';
    }
    
    private function triggerScoreActions($leadId, $score, $grade) {
        // High-priority lead actions
        if ($grade === 'A') {
            // Assign to best agent
            $this->assignToBestAgent($leadId);
            
            // Send high-priority notification
            $this->sendHighPriorityNotification($leadId);
            
            // Schedule immediate follow-up
            $this->scheduleFollowUp($leadId, 'immediate');
        }
        
        // Medium-priority lead actions
        elseif ($grade === 'B') {
            // Normal assignment
            $this->autoAssignLead($leadId);
            
            // Schedule follow-up within 24 hours
            $this->scheduleFollowUp($leadId, '24_hours');
        }
        
        // Nurture campaign for lower scores
        elseif ($grade === 'C' || $grade === 'D') {
            // Add to nurture campaign
            $this->addToNurtureCampaign($leadId);
        }
    }
    
    private function assignToBestAgent($leadId) {
        // Get best performing available agent
        $stmt = $this->pdo->query("
            SELECT 
                u.id,
                COUNT(s.id) as sales_count,
                AVG(DATEDIFF(s.sale_date, l.created_at)) as avg_conversion_time
            FROM users u
            LEFT JOIN sales s ON u.id = s.agent_id AND s.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            LEFT JOIN clients c ON s.client_id = c.id
            LEFT JOIN leads l ON c.lead_id = l.id
            WHERE u.role = 'sales_agent' 
            AND u.status = 'active'
            AND (
                SELECT COUNT(*) FROM leads 
                WHERE assigned_to = u.id 
                AND status IN ('new', 'contacted')
            ) < 20
            GROUP BY u.id
            ORDER BY sales_count DESC, avg_conversion_time ASC
            LIMIT 1
        ");
        
        $bestAgent = $stmt->fetch();
        
        if ($bestAgent) {
            $stmt = $this->pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$bestAgent['id'], $leadId]);
            
            // Notify agent
            $this->notifyAgent($bestAgent['id'], $leadId, 'high_priority');
        }
    }
    
    private function autoAssignLead($leadId) {
        // Round-robin with load balancing
        $stmt = $this->pdo->query("
            SELECT 
                u.id,
                COUNT(l.id) as lead_count
            FROM users u
            LEFT JOIN leads l ON u.id = l.assigned_to 
                AND l.status IN ('new', 'contacted', 'qualified')
            WHERE u.role = 'sales_agent' 
            AND u.status = 'active'
            GROUP BY u.id
            ORDER BY lead_count ASC
            LIMIT 1
        ");
        
        $agent = $stmt->fetch();
        
        if ($agent) {
            $stmt = $this->pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$agent['id'], $leadId]);
            
            // Notify agent
            $this->notifyAgent($agent['id'], $leadId, 'normal');
        }
    }
    
    private function scheduleFollowUp($leadId, $priority) {
        $dueDate = match($priority) {
            'immediate' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            '24_hours' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            default => date('Y-m-d H:i:s', strtotime('+48 hours'))
        };
        
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks 
            (title, description, related_to, related_id, priority, due_date, status, created_at) 
            VALUES (?, ?, 'lead', ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            'Follow up with lead',
            'High-priority lead requires immediate attention',
            $leadId,
            $priority === 'immediate' ? 'urgent' : 'high',
            $dueDate
        ]);
    }
    
    private function addToNurtureCampaign($leadId) {
        // Add to email drip campaign
        $campaignId = $this->getOrCreateNurtureCampaign();
        
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO campaign_recipients 
            (campaign_id, recipient_type, recipient_id, status, created_at) 
            VALUES (?, 'lead', ?, 'pending', NOW())
        ");
        
        $stmt->execute([$campaignId, $leadId]);
    }
    
    private function notifyAgent($agentId, $leadId, $priority) {
        // Get lead details
        $stmt = $this->pdo->prepare("
            SELECT l.*, ls.score, ls.grade 
            FROM leads l
            LEFT JOIN lead_scores ls ON l.id = ls.lead_id
            WHERE l.id = ?
        ");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        
        $message = $priority === 'high_priority' 
            ? "🔥 High-priority lead assigned: {$lead['full_name']} (Score: {$lead['score']}, Grade: {$lead['grade']})"
            : "New lead assigned: {$lead['full_name']}";
        
        // Create notification
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, link, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $agentId,
            'New Lead Assignment',
            $message,
            $priority === 'high_priority' ? 'warning' : 'info',
            "/leads.php?action=view&id={$leadId}"
        ]);
        
        // Send push notification if mobile app
        $this->sendPushNotification($agentId, $message);
    }
    
    private function sendPushNotification($userId, $message) {
        // Get user's device tokens
        $stmt = $this->pdo->prepare("
            SELECT push_token, device_type 
            FROM mobile_devices 
            WHERE user_id = ? AND push_token IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $devices = $stmt->fetchAll();
        
        foreach ($devices as $device) {
            // Send via Firebase or APN
            $this->sendToDevice($device['push_token'], $message, $device['device_type']);
        }
    }
    
    private function extractBudget($notes) {
        // Extract budget from notes using regex
        preg_match('/budget[:\s]*([0-9,]+)/i', $notes, $matches);
        if (isset($matches[1])) {
            return floatval(str_replace(',', '', $matches[1]));
        }
        
        // Default budget assumption
        return 500000;
    }
    
    private function getOrCreateNurtureCampaign() {
        $stmt = $this->pdo->prepare("
            SELECT id FROM campaigns 
            WHERE name = 'Lead Nurture Campaign' 
            AND status = 'running'
        ");
        $stmt->execute();
        $campaign = $stmt->fetch();
        
        if ($campaign) {
            return $campaign['id'];
        }
        
        // Create new nurture campaign
        $stmt = $this->pdo->prepare("
            INSERT INTO campaigns 
            (name, type, status, created_by, created_at) 
            VALUES ('Lead Nurture Campaign', 'nurture', 'running', 1, NOW())
        ");
        $stmt->execute();
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Track lead activity
     */
    public function trackActivity($leadId, $activityType, $activityData, $scoreImpact = 0) {
        $stmt = $this->pdo->prepare("
            INSERT INTO lead_activities 
            (lead_id, activity_type, activity_data, score_impact, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $leadId,
            $activityType,
            json_encode($activityData),
            $scoreImpact
        ]);
        
        // Recalculate score if significant activity
        if ($scoreImpact !== 0) {
            $this->calculateScore($leadId);
        }
    }
    
    /**
     * Bulk score calculation
     */
    public function bulkCalculateScores() {
        $stmt = $this->pdo->query("
            SELECT id FROM leads 
            WHERE status IN ('new', 'contacted', 'qualified', 'negotiation')
        ");
        $leads = $stmt->fetchAll();
        
        $results = [];
        foreach ($leads as $lead) {
            $results[] = $this->calculateScore($lead['id']);
        }
        
        return $results;
    }
    
    /**
     * Get lead score history
     */
    public function getScoreHistory($leadId, $days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                MAX(score_impact) as score_change,
                COUNT(*) as activities
            FROM lead_activities
            WHERE lead_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        
        $stmt->execute([$leadId, $days]);
        return $stmt->fetchAll();
    }
}

// lead-scoring-dashboard.php - Lead Scoring Dashboard
$pageTitle = 'Lead Scoring Dashboard';
require_once 'config.php';
requirePermission('leads', 'view');

$scoringService = new LeadScoringService($pdo);

// Get lead score distribution
$stmt = $pdo->query("
    SELECT 
        grade,
        COUNT(*) as count,
        AVG(score) as avg_score
    FROM lead_scores ls
    JOIN leads l ON ls.lead_id = l.id
    WHERE l.status NOT IN ('converted', 'lost')
    GROUP BY grade
    ORDER BY grade
");
$scoreDistribution = $stmt->fetchAll();

// Get top scored leads
$stmt = $pdo->query("
    SELECT 
        l.*,
        ls.score,
        ls.grade,
        ls.factors,
        u.full_name as agent_name
    FROM leads l
    JOIN lead_scores ls ON l.id = ls.lead_id
    LEFT JOIN users u ON l.assigned_to = u.id
    WHERE l.status NOT IN ('converted', 'lost')
    ORDER BY ls.score DESC
    LIMIT 20
");
$topLeads = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Lead Scoring Dashboard</h1>
            <p class="text-gray-600 mt-1">AI-powered lead prioritization</p>
        </div>
        <button onclick="recalculateAllScores()" class="px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90">
            <i class="fas fa-sync mr-2"></i>Recalculate All Scores
        </button>
    </div>
    
    <!-- Score Distribution -->
    <div class="grid grid-cols-5 gap-4 mb-6">
        <?php
        $gradeColors = [
            'A' => 'green',
            'B' => 'blue',
            'C' => 'yellow',
            'D' => 'orange',
            'E' => 'red'
        ];
        
        foreach (['A', 'B', 'C', 'D', 'E'] as $grade):
            $data = array_filter($scoreDistribution, fn($d) => $d['grade'] === $grade);
            $data = reset($data) ?: ['count' => 0, 'avg_score' => 0];
            $color = $gradeColors[$grade];
        ?>
        <div class="bg-white rounded-lg shadow p-4 border-t-4 border-<?php echo $color; ?>-500">
            <div class="text-center">
                <div class="text-3xl font-bold text-<?php echo $color; ?>-600 mb-2">
                    Grade <?php echo $grade; ?>
                </div>
                <div class="text-2xl font-bold"><?php echo $data['count']; ?></div>
                <div class="text-sm text-gray-600">leads</div>
                <div class="text-xs text-gray-500 mt-2">
                    Avg: <?php echo number_format($data['avg_score'], 1); ?> pts
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Top Leads Table -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h2 class="text-lg font-bold">Top Scored Leads</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Lead</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Grade</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Breakdown</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Agent</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($topLeads as $lead): 
                        $factors = json_decode($lead['factors'], true);
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-semibold"><?php echo sanitize($lead['full_name']); ?></p>
                                <p class="text-xs text-gray-600"><?php echo sanitize($lead['phone']); ?></p>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <div class="w-20 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: <?php echo $lead['score']; ?>%"></div>
                                </div>
                                <span class="font-bold"><?php echo $lead['score']; ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-3 py-1 text-sm font-bold rounded-full bg-<?php echo $gradeColors[$lead['grade']]; ?>-100 text-<?php echo $gradeColors[$lead['grade']]; ?>-800">
                                <?php echo $lead['grade']; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div class="space-y-1">
                                <?php foreach ($factors as $factor => $value): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600"><?php echo ucfirst($factor); ?>:</span>
                                    <span class="font-semibold"><?php echo $value; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                <?php echo ucfirst($lead['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo $lead['agent_name'] ? sanitize($lead['agent_name']) : '<span class="text-gray-400">Unassigned</span>'; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a href="/leads.php?action=view&id=<?php echo $lead['id']; ?>" 
                                   class="text-primary hover:underline text-sm">View</a>
                                <?php if (!$lead['assigned_to']): ?>
                                <button onclick="assignLead(<?php echo $lead['id']; ?>)" 
                                        class="text-green-600 hover:underline text-sm">Assign</button>
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

<script>
function recalculateAllScores() {
    if (!confirm('This will recalculate scores for all active leads. Continue?')) return;
    
    fetch('/api/leads/recalculate-scores.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Scores recalculated successfully!');
            location.reload();
        }
    });
}

function assignLead(leadId) {
    fetch('/api/leads/auto-assign.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({lead_id: leadId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Lead assigned successfully!');
            location.reload();
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>