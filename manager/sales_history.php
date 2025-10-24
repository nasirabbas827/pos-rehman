<?php
session_start();
// --- AUTHENTICATION: Check for manager role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$manager_id = $_SESSION["id"];
$sales_data = [];
$error_message = "";
$branch_name = "N/A";
$branch_id = null; // Initialize variable

// Fetch manager info and branch ID
$sql_manager = "SELECT b.name, m.branch_id FROM managers m JOIN branches b ON m.branch_id = b.id WHERE m.id = ?";
$stmt_m = mysqli_prepare($conn, $sql_manager);
mysqli_stmt_bind_param($stmt_m, "i", $manager_id);
mysqli_stmt_execute($stmt_m);
mysqli_stmt_bind_result($stmt_m, $branch_name, $branch_id);
mysqli_stmt_fetch($stmt_m);
mysqli_stmt_close($stmt_m);

// Check if branch ID is available
if (!$branch_id) {
    $error_message = "Error: Manager is not linked to a branch.";
} else {
    // Fetch all sales for this branch
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
        WHERE s.branch_id = ?
        ORDER BY s.sale_date DESC";

    $stmt_sales = mysqli_prepare($conn, $sql_sales);
    if ($stmt_sales) {
        mysqli_stmt_bind_param($stmt_sales, "i", $branch_id);
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
// mysqli_close($conn); // Connection is closed in the separate file for the AJAX call
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Branch Sales History - Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin-left: 280px; 
        }
        .container {
            padding: 2rem 1rem;
        }
        h2 {
            color: #0b5394;
            font-size: clamp(1.6rem, 4vw, 2rem);
            margin-bottom: 1.5rem;
        }
        .table {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden; 
        }
        .table th {
            background-color: #d9e6f7; /* Light blue header for manager */
            font-size: 0.95rem;
        }
        .table-hover tbody tr:hover {
            background-color: #f1f6ff;
        }
        .badge-credit {
            background-color: #ffc107; 
            color: #333;
        }
        .badge-cash {
            background-color: #28a745; 
            color: #fff;
        }
        .action-icon {
            cursor: pointer;
            margin-right: 5px;
            transition: color 0.2s;
        }
        .action-icon:hover {
            color: #0d6efd !important; /* Blue hover for better UX */
        }
        @media (max-width: 992px) {
            body {
                margin-left: 0;
            }
        }
        .modal-header {
            background-color: #0b5394; /* Manager header color */
            color: white;
            border-radius: 6px 6px 0 0;
        }
    </style>
</head>
<body>
    
    <?php include('manager_sidebar.php'); // Assuming the sidebar file is named this way ?>

    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-store me-2"></i>Sales History for <?php echo htmlspecialchars($branch_name); ?> Branch</h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (empty($sales_data)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-1"></i> No sales transactions found for your branch.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-receipt me-1"></i> ID</th>
                            <th><i class="fas fa-user me-1"></i> Salesperson</th>
                            <th><i class="fas fa-user-tag me-1"></i> Customer</th>
                            <th><i class="fas fa-calendar-alt me-1"></i> Date/Time</th>
                            <th><i class="fas fa-rupee-sign me-1"></i> Total Amt</th>
                            <th><i class="fas fa-money-check-alt me-1"></i> Cash Received</th>
                            <th><i class="fas fa-credit-card me-1"></i> Method</th>
                            <th><i class="fas fa-cogs me-1"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $sale): 
                            $khata_change = $sale['total_amount'] - $sale['cash_received'];
                        ?>
                            <tr>
                                <td><?php echo $sale['id']; ?></td>
                                <td class="sale-salesperson-name"><?php echo htmlspecialchars($sale['salesperson_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'Guest Customer'); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($sale['sale_date'])); ?></td>
                                <td class="fw-bold"><?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo number_format($sale['cash_received'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo ($sale['payment_method'] == 'Credit') ? 'badge-credit' : 'badge-cash'; ?>">
                                        <?php echo htmlspecialchars($sale['payment_method']); ?>
                                    </span>
                                    <?php if ($sale['payment_method'] == 'Credit' && $khata_change != 0): ?>
                                        <br><small class="text-<?php echo $khata_change > 0 ? 'danger' : 'success'; ?> fw-bold">
                                            <?php if ($khata_change > 0): ?>
                                                Udhaar: <?php echo number_format($khata_change, 2); ?>
                                            <?php else: ?>
                                                Advance: <?php echo number_format(abs($khata_change), 2); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas fa-eye text-success action-icon me-2 view-sale-details" 
                                       data-sale-id="<?php echo $sale['id']; ?>"
                                       data-bs-toggle="modal" 
                                       data-bs-target="#saleDetailsModal"
                                       title="View Details"></i>
                                       
                                    <a href="print_invoice.php?sale_id=<?php echo $sale['id']; ?>" target="_blank" title="Print/View Invoice">
                                        <i class="fas fa-print text-primary action-icon"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="saleDetailsModal" tabindex="-1" aria-labelledby="saleDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saleDetailsModalLabel">Sale Details - ID: <span id="modal-sale-id">...</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6"><strong>Salesperson:</strong> <span id="modal-salesperson-name">...</span></div>
                        <div class="col-md-6"><strong>Customer:</strong> <span id="modal-customer-name">...</span></div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-4"><strong>Sale Date:</strong> <span id="modal-sale-date">...</span></div>
                        <div class="col-md-4"><strong>Payment Method:</strong> <span id="modal-payment-method">...</span></div>
                        <div class="col-md-4"><strong>Total Amount:</strong> <span id="modal-total-amount" class="text-primary fw-bold">...</span></div>
                    </div>

                    <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-list-alt me-1"></i> Products Sold</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Name</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="sale-items-body">
                                </tbody>
                            <tfoot id="sale-summary-footer">
                                </tfoot>
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
            // Event listener for the "View Details" icon
            $('.view-sale-details').on('click', function() {
                const saleId = $(this).data('sale-id');
                
                // Update modal general info immediately from table row data
                const $row = $(this).closest('tr');
                const salespersonName = $row.find('td:eq(1)').text().trim(); // Salesperson is now column 1
                const customerName = $row.find('td:eq(2)').text().trim(); // Customer is now column 2
                const saleDate = $row.find('td:eq(3)').text().trim(); // Date is now column 3
                const totalAmount = $row.find('td:eq(4)').text().trim(); // Total Amt is now column 4
                const paymentMethod = $row.find('td:eq(6)').find('.badge').text().trim(); // Method is column 6

                $('#modal-sale-id').text(saleId);
                $('#modal-salesperson-name').text(salespersonName);
                $('#modal-customer-name').text(customerName);
                $('#modal-sale-date').text(saleDate);
                $('#modal-total-amount').text('₹' + totalAmount);
                $('#modal-payment-method').text(paymentMethod);
                $('#modal-print-link').attr('href', 'print_invoice.php?sale_id=' + saleId);

                // Clear previous item data and show a loading message
                $('#sale-items-body').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading details...</td></tr>');
                $('#sale-summary-footer').empty();

                // AJAX call to fetch detailed sales items
                $.ajax({
                    url: 'fetch_sale_details.php', // This file handles both roles now
                    method: 'GET',
                    data: { sale_id: saleId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let itemsHtml = '';
                            let grandTotal = 0;
                            let totalDiscount = 0;

                            // 1. Build the items table rows
                            response.items.forEach(item => {
                                const lineTotal = parseFloat(item.line_total);
                                grandTotal += lineTotal;
                                totalDiscount += parseFloat(item.discount);
                                
                                itemsHtml += `
                                    <tr>
                                        <td>${item.product_name}</td>
                                        <td class="text-end">${item.quantity} ${item.unit}</td>
                                        <td class="text-end">${parseFloat(item.unit_price).toFixed(2)}</td>
                                        <td class="text-end text-danger">${parseFloat(item.discount).toFixed(2)}</td>
                                        <td class="text-end fw-bold">${lineTotal.toFixed(2)}</td>
                                    </tr>
                                `;
                            });
                            
                            $('#sale-items-body').html(itemsHtml);

                            // 2. Build the summary footer
                            let footerHtml = `
                                <tr>
                                    <th colspan="3" class="text-end">Total Discount:</th>
                                    <th class="text-end text-danger">${totalDiscount.toFixed(2)}</th>
                                    <td></td>
                                </tr>
                                <tr>
                                    <th colspan="4" class="text-end">GRAND TOTAL:</th>
                                    <th class="text-end text-primary">${grandTotal.toFixed(2)}</th>
                                </tr>
                            `;

                            // 3. Add cash received and credit/change info
                            const saleInfo = response.sale_info;
                            const totalAmt = parseFloat(saleInfo.total_amount);
                            const cashRcvd = parseFloat(saleInfo.cash_received);

                            if(cashRcvd > 0) {
                                footerHtml += `
                                    <tr>
                                        <th colspan="4" class="text-end">Cash Received:</th>
                                        <th class="text-end text-success">${cashRcvd.toFixed(2)}</th>
                                    </tr>
                                `;
                            }
                            
                            if(saleInfo.payment_method === 'Credit' && totalAmt > cashRcvd) {
                                const udhaar = totalAmt - cashRcvd;
                                footerHtml += `
                                    <tr>
                                        <th colspan="4" class="text-end text-danger">Udhaar/Credit Remaining:</th>
                                        <th class="text-end text-danger fw-bold">${udhaar.toFixed(2)}</th>
                                    </tr>
                                `;
                            } else if (cashRcvd > totalAmt) {
                                const change = cashRcvd - totalAmt;
                                footerHtml += `
                                    <tr>
                                        <th colspan="4" class="text-end text-info">Change/Return:</th>
                                        <th class="text-end text-info fw-bold">${change.toFixed(2)}</th>
                                    </tr>
                                `;
                            }
                            
                            $('#sale-summary-footer').html(footerHtml);

                        } else {
                            $('#sale-items-body').html(`<tr><td colspan="5" class="text-center text-danger">${response.message}</td></tr>`);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", status, error);
                        $('#sale-items-body').html('<tr><td colspan="5" class="text-center text-danger">An unexpected error occurred while fetching data.</td></tr>');
                    }
                });
            });
        });
    </script>
</body>
</html>