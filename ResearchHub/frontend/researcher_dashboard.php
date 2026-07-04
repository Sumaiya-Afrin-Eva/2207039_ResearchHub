<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}
$displayName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Researcher Dashboard | ResearchHub</title>

    <link rel="stylesheet" href="researcher_dashboard.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="container">

    <!-- Sidebar -->

    <aside class="sidebar">

        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>ResearchHub</span>
        </div>

        <ul>

            <li class="active">
                <a href="#">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-file-upload"></i>
                    Submit Paper
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-book"></i>
                    Submit Thesis
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-folder-open"></i>
                    My Submissions
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-comments"></i>
                    Reviews & Feedback
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-search"></i>
                    Search Papers
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-chart-line"></i>
                    Analytics
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-user"></i>
                    Profile
                </a>
            </li>

            <li>
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </li>

        </ul>

    </aside>

    <!-- Main Content -->

    <main class="main-content">

        <!-- Header -->

        <div class="topbar">

            <div>
                <h2>Researcher Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($displayName); ?></p>
            </div>

            <div class="top-icons">

                <i class="fas fa-bell"></i>

                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($displayName); ?>&background=2563eb&color=fff"
                     alt="profile">

            </div>

        </div>

        <!-- Statistics -->

        <section class="stats">

            <div class="card">
                <i class="fas fa-file-alt"></i>
                <h3>12</h3>
                <p>Total Papers</p>
            </div>

            <div class="card">
                <i class="fas fa-book"></i>
                <h3>5</h3>
                <p>Total Thesis</p>
            </div>

            <div class="card">
                <i class="fas fa-check-circle"></i>
                <h3>8</h3>
                <p>Approved</p>
            </div>

            <div class="card">
                <i class="fas fa-clock"></i>
                <h3>4</h3>
                <p>Pending Review</p>
            </div>

        </section>

        <!-- Quick Actions -->

        <section class="quick-actions">

            <h3>Quick Actions</h3>

            <div class="action-grid">

                <div class="action-box">
                    <i class="fas fa-upload"></i>
                    <h4>Submit New Paper</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-book-open"></i>
                    <h4>Submit Thesis</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-search"></i>
                    <h4>Search Publications</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-chart-pie"></i>
                    <h4>View Reports</h4>
                </div>

            </div>

        </section>

        <!-- Recent Submissions -->

        <section class="table-section">

            <div class="section-header">
                <h3>Recent Submissions</h3>
            </div>

            <table>

                <thead>

                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>

                </thead>

                <tbody>

                <tr>
                    <td>AI Based Disease Detection</td>
                    <td>Paper</td>
                    <td><span class="approved">Approved</span></td>
                    <td>15 Jun 2026</td>
                </tr>

                <tr>
                    <td>Machine Learning in Healthcare</td>
                    <td>Thesis</td>
                    <td><span class="pending">Pending</span></td>
                    <td>10 Jun 2026</td>
                </tr>

                <tr>
                    <td>Blockchain Security Framework</td>
                    <td>Paper</td>
                    <td><span class="review">Under Review</span></td>
                    <td>05 Jun 2026</td>
                </tr>

                </tbody>

            </table>

        </section>

        <!-- Progress -->

        <section class="progress-section">

            <h3>Research Progress</h3>

            <div class="progress-card">

                <p>Current Thesis Completion</p>

                <div class="progress-bar">
                    <div class="progress-fill">
                        75%
                    </div>
                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>
