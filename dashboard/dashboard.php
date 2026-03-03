<?php
session_start();
require_once "../auth/check_login.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
</head>
<body>

<header>
    <h2>Staff Dashboard</h2>
    <a href="../auth/logout.php" class="btn btn-secondary">Logout</a>
</header>

<div class="container">

    <div class="info-card">
        <h3>Welcome</h3>
        <p><strong>User:</strong> <?= htmlspecialchars($_SESSION['username']) ?></p>
        <p><strong>Role:</strong> <?= htmlspecialchars($_SESSION['role']) ?></p>
    </div>

    <div class="info-card">
        <h3>Actions</h3>
        <a href="../applications/list.php" class="btn btn-primary">
            View Applications
        </a>
        <?php if ($_SESSION['role'] === 'ADMIN'): ?>
            <br><br>
            <a href="../admin/manage_verifiers.php" class="btn btn-primary">
                Manage Verifiers
            </a>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
