<?php
session_start();
header('Content-Type: application/json');

// --- INITIAL AUTHENTICATION CHECK ---
if (!isset($_SESSION["id"]) || !isset($_SESSION["role"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

include('../config.php');

$user_id = $_SESSION["id"];
$user_role = $_SESSION["role"];
$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

if ($sale_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Sale ID provided.']);
    mysqli_close($conn);
    exit();
}

$response = [
    'success' => false,
    'message' => '',
    'sale_info' => null,
    'items' => []
];

// --- 1. DETERMINE AUTHORIZATION AND SQL CLAUSE ---
$auth_check_sql = "";
$bind_types = "i"; // Sale ID is always the first parameter
$bind_params = [$sale_id];

if ($user_role === 'owner') {
    // Owner can see any sale in the system. No additional WHERE clause needed.

} elseif ($user_role === 'salesperson') {
    // Salespersons can only see their own sales
    $auth_check_sql = " AND s.salesperson_id = ?";
    $bind_types .= "i";
    $bind_params[] = $user_id;

} elseif ($user_role === 'manager') {
    // Managers can see any sale in their branch. First, find their branch ID.
    $sql_branch = "SELECT branch_id FROM managers WHERE id = ?";
    $stmt_branch = mysqli_prepare($conn, $sql_branch);
    mysqli_stmt_bind_param($stmt_branch, "i", $user_id);
    mysqli_stmt_execute($stmt_branch);
    $result_branch = mysqli_stmt_get_result($stmt_branch);
    $branch_row = mysqli_fetch_assoc($result_branch);
    mysqli_stmt_close($stmt_branch);

    if (!$branch_row || !$branch_row['branch_id']) {
        $response['message'] = 'Manager is not linked to a branch.';
        echo json_encode($response);
        mysqli_close($conn);
        exit();
    }
    
    // Use the retrieved branch ID to filter sales
    $auth_check_sql = " AND s.branch_id = ?";
    $bind_types .= "i";
    $bind_params[] = $branch_row['branch_id'];

} else {
    $response['message'] = 'Insufficient permissions to view sale details.';
    echo json_encode($response);
    mysqli_close($conn);
    exit();
}


// --- 2. FETCH MAIN SALE INFO (with Authorization Check) ---
$sql_sale_info = "
    SELECT 
        s.id, s.total_amount, s.cash_received, s.payment_method
    FROM sales s
    WHERE s.id = ?" . $auth_check_sql;

$stmt_info = mysqli_prepare($conn, $sql_sale_info);
if ($stmt_info) {
    // Dynamically bind parameters
    mysqli_stmt_bind_param($stmt_info, $bind_types, ...$bind_params);
    mysqli_stmt_execute($stmt_info);
    $result_info = mysqli_stmt_get_result($stmt_info);
    $sale_info = mysqli_fetch_assoc($result_info);
    mysqli_stmt_close($stmt_info);

    if (!$sale_info) {
        $response['message'] = 'Sale not found or unauthorized to view.';
        echo json_encode($response);
        mysqli_close($conn);
        exit();
    }
    $response['sale_info'] = $sale_info;
} else {
    $response['message'] = 'Database error: Could not prepare the sale info query.';
    echo json_encode($response);
    mysqli_close($conn);
    exit();
}


// --- 3. FETCH DETAILED SALES ITEMS ---
$sql_items = "
    SELECT 
        si.quantity, 
        si.unit_price, 
        si.discount, 
        si.line_total,
        p.name AS product_name,
        p.unit
    FROM sales_items si
    INNER JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?";

$stmt_items = mysqli_prepare($conn, $sql_items);
if ($stmt_items) {
    mysqli_stmt_bind_param($stmt_items, "i", $sale_id);
    mysqli_stmt_execute($stmt_items);
    $result_items = mysqli_stmt_get_result($stmt_items);

    while ($row = mysqli_fetch_assoc($result_items)) {
        $response['items'][] = $row;
    }
    mysqli_stmt_close($stmt_items);

    if (!empty($response['items'])) {
        $response['success'] = true;
    } else {
        $response['message'] = 'No items found for this sale.';
    }

} else {
    $response['message'] = 'Database error: Could not prepare the items query.';
}

mysqli_close($conn);
echo json_encode($response);
?>