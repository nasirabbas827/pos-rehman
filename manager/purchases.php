<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$manager_id = $_SESSION["id"];

// Get manager & branch info
$stmt = $conn->prepare("SELECT m.full_name, m.branch_id, b.name as branch_name 
                        FROM managers m 
                        JOIN branches b ON m.branch_id = b.id 
                        WHERE m.id = ?");
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$manager_data = $stmt->get_result()->fetch_assoc();
$branch_id   = $manager_data['branch_id'];
$branch_name = $manager_data['branch_name'];
$manager_name = $manager_data['full_name'];
$stmt->close();

if (!$branch_id) {
    die("Error: Manager is not assigned to any branch.");
}

// =========================================================================
// AJAX: Search Products (only this branch)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'search_products') {
    header('Content-Type: application/json');
    $term = isset($_GET['term']) ? "%" . $_GET['term'] . "%" : "%";
    $sql = "SELECT id, name, price, quantity 
            FROM products 
            WHERE branch_id = ? AND (name LIKE ? OR barcode_id LIKE ?) 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $branch_id, $term, $term);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'text' => htmlspecialchars($row['name'] . ' (Stock: ' . $row['quantity'] . ')'),
            'name' => htmlspecialchars($row['name']),
            'price' => (float)$row['price'],           // selling price (just for reference)
            'quantity_available' => (int)$row['quantity']
        ];
    }
    $stmt->close();
    echo json_encode(['results' => $products]);
    exit();
}

