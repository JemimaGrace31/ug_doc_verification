<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>ImageMagick Test</h2>";

$magick = '"C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe"';
$testPdf = 'C:/xampp/htdocs/ug_doc_verification/assets/uploads/2541919/bank.pdf';
$outputPng = sys_get_temp_dir() . '/test_output.png';

echo "<p><strong>Magick Path:</strong> $magick</p>";
echo "<p><strong>Test PDF:</strong> $testPdf</p>";
echo "<p><strong>Output PNG:</strong> $outputPng</p>";
echo "<p><strong>PDF Exists:</strong> " . (file_exists($testPdf) ? 'YES' : 'NO') . "</p>";

$cmd = $magick . ' -density 150 "' . $testPdf . '[0]" "' . $outputPng . '" 2>&1';
echo "<p><strong>Command:</strong> <code>$cmd</code></p>";

echo "<h3>Executing...</h3>";
$output = shell_exec($cmd);

echo "<p><strong>Shell Output:</strong></p>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<p><strong>PNG Created:</strong> " . (file_exists($outputPng) ? 'YES' : 'NO') . "</p>";

if (file_exists($outputPng)) {
    echo "<p style='color:green;'>✓ SUCCESS! ImageMagick is working.</p>";
    echo "<img src='data:image/png;base64," . base64_encode(file_get_contents($outputPng)) . "' style='max-width:400px;border:1px solid #ddd;'>";
    unlink($outputPng);
} else {
    echo "<p style='color:red;'>✗ FAILED! ImageMagick could not convert the PDF.</p>";
}
?>