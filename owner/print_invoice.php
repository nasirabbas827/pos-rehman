<?php
session_start();
// Authentication Check (Remains the same)
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "owner") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$sale_id = $_GET['sale_id'] ?? 0;
$invoice_data = null;
$items_data = [];

if ($sale_id > 0) {
    // 1. Fetch main Sale details (s.* includes total_amount and cash_received)
    $sql_sale = "SELECT s.*, c.name AS customer_name, c.phone_number, sp.name AS salesperson_name 
                 FROM sales s
                 JOIN customers c ON s.customer_id = c.id
                 JOIN salespersons sp ON s.salesperson_id = sp.id
                 WHERE s.id = ?";
    $stmt_sale = mysqli_prepare($conn, $sql_sale);
    mysqli_stmt_bind_param($stmt_sale, "i", $sale_id);
    mysqli_stmt_execute($stmt_sale);
    $result_sale = mysqli_stmt_get_result($stmt_sale);
    $invoice_data = mysqli_fetch_assoc($result_sale);
    mysqli_stmt_close($stmt_sale);

    // 2. Fetch Sale Items (Remains the same)
    if ($invoice_data) {
        $sql_items = "SELECT si.*, p.name AS product_name 
                      FROM sales_items si
                      JOIN products p ON si.product_id = p.id
                      WHERE si.sale_id = ?";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        mysqli_stmt_bind_param($stmt_items, "i", $sale_id);
        mysqli_stmt_execute($stmt_items);
        $result_items = mysqli_stmt_get_result($stmt_items);
        while($row = mysqli_fetch_assoc($result_items)) {
            $items_data[] = $row;
        }
        mysqli_stmt_close($stmt_items);
    }
}

// Check if data was found
if (!$invoice_data) {
    die("Invoice not found or you don't have access.");
}

// Fetch Khata balance and the net amount added by this sale
$khata_balance = null;
$khata_entry = null;

if ($invoice_data['payment_method'] === 'Credit') {
    // Fetch the khata entry created by this sale (to get the net balance added/removed)
    $sql_khata_entry = "SELECT amount FROM khata WHERE sale_id = ? LIMIT 1";
    $stmt_khata_entry = mysqli_prepare($conn, $sql_khata_entry);
    mysqli_stmt_bind_param($stmt_khata_entry, "i", $sale_id);
    mysqli_stmt_execute($stmt_khata_entry);
    $result_khata_entry = mysqli_stmt_get_result($stmt_khata_entry);
    $khata_entry = mysqli_fetch_assoc($result_khata_entry);
    mysqli_stmt_close($stmt_khata_entry);

    
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $sale_id; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ... (CSS Styles remain the same) ... */
        body { font-family: 'Arial', sans-serif; background-color: #f8f9fa; }
        .invoice-container { max-width: 80mm; margin: 20px auto; padding: 10px; background: #fff; border: 1px solid #ccc; font-size: 10px; }
        .invoice-header { text-align: center; border-bottom: 1px dashed #ccc; padding-bottom: 5px; margin-bottom: 10px; }
        .invoice-header h4 { margin: 0; font-size: 14px; }
        .invoice-details, .invoice-customer { margin-bottom: 10px; }
        .invoice-details p, .invoice-customer p { margin: 1px 0; }
        .invoice-table th, .invoice-table td { font-size: 9px; padding: 3px; border-bottom: none !important; }
        .invoice-table th { border-top: 1px dashed #ccc !important; border-bottom: 1px dashed #ccc !important; }
        .invoice-footer { border-top: 1px dashed #ccc; padding-top: 5px; text-align: center; }
        .total-row { font-weight: bold; border-top: 1px solid #000; }
        @media print {
            body { margin: 0; padding: 0; background: #fff; }
            .invoice-container { max-width: 100%; margin: 0; padding: 0; border: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print text-center p-3">
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
        <a href="sales.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to POS</a>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <h4>Karobar.com (Invoice)</h4>
            <p>Salesperson: <?php echo htmlspecialchars($invoice_data['salesperson_name']); ?></p>
        </div>

        <div class="invoice-details">
            <p><strong>Invoice No:</strong> INV-<?php echo str_pad($invoice_data['id'], 6, '0', STR_PAD_LEFT); ?></p>
            <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s', strtotime($invoice_data['sale_date'])); ?></p>
        </div>
        
        <div class="invoice-customer">
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice_data['customer_name']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice_data['phone_number']); ?></p>
        </div>
        
        <table class="table table-sm invoice-table">
            <thead>
                <tr>
                    <th style="width: 50%">Item</th>
                    <th style="width: 10%" class="text-end">Qty</th>
                    <th style="width: 20%" class="text-end">Price</th>
                    <th style="width: 20%" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $total_discount = 0; foreach ($items_data as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="text-end"><?php echo (int)$item['quantity']; ?></td>
                    <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($item['line_total'], 2); ?></td>
                </tr>
                <?php 
                $total_discount += $item['discount'];
                endforeach; 
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end">Total Discount:</td>
                    <td class="text-end"><?php echo number_format($total_discount, 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" class="text-end">GRAND TOTAL:</td>
                    <td class="text-end"><?php echo number_format($invoice_data['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="invoice-footer">
            <p>Payment Method: <strong><?php echo $invoice_data['payment_method']; ?></strong></p>
            
            <?php 
                $grand_total = (float)$invoice_data['total_amount'];
                $cash_received = (float)$invoice_data['cash_received'];
                $net_khata_amount = $grand_total - $cash_received;
            ?>

            <?php if ($invoice_data['payment_method'] === 'Credit'): ?>
                <p>Cash Received: <strong><?php echo number_format($cash_received, 2); ?></strong></p>
                
                <?php if ($net_khata_amount > 0): ?>
                    <p style="color:red;">Net Udhaar Added to Khata: <strong><?php echo number_format($net_khata_amount, 2); ?></strong></p>
                <?php elseif ($net_khata_amount < 0): ?>
                    <p style="color:green;">Net Advance (Jama) Recorded: <strong><?php echo number_format(abs($net_khata_amount), 2); ?></strong></p>
                <?php endif; ?>

                <?php if ($khata_balance !== null): ?>
                    <p>New Khata Balance: <strong><?php echo number_format($khata_balance, 2); ?></strong></p>
                <?php endif; ?>
            <?php else: ?>
                <p>Cash Paid: <strong><?php echo number_format($grand_total, 2); ?></strong></p>
            <?php endif; ?>

            <p>Thank you for your business!</p>
        </div>
    </div>
    
    <script>
        // Automatic print on load (Remains the same)
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>