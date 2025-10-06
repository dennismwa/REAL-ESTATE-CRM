<artifacts>
<artifact id="ai-prediction-service" type="application/vnd.ant.code" language="php" title="AIPredictionService.php">
<?php
// app/services/AIPredictionService.php
class AIPredictionService {
private $pdo;
public function __construct($pdo) {
    $this->pdo = $pdo;
}

/**
 * Predict lead conversion probability
 */
public function predictLeadConversion($leadId) {
    $stmt = $this->pdo->prepare("
        SELECT l.*, 
               ls.score as lead_score,
               COUNT(DISTINCT la.id) as activity_count,
               DATEDIFF(NOW(), l.created_at) as days_since_creation
        FROM leads l
        LEFT JOIN lead_scores ls ON l.id = ls.lead_id
        LEFT JOIN lead_activities la ON l.id = la.lead_id
        WHERE l.id = ?
        GROUP BY l.id
    ");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        return 0;
    }
    
    // Simple prediction model based on multiple factors
    $probability = 0;
    
    // Lead score factor (40% weight)
    if ($lead['lead_score']) {
        $probability += ($lead['lead_score'] / 100) * 0.4;
    }
    
    // Activity factor (30% weight)
    $activityScore = min($lead['activity_count'] / 10, 1);
    $probability += $activityScore * 0.3;
    
    // Status factor (20% weight)
    $statusScores = [
        'new' => 0.1,
        'contacted' => 0.3,
        'qualified' => 0.6,
        'negotiation' => 0.8,
        'converted' => 1.0,
        'lost' => 0
    ];
    $probability += ($statusScores[$lead['status']] ?? 0) * 0.2;
    
    // Time factor (10% weight) - faster progress = higher probability
    $daysSinceCreation = $lead['days_since_creation'];
    if ($daysSinceCreation < 7) {
        $probability += 0.1;
    } elseif ($daysSinceCreation < 14) {
        $probability += 0.08;
    } elseif ($daysSinceCreation < 30) {
        $probability += 0.05;
    } else {
        $probability += 0.02;
    }
    
    // Save prediction
    $this->savePrediction('lead', $leadId, 'conversion_probability', [
        'probability' => $probability,
        'factors' => [
            'lead_score' => $lead['lead_score'],
            'activity_count' => $lead['activity_count'],
            'days_since_creation' => $daysSinceCreation,
            'status' => $lead['status']
        ]
    ], $probability);
    
    return $probability;
}

/**
 * Predict sales revenue for next period
 */
public function predictRevenue($months = 3) {
    // Get historical sales data
    $stmt = $this->pdo->query("
        SELECT 
            DATE_FORMAT(sale_date, '%Y-%m') as month,
            COUNT(*) as sales_count,
            SUM(sale_price) as revenue
        FROM sales
        WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND status != 'cancelled'
        GROUP BY month
        ORDER BY month
    ");
    $historicalData = $stmt->fetchAll();
    
    if (count($historicalData) < 3) {
        return ['error' => 'Insufficient historical data'];
    }
    
    // Calculate trend using linear regression
    $n = count($historicalData);
    $x = range(1, $n);
    $y = array_column($historicalData, 'revenue');
    
    $sumX = array_sum($x);
    $sumY = array_sum($y);
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $sumXY += $x[$i] * $y[$i];
        $sumX2 += $x[$i] * $x[$i];
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    
    // Generate predictions
    $predictions = [];
    for ($i = 1; $i <= $months; $i++) {
        $predictedValue = $slope * ($n + $i) + $intercept;
        $month = date('Y-m', strtotime("+$i month"));
        
        // Add seasonal adjustment
        $monthNum = date('n', strtotime("+$i month"));
        $seasonalFactor = $this->getSeasonalFactor($monthNum);
        $adjustedValue = $predictedValue * $seasonalFactor;
        
        $predictions[] = [
            'month' => $month,
            'predicted_revenue' => max(0, $adjustedValue),
            'confidence' => max(0.5, 0.9 - (0.05 * $i)), // Confidence decreases with time
            'trend' => $slope > 0 ? 'increasing' : 'decreasing'
        ];
    }
    
    // Save prediction
    $this->savePrediction('revenue', 0, 'monthly_forecast', [
        'predictions' => $predictions,
        'historical_data' => $historicalData,
        'trend_slope' => $slope
    ], 0.75);
    
    return $predictions;
}

/**
 * Predict plot demand
 */
public function predictPlotDemand($projectId) {
    // Get project data
    $stmt = $this->pdo->prepare("
        SELECT 
            p.*,
            COUNT(DISTINCT pl.id) as total_plots,
            COUNT(DISTINCT CASE WHEN pl.status = 'sold' THEN pl.id END) as sold_plots,
            COUNT(DISTINCT CASE WHEN pl.status = 'available' THEN pl.id END) as available_plots,
            COUNT(DISTINCT sv.id) as site_visits,
            COUNT(DISTINCT l.id) as leads_interested
        FROM projects p
        LEFT JOIN plots pl ON p.id = pl.project_id
        LEFT JOIN site_visits sv ON p.id = sv.project_id
        LEFT JOIN leads l ON l.notes LIKE CONCAT('%', p.project_name, '%')
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        return 0;
    }
    
    // Calculate demand score
    $demandScore = 0;
    
    // Sales velocity (40% weight)
    if ($project['total_plots'] > 0) {
        $soldPercentage = ($project['sold_plots'] / $project['total_plots']) * 100;
        $demandScore += min($soldPercentage / 100, 1) * 0.4;
    }
    
    // Lead interest (30% weight)
    $leadInterestScore = min($project['leads_interested'] / 20, 1);
    $demandScore += $leadInterestScore * 0.3;
    
    // Site visit activity (30% weight)
    $siteVisitScore = min($project['site_visits'] / 10, 1);
    $demandScore += $siteVisitScore * 0.3;
    
    // Predict sales for next 30 days
    $predictedSales = round($demandScore * $project['available_plots'] * 0.3);
    
    $prediction = [
        'demand_score' => $demandScore,
        'demand_level' => $this->getDemandLevel($demandScore),
        'predicted_sales_30_days' => $predictedSales,
        'recommended_action' => $this->getRecommendedAction($demandScore, $project['available_plots'])
    ];
    
    // Save prediction
    $this->savePrediction('project', $projectId, 'plot_demand', $prediction, $demandScore);
    
    return $prediction;
}

/**
 * Predict customer churn risk
 */
public function predictChurnRisk($clientId) {
    $stmt = $this->pdo->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT s.id) as total_purchases,
            MAX(s.sale_date) as last_purchase_date,
            SUM(s.balance) as outstanding_balance,
            COUNT(DISTINCT p.id) as payment_count,
            MAX(p.payment_date) as last_payment_date,
            DATEDIFF(NOW(), MAX(p.payment_date)) as days_since_payment
        FROM clients c
        LEFT JOIN sales s ON c.id = s.client_id
        LEFT JOIN payments p ON s.id = p.sale_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    
    if (!$client) {
        return 0;
    }
    
    $churnRisk = 0;
    
    // Payment behavior (40% weight)
    if ($client['outstanding_balance'] > 0) {
        if ($client['days_since_payment'] > 90) {
            $churnRisk += 0.4;
        } elseif ($client['days_since_payment'] > 60) {
            $churnRisk += 0.3;
        } elseif ($client['days_since_payment'] > 30) {
            $churnRisk += 0.2;
        } else {
            $churnRisk += 0.1;
        }
    }
    
    // Engagement level (30% weight)
    $daysSinceLastPurchase = $client['last_purchase_date'] ? 
        (strtotime('now') - strtotime($client['last_purchase_date'])) / 86400 : 999;
    
    if ($daysSinceLastPurchase > 180) {
        $churnRisk += 0.3;
    } elseif ($daysSinceLastPurchase > 90) {
        $churnRisk += 0.2;
    } else {
        $churnRisk += 0.05;
    }
    
    // Communication responsiveness (30% weight)
    // Check recent communication history
    $stmt = $this->pdo->prepare("
        SELECT COUNT(*) as comm_count
        FROM communications
        WHERE recipient_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$clientId]);
    $commData = $stmt->fetch();
    
