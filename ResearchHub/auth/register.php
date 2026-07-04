<?php

include '../php/db_connect.php';

$userid = 100;
$roleid = 2;
$deptid = 1;

$fname = $_POST['first_name'];
$lname = $_POST['last_name'];
$email = $_POST['email'];
$password = $_POST['password'];

$sql = "
INSERT INTO USERS
(
USER_ID,
ROLE_ID,
DEPARTMENT_ID,
FIRST_NAME,
LAST_NAME,
EMAIL,
PASSWORD,
INSTITUTION,
STATUS
)
VALUES
(
:userid,
:roleid,
:deptid,
:fname,
:lname,
:email,
:password,
'DIU',
'ACTIVE'
)
";

$stid = oci_parse($conn, $sql);

oci_bind_by_name($stid, ":userid", $userid);
oci_bind_by_name($stid, ":roleid", $roleid);
oci_bind_by_name($stid, ":deptid", $deptid);
oci_bind_by_name($stid, ":fname", $fname);
oci_bind_by_name($stid, ":lname", $lname);
oci_bind_by_name($stid, ":email", $email);
oci_bind_by_name($stid, ":password", $password);

oci_execute($stid);

echo "Registration Successful";

?>