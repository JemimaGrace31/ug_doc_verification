<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$reg_seq = (int) $_POST['reg_seq'];
$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if ($action === 'mark_verified') {
    // VERIFIER: Mark as Verified
    $stmt = $conn->prepare("
        UPDATE application_verification
        SET
            verification_status = 'VERIFIED',
            verifier_decision = 'VERIFIED'
        WHERE reg_seq = :seq
    ");
    
    $stmt->execute(['seq' => $reg_seq]);
    
    header("Location: ../documents/list.php?reg_seq=" . $reg_seq . "&msg=verified");
    exit;

} elseif ($action === 'mark_in_progress') {
    // VERIFIER: Mark as In Progress
    $stmt = $conn->prepare("
        UPDATE application_verification
        SET
            verification_status = 'IN_PROGRESS',
            verifier_decision = 'IN_PROGRESS'
        WHERE reg_seq = :seq
    ");
    
    $stmt->execute(['seq' => $reg_seq]);
    
    header("Location: ../documents/list.php?reg_seq=" . $reg_seq . "&msg=in_progress");
    exit;

} elseif ($action === 'admin_decision') {
    // ADMIN: Approve/Reject
    $decision = $_POST['decision'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($decision === 'REJECTED' && empty($remarks)) {
        die("Remarks are mandatory for rejection");
    }
    
    $stmt = $conn->prepare("
        UPDATE application_verification
        SET
            admin_decision = :status,
            admin_remark = :remarks
        WHERE reg_seq = :seq
    ");
    
    $stmt->execute([
        'status' => $decision,
        'remarks' => $remarks,
        'seq' => $reg_seq
    ]);
    
    header("Location: ../documents/list.php?reg_seq=" . $reg_seq . "&msg=decision_submitted");
    exit;

} else {
    die("Invalid action");
}
