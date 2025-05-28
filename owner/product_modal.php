<?php
session_start();
if (!isset($_SESSION["id"])) {
    http_response_code(403);
    exit("Access denied");
}

include('../config.php');
$owner_id = $_SESSION["id"];

function isBranchOwnedBy($conn, $branch_id, $owner_id) {
    $stmt = $conn->prepare("SELECT id FROM branches WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $branch_id, $owner_id);
    $stmt->execute();
    $stmt->store_result();
    $owned = $stmt->num_rows > 0;
    $stmt->close();
    return $owned;
}

// Handle POST requests (create/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
            exit;
        }
        // Delete only if product belongs to branch owned by owner
        $stmt = $conn->prepare("DELETE p FROM products p INNER JOIN branches b ON p.branch_id = b.id WHERE p.id = ? AND b.owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found or unauthorized']);
        }
        $stmt->close();
        exit;
    }

    // Validate inputs
    $id = (int)($_POST['id'] ?? 0);
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    
    if (!$branch_id || !$name || !is_numeric($price) || !is_numeric($quantity)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields correctly']);
        exit;
    }
    if (!isBranchOwnedBy($conn, $branch_id, $owner_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid branch selected']);
        exit;
    }
    $price = floatval($price);
    $quantity = intval($quantity);
    if ($price < 0 || $quantity < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Price and quantity cannot be negative']);
        exit;
    }

    // Handle image upload (optional on update)
    $imageName = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $img = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($img['type'], $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid image type']);
            exit;
        }
        if ($img['size'] > 2 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'Image too large (max 2MB)']);
            exit;
        }
        $ext = pathinfo($img['name'], PATHINFO_EXTENSION);
        $imageName = uniqid('prod_') . '.' . $ext;
        if (!move_uploaded_file($img['tmp_name'], __DIR__ . '/images/' . $imageName)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload image']);
            exit;
        }
    }

    if ($id > 0) {
        // Update
        // Get old image if exists
        $stmt = $conn->prepare("SELECT image FROM products p INNER JOIN branches b ON p.branch_id = b.id WHERE p.id = ? AND b.owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        $stmt->execute();
        $stmt->bind_result($oldImage);
        if (!$stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found or unauthorized']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // If new image uploaded, delete old one
        if ($imageName && $oldImage && file_exists(__DIR__ . '/images/' . $oldImage)) {
            unlink(__DIR__ . '/images/' . $oldImage);
        }

        $sql = "UPDATE products SET branch_id = ?, name = ?, description = ?, price = ?, quantity = ?";
        if ($imageName) $sql .= ", image = ?";
        $sql .= " WHERE id = ?";

        if ($imageName) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdisi", $branch_id, $name, $description, $price, $quantity, $imageName, $id);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdii", $branch_id, $name, $description, $price, $quantity, $id);
        }
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update product']);
        }
        $stmt->close();

    } else {
        // Create: generate unique barcode_id (random 12 digit number)
        do {
            $barcode = strval(random_int(100000000000, 999999999999));
            $res = $conn->query("SELECT id FROM products WHERE barcode_id = '$barcode'");
        } while ($res->num_rows > 0);

        // Require image on create
        if (!$imageName) {
            echo json_encode(['status' => 'error', 'message' => 'Product image is required']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO products (branch_id, barcode_id, name, description, price, quantity, image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssdis", $branch_id, $barcode, $name, $description, $price, $quantity, $imageName);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Product created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create product']);
        }
        $stmt->close();
    }
    exit;
}

// If GET request, render modal HTML
$id = (int)($_GET['id'] ?? 0);
$product = null;
if ($id > 0) {
    // Fetch product for editing
    $sql = "SELECT p.*, b.owner_id FROM products p INNER JOIN branches b ON p.branch_id = b.id WHERE p.id = ? AND b.owner_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    if (!$product) {
        http_response_code(404);
        exit("Product not found or unauthorized");
    }
}

// Fetch branches again for dropdown
$stmt = $conn->prepare("SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form id="productForm" class="modal-content needs-validation" novalidate enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="productModalLabel"><?= $product ? 'Edit Product' : 'Create Product' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <input type="hidden" name="id" value="<?= $product ? $product['id'] : '' ?>">

        <div class="mb-3">
            <label for="branch_id" class="form-label">Branch *</label>
            <select class="form-select" id="branch_id" name="branch_id" required>
                <option value="">Select branch</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $product && $product['branch_id'] == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select a branch.</div>
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">Product Name *</label>
            <input type="text" class="form-control" id="name" name="name" required maxlength="255" value="<?= $product ? htmlspecialchars($product['name']) : '' ?>">
            <div class="invalid-feedback">Please enter product name.</div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= $product ? htmlspecialchars($product['description']) : '' ?></textarea>
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Price (USD) *</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?= $product ? htmlspecialchars($product['price']) : '' ?>">
            <div class="invalid-feedback">Please enter a valid price.</div>
        </div>

        <div class="mb-3">
            <label for="quantity" class="form-label">Quantity *</label>
            <input type="number" min="0" class="form-control" id="quantity" name="quantity" required value="<?= $product ? htmlspecialchars($product['quantity']) : '0' ?>">
            <div class="invalid-feedback">Please enter available quantity.</div>
        </div>

        <div class="mb-3">
            <label for="image" class="form-label">Product Image <?= $product ? '(leave blank to keep current)' : '*' ?></label>
            <input type="file" accept="image/*" class="form-control" id="image" name="image" <?= $product ? '' : 'required' ?>>
            <?php if ($product && $product['image']): ?>
                <img src="images/<?= htmlspecialchars($product['image']) ?>" alt="Current Image" style="max-width: 150px; margin-top: 10px; border-radius: 6px;">
            <?php endif; ?>
            <div class="invalid-feedback">Please upload a product image.</div>
        </div>

        <?php if ($product): ?>
            <div class="mb-3">
                <label class="form-label">Barcode</label>
                <input type="text" class="form-control" readonly value="<?= htmlspecialchars($product['barcode_id']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Created At</label>
                <input type="text" class="form-control" readonly value="<?= htmlspecialchars($product['created_at']) ?>">
            </div>
        <?php endif; ?>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= $product ? 'Update' : 'Create' ?></button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    const formData = new FormData(form);
    const response = await fetch('path_to_this_php_file.php', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    alert(result.message);
    if (result.status === 'success') {
        // Close modal and refresh product list (implement as needed)
        var modal = bootstrap.Modal.getInstance(document.getElementById('productModal'));
        modal.hide();
        // Reload products list here
        location.reload();
    }
});
</script>
