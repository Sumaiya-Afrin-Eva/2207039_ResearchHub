<?php
/**
 * edit_thesis.php
 * Handles the editing/saving of an existing thesis.
 * Calls a custom PL/SQL transaction to update thesis and supervisors cleanly.
 */
session_start();
include_once 'db_connect.php';

// Verify that the user is logged in as Admin or Researcher
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2)) {
    header("Location: ../frontend/login.php?error=" . urlencode("Unauthorized access."));
    exit();
}

$role_id = (int)$_SESSION['role_id'];
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $thesis_id     = (int) trim($_POST['thesis_id'] ?? 0);
    $title         = trim($_POST['title'] ?? '');
    $abstract      = trim($_POST['abstract'] ?? '');
    $supervisor_id = (int) trim($_POST['supervisor_id'] ?? 0);

    // Redirect target based on role
    $redirect_url = ($role_id == 1) ? "../frontend/admin_dashboard.php" : "../frontend/researcher_dashboard.php";

    if ($thesis_id <= 0 || empty($title) || empty($abstract) || $supervisor_id <= 0) {
        header("Location: " . $redirect_url . "?error=" . urlencode("All fields are required."));
        exit();
    }

    if ($role_id == 2) {
        // Researcher - verify ownership
        $owner_query = "SELECT RESEARCHER_ID, DEPARTMENT_ID FROM THESES WHERE THESIS_ID = :thesis_id";
        $owner_stid = oci_parse($conn, $owner_query);
        oci_bind_by_name($owner_stid, ':thesis_id', $thesis_id);
        oci_execute($owner_stid);
        $owner_row = oci_fetch_assoc($owner_stid);
        
        if (!$owner_row || (int)$owner_row['RESEARCHER_ID'] !== $user_id) {
            header("Location: ../frontend/researcher_dashboard.php?error=" . urlencode("Unauthorized to edit this thesis."));
            exit();
        }
        $researcher_id = $user_id;
        $dept_id       = (int)$owner_row['DEPARTMENT_ID'];
    } else {
        // Administrator - get values from POST
        $researcher_id = (int) trim($_POST['researcher_id'] ?? 0);
        $dept_id       = (int) trim($_POST['department_id'] ?? 0);
    }

    if ($researcher_id <= 0 || $dept_id <= 0) {
        header("Location: " . $redirect_url . "?error=" . urlencode("Invalid researcher or department details."));
        exit();
    }

    // Call stored procedure logic via single PL/SQL block to ensure trigger TRG_THESIS_VERSION fires exactly once
    $plsql = "
    DECLARE
        V_LOG_ID NUMBER;
        V_SUPER_ID NUMBER;
    BEGIN
        -- 1. Single UPDATE statement to change thesis details and conditionally reset status
        UPDATE THESES
        SET TITLE = :title,
            ABSTRACT = :abstract,
            RESEARCHER_ID = :researcher_id,
            DEPARTMENT_ID = :dept_id,
            STATUS = CASE WHEN :role_id = 2 THEN 'SUBMITTED' ELSE STATUS END
        WHERE THESIS_ID = :thesis_id;

        -- 2. Update primary supervisor assignment
        UPDATE THESIS_SUPERVISIONS
        SET SUPERVISOR_ID = :supervisor_id
        WHERE THESIS_ID = :thesis_id AND SUPERVISOR_TYPE = 'PRIMARY';

        -- If no primary supervisor existed, insert one
        IF SQL%ROWCOUNT = 0 THEN
            SELECT COALESCE(MAX(SUPERVISION_ID), 0) + 1 INTO V_SUPER_ID FROM THESIS_SUPERVISIONS;
            INSERT INTO THESIS_SUPERVISIONS (SUPERVISION_ID, THESIS_ID, SUPERVISOR_ID, SUPERVISOR_TYPE)
            VALUES (V_SUPER_ID, :thesis_id, :supervisor_id, 'PRIMARY');
        END IF;

        -- 3. Log action to audit logs
        SELECT COALESCE(MAX(LOG_ID), 0) + 1 INTO V_LOG_ID FROM AUDIT_LOGS;
        INSERT INTO AUDIT_LOGS (LOG_ID, USER_ID, ACTION_TYPE, TABLE_NAME, ACTION_DATE, DESCRIPTION)
        VALUES (V_LOG_ID, :user_id, 'UPDATE', 'THESES', SYSDATE, 'Updated thesis ID ' || :thesis_id || ' (submitting new version/updating title or abstract)');

        COMMIT;
    END;
    ";

    $stid = oci_parse($conn, $plsql);

    // Bind values
    oci_bind_by_name($stid, ':thesis_id',     $thesis_id);
    oci_bind_by_name($stid, ':title',         $title);
    
    // Bind CLOB abstract
    $clob = oci_new_descriptor($conn, OCI_D_LOB);
    oci_bind_by_name($stid, ':abstract',      $clob, -1, OCI_B_CLOB);
    
    oci_bind_by_name($stid, ':researcher_id', $researcher_id);
    oci_bind_by_name($stid, ':dept_id',       $dept_id);
    oci_bind_by_name($stid, ':supervisor_id', $supervisor_id);
    oci_bind_by_name($stid, ':user_id',       $user_id);
    oci_bind_by_name($stid, ':role_id',       $role_id);

    // Write abstract content to LOB descriptor
    $clob->writeTemporary($abstract, OCI_TEMP_CLOB);

    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        $clob->close();
        header("Location: " . $redirect_url . "?error=" . urlencode("Failed to update thesis: " . $e['message']));
        exit();
    }

    $clob->close();

    // Success redirect
    header("Location: " . $redirect_url . "?success=" . urlencode("Thesis updated/resubmitted successfully."));
    exit();
} else {
    $redirect_url = ($role_id == 1) ? "../frontend/admin_dashboard.php" : "../frontend/researcher_dashboard.php";
    header("Location: " . $redirect_url);
    exit();
}
?>
