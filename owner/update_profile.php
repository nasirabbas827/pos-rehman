<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$user_id = $_SESSION["id"];

// Fetch user info
$sql = "SELECT name, email, phone_number, shop_name FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $name, $email, $phone_number, $shop_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Validation functions (same as register.php)
function isValidName($name) {
    return preg_match('/^[a-zA-Z_\- ]+$/', $name);
}
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && str_ends_with($email, '@gmail.com');
}
function isValidPassword($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password);
}

// Handle form submission
$success_msg = $error_msg = "";
$name_err = $email_err = $phone_number_err = $password_err = $confirm_password_err = "";
$new_name = $new_email = $new_phone_number = $new_shop_name = $new_password = $confirm_password = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate name
    $new_name = trim($_POST['name']);
    if (empty($new_name) || !isValidName($new_name)) {
        $name_err = "Only letters, underscores, and dashes allowed.";
    }

    // Validate email
    $new_email = trim($_POST['email']);
    if (empty($new_email) || !isValidEmail($new_email)) {
        $email_err = "Please enter a valid email address ending with @gmail.com.";
    } else {
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $email_err = "Email is already taken.";
        }
        mysqli_stmt_close($stmt);
    }

    // Validate phone number
    $new_phone_number = trim($_POST['phone_number']);
    if (empty($new_phone_number)) {
        $phone_number_err = "Please enter your phone number.";
    }

    // Validate password (optional)
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    if (!empty($new_password)) {
        if (!isValidPassword($new_password)) {
            $password_err = "Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char.";
        } elseif ($new_password !== $confirm_password) {
            $confirm_password_err = "Passwords do not match.";
        }
    }

    $new_shop_name = trim($_POST['shop_name']) ?: null;

    if (empty($name_err) && empty($email_err) && empty($phone_number_err) && empty($password_err) && empty($confirm_password_err)) {
        if (!empty($new_password)) {
            // Update with password
            $sql = "UPDATE users SET name = ?, email = ?, phone_number = ?, shop_name = ?, password = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            mysqli_stmt_bind_param($stmt, "sssssi", $new_name, $new_email, $new_phone_number, $new_shop_name, $hashed_password, $user_id);
        } else {
            // Update without password
            $sql = "UPDATE users SET name = ?, email = ?, phone_number = ?, shop_name = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssss", $new_name, $new_email, $new_phone_number, $new_shop_name, $user_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Profile updated successfully.";
            $name = $new_name;
            $email = $new_email;
            $phone_number = $new_phone_number;
            $shop_name = $new_shop_name;
        } else {
            $error_msg = "Error updating profile. Please try again.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Update Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css" />
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            padding: 2rem 1rem;
            max-width: 100%;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 0 auto;
            padding: 1rem;
            background-color: #fff;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card h3 {
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-label {
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            color: #333;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-color: #ddd;
        }

        .input-group-text i {
            color: #007bff;
            font-size: 1rem;
        }

        .form-control {
            font-size: clamp(0.875rem, 2.5vw, 1rem);
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }

        .invalid-feedback {
            font-size: 0.85rem;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 0.75rem;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
            width: 100%;
        }

        .btn-primary i {
            margin-right: 0.5rem;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .alert {
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
            border-radius: 5px;
        }

        .password-meter-bar {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
        }

        .weak {
            background-color: #f00;
            width: 25%;
        }

        .medium {
            background-color: #ffc107;
            width: 50%;
        }

        .strong {
            background-color: #28a745;
            width: 100%;
        }

        .input-group-text.password-toggle {
            cursor: pointer;
        }

        .iti {
            width: 100%;
        }

        .iti__flag-container {
            border-radius: 5px 0 0 5px;
        }

        /* Hamburger button for mobile */
        .toggle-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            border: 1px solid #007bff;
            background-color: #fff;
            border-radius: 5px;
            padding: 0.5rem;
        }

        .toggle-btn i {
            font-size: 1.5rem;
            color: #007bff;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            body {
                margin-left: 0;
            }

            .container {
                padding: 1.5rem 1rem;
            }

            .toggle-btn {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .card h3 {
                font-size: 1.4rem;
            }

            .form-label {
                font-size: 0.875rem;
            }

            .form-control {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hamburger button for mobile -->
    <button class="toggle-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <i class="fas fa-bars"></i>
    </button>

    <?php include('sidebar.php'); ?>

    <div class="container">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3><i class="fas fa-user-edit me-2"></i>Update Profile</h3>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php elseif ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <form method="post" novalidate onsubmit="return validateForm()">
                    <div class="mb-3 position-relative">
                        <label for="name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" id="name" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter your full name" required />
                            <div class="invalid-feedback"><?php echo $name_err; ?></div>
                        </div>
                    </div>

                    <div class="mb-3 position-relative">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your @gmail.com email" required />
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                    </div>

                    <div class="mb-3 position-relative">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="tel" id="phone_number_input" class="form-control <?php echo (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone_number); ?>" placeholder="Enter your phone number" required />
                        <input type="hidden" name="phone_number" id="full_phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
                        <div class="invalid-feedback" id="phone-number-feedback"><?php echo $phone_number_err; ?></div>
                    </div>

                    <div class="mb-3 position-relative">
                        <label for="password" class="form-label">New Password (optional)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter new password" />
                            <span class="input-group-text password-toggle" onclick="togglePasswordVisibility('password')"><i class="fas fa-eye-slash" id="toggle-password"></i></span>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        </div>
                        <div class="password-meter-bar" id="password-strength-bar"></div>
                        <small class="form-text text-muted">Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char.</small>
                    </div>

                    <div class="mb-3 position-relative">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm new password" />
                            <span class="input-group-text password-toggle" onclick="togglePasswordVisibility('confirm_password')"><i class="fas fa-eye-slash" id="toggle-confirm_password"></i></span>
                            <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                        </div>
                    </div>

                    <div class="mb-3 position-relative">
                        <label for="shop_name" class="form-label">Shop Name (optional)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-store"></i></span>
                            <input type="text" id="shop_name" name="shop_name" class="form-control" value="<?php echo htmlspecialchars($shop_name ?: ''); ?>" placeholder="Enter your shop name if any" />
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput-jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>

    <script>
        // Password Visibility Toggle
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById('toggle-' + fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        // Password Strength Meter
        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const bar = document.getElementById('password-strength-bar');
            let score = 0;
            if (password.length >= 8) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            bar.className = 'password-meter-bar';
            if (password.length > 0) {
                if (score < 3) {
                    bar.classList.add('weak');
                } else if (score < 5) {
                    bar.classList.add('medium');
                } else {
                    bar.classList.add('strong');
                }
            }
        }

        document.getElementById('password').addEventListener('input', updatePasswordStrength);

        // Phone Number Initialization
        const phoneInputField = document.querySelector("#phone_number_input");
        const fullPhoneNumberField = document.querySelector("#full_phone_number");
        const phoneErrorFeedback = document.querySelector("#phone-number-feedback");

        const phoneInput = window.intlTelInput(phoneInputField, {
            preferredCountries: ["pk", "us", "gb", "ca", "au"],
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
        });

        // Set initial phone number value
        phoneInput.setNumber(<?php echo json_encode($phone_number); ?>);

        // Form Validation
        function validateForm() {
            const isValidPhone = phoneInput.isValidNumber();
            if (isValidPhone) {
                fullPhoneNumberField.value = phoneInput.getNumber(intlTelInputUtils.numberFormat.E164);
                phoneInputField.classList.remove('is-invalid');
                phoneErrorFeedback.textContent = '';
                return true;
            } else {
                phoneInputField.classList.add('is-invalid');
                phoneErrorFeedback.textContent = 'Please enter a valid phone number for the selected country.';
                return false;
            }
        }
    </script>
</body>
</html>