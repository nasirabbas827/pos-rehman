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

// Fetch branches
$sqlBranches = "SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name";
$stmt = $conn->prepare($sqlBranches);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultBranches = $stmt->get_result();
$branches = $resultBranches->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent Managers
$sqlManagers = "SELECT m.id, m.full_name, m.email, b.name AS branch_name
                FROM managers m
                JOIN branches b ON m.branch_id = b.id
                WHERE b.owner_id = ?
                ORDER BY m.assigned_at DESC LIMIT 5";
$stmt = $conn->prepare($sqlManagers);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultManagers = $stmt->get_result();
$managers = $resultManagers->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent Products
$sqlProducts = "SELECT p.id, p.name, p.price, p.quantity, p.image, b.name AS branch_name
                FROM products p
                JOIN branches b ON p.branch_id = b.id
                WHERE b.owner_id = ?
                ORDER BY p.created_at DESC LIMIT 5";
$stmt = $conn->prepare($sqlProducts);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultProducts = $stmt->get_result();
$products = $resultProducts->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Counts
$totalBranches = count($branches);

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

// LOW STOCK ALERTS (quantity <= 5)
$lowStockThreshold = 5;
$sqlLowStock = "SELECT p.name, p.quantity, b.name AS branch_name 
                FROM products p 
                JOIN branches b ON p.branch_id = b.id 
                WHERE b.owner_id = ? AND p.quantity <= ? 
                ORDER BY p.quantity ASC LIMIT 10";
$stmt = $conn->prepare($sqlLowStock);
$stmt->bind_param("ii", $user_id, $lowStockThreshold);
$stmt->execute();
$resultLowStock = $stmt->get_result();
$lowStockItems = $resultLowStock->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// RECENT SALES (Last 5 sales)
$sqlRecentSales = "SELECT s.id, s.sale_date, s.total_amount, s.payment_method, b.name AS branch_name
                   FROM sales s
                   JOIN branches b ON s.branch_id = b.id
                   WHERE b.owner_id = ?
                   ORDER BY s.sale_date DESC LIMIT 5";
$stmt = $conn->prepare($sqlRecentSales);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultRecentSales = $stmt->get_result();
$recentSales = $resultRecentSales->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// CHART DATA: Sales per day (last 7 days)
$salesChartLabels = [];
$salesChartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $salesChartLabels[] = date('M j', strtotime($date));
    $sql = "SELECT COALESCE(SUM(s.total_amount), 0) 
            FROM sales s 
            JOIN branches b ON s.branch_id = b.id 
            WHERE b.owner_id = ? AND DATE(s.sale_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $stmt->bind_result($dailyTotal);
    $stmt->fetch();
    $salesChartData[] = $dailyTotal;
    $stmt->close();
}

// CHART DATA: Products per branch
$branchProductCounts = [];
foreach ($branches as $branch) {
    $sql = "SELECT COUNT(*) FROM products WHERE branch_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branch['id']);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $branchProductCounts[] = $count;
    $stmt->close();
}

