<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];

// Handle AJAX requests for Create, Update, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            echo json_encode(['status' => 'error', 'message' => 'Category name is required']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO categories (owner_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $owner_id, $name);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Category created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create category']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id <= 0 || $name === '') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
            exit;
        }

        // Make sure this category belongs to the owner
        $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("sii", $name, $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Category updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update category or no changes made']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid category ID']);
            exit;
        }
        // Confirm owner owns the category before deleting
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Category deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete category']);
        }
        $stmt->close();
        exit;
    }
}

// Fetch categories for this owner
$stmt = $conn->prepare("SELECT id, name, created_at FROM categories WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Categories</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-list me-2"></i>Categories</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCreateModal()">
            <i class="fas fa-plus me-1"></i>Create Category
        </button>
    </div>

    <div id="alertPlaceholder"></div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Created At</th>
                <th style="width: 120px;">Actions</th>
            </tr>
        </thead>
        <tbody id="categoriesTableBody">
            <?php if (count($categories) === 0): ?>
                <tr><td colspan="3" class="text-center">No categories found.</td></tr>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <tr data-id="<?= $category['id'] ?>">
                        <td class="category-name"><?= htmlspecialchars($category['name']) ?></td>
                        <td><?= htmlspecialchars($category['created_at']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= $category['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?= $category['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="categoryForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="categoryModalLabel">Create Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetForm()"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" id="categoryId" name="id" value="">
          <div class="mb-3">
              <label for="categoryName" class="form-label">Category Name</label>
              <input type="text" class="form-control" id="categoryName" name="name" required />
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="resetForm()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitBtn">Create</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'), {keyboard: false});

function showAlert(message, type = 'success') {
    const alertPlaceholder = document.getElementById('alertPlaceholder');
    alertPlaceholder.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
}

function resetForm() {
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryModalLabel').innerText = 'Create Category';
    document.getElementById('submitBtn').innerText = 'Create';
}

function openCreateModal() {
    resetForm();
    categoryModal.show();
}

function openEditModal(id) {
    const row = document.querySelector(`tr[data-id='${id}']`);
    const name = row.querySelector('.category-name').innerText;

    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('categoryModalLabel').innerText = 'Edit Category';
    document.getElementById('submitBtn').innerText = 'Update';

    categoryModal.show();
}

function deleteCategory(id) {
    if (!confirm("Are you sure you want to delete this category?")) {
        return;
    }
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'delete', id: id})
    }).then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            // Remove row from table
            const row = document.querySelector(`tr[data-id='${id}']`);
            if(row) row.remove();

            showAlert(data.message);
            // If no rows left, show no categories message
            if(document.querySelectorAll('#categoriesTableBody tr').length === 0) {
                document.getElementById('categoriesTableBody').innerHTML = `<tr><td colspan="3" class="text-center">No categories found.</td></tr>`;
            }
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(() => showAlert('Error deleting category', 'danger'));
}

document.getElementById('categoryForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const id = formData.get('id');
    const action = id ? 'update' : 'create';

    formData.append('action', action);

    fetch(window.location.href, {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            // Close modal
            categoryModal.hide();
            resetForm();
            showAlert(data.message);

            // Reload page to reflect changes
            location.reload();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(() => showAlert('Error processing request', 'danger'));
});
</script>

</body>
</html>