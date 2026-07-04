<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
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

<title>Reviewer Dashboard | ResearchHub</title>

<link rel="stylesheet" href="reviewer_dashboard.css">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>

<body>

<div class="container">

    <!-- Sidebar -->

    <aside class="sidebar">

        <div class="logo">
            <i class="fas fa-user-check"></i>
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
                    <i class="fas fa-file-alt"></i>
                    Assigned Papers
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-star"></i>
                    Submit Review
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-history"></i>
                    Review History
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-comments"></i>
                    Feedback
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-chart-line"></i>
                    Statistics
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
                <h2>Reviewer Dashboard</h2>
                <p>Welcome Back, <?php echo htmlspecialchars($displayName); ?></p>
            </div>

            <div class="profile-area">

                <i class="fas fa-bell"></i>

                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($displayName); ?>&background=2563eb&color=fff"
                     alt="Profile">

            </div>

        </div>

        <!-- Statistics -->

        <section class="stats">

            <div class="card">
                <i class="fas fa-file-alt"></i>
                <h3>24</h3>
                <p>Assigned Papers</p>
            </div>

            <div class="card">
                <i class="fas fa-check-circle"></i>
                <h3>18</h3>
                <p>Completed Reviews</p>
            </div>

            <div class="card">
                <i class="fas fa-clock"></i>
                <h3>6</h3>
                <p>Pending Reviews</p>
            </div>

            <div class="card">
                <i class="fas fa-star"></i>
                <h3>4.8</h3>
                <p>Average Rating</p>
            </div>

        </section>

        <!-- Quick Actions -->

        <section class="quick-actions">

            <h3>Quick Actions</h3>

            <div class="action-grid">

                <div class="action-box">
                    <i class="fas fa-clipboard-check"></i>
                    <h4>Review Paper</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-star-half-alt"></i>
                    <h4>Give Score</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-comment-dots"></i>
                    <h4>Add Feedback</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-chart-bar"></i>
                    <h4>Review Reports</h4>
                </div>

            </div>

        </section>

        <!-- Assigned Papers -->

        <section class="table-section">

            <h3>Assigned Papers</h3>

            <table>

                <thead>

                <tr>
                    <th>Paper Title</th>
                    <th>Author</th>
                    <th>Submission Date</th>
                    <th>Status</th>
                </tr>

                </thead>

                <tbody>

                <tr>
                    <td>AI Based Disease Detection</td>
                    <td>Sumaiya Afrin Eva</td>
                    <td>15 Jun 2026</td>
                    <td><span class="pending">Pending</span></td>
                </tr>

                <tr>
                    <td>Cyber Security Framework</td>
                    <td>John Smith</td>
                    <td>10 Jun 2026</td>
                    <td><span class="completed">Reviewed</span></td>
                </tr>

                <tr>
                    <td>Blockchain for Healthcare</td>
                    <td>Sarah Khan</td>
                    <td>08 Jun 2026</td>
                    <td><span class="reviewing">In Progress</span></td>
                </tr>

                </tbody>

            </table>

        </section>

        <!-- Review Evaluation Form -->

        <section class="review-form">

            <h3>Quick Review Submission</h3>

            <form>

                <div class="input-group">
                    <label>Paper Title</label>
                    <input type="text" placeholder="Enter paper title">
                </div>

                <div class="input-group">
                    <label>Score (Out of 10)</label>
                    <input type="number" min="1" max="10">
                </div>

                <div class="input-group">
                    <label>Review Comments</label>
                    <textarea rows="5"
                    placeholder="Write your review comments here..."></textarea>
                </div>

                <button type="submit">
                    Submit Review
                </button>

            </form>

        </section>

        <!-- Review Performance -->

        <section class="performance-section">

            <h3>Review Completion Rate</h3>

            <div class="progress-bar">

                <div class="progress-fill">
                    75%
                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>
