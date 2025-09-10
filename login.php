<?php
/**
 * Login System for AI Drowning Detection System
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/database.php';

// Start session
session_start();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $db = new Database();
        $user = $db->authenticateUser($username, $password);

        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();

            // Log successful login
            $db->logAudit($user['id'], 'LOGIN_SUCCESS', null, null, null, ['ip' => $_SERVER['REMOTE_ADDR']]);

            // Redirect to main page
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';

            // Log failed login attempt
            $db = new Database();
            $db->logAudit(null, 'LOGIN_FAILED', null, null, null, [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
        }
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AI Drowning Detection System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            padding: 12px;
            border-radius: 25px;
            width: 100%;
            font-weight: bold;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-card">
                    <div class="login-header">
                        <h2 class="mb-0">🔍 AI Drowning Detection</h2>
                        <p class="mb-0">System Login</p>
                    </div>
                    <div class="login-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-login">Login</button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <small class="text-muted">
                                Demo Credentials:<br>
                                Username: <code>admin</code><br>
                                Password: <code>admin123</code>
                            </small>
                        </div>

                        <div class="text-center mt-3">
                            <a href="index.php" class="text-decoration-none">← Back to Main</a>
                        </div>
                    </div>
                </div>

                <!-- System Info -->
                <div class="text-center mt-3">
                    <small class="text-white">
                        AI Drowning Detection System v<?php echo VERSION; ?><br>
                        <?php echo date('Y-m-d H:i:s'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Handle Enter key in password field
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>

<?php
// Create default admin user if it doesn't exist
$db = new Database();

// Check if any users exist
$users = $db->getUsers();
if (empty($users)) {
    // Create default admin user
    $admin_id = $db->createUser('admin', 'admin123', 'admin@drowning-system.com', 'admin');
    if ($admin_id) {
        logMessage("Default admin user created (username: admin, password: admin123)");
    }
}
?>