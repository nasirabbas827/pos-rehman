<?php
include 'config.php';

if (isset($_GET['email']) && isset($_GET['role'])) {
    $email = $_GET['email'];
    $role = $_GET['role'];

    // Sanitize role
    if (!in_array($role, ['owner', 'manager'])) {
        die('Invalid role.');
    }

    $table = ($role === 'manager') ? 'managers' : 'users';

    $sql = "UPDATE `$table` SET `status` = 'approved' WHERE `email` = ? AND `status` = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo "<h2>Email verified successfully!</h2><p>Your account is now active. <a href='login.php'>Click here to login</a>.</p>";
    } else {
        echo "<h2>Verification failed</h2><p>This email is already verified or invalid.</p>";
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
} else {
    echo "<h2>Invalid Request</h2><p>Missing email or role parameter.</p>";
}
?>
