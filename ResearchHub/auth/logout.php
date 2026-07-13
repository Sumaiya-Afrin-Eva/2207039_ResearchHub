<?php
session_start();
include '../php/db_connect.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Log logout in AUDIT_LOGS using database procedure
    $log_sql = "SELECT COALESCE(MAX(LOG_ID), 0) + 1 AS NEXT_ID FROM AUDIT_LOGS";
    $log_stid = oci_parse($conn, $log_sql);
    oci_execute($log_stid);
    $log_row = oci_fetch_assoc($log_stid);
    $next_log_id = $log_row['NEXT_ID'];

    $plsql = "
    BEGIN
        ADD_AUDIT_LOG(
            P_LOG_ID      => :log_id,
            P_USER_ID     => :user_id,
            P_ACTION_TYPE => 'LOGOUT',
            P_TABLE_NAME  => 'USERS',
            P_DESCRIPTION => 'User logged out successfully'
        );
    END;
    ";
    $audit_stid = oci_parse($conn, $plsql);
    oci_bind_by_name($audit_stid, ":log_id", $next_log_id);
    oci_bind_by_name($audit_stid, ":user_id", $user_id);
    oci_execute($audit_stid);
}

session_unset();
session_destroy();

header("Location: ../frontend/login.php");
exit();
?>
