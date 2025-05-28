<!DOCTYPE html>
<html lang="en">
<head>
    <title>Karobar.com</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e6effc;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Header styling */
        .header {
            text-align: center;
            padding: 3rem 1rem;
            color: #333;
        }

        .header h1 {
            font-size: clamp(1.8rem, 5vw, 2.5rem); /* Responsive font size */
            margin-bottom: 0.625rem;
        }

        .header p {
            font-size: clamp(0.9rem, 3vw, 1.1rem);
            color: #666;
            margin-bottom: 1.5rem;
        }

        .btn-signin {
            background-color: #000;
            color: #fff;
            padding: 0.625rem 1.875rem;
            border: none;
            border-radius: 5px;
            margin: 0.5rem;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            display: inline-flex;
            align-items: center;
            transition: background-color 0.3s;
        }

        .btn-register {
            background-color: #fff;
            color: #000;
            padding: 0.625rem 1.875rem;
            border: 1px solid #000;
            border-radius: 5px;
            margin: 0.5rem;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            display: inline-flex;
            align-items: center;
            transition: background-color 0.3s;
        }

        .btn-signin:hover, .btn-register:hover {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .btn-signin i, .btn-register i {
            margin-right: 0.5rem;
        }

        /* Feature cards styling */
        .feature-cards {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding: 2.5rem 1rem;
        }

        .feature-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 320px; /* Slightly larger for better desktop display */
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px); /* Subtle hover effect */
        }

        .feature-card i {
            font-size: 2.5rem;
            color: #007bff;
            margin-bottom: 1rem;
        }

        .feature-card h5 {
            margin-bottom: 1rem;
            color: #333;
            font-size: 1.25rem;
        }

        .feature-card ul {
            list-style: none;
            padding: 0;
            text-align: left;
        }

        .feature-card ul li {
            margin-bottom: 0.625rem;
            color: #666;
            display: flex;
            align-items: center;
        }

        .feature-card ul li:before {
            content: "\f058"; /* Font Awesome check-circle icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #007bff;
            display: inline-block;
            width: 1.5em;
            margin-right: 0.5rem;
        }

        /* Footer styling */
        footer {
            background: #f8f9fa;
            padding: 2rem 1rem;
            text-align: center;
        }

        footer p {
            margin: 0;
            color: #666;
        }

        .social-icons a {
            color: #333;
            margin: 0 0.5rem;
            font-size: 1.2rem;
            transition: color 0.3s;
        }

        .social-icons a:hover {
            color: #007bff;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header {
                padding: 2rem 1rem;
            }

            .feature-cards {
                flex-direction: column;
                align-items: center;
            }

            .feature-card {
                max-width: 90%; /* Adjust width for mobile */
            }

            .btn-signin, .btn-register {
                width: 100%;
                max-width: 200px;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .header p {
                font-size: 0.875rem;
            }

            .feature-card i {
                font-size: 2rem;
            }

            .feature-card h5 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Karobar.com</h1>
        <p>Complete commerce hub management system for shop owners, managers, and administrators</p>
        <div>
            <a class="btn-register" href="register.php"><i class="fas fa-user-plus"></i> Register</a>
            <a class="btn-signin" href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
        </div>
    </div>
    <div class="feature-cards">
        <div class="feature-card">
            <i class="fas fa-user-shield"></i>
            <h5>Admin Control</h5>
            <ul>
                <li>Monitor all registered shops</li>
                <li>Approve new shop registrations</li>
                <li>View system-wide statistics</li>
            </ul>
        </div>
        <div class="feature-card">
            <i class="fas fa-store"></i>
            <h5>Shop Management</h5>
            <ul>
                <li>Add and manage branches</li>
                <li>Assign managers to locations</li>
                <li>Control product inventory</li>
            </ul>
        </div>
        <div class="feature-card">
            <i class="fas fa-warehouse"></i>
            <h5>Branch Operations</h5>
            <ul>
                <li>View branch inventory</li>
                <li>Monitor stock levels</li>
                <li>Track product status</li>
            </ul>
        </div>
    </div>
    <footer class="mt-5 py-3 bg-light text-center">
        <div class="social-icons">
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
        </div>
        <p class="mt-2">© 2025 Karobar.com. All rights reserved.</p>
    </footer>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>