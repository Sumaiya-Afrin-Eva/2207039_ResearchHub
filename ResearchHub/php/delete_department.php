<?php
/**
 * delete_department.php
 * Handles the deletion of a department record.
 * Calls the DELETE_DEPARTMENT stored procedure via an anonymous PL/SQL block.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as an Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_id  = (int) trim($_POST['department_id'] ?? 0);
    $admin_id = (int) $_SESSION['user_id'];

    if ($dept_id <= 0) {
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Invalid department selected."));
        exit();
    }

    // Call stored procedure DELETE_DEPARTMENT
    $plsql = "
    BEGIN
        DELETE_DEPARTMENT(
            P_DEPT_ID  => :dept_id,
            P_ADMIN_ID => :admin_id
        );
    END;
    ";

    $stid = oci_parse($conn, $plsql);
    oci_bind_by_name($stid, ':dept_id',  $dept_id);
    oci_bind_by_name($stid, ':admin_id', $admin_id);

    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to delete department: " . $e['message']));
        exit();
    }

    // Success redirect
    header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("Department deleted successfully."));
    exit();
} else {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}
?>
