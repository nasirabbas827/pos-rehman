<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

// Validate branch ID and ownership
if ($branch_id <= 0) {
    header("Location: branches.php");
    exit();
}

// Fetch branch details to verify ownership and display name
$stmt = $conn->prepare("SELECT name FROM branches WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $branch_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$branch = $result->fetch_assoc();
$stmt->close();

if (!$branch) {
    header("Location: branches.php");
    exit();
}

// Fetch products for this branch
$sql = "
    SELECT p.id, p.barcode_id, p.name, p.description, p.price, p.quantity, p.unit, p.image, p.created_at, 
           c.name AS category_name
    FROM products p
    INNER JOIN branches b ON p.branch_id = b.id
    INNER JOIN categories c ON p.category_id = c.id
    WHERE p.branch_id = ? AND b.owner_id = ?
    ORDER BY p.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $branch_id, $owner_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Products for <?php echo htmlspecialchars($branch['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        .product-image-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-boxes me-2"></i>Products for <?php echo htmlspecialchars($branch['name']); ?></h2>
        <a href="branches.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Branches</a>
    </div>

    <div id="alertPlaceholder"></div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Image</th>
                <th>Barcode</th>
                <th>Name</th>
                <th>Description</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Category</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody id="productsTableBody">
            <?php if (count($products) === 0): ?>
                <tr><td colspan="9" class="text-center">No products found for this branch.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <tr data-id="<?= $p['id'] ?>">
                        <td><img src="images/<?= htmlspecialchars($p['image']) ?>" alt="Product Image" class="product-image-thumb"></td>
                        <td><?= htmlspecialchars($p['barcode_id']) ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars(strlen($p['description']) > 40 ? substr($p['description'], 0, 40).'...' : $p['description']) ?></td>
                        <td>$<?= number_format($p['price'], 2) ?></td>
                        <td><?= (int)$p['quantity'] ?></td>
                        <td><?= htmlspecialchars($p['unit']) ?></td>
                        <td><?= htmlspecialchars($p['category_name']) ?></td>
                        <td><?= htmlspecialchars($p['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>