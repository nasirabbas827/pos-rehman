<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include('../config.php');
$owner_id = $_SESSION["id"];

// Fetch branches owned by this owner for dropdown and validation
$stmt = $conn->prepare("SELECT id, name FROM branches WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$branches = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle filters
$search_name = trim($_GET['search_name'] ?? '');
$filter_branch = (int)($_GET['branch_id'] ?? 0);

// Fetch customers joined with branches (filtered)
$sql = "
    SELECT c.id, c.name, c.email, c.phone_number, c.address, c.created_at, b.name AS branch_name, c.branch_id
    FROM customers c
    LEFT JOIN branches b ON c.branch_id = b.id
    WHERE b.owner_id = ?
";

$params = [$owner_id];
$types = "i";

if ($search_name !== '') {
    $sql .= " AND c.name LIKE ?";
    $params[] = "%{$search_name}%";
    $types .= "s";
}

if ($filter_branch > 0) {
    $sql .= " AND c.branch_id = ?";
    $params[] = $filter_branch;
    $types .= "i";
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

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
        $phone = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if ($name === '' || $email === '' || $phone === '' || $address === '' || $branch_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
            exit;
        }

        if (!isBranchOwnedBy($conn, $branch_id, $owner_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid branch']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO customers (name, email, phone_number, address, branch_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $email, $phone, $address, $branch_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Customer added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add customer']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if ($id <= 0 || $name === '' || $email === '' || $phone === '' || $address === '' || $branch_id <= 0) {
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

        $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already in use']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("
            UPDATE customers
            SET name = ?, email = ?, phone_number = ?, address = ?, branch_id = ?
            WHERE id = ? AND branch_id IN (SELECT id FROM branches WHERE owner_id = ?)
        ");
        $stmt->bind_param("ssssiii", $name, $email, $phone, $address, $branch_id, $id, $owner_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Customer updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update customer or no changes made']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid customer ID']);
            exit;
        }
        $stmt = $conn->prepare("DELETE c FROM customers c INNER JOIN branches b ON c.branch_id = b.id WHERE c.id = ? AND b.owner_id = ?");
        $stmt->bind_param("ii", $id, $owner_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Customer deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete customer']);
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
    <title>Manage Customers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        .customer-name-link {
        font-weight: bold;
        color: #007bff; /* Blue link color */
        text-decoration: none;
    }
    .customer-name-link:hover {
        text-decoration: underline;
    }
    </style>
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Customers</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openCreateModal()">
            <i class="fas fa-plus me-1"></i>Add Customer
        </button>
    </div>

    <!-- Search & Filter Form -->
    <form class="row g-3 mb-4" method="get">
        <div class="col-md-5">
            <input type="text" name="search_name" class="form-control" placeholder="Search by customer name..." value="<?= htmlspecialchars($search_name) ?>">
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
            <a href="customers.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
        </div>
    </form>

    <div id="alertPlaceholder"></div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Branch</th>
                <th>Created At</th>
                <th style="width: 130px;">Actions</th>
            </tr>
        </thead>
        <tbody id="customersTableBody">
            <?php if (count($customers) === 0): ?>
                <tr><td colspan="7" class="text-center">No customers found.</td></tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                    <tr data-id="<?= $c['id'] ?>">
                        <td class="cust-name">
                            <a href="owner_customer_khata.php?customer_id=<?= $c['id'] ?>" class="customer-name-link">
                                <?= htmlspecialchars($c['name']) ?>
                            </a>
                        </td>
                        <td class="cust-email"><?= htmlspecialchars($c['email']) ?></td>
                        <td class="cust-phone"><?= htmlspecialchars($c['phone_number']) ?></td>
                        <td class="cust-address"><?= htmlspecialchars($c['address']) ?></td>
                        <td class="cust-branch" data-branch-id="<?= $c['branch_id'] ?>"><?= htmlspecialchars($c['branch_name']) ?></td>
                        <td><?= htmlspecialchars($c['created_at']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="openEditModal(<?= $c['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCustomer(<?= $c['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="customerForm" class="modal-content needs-validation" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="customerModalLabel">Add Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetForm()"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="customerId" name="id">
        <div class="mb-3">
            <label for="name" class="form-label">Name *</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email *</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="mb-3">
            <label for="phone_number" class="form-label">Phone *</label>
            <input type="text" class="form-control" id="phone_number" name="phone_number" required>
        </div>
        <div class="mb-3">
            <label for="address" class="form-label">Address *</label>
            <textarea class="form-control" id="address" name="address" required></textarea>
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
        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Add</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('customerModal'));
const form = document.getElementById('customerForm');
const alertPlaceholder = document.getElementById('alertPlaceholder');

function showAlert(message, type='success') {
    alertPlaceholder.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function resetForm() {
    form.reset();
    form.classList.remove('was-validated');
    document.getElementById('customerId').value = '';
    document.getElementById('modalSubmitBtn').textContent = 'Add';
    document.getElementById('customerModalLabel').textContent = 'Add Customer';
}

function openCreateModal() {
    resetForm();
    modal.show();
}

function openEditModal(id) {
    resetForm();
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    document.getElementById('customerId').value = id;
    document.getElementById('name').value = row.querySelector('.cust-name').textContent.trim();
    document.getElementById('email').value = row.querySelector('.cust-email').textContent.trim();
    document.getElementById('phone_number').value = row.querySelector('.cust-phone').textContent.trim();
    document.getElementById('address').value = row.querySelector('.cust-address').textContent.trim();
    document.getElementById('branch_id').value = row.querySelector('.cust-branch').getAttribute('data-branch-id');
    document.getElementById('modalSubmitBtn').textContent = 'Update';
    document.getElementById('customerModalLabel').textContent = 'Edit Customer';
    modal.show();
}

function deleteCustomer(id) {
    if (!confirm('Are you sure you want to delete this customer?')) return;
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
            if (document.querySelectorAll('#customersTableBody tr').length === 0)
                document.getElementById('customersTableBody').innerHTML = '<tr><td colspan="7" class="text-center">No customers found.</td></tr>';
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error processing request', 'danger'));
}

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
    fetch('', { method: 'POST', body: new URLSearchParams(formData) })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            modal.hide();
            resetForm();
            showAlert(data.message);
            location.reload();
        } else showAlert(data.message, 'danger');
    })
    .catch(() => showAlert('Error processing request', 'danger'));
});
</script>
</body>
</html>
