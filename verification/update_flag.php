<?php
require_once __DIR__.'/../config/db.php';

$flag_id = $_POST['flag_id'];
$status = $_POST['status'];
$remark = $_POST['remark'];

$stmt = $conn->prepare("
UPDATE verification_flags
SET verifier_status = :status,
    verifier_remark = :remark
WHERE flag_id = :id
");

$stmt->execute([
'status'=>$status,
'remark'=>$remark,
'id'=>$flag_id
]);

header("Location: ".$_SERVER['HTTP_REFERER']);
exit;