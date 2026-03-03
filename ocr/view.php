<?php
session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['document_id'])) {
    die("Document ID missing");
}

$document_id = (int) $_GET['document_id'];

/* Fetch OCR + document info */
$stmt = $conn->prepare("
    SELECT
        d.document_type,
        d.file_path,
        d.reg_seq,
        o.extracted_text,
        o.extracted_fields,
        o.confidence_score,
        o.created_at
    FROM uploaded_documents d
    JOIN ocr_extracted_data o
        ON o.document_id = d.document_id
    WHERE d.document_id = :id
");
$stmt->execute(['id' => $document_id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("OCR data not found");
}
$reg_seq = (int) $data['reg_seq'];

?>

<!DOCTYPE html>
<html>
<head>
    <title>OCR View</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
</head>
<body>

<h2>OCR Extracted Data</h2>

<p><strong>Document Type:</strong> <?= htmlspecialchars($data['document_type']) ?></p>
<p><strong>Confidence Score:</strong> <?= $data['confidence_score'] ?>%</p>
<p><strong>Processed At:</strong> <?= $data['created_at'] ?></p>

<hr>

<h3>Extracted Fields</h3>
<pre>
<?= htmlspecialchars(
    json_encode(json_decode($data['extracted_fields'], true), JSON_PRETTY_PRINT)
) ?>
</pre>

<h3>Raw OCR Text</h3>
<pre><?= htmlspecialchars($data['extracted_text']) ?></pre>

<br>
<!-- <a href="../verification/review.php?document_id=<?= $document_id ?>&reg_seq=<?= $reg_seq ?>"
   class="btn btn-primary">
    Go to Verification
</a> -->


<a href="../documents/list.php?reg_seq=<?= $data['reg_seq'] ?>"
   class="btn btn-secondary">
    Back to Documents
</a>

</body>
</html>
