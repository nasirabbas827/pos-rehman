<?php
session_start();
header('Content-Type: application/json');

// --- AUTHENTICATION: Check for salesperson role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "salesperson") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

include('../config.php');

$salesperson_id = $_SESSION["id"];
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

// 1. Fetch main sale info (for double-check and summary)
$sql_sale_info = "
    SELECT 
        s.id, s.total_amount, s.cash_received, s.payment_method
    FROM sales s
    WHERE s.id = ? AND s.salesperson_id = ?";

$stmt_info = mysqli_prepare($conn, $sql_sale_info);
if ($stmt_info) {
    mysqli_stmt_bind_param($stmt_info, "ii", $sale_id, $salesperson_id);
    mysqli_stmt_execute($stmt_info);
    $result_info = mysqli_stmt_get_result($stmt_info);
    $sale_info = mysqli_fetch_assoc($result_info);
    mysqli_stmt_close($stmt_info);

    if (!$sale_info) {
        $response['message'] = 'Sale not found or does not belong to this salesperson.';
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


// 2. Fetch detailed sales items
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