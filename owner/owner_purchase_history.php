<?php
session_start();
// --- AUTHENTICATION: Check for owner role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "owner") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$purchase_data = [];
$branches = [];
$error_message = "";

// 1. Inputs from GET
$filter_branch_id = (int)($_GET['branch_id'] ?? 0);
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$owner_id = $_SESSION["id"];

// 2. FETCH OWNER'S BRANCHES (Security check)
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
} else {
    // 3. CONSTRUCT SQL QUERY
    $sql_purchases = "
        SELECT p.*, b.name AS branch_name, m.full_name AS manager_name
        FROM purchases p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN managers m ON p.manager_id = m.id
    ";

    $where_clauses = [];
    $param_types = "";
    $param_values = [];

    // Filter: Branch Logic
    if ($filter_branch_id > 0 && in_array($filter_branch_id, $owner_branch_ids)) {
        $where_clauses[] = "p.branch_id = ?";
        $param_types .= "i";
        $param_values[] = $filter_branch_id;
    } else {
        // Default: Show all branches owned by this owner
        $placeholders = implode(',', array_fill(0, count($owner_branch_ids), '?'));
        $where_clauses[] = "p.branch_id IN ($placeholders)";
        $param_types .= str_repeat('i', count($owner_branch_ids)); 
        $param_values = array_merge($param_values, $owner_branch_ids);
    }

    // Filter: Date Range
    if (!empty($start_date)) {
        $where_clauses[] = "DATE(p.purchase_date) >= ?";
        $param_types .= "s";
        $param_values[] = $start_date;
    }
    if (!empty($end_date)) {
        $where_clauses[] = "DATE(p.purchase_date) <= ?";
        $param_types .= "s";
        $param_values[] = $end_date;
    }

    if (!empty($where_clauses)) {
        $sql_purchases .= " WHERE " . implode(' AND ', $where_clauses);
    }
    $sql_purchases .= " ORDER BY p.purchase_date DESC";

    // 4. EXECUTE
    $stmt_p = mysqli_prepare($conn, $sql_purchases);
    if ($stmt_p) {
        mysqli_stmt_bind_param($stmt_p, $param_types, ...$param_values);
        mysqli_stmt_execute($stmt_p);
        $result_p = mysqli_stmt_get_result($stmt_p);
        while ($row = mysqli_fetch_assoc($result_p)) {
            $purchase_data[] = $row;
        }
        mysqli_stmt_close($stmt_p);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Purchase History | Owner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; margin-left: 280px; transition: 0.3s; }
        .container { padding: 2rem 1rem; }
        .card { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 12px; }
        .table thead { background-color: #343a40; color: white; }
        .status-paid { background-color: #d1e7dd; color: #0f5132; }
        .status-pending { background-color: #f8d7da; color: #842029; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <div class="container py-4">
        <h2 class="mb-4 text-danger"><i class="fas fa-file-invoice me-2"></i>Purchase History (Stock In)</h2>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Branch</label>
                        <select name="branch_id" class="form-select form-select-sm">
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
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-danger btn-sm flex-grow-1">Search</button>
                        <a href="owner_purchase_history.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-warning"><?= $error_message ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover bg-white shadow-sm align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Branch</th>
                            <th>Supplier</th>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th class="text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchase_data as $p): ?>
                        <tr>
                            <td>#<?= $p['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($p['branch_name']) ?></td>
                            <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                            <td><span class="text-muted small"><?= htmlspecialchars($p['invoice_number'] ?: 'N/A') ?></span></td>
                            <td><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                            <td class="fw-bold text-danger">Rs <?= number_format($p['grand_total'], 2) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= $p['payment_status'] == 'paid' ? 'status-paid' : 'status-pending' ?>">
                                    <?= ucfirst($p['payment_status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-dark view-purchase-details" 
                                        data-id="<?= $p['id'] ?>" 
                                        data-bs-toggle="modal" data-bs-target="#pModal">
                                    <i class="fas fa-list"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="pModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Purchase Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modal-body-content">
                    </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).on('click', '.view-purchase-details', function() {
            const pid = $(this).data('id');
            $('#modal-body-content').html('<div class="text-center"><div class="spinner-border"></div></div>');
            $.get('fetch_purchase_details.php', { purchase_id: pid }, function(data) {
                $('#modal-body-content').html(data);
            });
        });
    </script>
</body>
</html>