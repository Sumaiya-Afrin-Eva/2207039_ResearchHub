<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}
include_once '../php/db_connect.php';
$displayName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$supervisor_id = (int)$_SESSION['user_id'];

// 1. Researchers Assigned count
$stats_res_sql = "
    SELECT COUNT(DISTINCT T.RESEARCHER_ID) AS CNT
    FROM THESIS_SUPERVISIONS TS
    JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
    WHERE TS.SUPERVISOR_ID = :supervisor_id
";
$stats_res_stid = oci_parse($conn, $stats_res_sql);
oci_bind_by_name($stats_res_stid, ':supervisor_id', $supervisor_id);
oci_execute($stats_res_stid);
$stats_res_row = oci_fetch_assoc($stats_res_stid);
$cnt_researchers = $stats_res_row ? (int)$stats_res_row['CNT'] : 0;

// Query for total researchers in system
$total_res_sql = "SELECT COUNT(*) AS CNT FROM USERS WHERE ROLE_ID = 2";
$total_res_stid = oci_parse($conn, $total_res_sql);
oci_execute($total_res_stid);
$total_res_row = oci_fetch_assoc($total_res_stid);
$total_researchers = $total_res_row ? (int)$total_res_row['CNT'] : 0;
$workload_percent = ($total_researchers > 0) ? round(($cnt_researchers / $total_researchers) * 100) : 0;

// 2. Active Theses count
$stats_thesis_sql = "
    SELECT COUNT(*) AS CNT
    FROM THESIS_SUPERVISIONS TS
    JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
    WHERE TS.SUPERVISOR_ID = :supervisor_id
      AND T.STATUS IN ('SUBMITTED', 'UNDER REVIEW')
";
$stats_thesis_stid = oci_parse($conn, $stats_thesis_sql);
oci_bind_by_name($stats_thesis_stid, ':supervisor_id', $supervisor_id);
oci_execute($stats_thesis_stid);
$stats_thesis_row = oci_fetch_assoc($stats_thesis_stid);
$cnt_theses = $stats_thesis_row ? (int)$stats_thesis_row['CNT'] : 0;

// 3. Approved Works count
$stats_approved_sql = "
    SELECT COUNT(*) AS CNT
    FROM THESIS_SUPERVISIONS TS
    JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
    WHERE TS.SUPERVISOR_ID = :supervisor_id
      AND T.STATUS = 'APPROVED'
";
$stats_approved_stid = oci_parse($conn, $stats_approved_sql);
oci_bind_by_name($stats_approved_stid, ':supervisor_id', $supervisor_id);
oci_execute($stats_approved_stid);
$stats_approved_row = oci_fetch_assoc($stats_approved_stid);
$cnt_approved = $stats_approved_row ? (int)$stats_approved_row['CNT'] : 0;

// 4. Pending Reviews count (from review assignments for this supervisor)
$stats_pending_reviews_sql = "
    SELECT COUNT(*) AS CNT
    FROM REVIEW_ASSIGNMENTS RA
    WHERE RA.REVIEWER_ID = :supervisor_id
      AND RA.ASSIGNMENT_STATUS = 'PENDING'
";
$stats_pending_stid = oci_parse($conn, $stats_pending_reviews_sql);
oci_bind_by_name($stats_pending_stid, ':supervisor_id', $supervisor_id);
oci_execute($stats_pending_stid);
$stats_pending_row = oci_fetch_assoc($stats_pending_stid);
$cnt_pending = $stats_pending_row ? (int)$stats_pending_row['CNT'] : 0;

// 5. Fetch assigned researchers list
$researchers_sql = "SELECT U.FIRST_NAME || ' ' || U.LAST_NAME AS RESEARCHER_NAME,
           CASE U.DEPARTMENT_ID
               WHEN 1 THEN 'CSE'
               WHEN 2 THEN 'EEE'
               WHEN 3 THEN 'Business Administration'
               ELSE D.DEPARTMENT_NAME
           END AS DEPT_NAME,
           CASE
               WHEN UPPER(T.TITLE) LIKE '%BLOCKCHAIN%' OR UPPER(T.TITLE) LIKE '%SECURITY%' THEN 'Cyber Security'
               WHEN UPPER(T.TITLE) LIKE '%AI%' OR UPPER(T.TITLE) LIKE '%ARTIFICIAL%' OR UPPER(T.TITLE) LIKE '%DIAGNOSIS%' THEN 'Artificial Intelligence'
               ELSE 'General Research'
           END AS RESEARCH_AREA,
           CASE T.STATUS
               WHEN 'APPROVED' THEN '100%'
               WHEN 'UNDER REVIEW' THEN '75%'
               WHEN 'SUBMITTED' THEN '60%'
               ELSE '50%'
           END AS PROGRESS
    FROM THESIS_SUPERVISIONS TS
    JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
    JOIN USERS U ON T.RESEARCHER_ID = U.USER_ID
    JOIN DEPARTMENTS D ON U.DEPARTMENT_ID = D.DEPARTMENT_ID
    WHERE TS.SUPERVISOR_ID = :supervisor_id
    ORDER BY T.THESIS_ID DESC
