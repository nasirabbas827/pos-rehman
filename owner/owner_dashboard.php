<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "owner") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];

// ────────────────────────────────────────────────
// 1. Fetch Owner Profile
// ────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT name, shop_name FROM users WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$stmt->bind_result($owner_name, $shop_name);
$stmt->fetch();
$stmt->close();

// ────────────────────────────────────────────────
// 2. KPI / Summary Data
// ────────────────────────────────────────────────
$today = date('Y-m-d');
$this_month_start = date('Y-m-01');

$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN DATE(sale_date) = ? THEN total_amount END), 0) AS today_sales,
        COALESCE(SUM(CASE WHEN sale_date >= ? THEN total_amount END), 0) AS month_sales,
        COALESCE(SUM(CASE WHEN sale_date >= ? THEN cash_received END), 0) AS month_cash
    FROM sales s
    JOIN branches b ON s.branch_id = b.id
    WHERE b.owner_id = ?
");
$stmt->bind_param("sssi", $today, $this_month_start, $this_month_start, $owner_id);
$stmt->execute();
$sales_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Map array values to variables to avoid Undefined Variable errors
$today_sales = $sales_row['today_sales'] ?? 0;
$month_sales = $sales_row['month_sales'] ?? 0;
$month_cash  = $sales_row['month_cash'] ?? 0;

// Total Outstanding Credit
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(k.amount - k.cash_received), 0) AS total_credit
    FROM khata k
    JOIN customers c ON k.customer_id = c.id
    JOIN branches b ON c.branch_id = b.id
    WHERE b.owner_id = ? AND k.type = 'CREDIT'
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$stmt->bind_result($total_credit);
$stmt->fetch();
$stmt->close();

// Products & Low Stock
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_products,
        SUM(CASE WHEN quantity <= low_stock_threshold AND quantity > 0 THEN 1 ELSE 0 END) AS low_stock
    FROM products p
    JOIN branches b ON p.branch_id = b.id
    WHERE b.owner_id = ?
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$product_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ────────────────────────────────────────────────
// 3. Chart Data
// ────────────────────────────────────────────────
$sales_trend_labels = []; $sales_trend_data = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sales_trend_labels[] = date('M j', strtotime($date));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(s.total_amount), 0) FROM sales s JOIN branches b ON s.branch_id = b.id WHERE b.owner_id = ? AND DATE(s.sale_date) = ?");
    $stmt->bind_param("is", $owner_id, $date); $stmt->execute(); $stmt->bind_result($daily); $stmt->fetch();
    $sales_trend_data[] = $daily; $stmt->close();
}

$stmt = $conn->prepare("SELECT payment_method, COALESCE(SUM(total_amount), 0) AS total FROM sales s JOIN branches b ON s.branch_id = b.id WHERE b.owner_id = ? GROUP BY payment_method");
$stmt->bind_param("i", $owner_id); $stmt->execute(); $payment_result = $stmt->get_result();
$payment_labels = ['Cash' => 0, 'Credit' => 0];
while ($row = $payment_result->fetch_assoc()) { $payment_labels[$row['payment_method']] = $row['total']; }
$stmt->close();

