<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Karobar Dashboard</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet" />

  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #e6effc;
      margin-left: 280px;
    }

    nav.sidebar {
      width: 280px;
      height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      background-color: #212529;
      color: white;
      padding: 1rem;
      z-index: 1000;
      overflow-y: auto;
    }

    .nav-link {
      font-size: 0.95rem;
      transition: background-color 0.3s;
      color: white;
      padding: 0.55rem 1rem;
      border-radius: 0.25rem;
    }

    .nav-link:hover,
    .nav-link.active {
      background-color: #0d6efd;
    }

    .toggle-btn {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1100;
    }

    @media (max-width: 992px) {
      body {
        margin-left: 0 !important;
      }

      nav.sidebar {
        display: none !important;
      }

      .toggle-btn {
        display: block;
      }
    }

    .offcanvas {
      width: 280px;
      background-color: #212529;
    }

    .offcanvas .nav-link {
      color: white;
    }

    .offcanvas .nav-link:hover,
    .offcanvas .nav-link.active {
      background-color: #0d6efd;
    }
  </style>
</head>
<body>

<!-- Mobile Toggle -->
<button class="btn btn-primary toggle-btn"
        data-bs-toggle="offcanvas"
        data-bs-target="#sidebarOffcanvas">
  <i class="fas fa-bars"></i>
</button>

<!-- Desktop Sidebar -->
<nav class="sidebar">
  <a href="#" class="d-flex align-items-center mb-3 text-white text-decoration-none">
    <i class="fas fa-scale-balanced me-2"></i>
    <span class="fs-4">Karobar.com</span>
  </a>
  <hr>

  <?php $page = basename($_SERVER['PHP_SELF']); ?>

  <ul class="nav nav-pills flex-column gap-1">
    <li class="nav-item">
      <a class="nav-link <?= $page=='owner_dashboard.php'?'active':'' ?>" href="owner_dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='update_profile.php'?'active':'' ?>" href="update_profile.php">
        <i class="fas fa-user-edit me-2"></i> Profile
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='branches.php'?'active':'' ?>" href="branches.php">
        <i class="fas fa-store me-2"></i> Branches
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='categories.php'?'active':'' ?>" href="categories.php">
        <i class="fas fa-list me-2"></i> Categories
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='managers.php'?'active':'' ?>" href="managers.php">
        <i class="fas fa-user-tie me-2"></i> Managers
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='products.php'?'active':'' ?>" href="products.php">
        <i class="fas fa-box me-2"></i> Products
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='salespersons.php'?'active':'' ?>" href="salespersons.php">
        <i class="fas fa-user-tag me-2"></i> Sales Person
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='customers.php'?'active':'' ?>" href="customers.php">
        <i class="fas fa-users me-2"></i> Customers
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='sales_history.php'?'active':'' ?>" href="sales_history.php">
        <i class="fas fa-chart-line me-2"></i> Sales History
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= $page=='notifications.php'?'active':'' ?>" href="notifications.php">
        <i class="fas fa-bell me-2"></i> Notifications
      </a>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link text-danger" href="logout.php">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
      </a>
    </li>
  </ul>
</nav>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start text-white" id="sidebarOffcanvas">
  <div class="offcanvas-header">
    <h5><i class="fas fa-scale-balanced me-2"></i>Karobar.com</h5>
    <button class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body">
    <ul class="nav nav-pills flex-column gap-1">
      <li><a class="nav-link" href="owner_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
      <li><a class="nav-link" href="update_profile.php"><i class="fas fa-user-edit me-2"></i> Profile</a></li>
      <li><a class="nav-link" href="branches.php"><i class="fas fa-store me-2"></i> Branches</a></li>
      <li><a class="nav-link" href="categories.php"><i class="fas fa-list me-2"></i> Categories</a></li>
      <li><a class="nav-link" href="managers.php"><i class="fas fa-user-tie me-2"></i> Managers</a></li>
      <li><a class="nav-link" href="products.php"><i class="fas fa-box me-2"></i> Products</a></li>
      <li><a class="nav-link" href="salespersons.php"><i class="fas fa-user-tag me-2"></i> Sales Person</a></li>
      <li><a class="nav-link" href="customers.php"><i class="fas fa-users me-2"></i> Customers</a></li>
      <li><a class="nav-link" href="sales_history.php"><i class="fas fa-chart-line me-2"></i> Sales History</a></li>
      <li><a class="nav-link" href="notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a></li>
      <li><a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
    </ul>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
