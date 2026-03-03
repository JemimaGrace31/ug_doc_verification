<?php
session_start();
require_once "../config/db.php";   

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare(
        "SELECT staff_id, username, password_hash, role, status
         FROM staff_users
         WHERE email = :email AND status = 'ACTIVE'"
    );
    $stmt->execute(['email' => $email]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staff && password_verify($password, $staff['password_hash'])) {
        $_SESSION['staff_id'] = $staff['staff_id'];
        $_SESSION['username'] = $staff['username'];
        $_SESSION['role'] = $staff['role'];

        header("Location: ../dashboard/dashboard.php");
        exit;
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Login</title>
    <link rel="stylesheet" href="/ug_doc_verification/assets/css/style.css">
</head>
<body>

<header>
    <h2>UG Document Verification System</h2>
</header>

<div class="login-wrapper">

    <div class="info-card login-card">

        <h3>Staff Login</h3>

        <?php if ($error): ?>
            <p style="color:red;text-align:center;">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form method="post">

            <label>Email</label>
            <input type="email" name="email" required>

            <br><br>

            <label>Password</label>
            <input type="password" name="password" required>

            <br><br>

            <button type="submit" class="btn btn-primary" style="width:100%;">
                Login
            </button>

        </form>

    </div>

</div>

</body>
</html>
