<?php
session_start();
require_once __DIR__ . '/../auth/check_login.php';
require_once __DIR__ . '/../config/db.php';

if ($_SESSION['role'] !== 'ADMIN') {
    die("Access denied");
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT staff_id FROM staff_users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    
    if ($stmt->fetch()) {
        $error = "Email already exists";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO staff_users (username, email, password_hash, role, status) 
            VALUES (:username, :email, :password_hash, 'VERIFIER', 'ACTIVE')
        ");
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash
        ]);
        
        $success = "Verifier added successfully";
    }
}

// Fetch all staff users
$stmt = $conn->query("
    SELECT staff_id, username, email, role, status 
    FROM staff_users 
    ORDER BY role, username
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Verifier</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .form-box { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; max-width: 500px; margin-bottom: 30px; }
    </style>
</head>
<body>

<header>
    <h2>Add New Verifier</h2>
    <a href="manage_verifiers.php" class="btn btn-secondary">Back to Manage Verifiers</a>
</header>

<div class="container">

    <div class="form-box">
        <h3>Create New Verifier Account</h3>
        
        <?php if ($error): ?>
            <p style="color: red; font-weight: bold;">✗ <?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <p style="color: green; font-weight: bold;">✓ <?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" required>
            <br><br>
            
            <label>Email</label>
            <input type="email" name="email" required>
            <br><br>
            
            <label>Password</label>
            <input type="password" name="password" required minlength="6">
            <br><br>
            
            <button type="submit" class="btn btn-primary">Add Verifier</button>
        </form>
    </div>

    <h3>All Staff Users</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
        </tr>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['staff_id']) ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><strong><?= htmlspecialchars($user['role']) ?></strong></td>
                <td><?= htmlspecialchars($user['status']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

</div>

</body>
</html>
