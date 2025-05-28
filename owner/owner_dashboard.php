<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$user_id = $_SESSION["id"];

// Fetch user info
$sql = "SELECT name, email, shop_name, created_at FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $name, $email, $shop_name, $created_at);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Fetch counts for dashboard cards
function fetchCount($conn, $user_id, $table, $join_clause = '', $where_clause = '') {
    $sql = "SELECT COUNT(*) FROM $table $join_clause WHERE 1=1 $where_clause";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) return 0;

    if ($join_clause && strpos($join_clause, 'branches') !== false) {
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}

// Branches owned by user
$sqlBranches = "SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name";
$stmt = $conn->prepare($sqlBranches);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultBranches = $stmt->get_result();
$branches = $resultBranches->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Managers linked to user's branches
$sqlManagers = "SELECT m.id, m.full_name, m.email, b.name AS branch_name
                FROM managers m
                JOIN branches b ON m.branch_id = b.id
                WHERE b.owner_id = ?
                ORDER BY m.assigned_at DESC
                LIMIT 5";
$stmt = $conn->prepare($sqlManagers);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultManagers = $stmt->get_result();
$managers = $resultManagers->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Products linked to user's branches
$sqlProducts = "SELECT p.id, p.name, p.price, p.quantity, p.image, b.name AS branch_name
                FROM products p
                JOIN branches b ON p.branch_id = b.id
                WHERE b.owner_id = ?
                ORDER BY p.created_at DESC
                LIMIT 5";
$stmt = $conn->prepare($sqlProducts);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultProducts = $stmt->get_result();
$products = $resultProducts->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Counts
$totalBranches = count($branches);
$totalManagers = count($managers); // Or count all managers if needed separately
$totalProducts = count($products); // Or count all products in DB

// Alternatively, count total managers and products for cards
$sqlCountManagers = "SELECT COUNT(*) FROM managers m JOIN branches b ON m.branch_id = b.id WHERE b.owner_id = ?";
$stmt = $conn->prepare($sqlCountManagers);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($countManagers);
$stmt->fetch();
$stmt->close();

$sqlCountProducts = "SELECT COUNT(*) FROM products p JOIN branches b ON p.branch_id = b.id WHERE b.owner_id = ?";
$stmt = $conn->prepare($sqlCountProducts);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($countProducts);
$stmt->fetch();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Owner Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin-left: 280px; /* Space for sidebar */
        }

        .container {
            padding: 2rem 1rem;
        }

        h2, h4 {
            color: #333;
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            margin-bottom: 1.5rem;
        }

        h4 {
            font-size: clamp(1.2rem, 3vw, 1.5rem);
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

        .card {
            border-radius: 8px;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            color: #333;
        }

        .card-text {
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        .card-img-top {
            object-fit: cover;
            max-height: 180px;
        }

        .display-6 {
            font-size: clamp(1.5rem, 4vw, 2rem);
        }

        .no-image {
            background-color: #6c757d;
            color: #fff;
            text-align: center;
            padding: 3rem 0;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        footer {
            background: #f8f9fa;
            padding: 2rem 1rem;
            text-align: center;
            margin-top: 2rem;
        }

        footer p {
            margin: 0;
            color: #666;
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        .social-icons a {
            color: #333;
            margin: 0 0.5rem;
            font-size: 1.2rem;
            transition: color 0.3s;
        }

        .social-icons a:hover {
            color: #007bff;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            body {
                margin-left: 0; /* Remove sidebar space on mobile */
            }

            .container {
                padding: 1.5rem 1rem;
            }

            .table {
                width: 100% !important;
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

            .card-img-top {
                max-height: 150px;
            }

            .display-6 {
                font-size: 1.5rem;
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

    <div class="container my-4">
        <h2><i class="fas fa-user-circle me-2"></i>Your Profile</h2>
        <table class="table table-bordered w-100 w-md-50 mb-5">
            <tbody>
                <tr><th><i class="fas fa-id-badge me-1"></i>Name</th><td><?= htmlspecialchars($name) ?></td></tr>
                <tr><th><i class="fas fa-envelope me-1"></i>Email</th><td><?= htmlspecialchars($email) ?></td></tr>
                <tr><th><i class="fas fa-store me-1"></i>Shop Name</th><td><?= htmlspecialchars($shop_name ?: '-') ?></td></tr>
                <tr><th><i class="fas fa-calendar-alt me-1"></i>Member Since</th><td><?= htmlspecialchars($created_at) ?></td></tr>
            </tbody>
        </table>

        <!-- Summary cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-store-alt me-2"></i>Branches</h5>
                        <p class="display-6 text-primary"><?= $totalBranches ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-tie me-2"></i>Managers</h5>
                        <p class="display-6 text-warning"><?= $countManagers ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-boxes me-2"></i>Products</h5>
                        <p class="display-6 text-success"><?= $countProducts ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branches preview -->
        <h4>Branches</h4>
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-5">
            <?php foreach ($branches as $branch): ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-store-alt me-2"></i> <?= htmlspecialchars($branch['name']) ?></h5>
                            <p class="card-text"><small class="text-muted">Branch ID: <?= $branch['id'] ?></small></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($branches)): ?>
                <p class="text-muted">No branches found.</p>
            <?php endif; ?>
        </div>

        <!-- Managers preview -->
        <h4>Recent Managers</h4>
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-5">
            <?php foreach ($managers as $manager): ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user-tie me-2"></i> <?= htmlspecialchars($manager['full_name']) ?></h5>
                            <p class="card-text"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($manager['email']) ?></p>
                            <p class="card-text"><small class="text-muted">Branch: <?= htmlspecialchars($manager['branch_name']) ?></small></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($managers)): ?>
                <p class="text-muted">No managers found.</p>
            <?php endif; ?>
        </div>

        <!-- Products preview -->
        <h4>Recent Products</h4>
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-5">
            <?php foreach ($products as $product): ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <?php if ($product['image'] && file_exists('images/' . $product['image'])): ?>
                            <img src="images/<?= htmlspecialchars($product['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-box me-2"></i> <?= htmlspecialchars($product['name']) ?></h5>
                            <p class="card-text">$<?= number_format($product['price'], 2) ?> | Qty: <?= (int)$product['quantity'] ?></p>
                            <p class="card-text"><small class="text-muted">Branch: <?= htmlspecialchars($product['branch_name']) ?></small></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <p class="text-muted">No products found.</p>
            <?php endif; ?>
        </div>
    </div>

    <footer class="bg-light text-center">
        <div class="social-icons">
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
        </div>
        <p class="mt-2">© 2025 Karobar.com. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>