<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "owner") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$sales_data = [];
$branches = [];
$error_message = "";

// 1. Inputs from GET
$filter_branch_id = (int)($_GET['branch_id'] ?? 0);
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$owner_id = $_SESSION["id"];

// 2. FETCH OWNER'S BRANCHES
$owner_branch_ids = [];
$owner_branches_query = "SELECT id, name FROM branches WHERE owner_id = ?";
$stmt_owner_branches = mysqli_prepare($conn, $owner_branches_query);

if ($stmt_owner_branches) {
    mysqli_stmt_bind_param($stmt_owner_branches, "i", $owner_id);
    mysqli_stmt_execute($stmt_owner_branches);
    $result_owner_branches = mysqli_stmt_get_result($stmt_owner_branches);
    while ($row = mysqli_fetch_assoc($result_owner_branches)) {
        $branches[] = $row;
        $owner_branch_ids[] = $row['id'];
    }
    mysqli_stmt_close($stmt_owner_branches);
}

if (empty($owner_branch_ids)) {
    $error_message = "No branches are currently assigned to your account.";
}

// 3. CONSTRUCT SQL QUERY
$sql_sales = "
    SELECT s.id, s.sale_date, s.total_amount, s.cash_received, s.payment_method, 
           c.name AS customer_name, sp.name AS salesperson_name, b.name AS branch_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN salespersons sp ON s.salesperson_id = sp.id
    LEFT JOIN branches b ON s.branch_id = b.id
";

$where_clauses = [];
$param_types = "";
$param_values = [];

// Filter: Logic for Branch (Owner Security)
if ($filter_branch_id > 0 && in_array($filter_branch_id, $owner_branch_ids)) {
    $where_clauses[] = "s.branch_id = ?";
    $param_types .= "i";
    $param_values[] = $filter_branch_id;
} else {
    // Default: Only show branches owned by this user
    $placeholders = implode(',', array_fill(0, count($owner_branch_ids), '?'));
    $where_clauses[] = "s.branch_id IN ($placeholders)";
    $param_types .= str_repeat('i', count($owner_branch_ids)); 
    $param_values = array_merge($param_values, $owner_branch_ids);
}

// Filter: Date Range
if (!empty($start_date)) {
    $where_clauses[] = "DATE(s.sale_date) >= ?";
    $param_types .= "s";
    $param_values[] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "DATE(s.sale_date) <= ?";
    $param_types .= "s";
    $param_values[] = $end_date;
}

if (!empty($where_clauses)) {
    $sql_sales .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql_sales .= " ORDER BY s.sale_date DESC";

// 4. EXECUTE
if (!empty($owner_branch_ids)) {
    $stmt_sales = mysqli_prepare($conn, $sql_sales);
    if ($stmt_sales) {
        mysqli_stmt_bind_param($stmt_sales, $param_types, ...$param_values);
        mysqli_stmt_execute($stmt_sales);
        $result_sales = mysqli_stmt_get_result($stmt_sales);
        while ($row = mysqli_fetch_assoc($result_sales)) {
            $sales_data[] = $row;
        }
        mysqli_stmt_close($stmt_sales);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Sales History | Owner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f0f4f8; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin-left: 280px; transition: 0.3s; }
        .container { padding: 2rem 1rem; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 10px; }
        .badge-credit { background-color: #fac000; color: #ffc002; border: 1px solid #ffeeba; }
        .badge-cash { background-color: #00ff8c; color: #00fc87; border: 1px solid #badbcc; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-history me-2 text-primary"></i>Sales History</h2>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="0">All Branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $filter_branch_id == $branch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                        <a href="sales_history.php" class="btn btn-outline-secondary" title="Reset">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-3">
            <?php 
                $export_params = http_build_query([
                    'branch_id' => $filter_branch_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]);
            ?>
            <a href="export_sales_csv.php?<?= $export_params ?>" class="btn btn-success btn-sm shadow-sm" target="_blank">
                <i class="fas fa-file-excel me-1"></i> Export Results (CSV)
            </a>
        </div>

        <?php if (empty($sales_data)): ?>
            <div class="alert alert-info border-0 shadow-sm text-center py-5">
                <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">No sales transactions found for the selected criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover bg-white shadow-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Branch</th>
                            <th>Salesperson</th>
                            <th>Customer</th>
                            <th>Date/Time</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $sale): 
                            $balance = $sale['total_amount'] - $sale['cash_received'];
                        ?>
                        <tr>
                            <td>#<?= $sale['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($sale['branch_name']) ?></td>
                            <td class="small"><?= htmlspecialchars($sale['salesperson_name']) ?></td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?: 'Guest') ?></td>
                            <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></td>
                            <td class="fw-bold text-primary">Rs <?= number_format($sale['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge <?= ($sale['payment_method'] == 'Credit') ? 'badge-credit' : 'badge-cash' ?>">
                                    <?= $sale['payment_method'] ?>
                                </span>
                                <?php if ($sale['payment_method'] == 'Credit' && $balance > 0): ?>
                                    <div class="text-danger extra-small fw-bold" style="font-size: 10px;">
                                        Pending: Rs <?= number_format($balance, 2) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-success view-sale-details" 
                                        data-sale-id="<?= $sale['id'] ?>" data-bs-toggle="modal" 
                                        data-bs-target="#saleDetailsModal">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="print_invoice.php?sale_id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    </body>
</html>