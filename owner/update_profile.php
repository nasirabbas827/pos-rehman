<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$user_id = $_SESSION["id"];

// Fetch user info
$sql = "SELECT name, email, shop_name FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $name, $email, $shop_name);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Handle form submission
$success_msg = $error_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_name = trim($_POST['name']);
    $new_email = trim($_POST['email']);
    $new_shop_name = trim($_POST['shop_name']);

    if (empty($new_name) || empty($new_email)) {
        $error_msg = "Name and Email are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email format.";
    } else {
        $update_sql = "UPDATE users SET name = ?, email = ?, shop_name = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "sssi", $new_name, $new_email, $new_shop_name, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Profile updated successfully.";
            $name = $new_name;
            $email = $new_email;
            $shop_name = $new_shop_name;
        } else {
            $error_msg = "Error updating profile. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Update Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #e6effc; /* Consistent with other pages */
            font-family: Arial, sans-serif;
            margin-left: 280px; /* Space for sidebar */
        }

        .container {
            padding: 2rem 1rem;
            max-width: 600px; /* Compact form width */
        }

        h2 {
            color: #333;
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            color: #333;
        }

        .form-control {
            font-size: clamp(0.875rem, 2.5vw, 1rem);
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
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

        
        /* Hamburger button for mobile */
        .navbar-toggler {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            body {
                margin-left: 0; /* Remove sidebar space on mobile */
            }

            .container {
                padding: 1.5rem 1rem;
            }

            .navbar-toggler {
                display: block; /* Show hamburger menu */
            }

            .btn-primary {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            h2 {
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
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <span class="navbar-toggler-icon"></span>
    </button>

    <?php include('sidebar.php'); ?>

    <div class="container">
            <div class="card mx-auto" >
        <div class="card-body">
        <h2><i class="fas fa-user-edit me-2"></i>Update Profile</h2>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php elseif ($error_msg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label for="name" class="form-label"><i class="fas fa-id-badge me-1"></i>Name</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required />
                </div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label"><i class="fas fa-envelope me-1"></i>Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required />
                </div>
            </div>
            <div class="mb-3">
                <label for="shop_name" class="form-label"><i class="fas fa-store me-1"></i>Shop Name</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-store"></i></span>
                    <input type="text" id="shop_name" name="shop_name" class="form-control" value="<?php echo htmlspecialchars($shop_name ?: ''); ?>" />
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
    </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>