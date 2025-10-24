<?php
session_start();
// Authentication Check
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "salesperson") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$salesperson_id = $_SESSION["id"];

// Fetch salesperson info & branch
$sql_sp = "SELECT name, branch_id FROM salespersons WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_sp);
mysqli_stmt_bind_param($stmt, "i", $salesperson_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $salesperson_name, $branch_id);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$branch_id) {
    die("Error: Salesperson is not linked to a branch.");
}

// =========================================================================
// AJAX ENDPOINT HANDLERS
// =========================================================================

// Handle AJAX request for customers
if (isset($_GET['action']) && $_GET['action'] == 'search_customers') {
    header('Content-Type: application/json');
    $term = isset($_GET['term']) ? "%" . $_GET['term'] . "%" : "%";

    $sql_cust = "SELECT id, name, phone_number FROM customers WHERE branch_id = ? AND (name LIKE ? OR phone_number LIKE ?) LIMIT 10";
    $stmt_cust = mysqli_prepare($conn, $sql_cust);
    mysqli_stmt_bind_param($stmt_cust, "iss", $branch_id, $term, $term);
    mysqli_stmt_execute($stmt_cust);
    $result_cust = mysqli_stmt_get_result($stmt_cust);

    $customers = [];
    while($row = mysqli_fetch_assoc($result_cust)) {
        $customers[] = [
            'id' => $row['id'],
            'text' => htmlspecialchars($row['name'] . ' (' . $row['phone_number'] . ')'),
            'name' => htmlspecialchars($row['name'])
        ];
    }
    mysqli_stmt_close($stmt_cust);
    echo json_encode(['results' => $customers]);
    exit();
}

// Handle AJAX request for products (Stock logic remains the same)
if (isset($_GET['action']) && $_GET['action'] == 'search_products') {
    header('Content-Type: application/json');
    $term = isset($_GET['term']) ? "%" . $_GET['term'] . "%" : "%";

    $sql_prod = "SELECT id, name, price, quantity FROM products WHERE branch_id = ? AND (name LIKE ? OR id = ?) LIMIT 10";
    $stmt_prod = mysqli_prepare($conn, $sql_prod);
    mysqli_stmt_bind_param($stmt_prod, "isi", $branch_id, $term, $term);
    mysqli_stmt_execute($stmt_prod);
    $result_prod = mysqli_stmt_get_result($stmt_prod);

    $products = [];
    while($row = mysqli_fetch_assoc($result_prod)) {
        $products[] = [
            'id' => $row['id'],
            'text' => htmlspecialchars($row['name'] . ' (Stock: ' . (int)$row['quantity'] . ')'),
            'name' => htmlspecialchars($row['name']),
            'price' => (float)$row['price'],
            'quantity_available' => (int)$row['quantity']
        ];
    }
    mysqli_stmt_close($stmt_prod);
    echo json_encode(['results' => $products]);
    exit();
}

// --- AJAX: Fetch Khata Balance ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch_khata_balance') {
    header('Content-Type: application/json');
    $customer_id = (int)($_GET['customer_id'] ?? 0);
    $balance = 0.00;

    if ($customer_id > 0) {
        $sql_balance = "SELECT SUM(amount) as total_balance FROM khata WHERE customer_id = ?";
        $stmt_balance = mysqli_prepare($conn, $sql_balance);
        mysqli_stmt_bind_param($stmt_balance, "i", $customer_id);
        mysqli_stmt_execute($stmt_balance);
        $result_balance = mysqli_stmt_get_result($stmt_balance);
        $row = mysqli_fetch_assoc($result_balance);
        $balance = (float)($row['total_balance'] ?? 0.00);
        mysqli_stmt_close($stmt_balance);
    }
    // Retain number_format here as it is only for display to the user
    echo json_encode(['balance' => number_format($balance, 2)]);
    exit();
}

