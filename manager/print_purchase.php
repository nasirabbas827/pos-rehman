<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$purchase_id = (int)($_GET['purchase_id'] ?? 0);
if ($purchase_id <= 0) die("Invalid purchase ID");

$sql = "SELECT p.*, m.full_name AS manager_name 
        FROM purchases p
        LEFT JOIN managers m ON p.manager_id = m.id
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $purchase_id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) die("Purchase not found.");

$sql_items = "SELECT pi.*, pr.name AS product_name 
              FROM purchase_items pi
              JOIN products pr ON pi.product_id = pr.id
              WHERE pi.purchase_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $purchase_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase #<?= $purchase_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        .invoice { max-width: 800px; margin: 30px auto; padding: 20px; background: white; border: 1px solid #ddd; }
        .header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; }
        th { background: #e9f5ff; }
        .total-row { font-weight: bold; font-size: 1.1rem; border-top: 2px solid #000; }
        @media print {
            body { background: white; margin: 0; }
            .no-print { display: none; }
            .invoice { border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

<div class="no-print text-center my-4">
    <button class="btn btn-primary" onclick="window.print()">Print Purchase</button>
    <a href="purchases.php" class="btn btn-secondary ms-2">Back</a>
</div>

<div class="invoice">
    <div class="header">
        <h3>PURCHASE INVOICE</h3>
        <p><strong><?= htmlspecialchars($purchase['branch_name'] ?? 'Branch') ?></strong></p>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <strong>Supplier:</strong><br>
            <?= htmlspecialchars($purchase['supplier_name']) ?><br>
            <?php if ($purchase['supplier_phone']): ?>
                Phone: <?= htmlspecialchars($purchase['supplier_phone']) ?><br>
            <?php endif; ?>
            <?php if ($purchase['supplier_email']): ?>
                Email: <?= htmlspecialchars($purchase['supplier_email']) ?>
            <?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <strong>Invoice #:</strong> PUR-<?= str_pad($purchase_id, 6, '0', STR_PAD_LEFT) ?><br>
            <strong>Date:</strong> <?= date('d-m-Y H:i', strtotime($purchase['purchase_date'])) ?><br>
            <strong>Recorded by:</strong> <?= htmlspecialchars($purchase['manager_name'] ?? 'Manager') ?>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Discount</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-end"><?= $item['quantity'] ?></td>
                <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                <td class="text-end"><?= number_format($item['discount'], 2) ?></td>
                <td class="text-end"><?= number_format($item['line_total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-end">Grand Total:</td>
                <td class="text-end"><?= number_format($purchase['grand_total'], 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-4">
        <strong>Payment Method:</strong> <?= htmlspecialchars($purchase['payment_method']) ?><br>
        <strong>Status:</strong> <?= htmlspecialchars(ucfirst($purchase['payment_status'])) ?>
    </div>

    <div class="text-center mt-5">
        <small>Thank you for your business • Karobar POS</small>
    </div>
</div>

</body>
</html>