";
$researchers_stid = oci_parse($conn, $researchers_sql);
oci_bind_by_name($researchers_stid, ':supervisor_id', $supervisor_id);
oci_execute($researchers_stid);

// 6. Fetch pending thesis requests
$pending_theses_sql = "SELECT T.THESIS_ID, T.TITLE,
           U.FIRST_NAME || ' ' || U.LAST_NAME AS RESEARCHER_NAME
    FROM THESIS_SUPERVISIONS TS
    JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
    JOIN USERS U ON T.RESEARCHER_ID = U.USER_ID
    WHERE TS.SUPERVISOR_ID = :supervisor_id
      AND T.STATUS NOT IN ('APPROVED', 'REJECTED')
    ORDER BY T.THESIS_ID DESC
";
$pending_theses_stid = oci_parse($conn, $pending_theses_sql);
oci_bind_by_name($pending_theses_stid, ':supervisor_id', $supervisor_id);
oci_execute($pending_theses_stid);

// Fetch approved theses list
$approved_theses_sql = "
    SELECT T.THESIS_ID, T.TITLE, T.VERSION_NO,
           U.FIRST_NAME || ' ' || U.LAST_NAME AS RESEARCHER_NAME,
           TO_CHAR(T.SUBMISSION_DATE, 'DD Mon YYYY') AS SUB_DATE
    FROM THESIS_SUPERVISIONS TS
    JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
    JOIN USERS U ON T.RESEARCHER_ID = U.USER_ID
    WHERE TS.SUPERVISOR_ID = :supervisor_id
      AND T.STATUS = 'APPROVED'
    ORDER BY T.THESIS_ID DESC
";
$approved_theses_stid = oci_parse($conn, $approved_theses_sql);
oci_bind_by_name($approved_theses_stid, ':supervisor_id', $supervisor_id);
oci_execute($approved_theses_stid);

$approved_theses_list = [];
while ($row = oci_fetch_assoc($approved_theses_stid)) {
    $approved_theses_list[] = $row;
}

// 7. Fetch theses for review list
$theses_review_sql = "SELECT T.THESIS_ID, T.TITLE, T.STATUS, T.VERSION_NO, CONCAT(CONCAT(U.FIRST_NAME, ' '), U.LAST_NAME) AS RESEARCHER_NAME, D.DEPARTMENT_NAME FROM THESIS_SUPERVISIONS TS 
    JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID 
    JOIN USERS U ON T.RESEARCHER_ID = U.USER_ID 
    JOIN DEPARTMENTS D ON T.DEPARTMENT_ID = D.DEPARTMENT_ID 
    WHERE TS.SUPERVISOR_ID = :supervisor_id 
    ORDER BY T.THESIS_ID DESC";
$theses_review_stid = oci_parse($conn, $theses_review_sql);
oci_bind_by_name($theses_review_stid, ':supervisor_id', $supervisor_id);
oci_execute($theses_review_stid);

// 8. Fetch papers for monitoring
$monitored_papers_sql = "
    SELECT P.PAPER_ID,
           P.TITLE,
           U.FIRST_NAME || ' ' || U.LAST_NAME AS RESEARCHER_NAME,
           P.PUBLICATION_YEAR,
           P.STATUS,
           TO_CHAR(P.SUBMISSION_DATE, 'DD Mon YYYY') AS SUB_DATE,
           R.SCORE,
           R.RECOMMENDATION
    FROM PAPERS P
    JOIN USERS U ON P.RESEARCHER_ID = U.USER_ID
    JOIN (
        SELECT DISTINCT T.RESEARCHER_ID
        FROM THESIS_SUPERVISIONS TS
        JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
        WHERE TS.SUPERVISOR_ID = :supervisor_id
    ) SR ON P.RESEARCHER_ID = SR.RESEARCHER_ID
    LEFT JOIN REVIEW_ASSIGNMENTS RA ON P.PAPER_ID = RA.PAPER_ID
    LEFT JOIN REVIEWS R ON RA.ASSIGNMENT_ID = R.ASSIGNMENT_ID
    ORDER BY P.SUBMISSION_DATE DESC
