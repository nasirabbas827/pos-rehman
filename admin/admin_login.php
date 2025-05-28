<?php
session_start();
include('config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    $sql = "SELECT id FROM admins WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $_SESSION["usertype"] = "admin";
        $_SESSION["username"] = $username;
        $stmt->bind_result($admin_id);
        $stmt->fetch();
        $_SESSION["userid"] = $admin_id;
        header("Location: admin_home.php"); 
        exit;
    } else {
        $error = "Invalid username or password.";
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #e6effc; /* Consistent with other pages */
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            padding: 3rem 1rem;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px; /* Compact form width */
            margin: 0 auto;
        }

        .card-body {
            padding: 2rem;
        }

        h2 {
            color: #333;
            font-size: clamp(1.5rem, 4vw, 1.8rem);
            margin-bottom: 1.5rem;
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
            font-size: 1.1rem;
        }

        .form-control {
            font-size: clamp(0.875rem, 2.5vw, 1rem);
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }

        .text-danger {
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 0.75rem;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            transition: background-color 0.3s;
        }

        .btn-primary i {
            margin-right: 0.5rem;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card {
                max-width: 90%;
            }

            .card-body {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            h2 {
                font-size: 1.4rem;
            }

            .form-label {
                font-size: 0.875rem;
            }

            .input-group-text i {
                font-size: 1rem;
            }

            .form-control {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card mx-auto">
            <div class="card-body">
                <h2 class="text-center mb-4"><i class="fas fa-user-shield me-2"></i>Admin Login</h2>
                <?php if (isset($error)) { ?>
                    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
                <?php } ?>
                <form class="login-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label"><i class="fas fa-user me-1"></i>Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><i class="fas fa-lock me-1"></i>Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required />
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt me-1"></i>Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>