// CHART DATA: Low Stock Summary
$lowStockCount = count($lowStockItems);
$inStockCount = $countProducts - $lowStockCount;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Owner Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin-left: 280px;
        }
        .container { padding: 2rem 1rem; }
        h2, h4 { color: #333; margin-bottom: 1.5rem; }
        h2 { font-size: clamp(1.4rem, 4vw, 1.8rem); }
        h4 { font-size: clamp(1.2rem, 3vw, 1.5rem); }
        .table { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .table th { background-color: #f8f9fa; }
        .card { border-radius: 8px; transition: transform 0.3s; }
        .card:hover { transform: translateY(-5px); }
        .card-title { font-size: clamp(1rem, 2.5vw, 1.2rem); }
        .card-text { font-size: clamp(0.85rem, 2.5vw, 0.95rem); }
        .card-img-top { object-fit: cover; max-height: 180px; }
        .display-6 { font-size: clamp(1.5rem, 4vw, 2rem); }
        .no-image { background-color: #6c757d; color: #fff; text-align: center; padding: 3rem 0; }
        .alert-dismissible .btn-close { padding: 0.75rem; }
        .chart-container { position: relative; height: 300px; margin-bottom: 2rem; }
        footer { background: #f8f9fa; padding: 2rem 1rem; text-align: center; margin-top: 3rem; }
        .social-icons a { color: #333; margin: 0 0.5rem; font-size: 1.2rem; transition: color 0.3s; }
        .social-icons a:hover { color: #007bff; }

        @media (max-width: 992px) {
            body { margin-left: 0; }
            .container { padding: 1.5rem 1rem; }
        }
        @media (max-width: 576px) {
            h2 { font-size: 1.4rem; }
            h4 { font-size: 1.2rem; }
            .card-title { font-size: 1rem; }
            .card-img-top { max-height: 150px; }
            .display-6 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
        <span class="navbar-toggler-icon"></span>
    </button>

    <?php include('sidebar.php'); ?>

    <div class="container my-4">

        <!-- Alerts Section -->
        <?php if (!empty($lowStockItems)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong><i class="fas fa-exclamation-triangle"></i> Low Stock Alert!</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($lowStockItems as $item): ?>
                        <li><strong><?= htmlspecialchars($item['name']) ?></strong> (Qty: <?= $item['quantity'] ?>) at <em><?= htmlspecialchars($item['branch_name']) ?></em></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($recentSales)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong><i class="fas fa-shopping-cart"></i> Recent Sales</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($recentSales as $sale): ?>
                        <li>Sale #<?= $sale['id'] ?> - RS:<?= number_format($sale['total_amount'], 2) ?> (<?= $sale['payment_method'] ?>) at <?= htmlspecialchars($sale['branch_name']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- User Profile -->
        <h2><i class="fas fa-user-circle me-2"></i>Your Profile</h2>
        <table class="table table-bordered w-100 w-md-50 mb-5">
            <tbody>
                <tr><th><i class="fas fa-id-badge me-1"></i>Name</th><td><?= htmlspecialchars($name) ?></td></tr>
                <tr><th><i class="fas fa-envelope me-1"></i>Email</th><td><?= htmlspecialchars($email) ?></td></tr>
                <tr><th><i class="fas fa-store me-1"></i>Shop Name</th><td><?= htmlspecialchars($shop_name ?: '-') ?></td></tr>
                <tr><th><i class="fas fa-calendar-alt me-1"></i>Member Since</th><td><?= htmlspecialchars($created_at) ?></td></tr>
            </tbody>
        </table>

        <!-- Summary Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title m-0"><i class="fas fa-store-alt me-2"></i>Branches</h5>
                    </div>
                    <div class="card-body">
                        <p class="display-6 text-primary"><?= $totalBranches ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title m-0"><i class="fas fa-user-tie me-2"></i>Managers</h5>
                    </div>
                    <div class="card-body">
                        <p class="display-6 text-warning"><?= $countManagers ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title m-0"><i class="fas fa-boxes me-2"></i>Products</h5>
                    </div>
                    <div class="card-body">
                        <p class="display-6 text-success"><?= $countProducts ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title m-0"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock</h5>
                    </div>
                    <div class="card-body">
                        <p class="display-6 text-danger"><?= $lowStockCount ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title m-0"><i class="fas fa-chart-line me-2"></i>Sales Trend (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-purple text-white" style="background-color: #6f42c1;">
                        <h5 class="card-title m-0"><i class="fas fa-chart-bar me-2"></i>Products per Branch</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="branchChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        

        <!-- Branches Preview -->
        <h4>Branches</h4>
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-5">
            <?php foreach ($branches as $branch): ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title m-0"><i class="fas fa-store-alt me-2"></i> <?= htmlspecialchars($branch['name']) ?></h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><small class="text-muted">Branch ID: <?= $branch['id'] ?></small></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($branches)): ?>
                <p class="text-muted">No branches found.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Managers -->
        <h4>Recent Managers</h4>
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-5">
            <?php foreach ($managers as $manager): ?>
                <div class="col">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="card-title m-0"><i class="fas fa-user-tie me-2"></i> <?= htmlspecialchars($manager['full_name']) ?></h5>
                        </div>
                        <div class="card-body">
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

        <!-- Recent Products -->
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
                            <p class="card-text">RS: <?= number_format($product['price'], 2) ?> | Qty: <?= (int)$product['quantity'] ?></p>
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

    <!-- Chart.js Scripts -->
    <script>
        // Sales Trend Line Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($salesChartLabels) ?>,
                datasets: [{
                    label: 'Daily Sales (RS)',
                    data: <?= json_encode($salesChartData) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Branch Products Bar Chart
        const branchCtx = document.getElementById('branchChart').getContext('2d');
        new Chart(branchCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($branches, 'name')) ?>,
                datasets: [{
                    label: 'Products',
                    data: <?= json_encode($branchProductCounts) ?>,
                    backgroundColor: ['#6f42c1', '#0d6efd', '#198754', '#ffc107', '#dc3545']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        
    </script>
</body>
</html>