<?php
session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db.php';

// Get current user role and ID 
$userRole = $_SESSION['role'] ?? 'VERIFIER';
$userId = $_SESSION['staff_id'] ?? null;

// Search & Category Filter 
$search   = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

// JOIN category table + derive application status 
$sql = "
    SELECT 
        r.reg_seq,
        r.app_name,
        c.cat_name,
        r.cat_applied,
        av.verification_status,
        av.admin_decision,
        COUNT(d.document_id) as doc_count,
        COUNT(o.ocr_id) as ocr_count

    FROM registration r

    JOIN applicant_category c
        ON r.cat_applied = c.cat_code

    LEFT JOIN application_verification av
        ON av.reg_seq = r.reg_seq

    LEFT JOIN uploaded_documents d
        ON d.reg_seq = r.reg_seq

    LEFT JOIN ocr_extracted_data o
        ON o.document_id = d.document_id

    WHERE
        (r.reg_seq::TEXT ILIKE :search
         OR r.app_name ILIKE :search)
";

// Apply category filter if selected 
$params = ['search' => '%' . $search . '%'];

if (!empty($category)) {
    $sql .= " AND c.cat_code = :category";
    $params['category'] = $category;
}

// Only show applications from their assigned categories 
if ($userRole !== 'ADMIN') {
    $sql .= " AND EXISTS (
        SELECT 1 FROM category_verifier cv 
        WHERE cv.cat_code = r.cat_applied AND cv.staff_id = :staff_id
    )";
    $params['staff_id'] = $userId;
}

$sql .= "
    GROUP BY r.reg_seq, r.app_name, c.cat_name, r.cat_applied, av.verification_status, av.admin_decision
    ORDER BY r.reg_seq DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories by radio buttons 
$catStmt = $conn->query("
    SELECT cat_code, cat_name
    FROM applicant_category
    ORDER BY cat_name
");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Applications List</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
</head>
<body>

<h2>UG Applications</h2>

<!-- Search -->
<form method="get" style="margin-bottom:15px;">
    <input type="text"
           name="search"
           placeholder="Search by Application ID or Name"
           value="<?= htmlspecialchars($search) ?>"
           style="padding:8px; width:260px;">

    <button type="submit" class="btn btn-primary">Search</button>

    <?php if ($category): ?>
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
    <?php endif; ?>
</form>

<!--  Category Radio Buttons -->
<form method="get" style="margin-bottom:20px;">
    <strong>Category:</strong>

    <?php foreach ($categories as $cat): ?>

    <?php
       
        if ($cat['cat_code'] == 104) {
            continue;
        }
    ?>

    <label style="margin-right:15px;">
        <input type="radio"
               name="category"
               value="<?= $cat['cat_code'] ?>"
               <?= ($category == $cat['cat_code']) ? 'checked' : '' ?>
               onchange="this.form.submit()">
        <?= htmlspecialchars($cat['cat_name']) ?>
    </label>

<?php endforeach; ?>


    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

    <a href="list.php" class="btn btn-secondary" style="margin-left:10px;">
        Clear
    </a>
</form>

<!-- Applications Table -->
<table>
    <tr>
        <th>Application ID</th>
        <th>Applicant Name</th>
        <th>Category</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

<?php if (!empty($applications)): ?>
    <?php foreach ($applications as $app): ?>
        <tr>
            <td><?= htmlspecialchars($app['reg_seq']) ?></td>
            <td><?= htmlspecialchars($app['app_name']) ?></td>
            <td><?= htmlspecialchars($app['cat_name']) ?></td>

            <td>
                <?php 
                $status = 'PENDING';
                if ($app['admin_decision'] === 'APPROVED') {
                    $status = 'APPROVED';
                } elseif ($app['admin_decision'] === 'REJECTED') {
                    $status = 'REJECTED';
                } elseif ($app['verification_status'] === 'VERIFIED') {
                    $status = 'VERIFIED';
                } elseif ($app['verification_status'] === 'IN_PROGRESS') {
                    $status = 'IN_PROGRESS';
                }
                ?>
                <?php if ($status === 'APPROVED'): ?>
                    <span class="badge badge-approved">APPROVED</span>
                <?php elseif ($status === 'REJECTED'): ?>
                    <span class="badge badge-rejected">REJECTED</span>
                <?php elseif ($status === 'VERIFIED'): ?>
                    <span class="badge badge-pending">VERIFIED</span>
                <?php elseif ($status === 'IN_PROGRESS'): ?>
                    <span class="badge badge-warning">IN PROGRESS</span>
                <?php else: ?>
                    <span class="badge badge-pending">PENDING</span>
                <?php endif; ?>
            </td>

            <td>
                <?php if ($app['doc_count'] > 0 && $app['ocr_count'] == $app['doc_count']): ?>
                    <a href="../verification/review.php?reg_seq=<?= $app['reg_seq'] ?>"
                        class="btn btn-primary">
                        Review
                    </a>
                <?php else: ?>
                    <a href="../documents/list.php?reg_seq=<?= $app['reg_seq'] ?>"
                        class="btn btn-secondary">
                        View Documents
                    </a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="5">No applications found</td>
    </tr>
<?php endif; ?>

</table>

<br>
<a href="../dashboard/dashboard.php" class="btn btn-secondary">
    Back to Dashboard
</a>

</body>
</html>
