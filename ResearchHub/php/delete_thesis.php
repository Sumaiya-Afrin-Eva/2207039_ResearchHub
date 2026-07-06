<?php
/**
 * delete_thesis.php
 * Handles the deletion of a thesis record.
 * Calls the DELETE_THESIS stored procedure via an anonymous PL/SQL block.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as an Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $thesis_id = (int) trim($_POST['thesis_id'] ?? 0);
    $admin_id  = (int) $_SESSION['user_id'];

    if ($thesis_id <= 0) {
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Invalid thesis selected."));
        exit();
    }

    // Call stored procedure DELETE_THESIS
    $plsql = "
    BEGIN
        DELETE_THESIS(
            P_THESIS_ID => :thesis_id,
            P_ADMIN_ID  => :admin_id
        );
    END;
    ";

    $stid = oci_parse($conn, $plsql);
    oci_bind_by_name($stid, ':thesis_id', $thesis_id);
    oci_bind_by_name($stid, ':admin_id',  $admin_id);

    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to delete thesis: " . $e['message']));
        exit();
    }

    // Success redirect
    header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("Thesis deleted successfully."));
    exit();
} else {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}
?>
