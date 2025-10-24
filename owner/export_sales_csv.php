<?php
session_start();
// --- AUTHENTICATION: Check for owner role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "owner") {
    die("Unauthorized access.");
}

include('../config.php');

$filter_branch_id = (int)($_GET['branch_id'] ?? 0);

// 1. Construct the filtered SQL query (same logic as sales_history.php)
$sql_sales = "
    SELECT 
        s.id, 
        b.name AS branch_name,
        sp.name AS salesperson_name,
        c.name AS customer_name,
        s.sale_date, 
        s.total_amount, 
        s.cash_received,
        s.payment_method
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN salespersons sp ON s.salesperson_id = sp.id
    LEFT JOIN branches b ON s.branch_id = b.id
";

$where_clauses = [];
$param_types = "";
$param_values = [];

// Apply Branch Filter
if ($filter_branch_id > 0) {
    $where_clauses[] = "s.branch_id = ?";
    $param_types .= "i";
    $param_values[] = $filter_branch_id;
}

if (!empty($where_clauses)) {
    $sql_sales .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_sales .= " ORDER BY s.sale_date DESC";

$stmt_sales = mysqli_prepare($conn, $sql_sales);

if (!$stmt_sales) {
    die("Database error preparing export query.");
}

if (!empty($param_values)) {
    mysqli_stmt_bind_param($stmt_sales, $param_types, ...$param_values);
}

mysqli_stmt_execute($stmt_sales);
$result = mysqli_stmt_get_result($stmt_sales);

// 2. Set headers for CSV file download
$filename = "sales_export_" . ($filter_branch_id > 0 ? "branch_" . $filter_branch_id . "_" : "global_") . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// 3. Output CSV Headers
$headers = [
    'Sale ID', 
    'Branch', 
    'Salesperson', 
    'Customer Name', 
    'Date/Time', 
    'Total Amount', 
    'Cash Received', 
    'Payment Method', 
    'Khata (Udhaar/Advance)'
];
fputcsv($output, $headers);

// 4. Output Data Rows
while ($row = mysqli_fetch_assoc($result)) {
    $khata_change = $row['total_amount'] - $row['cash_received'];
    
    // Determine Khata status label
    if ($khata_change > 0) {
        $khata_status = 'Udhaar: ' . number_format($khata_change, 2);
    } elseif ($khata_change < 0) {
        $khata_status = 'Advance: ' . number_format(abs($khata_change), 2);
    } else {
        $khata_status = 'Settled';
    }

    $csv_row = [
        $row['id'],
        $row['branch_name'],
        $row['salesperson_name'],
        $row['customer_name'],
        $row['sale_date'],
        number_format($row['total_amount'], 2),
        number_format($row['cash_received'], 2),
        $row['payment_method'],
        $khata_status
    ];
    fputcsv($output, $csv_row);
}

fclose($output);
mysqli_stmt_close($stmt_sales);
mysqli_close($conn);
exit();
?>