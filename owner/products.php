<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];

// Fetch branches for filter dropdown
$stmt = $conn->prepare("SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$branches_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch products with threshold info
$sql = "
    SELECT p.*, b.name AS branch_name, c.name AS category_name
    FROM products p
    INNER JOIN branches b ON p.branch_id = b.id
    INNER JOIN categories c ON p.category_id = c.id
    WHERE b.owner_id = ?
    ORDER BY p.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Filter for notifications (Quantity <= Threshold)
$low_stock_items = array_filter($products, function($p) {
    return $p['quantity'] <= $p['low_stock_threshold'];
});

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Products</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .product-image-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; }
        .stock-warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body class="bg-light">

<?php include('sidebar.php'); ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-boxes me-2"></i>Product Inventory</h2>
        <div>
            <button class="btn btn-outline-dark me-2" onclick="printAllBarcodes()">
                <i class="fas fa-print me-1"></i> Print All Barcodes
            </button>
            <button id="btnCreateProduct" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Create Product
            </button>
        </div>
    </div>

    <?php if (count($low_stock_items) > 0): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Stock Alert:</strong> You have <?= count($low_stock_items) ?> products that are low or out of stock.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div id="alertPlaceholder"></div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" id="productSearch" class="form-control" placeholder="Search by name or barcode..." onkeyup="filterTable()">
        </div>
        <div class="col-md-3">
            <select id="filterStock" class="form-select" onchange="filterTable()">
                <option value="all">All Stock Levels</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
            </select>
        </div>
        <div class="col-md-3">
            <select id="filterBranch" class="form-select" onchange="filterTable()">
                <option value="all">All Branches</option>
                <?php foreach($branches_list as $b): ?>
                    <option value="<?= htmlspecialchars($b['name']) ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
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
                        <th>Min. Limit</th>
                        <th>Branch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    <?php foreach ($products as $p): 
                        $isOut = $p['quantity'] <= 0;
                        $isLow = !$isOut && ($p['quantity'] <= $p['low_stock_threshold']);
                    ?>
                    <tr data-id="<?= $p['id'] ?>" 
                        data-stock-status="<?= $isOut ? 'out' : ($isLow ? 'low' : 'ok') ?>"
                        data-branch="<?= htmlspecialchars($p['branch_name']) ?>">
                        <td><img src="images/<?= htmlspecialchars($p['image']) ?>" class="product-image-thumb"></td>
                        <td class="search-col-barcode font-monospace"><?= htmlspecialchars($p['barcode_id']) ?></td>
                        <td class="search-col-name"><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><?= number_format($p['price'], 2) ?></td>
                        <td>
                            <span class="<?= ($isLow || $isOut) ? 'stock-warning' : '' ?>">
                                <?= (int)$p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?>
                                <?php if($isOut): ?> <i class="fas fa-times-circle"></i> <?php elseif($isLow): ?> <i class="fas fa-exclamation-circle"></i> <?php endif; ?>
                            </span>
                        </td>
                        <td><?= (int)$p['low_stock_threshold'] ?></td>
                        <td><?= htmlspecialchars($p['branch_name']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" onclick="printSingleBarcode('<?= $p['barcode_id'] ?>', '<?= addslashes($p['name']) ?>')"><i class="fas fa-print"></i></button>
                            <button class="btn btn-sm btn-info btn-edit text-white" data-id="<?= $p['id'] ?>"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $p['id'] ?>"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<canvas id="tempCanvas" style="display:none;"></canvas>
<div id="productModalPlaceholder"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const { jsPDF } = window.jspdf;

function filterTable() {
    const search = document.getElementById('productSearch').value.toUpperCase();
    const stockFilter = document.getElementById('filterStock').value;
    const branchFilter = document.getElementById('filterBranch').value;
    const rows = document.querySelectorAll('#productsTableBody tr');

    rows.forEach(row => {
        const name = row.querySelector('.search-col-name').textContent.toUpperCase();
        const barcode = row.querySelector('.search-col-barcode').textContent.toUpperCase();
        const status = row.getAttribute('data-stock-status');
        const branch = row.getAttribute('data-branch');

        const matchesSearch = name.includes(search) || barcode.includes(search);
        const matchesStock = stockFilter === 'all' || status === stockFilter;
        const matchesBranch = branchFilter === 'all' || branch === branchFilter;

        row.style.display = (matchesSearch && matchesStock && matchesBranch) ? "" : "none";
    });
}

function printSingleBarcode(code, name) {
    const doc = new jsPDF({ orientation: 'l', unit: 'mm', format: [50, 30] });
    const canvas = document.getElementById('tempCanvas');
    JsBarcode(canvas, code, { format: "CODE128", width: 2, height: 40 });
    doc.setFontSize(8);
    doc.text(name.substring(0, 20), 5, 5);
    doc.addImage(canvas.toDataURL("image/png"), 'PNG', 5, 7, 40, 18);
    doc.save(`barcode_${code}.pdf`);
}

function printAllBarcodes() {
    const rows = document.querySelectorAll('#productsTableBody tr:not([style*="display: none"])');
    if(rows.length === 0) return;
    const doc = new jsPDF();
    let y = 10;
    rows.forEach((row, i) => {
        const code = row.querySelector('.search-col-barcode').textContent;
        const name = row.querySelector('.search-col-name').textContent;
        const canvas = document.getElementById('tempCanvas');
        JsBarcode(canvas, code, { height: 30 });
        if (y > 260) { doc.addPage(); y = 10; }
        doc.text(name, 10, y);
        doc.addImage(canvas.toDataURL("image/png"), 'PNG', 10, y+2, 50, 15);
        y += 30;
    });
    doc.save('inventory_barcodes.pdf');
}

// Logic to load modal
function loadModal(id = null) {
    let url = 'product_modal.php' + (id ? '?id='+id : '');
    fetch(url).then(r => r.text()).then(html => {
        document.getElementById('productModalPlaceholder').innerHTML = html;
        const m = new bootstrap.Modal(document.getElementById('productModal'));
        m.show();

        document.getElementById('productForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('product_modal.php', { method: 'POST', body: new FormData(this) })
            .then(r => r.json()).then(data => {
                if(data.status === 'success') location.reload();
                else alert(data.message);
            });
        });
    });
}

document.getElementById('btnCreateProduct').onclick = () => loadModal();
document.querySelectorAll('.btn-edit').forEach(b => b.onclick = () => loadModal(b.dataset.id));
// Delete logic
document.querySelectorAll('.btn-delete').forEach(b => {
    b.onclick = () => {
        if(confirm('Delete product?')) {
            fetch('product_modal.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&id=${b.dataset.id}`
            }).then(() => location.reload());
        }
    }
});
</script>
</body>
</html>