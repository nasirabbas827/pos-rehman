<?php
session_start();
// --- AUTHENTICATION: Check for salesperson role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "salesperson") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$salesperson_id = $_SESSION["id"];
$customer_id = (int)($_GET['customer_id'] ?? 0);
$customer_name = "N/A";
$current_balance = 0.00;
$khata_entries = [];
$message = '';
$error = '';

// 1. Fetch Salesperson/Branch Info
$sql_sp = "SELECT branch_id FROM salespersons WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_sp);
mysqli_stmt_bind_param($stmt, "i", $salesperson_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $branch_id);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$branch_id || $customer_id === 0) {
    // Note: A more user-friendly error page might be better than die()
    die("Error: Invalid access or missing customer ID.");
}

// 2. Fetch Customer Info & Validate Branch ownership
$sql_cust = "SELECT name, phone_number FROM customers WHERE id = ? AND branch_id = ?";
$stmt = mysqli_prepare($conn, $sql_cust);
mysqli_stmt_bind_param($stmt, "ii", $customer_id, $branch_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $customer_name, $customer_phone);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$customer_name) {
    die("Error: Customer not found or does not belong to your branch.");
}

// 3. Handle Khata Payment/Adjustment Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_payment'])) {
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);

    if ($payment_amount > 0) {
        // Recording a payment (DEBIT: Reduces debt or increases advance)
        $sql_insert = "INSERT INTO khata (customer_id, amount, cash_received, type) VALUES (?, ?, ?, 'DEBIT')";
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        
        // Khata amount for a payment/received cash is typically NEGATIVE
        // to balance a positive debt (CREDIT type in sales).
        $negative_amount = -$payment_amount; 

        // cash_received field is used here to log the cash amount paid
        mysqli_stmt_bind_param($stmt_insert, "idd", $customer_id, $negative_amount, $payment_amount);
        
        if (mysqli_stmt_execute($stmt_insert)) {
            $message = "Payment of " . number_format($payment_amount, 2) . " recorded successfully.";
        } else {
            $error = "Error recording payment: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_insert);
    } else {
        $error = "Payment amount must be greater than zero.";
    }
    // Redirect to prevent form resubmission
    header("Location: customer_khata.php?customer_id={$customer_id}&msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit();
}

// 4. Fetch Khata History and Calculate Running Balance
$sql_khata = "SELECT id, sale_id, amount, cash_received, created_at FROM khata WHERE customer_id = ? ORDER BY created_at ASC";
$stmt_khata = mysqli_prepare($conn, $sql_khata);
mysqli_stmt_bind_param($stmt_khata, "i", $customer_id);
mysqli_stmt_execute($stmt_khata);
$result_khata = mysqli_stmt_get_result($stmt_khata);

while ($row = mysqli_fetch_assoc($result_khata)) {
    // Amount is the ledger change (Positive = Debt/Udhaar, Negative = Advance/Jama)
    $current_balance += (float)$row['amount']; 
    
    // Determine description based on sale_id presence
    if ($row['sale_id']) {
        // This is a sale transaction where payment_method was 'Credit'
        // $row['amount'] is the credit amount, $row['cash_received'] is the cash paid upfront (if any)
        $description = "Sale #{$row['sale_id']} (Total Sale Value: " . number_format($row['amount'] + $row['cash_received'], 2) . ")";
        $khata_type = $row['amount'] > 0 ? 'Udhaar (Debt)' : 'Advance (Jama)';
    } else {
        // This is a manual payment/adjustment (DEBIT type)
        $description = "Cash Payment Received";
        // The ledger change is negative (amount), but the cash received is positive
        $khata_type = 'Payment';
    }

    $khata_entries[] = [
        'date' => date('Y-m-d H:i', strtotime($row['created_at'])),
        'description' => $description,
        'change' => (float)$row['amount'],
        'cash_received_in_entry' => (float)$row['cash_received'],
        'type_label' => $khata_type,
        'running_balance' => $current_balance
    ];
}
mysqli_stmt_close($stmt_khata);

// 5. Display messages from redirect
if (isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);
if (isset($_GET['err'])) $error = htmlspecialchars($_GET['err']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Khata</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e40af; /* Deeper blue */
            --debt-color: #dc3545; /* Red for debt */
            --advance-color: #198754; /* Green for advance */
        }
        body {
            background-color: #f8fafc; /* Light off-white background */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-left: 280px;
        }
        .container { padding: 2rem 1rem; }
        h2 { color: var(--primary-blue); font-size: 2rem; margin-bottom: 0.5rem; font-weight: 600; }
        .card { 
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08); 
            border: none; 
            border-radius: 12px;
        }
        
        /* New Balance Box Styling */
        .balance-box {
            padding: 20px 25px;
            border-radius: 12px;
            text-align: center;
            font-weight: 700;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .balance-box i {
            margin-right: 10px;
        }
        .debt { 
            background-color: #ffeaea; /* Very light red */
            color: var(--debt-color); 
            border-left: 5px solid var(--debt-color);
        }
        .advance { 
            background-color: #eaffe9; /* Very light green */
            color: var(--advance-color); 
            border-left: 5px solid var(--advance-color);
        }
        .zero { 
            background-color: #f0f4f8; 
            color: #333; 
            border-left: 5px solid #6c757d; 
        }
        .balance-label {
            display: block;
            font-size: 0.9rem;
            opacity: 0.75;
            margin-bottom: 5px;
        }

        /* Transaction Table Styling */
        .table-khata th { 
            background-color: var(--primary-blue); 
            color: white; 
            border-color: #e0e0e0;
            font-weight: 500;
        }
        .table-khata td {
            vertical-align: middle;
            font-size: 0.95rem;
        }
        .positive-amount { color: var(--debt-color); } 
        .negative-amount { color: var(--advance-color); } 
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #fcfdfe; /* Lighter stripe color */
        }

        /* Utility */
        .badge-info-custom {
            background-color: #0d6efd; /* Use primary blue for badges */
        }
        
        @media (max-width: 992px) { 
            body { margin-left: 0; } 
        }
    </style>
