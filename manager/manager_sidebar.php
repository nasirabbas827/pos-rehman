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

    /* Desktop Sidebar */
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
      color: white;
      padding: 0.55rem 1rem;
      border-radius: 0.35rem;
      transition: background-color 0.25s ease;
    }

    .nav-link i {
      width: 22px;
    }

    .nav-link:hover {
      background-color: #0d6efd;
    }

    /* Mobile Hamburger */
    .navbar-toggler {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1100;
      border: 1px solid #0d6efd;
      background-color: #fff;
      border-radius: 6px;
      padding: 0.45rem;
    }

    .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(0,0,0,0.75)' stroke-width='2' stroke-linecap='round' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
      width: 1.4em;
      height: 1.4em;
    }

    @media (max-width: 992px) {
      body {
        margin-left: 0 !important;
      }
      nav.sidebar {
        display: none !important;
      }
      .navbar-toggler {
        display: block;
      }
    }

    /* Offcanvas */
    .offcanvas {
      width: 280px;
      background-color: #212529;
    }

    .offcanvas .nav-link {
      color: white;
    }

    .offcanvas .nav-link:hover {
      background-color: #0d6efd;
    }
  </style>
</head>
<body>

<!-- Mobile Toggle -->
<button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
  <span class="navbar-toggler-icon"></span>
</button>

<!-- Desktop Sidebar -->
<nav class="sidebar d-flex flex-column">
  <a href="manager_dashboard.php" class="d-flex align-items-center mb-3 text-white text-decoration-none">
    <i class="fas fa-store me-2"></i>
    <span class="fs-5 fw-semibold">Karobar Manager</span>
  </a>
  <hr>

  <ul class="nav nav-pills flex-column mb-auto gap-1">

    <li class="nav-item">
      <a class="nav-link" href="manager_dashboard.php">
        <i class="fas fa-gauge-high"></i> Dashboard
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link" href="products.php">
        <i class="fas fa-boxes-stacked"></i> Products
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link" href="salespersons.php">
        <i class="fas fa-user-tie"></i> Sales Persons
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link" href="customers.php">
        <i class="fas fa-users"></i> Customers
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link" href="sales_history.php">
        <i class="fas fa-receipt"></i> Sales History
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="purchases.php">
        <i class="fas fa-receipt"></i> Purchase
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="purchase_history.php">
        <i class="fas fa-receipt"></i> Purchase History
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link" href="notifications.php">
        <i class="fas fa-bell"></i> Notifications
      </a>
    </li>

    <hr class="text-secondary">

    <li class="nav-item">
      <a class="nav-link text-danger" href="logout.php">
        <i class="fas fa-right-from-bracket"></i> Logout
      </a>
    </li>

  </ul>
</nav>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start text-white" tabindex="-1" id="sidebarOffcanvas">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">
      <i class="fas fa-store me-2"></i>Karobar Manager
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body">
    <ul class="nav nav-pills flex-column gap-1">

      <li class="nav-item">
        <a class="nav-link" href="manager_dashboard.php">
          <i class="fas fa-gauge-high"></i> Dashboard
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="products.php">
          <i class="fas fa-boxes-stacked"></i> Products
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="salespersons.php">
          <i class="fas fa-user-tie"></i> Sales Persons
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="customers.php">
          <i class="fas fa-users"></i> Customers
        </a>
      </li>

      <li class="nav-item">
      <a class="nav-link" href="sales_history.php">
        <i class="fas fa-receipt"></i> Sales History
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="purchases.php">
        <i class="fas fa-receipt"></i> Purchase
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="purchase_history.php">
        <i class="fas fa-receipt"></i> Purchase History
      </a>
    </li>

      <li class="nav-item">
        <a class="nav-link" href="notifications.php">
          <i class="fas fa-bell"></i> Notifications
        </a>
      </li>

      <hr class="text-secondary">

      <li class="nav-item">
        <a class="nav-link text-danger" href="logout.php">
          <i class="fas fa-right-from-bracket"></i> Logout
        </a>
      </li>

    </ul>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
