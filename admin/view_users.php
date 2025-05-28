<?php
include('config.php');
session_start();

if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] !== "admin") {
    header("Location: admin_login.php");
    exit;
}

$result = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>View Users</title>
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
        }

        h2 {
            color: #333;
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            margin-bottom: 1.5rem;
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

            .table {
                font-size: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            h2 {
                font-size: 1.4rem;
            }

            .table th, .table td {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hamburger button for mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <span class="navbar-toggler-icon"></span>
    </button>

    <?php include('admin_sidebar.php'); ?>

    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-users me-2"></i>All Shop Owners</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mt-3">
                <thead class="table-light">
                    <tr>
                        <th><i class="fas fa-id-badge me-1"></i>ID</th>
                        <th><i class="fas fa-user me-1"></i>Name</th>
                        <th><i class="fas fa-envelope me-1"></i>Email</th>
                        <th><i class="fas fa-store me-1"></i>Shop Name</th>
                        <th><i class="fas fa-calendar-alt me-1"></i>Registered At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['shop_name'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>