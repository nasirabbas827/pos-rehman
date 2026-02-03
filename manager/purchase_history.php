<?php
session_start();
// --- AUTHENTICATION: Check for manager role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$manager_id = $_SESSION["id"];
$purchase_data = [];
$error_message = "";
$branch_name = "N/A";
$branch_id = null;

// Get Filter Dates from GET request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// 1. Fetch manager info and branch ID
$sql_manager = "SELECT b.name, m.branch_id FROM managers m JOIN branches b ON m.branch_id = b.id WHERE m.id = ?";
$stmt_m = mysqli_prepare($conn, $sql_manager);
mysqli_stmt_bind_param($stmt_m, "i", $manager_id);
mysqli_stmt_execute($stmt_m);
mysqli_stmt_bind_result($stmt_m, $branch_name, $branch_id);
mysqli_stmt_fetch($stmt_m);
mysqli_stmt_close($stmt_m);

// 2. Check if branch ID is available
if (!$branch_id) {
    $error_message = "Error: Manager is not linked to a branch.";
} else {
    // Build SQL Query for Purchases
    $sql_purchases = "
        SELECT 
            p.id, 
            p.purchase_date, 
            p.supplier_name,
            p.invoice_number,
            p.total_amount, 
            p.grand_total,
            p.payment_status,
            p.payment_method
        FROM purchases p
        WHERE p.branch_id = ?";

    // Add Date Conditions
    if (!empty($start_date) && !empty($end_date)) {
        $sql_purchases .= " AND DATE(p.purchase_date) BETWEEN ? AND ?";
    }

    $sql_purchases .= " ORDER BY p.purchase_date DESC";

    $stmt_p = mysqli_prepare($conn, $sql_purchases);
    if ($stmt_p) {
        if (!empty($start_date) && !empty($end_date)) {
            mysqli_stmt_bind_param($stmt_p, "iss", $branch_id, $start_date, $end_date);
        } else {
            mysqli_stmt_bind_param($stmt_p, "i", $branch_id);
        }
        
        mysqli_stmt_execute($stmt_p);
        $result_p = mysqli_stmt_get_result($stmt_p);

        while ($row = mysqli_fetch_assoc($result_p)) {
            $purchase_data[] = $row;
        }
        mysqli_stmt_close($stmt_p);
    } else {
        $error_message = "Database error: Could not prepare the purchase query.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Purchase History - <?= htmlspecialchars($branch_name); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; margin-left: 280px; transition: 0.3s; }
        .container { padding: 2rem 1rem; }
        h2 { color: #d9534f; font-weight: bold; } /* Red theme for purchases/expenses */
        .filter-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .action-icon { cursor: pointer; transition: 0.2s; }
        .action-icon:hover { transform: scale(1.2); }
        @media (max-width: 992px) { body { margin-left: 0; } }
        .modal-header { background-color: #d9534f; color: white; }
    </style>
</head>
<body>
    
    <?php include('manager_sidebar.php'); ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-truck-loading me-2"></i>Purchase History: <?= htmlspecialchars($branch_name); ?></h2>
        </div>

        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date; ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-danger px-4"><i class="fas fa-filter me-2"></i>Filter</button>
                    <a href="purchase_history.php" class="btn btn-outline-secondary px-4">Reset</a>
                </div>
            </form>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message; ?></div>
        <?php endif; ?>

        <div class="table-responsive bg-white p-3 rounded shadow-sm">
            <?php if (empty($purchase_data)): ?>
                <div class="alert alert-info text-center py-4">No purchase records found.</div>
            <?php else: ?>
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Bill ID</th>
                            <th>Invoice #</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Grand Total</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchase_data as $p): ?>
                        <tr>
                            <td class="fw-bold">#<?= $p['id']; ?></td>
                            <td><?= htmlspecialchars($p['invoice_number'] ?: 'N/A'); ?></td>
                            <td><?= htmlspecialchars($p['supplier_name']); ?></td>
                            <td class="small"><?= date('d M Y', strtotime($p['purchase_date'])); ?></td>
                            <td class="fw-bold text-danger">RS <?= number_format($p['grand_total'], 2); ?></td>
                            <td><?= $p['payment_method']; ?></td>
                            <td>
                                <?php 
                                    $status_class = [
                                        'paid' => 'bg-success',
                                        'partial' => 'bg-warning text-dark',
                                        'pending' => 'bg-danger'
                                    ][$p['payment_status']];
                                ?>
                                <span class="badge <?= $status_class; ?>"><?= ucfirst($p['payment_status']); ?></span>
                            </td>
                            <td>
                                <i class="fas fa-list text-primary action-icon view-purchase-details" 
                                   data-id="<?= $p['id']; ?>"
                                   data-bs-toggle="modal" 
                                   data-bs-target="#purchaseDetailsModal"
                                   title="View Items"></i>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="purchaseDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Purchase Bill Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modal-content-area">
                        <div class="text-center py-5">
                            <div class="spinner-border text-danger"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.view-purchase-details').on('click', function() {
                const purchaseId = $(this).data('id');
                $('#modal-content-area').html('<div class="text-center py-5"><div class="spinner-border text-danger"></div></div>');

                $.ajax({
                    url: 'fetch_purchase_details.php',
                    method: 'GET',
                    data: { purchase_id: purchaseId },
                    success: function(response) {
                        $('#modal-content-area').html(response);
                    }
                });
            });
        });
    </script>
</body>
</html>