<?php
// Simple direct test of fallback insights

echo "=== FALLBACK INSIGHTS TEST ===\n\n";

// Test data that would come from the API
$analysis_data = <<<'EOD'
NOTERIA ANALYTICS SUMMARY
========================

Total Revenue: €623.40
Total Reservations: 19
Average Payment: €41.56
Min Payment: €20.00
Max Payment: €100.00
Active Days: 14

Top Services:
None recorded in this period

Trend: DOWN
Single day change: -1.52 EUR

DAILY TREND:
2026-03-12: €45.00 (2 reservations)
2026-03-11: €62.55 (1 reservations)
2026-03-10: €83.10 (2 reservations)
EOD;

echo "Test Analysis Data:\n";
echo $analysis_data . "\n\n";

// Now test fallback insights generation
echo "=== GENERATING FALLBACK INSIGHTS ===\n\n";

$insights = "📊 NOTERIA KOSOVO - BUSINESS INSIGHTS\n";
$insights .= "=" . str_repeat("=", 40) . "\n\n";

// Extract data
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

echo "Generated Insights:\n";
echo $insights;

echo "\n=== WHAT THE DASHBOARD WILL DISPLAY ===\n";
echo "Status: success\n";
echo "Is Fallback: true\n";
echo "Will show: [Auto-Analysis] badge\n";
echo "\n=== FALLBACK TEST COMPLETE ===\n";
?>
