<?php

require_once 'ocr/extractors/MarksheetExtractor.php';

// Sample CBSE tabular format text
$sampleText = "
CENTRAL BOARD OF SECONDARY EDUCATION
SENIOR SCHOOL CERTIFICATE EXAMINATION 2024

CANDIDATE NAME: RAJESH KUMAR
ROLL NO: 1234567

SUB CODE  SUB NAME        THEORY  Prac./IA  MARKS  GRADE
041       MATHEMATICS     033     020       053    C1
042       PHYSICS         029     030       059    C1
043       CHEMISTRY       026     030       056    C1
044       ENGLISH         045     000       045    C2
049       COMPUTER SCI    035     015       050    C1

RESULT: PASS
TOTAL MARKS: 263
";

echo "=== Testing PCM Extraction ===\n\n";
echo "Input Text:\n";
echo $sampleText . "\n\n";

$extracted = MarksheetExtractor::extract($sampleText);

echo "=== Extracted Fields ===\n";
print_r($extracted);

echo "\n=== PCM Validation ===\n";
if (!empty($extracted['pcm_marks'])) {
    echo "Physics: " . ($extracted['pcm_marks']['physics'] ?? 'NOT FOUND') . "\n";
    echo "Chemistry: " . ($extracted['pcm_marks']['chemistry'] ?? 'NOT FOUND') . "\n";
    echo "Mathematics: " . ($extracted['pcm_marks']['mathematics'] ?? 'NOT FOUND') . "\n";
    echo "PCM Total: " . ($extracted['pcm_total'] ?? 'NOT CALCULATED') . "\n";
    echo "PCM Percentage: " . ($extracted['pcm_percentage'] ?? 'NOT CALCULATED') . "%\n";
} else {
    echo "❌ PCM marks not extracted!\n";
}
