<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}
include_once '../php/db_connect.php';
$displayName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$researcher_id = (int)$_SESSION['user_id'];

// 1. Fetch statistics using PL/SQL functions via DUAL
$stats_sql = "
    SELECT GET_TOTAL_PAPERS(:researcher_id) AS TOTAL_PAPERS,
           GET_TOTAL_THESES(:researcher_id) AS TOTAL_THESES,
           GET_APPROVED_COUNT(:researcher_id) AS APPROVED_COUNT,
           GET_PENDING_COUNT(:researcher_id) AS PENDING_COUNT
    FROM DUAL
";
$stats_stid = oci_parse($conn, $stats_sql);
oci_bind_by_name($stats_stid, ':researcher_id', $researcher_id);
oci_execute($stats_stid);
$stats_row = oci_fetch_assoc($stats_stid);

$total_papers = $stats_row ? (int)$stats_row['TOTAL_PAPERS'] : 0;
$total_theses = $stats_row ? (int)$stats_row['TOTAL_THESES'] : 0;
$approved_count = $stats_row ? (int)$stats_row['APPROVED_COUNT'] : 0;
$pending_count = $stats_row ? (int)$stats_row['PENDING_COUNT'] : 0;

// 2. Fetch recent submissions
$submissions_sql = "
    SELECT TITLE, 'Paper' AS TYPE, STATUS, TO_CHAR(SUBMISSION_DATE, 'DD Mon YYYY') AS SUB_DATE, SUBMISSION_DATE
    FROM PAPERS
    WHERE RESEARCHER_ID = :researcher_id
    UNION ALL
    SELECT TITLE, 'Thesis' AS TYPE, STATUS, TO_CHAR(SUBMISSION_DATE, 'DD Mon YYYY') AS SUB_DATE, SUBMISSION_DATE
    FROM THESES
    WHERE RESEARCHER_ID = :researcher_id
    ORDER BY SUBMISSION_DATE DESC
";
$submissions_stid = oci_parse($conn, $submissions_sql);
oci_bind_by_name($submissions_stid, ':researcher_id', $researcher_id);
oci_execute($submissions_stid);

$all_submissions = [];
while ($row = oci_fetch_assoc($submissions_stid)) {
    $all_submissions[] = $row;
}

// Fetch reviews and feedback for the researcher's papers
$reviews_sql = "
    SELECT P.TITLE, 
           R.SCORE, 
           DBMS_LOB.SUBSTR(R.COMMENTS, 4000, 1) AS COMMENTS, 
           R.RECOMMENDATION, 
           TO_CHAR(R.REVIEW_DATE, 'DD Mon YYYY') AS REV_DATE,
           U.FIRST_NAME || ' ' || U.LAST_NAME AS REVIEWER_NAME
    FROM REVIEWS R
    JOIN REVIEW_ASSIGNMENTS RA ON R.ASSIGNMENT_ID = RA.ASSIGNMENT_ID
    JOIN PAPERS P ON RA.PAPER_ID = P.PAPER_ID
    JOIN USERS U ON RA.REVIEWER_ID = U.USER_ID
    WHERE P.RESEARCHER_ID = :researcher_id
    ORDER BY R.REVIEW_DATE DESC
";
$reviews_stid = oci_parse($conn, $reviews_sql);
oci_bind_by_name($reviews_stid, ':researcher_id', $researcher_id);
oci_execute($reviews_stid);

$reviews_list = [];
while ($row = oci_fetch_assoc($reviews_stid)) {
    $reviews_list[] = $row;
}

// 3. Fetch thesis progress
$progress_sql = "
    SELECT TITLE,
           CASE STATUS
               WHEN 'APPROVED' THEN 100
               WHEN 'UNDER REVIEW' THEN 75
               WHEN 'SUBMITTED' THEN 60
               ELSE 50
           END AS PROGRESS_PCT
    FROM THESES
    WHERE RESEARCHER_ID = :researcher_id
    ORDER BY THESIS_ID DESC
