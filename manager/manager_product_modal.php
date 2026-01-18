<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") { exit; }
include('../config.php');

$manager_id = $_SESSION["id"];
$res = $conn->query("SELECT branch_id FROM managers WHERE id = $manager_id")->fetch_assoc();
$branch_id = $res['branch_id'];

// Handling POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Security check: ensure product belongs to manager's branch
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND branch_id = ?");
        $stmt->bind_param("ii", $id, $branch_id);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $category_id = (int)$_POST['category_id'];
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $threshold = intval($_POST['low_stock_threshold'] ?? 10);
    $unit = $_POST['unit'];
    $barcode = trim($_POST['barcode_id']);

    if (empty($barcode) && $id == 0) {
        $barcode = strval(random_int(100000000000, 999999999999));
    }

    $img_name = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $img_name = uniqid('p_') . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        // Save into Owner's image folder
        move_uploaded_file($_FILES['image']['tmp_name'], '../owner/images/' . $img_name);
    }

    if ($id > 0) {
        // Update logic (with branch check for security)
        $sql = "UPDATE products SET category_id=?, name=?, description=?, price=?, quantity=?, unit=?, low_stock_threshold=?, barcode_id=?";
        $types = "issdisis";
        $params = [$category_id, $name, $_POST['description'], $price, $quantity, $unit, $threshold, $barcode];
        if ($img_name) { $sql .= ", image=?"; $params[] = $img_name; $types .= "s"; }
        $sql .= " WHERE id=? AND branch_id=?"; $params[] = $id; $params[] = $branch_id; $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
    } else {
        // Insert logic
        $stmt = $conn->prepare("INSERT INTO products (branch_id, category_id, barcode_id, name, description, price, quantity, unit, image, low_stock_threshold, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->bind_param("iisssdisis", $branch_id, $category_id, $barcode, $name, $_POST['description'], $price, $quantity, $unit, $img_name, $threshold);
    }

    if ($stmt->execute()) echo json_encode(['status' => 'success']);
    else echo json_encode(['status' => 'error', 'message' => 'Save failed']);
    exit;
}

// Handling GET (Render UI)
$id = (int)($_GET['id'] ?? 0);
$p = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND branch_id = ?");
    $stmt->bind_param("ii", $id, $branch_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
}
$cats = $conn->query("SELECT id, name FROM categories WHERE owner_id = (SELECT owner_id FROM branches WHERE id = $branch_id)")->fetch_all(MYSQLI_ASSOC);
?>

<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form id="productForm" class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><?= $p ? 'Edit' : 'Add' ?> Product (Branch Only)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" value="<?= $p['id'] ?? '' ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Category *</label>
                <select class="form-select" name="category_id" required>
                    <?php foreach($cats as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($p['category_id']??'')==$c['id']?'selected':'' ?>><?= $c['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Product Name *</label>
                <input type="text" class="form-control" name="name" value="<?= $p['name']??'' ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Price (RS) *</label>
                <input type="number" step="0.01" class="form-control" name="price" value="<?= $p['price']??'' ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Stock Quantity *</label>
                <input type="number" class="form-control" name="quantity" value="<?= $p['quantity']??'0' ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Low Stock Limit</label>
                <input type="number" class="form-control" name="low_stock_threshold" value="<?= $p['low_stock_threshold']??'10' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Unit *</label>
                <select class="form-select" name="unit" required>
                    <?php $us=['Piece','Gram','Kilogram','Liter','Dozen']; foreach($us as $u): ?>
                        <option value="<?= $u ?>" <?= ($p['unit']??'')==$u?'selected':'' ?>><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Barcode ID (Optional)</label>
                <input type="text" class="form-control" name="barcode_id" value="<?= $p['barcode_id']??'' ?>" placeholder="Leave blank for auto-generation">
            </div>
            <div class="col-md-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2"><?= $p['description']??'' ?></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Product Image</label>
                <input type="file" class="form-control" name="image" <?= $p ? '' : 'required' ?>>
                <?php if($p): ?><small class="text-muted">Currently: <?= $p['image'] ?></small><?php endif; ?>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Save Product</button>
      </div>
    </form>
  </div>
</div>