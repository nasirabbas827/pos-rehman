<?php
session_start();
include('config.php');

// 1. Access Control
if (!isset($_SESSION["usertype"]) || $_SESSION["usertype"] !== "admin") {
    header("Location: admin_login.php");
    exit;
}

// 2. Summary Statistics
$stats = [
    'owners'   => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status='approved'"))['count'],
    'pending'  => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status='pending'"))['count'],
    'branches' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM branches"))['count'],
    'sales'    => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM sales"))['count']
];

// 3. Chart Data Queries

// Chart 1: Registration Trend (Last 7 Days)
$regTrend = mysqli_query($conn, "SELECT DATE(created_at) as date, COUNT(*) as total FROM users GROUP BY DATE(created_at) LIMIT 7");
$chart1_labels = []; $chart1_data = [];
while($row = mysqli_fetch_assoc($regTrend)) { $chart1_labels[] = $row['date']; $chart1_data[] = $row['total']; }

// Chart 2: Status Distribution
$statusDist = mysqli_query($conn, "SELECT status, COUNT(*) as total FROM users GROUP BY status");
$chart2_labels = []; $chart2_data = [];
while($row = mysqli_fetch_assoc($statusDist)) { $chart2_labels[] = $row['status']; $chart2_data[] = $row['total']; }

// Chart 3: Owner-Wise Branch Count
$ownerBranch = mysqli_query($conn, "SELECT u.name, COUNT(b.id) as bcount FROM users u LEFT JOIN branches b ON u.id = b.owner_id WHERE u.status='approved' GROUP BY u.id LIMIT 10");
$chart3_labels = []; $chart3_data = [];
while($row = mysqli_fetch_assoc($ownerBranch)) { $chart3_labels[] = $row['name']; $chart3_data[] = $row['bcount']; }

// 4. Fetch User Approval Queue
$approvalQueue = mysqli_query($conn, "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");

// 5. Fetch Active Owners
$activeOwners = mysqli_query($conn, "SELECT * FROM users WHERE status = 'approved' ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin System Oversight</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f7fe; font-family: 'Segoe UI', sans-serif; margin-left: 280px; }
        .stat-card { border: none; border-radius: 15px; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .chart-container { background: #fff; padding: 20px; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); height: 100%; }
        .table-card { background: #fff; border-radius: 15px; padding: 20px; margin-bottom: 30px; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>

<?php include('admin_sidebar.php'); ?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line me-2 text-primary"></i>System Oversight</h2>
        <span class="badge bg-white text-dark shadow-sm p-2">Server Time: <?= date('Y-m-d H:i') ?></span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white p-3">
                <small>Active Owners</small>
                <h3 class="fw-bold"><?= $stats['owners'] ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-dark p-3">
                <small>Pending Approvals</small>
                <h3 class="fw-bold"><?= $stats['pending'] ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white p-3">
                <small>System Branches</small>
                <h3 class="fw-bold"><?= $stats['branches'] ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white p-3">
                <small>Total Sales Volume</small>
                <h3 class="fw-bold"><?= $stats['sales'] ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-8">
            <div class="chart-container">
                <h6>Registration Trend & Owner Growth</h6>
                <canvas id="regTrendChart"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container">
                <h6>User Status Distribution</h6>
                <canvas id="statusPieChart"></canvas>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="chart-container">
                <h6>Branch Count per Owner</h6>
                <canvas id="branchBarChart"></canvas>
            </div>
        </div>
    </div>

    <div class="table-card shadow-sm">
        <h5 class="mb-4 text-danger"><i class="fas fa-clock me-2"></i>User Approval Queue</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Shop Name</th>
                        <th>Owner</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = mysqli_fetch_assoc($approvalQueue)): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user['shop_name']) ?></strong></td>
                        <td><?= htmlspecialchars($user['name']) ?><br><small><?= $user['email'] ?></small></td>
                        <td><?= $user['phone_number'] ?></td>
                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <a href="update_status.php?id=<?= $user['id'] ?>&status=approved" class="btn btn-sm btn-success">Approve</a>
                            <a href="update_status.php?id=<?= $user['id'] ?>&status=rejected" class="btn btn-sm btn-outline-danger">Reject</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Chart 1: Line Chart
const ctx1 = document.getElementById('regTrendChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart1_labels) ?>,
        datasets: [{
            label: 'New Registrations',
            data: <?= json_encode($chart1_data) ?>,
            borderColor: '#0d6efd',
            tension: 0.3,
            fill: true,
            backgroundColor: 'rgba(13, 110, 253, 0.1)'
        }]
    }
});

// Chart 2: Pie Chart
const ctx2 = document.getElementById('statusPieChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chart2_labels) ?>,
        datasets: [{
            data: <?= json_encode($chart2_data) ?>,
            backgroundColor: ['#198754', '#ffc107', '#dc3545']
        }]
    }
});

// Chart 3: Bar Chart
const ctx3 = document.getElementById('branchBarChart').getContext('2d');
new Chart(ctx3, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart3_labels) ?>,
        datasets: [{
            label: 'Branches Managed',
            data: <?= json_encode($chart3_data) ?>,
            backgroundColor: '#0dcaf0'
        }]
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>