";
$progress_stid = oci_parse($conn, $progress_sql);
oci_bind_by_name($progress_stid, ':researcher_id', $researcher_id);
oci_execute($progress_stid);
$progress_row = oci_fetch_assoc($progress_stid);
$thesis_title = $progress_row ? $progress_row['TITLE'] : 'No active thesis';
$progress_pct = $progress_row ? (int)$progress_row['PROGRESS_PCT'] : 0;

// 4. Fetch active supervisors for thesis submission
$supervisors_sql = "SELECT USER_ID, FIRST_NAME, LAST_NAME FROM USERS WHERE ROLE_ID = 3 AND STATUS = 'ACTIVE' ORDER BY FIRST_NAME, LAST_NAME";
$supervisors_stid = oci_parse($conn, $supervisors_sql);
oci_execute($supervisors_stid);

// Fetch distinct publication years for the dropdown menu
$years_sql = "SELECT DISTINCT PUBLICATION_YEAR FROM PAPERS WHERE PUBLICATION_YEAR IS NOT NULL ORDER BY PUBLICATION_YEAR DESC";
$years_stid = oci_parse($conn, $years_sql);
oci_execute($years_stid);
$available_years = [];
while ($y_row = oci_fetch_assoc($years_stid)) {
    $available_years[] = (int)$y_row['PUBLICATION_YEAR'];
}

// 5. Search Papers controller
$search_results = [];
$search_keyword = '';
$selected_year_filter = '';
$is_search_performed = false;

