<?php
// Strict error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Check authentication and redirect if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: /shopper/index.php");
    exit;
}

// Get current page information safely
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? __DIR__));

// Buffer output to prevent header issues
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Shopper - Inventory and Sales Management System">
    <meta name="author" content="Your Company">
    
    <title><?= htmlspecialchars(isset($page_title) ? $page_title . " - Shopper" : "Shopper") ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="/shopper/assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/shopper/assets/css/notifications.css">
    <link rel="stylesheet" href="/shopper/assets/css/custom.css">

    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --topbar-height: 60px;
            --transition-speed: 0.3s;
        }
        
        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
            min-height: 100vh;
            position: relative;
        }
        
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background: #2c3e50;
            color: #fff;
            transition: all var(--transition-speed);
            z-index: 1000;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        
        #sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        #sidebar.collapsed {
            min-width: var(--sidebar-collapsed-width);
            max-width: var(--sidebar-collapsed-width);
        }
        
        #sidebar .sidebar-header {
            padding: 15px;
            background: #1a2634;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        #sidebar .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            white-space: nowrap;
            font-weight: 600;
        }
        
        #sidebar.collapsed .sidebar-text,
        #sidebar.collapsed .fa-chevron-down {
            display: none;
        }
        
        #sidebar ul.components {
            padding: 0;
            border-bottom: 1px solid #47748b;
        }
        
        #sidebar ul li a {
            padding: 12px 15px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            transition: all var(--transition-speed);
            position: relative;
            white-space: nowrap;
        }
        
        #sidebar ul li a:hover,
        #sidebar ul li.active > a {
            background: #3498db;
            color: #fff;
        }
        
        #sidebar ul ul a {
            padding-left: 30px;
            background: #2c3e50;
        }
        
        #content {
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            transition: all var(--transition-speed);
            display: flex;
            flex-direction: column;
        }
        
        .wrapper.collapsed #content {
            width: calc(100% - var(--sidebar-collapsed-width));
            margin-left: var(--sidebar-collapsed-width);
        }
        
        .navbar {
            height: var(--topbar-height);
            padding: 0 1rem;
            background: #fff !important;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
        }
        
        #sidebar ul li a i:not(.fa-chevron-down) {
            width: 20px;
            margin-right: 10px;
            text-align: center;
            flex-shrink: 0;
        }
        
        #sidebar.collapsed ul li a i:not(.fa-chevron-down) {
            margin-right: 0;
        }
        
        #sidebar ul li a .fa-chevron-down {
            margin-left: auto;
            transition: transform var(--transition-speed);
            font-size: 0.8rem;
        }
        
        #sidebar ul li a[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }
        
        /* Mobile styles */
        @media (max-width: 992px) {
            #sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
                z-index: 1050;
            }
            
            #sidebar.show {
                margin-left: 0;
            }
            
            #sidebar.collapsed {
                margin-left: calc(-1 * var(--sidebar-collapsed-width));
            }
            
            #content {
                width: 100%;
                margin-left: 0;
            }
            
            .wrapper.collapsed #content {
                width: 100%;
                margin-left: 0;
            }
            
            .navbar {
                padding-left: 60px;
            }
        }
        
        /* DataTables Customization */
        .dataTables_wrapper .dataTables_length select {
            min-width: 60px;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            min-width: 250px;
        }
        
        /* Card Customization */
        .card {
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 1.5rem;
        }
        
        /* Mobile menu toggle button */
        #mobileMenuToggle {
            display: none;
            position: fixed;
            left: 10px;
            top: 10px;
            z-index: 1060;
            background: #3498db;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
        }
        
        @media (max-width: 992px) {
            #mobileMenuToggle {
                display: block;
            }
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body class="g-sidenav-show bg-gray-100">
    <!-- Loading Spinner -->
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <?php 
    // Display notifications if any exist
    require_once __DIR__ . "/notifications.php";
    $notifications = get_notifications();
    if (!empty($notifications)): 
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showPhpNotifications(<?= json_encode($notifications) ?>);
        });
    </script>
    <?php endif; ?>
    
    <!-- Mobile Menu Toggle Button -->
    <button id="mobileMenuToggle" class="btn btn-primary">
        <i class="fas fa-bars"></i>
    </button>

    <div class="wrapper <?php echo isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'collapsed' : ''; ?>">
        <!-- Sidebar -->
        <nav id="sidebar" class="<?php echo isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true' ? 'collapsed' : ''; ?>">
            <div class="sidebar-header">
                <h3><i class="fas fa-shopping-cart"></i> <span class="sidebar-text">Shopper</span></h3>
                <button type="button" id="sidebarCollapse" class="btn btn-link text-white">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <ul class="list-unstyled components">
                <li class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="/shopper/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                
                <li class="<?php echo $current_dir === 'products' ? 'active' : ''; ?>">
                    <a href="#productsSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $current_dir === 'products' ? 'true' : 'false'; ?>">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Products</span>
                        <i class="fas fa-chevron-down ms-auto"></i>
                    </a>
                    <ul class="collapse list-unstyled <?php echo $current_dir === 'products' ? 'show' : ''; ?>" id="productsSubmenu">
                        <li><a href="/shopper/products/index.php"><i class="fas fa-list"></i> <span class="sidebar-text">All Products</span></a></li>
                        <li><a href="/shopper/products/add.php"><i class="fas fa-plus"></i> <span class="sidebar-text">Add Product</span></a></li>
                        <li><a href="/shopper/products/import.php"><i class="fas fa-file-import"></i> <span class="sidebar-text">Import/Export</span></a></li>
                    </ul>
                </li>

                <!-- Distributors Section -->
                <li class="<?php echo $current_dir === 'distributors' ? 'active' : ''; ?>">
                    <a href="#distributorsSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $current_dir === 'distributors' ? 'true' : 'false'; ?>">
                        <i class="fas fa-truck"></i>
                        <span class="sidebar-text">Distributors</span>
                        <i class="fas fa-chevron-down ms-auto"></i>
                    </a>
                    <ul class="collapse list-unstyled <?php echo $current_dir === 'distributors' ? 'show' : ''; ?>" id="distributorsSubmenu">
                        <li><a href="/shopper/distributors/index.php"><i class="fas fa-list"></i> <span class="sidebar-text">All Distributors</span></a></li>
                        <li><a href="/shopper/distributors/add.php"><i class="fas fa-plus"></i> <span class="sidebar-text">Add Distributor</span></a></li>
                        <li><a href="/shopper/distributors/transactions.php"><i class="fas fa-exchange-alt"></i> <span class="sidebar-text">Transactions</span></a></li>
                    </ul>
                </li>

                <li class="<?php echo $current_dir === 'customers' ? 'active' : ''; ?>">
                    <a href="#customersSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $current_dir === 'customers' ? 'true' : 'false'; ?>">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Customers</span>
                        <i class="fas fa-chevron-down ms-auto"></i>
                    </a>
                    <ul class="collapse list-unstyled <?php echo $current_dir === 'customers' ? 'show' : ''; ?>" id="customersSubmenu">
                        <li><a href="/shopper/customers/index.php"><i class="fas fa-list"></i> <span class="sidebar-text">All Customers</span></a></li>
                        <li><a href="/shopper/customers/add.php"><i class="fas fa-user-plus"></i> <span class="sidebar-text">Add Customer</span></a></li>
                        <li><a href="/shopper/customers/import.php"><i class="fas fa-file-import"></i> <span class="sidebar-text">Import/Export</span></a></li>
                    </ul>
                </li>

                <li class="<?php echo $current_dir === 'sales' ? 'active' : ''; ?>">
                    <a href="#salesSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $current_dir === 'sales' ? 'true' : 'false'; ?>">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="sidebar-text">Sales</span>
                        <i class="fas fa-chevron-down ms-auto"></i>
                    </a>
                    <ul class="collapse list-unstyled <?php echo $current_dir === 'sales' ? 'show' : ''; ?>" id="salesSubmenu">
                        <li><a href="/shopper/sales/index.php"><i class="fas fa-list"></i> <span class="sidebar-text">All Sales</span></a></li>
                        <li><a href="/shopper/sales/add.php"><i class="fas fa-plus"></i> <span class="sidebar-text">Add Sale</span></a></li>
                        <li><a href="/shopper/sales/import.php"><i class="fas fa-file-import"></i> <span class="sidebar-text">Import/Export</span></a></li>
                    </ul>
                </li>

                <li class="<?php echo $current_dir === 'reports' ? 'active' : ''; ?>">
                    <a href="#reportsSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $current_dir === 'reports' ? 'true' : 'false'; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span class="sidebar-text">Reports</span>
                        <i class="fas fa-chevron-down ms-auto"></i>
                    </a>
                    <ul class="collapse list-unstyled <?php echo $current_dir === 'reports' ? 'show' : ''; ?>" id="reportsSubmenu">
                        <li><a href="/shopper/reports/customers.php"><i class="fas fa-users"></i> <span class="sidebar-text">Customers Report</span></a></li>
                        <li><a href="/shopper/reports/products.php"><i class="fas fa-box"></i> <span class="sidebar-text">Products Report</span></a></li>
                        <li><a href="/shopper/reports/sales.php"><i class="fas fa-shopping-cart"></i> <span class="sidebar-text">Sales Report</span></a></li>
                        <li><a href="/shopper/reports/inventory.php"><i class="fas fa-warehouse"></i> <span class="sidebar-text">Inventory Report</span></a></li>
                        <li><a href="/shopper/reports/distributors.php"><i class="fas fa-truck"></i> <span class="sidebar-text">Distributors Report</span></a></li>
                    </ul>
                </li>

                <li class="<?php echo $current_dir === 'analytics' ? 'active' : ''; ?>">
                    <a href="/shopper/analytics/overview.php">
                        <i class="fas fa-chart-line"></i>
                        <span class="sidebar-text">Analytics</span>
                    </a>
                </li>

                <li class="<?php echo $current_dir === 'gst' ? 'active' : ''; ?>">
                    <a href="/shopper/gst/">
                        <i class="fas fa-percentage"></i>
                        <span class="sidebar-text">GST Management</span>
                    </a>
                </li>

                <li class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <a href="/shopper/settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>

                <li>
                    <a href="/shopper/auth/logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="sidebar-text">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <h4 class="mb-0"><?php echo htmlspecialchars(ucfirst(str_replace(['.php', '-', '_'], ['', ' ', ' '], $current_page))); ?></h4>
                        <div class="d-flex align-items-center">
                            <div class="dropdown">
                                <button class="btn btn-link dropdown-toggle text-dark text-decoration-none" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION["username"] ?? 'User'); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="/shopper/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item logout-link" href="/shopper/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <div class="main-content">
                <script src="/shopper/assets/js/notifications.js"></script>
                <script>
                // Enhanced JavaScript for better functionality
                document.addEventListener('DOMContentLoaded', function() {
                    // Sidebar collapse functionality
                    const sidebar = document.getElementById('sidebar');
                    const sidebarCollapse = document.getElementById('sidebarCollapse');
                    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
                    const wrapper = document.querySelector('.wrapper');
                    
                    // Toggle sidebar collapse
                    function toggleSidebar() {
                        sidebar.classList.toggle('collapsed');
                        wrapper.classList.toggle('collapsed');
                        
                        // Set cookie to remember state
                        const isCollapsed = sidebar.classList.contains('collapsed');
                        document.cookie = `sidebar_collapsed=${isCollapsed}; path=/; max-age=${60 * 60 * 24 * 30}`; // 30 days
                    }
                    
                    // Mobile menu toggle
                    function toggleMobileMenu() {
                        sidebar.classList.toggle('show');
                    }
                    
                    // Initialize sidebar state from cookie
                    const sidebarCookie = document.cookie.split('; ').find(row => row.startsWith('sidebar_collapsed='));
                    if (sidebarCookie && sidebarCookie.split('=')[1] === 'true') {
                        toggleSidebar();
                    }
                    
                    // Event listeners
                    sidebarCollapse.addEventListener('click', toggleSidebar);
                    mobileMenuToggle.addEventListener('click', toggleMobileMenu);
                    
                    // Show loading spinner on navigation
                    const navLinks = document.querySelectorAll('a:not([href^="#"]):not([target="_blank"])');
                    navLinks.forEach(link => {
                        link.addEventListener('click', function(e) {
                            // Don't show spinner for logout links
                            if (!this.classList.contains('logout-link')) {
                                document.querySelector('.loading-spinner').style.display = 'flex';
                            }
                        });
                    });
                    
                    // Close mobile menu when clicking outside
                    document.addEventListener('click', function(e) {
                        if (window.innerWidth <= 992 && !sidebar.contains(e.target) && e.target !== mobileMenuToggle) {
                            sidebar.classList.remove('show');
                        }
                    });
                    
                    // Responsive adjustments
                    function handleResize() {
                        if (window.innerWidth > 992) {
                            sidebar.classList.remove('show');
                        }
                    }
                    
                    window.addEventListener('resize', handleResize);
                    
                    // Initialize tooltips
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                });
                </script>