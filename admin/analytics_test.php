<?php
/**
 * ANALYTICS DASHBOARD TEST
 * (No authentication - for testing)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/AnalyticsEngine.php';

// For testing: simulate admin access
$_SESSION['admin_id'] = 1;

$analytics = new AnalyticsEngine($pdo, 'XK');
$days = intval($_GET['days'] ?? 30);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analytics AI - Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #b8962e;
            --secondary: #1a1410;
            --accent: #d4af37;
            --light: #faf7f2;
            --danger: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
        }

        body {
            background: linear-gradient(135deg, var(--secondary) 0%, #2d241f 100%);
            color: var(--secondary);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            margin-bottom: 40px;
            color: var(--light);
        }

        .header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem;
            font-weight: 300;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: rgba(250, 247, 242, 0.7);
            font-size: 1.1rem;
        }

        .controls {
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-group-period {
            display: flex;
            gap: 10px;
            background: rgba(255, 255, 255, 0.05);
            padding: 8px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(184, 150, 46, 0.2);
        }

        .btn-period {
            padding: 8px 16px;
            border: none;
            background: transparent;
            color: var(--light);
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-period.active {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: var(--light);
            box-shadow: 0 4px 15px rgba(184, 150, 46, 0.3);
        }

        .btn-period:hover {
            background: rgba(184, 150, 46, 0.1);
        }

        .btn-reload {
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--success), #20c997);
            border: none;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.3);
        }

        .btn-reload.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(184, 150, 46, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            color: var(--light);
            transition: all 0.3s ease;
        }

        .metric-card:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(184, 150, 46, 0.4);
            transform: translateY(-5px);
        }

        .metric-title {
            color: rgba(250, 247, 242, 0.7);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .metric-change {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .metric-change.up {
            color: var(--success);
        }

        .metric-change.down {
            color: var(--danger);
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(184, 150, 46, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .chart-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            color: var(--light);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title i {
            color: var(--primary);
        }

        .insights-container {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(184, 150, 46, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            color: var(--light);
        }

        .insights-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--primary);
            font-size: 1.1rem;
            min-height: 200px;
        }

        .spinner-border {
            color: var(--primary);
        }

        .insight-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(184, 150, 46, 0.2);
        }

        .insight-section:last-child {
            border-bottom: none;
        }

        .insight-title {
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .insight-text {
            color: rgba(250, 247, 242, 0.85);
            line-height: 1.6;
        }

        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(184, 150, 46, 0.05);
            border-left: 3px solid var(--primary);
            margin-bottom: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .service-item:hover {
            background: rgba(184, 150, 46, 0.1);
            transform: translateX(5px);
        }

        .service-name {
            font-weight: 600;
            color: var(--light);
        }

        .service-stats {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .stat-badge {
            background: rgba(184, 150, 46, 0.15);
            padding: 5px 12px;
            border-radius: 20px;
            color: var(--accent);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .recommendation-card {
            background: rgba(243, 156, 18, 0.1);
            border-left: 4px solid var(--warning);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .recommendation-title {
            font-weight: 700;
            color: var(--warning);
            margin-bottom: 5px;
        }

        .recommendation-text {
            color: rgba(250, 247, 242, 0.85);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .controls {
                flex-direction: column;
                align-items: flex-start;
            }

            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
        }

        .text-muted {
            color: rgba(250, 247, 242, 0.5);
        }

        .loading-state {
            text-align: center;
            padding: 40px;
            color: var(--light);
        }

        .error-state {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #ecf0f1;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
            <p>Smart insights powered by Claude AI</p>
        </div>

        <!-- Controls -->
        <div class="controls">
            <div class="btn-group-period">
                <button class="btn-period active" data-days="7">7 Ditë</button>
                <button class="btn-period" data-days="30">30 Ditë</button>
                <button class="btn-period" data-days="60">60 Ditë</button>
                <button class="btn-period" data-days="90">90 Ditë</button>
            </div>
            <button class="btn-reload" id="btn-reload">
                <i class="fas fa-sync-alt"></i> <span>Përditëso</span>
            </button>
        </div>

        <!-- Summary Metrics -->
        <div class="grid-3" id="metrics-container">
            <div class="metric-card">
                <div class="metric-title"><i class="fas fa-wallet"></i> Total Revenue</div>
                <div class="metric-value">€<span id="total-revenue">0</span></div>
                <div class="metric-change up"><i class="fas fa-arrow-up"></i> <span id="revenue-change">0</span>%</div>
            </div>

            <div class="metric-card">
                <div class="metric-title"><i class="fas fa-book"></i> Reservations</div>
                <div class="metric-value" id="total-reservations">0</div>
                <div class="metric-change up"><i class="fas fa-arrow-up"></i> <span id="booking-change">0</span>%</div>
            </div>

            <div class="metric-card">
                <div class="metric-title"><i class="fas fa-chart-bar"></i> Average Payment</div>
                <div class="metric-value">€<span id="avg-payment">0</span></div>
                <div class="text-muted" id="payment-range">0€ - 0€</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid-2">
            <!-- Revenue Trend Chart -->
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-line-chart"></i> Revenue Trend
                </div>
                <canvas id="revenue-chart"></canvas>
            </div>

            <!-- Services Performance Chart -->
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-bars"></i> Top Services
                </div>
                <canvas id="services-chart"></canvas>
            </div>
        </div>

        <!-- AI Insights Section -->
        <div class="insights-container">
            <div class="chart-title">
                <i class="fas fa-brain"></i> AI Insights by Claude
            </div>
            <div id="insights-content" class="insights-loading">
                <div class="spinner-border" role="status"></div>
                <span>Analyzing data with Claude AI...</span>
            </div>
        </div>

        <!-- Services Performance Table -->
        <div class="chart-container">
            <div class="chart-title">
                <i class="fas fa-layer-group"></i> Service Performance
            </div>
            <div id="services-list"></div>
        </div>

        <!-- Price Recommendations -->
        <div class="chart-container">
            <div class="chart-title">
                <i class="fas fa-lightbulb"></i> Price Recommendations
            </div>
            <div id="recommendations-list"></div>
        </div>

        <!-- Forecast Section -->
        <div class="chart-container">
            <div class="chart-title">
                <i class="fas fa-crystal-ball"></i> 7-Day Forecast
            </div>
            <div id="forecast-list"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        let currentDays = 7;
        let revenueChart = null;
        let servicesChart = null;

        $(document).ready(function() {
            loadAnalytics(currentDays);
            setupEventListeners();
        });

        function setupEventListeners() {
            $('.btn-period').on('click', function() {
                $('.btn-period').removeClass('active');
                $(this).addClass('active');
                currentDays = $(this).data('days');
                loadAnalytics(currentDays);
            });

            $('#btn-reload').on('click', function() {
                $(this).addClass('loading');
                loadAnalytics(currentDays);
                setTimeout(() => $(this).removeClass('loading'), 3000);
            });
        }

        function loadAnalytics(days) {
            // Get the base URL
            const baseUrl = window.location.origin + window.location.pathname.split('admin')[0];
            const apiUrl = baseUrl + 'api/analytics_api.php?test=1';
            
            // Load summary
            $.ajax({
                url: apiUrl,
                type: 'GET',
                data: { action: 'summary', days: days },
                dataType: 'json',
                success: function(data) {
                    console.log('Analytics data:', data);
                    updateMetrics(data);
                    updateCharts(data);
                    updateServicesList(data.services);
                    updateRecommendations(data.recommendations);
                    updateForecast(data.forecast);
                },
                error: function(xhr, status, error) {
                    console.error('Error loading analytics:', error, xhr);
                    showError('Failed to load analytics data: ' + xhr.status + ' ' + error);
                }
            });

            // Load AI insights
            $.ajax({
                url: apiUrl,
                type: 'GET',
                data: { action: 'ai_insights', days: days },
                dataType: 'json',
                success: function(data) {
                    const insights = data.ai_insights || { message: 'Unable to load AI insights' };
                    
                    if (insights.status === 'success') {
                        const content = insights.insights || insights.message || 'No insights available';
                        const isFallback = insights.is_fallback ? ' <small style="opacity: 0.7;">[Auto-Analysis]</small>' : ' <small style="opacity: 0.7;">[Claude AI]</small>';
                        const html = '<div class="text-light"><pre style="font-family: var(--font); color: #e8d5b7; font-size: 13px; line-height: 1.6; margin: 0;">' + escapeHtml(content) + '</pre>' + isFallback + '</div>';
                        $('#insights-content').html(html);
                    } else {
                        // Check if it's a credit issue
                        if (insights.details && insights.details.includes('credit')) {
                            const html = `
                                <div style="color: #b8962e; padding: 15px; background: rgba(184,150,46,0.1); border-radius: 8px; border-left: 3px solid #b8962e; text-align: center;">
                                    <strong style="display: block; margin-bottom: 8px;">💳 Add Credits to Unlock Claude AI</strong>
                                    <p style="margin: 8px 0; font-size: 0.95rem;">Your Anthropic account needs credits for AI insights.</p>
                                    <a href="https://console.anthropic.com/account/billing/overview" target="_blank" style="color: #b8962e; text-decoration: underline; display: inline-block; margin: 10px 0;">Add Credits →</a>
                                    <p style="margin: 8px 0; font-size: 0.85rem; opacity: 0.7;">Refresh after adding credits to see Claude AI insights</p>
                                </div>
                            `;
                            $('#insights-content').html(html);
                        } else {
                            const html = '<div style="color: #ff6b6b; padding: 15px; background: rgba(255,107,107,0.1); border-radius: 8px; border-left: 3px solid #ff6b6b;"><strong>⚠️ API Error:</strong> ' + escapeHtml(insights.message || 'Unknown error') + '</div>';
                            $('#insights-content').html(html);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading insights:', error);
                    $('#insights-content').html('<div style="color: #ff6b6b; padding: 15px; background: rgba(255,107,107,0.1); border-radius: 8px; border-left: 3px solid #ff6b6b;"><strong>⚠️ Load Error:</strong> ' + escapeHtml(error) + '</div>');
                }
            });
        }

        function updateMetrics(data) {
            const revenue = data.revenue || {};
            const total = parseFloat(revenue.total_revenue) || 0;
            const reservations = parseInt(revenue.total_reservations) || 0;
            const avg = parseFloat(revenue.avg_payment) || 0;
            const min = parseFloat(revenue.min_payment) || 0;
            const max = parseFloat(revenue.max_payment) || 0;

            $('#total-revenue').text(total.toFixed(2));
            $('#total-reservations').text(reservations);
            $('#avg-payment').text(avg.toFixed(2));
            $('#payment-range').text(min.toFixed(2) + '€ - ' + max.toFixed(2) + '€');
        }

        function updateCharts(data) {
            // Revenue Trend Chart
            const trend = data.trend ? (data.trend.data || []) : [];
            const trendDates = trend.map(t => t.date.substring(5));
            const trendRevenue = trend.map(t => parseFloat(t.revenue) || 0);

            const revenueCtx = document.getElementById('revenue-chart')?.getContext('2d');
            if (revenueCtx && trendDates.length > 0) {
                if (revenueChart) revenueChart.destroy();
                revenueChart = new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: trendDates,
                        datasets: [{
                            label: 'Daily Revenue (€)',
                            data: trendRevenue,
                            borderColor: '#b8962e',
                            backgroundColor: 'rgba(184, 150, 46, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#d4af37',
                            pointBorderColor: '#b8962e',
                            pointRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                ticks: { color: '#faf7f2' },
                                grid: { color: 'rgba(184, 150, 46, 0.1)' }
                            },
                            x: {
                                ticks: { color: '#faf7f2' },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // Services Chart
            const services = data.services || [];
            const serviceNames = services.slice(0, 5).map(s => s.service_name_sq);
            const serviceRevenue = services.slice(0, 5).map(s => parseFloat(s.total_revenue) || 0);

            const servicesCtx = document.getElementById('services-chart')?.getContext('2d');
            if (servicesCtx && serviceNames.length > 0) {
                if (servicesChart) servicesChart.destroy();
                servicesChart = new Chart(servicesCtx, {
                    type: 'bar',
                    data: {
                        labels: serviceNames,
                        datasets: [{
                            label: 'Revenue (€)',
                            data: serviceRevenue,
                            backgroundColor: [
                                'rgba(184, 150, 46, 0.8)',
                                'rgba(212, 175, 55, 0.8)',
                                'rgba(139, 112, 37, 0.8)',
                                'rgba(184, 150, 46, 0.6)',
                                'rgba(212, 175, 55, 0.6)'
                            ],
                            borderColor: '#b8962e',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: {
                            x: {
                                ticks: { color: '#faf7f2' },
                                grid: { color: 'rgba(184, 150, 46, 0.1)' }
                            },
                            y: {
                                ticks: { color: '#faf7f2' },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        }

        function updateServicesList(services) {
            let html = '';
            services.forEach(service => {
                const price = parseFloat(service.base_price) || 0;
                const revenue = parseFloat(service.total_revenue) || 0;
                const completion = service.completed / (service.completed + service.pending) * 100;
                html += `
                    <div class="service-item">
                        <div>
                            <div class="service-name">${escapeHtml(service.service_name_sq)}</div>
                            <small class="text-muted">€${price.toFixed(2)} base price</small>
                        </div>
                        <div class="service-stats">
                            <span class="stat-badge"><i class="fas fa-check"></i> ${service.bookings} Reservations</span>
                            <span class="stat-badge" style="color: #27ae60;">€${revenue.toFixed(2)}</span>
                        </div>
                    </div>
                `;
            });
            $('#services-list').html(html || '<p class="text-muted">No data available</p>');
        }

        function updateRecommendations(recommendations) {
            let html = '';
            recommendations.slice(0, 5).forEach(rec => {
                const currentPrice = parseFloat(rec.current_price) || 0;
                const suggestedPrice = parseFloat(rec.suggested_price) || 0;
                if (suggestedPrice !== currentPrice) {
                    const change = ((suggestedPrice - currentPrice) / currentPrice * 100).toFixed(1);
                    html += `
                        <div class="recommendation-card">
                            <div class="recommendation-title">
                                ${suggestedPrice > currentPrice ? '📈' : '📉'} ${escapeHtml(rec.service)}
                            </div>
                            <div class="recommendation-text">
                                <strong>Current:</strong> €${currentPrice.toFixed(2)} 
                                <strong>→ Suggested:</strong> €${suggestedPrice.toFixed(2)} 
                                <strong style="color: ${suggestedPrice > currentPrice ? '#27ae60' : 'var(--danger)'};">
                                    (${suggestedPrice > currentPrice ? '+' : ''}${change}%)
                                </strong>
                                <br>
                                <em>${escapeHtml(rec.reason)}</em>
                                <br>
                                <small class="text-muted">Confidence: ${rec.confidence}%</small>
                            </div>
                        </div>
                    `;
                }
            });
            $('#recommendations-list').html(html || '<p class="text-muted">All services are optimally priced</p>');
        }

        function updateForecast(forecast) {
            if (!forecast || !forecast.data || forecast.data.length === 0) {
                $('#forecast-list').html('<p class="text-muted">Not enough data for forecast</p>');
                return;
            }

            const slope = parseFloat(forecast.slope) || 0;
            let html = '<div style="color: var(--light);">';
            html += '<p><strong>Trend:</strong> ' + (forecast.trend === 'up' ? '📈 Upward' : '📉 Downward') + '</p>';
            html += '<p><strong>Daily Change:</strong> ' + slope.toFixed(2) + '€/day</p>';
            html += '<div style="margin-top: 15px;">';

            forecast.data.forEach(item => {
                const revenue = parseFloat(item.predicted_revenue) || 0;
                const confidence = parseFloat(item.confidence) || 0;
                html += `
                    <div class="service-item">
                        <div>
                            <div class="service-name">${item.date}</div>
                            <small class="text-muted">Predicted Revenue</small>
                        </div>
                        <div class="service-stats">
                            <span class="stat-badge">€${revenue.toFixed(2)}</span>
                            <span class="stat-badge">✓ ${confidence.toFixed(0)}%</span>
                        </div>
                    </div>
                `;
            });

            html += '</div></div>';
            $('#forecast-list').html(html);
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function showError(message) {
            const errorHtml = '<div class="error-state"><i class="fas fa-exclamation-circle"></i> ' + message + '</div>';
            $('#metrics-container').prepend(errorHtml);
        }
    </script>
</body>
</html>
