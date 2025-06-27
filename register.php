<?php
include('config.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

$name = $email = $password = $shop_name = "";
$name_err = $email_err = $password_err = "";
$registration_msg = "";

// Validation functions
function isValidName($name) {
    return preg_match('/^[a-zA-Z_\- ]+$/', $name);
}
function isValidEmail($email) {
    return preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email);
}
function isValidPassword($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password);
}
function sendVerificationEmail($email) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'nasiryt.827@gmail.com'; // change
        $mail->Password = 'ddgc zhft gohg bjmz';    // change
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('nasiryt.827@gmail.com', 'POS System');
        $mail->addAddress($email);

        $verifyLink = " http://localhost/spring25/pos-rehman/verify.php?email=$email&role=owner";
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email';
        $mail->Body = "Click this link to verify your email: <a href='$verifyLink'>$verifyLink</a>";

        $mail->send();
    } catch (Exception $e) {
        // log if needed
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate name
    if (empty(trim($_POST["name"])) || !isValidName($_POST["name"])) {
        $name_err = "Only letters, underscores and dashes allowed.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate email
    if (empty(trim($_POST["email"])) || !isValidEmail($_POST["email"])) {
        $email_err = "Enter a valid @gmail.com email.";
    } else {
        $email = trim($_POST["email"]);
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $email_err = "Email is already taken.";
        }
        mysqli_stmt_close($stmt);
    }

    // Validate password
    if (empty(trim($_POST["password"])) || !isValidPassword($_POST["password"])) {
        $password_err = "Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char.";
    } else {
        $password = trim($_POST["password"]);
    }

    $shop_name = trim($_POST["shop_name"]) ?: null;

    if (empty($name_err) && empty($email_err) && empty($password_err)) {
        $sql = "INSERT INTO users (name, email, password, shop_name, status) VALUES (?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed_password, $shop_name);

        if (mysqli_stmt_execute($stmt)) {
            sendVerificationEmail($email);
            $registration_msg = '<div class="alert alert-success">Registered! Check your email for verification link.</div>';
        } else {
            $registration_msg = '<div class="alert alert-danger">Something went wrong.</div>';
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        body { background-color: #e6effc; font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .container { padding: 2rem 1rem; max-width: 100%; }
        .card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); max-width: 500px; margin: 0 auto; padding: 1rem; background-color: #fff; }
        .card-body { padding: 1.5rem; }
        .card h3 { font-size: clamp(1.4rem, 4vw, 1.8rem); color: #333; margin-bottom: 1.5rem; text-align: center; }
        .form-label { font-size: clamp(0.9rem, 2.5vw, 1rem); color: #333; }
        .input-group-text { background-color: #f8f9fa; border-color: #ddd; }
        .input-group-text i { color: #007bff; font-size: 1rem; }
        .form-control { font-size: clamp(0.875rem, 2.5vw, 1rem); border-radius: 5px; }
        .form-control:focus { border-color: #007bff; box-shadow: 0 0 5px rgba(0, 123, 255, 0.3); }
        .invalid-feedback { font-size: 0.85rem; }
        .btn-primary { background-color: #007bff; border: none; padding: 0.75rem; font-size: clamp(0.9rem, 2.5vw, 1rem); display: flex; align-items: center; justify-content: center; transition: background-color 0.3s; width: 100%; }
        .btn-primary i { margin-right: 0.5rem; }
        .btn-primary:hover { background-color: #0056b3; }
        .login-link { font-size: 0.95rem; color: #333; text-align: center; }
        .login-link a { color: #007bff; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        @media (max-width: 576px) {
            .card-body { padding: 1rem; }
            .card h3 { font-size: 1.25rem; }
            .form-label { font-size: 0.85rem; }
            .input-group-text i { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3>Register</h3>
            <?php echo $registration_msg; ?>
            <form method="post" novalidate>
                <div class="mb-3 position-relative">
                    <label for="name" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" id="name" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter your full name" />
                        <div class="invalid-feedback"><?php echo $name_err; ?></div>
                    </div>
                </div>
                <div class="mb-3 position-relative">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" />
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                </div>
                <div class="mb-3 position-relative">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your password" />
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                </div>
                <div class="mb-3 position-relative">
                    <label for="shop_name" class="form-label">Shop Name (optional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-store"></i></span>
                        <input type="text" id="shop_name" name="shop_name" class="form-control" value="<?php echo htmlspecialchars($shop_name); ?>" placeholder="Enter your shop name if any" />
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</button>
                </div>
            </form>
            <p class="mt-3 login-link">
                Already have an account? <a href="login.php">Login now</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
