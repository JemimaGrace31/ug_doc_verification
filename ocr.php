<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

/* Validate document_id */
if (!isset($_GET['document_id'])) {
    die("Document ID missing");
}

$document_id = (int) $_GET['document_id'];

/* Fetch document from DB */
$stmt = $conn->prepare("
    SELECT document_id, document_type, file_path, reg_seq
    FROM uploaded_documents
    WHERE document_id = :id
");
$stmt->execute(['id' => $document_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die("Document not found in database");
}

/* Resolve file path */
$filePath = __DIR__ . '/../' . $document['file_path'];
$filePath = str_replace('\\', '/', $filePath);


if (!file_exists($filePath)) {
    die("File not found: " . $filePath);
}


/* run Tesseract OCR*/

$tesseractPath = 'C:/Program Files/Tesseract-OCR/tesseract.exe';

$tessdataDir   = 'C:/Program Files/Tesseract-OCR/tessdata';
$imagePath = $filePath;

$command = '"' . $tesseractPath . '" '
         . '"' . $imagePath . '" '
         . 'stdout '
         . '--tessdata-dir "' . $tessdataDir . '" '
         . '-l eng 2>&1';

$output = shell_exec($command);

if (!$output || trim($output) === '') {
    die("OCR failed: Tesseract could not locate tessdata or read image.");
}


/* Initialize extracted fields  */
$extracted_fields = [];

/* Normalize OCR text */
$cleanText = strtoupper($output);
$cleanText = str_replace("\r", "", $cleanText);

/* Passport Number */
if (preg_match('/\b[A-Z][0-9]{7,8}\b/', $cleanText, $m)) {
    $extracted_fields['passport_number'] = $m[0];
}

/* Nationality */
if (preg_match('/\bNIGERIAN\b/', $cleanText)) {
    $extracted_fields['nationality'] = 'NIGERIAN';
}

/* DOB & EXPIRY FROM MRZ */
if (preg_match('/\d{6}[MF]\d{6}/', $cleanText, $m)) {

    $mrz_dates = $m[0]; // YYMMDDFYYMMDD

    $dob_raw    = substr($mrz_dates, 0, 6);
    $expiry_raw = substr($mrz_dates, 7, 6);

    $extracted_fields['date_of_birth'] =
        '19' . substr($dob_raw, 0, 2) . '-' .
        substr($dob_raw, 2, 2) . '-' .
        substr($dob_raw, 4, 2);

    $extracted_fields['expiry_date'] =
        '20' . substr($expiry_raw, 0, 2) . '-' .
        substr($expiry_raw, 2, 2) . '-' .
        substr($expiry_raw, 4, 2);
}
/* MRZ LINE 1 EXTRACTIOn */

$mrz_line1 = null;

// Split OCR output into lines
$lines = preg_split('/\R/', $output);

foreach ($lines as $line) {
    if (strpos($line, 'P<') !== false) {
        $mrz_line1 = strtoupper($line);
        break;
    }
}

if ($mrz_line1) {
    // Remove spaces and line breaks
    $mrz_line1 = str_replace([' ', "\r", "\n"], '', $mrz_line1);
}

/* NAME EXTRACTION */

if ($mrz_line1 && preg_match('/P<\w{3}([^<]+)<<(.+)/', $mrz_line1, $m)) {


    $surname = $m[1]; // OMORUYI
    $given   = $m[2]; // SONIA<OSAHENRUMWENS<<<<<<<<K<<

    // Cut off filler <<<<
    $given = preg_split('/<{4,}/', $given)[0];

    // Replace < with space
    $surname = str_replace('<', ' ', $surname);
    $given   = str_replace('<', ' ', $given);

    $fullName = trim($surname . ' ' . $given);

    // Normalize spacing
    $fullName = preg_replace('/\s+/', ' ', $fullName);

    $extracted_fields['name'] = $fullName;
}

/* Confidence score */
$confidence_score = count($extracted_fields) >= 4 ? 95.0 : 75.0;

/* Prevent duplicate OCR for same document */
$checkStmt = $conn->prepare("
    SELECT ocr_id
    FROM ocr_extracted_data
    WHERE document_id = :document_id
");
$checkStmt->execute(['document_id' => $document_id]);

if ($checkStmt->fetch()) {
    die("OCR already completed for this document.");
}

/* Store OCR result */
$insertStmt = $conn->prepare("
    INSERT INTO ocr_extracted_data
    (
        document_id,
        extracted_text,
        extracted_fields,
        confidence_score
    )
    VALUES
    (
        :document_id,
        :extracted_text,
        :extracted_fields,
        :confidence_score
    )
");

$insertStmt->execute([
    'document_id'      => $document_id,
    'extracted_text'   => $output,  
    'extracted_fields' => json_encode($extracted_fields),
    'confidence_score' => $confidence_score
]);


?>

<!DOCTYPE html>
<html>
<head>
    <title>OCR Result</title>
    <link rel="stylesheet" href="../assets/css/style.css">

</head>
<body>

<h2>OCR Completed</h2>

<p><strong>Document Type:</strong> <?php echo htmlspecialchars($document['document_type']); ?></p>
<p><strong>Confidence Score:</strong> <?php echo $confidence_score; ?>%</p>

<h3>Extracted Fields</h3>
<pre><?php print_r($extracted_fields); ?></pre>

<h3>Raw OCR Text</h3>
<pre><?php echo htmlspecialchars($output); ?></pre>

<br>
<a href="../documents/list.php?reg_seq=<?php echo $document['reg_seq']; ?>">
    Back to Documents
</a>

</body>
</html>