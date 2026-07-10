<?php
/**
 * add_paper.php
 * Handles the creation of a new research paper.
 * Calls the ADD_PAPER stored procedure via an anonymous PL/SQL block.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as Admin or Researcher
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

$role_id = (int)$_SESSION['role_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title            = trim($_POST['title'] ?? '');
    $abstract         = trim($_POST['abstract'] ?? '');
    $keywords         = trim($_POST['keywords'] ?? '');
    $publication_year = (int) trim($_POST['publication_year'] ?? date('Y'));
    
    // If researcher, primary researcher_id is their own session ID.
    // If admin, it is passed via POST.
    if ($role_id == 2) {
        $researcher_id = (int)$_SESSION['user_id'];
    } else {
        $researcher_id = (int) trim($_POST['researcher_id'] ?? 0);
    }
    
    $co_authors       = trim($_POST['co_authors'] ?? '');
    $admin_id         = (int) $_SESSION['user_id']; // Logged in user ID for audit log

    // Redirect targets
    $redirect_url = ($role_id == 1) ? "../frontend/admin_dashboard.php" : "../frontend/researcher_dashboard.php";

    // Input Validation
    if (empty($title) || empty($abstract) || $researcher_id <= 0 || $publication_year <= 0) {
        header("Location: " . $redirect_url . "?error=" . urlencode("Title, Abstract, and Year are required."));
        exit();
    }

    // Call stored procedure ADD_PAPER
    $plsql = "
    BEGIN
        ADD_PAPER(
            P_TITLE            => :title,
            P_ABSTRACT         => :abstract,
            P_KEYWORDS         => :keywords,
            P_PUBLICATION_YEAR => :publication_year,
            P_RESEARCHER_ID    => :researcher_id,
            P_CO_AUTHORS       => :co_authors,
            P_ADMIN_ID         => :admin_id
        );
    END;
    ";

    $stid = oci_parse($conn, $plsql);

    // Bind values
    oci_bind_by_name($stid, ':title',            $title);
    oci_bind_by_name($stid, ':keywords',         $keywords);
    oci_bind_by_name($stid, ':publication_year', $publication_year);
    oci_bind_by_name($stid, ':researcher_id',    $researcher_id);
    oci_bind_by_name($stid, ':co_authors',       $co_authors);
    oci_bind_by_name($stid, ':admin_id',         $admin_id);

    // Bind CLOB abstract
    $clob = oci_new_descriptor($conn, OCI_D_LOB);
    oci_bind_by_name($stid, ':abstract',         $clob, -1, OCI_B_CLOB);

    // Write abstract content to LOB descriptor
    $clob->writeTemporary($abstract, OCI_TEMP_CLOB);

    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        $clob->close();
        header("Location: " . $redirect_url . "?error=" . urlencode("Failed to add paper: " . $e['message']));
        exit();
    }

    $clob->close();

    // Success redirect
    header("Location: " . $redirect_url . "?success=" . urlencode("Research paper added successfully."));
    exit();
} else {
    $redirect_url = ($role_id == 1) ? "../frontend/admin_dashboard.php" : "../frontend/researcher_dashboard.php";
    header("Location: " . $redirect_url);
    exit();
}
?>
