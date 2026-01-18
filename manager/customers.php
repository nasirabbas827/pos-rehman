<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "manager") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$manager_id = $_SESSION["id"];

// Fetch manager info & branch
$sql_manager = "SELECT full_name, email, branch_id FROM managers WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_manager);
mysqli_stmt_bind_param($stmt, "i", $manager_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $full_name, $email, $branch_id);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// CRUD Operations
if (isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];

    $sql = "INSERT INTO customers (name, email, phone_number, address, branch_id) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $phone_number, $address, $branch_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: customers.php");
    exit();
}

if (isset($_POST['edit_customer'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];

    $sql = "UPDATE customers SET name=?, email=?, phone_number=?, address=? WHERE id=? AND branch_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssii", $name, $email, $phone_number, $address, $id, $branch_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: customers.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM customers WHERE id=? AND branch_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $branch_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: customers.php");
    exit();
}

// Search Filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT * FROM customers WHERE branch_id = ? AND (name LIKE ? OR email LIKE ? OR phone_number LIKE ?) ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
$search_param = "%$search%";
mysqli_stmt_bind_param($stmt, "isss", $branch_id, $search_param, $search_param, $search_param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Customers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin-left: 280px;
        }
        .container { padding: 2rem 1rem; }
        h2 { color: #333; font-size: 1.8rem; margin-bottom: 1.5rem; }
        .table { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .table-hover tbody tr:hover { background-color: #f1f6ff; }
        .btn-sm { font-size: 0.85rem; }
        @media (max-width: 992px) { body { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include('manager_sidebar.php'); ?>

    <div class="container py-4">
        <h2><i class="fas fa-users me-2"></i>Manage Customers (<?php echo htmlspecialchars($full_name); ?>)</h2>

        <form method="GET" class="mb-3 d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i></button>
            <a href="customers.php" class="btn btn-secondary" title="Reset Search"><i class="fas fa-redo"></i></a>
        </form>

        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#upsertModal" data-action="add">
            <i class="fas fa-user-plus"></i> Add Customer
        </button>
    <div class="table-container">
        <div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <a href="customer_khata.php?customer_id=<?php echo $row['id']; ?>" class="text-primary fw-bold text-decoration-none">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                    <td>
                        <button 
                            class="btn btn-warning btn-sm edit-btn" 
                            data-bs-toggle="modal" 
                            data-bs-target="#upsertModal"
                            data-action="edit"
                            data-id="<?php echo $row['id']; ?>"
                            data-name="<?php echo htmlspecialchars($row['name']); ?>"
                            data-email="<?php echo htmlspecialchars($row['email']); ?>"
                            data-phone="<?php echo htmlspecialchars($row['phone_number']); ?>"
                            data-address="<?php echo htmlspecialchars($row['address']); ?>"
                        ><i class="fas fa-edit"></i></button>
                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this customer?');"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center">No customers found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="upsertModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="upsertForm" class="modal-content">
                <div class="modal-header text-white" id="modalHeader">
                    <h5 class="modal-title" id="modalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="customer_id" value="">
                    
                    <div class="mb-3"><label>Name</label><input type="text" name="name" id="name" class="form-control" required></div>
                    <div class="mb-3"><label>Email</label><input type="email" name="email" id="email" class="form-control" required></div>
                    <div class="mb-3"><label>Phone</label><input type="text" name="phone_number" id="phone_number" class="form-control" required></div>
                    <div class="mb-3"><label>Address</label><textarea name="address" id="address" class="form-control" required></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="action_button" id="action_button" class="btn"></button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Get the single modal element
    const upsertModal = document.getElementById('upsertModal');
    
    // Listen for the Bootstrap modal 'show' event
    upsertModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; // Button that triggered the modal
        const action = button.getAttribute('data-action');
        
        // Get modal elements for dynamic update
        const modalTitle = document.getElementById('modalTitle');
        const modalHeader = document.getElementById('modalHeader');
        const customerId = document.getElementById('customer_id');
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone_number');
        const addressInput = document.getElementById('address');
        const actionButton = document.getElementById('action_button');
        const upsertForm = document.getElementById('upsertForm');

        if (action === 'add') {
            // --- ADD MODE ---
            modalTitle.textContent = 'Add New Customer';
            modalHeader.className = 'modal-header bg-success text-white';
            actionButton.textContent = 'Add Customer';
            actionButton.name = 'add_customer';
            actionButton.className = 'btn btn-success';
            
            // Clear all fields for a new entry
            customerId.value = '';
            nameInput.value = '';
            emailInput.value = '';
            phoneInput.value = '';
            addressInput.value = '';

        } else if (action === 'edit') {
            // --- EDIT MODE ---
            modalTitle.textContent = 'Edit Customer';
            modalHeader.className = 'modal-header bg-primary text-white';
            actionButton.textContent = 'Update Customer';
            actionButton.name = 'edit_customer';
            actionButton.className = 'btn btn-primary';

            // Extract data from the trigger button
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const email = button.getAttribute('data-email');
            const phone = button.getAttribute('data-phone');
            const address = button.getAttribute('data-address');
            
            // Populate modal fields
            customerId.value = id;
            nameInput.value = name;
            emailInput.value = email;
            phoneInput.value = phone;
            addressInput.value = address;
        }
    });
</script>
</body>
</html>

<?php 
mysqli_close($conn);
?>