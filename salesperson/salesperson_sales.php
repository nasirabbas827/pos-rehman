<?php
session_start();
// --- AUTHENTICATION: Check for salesperson role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "salesperson") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$salesperson_id = $_SESSION["id"];
$sales_data = [];
$error_message = "";
$full_name = ""; 
$branch_id = null; 

// --- DATE FILTER LOGIC ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Fetch salesperson info
$sql_sp = "SELECT name, branch_id FROM salespersons WHERE id = ?";
$stmt_sp = mysqli_prepare($conn, $sql_sp);
mysqli_stmt_bind_param($stmt_sp, "i", $salesperson_id);
mysqli_stmt_execute($stmt_sp);
mysqli_stmt_bind_result($stmt_sp, $full_name, $branch_id);
mysqli_stmt_fetch($stmt_sp);
mysqli_stmt_close($stmt_sp);

// Check if branch ID is available
if (!$branch_id) {
    $error_message = "Error: Salesperson is not linked to a branch.";
} else {
    // Build SQL Query with dynamic date filtering
    $sql_sales = "
        SELECT 
            s.id, 
            s.sale_date, 
            s.total_amount, 
            s.cash_received,
            s.payment_method, 
            c.name AS customer_name,
            sp.name AS salesperson_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN salespersons sp ON s.salesperson_id = sp.id
        WHERE s.salesperson_id = ?";

    // Add Date Conditions if both dates are provided
    if (!empty($start_date) && !empty($end_date)) {
        $sql_sales .= " AND DATE(s.sale_date) BETWEEN ? AND ?";
    }

    $sql_sales .= " ORDER BY s.sale_date DESC";

    $stmt_sales = mysqli_prepare($conn, $sql_sales);
    if ($stmt_sales) {
        if (!empty($start_date) && !empty($end_date)) {
            mysqli_stmt_bind_param($stmt_sales, "iss", $salesperson_id, $start_date, $end_date);
        } else {
            mysqli_stmt_bind_param($stmt_sales, "i", $salesperson_id);
        }
        
        mysqli_stmt_execute($stmt_sales);
        $result_sales = mysqli_stmt_get_result($stmt_sales);

        while ($row = mysqli_fetch_assoc($result_sales)) {
            $sales_data[] = $row;
        }
        mysqli_stmt_close($stmt_sales);
    } else {
        $error_message = "Database error: Could not prepare the sales query.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>My Sales - POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background-color: #e6effc; font-family: 'Segoe UI', Arial, sans-serif; margin-left: 280px; transition: 0.3s; }
        .container { padding: 2rem 1rem; }
        h2 { color: #1e3a8a; font-weight: bold; }
        
        /* Filter Section Styles */
        .filter-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        
        .table-container { background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 15px; }
        .badge-credit { background-color: #ffc107; color: #333; }
        .badge-cash { background-color: #28a745; color: #fff; }
        .action-icon { cursor: pointer; transition: 0.2s; }
        .action-icon:hover { transform: scale(1.2); }

        @media (max-width: 992px) { body { margin-left: 0; } }
        .modal-header { background-color: #1e3a8a; color: white; }
    </style>
</head>
<body>
    
    <?php include('salesperson_sidebar.php'); ?>

    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-chart-line me-2"></i>My Sales Transactions</h2>

        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-filter me-2"></i>Filter</button>
                    <a href="salesperson_sales.php" class="btn btn-outline-secondary px-4">Reset</a>
                </div>
            </form>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="table-container">
            <?php if (empty($sales_data)): ?>
                <div class="alert alert-info text-center py-4">
                    <i class="fas fa-info-circle me-1"></i> No transactions found for the selected period.
                </div>
            <?php else: ?>
<table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Date/Time</th>
                                <th>Total Amt</th>
                                <th>Cash Received</th>
                                <th>Method</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_data as $sale): 
                                $khata_change = $sale['total_amount'] - $sale['cash_received'];
                            ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $sale['id']; ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'Guest'); ?></td>
                                <td class="small"><?php echo date('d M Y, h:i A', strtotime($sale['sale_date'])); ?></td>
                                <td class="fw-bold">RS <?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td class="text-success">RS <?php echo number_format($sale['cash_received'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo ($sale['payment_method'] == 'Credit') ? 'badge-credit' : 'badge-cash'; ?>">
                                        <?php echo htmlspecialchars($sale['payment_method']); ?>
                                    </span>
                                    <?php if ($sale['payment_method'] == 'Credit' && $khata_change > 0): ?>
                                        <div class="text-danger fw-bold" style="font-size:0.75rem">Due: <?php echo number_format($khata_change, 2); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas fa-eye text-success action-icon me-3 view-sale-details" 
                                       data-sale-id="<?php echo $sale['id']; ?>"
                                       data-bs-toggle="modal" 
                                       data-bs-target="#saleDetailsModal"
                                       title="View Details"></i>
                                    <a href="print_invoice.php?sale_id=<?php echo $sale['id']; ?>" target="_blank">
                                        <i class="fas fa-print text-primary action-icon" title="Print Invoice"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="saleDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sale Details - ID: <span id="modal-sale-id">...</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6"><strong>Customer:</strong> <span id="modal-customer-name">...</span></div>
                        <div class="col-md-6 text-md-end"><strong>Date:</strong> <span id="modal-sale-date">...</span></div>
                    </div>
                    <div class="row mb-4 p-2 bg-light rounded">
                        <div class="col-md-6"><strong>Payment Method:</strong> <span id="modal-payment-method" class="badge bg-secondary">...</span></div>
                        <div class="col-md-6 text-md-end"><strong>Total:</strong> <span id="modal-total-amount" class="text-primary fw-bold h5">...</span></div>
                    </div>

                    <h6>Items Purchased</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Disc.</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="sale-items-body"></tbody>
                            <tfoot id="sale-summary-footer"></tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="modal-print-link" target="_blank" class="btn btn-primary"><i class="fas fa-print me-1"></i> Print Invoice</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.view-sale-details').on('click', function() {
                const saleId = $(this).data('sale-id');
                const $row = $(this).closest('tr');
                
                // Set initial modal info from the table row
                $('#modal-sale-id').text(saleId);
                $('#modal-customer-name').text($row.find('td:eq(1)').text());
                $('#modal-sale-date').text($row.find('td:eq(2)').text());
                $('#modal-total-amount').text($row.find('td:eq(3)').text());
                $('#modal-payment-method').text($row.find('.badge').first().text());
                $('#modal-print-link').attr('href', 'print_invoice.php?sale_id=' + saleId);

                $('#sale-items-body').html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');
                $('#sale-summary-footer').empty();

                $.ajax({
                    url: 'fetch_sale_details.php',
                    method: 'GET',
                    data: { sale_id: saleId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let itemsHtml = '';
                            let totalDiscount = 0;

                            response.items.forEach(item => {
                                totalDiscount += parseFloat(item.discount);
                                itemsHtml += `
                                    <tr>
                                        <td>${item.product_name}</td>
                                        <td class="text-end">${item.quantity} ${item.unit}</td>
                                        <td class="text-end">${parseFloat(item.unit_price).toFixed(2)}</td>
                                        <td class="text-end text-danger">${parseFloat(item.discount).toFixed(2)}</td>
                                        <td class="text-end fw-bold">${parseFloat(item.line_total).toFixed(2)}</td>
                                    </tr>`;
                            });
                            $('#sale-items-body').html(itemsHtml);

                            const sale = response.sale_info;
                            let footerHtml = `
                                <tr><th colspan="4" class="text-end">Total Discount:</th><th class="text-end text-danger">${totalDiscount.toFixed(2)}</th></tr>
                                <tr><th colspan="4" class="text-end">Grand Total:</th><th class="text-end text-primary">${parseFloat(sale.total_amount).toFixed(2)}</th></tr>
                                <tr><th colspan="4" class="text-end">Cash Received:</th><th class="text-end text-success">${parseFloat(sale.cash_received).toFixed(2)}</th></tr>`;
                            
                            $('#sale-summary-footer').html(footerHtml);
                        } else {
                            $('#sale-items-body').html(`<tr><td colspan="5" class="text-center text-danger">${response.message}</td></tr>`);
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>