<?php
session_start();
header('Content-Type: application/json');

$logFile = __DIR__ . '/log.txt';

function writeLog($message)
{
    global $logFile;
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

writeLog('----- SCRIPT START -----');

// --------------------
// SESSION CHECK
// --------------------
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'salesperson') {
    writeLog('Unauthorized access. Session: ' . json_encode($_SESSION));
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

writeLog('Session OK. Salesperson ID: ' . $_SESSION['id']);

include('../config.php');

if (!$conn) {
    writeLog('Database connection failed');
    echo json_encode(['success' => false, 'message' => 'DB connection error']);
    exit;
}

writeLog('Database connected');

// --------------------
// FETCH BRANCH ID
// --------------------
$salesperson_id = $_SESSION['id'];

$sql = "SELECT branch_id FROM salespersons WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    writeLog('Branch prepare failed: ' . mysqli_error($conn));
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $salesperson_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $branch_id);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

writeLog('Fetched branch_id: ' . $branch_id);

if (!$branch_id) {
    writeLog('Branch ID not found');
    echo json_encode(['success' => false, 'message' => 'Branch not found']);
    exit;
}

// --------------------
// BARCODE INPUT
// --------------------
if (!isset($_POST['barcode'])) {
    writeLog('Barcode not received');
    echo json_encode(['success' => false, 'message' => 'Barcode missing']);
    exit;
}

$barcode = trim($_POST['barcode']);

writeLog('Barcode received: [' . $barcode . '] Length: ' . strlen($barcode));

if ($barcode === '') {
    writeLog('Empty barcode');
    echo json_encode(['success' => false, 'message' => 'Empty barcode']);
    exit;
}

// --------------------
// PRODUCT QUERY (EXACT MATCH)
// --------------------
$sql = "
SELECT id, name, price, quantity, barcode_id
FROM products
WHERE branch_id = ?
  AND barcode_id = ?
  AND quantity > 0
LIMIT 1
";

writeLog('Executing product query');

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    writeLog('Product prepare failed: ' . mysqli_error($conn));
    exit;
}

mysqli_stmt_bind_param($stmt, "is", $branch_id, $barcode);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$rows = mysqli_num_rows($result);
writeLog('Rows found: ' . $rows);

// --------------------
// RESULT
// --------------------
if ($row = mysqli_fetch_assoc($result)) {

    writeLog('Product FOUND: ID ' . $row['id']);

    echo json_encode([
        'success' => true,
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'price' => (float)$row['price'],
        'quantity_available' => (int)$row['quantity']
    ]);

} else {

    writeLog('Product NOT FOUND for barcode: ' . $barcode);

    echo json_encode([
        'success' => false,
        'message' => 'Product not found'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

writeLog('----- SCRIPT END -----');
