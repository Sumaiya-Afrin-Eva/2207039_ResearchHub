<?php
session_start();
include_once '../php/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

$displayName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$count_users_sql = "SELECT COUNT(*) AS TOTAL FROM USERS";
$stid = oci_parse($conn, $count_users_sql);
oci_execute($stid);
$users_row = oci_fetch_assoc($stid);
$total_users = $users_row['TOTAL'] ?? 0;

$count_papers_sql = "SELECT COUNT(*) AS TOTAL FROM PAPERS";
$stid = oci_parse($conn, $count_papers_sql);
oci_execute($stid);
$papers_row = oci_fetch_assoc($stid);
$total_papers = $papers_row['TOTAL'] ?? 0;

$count_theses_sql = "SELECT COUNT(*) AS TOTAL FROM THESES";
$stid = oci_parse($conn, $count_theses_sql);
oci_execute($stid);
$theses_row = oci_fetch_assoc($stid);
$total_theses = $theses_row['TOTAL'] ?? 0;

$count_depts_sql = "SELECT COUNT(*) AS TOTAL FROM DEPARTMENTS";
$stid = oci_parse($conn, $count_depts_sql);
oci_execute($stid);
$depts_row = oci_fetch_assoc($stid);
$total_depts = $depts_row['TOTAL'] ?? 0;

$users_sql = "
    SELECT U.USER_ID, U.FIRST_NAME, U.LAST_NAME, U.EMAIL, U.INSTITUTION,
           U.DEPARTMENT_ID, U.ROLE_ID, U.STATUS,
           R.ROLE_NAME, D.DEPARTMENT_NAME
    FROM USERS U
    JOIN ROLE R ON U.ROLE_ID = R.ROLE_ID
    JOIN DEPARTMENTS D ON U.DEPARTMENT_ID = D.DEPARTMENT_ID
    ORDER BY U.USER_ID DESC
";
$users_stid = oci_parse($conn, $users_sql);
oci_execute($users_stid);

$roles_sql = "SELECT ROLE_ID, ROLE_NAME FROM ROLE ORDER BY ROLE_ID";
$roles_stid = oci_parse($conn, $roles_sql);
oci_execute($roles_stid);

$depts_sql = "SELECT DEPARTMENT_ID, DEPARTMENT_NAME FROM DEPARTMENTS ORDER BY DEPARTMENT_NAME";
$depts_stid = oci_parse($conn, $depts_sql);
oci_execute($depts_stid);

// Query for thesis department list dropdown
$depts_thesis_sql = "SELECT DEPARTMENT_ID, DEPARTMENT_NAME FROM DEPARTMENTS ORDER BY DEPARTMENT_NAME";
$depts_thesis_stid = oci_parse($conn, $depts_thesis_sql);
oci_execute($depts_thesis_stid);

// Query for researchers list dropdown
$researchers_sql = "SELECT USER_ID, FIRST_NAME, LAST_NAME FROM USERS WHERE ROLE_ID = 2 ORDER BY FIRST_NAME, LAST_NAME";
$researchers_stid = oci_parse($conn, $researchers_sql);
oci_execute($researchers_stid);

// Query for paper researcher list dropdown
$researchers_paper_sql = "SELECT USER_ID, FIRST_NAME, LAST_NAME FROM USERS WHERE ROLE_ID = 2 ORDER BY FIRST_NAME, LAST_NAME";
$researchers_paper_stid = oci_parse($conn, $researchers_paper_sql);
oci_execute($researchers_paper_stid);

// Query for supervisors list dropdown
$supervisors_sql = "SELECT USER_ID, FIRST_NAME, LAST_NAME FROM USERS WHERE ROLE_ID = 3 ORDER BY FIRST_NAME, LAST_NAME";
$supervisors_stid = oci_parse($conn, $supervisors_sql);
oci_execute($supervisors_stid);

// Full departments table data (with member count via LEFT JOIN)
$depts_table_sql = "
    SELECT D.DEPARTMENT_ID, D.DEPARTMENT_NAME, D.FACULTY,
           COUNT(U.USER_ID) AS TOTAL_MEMBERS
    FROM   DEPARTMENTS D
    LEFT JOIN USERS U ON U.DEPARTMENT_ID = D.DEPARTMENT_ID
    GROUP BY D.DEPARTMENT_ID, D.DEPARTMENT_NAME, D.FACULTY
    ORDER BY D.DEPARTMENT_ID
";
$depts_table_stid = oci_parse($conn, $depts_table_sql);
oci_execute($depts_table_stid);

// Full theses table data
$theses_table_sql = "
    SELECT T.THESIS_ID, T.TITLE, T.ABSTRACT, T.VERSION_NO, T.STATUS,
           T.RESEARCHER_ID, T.DEPARTMENT_ID,
           TO_CHAR(T.SUBMISSION_DATE, 'YYYY-MM-DD') AS SUB_DATE,
           U.FIRST_NAME || ' ' || U.LAST_NAME AS RESEARCHER_NAME,
           D.DEPARTMENT_NAME,
           TS.SUPERVISOR_ID,
           COALESCE(SV.FIRST_NAME || ' ' || SV.LAST_NAME, 'Not Assigned') AS SUPERVISOR_NAME
    FROM THESES T
    JOIN USERS U ON T.RESEARCHER_ID = U.USER_ID
    JOIN DEPARTMENTS D ON T.DEPARTMENT_ID = D.DEPARTMENT_ID
    LEFT JOIN THESIS_SUPERVISIONS TS ON T.THESIS_ID = TS.THESIS_ID AND TS.SUPERVISOR_TYPE = 'PRIMARY'
    LEFT JOIN USERS SV ON TS.SUPERVISOR_ID = SV.USER_ID
    ORDER BY T.THESIS_ID DESC
";
$theses_table_stid = oci_parse($conn, $theses_table_sql);
oci_execute($theses_table_stid);

// Full research papers table data (with co-authors listagg)
$papers_table_sql = "
    SELECT P.PAPER_ID, P.TITLE, P.ABSTRACT, P.KEYWORDS, P.PUBLICATION_YEAR, P.STATUS, P.RESEARCHER_ID,
           TO_CHAR(P.SUBMISSION_DATE, 'YYYY-MM-DD') AS SUB_DATE,
           U.FIRST_NAME || ' ' || U.LAST_NAME AS RESEARCHER_NAME,
            (
                SELECT LISTAGG(PA.AUTHOR_NAME, ', ') WITHIN GROUP (ORDER BY PA.AUTHOR_NAME)
                FROM PAPER_AUTHORS PA
                WHERE PA.PAPER_ID = P.PAPER_ID
                  AND PA.AUTHOR_NAME != (U.FIRST_NAME || ' ' || U.LAST_NAME)
            ) AS AUTHORS
    FROM PAPERS P
    JOIN USERS U ON P.RESEARCHER_ID = U.USER_ID
    ORDER BY P.PAPER_ID DESC
";
$papers_table_stid = oci_parse($conn, $papers_table_sql);
oci_execute($papers_table_stid);

// Full reviews table data
$reviews_table_sql = "
    SELECT R.REVIEW_ID,
           R.ASSIGNMENT_ID,
           R.SCORE,
           DBMS_LOB.SUBSTR(R.COMMENTS, 4000, 1) AS COMMENTS,
           TO_CHAR(R.REVIEW_DATE, 'YYYY-MM-DD') AS REV_DATE,
           R.RECOMMENDATION,
           P.TITLE AS PAPER_TITLE,
           U_REV.FIRST_NAME || ' ' || U_REV.LAST_NAME AS REVIEWER_NAME,
           U_RES.FIRST_NAME || ' ' || U_RES.LAST_NAME AS RESEARCHER_NAME
    FROM REVIEWS R
    JOIN REVIEW_ASSIGNMENTS RA ON R.ASSIGNMENT_ID = RA.ASSIGNMENT_ID
    JOIN PAPERS P ON RA.PAPER_ID = P.PAPER_ID
    JOIN USERS U_REV ON RA.REVIEWER_ID = U_REV.USER_ID
    JOIN USERS U_RES ON P.RESEARCHER_ID = U_RES.USER_ID
    ORDER BY R.REVIEW_ID DESC
";
$reviews_table_stid = oci_parse($conn, $reviews_table_sql);
oci_execute($reviews_table_stid);

$reviews_list = [];
while ($row = oci_fetch_assoc($reviews_table_stid)) {
    $reviews_list[] = $row;
}

// --- Proposal Analytics Queries ---

// Summary card queries
$papers_this_year_sql = "SELECT COUNT(*) AS TOTAL FROM PAPERS WHERE PUBLICATION_YEAR = EXTRACT(YEAR FROM SYSDATE)";
$stid_pt = oci_parse($conn, $papers_this_year_sql);
oci_execute($stid_pt);
$row_pt = oci_fetch_assoc($stid_pt);
$papers_this_year = $row_pt['TOTAL'] ?? 0;

