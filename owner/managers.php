<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];

// Fetch branches owned by this owner for branch selection dropdown and validation
$stmt = $conn->prepare("SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$branches = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// For listing managers joined with branches owned by owner
$sql = "
    SELECT m.id, m.full_name, m.email, m.assigned_at, b.name AS branch_name, m.branch_id
    FROM managers m
    INNER JOIN branches b ON m.branch_id = b.id
    WHERE b.owner_id = ?
    ORDER BY m.assigned_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$managers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // Helper: validate branch_id belongs to owner
    function isBranchOwnedBy($conn, $branch_id, $owner_id) {
        $stmt = $conn->prepare("SELECT id FROM branches WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $branch_id, $owner_id);
        $stmt->execute();
        $stmt->store_result();
        $owned = $stmt->num_rows > 0;
        $stmt->close();
        return $owned;
    }

    if ($action === 'create') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if ($full_name === '' || $email === '' || $password === '' || $branch_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
            exit;
        }

        if (!isBranchOwnedBy($conn, $branch_id, $owner_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid branch']);
            exit;
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM managers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO managers (full_name, email, password, branch_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $full_name, $email, $hashed_password, $branch_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Manager created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create manager']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if ($id <= 0 || $full_name === '' || $email === '' || $branch_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
            exit;
        }

        if (!isBranchOwnedBy($conn, $branch_id, $owner_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid branch']);
            exit;
        }

        // Check if email belongs to another manager
        $stmt = $conn->prepare("SELECT id FROM managers WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already used by another manager']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // If password provided, hash it; else keep old password
        if ($password !== '') {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE managers SET full_name = ?, email = ?, password = ?, branch_id = ? WHERE id = ? AND branch_id IN (SELECT id FROM branches WHERE owner_id = ?)");
            $stmt->bind_param("sssiii", $full_name, $email, $hashed_password, $branch_id, $id, $owner_id);
        } else {
            $stmt = $conn->prepare("UPDATE managers SET full_name = ?, email = ?, branch_id = ? WHERE id = ? AND branch_id IN (SELECT id FROM branches WHERE owner_id = ?)");
            $stmt->bind_param("ssiii", $full_name, $email, $branch_id, $id, $owner_id);
        }

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Manager updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update manager or no changes made']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid manager ID']);
            exit;
        }
        // Delete only if manager belongs to a branch owned by the owner
        $stmt = $conn->prepare("DELETE m FROM managers m INNER JOIN branches b ON m.branch_id = b.id WHERE m.id = ? AND b.owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Manager deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete manager']);
        }
        $stmt->close();
        exit;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Managers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-tie me-2"></i>Managers</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#managerModal" onclick="openCreateModal()">
            <i class="fas fa-plus me-1"></i>Create Manager
        </button>
    </div>

    <div id="alertPlaceholder"></div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Branch</th>
                <th>Assigned At</th>
                <th style="width: 130px;">Actions</th>
            </tr>
        </thead>
        <tbody id="managersTableBody">
            <?php if (count($managers) === 0): ?>
                <tr><td colspan="5" class="text-center">No managers found.</td></tr>
            <?php else: ?>
                <?php foreach ($managers as $m): ?>
                    <tr data-id="<?= $m['id'] ?>">
                        <td class="manager-full_name"><?= htmlspecialchars($m['full_name']) ?></td>
                        <td class="manager-email"><?= htmlspecialchars($m['email']) ?></td>
                        <td class="manager-branch_id" data-branch-id="<?= $m['branch_id'] ?>"><?= htmlspecialchars($m['branch_name']) ?></td>
                        <td><?= htmlspecialchars($m['assigned_at']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= $m['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteManager(<?= $m['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- Modal -->
<div class="modal fade" id="managerModal" tabindex="-1" aria-labelledby="managerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="managerForm" class="modal-content needs-validation" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="managerModalLabel">Create Manager</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetForm()"></button>
      </div>
      <div class="modal-body">

            <input type="hidden" id="managerId" name="id" value="">

            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name *</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required>
                <div class="invalid-feedback">Please enter full name.</div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div class="invalid-feedback">Please enter a valid email.</div>
            </div>

            <div class="mb-3" id="passwordGroup">
                <label for="password" class="form-label">Password *</label>
                <input type="password" class="form-control" id="password" name="password" minlength="6" >
                <div class="form-text" id="passwordHelp">Min 6 characters</div>
                <div class="invalid-feedback">Please enter a password (min 6 chars).</div>
            </div>

            <div class="mb-3">
                <label for="branch_id" class="form-label">Branch *</label>
                <select class="form-select" id="branch_id" name="branch_id" required>
                    <option value="">Select branch</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Please select a branch.</div>
            </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="resetForm()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Create</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const managerModal = new bootstrap.Modal(document.getElementById('managerModal'));
const form = document.getElementById('managerForm');
const alertPlaceholder = document.getElementById('alertPlaceholder');

function showAlert(message, type='success') {
    alertPlaceholder.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function resetForm() {
    form.reset();
    form.classList.remove('was-validated');
    document.getElementById('managerId').value = '';
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('password').required = true;
    document.getElementById('modalSubmitBtn').textContent = 'Create';
    document.getElementById('managerModalLabel').textContent = 'Create Manager';
}

function openCreateModal() {
    resetForm();
    managerModal.show();
}

function openEditModal(id) {
    resetForm();
    // Find the row data
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;

    document.getElementById('managerId').value = id;
    document.getElementById('full_name').value = row.querySelector('.manager-full_name').textContent.trim();
    document.getElementById('email').value = row.querySelector('.manager-email').textContent.trim();

    // Branch select
    const branchId = row.querySelector('.manager-branch_id').getAttribute('data-branch-id');
    document.getElementById('branch_id').value = branchId;

    // Password optional on edit - hide required and clear
    document.getElementById('password').value = '';
    document.getElementById('passwordGroup').style.display = 'block'; 
    document.getElementById('password').required = false;

    document.getElementById('modalSubmitBtn').textContent = 'Update';
    document.getElementById('managerModalLabel').textContent = 'Edit Manager';

    managerModal.show();
}

function deleteManager(id) {
    if (!confirm('Are you sure you want to delete this manager?')) return;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'delete', id: id})
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert(data.message);
            // Remove the row from the table
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.remove();

            // Show "no managers" message if empty
            if (document.querySelectorAll('#managersTableBody tr').length === 0) {
                document.getElementById('managersTableBody').innerHTML = '<tr><td colspan="5" class="text-center">No managers found.</td></tr>';
            }
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(() => showAlert('Error processing request', 'danger'));
}

// Form submit handler
form.addEventListener('submit', e => {
    e.preventDefault();
    e.stopPropagation();

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const formData = new FormData(form);
    let action = formData.get('id') ? 'update' : 'create';
    formData.set('action', action);

    // Send AJAX request
    fetch('', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            managerModal.hide();
            resetForm();
            showAlert(data.message);

            // Reload to reflect changes
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