    if ($commData['comm_count'] == 0) {
        $churnRisk += 0.3;
    } elseif ($commData['comm_count'] < 3) {
        $churnRisk += 0.15;
    }
    
    $riskLevel = $this->getRiskLevel($churnRisk);
    
    // Save prediction
    $this->savePrediction('client', $clientId, 'churn_risk', [
        'risk_score' => $churnRisk,
        'risk_level' => $riskLevel,
        'factors' => [
            'outstanding_balance' => $client['outstanding_balance'],
            'days_since_payment' => $client['days_since_payment'],
            'days_since_purchase' => $daysSinceLastPurchase
        ],
        'recommended_actions' => $this->getChurnPreventionActions($churnRisk)
    ], $churnRisk);
    
    return [
        'risk_score' => $churnRisk,
        'risk_level' => $riskLevel
    ];
}

/**
 * Get seasonal factor for revenue prediction
 */
private function getSeasonalFactor($month) {
    // Based on real estate seasonality in Kenya
    $factors = [
        1 => 1.1,   // January - New Year activity
        2 => 0.9,   // February
        3 => 1.0,   // March
        4 => 1.0,   // April
        5 => 0.95,  // May
        6 => 1.05,  // June - Mid-year
        7 => 1.0,   // July
        8 => 0.9,   // August
        9 => 1.1,   // September - Q3 push
        10 => 1.15, // October - Year-end preparation
        11 => 1.2,  // November - High activity
        12 => 1.3   // December - Year-end rush
    ];
    
    return $factors[$month] ?? 1.0;
}

