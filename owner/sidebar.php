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
    }

    .nav-link {
      font-size: clamp(0.9rem, 2.5vw, 1rem);
      transition: background-color 0.3s;
    }

    .nav-link:hover {
      background-color: #007bff;
    }

    /* Mobile hamburger button */
    .toggle-btn {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1100;
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

      .toggle-btn {
        display: block;
      }
    }

    /* Style offcanvas sidebar */
    .offcanvas {
      width: 280px;
    }

    .offcanvas .nav-link {
      color: white;
    }

    .offcanvas .nav-link:hover {
      background-color: #007bff;
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
<button class="btn btn-primary toggle-btn" type="button"
        data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas"
        aria-controls="sidebarOffcanvas">
  <i class="fas fa-bars"></i>
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
      <a class="nav-link text-white" href="owner_dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="update_profile.php">
        <i class="fas fa-user-edit me-2"></i> Profile
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="branches.php">
        <i class="fas fa-store-alt me-2"></i> Branches
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="categories.php">
        <i class="fas fa-list-alt me-2"></i> Categories
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="managers.php">
        <i class="fas fa-user-tie me-2"></i> Managers
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="products.php">
        <i class="fas fa-boxes me-2"></i> Products
      </a>
    </li>
  </ul>
  <hr>
  <div>
    <a class="nav-link text-white" href="logout.php">
      <i class="fas fa-sign-out-alt me-2"></i> Logout
    </a>
  </div>
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
        <a class="nav-link text-white" href="owner_dashboard.php">
          <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="update_profile.php">
          <i class="fas fa-user-edit me-2"></i> Profile
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="branches.php">
          <i class="fas fa-store-alt me-2"></i> Branches
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="categories.php">
          <i class="fas fa-list-alt me-2"></i> Categories
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="managers.php">
          <i class="fas fa-user-tie me-2"></i> Managers
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-white" href="products.php">
          <i class="fas fa-boxes me-2"></i> Products
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
