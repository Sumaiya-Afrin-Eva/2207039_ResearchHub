<?php
/**
 * edit_user.php
 * Handles the Edit User form submission.
 * Calls the UPDATE_USER stored procedure via an anonymous PL/SQL block.
 */
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../frontend/admin_dashboard.php");
    exit();
}

// ── Collect & sanitise inputs ─────────────────────────────────────────────────
$user_id       = (int) trim($_POST['user_id']       ?? 0);
$first_name    = trim($_POST['first_name']    ?? '');
$last_name     = trim($_POST['last_name']     ?? '');
$email         = trim($_POST['email']         ?? '');
$password      = trim($_POST['password']      ?? '');   // blank = keep current
$institution   = trim($_POST['institution']   ?? '');
$department_id = (int) trim($_POST['department_id'] ?? 0);
$role_id       = (int) trim($_POST['role_id']       ?? 0);
$status        = trim($_POST['status']        ?? 'ACTIVE');
$admin_id      = (int) $_SESSION['user_id'];

// ── Basic validation ──────────────────────────────────────────────────────────
if ($user_id <= 0 || empty($first_name) || empty($last_name) ||
    empty($email) || empty($institution) || $department_id <= 0 || $role_id <= 0) {
    header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("All required fields must be filled."));
    exit();
}

// ── Call UPDATE_USER stored procedure via anonymous PL/SQL block ──────────────
// Passing NULL for password means the procedure will not change it.
$new_password = ($password !== '') ? $password : null;

$plsql = "
BEGIN
    UPDATE_USER(
        P_USER_ID       => :user_id,
        P_FIRST_NAME    => :first_name,
        P_LAST_NAME     => :last_name,
        P_EMAIL         => :email,
        P_INSTITUTION   => :institution,
        P_DEPARTMENT_ID => :department_id,
        P_ROLE_ID       => :role_id,
        P_STATUS        => :status,
        P_ADMIN_ID      => :admin_id,
        P_NEW_PASSWORD  => :new_password
    );
END;
";

$stid = oci_parse($conn, $plsql);

oci_bind_by_name($stid, ':user_id',       $user_id);
oci_bind_by_name($stid, ':first_name',    $first_name);
oci_bind_by_name($stid, ':last_name',     $last_name);
oci_bind_by_name($stid, ':email',         $email);
oci_bind_by_name($stid, ':institution',   $institution);
oci_bind_by_name($stid, ':department_id', $department_id);
oci_bind_by_name($stid, ':role_id',       $role_id);
oci_bind_by_name($stid, ':status',        $status);
oci_bind_by_name($stid, ':admin_id',      $admin_id);

// Bind password using SQLT_CHR; OCI8 will pass NULL when $new_password is null
oci_bind_by_name($stid, ':new_password', $new_password, 255, SQLT_CHR);

if (!oci_execute($stid)) {
    $e = oci_error($stid);
    header("Location: ../frontend/admin_dashboard.php?error=" . urlencode("Failed to update user: " . $e['message']));
    exit();
}

header("Location: ../frontend/admin_dashboard.php?success=" . urlencode("User updated successfully."));
exit();
?>