</head>
<body>
    <?php include('salesperson_sidebar.php'); ?>

    <div class="container py-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-book-open me-2"></i>Khata Ledger</h2>
                <p class="lead text-muted mb-0">
                    Customer: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_name); ?></span> | 
                    Phone: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_phone); ?></span>
                </p>
            </div>
            
        </div>
        
        <hr class="mt-0 mb-4">

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="balance-box <?php echo $current_balance > 0 ? 'debt' : ($current_balance < 0 ? 'advance' : 'zero'); ?>">
            <?php if ($current_balance > 0): ?>
                <span class="balance-label">Total Outstanding Debt (Udhaar)</span>
                <i class="fas fa-hand-holding-dollar"></i> 
                <span class="fs-3 fw-bolder"><?php echo number_format($current_balance, 2); ?></span>
            <?php elseif ($current_balance < 0): ?>
                <span class="balance-label">Customer Advance / Credit (Jama)</span>
                <i class="fas fa-hand-holding-medical"></i> 
                <span class="fs-3 fw-bolder"><?php echo number_format(abs($current_balance), 2); ?></span>
            <?php else: ?>
                <span class="balance-label">Current Balance</span>
                <i class="fas fa-check-circle"></i> 
                <span class="fs-3 fw-bolder">0.00</span>
            <?php endif; ?>
        </div>

        <div class="card mb-5 border-0 shadow-sm">
            <div class="card-header bg-primary text-white fw-bold rounded-top-3">
                <i class="fas fa-money-bill-transfer me-1"></i> Record New Payment
            </div>
            <div class="card-body">
                <form method="POST" action="customer_khata.php?customer_id=<?php echo $customer_id; ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-9">
                            <label for="payment_amount" class="form-label fw-medium">Amount Received from Customer</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                <input type="number" name="payment_amount" id="payment_amount" class="form-control form-control-lg" step="0.01" min="0.01" required placeholder="Enter amount (e.g., 500.00)">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="record_payment" class="btn btn-success btn-lg w-100 shadow-sm">
                                <i class="fas fa-save me-1"></i> Submit Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        <div class="card border-0 shadow">
            <div class="card-header bg-secondary text-white fw-bold rounded-top-3">
                <i class="fas fa-receipt me-1"></i> Transaction History
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-khata mb-0">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 45%;">Description</th>
                                <th style="width: 20%;" class="text-end">Amount (Change)</th>
                                <th style="width: 20%;" class="text-end">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($khata_entries)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No khata transactions recorded yet for this customer.</td></tr>
                            <?php else: ?>
                                <?php foreach ($khata_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo $entry['date']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($entry['description']); ?>
                                            <span class="badge badge-info-custom ms-2"><?php echo $entry['type_label']; ?></span>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $entry['change'] > 0 ? 'positive-amount' : 'negative-amount'; ?>">
                                            <?php echo number_format($entry['change'], 2); ?>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?php 
                                                // Style running balance based on its sign
                                                $balance_class = $entry['running_balance'] > 0 ? 'text-danger' : ($entry['running_balance'] < 0 ? 'text-success' : 'text-dark');
                                            ?>
                                            <span class="<?php echo $balance_class; ?>">
                                                <?php echo number_format($entry['running_balance'], 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>