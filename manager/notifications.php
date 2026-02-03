<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$manager_id = $_SESSION["id"];

// 1. Get Manager's Branch and Owner ID
$stmt = $conn->prepare("SELECT m.branch_id, b.owner_id FROM managers m JOIN branches b ON m.branch_id = b.id WHERE m.id = ?");
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$manager_meta = $stmt->get_result()->fetch_assoc();
$branch_id = $manager_meta['branch_id'];
$owner_id = $manager_meta['owner_id'];
$stmt->close();

// --- FETCH DATA (Limit 5 each) ---

// 1. Sales (Branch Specific)
$res_sales = $conn->query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.branch_id = $branch_id ORDER BY s.sale_date DESC LIMIT 5");

// 2. Low Stock (Branch Specific)
$res_stock = $conn->query("SELECT * FROM products WHERE branch_id = $branch_id AND quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 5");

// 3. New Staff (Branch Specific)
$res_staff = $conn->query("SELECT * FROM salespersons WHERE branch_id = $branch_id ORDER BY created_at DESC LIMIT 5");

// 4. Khata Records (Branch Specific)
$res_khata = $conn->query("SELECT k.*, c.name as customer_name FROM khata k JOIN customers c ON k.customer_id = c.id WHERE c.branch_id = $branch_id ORDER BY k.created_at DESC LIMIT 5");

// 5. Recent Customers (Branch Specific)
$res_cust = $conn->query("SELECT * FROM customers WHERE branch_id = $branch_id ORDER BY created_at DESC LIMIT 5");

// 6. New Categories (Global from Owner)
$res_cats = $conn->query("SELECT * FROM categories WHERE owner_id = $owner_id ORDER BY created_at DESC LIMIT 5");

// 7. NEW: Branch Purchases (Inventory Added to THIS branch)
$res_purchases = $conn->query("SELECT * FROM purchases WHERE branch_id = $branch_id ORDER BY purchase_date DESC LIMIT 5");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Notifications Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; margin-left: 260px; transition: 0.3s; }
        .notif-card { border: none; border-radius: 15px; background: #fff; height: 100%; transition: 0.3s; }
        .notif-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .notif-item { display: flex; align-items: flex-start; padding: 12px; border-bottom: 1px solid #f8f9fa; }
        .notif-item:last-child { border-bottom: none; }
        .icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; }
        .time-label { font-size: 0.7rem; color: #adb5bd; text-transform: uppercase; font-weight: bold; }
        .notif-title { font-size: 0.9rem; font-weight: 600; color: #343a40; margin-bottom: 2px; }
        .notif-sub { font-size: 0.8rem; color: #6c757d; }
        .bg-indigo { background-color: #e0e7ff; color: #4338ca; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>

<?php include('manager_sidebar.php'); ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="fw-bold">Branch Activity Monitor</h3>
            <p class="text-muted">Real-time updates for your assigned branch</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4 col-md-6">
            <div class="card notif-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-shopping-bag text-primary me-2"></i>Recent Sales</h6>
                </div>
                <div class="card-body p-2">
                    <?php while($s = $res_sales->fetch_assoc()): ?>
                    <div class="notif-item">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="fas fa-plus small"></i></div>
                        <div>
                            <div class="notif-title">Sale #<?= $s['id'] ?> - RS <?= number_format($s['total_amount'],2) ?></div>
                            <div class="notif-sub"><?= $s['customer_name'] ?? 'Walk-in' ?> • <?= $s['payment_method'] ?></div>
                            <div class="time-label"><?= date('h:i A', strtotime($s['sale_date'])) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card notif-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-truck-loading text-indigo me-2"></i>Stock Purchases</h6>
                </div>
                <div class="card-body p-2">
                    <?php while($p = $res_purchases->fetch_assoc()): ?>
                    <div class="notif-item">
                        <div class="icon-box bg-indigo bg-opacity-10 text-indigo"><i class="fas fa-file-invoice small"></i></div>
                        <div>
                            <div class="notif-title">RS <?= number_format($p['grand_total'],2) ?></div>
                            <div class="notif-sub"><?= htmlspecialchars($p['supplier_name']) ?> • Inv: <?= $p['invoice_number'] ?></div>
                            <div class="time-label"><?= date('M d, h:i A', strtotime($p['purchase_date'])) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card notif-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Low Stock Alerts</h6>
                </div>
                <div class="card-body p-2">
                    <?php while($st = $res_stock->fetch_assoc()): ?>
                    <div class="notif-item">
                        <div class="icon-box bg-danger bg-opacity-10 text-danger"><i class="fas fa-box-open small"></i></div>
                        <div>
                            <div class="notif-title"><?= htmlspecialchars($st['name']) ?></div>
                            <div class="notif-sub text-danger">Only <?= $st['quantity'] ?> <?= $st['unit'] ?> remaining</div>
                            <div class="time-label">Threshold: <?= $st['low_stock_threshold'] ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card notif-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-book text-warning me-2"></i>Khata Ledger</h6>
                </div>
                <div class="card-body p-2">
                    <?php while($k = $res_khata->fetch_assoc()): ?>
                    <div class="notif-item">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning"><i class="fas fa-wallet small"></i></div>
                        <div>
                            <div class="notif-title"><?= $k['type'] ?>: RS <?= number_format($k['amount'], 2) ?></div>
                            <div class="notif-sub"><?= htmlspecialchars($k['customer_name']) ?></div>
                            <div class="time-label"><?= date('M d, H:i', strtotime($k['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card notif-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-user-friends text-info me-2"></i>New Customers</h6>
                </div>
                <div class="card-body p-2">
                    <?php while($c = $res_cust->fetch_assoc()): ?>
                    <div class="notif-item">
                        <div class="icon-box bg-info bg-opacity-10 text-info"><i class="fas fa-user-plus small"></i></div>
                        <div>
                            <div class="notif-title"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="notif-sub"><?= $c['phone_number'] ?></div>
                            <div class="time-label">Joined <?= date('M d', strtotime($c['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card notif-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-user-tie text-success me-2"></i>Sales Team</h6>
                </div>
                <div class="card-body p-2">
                    <?php while($sp = $res_staff->fetch_assoc()): ?>
                    <div class="notif-item">
                        <div class="icon-box bg-success bg-opacity-10 text-success"><i class="fas fa-id-badge small"></i></div>
                        <div>
                            <div class="notif-title"><?= htmlspecialchars($sp['name']) ?></div>
                            <div class="notif-sub"><?= $sp['email'] ?></div>
                            <div class="time-label">Added <?= date('M d', strtotime($sp['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card notif-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-tags text-secondary me-2"></i>New Categories</h6>
                </div>
                <div class="card-body p-2">
                    <?php while($cat = $res_cats->fetch_assoc()): ?>
                    <div class="notif-item">
                        <div class="icon-box bg-secondary bg-opacity-10 text-secondary"><i class="fas fa-tag small"></i></div>
                        <div>
                            <div class="notif-title"><?= htmlspecialchars($cat['name']) ?></div>
                            <div class="notif-sub">Available for products</div>
                            <div class="time-label">Added by Owner</div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>