$reviews_completed_sql = "SELECT COUNT(*) AS TOTAL FROM REVIEWS";
$stid_rc = oci_parse($conn, $reviews_completed_sql);
oci_execute($stid_rc);
$row_rc = oci_fetch_assoc($stid_rc);
$reviews_completed = $row_rc['TOTAL'] ?? 0;

$top_dept_sql = "
    SELECT D.DEPARTMENT_NAME
    FROM DEPARTMENTS D
    ORDER BY (
        (SELECT COUNT(*) FROM PAPERS P JOIN USERS U ON P.RESEARCHER_ID = U.USER_ID WHERE U.DEPARTMENT_ID = D.DEPARTMENT_ID) +
        (SELECT COUNT(*) FROM THESES T WHERE T.DEPARTMENT_ID = D.DEPARTMENT_ID)
    ) DESC
    FETCH FIRST 1 ROW ONLY
";
$stid_td = oci_parse($conn, $top_dept_sql);
oci_execute($stid_td);
$row_td = oci_fetch_assoc($stid_td);
$top_dept_name = $row_td['DEPARTMENT_NAME'] ?? 'None';

// 1. Publications by Department
$pub_dept_sql = "
    SELECT D.DEPARTMENT_NAME,
           (SELECT COUNT(*) FROM PAPERS P JOIN USERS U ON P.RESEARCHER_ID = U.USER_ID WHERE U.DEPARTMENT_ID = D.DEPARTMENT_ID) AS PAPER_COUNT,
           (SELECT COUNT(*) FROM THESES T WHERE T.DEPARTMENT_ID = D.DEPARTMENT_ID) AS THESIS_COUNT,
           ((SELECT COUNT(*) FROM PAPERS P JOIN USERS U ON P.RESEARCHER_ID = U.USER_ID WHERE U.DEPARTMENT_ID = D.DEPARTMENT_ID) +
            (SELECT COUNT(*) FROM THESES T WHERE T.DEPARTMENT_ID = D.DEPARTMENT_ID)) AS TOTAL_PUBLICATIONS
    FROM DEPARTMENTS D
    ORDER BY TOTAL_PUBLICATIONS DESC
";
$pub_dept_stid = oci_parse($conn, $pub_dept_sql);
oci_execute($pub_dept_stid);
$pub_dept_list = [];
while ($row = oci_fetch_assoc($pub_dept_stid)) {
    $pub_dept_list[] = $row;
}

// 2. Publications by Year
$pub_year_sql = "
    SELECT COALESCE(P.YEAR, T.YEAR) AS PUB_YEAR,
           COALESCE(P.PAPER_COUNT, 0) AS PAPER_COUNT,
           COALESCE(T.THESIS_COUNT, 0) AS THESIS_COUNT,
           (COALESCE(P.PAPER_COUNT, 0) + COALESCE(T.THESIS_COUNT, 0)) AS TOTAL_COUNT
    FROM (
        SELECT PUBLICATION_YEAR AS YEAR, COUNT(*) AS PAPER_COUNT
        FROM PAPERS
        GROUP BY PUBLICATION_YEAR
    ) P
    FULL OUTER JOIN (
        SELECT EXTRACT(YEAR FROM SUBMISSION_DATE) AS YEAR, COUNT(*) AS THESIS_COUNT
        FROM THESES
        GROUP BY EXTRACT(YEAR FROM SUBMISSION_DATE)
    ) T ON P.YEAR = T.YEAR
    ORDER BY PUB_YEAR DESC
";
$pub_year_stid = oci_parse($conn, $pub_year_sql);
oci_execute($pub_year_stid);
$pub_year_list = [];
while ($row = oci_fetch_assoc($pub_year_stid)) {
    $pub_year_list[] = $row;
}

// 3. Supervisor Workload
$super_workload_sql = "
    SELECT U.FIRST_NAME || ' ' || U.LAST_NAME AS SUPERVISOR_NAME,
           D.DEPARTMENT_NAME,
           COUNT(TS.THESIS_ID) AS ACTIVE_THESES
    FROM USERS U
    JOIN DEPARTMENTS D ON U.DEPARTMENT_ID = D.DEPARTMENT_ID
    LEFT JOIN THESIS_SUPERVISIONS TS ON U.USER_ID = TS.SUPERVISOR_ID AND TS.SUPERVISOR_TYPE = 'PRIMARY'
    WHERE U.ROLE_ID = 3
    GROUP BY U.USER_ID, U.FIRST_NAME, U.LAST_NAME, D.DEPARTMENT_NAME
    ORDER BY ACTIVE_THESES DESC, SUPERVISOR_NAME ASC
";
$super_workload_stid = oci_parse($conn, $super_workload_sql);
oci_execute($super_workload_stid);
$super_workload_list = [];
while ($row = oci_fetch_assoc($super_workload_stid)) {
    $super_workload_list[] = $row;
}

// 4. Review Statistics
$review_stats_sql = "
    SELECT COUNT(*) AS TOTAL_REVIEWS,
           ROUND(AVG(SCORE), 2) AS AVG_SCORE,
           MAX(SCORE) AS MAX_SCORE,
           MIN(SCORE) AS MIN_SCORE,
           SUM(CASE WHEN RECOMMENDATION IN ('ACCEPT', 'MINOR REVISION') THEN 1 ELSE 0 END) AS ACCEPT_COUNT,
           SUM(CASE WHEN RECOMMENDATION = 'REJECT' THEN 1 ELSE 0 END) AS REJECT_COUNT,
           SUM(CASE WHEN RECOMMENDATION = 'MAJOR REVISION' THEN 1 ELSE 0 END) AS REVISION_COUNT
    FROM REVIEWS
";
$review_stats_stid = oci_parse($conn, $review_stats_sql);
oci_execute($review_stats_stid);
$review_stats_row = oci_fetch_assoc($review_stats_stid);

// 5. Top Researchers
$top_researchers_sql = "
    SELECT U.FIRST_NAME || ' ' || U.LAST_NAME AS RESEARCHER_NAME,
           D.DEPARTMENT_NAME,
           (SELECT COUNT(*) FROM PAPERS P WHERE P.RESEARCHER_ID = U.USER_ID AND P.STATUS = 'ACCEPTED') AS APPROVED_PAPERS,
           (SELECT COUNT(*) FROM THESES T WHERE T.RESEARCHER_ID = U.USER_ID AND T.STATUS = 'APPROVED') AS APPROVED_THESES,
           ((SELECT COUNT(*) FROM PAPERS P WHERE P.RESEARCHER_ID = U.USER_ID AND P.STATUS = 'ACCEPTED') +
            (SELECT COUNT(*) FROM THESES T WHERE T.RESEARCHER_ID = U.USER_ID AND T.STATUS = 'APPROVED')) AS TOTAL_APPROVED
    FROM USERS U
    JOIN DEPARTMENTS D ON U.DEPARTMENT_ID = D.DEPARTMENT_ID
    WHERE U.ROLE_ID = 2
    ORDER BY TOTAL_APPROVED DESC, RESEARCHER_NAME ASC
";
$top_researchers_stid = oci_parse($conn, $top_researchers_sql);
oci_execute($top_researchers_stid);
$top_researchers_list = [];
while ($row = oci_fetch_assoc($top_researchers_stid)) {
    $top_researchers_list[] = $row;
}

// Handle Assign Reviewer POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_reviewer') {
    $paper_id = isset($_POST['paper_id']) ? (int)$_POST['paper_id'] : 0;
    $reviewer_id = isset($_POST['reviewer_id']) ? (int)$_POST['reviewer_id'] : 0;

    if ($paper_id > 0 && $reviewer_id > 0) {
        // Fetch next assignment ID
        $id_sql = "SELECT COALESCE(MAX(ASSIGNMENT_ID), 0) + 1 AS NEXT_ID FROM REVIEW_ASSIGNMENTS";
        $id_stid = oci_parse($conn, $id_sql);
        oci_execute($id_stid);
        $id_row = oci_fetch_assoc($id_stid);
        $next_assignment_id = $id_row['NEXT_ID'];

        // Call the ASSIGN_REVIEWER stored procedure inside a PL/SQL block
        $stmt = oci_parse($conn, "BEGIN ASSIGN_REVIEWER(:assignment_id, :paper_id, :reviewer_id); END;");
        oci_bind_by_name($stmt, ':assignment_id', $next_assignment_id);
        oci_bind_by_name($stmt, ':paper_id', $paper_id);
        oci_bind_by_name($stmt, ':reviewer_id', $reviewer_id);

        $r = oci_execute($stmt);
        if ($r) {
            header("Location: admin_dashboard.php?success=" . urlencode("Reviewer assigned successfully."));
            exit();
        } else {
            $e = oci_error($stmt);
            header("Location: admin_dashboard.php?error=" . urlencode("Failed to assign reviewer: " . $e['message']));
            exit();
        }
    } else {
        header("Location: admin_dashboard.php?error=" . urlencode("Invalid paper or reviewer selection."));
        exit();
    }
}

