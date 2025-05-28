<nav class="d-flex flex-column flex-shrink-0 p-3 bg-dark text-white" style="width: 280px; height: 100vh; position: fixed; top: 0; left: 0; z-index: 1000;">
    <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-scale-balanced me-2"></i>
        <span class="fs-4">Karobar.com</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a class="nav-link text-white" href="manager_dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
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
                <a class="nav-link text-white" href="manager_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
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
    .nav-link {
        font-size: clamp(0.9rem, 2.5vw, 1rem);
        transition: background-color 0.3s;
    }

    .nav-link:hover {
        background-color: #007bff;
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        nav {
            display: none; /* Hide fixed sidebar on mobile */
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