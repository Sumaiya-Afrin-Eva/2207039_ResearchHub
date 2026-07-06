<?php
session_start();
include 'db_connect.php';

// Verify that the user is logged in as an Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $department_id = trim($_POST['department_id'] ?? '');
    $role_id = trim($_POST['role_id'] ?? '');
    $status = trim($_POST['status'] ?? 'ACTIVE');

    // Input Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($institution) || empty($department_id) || empty($role_id)) {
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("All fields are required."));
        exit();
    }

    // 1. Get next USER_ID
    $user_id_sql = "SELECT COALESCE(MAX(USER_ID), 0) + 1 AS NEXT_ID FROM USERS";
    $user_id_stid = oci_parse($conn, $user_id_sql);
    if (!oci_execute($user_id_stid)) {
        $e = oci_error($user_id_stid);
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to generate User ID: " . $e['message']));
        exit();
    }
    $user_id_row = oci_fetch_assoc($user_id_stid);
    $next_user_id = $user_id_row['NEXT_ID'];

    // 2. Insert User into USERS table
    $insert_sql = "
        INSERT INTO USERS (
            USER_ID, 
            ROLE_ID, 
            DEPARTMENT_ID, 
            FIRST_NAME, 
            LAST_NAME, 
            EMAIL, 
            PASSWORD, 
            INSTITUTION, 
            REGISTRATION_DATE, 
            STATUS
        ) VALUES (
            :user_id, 
            :role_id, 
            :department_id, 
            :first_name, 
            :last_name, 
            :email, 
            :password, 
            :institution, 
            SYSDATE, 
            :status
        )
    ";

    $insert_stid = oci_parse($conn, $insert_sql);
    oci_bind_by_name($insert_stid, ":user_id", $next_user_id);
    oci_bind_by_name($insert_stid, ":role_id", $role_id);
    oci_bind_by_name($insert_stid, ":department_id", $department_id);
    oci_bind_by_name($insert_stid, ":first_name", $first_name);
    oci_bind_by_name($insert_stid, ":last_name", $last_name);
    oci_bind_by_name($insert_stid, ":email", $email);
    oci_bind_by_name($insert_stid, ":password", $password);
    oci_bind_by_name($insert_stid, ":institution", $institution);
    oci_bind_by_name($insert_stid, ":status", $status);

    if (!oci_execute($insert_stid)) {
        $e = oci_error($insert_stid);
        header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to insert user record: " . $e['message']));
        exit();
    }

    // 3. Log the event in AUDIT_LOGS table
    $log_sql = "SELECT COALESCE(MAX(LOG_ID), 0) + 1 AS NEXT_ID FROM AUDIT_LOGS";
    $log_stid = oci_parse($conn, $log_sql);
    oci_execute($log_stid);
    $log_row = oci_fetch_assoc($log_stid);
    $next_log_id = $log_row['NEXT_ID'];

    $admin_id = $_SESSION['user_id'];
    $description = "Admin created user account: " . htmlspecialchars($email);

    $audit_sql = "
        INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
        VALUES (:log_id, :admin_id, 'INSERT', 'USERS', SYSDATE, :description)
    ";
    $audit_stid = oci_parse($conn, $audit_sql);
    oci_bind_by_name($audit_stid, ":log_id", $next_log_id);
    oci_bind_by_name($audit_stid, ":admin_id", $admin_id);
    oci_bind_by_name($audit_stid, ":description", $description);
    oci_execute($audit_stid);

    header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("User added successfully."));
    exit();
} else {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}
?>
