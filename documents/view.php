<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../ocr/auto_ocr.php';

//  Validate document_id 
if (!isset($_GET['document_id'])) {
    die("Document ID missing");
}

$document_id = (int) $_GET['document_id'];

//  Fetch document details 
$stmt = $conn->prepare("
    SELECT d.document_id, d.document_type, d.file_path, d.reg_seq
    FROM uploaded_documents d
    WHERE d.document_id = :id
");
$stmt->execute(['id' => $document_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die("Document not found");
}

//  AUTO OCR
$check = $conn->prepare("
    SELECT ocr_id
    FROM ocr_extracted_data
    WHERE document_id = :id
");
$check->execute(['id' => $document_id]);

if (!$check->fetch()) {
    // OCR missing → run silently
    runOCR($document_id);
}

// Fetch OCR result 
$ocrStmt = $conn->prepare("
    SELECT extracted_fields, confidence_score
    FROM ocr_extracted_data
    WHERE document_id = :id
");
$ocrStmt->execute(['id' => $document_id]);
$ocr = $ocrStmt->fetch(PDO::FETCH_ASSOC);

$fields = [];
if ($ocr && !empty($ocr['extracted_fields'])) {
    $fields = json_decode($ocr['extracted_fields'], true);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Document Verification</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 60%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #f4f4f4; text-align: left; }
    </style>
</head>
<body>

<h2>Document Verification (Staff View)</h2>

<p><strong>Document Type:</strong> <?php echo htmlspecialchars($document['document_type']); ?></p>

<p>
    <strong>View Document:</strong>
    <a href="../<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank">
        Open File
    </a>
</p>

<hr>

<h3>OCR Extracted Details</h3>

<?php if (!empty($fields)): ?>
<table border="1" cellpadding="8">
    <?php foreach ($fields as $key => $value): ?>
        <tr>
            <th><?php echo ucwords(str_replace('_', ' ', $key)); ?></th>
            <td><?php echo htmlspecialchars($value); ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<p><strong>Confidence:</strong> <?php echo $ocr['confidence_score']; ?>%</p>

<?php else: ?>
<p style="color:red;">OCR data not available yet.</p>
<?php endif; ?>


<br>
<a href="list.php?reg_seq=<?php echo $document['reg_seq']; ?>">⬅ Back to Documents</a>

</body>
</html>
