<?php
session_start();

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role_id']) {
        case 1: header("Location: admin_dashboard.php"); exit();
        case 2: header("Location: researcher_dashboard.php"); exit();
        case 3: header("Location: supervisor_dashboard.php"); exit();
        case 4: header("Location: reviewer_dashboard.php"); exit();
    }
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ResearchHub</title>

    <link rel="stylesheet" href="login.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="container">

    <!-- Left Side -->
    <div class="left-panel">

        <div class="overlay">

            <h1><a href="index.php" style="color: white; text-decoration: none;">ResearchHub</a></h1>

            <p>
                Research Paper & Thesis Management System
            </p>

            <div class="features">

                <div class="feature">
                    <i class="fas fa-file-alt"></i>
                    <span>Research Paper Submission</span>
                </div>

                <div class="feature">
                    <i class="fas fa-book"></i>
                    <span>Thesis Management</span>
                </div>

                <div class="feature">
                    <i class="fas fa-user-check"></i>
                    <span>Peer Review System</span>
                </div>

                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics & Reports</span>
                </div>

            </div>

        </div>

    </div>

    <!-- Right Side -->
    <div class="right-panel">

        <div class="login-box">

            <div class="logo">
                <a href="index.php" style="color: #2563eb;"><i class="fas fa-graduation-cap"></i></a>
            </div>

            <h2>Welcome Back</h2>

            <p class="subtitle">
                Login to continue your research journey
            </p>

            <?php if (!empty($error)): ?>
                <div class="error-box" style="background-color: #fef2f2; border: 1px solid #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="../auth/login.php" method="POST">

                <div class="input-group">
                    <label>Email Address</label>

                    <div class="input-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email"
                               name="email"
                               placeholder="Enter your email"
                               required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>

                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password"
                               name="password"
                               placeholder="Enter your password"
                               required>
                    </div>
                </div>

                <div class="options">

                    <label>
                        <input type="checkbox" name="remember">
                        Remember Me
                    </label>

                    <a href="#">Forgot Password?</a>

                </div>

                <button type="submit" class="login-btn">
                    Login
                </button>

            </form>

            <div class="divider">
                <span>OR</span>
            </div>

            <button class="google-btn">
                <i class="fab fa-google"></i>
                Continue with Google
            </button>

            <p class="register-link">
                Don't have an account?
                <a href="register.php">Register Now</a>
            </p>

        </div>

    </div>

</div>

</body>
</html>