";
$monitored_papers_stid = oci_parse($conn, $monitored_papers_sql);
oci_bind_by_name($monitored_papers_stid, ':supervisor_id', $supervisor_id);
oci_execute($monitored_papers_stid);

$monitored_papers = [];
while ($row = oci_fetch_assoc($monitored_papers_stid)) {
    $monitored_papers[] = $row;
}

// 9. Fetch feedback and reviews for monitored researchers
$monitored_reviews_sql = "
    SELECT P.TITLE AS PAPER_TITLE,
           U_RES.FIRST_NAME || ' ' || U_RES.LAST_NAME AS RESEARCHER_NAME,
           U_REV.FIRST_NAME || ' ' || U_REV.LAST_NAME AS REVIEWER_NAME,
           R.SCORE,
           DBMS_LOB.SUBSTR(R.COMMENTS, 4000, 1) AS COMMENTS,
           R.RECOMMENDATION,
           TO_CHAR(R.REVIEW_DATE, 'DD Mon YYYY') AS REV_DATE
    FROM REVIEWS R
    JOIN REVIEW_ASSIGNMENTS RA ON R.ASSIGNMENT_ID = RA.ASSIGNMENT_ID
    JOIN PAPERS P ON RA.PAPER_ID = P.PAPER_ID
    JOIN USERS U_RES ON P.RESEARCHER_ID = U_RES.USER_ID
    JOIN USERS U_REV ON RA.REVIEWER_ID = U_REV.USER_ID
    JOIN (
        SELECT DISTINCT T.RESEARCHER_ID
        FROM THESIS_SUPERVISIONS TS
        JOIN THESES T ON TS.THESIS_ID = T.THESIS_ID
        WHERE TS.SUPERVISOR_ID = :supervisor_id
    ) SR ON P.RESEARCHER_ID = SR.RESEARCHER_ID
    ORDER BY R.REVIEW_DATE DESC
";
$monitored_reviews_stid = oci_parse($conn, $monitored_reviews_sql);
oci_bind_by_name($monitored_reviews_stid, ':supervisor_id', $supervisor_id);
oci_execute($monitored_reviews_stid);

