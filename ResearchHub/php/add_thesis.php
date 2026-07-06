<?php
/**
 * add_thesis.php
 * Handles the creation of a new thesis.
 * Calls the ADD_THESIS stored procedure via an anonymous PL/SQL block.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as an Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title'] ?? '');
    $abstract      = trim($_POST['abstract'] ?? '');
    $researcher_id = (int) trim($_POST['researcher_id'] ?? 0);
    $dept_id       = (int) trim($_POST['department_id'] ?? 0);
    $supervisor_id = (int) trim($_POST['supervisor_id'] ?? 0);
    $admin_id      = (int) $_SESSION['user_id'];

    // Input Validation
    if (empty($title) || empty($abstract) || $researcher_id <= 0 || $dept_id <= 0 || $supervisor_id <= 0) {
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("All fields are required."));
        exit();
    }

    // Call stored procedure ADD_THESIS
    $plsql = "
    BEGIN
        ADD_THESIS(
            P_TITLE         => :title,
            P_ABSTRACT      => :abstract,
            P_RESEARCHER_ID => :researcher_id,
            P_DEPT_ID       => :dept_id,
            P_SUPERVISOR_ID => :supervisor_id,
            P_ADMIN_ID      => :admin_id
        );
    END;
    ";

    $stid = oci_parse($conn, $plsql);

    // Bind values
    oci_bind_by_name($stid, ':title',         $title);
    
    // Bind CLOB abstract
    $clob = oci_new_descriptor($conn, OCI_D_LOB);
    oci_bind_by_name($stid, ':abstract',      $clob, -1, OCI_B_CLOB);
    
    oci_bind_by_name($stid, ':researcher_id', $researcher_id);
    oci_bind_by_name($stid, ':dept_id',       $dept_id);
    oci_bind_by_name($stid, ':supervisor_id', $supervisor_id);
    oci_bind_by_name($stid, ':admin_id',      $admin_id);

    // Write abstract content to LOB descriptor
    $clob->writeTemporary($abstract, OCI_TEMP_CLOB);

    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        $clob->close();
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to add thesis: " . $e['message']));
        exit();
    }

    $clob->close();

    // Success redirect
    header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("Thesis added successfully."));
    exit();
} else {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}
?>