// --- AJAX: Fetch Recent Discount ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch_recent_discount') {
    header('Content-Type: application/json');
    $customer_id = (int)($_GET['customer_id'] ?? 0);
    $product_id = (int)($_GET['product_id'] ?? 0);
    $recent_discount = 0.00;
    $recent_unit_price = 0.00;

    if ($customer_id > 0 && $product_id > 0) {
        $sql_discount = "
            SELECT si.discount, si.unit_price 
            FROM sales_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.customer_id = ? AND si.product_id = ?
            ORDER BY s.sale_date DESC
            LIMIT 1";
        
        $stmt_discount = mysqli_prepare($conn, $sql_discount);
        mysqli_stmt_bind_param($stmt_discount, "ii", $customer_id, $product_id);
        mysqli_stmt_execute($stmt_discount);
        mysqli_stmt_bind_result($stmt_discount, $recent_discount, $recent_unit_price);
        mysqli_stmt_fetch($stmt_discount);
        mysqli_stmt_close($stmt_discount);
    }
    
    $discount_value = $recent_discount ? (float)$recent_discount : 0.00;

    // FIX: Return raw floats instead of number_format() strings for accurate JavaScript parsing.
    echo json_encode([
        'discount' => $discount_value,
        'unit_price' => (float)$recent_unit_price
    ]);
    exit();
}

