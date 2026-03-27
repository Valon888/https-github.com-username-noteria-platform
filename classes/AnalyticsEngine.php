<?php
/**
 * ANALYTICS ENGINE
 * Collects data, analyzes trends, and generates AI insights
 * Powers the Analytics Dashboard
 */

class AnalyticsEngine {
    
    private $db;
    private $country_code = 'XK';
    
    public function __construct($pdo, $country_code = 'XK') {
        $this->db = $pdo;
        $this->country_code = $country_code;
    }
    
    /**
     * Get revenue summary
     */
    public function getRevenueSummary($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_reservations,
                    SUM(p.amount) as total_revenue,
                    AVG(p.amount) as avg_payment,
                    MAX(p.amount) as max_payment,
                    MIN(p.amount) as min_payment,
                    COUNT(DISTINCT DATE(r.date)) as active_days
                FROM reservations r
                LEFT JOIN payments p ON r.id = p.reservation_id
                WHERE r.country_code = :country_code
                AND r.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            
            $stmt->execute([
                'country_code' => $this->country_code,
                'days' => $days
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting revenue summary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get service performance
     */
    public function getServicePerformance($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    r.service,
                    cp.service_name_sq,
                    cp.base_price,
                    COUNT(*) as bookings,
                    SUM(p.amount) as total_revenue,
                    AVG(p.amount) as avg_revenue,
                    COUNT(CASE WHEN p.payment_status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN p.payment_status = 'pending' THEN 1 END) as pending
                FROM reservations r
                LEFT JOIN payments p ON r.id = p.reservation_id
                LEFT JOIN country_pricing cp ON r.service = cp.service_name AND cp.country_code = r.country_code
                WHERE r.country_code = :country_code
                AND r.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY r.service, cp.service_name_sq, cp.base_price
                ORDER BY total_revenue DESC
            ");
            
            $stmt->execute([
                'country_code' => $this->country_code,
                'days' => $days
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting service performance: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get daily revenue trend
     */
    public function getDailyTrend($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(r.created_at) as date,
                    COUNT(*) as reservations,
                    SUM(p.amount) as revenue,
                    AVG(p.amount) as avg_payment
                FROM reservations r
                LEFT JOIN payments p ON r.id = p.reservation_id
                WHERE r.country_code = :country_code
                AND r.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(r.created_at)
                ORDER BY date ASC
            ");
            
            $stmt->execute([
                'country_code' => $this->country_code,
                'days' => $days
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting daily trend: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get office performance (if multiple offices)
     */
    public function getOfficePerformance($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    z.id,
                    z.name as office_name,
                    COUNT(r.id) as reservations,
                    SUM(p.amount) as total_revenue,
                    AVG(p.amount) as avg_payment,
                    COUNT(DISTINCT DATE(r.date)) as active_days
                FROM zyrat z
                LEFT JOIN reservations r ON z.id = r.zyra_id AND r.country_code = :country_code
                LEFT JOIN payments p ON r.id = p.reservation_id
                WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY z.id, z.name
                ORDER BY total_revenue DESC
            ");
            
            $stmt->execute([
                'country_code' => $this->country_code,
                'days' => $days
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting office performance: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate forecast (linear regression)
     */
    public function forecastRevenue($days = 30, $forecast_days = 7) {
        try {
            $trend_data = $this->getDailyTrend($days);
            
            if (count($trend_data) < 3) {
                return [
                    'error' => 'Not enough data for forecast',
                    'data' => []
                ];
            }
            
            // Extract revenue values
            $revenues = array_map(function($item) {
                return $item['revenue'] ?? 0;
            }, $trend_data);
            
            // Simple linear regression
            $n = count($revenues);
            $x_values = range(1, $n);
            
            $sum_x = array_sum($x_values);
            $sum_y = array_sum($revenues);
            $sum_xy = 0;
            $sum_x2 = 0;
            
            for ($i = 0; $i < $n; $i++) {
                $sum_xy += $x_values[$i] * $revenues[$i];
                $sum_x2 += $x_values[$i] * $x_values[$i];
            }
            
            // Calculate slope and intercept
            $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
            $intercept = ($sum_y - $slope * $sum_x) / $n;
            
            // Generate forecast
            $forecast = [];
            $last_date = end($trend_data)['date'];
            $last_date_obj = new DateTime($last_date);
            
            for ($i = 1; $i <= $forecast_days; $i++) {
                $x = $n + $i;
                $predicted_revenue = max(0, $slope * $x + $intercept);
                
                $last_date_obj->add(new DateInterval('P1D'));
                
                $forecast[] = [
                    'date' => $last_date_obj->format('Y-m-d'),
                    'predicted_revenue' => round($predicted_revenue, 2),
                    'confidence' => $this->calculateConfidence($revenues)
                ];
            }
            
            return [
                'data' => $forecast,
                'slope' => $slope,
                'trend' => $slope > 0 ? 'up' : 'down'
            ];
        } catch (Exception $e) {
            error_log("Error forecasting: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate confidence for forecast
     */
    private function calculateConfidence($values) {
        if (count($values) < 2) return 0;
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $val) {
            $variance += pow($val - $mean, 2);
        }
        $variance /= count($values);
        $std_dev = sqrt($variance);
        
        // More stable data = higher confidence
        $cv = $mean > 0 ? $std_dev / $mean : 0;
        return max(0, min(100, 100 - ($cv * 50))); // 0-100%
    }
    
    /**
     * Get price recommendations
     */
    public function getPriceRecommendations() {
        try {
            $performance = $this->getServicePerformance(60);
            $recommendations = [];
            
            foreach ($performance as $service) {
                $bookings = $service['bookings'] ?? 0;
                $base_price = $service['base_price'] ?? 0;
                $avg_revenue = $service['avg_revenue'] ?? 0;
                
                if ($bookings == 0) continue;
                
                // Analysis logic
                $demand = $bookings / 60; // bookings per day average
                $revenue_ratio = $avg_revenue / $base_price;
                
                $recommendation = [
                    'service' => $service['service_name_sq'],
                    'current_price' => $base_price,
                    'suggested_price' => $base_price,
                    'reason' => 'Stable demand',
                    'confidence' => 50
                ];
                
                // High demand + good revenue = increase price
                if ($demand > 0.5 && $revenue_ratio > 1.1) {
                    $recommended = round($base_price * 1.10, 2); // +10%
                    $recommendation = [
                        'service' => $service['service_name_sq'],
                        'current_price' => $base_price,
                        'suggested_price' => $recommended,
                        'reason' => 'High demand observed - customers willing to pay more',
                        'confidence' => 75
                    ];
                }
                // Low demand = decrease price
                elseif ($demand < 0.1 && $bookings > 0) {
                    $recommended = round($base_price * 0.90, 2); // -10%
                    $recommendation = [
                        'service' => $service['service_name_sq'],
                        'current_price' => $base_price,
                        'suggested_price' => $recommended,
                        'reason' => 'Low booking rate - consider price reduction to attract more clients',
                        'confidence' => 60
                    ];
                }
                
                $recommendations[] = $recommendation;
            }
            
            return $recommendations;
        } catch (Exception $e) {
            error_log("Error getting price recommendations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Prepare data for Claude AI analysis
     */
    public function prepareAIAnalysisData($days = 30) {
        try {
            $revenue = $this->getRevenueSummary($days);
            $services = $this->getServicePerformance($days);
            $forecast = $this->forecastRevenue($days);
            $recommendations = $this->getPriceRecommendations();
            
            $summary = "NOTERIA KOSOVO ANALYTICS SUMMARY\n";
            $summary .= "=" . str_repeat("=", 50) . "\n\n";
            
            $summary .= "REVENUE METRICS (Last $days days):\n";
            $summary .= "- Total Revenue: €" . number_format($revenue['total_revenue'] ?? 0, 2) . "\n";
            $summary .= "- Total Reservations: " . ($revenue['total_reservations'] ?? 0) . "\n";
            $summary .= "- Average Payment: €" . number_format($revenue['avg_payment'] ?? 0, 2) . "\n";
            $summary .= "- Active Days: " . ($revenue['active_days'] ?? 0) . "\n\n";
            
            $summary .= "TOP SERVICES:\n";
            foreach (array_slice($services, 0, 5) as $service) {
                $summary .= "- " . $service['service_name_sq'] . ": " . $service['bookings'] . " bookings, €" . number_format($service['total_revenue'] ?? 0, 2) . "\n";
            }
            $summary .= "\n";
            
            if (isset($forecast['data']) && !empty($forecast['data'])) {
                $summary .= "FORECAST (Next 7 days):\n";
                $summary .= "Trend: " . strtoupper($forecast['trend'] ?? 'stable') . "\n";
                $summary .= "- Projected daily revenue increase/decrease: " . round($forecast['slope'] ?? 0, 2) . "€/day\n\n";
            }
            
            $summary .= "PRICE RECOMMENDATIONS:\n";
            foreach ($recommendations as $rec) {
                if ($rec['suggested_price'] != $rec['current_price']) {
                    $change = (($rec['suggested_price'] - $rec['current_price']) / $rec['current_price'] * 100);
                    $summary .= "- " . $rec['service'] . ": €" . $rec['current_price'] . " → €" . $rec['suggested_price'] . " (" . ($change > 0 ? "+" : "") . round($change, 1) . "%)\n";
                    $summary .= "  Reason: " . $rec['reason'] . "\n";
                }
            }
            
            return $summary;
        } catch (Exception $e) {
            error_log("Error preparing AI analysis data: " . $e->getMessage());
            return null;
        }
    }
}
