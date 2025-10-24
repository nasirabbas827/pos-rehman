<?php
session_start();
include('config.php');

$email = $password = $role = "";
$email_err = $password_err = $role_err = $login_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 1. Validate role: ADD 'salesperson' to the valid roles ---
    $valid_roles = ['owner', 'manager', 'salesperson'];
    if (empty($_POST["role"]) || !in_array($_POST["role"], $valid_roles)) {
        $role_err = "Please select a valid role.";
    } else {
        $role = $_POST["role"];
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // --- 2. If no errors, check credentials ---
    if (empty($email_err) && empty($password_err) && empty($role_err)) {
        $sql = "";
        $redirect_url = "";
        $is_owner = false;

        if ($role === 'owner') {
            $sql = "SELECT id, email, password, status FROM users WHERE email = ?";
            $redirect_url = "owner/owner_dashboard.php";
            $is_owner = true;
        } else if ($role === 'manager') {
            $sql = "SELECT id, email, password FROM managers WHERE email = ?";
            $redirect_url = "manager/manager_dashboard.php";
        } else if ($role === 'salesperson') { // ADDED SALESPERSON LOGIC
            $sql = "SELECT id, email, password FROM salespersons WHERE email = ?";
            $redirect_url = "salesperson/salesperson_dashboard.php";
        }

        if (!empty($sql)) {
            $stmt = mysqli_prepare($conn, $sql);
            
            // Handle binding for owner (which includes 'status') vs. others
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = $email;
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) == 1) {
                if ($is_owner) {
                    mysqli_stmt_bind_result($stmt, $id, $email, $hashed_password, $status);
                } else {
                    // Manager and Salesperson tables only have id, email, password
                    mysqli_stmt_bind_result($stmt, $id, $email, $hashed_password);
                }

                if (mysqli_stmt_fetch($stmt)) {
                    if ($role === 'owner' && $status !== 'approved') {
                        $login_msg = "Your account is not approved yet. Current status: $status.";
                    } elseif (password_verify($password, $hashed_password)) {
                        // Success! Store session variables and redirect
                        $_SESSION["id"] = $id;
                        $_SESSION["email"] = $email;
                        $_SESSION["role"] = $role;
                        header("Location: " . $redirect_url);
                        exit();
                    } else {
                        $password_err = "The password you entered is incorrect.";
                    }
                }
            } else {
                $email_err = "No account found with that email for the selected role.";
            }
            mysqli_stmt_close($stmt);
        } else {
            // Should not happen if validation is correct
            $role_err = "Invalid role selected.";
        }
    }


    // Close connection outside the successful login block
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>User Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #e6effc; /* Consistent with index.php and register.php */
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Container styling */
        .container {
            padding: 3rem 1rem;
        }

        /* Card styling */
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 450px; /* Slightly narrower than register.php for compact login form */
            margin: 0 auto;
        }

        .card-body {
            padding: 2rem;
        }

        .card h3 {
            font-size: clamp(1.5rem, 4vw, 1.8rem); /* Responsive heading */
            color: #333;
            margin-bottom: 1.5rem;
        }

        /* Form input styling */
        .form-label {
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            color: #333;
        }

        .form-select, .form-control {
            font-size: clamp(0.875rem, 2.5vw, 1rem);
            border-radius: 5px;
        }

        .form-select:focus, .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-color: #ddd;
        }

        .input-group-text i {
            color: #007bff;
            font-size: 1.1rem;
        }

        .invalid-feedback {
            font-size: 0.85rem;
        }

        /* Submit button styling */
        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 0.75rem;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }

        .btn-primary i {
            margin-right: 0.5rem;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        /* Link styling */
        .text-center a {
            color: #007bff;
            text-decoration: none;
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        .text-center a:hover {
            text-decoration: underline;
        }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card {
                max-width: 90%;
            }

            .card-body {
                padding: 1.5rem;
            }

            .btn-primary {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .card h3 {
                font-size: 1.4rem;
            }

            .form-label {
                font-size: 0.875rem;
            }

            .input-group-text i {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="text-center mb-4">User Login</h3>
                    <?php if (!empty($login_msg)) echo '<div class="alert alert-danger">'.$login_msg.'</div>'; ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                        <div class="mb-3">
                            <label for="role" class="form-label">Login as</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                <select id="role" name="role" class="form-select <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Role</option>
                                    <option value="owner" <?php echo ($role === 'owner') ? 'selected' : ''; ?>>Shop Owner</option>
                                    <option value="manager" <?php echo ($role === 'manager') ? 'selected' : ''; ?>>Manager</option>
                                    <option value="salesperson" <?php echo ($role === 'salesperson') ? 'selected' : ''; ?>>Salesperson</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $role_err; ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="text" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" />
                                <div class="invalid-feedback"><?php echo $email_err; ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your password" />
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Log In</button>
                        </div>
                    </form>
                    <p class="text-center mt-3">Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>