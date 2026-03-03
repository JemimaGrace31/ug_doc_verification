<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes for batch processing

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['reg_seq'])) {
    die("Application ID missing");
}

$reg_seq = (int) $_GET['reg_seq'];

// Fetch all documents that need OCR
$stmt = $conn->prepare("
    SELECT d.document_id, d.document_type
    FROM uploaded_documents d
    LEFT JOIN ocr_extracted_data o ON o.document_id = d.document_id
    WHERE d.reg_seq = :seq AND o.ocr_id IS NULL
");
$stmt->execute(['seq' => $reg_seq]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($documents)) {
    header("Location: ../verification/review.php?document_id=1&reg_seq=$reg_seq");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Processing Documents</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
    <style>
        .progress-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .progress-item {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background: #f8f9fa;
        }
        .progress-item.processing {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .progress-item.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .progress-item.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>

<div class="progress-container">
    <h2> Processing Documents</h2>
    <p>Please wait while we process all documents...</p>
    <hr>
    
    <div id="progress">
        <?php foreach ($documents as $doc): ?>
            <div class="progress-item" id="doc-<?= $doc['document_id'] ?>">
                ⏳ <?= htmlspecialchars($doc['document_type']) ?> - Waiting...
            </div>
        <?php endforeach; ?>
    </div>
    
    <div id="complete" style="display:none; margin-top: 20px;">
        <p style="color: green; font-weight: bold;">✓ All documents processed!</p>
        <p>Redirecting to verification...</p>
    </div>
</div>

<script>
const documents = <?= json_encode($documents) ?>;
const regSeq = <?= $reg_seq ?>;
let currentIndex = 0;
let firstDocId = <?= $documents[0]['document_id'] ?>;

async function processNext() {
    if (currentIndex >= documents.length) {
        document.getElementById('complete').style.display = 'block';
        setTimeout(() => {
            window.location.href = '../verification/review.php?reg_seq=' + regSeq;
        }, 2000);
        return;
    }
    
    const doc = documents[currentIndex];
    const element = document.getElementById('doc-' + doc.document_id);
    
    element.className = 'progress-item processing';
    element.innerHTML = '⏳ ' + doc.document_type + ' - Processing...';
    
    try {
        const response = await fetch('run_ocr.php?document_id=' + doc.document_id + '&batch=1');
        const text = await response.text();
        
        element.className = 'progress-item success';
        element.innerHTML = '✓ ' + doc.document_type + ' - Complete';
    } catch (error) {
        element.className = 'progress-item error';
        element.innerHTML = '✗ ' + doc.document_type + ' - Failed';
    }
    
    currentIndex++;
    setTimeout(processNext, 500);
}

// Start processing
processNext();
</script>

</body>
</html>
