<?php
session_start();
include '../php/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: ../frontend/login.php?error=" . urlencode("Please enter both email and password."));
        exit();
    }

    $sql = "
        SELECT USER_ID, ROLE_ID, FIRST_NAME, LAST_NAME, EMAIL, STATUS
        FROM USERS
        WHERE EMAIL = :email AND PASSWORD = :password
    ";

    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":email", $email);
    oci_bind_by_name($stid, ":password", $password);

    if (oci_execute($stid)) {
        $row = oci_fetch_assoc($stid);
        if ($row) {
            if ($row['STATUS'] !== 'ACTIVE') {
                header("Location: ../frontend/login.php?error=" . urlencode("Your account is inactive. Please contact the administrator."));
                exit();
            }

            // Set session variables
            $_SESSION['user_id'] = $row['USER_ID'];
            $_SESSION['role_id'] = $row['ROLE_ID'];
            $_SESSION['email'] = $row['EMAIL'];
            $_SESSION['first_name'] = $row['FIRST_NAME'];
            $_SESSION['last_name'] = $row['LAST_NAME'];

            // Log the login activity in AUDIT_LOGS using database procedure
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
                    P_ACTION_TYPE => 'LOGIN',
                    P_TABLE_NAME  => 'USERS',
                    P_DESCRIPTION => 'User logged in successfully'
                );
            END;
            ";
            $audit_stid = oci_parse($conn, $plsql);
            oci_bind_by_name($audit_stid, ":log_id", $next_log_id);
            oci_bind_by_name($audit_stid, ":user_id", $row['USER_ID']);
            oci_execute($audit_stid);

            // Redirect based on role
            switch ($row['ROLE_ID']) {
                case 1:
                    header("Location: ../frontend/admin_dashboard.php");
                    break;
                case 2:
                    header("Location: ../frontend/researcher_dashboard.php");
                    break;
                case 3:
                    header("Location: ../frontend/supervisor_dashboard.php");
                    break;
                case 4:
                    header("Location: ../frontend/reviewer_dashboard.php");
                    break;
                default:
                    header("Location: ../frontend/login.php?error=" . urlencode("Unknown user role."));
                    break;
            }
            exit();
        } else {
            header("Location: ../frontend/login.php?error=" . urlencode("Invalid email or password."));
            exit();
        }
    } else {
        header("Location: ../frontend/login.php?error=" . urlencode("Database query execution failed."));
        exit();
    }
} else {
    header("Location: ../frontend/login.php");
    exit();
}
?>