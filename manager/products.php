<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$manager_id = $_SESSION["id"];

// Fetch manager's branch_id and info
$stmt = $conn->prepare("SELECT full_name, branch_id FROM managers WHERE id = ?");
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$branch_id = $res['branch_id'];
$full_name = $res['full_name'];
$stmt->close();

// Fetch products for THIS branch only
$sql = "SELECT p.*, b.name AS branch_name, c.name AS category_name
        FROM products p
        INNER JOIN branches b ON p.branch_id = b.id
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check for low stock items for notifications
$low_stock_alerts = array_filter($products, function($p) {
    return $p['quantity'] <= $p['low_stock_threshold'];
});

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Branch Products</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { background-color: #f4f7f6; }
        .product-image-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
        .stock-warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>

<?php include('manager_sidebar.php'); ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-store me-2"></i>Welcome, <?= htmlspecialchars($full_name) ?></h2>
        <div>
            <button class="btn btn-outline-dark me-2" onclick="printAllBarcodes()">
                <i class="fas fa-print me-1"></i> Print Barcodes
            </button>
            <button id="btnCreateProduct" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Product
            </button>
        </div>
    </div>

    <?php if (!empty($low_stock_alerts)): ?>
    <div class="alert alert-warning shadow-sm border-0 mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i> 
        <strong>Branch Alert:</strong> <?= count($low_stock_alerts) ?> products are below threshold.
    </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body row g-3">
            <div class="col-md-8">
                <input type="text" id="productSearch" class="form-control" placeholder="Search product name or barcode..." onkeyup="filterTable()">
            </div>
            <div class="col-md-4">
                <select id="filterStock" class="form-select" onchange="filterTable()">
                    <option value="all">All Stock Status</option>
                    <option value="low">Low Stock</option>
                    <option value="out">Out of Stock</option>
                </select>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Image</th>
                    <th>Barcode</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Threshold</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="productsTableBody">
                <?php foreach ($products as $p): 
                    $isOut = $p['quantity'] <= 0;
                    $isLow = !$isOut && ($p['quantity'] <= $p['low_stock_threshold']);
                ?>
                <tr data-id="<?= $p['id'] ?>" data-stock-status="<?= $isOut ? 'out' : ($isLow ? 'low' : 'ok') ?>">
                    <td><img src="../owner/images/<?= htmlspecialchars($p['image']) ?>" class="product-image-thumb"></td>
                    <td class="search-col-barcode"><?= htmlspecialchars($p['barcode_id']) ?></td>
                    <td class="search-col-name"><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td>RS <?= number_format($p['price'], 2) ?></td>
                    <td>
                        <span class="<?= ($isLow || $isOut) ? 'stock-warning' : '' ?>">
                            <?= (int)$p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?>
                        </span>
                    </td>
                    <td><?= (int)$p['low_stock_threshold'] ?></td>
                    <td><?= htmlspecialchars($p['category_name']) ?></td>
                    <td>
                        <button class="mt-2 btn btn-sm btn-light border" onclick="printSingleBarcode('<?= $p['barcode_id'] ?>', '<?= addslashes($p['name']) ?>')"><i class="fas fa-print"></i></button>
                        <button class="mt-2 btn btn-sm btn-info text-white btn-edit" data-id="<?= $p['id'] ?>"><i class="fas fa-edit"></i></button>
                        <button class="mt-2 btn btn-sm btn-danger btn-delete" data-id="<?= $p['id'] ?>"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<canvas id="barcodeCanvas" style="display:none;"></canvas>
<div id="modalPlaceholder"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter Function
function filterTable() {
    const search = document.getElementById('productSearch').value.toUpperCase();
    const stockFilter = document.getElementById('filterStock').value;
    const rows = document.querySelectorAll('#productsTableBody tr');

    rows.forEach(row => {
        const name = row.querySelector('.search-col-name').textContent.toUpperCase();
        const barcode = row.querySelector('.search-col-barcode').textContent.toUpperCase();
        const status = row.getAttribute('data-stock-status');

        const matchesSearch = name.includes(search) || barcode.includes(search);
        const matchesStock = stockFilter === 'all' || status === stockFilter;

        row.style.display = (matchesSearch && matchesStock) ? "" : "none";
    });
}

// PDF Barcode Function
function printSingleBarcode(code, name) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'l', unit: 'mm', format: [50, 30] });
    const canvas = document.getElementById('barcodeCanvas');
    JsBarcode(canvas, code, { format: "CODE128", height: 40 });
    doc.setFontSize(8);
    doc.text(name, 5, 5);
    doc.addImage(canvas.toDataURL("image/png"), 'PNG', 5, 7, 40, 18);
    doc.save(`barcode_${code}.pdf`);
}

// Modal Logic
function loadModal(id = null) {
    let url = 'manager_product_modal.php' + (id ? '?id=' + id : '');
    fetch(url).then(r => r.text()).then(html => {
        document.getElementById('modalPlaceholder').innerHTML = html;
        const m = new bootstrap.Modal(document.getElementById('productModal'));
        m.show();

        document.getElementById('productForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('manager_product_modal.php', { method: 'POST', body: new FormData(this) })
            .then(r => r.json()).then(data => {
                if(data.status === 'success') location.reload();
                else alert(data.message);
            });
        });
    });
}

document.getElementById('btnCreateProduct').onclick = () => loadModal();
document.addEventListener('click', e => {
    if(e.target.closest('.btn-edit')) loadModal(e.target.closest('.btn-edit').dataset.id);
    if(e.target.closest('.btn-delete')) {
        if(confirm('Delete this product?')) {
            fetch('manager_product_modal.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&id=${e.target.closest('.btn-delete').dataset.id}`
            }).then(() => location.reload());
        }
    }
});
</script>
</body>
</html>