<?php
session_start();
// --- AUTHENTICATION: Check for owner role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "owner") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$owner_id = $_SESSION["id"];
$customer_id = (int)($_GET['customer_id'] ?? 0);
$customer_info = null;
$current_balance = 0.00;
$khata_entries = [];

if ($customer_id === 0) {
    die("Error: Missing customer ID.");
}

// 1. Fetch Customer Info & Validate Owner ownership via Branch
$sql_cust = "
    SELECT c.name, c.phone_number, b.name AS branch_name
    FROM customers c
    JOIN branches b ON c.branch_id = b.id
    WHERE c.id = ? AND b.owner_id = ?
";
$stmt = $conn->prepare($sql_cust);
$stmt->bind_param("ii", $customer_id, $owner_id);
$stmt->execute();
$result_cust = $stmt->get_result();
$customer_info = $result_cust->fetch_assoc();
$stmt->close();

if (!$customer_info) {
    die("Error: Customer not found or does not belong to your branches.");
}

// 2. Fetch Khata History and Calculate Running Balance
$sql_khata = "SELECT id, sale_id, amount, cash_received, type, created_at FROM khata WHERE customer_id = ? ORDER BY created_at ASC";
$stmt_khata = $conn->prepare($sql_khata);
$stmt_khata->bind_param("i", $customer_id);
$stmt_khata->execute();
$result_khata = $stmt_khata->get_result();

while ($row = $result_khata->fetch_assoc()) {
    // Amount is the ledger change (Positive = Debt/Udhaar, Negative = Payment/Advance)
    $change = (float)$row['amount'];
    $current_balance += $change;
    
    // Determine description based on sale_id presence
    if ($row['sale_id']) {
        // This is a sale transaction where payment_method was 'Credit'
        $description = "Sale #{$row['sale_id']}";
        $khata_type = $change > 0 ? 'Udhaar (Debt - CREDIT)' : 'Advance (Jama - CREDIT)';
    } else {
        // This is a manual payment/adjustment (DEBIT type)
        $description = "Cash Payment Received";
        $khata_type = 'Payment (DEBIT)';
    }

    $khata_entries[] = [
        'date' => date('Y-m-d H:i', strtotime($row['created_at'])),
        'description' => $description,
        'change' => $change,
        'type_label' => $khata_type, // Added type label for badge
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
    <title>Customer Khata Audit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #6b21a8; /* Deep Purple for titles/headers */
            --debt-color: #dc3545; /* Red for debt (Udhaar) */
            --advance-color: #198754; /* Green for advance/payment (Jama/Payment) */
        }
        body {
            background-color: #f8fafc; /* Light off-white background */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-left: 280px;
        }
        .container { padding: 2rem 1rem; }
        h2 { color: var(--primary-purple); font-size: 2rem; margin-bottom: 0.5rem; font-weight: 600; }
        .card { 
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.08); 
            border: none; 
            border-radius: 12px;
        }
        
        /* Balance Box Styling (Same as Manager/Salesperson, but distinct color scheme in CSS block) */
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
        /* Debt/Advance colors are the same for consistency across roles */
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
        .table-khata th { 
            background-color: var(--primary-purple); /* Changed to purple for owner role */
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
        
        @media (max-width: 992px) { 
            body { margin-left: 0; } 
        }
    </style>
</head>
<body>
    <?php include('sidebar.php'); // Assuming 'sidebar.php' is the correct include for the owner's sidebar ?>

    <div class="container py-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-search-dollar me-2"></i>Khata Audit Ledger</h2>
                <p class="lead text-muted mb-0">
                    Customer: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_info['name']); ?></span> | 
                    Phone: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_info['phone_number']); ?></span> |
                    Branch: <span class="fw-bold text-dark"><?php echo htmlspecialchars($customer_info['branch_name']); ?></span>
                </p>
            </div>
            
        </div>
        
        <hr class="mt-0 mb-4">

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

        <div class="card border-0 shadow">
            <div class="card-header bg-primary text-white fw-bold rounded-top-3" style="background-color: var(--primary-purple) !important;">
                <i class="fas fa-history me-1"></i> Transaction History (Full Audit)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped table-khata mb-0">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 45%;">Description / Type</th>
                                <th class="text-end" style="width: 20%;">Amount (Change)</th>
                                <th class="text-end" style="width: 20%;">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($khata_entries)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No khata transactions recorded for this customer.</td></tr>
                            <?php else: ?>
                                <?php foreach ($khata_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo $entry['date']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($entry['description']); ?>
                                            <span class="badge bg-secondary ms-2"><?php echo $entry['type_label']; ?></span>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $entry['change'] > 0 ? 'positive-amount' : 'negative-amount'; ?>">
                                            <?php echo number_format($entry['change'], 2); ?>
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