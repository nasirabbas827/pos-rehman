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

    /* Fixed Sidebar for desktop */
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
      overflow-y: auto; /* Enable scrolling */
    }

    .nav-link {
      font-size: clamp(0.9rem, 2.5vw, 1rem);
      transition: background-color 0.3s;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.25rem;
    }

    .nav-link:hover {
      background-color: #007bff;
    }

    /* Hamburger button for mobile */
    .navbar-toggler {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1100;
      border: 1px solid #007bff;
      background-color: #fff;
      border-radius: 5px;
      padding: 0.5rem; /* Adjusted padding for better appearance */
    }

    /* Ensure the toggler icon is visible */
    .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(0, 0, 0, 0.75)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
      width: 1.5em;
      height: 1.5em;
    }

    /* Hide fixed sidebar on mobile */
    @media (max-width: 992px) {
      body {
        margin-left: 0 !important;
      }

      nav.sidebar {
        display: none !important;
        position: absolute !important;
        width: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
      }

      .navbar-toggler {
        display: block;
      }
    }

    /* Style offcanvas sidebar */
    .offcanvas {
      width: 280px;
      background-color: #212529;
    }

    .offcanvas .nav-link {
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.25rem;
    }

    .offcanvas .nav-link:hover {
      background-color: #007bff;
    }

    .offcanvas-body {
      overflow-y: auto; /* Enable scrolling in offcanvas */
    }

    @media (max-width: 576px) {
      .nav-link {
        font-size: 0.875rem;
      }

      .offcanvas-title {
        font-size: 1.2rem;
      }
    }
  </style>
</head>
<body>

<!-- 🔘 Hamburger Button (mobile only) -->
<button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
  <span class="navbar-toggler-icon"></span>
</button>

<!-- 🧱 Fixed Sidebar (desktop only) -->
<nav class="sidebar d-flex flex-column flex-shrink-0 p-3 bg-dark text-white">
  <a href="#" class="d-flex align-items-center mb-3 text-white text-decoration-none">
    <i class="fas fa-scale-balanced me-2"></i>
    <span class="fs-4">Karobar.com</span>
  </a>
  <hr>
  <ul class="nav nav-pills flex-column mb-auto">
    <li class="nav-item">
      <a class="nav-link text-white" href="salesperson_dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="customers.php">
        <i class="fas fa-users me-2"></i> Customers
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="sales.php">
        <i class="fas fa-receipt me-2"></i> Sales/Orders
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="salesperson_sales.php">
        <i class="fas fa-chart-line me-2"></i> Sales History
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="logout.php">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
      </a>
    </li>
  </ul>
</nav>

<!-- 📱 Offcanvas Sidebar (mobile only) -->
<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">
      <i class="fas fa-scale-balanced me-2"></i>Karobar.com
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item">
        <a class="nav-link text-white" href="salesperson_dashboard.php">
          <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="customers.php">
          <i class="fas fa-users me-2"></i> Customers
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="sales.php">
          <i class="fas fa-receipt me-2"></i> Sales/Orders
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="salesperson_sales.php">
          <i class="fas fa-chart-line me-2"></i> Sales History
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="logout.php">
          <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
      </li>
    </ul>
  </div>
</div>

<!-- ✅ Bootstrap JS (Bundle includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>