$monitored_reviews = [];
while ($row = oci_fetch_assoc($monitored_reviews_stid)) {
    $monitored_reviews[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Supervisor Dashboard | ResearchHub</title>

<link rel="stylesheet" href="supervisor_dashboard.css">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>

<body>

<div class="container">

    <!-- Sidebar -->

    <aside class="sidebar">

        <div class="logo">
            <i class="fas fa-user-tie"></i>
            <span>ResearchHub</span>
        </div>

        <ul>

            <li class="active" id="nav-dashboard">
                <a href="#" onclick="showSection('dashboard'); return false;">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </li>

            <li id="nav-researchers">
                <a href="#" onclick="showSection('researchers'); return false;">
                    <i class="fas fa-users"></i>
                    Assigned Researchers
                </a>
            </li>

            <li id="nav-reviews">
                <a href="#" onclick="showSection('reviews'); return false;">
                    <i class="fas fa-book"></i>
                    Thesis Reviews
                </a>
            </li>

            <li id="nav-paper-monitoring">
                <a href="#" onclick="showSection('paper-monitoring'); return false;">
                    <i class="fas fa-file-alt"></i>
                    Paper Monitoring
                </a>
            </li>

            <li id="nav-approvals">
                <a href="#" onclick="showSection('approvals'); return false;">
                    <i class="fas fa-check-circle"></i>
                    Approvals
                </a>
            </li>

            <li id="nav-feedback">
                <a href="#" onclick="showSection('feedback'); return false;">
                    <i class="fas fa-comments"></i>
                    Feedback
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

        <!-- Topbar -->

        <div class="topbar">

            <div>
                <h2>Supervisor Dashboard</h2>
                <p>Welcome Back, <?php echo htmlspecialchars($displayName); ?></p>
            </div>

            <div class="profile-area">

                <i class="fas fa-bell"></i>

                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($displayName); ?>&background=2563eb&color=fff"
                     alt="Profile">

            </div>

        </div>

        <!-- Statistics -->

        <section class="stats" id="view-stats">

            <div class="card">
                <i class="fas fa-users"></i>
                <h3><?php echo $cnt_researchers; ?></h3>
                <p>Researchers Assigned</p>
            </div>

            <div class="card">
                <i class="fas fa-book-open"></i>
                <h3><?php echo $cnt_theses; ?></h3>
                <p>Active Theses</p>
            </div>

            <div class="card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $cnt_approved; ?></h3>
                <p>Approved Works</p>
            </div>


        </section>

        <!-- Thesis Reviews -->

        <section class="table-section" id="view-thesis-reviews" style="display:none;">

            <div class="section-header">
                <h3>Thesis Reviews</h3>
            </div>

            <table>

                <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Researcher</th>
                    <th>Department</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody>

                <?php 
                    $has_theses_review = false;
                    while ($th = oci_fetch_assoc($theses_review_stid)): 
                        $has_theses_review = true;
                ?>
                    <tr>
                        <td><?php echo (int)$th['THESIS_ID']; ?></td>
                        <td><strong><?php echo htmlspecialchars($th['TITLE']); ?></strong></td>
                        <td><?php echo htmlspecialchars($th['RESEARCHER_NAME']); ?></td>
                        <td><?php echo htmlspecialchars($th['DEPARTMENT_NAME']); ?></td>
                        <td>V<?php echo (int)$th['VERSION_NO']; ?></td>
                        <td>
                            <span class="<?php 
                                if ($th['STATUS'] === 'APPROVED') {
                                    echo 'status-approved';
                                } elseif ($th['STATUS'] === 'REJECTED') {
                                    echo 'status-rejected';
                                } elseif ($th['STATUS'] === 'UNDER REVIEW') {
                                    echo 'status-review';
                                } else {
                                    echo 'status-submitted';
                                }
                            ?>">
                                <?php echo htmlspecialchars($th['STATUS']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($th['STATUS'] === 'SUBMITTED' || $th['STATUS'] === 'UNDER REVIEW'): ?>
                                <button onclick="location.href='../php/review_thesis.php?id=<?php echo $th['THESIS_ID']; ?>&status=APPROVED'" style="padding: 6px 12px; background:#10b981; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px; margin-right:5px; transition: background 0.2s;">
                                    Approve
                                </button>
                                <button onclick="location.href='../php/review_thesis.php?id=<?php echo $th['THESIS_ID']; ?>&status=REJECTED'" style="padding: 6px 12px; background:#ef4444; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px; transition: background 0.2s;">
                                    Reject
                                </button>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-style:italic;">Reviewed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>

                <?php if (!$has_theses_review): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#94a3b8; font-style:italic;">No theses connected for review.</td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <!-- Assigned Researchers -->

        <section class="table-section" id="view-researchers-table">

            <div class="section-header">
                <h3>Assigned Researchers</h3>
            </div>

            <table>

                <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Research Area</th>
                    <th>Progress</th>
                </tr>
                </thead>

                <tbody>

                <?php 
                    $has_researchers = false;
                    while ($res = oci_fetch_assoc($researchers_stid)): 
                        $has_researchers = true;
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['RESEARCHER_NAME']); ?></td>
                        <td><?php echo htmlspecialchars($res['DEPT_NAME']); ?></td>
                        <td><?php echo htmlspecialchars($res['RESEARCH_AREA']); ?></td>
                        <td><?php echo htmlspecialchars($res['PROGRESS']); ?></td>
                    </tr>
                <?php endwhile; ?>

                <?php if (!$has_researchers): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#94a3b8; font-style:italic;">No researchers assigned.</td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <!-- Thesis Approval Requests -->

        <section class="approval-section" id="view-approvals">

            <h3>Pending Thesis Requests</h3>

            <?php 
                $has_pending = false;
                while ($req = oci_fetch_assoc($pending_theses_stid)): 
                    $has_pending = true;
            ?>
                <div class="request-card">

                    <div>
                        <h4><?php echo htmlspecialchars($req['TITLE']); ?></h4>
                        <p>Submitted by: <?php echo htmlspecialchars($req['RESEARCHER_NAME']); ?></p>
                    </div>

                    <div class="buttons">

                        <button class="approve" onclick="location.href='../php/review_thesis.php?id=<?php echo $req['THESIS_ID']; ?>&status=APPROVED'">
                            Approve
                        </button>

                        <button class="reject" onclick="location.href='../php/review_thesis.php?id=<?php echo $req['THESIS_ID']; ?>&status=REJECTED'">
                            Reject
                        </button>

                    </div>

                </div>
            <?php endwhile; ?>

            <?php if (!$has_pending): ?>
                <div style="background:white; padding:25px; border-radius:12px; border: 1px dashed #cbd5e1; text-align:center; color:#64748b; margin-bottom: 30px;">
                    No pending thesis requests.
                </div>
            <?php endif; ?>

            <h3 style="margin-top: 30px; margin-bottom: 20px;">Your Approved Theses</h3>

            <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 14px 20px; font-weight: 600; color: #475569; font-size: 14px;">Thesis Title</th>
                            <th style="padding: 14px 20px; font-weight: 600; color: #475569; font-size: 14px;">Researcher</th>
                            <th style="padding: 14px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Version</th>
                            <th style="padding: 14px 20px; font-weight: 600; color: #475569; font-size: 14px;">Submission Date</th>
                            <th style="padding: 14px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($approved_theses_list)): ?>
                            <tr>
                                <td colspan="5" style="padding: 20px; text-align: center; color: #94a3b8; font-style: italic;">No approved theses found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($approved_theses_list as $thesis): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 14px 20px; color: #0f172a; font-weight: 600; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($thesis['TITLE']); ?>"><?php echo htmlspecialchars($thesis['TITLE']); ?></td>
                                    <td style="padding: 14px 20px; color: #475569;"><?php echo htmlspecialchars($thesis['RESEARCHER_NAME']); ?></td>
                                    <td style="padding: 14px 20px; text-align: center; color: #64748b; font-weight: 500;">v<?php echo htmlspecialchars($thesis['VERSION_NO']); ?></td>
                                    <td style="padding: 14px 20px; color: #64748b;"><?php echo htmlspecialchars($thesis['SUB_DATE']); ?></td>
                                    <td style="padding: 14px 20px; text-align: center;">
                                        <span class="status-badge status-approved" style="background: #ecfdf5; color: #047857; padding: 4px 10px; font-weight: 600; font-size: 12px; border-radius: 12px;">
                                            APPROVED
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

        <!-- Workload -->

        <section class="workload-section" id="view-workload">

            <h3>Current Workload</h3>

            <div class="progress-card">

                <p>Research Supervision Capacity</p>

                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $workload_percent; ?>%;">
                        <?php echo $workload_percent; ?>% (<?php echo $cnt_researchers; ?>/<?php echo $total_researchers; ?> Assigned)
                    </div>
                </div>

            </div>

        </section>

        <!-- Paper Monitoring -->
        <section class="table-section" id="view-paper-monitoring" style="display:none;">
            <div class="section-header">
                <h3>Paper Monitoring</h3>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Paper Title</th>
                    <th>Researcher</th>
                    <th>Year</th>
                    <th>Submission Date</th>
                    <th>Review Score</th>
                    <th>Recommendation</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($monitored_papers)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#94a3b8; font-style:italic;">No papers submitted by your assigned researchers yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($monitored_papers as $paper): ?>
                        <tr>
                            <td style="font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($paper['TITLE']); ?></td>
                            <td><?php echo htmlspecialchars($paper['RESEARCHER_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($paper['PUBLICATION_YEAR']); ?></td>
                            <td><?php echo htmlspecialchars($paper['SUB_DATE']); ?></td>
                            <td style="font-weight:600; color:#2563eb;">
                                <?php echo $paper['SCORE'] !== null ? htmlspecialchars($paper['SCORE']) . '/10' : '-'; ?>
                            </td>
                            <td>
                                <?php 
                                $rec = $paper['RECOMMENDATION'];
                                if ($rec) {
                                    $rec_class = ($rec === 'ACCEPT' || $rec === 'MINOR REVISION') ? 'completed' : (($rec === 'REJECT') ? 'rejected' : 'pending');
                                    echo '<span class="' . $rec_class . '">' . htmlspecialchars($rec) . '</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $stat = $paper['STATUS'];
                                $stat_class = 'pending';
                                if ($stat === 'ACCEPTED' || $stat === 'PUBLISHED') $stat_class = 'completed';
                                elseif ($stat === 'REJECTED') $stat_class = 'rejected';
                                ?>
                                <span class="<?php echo $stat_class; ?>"><?php echo htmlspecialchars($stat); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Feedback & Reviews -->
        <section class="table-section" id="view-feedback" style="display:none;">
            <div class="section-header">
                <h3>Reviewer Feedback & Comments</h3>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Paper Title</th>
                    <th>Researcher</th>
                    <th>Reviewer</th>
                    <th>Score</th>
                    <th>Recommendation</th>
                    <th>Comments</th>
                    <th>Review Date</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($monitored_reviews)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#94a3b8; font-style:italic;">No reviewer reviews or feedback submitted yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($monitored_reviews as $rev): ?>
                        <tr>
                            <td style="font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($rev['PAPER_TITLE']); ?></td>
                            <td><?php echo htmlspecialchars($rev['RESEARCHER_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($rev['REVIEWER_NAME']); ?></td>
                            <td style="font-weight:600; color:#2563eb;"><?php echo htmlspecialchars($rev['SCORE']); ?>/10</td>
                            <td>
                                <?php 
                                $rec = $rev['RECOMMENDATION'];
                                $rec_class = ($rec === 'ACCEPT' || $rec === 'MINOR REVISION') ? 'completed' : (($rec === 'REJECT') ? 'rejected' : 'pending');
                                ?>
                                <span class="<?php echo $rec_class; ?>"><?php echo htmlspecialchars($rec); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($rev['COMMENTS']); ?></td>
                            <td><?php echo htmlspecialchars($rev['REV_DATE']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

    </main>

</div>

<script>
function showSection(section) {
    // Remove active class from all sidebar items
    document.querySelectorAll('.sidebar li').forEach(function(li) {
        li.classList.remove('active');
    });

    var viewStats = document.getElementById('view-stats');
    var viewTable = document.getElementById('view-researchers-table');
    var viewApprovals = document.getElementById('view-approvals');
    var viewWorkload = document.getElementById('view-workload');
    var viewThesisReviews = document.getElementById('view-thesis-reviews');
    var viewPaperMonitoring = document.getElementById('view-paper-monitoring');
    var viewFeedback = document.getElementById('view-feedback');

    // Hide all views first
    if (viewStats) viewStats.style.display = 'none';
    if (viewTable) viewTable.style.display = 'none';
    if (viewApprovals) viewApprovals.style.display = 'none';
    if (viewWorkload) viewWorkload.style.display = 'none';
    if (viewThesisReviews) viewThesisReviews.style.display = 'none';
    if (viewPaperMonitoring) viewPaperMonitoring.style.display = 'none';
    if (viewFeedback) viewFeedback.style.display = 'none';

    if (section === 'dashboard') {
        var navDash = document.getElementById('nav-dashboard');
        if (navDash) navDash.classList.add('active');

        if (viewStats) viewStats.style.display = 'grid';
        if (viewTable) viewTable.style.display = 'block';
        if (viewApprovals) viewApprovals.style.display = 'block';
        if (viewWorkload) viewWorkload.style.display = 'block';

    } else if (section === 'researchers') {
        var navRes = document.getElementById('nav-researchers');
        if (navRes) navRes.classList.add('active');
        if (viewTable) viewTable.style.display = 'block';

    } else if (section === 'reviews') {
        var navReviews = document.getElementById('nav-reviews');
        if (navReviews) navReviews.classList.add('active');
        if (viewThesisReviews) viewThesisReviews.style.display = 'block';

    } else if (section === 'paper-monitoring') {
        var navPaperMon = document.getElementById('nav-paper-monitoring');
        if (navPaperMon) navPaperMon.classList.add('active');
        if (viewPaperMonitoring) viewPaperMonitoring.style.display = 'block';

    } else if (section === 'feedback') {
        var navFeed = document.getElementById('nav-feedback');
        if (navFeed) navFeed.classList.add('active');
        if (viewFeedback) viewFeedback.style.display = 'block';
    } else if (section === 'approvals') {
        var navApp = document.getElementById('nav-approvals');
        if (navApp) navApp.classList.add('active');
        if (viewApprovals) viewApprovals.style.display = 'block';
    }
}
</script>

</body>
</html>
