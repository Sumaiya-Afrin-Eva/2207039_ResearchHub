<?php
/**
 * get_user.php
 * AJAX endpoint – returns one user's data as JSON for the Edit modal.
 * SQL: parameterised SELECT via OCI8.
 */
ob_start();
session_start();
include_once 'db_connect.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user_id']);
    exit();
}

$sql = "
    SELECT
        U.USER_ID,
        U.FIRST_NAME,
        U.LAST_NAME,
        U.EMAIL,
        U.INSTITUTION,
        U.DEPARTMENT_ID,
        U.ROLE_ID,
        U.STATUS
    FROM USERS U
    WHERE U.USER_ID = :uid
";

$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ':uid', $user_id);

if (!oci_execute($stid)) {
    $e = oci_error($stid);
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e['message']]);
    exit();
}

$row = oci_fetch_assoc($stid);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

echo json_encode([
    'user_id'       => (int)$row['USER_ID'],
    'first_name'    => $row['FIRST_NAME'],
    'last_name'     => $row['LAST_NAME'],
    'email'         => $row['EMAIL'],
    'institution'   => $row['INSTITUTION'],
    'department_id' => (int)$row['DEPARTMENT_ID'],
    'role_id'       => (int)$row['ROLE_ID'],
    'status'        => $row['STATUS'],
]);
?>