if (isset($_GET['keyword']) || isset($_GET['year_filter'])) {
    $search_keyword = trim($_GET['keyword'] ?? '');
    $selected_year_filter = trim($_GET['year_filter'] ?? '');
    $is_search_performed = true;
    
    // Base SQL query
    $search_sql = "
        SELECT PAPER_ID, TITLE, KEYWORDS, STATUS, PUBLICATION_YEAR 
        FROM PAPERS 
        WHERE RESEARCHER_ID = :researcher_id 
    ";
    
    // Add keyword filter if not empty
    if ($search_keyword !== '') {
        $search_sql .= " AND (LOWER(KEYWORDS) LIKE '%' || LOWER(:keyword) || '%' OR LOWER(TITLE) LIKE '%' || LOWER(:keyword) || '%') ";
    }
    
    // Add year filter if not empty
    if ($selected_year_filter !== '') {
        if (strpos($selected_year_filter, '-') !== false) {
            // Range of years, e.g. "2020-2026"
            $parts = explode('-', $selected_year_filter);
            $start_year = (int)$parts[0];
            $end_year = (int)$parts[1];
            $search_sql .= " AND PUBLICATION_YEAR BETWEEN :start_year AND :end_year ";
        } else {
            // Particular year, e.g. "2026"
            $particular_year = (int)$selected_year_filter;
            $search_sql .= " AND PUBLICATION_YEAR = :particular_year ";
        }
    }
    
    $search_sql .= " ORDER BY SUBMISSION_DATE DESC ";
    
    $search_stid = oci_parse($conn, $search_sql);
    
    // Bind parameters
    oci_bind_by_name($search_stid, ':researcher_id', $researcher_id);
    if ($search_keyword !== '') {
        oci_bind_by_name($search_stid, ':keyword', $search_keyword);
    }
    if ($selected_year_filter !== '') {
        if (strpos($selected_year_filter, '-') !== false) {
            oci_bind_by_name($search_stid, ':start_year', $start_year);
            oci_bind_by_name($search_stid, ':end_year', $end_year);
        } else {
            oci_bind_by_name($search_stid, ':particular_year', $particular_year);
        }
    }
    
    oci_execute($search_stid);
    
    while ($row = oci_fetch_assoc($search_stid)) {
        $search_results[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Researcher Dashboard | ResearchHub</title>

    <link rel="stylesheet" href="researcher_dashboard.css?v=<?php echo time(); ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
    /* Modal Styling */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .modal-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }
    .modal-container {
        background: white;
        width: 100%;
        max-width: 550px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        overflow: hidden;
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }
    .modal-overlay.active .modal-container {
        transform: scale(1);
    }
    .modal-header {
        background: #0f172a;
        color: white;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-header h3 {
        font-size: 20px;
        font-weight: 600;
        margin: 0;
    }
    .modal-header .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        opacity: 0.8;
        transition: 0.2s;
    }
    .modal-header .close-btn:hover {
        opacity: 1;
    }
    .modal-body {
        padding: 25px;
    }
    .modal-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }
    .modal-form .form-group {
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .modal-form label {
        font-size: 14px;
        font-weight: 500;
        color: #475569;
    }
    .modal-form input,
    .modal-form textarea,
    .modal-form select {
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
    }
    .modal-form input:focus,
    .modal-form textarea:focus,
    .modal-form select:focus {
        border-color: #2563eb;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 25px;
    }
    .modal-footer button {
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border: none;
    }
    .modal-footer .btn-cancel {
        background: #f1f5f9;
        color: #475569;
        transition: background 0.2s;
    }
    .modal-footer .btn-cancel:hover {
        background: #e2e8f0;
    }
    .modal-footer .btn-submit {
        background: #2563eb;
        color: white;
        transition: background 0.2s;
    }
    .modal-footer .btn-submit:hover {
        background: #1d4ed8;
    }
    </style>
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

            <li class="active" id="nav-home">
                <a href="#" onclick="showSection('home'); return false;">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </li>

            <li>
                <a href="#" onclick="openAddPaperModal(); return false;">
                    <i class="fas fa-file-upload"></i>
                    Submit Paper
                </a>
            </li>

            <li>
                <a href="#" onclick="openAddThesisModal(); return false;">
                    <i class="fas fa-book"></i>
                    Submit Thesis
                </a>
            </li>

            <li id="nav-submissions">
                <a href="#" onclick="showSection('submissions'); return false;">
                    <i class="fas fa-folder-open"></i>
                    My Submissions
                </a>
            </li>

            <li id="nav-reviews">
                <a href="#" onclick="showSection('reviews'); return false;">
                    <i class="fas fa-comments"></i>
                    Reviews & Feedback
                </a>
            </li>

            <li id="nav-search">
                <a href="#" onclick="showSection('search'); return false;">
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

        <?php if (isset($_GET['success'])): ?>
            <div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:500; font-size:14px; border:1px solid #a7f3d0;">
                <i class="fas fa-check-circle" style="margin-right:8px;"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:500; font-size:14px; border:1px solid #fecaca;">
                <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Home/Dashboard Section Wrapper -->
        <div id="home-section">

        <!-- Statistics -->

        <section class="stats">

            <div class="card">
                <i class="fas fa-file-alt"></i>
                <h3><?php echo $total_papers; ?></h3>
                <p>Total Papers</p>
            </div>

            <div class="card">
                <i class="fas fa-book"></i>
                <h3><?php echo $total_theses; ?></h3>
                <p>Total Thesis</p>
            </div>

            <div class="card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $approved_count; ?></h3>
                <p>Approved</p>
            </div>

            <div class="card">
                <i class="fas fa-clock"></i>
                <h3><?php echo $pending_count; ?></h3>
                <p>Pending Review</p>
            </div>

        </section>

        <!-- Quick Actions -->

        <section class="quick-actions">

            <h3>Quick Actions</h3>

            <div class="action-grid">

                <div class="action-box" onclick="openAddPaperModal()" style="cursor:pointer;">
                    <i class="fas fa-upload"></i>
                    <h4>Submit New Paper</h4>
                </div>

                <div class="action-box" onclick="openAddThesisModal()" style="cursor:pointer;">
                    <i class="fas fa-book-open"></i>
                    <h4>Submit Thesis</h4>
                </div>

                <div class="action-box" onclick="showSection('search')" style="cursor:pointer;">
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

                <?php 
                    $recent_submissions = array_slice($all_submissions, 0, 5);
                    if (empty($recent_submissions)): 
                ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#94a3b8; font-style:italic;">No submissions found.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    foreach ($recent_submissions as $row): 
                        $status_class = 'pending';
                        if ($row['STATUS'] === 'APPROVED' || $row['STATUS'] === 'PUBLISHED' || $row['STATUS'] === 'ACCEPTED') {
                            $status_class = 'approved';
                        } elseif ($row['STATUS'] === 'UNDER REVIEW') {
                            $status_class = 'review';
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['TITLE']); ?></td>
                            <td><?php echo htmlspecialchars($row['TYPE']); ?></td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($row['STATUS']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['SUB_DATE']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                </tbody>

            </table>

        </section>

        <!-- Progress -->

        <section class="progress-section">

            <h3>Research Progress</h3>

            <div class="progress-card">

                <p>Current Thesis: <strong><?php echo htmlspecialchars($thesis_title); ?></strong></p>

                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress_pct; ?>%;">
                        <?php echo $progress_pct; ?>%
                    </div>
                </div>

            </div>

        </section>
        </div> <!-- End of home-section -->

        <!-- Reviews & Feedback Section -->
        <div id="reviews-section" style="display: none;">
            <section class="table-section">
                <div class="section-header">
                    <h3>Reviews & Feedback</h3>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th>Paper Title</th>
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
                            <td colspan="6" style="text-align:center; color:#94a3b8; font-style:italic;">No reviews or feedback received yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews_list as $rev): ?>
                            <tr>
                                <td style="font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($rev['TITLE']); ?></td>
                                <td><?php echo htmlspecialchars($rev['REVIEWER_NAME']); ?></td>
                                <td style="font-weight:600; color:#2563eb;"><?php echo htmlspecialchars($rev['SCORE']); ?>/10</td>
                                <td>
                                    <?php 
                                    $rec = $rev['RECOMMENDATION'];
                                    $rec_class = 'pending';
                                    if ($rec === 'ACCEPT' || $rec === 'MINOR REVISION') $rec_class = 'completed';
                                    elseif ($rec === 'REJECT') $rec_class = 'rejected';
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
        </div>

        <!-- My Submissions Full List Section -->
        <div id="submissions-section" style="display: none;">
            <section class="table-section">
                <div class="section-header">
                    <h3>My Submissions</h3>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Submission Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($all_submissions)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#94a3b8; font-style:italic;">No submissions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        foreach ($all_submissions as $row): 
                            $status_class = 'pending';
                            if ($row['STATUS'] === 'APPROVED' || $row['STATUS'] === 'PUBLISHED' || $row['STATUS'] === 'ACCEPTED') {
                                $status_class = 'approved';
                            } elseif ($row['STATUS'] === 'UNDER REVIEW') {
                                $status_class = 'review';
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['TITLE']); ?></td>
                                <td><?php echo htmlspecialchars($row['TYPE']); ?></td>
                                <td><span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($row['STATUS']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['SUB_DATE']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- Search Papers Section -->
        <div id="search-section" style="display: none;">
            <!-- Search Form Card -->
            <section class="table-section" style="margin-bottom: 25px;">
                <div class="section-header">
                    <h3>Search My Papers</h3>
                </div>
                <form method="GET" action="researcher_dashboard.php" style="padding: 20px; display: flex; gap: 15px; align-items: flex-end;">
                    <!-- Keyword input -->
                    <div style="flex: 2; display: flex; flex-direction: column; gap: 8px;">
                        <label for="search_input" style="font-weight: 600; color: #475569; font-size: 14px;">Enter Keyword or Title</label>
                        <input type="text" id="search_input" name="keyword" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="e.g. machine learning, neural networks, blockchain..." style="padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s;">
                    </div>
                    
                    <!-- Year Filter dropdown -->
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 8px;">
                        <label for="year_filter" style="font-weight: 600; color: #475569; font-size: 14px;">Publication Year / Range</label>
                        <select id="year_filter" name="year_filter" style="padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; background: white; transition: border-color 0.2s;">
                            <option value="">All Years</option>
                            
                            <?php if (!empty($available_years)): ?>
                                <optgroup label="Particular Year">
                                    <?php foreach ($available_years as $yr): ?>
                                        <option value="<?php echo $yr; ?>" <?php echo ($selected_year_filter === (string)$yr) ? 'selected' : ''; ?>>
                                            <?php echo $yr; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            
                            <optgroup label="Year Range">
                                <option value="2020-2026" <?php echo ($selected_year_filter === '2020-2026') ? 'selected' : ''; ?>>2020 - 2026</option>
                                <option value="2015-2019" <?php echo ($selected_year_filter === '2015-2019') ? 'selected' : ''; ?>>2015 - 2019</option>
                                <option value="2010-2014" <?php echo ($selected_year_filter === '2010-2014') ? 'selected' : ''; ?>>2010 - 2014</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <!-- Buttons -->
                    <button type="submit" class="btn-submit" style="height: 46px; padding: 0 25px; font-weight: 600; border-radius: 8px; border: none; background: #2563eb; color: white; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <?php if ($is_search_performed): ?>
                        <a href="researcher_dashboard.php" class="btn-cancel" style="height: 46px; padding: 0 20px; font-weight: 600; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #64748b; text-decoration: none; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- Search Results -->
            <?php if ($is_search_performed): ?>
                <section class="table-section">
                    <div class="section-header">
                        <h3>
                            Search Results 
                            <?php 
                            $criteria = [];
                            if ($search_keyword !== '') {
                                $criteria[] = 'Keyword: "' . htmlspecialchars($search_keyword) . '"';
                            }
                            if ($selected_year_filter !== '') {
                                $criteria[] = 'Year/Range: "' . htmlspecialchars($selected_year_filter) . '"';
                            }
                            echo !empty($criteria) ? 'for ' . implode(' & ', $criteria) : '';
                            ?>
                        </h3>
                    </div>
                    <table>
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Keywords</th>
                            <th>Publication Year</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($search_results)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:#94a3b8; font-style:italic; padding: 25px;">No matching papers found. Try another keyword.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            foreach ($search_results as $row): 
                                $status_class = 'pending';
                                if ($row['STATUS'] === 'APPROVED' || $row['STATUS'] === 'PUBLISHED' || $row['STATUS'] === 'ACCEPTED') {
                                    $status_class = 'approved';
                                } elseif ($row['STATUS'] === 'UNDER REVIEW') {
                                    $status_class = 'review';
                                }
                            ?>
                                <tr>
                                    <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($row['TITLE']); ?></td>
                                    <td style="color: #64748b; font-size: 13px;"><?php echo htmlspecialchars($row['KEYWORDS'] ?: 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($row['PUBLICATION_YEAR']); ?></td>
                                    <td><span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($row['STATUS']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            <?php endif; ?>
        </div>

    </main>

</div>

<!-- ── Submit Paper Modal ──────────────────────────────── -->
<div class="modal-overlay" id="addPaperModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Submit New Paper</h3>
            <button class="close-btn" onclick="closeAddPaperModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form class="modal-form" action="../php/add_paper.php" method="POST">
                <div class="form-group">
                    <label for="paper_title">Paper Title</label>
                    <input type="text" id="paper_title" name="title" placeholder="e.g. AI-Based Disease Detection" required>
                </div>

                <div class="form-group">
                    <label for="paper_abstract">Abstract</label>
                    <textarea id="paper_abstract" name="abstract" placeholder="Describe the research paper..." rows="4" style="padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; font-family: inherit;" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="paper_keywords">Keywords</label>
                        <input type="text" id="paper_keywords" name="keywords" placeholder="e.g. Deep Learning, CNN, Diagnosis">
                    </div>

                    <div class="form-group">
                        <label for="paper_publication_year">Publication Year</label>
                        <input type="number" id="paper_publication_year" name="publication_year" value="<?php echo date('Y'); ?>" min="1900" max="2100" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="paper_co_authors">Co-Authors (comma-separated names)</label>
                    <input type="text" id="paper_co_authors" name="co_authors" placeholder="e.g. Dr. Rahman, John Smith">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddPaperModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Submit Paper</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Submit Thesis Modal ──────────────────────────────── -->
<div class="modal-overlay" id="addThesisModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Submit New Thesis</h3>
            <button class="close-btn" onclick="closeAddThesisModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form class="modal-form" action="../php/add_thesis.php" method="POST">
                <div class="form-group">
                    <label for="thesis_title">Thesis Title</label>
                    <input type="text" id="thesis_title" name="title" placeholder="e.g. Machine Learning in Healthcare" required>
                </div>

                <div class="form-group">
                    <label for="thesis_abstract">Abstract</label>
                    <textarea id="thesis_abstract" name="abstract" placeholder="Describe your thesis work..." rows="6" style="padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; font-family: inherit;" required></textarea>
                </div>

                <div class="form-group">
                    <label for="thesis_supervisor_id">Primary Supervisor</label>
                    <select id="thesis_supervisor_id" name="supervisor_id" required>
                        <option value="">Select Supervisor</option>
                        <?php 
                        oci_execute($supervisors_stid);
                        while ($sup = oci_fetch_assoc($supervisors_stid)): 
                        ?>
                            <option value="<?php echo htmlspecialchars($sup['USER_ID']); ?>">
                                <?php echo htmlspecialchars($sup['FIRST_NAME'] . ' ' . $sup['LAST_NAME']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddThesisModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Submit Thesis</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddPaperModal() {
    document.getElementById('addPaperModal').classList.add('active');
}
function closeAddPaperModal() {
    document.getElementById('addPaperModal').classList.remove('active');
}
function openAddThesisModal() {
    document.getElementById('addThesisModal').classList.add('active');
}
function closeAddThesisModal() {
    document.getElementById('addThesisModal').classList.remove('active');
}
function showSection(sectionId) {
    document.getElementById('home-section').style.display = 'none';
    document.getElementById('submissions-section').style.display = 'none';
    document.getElementById('search-section').style.display = 'none';
    if (document.getElementById('reviews-section')) {
        document.getElementById('reviews-section').style.display = 'none';
    }
    
    document.getElementById('nav-home').classList.remove('active');
    document.getElementById('nav-submissions').classList.remove('active');
    document.getElementById('nav-search').classList.remove('active');
    if (document.getElementById('nav-reviews')) {
        document.getElementById('nav-reviews').classList.remove('active');
    }
    
    if (sectionId === 'home') {
        document.getElementById('home-section').style.display = 'block';
        document.getElementById('nav-home').classList.add('active');
    } else if (sectionId === 'submissions') {
        document.getElementById('submissions-section').style.display = 'block';
        document.getElementById('nav-submissions').classList.add('active');
    } else if (sectionId === 'search') {
        document.getElementById('search-section').style.display = 'block';
        document.getElementById('nav-search').classList.add('active');
    } else if (sectionId === 'reviews') {
        if (document.getElementById('reviews-section')) {
            document.getElementById('reviews-section').style.display = 'block';
        }
        if (document.getElementById('nav-reviews')) {
            document.getElementById('nav-reviews').classList.add('active');
        }
    }
}

window.addEventListener('DOMContentLoaded', () => {
    <?php if ($is_search_performed): ?>
        showSection('search');
    <?php endif; ?>
});
</script>

</body>
</html>
