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
        $location = trim($_POST['location'] ?? '');

        if ($name === '' || $location === '') {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO branches (owner_id, name, location) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $owner_id, $name, $location);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Branch created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create branch']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($id <= 0 || $name === '' || $location === '') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
            exit;
        }

        // Make sure this branch belongs to the owner
        $stmt = $conn->prepare("UPDATE branches SET name = ?, location = ? WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("ssii", $name, $location, $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Branch updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update branch or no changes made']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid branch ID']);
            exit;
        }
        // Confirm owner owns the branch before deleting
        $stmt = $conn->prepare("DELETE FROM branches WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Branch deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete branch']);
        }
        $stmt->close();
        exit;
    }
}

// Fetch branches for this owner
$stmt = $conn->prepare("SELECT id, name, location, created_at FROM branches WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$branches = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Branches</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-code-branch me-2"></i>Branches</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal" onclick="openCreateModal()">
            <i class="fas fa-plus me-1"></i>Create Branch
        </button>
    </div>

    <div id="alertPlaceholder"></div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Location</th>
                <th>Created At</th>
                <th style="width: 120px;">Actions</th>
            </tr>
        </thead>
        <tbody id="branchesTableBody">
            <?php if (count($branches) === 0): ?>
                <tr><td colspan="4" class="text-center">No branches found.</td></tr>
            <?php else: ?>
                <?php foreach ($branches as $branch): ?>
                    <tr data-id="<?= $branch['id'] ?>">
                        <td class="branch-name"><a href="branch_products.php?branch_id=<?= $branch['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($branch['name']) ?></a></td>
                        <td class="branch-location"><?= htmlspecialchars($branch['location']) ?></td>
                        <td><?= htmlspecialchars($branch['created_at']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= $branch['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteBranch(<?= $branch['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="branchModal" tabindex="-1" aria-labelledby="branchModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="branchForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="branchModalLabel">Create Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetForm()"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" id="branchId" name="id" value="">
          <div class="mb-3">
              <label for="branchName" class="form-label">Branch Name</label>
              <input type="text" class="form-control" id="branchName" name="name" required />
          </div>
          <div class="mb-3">
              <label for="branchLocation" class="form-label">Location</label>
              <input type="text" class="form-control" id="branchLocation" name="location" required />
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
const branchModal = new bootstrap.Modal(document.getElementById('branchModal'), {keyboard: false});

function showAlert(message, type = 'success') {
    const alertPlaceholder = document.getElementById('alertPlaceholder');
    alertPlaceholder.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
}

function resetForm() {
    document.getElementById('branchForm').reset();
    document.getElementById('branchId').value = '';
    document.getElementById('branchModalLabel').innerText = 'Create Branch';
    document.getElementById('submitBtn').innerText = 'Create';
}

function openCreateModal() {
    resetForm();
    branchModal.show();
}

function openEditModal(id) {
    const row = document.querySelector(`tr[data-id='${id}']`);
    const name = row.querySelector('.branch-name').innerText;
    const location = row.querySelector('.branch-location').innerText;

    document.getElementById('branchId').value = id;
    document.getElementById('branchName').value = name;
    document.getElementById('branchLocation').value = location;
    document.getElementById('branchModalLabel').innerText = 'Edit Branch';
    document.getElementById('submitBtn').innerText = 'Update';

    branchModal.show();
}

function deleteBranch(id) {
    if (!confirm("Are you sure you want to delete this branch?")) {
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
            // If no rows left, show no branches message
            if(document.querySelectorAll('#branchesTableBody tr').length === 0) {
                document.getElementById('branchesTableBody').innerHTML = `<tr><td colspan="4" class="text-center">No branches found.</td></tr>`;
            }
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(() => showAlert('Error deleting branch', 'danger'));
}

document.getElementById('branchForm').addEventListener('submit', function(e) {
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
            branchModal.hide();
            resetForm();
            showAlert(data.message);

            // Reload page or update row dynamically
            // For simplicity: reload page to reflect changes
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