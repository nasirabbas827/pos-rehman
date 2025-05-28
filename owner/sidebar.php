<nav class="d-flex flex-column flex-shrink-0 p-3 bg-dark text-white" style="width: 280px; height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000;">
    <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
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
<!-- Offcanvas for mobile -->
<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel"><i class="fas fa-scale-balanced me-2"></i>Karobar.com</h5>
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
<style>
    body {
        margin-left: 280px; /* Space for fixed sidebar */
        background-color: #e6effc;
        font-family: Arial, sans-serif;
    }

    .nav-link {
        font-size: clamp(0.9rem, 2.5vw, 1rem);
        transition: background-color 0.3s;
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
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        body {
            margin-left: 0; /* Remove sidebar space on mobile */
        }

        nav {
            display: none; /* Hide fixed sidebar on mobile */
        }

        .navbar-toggler {
            display: block; /* Show hamburger menu */
        }
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