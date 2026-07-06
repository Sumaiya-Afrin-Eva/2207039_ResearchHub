<?php
/**
 * delete_user.php
 * Handles the Delete User action.
 * Calls the DELETE_USER stored procedure via an anonymous PL/SQL block.
 * Permanently removes the user and all dependent records from the database.
 */
session_start();
include_once 'db_connect.php';

// Only admins may delete users
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}

$user_id  = (int) trim($_POST['user_id']  ?? 0);
$admin_id = (int) $_SESSION['user_id'];

// Prevent admin from deleting themselves
if ($user_id <= 0) {
    header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Invalid user ID."));
    exit();
}

if ($user_id === $admin_id) {
    header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("You cannot delete your own account."));
    exit();
}

// ── Call DELETE_USER stored procedure via anonymous PL/SQL block ──────────────
$plsql = "
BEGIN
    DELETE_USER(
        P_USER_ID  => :user_id,
        P_ADMIN_ID => :admin_id
    );
END;
";

$stid = oci_parse($conn, $plsql);
oci_bind_by_name($stid, ':user_id',  $user_id);
oci_bind_by_name($stid, ':admin_id', $admin_id);

if (!oci_execute($stid)) {
    $e = oci_error($stid);
    header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to delete user: " . $e['message']));
    exit();
}

header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("User deleted successfully."));
exit();
?>
