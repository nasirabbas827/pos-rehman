<?php
session_start();
// --- AUTHENTICATION: Check for manager role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$manager_id = $_SESSION["id"];
$customer_id = (int)($_GET['customer_id'] ?? 0);
$customer_info = null;
$current_balance = 0.00;
$khata_entries = [];
$error = '';
$success = '';

// Fetch manager's branch ID
$sql_manager = "SELECT branch_id FROM managers WHERE id = ?";
$stmt = $conn->prepare($sql_manager);
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$stmt->bind_result($manager_branch_id);
$stmt->fetch();
$stmt->close();

if ($customer_id === 0) {
    die("Error: Missing customer ID.");
}

// 1. Fetch Customer Info & Validate Manager's branch ownership
$sql_cust = "
    SELECT c.id, c.name, c.phone_number, b.name AS branch_name
    FROM customers c
    JOIN branches b ON c.branch_id = b.id
    WHERE c.id = ? AND c.branch_id = ?
";
$stmt = $conn->prepare($sql_cust);
$stmt->bind_param("ii", $customer_id, $manager_branch_id);
$stmt->execute();
$result_cust = $stmt->get_result();
$customer_info = $result_cust->fetch_assoc();
$stmt->close();

if (!$customer_info) {
    die("Error: Customer not found or does not belong to your branch.");
}

// 2. Handle Add Payment POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);
    
    if ($payment_amount <= 0) {
        $error = "Payment amount must be greater than zero.";
    } else {
        // --- USING PROVIDED KHATA SCHEMA ---
        // 'DEBIT' type for payments (money coming in/reducing debt).
        // `amount` is NEGATIVE for reduction in balance.
        // `cash_received` holds the actual cash amount paid.
        $khata_amount = -$payment_amount;
        $type = 'DEBIT';
        
        $stmt = $conn->prepare("INSERT INTO khata (customer_id, amount, cash_received, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idds", $customer_id, $khata_amount, $payment_amount, $type);
        
        if ($stmt->execute()) {
            $success = "Payment of " . number_format($payment_amount, 2) . " recorded successfully.";
            // Redirect to prevent form resubmission
            header("Location: customer_khata.php?customer_id=$customer_id&success=".urlencode($success));
            exit();
        } else {
            $error = "Failed to record payment: " . $conn->error;
        }
        $stmt->close();
    }
}

// Check for success message after redirect
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
// Check for error message after redirect
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}


// 3. Fetch Khata History and Calculate Running Balance
$sql_khata = "SELECT id, sale_id, amount, cash_received, type, created_at FROM khata WHERE customer_id = ? ORDER BY created_at ASC";
$stmt_khata = $conn->prepare($sql_khata);
$stmt_khata->bind_param("i", $customer_id);
$stmt_khata->execute();
$result_khata = $stmt_khata->get_result();

while ($row = $result_khata->fetch_assoc()) {
    $change = (float)$row['amount']; // The net change to the balance
    $current_balance += $change;
    
    // Determine description based on type and sale_id
    if ($row['type'] === 'CREDIT') {
        $description = "Sale #{$row['sale_id']} (Udhaar)";
    } else { // DEBIT
        $description = "Cash Payment Received";
    }

    $khata_entries[] = [
        'date' => date('Y-m-d H:i', strtotime($row['created_at'])),
        'description' => $description,
        'type' => $row['type'],
        'cash_received' => (float)$row['cash_received'],
        'change' => $change,
        'running_balance' => $current_balance
    ];
}
$stmt_khata->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager - Customer Khata</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e40af; /* Deeper blue for headers/titles */
            --debt-color: #dc3545; /* Red for debt (Udhaar) */
            --advance-color: #198754; /* Green for advance/payment (Jama/Payment) */
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
        
        /* Balance Box Styling */
        .balance-box {
            padding: 20px 25px;
            border-radius: 12px;
            text-align: center;
            font-weight: 700;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .balance-box i {
            margin-right: 10px;
        }
        .balance-label {
            display: block;
            font-size: 0.9rem;
            opacity: 0.75;
            margin-bottom: 5px;
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

        /* Transaction Table Styling */
        .table th { 
            background-color: var(--primary-blue); 
            color: white; 
            border-color: #e0e0e0;
            font-weight: 500;
        }
        .table td {
            vertical-align: middle;
            font-size: 0.95rem;
        }
        .positive-amount { color: var(--debt-color); } /* CREDIT / Udhaar */
        .negative-amount { color: var(--advance-color); } /* DEBIT / Payment / Advance */
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #fcfdfe; /* Lighter stripe color */
        }
        
        @media (max-width: 992px) { 
            body { margin-left: 0; } 
        }
    </style>
</head>
<body>
    <?php include('manager_sidebar.php'); ?>

    <div class="container py-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-book-open me-2"></i>Khata Ledger</h2>
                <p class="lead text-muted mb-0">
                    Customer: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_info['name']); ?></span> | 
                    Phone: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_info['phone_number']); ?></span> |
                    Branch: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_info['branch_name']); ?></span>
                </p>
            </div>
            
        </div>
        
        <hr class="mt-0 mb-4">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
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
            <div class="card-header bg-success text-white fw-bold rounded-top-3">
                <i class="fas fa-money-bill-transfer me-1"></i> Record New Payment Received
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="action" value="add_payment">
                    <div class="col-md-9">
                        <label for="payment_amount" class="form-label fw-medium">Payment Amount</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-lg" id="payment_amount" name="payment_amount" required placeholder="Enter amount (e.g., 500.00)">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success btn-lg w-100 shadow-sm">
                            <i class="fas fa-save me-1"></i> Add Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow">
            <div class="card-header bg-primary text-white fw-bold rounded-top-3">
                <i class="fas fa-receipt me-1"></i> Transaction History
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 30%;">Transaction</th>
                                <th class="text-end" style="width: 15%;">Ledger Change</th>
                                <th class="text-end" style="width: 15%;">Cash Received</th>
                                <th class="text-end" style="width: 25%;">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($khata_entries)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No khata transactions recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($khata_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo $entry['date']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($entry['description']); ?>
                                            <span class="badge bg-<?php echo $entry['type'] === 'CREDIT' ? 'danger' : 'success'; ?> ms-2"><?php echo $entry['type']; ?></span>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $entry['change'] > 0 ? 'positive-amount' : 'negative-amount'; ?>">
                                            <?php echo number_format($entry['change'], 2); ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo number_format($entry['cash_received'], 2); ?>
                                        </td>
                                        <td class="text-end fw-bolder">
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