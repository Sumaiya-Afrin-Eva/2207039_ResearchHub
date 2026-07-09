<?php
/**
 * review_thesis.php
 * Handles supervisor approval/rejection of a thesis.
 * Calls the REVIEW_THESIS stored procedure via an anonymous PL/SQL block.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as a Supervisor (role_id = 3) or Admin (role_id = 1)
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 3 && $_SESSION['role_id'] != 1)) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

$thesis_id = (int)($_POST['thesis_id'] ?? ($_GET['id'] ?? 0));
$status    = trim($_POST['status'] ?? ($_GET['status'] ?? ''));
$user_id   = (int)$_SESSION['user_id'];

if ($thesis_id <= 0 || !in_array($status, ['APPROVED', 'REJECTED'])) {
    header("Location: ../frontend/supervisor_dashboard.php?error=" . urlencode("Invalid thesis or status."));
    exit();
}

// Call stored procedure REVIEW_THESIS
$plsql = "
BEGIN
    REVIEW_THESIS(
        P_THESIS_ID => :thesis_id,
        P_STATUS    => :status,
        P_USER_ID   => :user_id
    );
END;
";

$stid = oci_parse($conn, $plsql);
oci_bind_by_name($stid, ':thesis_id', $thesis_id);
oci_bind_by_name($stid, ':status',    $status);
oci_bind_by_name($stid, ':user_id',   $user_id);

if (!oci_execute($stid)) {
    $e = oci_error($stid);
    header("Location: ../frontend/supervisor_dashboard.php?error=" . urlencode("Failed to review thesis: " . $e['message']));
    exit();
}

header("Location: ../frontend/supervisor_dashboard.php?success=" . urlencode("Thesis status updated to " . $status . "."));
exit();
?>
