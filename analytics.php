<?php
/**
 * Analytics Dashboard for AI Drowning Detection System
 * Comprehensive data visualization and reporting
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';

$db = new Database();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_GET['action']) {
            case 'get_analytics':
                $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
                $date_to = $_GET['date_to'] ?? date('Y-m-d');
                $analytics = $db->getAdvancedAnalytics($date_from, $date_to);
                echo json_encode(['success' => true, 'analytics' => $analytics]);
                break;

            case 'get_system_performance':
                $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
                $performance = $db->getSystemStats($hours);
                echo json_encode(['success' => true, 'performance' => $performance]);
                break;

            case 'generate_report':
                $type = $_GET['type'] ?? 'alerts_summary';
                $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
                $date_to = $_GET['date_to'] ?? date('Y-m-d');
                $user_id = $_SESSION['user_id'] ?? null;

                $report_id = $db->generateReport($type, $date_from, $date_to, $user_id);
                if ($report_id) {
                    echo json_encode(['success' => true, 'report_id' => $report_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to generate report']);
                }
                break;

            case 'get_reports':
                $reports = $db->getReports();
                echo json_encode(['success' => true, 'reports' => $reports]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - AI Drowning Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <style>
        .metric-card {
            transition: transform 0.2s;
            border-radius: 10px;
        }
        .metric-card:hover { transform: translateY(-2px); }
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        .alert-trend { color: #dc3545; }
        .motion-trend { color: #28a745; }
        .performance-good { color: #28a745; }
        .performance-warning { color: #ffc107; }
        .performance-critical { color: #dc3545; }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Analytics Dashboard</h1>
                    <div>
                        <a href="index.php" class="btn btn-secondary">← Back to Main</a>
                        <button class="btn btn-primary" onclick="refreshAnalytics()">Refresh Data</button>
                    </div>
                </div>

                <!-- Date Range Filter -->
                <div class="filter-section">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from"
                                   value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to"
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success" onclick="applyDateFilter()">Apply Filter</button>
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-info" onclick="generateReport()">Generate Report</button>
                        </div>
                    </div>
                </div>

                <!-- Key Metrics -->
                <div class="row mb-4" id="metrics-container">
                    <div class="col-md-3">
                        <div class="card metric-card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Drowning Alerts</h5>
                                <h2 id="drowning-alerts">0</h2>
                                <small>Last 30 days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Motion Alerts</h5>
                                <h2 id="motion-alerts">0</h2>
                                <small>Last 30 days</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Active Cameras</h5>
                                <h2 id="active-cameras">0</h2>
                                <small>Currently online</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Avg Response Time</h5>
                                <h2 id="avg-response">0ms</h2>
                                <small>Detection speed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Alert Trends Over Time</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="alertTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Alert Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="alertDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>System Performance</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Motion Detection Patterns</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="motionPatternsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Analytics Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Detailed Analytics</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportAnalytics()">Export Data</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="viewReports()">View Reports</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="analytics-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Drowning Alerts</th>
                                        <th>Motion Alerts</th>
                                        <th>High Severity</th>
                                        <th>Avg Response Time</th>
                                        <th>Active Cameras</th>
                                        <th>Motion Events</th>
                                    </tr>
                                </thead>
                                <tbody id="analytics-tbody">
                                    <tr>
                                        <td colspan="7" class="text-center">Loading analytics data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        let alertTrendsChart, alertDistributionChart, performanceChart, motionPatternsChart;

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            loadAnalytics();
        });

        function initCharts() {
            // Alert Trends Chart
            const alertTrendsCtx = document.getElementById('alertTrendsChart').getContext('2d');
            alertTrendsChart = new Chart(alertTrendsCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Drowning Alerts',
                        data: [],
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Motion Alerts',
                        data: [],
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day'
                            }
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Alert Distribution Chart
            const alertDistCtx = document.getElementById('alertDistributionChart').getContext('2d');
            alertDistributionChart = new Chart(alertDistCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Drowning Alerts', 'Motion Alerts', 'High Severity', 'Other'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: [
                            '#dc3545',
                            '#ffc107',
                            '#fd7e14',
                            '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });

            // Performance Chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            performanceChart = new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'CPU Usage (%)',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Memory Usage (%)',
                        data: [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });

            // Motion Patterns Chart
            const motionCtx = document.getElementById('motionPatternsChart').getContext('2d');
            motionPatternsChart = new Chart(motionCtx, {
                type: 'bar',
                data: {
                    labels: ['Normal Activity', 'Motion Detected', 'Potential Drowning', 'No Activity'],
                    datasets: [{
                        label: 'Frequency',
                        data: [0, 0, 0, 0],
                        backgroundColor: [
                            '#28a745',
                            '#007bff',
                            '#dc3545',
                            '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function loadAnalytics() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;

            // Load analytics data
            fetch(`analytics.php?action=get_analytics&date_from=${dateFrom}&date_to=${dateTo}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateAnalytics(data.analytics);
                    }
                })
                .catch(error => console.error('Error loading analytics:', error));

            // Load performance data
            fetch('analytics.php?action=get_system_performance&hours=24')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updatePerformance(data.performance);
                    }
                })
                .catch(error => console.error('Error loading performance:', error));
        }

        function updateAnalytics(analytics) {
            if (!analytics || analytics.length === 0) {
                document.getElementById('analytics-tbody').innerHTML =
                    '<tr><td colspan="7" class="text-center">No analytics data available</td></tr>';
                return;
            }

            // Update metrics
            const totalDrowning = analytics.reduce((sum, item) => sum + (item.drowning_alerts || 0), 0);
            const totalMotion = analytics.reduce((sum, item) => sum + (item.motion_alerts || 0), 0);
            const avgCameras = analytics.reduce((sum, item) => sum + (item.active_cameras || 0), 0) / analytics.length;
            const avgResponse = analytics.reduce((sum, item) => sum + (item.avg_processing_time || 0), 0) / analytics.length;

            document.getElementById('drowning-alerts').textContent = totalDrowning;
            document.getElementById('motion-alerts').textContent = totalMotion;
            document.getElementById('active-cameras').textContent = Math.round(avgCameras);
            document.getElementById('avg-response').textContent = Math.round(avgResponse) + 'ms';

            // Update table
            const tbody = document.getElementById('analytics-tbody');
            let html = '';
            analytics.forEach(item => {
                html += `
                    <tr>
                        <td>${item.date}</td>
                        <td>${item.drowning_alerts || 0}</td>
                        <td>${item.motion_alerts || 0}</td>
                        <td>${item.high_severity || 0}</td>
                        <td>${Math.round(item.avg_processing_time || 0)}ms</td>
                        <td>${item.active_cameras || 0}</td>
                        <td>${item.total_motion_events || 0}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;

            // Update charts
            updateCharts(analytics);
        }

        function updatePerformance(performance) {
            if (!performance || performance.length === 0) return;

            const labels = performance.map(item => new Date(item.timestamp));
            const cpuData = performance.map(item => item.cpu_usage || 0);
            const memoryData = performance.map(item => item.memory_usage || 0);

            performanceChart.data.labels = labels;
            performanceChart.data.datasets[0].data = cpuData;
            performanceChart.data.datasets[1].data = memoryData;
            performanceChart.update();
        }

        function updateCharts(analytics) {
            // Update alert trends
            const labels = analytics.map(item => item.date);
            const drowningData = analytics.map(item => item.drowning_alerts || 0);
            const motionData = analytics.map(item => item.motion_alerts || 0);

            alertTrendsChart.data.labels = labels;
            alertTrendsChart.data.datasets[0].data = drowningData;
            alertTrendsChart.data.datasets[1].data = motionData;
            alertTrendsChart.update();

            // Update distribution
            const totalDrowning = drowningData.reduce((a, b) => a + b, 0);
            const totalMotion = motionData.reduce((a, b) => a + b, 0);
            const totalHigh = analytics.reduce((sum, item) => sum + (item.high_severity || 0), 0);
            const other = Math.max(0, totalDrowning + totalMotion - totalHigh);

            alertDistributionChart.data.datasets[0].data = [totalDrowning, totalMotion, totalHigh, other];
            alertDistributionChart.update();

            // Update motion patterns (simplified)
            const normalActivity = analytics.filter(item => (item.total_motion_events || 0) > 10).length;
            const motionDetected = analytics.filter(item => (item.motion_alerts || 0) > 0).length;
            const potentialDrowning = analytics.filter(item => (item.drowning_alerts || 0) > 0).length;
            const noActivity = analytics.length - normalActivity - motionDetected - potentialDrowning;

            motionPatternsChart.data.datasets[0].data = [normalActivity, motionDetected, potentialDrowning, noActivity];
            motionPatternsChart.update();
        }

        function applyDateFilter() {
            loadAnalytics();
            showMessage('Filter applied successfully!', 'success');
        }

        function refreshAnalytics() {
            loadAnalytics();
            showMessage('Analytics refreshed!', 'success');
        }

        function generateReport() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;

            fetch(`analytics.php?action=generate_report&type=alerts_summary&date_from=${dateFrom}&date_to=${dateTo}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Report generated successfully!', 'success');
                    } else {
                        showMessage('Failed to generate report: ' + data.error, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error generating report:', error);
                    showMessage('Error generating report', 'danger');
                });
        }

        function exportAnalytics() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;

            fetch(`analytics.php?action=get_analytics&date_from=${dateFrom}&date_to=${dateTo}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const blob = new Blob([JSON.stringify(data.analytics, null, 2)], {type: 'application/json'});
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `analytics_${dateFrom}_to_${dateTo}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        showMessage('Analytics exported successfully!', 'success');
                    }
                })
                .catch(error => {
                    console.error('Export error:', error);
                    showMessage('Export failed', 'danger');
                });
        }

        function viewReports() {
            fetch('analytics.php?action=get_reports')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.reports.length > 0) {
                        console.log('Available reports:', data.reports);
                        showMessage(`Found ${data.reports.length} reports. Check console for details.`, 'info');
                    } else {
                        showMessage('No reports available', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error loading reports:', error);
                    showMessage('Error loading reports', 'danger');
                });
        }

        function showMessage(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>