// =========================================================================
// FORM SUBMISSION → Create Purchase
// =========================================================================
$purchase_error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['finalize_purchase'])) {
    $supplier_name  = trim($_POST['supplier_name'] ?? '');
    $supplier_phone = trim($_POST['supplier_phone'] ?? '');
    $supplier_email = trim($_POST['supplier_email'] ?? '');
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $grand_total    = (float)($_POST['grand_total'] ?? 0);
    $items          = $_POST['items'] ?? [];

    if (empty($supplier_name) || $grand_total <= 0 || empty($items)) {
        $purchase_error = "Supplier name, items and valid total amount are required.";
    } else {
        $conn->begin_transaction();
        try {
            // Insert purchase header
            $sql = "INSERT INTO purchases (
                branch_id, manager_id, supplier_name, supplier_phone, supplier_email,
                invoice_number, total_amount, grand_total, payment_method
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", 
                $branch_id, $manager_id, $supplier_name, $supplier_phone, $supplier_email,
                $invoice_number, $grand_total, $grand_total, $payment_method
            );
            $stmt->execute();
            $purchase_id = $conn->insert_id;
            $stmt->close();

            // Insert items & update stock
            foreach ($items as $item) {
                $product_id = (int)($item['product_id'] ?? 0);
                $qty        = (int)($item['quantity'] ?? 0);
                $buy_price  = (float)($item['unit_price'] ?? 0);
                $discount   = (float)($item['discount'] ?? 0);

                if ($product_id > 0 && $qty > 0 && $buy_price >= 0) {
                    // Insert item
                    $sql_item = "INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_price, discount) 
                                 VALUES (?, ?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_item);
                    $stmt_item->bind_param("iiidd", $purchase_id, $product_id, $qty, $buy_price, $discount);
                    $stmt_item->execute();
                    $stmt_item->close();

                    // Increase stock
                    $sql_stock = "UPDATE products SET quantity = quantity + ? WHERE id = ? AND branch_id = ?";
                    $stmt_stock = $conn->prepare($sql_stock);
                    $stmt_stock->bind_param("iii", $qty, $product_id, $branch_id);
                    $stmt_stock->execute();
                    $stmt_stock->close();
                }
            }

            $conn->commit();

            // Open print page in new tab & refresh
            echo "<script>
                window.open('print_purchase.php?purchase_id=$purchase_id', '_blank');
                window.location.href = 'purchases.php';
            </script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $purchase_error = "Purchase failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Purchase - <?= htmlspecialchars($branch_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1100px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table-items th { background-color: #e9f5ff; }
    </style>
</head>
<body>

<div class="container mt-4 mb-4">
    <h2><i class="fas fa-truck me-2"></i>New Purchase - <?= htmlspecialchars($branch_name) ?></h2>
    
    <div class="d-flex justify-content-end mb-3">
        <a href="manager_dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($purchase_error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($purchase_error) ?></div>
    <?php endif; ?>

    <div class="card p-4">
        <form method="POST" id="purchaseForm">
            <div class="row mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Supplier Name *</label>
                    <input type="text" name="supplier_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Phone</label>
                    <input type="text" name="supplier_phone" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" name="supplier_email" class="form-control">
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Supplier Invoice #</label>
                    <input type="text" name="invoice_number" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="Cash">Cash</option>
                        <option value="Bank">Bank Transfer</option>
                        <option value="Credit">Credit (On Account)</option>
                        <option value="Mobile">Mobile Payment</option>
                    </select>
                </div>
            </div>

            <h4 class="mb-3"><i class="fas fa-boxes-stacked"></i> Purchased Items</h4>

            <div class="table-responsive">
                <table class="table table-bordered table-items" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:40%">Product</th>
                            <th style="width:12%">Qty</th>
                            <th style="width:18%">Purchase Price / unit</th>
                            <th style="width:15%">Discount / unit</th>
                            <th style="width:15%">Line Total</th>
                            <th style="width:10%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="text-center text-muted">Click "Add Item" to begin</td></tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold fs-5 text-primary">GRAND TOTAL:</td>
                            <td class="fw-bold fs-5 text-primary" id="grandTotalDisplay">0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <input type="hidden" name="grand_total" id="grandTotalInput" value="0">

            <div class="mt-4">
                <button type="button" class="btn btn-primary" id="addItemBtn">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                <button type="submit" name="finalize_purchase" class="btn btn-success float-end">
                    <i class="fas fa-check"></i> Finalize Purchase
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
let itemCounter = 0;

$(document).ready(function() {

    $('#addItemBtn').click(function() {
        if ($('#itemsTable tbody tr').length === 1 && $('#itemsTable tbody tr td').text().includes('Click')) {
            $('#itemsTable tbody').empty();
        }
        addItemRow();
    });

    function addItemRow() {
        itemCounter++;
        const html = `
            <tr data-row="${itemCounter}">
                <td>
                    <select name="items[${itemCounter}][product_id]" class="form-select product-select" required>
                        <option value="">Search product...</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${itemCounter}][quantity]" class="form-control item-qty" value="1" min="1" required>
                </td>
                <td>
                    <input type="number" name="items[${itemCounter}][unit_price]" class="form-control item-price" step="0.01" min="0" required>
                </td>
                <td>
                    <input type="number" name="items[${itemCounter}][discount]" class="form-control item-discount" value="0.00" step="0.01" min="0">
                </td>
                <td>
                    <span class="line-total fw-bold">0.00</span>
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        $('#itemsTable tbody').append(html);

        $(`select[name="items[${itemCounter}][product_id]"]`).select2({
            placeholder: "Search product...",
            ajax: {
                url: 'purchases.php',
                dataType: 'json',
                delay: 250,
                data: params => ({ action: 'search_products', term: params.term }),
                processResults: data => ({ results: data.results })
            }
        }).on('select2:select', function(e) {
            const data = e.params.data;
            const row = $(this).closest('tr');
            row.find('.item-price').val(data.price.toFixed(2)); // default to selling price – manager can change
            calculateTotal();
        });
    }

    $('#itemsTable').on('input', '.item-qty, .item-price, .item-discount', calculateTotal);

    $('#itemsTable').on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
        if ($('#itemsTable tbody tr').length === 0) {
            $('#itemsTable tbody').html('<tr><td colspan="6" class="text-center text-muted">Click "Add Item" to begin</td></tr>');
        }
        calculateTotal();
    });

    function calculateTotal() {
        let total = 0;
        $('#itemsTable tbody tr').each(function() {
            const qty = parseFloat($(this).find('.item-qty').val()) || 0;
            const price = parseFloat($(this).find('.item-price').val()) || 0;
            const disc = parseFloat($(this).find('.item-discount').val()) || 0;
            const line = qty * (price - disc);
            $(this).find('.line-total').text(line.toFixed(2));
            total += line;
        });
        $('#grandTotalDisplay').text(total.toFixed(2));
        $('#grandTotalInput').val(total.toFixed(2));
    }
});
</script>
</body>
</html>