// ────────────────────────────────────────────────
// 4. Tables Data
// ────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT s.id, s.total_amount, s.payment_method, s.sale_date, b.name AS branch FROM sales s JOIN branches b ON s.branch_id = b.id WHERE b.owner_id = ? ORDER BY s.sale_date DESC LIMIT 6");
$stmt->bind_param("i", $owner_id); $stmt->execute(); $recent_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$stmt = $conn->prepare("SELECT b.name, COALESCE(SUM(s.total_amount), 0) AS total_sales, (SELECT COUNT(*) FROM products p WHERE p.branch_id = b.id AND p.quantity <= p.low_stock_threshold AND p.quantity > 0) AS low_stock_count FROM branches b LEFT JOIN sales s ON s.branch_id = b.id WHERE b.owner_id = ? GROUP BY b.id, b.name ORDER BY total_sales DESC");
$stmt->bind_param("i", $owner_id); $stmt->execute(); $branch_health = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owner Dashboard | <?= htmlspecialchars($shop_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --primary-color: #4f46e5;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }
        body { background: #f8fafc; margin-left: var(--sidebar-width); font-family: 'Inter', sans-serif; color: #1e293b; transition: margin 0.3s; }
        .dashboard-container { padding: 2rem; }
        .stat-card { border: none; border-radius: 12px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .chart-container { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: 100%; }
        .table-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .badge { border: none; font-weight: 600; padding: 0.4em 0.8em; }
        .bg-success { background: #dcfce7; color: #166534; }
        .bg-warning { background: #fef9c3; color: #854d0e; }
        .bg-danger { background: #fee2e2; color: #991b1b; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1">Business Overview</h2>
            <p class="text-muted mb-0">Welcome back, <strong><?= htmlspecialchars($owner_name) ?></strong></p>
        </div>
        <div class="bg-white border px-3 py-2 rounded-3 shadow-sm">
            <i class="far fa-calendar-alt text-primary me-2"></i><?= date('D, M j, Y') ?>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card stat-card p-3 border-start border-4 border-primary">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary text-white me-3"><i class="fas fa-coins"></i></div>
                    <div>
                        <small class="text-muted fw-bold">TODAY'S SALES</small>
                        <h4 class="fw-bold mb-0">Rs <?= number_format($today_sales) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3 border-start border-4 border-success">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-success text-white me-3"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <small class="text-muted fw-bold">MONTH SALES</small>
                        <h4 class="fw-bold mb-0">Rs <?= number_format($month_sales) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3 border-start border-4 border-danger">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-danger text-white me-3"><i class="fas fa-user-clock"></i></div>
                    <div>
                        <small class="text-muted fw-bold">TOTAL CREDIT</small>
                        <h4 class="fw-bold mb-0">Rs <?= number_format($total_credit) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3 border-start border-4 border-warning">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-warning text-white me-3"><i class="fas fa-box-open"></i></div>
                    <div>
                        <small class="text-muted fw-bold">LOW STOCK</small>
                        <h4 class="fw-bold mb-0"><?= $product_stats['low_stock'] ?? 0 ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="chart-container">
                <h6 class="fw-bold mb-4 text-uppercase small text-muted">Sales Performance (30 Days)</h6>
                <div style="height: 300px;"><canvas id="salesTrendChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container">
                <h6 class="fw-bold mb-4 text-uppercase small text-muted">Payment Methods</h6>
                <div style="height: 300px;"><canvas id="paymentChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="table-card">
                <div class="p-3 border-bottom d-flex justify-content-between">
                    <h6 class="fw-bold mb-0">Recent Transactions</h6>
                    <a href="sales_history.php" class="small text-decoration-none">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-dark">
                            <tr>
                                <th class="border-0">Order ID</th>
                                <th class="border-0">Branch</th>
                                <th class="border-0">Amount</th>
                                <th class="border-0">Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td class="fw-medium">#<?= $sale['id'] ?></td>
                                <td><?= htmlspecialchars($sale['branch']) ?></td>
                                <td class="fw-bold">Rs <?= number_format($sale['total_amount']) ?></td>
                                <td>
                                    <span class="badge badge <?= $sale['payment_method'] === 'Cash' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= $sale['payment_method'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="table-card">
                <div class="p-3 border-bottom">
                    <h6 class="fw-bold mb-0">Branch Health Overview</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Branch</th>
                                <th>Sales</th>
                                <th>Alerts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branch_health as $bh): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($bh['name']) ?></td>
                                <td>Rs <?= number_format($bh['total_sales']) ?></td>
                                <td>
                                    <?php if ($bh['low_stock_count'] > 0): ?>
                                        <span class="badge bg-danger"><?= $bh['low_stock_count'] ?> Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Main Sales Line Chart
const ctxTrend = document.getElementById('salesTrendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: <?= json_encode($sales_trend_labels) ?>,
        datasets: [{
            data: <?= json_encode($sales_trend_data) ?>,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { border: { dash: [5,5] } }
        }
    }
});

// Payment Doughnut Chart
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: ['Cash', 'Credit'],
        datasets: [{
            data: [<?= $payment_labels['Cash'] ?>, <?= $payment_labels['Credit'] ?>],
            backgroundColor: ['#10b981', '#f59e0b'],
            borderWidth: 5,
            borderColor: '#ffffff'
        }]
    },
    options: {
        cutout: '75%',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>