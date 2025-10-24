<?php
session_start();
// --- AUTHENTICATION: Check for salesperson role ---
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "salesperson") {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

$salesperson_id = $_SESSION["id"];

// Fetch salesperson info along with branch_id
$sql_sp = "SELECT name, email, branch_id FROM salespersons WHERE id = ?";
$stmt_sp = mysqli_prepare($conn, $sql_sp);
mysqli_stmt_bind_param($stmt_sp, "i", $salesperson_id);
mysqli_stmt_execute($stmt_sp);
mysqli_stmt_bind_result($stmt_sp, $full_name, $email, $branch_id);
mysqli_stmt_fetch($stmt_sp);
mysqli_stmt_close($stmt_sp);

// Fetch products for salesperson's branch only
$sql_products = "SELECT p.id, p.name, p.description, p.price, p.quantity, p.image, b.name AS branch_name
                 FROM products p
                 LEFT JOIN branches b ON p.branch_id = b.id
                 WHERE p.branch_id = ?
                 ORDER BY p.created_at DESC";
$stmt_products = mysqli_prepare($conn, $sql_products);
mysqli_stmt_bind_param($stmt_products, "i", $branch_id);
mysqli_stmt_execute($stmt_products);
$result_products = mysqli_stmt_get_result($stmt_products);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Salesperson Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />
    <style>
        /* Shared Styles for Dashboard and Sidebar */
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin-left: 280px; /* Space for sidebar */
        }

        .container {
            padding: 2rem 1rem;
        }

        h2 {
            color: #333;
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            margin-bottom: 1.5rem;
        }

        h4 {
            color: #333;
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            margin-bottom: 1rem;
        }

        .table {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #f8f9fa;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .table td {
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        .table-hover tbody tr:hover {
            background-color: #f1f6ff;
        }

        .product-image {
            max-width: 100px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 4px;
        }

        .no-image {
            color: #666;
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            body {
                margin-left: 0; /* Remove sidebar space on mobile */
            }
        }

        @media (max-width: 576px) {
            h2 {
                font-size: 1.4rem;
            }

            h4 {
                font-size: 1.2rem;
            }

            .table th, .table td {
                font-size: 0.8rem;
            }

            .product-image {
                max-width: 60px;
                max-height: 50px;
            }
        }
    </style>
</head>
<body>
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <?php include('salesperson_sidebar.php'); ?>

    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-handshake me-2"></i>Welcome, <?php echo htmlspecialchars($full_name); ?></h2>

        <h4>Products in Your Branch (Branch ID: <?php echo (int)$branch_id; ?>)</h4>
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                    <th><i class="fas fa-image me-1"></i>Image</th>
                    <th><i class="fas fa-box me-1"></i>Name</th>
                    <th><i class="fas fa-file-alt me-1"></i>Description</th>
                    <th><i class="fas fa-rupee-sign me-1"></i> Price</th>
                    <th><i class="fas fa-cubes me-1"></i>Quantity</th>
                    <th><i class="fas fa-store-alt me-1"></i>Branch</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_products && mysqli_num_rows($result_products) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result_products)): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <?php if ($row['image']): ?>
                                    <img src="../owner/images/<?php echo htmlspecialchars($row['image']); ?>" alt="Product Image" class="product-image" />
                                <?php else: ?>
                                    <span class="no-image">No image</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo number_format($row['price'], 2); ?></td>
                            <td><?php echo (int)$row['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($row['branch_name'] ?: 'N/A'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">No products found in your branch.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
mysqli_stmt_close($stmt_products);
mysqli_close($conn); 
?>