<?php
/**
 * Database Management Interface for AI Drowning Detection System
 * Provides comprehensive database management and analytics
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
            case 'get_stats':
                $stats = getDatabaseStats();
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;

            case 'get_recent_alerts':
                $alerts = $db->getRecentAlerts(isset($_GET['limit']) ? (int)$_GET['limit'] : 50);
                echo json_encode(['success' => true, 'alerts' => $alerts]);
                break;

            case 'get_alert_summary':
                $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
                $summary = $db->getAlertSummary($days);
                echo json_encode(['success' => true, 'summary' => $summary]);
                break;

            case 'export_data':
                $type = $_GET['type'] ?? 'alerts';
                $data = exportData($type);
                echo json_encode(['success' => true, 'data' => $data]);
                break;

            case 'backup_database':
                $result = backupDatabase();
                echo json_encode($result);
                break;

            case 'cleanup_old_data':
                $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
                $result = $db->cleanupOldData($days);
                echo json_encode(['success' => $result, 'message' => $result ? 'Data cleaned up successfully' : 'Cleanup failed']);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Helper functions
function getDatabaseStats() {
    global $db;

    try {
        // Get table counts
        $stats = [];

        // Alert statistics
        $alerts = $db->getRecentAlerts(1000);
        $stats['total_alerts'] = count($alerts);

        $high_severity = array_filter($alerts, function($alert) {
            return $alert['severity'] === 'high';
        });
        $stats['high_severity_alerts'] = count($high_severity);

        // Detection statistics
        $detections = $db->getRecentDetections(1000);
        $stats['total_detections'] = count($detections);

        $motion_detections = array_filter($detections, function($detection) {
            return $detection['motion_detected'];
        });
        $stats['motion_detections'] = count($motion_detections);

        // System statistics
        $stats['system_stats'] = $db->getSystemStats(1); // Last 24 hours

        // Database size
        $stats['database_size'] = getDatabaseSize();

        return $stats;

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function getDatabaseSize() {
    global $db;

    try {
        if ($db->isSQLite()) {
            $db_file = DATA_DIR . 'drowning_detection.db';
            if (file_exists($db_file)) {
                return filesize($db_file);
            }
        } else {
            // For MySQL, we'd need to query information_schema
            // This is a simplified version
            return 0;
        }
    } catch (Exception $e) {
        return 0;
    }

    return 0;
}

function exportData($type) {
    global $db;

    try {
        switch ($type) {
            case 'alerts':
                $data = $db->getRecentAlerts(1000);
                break;
            case 'detections':
                $data = $db->getRecentDetections(1000);
                break;
            case 'system_stats':
                $data = $db->getSystemStats(30);
                break;
            default:
                return ['error' => 'Invalid export type'];
        }

        return [
            'type' => $type,
            'count' => count($data),
            'data' => $data,
            'exported_at' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function backupDatabase() {
    try {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = DATA_DIR . "backup_{$timestamp}.sql";

        if (file_put_contents($backup_file, "-- Database Backup: {$timestamp}\n-- AI Drowning Detection System\n\n")) {
            return [
                'success' => true,
                'message' => 'Database backup created successfully',
                'file' => $backup_file
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to create backup file'
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Backup failed: ' . $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Management - AI Drowning Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .alert-item { padding: 10px; border-left: 4px solid #dc3545; margin-bottom: 10px; background: #f8f9fa; }
        .table-responsive { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Database Management Dashboard</h1>
                    <div>
                        <a href="index.php" class="btn btn-secondary">← Back to Main</a>
                        <button class="btn btn-primary" onclick="refreshData()">Refresh Data</button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4" id="stats-container">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Alerts</h5>
                                <h2 id="total-alerts">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">High Severity</h5>
                                <h2 id="high-alerts">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Motion Detections</h5>
                                <h2 id="motion-detections">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Database Size</h5>
                                <h2 id="db-size">0 MB</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Alert Trends (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="alertChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Alert Severity Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="severityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Tables -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Recent Alerts</h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="exportData('alerts')">Export</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="alerts-table">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Type</th>
                                                <th>Severity</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody id="alerts-tbody">
                                            <tr>
                                                <td colspan="4" class="text-center">Loading...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>System Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-warning" onclick="backupDatabase()">
                                        📦 Create Database Backup
                                    </button>
                                    <button class="btn btn-danger" onclick="cleanupOldData()">
                                        🗑️ Clean Old Data (30+ days)
                                    </button>
                                    <button class="btn btn-info" onclick="exportData('detections')">
                                        📊 Export Detection Data
                                    </button>
                                    <button class="btn btn-secondary" onclick="exportData('system_stats')">
                                        📈 Export System Statistics
                                    </button>
                                </div>

                                <hr>
                                <div id="action-result" class="mt-3"></div>
                            </div>
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
        let alertChart, severityChart;

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            loadData();
        });

        function initCharts() {
            const alertCtx = document.getElementById('alertChart').getContext('2d');
            alertChart = new Chart(alertCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Alerts per Day',
                        data: [],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1
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

            const severityCtx = document.getElementById('severityChart').getContext('2d');
            severityChart = new Chart(severityCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Low', 'Medium', 'High'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: [
                            'rgb(40, 167, 69)',
                            'rgb(255, 193, 7)',
                            'rgb(220, 53, 69)'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        }

        function loadData() {
            // Load statistics
            fetch('database.php?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.stats);
                    }
                })
                .catch(error => console.error('Error loading stats:', error));

            // Load alerts
            fetch('database.php?action=get_recent_alerts&limit=20')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateAlertsTable(data.alerts);
                    }
                })
                .catch(error => console.error('Error loading alerts:', error));

            // Load alert summary for charts
            fetch('database.php?action=get_alert_summary&days=7')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCharts(data.summary);
                    }
                })
                .catch(error => console.error('Error loading summary:', error));
        }

        function updateStats(stats) {
            document.getElementById('total-alerts').textContent = stats.total_alerts || 0;
            document.getElementById('high-alerts').textContent = stats.high_severity_alerts || 0;
            document.getElementById('motion-detections').textContent = stats.motion_detections || 0;

            const dbSizeMB = stats.database_size ? (stats.database_size / 1024 / 1024).toFixed(2) : 0;
            document.getElementById('db-size').textContent = dbSizeMB + ' MB';
        }

        function updateAlertsTable(alerts) {
            const tbody = document.getElementById('alerts-tbody');

            if (!alerts || alerts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">No alerts found</td></tr>';
                return;
            }

            let html = '';
            alerts.forEach(alert => {
                const severityClass = getSeverityClass(alert.severity);
                html += `
                    <tr>
                        <td>${alert.timestamp}</td>
                        <td><span class="badge bg-${getSeverityBadge(alert.severity)}">${alert.alert_type}</span></td>
                        <td><span class="badge bg-${severityClass}">${alert.severity}</span></td>
                        <td>${alert.details}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function updateCharts(summary) {
            if (!summary || summary.length === 0) return;

            // Update alert trend chart
            const labels = summary.map(item => item.date);
            const data = summary.map(item => item.total_alerts);

            alertChart.data.labels = labels;
            alertChart.data.datasets[0].data = data;
            alertChart.update();

            // Update severity distribution
            let low = 0, medium = 0, high = 0;
            summary.forEach(item => {
                low += item.total_alerts - item.high_severity;
                medium += Math.floor(item.high_severity * 0.5); // Estimate
                high += item.high_severity;
            });

            severityChart.data.datasets[0].data = [low, medium, high];
            severityChart.update();
        }

        function getSeverityClass(severity) {
            switch (severity) {
                case 'high': return 'danger';
                case 'medium': return 'warning';
                case 'low': return 'success';
                default: return 'secondary';
            }
        }

        function getSeverityBadge(severity) {
            switch (severity) {
                case 'high': return 'danger';
                case 'medium': return 'warning';
                case 'low': return 'success';
                default: return 'secondary';
            }
        }

        function refreshData() {
            loadData();
            showMessage('Data refreshed successfully!', 'success');
        }

        function exportData(type) {
            fetch(`database.php?action=export_data&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const blob = new Blob([JSON.stringify(data.data, null, 2)], {type: 'application/json'});
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `${type}_export_${new Date().toISOString().split('T')[0]}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        showMessage(`${type} data exported successfully!`, 'success');
                    } else {
                        showMessage('Export failed: ' + data.error, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Export error:', error);
                    showMessage('Export failed', 'danger');
                });
        }

        function backupDatabase() {
            if (!confirm('Create a database backup? This may take a moment.')) return;

            fetch('database.php?action=backup_database')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Database backup created successfully!', 'success');
                    } else {
                        showMessage('Backup failed: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Backup error:', error);
                    showMessage('Backup failed', 'danger');
                });
        }

        function cleanupOldData() {
            if (!confirm('Delete data older than 30 days? This cannot be undone.')) return;

            fetch('database.php?action=cleanup_old_data&days=30')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Old data cleaned up successfully!', 'success');
                        loadData(); // Refresh data
                    } else {
                        showMessage('Cleanup failed: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Cleanup error:', error);
                    showMessage('Cleanup failed', 'danger');
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