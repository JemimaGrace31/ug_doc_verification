<?php
session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

if ($_SESSION['role'] !== 'ADMIN') {
    die("Access denied");
}

// Handle assignment update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catCode = $_POST['cat_code'];
    $staffId = $_POST['staff_id'];
    
    $stmt = $conn->prepare("
        INSERT INTO category_verifier (cat_code, staff_id) 
        VALUES (:cat, :staff)
        ON CONFLICT (cat_code) 
        DO UPDATE SET staff_id = :staff
    ");
    $stmt->execute(['cat' => $catCode, 'staff' => $staffId]);
    
    header("Location: manage_verifiers.php?success=1");
    exit;
}

// Fetch categories with current assignments
$stmt = $conn->query("
    SELECT c.cat_code, c.cat_name, cv.staff_id, s.username
    FROM applicant_category c
    LEFT JOIN category_verifier cv ON cv.cat_code = c.cat_code
    LEFT JOIN staff_users s ON s.staff_id = cv.staff_id
    ORDER BY c.cat_code
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all verifiers
$stmt = $conn->query("
    SELECT staff_id, username, email 
    FROM staff_users 
    WHERE role = 'VERIFIER' AND status = 'ACTIVE'
    ORDER BY username
");
$verifiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Verifiers</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
        select { padding: 8px; width: 200px; }
    </style>
</head>
<body>

<header>
    <h2>Manage Verifiers</h2>
    <a href="add_verifier.php" class="btn btn-primary">Add New Verifier</a>
    <a href="../dashboard/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</header>

<div class="container">

    <?php if (isset($_GET['success'])): ?>
        <p style="color: green; font-weight: bold;">✓ Assignment updated successfully</p>
    <?php endif; ?>

    <h3>Category Verifier Assignments</h3>

    <table>
        <tr>
            <th>Category Code</th>
            <th>Category Name</th>
            <th>Current Verifier</th>
            <th>Assign/Reassign</th>
        </tr>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= htmlspecialchars($cat['cat_code']) ?></td>
                <td><?= htmlspecialchars($cat['cat_name']) ?></td>
                <td>
                    <?php if ($cat['username']): ?>
                        <strong><?= htmlspecialchars($cat['username']) ?></strong> (ID: <?= $cat['staff_id'] ?>)
                    <?php else: ?>
                        <span style="color: #999;">Not assigned</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="cat_code" value="<?= $cat['cat_code'] ?>">
                        <select name="staff_id" required>
                            <option value="">-- Select Verifier --</option>
                            <?php foreach ($verifiers as $v): ?>
                                <option value="<?= $v['staff_id'] ?>" 
                                    <?= ($v['staff_id'] == $cat['staff_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['username']) ?> (<?= htmlspecialchars($v['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Assign</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

</div>

</body>
</html>
