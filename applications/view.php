<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

/* Validate reg_seq */
if (!isset($_GET['reg_seq'])) {
    die("Application ID not provided");
}

$reg_seq = (int) $_GET['reg_seq'];

/* Fetch application basic details */
$stmt = $conn->prepare("
    SELECT
        reg_seq,
        app_name,
        cat_applied
    FROM registration
    WHERE reg_seq = :reg_seq
");
$stmt->execute(['reg_seq' => $reg_seq]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    die("Application not found");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Application Details</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">

</head>

<body>

<h2>Application Details</h2>

<p><strong>Application ID:</strong> <?php echo htmlspecialchars($app['reg_seq']); ?></p>
<p><strong>Applicant Name:</strong> <?php echo htmlspecialchars($app['app_name']); ?></p>
<p><strong>Category:</strong> <?php echo htmlspecialchars($app['cat_applied']); ?></p>

<hr>

<h3>Verification Actions</h3>

<ul>
    <li>
        <a href="../documents/list.php?reg_seq=<?php echo $app['reg_seq']; ?>">
            View Uploaded Documents
        </a>
    </li>
</ul>

<br>

<a href="list.php"> Back to Applications List</a>

</body>
</html>
