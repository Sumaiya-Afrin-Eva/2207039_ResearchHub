<?php
/**
 * add_department.php
 * Handles the insertion of a new department.
 * Calls the ADD_DEPARTMENT stored procedure via an anonymous PL/SQL block.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as an Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_name = trim($_POST['department_name'] ?? '');
    $faculty   = trim($_POST['faculty'] ?? '');
    $admin_id  = (int) $_SESSION['user_id'];

    // Input Validation
    if (empty($dept_name) || empty($faculty)) {
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("All fields are required."));
        exit();
    }

    // Call stored procedure ADD_DEPARTMENT
    $plsql = "
    BEGIN
        ADD_DEPARTMENT(
            P_DEPT_NAME => :dept_name,
            P_FACULTY   => :faculty,
            P_ADMIN_ID  => :admin_id
        );
    END;
    ";

    $stid = oci_parse($conn, $plsql);
    oci_bind_by_name($stid, ':dept_name', $dept_name);
    oci_bind_by_name($stid, ':faculty',   $faculty);
    oci_bind_by_name($stid, ':admin_id',  $admin_id);

    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to add department: " . $e['message']));
        exit();
    }

    // Success redirect
    header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("Department added successfully."));
    exit();
} else {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}
?>