/**
 * Get demand level from score
 */
private function getDemandLevel($score) {
    if ($score >= 0.8) return 'Very High';
    if ($score >= 0.6) return 'High';
    if ($score >= 0.4) return 'Moderate';
    if ($score >= 0.2) return 'Low';
    return 'Very Low';
}

/**
 * Get recommended action based on demand
 */
private function getRecommendedAction($demandScore, $availablePlots) {
    if ($demandScore >= 0.8 && $availablePlots < 10) {
        return 'Consider price increase or launch new phase';
    } elseif ($demandScore >= 0.6) {
        return 'Maintain current strategy, monitor closely';
    } elseif ($demandScore >= 0.4) {
        return 'Increase marketing efforts';
    } else {
        return 'Review pricing strategy and boost promotions';
    }
}

/**
 * Get risk level from score
 */
private function getRiskLevel($score) {
    if ($score >= 0.8) return 'Critical';
    if ($score >= 0.6) return 'High';
    if ($score >= 0.4) return 'Medium';
    if ($score >= 0.2) return 'Low';
    return 'Very Low';
}

/**
 * Get churn prevention actions
 */
private function getChurnPreventionActions($riskScore) {
    $actions = [];
    
    if ($riskScore >= 0.8) {
        $actions[] = 'Immediate personal call from management';
        $actions[] = 'Offer payment restructuring options';
        $actions[] = 'Schedule urgent meeting';
    } elseif ($riskScore >= 0.6) {
        $actions[] = 'Personal follow-up from assigned agent';
        $actions[] = 'Send payment reminder with flexible options';
        $actions[] = 'Offer incentives for prompt payment';
    } elseif ($riskScore >= 0.4) {
        $actions[] = 'Send personalized communication';
        $actions[] = 'Share project updates and progress';
        $actions[] = 'Invite to site visit';
    } else {
        $actions[] = 'Maintain regular communication';
        $actions[] = 'Send newsletter and updates';
    }
    
    return $actions;
}

/**
 * Save prediction to database
 */
private function savePrediction($entityType, $entityId, $predictionType, $data, $confidenceScore) {
    $stmt = $this->pdo->prepare("
        INSERT INTO ai_predictions 
        (entity_type, entity_id, prediction_type, prediction_data, confidence_score, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $entityType,
        $entityId,
        $predictionType,
        json_encode($data),
        $confidenceScore
    ]);
}

/**
 * Get latest predictions
 */
public function getLatestPredictions($limit = 10) {
    $stmt = $this->pdo->prepare("
        SELECT * FROM ai_predictions 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll();
}

/**
 * Predict best time to contact lead
 */
public function predictBestContactTime($leadId) {
    // Analyze historical successful contact patterns
    $stmt = $this->pdo->prepare("
        SELECT 
            HOUR(created_at) as contact_hour,
            DAYOFWEEK(created_at) as contact_day,
            COUNT(*) as success_count
        FROM lead_activities
        WHERE lead_id = ? 
        AND activity_type IN ('contacted', 'meeting_scheduled', 'qualified')
        GROUP BY contact_hour, contact_day
        ORDER BY success_count DESC
    ");
    $stmt->execute([$leadId]);
    $patterns = $stmt->fetchAll();
    
    if (empty($patterns)) {
        // Use general patterns
        return [
            'best_time' => '10:00 AM - 12:00 PM',
            'best_days' => ['Tuesday', 'Wednesday', 'Thursday'],
            'confidence' => 0.6
        ];
    }
    
    // Analyze patterns
    $bestHour = $patterns[0]['contact_hour'];
    $bestDay = $patterns[0]['contact_day'];
    
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    return [
        'best_time' => $this->formatHourRange($bestHour),
        'best_days' => [$dayNames[$bestDay - 1]],
        'confidence' => min(0.9, 0.6 + (count($patterns) * 0.05))
    ];
}

/**
 * Format hour range
 */
private function formatHourRange($hour) {
    $start = $hour % 12 ?: 12;
    $end = ($hour + 2) % 12 ?: 12;
    $startPeriod = $hour < 12 ? 'AM' : 'PM';
    $endPeriod = ($hour + 2) < 12 ? 'AM' : 'PM';
    
    return "$start:00 $startPeriod - $end:00 $endPeriod";
}
}
</artifact>
</artifacts>
