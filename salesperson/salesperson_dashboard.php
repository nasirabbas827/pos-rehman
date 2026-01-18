<?php
session_start();
// --- AUTHENTICATION: Check for salesperson role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "salesperson") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$salesperson_id = $_SESSION["id"];

// 1. Fetch salesperson info along with branch details
$sql_sp = "SELECT s.name, s.email, s.branch_id, b.name as branch_name 
           FROM salespersons s 
           LEFT JOIN branches b ON s.branch_id = b.id 
           WHERE s.id = ?";
$stmt_sp = mysqli_prepare($conn, $sql_sp);
mysqli_stmt_bind_param($stmt_sp, "i", $salesperson_id);
mysqli_stmt_execute($stmt_sp);
mysqli_stmt_bind_result($stmt_sp, $full_name, $email, $branch_id, $branch_name);
mysqli_stmt_fetch($stmt_sp);
mysqli_stmt_close($stmt_sp);

// 2. Fetch Dashboard Statistics
$today = date('Y-m-d');
$sql_stats = "SELECT 
                COUNT(id) as total_orders, 
                SUM(total_amount) as total_revenue 
              FROM sales 
              WHERE salesperson_id = ? AND DATE(sale_date) = ?";
$stmt_stats = mysqli_prepare($conn, $sql_stats);
mysqli_stmt_bind_param($stmt_stats, "is", $salesperson_id, $today);
mysqli_stmt_execute($stmt_stats);
$res_stats = mysqli_stmt_get_result($stmt_stats);
$stats = mysqli_fetch_assoc($res_stats);
$today_orders = $stats['total_orders'] ?? 0;
$today_revenue = $stats['total_revenue'] ?? 0;

// Low Stock Alert
$sql_low_stock = "SELECT COUNT(id) as low_count FROM products WHERE branch_id = ? AND quantity < 10";
$stmt_low = mysqli_prepare($conn, $sql_low_stock);
mysqli_stmt_bind_param($stmt_low, "i", $branch_id);
mysqli_stmt_execute($stmt_low);
mysqli_stmt_bind_result($stmt_low, $low_stock_count);
mysqli_stmt_fetch($stmt_low);
mysqli_stmt_close($stmt_low);

// 3. CHART DATA: Last 7 Days Revenue
$labels = [];
$revenue_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('D', strtotime($date)); 
    
    $sql_day = "SELECT SUM(total_amount) as daily_rev FROM sales WHERE salesperson_id = ? AND DATE(sale_date) = ?";
    $stmt_day = mysqli_prepare($conn, $sql_day);
    mysqli_stmt_bind_param($stmt_day, "is", $salesperson_id, $date);
    mysqli_stmt_execute($stmt_day);
    $res_day = mysqli_stmt_get_result($stmt_day);
    $row_day = mysqli_fetch_assoc($res_day);
    $revenue_data[] = (float)($row_day['daily_rev'] ?? 0);
}

// 4. CHART DATA: Payment Methods
$sql_methods = "SELECT payment_method, COUNT(*) as count FROM sales WHERE salesperson_id = ? GROUP BY payment_method";
$stmt_meth = mysqli_prepare($conn, $sql_methods);
mysqli_stmt_bind_param($stmt_meth, "i", $salesperson_id);
mysqli_stmt_execute($stmt_meth);
$res_meth = mysqli_stmt_get_result($stmt_meth);
$method_labels = [];
$method_counts = [];
while($row = mysqli_fetch_assoc($res_meth)){
    $method_labels[] = $row['payment_method'];
    $method_counts[] = (int)$row['count'];
}

// 5. Fetch products for branch preview
$sql_products = "SELECT p.id, p.name, p.price, p.quantity, p.image, p.unit, c.name as category_name
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE p.branch_id = ?
                 ORDER BY p.quantity ASC LIMIT 10";
$stmt_products = mysqli_prepare($conn, $sql_products);
mysqli_stmt_bind_param($stmt_products, "i", $branch_id);
mysqli_stmt_execute($stmt_products);
$result_products = mysqli_stmt_get_result($stmt_products);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Salesperson Dashboard | POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f7fe; font-family: 'Segoe UI', sans-serif; margin-left: 280px; }
        .container { padding: 2rem 1.5rem; }
        .stat-card { border: none; border-radius: 15px; transition: 0.3s; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: translateY(-5px); }
        .bg-gradient-primary { background: linear-gradient(45deg, #0b5394, #2196f3); color: white; }
        .bg-gradient-success { background: linear-gradient(45deg, #28a745, #5dd478); color: white; }
        .bg-gradient-warning { background: linear-gradient(45deg, #f39c12, #fbc02d); color: white; }
        .table-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .product-img { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>
    
    <?php include('salesperson_sidebar.php'); ?>

    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold text-dark">Dashboard</h2>
                <p class="text-muted">Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong> | <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($branch_name); ?></p>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card stat-card bg-gradient-primary p-3 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 small opacity-75">Today's Revenue</h6>
                            <h3 class="mb-0">RS <?php echo number_format($today_revenue, 2); ?></h3>
                        </div>
                        <i class="fas fa-wallet fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-gradient-success p-3 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 small opacity-75">Today's Orders</h6>
                            <h3 class="mb-0"><?php echo $today_orders; ?></h3>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-gradient-warning p-3 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 small opacity-75">Low Stock Alert</h6>
                            <h3 class="mb-0"><?php echo $low_stock_count; ?> Items</h3>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="table-card h-100">
                    <h5 class="fw-bold mb-4"><i class="fas fa-chart-line me-2 text-primary"></i>7-Day Sales Trend</h5>
                    <div style="height: 300px;"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="table-card h-100 text-center">
                    <h5 class="fw-bold mb-4 text-start"><i class="fas fa-receipt me-2 text-primary"></i>Payment split</h5>
                    <div style="height: 300px;"><canvas id="paymentChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-boxes me-2 text-primary"></i>Stock Preview</h5>
                <a href="sales.php" class="btn btn-primary btn-sm rounded-pill px-4">Create Sale</a>
            </div>
            <div class="table-responsive">
    <div class="table-container">
<table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result_products)): ?>
                        <tr>
                            <td>
                                <?php if ($row['image']): ?>
                                    <img src="../owner/images/<?php echo htmlspecialchars($row['image']); ?>" class="product-img border" />
                                <?php else: ?>
                                    <div class="product-img bg-light d-flex align-items-center justify-content-center border"><i class="fas fa-box text-muted"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></div>
                                <small class="text-muted">#<?php echo $row['id']; ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                            <td class="fw-bold">RS <?php echo number_format($row['price'], 2); ?></td>
                            <td><?php echo (int)$row['quantity']; ?> <small><?php echo htmlspecialchars($row['unit']); ?></small></td>
                            <td>
                                <?php if($row['quantity'] <= 0): ?>
                                    <span class="badge bg-danger">Out of Stock</span>
                                <?php elseif($row['quantity'] < 10): ?>
                                    <span class="badge bg-warning text-dark">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Line Chart: Revenue Trend
        const revCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Revenue (RS)',
                    data: <?php echo json_encode($revenue_data); ?>,
                    borderColor: '#2196f3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });

        // Doughnut Chart: Payment Methods
        const payCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(payCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($method_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($method_counts); ?>,
                    backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    </script>
</body>
</html>
<?php 
mysqli_stmt_close($stmt_products);
mysqli_close($conn); 
?>