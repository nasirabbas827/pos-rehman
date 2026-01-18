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
if (isset($_POST['add_salesperson'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO salespersons (name, email, password, branch_id) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssi", $name, $email, $password, $branch_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: salespersons.php");
    exit();
}

if (isset($_POST['edit_salesperson'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    if ($password) {
        $sql = "UPDATE salespersons SET name=?, email=?, password=? WHERE id=? AND branch_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssii", $name, $email, $password, $id, $branch_id);
    } else {
        $sql = "UPDATE salespersons SET name=?, email=? WHERE id=? AND branch_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $name, $email, $id, $branch_id);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: salespersons.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM salespersons WHERE id=? AND branch_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $branch_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: salespersons.php");
    exit();
}

// Search Filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT * FROM salespersons WHERE branch_id = ? AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
$search_param = "%$search%";
mysqli_stmt_bind_param($stmt, "iss", $branch_id, $search_param, $search_param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Salespersons</title>
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
        <h2><i class="fas fa-user-tie me-2"></i>Manage Salespersons (<?php echo htmlspecialchars($full_name); ?>)</h2>

        <form method="GET" class="mb-3 d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Search salespersons..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i></button>
            <a href="salespersons.php" class="btn btn-secondary" title="Reset Search"><i class="fas fa-redo"></i></a>
        </form>

        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#upsertModal" data-action="add">
            <i class="fas fa-user-plus"></i> Add Salesperson
        </button>

    <div class="table-container">
        <div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
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
                        ><i class="fas fa-edit"></i></button>
                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this salesperson?');"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center">No salespersons found.</td></tr>
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
                    <input type="hidden" name="id" id="salesperson_id" value="">
                    
                    <div class="mb-3"><label>Name</label><input type="text" name="name" id="name" class="form-control" required></div>
                    <div class="mb-3"><label>Email</label><input type="email" name="email" id="email" class="form-control" required></div>
                    <div class="mb-3"><label>Password</label><input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep unchanged"></div>
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
    const upsertModal = document.getElementById('upsertModal');
    upsertModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const action = button.getAttribute('data-action');

        const modalTitle = document.getElementById('modalTitle');
        const modalHeader = document.getElementById('modalHeader');
        const salespersonId = document.getElementById('salesperson_id');
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const actionButton = document.getElementById('action_button');

        if (action === 'add') {
            modalTitle.textContent = 'Add New Salesperson';
            modalHeader.className = 'modal-header bg-success text-white';
            actionButton.textContent = 'Add Salesperson';
            actionButton.name = 'add_salesperson';
            actionButton.className = 'btn btn-success';

            salespersonId.value = '';
            nameInput.value = '';
            emailInput.value = '';
            passwordInput.value = '';
        } else if (action === 'edit') {
            modalTitle.textContent = 'Edit Salesperson';
            modalHeader.className = 'modal-header bg-primary text-white';
            actionButton.textContent = 'Update Salesperson';
            actionButton.name = 'edit_salesperson';
            actionButton.className = 'btn btn-primary';

            salespersonId.value = button.getAttribute('data-id');
            nameInput.value = button.getAttribute('data-name');
            emailInput.value = button.getAttribute('data-email');
            passwordInput.value = '';
        }
    });
</script>
</body>
</html>

<?php 
mysqli_close($conn);
?>
