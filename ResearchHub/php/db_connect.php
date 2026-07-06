<?php

$db_username = "RESEARCHHUB";
$db_password = "ResearchPass123";
$db_connection_string = "//oracle-db:1521/XE";

$conn = oci_connect(
    $db_username,
    $db_password,
    $db_connection_string,
    'AL32UTF8'
);

if (!$conn) {

    $e = oci_error();

    die(
        "<h2>Database Connection Failed</h2>" .
        "<p><strong>Oracle Error:</strong> " .
        htmlspecialchars($e['message']) .
        "</p>"
    );
}

$stid = oci_parse($conn, "
    ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'
");
oci_execute($stid);
?>