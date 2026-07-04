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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | ResearchHub</title>

    <link rel="stylesheet" href="register.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="container">

    <!-- Left Side -->
    <div class="left-panel">

        <div class="overlay">

            <h1><a href="index.php" style="color: white; text-decoration: none;">Join ResearchHub</a></h1>

            <p>
                Connect with researchers, supervisors, and reviewers
                through a modern academic research platform.
            </p>

            <div class="benefits">

                <div class="benefit">
                    <i class="fas fa-file-upload"></i>
                    <span>Submit Research Papers</span>
                </div>

                <div class="benefit">
                    <i class="fas fa-book-open"></i>
                    <span>Manage Thesis Workflow</span>
                </div>

                <div class="benefit">
                    <i class="fas fa-users"></i>
                    <span>Collaborate with Experts</span>
                </div>

                <div class="benefit">
                    <i class="fas fa-chart-bar"></i>
                    <span>Track Research Progress</span>
                </div>

            </div>

        </div>

    </div>

    <!-- Right Side -->
    <div class="right-panel">

        <div class="register-box">

            <div class="logo">
                <a href="index.php" style="color: #2563eb;"><i class="fas fa-graduation-cap"></i></a>
            </div>

            <h2>Create Account</h2>

            <p class="subtitle">
                Register to access ResearchHub services
            </p>

            <form action="../auth/register.php" method="POST">

                <div class="row">

                    <div class="input-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" placeholder="Enter first name" required>
                    </div>

                    <div class="input-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" placeholder="Enter last name" required>
                    </div>

                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter email address" required>
                </div>

                <div class="input-group">
                    <label>Institution / University</label>
                    <input type="text" name="institution" placeholder="Enter institution name" required>
                </div>

                <div class="input-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="Enter department" required>
                </div>

                <div class="input-group">
                    <label>Select Role</label>

                    <select name="role_id" required>
                        <option value="">Select Role</option>
                        <option value="2">Researcher</option>
                        <option value="3">Supervisor</option>
                        <option value="4">Reviewer</option>
                    </select>
                </div>

                <div class="row">

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>

                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    </div>

                </div>

                <div class="checkbox">

                    <input type="checkbox" id="terms" required>

                    <label for="terms">
                        I agree to the Terms & Conditions and Privacy Policy
                    </label>

                </div>

                <button type="submit" class="register-btn">
                    Create Account
                </button>

            </form>

            <div class="divider">
                <span>OR</span>
            </div>

            <button class="google-btn">
                <i class="fab fa-google"></i>
                Sign Up with Google
            </button>

            <p class="login-link">
                Already have an account?
                <a href="login.php">Login</a>
            </p>

        </div>

    </div>

</div>

</body>
</html>
