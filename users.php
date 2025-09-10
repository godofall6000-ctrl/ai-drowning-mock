<?php
/**
 * User Management Interface for AI Drowning Detection System
 * Provides comprehensive user management and authentication
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';

// Start session
session_start();

// Check if user is logged in (basic check)
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header('Location: login.php');
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $db = new Database();

    try {
        switch ($_GET['action']) {
            case 'get_users':
                $users = $db->getUsers();
                echo json_encode(['success' => true, 'users' => $users]);
                break;

            case 'create_user':
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data || !isset($data['username']) || !isset($data['password'])) {
                    echo json_encode(['success' => false, 'error' => 'Invalid user data']);
                    break;
                }

                $user_id = $db->createUser(
                    $data['username'],
                    $data['password'],
                    $data['email'] ?? null,
                    $data['role'] ?? 'user'
                );

                if ($user_id) {
                    $db->logAudit($_SESSION['user_id'] ?? null, 'CREATE_USER', 'users', $user_id, null, $data);
                    echo json_encode(['success' => true, 'user_id' => $user_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to create user']);
                }
                break;

            case 'update_user':
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data || !isset($data['id'])) {
                    echo json_encode(['success' => false, 'error' => 'Invalid user data']);
                    break;
                }

                // Get old values for audit
                $old_user = $db->getUsers(false);
                $old_user = array_filter($old_user, function($u) use ($data) {
                    return $u['id'] == $data['id'];
                });
                $old_user = reset($old_user);

                // Update user (simplified - in real app, you'd have proper update methods)
                $sql = "UPDATE users SET username = ?, email = ?, role = ?, active = ? WHERE id = ?";
                $stmt = $db->pdo->prepare($sql);
                $result = $stmt->execute([
                    $data['username'],
                    $data['email'],
                    $data['role'],
                    $data['active'] ?? true,
                    $data['id']
                ]);

                if ($result) {
                    $db->logAudit($_SESSION['user_id'], 'UPDATE_USER', 'users', $data['id'], $old_user, $data);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update user']);
                }
                break;

            case 'delete_user':
                $user_id = $_GET['user_id'] ?? null;
                if (!$user_id) {
                    echo json_encode(['success' => false, 'error' => 'User ID required']);
                    break;
                }

                // Get user data for audit
                $users = $db->getUsers(false);
                $user = array_filter($users, function($u) use ($user_id) {
                    return $u['id'] == $user_id;
                });
                $user = reset($user);

                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $db->pdo->prepare($sql);
                $result = $stmt->execute([$user_id]);

                if ($result) {
                    $db->logAudit($_SESSION['user_id'], 'DELETE_USER', 'users', $user_id, $user, null);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to delete user']);
                }
                break;

            case 'get_audit_log':
                $audit_log = $db->getAuditLog(100);
                echo json_encode(['success' => true, 'audit_log' => $audit_log]);
                break;

            case 'change_password':
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data || !isset($data['current_password']) || !isset($data['new_password'])) {
                    echo json_encode(['success' => false, 'error' => 'Invalid password data']);
                    break;
                }

                // Verify current password
                $users = $db->getUsers(false);
                $current_user = array_filter($users, function($u) {
                    return $u['id'] == $_SESSION['user_id'];
                });
                $current_user = reset($current_user);

                if (!$current_user || !password_verify($data['current_password'], $current_user['password_hash'])) {
                    echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
                    break;
                }

                // Update password
                $new_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
                $stmt = $db->pdo->prepare($sql);
                $result = $stmt->execute([$new_hash, $_SESSION['user_id']]);

                if ($result) {
                    $db->logAudit($_SESSION['user_id'], 'CHANGE_PASSWORD', 'users', $_SESSION['user_id']);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to change password']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get current user info
$db = new Database();
$users = $db->getUsers(false);
$current_user = array_filter($users, function($u) {
    return $u['id'] == ($_SESSION['user_id'] ?? 0);
});
$current_user = reset($current_user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - AI Drowning Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .user-card { transition: transform 0.2s; }
        .user-card:hover { transform: translateY(-2px); }
        .status-active { color: #28a745; }
        .status-inactive { color: #6c757d; }
        .role-admin { background: #dc3545; color: white; }
        .role-user { background: #007bff; color: white; }
        .role-moderator { background: #ffc107; color: black; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>User Management Dashboard</h1>
                    <div>
                        <a href="index.php" class="btn btn-secondary">← Back to Main</a>
                        <button class="btn btn-primary" onclick="refreshUsers()">Refresh</button>
                    </div>
                </div>

                <!-- Current User Info -->
                <?php if ($current_user): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Current User: <?php echo htmlspecialchars($current_user['username']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Role:</strong>
                                <span class="badge role-<?php echo $current_user['role']; ?>">
                                    <?php echo ucfirst($current_user['role']); ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <strong>Email:</strong> <?php echo htmlspecialchars($current_user['email'] ?? 'Not set'); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Last Login:</strong> <?php echo $current_user['last_login'] ? date('Y-m-d H:i', strtotime($current_user['last_login'])) : 'Never'; ?>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-primary btn-sm" onclick="changePassword()">Change Password</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User Statistics -->
                <div class="row mb-4" id="user-stats">
                    <!-- Stats will be loaded dynamically -->
                </div>

                <!-- Users Table -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Users</h5>
                        <button class="btn btn-success btn-sm" onclick="showCreateUserModal()">Add User</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="users-tbody">
                                    <tr>
                                        <td colspan="8" class="text-center">Loading users...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Audit Log -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Activity (Audit Log)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm" id="audit-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody id="audit-tbody">
                                    <tr>
                                        <td colspan="5" class="text-center">Loading audit log...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-control" id="role">
                                <option value="user">User</option>
                                <option value="moderator">Moderator</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createUser()">Create User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updatePassword()">Change Password</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            loadAuditLog();
            loadUserStats();
        });

        function loadUsers() {
            fetch('users.php?action=get_users')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayUsers(data.users);
                    } else {
                        console.error('Error loading users:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function loadAuditLog() {
            fetch('users.php?action=get_audit_log')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAuditLog(data.audit_log);
                    } else {
                        console.error('Error loading audit log:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function loadUserStats() {
            fetch('users.php?action=get_users')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = calculateUserStats(data.users);
                        displayUserStats(stats);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function calculateUserStats(users) {
            const total = users.length;
            const active = users.filter(u => u.active).length;
            const admins = users.filter(u => u.role === 'admin').length;
            const recentLogins = users.filter(u => {
                if (!u.last_login) return false;
                const lastLogin = new Date(u.last_login);
                const weekAgo = new Date();
                weekAgo.setDate(weekAgo.getDate() - 7);
                return lastLogin > weekAgo;
            }).length;

            return { total, active, admins, recentLogins };
        }

        function displayUserStats(stats) {
            const statsHtml = `
                <div class="col-md-3">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Users</h5>
                            <h2>${stats.total}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Active Users</h5>
                            <h2>${stats.active}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Administrators</h5>
                            <h2>${stats.admins}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Recent Logins</h5>
                            <h2>${stats.recentLogins}</h2>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('user-stats').innerHTML = statsHtml;
        }

        function displayUsers(users) {
            const tbody = document.getElementById('users-tbody');

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No users found</td></tr>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const statusClass = user.active ? 'status-active' : 'status-inactive';
                const statusText = user.active ? 'Active' : 'Inactive';
                const roleClass = 'role-' + user.role;

                html += `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.username}</td>
                        <td>${user.email || 'Not set'}</td>
                        <td><span class="badge ${roleClass}">${user.role}</span></td>
                        <td><span class="${statusClass}">${statusText}</span></td>
                        <td>${new Date(user.created_at).toLocaleDateString()}</td>
                        <td>${user.last_login ? new Date(user.last_login).toLocaleString() : 'Never'}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(${user.id})">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id}, '${user.username}')">Delete</button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function displayAuditLog(auditLog) {
            const tbody = document.getElementById('audit-tbody');

            if (!auditLog || auditLog.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No audit records found</td></tr>';
                return;
            }

            let html = '';
            auditLog.forEach(entry => {
                html += `
                    <tr>
                        <td>${new Date(entry.timestamp).toLocaleString()}</td>
                        <td>${entry.username || 'System'}</td>
                        <td>${entry.action}</td>
                        <td>${entry.table_name || 'N/A'}</td>
                        <td>${entry.record_id || 'N/A'}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function showCreateUserModal() {
            document.getElementById('createUserForm').reset();
            new bootstrap.Modal(document.getElementById('createUserModal')).show();
        }

        function createUser() {
            const form = document.getElementById('createUserForm');
            const formData = new FormData(form);

            const userData = {
                username: formData.get('username'),
                email: formData.get('email'),
                password: formData.get('password'),
                role: formData.get('role')
            };

            fetch('users.php?action=create_user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
                    loadUsers();
                    loadUserStats();
                    showMessage('User created successfully!', 'success');
                } else {
                    showMessage('Error creating user: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error creating user', 'danger');
            });
        }

        function deleteUser(userId, username) {
            if (!confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                return;
            }

            fetch(`users.php?action=delete_user&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadUsers();
                        loadUserStats();
                        showMessage('User deleted successfully!', 'success');
                    } else {
                        showMessage('Error deleting user: ' + data.error, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error deleting user', 'danger');
                });
        }

        function changePassword() {
            document.getElementById('changePasswordForm').reset();
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }

        function updatePassword() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                showMessage('New passwords do not match', 'danger');
                return;
            }

            if (newPassword.length < 6) {
                showMessage('Password must be at least 6 characters long', 'danger');
                return;
            }

            const passwordData = {
                current_password: currentPassword,
                new_password: newPassword
            };

            fetch('users.php?action=change_password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(passwordData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
                    showMessage('Password changed successfully!', 'success');
                } else {
                    showMessage('Error changing password: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error changing password', 'danger');
            });
        }

        function refreshUsers() {
            loadUsers();
            loadUserStats();
            loadAuditLog();
            showMessage('Data refreshed successfully!', 'success');
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

        // Placeholder for edit user functionality
        function editUser(userId) {
            showMessage('Edit user functionality coming soon!', 'info');
        }
    </script>
</body>
</html>