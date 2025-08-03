<?php
// This file is included in other PHP files.
// The active class logic should be handled by the parent page based on current URL.
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<style>
/* Enhanced Sidebar Design */
.sidebar-nav {
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
}

.nav-item a {
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    position: relative;
    overflow: hidden;
}

.nav-item a::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    transition: left 0.6s ease;
}

.nav-item a:hover::before {
    left: 100%;
}

.nav-item a:hover {
    background: rgba(255, 255, 255, 0.15) !important;
    transform: translateX(5px);
    color: white !important;
}

.nav-item a.active {
    background: rgba(255, 255, 255, 0.2) !important;
    border-left-color: #fbbf24;
    color: white !important;
}

.nav-item i {
    width: 20px;
    text-align: center;
    transition: transform 0.3s ease;
}



.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

.sidebar-scroll::-webkit-scrollbar {
    width: 6px;
}

.sidebar-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}
</style>

<nav class="sidebar-nav bg-primary text-white w-64 fixed h-full top-0 left-0 pt-16 shadow-lg z-40">
    <div class="sidebar-scroll overflow-y-auto h-full pb-4">
        <ul class="flex flex-col p-4 space-y-2">
            <li class="nav-item">
                <a class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-primary hover:text-white
                    <?php echo ($current_page == 'dashboard.php') ? 'active bg-primary text-white' : ''; ?>"
                   href="/dashboard.php">
                    <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-primary hover:text-white
                    <?php echo ($current_dir == 'customer') ? 'active bg-primary text-white' : ''; ?>"
                   href="/customer/list.php">
                    <i class="fas fa-users mr-3"></i> Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-primary hover:text-white
                    <?php echo ($current_dir == 'inventory') ? 'active bg-primary text-white' : ''; ?>"
                   href="/inventory/list.php">
                    <i class="fas fa-boxes mr-3"></i> Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-primary hover:text-white
                    <?php echo ($current_dir == 'supplier') ? 'active bg-primary text-white' : ''; ?>"
                   href="/supplier/list.php">
                    <i class="fas fa-truck mr-3"></i> Suppliers
                </a>
            </li>
            <li class="nav-item">
                <a class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-primary hover:text-white
                    <?php echo ($current_dir == 'order') ? 'active bg-primary text-white' : ''; ?>"
                   href="/order/list.php">
                    <i class="fas fa-file-invoice-dollar mr-3"></i> Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-primary hover:text-white" href="#">
                    <i class="fas fa-chart-bar mr-3"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-primary hover:text-white" href="#">
                    <i class="fas fa-cog mr-3"></i> Settings
                </a>
            </li>
        </ul>
    </div>
</nav>