<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_os.php';
require_once __DIR__ . '/../rules/nri_rules.php';
require_once __DIR__ . '/../rules/ciwgc_rules.php';
require_once __DIR__ . '/../rules/foreign_rules.php';
require_once __DIR__ . '/../rules/academic_rules.php';


if (!isset($_GET['reg_seq'])) {
    die("Invalid navigation: missing reg_seq");
}

$reg_seq = (int) $_GET['reg_seq'];
echo "REG_SEQ: " . $reg_seq . "<br>";
$selectedDocId = isset($_GET['document_id']) ? (int)$_GET['document_id'] : null;

// Get current user role and ID
$userRole = $_SESSION['role'] ?? 'VERIFIER';
$userId = $_SESSION['staff_id'] ?? null;

//FETCH APPLICANT & VERIFICATION STATUS
// 🔥 Try NRI DB first
$stmt = $conn->prepare("
    SELECT r.app_name, r.cat_applied, 
           av.verification_status, av.assigned_verifier, av.verifier_decision, av.admin_decision
    FROM registration r
    LEFT JOIN application_verification av ON av.reg_seq = r.reg_seq
    WHERE r.reg_seq = :seq
");
$stmt->execute(['seq' => $reg_seq]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

$source = 'NRI';

// 🔥 If NOT found in NRI → try OS
if (!$applicant || empty($applicant['app_name'])) {
    echo "INSIDE OS BLOCK<br>";

    $stmt = $osConn->prepare("
        SELECT app_name
        FROM registration
        WHERE reg_seq = :seq
    ");
    $stmt->execute(['seq' => $reg_seq]);
    $osApplicant = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($osApplicant);

    if ($osApplicant) {

        $applicant = [
            'app_name' => $osApplicant['app_name'],
            'cat_applied' => 106, // ✅ OS CATEGORY
            'verification_status' => 'PENDING',
            'assigned_verifier' => null,
            'verifier_decision' => null,
            'admin_decision' => null
        ];

        $source = 'OS';

        // 🔥 Ensure entry exists in verification table
        $stmt = $conn->prepare("
            INSERT INTO application_verification (reg_seq)
            VALUES (:seq)
            ON CONFLICT (reg_seq) DO NOTHING
        ");
        $stmt->execute(['seq' => $reg_seq]);

    } else {
        die("Applicant not found in both databases");
    }
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

/* STORE FLAGS IN DATABASE */

foreach ($allFlags as $flag) {

    $severity = ($flag['type'] === 'CRITICAL') ? 'HIGH' : 'MEDIUM';

    $stmt = $conn->prepare("
        INSERT INTO verification_flags (reg_seq, flag_type, severity, description)
        SELECT :seq, 'RULE_ENGINE', :severity, :desc
        WHERE NOT EXISTS (
            SELECT 1 FROM verification_flags
            WHERE reg_seq = :seq
            AND description = :desc
        )
    ");

    $stmt->execute([
        'seq' => $reg_seq,
        'severity' => $severity,
        'desc' => $flag['message']
    ]);
}

/* FETCH FLAGS FROM DATABASE */
$stmt = $conn->prepare("
SELECT *
FROM verification_flags
WHERE reg_seq = :seq
AND verifier_status != 'RESOLVED'
ORDER BY severity DESC
");

$stmt->execute(['seq'=>$reg_seq]);
$dbFlags = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
        body{
            font-family: Arial, sans-serif;
            margin:0;
            padding:20px;
            background:#f4f6fb;
        }

        /* Header */
        .header-section{
            background:white;
            padding:20px;
            border-radius:10px;
            margin-bottom:20px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }

        /* Main layout */
        .main-layout{
            display:grid;
            grid-template-columns:240px 450px 1fr;
            gap:20px;
            margin-bottom:30px;
        }

        /* Document sidebar */
        .left-sidebar{
            position:sticky;
            top:20px;
        }

        .doc-list{
            background:white;
            border-radius:10px;
            padding:15px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }

        .doc-list h3{
            margin-top:0;
            margin-bottom:12px;
        }

        .doc-list a{
            display:block;
            padding:10px;
            margin-bottom:6px;
            text-decoration:none;
            border-radius:6px;
            color:#333;
            font-size:14px;
        }

        .doc-list a:hover{
            background:#eef3ff;
        }

        .doc-list a.active{
            background:#007bff;
            color:white;
        }

        /* OCR panel */
        .middle-panel{
            display:flex;
            flex-direction:column;
            gap:20px;
        }

        .ocr-section{
            background:white;
            border-radius:10px;
            padding:15px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }

        .ocr-section h4{
            margin-top:0;
        }

        .ocr-section pre{
            background:#1e1e1e;
            color:#00ff9d;
            padding:12px;
            border-radius:6px;
            font-size:12px;
            max-height:350px;
            overflow:auto;
        }

        /* Validation flags */
        .flags-section{
            background:white;
            border-radius:10px;
            padding:15px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }

        .flag-item{
            padding:10px;
            margin:8px 0;
            border-radius:6px;
            background:#f8f9fa;
        }

        /* Document viewer */
        .viewer-box{
            background:white;
            border-radius:10px;
            padding:15px;
            box-shadow:0 2px 10px rgba(0,0,0,0.08);
        }

        .viewer-box h4{
            margin-top:0;
        }

        .viewer-box iframe{
            width:100%;
            height:780px;
            border:none;
        }

        .viewer-box img{
            width:100%;
            max-height:780px;
            object-fit:contain;
        }

        /* Decision panel */
        .decision-form{
            background:white;
            border-radius:10px;
            padding:20px;
            border-left:5px solid #007bff;
            box-shadow:0 2px 12px rgba(0,0,0,0.08);
        }

        .decision-form h3{
            margin-top:0;
        }

        .decision-form button{
            background:#007bff;
            color:white;
            padding:10px 25px;
            border:none;
            border-radius:6px;
            font-size:15px;
            cursor:pointer;
        }

        .decision-form button:hover{
            background:#0056b3;
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
            
<?php if (empty($dbFlags)): ?>
    <p style="color:green;font-weight:bold;">✓ All validation checks passed</p>
<?php else: ?>

<?php foreach ($dbFlags as $flag): ?>

<div class="flag-item">

<strong>
<?= $flag['severity'] === 'HIGH' ? '🔴' : '⚠️' ?>
<?= htmlspecialchars($flag['description']) ?>
</strong>

<form method="post" action="update_flag.php" style="margin-top:6px;">

<input type="hidden" name="flag_id" value="<?= $flag['flag_id'] ?>">

<select name="status">
<option value="OPEN">Open</option>
<option value="RESOLVED">Resolved</option>
<option value="IGNORED">Ignored</option>
</select>

<input type="text"
name="remark"
placeholder="Verifier remark"
style="width:200px">

<button type="submit">Update</button>

</form>

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
