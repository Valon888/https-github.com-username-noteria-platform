<?php
/**
 * ANALYTICS AI API
 * Integrates Claude AI with analytics data
 * Generates intelligent business insights
 */

header('Content-Type: application/json');

// Initialize session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/AnalyticsEngine.php';

// For testing: allow without authentication via GET parameter
$allow_test = (isset($_GET['test']) || isset($_POST['test']) || (php_sapi_name() === 'cli'));
if (!$allow_test && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Set a test admin session for testing
if (($allow_test || php_sapi_name() === 'cli') && !isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
}

try {
    $request_type = $_GET['action'] ?? $_POST['action'] ?? 'summary';
    $days = intval($_GET['days'] ?? $_POST['days'] ?? 30);
    $debug = isset($_GET['debug']);
    
    if ($debug) {
        error_log("Analytics API: action=$request_type, days=$days");
    }
    
    $analytics = new AnalyticsEngine($pdo, 'XK');
    
    switch ($request_type) {
        case 'summary':
            // Get basic analytics summary
            $result = [
                'revenue' => $analytics->getRevenueSummary($days),
                'services' => $analytics->getServicePerformance($days),
                'forecast' => $analytics->forecastRevenue($days),
                'recommendations' => $analytics->getPriceRecommendations(),
                'days' => $days
            ];
            echo json_encode($result);
            break;
            
        case 'ai_insights':
            // Generate AI insights using Claude
            $analysis_data = $analytics->prepareAIAnalysisData($days);
            
            if (!$analysis_data) {
                echo json_encode(['error' => 'Not enough data for analysis']);
                break;
            }
            
            // Call Claude API
            $insights = getClaudeAIInsights($analysis_data);
            echo json_encode([
                'success' => true,
                'analysis_summary' => $analysis_data,
                'ai_insights' => $insights
            ]);
            break;
            
        case 'trend':
            // Get trend data
            $trend = $analytics->getDailyTrend($days);
            echo json_encode(['data' => $trend, 'days' => $days]);
            break;
            
        case 'forecast':
            // Get forecast
            $forecast = $analytics->forecastRevenue($days, 14); // 14 days forecast
            echo json_encode($forecast);
            break;
            
        case 'office_performance':
            // Get office-level analytics
            $offices = $analytics->getOfficePerformance($days);
            echo json_encode(['data' => $offices, 'days' => $days]);
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action: ' . $request_type]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Analytics API Error: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    echo json_encode([
        'error' => 'Error: ' . $e->getMessage(),
        'type' => get_class($e),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
}

/**
 * Call Claude API to generate AI insights
 */
function getClaudeAIInsights($analysis_data) {
    try {
        $api_key = getenv('CLAUDE_API_KEY');
        
        if (!$api_key || strpos($api_key, 'YOUR-API-KEY') !== false) {
            return [
                'status' => 'warning',
                'message' => 'Claude API not configured',
                'details' => 'API key not found or is placeholder'
            ];
        }
        
        // Prepare prompt for Claude
        $prompt = "You are a business analyst for a notary office in Kosovo. Review the following analytics data and provide:
1. Key insights about current business performance
2. Actionable recommendations for revenue growth
3. Risk indicators to watch
4. Suggestions for service optimization

Keep response in Albanian (Shqip) and format as clear sections.

ANALYTICS DATA:\n$analysis_data";

        // Make API call with error handling
        $ch = curl_init();
        
        if ($ch === false) {
            return [
                'status' => 'error',
                'message' => 'cURL not available',
                'details' => 'cURL extension is not enabled'
            ];
        }
        
        curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 2048,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development - in production use proper SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For development
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        
        $response = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Handle curl errors
        if ($curl_errno !== 0) {
            error_log("cURL Error ($curl_errno): $curl_error");
            return [
                'status' => 'error',
                'message' => 'Network error: ' . $curl_error,
                'details' => 'Error ' . $curl_errno
            ];
        }
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            
            if (isset($data['content'][0]['text'])) {
                $content = $data['content'][0]['text'];
                return [
                    'status' => 'success',
                    'insights' => $content
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Unexpected API response format',
                    'details' => json_encode($data)
                ];
            }
        } elseif ($http_code === 401) {
            return [
                'status' => 'error',
                'message' => 'Authentication failed (401)',
                'details' => 'API key is invalid or expired. Get a new key from https://console.anthropic.com/'
            ];
        } elseif ($http_code === 429) {
            return [
                'status' => 'error',
                'message' => 'Rate limited (429)',
                'details' => 'Too many requests. Try again in a moment.'
            ];
        } elseif ($http_code === 400) {
            $error_data = json_decode($response, true);
            $error_msg = $error_data['error']['message'] ?? 'Bad request';
            
            // If it's a credit issue, return fallback insights
            if (strpos($error_msg, 'credit') !== false) {
                return getEmbeddedInsights($analysis_data);
            }
            
            return [
                'status' => 'error',
                'message' => 'API error (400)',
                'details' => $error_msg
            ];
        } else {
            error_log("Claude API Error ($http_code): $response");
            return [
                'status' => 'error',
                'message' => 'API error: ' . $http_code,
                'details' => substr($response, 0, 200)
            ];
        }
        
    } catch (Exception $e) {
        error_log("Exception in getClaudeAIInsights: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}

/**
 * Fallback embedded insights (no API needed)
 * Useful for development and testing
 */
function getEmbeddedInsights($analysis_data) {
    try {
        // Parse the analysis data to generate insights
        $insights = "📊 NOTERIA KOSOVO - BUSINESS INSIGHTS\n";
        $insights .= "=" . str_repeat("=", 40) . "\n\n";
        
        // Extract data from analysis summary
        if (preg_match('/Total Revenue: €([0-9,.]+)/', $analysis_data, $matches)) {
            $revenue = floatval(str_replace(['€', ','], ['', ''], $matches[1]));
            $insights .= "💰 REVENUE ANALYSIS\n";
            $insights .= "Total revenue in period: €" . number_format($revenue, 2) . "\n";
            
            if ($revenue > 500) {
                $insights .= "✅ Strong revenue generation - maintain current service quality\n";
            } elseif ($revenue > 200) {
                $insights .= "🟡 Moderate revenue - consider promotional activities\n";
            } else {
                $insights .= "📈 Low revenue - increase marketing efforts\n";
            }
        }
        
        if (preg_match('/Total Reservations: (\d+)/', $analysis_data, $matches)) {
            $reservations = intval($matches[1]);
            $insights .= "\n📅 BOOKING ANALYSIS\n";
            $insights .= "Total reservations: " . $reservations . "\n";
            
            if ($reservations > 15) {
                $insights .= "✅ Strong booking activity - platform is gaining traction\n";
            } else {
                $insights .= "⚠️  Moderate bookings - increase visibility through marketing\n";
            }
        }
        
        if (preg_match('/Trend: (UP|DOWN)/', $analysis_data, $matches)) {
            $trend = $matches[1];
            $insights .= "\n📈 TREND ANALYSIS\n";
            
            if ($trend === 'UP') {
                $insights .= "✅ Revenue is GROWING\n";
                $insights .= "• Maintain current service quality\n";
                $insights .= "• Consider slight price increases\n";
            } else {
                $insights .= "📉 Revenue is DECLINING\n";
                $insights .= "• Investigate customer feedback\n";
                $insights .= "• Enhance marketing visibility\n";
                $insights .= "• Review pricing strategy\n";
            }
        }
        
        $insights .= "\n💡 RECOMMENDATIONS\n";
        $insights .= "1. Monitor daily revenue trends closely\n";
        $insights .= "2. Gather customer feedback regularly\n";
        $insights .= "3. Optimize service pricing based on demand\n";
        $insights .= "4. Expand marketing to underutilized services\n";
        $insights .= "5. Build loyalty programs for repeat customers\n";
        
        $insights .= "\n⚠️  Note: These are automated insights.\n";
        $insights .= "For detailed Claude AI analysis, add credits to your Anthropic account:\n";
        $insights .= "https://console.anthropic.com/account/billing/overview\n";
        
        return [
            'status' => 'success',
            'insights' => $insights,
            'is_fallback' => true,
            'message' => 'Using embedded analysis (Claude API unavailable)'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Unable to generate insights: ' . $e->getMessage()
        ];
    }
}
?>
