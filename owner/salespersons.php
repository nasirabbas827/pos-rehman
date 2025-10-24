<?php
session_start();
if (!isset($_SESSION["id"])) {
    // Assuming 'owner' is the default role if no specific role check is present for this context
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];

// Fetch branches owned by this owner for dropdown and validation
// Use the 'branches' table for the owner's context
$stmt = $conn->prepare("SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$branches = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Handle Filters ---
$search_name = trim($_GET['search_name'] ?? '');
$filter_branch = (int)($_GET['branch_id'] ?? 0);

// Fetch salespersons joined with branches (filtered)
$sql = "
    SELECT sp.id, sp.name, sp.email, sp.created_at, b.name AS branch_name, sp.branch_id
    FROM salespersons sp
    LEFT JOIN branches b ON sp.branch_id = b.id
    WHERE b.owner_id = ?
";

$params = [$owner_id];
$types = "i";

if ($search_name !== '') {
    $sql .= " AND sp.name LIKE ?";
    $params[] = "%{$search_name}%";
    $types .= "s";
}

if ($filter_branch > 0) {
    $sql .= " AND sp.branch_id = ?";
    $params[] = $filter_branch;
    $types .= "i";
}

$sql .= " ORDER BY sp.created_at DESC";

$stmt = $conn->prepare($sql);
// Check if the statement was prepared successfully
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$salespersons = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- CRUD Operations (AJAX/Fetch) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // Helper function to validate ownership of a branch
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
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if (empty($name) || empty($email) || empty($password) || $branch_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'All fields (including password) are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
            exit;
        }

        if (!isBranchOwnedBy($conn, $branch_id, $owner_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid branch selected']);
            exit;
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM salespersons WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO salespersons (name, email, password, branch_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $email, $hashed_password, $branch_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Salesperson added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add salesperson']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if ($id <= 0 || empty($name) || empty($email) || $branch_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data provided']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
            exit;
        }

        if (!isBranchOwnedBy($conn, $branch_id, $owner_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid branch selected']);
            exit;
        }
        
        // Check if the salesperson actually belongs to an owner's branch
        $stmt = $conn->prepare("SELECT sp.id FROM salespersons sp INNER JOIN branches b ON sp.branch_id = b.id WHERE sp.id = ? AND b.owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Salesperson not found or unauthorized']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        // Check for duplicate email (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM salespersons WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already in use']);
            $stmt->close();
            exit;
        }
        $stmt->close();


        $params_update = [];
        $types_update = "";
        $sql_update = "UPDATE salespersons SET name = ?, email = ?, branch_id = ?";
        $params_update[] = $name;
        $params_update[] = $email;
        $params_update[] = $branch_id;
        $types_update .= "ssi";

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_update .= ", password = ?";
            $params_update[] = $hashed_password;
            $types_update .= "s";
        }
        
        $sql_update .= " WHERE id = ? AND branch_id IN (SELECT id FROM branches WHERE owner_id = ?)";
        $params_update[] = $id;
        $params_update[] = $owner_id;
        $types_update .= "ii";

        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param($types_update, ...$params_update);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Salesperson updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update salesperson or no changes made']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }
        // Ensure the salesperson belongs to a branch owned by the current owner before deleting
        $stmt = $conn->prepare("DELETE sp FROM salespersons sp INNER JOIN branches b ON sp.branch_id = b.id WHERE sp.id = ? AND b.owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Salesperson deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete salesperson']);
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
    <title>Manage Salespersons</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        /* Add some basic styles to match the customer page appearance if needed */
        body { margin-left: 280px; } /* Adjust if you use a full-width sidebar */
        .container { padding: 2rem 1rem; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>

<?php include('sidebar.php'); // Assuming you have an owner sidebar ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-handshake me-2"></i>Salespersons</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#salespersonModal" onclick="openCreateModal()">
            <i class="fas fa-user-plus me-1"></i>Add Salesperson
        </button>
    </div>

    <form class="row g-3 mb-4" method="get">
        <div class="col-md-5">
            <input type="text" name="search_name" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search_name) ?>">
        </div>
        <div class="col-md-4">
            <select name="branch_id" class="form-select">
                <option value="0">Filter by Branch</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $filter_branch == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex">
            <button type="submit" class="btn btn-dark me-2"><i class="fas fa-filter"></i> Filter</button>
            <a href="salespersons.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
        </div>
    </form>

    <div id="alertPlaceholder"></div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Branch</th>
                <th>Created At</th>
                <th style="width: 130px;">Actions</th>
            </tr>
        </thead>
        <tbody id="salespersonsTableBody">
            <?php if (count($salespersons) === 0): ?>
                <tr><td colspan="5" class="text-center">No salespersons found.</td></tr>
            <?php else: ?>
                <?php foreach ($salespersons as $sp): ?>
                    <tr data-id="<?= $sp['id'] ?>">
                        <td class="sp-name"><?= htmlspecialchars($sp['name']) ?></td>
                        <td class="sp-email"><?= htmlspecialchars($sp['email']) ?></td>
                        <td class="sp-branch" data-branch-id="<?= $sp['branch_id'] ?>"><?= htmlspecialchars($sp['branch_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($sp['created_at']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= $sp['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteSalesperson(<?= $sp['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="salespersonModal" tabindex="-1" aria-labelledby="salespersonModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="salespersonForm" class="modal-content needs-validation" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="salespersonModalLabel">Add Salesperson</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetForm()"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="salespersonId" name="id">
        <div class="mb-3">
            <label for="name" class="form-label">Name *</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email *</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label" id="passwordLabel">Password *</label>
            <input type="password" class="form-control" id="password" name="password" required>
            <small class="form-text text-muted" id="passwordHint"></small>
        </div>
        <div class="mb-3">
            <label for="branch_id" class="form-label">Branch *</label>
            <select class="form-select" id="branch_id" name="branch_id" required>
                <option value="">Select branch</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="resetForm()">Cancel</button>
        <button type="submit" class="btn btn-success" id="modalSubmitBtn">Add</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('salespersonModal'));
const form = document.getElementById('salespersonForm');
const alertPlaceholder = document.getElementById('alertPlaceholder');
const passwordInput = document.getElementById('password');
const passwordHint = document.getElementById('passwordHint');

function showAlert(message, type='success') {
    alertPlaceholder.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function resetForm() {
    form.reset();
    form.classList.remove('was-validated');
    document.getElementById('salespersonId').value = '';
    document.getElementById('modalSubmitBtn').textContent = 'Add';
    document.getElementById('modalSubmitBtn').className = 'btn btn-success';
    document.getElementById('salespersonModalLabel').textContent = 'Add Salesperson';
    
    // Reset password field to required for 'Add'
    passwordInput.required = true;
    passwordHint.textContent = 'Required for a new salesperson.';
}

function openCreateModal() {
    resetForm();
    modal.show();
}

function openEditModal(id) {
    resetForm();
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    
    document.getElementById('salespersonId').value = id;
    document.getElementById('name').value = row.querySelector('.sp-name').textContent.trim();
    document.getElementById('email').value = row.querySelector('.sp-email').textContent.trim();
    document.getElementById('branch_id').value = row.querySelector('.sp-branch').getAttribute('data-branch-id');
    
    document.getElementById('modalSubmitBtn').textContent = 'Update';
    document.getElementById('modalSubmitBtn').className = 'btn btn-info';
    document.getElementById('salespersonModalLabel').textContent = 'Edit Salesperson';
    
    // Set password field to optional for 'Edit'
    passwordInput.required = false;
    passwordHint.textContent = 'Leave blank to keep current password.';
    
    modal.show();
}

function deleteSalesperson(id) {
    if (!confirm('Are you sure you want to delete this salesperson?')) return;
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: 'delete', id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showAlert(data.message);
            document.querySelector(`tr[data-id="${id}"]`)?.remove();
            if (document.querySelectorAll('#salespersonsTableBody tr').length === 0)
                document.getElementById('salespersonsTableBody').innerHTML = '<tr><td colspan="5" class="text-center">No salespersons found.</td></tr>';
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error processing request', 'danger'));
}

form.addEventListener('submit', e => {
    e.preventDefault();
    e.stopPropagation();
    
    // Custom check for password field in Edit mode
    const isEditing = document.getElementById('salespersonId').value !== '';
    if (isEditing && passwordInput.value === '') {
        passwordInput.required = false; // Temporarily make optional for submission if empty
    } else if (!isEditing) {
        passwordInput.required = true; // Ensure required for 'create'
    }

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(form);
    let action = formData.get('id') ? 'update' : 'create';
    formData.set('action', action);
    
    fetch('', { method: 'POST', body: new URLSearchParams(formData) })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            modal.hide();
            resetForm();
            showAlert(data.message);
            // Reload the page to show the updated data (simplest way to update table)
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