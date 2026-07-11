<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: login.php");
    exit();
}
include_once '../php/db_connect.php';

$reviewer_id = (int)$_SESSION['user_id'];
$displayName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// 1. Fetch statistics
// Assigned papers
$assigned_sql = "SELECT COUNT(*) AS CNT FROM REVIEW_ASSIGNMENTS WHERE REVIEWER_ID = :reviewer_id";
$assigned_stid = oci_parse($conn, $assigned_sql);
oci_bind_by_name($assigned_stid, ':reviewer_id', $reviewer_id);
oci_execute($assigned_stid);
$assigned_row = oci_fetch_assoc($assigned_stid);
$assigned_count = $assigned_row ? (int)$assigned_row['CNT'] : 0;

// Completed reviews
$completed_sql = "SELECT COUNT(*) AS CNT FROM REVIEW_ASSIGNMENTS WHERE REVIEWER_ID = :reviewer_id AND ASSIGNMENT_STATUS = 'COMPLETED'";
$completed_stid = oci_parse($conn, $completed_sql);
oci_bind_by_name($completed_stid, ':reviewer_id', $reviewer_id);
oci_execute($completed_stid);
$completed_row = oci_fetch_assoc($completed_stid);
$completed_count = $completed_row ? (int)$completed_row['CNT'] : 0;

// Pending reviews
$pending_sql = "SELECT COUNT(*) AS CNT FROM REVIEW_ASSIGNMENTS WHERE REVIEWER_ID = :reviewer_id AND ASSIGNMENT_STATUS = 'PENDING'";
$pending_stid = oci_parse($conn, $pending_sql);
oci_bind_by_name($pending_stid, ':reviewer_id', $reviewer_id);
oci_execute($pending_stid);
$pending_row = oci_fetch_assoc($pending_stid);
$pending_count = $pending_row ? (int)$pending_row['CNT'] : 0;

// Average score
$avg_sql = "
    SELECT COALESCE(ROUND(AVG(R.SCORE), 1), 0.0) AS AVG_SCORE 
    FROM REVIEWS R
    JOIN REVIEW_ASSIGNMENTS RA ON R.ASSIGNMENT_ID = RA.ASSIGNMENT_ID
    WHERE RA.REVIEWER_ID = :reviewer_id
";
$avg_stid = oci_parse($conn, $avg_sql);
oci_bind_by_name($avg_stid, ':reviewer_id', $reviewer_id);
oci_execute($avg_stid);
$avg_row = oci_fetch_assoc($avg_stid);
$avg_score = $avg_row ? $avg_row['AVG_SCORE'] : 0.0;

// Completion rate
$completion_rate = $assigned_count > 0 ? round(($completed_count / $assigned_count) * 100) : 0;

// 2. Fetch assigned papers
$papers_sql = "
    SELECT P.PAPER_ID, 
           P.TITLE, 
           U.FIRST_NAME || ' ' || U.LAST_NAME AS AUTHOR_NAME, 
           TO_CHAR(P.SUBMISSION_DATE, 'DD Mon YYYY') AS SUB_DATE, 
           RA.ASSIGNMENT_STATUS
    FROM REVIEW_ASSIGNMENTS RA
    JOIN PAPERS P ON RA.PAPER_ID = P.PAPER_ID
    JOIN USERS U ON P.RESEARCHER_ID = U.USER_ID
    WHERE RA.REVIEWER_ID = :reviewer_id
    ORDER BY RA.ASSIGNED_DATE DESC
";
$papers_stid = oci_parse($conn, $papers_sql);
oci_bind_by_name($papers_stid, ':reviewer_id', $reviewer_id);
oci_execute($papers_stid);

$assigned_papers = [];
while ($row = oci_fetch_assoc($papers_stid)) {
    $assigned_papers[] = $row;
}

