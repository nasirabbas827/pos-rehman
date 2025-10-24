<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];

// Fetch branches for dropdown and validation
$stmt = $conn->prepare("SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch categories for dropdown
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch products with branch and category info (only for owner)
$sql = "
    SELECT p.id, p.barcode_id, p.name, p.description, p.price, p.quantity, p.unit, p.image, p.created_at, 
           b.name AS branch_name, c.name AS category_name
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
        <h2><i class="fas fa-boxes me-2"></i>Products</h2>
        <button id="btnCreateProduct" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Create Product
        </button>
    </div>

    <div id="alertPlaceholder"></div>

    <div class="row mb-3">
        <div class="col-md-6 col-lg-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="productSearch" class="form-control" placeholder="Filter by Name or Barcode ID..." onkeyup="filterTable()">
            </div>
        </div>
    </div>
    <div class="table-responsive">
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
                    <th>Branch</th>
                    <th>Created At</th>
                    <th style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody id="productsTableBody">
                <?php if (count($products) === 0): ?>
                    <tr><td colspan="11" class="text-center" id="noProductsRow">No products found.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr data-id="<?= $p['id'] ?>">
                            <td><img src="images/<?= htmlspecialchars($p['image']) ?>" alt="Product Image" class="product-image-thumb"></td>
                            <td class="search-col-barcode"><?= htmlspecialchars($p['barcode_id']) ?></td>
                            <td class="search-col-name"><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars(strlen($p['description']) > 40 ? substr($p['description'], 0, 40).'...' : $p['description']) ?></td>
                            <td><?= number_format($p['price'], 2) ?></td>
                            <td><?= (int)$p['quantity'] ?></td>
                            <td><?= htmlspecialchars($p['unit']) ?></td>
                            <td><?= htmlspecialchars($p['category_name']) ?></td>
                            <td><?= htmlspecialchars($p['branch_name']) ?></td>
                            <td><?= htmlspecialchars($p['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info btn-edit" data-id="<?= $p['id'] ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $p['id'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="productModalPlaceholder"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const alertPlaceholder = document.getElementById('alertPlaceholder');
const modalPlaceholder = document.getElementById('productModalPlaceholder');
const productsTableBody = document.getElementById('productsTableBody');

function showAlert(message, type = 'success') {
    alertPlaceholder.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

// Load modal content via AJAX
function loadModal(id = null) {
    let url = 'product_modal.php';
    if (id) url += '?id=' + id;

    fetch(url)
    .then(response => response.text())
    .then(html => {
        modalPlaceholder.innerHTML = html;
        const modalEl = document.getElementById('productModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // Form submission handler
        const form = document.getElementById('productForm');
        form.addEventListener('submit', e => {
            e.preventDefault();
            e.stopPropagation();

            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            // Prepare form data including file
            const formData = new FormData(form);
            fetch('product_modal.php', {
                method: 'POST',
                body: formData
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.status === 'success') {
                    modal.hide();
                    showAlert(data.message);
                    setTimeout(() => location.reload(), 800);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(() => showAlert('Error processing request', 'danger'));
        });
    })
    .catch(() => showAlert('Failed to load modal', 'danger'));
}

document.getElementById('btnCreateProduct').addEventListener('click', () => loadModal());

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', e => {
        const id = e.currentTarget.getAttribute('data-id');
        loadModal(id);
    });
});

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', e => {
        const id = e.currentTarget.getAttribute('data-id');
        if (!confirm('Are you sure you want to delete this product?')) return;

        fetch('product_modal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'delete', id: id})
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert(data.message);
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) row.remove();
                
                // Re-check for products after deletion
                if (productsTableBody.querySelectorAll('tr[data-id]').length === 0) {
                     productsTableBody.innerHTML = '<tr><td colspan="11" class="text-center" id="noProductsRow">No products found.</td></tr>';
                }
                
                // Re-apply filter in case it was active
                filterTable(); 

            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(() => showAlert('Error processing request', 'danger'));
    });
});


// --- Filtering Functionality ---
/**
 * Filters the product table rows based on the input in the search field.
 * It checks the Product Name (search-col-name) and Barcode ID (search-col-barcode).
 */
function filterTable() {
    const input = document.getElementById('productSearch');
    const filter = input.value.toUpperCase();
    const rows = productsTableBody.querySelectorAll('tr[data-id]');
    let visibleRowCount = 0;
    const noProductsRow = document.getElementById('noProductsRow');
    
    rows.forEach(row => {
        // Get the Name and Barcode cells for comparison
        const nameCell = row.querySelector('.search-col-name');
        const barcodeCell = row.querySelector('.search-col-barcode');

        // Extract text and convert to uppercase for case-insensitive search
        const nameText = nameCell ? nameCell.textContent.toUpperCase() : '';
        const barcodeText = barcodeCell ? barcodeCell.textContent.toUpperCase() : '';

        // Check if the filter matches either the Name or the Barcode
        if (nameText.includes(filter) || barcodeText.includes(filter)) {
            row.style.display = ""; // Show the row
            visibleRowCount++;
        } else {
            row.style.display = "none"; // Hide the row
        }
    });

    // Handle "No products found" row visibility
    if (noProductsRow) {
        // Hide the pre-existing "No products found" row if any products are visible
        noProductsRow.style.display = (visibleRowCount > 0) ? 'none' : 'table-row';
    } else if (visibleRowCount === 0) {
        // If there's no pre-existing message, but no rows are visible, add one for filtering
        if (!document.getElementById('tempNoResults')) {
             productsTableBody.insertAdjacentHTML('beforeend', '<tr id="tempNoResults"><td colspan="11" class="text-center">No results found for this filter.</td></tr>');
        } else {
             document.getElementById('tempNoResults').style.display = 'table-row';
        }
    } else if (visibleRowCount > 0 && document.getElementById('tempNoResults')) {
        // If results are found, remove any temporary message
        document.getElementById('tempNoResults').remove();
    }
}
</script>
</body>
</html>