// =========================================================================
// MAIN FORM SUBMISSION (Sale Creation)
// =========================================================================
$sale_error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalize_sale'])) {
    
    // 1. Gather & Validate data
    $customer_id = $_POST['customer_id'] ?? null;
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    // cash_received is the actual cash amount paid by the customer, always taken from the input.
    $cash_received = (float)($_POST['cash_received'] ?? 0); 
    
    // ⭐ PHP FIX: Calculate the amount to be added as debt to khata (Net effect of sale)
    // The amount should be the Grand Total minus the Cash Received.
    // Positive = Debt (Udhaar), Negative = Advance (Jama)
    $khata_net_amount = $grand_total - $cash_received; // <--- This now correctly represents the net change in Khata
    
    $items = $_POST['items'] ?? [];

    if (empty($customer_id) || $grand_total <= 0 || empty($items)) {
        $sale_error_msg = "Please ensure a customer is selected and items are added to the sale.";
    } else {
        mysqli_begin_transaction($conn);
        $new_sale_id = null;

        try {
            // 2. Insert into `sales` table (with cash_received)
            // Note: $cash_received holds the actual payment amount, regardless of method.
            $sql_sale = "INSERT INTO sales (total_amount, cash_received, payment_method, branch_id, salesperson_id, customer_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_sale = mysqli_prepare($conn, $sql_sale);
            mysqli_stmt_bind_param($stmt_sale, "ddsiis", $grand_total, $cash_received, $payment_method, $branch_id, $salesperson_id, $customer_id);
            mysqli_stmt_execute($stmt_sale);
            $new_sale_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_sale);

            // 3. Loop through items, insert, and update stock
            foreach ($items as $item) {
                $product_id = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $price = (float)$item['unit_price'];
                $discount = (float)$item['discount'];
                
                if ($product_id > 0 && $qty > 0) {
                    
                    // Insert into sales_items
                    $sql_item = "INSERT INTO sales_items (sale_id, product_id, quantity, unit_price, discount) VALUES (?, ?, ?, ?, ?)";
                    $stmt_item = mysqli_prepare($conn, $sql_item);
                    // The 'discount' value is the per-unit discount amount from the form
                    mysqli_stmt_bind_param($stmt_item, "iiddi", $new_sale_id, $product_id, $qty, $price, $discount);
                    mysqli_stmt_execute($stmt_item);
                    mysqli_stmt_close($stmt_item);
                    
                    // Update product stock (DECREMENT)
                    $sql_stock = "UPDATE products SET quantity = quantity - ? WHERE id = ? AND branch_id = ?";
                    $stmt_stock = mysqli_prepare($conn, $sql_stock);
                    mysqli_stmt_bind_param($stmt_stock, "iii", $qty, $product_id, $branch_id);
                    mysqli_stmt_execute($stmt_stock);
                    mysqli_stmt_close($stmt_stock);
                }
            }

            // 4. Handle Credit (Khata) entry
            if ($payment_method === 'Credit') {
                
                // Insert into khata: 
                // khata.amount = Net effect of sale (Udhaar: positive, Advance: negative)
                $sql_khata = "INSERT INTO khata (customer_id, sale_id, amount, cash_received) VALUES (?, ?, ?, ?)";
                $stmt_khata = mysqli_prepare($conn, $sql_khata);
                mysqli_stmt_bind_param($stmt_khata, "iidd", $customer_id, $new_sale_id, $khata_net_amount, $cash_received);
                mysqli_stmt_execute($stmt_khata);
                mysqli_stmt_close($stmt_khata);
            }

            // 5. Commit transaction and redirect
            mysqli_commit($conn);
            
            echo "<script>
                    window.open('print_invoice.php?sale_id=" . $new_sale_id . "', '_blank');
                    window.location.href = 'sales.php';
                  </script>";
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $sale_error_msg = "Sale failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Sale - POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin-left: 280px;
        }
        .container { padding: 2rem 1rem; }
        h2 { color: #1e3a8a; font-size: 1.8rem; margin-bottom: 1.5rem; }
        .card { box-shadow: 0 4px 8px rgba(0,0,0,0.1); border: none; }
        .table-items th { font-size: 0.9rem; background-color: #f1f6ff; }
        .select2-container .select2-selection--single { height: calc(1.5em + 0.75rem + 2px) !important; padding-top: 5px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(1.5em + 0.75rem + 2px) !important; }

        @media (max-width: 992px) { 
            body { margin-left: 0; } 
        }
        .khata-display { 
            font-size: 1.1rem; 
            font-weight: bold; 
            padding: 5px 10px; 
            border-radius: 5px; 
            text-align: center;
        }
        .khata-debt { background-color: #fde4e1; color: #a50000; border: 1px solid #a50000; }
        .khata-credit { background-color: #e1fde7; color: #007a00; border: 1px solid #007a00; }
    </style>
</head>
<body>
    <?php include('salesperson_sidebar.php'); ?>

    <div class="container py-4">
        <h2><i class="fas fa-cash-register me-2"></i>New Sale Transaction</h2>
        
        <?php if ($sale_error_msg): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $sale_error_msg; ?></div>
        <?php endif; ?>

        <div class="card p-4">
            <form method="POST" id="saleForm">
                <div class="row mb-4 bg-light p-3 rounded">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Invoice No.</label>
                        <input type="text" class="form-control" value="INV-<?php echo time(); ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i'); ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="payment_method" class="form-label fw-bold">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-select" required>
                            <option value="Cash">Cash (Full Payment)</option>
                            <option value="Credit">Credit (Khata/Advance)</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-4 align-items-center">
                    <div class="col-md-8">
                        <label for="customer_id" class="form-label fw-bold"><i class="fas fa-user-tag me-1"></i>Select Customer</label>
                        <select id="customer_id" name="customer_id" class="form-control" style="width: 100%" required>
                            <option value="">Search Customer by Name/Phone...</option>
                        </select>
                    </div>
                    <div class="col-md-4 mt-3 mt-md-0">
                        <div id="khataBalanceDisplay" class="khata-display" style="display:none;"></div>
                    </div>
                </div>

                <h4 class="mb-3 text-primary"><i class="fas fa-cubes me-1"></i>Sale Items</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-items align-middle" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 35%">Product (Stock)</th>
                                <th style="width: 10%">Qty</th>
                                <th style="width: 15%">Unit Price</th>
                                <th style="width: 15%">Discount (Per Unit)</th>
                                <th style="width: 15%">Line Total</th>
                                <th style="width: 10%" class="action-cell">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center text-muted">Click 'Add Item' to start the sale.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold fs-5 text-success">GRAND TOTAL:</td>
                                <td class="fw-bold fs-5 text-success" id="grandTotalDisplay">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row mb-3 mt-3 align-items-center bg-warning-subtle p-3 rounded" id="cashReceivedRow" style="display:none;">
                    <div class="col-md-8 text-end">
                        <label for="cash_received_input" class="form-label fw-bold fs-5">Cash Received (Advance/Partial Payment):</label>
                    </div>
                    <div class="col-md-4">
                        <input type="number" name="cash_received" id="cash_received_input" class="form-control form-control-lg" value="0.00" step="0.01" min="0">
                        <small class="text-danger fw-bold mt-1 d-block" id="remainingCreditWarning"></small>
                    </div>
                </div>
                <input type="hidden" name="grand_total" id="grandTotalInput" value="0.00">
                
                <button type="button" class="btn btn-primary add-row-btn me-2 mt-3" id="addItemBtn">
                    <i class="fas fa-plus-circle"></i> Add Item
                </button>
                <button type="submit" name="finalize_sale" class="btn btn-success float-end mt-3">
                    <i class="fas fa-check"></i> Finalize Sale
                </button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    let itemCounter = 0;

    $(document).ready(function() {
        
        // --- 1. Customer Dropdown (Select2 AJAX) ---
        $('#customer_id').select2({
            theme: "bootstrap",
            placeholder: "Search Customer by Name/Phone...",
            allowClear: true,
            ajax: {
                url: 'sales.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { action: 'search_customers', term: params.term };
                },
                processResults: function (data) {
                    return { results: data.results };
                },
                cache: true
            }
        }).on('select2:select', function (e) {
            const customerId = e.params.data.id;
            fetchKhataBalance(customerId);
            // Clear items table to avoid using old discount/price context
            $('#itemsTable tbody').empty().append('<tr><td colspan="6" class="text-center text-muted">Click \'Add Item\' to start the sale.</td></tr>');
            calculateTotal();

        }).on('select2:clear', function() {
            $('#khataBalanceDisplay').hide().removeClass('khata-debt khata-credit alert-secondary');
        });

        // Khata Balance Fetching function
        function fetchKhataBalance(customerId) {
            $.getJSON('sales.php?action=fetch_khata_balance&customer_id=' + customerId, function(data) {
                // Note: The Khata balance data still contains number_format, so we clean it.
                const balance = parseFloat(data.balance.replace(/,/g, '')) || 0; 
                const display = $('#khataBalanceDisplay');
                
                display.removeClass('khata-debt khata-credit alert-secondary').show();
                
                if (balance > 0) {
                    display.addClass('khata-debt').html(`<i class="fas fa-exclamation-triangle"></i> Khata Udhaar: <strong>${balance.toFixed(2)}</strong>`);
                } else if (balance < 0) {
                    display.addClass('khata-credit').html(`<i class="fas fa-check-circle"></i> Khata Advance: <strong>${Math.abs(balance).toFixed(2)}</strong>`);
                } else {
                    display.addClass('alert-secondary').html(`Khata Balance: <strong>0.00</strong>`);
                }
            });
        }

        // --- 2. Add New Item Row Handler ---
        $('#addItemBtn').on('click', function() {
            if ($('#itemsTable tbody tr:first').find('td').length === 6 && $('#itemsTable tbody tr:first').text().includes('Click \'Add Item\'')) {
                $('#itemsTable tbody').empty();
            }
            addItemRow();
        });

        // --- 3. Dynamic Row HTML Generation ---
        function addItemRow() {
            itemCounter++;
            const rowHtml = `
                <tr data-id="${itemCounter}">
                    <td>
                        <select name="items[${itemCounter}][product_id]" class="form-select form-select-sm product-select" style="width: 100%;" required>
                            <option value="">Search Product...</option>
                        </select>
                        <input type="hidden" name="items[${itemCounter}][max_qty]" class="max-qty-input" value="0">
                    </td>
                    <td>
                        <input type="number" name="items[${itemCounter}][quantity]" class="form-control form-control-sm item-qty" value="1" min="1" required>
                        <small class="text-danger stock-error" style="display:none;"></small>
                    </td>
                    <td>
                        <input type="number" name="items[${itemCounter}][unit_price]" class="form-control form-control-sm item-price" value="0.00" step="0.01" min="0" readonly>
                    </td>
                    <td>
                        <input type="number" name="items[${itemCounter}][discount]" class="form-control form-control-sm item-discount" value="0.00" step="0.01" min="0">
                        <small class="text-info recent-discount-info" style="display:none;"></small>
                    </td>
                    <td>
                        <span class="line-total-display fw-bold">0.00</span>
                        <input type="hidden" name="items[${itemCounter}][line_total]" class="line-total-input" value="0.00">
                    </td>
                    <td class="action-cell">
                        <button type="button" class="btn btn-danger btn-sm remove-item"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            $('#itemsTable tbody').append(rowHtml);
            
            // Initialize Select2 for the new product dropdown
            $(`#itemsTable tbody tr[data-id="${itemCounter}"] .product-select`).select2({
                theme: "bootstrap",
                placeholder: "Search Product...",
                dropdownParent: $('#itemsTable'),
                ajax: {
                    url: 'sales.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { action: 'search_products', term: params.term };
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    },
                    cache: true
                }
            }).on('select2:select', function (e) {
                const data = e.params.data;
                const row = $(this).closest('tr');
                const customerId = $('#customer_id').val();
                
                // Set the stock and price
                row.find('.item-price').val(data.price.toFixed(2));
                row.find('.max-qty-input').val(data.quantity_available);
                row.find('.item-qty').val(1).trigger('input'); 
                
                // Fetch recent discount if a customer is selected
                if (customerId) {
                    fetchRecentDiscount(customerId, data.id, row);
                }
            });
        }
        
        // --- Fetch Recent Discount Function ---
        function fetchRecentDiscount(customerId, productId, row) {
            const infoEl = row.find('.recent-discount-info');
            infoEl.hide().text('');
            
            $.getJSON(`sales.php?action=fetch_recent_discount&customer_id=${customerId}&product_id=${productId}`, function(data) {
                // The flat discount AMOUNT recorded in the last sale
                const lastDiscount = parseFloat(data.discount) || 0; 
                // The UNIT PRICE recorded in the last sale 
                const lastPrice = parseFloat(data.unit_price) || 0;  
                
                // The current unit price for the product from the products table
                const currentProductPrice = parseFloat(row.find('.item-price').val()) || 0; 
                
                let priceWasUpdated = false;
                
                // LOGIC 1: Apply Last Price if it was lower (Customer protection)
                if (lastPrice > 0 && currentProductPrice > lastPrice) {
                    // Price has increased since last sale, set the price back to the lower last-sale price.
                    row.find('.item-price').val(lastPrice.toFixed(2));
                    priceWasUpdated = true;
                } 
                
                // LOGIC 2: Apply Last Discount Amount
                if (lastDiscount > 0) {
                    // Set the discount input to the flat amount (e.g., 500.00)
                    row.find('.item-discount').val(lastDiscount.toFixed(2)); 
                } else {
                    // Ensure discount is reset if the last one was 0
                    row.find('.item-discount').val(0.00); 
                }

                // LOGIC 3: Update Info Message (Using .toFixed(2) for clean display)
                let infoMessage = '';
                if (lastDiscount > 0) {
                    infoMessage += `Last Per-Unit Discount: ${lastDiscount.toFixed(2)}`;
                }
                
                if (priceWasUpdated) {
                     // Use the potentially updated price (lastPrice) for display
                     infoMessage += (infoMessage ? ' | ' : '') + `Price set to: ${lastPrice.toFixed(2)}`;
                }
                
                if (infoMessage) {
                    infoEl.text(infoMessage).show();
                } else {
                     infoEl.hide();
                }
                
                calculateTotal();
            });
        }

        // --- 4. Event Delegation for Calculations and Validation ---
        $('#itemsTable').on('input', '.item-qty, .item-discount', function() {
            validateStock($(this).closest('tr'));
            calculateTotal();
        });

        // Stock Validation Function
        function validateStock(row) {
            const qtyInput = row.find('.item-qty');
            const qty = parseInt(qtyInput.val()) || 0;
            const maxQty = parseInt(row.find('.max-qty-input').val()) || 0;
            const errorEl = row.find('.stock-error');
            
            if (qty > maxQty) {
                errorEl.text(`Max stock: ${maxQty}`).show();
                qtyInput.addClass('is-invalid');
            } else {
                errorEl.hide();
                qtyInput.removeClass('is-invalid');
            }
            checkGlobalValidation();
        }
        
        function checkGlobalValidation() {
            let hasError = false;
            if (!$('#customer_id').val()) hasError = true; 
            $('#itemsTable tbody tr').each(function() {
                if ($(this).find('.item-qty').hasClass('is-invalid')) {
                    hasError = true;
                }
            });
            $('#saleForm button[type="submit"]').prop('disabled', hasError);
        }

        // --- 5. Row Removal ---
        $('#itemsTable').on('click', '.remove-item', function() {
            $(this).closest('tr').remove();
            if ($('#itemsTable tbody tr').length === 0) {
                $('#itemsTable tbody').append('<tr><td colspan="6" class="text-center text-muted">Click \'Add Item\' to start the sale.</td></tr>');
            }
            calculateTotal();
            checkGlobalValidation();
        });

        // --- 6. Calculation Function (Updated Discount Formula) ---
        function calculateTotal() {
            let grandTotal = 0;
            
            $('#itemsTable tbody tr').each(function() {
                const row = $(this);
                const qty = parseInt(row.find('.item-qty').val()) || 0;
                const price = parseFloat(row.find('.item-price').val()) || 0;
                // 'discount' is now the per-unit discount amount
                let discount = parseFloat(row.find('.item-discount').val()) || 0;

                // ⭐ JS FIX: Line total calculation: quantity * (unit_price - discount)
                let lineTotal = qty * (price - discount); 
                if (lineTotal < 0) lineTotal = 0;
                
                row.find('.line-total-display').text(lineTotal.toFixed(2));
                row.find('.line-total-input').val(lineTotal.toFixed(2));
                
                grandTotal += lineTotal;
            });

            $('#grandTotalDisplay').text(grandTotal.toFixed(2));
            $('#grandTotalInput').val(grandTotal.toFixed(2));
            
            if ($('#payment_method').val() === 'Credit') {
                updateCreditRemaining();
            } else {
                // Ensure cash received is always updated to grand total for 'Cash' sales
                $('#cash_received_input').val(grandTotal.toFixed(2));
            }
            checkGlobalValidation(); 
        }
        
        // --- 7. Payment Method & Cash Received Handlers ---
        $('#payment_method').on('change', function() {
            const method = $(this).val();
            const cashReceivedRow = $('#cashReceivedRow');
            const grandTotal = parseFloat($('#grandTotalInput').val()) || 0;

            if (method === 'Credit') {
                cashReceivedRow.show();
                $('#cash_received_input').val(0.00); 
                updateCreditRemaining();
            } else {
                // For Cash sales, cash received defaults to grand total
                cashReceivedRow.hide();
                $('#cash_received_input').val(grandTotal.toFixed(2));
                $('#remainingCreditWarning').text('');
            }
        }).trigger('change'); 
        
        $('#cash_received_input').on('input', function() {
            updateCreditRemaining();
        });

        function updateCreditRemaining() {
            const grandTotal = parseFloat($('#grandTotalInput').val()) || 0;
            let cashReceived = parseFloat($('#cash_received_input').val()) || 0;
            const warningEl = $('#remainingCreditWarning');
            
            // Khata net amount (Positive=Debt, Negative=Advance/Jama)
            // This is the amount that will be recorded in the Khata ledger.
            const khataNetAmount = grandTotal - cashReceived;
            
            if (khataNetAmount > 0) {
                warningEl.html(`<i class="fas fa-arrow-up"></i> Net Udhaar (Khata): <strong>${khataNetAmount.toFixed(2)}</strong>`);
                warningEl.removeClass('text-success').addClass('text-danger');
            } else if (khataNetAmount < 0) {
                warningEl.html(`<i class="fas fa-arrow-down"></i> Net Advance (Jama): <strong>${Math.abs(khataNetAmount).toFixed(2)}</strong>`);
                warningEl.removeClass('text-danger').addClass('text-success');
            } else {
                warningEl.html(`<span class="text-success">Payment Complete. (Net: 0.00)</span>`);
                warningEl.removeClass('text-danger').addClass('text-success');
            }
        }
    });
    </script>
</body>
</html>