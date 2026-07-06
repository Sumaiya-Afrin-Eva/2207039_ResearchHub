<?php
/**
 * delete_paper.php
 * Handles permanent deletion of a research paper.
 * Calls the DELETE_PAPER stored procedure via an anonymous PL/SQL block.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as an Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper_id = (int) trim($_POST['paper_id'] ?? 0);
    $admin_id = (int) $_SESSION['user_id'];

    if ($paper_id <= 0) {
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Invalid Paper ID."));
        exit();
    }

    // Call stored procedure DELETE_PAPER
    $plsql = "
    BEGIN
        DELETE_PAPER(
            P_PAPER_ID => :paper_id,
            P_ADMIN_ID => :admin_id
        );
    END;
    ";

    $stid = oci_parse($conn, $plsql);
    oci_bind_by_name($stid, ':paper_id', $paper_id);
    oci_bind_by_name($stid, ':admin_id', $admin_id);

    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to delete paper: " . $e['message']));
        exit();
    }

    // Success redirect
    header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("Research paper deleted permanently."));
    exit();
} else {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}
?>
