<?php
session_start();
include('config.php');

// Check if the user is logged in as an admin
if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] !== "admin") {
    header("Location: admin_login.php");
    exit;
}

// Count total users
$userCount = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
if ($row = mysqli_fetch_assoc($result)) {
    $userCount = $row['total'];
}

// Count total managers
$managerCount = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM managers");
if ($row = mysqli_fetch_assoc($result)) {
    $managerCount = $row['total'];
}

// Count total products
$productCount = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products");
if ($row = mysqli_fetch_assoc($result)) {
    $productCount = $row['total'];
}

// Count total branches
$branchCount = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM branches");
if ($row = mysqli_fetch_assoc($result)) {
    $branchCount = $row['total'];
}

// Fetch all users
$userList = mysqli_query($conn, "SELECT id, name, email, created_at FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin-left: 280px;
        }

        .container {
            padding: 2rem 1rem;
        }

        h2 {
            color: #333;
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            margin-bottom: 1.5rem;
        }

        h4 {
            color: #333;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            margin-bottom: 1rem;
        }

        .card {
            border-radius: 8px;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
        }

        .card-text {
            font-size: clamp(1.2rem, 3vw, 1.5rem);
        }

        .card-icon {
            font-size: clamp(2rem, 5vw, 3rem);
        }

        .table {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #f8f9fa;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .table td {
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        .table-hover tbody tr:hover {
            background-color: #f1f6ff;
        }

        .btn-light {
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        .navbar-toggler {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
        }

       
        @media (max-width: 992px) {
            body {
                margin-left: 0;
            }

            .container {
                padding: 1.5rem 1rem;
            }

            .navbar-toggler {
                display: block;
            }

            .table {
                font-size: 0.875rem;
            }
        }

        @media (max-width: 768px) {
            .row.mb-5 {
                margin-bottom: 2rem !important;
            }
        }

        @media (max-width: 576px) {
            h2 {
                font-size: 1.4rem;
            }

            h4 {
                font-size: 1.2rem;
            }

            .card-title {
                font-size: 1rem;
            }

            .card-text {
                font-size: 1.2rem;
            }

            .card-icon {
                font-size: 2rem;
            }

            .table th, .table td {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <span class="navbar-toggler-icon"></span>
    </button>

    <?php include('admin_sidebar.php'); ?>

    <div class="container mt-5">
        <h2 class="text-center mb-4"><i class="fas fa-user-shield me-2"></i>Admin Dashboard</h2>

        <div class="row mb-5">
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card text-white bg-primary shadow">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><i class="fas fa-users me-2"></i>Total Shop Owners</h5>
                            <p class="card-text h4"><?= $userCount ?></p>
                        </div>
                        <i class="fas fa-users card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card text-white bg-success shadow">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><i class="fas fa-user-tie me-2"></i>Total Managers</h5>
                            <p class="card-text h4"><?= $managerCount ?></p>
                        </div>
                        <i class="fas fa-user-tie card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card text-white bg-info shadow">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><i class="fas fa-box me-2"></i>Total Products</h5>
                            <p class="card-text h4"><?= $productCount ?></p>
                        </div>
                        <i class="fas fa-box card-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card text-white bg-warning shadow">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><i class="fas fa-code-branch me-2"></i>Total Branches</h5>
                            <p class="card-text h4"><?= $branchCount ?></p>
                        </div>
                        <i class="fas fa-code-branch card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <h4 id="userTable"><i class="fas fa-table me-2"></i>Shop Owners Table</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mt-3">
                <thead class="table-light">
                    <tr>
                        <th><i class="fas fa-hashtag me-1"></i>ID</th>
                        <th><i class="fas fa-user me-1"></i>Full Name</th>
                        <th><i class="fas fa-envelope me-1"></i>Email</th>
                        <th><i class="fas fa-calendar-alt me-1"></i>Registered On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($userList) > 0): ?>
                        <?php while ($user = mysqli_fetch_assoc($userList)): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php mysqli_close($conn); ?>