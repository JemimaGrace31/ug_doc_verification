<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../rules/nri_rules.php';
require_once __DIR__ . '/../rules/ciwgc_rules.php';
require_once __DIR__ . '/../rules/foreign_rules.php';
require_once __DIR__ . '/../rules/academic_rules.php';


if (!isset($_GET['reg_seq'])) {
    die("Invalid navigation: missing reg_seq");
}

$reg_seq = (int) $_GET['reg_seq'];
$selectedDocId = isset($_GET['document_id']) ? (int)$_GET['document_id'] : null;

// Get current user role and ID
$userRole = $_SESSION['role'] ?? 'VERIFIER';
$userId = $_SESSION['staff_id'] ?? null;

//FETCH APPLICANT & VERIFICATION STATUS

$stmt = $conn->prepare("
    SELECT r.app_name, r.cat_applied, 
           av.verification_status, av.assigned_verifier, av.verifier_decision, av.admin_decision
    FROM registration r
    LEFT JOIN application_verification av ON av.reg_seq = r.reg_seq
    WHERE r.reg_seq = :seq
");
$stmt->execute(['seq' => $reg_seq]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant not found");
}

//ACCESS CONTROL: Verifiers can only see their assigned categories

if ($userRole !== 'ADMIN') {
    $stmt = $conn->prepare("
        SELECT 1 FROM category_verifier 
        WHERE cat_code = :cat AND staff_id = :staff
    ");
    $stmt->execute(['cat' => $applicant['cat_applied'], 'staff' => $userId]);
    
    if (!$stmt->fetch()) {
        die("Access denied: You are not assigned to this category");
    }
}

//FETCH ALL DOCUMENTS

$stmt = $conn->prepare("
    SELECT 
        d.document_id,
        d.document_type,
        d.file_path,
        o.extracted_fields
    FROM uploaded_documents d
    LEFT JOIN ocr_extracted_data o 
        ON o.document_id = d.document_id
    WHERE d.reg_seq = :seq
    ORDER BY d.document_type
");
$stmt->execute(['seq' => $reg_seq]);
$allDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

//GROUP DOCUMENTS FOR RULE ENGINE

$allDocuments = [];

foreach ($allDocs as $doc) {
    $fields = json_decode($doc['extracted_fields'] ?? '{}', true) ?? [];
    
    // Map document type names
    $mappedType = ($doc['document_type'] === 'TENTH_MARKSHEET') ? '10TH MARKSHEET' : 
                  (($doc['document_type'] === 'HSC_MARKSHEET') ? '12TH MARKSHEET' : $doc['document_type']);

    if (empty($fields) || (isset($fields['note']) && $fields['note'] === 'No data extracted')) {
        // No OCR data - still add to allDocuments as empty to track document exists
        $allDocuments[$mappedType] = [];
        continue;
    }

    // Check if this is multi-page extraction (grouped by type)
    foreach ($fields as $key => $value) {
        // If value is an array of documents (multi-page)
        if (is_array($value) && isset($value[0]) && is_array($value[0])) {
            // Map MARKSHEET to uploaded document type (10TH/12TH MARKSHEET)
            if ($key === 'MARKSHEET') {
                $allDocuments[$mappedType] = $value[0];
            } else {
                // Multiple documents of same type
                $allDocuments[$key] = $value;
            }
        } 
        // If it's a single document with fields
        elseif (is_array($value) && !empty($value)) {
            // Check if it's a document type key
            if (in_array($key, ['PASSPORT', 'VISA', 'NRI CERTIFICATE', 'EMPLOYMENT CERTIFICATE', 'BANK STATEMENT', 'BIRTH CERTIFICATE', 'MARKSHEET', '10TH MARKSHEET', '12TH MARKSHEET', 'SCHOOL TC'])) {
                // Map MARKSHEET to uploaded document type
                if ($key === 'MARKSHEET') {
                    $allDocuments[$mappedType] = $value;
                } else {
                    $allDocuments[$key] = $value;
                }
            } else {
                // It's a single document, use uploaded document type with mapping
                $allDocuments[$mappedType] = $fields;
                break;
            }
        }
    }
}

//RUN RULE ENGINE

$flags = [];

if ((int)$applicant['cat_applied'] === 102) {
    $flags = runNriRules($allDocuments);
}
if ((int)$applicant['cat_applied'] === 101) {
    $flags = runCiwgcRules($allDocuments);
}
if ((int)$applicant['cat_applied'] === 103 || (int)$applicant['cat_applied'] === 105) {
    $flags = runForeignRules($allDocuments);
}

$academicFlags = runAcademicRules($allDocuments);
$allFlags = array_merge($flags, $academicFlags);

//FETCH SELECTED DOCUMENT

$selectedDoc = null;

if ($selectedDocId) {
    $stmt = $conn->prepare("
        SELECT d.document_type, d.file_path, o.extracted_fields
        FROM uploaded_documents d
        LEFT JOIN ocr_extracted_data o 
            ON o.document_id = d.document_id
        WHERE d.document_id = :id
    ");
    $stmt->execute(['id' => $selectedDocId]);
    $selectedDoc = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Application Verification</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0;
            padding: 20px;
        }

        .header-section {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .main-layout {
            display: grid;
            grid-template-columns: 220px 420px 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        
        .left-sidebar {
            width: 250px;
            flex-shrink: 0;
        }

        .right-content {
            flex: 1;
        }

        .doc-list {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
        }

        .doc-list h3 {
            margin-top: 0;
            font-size: 16px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
        }

        .doc-list a {
            display: block;
            padding: 10px;
            margin-bottom: 5px;
            text-decoration: none;
            color: #007bff;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .doc-list a:hover {
            background: #e7f3ff;
        }

        .doc-list a.active {
            background: #007bff;
            color: white;
            font-weight: bold;
        }

        .viewer-box {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            min-height: 400px;
        }

        .viewer-box h4 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        .doc-ocr-container {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .doc-preview {
            flex-shrink: 0;
        }

        .ocr-section {
            flex: 1;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .ocr-section h5 {
            margin-top: 0;
            color: #555;
        }

        .ocr-section pre {
            background: white;
            padding: 15px;
            border-radius: 5px;
            max-height: 500px;
            overflow: auto;
            font-size: 13px;
             line-height:1.4;
        }

        .flags-section {
            background: #f8f9fa;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .flags-section.success {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .flag-item {
            padding: 10px;
            margin: 8px 0;
            background: white;
            border-radius: 3px;
            border-left: 3px solid #6c757d;
        }

        .flag-critical {
            border-left-color: #dc3545;
            color: #721c24;
        }

        .flag-warning {
            border-left-color: #ffc107;
            color: #856404;
        }

        .decision-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 2px solid #007bff;
        }
    </style>
</head>
<body>


     <!--APPLICANT DETAILs-->
<div class="header-section">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2>Application Verification</h2>
            <p><strong>Applicant:</strong> <?= htmlspecialchars($applicant['app_name']) ?></p>
            <p><strong>Category:</strong> <?= htmlspecialchars($applicant['cat_applied']) ?></p>
            <p><strong>Application ID:</strong> <?= $reg_seq ?></p>
        </div>
        <div>
            <a href="../applications/list.php?search=<?= $reg_seq ?>&category=<?= $applicant['cat_applied'] ?>" class="btn btn-secondary" 
               style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">
                ← Back to Applicants List
            </a>
        </div>
    </div>
</div>

<!-- MAIN LAYOUT -->
<div class="main-layout">

    <!-- LEFT: DOCUMENT LIST -->
    <div class="left-sidebar">
        <div class="doc-list">
            <h3>📄 Documents</h3>
            <?php foreach ($allDocs as $doc): ?>
                <a href="review.php?reg_seq=<?= $reg_seq ?>&document_id=<?= $doc['document_id'] ?>"
                   class="<?= ($selectedDocId == $doc['document_id']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($doc['document_type']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MIDDLE: OCR + VALIDATION -->
    <div class="middle-panel">

        <!-- OCR DATA -->
        <div class="ocr-section">
            <h4>📝 OCR Extracted Data</h4>

            <?php
            $ocr = json_decode($selectedDoc['extracted_fields'] ?? '{}', true) ?? [];
            ?>

            <?php if (!empty($ocr)): ?>
                <pre><?= htmlspecialchars(json_encode($ocr, JSON_PRETTY_PRINT)) ?></pre>
            <?php else: ?>
                <p style="color:#dc3545;">⚠️ OCR data not available</p>
            <?php endif; ?>
        </div>

        <!-- VALIDATION REPORT -->
        <div class="flags-section">
            <h4>Validation Report</h4>

            <?php if (empty($allFlags)): ?>
                <p style="color:green;font-weight:bold;">✓ All validation checks passed</p>
            <?php else: ?>
                <?php foreach ($allFlags as $flag): ?>
                    <div class="flag-item flag-<?= strtolower($flag['type']) ?>">
                        <?= $flag['type'] === 'CRITICAL' ? '🔴' : '⚠️' ?>
                        <?= htmlspecialchars($flag['message']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>

    </div>

    <!-- RIGHT: DOCUMENT VIEWER -->
    <div class="right-viewer">

        <?php if ($selectedDoc): ?>

            <div class="viewer-box">
                <h4><?= htmlspecialchars($selectedDoc['document_type']) ?></h4>

                <?php if (preg_match('/\.pdf$/i', $selectedDoc['file_path'])): ?>

                    <iframe src="/ug_doc_verification/<?= htmlspecialchars($selectedDoc['file_path']) ?>"
                            style="width:100%;height:700px;border:1px solid #ddd;">
                    </iframe>

                <?php else: ?>

                    <img src="/ug_doc_verification/<?= htmlspecialchars($selectedDoc['file_path']) ?>"
                         style="width:100%;max-height:700px;border:1px solid #ddd;border-radius:5px;">

                <?php endif; ?>

            </div>

        <?php else: ?>

            <div class="viewer-box">
                <p style="text-align:center;color:#6c757d;padding:50px;">
                    ← Select a document from the list
                </p>
            </div>

        <?php endif; ?>

    </div>

</div>
<!-- STAFF ACTION -->
<div class="decision-form">
    <?php if ($userRole === 'ADMIN'): ?>
        <!-- ADMIN: Approve/Reject -->
        <h3>Admin Decision</h3>
        <?php if ($applicant['verification_status'] === 'VERIFIED'): ?>
            <p style="color: green;">✓ Verified by staff</p>
        <?php endif; ?>
        
        <form method="post" action="submit_decision.php">
            <input type="hidden" name="reg_seq" value="<?= $reg_seq ?>">
            <input type="hidden" name="action" value="admin_decision">

            <label>
                <input type="radio" name="decision" value="APPROVED" required>
                <strong>Approve Application</strong>
            </label><br><br>

            <label>
                <input type="radio" name="decision" value="REJECTED">
                <strong>Reject Application</strong>
            </label><br><br>

            <label><strong>Admin Remarks:</strong></label><br>
            <textarea name="remarks" rows="4"
                      style="width: 100%; max-width: 600px;"
                      placeholder="Required if rejecting"></textarea><br><br>

            <button type="submit" class="btn btn-primary"
                    style="font-size: 16px; padding: 10px 20px; background: #28a745; border: none; color: white; border-radius: 5px; cursor: pointer;">
                Submit Final Decision
            </button>
        </form>
    
    <?php else: ?>
        <!-- VERIFIER: Mark as Verified -->
        <h3>Verifier Action</h3>
        
        <?php if ($applicant['verification_status'] === 'VERIFIED'): ?>
            <p style="color: green; font-weight: bold;">✓ This application has been marked as VERIFIED</p>
        <?php else: ?>
            <form method="post" action="submit_decision.php">
                <input type="hidden" name="reg_seq" value="<?= $reg_seq ?>">
                <input type="hidden" name="action" value="mark_verified">

                <p>After reviewing all documents, OCR data, and validation flags:</p>

                <button type="submit" class="btn btn-success"
                        style="font-size: 16px; padding: 12px 30px; background: #007bff; border: none; color: white; border-radius: 5px; cursor: pointer;">
                    ✓ Mark as Verified
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>


</body>
</html>
