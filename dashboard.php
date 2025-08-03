<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Basic authentication check
if (!isset($_SESSION['admin_id'])) {
    header('Location: /login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch admin info for navbar
$admin_info = ['name' => 'Guest']; // Default
if (isset($_SESSION['admin_id'])) {
    $stmt = $db->prepare("SELECT name FROM admin WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['admin_id']);
    $stmt->execute();
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Dashboard data
$total_customers = 0;
$total_inventory_items = 0;
$total_orders = 0;
$total_revenue = 0;
$total_profit = 0;
$today_profit = 0;
$monthly_profit = 0;
$total_seller_cost = 0;

try {
    $stmt = $db->query("SELECT COUNT(*) FROM customers");
    $total_customers = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM inventory_items");
    $total_inventory_items = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn();

    $stmt = $db->query("SELECT SUM(grand_total) FROM orders WHERE status = 'paid'");
    $total_revenue = $stmt->fetchColumn();
    $total_revenue = $total_revenue ? number_format($total_revenue, 2) : '0.00';

    // Calculate Total Profit
    $stmt = $db->query("SELECT SUM(oi.quantity * (ii.selling_price - ii.unit_cost)) FROM order_items oi JOIN inventory_items ii ON oi.item_id = ii.item_id JOIN orders o ON oi.order_id = o.order_id WHERE o.status = 'paid'");
    $total_profit = $stmt->fetchColumn();
    $total_profit = $total_profit ? number_format($total_profit, 2) : '0.00';

    // Calculate Today's Profit
    $stmt = $db->query("SELECT SUM(oi.quantity * (ii.selling_price - ii.unit_cost)) FROM order_items oi JOIN inventory_items ii ON oi.item_id = ii.item_id JOIN orders o ON oi.order_id = o.order_id WHERE o.status = 'paid' AND DATE(o.order_date) = CURDATE()");
    $today_profit = $stmt->fetchColumn();
    $today_profit = $today_profit ? number_format($today_profit, 2) : '0.00';

    // Calculate Monthly Profit
    $stmt = $db->query("SELECT SUM(oi.quantity * (ii.selling_price - ii.unit_cost)) FROM order_items oi JOIN inventory_items ii ON oi.item_id = ii.item_id JOIN orders o ON oi.order_id = o.order_id WHERE o.status = 'paid' AND MONTH(o.order_date) = MONTH(CURDATE()) AND YEAR(o.order_date) = YEAR(CURDATE())");
    $monthly_profit = $stmt->fetchColumn();
    $monthly_profit = $monthly_profit ? number_format($monthly_profit, 2) : '0.00';

    // Calculate Total Seller Cost (Cost of Goods Sold for paid orders)
    $stmt = $db->query("SELECT SUM(oi.quantity * ii.unit_cost) FROM order_items oi JOIN inventory_items ii ON oi.item_id = ii.item_id JOIN orders o ON oi.order_id = o.order_id WHERE o.status = 'paid'");
    $total_seller_cost = $stmt->fetchColumn();
    $total_seller_cost = $total_seller_cost ? number_format($total_seller_cost, 2) : '0.00';

} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    // Handle error gracefully, e.g., display 0 or an error message
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Removed Font Awesome, using inline SVGs for Heroicons -->
    <style>
        /* Custom styles for layout adjustments */
        body {
            font-family: 'Inter', sans-serif;
        }
        main {
            margin-left: 16rem; /* Equivalent to w-64 for sidebar */
            margin-top: 4rem; /* Equivalent to navbar height */
            padding: 1.5rem; /* Equivalent to p-6 */
        }
        @media (max-width: 768px) {
            main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/component/navbar.php'; ?>
    <div class="flex">
        <?php include __DIR__ . '/component/sidebar.php'; ?>
        <main class="flex-1">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Dashboard</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1: Total Customers -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Customers</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_customers; ?></p>
                    </div>
                    <div class="text-blue-500 text-4xl">
                        <!-- Heroicon: UsersIcon -->
                      <img src="assets/icons/cusotmer.png" class="h-10 w-10">
                    </div>
                </div>

                <!-- Card 2: Total Inventory Items -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Inventory Items</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_inventory_items; ?></p>
                    </div>
                    <div class="text-green-500 text-4xl">
                        <!-- Heroicon: CubeTransparentIcon -->
                                           <img src="assets/icons/item.png" class="h-10 w-10">

                    </div>
                </div>

                <!-- Card 3: Total Orders -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Orders</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_orders; ?></p>
                    </div>
                    <div class="text-yellow-500 text-4xl">
                        <!-- Heroicon: DocumentTextIcon -->
                                           <img src="assets/icons/bill.png" class="h-10 w-10">

                    </div>
                </div>

                <!-- Card 4: Total Revenue -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Revenue (Paid)</p>
                        <p class="text-3xl font-bold text-gray-900">Rs<?php echo $total_revenue; ?></p>
                    </div>
                    <div class="text-purple-500 text-4xl">
                        <!-- Heroicon: BanknotesIcon -->
                        <img src="assets/icons/paid.png" class="h-10 w-10">

                    </div>
                </div>

                <!-- Card 5: Total Profit -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Profit</p>
                        <p class="text-3xl font-bold text-gray-900">Rs<?php echo $total_profit; ?></p>
                    </div>
                    <div class="text-indigo-500 text-4xl">
                        <!-- Heroicon: ChartBarIcon -->
                                              <img src="assets/icons/profit.png" class="h-10 w-10">

                    </div>
                </div>

                <!-- Card 6: Today's Profit -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Today's Profit</p>
                        <p class="text-3xl font-bold text-gray-900">Rs<?php echo $today_profit; ?></p>
                    </div>
                    <div class="text-orange-500 text-4xl">
                        <!-- Heroicon: CalendarDaysIcon -->
                                                                    <img src="assets/icons/profit.png" class="h-10 w-10">

                    </div>
                </div>

                <!-- Card 7: Monthly Profit -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Monthly Profit</p>
                        <p class="text-3xl font-bold text-gray-900">Rs<?php echo $monthly_profit; ?></p>
                    </div>
                    <div class="text-teal-500 text-4xl">
                        <!-- Heroicon: CalendarIcon -->
                                                                  <img src="assets/icons/profit.png" class="h-10 w-10">

                    </div>
                </div>

                <!-- Card 8: Total Seller Cost -->
                <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Seller Cost</p>
                        <p class="text-3xl font-bold text-gray-900">Rs<?php echo $total_seller_cost; ?></p>
                    </div>
                    <div class="text-red-500 text-4xl">
                        <!-- Heroicon: ReceiptPercentIcon -->
                                                               <img src="assets/icons/cost.png" class="h-10 w-10">

                    </div>
                </div>
            </div>

            <!-- Add more dashboard content here -->
        </main>
    </div>
</body>
</html>