// Query for reviewers list dropdown
$reviewers_dropdown_sql = "SELECT USER_ID, FIRST_NAME || ' ' || LAST_NAME AS FULL_NAME FROM USERS WHERE ROLE_ID = 4 AND STATUS = 'ACTIVE' ORDER BY FIRST_NAME, LAST_NAME";
$reviewers_dropdown_stid = oci_parse($conn, $reviewers_dropdown_sql);
oci_execute($reviewers_dropdown_stid);

$reviewers_dropdown_list = [];
while ($row = oci_fetch_assoc($reviewers_dropdown_stid)) {
    $reviewers_dropdown_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | ResearchHub</title>

<link rel="stylesheet" href="admin_dashboard.css">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>

<body>

<div class="container">

    <aside class="sidebar">

        <div class="logo">
            <i class="fas fa-user-shield"></i>
            <span>ResearchHub</span>
        </div>

        <ul>

            <li class="active" id="nav-dashboard">
                <a href="#" onclick="showSection('dashboard'); return false;">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </li>

            <li id="nav-users">
                <a href="#" onclick="showSection('users'); return false;">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-user-tag"></i>
                    Roles
                </a>
            </li>

            <li id="nav-papers">
                <a href="#" onclick="showSection('papers'); return false;">
                    <i class="fas fa-file-alt"></i>
                    Research Papers
                </a>
            </li>

            <li id="nav-theses">
                <a href="#" onclick="showSection('theses'); return false;">
                    <i class="fas fa-book"></i>
                    Theses
                </a>
            </li>

            <li id="nav-departments">
                <a href="#" onclick="showSection('departments'); return false;">
                    <i class="fas fa-building"></i>
                    Departments
                </a>
            </li>

            <li id="nav-reviews">
                <a href="#" onclick="showSection('reviews'); return false;">
                    <i class="fas fa-star"></i>
                    Reviews
                </a>
            </li>

            <li id="nav-analytics">
                <a href="#" onclick="showSection('analytics'); return false;">
                    <i class="fas fa-chart-bar"></i>
                    Analytics
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-history"></i>
                    Audit Logs
                </a>
            </li>

            <li>
                <a href="#">
                    <i class="fas fa-cog"></i>
                    Settings
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

    <main class="main-content">

        <div class="topbar">

            <div>
                <h2>Admin Dashboard</h2>
                <p>Welcome Back, <?php echo htmlspecialchars($displayName); ?></p>
            </div>

            <div class="top-right">

                <i class="fas fa-bell"></i>

                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($displayName); ?>&background=2563eb&color=fff"
                     alt="Admin">

            </div>

        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-banner success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_GET['success']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert-banner error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_GET['error']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Dashboard home sections -->
        <section class="stats page-view" id="view-dashboard">

            <div class="card">
                <i class="fas fa-users"></i>
                <h3><?php echo number_format($total_users); ?></h3>
                <p>Total Users</p>
            </div>

            <div class="card">
                <i class="fas fa-file-alt"></i>
                <h3><?php echo number_format($total_papers); ?></h3>
                <p>Research Papers</p>
            </div>

            <div class="card">
                <i class="fas fa-book"></i>
                <h3><?php echo number_format($total_theses); ?></h3>
                <p>Theses</p>
            </div>

            <div class="card">
                <i class="fas fa-building"></i>
                <h3><?php echo number_format($total_depts); ?></h3>
                <p>Departments</p>
            </div>

        </section>

        <section class="quick-actions page-view" id="view-dashboard-actions">

            <h3>Quick Actions</h3>

            <div class="action-grid">

                <div class="action-box" id="actionAddUser">
                    <i class="fas fa-user-plus"></i>
                    <h4>Add User</h4>
                </div>

                <div class="action-box" id="actionAddDept" onclick="openAddDeptModal()">
                    <i class="fas fa-plus-circle"></i>
                    <h4>Add Department</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-chart-line"></i>
                    <h4>Generate Report</h4>
                </div>

                <div class="action-box">
                    <i class="fas fa-history"></i>
                    <h4>View Logs</h4>
                </div>

            </div>

        </section>

        <section class="table-section page-view" id="view-users">

            <div class="table-header">
                <h3>Recent Users</h3>

                <button class="add-btn" id="addUserBtn">
                    + Add User
                </button>
            </div>

            <table>

                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php while ($row = oci_fetch_assoc($users_stid)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['FIRST_NAME'] . ' ' . $row['LAST_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($row['ROLE_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($row['DEPARTMENT_NAME']); ?></td>
                            <td>
                                <span class="<?php echo ($row['STATUS'] === 'ACTIVE') ? 'active-status' : 'inactive-status'; ?>">
                                    <?php echo htmlspecialchars($row['STATUS']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="edit"
                                    onclick="openEditModal(this)"
                                    data-user-id="<?php echo (int)$row['USER_ID']; ?>"
                                    data-first-name="<?php echo htmlspecialchars($row['FIRST_NAME'], ENT_QUOTES); ?>"
                                    data-last-name="<?php echo htmlspecialchars($row['LAST_NAME'], ENT_QUOTES); ?>"
                                    data-email="<?php echo htmlspecialchars($row['EMAIL'], ENT_QUOTES); ?>"
                                    data-institution="<?php echo htmlspecialchars($row['INSTITUTION'], ENT_QUOTES); ?>"
                                    data-department-id="<?php echo (int)$row['DEPARTMENT_ID']; ?>"
                                    data-role-id="<?php echo (int)$row['ROLE_ID']; ?>"
                                    data-status="<?php echo htmlspecialchars($row['STATUS'], ENT_QUOTES); ?>"
                                >Edit</button>
                                <button class="delete"
                                    onclick="confirmDelete(this)"
                                    data-user-id="<?php echo (int)$row['USER_ID']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($row['FIRST_NAME'] . ' ' . $row['LAST_NAME'], ENT_QUOTES); ?>"
                                >Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>

            </table>

        </section>

        <section class="analytics page-view" id="view-analytics-summary">

            <div class="analytics-card">
                <h3>Publication Statistics</h3>
                <p>Research Papers Published This Year</p>
                <h2><?php echo number_format($papers_this_year); ?></h2>
            </div>

            <div class="analytics-card">
                <h3>Review Statistics</h3>
                <p>Total Reviews Completed</p>
                <h2><?php echo number_format($reviews_completed); ?></h2>
            </div>

            <div class="analytics-card">
                <h3>Top Department</h3>
                <p>Highest Publication Count</p>
                <h2><?php echo htmlspecialchars($top_dept_name); ?></h2>
            </div>

        </section>

        <!-- ─── Proposal Analytics Detail Section ────────────────── -->
        <section class="page-view" id="view-analytics-detail" style="display:none;">

            <!-- Row 1: Review Statistics Metrics Card & Recommendation Splits Card -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px;">
                
                <div class="analytics-card" style="margin: 0; background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; text-align: left;">
                    <h4 style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-star-half-alt" style="color: #3b82f6;"></i> Review Scoring Metrics
                    </h4>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #64748b; font-size: 14px;">Total Reviews Submitted</span>
                        <span style="font-weight: 700; color: #0f172a; font-size: 14px;"><?php echo (int)($review_stats_row['TOTAL_REVIEWS'] ?? 0); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #64748b; font-size: 14px;">Average Score</span>
                        <span style="font-weight: 700; color: #2563eb; font-size: 14px;"><?php echo $review_stats_row['AVG_SCORE'] !== null ? $review_stats_row['AVG_SCORE'] : '0'; ?>/10</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #64748b; font-size: 14px;">Highest Score Given</span>
                        <span style="font-weight: 700; color: #16a34a; font-size: 14px;"><?php echo $review_stats_row['MAX_SCORE'] !== null ? (int)$review_stats_row['MAX_SCORE'] : '0'; ?>/10</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b; font-size: 14px;">Lowest Score Given</span>
                        <span style="font-weight: 700; color: #dc2626; font-size: 14px;"><?php echo $review_stats_row['MIN_SCORE'] !== null ? (int)$review_stats_row['MIN_SCORE'] : '0'; ?>/10</span>
                    </div>
                </div>

                <div class="analytics-card" style="margin: 0; background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; text-align: left;">
                    <h4 style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-chart-pie" style="color: #10b981;"></i> Recommendation Splits
                    </h4>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #64748b; font-size: 14px;">Accepted (Accept/Minor)</span>
                        <span style="font-weight: 700; color: #16a34a; font-size: 14px;"><?php echo (int)($review_stats_row['ACCEPT_COUNT'] ?? 0); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #64748b; font-size: 14px;">Rejected</span>
                        <span style="font-weight: 700; color: #dc2626; font-size: 14px;"><?php echo (int)($review_stats_row['REJECT_COUNT'] ?? 0); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b; font-size: 14px;">Major Revision Pending</span>
                        <span style="font-weight: 700; color: #d97706; font-size: 14px;"><?php echo (int)($review_stats_row['REVISION_COUNT'] ?? 0); ?></span>
                    </div>
                </div>

            </div>

            <!-- Publications by Department -->
            <div class="table-section" style="margin-bottom: 25px; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden;">
                <div class="table-header" style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-building" style="color: #64748b;"></i>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #0f172a;">Publications by Department</h3>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px;">Department Name</th>
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Papers</th>
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Theses</th>
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Total Publications</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pub_dept_list)): ?>
                            <tr>
                                <td colspan="4" style="padding: 16px 20px; text-align: center; color: #94a3b8;">No publications data found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pub_dept_list as $row): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 14px 20px; color: #0f172a; font-weight: 500;"><?php echo htmlspecialchars($row['DEPARTMENT_NAME']); ?></td>
                                    <td style="padding: 14px 20px; text-align: center; color: #475569;"><?php echo (int)$row['PAPER_COUNT']; ?></td>
                                    <td style="padding: 14px 20px; text-align: center; color: #475569;"><?php echo (int)$row['THESIS_COUNT']; ?></td>
                                    <td style="padding: 14px 20px; text-align: center; font-weight: 600; color: #2563eb;"><?php echo (int)$row['TOTAL_PUBLICATIONS']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Publications by Year -->
            <div class="table-section" style="margin-bottom: 25px; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden;">
                <div class="table-header" style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-calendar-alt" style="color: #64748b;"></i>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #0f172a;">Publications by Year</h3>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px;">Year</th>
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Papers Published</th>
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Theses Submitted</th>
                            <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Total Combined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pub_year_list)): ?>
                            <tr>
                                <td colspan="4" style="padding: 16px 20px; text-align: center; color: #94a3b8;">No yearly data found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pub_year_list as $row): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 14px 20px; color: #0f172a; font-weight: 600;"><?php echo htmlspecialchars($row['PUB_YEAR']); ?></td>
                                    <td style="padding: 14px 20px; text-align: center; color: #475569;"><?php echo (int)$row['PAPER_COUNT']; ?></td>
                                    <td style="padding: 14px 20px; text-align: center; color: #475569;"><?php echo (int)$row['THESIS_COUNT']; ?></td>
                                    <td style="padding: 14px 20px; text-align: center; font-weight: 600; color: #2563eb;"><?php echo (int)$row['TOTAL_COUNT']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Two Column Row: Supervisor Workload & Top Researchers -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(45%, 1fr)); gap: 25px;">
                
                <!-- Supervisor Workload -->
                <div class="table-section" style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden; height: fit-content;">
                    <div class="table-header" style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-tie" style="color: #64748b;"></i>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #0f172a;">Supervisor Workload</h3>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; border-bottom: 1px solid #e2e8f0;">
                                <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px;">Supervisor</th>
                                <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px;">Department</th>
                                <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Active Theses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($super_workload_list)): ?>
                                <tr>
                                    <td colspan="3" style="padding: 16px 20px; text-align: center; color: #94a3b8;">No supervisor data found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($super_workload_list as $row): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 14px 20px; color: #0f172a; font-weight: 500;"><?php echo htmlspecialchars($row['SUPERVISOR_NAME']); ?></td>
                                        <td style="padding: 14px 20px; color: #64748b;"><?php echo htmlspecialchars($row['DEPARTMENT_NAME']); ?></td>
                                        <td style="padding: 14px 20px; text-align: center; font-weight: 600; color: #2563eb;"><?php echo (int)$row['ACTIVE_THESES']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Top Researchers -->
                <div class="table-section" style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden; height: fit-content;">
                    <div class="table-header" style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-medal" style="color: #64748b;"></i>
                        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #0f172a;">Top Researchers</h3>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; border-bottom: 1px solid #e2e8f0;">
                                <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px;">Researcher</th>
                                <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px;">Department</th>
                                <th style="padding: 12px 20px; font-weight: 600; color: #475569; font-size: 14px; text-align: center;">Approved Pubs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_researchers_list)): ?>
                                <tr>
                                    <td colspan="3" style="padding: 16px 20px; text-align: center; color: #94a3b8;">No researcher data found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_researchers_list as $row): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 14px 20px; color: #0f172a; font-weight: 500;"><?php echo htmlspecialchars($row['RESEARCHER_NAME']); ?></td>
                                        <td style="padding: 14px 20px; color: #64748b;"><?php echo htmlspecialchars($row['DEPARTMENT_NAME']); ?></td>
                                        <td style="padding: 14px 20px; text-align: center; font-weight: 600; color: #16a34a;"><?php echo (int)$row['TOTAL_APPROVED']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </section>

        <section class="logs-section page-view" id="view-logs">

            <h3>Recent Audit Logs</h3>

            <div class="log-item">
                Admin updated Research Paper #105
                <span>2 mins ago</span>
            </div>

            <div class="log-item">
                New Reviewer Account Created
                <span>12 mins ago</span>
            </div>

            <div class="log-item">
                Department Information Modified
                <span>25 mins ago</span>
            </div>

            <div class="log-item">
                User Account Deleted
                <span>1 hour ago</span>
            </div>

        </section>

        <!-- ─── Departments Section ─────────────────────────── -->
        <section class="table-section page-view" id="view-departments" style="display:none;">

            <div class="table-header">
                <h3>Departments</h3>
                <button class="add-btn" id="addDeptBtn" onclick="openAddDeptModal()">+ Add Department</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Department Name</th>
                        <th>Faculty</th>
                        <th>Total Members</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($dept = oci_fetch_assoc($depts_table_stid)): ?>
                        <tr>
                            <td><?php echo (int)$dept['DEPARTMENT_ID']; ?></td>
                            <td><strong><?php echo htmlspecialchars($dept['DEPARTMENT_NAME']); ?></strong></td>
                            <td>
                                <span class="faculty-badge">
                                    <?php echo htmlspecialchars($dept['FACULTY']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="member-count">
                                    <i class="fas fa-users" style="font-size:12px;margin-right:5px;"></i>
                                    <?php echo (int)$dept['TOTAL_MEMBERS']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="edit"
                                    onclick="openEditDeptModal(this)"
                                    data-dept-id="<?php echo (int)$dept['DEPARTMENT_ID']; ?>"
                                    data-dept-name="<?php echo htmlspecialchars($dept['DEPARTMENT_NAME'], ENT_QUOTES); ?>"
                                    data-faculty="<?php echo htmlspecialchars($dept['FACULTY'], ENT_QUOTES); ?>"
                                >Edit</button>
                                <button class="delete"
                                    onclick="confirmDeleteDept(this)"
                                    data-dept-id="<?php echo (int)$dept['DEPARTMENT_ID']; ?>"
                                    data-dept-name="<?php echo htmlspecialchars($dept['DEPARTMENT_NAME'], ENT_QUOTES); ?>"
                                >Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        </section>

        <!-- ─── Theses Section ─────────────────────────────── -->
        <section class="table-section page-view" id="view-theses" style="display:none;">

            <div class="table-header">
                <h3>Theses</h3>
                <button class="add-btn" id="addThesisBtn" onclick="openAddThesisModal()">+ Add Thesis</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Researcher</th>
                        <th>Department</th>
                        <th>Supervisor</th>
                        <th>Submission Date</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($thesis = oci_fetch_assoc($theses_table_stid)): ?>
                        <tr>
                            <td><?php echo (int)$thesis['THESIS_ID']; ?></td>
                            <td><strong><?php echo htmlspecialchars($thesis['TITLE']); ?></strong></td>
                            <td><?php echo htmlspecialchars($thesis['RESEARCHER_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($thesis['DEPARTMENT_NAME']); ?></td>
                            <td>
                                <span class="supervisor-badge">
                                    <i class="fas fa-user-tie" style="font-size:12px;margin-right:5px;"></i>
                                    <?php echo htmlspecialchars($thesis['SUPERVISOR_NAME']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($thesis['SUB_DATE']); ?></td>
                            <td>
                                <span class="version-badge">
                                    V<?php echo (int)$thesis['VERSION_NO']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?php 
                                    if ($thesis['STATUS'] === 'APPROVED') {
                                        echo 'status-approved';
                                    } elseif ($thesis['STATUS'] === 'REJECTED') {
                                        echo 'status-rejected';
                                    } elseif ($thesis['STATUS'] === 'UNDER REVIEW') {
                                        echo 'status-review';
                                    } else {
                                        echo 'status-submitted';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($thesis['STATUS']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $thesis_abstract = "";
                                    if (isset($thesis['ABSTRACT'])) {
                                        if (is_object($thesis['ABSTRACT'])) {
                                            $thesis_abstract = $thesis['ABSTRACT']->load();
                                        } else {
                                            $thesis_abstract = (string)$thesis['ABSTRACT'];
                                        }
                                    }
                                ?>
                                <button class="edit"
                                    onclick="openEditThesisModal(this)"
                                    data-thesis-id="<?php echo (int)$thesis['THESIS_ID']; ?>"
                                    data-title="<?php echo htmlspecialchars($thesis['TITLE'], ENT_QUOTES); ?>"
                                    data-abstract="<?php echo htmlspecialchars($thesis_abstract, ENT_QUOTES); ?>"
                                    data-researcher-id="<?php echo (int)$thesis['RESEARCHER_ID']; ?>"
                                    data-department-id="<?php echo (int)$thesis['DEPARTMENT_ID']; ?>"
                                    data-supervisor-id="<?php echo (int)$thesis['SUPERVISOR_ID']; ?>"
                                >Edit</button>
                                <button class="delete"
                                    onclick="confirmDeleteThesis(this)"
                                    data-thesis-id="<?php echo (int)$thesis['THESIS_ID']; ?>"
                                    data-title="<?php echo htmlspecialchars($thesis['TITLE'], ENT_QUOTES); ?>"
                                >Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        </section>

        <!-- ─── Research Papers Section ────────────────────── -->
        <section class="table-section page-view" id="view-papers" style="display:none;">

            <div class="table-header">
                <h3>Research Papers</h3>
                <button class="add-btn" id="addPaperBtn" onclick="openAddPaperModal()">+ Add Paper</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Researcher</th>
                        <th>Co-Authors</th>
                        <th>Keywords</th>
                        <th>Submission Date</th>
                        <th>Year</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        while ($paper = oci_fetch_assoc($papers_table_stid)): 
                            $paper_abstract = "";
                            if (isset($paper['ABSTRACT'])) {
                                if (is_object($paper['ABSTRACT'])) {
                                    $paper_abstract = $paper['ABSTRACT']->load();
                                } else {
                                    $paper_abstract = (string)$paper['ABSTRACT'];
                                }
                            }
                    ?>
                        <tr>
                            <td><?php echo (int)$paper['PAPER_ID']; ?></td>
                            <td><strong><?php echo htmlspecialchars($paper['TITLE']); ?></strong></td>
                            <td><?php echo htmlspecialchars($paper['RESEARCHER_NAME']); ?></td>
                            <td>
                                <?php if (!empty($paper['AUTHORS'])): ?>
                                    <span class="authors-badge">
                                        <i class="fas fa-feather-alt" style="font-size:11px;margin-right:5px;"></i>
                                        <?php echo htmlspecialchars($paper['AUTHORS']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-style:italic;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $kws = explode(',', $paper['KEYWORDS'] ?? '');
                                    foreach ($kws as $kw) {
                                        if (trim($kw) !== '') {
                                            echo '<span class="kw-badge">' . htmlspecialchars(trim($kw)) . '</span>';
                                        }
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($paper['SUB_DATE']); ?></td>
                            <td><?php echo (int)$paper['PUBLICATION_YEAR']; ?></td>
                            <td>
                                <span class="<?php 
                                    if ($paper['STATUS'] === 'PUBLISHED') {
                                        echo 'status-approved';
                                    } elseif ($paper['STATUS'] === 'ACCEPTED') {
                                        echo 'status-approved';
                                    } elseif ($paper['STATUS'] === 'REJECTED') {
                                        echo 'status-rejected';
                                    } elseif ($paper['STATUS'] === 'UNDER REVIEW') {
                                        echo 'status-review';
                                    } else {
                                        echo 'status-submitted';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($paper['STATUS']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="add-btn" style="padding: 4px 8px; font-size: 12px; margin-right: 4px; background: #0ea5e9; border: none; border-radius: 4px; color: white; cursor: pointer;"
                                    onclick="openAssignReviewerModal(this)"
                                    data-paper-id="<?php echo (int)$paper['PAPER_ID']; ?>"
                                    data-title="<?php echo htmlspecialchars($paper['TITLE'], ENT_QUOTES); ?>"
                                >Assign</button>
                                <button class="edit"
                                    onclick="openEditPaperModal(this)"
                                    data-paper-id="<?php echo (int)$paper['PAPER_ID']; ?>"
                                    data-title="<?php echo htmlspecialchars($paper['TITLE'], ENT_QUOTES); ?>"
                                    data-abstract="<?php echo htmlspecialchars($paper_abstract, ENT_QUOTES); ?>"
                                    data-keywords="<?php echo htmlspecialchars($paper['KEYWORDS'] ?? '', ENT_QUOTES); ?>"
                                    data-publication-year="<?php echo (int)$paper['PUBLICATION_YEAR']; ?>"
                                    data-researcher-id="<?php echo (int)$paper['RESEARCHER_ID']; ?>"
                                    data-co-authors="<?php echo htmlspecialchars($paper['AUTHORS'] ?? '', ENT_QUOTES); ?>"
                                    data-status="<?php echo htmlspecialchars($paper['STATUS'], ENT_QUOTES); ?>"
                                >Edit</button>
                                <button class="delete"
                                    onclick="confirmDeletePaper(this)"
                                    data-paper-id="<?php echo (int)$paper['PAPER_ID']; ?>"
                                    data-title="<?php echo htmlspecialchars($paper['TITLE'], ENT_QUOTES); ?>"
                                >Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        </section>

        <!-- ─── Reviews Section ────────────────────────────── -->
        <section class="table-section page-view" id="view-reviews" style="display:none;">

            <div class="table-header">
                <h3>Reviews</h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
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
                    <?php if (empty($reviews_list)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:#94a3b8; font-style:italic; padding:20px;">No reviews submitted yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews_list as $rev): ?>
                            <tr>
                                <td><?php echo (int)$rev['REVIEW_ID']; ?></td>
                                <td style="font-weight:600; color:#0f172a; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($rev['PAPER_TITLE']); ?></td>
                                <td><?php echo htmlspecialchars($rev['RESEARCHER_NAME']); ?></td>
                                <td><?php echo htmlspecialchars($rev['REVIEWER_NAME']); ?></td>
                                <td style="font-weight:600; color:#2563eb;"><?php echo htmlspecialchars($rev['SCORE']); ?>/10</td>
                                <td>
                                    <?php 
                                        $rec = $rev['RECOMMENDATION'];
                                        $status_class = 'status-review';
                                        if ($rec === 'ACCEPT' || $rec === 'MINOR REVISION') {
                                            $status_class = 'status-approved';
                                        } elseif ($rec === 'REJECT') {
                                            $status_class = 'status-rejected';
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($rec); ?>
                                    </span>
                                </td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($rev['COMMENTS']); ?>">
                                    <?php echo htmlspecialchars($rev['COMMENTS']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($rev['REV_DATE']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        </section>

    </main>

</div>

<!-- ── Assign Reviewer Modal ────────────────────────── -->
<div class="modal-overlay" id="assignReviewerModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Assign Reviewer to Paper</h3>
            <button class="close-btn" onclick="closeAssignReviewerModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form class="modal-form" action="" method="POST">
                <input type="hidden" name="action" value="assign_reviewer">
                <input type="hidden" name="paper_id" id="assign_paper_id">

                <div class="form-group">
                    <label>Paper Title</label>
                    <input type="text" id="assign_paper_title" readonly style="background:#f1f5f9; cursor:not-allowed;">
                </div>

                <div class="form-group">
                    <label for="assign_reviewer_id">Select Reviewer</label>
                    <select id="assign_reviewer_id" name="reviewer_id" required>
                        <option value="">Select Reviewer</option>
                        <?php foreach ($reviewers_dropdown_list as $rev): ?>
                            <option value="<?php echo htmlspecialchars($rev['USER_ID']); ?>">
                                <?php echo htmlspecialchars($rev['FULL_NAME']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAssignReviewerModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Assign Reviewer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Paper Modal ──────────────────────────────── -->
<div class="modal-overlay" id="addPaperModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="paperModalTitle">Add New Paper</h3>
            <button class="close-btn" id="closePaperModalBtn" onclick="closeAddPaperModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form class="modal-form" id="paperForm" action="../php/add_paper.php" method="POST">
                <!-- Hidden field: only populated in Edit mode -->
                <input type="hidden" id="edit_paper_id" name="paper_id" value="">

                <div class="form-group">
                    <label for="paper_title">Paper Title</label>
                    <input type="text" id="paper_title" name="title" placeholder="e.g. Blockchain Security Framework" required>
                </div>

                <div class="form-group">
                    <label for="paper_abstract">Abstract</label>
                    <textarea id="paper_abstract" name="abstract" placeholder="Describe the paper..." rows="4" style="padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; font-family: inherit;" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="paper_keywords">Keywords</label>
                        <input type="text" id="paper_keywords" name="keywords" placeholder="e.g. Blockchain, Cybersecurity, IoT">
                    </div>

                    <div class="form-group">
                        <label for="paper_publication_year">Publication Year</label>
                        <input type="number" id="paper_publication_year" name="publication_year" value="<?php echo date('Y'); ?>" min="1900" max="2100" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="paper_researcher_id">Primary Researcher</label>
                        <select id="paper_researcher_id" name="researcher_id" required>
                            <option value="">Select Researcher</option>
                            <?php while ($res = oci_fetch_assoc($researchers_paper_stid)): ?>
                                <option value="<?php echo htmlspecialchars($res['USER_ID']); ?>">
                                    <?php echo htmlspecialchars($res['FIRST_NAME'] . ' ' . $res['LAST_NAME']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="paper_co_authors">Co-Authors (comma-separated names)</label>
                        <input type="text" id="paper_co_authors" name="co_authors" placeholder="e.g. Dr. Rahman, John Smith">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelPaperModalBtn" onclick="closeAddPaperModal()">Cancel</button>
                    <button type="submit" class="btn-submit" id="paperSubmitBtn">Add Paper</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Thesis Modal ─────────────────────────────── -->
<div class="modal-overlay" id="addThesisModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="thesisModalTitle">Add New Thesis</h3>
            <button class="close-btn" id="closeThesisModalBtn" onclick="closeAddThesisModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form class="modal-form" id="thesisForm" action="../php/add_thesis.php" method="POST">
                <!-- Hidden field: only populated in Edit mode -->
                <input type="hidden" id="edit_thesis_id" name="thesis_id" value="">

                <div class="form-group">
                    <label for="thesis_title">Thesis Title</label>
                    <input type="text" id="thesis_title" name="title" placeholder="e.g. AI Driven Medical Diagnosis" required>
                </div>

                <div class="form-group">
                    <label for="thesis_abstract">Abstract</label>
                    <textarea id="thesis_abstract" name="abstract" placeholder="Describe the thesis..." rows="4" style="padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; font-family: inherit;" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="thesis_researcher_id">Researcher</label>
                        <select id="thesis_researcher_id" name="researcher_id" required>
                            <option value="">Select Researcher</option>
                            <?php while ($res = oci_fetch_assoc($researchers_stid)): ?>
                                <option value="<?php echo htmlspecialchars($res['USER_ID']); ?>">
                                    <?php echo htmlspecialchars($res['FIRST_NAME'] . ' ' . $res['LAST_NAME']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="thesis_department_id">Department</label>
                        <select id="thesis_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php while ($dept = oci_fetch_assoc($depts_thesis_stid)): ?>
                                <option value="<?php echo htmlspecialchars($dept['DEPARTMENT_ID']); ?>">
                                    <?php echo htmlspecialchars($dept['DEPARTMENT_NAME']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="thesis_supervisor_id">Primary Supervisor</label>
                        <select id="thesis_supervisor_id" name="supervisor_id" required>
                            <option value="">Select Supervisor</option>
                            <?php while ($sup = oci_fetch_assoc($supervisors_stid)): ?>
                                <option value="<?php echo htmlspecialchars($sup['USER_ID']); ?>">
                                    <?php echo htmlspecialchars($sup['FIRST_NAME'] . ' ' . $sup['LAST_NAME']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelThesisModalBtn" onclick="closeAddThesisModal()">Cancel</button>
                    <button type="submit" class="btn-submit" id="thesisSubmitBtn">Add Thesis</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Department Modal ──────────────────────────── -->
<div class="modal-overlay" id="addDeptModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="deptModalTitle">Add New Department</h3>
            <button class="close-btn" id="closeDeptModalBtn" onclick="closeAddDeptModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form class="modal-form" id="deptForm" action="../php/add_department.php" method="POST">
                <!-- Hidden field: only populated in Edit mode -->
                <input type="hidden" id="edit_department_id" name="department_id" value="">

                <div class="form-group">
                    <label for="department_name">Department Name</label>
                    <input type="text" id="department_name" name="department_name" placeholder="e.g. Computer Science & Engineering" required>
                </div>
                <div class="form-group">
                    <label for="faculty">Faculty</label>
                    <input type="text" id="faculty" name="faculty" placeholder="e.g. Engineering" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelDeptModalBtn" onclick="closeAddDeptModal()">Cancel</button>
                    <button type="submit" class="btn-submit" id="deptSubmitBtn">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addUserModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="modalTitle">Add New User</h3>
            <button class="close-btn" id="closeModalBtn">&times;</button>
        </div>
        <div class="modal-body">
            <form class="modal-form" id="userForm" action="../php/add_user.php" method="POST">

                <!-- Hidden field: only populated in Edit mode -->
                <input type="hidden" id="edit_user_id" name="user_id" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Last Name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group" id="passwordGroup">
                        <label for="password" id="passwordLabel">Password</label>
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <small id="passwordHint" style="display:none; color:#888; font-size:0.78rem; margin-top:4px;">Leave blank to keep current password.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="institution">Institution</label>
                    <input type="text" id="institution" name="institution" placeholder="University Name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php while ($dept = oci_fetch_assoc($depts_stid)): ?>
                                <option value="<?php echo htmlspecialchars($dept['DEPARTMENT_ID']); ?>">
                                    <?php echo htmlspecialchars($dept['DEPARTMENT_NAME']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="role_id">Role</label>
                        <select id="role_id" name="role_id" required>
                            <option value="">Select Role</option>
                            <?php while ($role = oci_fetch_assoc($roles_stid)): ?>
                                <option value="<?php echo htmlspecialchars($role['ROLE_ID']); ?>">
                                    <?php echo htmlspecialchars($role['ROLE_NAME']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">Account Status</label>
                    <select id="status" name="status" required>
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="INACTIVE">INACTIVE</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn-submit" id="submitBtn">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* ─── Global: Navigation Section Switcher ─── */
function showSection(section) {
    // Remove active class from all sidebar items
    document.querySelectorAll('.sidebar li').forEach(function(li) {
        li.classList.remove('active');
    });

    // Add active class to clicked nav item
    var activeLi = document.getElementById('nav-' + section);
    if (activeLi) {
        activeLi.classList.add('active');
    }

    var views = [
        'view-dashboard',
        'view-dashboard-actions',
        'view-users',
        'view-analytics-summary',
        'view-analytics-detail',
        'view-logs',
        'view-departments',
        'view-theses',
        'view-papers',
        'view-reviews'
    ];

    // Hide all views first
    views.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });

    // Toggle visibility of sections based on selection
    if (section === 'dashboard') {
        if (document.getElementById('view-dashboard')) document.getElementById('view-dashboard').style.display = 'grid';
        if (document.getElementById('view-dashboard-actions')) document.getElementById('view-dashboard-actions').style.display = 'block';
        if (document.getElementById('view-users')) document.getElementById('view-users').style.display = 'block';
        if (document.getElementById('view-analytics-summary')) document.getElementById('view-analytics-summary').style.display = 'grid';
        if (document.getElementById('view-logs')) document.getElementById('view-logs').style.display = 'block';
    } else if (section === 'users') {
        if (document.getElementById('view-users')) document.getElementById('view-users').style.display = 'block';
    } else if (section === 'departments') {
        if (document.getElementById('view-departments')) document.getElementById('view-departments').style.display = 'block';
    } else if (section === 'theses') {
        if (document.getElementById('view-theses')) document.getElementById('view-theses').style.display = 'block';
    } else if (section === 'papers') {
        if (document.getElementById('view-papers')) document.getElementById('view-papers').style.display = 'block';
    } else if (section === 'reviews') {
        if (document.getElementById('view-reviews')) document.getElementById('view-reviews').style.display = 'block';
    } else if (section === 'analytics') {
        if (document.getElementById('view-analytics-detail')) document.getElementById('view-analytics-detail').style.display = 'block';
    }
}

/* ─── Global: called directly by onclick on every Edit button ─── */
function openEditModal(btn) {
    var d = btn.dataset;

    var modal         = document.getElementById('addUserModal');
    var userForm      = document.getElementById('userForm');
    var modalTitle    = document.getElementById('modalTitle');
    var submitBtn     = document.getElementById('submitBtn');
    var editUserIdFld = document.getElementById('edit_user_id');
    var passwordInput = document.getElementById('password');
    var passwordHint  = document.getElementById('passwordHint');

    /* Reset to clean state first */
    userForm.reset();

    /* Switch to Edit mode */
    userForm.action            = '../php/edit_user.php';
    editUserIdFld.value        = d.userId        || '';
    modalTitle.textContent     = 'Edit User';
    submitBtn.textContent      = 'Save Changes';
    passwordInput.required     = false;
    passwordInput.placeholder  = 'Leave blank to keep current password';
    passwordHint.style.display = 'block';

    /* Populate text inputs */
    document.getElementById('first_name').value  = d.firstName   || '';
    document.getElementById('last_name').value   = d.lastName    || '';
    document.getElementById('email').value       = d.email       || '';
    document.getElementById('institution').value = d.institution || '';

    /* Populate selects */
    document.getElementById('department_id').value = d.departmentId || '';
    document.getElementById('role_id').value       = d.roleId       || '';
    document.getElementById('status').value        = d.status       || 'ACTIVE';

    /* Open modal */
    modal.classList.add('active');
}

/* ─── Global: Add/Edit Department Modal handlers ─── */
function openAddDeptModal() {
    var deptModal    = document.getElementById('addDeptModal');
    var deptForm     = document.getElementById('deptForm');
    var modalTitle   = document.getElementById('deptModalTitle');
    var submitBtn    = document.getElementById('deptSubmitBtn');
    var editDeptId   = document.getElementById('edit_department_id');

    if (deptForm) {
        deptForm.reset();
        deptForm.action = '../php/add_department.php';
    }
    if (editDeptId) editDeptId.value = '';
    if (modalTitle) modalTitle.textContent = 'Add New Department';
    if (submitBtn) submitBtn.textContent = 'Add Department';

    if (deptModal) deptModal.classList.add('active');
}

function openEditDeptModal(btn) {
    var d = btn.dataset;

    var deptModal    = document.getElementById('addDeptModal');
    var deptForm     = document.getElementById('deptForm');
    var modalTitle   = document.getElementById('deptModalTitle');
    var submitBtn    = document.getElementById('deptSubmitBtn');
    var editDeptId   = document.getElementById('edit_department_id');

    if (deptForm) {
        deptForm.reset();
        deptForm.action = '../php/edit_department.php';
    }

    if (editDeptId) editDeptId.value = d.deptId || '';
    if (modalTitle) modalTitle.textContent = 'Edit Department';
    if (submitBtn) submitBtn.textContent = 'Save Changes';

    document.getElementById('department_name').value = d.deptName || '';
    document.getElementById('faculty').value         = d.faculty  || '';

    if (deptModal) deptModal.classList.add('active');
}

function closeAddDeptModal() {
    var deptModal = document.getElementById('addDeptModal');
    var deptForm  = document.getElementById('deptForm');
    if (deptModal) deptModal.classList.remove('active');
    if (deptForm) deptForm.reset();
}

/* ─── Global: Add Paper Modal handlers ─── */
function openAddPaperModal() {
    var paperModal = document.getElementById('addPaperModal');
    var paperForm  = document.getElementById('paperForm');
    var modalTitle = document.getElementById('paperModalTitle');
    var submitBtn  = document.getElementById('paperSubmitBtn');
    var editPaperId = document.getElementById('edit_paper_id');

    if (paperForm) {
        paperForm.reset();
        paperForm.action = '../php/add_paper.php';
    }
    if (editPaperId) editPaperId.value = '';
    if (modalTitle) modalTitle.textContent = 'Add New Paper';
    if (submitBtn) submitBtn.textContent = 'Add Paper';

    if (paperModal) paperModal.classList.add('active');
}

function closeAddPaperModal() {
    var paperModal = document.getElementById('addPaperModal');
    var paperForm  = document.getElementById('paperForm');
    if (paperModal) paperModal.classList.remove('active');
    if (paperForm) paperForm.reset();
}

function openEditPaperModal(btn) {
    var d = btn.dataset;

    var paperModal  = document.getElementById('addPaperModal');
    var paperForm   = document.getElementById('paperForm');
    var modalTitle  = document.getElementById('paperModalTitle');
    var submitBtn   = document.getElementById('paperSubmitBtn');
    var editPaperId = document.getElementById('edit_paper_id');

    if (paperForm) {
        paperForm.reset();
        paperForm.action = '../php/edit_paper.php';
    }

    if (editPaperId) editPaperId.value = d.paperId || '';
    if (modalTitle) modalTitle.textContent = 'Edit Paper';
    if (submitBtn) submitBtn.textContent = 'Save Changes';

    document.getElementById('paper_title').value            = d.title           || '';
    document.getElementById('paper_abstract').value         = d.abstract        || '';
    document.getElementById('paper_keywords').value         = d.keywords        || '';
    document.getElementById('paper_publication_year').value = d.publicationYear || '';
    document.getElementById('paper_researcher_id').value    = d.researcherId    || '';
    document.getElementById('paper_co_authors').value       = d.coAuthors       || '';

    if (paperModal) paperModal.classList.add('active');
}
/* ─── Global: Assign Reviewer Modal handlers ─── */
function openAssignReviewerModal(btn) {
    var paperId = btn.dataset.paperId;
    var paperTitle = btn.dataset.title;
    document.getElementById('assign_paper_id').value = paperId;
    document.getElementById('assign_paper_title').value = paperTitle;
    document.getElementById('assignReviewerModal').classList.add('active');
}

function closeAssignReviewerModal() {
    document.getElementById('assignReviewerModal').classList.remove('active');
}

/* ─── Global: Add Thesis Modal handlers ─── */
function openAddThesisModal() {
    var thesisModal = document.getElementById('addThesisModal');
    var thesisForm  = document.getElementById('thesisForm');
    var modalTitle  = document.getElementById('thesisModalTitle');
    var submitBtn   = document.getElementById('thesisSubmitBtn');
    var editThesisId = document.getElementById('edit_thesis_id');

    if (thesisForm) {
        thesisForm.reset();
        thesisForm.action = '../php/add_thesis.php';
    }
    if (editThesisId) editThesisId.value = '';
    if (modalTitle) modalTitle.textContent = 'Add New Thesis';
    if (submitBtn) submitBtn.textContent = 'Add Thesis';

    if (thesisModal) thesisModal.classList.add('active');
}

function openEditThesisModal(btn) {
    var d = btn.dataset;

    var thesisModal  = document.getElementById('addThesisModal');
    var thesisForm   = document.getElementById('thesisForm');
    var modalTitle   = document.getElementById('thesisModalTitle');
    var submitBtn    = document.getElementById('thesisSubmitBtn');
    var editThesisId = document.getElementById('edit_thesis_id');

    if (thesisForm) {
        thesisForm.reset();
        thesisForm.action = '../php/edit_thesis.php';
    }

    if (editThesisId) editThesisId.value = d.thesisId || '';
    if (modalTitle) modalTitle.textContent = 'Edit Thesis';
    if (submitBtn) submitBtn.textContent = 'Save Changes';

    document.getElementById('thesis_title').value         = d.title     || '';
    document.getElementById('thesis_abstract').value      = d.abstract  || '';
    document.getElementById('thesis_researcher_id').value = d.researcherId || '';
    document.getElementById('thesis_department_id').value = d.departmentId || '';
    document.getElementById('thesis_supervisor_id').value = d.supervisorId || '';

    if (thesisModal) thesisModal.classList.add('active');
}

function closeAddThesisModal() {
    var thesisModal = document.getElementById('addThesisModal');
    var thesisForm  = document.getElementById('thesisForm');
    if (thesisModal) thesisModal.classList.remove('active');
    if (thesisForm) thesisForm.reset();
}


/* ─── Global: called directly by onclick on every Delete button ─── */
function confirmDelete(btn) {
    var userId   = btn.dataset.userId;
    var userName = btn.dataset.userName || 'this user';

    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.getElementById('deleteUserId').value = '';
}

/* ─── Global: called directly by onclick on every Delete Department button ─── */
function confirmDeleteDept(btn) {
    var deptId   = btn.dataset.deptId;
    var deptName = btn.dataset.deptName || 'this department';

    document.getElementById('deleteDeptId').value = deptId;
    document.getElementById('deleteDeptName').textContent = deptName;
    document.getElementById('deleteDeptModal').classList.add('active');
}

function closeDeleteDeptModal() {
    document.getElementById('deleteDeptModal').classList.remove('active');
    document.getElementById('deleteDeptId').value = '';
}

/* ─── Global: called directly by onclick on every Delete Thesis button ─── */
function confirmDeleteThesis(btn) {
    var thesisId    = btn.dataset.thesisId;
    var thesisTitle = btn.dataset.title || 'this thesis';

    document.getElementById('deleteThesisId').value = thesisId;
    document.getElementById('deleteThesisTitle').textContent = thesisTitle;
    document.getElementById('deleteThesisModal').classList.add('active');
}

function closeDeleteThesisModal() {
    document.getElementById('deleteThesisModal').classList.remove('active');
    document.getElementById('deleteThesisId').value = '';
}

/* ─── Global: called directly by onclick on every Delete Paper button ─── */
function confirmDeletePaper(btn) {
    var paperId    = btn.dataset.paperId;
    var paperTitle = btn.dataset.title || 'this paper';

    document.getElementById('deletePaperId').value = paperId;
    document.getElementById('deletePaperTitle').textContent = paperTitle;
    document.getElementById('deletePaperModal').classList.add('active');
}

function closeDeletePaperModal() {
    document.getElementById('deletePaperModal').classList.remove('active');
    document.getElementById('deletePaperId').value = '';
}

document.addEventListener('DOMContentLoaded', function () {

    var modal         = document.getElementById('addUserModal');
    var userForm      = document.getElementById('userForm');
    var modalTitle    = document.getElementById('modalTitle');
    var submitBtn     = document.getElementById('submitBtn');
    var editUserIdFld = document.getElementById('edit_user_id');
    var passwordInput = document.getElementById('password');
    var passwordHint  = document.getElementById('passwordHint');

    function resetToAddMode() {
        userForm.reset();
        editUserIdFld.value        = '';
        userForm.action            = '../php/add_user.php';
        modalTitle.textContent     = 'Add New User';
        submitBtn.textContent      = 'Add User';
        passwordInput.required     = true;
        passwordInput.placeholder  = 'Password';
        passwordHint.style.display = 'none';
    }

    function closeModal() {
        modal.classList.remove('active');
        resetToAddMode();
    }

    /* + Add User buttons */
    ['addUserBtn', 'actionAddUser'].forEach(function(id) {
        var b = document.getElementById(id);
        if (b) b.addEventListener('click', function(e) {
            e.preventDefault();
            resetToAddMode();
            modal.classList.add('active');
        });
    });

    /* Close handlers */
    ['closeModalBtn', 'cancelModalBtn'].forEach(function(id) {
        var b = document.getElementById(id);
        if (b) b.addEventListener('click', function(e) {
            e.preventDefault();
            closeModal();
        });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    /* --- Department Modal Event Listeners --- */
    var deptModal = document.getElementById('addDeptModal');
    var deptForm  = document.getElementById('deptForm');

    console.log("Initializing Department Modal listeners...");
    console.log("deptModal element: ", deptModal);
    console.log("deptForm element: ", deptForm);

    // Open department modal
    ['addDeptBtn', 'actionAddDept'].forEach(function(id) {
        var b = document.getElementById(id);
        console.log("Registering listener for ID: " + id + ", element found: ", b);
        if (b) {
            b.addEventListener('click', function(e) {
                console.log("Button " + id + " clicked! Opening modal...");
                e.preventDefault();
                deptForm.reset();
                deptModal.classList.add('active');
            });
        }
    });

    // Close department modal
    ['closeDeptModalBtn', 'cancelDeptModalBtn'].forEach(function(id) {
        var b = document.getElementById(id);
        if (b) b.addEventListener('click', function(e) {
            e.preventDefault();
            deptModal.classList.remove('active');
            deptForm.reset();
        });
    });

    // Click on overlay to close
    if (deptModal) {
        deptModal.addEventListener('click', function(e) {
            if (e.target === deptModal) {
                deptModal.classList.remove('active');
                deptForm.reset();
            }
        });
    }

    // Click on overlay to close paper modal
    var paperModal = document.getElementById('addPaperModal');
    var paperForm  = document.getElementById('paperForm');
    if (paperModal) {
        paperModal.addEventListener('click', function(e) {
            if (e.target === paperModal) {
                paperModal.classList.remove('active');
                paperForm.reset();
            }
        });
    }

    // Click on overlay to close thesis modal
    var thesisModal = document.getElementById('addThesisModal');
    var thesisForm  = document.getElementById('thesisForm');
    if (thesisModal) {
        thesisModal.addEventListener('click', function(e) {
            if (e.target === thesisModal) {
                thesisModal.classList.remove('active');
                thesisForm.reset();
            }
        });
    }

    // Click on overlay to close delete user modal
    var deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) closeDeleteModal();
        });
    }

    // Click on overlay to close delete dept modal
    var deleteDeptModal = document.getElementById('deleteDeptModal');
    if (deleteDeptModal) {
        deleteDeptModal.addEventListener('click', function(e) {
            if (e.target === deleteDeptModal) closeDeleteDeptModal();
        });
    }

    // Click on overlay to close delete thesis modal
    var deleteThesisModal = document.getElementById('deleteThesisModal');
    if (deleteThesisModal) {
        deleteThesisModal.addEventListener('click', function(e) {
            if (e.target === deleteThesisModal) closeDeleteThesisModal();
        });
    }

    // Click on overlay to close delete paper modal
    var deletePaperModal = document.getElementById('deletePaperModal');
    if (deletePaperModal) {
        deletePaperModal.addEventListener('click', function(e) {
            if (e.target === deletePaperModal) closeDeletePaperModal();
        });
    }

    /* Auto-dismiss banners after 4 s */
    document.querySelectorAll('.alert-banner').forEach(function(el) {
        setTimeout(function() { el.style.opacity = '0'; }, 4000);
        setTimeout(function() { el.remove(); }, 4500);
    });
});
</script>


<!-- ── Delete Confirmation Modal ─────────────────────── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-container delete-modal-container">
        <div class="modal-header delete-modal-header">
            <h3 id="deleteModalTitle"><i class="fas fa-exclamation-triangle" style="color:#fbbf24;margin-right:8px;"></i>Confirm Deletion</h3>
            <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:35px 30px;">
            <div class="delete-icon-wrap">
                <i class="fas fa-user-times"></i>
            </div>
            <p class="delete-warning-text">You are about to permanently delete:</p>
            <p class="delete-user-name" id="deleteUserName">—</p>
            <p class="delete-sub-text">This will <strong>permanently remove</strong> the user and all their associated records (papers, theses, reviews) from the database. <strong>This action cannot be undone.</strong></p>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:15px;padding-bottom:25px;">
            <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <form id="deleteForm" action="../php/delete_user.php" method="POST" style="display:inline;">
                <input type="hidden" id="deleteUserId" name="user_id" value="">
                <button type="submit" class="btn-delete-confirm">
                    <i class="fas fa-trash-alt" style="margin-right:6px;"></i>Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ── Delete Department Confirmation Modal ──────────── -->
<div class="modal-overlay" id="deleteDeptModal">
    <div class="modal-container delete-modal-container">
        <div class="modal-header delete-modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color:#fbbf24;margin-right:8px;"></i>Confirm Deletion</h3>
            <button class="close-btn" onclick="closeDeleteDeptModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:35px 30px;">
            <div class="delete-icon-wrap" style="background:#fee2e2;">
                <i class="fas fa-building" style="font-size:32px;color:#dc2626;"></i>
            </div>
            <p class="delete-warning-text">You are about to permanently delete department:</p>
            <p class="delete-user-name" id="deleteDeptName" style="color:#0f172a;font-weight:700;font-size:20px;">—</p>
            <p class="delete-sub-text">This will <strong>permanently remove</strong> the department and all users, research papers, and theses associated with it from the database. <strong>This action cannot be undone.</strong></p>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:15px;padding-bottom:25px;">
            <button type="button" class="btn-cancel" onclick="closeDeleteDeptModal()">Cancel</button>
            <form id="deleteDeptForm" action="../php/delete_department.php" method="POST" style="display:inline;">
                <input type="hidden" id="deleteDeptId" name="department_id" value="">
                <button type="submit" class="btn-delete-confirm">
                    <i class="fas fa-trash-alt" style="margin-right:6px;"></i>Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>
<!-- ── Delete Thesis Confirmation Modal ─────────────── -->
<div class="modal-overlay" id="deleteThesisModal">
    <div class="modal-container delete-modal-container">
        <div class="modal-header delete-modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color:#fbbf24;margin-right:8px;"></i>Confirm Deletion</h3>
            <button class="close-btn" onclick="closeDeleteThesisModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:35px 30px;">
            <div class="delete-icon-wrap" style="background:#fee2e2;">
                <i class="fas fa-book" style="font-size:32px;color:#dc2626;"></i>
            </div>
            <p class="delete-warning-text">You are about to permanently delete thesis:</p>
            <p class="delete-user-name" id="deleteThesisTitle" style="color:#0f172a;font-weight:700;font-size:20px;">—</p>
            <p class="delete-sub-text">This will <strong>permanently remove</strong> the thesis and its supervisor assignments from the database. <strong>This action cannot be undone.</strong></p>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:15px;padding-bottom:25px;">
            <button type="button" class="btn-cancel" onclick="closeDeleteThesisModal()">Cancel</button>
            <form id="deleteThesisForm" action="../php/delete_thesis.php" method="POST" style="display:inline;">
                <input type="hidden" id="deleteThesisId" name="thesis_id" value="">
                <button type="submit" class="btn-delete-confirm">
                    <i class="fas fa-trash-alt" style="margin-right:6px;"></i>Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ── Delete Paper Confirmation Modal ──────────────── -->
<div class="modal-overlay" id="deletePaperModal">
    <div class="modal-container delete-modal-container">
        <div class="modal-header delete-modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color:#fbbf24;margin-right:8px;"></i>Confirm Deletion</h3>
            <button class="close-btn" onclick="closeDeletePaperModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:35px 30px;">
            <div class="delete-icon-wrap" style="background:#fee2e2;">
                <i class="fas fa-file-alt" style="font-size:32px;color:#dc2626;"></i>
            </div>
            <p class="delete-warning-text">You are about to permanently delete research paper:</p>
            <p class="delete-user-name" id="deletePaperTitle" style="color:#0f172a;font-weight:700;font-size:20px;">—</p>
            <p class="delete-sub-text">This will <strong>permanently remove</strong> the research paper, its co-authors, and all review assignments associated with it from the database. <strong>This action cannot be undone.</strong></p>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:15px;padding-bottom:25px;">
            <button type="button" class="btn-cancel" onclick="closeDeletePaperModal()">Cancel</button>
            <form id="deletePaperForm" action="../php/delete_paper.php" method="POST" style="display:inline;">
                <input type="hidden" id="deletePaperId" name="paper_id" value="">
                <button type="submit" class="btn-delete-confirm">
                    <i class="fas fa-trash-alt" style="margin-right:6px;"></i>Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