// Handle Review Submission
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
    $score = isset($_POST['score']) ? (int)$_POST['score'] : 0;
    $recommendation = trim($_POST['recommendation'] ?? '');
    $comments = trim($_POST['comments'] ?? '');

    if ($assignment_id <= 0 || $score < 1 || $score > 10 || empty($recommendation) || empty($comments)) {
        $error_msg = 'Please fill out all fields correctly. Score must be between 1 and 10.';
    } else {
        // 1. Verify that this assignment belongs to this reviewer and is PENDING
        $check_sql = "
            SELECT RA.PAPER_ID 
            FROM REVIEW_ASSIGNMENTS RA
            WHERE RA.ASSIGNMENT_ID = :assignment_id 
              AND RA.REVIEWER_ID = :reviewer_id 
              AND RA.ASSIGNMENT_STATUS = 'PENDING'
        ";
        $check_stid = oci_parse($conn, $check_sql);
        oci_bind_by_name($check_stid, ':assignment_id', $assignment_id);
        oci_bind_by_name($check_stid, ':reviewer_id', $reviewer_id);
        oci_execute($check_stid);
        $check_row = oci_fetch_assoc($check_stid);

        if (!$check_row) {
            $error_msg = 'Invalid assignment selection or the review has already been completed.';
        } else {
            $paper_id = $check_row['PAPER_ID'];

            // 2. Fetch the next review ID
            $id_sql = "SELECT COALESCE(MAX(REVIEW_ID), 0) + 1 AS NEXT_ID FROM REVIEWS";
            $id_stid = oci_parse($conn, $id_sql);
            oci_execute($id_stid);
            $id_row = oci_fetch_assoc($id_stid);
            $next_review_id = $id_row['NEXT_ID'];

            // 3. Insert review and update statuses using a PL/SQL block
            $plsql = "
                BEGIN
                    -- Insert review record
                    INSERT INTO REVIEWS (REVIEW_ID, ASSIGNMENT_ID, SCORE, COMMENTS, RECOMMENDATION, REVIEW_DATE)
                    VALUES (:review_id, :assignment_id, :score, :comments, :recommendation, SYSDATE);

                    -- Update review assignment status to completed
                    UPDATE REVIEW_ASSIGNMENTS
                    SET ASSIGNMENT_STATUS = 'COMPLETED'
                    WHERE ASSIGNMENT_ID = :assignment_id;

                    -- Update paper status based on recommendation
                    UPDATE PAPERS
                    SET STATUS = CASE :recommendation
                        WHEN 'ACCEPT' THEN 'ACCEPTED'
                        WHEN 'REJECT' THEN 'REJECTED'
                        ELSE 'UNDER REVIEW'
                    END
                    WHERE PAPER_ID = :paper_id;
                END;
            ";

            $plsql_stid = oci_parse($conn, $plsql);
            oci_bind_by_name($plsql_stid, ':review_id', $next_review_id);
            oci_bind_by_name($plsql_stid, ':assignment_id', $assignment_id);
            oci_bind_by_name($plsql_stid, ':score', $score);
            oci_bind_by_name($plsql_stid, ':comments', $comments);
            oci_bind_by_name($plsql_stid, ':recommendation', $recommendation);
            oci_bind_by_name($plsql_stid, ':paper_id', $paper_id);

            $r = oci_execute($plsql_stid);
            if ($r) {
                // Log the activity in AUDIT_LOGS
                $log_id_sql = "SELECT COALESCE(MAX(LOG_ID), 0) + 1 AS NEXT_ID FROM AUDIT_LOGS";
                $log_id_stid = oci_parse($conn, $log_id_sql);
                oci_execute($log_id_stid);
                $log_id_row = oci_fetch_assoc($log_id_stid);
                $next_log_id = $log_id_row['NEXT_ID'];

                $audit_sql = "
                    INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
                    VALUES (:log_id, :user_id, 'INSERT', 'REVIEWS', SYSDATE, 'Submitted review for paper ID ' || :paper_id)
                ";
                $audit_stid = oci_parse($conn, $audit_sql);
                oci_bind_by_name($audit_stid, ':log_id', $next_log_id);
                oci_bind_by_name($audit_stid, ':user_id', $reviewer_id);
                oci_bind_by_name($audit_stid, ':paper_id', $paper_id);
                oci_execute($audit_stid);

                $success_msg = 'Review submitted successfully!';
                
                // Re-run stats & papers fetch so updated values show up immediately
                oci_execute($assigned_stid);
                $assigned_row = oci_fetch_assoc($assigned_stid);
                $assigned_count = $assigned_row ? (int)$assigned_row['CNT'] : 0;

                oci_execute($completed_stid);
                $completed_row = oci_fetch_assoc($completed_stid);
                $completed_count = $completed_row ? (int)$completed_row['CNT'] : 0;

                oci_execute($pending_stid);
                $pending_row = oci_fetch_assoc($pending_stid);
                $pending_count = $pending_row ? (int)$pending_row['CNT'] : 0;

                oci_execute($avg_stid);
                $avg_row = oci_fetch_assoc($avg_stid);
                $avg_score = $avg_row ? $avg_row['AVG_SCORE'] : 0.0;

                $completion_rate = $assigned_count > 0 ? round(($completed_count / $assigned_count) * 100) : 0;

                oci_execute($papers_stid);
                $assigned_papers = [];
                while ($p_row = oci_fetch_assoc($papers_stid)) {
                    $assigned_papers[] = $p_row;
                }
            } else {
                $e = oci_error($plsql_stid);
                $error_msg = 'Database insertion failed: ' . htmlspecialchars($e['message']);
            }
        }
    }
}

