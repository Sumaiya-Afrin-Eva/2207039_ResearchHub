<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ResearchHub | Research Paper & Thesis Management System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <header>
        <nav class="navbar">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                ResearchHub
            </div>

            <ul class="nav-links">
                <li><a href="#">Home</a></li>
                <li><a href="#">Research Papers</a></li>
                <li><a href="#">Thesis</a></li>
                <li><a href="#">Departments</a></li>
                <li><a href="#">About</a></li>
            </ul>

            <div class="buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    $dashboardLink = 'login.php';
                    switch ($_SESSION['role_id']) {
                        case 1: $dashboardLink = 'admin_dashboard.php'; break;
                        case 2: $dashboardLink = 'researcher_dashboard.php'; break;
                        case 3: $dashboardLink = 'supervisor_dashboard.php'; break;
                        case 4: $dashboardLink = 'reviewer_dashboard.php'; break;
                    }
                    ?>
                    <span class="user-welcome" style="margin-right: 15px; font-weight: 500; color: #1e293b;">Hello, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                    <a href="<?php echo $dashboardLink; ?>" class="login-btn">Dashboard</a>
                    <a href="../auth/logout.php" class="register-btn">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="login-btn">Login</a>
                    <a href="register.php" class="register-btn">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">

        <div class="hero-content">
            <h1>Empowering Academic Research & Innovation</h1>

            <p>
                Submit, review, manage and publish research papers
                and theses efficiently through a centralized academic platform.
            </p>

            <div class="hero-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $dashboardLink; ?>" class="primary-btn">Go to Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="primary-btn">Get Started</a>
                <?php endif; ?>
                <a href="#" class="secondary-btn">Explore Papers</a>
            </div>
        </div>

        <div class="hero-image">
            <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800" alt="">
        </div>

    </section>

    <!-- Statistics -->
    <section class="stats">

        <div class="stat-box">
            <h2>1200+</h2>
            <p>Research Papers</p>
        </div>

        <div class="stat-box">
            <h2>350+</h2>
            <p>Published Theses</p>
        </div>

        <div class="stat-box">
            <h2>90+</h2>
            <p>Supervisors</p>
        </div>

        <div class="stat-box">
            <h2>500+</h2>
            <p>Researchers</p>
        </div>

    </section>

    <!-- Features -->
    <section class="features">

        <h2>Core Features</h2>

        <div class="feature-grid">

            <div class="feature-card">
                <i class="fas fa-file-upload"></i>
                <h3>Paper Submission</h3>
                <p>Submit and manage research papers easily.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-book"></i>
                <h3>Thesis Management</h3>
                <p>Track thesis progress and approvals.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-user-check"></i>
                <h3>Peer Review</h3>
                <p>Structured review and scoring system.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-search"></i>
                <h3>Smart Search</h3>
                <p>Search by title, author, supervisor or department.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-chart-line"></i>
                <h3>Analytics</h3>
                <p>Publication reports and research statistics.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-shield-alt"></i>
                <h3>Role Management</h3>
                <p>Admin, Researcher, Supervisor & Reviewer access.</p>
            </div>

        </div>

    </section>

    <!-- Call To Action -->
    <section class="cta">

        <h2>Join the Future of Academic Research</h2>

        <p>
            Collaborate with researchers, supervisors, and reviewers
            through one intelligent research management platform.
        </p>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo $dashboardLink; ?>" class="cta-btn">Go to Dashboard</a>
        <?php else: ?>
            <a href="register.php" class="cta-btn">Get Started</a>
        <?php endif; ?>

    </section>

    <!-- Footer -->
    <footer>

        <div class="footer-content">
            <h3>ResearchHub</h3>

            <p>
                Research Paper & Thesis Management System
            </p>

            <p>
                © 2026 All Rights Reserved.
            </p>
        </div>

    </footer>

</body>
</html>
