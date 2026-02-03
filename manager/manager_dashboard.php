<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$manager_id = $_SESSION["id"];

// 1. Get Manager & Branch Info
$stmt = $conn->prepare("SELECT m.full_name, m.branch_id, b.name as branch_name 
                        FROM managers m 
                        JOIN branches b ON m.branch_id = b.id 
                        WHERE m.id = ?");
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$manager_data = $stmt->get_result()->fetch_assoc();
$branch_id = $manager_data['branch_id'];
$branch_name = $manager_data['branch_name'];
$stmt->close();

// 2. Stats: Today's Sales
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE branch_id = ? AND DATE(sale_date) = ?");
$stmt->bind_param("is", $branch_id, $today);
$stmt->execute();
$sales_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// 3. Stats: Low Stock Count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE branch_id = ? AND quantity <= low_stock_threshold");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$low_stock_count = $stmt->get_result()->fetch_assoc()['count'];

// 4. Stats: Total Staff
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM salespersons WHERE branch_id = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$staff_count = $stmt->get_result()->fetch_assoc()['count'];

// 5. Chart Data: Last 7 Days Sales vs Purchases
$chart_days = [];
$sales_data_points = [];
$purchase_data_points = [];

// Initialize last 7 days with 0 values to ensure the graph doesn't break on empty days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($date));
    $chart_days[$date] = $day_name;
    $sales_data_points[$date] = 0;
    $purchase_data_points[$date] = 0;
}

// Fetch Sales for the chart
$sql_sales = "SELECT DATE(sale_date) as date, SUM(total_amount) as daily_total 
              FROM sales WHERE branch_id = ? AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY DATE(sale_date)";
$stmt_s = $conn->prepare($sql_sales);
$stmt_s->bind_param("i", $branch_id);
$stmt_s->execute();
$res_s = $stmt_s->get_result();
while($row = $res_s->fetch_assoc()) {
    if(isset($sales_data_points[$row['date']])) $sales_data_points[$row['date']] = (float)$row['daily_total'];
}

// Fetch Purchases for the chart
$sql_purchases = "SELECT DATE(purchase_date) as date, SUM(grand_total) as daily_total 
                  FROM purchases WHERE branch_id = ? AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY DATE(purchase_date)";
$stmt_p = $conn->prepare($sql_purchases);
$stmt_p->bind_param("i", $branch_id);
$stmt_p->execute();
$res_p = $stmt_p->get_result();
while($row = $res_p->fetch_assoc()) {
    if(isset($purchase_data_points[$row['date']])) $purchase_data_points[$row['date']] = (float)$row['daily_total'];
}

$sales_labels = array_values($chart_days);
$sales_values = array_values($sales_data_points);
$purchase_values = array_values($purchase_data_points);

// 6. Recent Sales List
$stmt = $conn->prepare("SELECT s.*, c.name as customer_name 
                        FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.id 
                        WHERE s.branch_id = ? 
                        ORDER BY s.sale_date DESC LIMIT 5");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$recent_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($branch_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .stat-card { border: none; border-radius: 15px; color: white; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .bg-primary-grad { background: linear-gradient(45deg, #4e73df, #224abe); }
        .bg-success-grad { background: linear-gradient(45deg, #1cc88a, #13855c); }
        .bg-warning-grad { background: linear-gradient(45deg, #f6c23e, #dda20a); }
        .bg-info-grad { background: linear-gradient(45deg, #36b9cc, #258391); }
        .card-title-info { font-size: 0.8rem; text-uppercase: uppercase; opacity: 0.8; }
        .card { border-radius: 15px; }
    </style>
</head>
<body>

<?php include('manager_sidebar.php'); ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="fw-bold">Welcome, <?= htmlspecialchars($manager_data['full_name']) ?></h2>
            <p class="text-muted">Branch: <strong><?= htmlspecialchars($branch_name) ?></strong></p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="p-2 bg-white rounded shadow-sm d-inline-block">
                <i class="fas fa-calendar-alt me-2 text-primary"></i><?= date('D, M d, Y') ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary-grad p-3 h-100">
                <div class="card-body">
                    <div class="card-title-info">Today's Sales</div>
                    <h3 class="fw-bold">RS <?= number_format($sales_today, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning-grad p-3 h-100">
                <div class="card-body">
                    <div class="card-title-info">Low Stock Items</div>
                    <h3 class="fw-bold"><?= $low_stock_count ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info-grad p-3 h-100">
                <div class="card-body">
                    <div class="card-title-info">Branch Staff</div>
                    <h3 class="fw-bold"><?= $staff_count ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success-grad p-3 h-100">
                <div class="card-body">
                    <div class="card-title-info">Khata System</div>
                    <h3 class="fw-bold">Active</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Sales vs Purchases (Last 7 Days)</h5>
                        <span class="badge bg-light text-dark border">Currency: PKR</span>
                    </div>
                    <div style="height: 350px;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Recent Branch Sales</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_sales as $sale): ?>
                                <tr>
                                    <td>#<?= $sale['id'] ?></td>
                                    <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                    <td><strong>RS <?= number_format($sale['total_amount'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $sale['payment_method'] == 'Cash' ? 'bg-success' : 'bg-warning' ?>">
                                            <?= $sale['payment_method'] ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= date('h:i A', strtotime($sale['sale_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <a href="products.php" class="btn btn-outline-primary p-3 text-start"><i class="fas fa-boxes me-2"></i> Manage Stock</a>
                        <a href="salespersons.php" class="btn btn-outline-primary p-3 text-start"><i class="fas fa-user-tie me-2"></i> Manage Staff</a>
                        <a href="purchases.php" class="btn btn-outline-danger p-3 text-start"><i class="fas fa-truck-loading me-2"></i> New Purchase Bill</a>
                        <a href="sales_history.php" class="btn btn-outline-dark p-3 text-start"><i class="fas fa-book me-2"></i> Sales History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('performanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($sales_labels) ?>,
            datasets: [
                {
                    label: 'Sales (Revenue)',
                    data: <?= json_encode($sales_values) ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 3,
                    pointRadius: 4
                },
                {
                    label: 'Purchases (Expenses)',
                    data: <?= json_encode($purchase_values) ?>,
                    borderColor: '#e74a3b',
                    backgroundColor: 'rgba(231, 74, 59, 0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 3,
                    pointRadius: 4
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: { weight: 'bold' }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'RS ' + value.toLocaleString(); }
                    },
                    grid: { color: '#eee' }
                },
                x: { grid: { display: false } }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
</script>
</body>
</html>