// Fetch pending review assignments for the dropdown list
$pending_list_sql = "
    SELECT RA.ASSIGNMENT_ID, P.TITLE
    FROM REVIEW_ASSIGNMENTS RA
    JOIN PAPERS P ON RA.PAPER_ID = P.PAPER_ID
    WHERE RA.REVIEWER_ID = :reviewer_id 
      AND RA.ASSIGNMENT_STATUS = 'PENDING'
    ORDER BY RA.ASSIGNED_DATE DESC
";
$pending_list_stid = oci_parse($conn, $pending_list_sql);
oci_bind_by_name($pending_list_stid, ':reviewer_id', $reviewer_id);
oci_execute($pending_list_stid);

$pending_assignments = [];
while ($row = oci_fetch_assoc($pending_list_stid)) {
    $pending_assignments[] = $row;
}
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

            <li class="active" id="nav-home">
                <a href="#" onclick="showSection('home'); return false;">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </li>

            <li id="nav-assigned">
                <a href="#" onclick="showSection('assigned'); return false;">
                    <i class="fas fa-file-alt"></i>
                    Assigned Papers
                </a>
            </li>

            <li id="nav-submit-review">
                <a href="#" onclick="showSection('submit-review'); return false;">
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

        <!-- Home/Dashboard Section Wrapper -->
        <div id="home-section">

        <!-- Statistics -->

        <section class="stats">

            <div class="card">
                <i class="fas fa-file-alt"></i>
                <h3><?php echo $assigned_count; ?></h3>
                <p>Assigned Papers</p>
            </div>

            <div class="card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $completed_count; ?></h3>
                <p>Completed Reviews</p>
            </div>

            <div class="card">
                <i class="fas fa-clock"></i>
                <h3><?php echo $pending_count; ?></h3>
                <p>Pending Reviews</p>
            </div>

            <div class="card">
                <i class="fas fa-star"></i>
                <h3><?php echo $avg_score; ?></h3>
                <p>Average Rating</p>
            </div>

        </section>

        <!-- Quick Actions -->

        <section class="quick-actions">

            <h3>Quick Actions</h3>

            <div class="action-grid">

                <div class="action-box" onclick="showSection('assigned')" style="cursor:pointer;">
                    <i class="fas fa-clipboard-check"></i>
                    <h4>Review Paper</h4>
                </div>

                <div class="action-box" onclick="showSection('submit-review')" style="cursor:pointer;">
                    <i class="fas fa-star-half-alt"></i>
                    <h4>Give Score</h4>
                </div>

                <div class="action-box" onclick="showSection('submit-review')" style="cursor:pointer;">
                    <i class="fas fa-comment-dots"></i>
                    <h4>Add Feedback</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-chart-bar"></i>
                    <h4>Review Reports</h4>
                </div>

            </div>

        </section>

        <!-- Recent Assigned Papers -->
        <section class="table-section">
            <h3>Recent Assigned Papers</h3>
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
                <?php 
                $recent_papers = array_slice($assigned_papers, 0, 5);
                if (empty($recent_papers)): 
                ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#94a3b8; font-style:italic;">No papers assigned.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    foreach ($recent_papers as $row): 
                        $status_class = ($row['ASSIGNMENT_STATUS'] === 'COMPLETED') ? 'completed' : 'pending';
                        $status_text  = ($row['ASSIGNMENT_STATUS'] === 'COMPLETED') ? 'Reviewed' : 'Pending';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['TITLE']); ?></td>
                            <td><?php echo htmlspecialchars($row['AUTHOR_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($row['SUB_DATE']); ?></td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Review Performance -->
        <section class="performance-section">
            <h3>Review Completion Rate</h3>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%;">
                    <?php echo $completion_rate; ?>%
                </div>
            </div>
        </section>

        </div> <!-- End of home-section -->

        <!-- Submit Review Section Wrapper -->
        <div id="submit-review-section" style="display: none;">
            <section class="review-form">
                <h3>Submit Review</h3>
                
                <?php if ($success_msg): ?>
                    <div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:500;">
                        <i class="fas fa-check-circle" style="margin-right:8px;"></i><?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:500;">
                        <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i><?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="submit_review">

                    <div class="input-group">
                        <label>Paper Title (Pending Assignments)</label>
                        <select name="assignment_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; font-family: inherit; font-size: 15px; color: #334155;">
                            <option value="">Select Paper to Review</option>
                            <?php foreach ($pending_assignments as $pa): ?>
                                <option value="<?php echo $pa['ASSIGNMENT_ID']; ?>">
                                    <?php echo htmlspecialchars($pa['TITLE']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Score (Out of 10)</label>
                        <input type="number" name="score" min="1" max="10" required placeholder="Enter score (1-10)">
                    </div>

                    <div class="input-group">
                        <label>Recommendation</label>
                        <select name="recommendation" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; font-family: inherit; font-size: 15px; color: #334155;">
                            <option value="">Select Recommendation</option>
                            <option value="ACCEPT">ACCEPT</option>
                            <option value="MINOR REVISION">MINOR REVISION</option>
                            <option value="MAJOR REVISION">MAJOR REVISION</option>
                            <option value="REJECT">REJECT</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Review Comments</label>
                        <textarea name="comments" rows="6" required placeholder="Write your comprehensive review comments and feedback here..."></textarea>
                    </div>

                    <button type="submit">
                        Submit Review
                    </button>
                </form>
            </section>
        </div>


        <!-- Assigned Papers Full List Section -->
        <div id="assigned-section" style="display: none;">
            <section class="table-section">
                <div class="section-header">
                    <h3>Assigned Papers</h3>
                </div>
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
                    <?php if (empty($assigned_papers)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#94a3b8; font-style:italic;">No papers assigned.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        foreach ($assigned_papers as $row): 
                            $status_class = ($row['ASSIGNMENT_STATUS'] === 'COMPLETED') ? 'completed' : 'pending';
                            $status_text  = ($row['ASSIGNMENT_STATUS'] === 'COMPLETED') ? 'Reviewed' : 'Pending';
                        ?>
                            <tr>
                                <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($row['TITLE']); ?></td>
                                <td><?php echo htmlspecialchars($row['AUTHOR_NAME']); ?></td>
                                <td><?php echo htmlspecialchars($row['SUB_DATE']); ?></td>
                                <td><span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_text); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>

    </main>

</div>

<script>
function showSection(sectionId) {
    document.getElementById('home-section').style.display = 'none';
    document.getElementById('assigned-section').style.display = 'none';
    if (document.getElementById('submit-review-section')) {
        document.getElementById('submit-review-section').style.display = 'none';
    }
    
    document.getElementById('nav-home').classList.remove('active');
    document.getElementById('nav-assigned').classList.remove('active');
    if (document.getElementById('nav-submit-review')) {
        document.getElementById('nav-submit-review').classList.remove('active');
    }
    
    if (sectionId === 'home') {
        document.getElementById('home-section').style.display = 'block';
        document.getElementById('nav-home').classList.add('active');
    } else if (sectionId === 'assigned') {
        document.getElementById('assigned-section').style.display = 'block';
        document.getElementById('nav-assigned').classList.add('active');
    } else if (sectionId === 'submit-review') {
        if (document.getElementById('submit-review-section')) {
            document.getElementById('submit-review-section').style.display = 'block';
        }
        if (document.getElementById('nav-submit-review')) {
            document.getElementById('nav-submit-review').classList.add('active');
        }
    }
}

window.addEventListener('DOMContentLoaded', () => {
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        showSection('submit-review');
    <?php endif; ?>
});
</script>

</body>
</html>
