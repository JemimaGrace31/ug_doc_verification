<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

// LOAD EXTRACTORS

require_once __DIR__ . '/extractors/PassportExtractor.php';
require_once __DIR__ . '/extractors/MarksheetExtractor.php';
require_once __DIR__ . '/extractors/CandidateIdentityExtractor.php';
require_once __DIR__ . '/extractors/VisaExtractor.php';
require_once __DIR__ . '/extractors/NriCertificateExtractor.php';
require_once __DIR__ . '/extractors/EmploymentCertificateExtractor.php';
require_once __DIR__ . '/extractors/BankStatementExtractor.php';
require_once __DIR__ . '/extractors/TransferCertificateExtractor.php';
require_once __DIR__ . '/extractors/ForeignCardExtractor.php';

// LOGGER

function writeLog(string $message): void
{
    $logFile = __DIR__ . '/../logs/system.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

//DOCUMENT TYPE DETECTION

function detectDocumentType(string $text): string
{
    $text = strtoupper($text);

    if (preg_match('/ACCOUNT\s*STATEMENT|NRE|NRO|BANK\s*STATEMENT/', $text))
        return 'BANK STATEMENT';

    if (preg_match('/TRANSFER\s*CERTIFICATE|SCHOOL\s*LEAVING\s*CERTIFICATE|MIGRATION\s*CERTIFICATE|\bT\.?C\.?\b/', $text))
        return 'SCHOOL TC';

    if (preg_match('/MARK\s*SHEET|MARKS STATEMENT|SECONDARY SCHOOL EXAMINATION|SENIOR SCHOOL CERTIFICATE|REPORT\s*CARD|ACADEMIC\s*YEAR/', $text))
        return 'MARKSHEET';

    if (preg_match('/OVERSEAS\s*CITIZEN\s*OF\s*INDIA|OCI\s*CARD|\bOCI\b/', $text))
        return 'OCI CARD';

    if (preg_match('/PERSON\s*OF\s*INDIAN\s*ORIGIN|PIO\s*CARD|\bPIO\b/', $text))
        return 'PIO CARD';

    // UNIVERSAL PASSPORT DETECTION (ICAO MRZ BASED)
    if (preg_match('/P<[A-Z]{3}/', $text)) 
        return 'PASSPORT';
    

    if (preg_match('/RESIDENCE\s*PERMIT|UNITED\s*ARAB\s*EMIRATES.*RESIDENCE/', $text))
        return 'VISA';

    if (preg_match('/VISA/', $text))
        return 'VISA';

    if (preg_match('/EMBAS|CONSUL|NRI\s*STATUS/', $text))
        return 'NRI CERTIFICATE';

    if (preg_match('/BIRTH\s*CERTIFICAT/', $text))
        return 'BIRTH CERTIFICATE';

    if (preg_match('/EMPLOYMENT\s*CERTIFICATE|TO\s*WHOM\s*IT\s*MAY\s*CONCERN.*EMPLOY|CERTIFY\s*THAT.*EMPLOY/', $text))
        return 'EMPLOYMENT CERTIFICATE';

    return 'UNKNOWN';
}

//VALIDATION

if (!isset($_GET['document_id'])) die("Document ID missing");

$document_id = (int) $_GET['document_id'];

$stmt = $conn->prepare("
    SELECT document_id, file_path, reg_seq
    FROM uploaded_documents
    WHERE document_id = :id
");
$stmt->execute(['id' => $document_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) die("Document not found");

$reg_seq = $document['reg_seq'];

// PREVENT DUPLICATE OCR

$check = $conn->prepare("SELECT 1 FROM ocr_extracted_data WHERE document_id = :id");
$check->execute(['id' => $document_id]);

if ($check->fetch()) {
    writeLog("OCR SKIPPED (DUPLICATE) | Doc ID: $document_id");
    die("OCR already completed");
}

//FILE PATH

$filePath = __DIR__ . '/../' . $document['file_path'];
$filePath = str_replace('\\', '/', $filePath);

if (!file_exists($filePath)) {
    writeLog("FILE NOT FOUND | Doc ID: $document_id");
    die("File not found");
}

//OCR CONFIG

$tesseract = 'C:/Program Files/Tesseract-OCR/tesseract.exe';
$tessdata  = 'C:/Program Files/Tesseract-OCR/tessdata';

$allExtractedData = [];
$allRawText = '';

//PDF HANDLING

if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf') {

    $imagePattern = sys_get_temp_dir() . "/ocr_{$document_id}_%02d.png";

    $magick = '"C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe"';
    $convertCmd = $magick . ' -density 300 "' . $filePath . '" ' .
                  '-auto-orient -colorspace Gray -contrast -sharpen 0x1 ' .
                  '"' . $imagePattern . '" 2>&1';

    shell_exec($convertCmd);

    $imageFiles = glob(sys_get_temp_dir() . "/ocr_{$document_id}_*.png");

    if (empty($imageFiles)) {
        writeLog("PDF CONVERSION FAILED | Doc ID: $document_id");
        die("PDF conversion failed");
    }

    foreach ($imageFiles as $img) {

        $ocrCommand = "\"$tesseract\" \"$img\" stdout --tessdata-dir \"$tessdata\" -l eng --oem 1 --psm 6 2>&1";
        $pageText = shell_exec($ocrCommand);

        $allRawText .= $pageText . "\n\n=== PAGE BREAK ===\n\n";

        $cleanText = strtoupper($pageText);
        $cleanText = str_replace("\r", "", $cleanText);
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        if (!empty(trim($cleanText))) {

            $detectedType = detectDocumentType($cleanText);
            writeLog("DETECTED TYPE: $detectedType | Doc ID: $document_id");

            $pageFields = [];

            switch ($detectedType) {
                case 'BIRTH CERTIFICATE':
                    $pageFields = CandidateIdentityExtractor::extract($cleanText);
                    break;
                case 'PASSPORT':
                    $pageFields = PassportExtractor::extract($cleanText);
                    break;
                case 'VISA':
                    $pageFields = VisaExtractor::extract($cleanText);
                    break;
                case 'OCI CARD':
                case 'PIO CARD':
                    $pageFields = ForeignCardExtractor::extract($cleanText);
                    break;
                case 'NRI CERTIFICATE':
                    $pageFields = NriCertificateExtractor::extract($cleanText);
                    break;
                case 'EMPLOYMENT CERTIFICATE':
                    $pageFields = EmploymentCertificateExtractor::extract($cleanText);
                    break;
                case 'BANK STATEMENT':
                    $pageFields = BankStatementExtractor::extract($cleanText);
                    break;
                case 'SCHOOL TC':
                    $pageFields = TransferCertificateExtractor::extract($cleanText);
                    break;
                case 'MARKSHEET':
                    $pageFields = MarksheetExtractor::extract($cleanText);
                    break;
            }

            if (!empty($pageFields)) {
                $allExtractedData[$detectedType][] = $pageFields;
            }
        }

        unlink($img);
    }
}
else {

    $ocrCommand = "\"$tesseract\" \"$filePath\" stdout --tessdata-dir \"$tessdata\" -l eng --oem 1 --psm 6 2>&1";
    $rawText = shell_exec($ocrCommand);
    $allRawText = $rawText;

    $cleanText = strtoupper($rawText);
    $cleanText = str_replace("\r", "", $cleanText);
    $cleanText = preg_replace('/\s+/', ' ', $cleanText);

    if (!empty(trim($cleanText))) {

        $detectedType = detectDocumentType($cleanText);
        writeLog("DETECTED TYPE: $detectedType | Doc ID: $document_id");

        $fields = [];

        switch ($detectedType) {
            case 'BIRTH CERTIFICATE':
                $fields = CandidateIdentityExtractor::extract($cleanText);
                break;
            case 'PASSPORT':
                $fields = PassportExtractor::extract($cleanText);
                break;
            case 'VISA':
                $fields = VisaExtractor::extract($cleanText);
                break;
            case 'OCI CARD':
            case 'PIO CARD':
                $fields = ForeignCardExtractor::extract($cleanText);
                break;
            case 'NRI CERTIFICATE':
                $fields = NriCertificateExtractor::extract($cleanText);
                break;
            case 'EMPLOYMENT CERTIFICATE':
                $fields = EmploymentCertificateExtractor::extract($cleanText);
                break;
            case 'BANK STATEMENT':
                $fields = BankStatementExtractor::extract($cleanText);
                break;
            case 'SCHOOL TC':
                $fields = TransferCertificateExtractor::extract($cleanText);
                break;
            case 'MARKSHEET':
                $fields = MarksheetExtractor::extract($cleanText);
                break;
        }

        if (!empty($fields)) {
            $allExtractedData[$detectedType] = $fields;
        }
    }
}

/* EMPTY CHECK */
if (empty($allExtractedData)) {
    writeLog("OCR FAILED EMPTY | Doc ID: $document_id");
    $allExtractedData = ['note' => 'No data extracted'];
}

/* CONFIDENCE SCORE */
$confidence = !isset($allExtractedData['note']) ? 90 : 60;

/* STORE RESULT */
$conn->prepare("
    INSERT INTO ocr_extracted_data
    (document_id, extracted_text, extracted_fields, confidence_score)
    VALUES (:id,:text,:fields,:score)
")->execute([
    'id'=>$document_id,
    'text'=>$allRawText,
    'fields'=>json_encode($allExtractedData),
    'score'=>$confidence
]);

/* UPDATE OCR STATUS */
$conn->prepare("
    UPDATE uploaded_documents
    SET ocr_status = 'OCR_COMPLETED'
    WHERE document_id = :id
")->execute([
    'id' => $document_id
]);

header("Location: ../verification/review.php?document_id=$document_id&reg_seq=$reg_seq");
exit;