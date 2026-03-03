<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['reg_seq'])) {
    die("Application ID missing");
}

$reg_seq = (int) $_GET['reg_seq'];

// Fetch application 
$appStmt = $conn->prepare("
    SELECT reg_seq, app_name, cat_applied
    FROM registration
    WHERE reg_seq = :reg_seq
");
$appStmt->execute(['reg_seq' => $reg_seq]);
$app = $appStmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    die("Application not found");
}

// Fetch documents 
$docStmt = $conn->prepare("
   SELECT 
        d.document_id,
        d.document_type,
        d.file_path,
        d.upload_status,
        d.ocr_status,
        d.verification_status,
        o.ocr_id
    FROM uploaded_documents d
    LEFT JOIN ocr_extracted_data o
        ON o.document_id = d.document_id
    WHERE d.reg_seq = :reg_seq
");
$docStmt->execute(['reg_seq' => $reg_seq]);
$documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Uploaded Documents</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
</head>
<body>

<h2>Uploaded Documents</h2>

<p><strong>Application ID:</strong> <?= htmlspecialchars($app['reg_seq']) ?></p>
<p><strong>Applicant Name:</strong> <?= htmlspecialchars($app['app_name']) ?></p>
<p><strong>Category:</strong> <?= htmlspecialchars($app['cat_applied']) ?></p>

<?php
// Check if any documents need OCR
$needOcr = false;
foreach ($documents as $doc) {
    if (empty($doc['ocr_id'])) {
        $needOcr = true;
        break;
    }
}
?>

<?php if ($needOcr): ?>
    <p>
        <a href="../ocr/run_ocr_batch.php?reg_seq=<?= $reg_seq ?>" 
           class="btn btn-success" 
           style="font-size: 18px; padding: 12px 24px;">
             Run OCR on All Documents & Verify
        </a>
    </p>
<?php elseif (!empty($documents)): ?>
    <?php
    // Find first document with OCR data
    $firstDocId = null;
    foreach ($documents as $doc) {
        if (!empty($doc['ocr_id'])) {
            $firstDocId = $doc['document_id'];
            break;
        }
    }
    ?>
    <?php if ($firstDocId): ?>
        <p>
            <a href="../verification/review.php?reg_seq=<?= $reg_seq ?>" 
               class="btn btn-success" 
               style="font-size: 18px; padding: 12px 24px;">
                 Proceed to Verification
            </a>
        </p>
    <?php endif; ?>
<?php endif; ?>

<hr>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>Document Type</th>
        <th>File</th>
        <th>Upload Status</th>
        <th>OCR Status</th>
        <th>Action</th>
    </tr>

<?php if (!empty($documents)): ?>
    <?php foreach ($documents as $doc): ?>
        <tr>
            <td><?= htmlspecialchars($doc['document_type']) ?></td>

            <td>
                <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank">
                    <?= htmlspecialchars(basename($doc['file_path'])) ?>
                </a>
            </td>

            <td><?= htmlspecialchars($doc['upload_status']) ?></td>

            <td><?= htmlspecialchars($doc['ocr_status'] ?? 'PENDING') ?></td>

            <td>

                <!-- View Document -->
                <a href="../<?= htmlspecialchars($doc['file_path']) ?>"
                target="_blank"
                class="btn btn-secondary">
                View Document
                </a>

                <?php if (!empty($doc['ocr_id'])): ?>
                    <a href="../ocr/view.php?document_id=<?= $doc['document_id'] ?>&reg_seq=<?= $reg_seq ?>"
                    class="btn btn-info">
                    View OCR Data
                    </a>
                <?php endif; ?>

            </td>

        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="5">No documents uploaded</td>
    </tr>
<?php endif; ?>

</table>

<br>
<a href="../applications/view.php?reg_seq=<?= $reg_seq ?>" class="btn btn-secondary">
    Back to Application
</a>

</body>
</html>
